<?php
// Include WordPress' database functions
global $wpdb;


function idemailwiz_update_insert_api_data( $items, $operation, $table_name ) {
	global $wpdb;
	$result = [ 'success' => [], 'errors' => [] ];


	$id_field = 'id'; // Default ID field
	$name_field = 'name'; // Default name field

	// Determine the ID and name fields based on the table name
	if ( $table_name == $wpdb->prefix . 'idemailwiz_templates' || $table_name == $wpdb->prefix . 'idemailwiz_experiments' ) {
		$id_field = 'templateId';
	}

	// If this is a user sync
	if ( $table_name == $wpdb->prefix . 'idemailwiz_users' ) {
		$id_field = 'wizId';
	}




	foreach ( $items as $key => $item ) {
		// Add 'name' to the metrics array
		if ( $table_name == $wpdb->prefix . 'idemailwiz_metrics' ) {
			$metricCampaign = get_idwiz_campaign( $item['id'] );
			$metricName = $metricCampaign['name'];
		}

		// If this is a purchase sync, we do some database cleanup
		if ( $table_name == $wpdb->prefix . 'idemailwiz_purchases' ) {
			// Exclude purchases with campaignIds that are negatives (like -12345)
			if ( isset( $item['campaignId'] ) && $item['campaignId'] < 0 ) {
				unset( $items[ $key ] );
			}
		}

		// If this is a user sync
		if ( $table_name == $wpdb->prefix . 'idemailwiz_users' ) {
			foreach ( $item as $field => $value ) {
				// Change blank values to NULL
				if ( $value === '' ) {
					$item[ $field ] = NULL;
					continue;
				}

				// Change string "[]" to NULL
				if ( $value === '[]' ) {
					$item[ $field ] = NULL;
					continue;
				}

				// Check if the value is a string representation of an array
				if ( is_string( $value ) && strpos( $value, '[' ) === 0 ) {
					// Attempt to decode it as JSON
					$decoded = json_decode( $value, true );

					// If decoding is successful and the result is an array, serialize it
					if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
						$item[ $field ] = serialize( $decoded );
					}
				}
			}
		}

		// If this is a template record, we check if there's a wiz builder template with this template ID set as the sync-to
		if ( $table_name == $wpdb->prefix . 'idemailwiz_templates' ) {
			$incomingTemplateId = $item['templateId'];
			$wizDbTemplate = get_idwiz_template( $incomingTemplateId );
			$args = array(
				'post_type' => 'idemailwiz_template',
				'meta_key' => 'itTemplateId',
				'meta_value' => $incomingTemplateId,
				'posts_per_page' => 1
			);

			$builderTemplates = get_posts( $args );

			if ( $wizDbTemplate && ! empty( $builderTemplates ) ) {

				$sql = $wpdb->prepare(
					"UPDATE `{$wpdb->prefix}idemailwiz_templates` SET clientTemplateId = %s WHERE templateId = %d",
					$builderTemplates[0]->ID,
					$wizDbTemplate['templateId']

				);
				$wpdb->query( $sql );
			} else {
				$sql = $wpdb->prepare(
					"UPDATE `{$wpdb->prefix}idemailwiz_templates` SET clientTemplateId = NULL WHERE templateId = %d",
					$incomingTemplateId
				);
				$wpdb->query( $sql );
			}

		}



		//If this is an experiment record, we check if the corrosponding campaign in the campaign table has the experiment ID present. If not, add it.
		if ( $table_name == $wpdb->prefix . 'idemailwiz_experiments' ) {
			$experimentId = $item['experimentId'];

			$experimentCampaign = get_idwiz_campaign( $item['campaignId'] );

			//wiz_log('Found matching campaign: '.$experimentCampaign['id'].' for experiment '.$experimentId);

			// Get the existing experimentIds, if any
			$existingExperimentIds = $experimentCampaign['experimentIds'];
			$experimentIdsArray = $existingExperimentIds ? unserialize( $existingExperimentIds ) : array();

			// Add the new experimentId if it doesn't already exist in the array
			if ( ! in_array( $experimentId, $experimentIdsArray ) ) {
				$experimentIdsArray[] = $experimentId;
			}

			// Serialize the updated array
			$serializedExperimentIds = serialize( $experimentIdsArray );

			// Using prepare to safely insert values into the query
			$sql = $wpdb->prepare(
				"UPDATE `{$wpdb->prefix}idemailwiz_campaigns` SET experimentIds = %s WHERE id = %s",

				$serializedExperimentIds,
				$item['campaignId']
			);
			$wpdb->query( $sql );
		}


		// Serialize values that are arrays
		foreach ( $item as $childKey => $childValue ) {
			if ( is_array( $childValue ) ) {
				if ( empty( $childValue ) ) {
					$item[ $childKey ] = null; // Set to NULL if the array is empty
				} else {
					$serialized = serialize( $childValue );
					$item[ $childKey ] = $serialized;
				}
			}
		}

		// Convert key/header to camel case for db compatibility
		$key = str_replace( '.', '_', $key ); // Replace periods with underscores
		$words = explode( ' ', $key ); // Split the string into words
		$words = array_map( 'ucwords', $words ); // Capitalize the first letter of each word
		$camelCaseString = implode( '', $words ); // Join the words back together
		$key = lcfirst( $camelCaseString ); // Make the first letter lowercase and return

		$fields = implode( ",", array_map( function ($field) {
			return "`" . esc_sql( $field ) . "`";
		}, array_keys( $item ) ) );
		$placeholders = implode( ",", array_fill( 0, count( $item ), "%s" ) );
		$prepared_values = array_values( $item );

		if ( $operation === "insert" ) {
			$sql = "INSERT INTO `{$table_name}` ({$fields}) VALUES ({$placeholders})";
			$prepared_sql = $wpdb->prepare( $sql, $prepared_values );
		} else {
			$updates = implode( ", ", array_map( function ($field) {
				return "`$field` = %s";
			}, array_keys( $item ) ) );
			$sql = "UPDATE `{$table_name}` SET {$updates} WHERE `{$id_field}` = %s";
			$prepared_sql = $wpdb->prepare( $sql, array_merge( $prepared_values, [ $item[ $id_field ] ] ) );
		}

		// Do the insert/update
		$query_result = $wpdb->query( $prepared_sql );




		// Extracting relevant details for logging
		$item_name = isset( $item[ $name_field ] ) ? $item[ $name_field ] : ''; // Item name
		if ( $table_name == $wpdb->prefix . 'idemailwiz_metrics' ) {
			$item_name = $metricName;
		}
		$item_id = $item[ $id_field ] ?? false; // Item ID

		if ( $item_id && $query_result !== false ) {
			if ( $query_result > 0 ) {
				// Success details
				$result['success'][] = "Successfully performed {$operation} on '{$item_name}' (ID: {$item_id}).";
			} else {
				// Success, but no rows were affected
				//$result['success'][] = "Skipped '{$item_name}' (ID: {$item_id}); no updates needed.";
			}
		} else {
			// Error details
			$result['errors'][] = "Failed to perform {$operation} on '{$item_name}' (ID: {$item_id}). Database Error: {$wpdb->last_error}.";
		}

	}

	return $result;
}


function idemailwiz_fetch_campaigns( $campaignIds = null ) {
	// $campaignIds is a dummy variable to prevent errors
	// Iterable doesn't allow API calls per campaign as of November 2023
	$url = 'https://api.iterable.com/api/campaigns';
	try {
		$response = idemailwiz_iterable_curl_call( $url );
	} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
		// Log the error with more details
		wiz_log( "Error encountered for fetch campaigns curl call to : " . $url . " - " . $e->getMessage() );


		// Specific check for the "CONSECUTIVE_400_ERRORS" message
		if ( $e->getMessage() === "CONSECUTIVE_400_ERRORS" ) {
			// Specific action for this type of error
			wiz_log( "More than 5 consecutive 400 errors encountered. Skipping..." );
		}

		// Optionally, you can rethrow the exception or handle it differently
		// throw $e;
	}

	// Check if campaigns exist in the API response
	if ( ! isset( $response['response']['campaigns'] ) ) {
		return "Error: No campaigns found in the API response.";
	}

	// Return the campaigns array
	return $response['response']['campaigns'];
}

function idemailwiz_fetch_templates( $campaignIds = null ) {
	$allTemplates = [];

	$tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );

	// Initialize URLs for fetching templates of different types and mediums
	$templateAPIurls = [ 
		'blastEmails' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=Email&endDateTime='. $tomorrow,
		'triggeredEmails' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=Email&endDateTime=' . $tomorrow,
		'workflowEmails' => 'https://api.iterable.com/api/templates?templateType=Workflow&messageMedium=Email&endDateTime=' . $tomorrow,
		'blastSMS' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=SMS&endDateTime=' . $tomorrow,
		'triggeredSMS' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=SMS&endDateTime=' . $tomorrow, 
	];

	// Fetch templates from all four endpoints
	foreach ( $templateAPIurls as $typeKey => $url ) {
		try {
			$response = idemailwiz_iterable_curl_call( $url );
			if ( ! empty( $response['response']['templates'] ) ) {
				$templates = $response['response']['templates'];

				// Add templates to the allTemplates array
				foreach ( $templates as $template ) {
					$allTemplates[] = $template;
				}
			}

			usleep( 1000 );
		} catch (Exception $e) {
			wiz_log( "Error during initial API call: " . $e->getMessage() );
		}
	}

	// Fetch the detailed templates for all fetched templates
	$urlsToFetch = [];
	foreach ( $allTemplates as $template ) {
		$templateId = $template['templateId'];

		// Try email endpoint first, and if it fails, fall back to SMS
		$emailEndpoint = "https://api.iterable.com/api/templates/email/get?templateId=$templateId";
		$smsEndpoint = "https://api.iterable.com/api/templates/sms/get?templateId=$templateId";

		$urlsToFetch[] = $emailEndpoint;
		$urlsToFetch[] = $smsEndpoint;
	}

	try {
		$multiResponses = idemailwiz_iterable_curl_multi_call( $urlsToFetch );
		$fetchedTemplates = [];

		foreach ( $multiResponses as $response ) {
			if ( $response['httpCode'] == 200 ) {
				$fetchedTemplate = idemailwiz_simplify_templates_array( $response['response'] );
				$fetchedTemplates[] = $fetchedTemplate;
			}
		}

		// Replace the original templates with the fetched ones
		$allTemplates = $fetchedTemplates;

		usleep( 10000 );
	} catch (Exception $e) {
		wiz_log( "Error during multi cURL request: " . $e->getMessage() );
	}

	return $allTemplates;
}


