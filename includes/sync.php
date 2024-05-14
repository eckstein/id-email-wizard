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




	foreach ($items as $key => $item) {

		// If this is a campaigns table sync
		if ($table_name == $wpdb->prefix . 'idemailwiz_campaigns') {
			//update the campaigns table lastWizSync with the current datetime
			$item['lastWizSync'] = date('Y-m-d H:i:s');
		}

		// Add 'name' to the metrics array
		if ($table_name == $wpdb->prefix . 'idemailwiz_metrics') {
			$metricCampaign = get_idwiz_campaign($item['id']);
			$metricName = $metricCampaign['name'];
		}

		// If this is a purchase sync, we do some database cleanup
		if ($table_name == $wpdb->prefix . 'idemailwiz_purchases') {
			// Exclude purchases with campaignIds that are negatives (like -12345)
			if (isset($item['campaignId']) && $item['campaignId'] < 0) {
				unset($items[$key]);
			}
		}

		// If this is a user sync
		if ($table_name == $wpdb->prefix . 'idemailwiz_users') {
			foreach ($item as $field => $value) {
				// Change blank values to NULL
				if ($value === '') {
					$item[$field] = NULL;
					continue;
				}

				// Change string "[]" to NULL
				if ($value === '[]') {
					$item[$field] = NULL;
					continue;
				}

				// Check if the value is a string representation of an array
				if (is_string($value) && strpos($value, '[') === 0) {
					// Attempt to decode it as JSON
					$decoded = json_decode($value, true);

					// If decoding is successful and the result is an array, serialize it
					if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
						$item[$field] = serialize($decoded);
					}
				}
			}
		}

		// If this is a template record, we check if there's a wiz builder template with this template ID set as the sync-to
		if ($table_name == $wpdb->prefix . 'idemailwiz_templates') {
			$incomingTemplateId = $item['templateId'];
			$wizDbTemplate = get_idwiz_template($incomingTemplateId);
			$args = array(
				'post_type' => 'idemailwiz_template',
				'meta_key' => 'itTemplateId',
				'meta_value' => $incomingTemplateId,
				'posts_per_page' => 1
			);

			$builderTemplates = get_posts($args);

			if ($wizDbTemplate && !empty($builderTemplates)) {

				$sql = $wpdb->prepare(
					"UPDATE `{$wpdb->prefix}idemailwiz_templates` SET clientTemplateId = %s WHERE templateId = %d",
					$builderTemplates[0]->ID,
					$wizDbTemplate['templateId']

				);
				$wpdb->query($sql);
			} else {
				$sql = $wpdb->prepare(
					"UPDATE `{$wpdb->prefix}idemailwiz_templates` SET clientTemplateId = NULL WHERE templateId = %d",
					$incomingTemplateId
				);
				$wpdb->query($sql);
			}
		}



		//If this is an experiment record, we check if the corrosponding campaign in the campaign table has the experiment ID present. If not, add it.
		if ($table_name == $wpdb->prefix . 'idemailwiz_experiments') {
			$experimentId = $item['experimentId'];

			$experimentCampaign = get_idwiz_campaign($item['campaignId']);

			//wiz_log('Found matching campaign: '.$experimentCampaign['id'].' for experiment '.$experimentId);

			// Get the existing experimentIds, if any
			$existingExperimentIds = $experimentCampaign['experimentIds'];
			$experimentIdsArray = $existingExperimentIds ? unserialize($existingExperimentIds) : array();

			// Add the new experimentId if it doesn't already exist in the array
			if (!in_array($experimentId, $experimentIdsArray)) {
				$experimentIdsArray[] = $experimentId;
			}

			// Serialize the updated array
			$serializedExperimentIds = serialize($experimentIdsArray);

			// Using prepare to safely insert values into the query
			$sql = $wpdb->prepare(
				"UPDATE `{$wpdb->prefix}idemailwiz_campaigns` SET experimentIds = %s WHERE id = %s",

				$serializedExperimentIds,
				$item['campaignId']
			);
			$wpdb->query($sql);
		}


		// Serialize values that are arrays
		foreach ($item as $childKey => $childValue) {
			if (is_array($childValue)) {
				if (empty($childValue)) {
					$item[$childKey] = null; // Set to NULL if the array is empty
				} else {
					$serialized = serialize($childValue);
					$item[$childKey] = $serialized;
				}
			}
		}

		// Convert key/header to camel case for db compatibility
		$key = str_replace('.', '_', $key); // Replace periods with underscores
		$words = explode(' ', $key); // Split the string into words
		$words = array_map('ucwords', $words); // Capitalize the first letter of each word
		$camelCaseString = implode('', $words); // Join the words back together
		$key = lcfirst($camelCaseString); // Make the first letter lowercase and return

		$fields = implode(",", array_map(function ($field) {
			return "`" . esc_sql($field) . "`";
		}, array_keys($item)));
		$placeholders = implode(",", array_fill(0, count($item), "%s"));
		$prepared_values = array_values($item);

		if ($operation === "insert") {
			$sql = "INSERT INTO `{$table_name}` ({$fields}) VALUES ({$placeholders})";
			$prepared_sql = $wpdb->prepare($sql, $prepared_values);
		} else {
			$updates = implode(", ", array_map(function ($field) {
				return "`$field` = %s";
			}, array_keys($item)));
			$sql = "UPDATE `{$table_name}` SET {$updates} WHERE `{$id_field}` = %s";
			$prepared_sql = $wpdb->prepare($sql, array_merge($prepared_values, [$item[$id_field]]));
		}

		// Do the insert/update
		$query_result = $wpdb->query($prepared_sql);




		// Extracting relevant details for logging
		$item_name = isset($item[$name_field]) ? $item[$name_field] : ''; // Item name
		if ($table_name == $wpdb->prefix . 'idemailwiz_metrics') {
			$item_name = $metricName;
		}
		$item_id = $item[$id_field] ?? false; // Item ID

		if ($item_id && $query_result !== false) {
			if ($query_result > 0) {
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


function idemailwiz_fetch_campaigns($campaignIds = null)
{

	$url = 'https://api.iterable.com/api/campaigns';
	try {
		$response = idemailwiz_iterable_curl_call($url);
	} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
		// Log the error with more details
		wiz_log("Error encountered for fetch campaigns curl call to : " . $url . " - " . $e->getMessage());


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
	} else {

		// Initialize URLs for fetching templates of different types and mediums

		// Get a formatted end date of tomorrow
		//$endDate = date('Y-m-d', strtotime('+1 day'));
		$endDate = new DateTime();
		$endDate->modify('+1 day');
		$endDate = $endDate->format('Y-m-d');
		$templateAPIurls = [
			'blastEmails' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=Email&endDateTime=' . $endDate,
			'triggeredEmails' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=Email&endDateTime=' . $endDate,
			'workflowEmails' => 'https://api.iterable.com/api/templates?templateType=Workflow&messageMedium=Email&endDateTime=' . $endDate,
			'blastSMS' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=SMS&endDateTime=' . $endDate,
			'triggeredSMS' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=SMS&endDateTime=' . $endDate,
		];

		// Fetch templates from all four endpoints
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
	}

	// Fetch the detailed templates for all fetched templates
	$urlsToFetch = [];
	foreach ($templateIds as $templateId) {

		//error_log($templateId);

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
				$fetchedTemplates[] = $fetchedTemplate;
			}
		}

		// Replace the original templates with the fetched ones
		$allTemplates = $fetchedTemplates;

		usleep(10000);
	} catch (Exception $e) {
		wiz_log("Error during multi cURL request: " . $e->getMessage());
	}

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

	return $result;
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
	$startFetchDate = $today->modify('-4 weeks')->format('Y-m-d');

	$metricCampaignArgs = array(
		'fields' => array('id'),
		'type' => array('Blast', 'Triggered'),
	);

	if ($campaignIds) {
		$metricCampaignArgs['campaignIds'] = $campaignIds;
	} else {
		// If not campaigns are passed, limit the call to a timeframe
		$metricCampaignArgs['startAt_start'] = $startFetchDate;
	}

	$campaigns = get_idwiz_campaigns($metricCampaignArgs);

	$batchCount = 0;
	$batches = array();
	$currentBatch = array();
	foreach ($campaigns as $campaign) {
		$currentBatch[] = $campaign['id'];
		if (++$batchCount % 200 == 0) {
			$batches[] = $currentBatch;
			$currentBatch = array();
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

			// Merge the metrics with the existing data
			$allMetrics[] = $metrics;
		}
		sleep(7); // Respect Iterable's rate limit of 10 requests per minute
	}

	// Return the data array
	return $allMetrics;
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

				// Check if the necessary data is present
				if (isset($userData['email']) && !empty($userData['email']) && isset($userData['signupDate']) && !empty($userData['signupDate'])) {
					// Use the signup date as the salt
					$salt = $userData['signupDate'];

					// Hash the email with the signup date salt and the pepper
					$pepperedEmail = $userData['email'] . $salt . WIZ_PEPPER;
					$userData['wizId'] = hash('sha256', $pepperedEmail);

					// Remove the plan text email from the data
					unset($userData['email']);

					// Store the salt to reproduce this hash in the future
					$userData['wizSalt'] = $salt;
				}

				// If there's data to add, yield the user data
				if (!empty($userData)) {
					yield $userData;
				}
			}
		}

		// Close the file handle
		fclose($handle);
	}
}


