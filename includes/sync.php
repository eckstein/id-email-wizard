<?php
// Include WordPress' database functions
global $wpdb;


function idemailwiz_update_insert_api_data($items, $operation, $table_name)
{
	global $wpdb;
	$result = ['success' => [], 'errors' => []];

	$id_field = 'id'; // Default ID field
	$name_field = 'name'; // Default name field

	// Determine the ID and name fields based on the table name
	if ($table_name == $wpdb->prefix . 'idemailwiz_templates' || $table_name == $wpdb->prefix . 'idemailwiz_experiments') {
		$id_field = 'templateId';
	}

	// If this is a user sync
	if ($table_name == $wpdb->prefix . 'idemailwiz_users') {
		$id_field = 'wizId';
	}

	// Get table columns once before processing items
	$table_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`");
	if (empty($table_columns)) {
		wiz_log("Error: Could not get columns for table {$table_name}");
		return ['success' => [], 'errors' => ["Failed to get table columns for {$table_name}"]];
	}

	// Batch processing - process items in smaller batches to prevent timeouts
	$batch_size = 250;
	$item_batches = array_chunk($items, $batch_size);
	
	foreach ($item_batches as $batch) {
		foreach ($batch as $key => $item) {
			// If this is a campaigns table sync
			if ($table_name == $wpdb->prefix . 'idemailwiz_campaigns' && $operation !== 'delete') {
				//update the campaigns table lastWizSync with the current datetime
				//$item['lastWizSync'] = date('Y-m-d H:i:s');
			}

			// Add 'name' to the metrics array
			if ($table_name == $wpdb->prefix . 'idemailwiz_metrics') {
				$metricCampaign = get_idwiz_campaign($item['id']);
				$metricName = $metricCampaign['name'] ?? '';
			}

			// If this is a purchase sync, we do some database cleanup and field normalization
			if ($table_name == $wpdb->prefix . 'idemailwiz_purchases') {
				// Exclude purchases with campaignIds that are negatives (like -12345)
				if (isset($item['campaignId']) && $item['campaignId'] < 0) {
					continue;
				}
			}

			// Filter out fields that don't exist in the table schema
			$filtered_item = array_intersect_key($item, array_flip($table_columns));
			
			// Log any fields that were filtered out
			$filtered_fields = array_diff_key($item, $filtered_item);
			if (!empty($filtered_fields)) {
				//wiz_log("Notice: Filtered out non-existent fields for table {$table_name}: " . implode(', ', array_keys($filtered_fields)));
			}

			if (($operation === "update" || $operation === "delete") && !isset($filtered_item[$id_field])) {
				$result['errors'][] = "Failed to perform {$operation}: missing ID field '{$id_field}'";
				continue;
			}

            // --- START: Handle potential array values ---
            $prepared_values = [];
            foreach ($filtered_item as $field_key => $field_value) {
                if (is_array($field_value)) {
                    // Serialize arrays before storing (e.g., for labels or other text fields)
                    // You might need more specific handling based on the column type
                    $prepared_values[] = serialize($field_value);
                    // wiz_log("Notice: Serialized array value for field '{$field_key}' in table {$table_name}");
                } else {
                    $prepared_values[] = $field_value;
                }
            }
            // --- END: Handle potential array values ---

			// Prepare field data for SQL query
			$fields = implode(",", array_map(function ($field) {
				return "`" . esc_sql($field) . "`";
			}, array_keys($filtered_item)));
			
			if (empty($fields)) {
				$result['errors'][] = "Failed to perform {$operation}: no valid fields found";
				continue;
			}

			// Create correct number of placeholders for the values
			$placeholders = implode(",", array_fill(0, count($filtered_item), "%s"));
			// $prepared_values = array_values($filtered_item); // Use the already processed values

			try {
				if ($operation === "insert") {
					if ($table_name == $wpdb->prefix . 'idemailwiz_users') {
						// For users table, use INSERT ... ON DUPLICATE KEY UPDATE
						$updates = implode(", ", array_map(function ($field) {
							return "`$field` = VALUES(`$field`)";
						}, array_keys($filtered_item)));
						$sql = "INSERT INTO `{$table_name}` ({$fields}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updates}";
						try {
							$prepared_sql = $wpdb->prepare($sql, $prepared_values);
						} catch (Exception $e) {
							wiz_log("Error in insert (with update) prepare statement: " . $e->getMessage() . " SQL: " . $sql);
							$result['errors'][] = "Database prepare error: " . $e->getMessage();
							continue;
						}
					} else {
						// For other tables, use regular INSERT
						$sql = "INSERT INTO `{$table_name}` ({$fields}) VALUES ({$placeholders})";
						try {
							$prepared_sql = $wpdb->prepare($sql, $prepared_values);
						} catch (Exception $e) {
							wiz_log("Error in insert prepare statement: " . $e->getMessage() . " SQL: " . $sql);
							$result['errors'][] = "Database prepare error: " . $e->getMessage();
							continue;
						}
					}

					$insert_result = $wpdb->query($prepared_sql);
					if ($insert_result === false) {
						$result['errors'][] = "Failed to insert record: " . $wpdb->last_error;
					} else {
						$result['success'][] = "Inserted record successfully";
						}
				} elseif ($operation === "update") {
					// For update operations
					$updates = implode(", ", array_map(function ($field) {
						return "`$field` = %s";
					}, array_keys($filtered_item)));
                    
                    // Ensure the ID field value is appended correctly for the WHERE clause
                    $update_values = $prepared_values;
                    $update_values[] = $filtered_item[$id_field];

					$sql = "UPDATE `{$table_name}` SET {$updates} WHERE `{$id_field}` = %s";
					// $prepared_values[] = $filtered_item[$id_field]; // Add ID to the end for WHERE clause - Now handled by $update_values
					try {
						$prepared_sql = $wpdb->prepare($sql, $update_values); // Use $update_values
					} catch (Exception $e) {
						wiz_log("Error in update prepare statement: " . $e->getMessage() . " SQL: " . $sql);
						$result['errors'][] = "Database prepare error: " . $e->getMessage();
						continue;
					}

					$update_result = $wpdb->query($prepared_sql);
					if ($update_result === false) {
						$result['errors'][] = "Failed to update record: " . $wpdb->last_error;
					} else {
						$result['success'][] = "Updated record successfully";
					}
				} elseif ($operation === "delete") {
					// For delete operations
					$sql = "DELETE FROM `{$table_name}` WHERE `{$id_field}` = %s";
					try {
						$prepared_sql = $wpdb->prepare($sql, $filtered_item[$id_field]);
					} catch (Exception $e) {
						wiz_log("Error in delete prepare statement: " . $e->getMessage() . " SQL: " . $sql);
						$result['errors'][] = "Database prepare error: " . $e->getMessage();
						continue;
					}

					$delete_result = $wpdb->query($prepared_sql);
					if ($delete_result === false) {
						$result['errors'][] = "Failed to delete record: " . $wpdb->last_error;
					} else {
						$result['success'][] = "Deleted record successfully";
					}
				}
			} catch (Exception $e) {
				wiz_log("Error in database operation: " . $e->getMessage());
				$result['errors'][] = "Database error: " . $e->getMessage();
			}
		}
	}

	return $result;
}


function idemailwiz_fetch_campaigns($campaignIds = null)
{

	$url = 'https://api.iterable.com/api/campaigns';
	wiz_log("Fetching Campaigns from Iterable API..."); // Simplified log
	try {
		$response = idemailwiz_iterable_curl_call($url);
		// $rawResponseSize = isset($response['response']) ? strlen(json_encode($response['response'])) : 0; // REMOVED response size log
		// wiz_log("Fetch Campaigns: API call completed. HTTP Code: \" . ($response['http_code'] ?? 'N/A') . \". Approx response size: $rawResponseSize bytes."); // REMOVED completion log
	} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
		// Log the error with more details
		wiz_log("Fetch Campaigns: CAUGHT EXCEPTION during curl call to $url - " . $e->getMessage()); // Log inside catch


		// Specific check for the "CONSECUTIVE_400_ERRORS" message
		if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
			// Specific action for this type of error
			wiz_log("More than 5 consecutive 400 errors encountered. Skipping...");
		}

		// Optionally, you can rethrow the exception or handle it differently
		// throw $e;
	}

	// Check if campaigns exist in the API response
	if (!isset($response['response']['campaigns'])) {
		// Add specific log for this condition
		wiz_log("Fetch Campaigns: Key ['response']['campaigns'] not found in API response structure."); 
		return "Error: No campaigns found in the API response.";
	}

	// Get only the campaign(s) we want if passed
	// Iterable doesn't allow API calls per campaign as of April 2024
	if ($campaignIds) {
		$campaigns = [];
		foreach ($response['response']['campaigns'] as $campaign) {
			if (in_array($campaign['id'], $campaignIds)) {
				$campaigns[] = $campaign;
			}
		}
		$response['response']['campaigns'] = $campaigns;
	}

	// Return the campaigns array
	return $response['response']['campaigns'];
}

function idemailwiz_fetch_templates($campaignIds = null)
{
	$allTemplates = [];

	if ($campaignIds) {
		$templateIds = array_column(get_idwiz_templates(['campaignIds' => $campaignIds, 'fields' => 'templateId']), 'templateId');
		wiz_log("Fetching " . count($templateIds) . " templates for specific campaigns.");
	} else {
		// Get a formatted end date of tomorrow
		$endDate = new DateTime();
		$endDate->modify('+1 day');
		$endDate = $endDate->format('Y-m-d');
		
		// Define start date to limit template fetch (last 90 days is a reasonable window)
		$startDate = new DateTime();
		$startDate->modify('-90 days');
		$startDate = $startDate->format('Y-m-d');
		
		$templateAPIurls = [
			'blastEmails' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=Email&startDateTime=' . $startDate . '&endDateTime=' . $endDate,
			'triggeredEmails' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=Email&startDateTime=' . $startDate . '&endDateTime=' . $endDate,
			'workflowEmails' => 'https://api.iterable.com/api/templates?templateType=Workflow&messageMedium=Email&startDateTime=' . $startDate . '&endDateTime=' . $endDate,
			'blastSMS' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=SMS&startDateTime=' . $startDate . '&endDateTime=' . $endDate,
			'triggeredSMS' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=SMS&startDateTime=' . $startDate . '&endDateTime=' . $endDate,
		];

		$templateIds = [];
		
		// Fetch templates from all endpoints
		foreach ($templateAPIurls as $typeKey => $url) {
			try {
				$response = idemailwiz_iterable_curl_call($url);
				if (!empty($response['response']['templates'])) {
					$templates = $response['response']['templates'];

					// Add templates to the allTemplates array
					foreach ($templates as $template) {
						$templateIds[] = $template['templateId'];
					}
				}

				usleep(1000);
			} catch (Exception $e) {
				wiz_log("Error during initial API call: " . $e->getMessage());
			}
		}
		
		wiz_log("Fetched " . count($templateIds) . " templates from API.");
	}

	// Process templates in larger batches to prevent timeout while maintaining performance
	$batchSize = 200; // Increased from 100
	$templateIdBatches = array_chunk($templateIds, $batchSize);
	
	// wiz_log("Processing templates in \" . count($templateIdBatches) . \" batches of \" . $batchSize);
	// Optional: Add a simpler log if needed, like: wiz_log("Processing template details...");
	
	foreach ($templateIdBatches as $batchIndex => $templateIdBatch) {
		// Fetch the detailed templates for this batch
		$urlsToFetch = [];
		foreach ($templateIdBatch as $templateId) {
			// Try email endpoint first, and if it fails, fall back to SMS
			$emailEndpoint = "https://api.iterable.com/api/templates/email/get?templateId=$templateId";
			$smsEndpoint = "https://api.iterable.com/api/templates/sms/get?templateId=$templateId";

			$urlsToFetch[] = $emailEndpoint;
			$urlsToFetch[] = $smsEndpoint;
		}

		try {
			$multiResponses = idemailwiz_iterable_curl_multi_call($urlsToFetch);
			$fetchedTemplates = [];

			foreach ($multiResponses as $response) {
				if ($response['httpCode'] == 200) {
					$fetchedTemplate = idemailwiz_simplify_templates_array($response['response']);
					if (!empty($fetchedTemplate)) {
						$allTemplates[] = $fetchedTemplate;
						$fetchedTemplates[] = $fetchedTemplate;
					}
				}
			}

			// wiz_log("Processed batch \" . ($batchIndex + 1) . \" of \" . count($templateIdBatches) . \" with \" . count($fetchedTemplates) . \" templates"); // REMOVED per-batch log
			
			// Add a reasonable pause between batches to prevent API rate limiting, but not too long
			if (count($templateIdBatches) > 1) {
				usleep(25000); // 25ms pause (reduced from 50ms)
			}
		} catch (Exception $e) {
			wiz_log("Error during multi cURL request: " . $e->getMessage());
		}
	}

	wiz_log("Total templates processed: " . count($allTemplates));
	return $allTemplates;
}


// For the returned Template object from Iterable.
// Flattens a multi-dimensional array into a single-level array by concatenating keys into a prefix string.
// Skips values we don't want in the database. Limits 'linkParams' keys to 2 (typically utm_term and utm_content)
function idemailwiz_simplify_templates_array($template)
{

	if (isset($template['metadata'])) {
		// Extract the desired keys from the 'metadata' array
		if (isset($template['metadata']['campaignId'])) {
			$result['campaignId'] = $template['metadata']['campaignId'];
		}
		if (isset($template['metadata']['createdAt'])) {
			$result['createdAt'] = $template['metadata']['createdAt'];
		}
		if (isset($template['metadata']['updatedAt'])) {
			$result['updatedAt'] = $template['metadata']['updatedAt'];
		}
		//if (isset($template['metadata']['clientTemplateId'])) {
		// $result['clientTemplateId'] = $template['metadata']['clientTemplateId'];
		// }
	}

	if (isset($template['linkParams'])) {
		// Extract the desired keys from the 'linkParams' array
		if (isset($template['linkParams'])) {
			foreach ($template['linkParams'] as $linkParam) {
				if ($linkParam['key'] == 'utm_term') {
					$result['utmTerm'] = $linkParam['value'];
				}
				if ($linkParam['key'] == 'utm_content') {
					$result['utmContent'] = $linkParam['value'];
				}
			}
		}
	}


	// Add the rest of the keys to the result
	if ($template && !empty($template)) {
		foreach ($template as $key => $value) {
			if (!isset($template['message'])) { //if 'message' is set, it's an SMS, so this is for email
				$result['messageMedium'] = 'Email';
				// Skip the excluded keys and the keys we've already added
				$excludeKeys = array('clientTemplateId', 'plainText', 'cacheDataFeed', 'mergeDataFeedContext', 'utm_term', 'utm_content', 'createdAt', 'updatedAt', 'ccEmails', 'bccEmails', 'dataFeedIds');
			} else {
				$result['messageMedium'] = 'SMS';
				$excludeKeys = array('messageTypeId', 'trackingDomain', 'googleAnalyticsCampaignName');
			}
			if ($key !== 'metadata' && $key !== 'linkParams' && !in_array($key, $excludeKeys)) {
				$result[$key] = $value;
			}
		}
	}

	return $result ?? [];
}


function idemailwiz_fetch_experiments($campaignIds = null)
{
	$today = new DateTime();
	$startFetchDate = $today->modify('-30 days')->format('Y-m-d');

	$fetchCampArgs = array(
		'messageMedium' => 'Email',
		'type' => 'Blast',
	);

	if ($campaignIds) {
		$fetchCampArgs['campaignIds'] = $campaignIds;
	} else {
		$fetchCampArgs['startAt_start'] = $startFetchDate;
	}

	$allCampaigns = get_idwiz_campaigns($fetchCampArgs);
	$campaignIds = array_column($allCampaigns, 'id');


	$data = [];
	$allExpMetrics = [];

	if (!empty($campaignIds)) {
		$url = 'https://api.iterable.com/api/experiments/metrics?';
		foreach ($campaignIds as $index => $id) {
			$url .= ($index === 0 ? '' : '&') . 'campaignId=' . $id;
		}

		try {
			$response = idemailwiz_iterable_curl_call($url);
		} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
			// Log the error with more details
			wiz_log("Error encountered for fetch experiments curl call to : " . $url . " - " . $e->getMessage());


			// Specific check for the "CONSECUTIVE_400_ERRORS" message
			if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
				// Specific action for this type of error
				wiz_log("More than 5 consecutive 400 errors encountered. Skipping...");
			}

			// Optionally, you can rethrow the exception or handle it differently
			// throw $e;
		}

		if ($response['response']) {

			// Split the CSV data into lines
			$lines = explode("\n", $response['response']);

			// Parse the header line into headers
			$headers = str_getcsv($lines[0]);

			// Replace spaces and slashes with underscores, remove parentheses, remove leading/trailing whitespace, and convert to CamelCase
			$headers = array_map(function ($header) {
				$header = str_replace([' ', '/'], '_', $header);
				$header = str_replace('(', '', $header);
				$header = str_replace(')', '', $header);
				$header = trim($header);
				$header = ucwords(strtolower($header), "_");
				$header = str_replace('_', '', $header);
				$header = lcfirst($header);
				return $header;
			}, $headers);

			// Iterate over the non-header lines
			for ($i = 1; $i < count($lines); $i++) {
				// Parse the line into values
				$values = str_getcsv($lines[$i]);

				// Check if the number of headers and values matches
				if (count($headers) != count($values)) {
					continue;
				}

				// Combine headers and values into an associative array
				$lineArray = array_combine($headers, $values);

				// Calculate the additional percentage metrics
				$metrics = idemailwiz_calculate_metrics($lineArray);

				// Merge the metrics with the existing data
				$data[] = $metrics;
			}
			$allExpMetrics = $data + $data;
		}
	}

	// Return the array of metrics
	return $allExpMetrics;
}



function idemailwiz_fetch_metrics($campaignIds = null)
{

	$today = new DateTime();
	$startFetchDate = $today->modify('-8 weeks')->format('Y-m-d');
	$campaigns_to_fetch = []; // Initialize array to hold campaign IDs

	if ($campaignIds) {
		// If specific campaigns are passed, use them directly
		$metricCampaignArgs = array(
			'fields' => array('id'),
			'campaignIds' => $campaignIds
		);
		$campaigns_to_fetch = get_idwiz_campaigns($metricCampaignArgs);
	} else {
		// If no campaigns are passed (default sync):
		// 1. Fetch Blast campaigns started in the last 8 weeks
		$blastCampaignArgs = array(
			'fields' => array('id'),
			'type' => 'Blast', // Only Blast type
			'startAt_start' => $startFetchDate
		);
		$blast_campaigns = get_idwiz_campaigns($blastCampaignArgs);

		// 2. Fetch Running Triggered campaigns (no date filter)
		$triggeredCampaignArgs = array(
			'fields' => array('id'),
			'type' => 'Triggered', // Only Triggered type
			'campaignState' => 'Running' // Only Running state
		);
		$triggered_campaigns = get_idwiz_campaigns($triggeredCampaignArgs);

		// 3. Merge the results
		$campaigns_to_fetch = array_merge($blast_campaigns ?: [], $triggered_campaigns ?: []); // Ensure we merge arrays even if one is empty/null

		// 4. Optional: Log the counts
		wiz_log("Default Metrics Fetch: Found " . count($blast_campaigns ?: []) . " Blast campaigns (last 8 weeks) and " . count($triggered_campaigns ?: []) . " Running Triggered campaigns.");

	}

	// If no campaigns were found after filtering, return early
	if (empty($campaigns_to_fetch)) {
		wiz_log("Fetch Metrics: No campaigns found matching the criteria to fetch metrics for.");
		return [
			'metrics' => [],
			'requested_ids' => [] // No campaigns were requested
		];
	}

	$batchCount = 0;
	$batches = array();
	$currentBatch = array();
	foreach ($campaigns_to_fetch as $campaign) {
		// Ensure campaign is an array and has an 'id' key before accessing it
		if (is_array($campaign) && isset($campaign['id'])) {
			$currentBatch[] = $campaign['id'];
			if (++$batchCount % 200 == 0) {
				$batches[] = $currentBatch;
				$currentBatch = array();
			}
		} else {
			wiz_log("Fetch Metrics Warning: Invalid campaign data encountered during batching: " . print_r($campaign, true));
		}
	}
	// Add any remaining campaigns that didn't fill a full batch
	if (!empty($currentBatch)) {
		$batches[] = $currentBatch;
	}

	$allMetrics = []; // Initialize $data as an array

	foreach ($batches as $batch) {
		$getString = '?startDateTime=2021-11-01&campaignId=' . implode('&campaignId=', $batch);

		$url = "https://api.iterable.com/api/campaigns/metrics" . $getString;
		try {
			$response = idemailwiz_iterable_curl_call($url);
		} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
			// Log the error with more details
			wiz_log("Error encountered for fetch metrics curl call to : " . $url . " - " . $e->getMessage());


			// Specific check for the "CONSECUTIVE_400_ERRORS" message
			if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
				// Specific action for this type of error
				wiz_log("More than 5 consecutive 400 errors encountered. Skipping...");
			}

			// Optionally, you can rethrow the exception or handle it differently
			// throw $e;
		}

		// Split the CSV data into lines
		$lines = explode("\n", $response['response']);

		// Parse the header line into headers
		$headers = str_getcsv($lines[0]);

		// Replace spaces and slashes with underscores, remove parentheses, remove leading/trailing whitespace, and convert to CamelCase
		$headers = array_map(function ($header) {
			$header = str_replace([' ', '/'], '_', $header);
			$header = str_replace('(', '', $header);
			$header = str_replace(')', '', $header);
			$header = trim($header);
			$header = ucwords(strtolower($header), "_");
			$header = str_replace('_', '', $header);
			$header = lcfirst($header);
			return $header;
		}, $headers);

		// Iterate over the non-header lines
		for ($i = 1; $i < count($lines); $i++) {
			// Parse the line into values
			$values = str_getcsv($lines[$i]);

			// Check if the number of headers and values matches
			if (count($headers) != count($values)) {
				continue;
			}

			// Combine headers and values into an associative array
			$lineArray = array_combine($headers, $values);

			// Calculate the additional percentage metrics
			$metrics = idemailwiz_calculate_metrics($lineArray);

			// --- Add Logging Here ---
			// $logCampaignIdKey = isset($metrics['confidence']) ? 'campaignId' : 'id'; // REMOVED debug log
			// if (isset($metrics[$logCampaignIdKey]) && $metrics[$logCampaignIdKey] == 11494140) { // REMOVED debug log
			// 	wiz_log("Fetch Metrics: Processing data for Campaign ID 11494140. Calculated Metrics: " . print_r($metrics, true)); // REMOVED debug log
			// } elseif (isset($lineArray['id']) && $lineArray['id'] == 11494140) { // REMOVED debug log
			// 	// Log if calculate_metrics returned false or something unexpected for this ID
			// 	wiz_log("Fetch Metrics: Found row for Campaign ID 11494140 in API response, but calculate_metrics result was: " . print_r($metrics, true)); // REMOVED debug log
			// }
			// --- End Logging ---

			// Merge the metrics with the existing data
			$allMetrics[] = $metrics;
		}
		sleep(7); // Respect Iterable's rate limit of 10 requests per minute
	}

	// Return the data array AND the original campaign IDs requested
	return [
		'metrics' => $allMetrics,
		'requested_ids' => array_column($campaigns_to_fetch, 'id') // Extract IDs from the combined fetch
	];
	// return $allMetrics; // Old return
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

				// If there's data to add, yield the user data
				if ($userData) {
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

function idemailwiz_fetch_purchases($campaignIds = [], $startDate = null, $endDate = null)
{
	date_default_timezone_set('UTC'); // Set timezone to UTC to match Iterable
	wiz_log("Fetching purchases from Iterable API...");

	// Define the base URL
	$baseUrl = 'https://api.iterable.com/api/export/data.csv';

	// Define the fields to be omitted
	$omitFields = [
		'shoppingCartItems.orderDetailId',
		'shoppingCartItems.parentOrderDetailId',
		'shoppingCartItems.courseId',
		'shoppingCartItems.predecessorOrderDetailId',
		'shoppingCartItems.financeUnitId',
		'shoppingCartItems.numberOfLessonsPurchasedOpl',
		'shoppingCartItems.sessionStartDateNonOpl',
		'shoppingCartItems.subscriptionAutoRenewDate',
		'shoppingCartItems.totalDaysOfInstruction',
		'currencyTypeId',
		'eventName',
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
	$startDateTime = $startDate ?? date('Y-m-d', strtotime('-3 days'));
	$endDateTime = $endDate ?? date('Y-m-d', strtotime('+1 day'));

	// Handle the campaign IDs if provided
	if (!empty($campaignIds)) {
		if (count($campaignIds) === 1) {
			// If there's only one campaign ID, add it directly
			$queryParams['campaignId'] = $campaignIds[0];
			$wizCampaign = get_idwiz_campaign($campaignIds[0]);

			if ($wizCampaign && isset($wizCampaign['startAt'])) {
				$startDateTime = date('Y-m-d', (int)($wizCampaign['startAt'] / 1000));
			}
		} else {
			// If multiple campaign IDs, find the earliest date
			// Iterable only allows one campaign ID per call to the export API, and only max of 4 per minute, so we estimate the earliest and latest dates and use those
			$wizCampaigns = get_idwiz_campaigns(['campaignIds' => $campaignIds]);
			
			if (!empty($wizCampaigns)) {
				$earliestDate = min(array_column($wizCampaigns, 'startAt'));
				$latestDate = max(array_column($wizCampaigns, 'startAt'));

				$startDateTime = date('Y-m-d', (int)(($earliestDate / 1000) - 86400)); // one day before campaign start date
				$endDateTime = date('Y-m-d', (int)(($latestDate / 1000) + MONTH_IN_SECONDS)); // one month after last campaign start date
			}
		}
	}

	// Add the start and end datetime to the query parameters
	$queryParams['startDateTime'] = $startDateTime;
	$queryParams['endDateTime'] = $endDateTime;
	
	wiz_log("Purchase API date range: " . $startDateTime . " to " . $endDateTime);

	// Build the base query string
	$queryString = http_build_query($queryParams);

	// Manually append each 'omitFields' parameter
	foreach ($omitFields as $field) {
		$queryString .= '&omitFields=' . urlencode($field);
	}

	// Combine the base URL with the query string
	$url = $baseUrl . '?' . $queryString;

	try {
		$response = idemailwiz_iterable_curl_call($url);
		
		if (!isset($response['response']) || empty($response['response'])) {
			wiz_log("Error: Empty response from Iterable API for purchases");
			return [];
		}
		
		if (isset($response['http_code']) && $response['http_code'] != 200) {
			wiz_log("Error: API returned HTTP code " . $response['http_code'] . " for purchases");
			return [];
		}
	} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
		// Log the error with more details
		wiz_log("Error encountered for fetch purchases curl call: " . $e->getMessage());
		return [];
	}

	// Prepare the omit fields to match the processed headers format (safeguard)
	$omitFields[] = 'shoppingCartItems'; //Add the main shoppingCartItems column to be omitted.
	$processedOmitFields = array_map(function ($field) {
		$fieldWithoutPeriods = str_replace('.', '_', $field);
		// Special handling for '_id' field to be 'id'
		return strtolower($fieldWithoutPeriods === '_id' ? 'id' : $fieldWithoutPeriods);
	}, $omitFields);

	$allPurchases = []; // Initialize $allPurchases as an array

	// Open a memory-based stream for reading and writing
	if (($handle = fopen("php://temp", "r+")) !== FALSE) {
		// Write the CSV content to the stream and rewind the pointer
		fwrite($handle, $response['response']);
		rewind($handle);

		// Parse the header line into headers
		$headers = fgetcsv($handle);
		
		if (!$headers) {
			wiz_log("Error: Could not parse CSV headers from API response");
			return [];
		}

		// Prepare the headers
		$processedHeaders = array_map(function ($header) {
			$headerWithoutUnderscores = str_replace('_', '', $header);
			return strtolower(str_replace('.', '_', $headerWithoutUnderscores));
		}, $headers);

		// Iterate over each line of the file
		while (($values = fgetcsv($handle)) !== FALSE) {
			$purchaseData = []; // Initialize as empty array

			// Only process lines with the correct number of columns
			if (count($values) === count($processedHeaders)) {
				// Iterate over the values and headers simultaneously
				foreach ($values as $index => $value) {
					$header = $processedHeaders[$index];
					// Skip the fields that are in the omit list
					if (in_array($header, $processedOmitFields)) {
						continue;
					}

					// Clean the value
					$cleanValue = str_replace(['[', ']', '"'], '', $value);
					
					// Add to the purchase data
					$purchaseData[$header] = $cleanValue;
				}
			}

			if (!empty($purchaseData)) {
				$allPurchases[] = $purchaseData;
			}
		}

		// Close the file handle
		fclose($handle);
		
		wiz_log("Processed " . count($allPurchases) . " purchases from API");
	} else {
		wiz_log("Error: Could not open temporary file handle for CSV processing");
	}

	// Return the data array
	return $allPurchases;
}




function idemailwiz_sync_campaigns($passedCampaigns = null)
{
	wiz_log("Starting campaign sync process...");

	// Fetch campaigns from the API
	$campaigns = idemailwiz_fetch_campaigns($passedCampaigns);
	// wiz_log("Sync Campaigns: Returned from fetch_campaigns. Received \" . (is_array($campaigns) ? count($campaigns) : 'Invalid data type') . \" campaigns."); // REMOVED - Summary below is better

	if (empty($campaigns) || is_string($campaigns)) {
		wiz_log("Sync Campaigns: No campaigns found to sync or error occurred: " . (is_string($campaigns) ? $campaigns : "Empty result")); // Log empty/error
		return "No campaigns found to sync or error occurred.";
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_campaigns';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];
	$records_to_delete = [];

	try {
		// First, filter out campaigns we want to skip
		$filtered_campaigns = [];
		// wiz_log("Sync Campaigns: Starting campaign filtering loop..."); // REMOVED - Redundant log
		$loop_counter = 0;
		foreach ($campaigns as $campaign) {
			$loop_counter++;
			// if ($loop_counter % 100 == 0) { // REMOVED per-100 log
			// 	wiz_log("Sync Campaigns: Processing campaign #{$loop_counter} in filter loop...");
			// }

			if (!isset($campaign['id'])) {
				// wiz_log('No ID found in the fetched campaign record!'); // Keep logs focused
				continue;
			}
			
			if (isset($campaign['campaignState']) && $campaign['campaignState'] == 'Aborted') {
				// Skip aborted campaigns
				continue;
			}
			
			// Get the latest startAt value from our DB for triggered campaigns
			// if (isset($campaign['type']) && $campaign['type'] == 'Triggered') {
			// 	$latestStartAt = get_latest_triggered_startAt($campaign['id']);
			// 	if ($latestStartAt !== null) {
			// 		$campaign['startAt'] = $latestStartAt;
			// 	}
			// }
			
			$filtered_campaigns[] = $campaign;
		}
		
		$campaigns = $filtered_campaigns;
		wiz_log("Sync Campaigns: Filtering complete. After filtering, processing " . count($campaigns) . " campaigns."); // Corrected log location
		
		// If we have no campaigns after filtering, return early
		if (empty($campaigns)) {
			return "No valid campaigns found to sync after filtering.";
		}

		// Get all campaign IDs in one query for efficiency
		$campaign_ids = array_filter(array_map(function($campaign) {
			return isset($campaign['id']) && is_numeric($campaign['id']) ? (int)$campaign['id'] : null;
		}, $campaigns));
		
		// Get all existing campaign records
		try {
			if (empty($campaign_ids)) {
				$existing_campaigns = [];
				wiz_log("No valid campaign IDs found to check against database.");
			} else {
				$placeholders = implode(',', array_fill(0, count($campaign_ids), '%d'));
				$query_params = array_merge(["SELECT * FROM $table_name WHERE id IN ($placeholders)"], array_values($campaign_ids));
				$existing_campaigns_query = call_user_func_array(
					array($wpdb, 'prepare'),
					$query_params
				);
				$existing_campaigns = $wpdb->get_results($existing_campaigns_query, ARRAY_A);
			}
			
			if ($wpdb->last_error) {
				throw new Exception("Database error fetching existing campaigns: " . $wpdb->last_error);
			}
			
			// Create a lookup array for faster checking
			$existing_campaign_lookup = [];
			foreach ($existing_campaigns as $existing_campaign) {
				$existing_campaign_lookup[$existing_campaign['id']] = $existing_campaign;
			}
		} catch (Exception $e) {
			wiz_log("Error fetching existing campaigns: " . $e->getMessage());
			return "Error fetching existing campaigns: " . $e->getMessage();
		}

		// Process campaigns in batches
		$batch_size = 500;
		$campaign_batches = array_chunk($campaigns, $batch_size);
		
		// wiz_log("Processing campaigns in \" . count($campaign_batches) . \" batches of \" . $batch_size);
		// Optional: Add simpler log wiz_log("Comparing API campaigns with database...");
		
		foreach ($campaign_batches as $batch_index => $campaign_batch) {
			$batch_to_update = [];
			$batch_to_insert = [];
			$batch_to_delete = [];
			
			foreach ($campaign_batch as $campaign) {
				// Check for archived campaigns and mark for deletion if needed
				if (isset($campaign['labels']) && is_array($campaign['labels']) && in_array('x_Archived', $campaign['labels'])) {
					$batch_to_delete[] = $campaign;
					continue;
				}
				
				// Check if campaign already exists using the lookup array
				if (isset($existing_campaign_lookup[$campaign['id']])) {
					// Perform deep comparison to decide if update is needed
					$wizCampaign = $existing_campaign_lookup[$campaign['id']];
					$fieldsDifferent = false;
					
					if ($passedCampaigns) {
						// If campaigns are passed, update them all
						$batch_to_update[] = $campaign;
					} else {
						// Otherwise, check if fields are different
						foreach ($campaign as $key => $value) {
							if (!isset($wizCampaign[$key]) || $wizCampaign[$key] != $value) {
								$fieldsDifferent = true;
								break;
							}
						}
						
						// Update the row if any field is different
						if ($fieldsDifferent) {
							$batch_to_update[] = $campaign;
						}
					}
				} else {
					// Campaign not in DB, add it
					$batch_to_insert[] = $campaign;
				}
			}
			
			// wiz_log("Batch \" . ($batch_index + 1) . \": Processing \" . count($batch_to_update) . \" updates, \" . count($batch_to_insert) . \" inserts, and \" . count($batch_to_delete) . \" deletes"); // REMOVED per-batch summary
			
			// Process this batch and add results to the main arrays
			if (!empty($batch_to_insert)) {
				$records_to_insert = array_merge($records_to_insert, $batch_to_insert);
			}
			
			if (!empty($batch_to_update)) {
				$records_to_update = array_merge($records_to_update, $batch_to_update);
			}
			
			if (!empty($batch_to_delete)) {
				$records_to_delete = array_merge($records_to_delete, $batch_to_delete);
			}
		}
		
		wiz_log("Campaigns to process: Total " . count($campaigns) . " (Insert: " . count($records_to_insert) . ", Update: " . count($records_to_update) . ", Delete: " . count($records_to_delete) . ")");

		// Process the insert/update and log the result
		return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update, $records_to_delete);
	} catch (Exception $e) {
		wiz_log("Error in campaign sync process: " . $e->getMessage());
		return "Error in campaign sync process: " . $e->getMessage();
	}
}





function idemailwiz_sync_templates($passedCampaigns = null)
{
	wiz_log("Starting template sync process...");
	
	// Fetch relevant templates
	// Note: The fetch function filters by updatedAt differences to limit results
	$templates = idemailwiz_fetch_templates($passedCampaigns);

	if (empty($templates)) {
		wiz_log("No templates found to sync");
		return "No templates found to sync.";
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_templates';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];

	// Get all template IDs in one query for efficiency
	$template_ids = array_column($templates, 'templateId');
	
	// Fix for empty array check
	if (empty($template_ids)) {
		wiz_log("No template IDs found in fetched templates");
		return "No template IDs found in fetched templates.";
	}
	
	// Get all existing template IDs
	$placeholders = implode(',', array_fill(0, count($template_ids), '%d'));
	$existing_templates_query = call_user_func_array(
		array($wpdb, 'prepare'),
		array_merge(array("SELECT templateId FROM $table_name WHERE templateId IN ($placeholders)"), $template_ids)
	);
	$existing_template_ids = $wpdb->get_col($existing_templates_query);
	
	// Create a lookup array for faster checking
	$existing_template_lookup = array_flip($existing_template_ids);

	foreach ($templates as $template) {
		if (!isset($template['templateId'])) {
			wiz_log('No templateId found in the fetched template record!');
			continue;
		}
		
		// Check if template already exists using the lookup array
		if (isset($existing_template_lookup[$template['templateId']])) {
			$records_to_update[] = $template;
		} else {
			$records_to_insert[] = $template;
		}
	}
	
	wiz_log("Templates to process: " . count($templates) . " (Insert: " . count($records_to_insert) . ", Update: " . count($records_to_update) . ")");

	// Process and log the sync operation
	return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}





function idemailwiz_sync_purchases($campaignIds = null, $startDate = null, $endDate = null)
{
	wiz_log("Starting purchase sync process...");
	
	try {
		$purchases = idemailwiz_fetch_purchases($campaignIds, $startDate, $endDate);
		
		if (empty($purchases)) {
			wiz_log("No purchases found to sync");
			return "No purchases found to sync.";
		}

		// Log the first purchase as a sample of the raw data
		if (!empty($purchases[0])) {
			wiz_log("Sample of raw purchase data: " . print_r($purchases[0], true));
		}

		global $wpdb;
		$purchases_table = $wpdb->prefix . 'idemailwiz_purchases';

		$records_to_insert = [];
		$records_to_update = [];
		
		// Get all existing purchase IDs in one query for efficiency
		$purchase_ids = array_column($purchases, 'id');
		
		// Clean purchase IDs from any 'purchase-' prefix
		foreach ($purchase_ids as &$id) {
			if (is_string($id) && strpos($id, 'purchase-') === 0) {
				$id = str_replace('purchase-', '', $id);
			}
		}
		unset($id); // Break the reference to the last element
		
		// Fix for wpdb::prepare error - handle empty arrays and properly prepare the query
		if (empty($purchase_ids)) {
			$existing_purchase_ids = [];
		} else {
			$placeholders = implode(',', array_fill(0, count($purchase_ids), '%s'));
			$existing_purchases_query = call_user_func_array(
				array($wpdb, 'prepare'),
				array_merge(array("SELECT id FROM $purchases_table WHERE id IN ($placeholders)"), $purchase_ids)
			);
			$existing_purchase_ids = $wpdb->get_col($existing_purchases_query);
		}
		
		// Create a lookup array for faster checking
		$existing_purchase_lookup = array_flip($existing_purchase_ids);

		// --- Prepare campaign start time lookup ---
		wiz_log("Fetching campaign start times for purchase attribution...");
		$campaign_ids_in_purchases = array_unique(array_filter(array_column($purchases, 'campaignid')));
		$campaign_start_times = [];
		if (!empty($campaign_ids_in_purchases)) {
			$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
			$placeholders = implode(',', array_fill(0, count($campaign_ids_in_purchases), '%d'));
			$campaign_results = $wpdb->get_results(
				call_user_func_array(
					array($wpdb, 'prepare'),
					array_merge(array("SELECT id, startAt FROM $campaigns_table WHERE id IN ($placeholders)"), $campaign_ids_in_purchases)
				),
				ARRAY_A
			);
			// Create the lookup array
			foreach ($campaign_results as $campaign) {
				$campaign_start_times[$campaign['id']] = $campaign['startAt'];
			}
		}
		// --- End campaign start time lookup ---

		foreach ($purchases as &$purchase) {
			if (!isset($purchase['id'])) {
				wiz_log('No ID found in the fetched purchase record!');
				continue;
			}

			// Remove 'purchase-' prefix from ID if it exists
			if (is_string($purchase['id']) && strpos($purchase['id'], 'purchase-') === 0) {
				$purchase['id'] = str_replace('purchase-', '', $purchase['id']);
			}

			// Convert campaignId to NULL if it's missing, empty, or zero
			if (!isset($purchase['campaignid']) || empty($purchase['campaignid']) || $purchase['campaignid'] === 0 || $purchase['campaignid'] === '0') {
				$purchase['campaignid'] = null;
			}

			// Fetch the campaign's startAt using the lookup array if campaignId is set
			if (isset($purchase['campaignid']) && isset($campaign_start_times[$purchase['campaignid']])) {
				$purchase['campaignstartat'] = $campaign_start_times[$purchase['campaignid']];
			}

			// Check if purchase already exists using the lookup array
			if (isset($existing_purchase_lookup[$purchase['id']])) {
				$records_to_update[] = $purchase;
			} else {
				$records_to_insert[] = $purchase;
			}
		}
		
		wiz_log("Purchases to process: " . count($purchases) . " (Insert: " . count($records_to_insert) . ", Update: " . count($records_to_update) . ")");
		
		return idemailwiz_process_and_log_sync($purchases_table, $records_to_insert, $records_to_update);
	} catch (Exception $e) {
		wiz_log("Error in purchase sync: " . $e->getMessage());
		return "Error in purchase sync: " . $e->getMessage();
	}
}




function idemailwiz_sync_experiments($passedCampaigns = null)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_experiments';

	// Fetch experiments
	$experiments = idemailwiz_fetch_experiments($passedCampaigns);

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];

	foreach ($experiments as $experiment) {

		if (!isset($experiment['templateId'])) {
			if (!empty($experiment)) {
				wiz_log('No templateId found in the fetched experiment record!');
				continue;
			}
		}

		// Retrieve existing experiments from the database
		$wizExperiments = get_idwiz_experiments(['campaignIds' => [$experiment['campaignId']], 'templateId' => $experiment['templateId']]);

		if ($wizExperiments) {
			// Check if the experiment is already marked for update to avoid duplicates
			if (!in_array($experiment, $records_to_update)) {
				// Mark for update
				$records_to_update[] = $experiment;
			}
		} else {
			// Mark for insert
			$records_to_insert[] = $experiment;
		}
	}

	// Process the insert/update and log them
	return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}




function idemailwiz_sync_metrics($passedCampaigns = null)
{

	$fetch_result = idemailwiz_fetch_metrics($passedCampaigns); // Gets all metrics if none are passed
	$metrics = $fetch_result['metrics'] ?? [];
	$requested_ids = $fetch_result['requested_ids'] ?? [];

	//(print_r($metrics, true));

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_metrics';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];
	$processed_ids = []; // Keep track of IDs returned by API

	if ($passedCampaigns) {
		// If specific campaigns are passed, assume we always want to update them if data exists
		$records_to_update = $metrics;
		$processed_ids = array_column($metrics, 'id');
	} else {
		// If syncing all, check existence before deciding insert/update
		foreach ($metrics as $metric) {
			if (!isset($metric['id'])) {
				wiz_log('No ID found in the fetched metric record!');
				continue;
			}
			$processed_ids[] = $metric['id']; // Track this ID

			// Handle SMS campaign header mapping
			$wizCampaign = get_idwiz_campaign($metric['id']);
			if ($wizCampaign && $wizCampaign['messageMedium'] == 'SMS') {
				$metric['uniqueEmailSends'] = $metric['uniqueSmsSent'] ?? 0;
				$metric['uniqueEmailsDelivered'] = $metric['uniqueSmsDelivered'] ?? 0;
				$metric['uniqueEmailClicks'] = $metric['uniqueSmsClicks'] ?? 0;
				// Potentially unset SMS specific fields if desired, e.g.:
				// unset($metric['uniqueSmsSent'], $metric['uniqueSmsDelivered'], $metric['uniqueSmsClicks']); 
			}

			// Check for existing metric
			$wizMetric = get_idwiz_metric($metric['id']);
			if ($wizMetric) {
				// Gather metric for update and de-dupe (shouldn't be necessary if API returns one row per ID)
				if (!in_array($metric, $records_to_update)) {
					$records_to_update[] = $metric;
				}
			} else {
				// metric not in db, we'll add it
				$records_to_insert[] = $metric;
			}
		}
	}

	// --- Add default records for requested IDs missing from API response --- 
	$missing_ids = array_diff($requested_ids, $processed_ids);
	if (!empty($missing_ids)) {
		wiz_log("Metrics Sync: Found " . count($missing_ids) . " requested campaigns missing from API response. Adding default zeroed records.");
		
		// Define all numeric columns expected in the metrics table (based on schema)
		$numeric_metric_columns = [
			'averageCustomConversionValue', 'averageOrderValue', 'purchasesMEmail', 'revenue', 
			'gaRevenue', 'revenueMEmail', 'sumOfCustomConversions', 'totalComplaints', 
			'totalCustomConversions', 'totalEmailHoldout', 'totalEmailOpens', 'totalEmailOpensFiltered', 
			'totalEmailSendSkips', 'totalEmailSends', 'totalEmailsBounced', 'totalEmailsClicked', 
			'totalEmailsDelivered', 'totalPurchases', 'totalUnsubscribes', 'uniqueCustomConversions', 
			'uniqueEmailClicks', 'uniqueEmailOpens', 'uniqueEmailOpensFiltered', 'uniqueEmailOpensOrClicks', 
			'uniqueEmailSends', 'uniqueEmailsBounced', 'uniqueEmailsDelivered', 'uniquePurchases', 
			'uniqueUnsubscribes', 'purchasesMSms', 'revenueMSms', 'totalInboundSms', 'totalSmsBounced', 
			'totalSmsDelivered', 'totalSmsHoldout', 'totalSmsSendSkips', 'totalSmsSent', 'totalSmsClicks', 
			'uniqueInboundSms', 'uniqueSmsBounced', 'uniqueSmsClicks', 'uniqueSmsDelivered', 
			'uniqueSmsSent', 'totalHostedUnsubscribeClicks', 'uniqueHostedUnsubscribeClicks', 
			'lastWizUpdate', 'wizDeliveryRate', 'wizOpenRate', 'wizCtr', 'wizCto', 'wizUnsubRate', 
			'wizCompRate', 'wizCvr', 'wizAov'
			// Note: opensByHour, clicksByHour are LONGTEXT, handle separately if needed, default to NULL
		];

		$default_metrics = [];
		foreach ($numeric_metric_columns as $col) {
			$default_metrics[$col] = 0;
		}
		$default_metrics['opensByHour'] = null;
		$default_metrics['clicksByHour'] = null;

		foreach ($missing_ids as $missing_id) {
			// Check if this ID already exists in DB (e.g., from a previous run)
			// If it exists, we don't need to insert a zeroed row unless we want to overwrite it.
			// For simplicity now, let's just add to insert - INSERT IGNORE will handle duplicates.
			$zeroed_record = array_merge(['id' => $missing_id], $default_metrics);
			$records_to_insert[] = $zeroed_record;
		}
	}
	// --- End default records addition --- 

	// Does our wiz_logging and returns data about the insert/update
	return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}


function idemailwiz_process_and_log_sync($table_name, $records_to_insert = null, $records_to_update = null, $records_to_delete = null)
{
    // Extracting the type (e.g., 'campaign', 'template', etc.) from the table name
    $type = substr($table_name, strrpos($table_name, '_') + 1);

    $insert_results = '';
    $update_results = '';
    $return = array();

    // Only process and log if we have records to handle
    if (!empty($records_to_insert)) {
        $insert_results = idemailwiz_update_insert_api_data($records_to_insert, 'insert', $table_name);
        $return['insert'] = $insert_results;
    }
    
    if (!empty($records_to_update)) {
        $update_results = idemailwiz_update_insert_api_data($records_to_update, 'update', $table_name);
        $return['update'] = $update_results;
    }
    
    if (!empty($records_to_delete)) {
        $delete_results = idemailwiz_update_insert_api_data($records_to_delete, 'delete', $table_name);
        $return['delete'] = $delete_results;
    }

    // Only log if we actually processed some records
    if (!empty($records_to_insert) || !empty($records_to_update) || !empty($records_to_delete)) {
        $logInsertUpdate = return_insert_update_logging($insert_results, $update_results, $table_name);
        if ($logInsertUpdate && !strpos($logInsertUpdate, 'up to date')) {
            wiz_log(ucfirst($type) . " sync results: " . $logInsertUpdate);
        }
    }

    return $return;
}



function return_insert_update_logging($insert_results, $update_results, $table_name)
{
	$logResults = function ($results, $type) {
		if (empty($results['success']) && empty($results['errors'])) {
			return ""; // No operations performed
		}
		if (!isset($results['success'], $results['errors'])) {
			return "Invalid {$type} results structure.";
		}
		$log = '';
		$cntSuccess = count($results['success']);
		$cntErrors = count($results['errors']);

		if ($cntSuccess) {
			$log .= "Successful {$type} of $cntSuccess records.\n";
		}
		foreach ($results['errors'] as $message) {
			$log .= "Error ({$type}): $message\n";
		}
		return rtrim($log); // Remove trailing newline
	};

	$logInsert = isset($insert_results) ? $logResults($insert_results, 'insert') : '';
	$logUpdate = isset($update_results) ? $logResults($update_results, 'update') : '';
	$logSync = $logInsert . $logUpdate;

	if (!$logSync) {
		$tableNameParts = explode('_', $table_name);
		$tableNameType = end($tableNameParts);
		return "The $tableNameType database is up to date! No inserts or updates are needed.";
	}

	return trim($logInsert . "\n" . $logUpdate);
}




// Ajax handler for sync button
// Also creates and logs readable sync responses from response arrays



function idemailwiz_ajax_sync()
{
	// Check for valid nonce
	if (
		!(
			check_ajax_referer('data-tables', 'security', false) ||
			check_ajax_referer('initiatives', 'security', false) ||
			check_ajax_referer('wiz-metrics', 'security', false) ||
			check_ajax_referer('id-general', 'security', false)
		)
	) {
		wp_die('Invalid action or nonce');
	}

	$campaignIds = isset($_POST['campaignIds']) ? json_decode(stripslashes($_POST['campaignIds']), true) : [];

	$response =	idemailwiz_sync_non_triggered_metrics($campaignIds);

	if ($response === false) {
		wp_send_json_error('There was an error in the sync process!');
	} else {
		wp_send_json_success($response);
	}
}
add_action('wp_ajax_idemailwiz_ajax_sync', 'idemailwiz_ajax_sync');




// Calculate percentage metrics
// Takes a row of metrics data from the api call
function idemailwiz_calculate_metrics($metrics)
{
	$campaignIdKey = 'id';

	if (isset($metrics['confidence'])) { // Only experiments have the 'confidence' key (since Iterable gives us no other way to check)
		// If this is an experiment, we look in the campaignId column instead of the id column
		$campaignIdKey = 'campaignId';
	}

	// Get the campaign using the campaignId from the passed metrics
	if (!isset($metrics[$campaignIdKey])) {
		wiz_log("Can't calculate metrics, no ID found in data!");
		return false;
	}
	$wiz_campaign = get_idwiz_campaign($metrics[$campaignIdKey]);

	// Campaign must already be in database for metrics to be added/updated
	if (!$wiz_campaign) {
		return false;
	}

	// Check the campaign medium
	$medium = $wiz_campaign['messageMedium'];

	// Required fields for Email
	$requiredFields = ['uniqueEmailSends', 'uniqueEmailsDelivered', 'uniqueEmailOpens', 'uniqueEmailClicks', 'uniqueUnsubscribes', 'totalComplaints', 'uniquePurchases', 'revenue'];

	// Update required fields if it's an SMS campaign
	if ($medium == 'SMS') {
		$requiredFields = ['uniqueSmsSent', 'uniqueSmsDelivered', 'uniqueSmsClicks', 'uniqueUnsubscribes', 'totalComplaints', 'uniquePurchases', 'revenue'];
		
		// Map SMS fields to email fields for consistency
		$metrics['uniqueEmailSends'] = $metrics['uniqueSmsSent'] ?? 0;
		$metrics['uniqueEmailsDelivered'] = $metrics['uniqueSmsDelivered'] ?? 0;
		$metrics['uniqueEmailClicks'] = $metrics['uniqueSmsClicks'] ?? 0;
		$metrics['uniqueEmailOpens'] = 0; // SMS doesn't have opens
	}

	// Ensure required fields are set
	foreach ($requiredFields as $field) {
		if (!isset($metrics[$field]) || $metrics[$field] === null) {
			$metrics[$field] = 0;
		}
	}

	// Calculate common metrics
	$sendValue = (float) $metrics['uniqueEmailSends'];
	$deliveredValue = (float) $metrics['uniqueEmailsDelivered'];
	$clicksValue = (float) $metrics['uniqueEmailClicks'];
	$opensValue = $medium == 'Email' ? (float) $metrics['uniqueEmailOpens'] : 0;
	$unsubscribesValue = (float) $metrics['uniqueUnsubscribes'];
	$complaintsValue = (float) $metrics['totalComplaints'];
	$purchasesValue = (float) $metrics['uniquePurchases'];
	$revenueValue = (float) $metrics['revenue'];

	if ($sendValue > 0) {
		$metrics['wizDeliveryRate'] = ($deliveredValue / $sendValue) * 100;
		$metrics['wizCtr'] = ($clicksValue / $sendValue) * 100;
		$metrics['wizUnsubRate'] = ($unsubscribesValue / $sendValue) * 100;
		$metrics['wizCompRate'] = ($complaintsValue / $sendValue) * 100;
		$metrics['wizCvr'] = ($purchasesValue / $sendValue) * 100;
	} else {
		$metrics['wizCtr'] = 0;
		$metrics['wizUnsubRate'] = 0;
		$metrics['wizCompRate'] = 0;
		$metrics['wizCvr'] = 0;
	}

	if ($purchasesValue > 0) {
		$metrics['wizAov'] = ($revenueValue / $purchasesValue);
	} else {
		$metrics['wizAov'] = 0;
	}

	// Open metrics (sms or no opens gets zero values)
	if ($medium == 'Email' && $sendValue > 0) {
		$metrics['wizOpenRate'] = ($opensValue / $sendValue) * 100;
		$metrics['wizCto'] = $opensValue > 0 ? ($clicksValue / $opensValue) * 100 : 0;
	} else {
		$metrics['wizOpenRate'] = 0;
		$metrics['wizCto'] = 0;
	}

	// Remove metrics we don't want to sync in
	unset($metrics['uniqueSmsSentByMessage']);

	// For SMS campaigns, store original SMS metrics as well
	if ($medium == 'SMS') {
		$metrics['uniqueSmsSent'] = $metrics['uniqueEmailSends'];
		$metrics['uniqueSmsDelivered'] = $metrics['uniqueEmailsDelivered'];
		$metrics['uniqueSmsClicks'] = $metrics['uniqueEmailClicks'];
	}

	return $metrics;
}

function get_latest_triggered_startAt($campaignId)
{
	global $wpdb;

	$table_name = $wpdb->prefix . 'idemailwiz_triggered_sends';

	// Prepare the SQL query to prevent SQL injection
	$sql = $wpdb->prepare(
		"SELECT startAt FROM $table_name WHERE campaignId = %s ORDER BY startAt DESC LIMIT 1",
		$campaignId
	);

	// Execute the SQL query
	$result = $wpdb->get_var($sql);

	if ($result !== null) {
		return (int) $result; // Convert to integer if your startAt is stored as string
	} else {
		return null; // Return null if no record is found
	}
}

// Add our Sync Sequence custom action for blast metrics
add_action("idemailwiz_process_blast_sync", 'idemailwiz_process_blast_sync', 10);

// Schedule the blast metrics sync to run hourly
function idemailwiz_schedule_blast_sync()
{
	if (!wp_next_scheduled('idemailwiz_process_blast_sync')) {
		wp_schedule_event(time(), 'hourly', 'idemailwiz_process_blast_sync');
	}
}
add_action('wp', 'idemailwiz_schedule_blast_sync');

// Runs the Blast metric sync sequence
function idemailwiz_process_blast_sync()
{
	// Clear expired transients manually before checking anything
	delete_expired_transients();

	$wizSettings = get_option('idemailwiz_settings');

	$blastSyncOn = $wizSettings['iterable_sync_toggle'] ?? 'off';

	$blastSyncInProgress = get_transient('idemailwiz_blast_sync_in_progress');

	// Check for GA sync
	$gaSyncWaiting = get_transient('ga_sync_waiting');
	if (!$gaSyncWaiting) {
		sync_ga_campaign_revenue_data();
		// Sync every 2 hours only
		set_transient('ga_sync_waiting', true, (120 * MINUTE_IN_SECONDS));
	}

	$blastSyncWaiting = get_transient('blast_sync_waiting');
	if ($blastSyncOn && !$blastSyncWaiting && !$blastSyncInProgress) {
		idemailwiz_sync_non_triggered_metrics();
	}
	return true; // Indicate successful completion of the sync sequence
}

function idemailwiz_sync_non_triggered_metrics($campaignIds = [], $sync_dbs = null)
{
	$syncArgs = [];
	$response = [];

	set_transient('idemailwiz_blast_sync_in_progress', true, (5 * MINUTE_IN_SECONDS));
	wiz_log("Starting metrics sync process...");

	$sync_dbs = $sync_dbs ?? ['campaigns', 'templates', 'metrics', 'purchases', 'experiments'];
	
	foreach ($sync_dbs as $db) {
		wiz_log("Syncing " . $db . "...");
		if (!empty($campaignIds)) {
			$syncArgs = $campaignIds;
		}
		
		$function_name = 'idemailwiz_sync_' . $db;
		if (!function_exists($function_name)) {
			wiz_log("Error: Sync function does not exist for " . $db);
			$response[$db] = ['error' => 'Sync function does not exist for ' . $db];
			continue;
		}
		
		try {
			$result = call_user_func($function_name, $syncArgs);
			
			if ($result === false) {
				wiz_log("Error: Sync failed for " . $db);
				$response[$db] = ['error' => 'Sync failed for ' . $db];
			} else {
				$response[$db] = $result;
			}
		} catch (Exception $e) {
			wiz_log("Exception during " . $db . " sync: " . $e->getMessage());
			$response[$db] = ['error' => 'Exception during sync: ' . $e->getMessage()];
		}
	}

	// Do our general database cleanups
	wiz_log('Doing database cleanups...');
	do_database_cleanups($campaignIds);

	delete_transient('idemailwiz_blast_sync_in_progress');
	wiz_log("Completed metrics sync process");

	return $response;
}

// Define the custom cron interval
add_filter('cron_schedules', 'idemailwiz_add_cron_intervals');
function idemailwiz_add_cron_intervals($schedules)
{
	$schedules['every_minute'] = array(
		'interval' => 60,
		'display' => __('Every Minute')
	);
	$schedules['every_three_minutes'] = array(
		'interval' => 180,
		'display' => __('Every 3 Minutes')
	);
	$schedules['every_two_minutes'] = array(
		'interval' => 120,
		'display' => __('Every 2 Minutes')
	);
	$schedules['every_thirty_minutes'] = array(
		'interval' => 30 * 60,
		'display' => __('Every 30 Minutes')
	);
	$schedules['every_two_hours'] = array(
		'interval' => 60 * 60 * 2,
		'display' => __('Every 2 Hours')
	);
	$schedules['weekly_monday_morning'] = array(
		'interval' => 604800,
		'display' => __('Every Monday Morning')
	);
	return $schedules;
}




// Schedule the sync process. It will continue until cron is deleted
add_action('wp_loaded', 'idemailwiz_schedule_sync_process');
function idemailwiz_schedule_sync_process()
{
	$wizSettings = get_option('idemailwiz_settings');
	$engSync = $wizSettings['iterable_engagement_data_sync_toggle'] ?? 'off';

	if ($engSync == 'on') {
		if (!wp_next_scheduled('idemailwiz_sync_engagement_data')) {
			if (get_transient('data_sync_in_progress')) {
				wiz_log("Auto-sync triggered, but data sync is already in progress.");
				wp_schedule_single_event(time() + 2 * HOUR_IN_SECONDS, 'idemailwiz_sync_engagement_data');
				sleep(1);
				return;
			};
			wp_schedule_event(time(), 'every_two_hours', 'idemailwiz_sync_engagement_data');

			//wp_schedule_single_event(time(), 'idemailwiz_sync_engagement_data');
		}
	}
}

// Callback function for the sync process event
add_action('idemailwiz_sync_engagement_data', 'idemailwiz_sync_engagement_data_callback');
function idemailwiz_sync_engagement_data_callback()
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	// Get the Wiz Settings to check if the sync is turned on
	$wizSettings = get_option('idemailwiz_settings');
	$engSync = $wizSettings['iterable_engagement_data_sync_toggle'] ?? 'off';

	if ($engSync !== 'on') {
		// If the sync is turned off, log a message and clear the scheduled hook
		wiz_log("Engagement data sync is disabled in Wiz Settings. Unscheduling sync events.");
		// Clear both recurring and potential single events
		wp_clear_scheduled_hook('idemailwiz_sync_engagement_data');
		// Maybe reschedule the main hook checker if needed, or rely on settings save/activation
		delete_transient('data_sync_in_progress'); // Ensure transient is cleared
		return;
	}

	// Prevent overlapping runs using a transient
	if (get_transient('idemailwiz_single_job_processor_running')) {
		wiz_log("Single job processor is already running. Skipping this callback.");
		return;
	}
	set_transient('idemailwiz_single_job_processor_running', true, MINUTE_IN_SECONDS * 5); // Lock for 5 minutes max

	// Get the next single ready job (pending or requeued, retryAfter passed)
	$now_pt = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));
	$now_formatted = $now_pt->format('Y-m-d H:i:s');

	$job = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $sync_jobs_table_name 
			 WHERE syncStatus IN ('pending', 'requeued') 
			 AND retryAfter <= %s 
			 ORDER BY syncPriority DESC, retryAfter ASC 
			 LIMIT 1",
			$now_formatted
		),
		ARRAY_A
	);

	if (!empty($job)) {
		// Job found - process it
		wiz_log("Found ready job {$job['jobId']}. Processing...");
		
		// Clear any existing single schedule before processing
		// (Prevents double scheduling if processing is very fast)
		$timestamp = wp_next_scheduled('idemailwiz_sync_engagement_data');
		if ($timestamp && !wp_get_schedule('idemailwiz_sync_engagement_data')) { // Check if it's a single event
			wp_unschedule_event($timestamp, 'idemailwiz_sync_engagement_data');
		}

		try {
			idemailwiz_process_job_from_sync_queue($job['jobId']);
		} catch (Exception $e) {
			 wiz_log("Error processing job {$job['jobId']}: " . $e->getMessage());
			 // Optionally mark the job as failed here if needed
		}

		// Reschedule quickly to check for the next job
		wiz_log("Job processing finished (or failed). Rescheduling check in 5 seconds.");
		wp_schedule_single_event(time() + 5, 'idemailwiz_sync_engagement_data');

	} else {
		// No ready jobs found
		wiz_log("No ready jobs found in the queue.");

		// Clear any lingering single schedule
		$timestamp = wp_next_scheduled('idemailwiz_sync_engagement_data');
		if ($timestamp && !wp_get_schedule('idemailwiz_sync_engagement_data')) { // Check if it's a single event
			wp_unschedule_event($timestamp, 'idemailwiz_sync_engagement_data');
		}

		// Ensure the main recurring schedule is active
		if (!wp_next_scheduled('idemailwiz_sync_engagement_data')) {
			wiz_log("Recurring schedule not found. Re-adding 'every_two_hours' schedule.");
			wp_schedule_event(time() + (2 * HOUR_IN_SECONDS), 'every_two_hours', 'idemailwiz_sync_engagement_data');
		}
	}

	// Release the lock
	delete_transient('idemailwiz_single_job_processor_running');
}

// Schedule the sync queue cleanup
add_action('idemailwiz_sync_queue_cleanup_event', 'idemailwiz_cleanup_sync_queue');
if (!wp_next_scheduled('idemailwiz_sync_queue_cleanup_event')) {
	wp_schedule_event(time(), 'every_thirty_minutes', 'idemailwiz_sync_queue_cleanup_event');
}

// Schedule the queue fill process, if turned on
add_action('wp_loaded', 'idemailwiz_schedule_queue_fill_process');

function idemailwiz_schedule_queue_fill_process()
{
	$wizSettings = get_option('idemailwiz_settings');
	$engSync = $wizSettings['iterable_engagement_data_sync_toggle'] ?? 'off';

	if ($engSync == 'on') {
		if (!wp_next_scheduled('idemailwiz_fill_sync_queue')) {
			wp_schedule_event(time(), 'twicedaily', 'idemailwiz_fill_sync_queue');
		}
	} else {
		$timestamp = wp_next_scheduled('idemailwiz_fill_sync_queue');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'idemailwiz_fill_sync_queue');
			wiz_log("Engagement data sync fill was deactivated and unscheduled.");
		}
	}
}

add_action('idemailwiz_fill_sync_queue', 'idwiz_export_and_store_jobs_to_sync_queue');

// Ajax handler for manual sync form on single campaign page
add_action('wp_ajax_handle_single_triggered_sync', 'handle_single_triggered_sync');
function handle_single_triggered_sync()
{
	check_ajax_referer('id-general', 'security');

	$campaignId = $_POST['campaignId'];
	$startAt = $_POST['startDate'] ?? null;
	$endAt = $_POST['endDate'] ?? null;
	$metricTypes = ['send', 'open', 'click', 'unSubscribe', 'complaint', 'bounce', 'sendSkip'];

	// Call the maybe_add_to_sync_queue function
	maybe_add_to_sync_queue([$campaignId], $metricTypes, $startAt, $endAt, 100);

	wp_send_json_success('Sync queued successfully.');
}



function maybe_add_to_sync_queue($campaignIds, $metricTypes, $startAt = null, $endAt = null, $priority = 1)
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	foreach ($campaignIds as $campaignId) {
		$campaign = get_idwiz_campaign($campaignId);
		$syncTypes = array_map(function ($metricType) use ($campaign) {
			return strtolower($campaign['type']) . '_' . $metricType . 's';
		}, $metricTypes);

		// Create placeholders for the IN clause
		$placeholders = implode(',', array_fill(0, count($syncTypes), '%s'));
		$existingJobs = $wpdb->get_results(
			call_user_func_array(
				array($wpdb, 'prepare'),
				array_merge(
					array("SELECT syncType FROM $sync_jobs_table_name WHERE campaignId = %d AND syncType IN ($placeholders) AND syncStatus = 'pending'"),
					array($campaignId),
					$syncTypes
				)
			)
		);

		$existingSyncTypes = array_column($existingJobs, 'syncType');
		$newSyncTypes = array_diff($syncTypes, $existingSyncTypes);

		if (!empty($newSyncTypes)) {
			// Add new sync jobs for the missing syncTypes
			idwiz_export_and_store_jobs_to_sync_queue([$campaignId], null, ['Email', 'SMS'], array_map(function ($syncType) {
				return explode('_', rtrim($syncType, 's'))[1];
			}, $newSyncTypes), $startAt, $endAt, $priority);
		}

		if (!empty($existingSyncTypes)) {
			// Update the priority of existing pending jobs
			$wpdb->update(
				$sync_jobs_table_name,
				['syncPriority' => $priority],
				['campaignId' => $campaignId, 'syncType' => $existingSyncTypes, 'syncStatus' => 'pending']
			);
			if ($wpdb->rows_affected) {
				return true;
			}
		}
	}
	return false;
}






function idwiz_export_and_store_jobs_to_sync_queue($campaignIds = null, $campaignTypes = ['Blast', 'Triggered', 'FromWorkflow'], $messageMediums = ['Email', 'SMS'], $metricTypes = ['send', 'open', 'click', 'unSubscribe', 'sendSkip', 'bounce', 'complaint'], $exportStart = null, $exportEnd = null, $priority = 1, $batchSize = 10)
{
	// Clean up the sync queue to get rid of old jobs
	idemailwiz_cleanup_sync_queue();

	$localTimezone = new DateTimeZone('America/Los_Angeles');
	$utcTimezone = new DateTimeZone('UTC');

	if (!$exportStart) {
		$exportStart = new DateTime('2021-11-01', $localTimezone);
	} elseif (is_string($exportStart)) {
		$exportStart = DateTime::createFromFormat('Y-m-d', $exportStart, $localTimezone);
	}

	if (!$exportEnd) {
		$exportEnd = new DateTime('tomorrow', $localTimezone);
	} elseif (is_string($exportEnd)) {
		$exportEnd = DateTime::createFromFormat('Y-m-d', $exportEnd, $localTimezone);
		$exportEnd->setTime(23, 59, 59); // Set to end of day
	}

	// Convert to UTC and format
	$exportStart->setTimezone($utcTimezone);
	$exportEnd->setTimezone($utcTimezone);

	$exportStartFormatted = $exportStart->format('Y-m-d H:i:s');
	$exportEndFormatted = $exportEnd->format('Y-m-d H:i:s');

	// If campaignIds are provided, override the campaignTypes
	if (!empty($campaignIds)) {
		$campaignIds = array_unique($campaignIds);
		$campaignTypes = [];
	}

	// Get the appropriate campaigns based on the provided parameters or default criteria
	$campaigns = get_campaigns_to_sync($campaignIds, $campaignTypes, $messageMediums);

	if (empty($campaigns)) {
		wiz_log('No campaigns found to export jobs for.');
		// Cancel next scheduled event and reschedule for 30 minutes later
		wp_clear_scheduled_hook('idemailwiz_fill_sync_queue');
		wp_schedule_single_event(time() + 30 * MINUTE_IN_SECONDS, 'idemailwiz_fill_sync_queue');
		return;
	}

	// Process campaigns in batches
	$campaignBatches = array_chunk($campaigns, $batchSize);
	$totalBatches = count($campaignBatches);

	// Schedule the first batch processing event
	wp_schedule_single_event(time(), 'idemailwiz_process_campaign_export_batch', [$campaignBatches, 0, $totalBatches, $metricTypes, $exportStartFormatted, $exportEndFormatted, $priority]);
}

add_action('idemailwiz_process_campaign_export_batch', 'idemailwiz_process_campaign_export_batch', 10, 7);
function idemailwiz_process_campaign_export_batch($campaignBatches, $currentBatch, $totalBatches, $metricTypes, $exportStart, $exportEnd, $priority)
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	$campaignBatch = $campaignBatches[$currentBatch];
	wiz_log("Processing batch " . ($currentBatch + 1) . " of $totalBatches...");

	foreach ($campaignBatch as $campaign) {
		$campaignId = $campaign['id'];
		$messageMedium = strtolower($campaign['messageMedium']);
		$campaignType = strtolower($campaign['type']);

		// Check for existing send records for this campaign
		$campaignSends = get_engagement_data_by_campaign_id($campaignId, $campaignType, 'send');

		foreach ($metricTypes as $metricType) {			

			// If sends already exist, we skip them (plus bounced and sendSkips)
			if ($campaignSends) {

				// Skip blast campaign send, sendSkip, and bounce records if the campaign's end time is more than 1 day ago
				// Prevents exporting unneccesary and/or huge jobs from Iterable
				$campaignEnd = $campaign['endedAt'];
				$campaignEndTimestamp = floor($campaignEnd / 1000);  // convert milliseconds to seconds

				if (
					($campaignType == 'blast')
					&& (in_array($metricType, ['send', 'bounce', 'sendSkip']))
					&& ($messageMedium == 'email')
					&& ($campaignEndTimestamp < strtotime('-1 day'))
					&& ($priority == 1) // higher priorities indicate manual syncs
				) {
					continue;  // skip processing
				}
			}

			// For SMS we only have sends and clicks
			if (($messageMedium == 'sms') && !in_array($metricType, ['send', 'click'])) {
				continue;
			}

			// Build syncType name to match database name
			$syncType = $campaignType . '_' . strtolower($metricType) . 's'; // matches database name after $wpdb->prefix . 'idemailwiz_'

			// Check if a queued job already exists for this campaign and metric type
			$existingJob = $wpdb->get_row("SELECT * FROM $sync_jobs_table_name WHERE campaignId = $campaignId AND syncType = '$syncType'");
			if ($existingJob) {
				continue;
			}

			// Schedule a single cron job to export data and add a row to the queue
			wp_schedule_single_event(time() + 1, 'idemailwiz_export_and_queue_single_job_event', [
				$campaignId, $messageMedium, $metricType, $syncType, $exportStart, $exportEnd, $priority
			]);
		}
	}

	// If there are more batches to process, schedule the next batch processing event
	if ($currentBatch + 1 < $totalBatches) {
		wp_schedule_single_event(time(), 'idemailwiz_process_campaign_export_batch', [$campaignBatches, $currentBatch + 1, $totalBatches, $metricTypes, $exportStart, $exportEnd, $priority]);
	} else {
		// If all batches have been processed, schedule the sync process
		wiz_log("Batches processing complete, sync starting in 30 seconds...");

		// Check for upcoming sync event in next 30 seconds, and if later or not scheduled, schedule it
		$nextSyncCheck = wp_next_scheduled('idemailwiz_sync_engagement_data');
		if ($nextSyncCheck && $nextSyncCheck > time() + 30) {
			wp_unschedule_event($nextSyncCheck, 'idemailwiz_sync_engagement_data');
			wp_schedule_single_event(time() + 30, 'idemailwiz_sync_engagement_data');
		}
	}
}


add_action('idemailwiz_export_and_queue_single_job_event', 'idemailwiz_export_and_queue_single_job', 10, 7);
// Callback function for the single cron job
function idemailwiz_export_and_queue_single_job($campaignId, $messageMedium, $metricType, $syncType, $exportStart, $exportEnd, $priority = 1)
{
	$apiData['url'] = "https://api.iterable.com/api/export/start";

	// Build dataTypeName in the format {messageMedium}{MetricType}
	$dataTypeName = $messageMedium . ucfirst($metricType);

	$apiData['args'] = [
		"outputFormat" => "application/x-json-stream",
		"dataTypeName" => $dataTypeName,
		"delimiter" => ",",
		"onlyFields" => "createdAt,userId,campaignId,templateId,messageId,email",
		"campaignId" => (int) $campaignId,
		"startDateTime" => $exportStart,
		"endDateTime" => $exportEnd,
	];

	try {
		$scheduledJob = idemailwiz_iterable_curl_call($apiData['url'], $apiData['args'], 'POST');
		//$scheduledJob = idemailwiz_iterable_curl_call($apiData['url'], $apiData['args']);
		if (!isset($scheduledJob['response']['jobId'])) {
			throw new Exception("No job ID received from API.");
		}
		idemailwiz_add_sync_queue_row(
			$scheduledJob,
			$campaignId,
			$syncType,
			$priority
		);
	} catch (Exception $e) {
		wiz_log("Error starting export job for campaign $campaignId: " . $e->getMessage());
		return;
	}
}



function idemailwiz_add_sync_queue_row($scheduledJob, $campaignId, $syncType, $priority = 1, $retries = 3)
{
	global $wpdb;

	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	if (isset($scheduledJob['response']['jobId'])) {
		$iterableJobId = $scheduledJob['response']['jobId'];

		// Create a DateTime object representing the current time
		$currentTime = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));

		$deleteAfter = $currentTime->modify('+ 12 hours');
		$retryAfter = $currentTime->modify('+1 hour');

		$deleteAfter = $deleteAfter->format('Y-m-d H:i:s');

		if ($priority > 1) {
			$retryAfter = $currentTime->format('Y-m-d H:i:s');
		} else {
			$retryAfter = $retryAfter;
		}

		$retry = 0;
		while ($retry < $retries) {
			try {
				// Start a transaction
				$wpdb->query('START TRANSACTION');

				// Insert a new row into the sync queue
				$result = $wpdb->insert($sync_jobs_table_name, [
					'jobId' => $iterableJobId,
					'campaignId' => $campaignId,
					'jobState' => '',
					'retryAfter' => $retryAfter,
					'deleteAfter' => $deleteAfter,
					'syncType' => $syncType,
					'syncStatus' => 'pending',
					'syncPriority' => $priority,
				]);

				if ($result === false) {
					// If the insertion fails, roll back the transaction
					$wpdb->query('ROLLBACK');
					throw new Exception("Error inserting sync queue row for jobId: $iterableJobId. Error: " . $wpdb->last_error);
				}

				// If the insertion is successful, commit the transaction
				$wpdb->query('COMMIT');
				return $result;
			} catch (Exception $e) {
				// Log the error
				wiz_log("Error inserting sync queue row (retry $retry): " . $e->getMessage());
				// Roll back the transaction if an exception occurs
				$wpdb->query('ROLLBACK');

				$retry++;
				if ($retry < $retries) {
					// Delay before retrying (adjust the delay as needed)
					usleep(100000); // Sleep for 100 milliseconds
				}
			}
		}

		// If all retries have been exhausted, log an error
		wiz_log("Failed to insert sync queue row after $retries retries for jobId: $iterableJobId");
	}

	return false;
}




function idemailwiz_process_job_from_sync_queue($jobId = null)
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	if (!$jobId) {
		$job = $wpdb->get_row("SELECT * FROM $sync_jobs_table_name WHERE syncStatus = 'pending' ORDER BY syncPriority ASC, retryAfter ASC LIMIT 1", ARRAY_A);
	} else {
		$job = $wpdb->get_row("SELECT * FROM $sync_jobs_table_name WHERE jobId = $jobId LIMIT 1", ARRAY_A);
	}

	if (empty($job)) {
		wiz_log("Could not find job in queue with id $jobId");
		// Add return here if job is not found
		return;
	}

	$retryAfter = new DateTimeImmutable($job['retryAfter'], new DateTimeZone('America/Los_Angeles'));

	$updateSyncing = $wpdb->update(
		$sync_jobs_table_name,
		['syncStatus' => 'syncing', 'retryAfter' => $retryAfter->format('Y-m-d H:i:s')],
		['jobId' => $jobId]
	);

	if ($updateSyncing === false) {
		wiz_log("Error updating sync status to syncing for job $jobId");
		return;
	}

	$jobId = $job['jobId'];
	$startAfter = $job['startAfter'] ? '?startAfter=' . $job['startAfter'] : '';
	$table_name = $wpdb->prefix . 'idemailwiz_' . $job['syncType'];

	// wiz_log("Job $jobId: Checking Iterable API for export status. URL: https://api.iterable.com/api/export/\" . $jobId . \"/files{$startAfter}"); // REMOVED - Too verbose
	// Wrap the API call in a try-catch block
	try {
		$jobApiResponse = idemailwiz_iterable_curl_call("https://api.iterable.com/api/export/" . $jobId . "/files{$startAfter}");
		// Log components separately instead of json_encode on the whole object
		// if (isset($jobApiResponse['httpCode'])) { // REMOVED - Only log errors/state
		// 	wiz_log("Job $jobId: Received HTTP Code: " . $jobApiResponse['httpCode']);
		// } else {
		// 	wiz_log("Job $jobId: HTTP Code not set in response.");
		// }
		if (isset($jobApiResponse['response'])) {
			// wiz_log("Job $jobId: Received 'response' key. Type: " . gettype($jobApiResponse['response'])); // REMOVED - Too verbose
			// Try logging jobState directly if 'response' is an array
			if (is_array($jobApiResponse['response']) && isset($jobApiResponse['response']['jobState'])) {
				 wiz_log("Job $jobId: Job State from API: " . $jobApiResponse['response']['jobState']); // Keep job state log
			} elseif (is_array($jobApiResponse['response'])) {
				 wiz_log("Job $jobId: 'jobState' key not found in response array.");
			}
			// Avoid logging potentially huge file lists for now
			// wiz_log("Job $jobId: Raw 'response' content: " . print_r($jobApiResponse['response'], true));
		} else {
			wiz_log("Job $jobId: 'response' key not set in response.");
		}
		//wiz_log("Job $jobId: Received API response: \" . json_encode($jobApiResponse)); // Replaced this line
	} catch (Exception $e) {
		wiz_log("Job $jobId: Exception during API call: " . $e->getMessage() . ". Requeueing job.");
		// Set job status back to pending and schedule retry
		$retryTimestamp = date('Y-m-d H:i:s', strtotime('+15 minutes')); // Retry in 15 minutes
		$wpdb->update(
			$sync_jobs_table_name,
			['syncStatus' => 'pending', 'retryAfter' => $retryTimestamp],
			['jobId' => $jobId]
		);
		return; // Exit processing for this job
	}

	// --- Add more robust checks after the API call --- 
	// Ensure the response variable is set and is an array
	if (!isset($jobApiResponse) || !is_array($jobApiResponse)) {
		wiz_log("Job $jobId: API Response variable is not set or not an array after call. Requeueing.");
		$wpdb->update($sync_jobs_table_name, ['syncStatus' => 'pending', 'retryAfter' => date('Y-m-d H:i:s', strtotime('+10 minutes'))], ['jobId' => $jobId]);
		return;
	}

	// Ensure the HTTP status code is set and is 200
	if (!isset($jobApiResponse['http_code']) || $jobApiResponse['http_code'] !== 200) {
		$loggedCode = isset($jobApiResponse['http_code']) ? $jobApiResponse['http_code'] : 'Not Set';
		wiz_log("Job $jobId: API call did not return HTTP 200 (Actual: $loggedCode). Requeueing.");
		$wpdb->update($sync_jobs_table_name, ['syncStatus' => 'pending', 'retryAfter' => date('Y-m-d H:i:s', strtotime('+10 minutes'))], ['jobId' => $jobId]);
		return; 
	}

	if (!isset($jobApiResponse['response']['jobState'])) {
		wiz_log("Job $jobId: API response structure missing 'jobState'. Requeueing.");
		$wpdb->update($sync_jobs_table_name, ['syncStatus' => 'pending', 'retryAfter' => date('Y-m-d H:i:s', strtotime('+10 minutes'))], ['jobId' => $jobId]);
		return; 
	}
	
	// --- Checks passed, proceed with job state logic --- 

	$batchInsertData = [];
	$lastProcessedFile = '';
	$insertBatchSize = 1000; // Define batch size for inserts
	$recordsInCurrentBatch = 0;
	$totalInsertedCount = 0;

	// Check jobState before attempting to process files
	if ($jobApiResponse['response']['jobState'] !== 'completed') {
		wiz_log("Job $jobId: Iterable job state is not 'completed' (State: " . $jobApiResponse['response']['jobState'] . "). Requeueing for later check."); // Added Log
		// Create a DateTime object representing the current time
		$currentTime = new DateTime('now', new DateTimeZone('UTC'));

		// Add 5 minutes to the current time
		$currentTime->modify('+5 minutes');

		// Set the timezone to Pacific Time (PT)
		$currentTime->setTimezone(new DateTimeZone('America/Los_Angeles'));

		// Format the time in a suitable format
		$retryAfter = $currentTime->format('Y-m-d H:i:s');

		// Update the database with the retryAfter timestamp
		$updatRetryAfter = $wpdb->update(
			$sync_jobs_table_name,
			[
				'retryAfter' => $retryAfter,
			],
			[
				'jobId' => $jobId,
			]
		);
		if ($updatRetryAfter === false) {
			wiz_log("Error updating retryAfter timestamp for job $jobId. Error: " . $wpdb->last_error);
		}

		// Change status back to pending
		wiz_log("Job $jobId: Setting status back to 'pending'."); // Added Log
		$updatePending = $wpdb->update(
			$sync_jobs_table_name,
			['syncStatus' => 'pending'],
			['jobId' => $jobId]
		);
		if ($updatePending === false) {
			wiz_log("Error updating sync status back to pending for requeued job $jobId");
		}

		return; // Exit processing since the job is not completed
	}


	// --- Job is completed, proceed with file processing ---
	wiz_log("Job $jobId: Job state is 'completed'. Processing files..."); // Added Log

	foreach ($jobApiResponse['response']['files'] as $file) {
		// wiz_log("Job $jobId: Attempting to download file: \" . $file['url']); // REMOVED per-file log
		$jsonResponse = @file_get_contents($file['url']);
		if ($jsonResponse === false) {
			wiz_log("Failed to retrieve file content for job $jobId. File URL: " . $file['url']);
			continue;
		}

		$lines = explode("\n", $jsonResponse);
		if (!empty($lines) && (count(array_filter($lines)) > 0)) {
			foreach ($lines as $line) {
				if (trim($line) === '') {
					continue;
				}
				$record = json_decode($line, true);
				if (json_last_error() !== JSON_ERROR_NONE) {
					wiz_log("Failed to decode JSON for job $jobId. Line: $line. Error: " . json_last_error_msg()); // Added Log with error
					continue;
				}

				if (!is_array($record) || empty($record) || !isset($record['messageId'])) {
					continue;
				}

				$createdAt = new DateTime($record['createdAt'], new DateTimeZone('UTC'));

				$msTimestamp = (int) ($createdAt->format('U.u') * 1000);

				// Log the extracted values before preparing for insert
				//$logCampaignId = $record['campaignId'] ?? 'NULL'; // Keep logging minimal for performance
				//$logTemplateId = $record['templateId'] ?? 'NULL';
				//$logUserId = $record['userId'] ?? 'NULL';
				//wiz_log("Job $jobId: Preparing record - MsgID: {$record['messageId']}, CampID: {$logCampaignId}, TmplID: {$logTemplateId}, UserID: {$logUserId}, Timestamp: {$msTimestamp}"); // REMOVED

				// Prepare data for insertion
				$query_args = array_merge(
					["(%s, %s, %d, %d, %d)"],
					[
						$record['messageId'],
						$record['userId'] ?? null,
						$record['campaignId'] ?? null,
						$record['templateId'] ?? null,
						$msTimestamp,
					]
				);
				$batchInsertData[] = call_user_func_array([$wpdb, 'prepare'], $query_args);
				$recordsInCurrentBatch++;

				// Insert batch if size limit reached
				if ($recordsInCurrentBatch >= $insertBatchSize) {
					// wiz_log("Job $jobId: Inserting batch of $recordsInCurrentBatch records..."); // REMOVED per-batch log
					$columns = ['messageId', 'userId', 'campaignId', 'templateId', 'startAt'];
					$placeholders = implode(", ", $batchInsertData);
					$insertQuery = "INSERT IGNORE INTO $table_name (" . implode(", ", $columns) . ") VALUES " . $placeholders;
					$insertResult = $wpdb->query($insertQuery);
					
					if ($insertResult === false) {
						wiz_log("Job $jobId: Error inserting batch records. Error: " . $wpdb->last_error);
						// Decide how to handle batch failure - skip job? Mark as failed? For now, log and continue potentially skipping records
					} else {
						$totalInsertedCount += $insertResult; 
						wiz_log("Job $jobId: Inserted $insertResult records in this batch.");
					}
					// Reset batch
					$batchInsertData = [];
					$recordsInCurrentBatch = 0;
				}
			}
		} else {
			wiz_log("Downloaded file is empty for job $jobId.");
			continue;
		}

		$lastProcessedFile = $file['file'];

		//Update lastWizSync in campaigns database
		$campaignId = $job['campaignId'];
		$campaign = get_idwiz_campaign($campaignId);
		if ($campaign) {
			$updateResult = $wpdb->update(
				$wpdb->prefix . 'idemailwiz_campaigns',
				[
					'lastWizSync' => date('Y-m-d H:i:s'),
				],
				[
					'id' => $campaignId,
				]
			);
			if ($updateResult === false) {
				wiz_log("Error updating lastWizSync for campaign $campaignId in job $jobId. Error: " . $wpdb->last_error); // Corrected logging
			}
		}
	}

	// Insert any remaining records in the last batch
	if (!empty($batchInsertData)) {
		// wiz_log("Job $jobId: Inserting final batch of $recordsInCurrentBatch records..."); // REMOVED final batch log
		$columns = ['messageId', 'userId', 'campaignId', 'templateId', 'startAt'];
		$placeholders = implode(", ", $batchInsertData);
		$insertQuery = "INSERT IGNORE INTO $table_name (" . implode(", ", $columns) . ") VALUES " . $placeholders;
		$insertResult = $wpdb->query($insertQuery);

		if ($insertResult === false) {
			wiz_log("Job $jobId: Error inserting final batch records. Error: " . $wpdb->last_error);
		} else {
			$totalInsertedCount += $insertResult;
			wiz_log("Job $jobId: Inserted $insertResult records in final batch.");
		}
	}

	// Log total inserted count for the job
	wiz_log("Job $jobId: Finished processing files. Total unique records inserted/updated: $totalInsertedCount"); // Adjusted wording slightly

	if ($jobApiResponse['response']['exportTruncated'] === true && (!empty($lines) && (count(array_filter($lines)) > 0))) {
		// Update the row to reflect the requeued job
		$updateRequeued = $wpdb->update(
			$sync_jobs_table_name,
			['startAfter' => $lastProcessedFile, 'syncStatus'  => 'requeued', 'retryAfter' => current_time('mysql')],
			['jobId' => $jobId]
		);
		if ($updateRequeued === false) {
			wiz_log("Error updating the requeue job status for job $jobId. Error: " . $wpdb->last_error);
		} else {
			wiz_log("Export truncated for job $jobId. Requeueing..");
		}
	} else {

		// Mark job as finished and schedule deleteAfter for 1 hour later
		$deleteAfter = date('Y-m-d H:i:s', strtotime('1 hour', current_time('timestamp')));
		$updateFinished = $wpdb->update(
			$sync_jobs_table_name,
			['startAfter' => null, 'syncStatus' => 'finished', 'deleteAfter' => $deleteAfter],
			['jobId' => $jobId]
		);
		if ($updateFinished === false) {
			wiz_log("Error marking job as finished for job $jobId. Error: " . $wpdb->last_error);
		}
	}

	// --- Update campaign startAt based on latest send --- 
	$latestSendTimestamp = null; // Changed from earliest
	$isSendJob = (strpos($job['syncType'], '_sends') !== false); // Check if it's a send job

	if ($isSendJob && !empty($batchInsertData)) { // Only proceed if it was a send job with data processed
		// Re-process lines to find max timestamp
		foreach ($jobApiResponse['response']['files'] as $file) {
			$jsonResponse = @file_get_contents($file['url']);
			if ($jsonResponse === false) continue;
			$lines = explode("\n", $jsonResponse);
			foreach ($lines as $line) {
				if (trim($line) === '') continue;
				$record = json_decode($line, true);
				if (json_last_error() !== JSON_ERROR_NONE || !is_array($record) || !isset($record['createdAt'])) continue;

				$createdAt = new DateTime($record['createdAt'], new DateTimeZone('UTC'));
				$msTimestamp = (int) ($createdAt->format('U.u') * 1000);

				if ($latestSendTimestamp === null || $msTimestamp > $latestSendTimestamp) { // Changed comparison to >
					$latestSendTimestamp = $msTimestamp; // Changed variable name
				}
			}
		}
	}

	if ($latestSendTimestamp !== null && $jobApiResponse['response']['exportTruncated'] !== true) {
		$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
		$campaignId = $job['campaignId'];
		$existingCampaign = $wpdb->get_row($wpdb->prepare("SELECT startAt FROM $campaigns_table WHERE id = %d", $campaignId), ARRAY_A);

		if ($existingCampaign && ($existingCampaign['startAt'] === null || $latestSendTimestamp > $existingCampaign['startAt'])) { // Changed comparison to >
			wiz_log("Job $jobId: Updating startAt for campaign $campaignId to latest send time: $latestSendTimestamp"); // Changed log message
			$updateResult = $wpdb->update(
				$campaigns_table,
				['startAt' => $latestSendTimestamp], // Changed variable name
				['id' => $campaignId]
			);
			if ($updateResult === false) {
				 wiz_log("Job $jobId: Error updating startAt for campaign $campaignId: " . $wpdb->last_error);
			}
		} elseif (!$existingCampaign) {
			 wiz_log("Job $jobId: Could not find campaign $campaignId in campaigns table to update startAt.");
		}
	}
	// --- End campaign startAt update --- 
}


function get_campaigns_to_sync(
	$campaignIds = null,
	$campaignTypes = ['Blast', 'Triggered', 'FromWorkflow'],
	$messageMediums = ['Email', 'SMS']
) {

	$campaigns = [];
	$triggeredCampaigns = [];
	$blastCampaigns = [];

	if (!empty($campaignIds)) {
		$args = [
			'campaignIds' => $campaignIds,
		];

		$campaigns = get_idwiz_campaigns($args);
	} else {
		$args = [
			'messageMedium' => $messageMediums,
		];
		if (in_array('Triggered', $campaignTypes) || in_array('FromWorkflow', $campaignTypes)) {
			$args = [
				'type' => ['Triggered', 'FromWorkflow'],
				'campaignState' => 'Running',
			];

			$triggeredCampaigns = get_idwiz_campaigns($args);
		}

		if (in_array('Blast', $campaignTypes)) {

			// Sync blast campaign data for max 7 days
			$blastStartDate = date('Y-m-d', strtotime('-7 days'));

			$args = [
				'type' => 'Blast',
				'campaignState' => 'Finished',
				'startAt_start' => $blastStartDate
			];

			$blastCampaigns = get_idwiz_campaigns($args);
		}
		$campaigns = array_merge($blastCampaigns, $triggeredCampaigns);
	}

	return $campaigns;
}

// Function to clean up old sync jobs from the queue
function idemailwiz_cleanup_sync_queue()
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	$currentTime = current_time('mysql');

	$wpdb->query("DELETE FROM $sync_jobs_table_name WHERE deleteAfter <= '$currentTime'");
}

function get_idwiz_sync_jobs($syncStatus = 'pending', $campaignId = null)
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';
	if ($campaignId) {
		$campaignId = (int) $campaignId;
		$jobs = $wpdb->get_results("SELECT * FROM $sync_jobs_table_name WHERE syncStatus = '$syncStatus' AND campaignId = $campaignId", ARRAY_A);
	} else {
		$jobs = $wpdb->get_results("SELECT * FROM $sync_jobs_table_name WHERE syncStatus = '$syncStatus'", ARRAY_A);
	}

	return $jobs;
}

// Ajax handler for manual sync form on sync station page
add_action('wp_ajax_handle_sync_station_sync', 'handle_sync_station_sync');
function handle_sync_station_sync()
{
	check_ajax_referer('id-general', 'security');

	// Extract the form fields from the POST data
	parse_str($_POST['formFields'], $formFields);

	

	// Check for sync types
	if (empty($formFields['syncTypes'])) {
		wp_send_json_error('No sync types were received.');
		return;
	}

	// Check for dates
	$syncStartAt = $formFields['startAt'] ?? null;
	$syncEndAt = $formFields['endAt'] ?? null;

	// If campaigns are not sent, decide which ones to sync based on dates sent
	if (empty($formFields['campaignIds'])) {

		$campaigns = get_idwiz_campaigns(['type' => 'Blast', 'fields' => 'id', 'startAt_start' => $syncStartAt, 'startAt_end' => $syncEndAt ?? date('Y-m-d')]);

		$campaignIds = array_column($campaigns, 'id');
	} else {
		// Extract campaign IDs, if provided
		$campaignIds = explode(',', $formFields['campaignIds']);
	}

	// wp_send_json_success($campaignIds);
	// return; 

	idemailwiz_cleanup_sync_queue();

	// Initiate the sync sequence
	if (in_array('blastMetrics', $formFields['syncTypes'])) {
		$blastSyncInProgress = get_transient('idemailwiz_blast_sync_in_progress');
		if ($blastSyncInProgress) {
			$syncResult = ['error' => 'Another sync is already in progress!'];
		} else {
			$syncResult = idemailwiz_sync_non_triggered_metrics($campaignIds);
		}
		$syncResult = idemailwiz_sync_non_triggered_metrics($campaignIds);
		unset($formFields['syncTypes'][array_search('blastMetrics', $formFields['syncTypes'])]);
	} else {
		$syncResult = maybe_add_to_sync_queue($campaignIds, $formFields['syncTypes'], $syncStartAt, $syncEndAt, 100);
	}


	if ($syncResult === false || isset($syncResult['error'])) {
		wp_send_json_error('Sync sequence aborted: Another sync is still in progress or there was an error.');
	} else {
		wp_send_json_success('Sync sequence successfully initiated.');
	}
}

//requeue_retry_afters();
function requeue_retry_afters()
{
	global $wpdb;

	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';
	// Set any 'syncing' jobs back to 'pending'
	$wpdb->update($sync_jobs_table_name, [
		'syncStatus' => 'pending',
	], [
		'syncStatus' => 'syncing',
	]);

	// Delete the idemailwiz_blast_sync_in_progress transiet
	delete_transient('idemailwiz_blast_sync_in_progress');
	
	// Set retryAfter to right now for all pending jobs
	$currentTime = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));
	$result = $wpdb->update($sync_jobs_table_name, [
		'retryAfter' => $currentTime->format('Y-m-d H:i:s'),
	], [
		'syncStatus' => 'pending',
	]);

	wp_schedule_single_event(time(), 'idemailwiz_sync_engagement_data');

	return $result;
}

// function sync_single_campaign_data($campaignId, $exportStart = null, $exportEnd = null)
// {
// 	global $wpdb;
// 	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';
// 	// check for existing pending jobs in queue
// 	$jobs = get_idwiz_sync_jobs('pending', $campaignId);
// 	if (count($jobs) > 0) {
// 		foreach ($jobs as $job) {
// 			// Process this job immediately
// 			wiz_log('Campaign found in queue, prioritizing...');
// 			$wpdb->update(
// 				$sync_jobs_table_name,
// 				['priority' => 100, 'retryAfter' => 0],
// 				['jobId' => $job['jobId']]
// 			);

// 			if (!wp_next_scheduled('idemailwiz_sync_engagement_data')) {
// 				wp_schedule_single_event(time() + 1, 'idemailwiz_sync_engagement_data');
// 			}
// 		}
// 	} else {
// 		wiz_log('Campaign not in queue, queuing jobs...');
// 		$campaign = get_idwiz_campaign($campaignId);
// 		$campaignTypes = [$campaign['type']];
// 		$messageMediums = [$campaign['messageMedium']];
// 		$metricTypes = ['send', 'open', 'click', 'bounce', 'unsubscribe', 'sendskip', 'complaint'];

// 		// Add jobs to queue with high priority
// 		wiz_log("Adding jobs for campaign $campaignId to queue...");
// 		idwiz_export_and_store_jobs_to_sync_queue([$campaignId], $campaignTypes, $messageMediums, $metricTypes, $exportStart, $exportEnd, 100, 1);

// 		wiz_log('Jobs queued successfully. Sync should occur within 2 minutes.');
// 		if (!wp_next_scheduled('idemailwiz_sync_engagement_data')) {
// 			wp_schedule_single_event(time() + 60, 'idemailwiz_sync_engagement_data');
// 		}
// 	}
// }

function idemailwiz_schedule_weekly_cron()
{
	if (!wp_next_scheduled('idemailwiz_weekly_send_sync')) {
		wp_schedule_event(strtotime('next Monday 1am'), 'weekly_monday_morning', 'idemailwiz_weekly_send_sync');
	}
}
add_action('init', 'idemailwiz_schedule_weekly_cron');

add_action('idemailwiz_weekly_send_sync', 'idemailwiz_sync_sends_weekly_cron');

function idemailwiz_sync_sends_weekly_cron()
{
	wiz_log('Starting weekly send sync...');
	$currentDate = new DateTime();
	$currentDate->modify('previous Monday');
	$year = $currentDate->format('Y');
	$week = $currentDate->format('W');
	$syncSends = idemailwiz_sync_sends_by_week($year, $week);
	if ($syncSends > 0) {
		wiz_log('Weekly send sync complete.');
	} else {
		wiz_log('Weekly send sync failed.');
	}
}


function idemailwiz_sync_sends_by_week($year, $week)
{
	// if week is 1 digit, convert it to 2 with a zero in front
	if (strlen($week) == 1) {
		$week = '0' . $week;
	}
	global $wpdb;
	$return = 0;
	$sends_by_week_table = $wpdb->prefix . 'idemailwiz_sends_by_week';

	// Calculate the start and end dates for the given year and week
	$startDate = date(
		'Y-m-d',
		strtotime("$year-W$week-1")
	);
	$endDate = date(
		'Y-m-d',
		strtotime("$year-W$week-7")
	);

	// Calculate the month based on the start date
	$month = date('n', strtotime($startDate));

	// Make the API call to Iterable to fetch send data for the specified week
	$sendDataGenerator = idemailwiz_fetch_sends(null, $startDate, $endDate);

	// Process the send data and prepare it for insertion into the sends_by_week table
	// Process the send data and prepare it for insertion into the sends_by_week table
	$userSendCounts = [];
	$userIdsBySendCount = [];

	foreach ($sendDataGenerator as $send) {
		$userId = $send['userId'];
		if (
			$userId === null || $userId === '' || $userId == '0'
		) {
			continue;
		}

		// Increment count, or start new count if it doesn't exist
		if (isset($userSendCounts[$userId])) {
			$userSendCounts[$userId]++;
		} else {
			$userSendCounts[$userId] = 1;
		}
	}

	// Aggregate the user send counts into cohorts and store user IDs for each send count
	foreach ($userSendCounts as $userId => $sendCount) {
		if (!isset($userIdsBySendCount[$sendCount])) {
			$userIdsBySendCount[$sendCount] = [];
		}
		$userIdsBySendCount[$sendCount][] = $userId;
	}

	// Insert or update the records in the sends_by_week table
	foreach ($userIdsBySendCount as $sends => $userIds) {
		// Skip cohorts with more than 25 sends (should exclude seed list people)
		if ($sends > 25) {
			continue;
		}

		$totalUsers = count($userIds);
		$serializedUserIds = serialize($userIds);

		// Check if a record already exists for the given year, month, week, and sends
		$existingRecord = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $sends_by_week_table WHERE year = %d AND month = %d AND week = %d AND sends = %d",
			$year,
			$month,
			$week,
			$sends
		));

		if ($existingRecord) {
			// Update the existing record
			$return = $wpdb->update(
				$sends_by_week_table,
				['total_users' => $totalUsers, 'userIds' => $serializedUserIds],
				['year' => $year, 'month' => $month, 'week' => $week, 'sends' => $sends]
			);
		} else {
			// Insert a new record
			$return = $wpdb->insert(
				$sends_by_week_table,
				[
					'year' => $year,
					'month' => $month,
					'week' => $week,
					'sends' => $sends,
					'total_users' => $totalUsers,
					'userIds' => $serializedUserIds
				]
			);
		}
	}
	return $return;
}

function idemailwiz_fetch_sends($campaignId = null, $startDateTime = null, $endDateTime = null)
{
	$baseUrl = 'https://api.iterable.com/api/export/data.csv';

	$queryParams = [
		'dataTypeName' => 'emailSend',
	];

	if ($startDateTime || $endDateTime) {
		if ($startDateTime) {
			$queryParams['startDateTime'] = $startDateTime;
		}
		if ($endDateTime) {
			$queryParams['endDateTime'] = $endDateTime;
		}
	} else {
		// If no date range is provided, default to the previously completed week
		$currentDate = new DateTime();
		$currentDate->setTimezone(new DateTimeZone('UTC'));
		$currentDate->modify('last Saturday');
		$endDateTime = $currentDate->format('Y-m-d\T23:59:59\Z');
		$currentDate->modify('previous Sunday');
		$startDateTime = $currentDate->format('Y-m-d\T00:00:00\Z');

		$queryParams['startDateTime'] = $startDateTime;
		$queryParams['endDateTime'] = $endDateTime;
	}

	if ($campaignId) {
		$queryParams['campaignId'] = $campaignId;
	}

	$queryString = http_build_query($queryParams);
	$url = $baseUrl . '?' . $queryString . '&onlyFields=createdAt&onlyFields=userId&onlyFields=campaignId';

	try {
		$response = idemailwiz_iterable_curl_call($url);
	} catch (Throwable $e) {
		wiz_log("Error encountered for fetch sends curl call to : " . $url . " - " . $e->getMessage());
		return [];
	}

	$tempFile = tmpfile();
	fwrite($tempFile, $response['response']);
	fseek($tempFile, 0);

	$file = new SplFileObject(stream_get_meta_data($tempFile)['uri']);
	$file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
	$file->rewind();

	$headers = $file->current();
	$file->next();

	while (!$file->eof()) {
		$values = $file->current();
		if (!empty($values)) {
			$values = array_pad($values, count($headers), '');
			yield array_combine($headers, $values);
		}
		$file->next();
	}

	fclose($tempFile);
}