// For the returned Template object from Iterable.
// Flattens a multi-dimensional array into a single-level array by concatenating keys into a prefix string.
// Skips values we don't want in the database. Limits 'linkParams' keys to 2 (typically utm_term and utm_content)
function idemailwiz_simplify_templates_array( $template ) {

	if ( isset( $template['metadata'] ) ) {
		// Extract the desired keys from the 'metadata' array
		if ( isset( $template['metadata']['campaignId'] ) ) {
			$result['campaignId'] = $template['metadata']['campaignId'];
		}
		if ( isset( $template['metadata']['createdAt'] ) ) {
			$result['createdAt'] = $template['metadata']['createdAt'];
		}
		if ( isset( $template['metadata']['updatedAt'] ) ) {
			$result['updatedAt'] = $template['metadata']['updatedAt'];
		}
		//if (isset($template['metadata']['clientTemplateId'])) {
		// $result['clientTemplateId'] = $template['metadata']['clientTemplateId'];
		// }
	}

	if ( isset( $template['linkParams'] ) ) {
		// Extract the desired keys from the 'linkParams' array
		if ( isset( $template['linkParams'] ) ) {
			foreach ( $template['linkParams'] as $linkParam ) {
				if ( $linkParam['key'] == 'utm_term' ) {
					$result['utmTerm'] = $linkParam['value'];
				}
				if ( $linkParam['key'] == 'utm_content' ) {
					$result['utmContent'] = $linkParam['value'];
				}
			}
		}
	}


	// Add the rest of the keys to the result
	foreach ( $template as $key => $value ) {
		if ( ! isset( $template['message'] ) ) { //if 'message' is set, it's an SMS, so this is for email
			$result['messageMedium'] = 'Email';
			// Skip the excluded keys and the keys we've already added
			$excludeKeys = array( 'clientTemplateId', 'plainText', 'cacheDataFeed', 'mergeDataFeedContext', 'utm_term', 'utm_content', 'createdAt', 'updatedAt', 'ccEmails', 'bccEmails', 'dataFeedIds' );
		} else {
			$result['messageMedium'] = 'SMS';
			$excludeKeys = array( 'messageTypeId', 'trackingDomain', 'googleAnalyticsCampaignName' );
		}
		if ( $key !== 'metadata' && $key !== 'linkParams' && ! in_array( $key, $excludeKeys ) ) {
			$result[ $key ] = $value;
		}
	}

	return $result;
}


function idemailwiz_fetch_experiments( $campaignIds = null ) {
	$today = new DateTime();
	$startFetchDate = $today->modify( '-30 days' )->format( 'Y-m-d' );

	$fetchCampArgs = array(
		'messageMedium' => 'Email',
		'type' => 'Blast',
	);

	if ( $campaignIds ) {
		$fetchCampArgs['campaignIds'] = $campaignIds;
	} else {
		$fetchCampArgs['startAt_start'] = $startFetchDate;
	}

	$allCampaigns = get_idwiz_campaigns( $fetchCampArgs );
	$campaignIds = array_column( $allCampaigns, 'id' );


	$data = [];
	$allExpMetrics = [];

	if ( ! empty( $campaignIds ) ) {
		$url = 'https://api.iterable.com/api/experiments/metrics?';
		foreach ( $campaignIds as $index => $id ) {
			$url .= ( $index === 0 ? '' : '&' ) . 'campaignId=' . $id;
		}

		try {
			$response = idemailwiz_iterable_curl_call( $url );
		} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
			// Log the error with more details
			wiz_log( "Error encountered for fetch experiments curl call to : " . $url . " - " . $e->getMessage() );


			// Specific check for the "CONSECUTIVE_400_ERRORS" message
			if ( $e->getMessage() === "CONSECUTIVE_400_ERRORS" ) {
				// Specific action for this type of error
				wiz_log( "More than 5 consecutive 400 errors encountered. Skipping..." );
			}

			// Optionally, you can rethrow the exception or handle it differently
			// throw $e;
		}

		if ( $response['response'] ) {

			// Split the CSV data into lines
			$lines = explode( "\n", $response['response'] );

			// Parse the header line into headers
			$headers = str_getcsv( $lines[0] );

			// Replace spaces and slashes with underscores, remove parentheses, remove leading/trailing whitespace, and convert to CamelCase
			$headers = array_map( function ($header) {
				$header = str_replace( [ ' ', '/' ], '_', $header );
				$header = str_replace( '(', '', $header );
				$header = str_replace( ')', '', $header );
				$header = trim( $header );
				$header = ucwords( strtolower( $header ), "_" );
				$header = str_replace( '_', '', $header );
				$header = lcfirst( $header );
				return $header;
			}, $headers );

			// Iterate over the non-header lines
			for ( $i = 1; $i < count( $lines ); $i++ ) {
				// Parse the line into values
				$values = str_getcsv( $lines[ $i ] );

				// Check if the number of headers and values matches
				if ( count( $headers ) != count( $values ) ) {
					continue;
				}

				// Combine headers and values into an associative array
				$lineArray = array_combine( $headers, $values );

				// Calculate the additional percentage metrics
				$metrics = idemailwiz_calculate_metrics( $lineArray );

				// Merge the metrics with the existing data
				$data[] = $metrics;
			}
			$allExpMetrics = $data + $data;
		}
	}

	// Return the array of metrics
	return $allExpMetrics;
}



function idemailwiz_fetch_metrics( $campaignIds = null ) {

	$today = new DateTime();
	$startFetchDate = $today->modify( '-4 weeks' )->format( 'Y-m-d' );

	$metricCampaignArgs = array(
		'fields' => array( 'id' ),
		'type' => array( 'Blast', 'Triggered' ),
	);

	if ( $campaignIds ) {
		$metricCampaignArgs['campaignIds'] = $campaignIds;
	} else {
		// If not campaigns are passed, limit the call to a timeframe
		$metricCampaignArgs['startAt_start'] = $startFetchDate;
	}

	$campaigns = get_idwiz_campaigns( $metricCampaignArgs );

	$batchCount = 0;
	$batches = array();
	$currentBatch = array();
	foreach ( $campaigns as $campaign ) {
		$currentBatch[] = $campaign['id'];
		if ( ++$batchCount % 200 == 0 ) {
			$batches[] = $currentBatch;
			$currentBatch = array();
		}
	}
	// Add any remaining campaigns that didn't fill a full batch
	if ( ! empty( $currentBatch ) ) {
		$batches[] = $currentBatch;
	}

	$allMetrics = []; // Initialize $data as an array

	foreach ( $batches as $batch ) {
		$getString = '?startDateTime=2021-11-01&campaignId=' . implode( '&campaignId=', $batch );

		$url = "https://api.iterable.com/api/campaigns/metrics" . $getString;
		try {
			$response = idemailwiz_iterable_curl_call( $url );
		} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
			// Log the error with more details
			wiz_log( "Error encountered for fetch metrics curl call to : " . $url . " - " . $e->getMessage() );


			// Specific check for the "CONSECUTIVE_400_ERRORS" message
			if ( $e->getMessage() === "CONSECUTIVE_400_ERRORS" ) {
				// Specific action for this type of error
				wiz_log( "More than 5 consecutive 400 errors encountered. Skipping..." );
			}

			// Optionally, you can rethrow the exception or handle it differently
			// throw $e;
		}

		// Split the CSV data into lines
		$lines = explode( "\n", $response['response'] );

		// Parse the header line into headers
		$headers = str_getcsv( $lines[0] );

		// Replace spaces and slashes with underscores, remove parentheses, remove leading/trailing whitespace, and convert to CamelCase
		$headers = array_map( function ($header) {
			$header = str_replace( [ ' ', '/' ], '_', $header );
			$header = str_replace( '(', '', $header );
			$header = str_replace( ')', '', $header );
			$header = trim( $header );
			$header = ucwords( strtolower( $header ), "_" );
			$header = str_replace( '_', '', $header );
			$header = lcfirst( $header );
			return $header;
		}, $headers );

		// Iterate over the non-header lines
		for ( $i = 1; $i < count( $lines ); $i++ ) {
			// Parse the line into values
			$values = str_getcsv( $lines[ $i ] );

			// Check if the number of headers and values matches
			if ( count( $headers ) != count( $values ) ) {
				continue;
			}

			// Combine headers and values into an associative array
			$lineArray = array_combine( $headers, $values );

			// Calculate the additional percentage metrics
			$metrics = idemailwiz_calculate_metrics( $lineArray );

			// Merge the metrics with the existing data
			$allMetrics[] = $metrics;
		}
		sleep( 7 ); // Respect Iterable's rate limit of 10 requests per minute
	}

	// Return the data array
	return $allMetrics;
}



