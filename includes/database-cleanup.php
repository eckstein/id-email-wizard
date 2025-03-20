<?php

function do_database_cleanups($campaignIds = [])
{
    update_null_user_ids();
    update_missing_purchase_dates();
    remove_zero_campaign_ids();
    idemailwiz_backfill_campaign_start_dates();
    update_opens_and_clicks_by_hour(get_idwiz_campaigns(['campaignIds'=>$campaignIds]));

    //idwiz_cleanup_users_database();
}

function update_opens_and_clicks_by_hour($blastCampaigns = [])
{
    wiz_log('Updating opens and clicks by hour...');
    
    if (empty($blastCampaigns)) {
        $now = new DateTime();
        // minus 3 months
        $startAt = $now->sub(new DateInterval('P30D'))->format('Y-m-d');
        $blastCampaigns = get_idwiz_campaigns(['type' => 'Blast', 'fields' => 'id', 'startAt_start' => $startAt]);
    }
    foreach ($blastCampaigns as $campaign) {
        idwiz_save_hourly_metrics($campaign['id']);
    }
    wiz_log('Updated opens and clicks by hour for ' . count($blastCampaigns) . ' campaigns.');
}

function update_null_user_ids()
{
    wiz_log('Updating null User IDs...');
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    $response = [];

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Update userId for rows with blank (or NULL) userId using a self-join
        $result = $wpdb->query("
            UPDATE {$table_name} p1
            INNER JOIN (
                SELECT MIN(id) as id, accountNumber, userId
                FROM {$table_name}
                WHERE TRIM(IFNULL(userId, '')) <> ''
                GROUP BY accountNumber
            ) p2 ON p1.accountNumber = p2.accountNumber
            SET p1.userId = p2.userId
            WHERE TRIM(IFNULL(p1.userId, '')) = ''
        ");

        // Check for the result to determine if rows were affected
        if ($result === false) {
            // Log the error
            wiz_log('No matching userIds found to update or error occurred.');
            $response = ['success' => false, 'error' => 'No matching userIds found to update or error occurred.'];
            $wpdb->query('ROLLBACK'); // Rollback if no rows were updated or error occurred
            return $response;
        }

        // Commit transaction if rows were updated
        $updatedUserIds = $wpdb->query('COMMIT');
        $response = ['success' => true, 'updated' => $result]; // $result will contain the number of rows affected
        wiz_log("$result null userIds updated.");
    } catch (Exception $e) {
        // Rollback the transaction on error
        $wpdb->query('ROLLBACK');
        // Log the error
        wiz_log('Failed to update null userIds: ' . $e->getMessage());
        $response = ['success' => false, 'error' => 'Failed to update null userIds due to database error: ' . $e->getMessage()];
        return $response;
    }

    return $response;
}



//update_missing_purchase_dates();
function update_missing_purchase_dates()
{
    wiz_log('Updating missing purchase dates...');
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Update purchaseDate based on createdAt for records where purchaseDate is NULL
        $result = $wpdb->query("
            UPDATE {$table_name}
            SET purchaseDate = DATE(createdAt)
            WHERE purchaseDate IS NULL OR purchaseDate = '0000-00-00'
        ");

        // Check for the result to determine if rows were affected
        if ($result === false) {
            // Log the error
            wiz_log("No purchases were found to update.");
        } else {
            // Log how many rows were affected
            wiz_log("Purchase dates updated for {$result} records with missing purchaseDate.");
        }

        // Commit transaction
        $wpdb->query('COMMIT');
    } catch (Exception $e) {
        // Rollback the transaction on error
        $wpdb->query('ROLLBACK');
        // Log the error
        wiz_log("Failed to update purchase dates: " . $e->getMessage());
        return false;
    }

    return true;
}