function wiz_encrypt_email($plaintext)
{
	$key = pack('H*', WIZ_ENCRYPTION_KEY); // Convert hex to binary
	$iv_length = openssl_cipher_iv_length('aes-256-cbc');
	$iv = openssl_random_pseudo_bytes($iv_length);
	$ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
	return base64_encode($iv . $ciphertext); // Prepend IV to ciphertext and encode
}

// Define the function for decryption
function wiz_decrypt_email($iv_ciphertext)
{
	$key = pack('H*', WIZ_ENCRYPTION_KEY);
	$iv_length = openssl_cipher_iv_length('aes-256-cbc');
	$iv_ciphertext_dec = base64_decode($iv_ciphertext);
	$iv_dec = substr($iv_ciphertext_dec, 0, $iv_length);
	$ciphertext_dec = substr($iv_ciphertext_dec, $iv_length);
	$plaintext_dec = openssl_decrypt($ciphertext_dec, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv_dec);
	return $plaintext_dec;
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

	$wpdb->query('START TRANSACTION');

	while (true) {
		$users = [];

		wiz_log("Processing batch of $batchSize users...");

		// Collect a batch of users
		for ($i = 0; $i < $batchSize && $userGenerator->valid(); $i++) {
			$users[] = $userGenerator->current();
			$userGenerator->next();
		}

		if (empty($users)) {
			break; // No more users to process
		}

		// Prepare arrays for comparison
		$records_to_update = [];
		$records_to_insert = [];

		foreach ($users as $user) {
			// Check if the user exists in the database
			$existingWizId = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name WHERE wizId = %s",
					$user['wizId']
				)
			);

			// Set update or insert designations
			if ($existingWizId > 0) {
				// User exists, prepare to update
				$records_to_update[] = $user;
			} else {
				// User not in the database, prepare to insert
				$records_to_insert[] = $user;
			}
		}
		
	}
	// Process and log the sync operation
	idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);

	$wpdb->query('COMMIT');
}