function idemailwiz_fetch_users() {
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
	];

	// Create the base array of query parameters without 'onlyFields'
	$queryParams = [ 
		'dataTypeName' => 'user',
		'delimiter' => ','
	];

	// Define the start and end date time for the API call
	$startDateTime = date( 'Y-m-d', strtotime( '-1 days' ) );
	$endDateTime = date( 'Y-m-d', strtotime( '+1 day' ) ); // assurance against timezone weirdness

	// Add the start and end datetime to the query parameters
	$queryParams['startDateTime'] = $startDateTime;
	$queryParams['endDateTime'] = $endDateTime;

	// Build the base query string
	$queryString = http_build_query( $queryParams );

	// Manually append each 'onlyFields' parameter
	foreach ( $onlyFields as $field ) {
		$queryString .= '&onlyFields=' . urlencode( $field );
	}

	// Combine the base URL with the query string
	$url = $baseUrl . '?' . $queryString;

	try {
		$response = idemailwiz_iterable_curl_call( $url );
	} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
		// Log the error with more details
		wiz_log( "Error encountered for fetch users curl call to : " . $url . " - " . $e->getMessage() );


		// Specific check for the "CONSECUTIVE_400_ERRORS" message
		if ( $e->getMessage() === "CONSECUTIVE_400_ERRORS" ) {
			// Specific action for this type of error
			wiz_log( "More than 5 consecutive 400 errors encountered. Skipping..." );
		}

		// Optionally, you can rethrow the exception or handle it differently
		// throw $e;
	}

	$allUsers = []; // Initialize $allUsers as an array

	// Open a memory-based stream for reading and writing
	if ( ( $handle = fopen( "php://temp", "r+" ) ) !== FALSE ) {
		// Write the CSV content to the stream and rewind the pointer
		fwrite( $handle, $response['response'] );
		rewind( $handle );

		// Parse the header line into headers
		$headers = fgetcsv( $handle );

		// Prepare the headers
		$processedHeaders = array_map( function ($header) {
			return lcfirst( $header );
		}, $headers );

		// Iterate over each line of the file
		while ( ( $values = fgetcsv( $handle ) ) !== FALSE ) {
			$userData = []; // Initialize as empty array

			// Only process lines with the correct number of columns
			if ( count( $values ) === count( $processedHeaders ) ) {
				// Iterate over the values and headers simultaneously
				foreach ( $values as $index => $value ) {
					$header = $processedHeaders[ $index ];
					$userData[ $header ] = $value;
				}

				// Check if the necessary data is present
				if ( isset( $userData['email'] ) && ! empty( $userData['email'] ) && isset( $userData['signupDate'] ) && ! empty( $userData['signupDate'] ) ) {
					// Use the signup date as the salt
					$salt = $userData['signupDate'];

					// Hash the email with the signup date salt and the pepper
					$pepperedEmail = $userData['email'] . $salt . WIZ_PEPPER;
					$userData['wizId'] = hash( 'sha256', $pepperedEmail );

					// Store the salt to reproduce this hash in the future
					$userData['wizSalt'] = $salt;

				}

				// If there's data to add, append it to all users
				if ( ! empty( $userData ) ) {
					$allUsers[] = $userData;
				}

			}
		}


		// Close the file handle
		fclose( $handle );
	}


	// Return the data array
	return $allUsers;
}

function wiz_encrypt_email( $plaintext ) {
	$key = pack( 'H*', WIZ_ENCRYPTION_KEY ); // Convert hex to binary
	$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
	$iv = openssl_random_pseudo_bytes( $iv_length );
	$ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
	return base64_encode( $iv . $ciphertext ); // Prepend IV to ciphertext and encode
}

// Define the function for decryption
function wiz_decrypt_email( $iv_ciphertext ) {
	$key = pack( 'H*', WIZ_ENCRYPTION_KEY );
	$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
	$iv_ciphertext_dec = base64_decode( $iv_ciphertext );
	$iv_dec = substr( $iv_ciphertext_dec, 0, $iv_length );
	$ciphertext_dec = substr( $iv_ciphertext_dec, $iv_length );
	$plaintext_dec = openssl_decrypt( $ciphertext_dec, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv_dec );
	return $plaintext_dec;
}

add_action( 'wiz_process_user_sync_queue_event', 'wiz_process_user_sync_queue' );
function wiz_process_user_sync_queue( $batchSize = 50 ) {
	$wizSettings = get_option( 'idemailwiz_settings' );
	$userSync = $wizSettings['user_send_sync_toggle'];
	if ( $userSync == 'on' ) {

		// Get the queue from the transient
		$sync_queue = get_transient( 'wiz_user_send_sync_queue' );
		if ( ! $sync_queue ) {
			delete_transient( 'wiz_user_sync_queue_processing' );
			//wiz_log('User sync queue is empty.');
			return; // Queue is empty or does not exist
		}

		// Process a batch of users
		$processedCount = 0;
		foreach ( $sync_queue as $wizId => $userInfo ) {
			if ( $processedCount >= $batchSize ) {
				break;
			}

			// Decrypt the email
			$decryptedEmail = wiz_decrypt_email( $userInfo['encryptedEmail'] );

			// Prepare the API endpoint URL
			$messagesUrl = "https://api.iterable.com/api/users/getSentMessages?email=" . urlencode( $decryptedEmail ) . "&limit=1000&excludeBlastCampaigns=false";

			// Make the API call to get sent messages for the user
			$response = idemailwiz_iterable_curl_call( $messagesUrl );

			if ( $response && ! empty( $response['response']['messages'] ) ) {
				global $wpdb;
				$table_name = $wpdb->prefix . 'idemailwiz_users';

				// Serialize the messages array
				$serializedMessages = serialize( $response['response']['messages'] );

				// Attempt to update the user's record with the new data
				$result = $wpdb->update(
					$table_name,
					[ 'campaignSends' => $serializedMessages ],
					[ 'wizId' => $wizId ]
				);

				// Check for errors
				if ( $result === false ) {
					// Log the error for debugging
					error_log( "Failed to update user with wizId {$wizId}: " . $wpdb->last_error );
				}
			}

			// Remove processed user from queue
			unset( $sync_queue[ $wizId ] );
			$processedCount++;

			// Update the transient on-the-fly
			if ( count( $sync_queue ) > 0 ) {
				set_transient( 'wiz_user_send_sync_queue', $sync_queue, 12 * HOUR_IN_SECONDS );
			} else {
				// If the queue is now empty, clear transients
				delete_transient( 'wiz_user_send_sync_queue' );
				delete_transient( 'wiz_user_sync_queue_processing' );
				break; // Exit the loop as the queue is empty
			}
		}

		$countUpdate = count( $sync_queue ) + $processedCount;

		if ( count( $sync_queue ) > 0 ) {

			wiz_log( 'Processed ' . $processedCount . ' of ' . $countUpdate . ' users in queue. Getting next batch...' );
		} else {
			wiz_log( 'Processed ' . $processedCount . ' of ' . $countUpdate . ' users in queue.' );
		}

		// Update the queue transient
		if ( count( $sync_queue ) > 0 ) {
			// Re-schedule the cron job for the next batch
			if ( ! get_transient( 'wiz_user_sync_queue_processing' ) ) {
				set_transient( 'wiz_user_sync_queue_processing', true, 12 * HOUR_IN_SECONDS );
				wp_schedule_single_event( time(), 'wiz_process_user_sync_queue_event' );
			}
			wiz_log( 'Processed ' . $processedCount . ' users. Remaining in queue: ' . count( $sync_queue ) );
		} else {
			wiz_log( 'Processed all users. Queue is now empty.' );
		}
	} else {
		wiz_log( 'User sends sync was triggered, but is turned off in the settings.' );
		delete_transient( 'wiz_user_sync_queue_processing' );
		set_transient( 'wiz_user_send_sync_waiting', true, 1 * HOUR_IN_SECONDS );
	}
}


function idemailwiz_fetch_purchases( $campaignIds = [] ) {
	date_default_timezone_set( 'UTC' ); // Set timezone to UTC to match Iterable


	// Define the base URL
	$baseUrl = 'https://api.iterable.com/api/export/data.csv';

	// Define the fields to be omitted

	$omitFields = [ 
		'shoppingCartItems.orderDetailId',
		'shoppingCartItems.parentOrderDetailId',
		'shoppingCartItems.predecessorOrderDetailId',
		'shoppingCartItems.financeUnitId',
		'currencyTypeId',
		'eventName',
		'shoppingCartItems.id',
		'shoppingCartItems.imageUrl',
		'shoppingCartItems.subsidiaryId',
		'shoppingCartItems.packageType',
		'shoppingCartItems.StudentFirstName',
		'shoppingCartItems.StudentLastName',
		'email',
	];

	// Create the array of query parameters
	$queryParams = [ 
		'dataTypeName' => 'purchase',
		'delimiter' => ',',
	];


	// Define the start and end date time for the API call
	$startDateTime = date( 'Y-m-d', strtotime( '-3 days' ) ); // defaults to past 3 days, unless altered below
	$endDateTime = date( 'Y-m-d', strtotime( '+1 day' ) ); // End date is always today

	// Handle the campaign IDs if provided
	if ( ! empty( $campaignIds ) ) {
		if ( count( $campaignIds ) === 1 ) {
			// If there's only one campaign ID, add it directly
			$queryParams['campaignId'] = $campaignIds[0];
			$wizCampaign = get_idwiz_campaign( $campaignIds[0] );

			$startDateTime = date( 'Y-m-d', $wizCampaign['startAt'] / 1000 );
		} else {
			// If multiple campaign IDs, find the earliest date
			// Iterable only allows one campaign ID per call to the export API, and only max of 4 per minute, so we estimate the earliest and latest dates and use those
			$wizCampaigns = get_idwiz_campaigns( [ 'campaignIds' => $campaignIds ] );
			$earliestDate = min( array_column( $wizCampaigns, 'startAt' ) );
			$latestDate = max( array_column( $wizCampaigns, 'startAt' ) );

			$startDateTime = date( 'Y-m-d', ( $earliestDate / 1000 ) - 86400 ); // one day before campaign start date
			$endDateTime = date( 'Y-m-d', ( $latestDate / 1000 ) + MONTH_IN_SECONDS ); // one month after last campaign start date

		}
	}


	// Add the start and end datetime to the query parameters
	$queryParams['startDateTime'] = $startDateTime;
	$queryParams['endDateTime'] = $endDateTime;

	// Build the base query string
	$queryString = http_build_query( $queryParams );

	// Manually append each 'omitFields' parameter
	foreach ( $omitFields as $field ) {
		$queryString .= '&omitFields=' . urlencode( $field );
	}

	// Combine the base URL with the query string
	$url = $baseUrl . '?' . $queryString;

	try {
		$response = idemailwiz_iterable_curl_call( $url );
	} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
		// Log the error with more details
		wiz_log( "Error encountered for fetch purchases curl call to : " . $url . " - " . $e->getMessage() );


		// Specific check for the "CONSECUTIVE_400_ERRORS" message
		if ( $e->getMessage() === "CONSECUTIVE_400_ERRORS" ) {
			// Specific action for this type of error
			wiz_log( "More than 5 consecutive 400 errors encountered. Skipping..." );
		}

		// Optionally, you can rethrow the exception or handle it differently
		// throw $e;
	}


	// Prepare the omit fields to match the processed headers format (safeguard)
	$omitFields[] = 'shoppingCartItems'; //Add the main shoppingCartItems column to be omitted.
	$processedOmitFields = array_map( function ($field) {
		$fieldWithoutPeriods = str_replace( '.', '_', $field );
		// Special handling for '_id' field to be 'id'
		return strtolower( $fieldWithoutPeriods === '_id' ? 'id' : $fieldWithoutPeriods );
	}, $omitFields );

	$allPurchases = []; // Initialize $allPurchases as an array

	// Open a memory-based stream for reading and writing
	if ( ( $handle = fopen( "php://temp", "r+" ) ) !== FALSE ) {
		// Write the CSV content to the stream and rewind the pointer
		fwrite( $handle, $response['response'] );
		rewind( $handle );

		// Parse the header line into headers
		$headers = fgetcsv( $handle );

		// Prepare the headers
		$processedHeaders = array_map( function ($header) {
			$headerWithoutUnderscores = str_replace( '_', '', $header );
			return strtolower( str_replace( '.', '_', $headerWithoutUnderscores ) );
		}, $headers );

		// Iterate over each line of the file
		while ( ( $values = fgetcsv( $handle ) ) !== FALSE ) {
			$purchaseData = []; // Initialize as empty array

			// Temporary variables to store campaignId and utmMedium values
			$tempCampaignId = '';
			$tempUtmMedium = '';

			// Only process lines with the correct number of columns
			if ( count( $values ) === count( $processedHeaders ) ) {
				// Iterate over the values and headers simultaneously
				foreach ( $values as $index => $value ) {
					$header = $processedHeaders[ $index ];
					// Skip the fields that are in the omit list
					if ( in_array( $header, $processedOmitFields ) ) {
						continue;
					}

					// Clean the value
					$cleanValue = str_replace( [ '[', ']', '"' ], '', $value );

					// Store campaignId and utmMedium values temporarily
					// if ( $header === 'campaignid' ) {
					// 	$tempCampaignId = $cleanValue;
					// } elseif ( $header === 'shoppingcartitems_utmmedium' ) {
					// 	$tempUtmMedium = $cleanValue;
					// }

					// Add to the purchase data
					$purchaseData[ $header ] = $cleanValue;
				}
			}

			// Check if the purchase should be added based on campaignId and utmMedium
			// if ( ! empty( $tempCampaignId ) || strpos( strtolower( $tempUtmMedium ), 'email' ) !== false ) {
			// 	// If there's data to add, append it to all purchases
			// 	if ( ! empty( $purchaseData ) ) {
			// 		$allPurchases[] = $purchaseData;
			// 	}
			// }

			if ( ! empty( $purchaseData ) ) {
				$allPurchases[] = $purchaseData;
			}
		}

		// Close the file handle
		fclose( $handle );
	}

	// Return the data array
	return $allPurchases;

}

