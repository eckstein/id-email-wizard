<?php
/**
 * Iterable API fetchers used by the sync subsystem.
 *
 * These functions only fetch/normalize data from the Iterable API; they do not
 * write to the database. The companion orchestration functions (idemailwiz_sync_*)
 * live in includes/sync.php.
 */

if (!defined('ABSPATH')) {
	exit;
}

function idemailwiz_fetch_campaigns($campaignIds = null)
{

	$url = 'https://api.iterable.com/api/campaigns';
	wiz_log("Fetching Campaigns from Iterable API...");
	try {
		$response = idemailwiz_iterable_curl_call($url);
	} catch (Throwable $e) {  // Catching Throwable to handle both Error and Exception
		// Log the error with more details
		wiz_log("Fetch Campaigns: CAUGHT EXCEPTION during curl call to $url - " . $e->getMessage());


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

function idemailwiz_fetch_journeys($journeyIds = null)
{
	$url = 'https://api.iterable.com/api/journeys';
	$allJourneys = [];
	$pageCount = 0;
	$totalExpected = null;
	
	wiz_log("Fetching Journeys from Iterable API with pagination support...");
	
	do {
		$pageCount++;
		wiz_log("Fetching journeys page $pageCount from: $url");
		
		try {
			$response = idemailwiz_iterable_curl_call($url);
		} catch (Throwable $e) {
			wiz_log("Fetch Journeys: CAUGHT EXCEPTION during curl call to $url - " . $e->getMessage());
			
			if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
				wiz_log("More than 5 consecutive 400 errors encountered. Skipping...");
			}
			
			return "Error: Exception during API call - " . $e->getMessage();
		}

		// Check if journeys exist in the API response
		if (!isset($response['response']['journeys'])) {
			wiz_log("Fetch Journeys: Key ['response']['journeys'] not found in API response structure.");
			return "Error: No journeys found in the API response.";
		}

		$pageJourneys = $response['response']['journeys'];
		$allJourneys = array_merge($allJourneys, $pageJourneys);
		
		if ($pageCount === 1 && isset($response['response']['totalJourneysCount'])) {
			$totalExpected = $response['response']['totalJourneysCount'];
		}
		
		// Check for next page
		$nextPageUrl = isset($response['response']['nextPageUrl']) ? $response['response']['nextPageUrl'] : null;
		
		// Handle relative URLs by prepending the base domain
		if ($nextPageUrl) {
			if (strpos($nextPageUrl, 'http') !== 0) {
				// It's a relative URL, prepend the base URL
				$nextPageUrl = 'https://api.iterable.com' . $nextPageUrl;
			}
		}
		
		$url = $nextPageUrl;
		
		// Safety check to prevent infinite loops
		if ($pageCount > 100) {
			wiz_log("Warning: Stopped after 100 pages to prevent infinite loop. This may indicate an API issue.");
			break;
		}
		
		// Add a small delay between requests to be respectful to the API
		if ($url) {
			usleep(100000); // 100ms delay
		}
		
	} while ($url);
	
	wiz_log("Pagination complete. Fetched $pageCount pages with " . count($allJourneys) . " total journeys" . ($totalExpected ? " (expected: $totalExpected)" : ""));

	// Filter journeys if specific IDs are requested
	if ($journeyIds) {
		$filteredJourneys = [];
		foreach ($allJourneys as $journey) {
			if (in_array($journey['id'], $journeyIds)) {
				$filteredJourneys[] = $journey;
			}
		}
		$allJourneys = $filteredJourneys;
		wiz_log("Filtered to " . count($allJourneys) . " journeys matching requested IDs.");
	}

	// Process journeys to match our database schema
	$processedJourneys = [];
	foreach ($allJourneys as $journey) {
		// Skip the draft object as requested
		unset($journey['draft']);
		
		// Convert triggerEventNames array to serialized string for database storage
		if (isset($journey['triggerEventNames']) && is_array($journey['triggerEventNames'])) {
			$journey['triggerEventNames'] = serialize($journey['triggerEventNames']);
		}
		
		// Ensure boolean fields are properly handled
		$journey['enabled'] = isset($journey['enabled']) ? (bool)$journey['enabled'] : true;
		$journey['isArchived'] = isset($journey['isArchived']) ? (bool)$journey['isArchived'] : false;
		
		$processedJourneys[] = $journey;
	}

	wiz_log("Successfully processed " . count($processedJourneys) . " journeys from API.");
	return $processedJourneys;
}



function idemailwiz_fetch_metrics($campaignIds = null)
{
	@set_time_limit(600);

	$today = new DateTime();
	$startFetchDate = $today->modify('-8 weeks')->format('Y-m-d');
	$campaigns_to_fetch = []; // Initialize array to hold campaign IDs

	if ($campaignIds) {
		$metricCampaignArgs = array(
			'fields' => array('id'),
			'campaignIds' => $campaignIds,
			'startAt_start' => $startFetchDate
		);
		$campaigns_to_fetch = get_idwiz_campaigns($metricCampaignArgs);
		wiz_log("Fetch Metrics: " . count($campaigns_to_fetch) . " of " . count($campaignIds) . " passed campaigns are within the last 8 weeks.");
	} else {
		$blastCampaignArgs = array(
			'fields' => array('id'),
			'type' => 'Blast',
			'startAt_start' => $startFetchDate
		);
		$blast_campaigns = get_idwiz_campaigns($blastCampaignArgs);

		$triggeredStartDate = (new DateTime())->modify('-1 year')->format('Y-m-d');
		$triggeredCampaignArgs = array(
			'fields' => array('id'),
			'type' => 'Triggered',
			'campaignState' => 'Running',
			'startAt_start' => $triggeredStartDate
		);
		$triggered_campaigns = get_idwiz_campaigns($triggeredCampaignArgs);

		$campaigns_to_fetch = array_merge($blast_campaigns ?: [], $triggered_campaigns ?: []);

		wiz_log("Default Metrics Fetch: Found " . count($blast_campaigns ?: []) . " Blast campaigns (last 8 weeks) and " . count($triggered_campaigns ?: []) . " Running Triggered campaigns (last year).");
	}

	// If no campaigns were found after filtering, return early
	if (empty($campaigns_to_fetch)) {
		wiz_log("Fetch Metrics: No campaigns found matching the criteria to fetch metrics for.");
		return [
			'metrics' => [],
			'requested_ids' => [] // No campaigns were requested
		];
	}

	$maxUrlLength = 2000;
	$baseUrl = "https://api.iterable.com/api/campaigns/metrics?startDateTime=2021-11-01";
	$batches = array();
	$currentBatch = array();
	$currentUrlLength = strlen($baseUrl);

	foreach ($campaigns_to_fetch as $campaign) {
		if (is_array($campaign) && isset($campaign['id'])) {
			$paramLength = strlen('&campaignId=' . $campaign['id']);
			if ($currentUrlLength + $paramLength > $maxUrlLength && !empty($currentBatch)) {
				$batches[] = $currentBatch;
				$currentBatch = array();
				$currentUrlLength = strlen($baseUrl);
			}
			$currentBatch[] = $campaign['id'];
			$currentUrlLength += $paramLength;
		} else {
			wiz_log("Fetch Metrics Warning: Invalid campaign data encountered during batching: " . print_r($campaign, true));
		}
	}
	if (!empty($currentBatch)) {
		$batches[] = $currentBatch;
	}

	$allMetrics = [];
	$batchNum = 0;
	$totalBatches = count($batches);
	wiz_log("Fetch Metrics: Processing $totalBatches batches for " . count($campaigns_to_fetch) . " campaigns.");

	foreach ($batches as $batch) {
		$batchNum++;
		$getString = '?startDateTime=2021-11-01&campaignId=' . implode('&campaignId=', $batch);

		$url = "https://api.iterable.com/api/campaigns/metrics" . $getString;
		wiz_log("Fetch Metrics: Batch $batchNum/$totalBatches - " . count($batch) . " campaigns.");
		try {
			$response = idemailwiz_iterable_curl_call($url);
		} catch (Throwable $e) {
			wiz_log("Fetch Metrics: Batch $batchNum/$totalBatches FAILED with exception: " . $e->getMessage());

			if (strpos($e->getMessage(), "CONSECUTIVE_400_ERRORS") !== false) {
				wiz_log("More than 5 consecutive 400 errors encountered. Skipping batch.");
			}

			continue;
		}

		if (empty($response) || !isset($response['response']) || empty($response['response'])) {
			wiz_log("Fetch Metrics: Batch $batchNum/$totalBatches returned empty response. HTTP code: " . ($response['http_code'] ?? 'N/A'));
			continue;
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

	// Return the data array AND the original campaign IDs requested
	return [
		'metrics' => $allMetrics,
		'requested_ids' => array_column($campaigns_to_fetch, 'id') // Extract IDs from the combined fetch
	];
	// return $allMetrics; // Old return
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
		//'shoppingCartItems.sessionStartDateNonOpl',
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
	$startDateTime = $startDate ?? date('Y-m-d', strtotime('-30 days'));
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
            // Remove logging
			return [];
		}

        // Remove logging

		// NEW Processing with explicit mapping to match DB Schema
        $processedHeaders = array_map(function ($header) {
            // Direct mapping for known mismatches
            $map = [
                '_id' => 'id', // Special case from Iterable export?
                'createdAt' => 'createdAt',
                'purchaseDate' => 'purchaseDate',
                'campaignId' => 'campaignId',
                'templateId' => 'templateId',
                'shoppingCartItems' => 'shoppingCartItems', // Ensure the main field is mapped
                'shoppingCartItems.price' => 'shoppingCartItems_price',
                'shoppingCartItems.quantity' => 'shoppingCartItems_quantity',
                'shoppingCartItems.name' => 'shoppingCartItems_name',
                'shoppingCartItems.discountAmount' => 'shoppingCartItems_discountAmount',
                'shoppingCartItems.discountCode' => 'shoppingCartItems_discountCode',
                'shoppingCartItems.divisionId' => 'shoppingCartItems_divisionId',
                'shoppingCartItems.divisionName' => 'shoppingCartItems_divisionName',
                'shoppingCartItems.isSubscription' => 'shoppingCartItems_isSubscription',
                'shoppingCartItems.locationName' => 'shoppingCartItems_locationName',
                'shoppingCartItems.productCategory' => 'shoppingCartItems_productCategory',
                'shoppingCartItems.productSubcategory' => 'shoppingCartItems_productSubcategory',
                'shoppingCartItems.studentAccountNumber' => 'shoppingCartItems_studentAccountNumber',
                'shoppingCartItems.studentDob' => 'shoppingCartItems_studentDob',
                'shoppingCartItems.studentGender' => 'shoppingCartItems_studentGender',
                'shoppingCartItems.utmCampaign' => 'shoppingCartItems_utmCampaign',
                'shoppingCartItems.utmContents' => 'shoppingCartItems_utmContents',
                'shoppingCartItems.utmMedium' => 'shoppingCartItems_utmMedium',
                'shoppingCartItems.utmSource' => 'shoppingCartItems_utmSource',
                'shoppingCartItems.utmTerm' => 'shoppingCartItems_utmTerm',
                'shoppingCartItems.categories' => 'shoppingCartItems_categories',
                'shoppingCartItems.imageUrl' => 'shoppingCartItems_imageUrl',
                 'shoppingCartItems.url' => 'shoppingCartItems_url',
                 'shoppingCartItems.discounts' => 'shoppingCartItems_discounts',
				'shoppingCartItems.sessionStartDateNonOpl' => 'shoppingCartItems_sessionStartDateNonOpl',
                // Add other direct mappings as needed based on CSV headers and DB schema
                'accountNumber' => 'accountNumber',
                'orderId' => 'orderId',
                'userId' => 'userId',
                'total' => 'total'
            ];
        
            if (isset($map[$header])) {
                return $map[$header];
            }
        
            // Fallback for headers not explicitly mapped: replace '.' with '_'
            $processed_key = str_replace('.', '_', $header);
            
            // Lowercase the character immediately after the underscore, preserving other casing
            $processed_key = preg_replace_callback('/(_)([A-Z])/', function($matches) {
                return $matches[1] . strtolower($matches[2]);
            }, $processed_key);

            // Ensure the final key is lowercase to match the DB schema - REMOVED this line as it was incorrect.
            // return strtolower($processed_key); 
            return $processed_key; // Return the key processed by the rule above
        
        }, $headers);

        // Remove logging

        // Remove logging flag
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
                // Remove logging
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
