<?php
/**
 * User + student-feed sync.
 *
 * Fetches user records from the Iterable CSV export, hashes emails into wizIds,
 * and batches upserts into the users and userfeed tables.
 */

if (!defined('ABSPATH')) {
	exit;
}

function idemailwiz_fetch_users($startDate = null, $endDate = null)
{
	// Define the base URL
	$baseUrl = 'https://api.iterable.com/api/export/data.csv';

	$onlyFields = [
		'email',
		'AccountNumber',
		'userId',
		'signupDate',
		'PostalCode',
		'timeZone',
		'StudentArray',
		'subscribedMessageTypeIds',
		'unsubscribedChannelIds',
		'unsubscribedMessageTypeIds',
		'leadLocationID',
	];

	// Create the base array of query parameters without 'onlyFields'
	$queryParams = [
		'dataTypeName' => 'user',
		'delimiter' => ','
	];

	// Define the start and end date time for the API call
	$startDateTime = $startDate ? $startDate : date('Y-m-d', strtotime('-1 days'));
	$endDateTime = $endDate ? $endDate : date('Y-m-d', strtotime('+1 day')); // assurance against timezone weirdness

	// Add the start and end datetime to the query parameters
	$queryParams['startDateTime'] = $startDateTime;
	$queryParams['endDateTime'] = $endDateTime;

	// Build the base query string
	$queryString = http_build_query($queryParams);

	// Manually append each 'onlyFields' parameter
	foreach ($onlyFields as $field) {
		$queryString .= '&onlyFields=' . urlencode($field);
	}

	// Combine the base URL with the query string
	$url = $baseUrl . '?' . $queryString;

	try {
		$response = idemailwiz_iterable_curl_call($url);
	} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
		// Log the error with more details
		wiz_log("Error encountered for fetch users curl call to : " . $url . " - " . $e->getMessage());

		// Specific check for the "CONSECUTIVE_400_ERRORS" message
		if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
			// Specific action for this type of error
			wiz_log("More than 5 consecutive 400 errors encountered. Skipping...");
		}

		// Optionally, you can rethrow the exception or handle it differently
		// throw $e;
	}

	// Open a memory-based stream for reading and writing
	if (($handle = fopen("php://temp", "r+")) !== FALSE) {
		// Write the CSV content to the stream and rewind the pointer
		fwrite($handle, $response['response']);
		rewind($handle);

		// Parse the header line into headers
		$headers = fgetcsv($handle);

		// Prepare the headers
		$processedHeaders = array_map(function ($header) {
			return lcfirst($header);
		}, $headers);

		// Iterate over each line of the file
		while (($values = fgetcsv($handle)) !== FALSE) {
			$userData = []; // Initialize as empty array

			// Only process lines with the correct number of columns
			if (count($values) === count($processedHeaders)) {
				// Iterate over the values and headers simultaneously
				foreach ($values as $index => $value) {
					$header = $processedHeaders[$index];
					$userData[$header] = $value;
				}

				$userData = wiz_encrypt_email($userData); // returns false when invalid userData is passed

				// If there's data to add, process leadLocationID and yield the user data
				if ($userData) {
					// Process leadLocationID - convert from Iterable field name to our field name
					if (isset($userData['leadLocationID']) && !empty($userData['leadLocationID']) && $userData['leadLocationID'] !== '0') {
						// Convert to integer if it's a valid numeric value
						$userData['leadLocationId'] = is_numeric($userData['leadLocationID']) ? (int)$userData['leadLocationID'] : $userData['leadLocationID'];
					} else {
						$userData['leadLocationId'] = null;
					}
					
					// Remove the original field name to avoid confusion
					unset($userData['leadLocationID']);
					
					yield $userData;
				}
			}
		}

		// Close the file handle
		fclose($handle);
	}
}


function wiz_encrypt_email($userData)
{

	// Check if the necessary data is present
	if (isset($userData['email']) && !empty($userData['email']) && isset($userData['signupDate']) && !empty($userData['signupDate'])) {
		// Use the signup date as the salt
		$salt = $userData['signupDate'];

		// Hash the email with the signup date salt and the pepper
		// WIZ_PEPPER should be defined globally, e.g., in wp-config.php
        if (!defined('WIZ_PEPPER')) {
            wiz_log('Error: WIZ_PEPPER constant is not defined. This will prevent user email hashing.');
            // Potentially return false or throw an exception if this is critical for user sync
            // For now, we'll allow the sync to continue but wizId will not be generated for users processed in this state.
            return false; // Or handle more gracefully depending on requirements
        }
		// WIZ_PEPPER is defined globally
		$pepperedEmail = $userData['email'] . $salt . WIZ_PEPPER;
		$userData['wizId'] = hash('sha256', $pepperedEmail);

		// Remove the plain text email from the data
		unset($userData['email']);

		// Store the salt to reproduce this hash in the future
		$userData['wizSalt'] = $salt;

		return $userData;
	}

	return false;
}