function idemailwiz_sync_users() {
	// Fetch the users
	$users = idemailwiz_fetch_users();

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_users';



	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];

	$sync_queue = [];
	foreach ( $users as $user ) {

		// Check if the user exists in the database
		$existingWizId = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE wizId = %s",
				$user['wizId']
			)
		);

		// Encrypt the email
		$encryptedEmail = wiz_encrypt_email( $user['email'] );

		// Prepare the data to be stored in the sync queue transient
		$transient_value = [ 
			'wizId' => $user['wizId'],
			'encryptedEmail' => $encryptedEmail,
		];

		// Use wizId as the key to ensure uniqueness
		$sync_queue[ $user['wizId'] ] = $transient_value;

		// Remove the plain text email from $user
		unset( $user['email'] );

		// Set update or insert designations
		if ( $existingWizId > 0 ) {
			// User exists, prepare to update
			$records_to_update[] = $user;
		} else {
			// User not in the database, prepare to insert
			$records_to_insert[] = $user;
		}
	}

	if ( ! empty( $sync_queue ) ) {
		// Store the entire queue in a single transient
		set_transient( 'wiz_user_send_sync_queue', $sync_queue, 12 * HOUR_IN_SECONDS );

		// With our transient filled, start the sync queue and set the processing transient
		set_transient( 'wiz_user_sync_queue_processing', true, 12 * HOUR_IN_SECONDS );
		wp_schedule_single_event( time(), 'wiz_process_user_sync_queue_event' );
	}

	// Process and log the sync operation
	return idemailwiz_process_and_log_sync( $table_name, $records_to_insert, $records_to_update );
}


function idemailwiz_sync_campaigns($passedCampaigns = null) {
	// If no campaigns are passed, fetch them from Iterable
	$campaigns = idemailwiz_fetch_campaigns($passedCampaigns);

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_campaigns';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];

	foreach ($campaigns as $campaign) {
		if (!isset($campaign['id'])) {
			wiz_log('No ID found in the fetched campaign record!');
			continue;
		}

		if ($campaign['campaignState'] == 'Aborted') {
			//Skip aborted campaigns
			continue;
		}

		// Get the latest startAt value from our DB for triggered campaigns
		if ($campaign['type'] == 'Triggered') {
			$latestStartAt = get_latest_triggered_startAt($campaign['id']);
			if ($latestStartAt !== null) {
				$campaign['startAt'] = $latestStartAt;
			} 
		}

		// Check for an existing campaign in the database
		$wizCampaign = get_idwiz_campaign($campaign['id']);

		if ($wizCampaign) {
			// If campaigns are passed, update them all
			if ($passedCampaigns) {
				$records_to_update[] = $campaign;
				continue;
			}

			// Perform deep comparison to decide if update is needed
			$fieldsDifferent = false;
			foreach ($campaign as $key => $value) {
				if (!isset($wizCampaign[$key]) || $wizCampaign[$key] != $value) {
					$fieldsDifferent = true;
					break;
				}
			}

			// Update the row if any field is different
			if ($fieldsDifferent) {
				$records_to_update[] = $campaign;
			}
		} else {
			// Campaign not in DB, add it
			$records_to_insert[] = $campaign;
		}
	}

	// Process the insert/update and log the result
	return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}





function idemailwiz_sync_templates( $passedCampaigns = null ) {
	// Fetch relevant templates
	// Note: The fetch function filters by updatedAt differences to limit results
	$templates = idemailwiz_fetch_templates( $passedCampaigns );


	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_templates';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];

	foreach ( $templates as $template ) {
		if ( ! isset( $template['templateId'] ) ) {
			wiz_log( 'No templateId found in the fetched template record!' );
			continue;
		}
		// See if the template exists in our database yet
		$wizTemplate = get_idwiz_template( $template['templateId'] );

		if ( $wizTemplate ) {
			// Template exists, we'll update it
			$records_to_update[] = $template;
		} else {
			// Template not in the database, we'll add it
			$records_to_insert[] = $template;
		}
	}

	// Process and log the sync operation
	return idemailwiz_process_and_log_sync( $table_name, $records_to_insert, $records_to_update );
}





function idemailwiz_sync_purchases( $campaignIds = null ) {
	$purchases = idemailwiz_fetch_purchases( $campaignIds );

	global $wpdb;
	$purchases_table = $wpdb->prefix . 'idemailwiz_purchases';
	$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';

	$records_to_insert = [];
	$records_to_update = [];

	foreach ( $purchases as $purchase ) {
		if ( ! isset( $purchase['id'] ) ) {
			wiz_log( 'No ID found in the fetched purchase record!' );
			continue;
		}

		// Fetch the campaign's startAt if campaignId is set
		if ( isset( $purchase['campaignId'] ) ) {
			$campaignStartAt = $wpdb->get_var( $wpdb->prepare( "SELECT startAt FROM $campaigns_table WHERE id = %d", $purchase['campaignId'] ) );
			if ( $campaignStartAt ) {
				$purchase['campaignStartAt'] = $campaignStartAt;
			}
		}

		$wizPurchase = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $purchases_table WHERE id = %s", $purchase['id'] ) );

		if ( ! $wizPurchase ) {
			$records_to_insert[] = $purchase;
		} else {
			$records_to_update[] = $purchase;
		}
	}

	return idemailwiz_process_and_log_sync( $purchases_table, $records_to_insert, $records_to_update );
}




function idemailwiz_sync_experiments( $passedCampaigns = null ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_experiments';

	// Fetch experiments
	$experiments = idemailwiz_fetch_experiments( $passedCampaigns );

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];

	foreach ( $experiments as $experiment ) {

		if ( ! isset( $experiment['templateId'] ) ) {
			if ( ! empty( $experiment ) ) {
				wiz_log( 'No templateId found in the fetched experiment record!' );
				continue;
			}
		}

		// Retrieve existing experiments from the database
		$wizExperiments = get_idwiz_experiments( [ 'campaignIds' => [ $experiment['campaignId'] ], 'templateId' => $experiment['templateId'] ] );

		if ( $wizExperiments ) {
			// Check if the experiment is already marked for update to avoid duplicates
			if ( ! in_array( $experiment, $records_to_update ) ) {
				// Mark for update
				$records_to_update[] = $experiment;
			}
		} else {
			// Mark for insert
			$records_to_insert[] = $experiment;
		}
	}

	// Process the insert/update and log them
	return idemailwiz_process_and_log_sync( $table_name, $records_to_insert, $records_to_update );
}




function idemailwiz_sync_metrics( $passedCampaigns = null ) {

	$metrics = idemailwiz_fetch_metrics( $passedCampaigns ); // Gets all metrics if none are passed

	//(print_r($metrics, true));

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_metrics';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];

	if ( $passedCampaigns ) {
		$records_to_update = $metrics;
	} else {
		foreach ( $metrics as $metric ) {
			if ( ! isset( $metric['id'] ) ) {
				wiz_log( 'No ID found in the fetched metric record!' );
				continue;
			}
			// Handle SMS campaign header mapping
			$wizCampaign = get_idwiz_campaign( $metric['id'] );
			if ( $wizCampaign && $wizCampaign['messageMedium'] == 'SMS' ) {
				$metric['uniqueEmailSends'] = $metric['uniqueSmsSent'];
				$metric['uniqueEmailsDelivered'] = $metric['uniqueSmsDelivered'];
				$metric['uniqueEmailClicks'] = $metric['uniqueSmsClicks'];
				$records_to_update[] = $metric;
			}

			// Check for existing metric
			$wizMetric = get_idwiz_metric( $metric['id'] );
			if ( $wizMetric ) {
				// Gather metric for update and de-dupe
				if ( ! in_array( $metric, $records_to_update ) ) {
					$records_to_update[] = $metric;
				}

			} else {
				// metric not in db, we'll add it
				$records_to_insert[] = $metric;
			}
		}
	}
	//error_log('Metrics Records to update: '. count($records_to_update));
	//error_log('Metrics Records to insert: '. count($records_to_insert));

	// Does our wiz_logging and returns data about the insert/update
	return idemailwiz_process_and_log_sync( $table_name, $records_to_insert, $records_to_update );
}