//remove_zero_campaign_ids();
function remove_zero_campaign_ids()
{
    wiz_log('Cleaning campaign IDs...');
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    // Attempt to update records with campaignId = 0
    $result = $wpdb->query($wpdb->prepare("
        UPDATE {$table_name}
        SET campaignId = NULL
        WHERE TRIM(campaignId) = %d
    ", 0));

    if ($result === false) {
        // Log the error
        wiz_log("Failed to update campaignIds: an error occurred.");
        return false;
    } else {
        // Log how many rows were affected
        wiz_log("CampaignIds set to NULL for {$result} records with campaignId = 0.");
        return true;
    }
}




//remove_apostrophes_from_column('idemailwiz_purchases', 'accountNumber');
function remove_apostrophes_from_column($table_suffix, $column_name)
{
    global $wpdb;
    $table_name = $wpdb->prefix . $table_suffix; // Construct table name with WP prefix

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Update the specified column by removing any single apostrophes
        $result = $wpdb->query($wpdb->prepare("
            UPDATE `{$table_name}`
            SET `{$column_name}` = REPLACE(`{$column_name}`, '''', '')
            WHERE `{$column_name}` LIKE '%%\'%%'
        "));

        // Check for the result to determine if rows were affected
        if ($result === false) {
            // Log the error
            error_log("Failed to remove apostrophes from {$column_name} in {$table_name}: " . $wpdb->last_error);
            $wpdb->query('ROLLBACK'); // Rollback the transaction on error
            return false;
        } else {
            // Log how many rows were affected
            error_log("Apostrophes removed from {$column_name} in {$result} rows of {$table_name}.");
            $wpdb->query('COMMIT'); // Commit the transaction
            return true;
        }
    } catch (Exception $e) {
        // Rollback the transaction on error
        $wpdb->query('ROLLBACK');
        // Log the error
        error_log("Exception occurred: " . $e->getMessage());
        return false;
    }
}

function idemailwiz_backfill_campaign_start_dates($purchases = false)
{
    global $wpdb;
    $purchases_table = $wpdb->prefix . 'idemailwiz_purchases';
    $campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
    $triggered_sends_table = $wpdb->prefix . 'idemailwiz_triggered_sends';

    // Fetch all purchases that have a campaignId and an empty or null campaignStartAt
    if (!$purchases) {
        $purchases = $wpdb->get_results("SELECT id, campaignId, userId, purchaseDate FROM $purchases_table WHERE campaignId IS NOT NULL AND (campaignStartAt IS NULL OR campaignStartAt = '') ORDER BY campaignId DESC LIMIT 1000");
    } else {
        $purchases = (array)$purchases;
    }

    // Process purchases one by one
    $countUpdates = 0;
    foreach ($purchases as $purchase) {
        try {
            // Fetch required campaign data - use prepare to avoid SQL injection
            $campaignType = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT type FROM $campaigns_table WHERE id = %d",
                    $purchase->campaignId
                )
            );
            
            // Make sure purchase date is valid before converting to timestamp
            if (empty($purchase->purchaseDate)) {
                wiz_log("Purchase {$purchase->id} has no purchase date. Skipping.");
                continue;
            }
            
            $purchaseTimestamp = strtotime($purchase->purchaseDate . ' 23:59:59 America/Los_Angeles') * 1000;
            
            // Default to null
            $campaignStartAt = null;
            
            if ($campaignType === 'Blast') {
                $campaignStartAt = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT startAt FROM $campaigns_table WHERE id = %d",
                        $purchase->campaignId
                    )
                );
            } elseif ($campaignType === 'Triggered' || $campaignType === 'FromWorkflow') {
                // Find the most recent triggered send before the purchase timestamp
                // Properly escape userId to avoid SQL injection
                $userId = $wpdb->_real_escape($purchase->userId);
                $triggeredSend = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT startAt
                        FROM $triggered_sends_table
                        WHERE campaignId = %d AND userId = %s AND startAt <= %d
                        ORDER BY startAt DESC
                        LIMIT 1",
                        $purchase->campaignId,
                        $userId,
                        $purchaseTimestamp
                    )
                );

                if ($triggeredSend !== null) {
                    $campaignStartAt = $triggeredSend->startAt;
                } else {
                    // Find the most recent triggered send for the campaign before the purchase timestamp
                    $triggeredSend = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT startAt
                            FROM $triggered_sends_table
                            WHERE campaignId = %d AND startAt <= %d
                            ORDER BY startAt DESC
                            LIMIT 1",
                            $purchase->campaignId,
                            $purchaseTimestamp
                        )
                    );

                    $campaignStartAt = $triggeredSend !== null ? $triggeredSend->startAt : null;
                }
            }

            // Update the purchase record with proper SQL escaping
            if ($campaignStartAt !== null) {
                $result = $wpdb->update(
                    $purchases_table,
                    ['campaignStartAt' => $campaignStartAt],
                    ['id' => $purchase->id],
                    ['%d'],
                    ['%s']
                );
            } else {
                // For NULL values, we need a different approach
                $result = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE $purchases_table SET campaignStartAt = NULL WHERE id = %s",
                        $purchase->id
                    )
                );
            }
            
            // Check if the update was successful and increment counter
            if ($result !== false) {
                $countUpdates += $result;
            } else {
                wiz_log("Failed to update purchase {$purchase->id}: " . $wpdb->last_error);
            }
        } catch (Exception $e) {
            wiz_log("Error processing purchase {$purchase->id}: " . $e->getMessage());
            continue; // Skip to the next purchase on error
        }
    }

    wiz_log("Purchase campaign start date backfill completed for {$countUpdates} records.");
    return "Purchase campaign start date backfill completed for {$countUpdates} records.";
}

function idwiz_cleanup_users_database($batchSize = 10000)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_users';

    wiz_log("Fetching users and cleaning up records...");

    // Fetch all users from the database
    $totalUsers = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    $batches = ceil($totalUsers / $batchSize);

    for ($batch = 0; $batch < $batches; $batch++) {
        $offset = $batch * $batchSize;

        // Fetch a batch of users from the database
        $users = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} LIMIT %d OFFSET %d", $batchSize, $offset), ARRAY_A);



        $countUpdates = 0;
        foreach ($users as $user) {
            $update = false; // Flag to check if we need to update the record

            foreach ($user as $field => $value) {
                // Change blank values to NULL
                if ($value === '') {
                    $user[$field] = NULL;
                    $update = true;
                }

                // Change string "[]" to NULL
                elseif ($value === '[]') {
                    $user[$field] = NULL;
                    $update = true;
                }

                // Check if the value is a string representation of an array
                elseif (is_string($value) && strpos($value, '[') === 0) {
                    // Attempt to decode it as JSON
                    $decoded = json_decode($value, true);

                    // If decoding is successful and the result is an array, serialize it
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $user[$field] = serialize($decoded);
                        $update = true;
                    }
                }
            }

            // Update the user record if changes were made
            if ($update) {
                $wpdb->update(
                    $table_name,
                    $user,
                    ['wizId' => $user['wizId']]
                );
                $countUpdates++;
            }
        }
        wiz_log("Cleaned up {$countUpdates} user records.");
    }
}

function updateTimestampsToMilliseconds()
{
    global $wpdb;

    $tableNames = [
        'idemailwiz_triggered_sends',
        'idemailwiz_triggered_opens',
        'idemailwiz_triggered_clicks',
        'idemailwiz_triggered_bounces',
        'idemailwiz_triggered_unsubscribes',
        'idemailwiz_triggered_complaints',
        'idemailwiz_triggered_sendskips'
    ];

    // SQL query to update startAt column
    // This query assumes that startAt is a BIGINT and multiplies values less than a certain threshold
    $threshold = 10000000000; // Adjust this threshold according to your needs
    foreach ($tableNames as $tableName) {
        $table = $wpdb->prefix . $tableName;
        wiz_log("Updating timestamps in table {$table}...");
        $sql = "UPDATE `$table` SET `startAt` = `startAt` * 1000 WHERE `startAt` < %d";

        try {
            // Execute the query
            $wpdb->query($wpdb->prepare($sql, $threshold));

            // Check for errors
            if (!empty($wpdb->last_error)) {
                wiz_log("Error updating timestamps in table {$table}: " . $wpdb->last_error);
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            wiz_log("Timestamps updated successfully in table {$table}.");
            //return "Timestamps updated successfully in table {$table}.";
        } catch (Exception $e) {
            wiz_log("Error updating timestamps in table {$table}: " . $e->getMessage());
            //return "Error: " . $e->getMessage();
        }
        sleep(2);
    }
    wiz_log("Timestamps updated successfully.");
}

function backfill_blast_engagment_data($campaignIds = [])
{
    if (empty($campaignIds)) {
        $campaignIds = array_column(get_idwiz_campaigns(['type' => 'Blast', 'fields' => 'id', 'startAt_start' => '2021-11-01']), 'id');
    }
    maybe_add_to_sync_queue($campaignIds, ['send'], '2021-11-01', null, 100);
}

function updateCourseFiscalYears()
{
    global $wpdb;

    // Get all courses
    $courses = $wpdb->get_results("SELECT id FROM wp_idemailwiz_courses");

    // Define the fiscal years we're interested in
    $firstFiscalYearStart = new DateTime('2021-10-15');
    $currentDate = new DateTime();
    $fiscalYears = [];
    $fiscalYearStart = clone $firstFiscalYearStart;

    while ($fiscalYearStart <= $currentDate) {
        $fiscalYearEnd = clone $fiscalYearStart;
        $fiscalYearEnd->modify('+1 year -1 day');
        $fiscalYears[] = [
            'start' => $fiscalYearStart->format('Y-m-d'),
            'end' => $fiscalYearEnd->format('Y-m-d'),
            'label' => $fiscalYearStart->format('Y') . '/' . $fiscalYearEnd->format('Y')
        ];
        $fiscalYearStart->modify('+1 year');
    }

    // Prepare the SQL query
    $sql = "SELECT shoppingCartItems_id as course_id, 
            GROUP_CONCAT(DISTINCT fiscal_year ORDER BY fiscal_year) as fiscal_years
            FROM (
                SELECT p.shoppingCartItems_id, 
                CASE ";

    foreach ($fiscalYears as $index => $fy) {
        $sql .= "WHEN p.purchaseDate BETWEEN '{$fy['start']}' AND '{$fy['end']}' THEN '{$fy['label']}' ";
    }

    $sql .= "END as fiscal_year
            FROM wp_idemailwiz_purchases p
            WHERE p.shoppingCartItems_id IN (" . implode(',', array_column($courses, 'id')) . ")
            ) AS subquery
            WHERE fiscal_year IS NOT NULL
            GROUP BY shoppingCartItems_id";

    $results = $wpdb->get_results($sql, ARRAY_A);

    // Prepare bulk update
    $update_queries = [];
    foreach ($results as $result) {
        $fiscal_years_array = explode(',', $result['fiscal_years']);
        $serialized_fiscal_years = serialize($fiscal_years_array);
        $update_queries[] = $wpdb->prepare(
            "UPDATE wp_idemailwiz_courses SET fiscal_years = %s WHERE id = %d",
            $serialized_fiscal_years,
            $result['course_id']
        );
    }

    // Add updates for courses with no purchases (N/A)
    $courses_with_purchases = array_column($results, 'course_id');
    foreach ($courses as $course) {
        if (!in_array($course->id, $courses_with_purchases)) {
            $update_queries[] = $wpdb->prepare(
                "UPDATE wp_idemailwiz_courses SET fiscal_years = %s WHERE id = %d",
                serialize(['N/A']),
                $course->id
            );
        }
    }

    // Execute bulk update
    if (!empty($update_queries)) {
        foreach ($update_queries as $query) {
            $wpdb->query($query);
        }
    }
}

function sync_user_feed_database() {
    global $wpdb;
    
    // Get the current batch from URL parameter
    $current_batch = isset($_GET['batch']) ? intval($_GET['batch']) : 0;
    $batch_size = 500; // Process 500 users at a time
    
    // Get total count of users for progress tracking
    $users_table = $wpdb->prefix . 'idemailwiz_users';
    $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $users_table WHERE studentArray IS NOT NULL AND studentArray != ''");
    
    if (!$total_users) {
        wiz_log('No users found to sync.');
        return;
    }
    
    // Calculate actual offset and verify we haven't exceeded total users
    $offset = $current_batch * $batch_size;
    if ($offset >= $total_users) {
        wiz_log("Sync completed - no more users to process.");
        return;
    }
    
    wiz_log("Starting user feed database sync (batch $current_batch). Total users to process: $total_users");
    
    // Get the current batch of users with a more strict query
    $users = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $users_table 
        WHERE studentArray IS NOT NULL 
        AND studentArray != '' 
        AND studentArray != 'a:0:{}'
        AND studentArray != '[]'
        ORDER BY wizId 
        LIMIT %d OFFSET %d",
        $batch_size,
        $offset
    ), ARRAY_A);
    
    if (empty($users)) {
        wiz_log("No users found in batch $current_batch. Sync completed.");
        return;
    }
    
    wiz_log("Found " . count($users) . " users in current batch");
    
    $processed_users = 0;
    $student_records = [];
    $processed_in_batch = 0;
    
    foreach ($users as $user) {
        if (empty($user['studentArray'])) {
            continue;
        }
        
        // Process the student array
        $student_array = maybe_unserialize($user['studentArray']);
        if (!is_array($student_array) || empty($student_array)) {
            wiz_log("Warning: Invalid or empty student array for user {$user['wizId']}");
            continue;
        }
        
        foreach ($student_array as $student) {
            if (empty($student['accountNumber'])) {
                continue;
            }
            
            $student_record = [
                'studentAccountNumber' => $student['accountNumber'],
                'userId' => $user['userId'],
                'accountNumber' => $user['accountNumber'],
                'wizId' => $user['wizId'],
                'studentFirstName' => $student['firstName'] ?? '',
                'studentLastName' => $student['lastName'] ?? '',
                'studentDOB' => $student['dateOfBirth'] ?? null,
                'studentBirthDay' => isset($student['dateOfBirth']) ? date('j', strtotime($student['dateOfBirth'])) : null,
                'studentBirthMonth' => isset($student['dateOfBirth']) ? date('n', strtotime($student['dateOfBirth'])) : null,
                'studentBirthYear' => isset($student['dateOfBirth']) ? date('Y', strtotime($student['dateOfBirth'])) : null,
                'l10Level' => $student['l10Level'] ?? null,
                'unscheduledLessons' => $student['unscheduledLessons'] ?? null,
                'studentGender' => $student['gender'] ?? '',
                'studentLastUpdated' => current_time('mysql')
            ];
            
            $student_records[] = $student_record;
            $processed_users++;
            $processed_in_batch++;
            
            // Process in smaller sub-batches of 100 to avoid memory issues
            if (count($student_records) >= 100) {
                $result = sync_user_feed_batch($student_records);
                if ($result === false) {
                    wiz_log("Error: Failed to sync batch of student records");
                }
                $student_records = [];
                wiz_log("Processed {$processed_in_batch} records in current batch...");
            }
        }
    }
    
    // Process any remaining records
    if (!empty($student_records)) {
        $result = sync_user_feed_batch($student_records);
        if ($result === false) {
            wiz_log("Error: Failed to sync final batch of student records");
        }
    }
    
    $progress_percentage = round(($offset + $batch_size) / $total_users * 100, 2);
    wiz_log("Completed batch $current_batch: Processed $processed_users users ($progress_percentage% complete)");
    
    // Only continue if we processed some users in this batch and haven't reached the end
    if ($processed_users > 0 && ($offset + $batch_size) < $total_users) {
        $next_batch = $current_batch + 1;
        $redirect_url = add_query_arg([
            'db-cleanup' => 'sync-user-feed',
            'batch' => $next_batch
        ], remove_query_arg('batch'));
        
        wiz_log("Moving to next batch ($next_batch)...");
        
        // Use JavaScript to redirect after a short delay to allow the log to be displayed
        echo '<script>
            setTimeout(function() {
                window.location.href = "' . esc_url_raw($redirect_url) . '";
            }, 1000);
        </script>';
        return;
    }
    
    wiz_log("User feed database sync completed. Total users processed in all batches: " . ($offset + $processed_users));
}