// Schedule sync users on cron twice daily.
add_action('init', 'schedule_sync_users');
function schedule_sync_users()
{
	if (!wp_next_scheduled('idemailwiz_sync_users')) {
		wp_schedule_event(time(), 'twicedaily', 'idemailwiz_sync_users');
	}
}
// Add action for sync cron
add_action('idemailwiz_sync_users', 'idemailwiz_sync_users');

function idemailwiz_sync_users($startDate = null, $endDate = null)
{
    // Fetch the users
    // Also cleans data and encrypts email
    wiz_log('Fetching users from iterable...');

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_users';

    $batchSize = 1000; // Adjust the batch size as needed
    $userGenerator = idemailwiz_fetch_users($startDate, $endDate);

    // Stats for overall sync
    $syncStats = [
        'users_processed' => 0,
        'users_updated' => 0,
        'users_inserted' => 0,
        'students_processed' => 0,
        'students_updated' => 0,
        'students_skipped' => 0,
        'errors' => []
    ];

    while (true) {
        $users = [];
        $studentRecords = [];
        $records_to_insert = []; // Initialize array for new records
        $records_to_update = []; // Initialize array for updates

        // Collect a batch of users
        for ($i = 0; $i < $batchSize && $userGenerator->valid(); $i++) {
            $users[] = $userGenerator->current();
            $userGenerator->next();
        }

        if (empty($users)) {
            break; // No more users to process
        }

        $wpdb->query('START TRANSACTION');

        try {
            // Process users in current batch
            foreach ($users as $user) {
                $syncStats['users_processed']++;
                
                // Check if user exists
                $existingUser = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT wizId FROM $table_name WHERE wizId = %s LIMIT 1",
                        $user['wizId']
                    )
                );

                // Update or insert user
                if ($existingUser) {
                    $records_to_update[] = $user;
                    $syncStats['users_updated']++;
                } else {
                    $records_to_insert[] = $user;
                    $syncStats['users_inserted']++;
                }

                // Process student array for this user
                $processedStudents = process_student_array($user);
                if ($processedStudents) {
                    $studentRecords = array_merge($studentRecords, $processedStudents);
                    $syncStats['students_processed'] += count($processedStudents);
                }
            }

            // Batch process all student records collected from this batch of users
            if (!empty($studentRecords)) {
                $feedSyncStats = sync_user_feed_batch($studentRecords);
                // Merge feed sync stats into overall stats
                $syncStats['students_updated'] += $feedSyncStats['updated'];
                $syncStats['students_skipped'] += $feedSyncStats['skipped'];
                if (!empty($feedSyncStats['errors'])) {
                    $syncStats['errors'] = array_merge($syncStats['errors'], $feedSyncStats['errors']);
                }
            }

            // Process user records
            if (!empty($records_to_insert) || !empty($records_to_update)) {
                idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
            }

            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $syncStats['errors'][] = "Batch processing failed: " . $e->getMessage();
            wiz_log("Error in user sync batch: " . $e->getMessage());
        }
    }

    // Log final summary
    $summary = sprintf(
        "User sync complete: %d users processed (%d updated, %d inserted). Student feed: %d processed, %d updated, %d skipped. %d errors.",
        $syncStats['users_processed'],
        $syncStats['users_updated'],
        $syncStats['users_inserted'],
        $syncStats['students_processed'],
        $syncStats['students_updated'],
        $syncStats['students_skipped'],
        count($syncStats['errors'])
    );
    wiz_log($summary);

    if (!empty($syncStats['errors'])) {
        wiz_log("Sync errors encountered: " . implode(", ", $syncStats['errors']));
    }
}