function idemailwiz_fetch_purchases($campaignIds = [])
{
	date_default_timezone_set('UTC'); // Set timezone to UTC to match Iterable


	// Define the base URL
	$baseUrl = 'https://api.iterable.com/api/export/data.csv';

	// Define the fields to be omitted

	$omitFields = [
		'shoppingCartItems.orderDetailId',
		'shoppingCartItems.parentOrderDetailId',
		'shoppingCartItems.predecessorOrderDetailId',
		'shoppingCartItems.financeUnitId',
		'shoppingCartItems.numberOfLessonsPurchasedOpl',
		'shoppingCartItems.sessionStartDateNonOpl',
		'shoppingCartItems.subscriptionAutoRenewDate',
		'shoppingCartItems.totalDaysOfInstruction',
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
	$startDateTime = date('Y-m-d', strtotime('-3 days')); // defaults to past 3 days, unless altered below
	$endDateTime = date('Y-m-d', strtotime('+1 day')); // End date is always today

	// Handle the campaign IDs if provided
	if (!empty($campaignIds)) {
		if (count($campaignIds) === 1) {
			// If there's only one campaign ID, add it directly
			$queryParams['campaignId'] = $campaignIds[0];
			$wizCampaign = get_idwiz_campaign($campaignIds[0]);

			$startDateTime = date('Y-m-d', $wizCampaign['startAt'] / 1000);
		} else {
			// If multiple campaign IDs, find the earliest date
			// Iterable only allows one campaign ID per call to the export API, and only max of 4 per minute, so we estimate the earliest and latest dates and use those
			$wizCampaigns = get_idwiz_campaigns(['campaignIds' => $campaignIds]);
			$earliestDate = min(array_column($wizCampaigns, 'startAt'));
			$latestDate = max(array_column($wizCampaigns, 'startAt'));

			$startDateTime = date('Y-m-d', ($earliestDate / 1000) - 86400); // one day before campaign start date
			$endDateTime = date('Y-m-d', ($latestDate / 1000) + MONTH_IN_SECONDS); // one month after last campaign start date

		}
	}


	// Add the start and end datetime to the query parameters
	$queryParams['startDateTime'] = $startDateTime;
	$queryParams['endDateTime'] = $endDateTime;

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
	} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
		// Log the error with more details
		wiz_log("Error encountered for fetch purchases curl call to : " . $url . " - " . $e->getMessage());


		// Specific check for the "CONSECUTIVE_400_ERRORS" message
		if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
			// Specific action for this type of error
			wiz_log("More than 5 consecutive 400 errors encountered. Skipping...");
		}

		// Optionally, you can rethrow the exception or handle it differently
		// throw $e;
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

		// Prepare the headers
		$processedHeaders = array_map(function ($header) {
			$headerWithoutUnderscores = str_replace('_', '', $header);
			return strtolower(str_replace('.', '_', $headerWithoutUnderscores));
		}, $headers);

		// Iterate over each line of the file
		while (($values = fgetcsv($handle)) !== FALSE) {
			$purchaseData = []; // Initialize as empty array

			// Temporary variables to store campaignId and utmMedium values
			$tempCampaignId = '';
			$tempUtmMedium = '';

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

					// Store campaignId and utmMedium values temporarily
					// if ( $header === 'campaignid' ) {
					// 	$tempCampaignId = $cleanValue;
					// } elseif ( $header === 'shoppingcartitems_utmmedium' ) {
					// 	$tempUtmMedium = $cleanValue;
					// }

					// Add to the purchase data
					$purchaseData[$header] = $cleanValue;
				}
			}

			// Check if the purchase should be added based on campaignId and utmMedium
			// if ( ! empty( $tempCampaignId ) || strpos( strtolower( $tempUtmMedium ), 'email' ) !== false ) {
			// 	// If there's data to add, append it to all purchases
			// 	if ( ! empty( $purchaseData ) ) {
			// 		$allPurchases[] = $purchaseData;
			// 	}
			// }

			if (!empty($purchaseData)) {
				$allPurchases[] = $purchaseData;
			}
		}

		// Close the file handle
		fclose($handle);
	}

	// Return the data array
	return $allPurchases;
}