function idemailwiz_process_and_log_sync( $table_name, $records_to_insert = null, $records_to_update = null ) {

	// Extracting the type (e.g., 'campaign', 'template', etc.) from the table name
	$type = substr( $table_name, strrpos( $table_name, '_' ) + 1 );

	$insert_results = '';
	$update_results = '';
	$logChunk = "";
	$return = array();

	$logChunk .= ucfirst( $type ) . " sync results: ";

	if ( $records_to_insert ) {
		//$logChunk .= count($records_to_insert) . " $type" . " to insert.\n";
		$insert_results = idemailwiz_update_insert_api_data( $records_to_insert, 'insert', $table_name );
	}
	if ( $records_to_update ) {
		//$logChunk .= count($records_to_update) . " $type" . " to update.\n";
		$update_results = idemailwiz_update_insert_api_data( $records_to_update, 'update', $table_name );
	}

	$logInsertUpdate = return_insert_update_logging( $insert_results, $update_results, $table_name );

	$logChunk .= $logInsertUpdate;

	wiz_log( $logChunk );

	if ( $records_to_insert ) {
		$return['insert'] = $insert_results;
	}
	if ( $records_to_update ) {
		$return['update'] = $update_results;
	}

	return $return;
}



function return_insert_update_logging( $insert_results, $update_results, $table_name ) {
	$logResults = function ($results, $type) {
		if ( empty( $results['success'] ) && empty( $results['errors'] ) ) {
			return ""; // No operations performed
		}
		if ( ! isset( $results['success'], $results['errors'] ) ) {
			return "Invalid {$type} results structure.";
		}
		$log = '';
		$cntSuccess = count( $results['success'] );
		$cntErrors = count( $results['errors'] );

		if ( $cntSuccess ) {
			$log .= "Successful {$type} of $cntSuccess records.\n";
		}
		foreach ( $results['errors'] as $message ) {
			$log .= "Error ({$type}): $message\n";
		}
		return rtrim( $log ); // Remove trailing newline
	};

	$logInsert = isset( $insert_results ) ? $logResults( $insert_results, 'insert' ) : '';
	$logUpdate = isset( $update_results ) ? $logResults( $update_results, 'update' ) : '';
	$logSync = $logInsert . $logUpdate;

	if ( ! $logSync ) {
		$tableNameParts = explode( '_', $table_name );
		$tableNameType = end( $tableNameParts );
		return "The $tableNameType database is up to date! No inserts or updates are needed.";
	}

	return trim( $logInsert . "\n" . $logUpdate );
}




// Ajax handler for sync button
// Also creates and logs readable sync responses from response arrays
function idemailwiz_sync_non_triggered_metrics( $campaignIds = null ) {

	$syncArgs = [];
	$response = [];

	// if ($campaignIds) {
	//     $wizCampaignIds = get_idwiz_campaigns(array('campaignIds' => $campaignIds, 'fields' => 'id'));
	// }

	$sync_dbs = [ 'campaigns', 'templates', 'metrics', 'purchases', 'experiments' ];
	foreach ( $sync_dbs as $db ) {
		if ( $campaignIds ) {
			//$syncArgs = array_column($wizCampaignIds, 'id');
			$syncArgs = $campaignIds;
		}
		$function_name = 'idemailwiz_sync_' . $db;
		if ( ! function_exists( $function_name ) ) {
			return [ 'error' => 'Sync function does not exist for ' . $db ];
		}
		$result = call_user_func( $function_name, $syncArgs );

		if ( $result === false ) {
			return [ 'error' => 'Sync failed for ' . $db ];
		}
		$response[ $db ] = $result;
	}

	// Do our general database cleanups
	wiz_log( 'Doing database cleanups...' );
	do_database_cleanups();

	delete_transient( 'idemailwiz_blast_sync_in_progress' );

	return $response;
}


function idemailwiz_ajax_sync() {
	// Check for valid nonce
	if (
		! (
			check_ajax_referer( 'data-tables', 'security', false ) ||
			check_ajax_referer( 'initiatives', 'security', false ) ||
			check_ajax_referer( 'wiz-metrics', 'security', false ) ||
			check_ajax_referer( 'id-general', 'security', false )
		)
	) {
		wp_die( 'Invalid action or nonce' );
	}

	$metricTypes = isset( $_POST['metricTypes'] ) ? json_decode( stripslashes( $_POST['metricTypes'] ), true ) : [ 'blast' ];
	$campaignIds = isset( $_POST['campaignIds'] ) ? json_decode( stripslashes( $_POST['campaignIds'] ), true ) : [];

	foreach ( $metricTypes as $metricType ) {
		$response = idemailwiz_process_sync_sequence( $metricType, $campaignIds, true );
	}

	if ( $response === false ) {
		wp_send_json_error( 'There was an error in the sync process!' );
	} else {
		wp_send_json_success( $response );
	}
}
add_action( 'wp_ajax_idemailwiz_ajax_sync', 'idemailwiz_ajax_sync' );




// Calculate percentage metrics
// Takes a row of metrics data from the api call
function idemailwiz_calculate_metrics( $metrics ) {

	$campaignIdKey = 'id';

	if ( isset( $metrics['confidence'] ) ) { // Only experiments have the 'confidence' key (since Iterable gives us no other way to check)
		// If this is an experiment, we look in the campaignId column instead of the id column
		$campaignIdKey = 'campaignId';
	}

	// Get the campaign using the campaignId from the passed metrics
	if ( ! isset( $metrics[ $campaignIdKey ] ) ) {
		wiz_log( "Can't calculate metrics, no ID found in data!" );
		return false;
	}
	$wiz_campaign = get_idwiz_campaign( $metrics[ $campaignIdKey ] );

	// Campaign must already be in database for metrics to be added/updated
	if ( ! $wiz_campaign ) {
		return false;
	}

	// Check the campaign medium
	$medium = $wiz_campaign['messageMedium'];

	// Required fields for Email
	$requiredFields = [ 'uniqueEmailSends', 'uniqueEmailsDelivered', 'uniqueEmailOpens', 'uniqueEmailClicks', 'uniqueUnsubscribes', 'totalComplaints', 'uniquePurchases', 'revenue' ];

	// Update required fields if it's an SMS campaign
	if ( $medium == 'SMS' ) {
		$requiredFields = [ 'uniqueSmsSent', 'uniqueSmsDelivered', 'uniqueSmsClicks', 'uniqueUnsubscribes', 'totalComplaints', 'uniquePurchases', 'revenue' ];
	}

	// Ensure required fields are set
	foreach ( $requiredFields as $field ) {
		if ( ! isset( $metrics[ $field ] ) || $metrics[ $field ] === null ) {
			$metrics[ $field ] = 0;
		}
	}

	// Calculate common metrics
	$sendField = $medium == 'SMS' ? 'uniqueSmsSent' : 'uniqueEmailSends';
	$deliveredField = $medium == 'SMS' ? 'uniqueSmsDelivered' : 'uniqueEmailsDelivered';
	$clicksField = $medium == 'SMS' ? 'uniqueSmsClicks' : 'uniqueEmailClicks';

	$sendValue = (float) $metrics[ $sendField ];
	$deliveredValue = (float) $metrics[ $deliveredField ];
	$clicksValue = (float) $metrics[ $clicksField ];
	$unsubscribesValue = (float) $metrics['uniqueUnsubscribes'];
	$complaintsValue = (float) $metrics['totalComplaints'];
	$purchasesValue = (float) $metrics['uniquePurchases'];
	$revenueValue = (float) $metrics['revenue'];

	if ( $sendValue > 0 ) {
		$metrics['wizDeliveryRate'] = ( $deliveredValue / $sendValue ) * 100;
		$metrics['wizCtr'] = ( $clicksValue / $sendValue ) * 100;
		$metrics['wizUnsubRate'] = ( $unsubscribesValue / $sendValue ) * 100;
		$metrics['wizCompRate'] = ( $complaintsValue / $sendValue ) * 100;
		$metrics['wizCvr'] = ( $purchasesValue / $sendValue ) * 100;
	} else {
		$metrics['wizCtr'] = 0;
		$metrics['wizUnsubRate'] = 0;
		$metrics['wizCompRate'] = 0;
		$metrics['wizCvr'] = 0;
	}

	if ( $purchasesValue > 0 ) {
		$metrics['wizAov'] = ( $revenueValue / $purchasesValue );
	} else {
		$metrics['wizAov'] = 0;
	}

	// Open metrics (sms or no opens gets zero values)
	$opensValue = $medium == 'Email' ? (float) $metrics['uniqueEmailOpens'] : 0;

	if ( $opensValue && $sendValue > 0 ) {
		$metrics['wizOpenRate'] = $medium == 'Email' ? ( $opensValue / $sendValue ) * 100 : 0;
	} else {
		$metrics['wizOpenRate'] = 0;
	}

	if ( $opensValue && $opensValue > 0 ) {
		$metrics['wizCto'] = $medium == 'Email' ? ( $clicksValue / $opensValue ) * 100 : 0;
	} else {
		$metrics['wizCto'] = 0;
	}

	// Remove metrics we don't want to sync in
	unset( $metrics['uniqueSmsSentByMessage'] );

	return $metrics;
}

function get_latest_triggered_startAt( $campaignId ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'idemailwiz_triggered_sends';

	// Prepare the SQL query to prevent SQL injection
	$sql = $wpdb->prepare(
		"SELECT startAt FROM $table_name WHERE campaignId = %s ORDER BY startAt DESC LIMIT 1",
		$campaignId
	);

	// Execute the SQL query
	$result = $wpdb->get_var( $sql );

	if ( $result !== null ) {
		return (int) $result; // Convert to integer if your startAt is stored as string
	} else {
		return null; // Return null if no record is found
	}
}

function do_after_wiz_settings_update( $old, $new ) {
	// Check if the new settings array contains 'user_send_sync_toggle' set to 'on'
	if ( isset( $new['user_send_sync_toggle'] ) && $new['user_send_sync_toggle'] == 'on' ) {
		delete_transient( 'wiz_user_send_sync_waiting' );
	}
}
add_action( 'update_option_idemailwiz_settings', 'do_after_wiz_settings_update', 10, 2 );