function process_student_array($userData) {
    if (!isset($userData['studentArray']) || empty($userData['studentArray'])) {
        return null;
    }

    $studentArray = $userData['studentArray'];

    // Handle serialized data
    if (is_string($studentArray) && strpos($studentArray, 'a:') === 0) {
        $unserializedData = @unserialize($studentArray);
        if ($unserializedData !== false) {
            $studentArray = $unserializedData;
        } else {
            return null;
        }
    }

    // Handle JSON data
    if (is_string($studentArray)) {
        $decodedData = json_decode($studentArray, true);
        if ($decodedData !== null) {
            $studentArray = $decodedData;
        }
    }

    if (!is_array($studentArray)) {
        return null;
    }

    // Process leadLocationID
    $leadLocationId = null;
    if (isset($userData['leadLocationID']) && !empty($userData['leadLocationID']) && $userData['leadLocationID'] !== '0') {
        // Convert to integer if it's a valid numeric value
        $leadLocationId = is_numeric($userData['leadLocationID']) ? (int)$userData['leadLocationID'] : $userData['leadLocationID'];
    }

    $processedStudents = [];
    foreach ($studentArray as $student) {
        if (!isset($student['StudentAccountNumber'])) {
            continue;
        }

        // Convert StudentLastUpdated to MySQL datetime format if it exists
        $studentLastUpdated = null;
        if (isset($student['StudentLastUpdated'])) {
            // Try to parse the date in various formats
            $timestamp = strtotime($student['StudentLastUpdated']);
            if ($timestamp !== false) {
                $studentLastUpdated = date('Y-m-d H:i:s', $timestamp);
            }
        }

        $processedStudents[] = [
            'studentAccountNumber' => $student['StudentAccountNumber'],
            'userId' => $userData['userId'],
            'accountNumber' => $userData['accountNumber'],
            'wizId' => $userData['wizId'],
            'studentFirstName' => $student['StudentFirstName'] ?? '',
            'studentLastName' => $student['StudentLastName'] ?? '',
            'studentDOB' => $student['StudentDOB'] ?? null,
            'studentBirthDay' => $student['StudentBirthDay'] ?? null,
            'studentBirthMonth' => $student['StudentBirthMonth'] ?? null,
            'studentBirthYear' => $student['StudentBirthYear'] ?? null,
            'l10Level' => $student['L10Level'] ?? null,
            'unscheduledLessons' => $student['UnscheduledLessons'] ?? null,
            'studentGender' => $student['StudentGender'] ?? null,
            'studentLastUpdated' => $studentLastUpdated,
            'leadLocationId' => $leadLocationId,
            'last_updated' => current_time('mysql')
        ];
    }

    return $processedStudents;
}

function sync_user_feed_batch($studentRecords) {
    global $wpdb;
    $userfeed_table = $wpdb->prefix . 'idemailwiz_userfeed';
    
    $stats = [
        'updated' => 0,
        'skipped' => 0,
        'errors' => []
    ];

    if (empty($studentRecords)) {
        return $stats;
    }

    // Process in smaller sub-batches to avoid overwhelming the database
    $subBatches = array_chunk($studentRecords, 100);
    
    foreach ($subBatches as $batch) {
        // First, get existing records for comparison
        $accountNumbers = array_column($batch, 'studentAccountNumber');
        $placeholders = array_fill(0, count($accountNumbers), '%s');
        $existing_records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $userfeed_table WHERE studentAccountNumber IN (" . implode(',', $placeholders) . ")",
                $accountNumbers
            ),
            ARRAY_A
        );

        // Index existing records by studentAccountNumber
        $existing_by_account = array();
        foreach ($existing_records as $record) {
            $existing_by_account[$record['studentAccountNumber']] = $record;
        }

        $values = [];
        $placeholders = [];
        $update_fields = [];
        $records_to_update = [];
        
        foreach ($batch as $record) {
            // Check if record exists and needs update based on studentLastUpdated
            $should_update = true;
            if (isset($existing_by_account[$record['studentAccountNumber']])) {
                $existing = $existing_by_account[$record['studentAccountNumber']];
                
                // If both records have studentLastUpdated, compare them
                if (!empty($record['studentLastUpdated']) && !empty($existing['studentLastUpdated'])) {
                    $new_date = strtotime($record['studentLastUpdated']);
                    $existing_date = strtotime($existing['studentLastUpdated']);
                    
                    if ($new_date <= $existing_date) {
                        $stats['skipped']++;
                        continue;
                    }
                } else {
                    // If studentLastUpdated is not available, fall back to field comparison
                    $should_update = false;
                    foreach ($record as $field => $value) {
                        if ($field !== 'last_updated' && $existing[$field] != $value) {
                            $should_update = true;
                            break;
                        }
                    }
                    if (!$should_update) {
                        $stats['skipped']++;
                        continue;
                    }
                }
            }

            $placeholder = '(';
            $placeholder .= implode(',', array_fill(0, count($record), '%s'));
            $placeholder .= ')';
            $placeholders[] = $placeholder;
            $values = array_merge($values, array_values($record));
            
            // Prepare the ON DUPLICATE KEY UPDATE clause
            if (empty($update_fields)) {
                foreach ($record as $field => $value) {
                    if ($field !== 'studentAccountNumber') { // Don't update the primary key
                        $update_fields[] = "$field = VALUES($field)";
                    }
                }
            }
        }

        if (!empty($placeholders)) {
            $fields = array_keys($batch[0]);
            $query = "INSERT INTO $userfeed_table (" . implode(',', $fields) . ") 
                     VALUES " . implode(',', $placeholders) . "
                     ON DUPLICATE KEY UPDATE " . implode(',', $update_fields);
            
            $prepared_query = $wpdb->prepare($query, $values);
            $result = $wpdb->query($prepared_query);
            
            if ($result === false) {
                $stats['errors'][] = "Error updating batch: " . $wpdb->last_error;
            } else {
                $stats['updated'] += $wpdb->rows_affected;
            }
        }
    }
    
    return $stats;
}