function idemailwiz_sync_campaigns($passedCampaigns = null)
{
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





function idemailwiz_sync_templates($passedCampaigns = null)
{
	// Fetch relevant templates
	// Note: The fetch function filters by updatedAt differences to limit results
	$templates = idemailwiz_fetch_templates($passedCampaigns);


	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_templates';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];

	foreach ($templates as $template) {
		if (!isset($template['templateId'])) {
			wiz_log('No templateId found in the fetched template record!');
			continue;
		}
		// See if the template exists in our database yet
		$wizTemplate = get_idwiz_template($template['templateId']);

		if ($wizTemplate) {
			// Template exists, we'll update it
			$records_to_update[] = $template;
		} else {
			// Template not in the database, we'll add it
			$records_to_insert[] = $template;
		}
	}

	// Process and log the sync operation
	return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}





function idemailwiz_sync_purchases($campaignIds = null)
{
	$purchases = idemailwiz_fetch_purchases($campaignIds);

	global $wpdb;
	$purchases_table = $wpdb->prefix . 'idemailwiz_purchases';
	$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';

	$records_to_insert = [];
	$records_to_update = [];

	foreach ($purchases as $purchase) {
		if (!isset($purchase['id'])) {
			wiz_log('No ID found in the fetched purchase record!');
			continue;
		}

		// Fetch the campaign's startAt if campaignId is set
		// if (isset($purchase['campaignId'])) {
		// 	$campaignStartAt = $wpdb->get_var($wpdb->prepare("SELECT startAt FROM $campaigns_table WHERE id = %d", $purchase['campaignId']));
		// 	if ($campaignStartAt) {
		// 		$purchase['campaignStartAt'] = $campaignStartAt;
		// 	}
		// }

		$wizPurchase = $wpdb->get_var($wpdb->prepare("SELECT id FROM $purchases_table WHERE id = %s", $purchase['id']));

		if (!$wizPurchase) {
			$records_to_insert[] = $purchase;
		} else {
			$records_to_update[] = $purchase;
		}
	}

	return idemailwiz_process_and_log_sync($purchases_table, $records_to_insert, $records_to_update);
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

	$metrics = idemailwiz_fetch_metrics($passedCampaigns); // Gets all metrics if none are passed

	//(print_r($metrics, true));

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_metrics';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];

	if ($passedCampaigns) {
		$records_to_update = $metrics;
	} else {
		foreach ($metrics as $metric) {
			if (!isset($metric['id'])) {
				wiz_log('No ID found in the fetched metric record!');
				continue;
			}
			// Handle SMS campaign header mapping
			$wizCampaign = get_idwiz_campaign($metric['id']);
			if ($wizCampaign && $wizCampaign['messageMedium'] == 'SMS') {
				$metric['uniqueEmailSends'] = $metric['uniqueSmsSent'];
				$metric['uniqueEmailsDelivered'] = $metric['uniqueSmsDelivered'];
				$metric['uniqueEmailClicks'] = $metric['uniqueSmsClicks'];
				$records_to_update[] = $metric;
			}

			// Check for existing metric
			$wizMetric = get_idwiz_metric($metric['id']);
			if ($wizMetric) {
				// Gather metric for update and de-dupe
				if (!in_array($metric, $records_to_update)) {
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
	return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}


function idemailwiz_process_and_log_sync($table_name, $records_to_insert = null, $records_to_update = null)
{

	// Extracting the type (e.g., 'campaign', 'template', etc.) from the table name
	$type = substr($table_name, strrpos($table_name, '_') + 1);

	$insert_results = '';
	$update_results = '';
	$logChunk = "";
	$return = array();

	$logChunk .= ucfirst($type) . " sync results: ";

	if ($records_to_insert) {
		//$logChunk .= count($records_to_insert) . " $type" . " to insert.\n";
		$insert_results = idemailwiz_update_insert_api_data($records_to_insert, 'insert', $table_name);
	}
	if ($records_to_update) {
		//$logChunk .= count($records_to_update) . " $type" . " to update.\n";
		$update_results = idemailwiz_update_insert_api_data($records_to_update, 'update', $table_name);
	}

	$logInsertUpdate = return_insert_update_logging($insert_results, $update_results, $table_name);

	$logChunk .= $logInsertUpdate;

	wiz_log($logChunk);

	if ($records_to_insert) {
		$return['insert'] = $insert_results;
	}
	if ($records_to_update) {
		$return['update'] = $update_results;
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
	}

	// Ensure required fields are set
	foreach ($requiredFields as $field) {
		if (!isset($metrics[$field]) || $metrics[$field] === null) {
			$metrics[$field] = 0;
		}
	}

	// Calculate common metrics
	$sendField = $medium == 'SMS' ? 'uniqueSmsSent' : 'uniqueEmailSends';
	$deliveredField = $medium == 'SMS' ? 'uniqueSmsDelivered' : 'uniqueEmailsDelivered';
	$clicksField = $medium == 'SMS' ? 'uniqueSmsClicks' : 'uniqueEmailClicks';

	$sendValue = (float) $metrics[$sendField];
	$deliveredValue = (float) $metrics[$deliveredField];
	$clicksValue = (float) $metrics[$clicksField];
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
	$opensValue = $medium == 'Email' ? (float) $metrics['uniqueEmailOpens'] : 0;

	if ($opensValue && $sendValue > 0) {
		$metrics['wizOpenRate'] = $medium == 'Email' ? ($opensValue / $sendValue) * 100 : 0;
	} else {
		$metrics['wizOpenRate'] = 0;
	}

	if ($opensValue && $opensValue > 0) {
		$metrics['wizCto'] = $medium == 'Email' ? ($clicksValue / $opensValue) * 100 : 0;
	} else {
		$metrics['wizCto'] = 0;
	}

	// Remove metrics we don't want to sync in
	unset($metrics['uniqueSmsSentByMessage']);

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

function idemailwiz_sync_non_triggered_metrics($campaignIds = null, $sync_dbs = null)
{
	$syncArgs = [];
	$response = [];

	set_transient('idemailwiz_blast_sync_in_progress', true, (5 * MINUTE_IN_SECONDS));

	$sync_dbs = $sync_dbs ?? ['campaigns', 'templates', 'metrics', 'purchases', 'experiments'];
	foreach ($sync_dbs as $db) {
		if ($campaignIds) {
			$syncArgs = $campaignIds;
		}
		$function_name = 'idemailwiz_sync_' . $db;
		if (!function_exists($function_name)) {
			return ['error' => 'Sync function does not exist for ' . $db];
		}
		$result = call_user_func($function_name, $syncArgs);

		if ($result === false) {
			return ['error' => 'Sync failed for ' . $db];
		}
		$response[$db] = $result;
	}

	// Do our general database cleanups
	wiz_log('Doing database cleanups...');
	do_database_cleanups();

	delete_transient('idemailwiz_blast_sync_in_progress');

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

	if ($engSync == 'on') {
		// If the sync is turned on, proceed with processing jobs
		set_transient('data_sync_in_progress', 'true', 2 * HOUR_IN_SECONDS);
		$jobs = $wpdb->get_results("SELECT * FROM $sync_jobs_table_name WHERE syncStatus = 'pending' ORDER BY syncPriority DESC, retryAfter ASC LIMIT 1", ARRAY_A);

		if (empty($jobs)) {
			wiz_log("No pending jobs found in the sync queue.");
			wp_clear_scheduled_hook('idemailwiz_sync_engagement_data');
			wp_schedule_single_event(time() + 2 * HOUR_IN_SECONDS, 'idemailwiz_sync_engagement_data');
			delete_transient('data_sync_in_progress');
			return;
		} else {
			$now = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));

			foreach ($jobs as $job) {
				$retryAfter = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $job['retryAfter'], new DateTimeZone('UTC'));

				if ($retryAfter !== false) {
					$retryAfter->setTimezone(new DateTimeZone('America/Los_Angeles'));

					if ($now > $retryAfter && $job['syncStatus'] == 'pending') {
						wiz_log("Processing {$job['syncType']} job {$job['jobId']} for campaign {$job['campaignId']}");
						idemailwiz_process_job_from_sync_queue($job['jobId']);
						wp_schedule_single_event(time() + 1, 'idemailwiz_sync_engagement_data');
					}
				} else {
					wiz_log("Invalid retryAfter value: " . $job['retryAfter']);
				}
			}
		}
	} else {
		// If the sync is turned off, log a message and clear the scheduled hook
		wiz_log("Engagement data sync is disabled in Wiz Settings.");
		wp_clear_scheduled_hook('idemailwiz_sync_engagement_data');
		delete_transient('data_sync_in_progress');
	}
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

		$existingJobs = $wpdb->get_results($wpdb->prepare(
			"SELECT syncType FROM $sync_jobs_table_name WHERE campaignId = %d AND syncType IN ('" . implode("','", $syncTypes) . "') AND syncStatus = 'pending'",
			(int)$campaignId
		));

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
		}
	}
}






function idwiz_export_and_store_jobs_to_sync_queue($campaignIds = null, $campaignTypes = ['Blast', 'Triggered', 'FromWorkflow'], $messageMediums = ['Email', 'SMS'], $metricTypes = ['send', 'open', 'click', 'unSubscribe', 'sendSkip', 'bounce', 'complaint'], $exportStart = null, $exportEnd = null, $priority = 1, $batchSize = 10)
{
	// Clean up the sync queue to get rid of old jobs
	idemailwiz_cleanup_sync_queue();

	if (!$exportStart) {
		$exportStart = date('Y-m-d H:i:s', strtotime('2021-11-01'));
	}
	if (!$exportEnd) {
		$exportEnd = date('Y-m-d H:i:s', strtotime('+1 day'));
	}

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
	wp_schedule_single_event(time(), 'idemailwiz_process_campaign_export_batch', [$campaignBatches, 0, $totalBatches, $metricTypes, $exportStart, $exportEnd, $priority]);
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

		foreach ($metricTypes as $metricType) {
			// Skip blast campaign send records if the campaign's end time is more than 1 day ago
			// Prevents exporting huge jobs from Iterable that will eventually just be skipped
			$campaignEnd = $campaign['endedAt'];
			$campaignEndTimestamp = floor($campaignEnd / 1000);  // convert milliseconds to seconds

			if (
				($campaignType == 'blast')
				&& ($metricType == 'send')
				&& ($messageMedium == 'email')
				&& ($campaignEndTimestamp < strtotime('-1 day'))
				&& ($priority == 1) // higher priorities indicate manual syncs
			) {
				continue;  // skip processing
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
			wp_schedule_single_event(time(), 'idemailwiz_export_and_queue_single_job_event', [$campaignId, $messageMedium, $metricType, $syncType, $exportStart, $exportEnd, $priority
			]);
		}
	}

	// If there are more batches to process, schedule the next batch processing event
	if ($currentBatch + 1 < $totalBatches) {
		wp_schedule_single_event(time(), 'idemailwiz_process_campaign_export_batch', [$campaignBatches, $currentBatch + 1, $totalBatches, $metricTypes, $exportStart, $exportEnd, $priority]);
	} else {
		// If all batches have been processed, schedule the sync process
		wiz_log("Batches processing complete, scheduling sync starting in 1 min...");
		wp_schedule_single_event(time() + 1 * MINUTE_IN_SECONDS, 'idemailwiz_sync_engagement_data');
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
		if (!isset($scheduledJob['response']['jobId'])) {
			throw new Exception("No job ID received from API.");
		}
		idemailwiz_add_sync_queue_row($scheduledJob,
			$campaignId,
			$syncType,
			$priority
		);
	} catch (Exception $e) {
		wiz_log("Error starting export job for campaign $campaignId: " . $e->getMessage());
		return;
	}
}



function idemailwiz_add_sync_queue_row($scheduledJob, $campaignId, $syncType, $priority = 1)
{
	global $wpdb;

	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	if (isset($scheduledJob['response']['jobId'])) {
		$iterableJobId = $scheduledJob['response']['jobId'];

		// Create a DateTime object representing the current time
		$currentTime = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));
		//$currentTime->setTimezone(new DateTimeZone('America/Los_Angeles'));

		$deleteAfter = $currentTime->modify('+ 12 hours');

		$retryAfter = $currentTime->modify('+1 hour');

		// Set retry after to 12 hours later so that manual syncs an re-queues are still prioritized
		// We also set deleteAfter to the same time so the job will be deleted if not syned within 12 hours
		$deleteAfter = $deleteAfter->format('Y-m-d H:i:s');

		if ($priority > 1) {
			$retryAfter = $currentTime->format('Y-m-d H:i:s');
		} else {
			$retryAfter = $retryAfter;
		}

		//wiz_log("priority: " . $priority . " retryAfter: " . $retryAfter);

		try {
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
				// Handle the insertion error
				throw new Exception("Error inserting sync queue row for jobId: $iterableJobId. Error: " . $wpdb->last_error);
			}
		} catch (Exception $e) {
			// Log the error
			wiz_log("Error inserting sync queue row: " . $e->getMessage());
			// You can also choose to rethrow the exception if needed
			// throw $e;
		}
	}

	return $result;
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
	}

	$retryAfter = new DateTimeImmutable($job['retryAfter'], new DateTimeZone('America/Los_Angeles'));

	$wpdb->update(
		$sync_jobs_table_name,
		['syncStatus' => 'syncing'],
		['jobId' => $jobId]
	);

	$jobId = $job['jobId'];
	$startAfter = $job['startAfter'] ? '?startAfter=' . $job['startAfter'] : '';
	$table_name = $wpdb->prefix . 'idemailwiz_' . $job['syncType'];

	$jobApiResponse = idemailwiz_iterable_curl_call("https://api.iterable.com/api/export/" . $jobId . "/files{$startAfter}");

	if (!isset($jobApiResponse['response']['files'])) {
		wiz_log("No files found at export link for job $jobId.");
		return;
	}

	$batchInsertData = [];
	$lastProcessedFile = '';
	if ($jobApiResponse['response']['jobState'] !== 'completed') {

		// Create a DateTime object representing the current time
		$currentTime = new DateTime('now', new DateTimeZone('UTC'));

		// Add 5 minutes to the current time
		$currentTime->modify('+5 minutes');

		// Set the timezone to Pacific Time (PT)
		$currentTime->setTimezone(new DateTimeZone('America/Los_Angeles'));

		// Format the time in a suitable format
		$retryAfter = $currentTime->format('Y-m-d H:i:s');

		// Update the database with the retryAfter timestamp
		$wpdb->update(
			$sync_jobs_table_name,
			[
				'retryAfter' => $retryAfter,
			],
			[
				'jobId' => $jobId,
			]
		);

		// Return from the function
		return;
	}

	
	
	foreach ($jobApiResponse['response']['files'] as $file) {
		$jsonResponse = file_get_contents($file['url']);

		$lines = explode("\n", $jsonResponse);
		if (!empty($lines) && (count(array_filter($lines)) > 0)) {
			foreach ($lines as $line) {
				if (trim($line) === '') {
					continue;
				}
				$record = json_decode($line, true);

				if (!is_array($record) || empty($record) || !isset($record['messageId'])) {
					continue;
				}

				$createdAt = new DateTime($record['createdAt'], new DateTimeZone('UTC'));

				$msTimestamp = (int) ($createdAt->format('U.u') * 1000);

				// Prepare data for insertion
				$batchInsertData[] = $wpdb->prepare("(%s, %s, %d, %d, %d)", [
					$record['messageId'],
					$record['userId'] ?? null,
					$record['campaignId'] ?? null,
					$record['templateId'] ?? null,
					$msTimestamp,
				]);
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
			$wpdb->update(
				$wpdb->prefix . 'idemailwiz_campaigns',
				[
					'lastWizSync' => date('Y-m-d H:i:s'),
				],
				[
					'id' => $campaignId,
				]
			);
		}
	}

	if (!empty($batchInsertData)) {
		$columns = ['messageId', 'userId', 'campaignId', 'templateId', 'startAt'];
		$placeholders = implode(", ", $batchInsertData);
		$insertQuery = "INSERT IGNORE INTO $table_name (" . implode(", ", $columns) . ") VALUES " . $placeholders;
		$insertResult = $wpdb->query($insertQuery);

		if ($insertResult === false) {
			wiz_log("Error inserting records for job $jobId. Error: " . $wpdb->last_error);
		} else {
			wiz_log("Inserted $insertResult records for job $jobId");
		}
	} else {
		//wiz_log("Batch insert data is empty");
	}

	if ($jobApiResponse['response']['exportTruncated'] === true) {
		// Update the row to reflect the requeued job
		$wpdb->update(
			$sync_jobs_table_name,
			['startAfter' => $lastProcessedFile, 'syncStatus'  => 'requeued', 'retryAfter' => current_time('mysql')],
			['jobId' => $jobId]
		);
		wiz_log("Export truncated for job $jobId. Requeueing..");
	} else {

		// Mark job as finished and schedule deleteAfter for 1 hour later
		$deleteAfter = date('Y-m-d H:i:s', strtotime('1 hour', current_time('timestamp')));
		$wpdb->update(
			$sync_jobs_table_name,
			['startAfter' => null, 'syncStatus' => 'finished', 'deleteAfter' => $deleteAfter],
			['jobId' => $jobId]
		);
	}
}