// Check for user sync queue processing and start it if needed
add_action( 'wp_loaded', function () {
	if ( ! wp_next_scheduled( 'wiz_process_user_sync_queue_event' ) && get_transient( 'wiz_user_send_sync_queue' ) && ! get_transient( 'wiz_user_send_sync_waiting' ) ) {
		wp_schedule_single_event( time(), 'wiz_process_user_sync_queue_event' );
		//wiz_log("Processing user send sync queue...");
	}
} );

if ( ! wp_next_scheduled( 'idemailwiz_check_for_cron_sequence_start' ) ) {
	wp_schedule_event( time(), 'hourly', 'idemailwiz_check_for_cron_sequence_start' );
}
add_action( 'idemailwiz_check_for_cron_sequence_start', 'idemailwiz_check_for_cron_sequence_start', 10, 0 );


// On each page load, check if the sync should start
//add_action('wp_loaded', 'idemailwiz_check_for_cron_sequence_start');

function idemailwiz_check_for_cron_sequence_start() {
	// Clear expired transients manually before checking anything
	delete_expired_transients();

	$wizSettings = get_option( 'idemailwiz_settings' );

	$blastSync = $wizSettings['iterable_sync_toggle'] ?? 'off';
	$triggeredSync = $wizSettings['iterable_triggered_sync_toggle'] ?? 'off';


	// Check for GA sync
	// Wait a couple seconds in case the transient is being set to avoid quickly re-running
	sleep( 2 );
	$gaSyncWaiting = get_transient( 'ga_sync_waiting' );
	if ( ! $gaSyncWaiting ) {
		sync_ga_campaign_revenue_data();
		// Sync every 2 hours only
		set_transient( 'ga_sync_waiting', true, ( 120 * MINUTE_IN_SECONDS ) );

	}

	$metricTypes = [ 'send', 'open', 'click', 'unSubscribe', 'bounce', 'sendSkip', 'complaint' ];
	$triggeredSyncInProgress = false;

	// Check if we're waiting to start the next sync
	$blastSyncWaiting = get_transient( 'blast_sync_waiting' );
	$triggeredSyncWaiting = get_transient( 'triggered_sync_waiting' );


	// Check if a sync is in progress
	$blastSyncInProgress = get_transient( 'idemailwiz_blast_sync_in_progress' );

	// Start blast sync sequence if not in progress or waiting
	if ( $blastSync == 'on' ) {
		if ( ! $blastSyncWaiting ) {
			if ( ! $blastSyncInProgress ) {
				set_transient( 'idemailwiz_blast_sync_in_progress', true, ( 10 * MINUTE_IN_SECONDS ) );
				set_transient( 'blast_sync_waiting', true, ( 60 * MINUTE_IN_SECONDS ) );
				idwiz_maybe_start_wpcron_sync_sequence( [ 'blast' ] );
			} else {
				wiz_log( 'Blast sync is already in progress.' );
			}
		}
	} else {
		wiz_log( 'Blast sync was triggered, but is turned off in the settings.' );
	}


	// Start triggered sync sequence if not in progress or waiting, and no other sync is in progress
	if ( $triggeredSync == 'on' ) {
		if ( ! $triggeredSyncWaiting ) {
			if ( ! $triggeredSyncInProgress ) {
				$addTime = 0;
				foreach ( $metricTypes as $metricType ) {
					set_transient( "idemailwiz_{$metricType}_sync_in_progress", true, ( 20 * MINUTE_IN_SECONDS ) + $addTime );
					$addTime += ( 4 * MINUTE_IN_SECONDS );
				}
				set_transient( 'triggered_sync_waiting', true, ( 90 * MINUTE_IN_SECONDS ) );
				idwiz_maybe_start_wpcron_sync_sequence( $metricTypes );
			} else {
				wiz_log( 'Triggered sync is already in progress.' );
			}
		}
	} else {
		wiz_log( 'Triggered sync was triggered, but is turned off in the settings.' );
	}




}

if ( ! wp_next_scheduled( 'idemailwiz_check_for_export_sequence_start' ) ) {
	wp_schedule_event( time(), 'hourly', 'idemailwiz_check_for_export_sequence_start' );
}
add_action( 'idemailwiz_check_for_export_sequence_start', 'idemailwiz_check_for_export_sequence_start', 10, 0 );

function idemailwiz_check_for_export_sequence_start() {
	$metricTypes = [ 'send', 'open', 'click', 'unSubscribe', 'bounce', 'sendSkip', 'complaint' ];

	$exportSyncWaiting = get_transient( 'export_sync_waiting' );
	// Start export sync sequence if not in progress or waiting, and no other sync is in progress
	if ( ! $exportSyncWaiting ) {
		set_transient( 'export_sync_waiting', true, ( 120 * MINUTE_IN_SECONDS ) );

		idwiz_maybe_start_wpcron_jobs_exports();

	}
}


// If we're syncing, we kick off the sync sequence cron schedules
function idwiz_maybe_start_wpcron_sync_sequence( $metricTypes = [ 'blast', 'send', 'open', 'click', 'unSubscribe', 'bounce', 'sendSkip', 'complaint' ] ) {

	$nextStart = time();
	foreach ( $metricTypes as $metricType ) {
		// Check for existing scheduled event, and if not present, scheduled it
		if ( ! wp_next_scheduled( "idemailwiz_process_{$metricType}_sync" ) ) {
			wp_schedule_single_event( $nextStart, "idemailwiz_process_{$metricType}_sync", array( $metricType ) );
		}
		// Schedule the next metric type for a few minutes later to avoid overlapping
		$nextStart += 420;
	}

}


// Add our Sync Sequence custom actions for each sync type (important: must be outside any other function)
$metricTypes = [ 'blast', 'send', 'open', 'click', 'unSubscribe', 'bounce', 'sendSkip', 'complaint' ];
// Register actions outside of the function
foreach ( $metricTypes as $metricType ) {
	add_action( "idemailwiz_process_{$metricType}_sync", 'idemailwiz_process_sync_sequence', 10, 1 );
}


// Runs the Blast and Triggered metric sync sequences based on passed metric type
// Checks if sync is already in progress, based on transients
function idemailwiz_process_sync_sequence( $metricType, $campaignIds = null ) {
	$allowedMetricTypes = [ 'blast', 'send', 'open', 'click', 'unSubscribe', 'bounce', 'sendSkip', 'complaint' ];

	if ( ! in_array( $metricType, $allowedMetricTypes ) ) {
		wiz_log( "Invalid metric ( $metricType) type passed to sync queue processor." );
		return false;
	}



	if ( $metricType == 'blast' ) {
		// Perform the non-triggered (blast) sync
		idemailwiz_sync_non_triggered_metrics( $campaignIds );


	} else {
		// Handle the triggered sync types
		idemailwiz_sync_triggered_metric_from_transient( $metricType );
	}

	return true; // Indicate successful completion of the sync sequence
}


// If we're starting export jobs, we kick off the export cron schedules with an optional delay (in seconds) passed in
function idwiz_maybe_start_wpcron_jobs_exports( $delay = 0 ) {
	$metricTypes = [ 'send', 'open', 'click', 'bounce', 'sendSkip', 'unSubscribe', 'complaint' ];

	wiz_log( "Triggered export jobs started via auto schedule..." );
	$nextStart = time() + $delay;
	foreach ( $metricTypes as $metricType ) {
		// Check for existing scheduled event, and if not present, scheduled it
		if ( ! wp_next_scheduled( "idemailwiz_start_{$metricType}_export_jobs" ) ) {
			wp_schedule_single_event( $nextStart, "idemailwiz_start_{$metricType}_export_jobs", array( $metricType ) );
		}
		// Schedule the next metric type for a bit later later to avoid overlapping
		$nextStart += 150;
	}

}
// Add our Export Job Fetching custom action for each sync type (important: must be outside any other function)
$syncJobMetricTypes = [ 'send', 'open', 'click', 'bounce', 'sendSkip', 'unSubscribe', 'complaint' ];
// Register actions outside of the function
foreach ( $syncJobMetricTypes as $syncJobMetricType ) {
	add_action( "idemailwiz_start_{$syncJobMetricType}_export_jobs", 'idemailwiz_start_triggered_export_jobs_by_metric', 10, 1 );
}
// Starts the triggered data exports jobs after checking the transient for last updated
function idemailwiz_start_triggered_export_jobs_by_metric( $metricType ) {
	$metricTypes = [ 'send', 'open', 'click', 'bounce', 'sendSkip', 'unSubscribe', 'complaint' ];
	if ( ! in_array( $metricType, $metricTypes ) ) {
		wiz_log( "Invalid metric type recieved for job exports" );
		return false;
	}


	$startTime = microtime( true );

	$jobExports = get_transient( "idemailwiz_sync_{$metricType}_jobs" ) ?? false;
	$transientLastUpdated = $jobExports['lastUpdated'] ?? false;

	if ( ! $jobExports || ( $transientLastUpdated && ( time() - $transientLastUpdated ) > ( 2 * HOUR_IN_SECONDS ) ) ) {
		if ( get_transient( 'idemailwiz_export_' . $metricType . '_jobs_running' ) ) {
			wiz_log( "Triggered $metricType export jobs do not need updated, skipping..." );
			return false;
		}
		set_transient( 'idemailwiz_export_' . $metricType . '_jobs_running', true, 60 * 60 );
		wiz_log( "Starting triggered {$metricType}s export jobs..." );

		$transientData = idwiz_export_and_store_jobs_to_transients( $metricType );
		$retrievedJobIds = count( $transientData['jobs'] );

		if ( $transientData['jobs'] > 0 ) {
			wiz_log( "Export Job IDs stored for $retrievedJobIds campaign {$metricType}s." );
		} else {
			wiz_log( "No export jobs were stored for campaign {$metricType}s." );
		}

	}

	$endTime = microtime( true );
	$elapsedTime = $endTime - $startTime;

	// If the request took less than 1 second, sleep the remaining time
	if ( $elapsedTime < 1 ) {
		usleep( ( 1 - $elapsedTime ) * 1000000 );
	}
	delete_transient( 'idemailwiz_export_' . $metricType . '_jobs_running' );

}