function get_campaigns_to_sync(
	$campaignIds = null,
	$campaignTypes = ['Blast', 'Triggered', 'FromWorkflow'],
	$messageMediums = ['Email', 'SMS']
) {

	$campaigns = [];

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

	// Check if we're syncing all campaigns
	if (empty($formFields['campaignIds'])) {
		
		$campaigns = get_idwiz_campaigns(['type' => 'Blast', 'fields' => 'id', 'startAt_end'=> '2024-04-28']);
		
		$campaignIds = array_column($campaigns, 'id');
	} else {
		// Extract campaign IDs, if provided
		$campaignIds = explode(',', $formFields['campaignIds']);
	}

	

	// Initiate the sync sequence
	foreach ($formFields['syncTypes'] as $manualSyncType) {
		switch ($manualSyncType) {
			case 'blastMetrics':
				$blastSyncInProgress = get_transient('idemailwiz_blast_sync_in_progress');
				if ($blastSyncInProgress) {
					$syncResult = ['error' => 'Another sync is already in progress!'];
				} else {
					$syncResult = idemailwiz_sync_non_triggered_metrics($campaignIds);
				}
				break;
			case 'send':
			case 'open':
			case 'click':
			case 'unSubscribe':
			case 'complaint':
			case 'bounce':
			case 'sendSkip':
				$metricType = $manualSyncType;
				maybe_add_to_sync_queue($campaignIds, [$metricType], null, null, 100);

				break;
		}
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
	// Set retryAfter to right now for all pending jobs
	$currentTime = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));
	$result = $wpdb->update($sync_jobs_table_name, [
		'retryAfter' => $currentTime->format('Y-m-d H:i:s'),
	], [
		'syncStatus' => 'pending',
	]);

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