// Fetches triggered data jobs from the Iterable API and saves them to transients
function idwiz_export_and_store_jobs_to_transients( $metricType, $campaignTypes = 'Triggered', $campaignIds = null, $exportStart = null, $exportEnd = null, $manual = false ) {

	$metricTypes = [ 'send', 'open', 'click', 'bounce', 'sendSkip', 'unSubscribe', 'complaint' ];
	if ( ! in_array( $metricType, $metricTypes ) ) {
		return false;
	}

	if ( ! $campaignIds || ( is_array( $campaignIds ) && empty( $campaignIds ) ) ) {
		// if no campaign IDs are passed, get all Running triggered campaigns 
		$campaigns = get_idwiz_campaigns( [ 'type' => $campaignTypes, 'campaignState' => 'Running', 'fields' => 'id', 'sortBy' => 'startAt', 'sort' => 'DESC' ] );
	} else {
		$campaigns = get_idwiz_campaigns( [ 'campaignIds' => $campaignIds, 'fields' => 'id', 'sortBy' => 'startAt', 'sort' => 'DESC' ] );
	}

	$countCampaigns = count( $campaigns );



	// Set the timezone to Pacific Time (Los Angeles)
	$timezone = new DateTimeZone('America/Los_Angeles');

	// Example for setting the start date/time
	if ( $exportStart ) {
		$exportFetchStart = new DateTimeImmutable( $exportStart, $timezone );
	} else {
		$wizSettings = get_option( 'idemailwiz_settings' );
		$triggered_sync_length = $wizSettings['triggered_sync_length'] ?? 30;
		$exportFetchStart = new DateTimeImmutable( '-' . $triggered_sync_length . ' days', $timezone );
	}

	$exportFetchEnd = false;
	if ( $exportEnd ) {
		$exportFetchEnd = new DateTimeImmutable( $exportEnd, $timezone );
	} else {
		//$exportFetchEnd = new DateTimeImmutable( 'now', $timezone );
	}


	$transientData = [ 'jobs' => [], 'lastUpdated' => '' ];
	$countRetrieved = 0;

	if ( ! $manual ) {
		wiz_log( "Exporting jobs for $countCampaigns campaign's {$metricType} records... (1-3 mins)" );
	}
	foreach ( $campaigns as $campaign ) {

		if ( ! isset( $campaign['id'] ) ) {
			continue;
		}

		$exportStartData = [];

		if ($exportFetchEnd) {
			$exportStartData['endDateTime'] = $exportFetchEnd->format( 'Y-m-d' );
		}
		$exportStartData = [ 
			"outputFormat" => "application/x-json-stream",
			"dataTypeName" => 'email' . ucfirst( $metricType ),
			"delimiter" => ",",
			"onlyFields" => "createdAt,userId,campaignId,templateId,messageId,email",
			"startDateTime" => $exportFetchStart->format( 'Y-m-d' ),
			"campaignId" => (int) $campaign['id']
		];
		try {
			$startTime = microtime( true );
			// Start the export job for all data
			$response = idemailwiz_iterable_curl_call( 'https://api.iterable.com/api/export/start', $exportStartData );
			if ( isset( $response['response']['jobId'] ) ) {
				$jobId = $response['response']['jobId'];
				$transientData['lastUpdated'] = time();

				// Initialize the jobs array if it's not already set
				if ( ! isset( $transientData['jobs'] ) ) {
					$transientData['jobs'] = [];
				}

				// Store the job with its initial state and retryAfter time (now)
				if ( ! array_key_exists( $jobId, $transientData['jobs'] ) ) {
					$transientData['jobs'][ $jobId ]['jobState'] = 'enqueued';
					$transientData['jobs'][ $jobId ]['retryAfter'] = time();
					$transientData['jobs'][ $jobId ]['campaignId'] = $campaign['id'];
				}

				if ( ! $manual ) {
					set_transient( "idemailwiz_sync_{$metricType}_jobs", $transientData, ( 60 * 60 * 24 ) * 1 ); // 1 day expiration

				} else {
					set_transient( "idemailwiz_sync_manual_{$metricType}_job_" . $campaign['id'], $transientData, 30 * MINUTE_IN_SECONDS ); // 30 min expiration
					//error_log( "idemailwiz_sync_manual_{$metricType}_job_" . $campaign['id'] );
				}


				$countRetrieved++;

			}
		} catch (Exception $e) {
			wiz_log( "Error starting export job: " . $e->getMessage() );
			continue;
		}
		$endTime = microtime( true );
		$elapsedTime = $endTime - $startTime;

		// If the request took less than 1 second, sleep the remaining time
		if ( $elapsedTime < 1 ) {
			usleep( ( 1 - $elapsedTime ) * 1000000 );
		}
	}

	return $transientData;
}


// Uses stored export jobIds to pull completed triggered data jobs from iterable
function idemailwiz_sync_triggered_metric_from_transient( $metricType, $manualTransient = false ) {
	global $wpdb;
	if ( $manualTransient === false ) {
		$transientName = "idemailwiz_sync_{$metricType}_jobs";
	} else {
		$transientName = $manualTransient;
	}
	$jobTransient = get_transient( $transientName ) ?? false;
	$jobs = $jobTransient['jobs'] ?? [];
	$jobCount = count( $jobs );

	if ( empty( $jobs ) ) {
		if ( $manualTransient === false ) {
			wiz_log( "No Export Job IDs found for Triggered {$metricType}s. Will check again later." );
			delete_transient( "idemailwiz_{$metricType}_sync_in_progress" );
		} else {
			//wiz_log( "Export jobs for sync of {$metricType} data not found (export queue may be backed up." );
			//delete_transient( $transientName );
			return false;

		}

	}


	$currentTimestamp = time();
	$batchSize = 50;
	$countProcessing = $batchSize;
	if ( $jobCount < $batchSize ) {
		$countProcessing = $jobCount;
	}
	wiz_log( "Exporting $countProcessing of $jobCount triggered $metricType export jobs..." );

	$results = [ 
		'totalInserted' => 0,
		'totalUpdated' => 0,
		'totalSkipped' => 0,
		'totalErrors' => 0
	];

	foreach ( $jobs as $jobId => $jobData ) {

		if ( $currentTimestamp >= $jobData['retryAfter'] ) {
			$processResult = idemailwiz_process_single_jobid( $jobId, $metricType );

			$results['totalInserted'] += $processResult['totalInserted'];
			$results['totalUpdated'] += $processResult['totalUpdated'];
			$results['totalSkipped'] += $processResult['totalSkipped'];
			$results['totalErrors'] += $processResult['totalErrors'];

			if ( in_array( $processResult['jobState'], [ 'failed', 'completed' ] ) ) {
				unset( $jobs[ $jobId ] );
			} else if ( $manualTransient === false ) {
				// if job is still running or enqueued, we re-queue it for 1 hour from now
				$jobs[ $jobId ]['jobState'] = $processResult['jobState'];
				if ( in_array( $processResult['jobState'], [ 'enqueued', 'running' ] ) ) {
					$jobs[ $jobId ]['retryAfter'] = $currentTimestamp + 3600;
				}
			}

			if ( $processResult['totalInserted'] + $processResult['totalUpdated'] + $processResult['totalSkipped'] + $processResult['totalErrors'] == 0 ) {
				//wiz_log( "JobID: $jobId | No data found in export." );
			} else {
				wiz_log( "JobID: $jobId | {$processResult['totalInserted']} inserted, {$processResult['totalUpdated']} updated, {$processResult['totalSkipped']} skipped, {$processResult['totalErrors']} errors." );
			}

			// Decrement batchSize and break if reached 0
			if ( --$batchSize == 0 )
				break;
			
		}
		

	}

	set_transient( $transientName, [ 'jobs' => $jobs ] );
	$jobsRemaining = array_filter( $jobs, function ($jobData) {
		return ! in_array( $jobData['jobState'], [ 'completed', 'failed', null ] );
	} );

	if ( ! empty( $jobsRemaining ) ) {
		if ( $manualTransient === false ) {
			wiz_log( "Scheduling next batch for Triggered {$metricType} jobs." );
			wp_schedule_single_event( time() + 10, "idemailwiz_process_{$metricType}_sync", [ $metricType ] );
		} else {
			wiz_log( "Manual sync failed for triggered {$metricType}s due to timeout. Try again later!" );
			delete_transient( $transientName );
			return false;
		}
	} else {
		if ( ! $manualTransient ) {
			wiz_log( "Finished processing Triggered {$metricType}s queue. Inserted: {$results['totalInserted']}, Updated: {$results['totalUpdated']}, Skipped: {$results['totalSkipped']}, Errors: {$results['totalErrors']}" );
		}

		delete_transient( $transientName );
	}

	if ( ! $manualTransient ) {
		delete_transient( "idemailwiz_{$metricType}_sync_in_progress" );
	} else {
		delete_transient( $transientName );
	}

	return true;
}





function idemailwiz_process_single_jobid( $jobId, $metricType ) {


	$apiResponse = idemailwiz_iterable_curl_call( 'https://api.iterable.com/api/export/' . $jobId . '/files' );


	$return = [ 'jobState' => '', 'jobId' => $jobId, 'totalInserted' => 0, 'totalUpdated' => 0, 'totalSkipped' => 0, 'totalErrors' => 0 ];

	if ( $apiResponse && ! empty( $apiResponse ) ) {

		// Set the new jobState for return
		$jobState = $apiResponse['response']['jobState'];
		$return['jobState'] = $jobState;

		if ( $jobState === 'failed' ) {
			wiz_log( "Job {$jobId} failed to complete on Iterable." );
			return $return;
		} elseif ( $jobState === 'completed' ) {
			//error_log( "Job {$jobId} completed successfully." );

			$startAfter = '';
			do {
				$fileApiResponse = idemailwiz_iterable_curl_call( 'https://api.iterable.com/api/export/' . $jobId . '/files?startAfter=' . $startAfter );
				if ( ! isset( $fileApiResponse['response']['files'] ) ) {
					wiz_log( 'No files found at export link.' );
					break; // Exit the loop if no files are returned
				}
				foreach ( $fileApiResponse['response']['files'] as $file ) {
					$processResult = idemailwiz_process_completed_sync_job( $file['url'], $jobId, $metricType );
					// $processResult is now an associative array with 'inserted' and 'updated' counts
					$return['totalInserted'] += $processResult['inserted'];
					$return['totalUpdated'] += $processResult['updated'];
					$return['totalSkipped'] += $processResult['skipped'];
					$return['totalErrors'] += $processResult['errors'];

					$startAfter = basename( $file['url'] );
				}

				$moreFilesAvailable = count( $fileApiResponse['response']['files'] ) > 0;

			} while ( $moreFilesAvailable );

			return $return;

		} else {
			return $return;
		}
	}

	return $return;

}


// Utility function to process all sync jobs for a metric type.
// Access the files in each job and passed them to another function to insert the records into the database
function idemailwiz_process_completed_sync_job( $fileUrl, $jobId, $metricType ) {

	global $wpdb;
	$cntRecords = 0;

	$jsonResponse = file_get_contents( $fileUrl );
	$lines = explode( "\n", $jsonResponse );

	$insertCount = 0;
	$updateCount = 0;
	$skippedCount = 0;
	$errorCount = 0;

	if ( ! empty( $lines ) && ( count( array_filter( $lines ) ) > 0 ) ) {
		foreach ( $lines as $line ) {
			if ( trim( $line ) === '' ) {
				continue;
			}

			$decodedData = json_decode( $line, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wiz_log( 'json_decode error: ' . json_last_error_msg() );
				continue;
				wiz_log( 'json error, skipping' );
			}

			$tableName = $wpdb->prefix . 'idemailwiz_triggered_' . strtolower( $metricType ) . 's';
			$upsertResult = idemailwiz_insert_exported_job_record( $decodedData, $tableName );

			if ( $upsertResult === 'inserted' ) {
				$insertCount++;
			} elseif ( $upsertResult === 'updated' ) {
				$updateCount++;
			} elseif ( $upsertResult === 'skipped' ) {
				$skippedCount++;
			} elseif ( $upsertResult === 'errors' ) {
				$errorCount++;
			} else {
				$errorCount++;
			}


		}
		if ( $cntRecords > 0 ) {
			//wiz_log("Job {$jobId}: Updated $cntRecords triggered $metricType records.");
		} else {
			//wiz_log("Job {$jobId}: No $metricType records found within exported file.");
		}
	} else {
		//wiz_log("Job {$job}: No $metricType records found within exported file.");
	}

	if ( $insertCount > 0 || $updateCount > 0 ) {
		//wiz_log( "Job {$jobId}: Inserted {$insertCount}, Updated {$updateCount}, and Skipped {$skippedCount} triggered {$metricType} records with {$errorCount} errors." );
	} else {
		// No changes made
		//wiz_log("Job {$jobId}: No changes made to triggered {$metricType} records. {$errorCount} errors encountered.");
	}

	return [ 'inserted' => $insertCount, 'updated' => $updateCount, 'skipped' => $skippedCount, 'errors' => $errorCount ];
}

// Final step in the sync process for triggered data. Inserts or updates records in the proper database
function idemailwiz_insert_exported_job_record( $record, $tableName ) {
	global $wpdb;

	if ( ! is_array( $record ) || empty( $record ) || ! isset( $record['messageId'] ) ) {
		return false;
	}

	$createdAt = new DateTime( $record['createdAt'], new DateTimeZone( 'UTC' ) );

	$msTimestamp = (int) ( $createdAt->format( 'U.u' ) * 1000 );

	// Prepare data for insertion or update
	$data = [ 
		'messageId' => $record['messageId'],
		'userId' => $record['userId'] ?? null,
		'campaignId' => $record['campaignId'] ?? null,
		'templateId' => $record['templateId'] ?? null,
		'startAt' => $msTimestamp,
	];


	// Check if the record exists
	$exists = $wpdb->get_var( $wpdb->prepare( "SELECT messageId FROM $tableName WHERE messageId = %s", $record['messageId'] ) );


	if ( $exists ) {
		// Update existing record
		// $where = [ 'messageId' => $record['messageId'] ];
		// $result = $wpdb->update( $tableName, $data, $where );

		// if ( $result !== false ) {
		// 	return 'updated';
		// }

		// Skip existing records
		return 'skipped';
	} else {
		// Insert new record
		$result = $wpdb->insert( $tableName, $data );
		if ( $result ) {
			return 'inserted';
		}
	}

	if ( ! $result ) {
		wiz_log( "Failed to upsert record into $tableName for messageId: " . $record['messageId'] . ". Error: " . $wpdb->last_error );
		return 'errors';
	}
}
















function idemailwiz_manual_export_request( $campaignId, $dataTypes = [], $startDate = null, $endDate = null ) {

	wiz_log( "Manual export request started for campaignId: {$campaignId}. Gathering data..." );

	global $wpdb;

	$startDate = $startDate ?? '2021-11-01';
	$endDate = $endDate ?? date( 'Y-m-d' );

	if ( empty( $dataTypes ) ) {
		$dataTypes = [ 'send', 'open', 'click', 'complaint', 'unSubscribe', 'sendSkip', 'bounce' ];
	}

	// Initialize array to track which data types need manual export
	$needsManualExport = array_fill_keys( $dataTypes, true );

	$transientNames = [];

	// Check for existing transient jobs
	foreach ( $dataTypes as $dataType ) {
		$jobTransient = get_transient( "idemailwiz_sync_{$dataType}_jobs" ) ?? false;

		if ( isset( $jobTransient['jobs'] ) && ! empty( $jobTransient['jobs'] ) ) {
			foreach ( $jobTransient['jobs'] as $jobId => $jobData ) {
				if ( isset( $jobData['campaignId'] ) && $jobData['campaignId'] == $campaignId ) {
					// Process this job and log
					$responseCounts = idemailwiz_process_single_jobid( $jobId, $dataType );
					//wiz_log( "Job {$jobId} summary for campaign {$campaignId}: " . print_r( $responseCounts, true ) );
					$buildLogResponse = [];
					foreach ($responseCounts as $responseType => $responseCount) {
						$responseTypeString = ucfirst(str_replace('total', '', $responseType));
						$buildLogResponse[] = "{$responseTypeString}: {$responseCount}";
					}
					$buildResponseSummary = implode( ', ', $buildLogResponse );
					wiz_log("Campaign {$campaignId} {$dataType}s sync: {$buildResponseSummary}");
					// Mark this data type as processed (no manual export needed)
					$needsManualExport[ $dataType ] = false;
					break;
				}
			}
		}
	}

	// Filter out data types that don't need manual export
	$dataTypesNeedingManualExport = array_filter( $needsManualExport );

	// Proceed only if there are data types that need manual export
	if ( ! empty( $dataTypesNeedingManualExport ) ) {
		wiz_log( "Exporting data for campaignId $campaignId from Iterable..." );

		// Process manual exports for data types that weren't found in transients
		foreach ( $needsManualExport as $dataType => $manualExportNeeded ) {
			if ( $manualExportNeeded ) {
				//wiz_log( "CampaignId $campaignId does not have a local export stored for {$dataType}. Exporting manually..." );
				// At least 1 data type campaignId was not found in the transient, so we need to run a new export job

				idwiz_export_and_store_jobs_to_transients( $dataType, 'Triggered', [ $campaignId ], $startDate, $endDate, true );

				// Store the transient name for later processing
				$transientName = "idemailwiz_sync_manual_{$dataType}_job_" . $campaignId;
				//error_log( "Transient name: $transientName" );
				$transientNames[ $transientName ] = $dataType; // Map transient name to data type
			}
			sleep( 1 );
		}

		// Allow some time for jobs to process
		sleep( 10 );

		// Process each manual export job
		if ( ! empty( $transientNames ) ) {
			foreach ( $transientNames as $transientName => $dataType ) {
				$syncMetrics = idemailwiz_sync_triggered_metric_from_transient( $dataType, $transientName );
				if ( ! $syncMetrics ) {
					wiz_log( "Campaign $campaignId triggered $dataType export jobs not ready yet, trying one more time..." );
					sleep( 10 );
					$syncMetrics = idemailwiz_sync_triggered_metric_from_transient( $dataType, $transientName );
					if ( ! $syncMetrics ) {
						wiz_log( "Campaign $campaignId triggered $dataType export jobs were not available. $dataType sync canceled." );
						delete_transient( $transientName );
					}
				} else {
					wiz_log( "Campaign $campaignId triggered $dataType data sync was successful." );
				}
				sleep( 5 );
			}
		} else {
			wiz_log( "Failed to find transients for campaignId: {$campaignId}." );
		}
	}

	wiz_log( "Manual Triggered data sync for campaign $campaignId complete. Doing additional sync cleanup..." );

	//delete_transient( $transientName );

	$wizCampaign = get_idwiz_campaign( $campaignId );

	idemailwiz_sync_campaigns([$wizCampaign['id']]); // does it's own logging
	//idemailwiz_sync_templates([$wizCampaign['id']]); // does it's own logging
	

}




function idemailwiz_manual_export_request_ajax() {
	$campaignId = $_POST['campaignId'];
	$startDate = $_POST['startDate'] ?? null;
	$endDate = $_POST['endDate'] ?? null;

	$return = idemailwiz_manual_export_request( $campaignId, [], $startDate, $endDate );

	if ( ! empty( $return ) ) {
		wp_send_json_success();
	} else {
		wp_send_json_error( 'Sync failed!' );
	}
}
add_action( 'wp_ajax_idemailwiz_manual_export_request_ajax', 'idemailwiz_manual_export_request_ajax' );





// Ajax handler for manual sync form on sync station page
add_action( 'wp_ajax_handle_sync_station_sync', 'handle_sync_station_sync' );
function handle_sync_station_sync() {
	check_ajax_referer( 'id-general', 'security' );

	// Extract the form fields from the POST data
	parse_str( $_POST['formFields'], $formFields );

	// Check for sync types
	if ( empty( $formFields['syncTypes'] ) ) {
		wp_send_json_error( 'No sync types were received.' );
		return;
	}

	// Extract campaign IDs, if provided
	$campaignIds = ! empty( $formFields['campaignIds'] ) ? explode( ',', $formFields['campaignIds'] ) : false;

	// Initiate the sync sequence
	foreach ( $formFields['syncTypes'] as $syncType ) {
		if ( $syncType == 'blast' ) {
			$syncResult = idemailwiz_process_sync_sequence( $syncType, $campaignIds, true );
		} else {
			$exportJob = idwiz_export_and_store_jobs_to_transients( $syncType, 'Triggered', $campaignIds, date( 'Y-m-d', strtotime( '-2 years' ) ) );
			sleep( 5 ); // wait 5 seconds for job to hopefully finished
			//idemailwiz_process_jobids( [ array_keys($exportJob['jobs']) ], $syncType );

		}

	}

	if ( $syncResult === false ) {
		wp_send_json_error( 'Sync sequence aborted: Another sync is still in progress or there was an error.' );
	} else {
		wp_send_json_success( 'Sync sequence successfully initiated.' );
	}
}