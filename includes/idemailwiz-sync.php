<?php
// Include WordPress' database functions
global $wpdb;



function idemailwiz_iterable_curl_call($apiURL, $postData = null, $verifySSL = false, $retryAttempts = 3, $maxConsecutive400Errors = 5)
{
    $attempts = 0;
    $consecutive400Errors = 0;
    $bearerToken = get_field('ga_revenue_api_sheet_bearer_token', 'options');

    do {
        // Initialize cURL
        $ch = curl_init($apiURL);
        

        // Set the appropriate headers based on the URL
        $headers = ["Content-Type: application/json"];
        if (strpos($apiURL, 'iterable')) {

            $api_key = get_field('iterable_api_key', 'options');
            $headers[] = "Api-Key: $api_key";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);


        // If POST data is provided, set up a POST request
        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }

        // Return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Execute the request
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ($response === false) {
            $error = curl_error($ch);
            error_log("cURL Error: $error");
        }

        // Get the HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close cURL
        curl_close($ch);

        // Check for rate limit
        if ($httpCode === 429) {
            error_log("Rate limit hit. Waiting 20 seconds before retrying...");
            sleep(20); // Adjust this delay as needed.
            continue;
        }

        // Check for authentication error
        if ($httpCode === 401) {
            error_log("Error fetching data from the API. HTTP Code: $httpCode. Response: $response");
            throw new Exception("Authentication failed for API endpoint $apiURL. Check your bearer token: $bearerToken");
        }

        // If a 400 error occurs, log the response and attempt details
        if ($httpCode === 400) {
            $consecutive400Errors++;
            sleep(3); // Wait for 3 seconds before retrying
            if ($consecutive400Errors > $maxConsecutive400Errors) {
                throw new Exception("Consecutive HTTP 400 Errors exceeded limit. Stopping execution.");
            }
        } else {
            $consecutive400Errors = 0; // Reset consecutive 400 errors count if other status code received
        }

        $attempts++;

        // If maximum attempts reached, throw an exception
        if ($attempts > $retryAttempts) {
            throw new Exception("HTTP 400 Error after $retryAttempts attempts. Stopping execution.");
        }

    } while ($httpCode === 400 || $httpCode === 429);

    // Check for other HTTP errors
    if ($httpCode >= 400) {
        throw new Exception("HTTP Error: $httpCode");
    }

    $decodedResponse = json_decode($response, true);
    if (is_array($decodedResponse)) {
        // If decoding was successful and it's an array
        $response = $decodedResponse;
    }

    // Return both the decoded response and the HTTP status code
    return ['response' => $response, 'httpCode' => $httpCode];
}


function idemailwiz_iterable_curl_multi_call($apiURLs, $verifySSL = false)
{
    // Fetch the API key
    $api_key = $api_key = get_field('iterable_api_key', 'options');

    // Initialize cURL Multi handle
    $mh = curl_multi_init();
    $handles = [];

    // Initialize each cURL handle and add it to the Multi handle
    foreach ($apiURLs as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Api-Key: $api_key",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch; // Store handles for later use
    }

    // Execute the handles
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    // Collect results
    $results = [];
    foreach ($handles as $handle) {
        $results[] = [
            'response' => json_decode(curl_multi_getcontent($handle), true),
            'httpCode' => curl_getinfo($handle, CURLINFO_HTTP_CODE)
        ];
        curl_multi_remove_handle($mh, $handle);
    }

    curl_multi_close($mh);
    return $results;
}

//Camel case for database headers
function to_camel_case($string)
{
    $string = str_replace('.', '_', $string); // Replace periods with underscores
    $words = explode(' ', $string); // Split the string into words
    $words = array_map('ucwords', $words); // Capitalize the first letter of each word
    $camelCaseString = implode('', $words); // Join the words back together
    return lcfirst($camelCaseString); // Make the first letter lowercase and return
}

function idemailwiz_update_insert_api_data($items, $operation, $table_name)
{
    global $wpdb;
    $result = ['success' => [], 'errors' => []];


    $id_field = 'id'; // Default ID field
    $name_field = 'name'; // Default name field

    // Determine the ID and name fields based on the table name
    if ($table_name == $wpdb->prefix.'idemailwiz_templates' || $table_name == $wpdb->prefix.'idemailwiz_experiments') {
        $id_field = 'templateId';
    }
    foreach ($items as $key => $item) {
        // Add 'name' to the metrics array
        if ($table_name == $wpdb->prefix.'idemailwiz_metrics') {
            $metricCampaign = get_idwiz_campaign($item['id']);
            $metricName = $metricCampaign['name'];
        }

        // If this is a template record, we check if there's a wiz builder template with this template ID set as the sync-to
        if ($table_name == $wpdb->prefix.'idemailwiz_templates') {
            $incomingTemplateId = $item['templateId'];
            $wizDbTemplate = get_idwiz_template($incomingTemplateId);
            $args = array(
                'post_type' => 'idemailwiz_template',
                'meta_key' => 'itTemplateId',
                'meta_value' => $incomingTemplateId,
                'posts_per_page' => 1
            );

            $builderTemplates = get_posts($args);

            if (!empty($builderTemplates)) {

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
        if ($table_name == $wpdb->prefix.'idemailwiz_experiments') {
            $experimentId = $item['experimentId']; // Retrieve the current experiment ID

            $experimentCampaign = get_idwiz_campaign($item['campaignId']);

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
                "UPDATE `{$wpdb->prefix}idemailwiz_campaigns` SET experimentIds = %s WHERE id = %d",
                
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
        $key = to_camel_case($key);

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
        if ($table_name == $wpdb->prefix.'idemailwiz_metrics') {
            $item_name = $metricName;
        }
        $item_id = $item[$id_field]; // Item ID

        if ($query_result !== false) {
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
    // $campaignIds is a dummy variable to prevent errors
    // Iterable doesn't allow API calls per campaign as of September 2023
    $url = 'https://api.iterable.com/api/campaigns';
    try {
        $response = idemailwiz_iterable_curl_call($url);
    } catch (Exception $e) {
        if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
            // Stop execution if more than 5 consecutive 400 errors
            wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
            return;
        }
    }

    // Check if campaigns exist in the API response
    if (!isset($response['response']['campaigns'])) {
        return "Error: No campaigns found in the API response.";
    }

    // Return the campaigns array
    return $response['response']['campaigns'];
}

function idemailwiz_fetch_templates($campaignIds = null)
{
    $allTemplates = [];

    // Perform the initial API call to fetch all templates
    $allTemplatesResponse = idemailwiz_iterable_curl_call("https://api.iterable.com/api/templates");

    if (!empty($allTemplatesResponse['response']['templates'])) {
        $allTemplates = $allTemplatesResponse['response']['templates'];
    }

    // Fetch the templates in batches using multi cURL
    $urlsToFetch = [];
    foreach ($allTemplates as $template) {
        $templateId = $template['templateId'];
        $mediumEndpoint = strtolower($template['messageMedium']);
        $urlsToFetch[] = "https://api.iterable.com/api/templates/$mediumEndpoint/get?templateId=$templateId";
    }

    try {
        $multiResponses = idemailwiz_iterable_curl_multi_call($urlsToFetch);
        foreach ($multiResponses as $response) {
            if ($response['httpCode'] == 200) {
                $simplifiedTemplate = idemailwiz_simplify_templates_array($response['response']);
                $allTemplates[] = $simplifiedTemplate;
            }
        }
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
    $startFetchDate = $today->modify('-4 weeks')->format('Y-m-d');

    $fetchCampArgs = array(
        'messageMedium' => 'Email',
        'type' => 'Blast',
        'startAt_start' => $startFetchDate
    );

    if ($campaignIds) {
        $fetchCampArgs['campaignIds'] = $campaignIds;
    }

    $allCampaigns = get_idwiz_campaigns($fetchCampArgs);

    $data = [];
    $allExpMetrics = [];
    foreach ($allCampaigns as $campaign) {
        $url = 'https://api.iterable.com/api/experiments/metrics?campaignId=' . $campaign['id'];
        try {
            $response = idemailwiz_iterable_curl_call($url);
        } catch (Exception $e) {
            if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
                // Stop execution if more than 5 consecutive 400 errors
                wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
                return;
            }
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
        if (++$batchCount % 100 == 0) {
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
        $getString = '?campaignId=' . implode('&campaignId=', $batch);

        $url = "https://api.iterable.com/api/campaigns/metrics" . $getString;
        try {
            $response = idemailwiz_iterable_curl_call($url);
        } catch (Exception $e) {
            if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
                // Stop execution if more than 5 consecutive 400 errors
                wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
                return;
            }
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




function idemailwiz_fetch_purchases($campaignIds = null) {
    date_default_timezone_set('UTC'); // Set timezone to UTC to match Iterable
    

    // Define the base URL
    $baseUrl = 'https://api.iterable.com/api/export/data.csv';

    // Define the fields to be omitted
    $omitFields = [
        'shoppingCartItems.orderDetailId',
        'shoppingCartItems.parentOrderDetailId',
        'shoppingCartItems.predecessorOrderDetailId',
        'shoppingCartItems.financeUnitId',
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
        'omitFields' => implode(',', $omitFields)
    ];

    // Handle the campaign IDs if provided
    if ($campaignIds) {
        if (count($campaignIds) === 1) {
            // If there's only one campaign ID, add it directly
            $queryParams['campaignId'] = $campaignIds[0];
        } else {
            // If multiple campaign IDs, find the earliest date
            $campaigns = get_idwiz_campaigns(['campaignIds' => $campaignIds]);
            $earliestDate = min(array_column($campaigns, 'startAt'));
            $startDateTime = date('Y-m-d', $earliestDate / 1000);
        }
    }

    // Define the start and end date time for the API call
    $startDateTime = date('Y-m-d', strtotime('-10 days'));
    $endDateTime = date('Y-m-d', strtotime('+1 day')); // End date is always today

    // Add the start and end datetime to the query parameters
    $queryParams['startDateTime'] = $startDateTime;
    $queryParams['endDateTime'] = $endDateTime;

    // Build the query string
    $queryString = http_build_query($queryParams);

    // Combine the base URL with the query string
    $url = $baseUrl . '?' . $queryString;

    try {
        $response = idemailwiz_iterable_curl_call($url);
    } catch (Exception $e) {
        if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
            // Stop execution if more than 5 consecutive 400 errors
            wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
            return;
        }
    }
    

    // Split the CSV data into lines
    $lines = explode("\n", trim($response['response']));

    // Parse the header line into headers
    $headers = str_getcsv(array_shift($lines));

    // Prepare the headers by replacing periods with underscores and making them lowercase
    // Also, handle the '_id' field specially to convert it to 'id'
    $processedHeaders = array_map(function ($header) {
        // Remove existing underscores (to handle the 'id' field)
        $headerWithoutUnderscores = str_replace('_', '', $header);
        // Replace periods with new underscores and make lowercase
        return strtolower(str_replace('.', '_', $headerWithoutUnderscores));
    }, $headers);

    // Prepare the omit fields to match the processed headers format
    $omitFields[] = 'shoppingCartItems';
    $processedOmitFields = array_map(function ($field) {
        // Special handling for '_id' field to be 'id'
        if ($field === '_id') {
            return 'id';
        }
        return strtolower(str_replace('.', '_', $field));
    }, $omitFields);

    $allPurchases = []; // Initialize $allPurchases as an array

    // Iterate over the lines of data
    foreach ($lines as $line) {
        if (empty($line)) continue; // Skip empty lines
    
        // Parse the line into values
        $values = str_getcsv($line);
    
        // Combine headers and values into an associative array
        $purchaseData = array_combine($processedHeaders, $values);
    
        // Filter out the values with omitted headers and clean up values
        $filteredPurchaseData = [];
        foreach ($purchaseData as $key => $value) {
            if (!in_array($key, $processedOmitFields)) {
                // Remove square brackets and quotes
                $cleanValue = str_replace(['[', ']', '"'], '', $value);
                $filteredPurchaseData[$key] = $cleanValue;
            }
        }
    
        // Add the filtered data to the purchases array
        $allPurchases[] = $filteredPurchaseData;
    }


    // Return the data array
    return $allPurchases;


}


function idemailwiz_sync_campaigns($passedCampaigns = null)
{

    // If no campaigns are passed, we fetch them from Iterable
    $campaigns = idemailwiz_fetch_campaigns($passedCampaigns);

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_campaigns';

    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];

    // For passed campaigns, we just update them all
    if ($passedCampaigns) {
        $records_to_update = $campaigns;
        // Otherwise, we do our logic for update vs insert from the Iterable API
    } else {
        foreach ($campaigns as $campaign) {
            if ($campaign['campaignState'] == 'Finished' || $campaign['campaignState'] == 'Running') {
                // Check for an existing campaign in the database
                $wizCampaign = get_idwiz_campaign($campaign['id']);

                if ($wizCampaign) {
                    $fieldsDifferent = false;


                    foreach ($campaign as $key => $value) {
                        // Perform deep comparison
                        if (!isset($wizCampaign[$key]) || $wizCampaign[$key] != $value) {
                            $fieldsDifferent = true;
                            break;
                        }

                    }

                    // Update the row if any field is different
                    if ($fieldsDifferent) {
                        // If this is a triggered campaign, get the latest startAt value from our DB
                        if ($campaign['type'] == 'Triggered') {
                            $latestStartAt = get_latest_triggered_startAt($campaign['id']);
                            if ($latestStartAt !== null) {
                                $campaign['startAt'] = $latestStartAt;


                            } else {
                                // Skip this campaign if not found in the wp_idemailwiz_triggered_sends database
                                continue;
                            }


                        }

                        $records_to_update[] = $campaign;
                    }
                } else {
                    // Campaign not in DB, we'll add it
                    $records_to_insert[] = $campaign;
                }
            }
        }

    }

    // Does our wiz_logging and returns the returns data about the insert/update
    return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);

}

global $wpdb; // Make sure to declare this as global if your function is inside a function scope

function get_latest_triggered_startAt($campaignId)
{
    global $wpdb;

    $table_name = $wpdb->prefix.'idemailwiz_triggered_sends';

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
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    // Fetch all existing purchase IDs from the database in one go
    //$existing_purchase_ids = $wpdb->get_col("SELECT id FROM $table_name");
    $existing_purchases = get_idwiz_purchases(['startAt_start'=>'2022-11-01','fields'=>'id']);
    $existing_purchase_ids = array_column($existing_purchases, 'id');

    $records_to_insert = [];
    $records_to_update = [];

    foreach ($purchases as $purchase) {
        // Check if the purchase ID is not in the array of existing purchases
        if (!in_array($purchase['id'], $existing_purchase_ids)) {
            $records_to_insert[] = $purchase;
        } else {
            $records_to_update[] = $purchase;
        }
    }

    return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}



function idemailwiz_sync_experiments($passedCampaigns = null)
{

    $experiments = idemailwiz_fetch_experiments($passedCampaigns);

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_experiments';

    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];

    if ($passedCampaigns) {
        $records_to_update = $experiments;
    } else {
        foreach ($experiments as $experiment) {
            $wizExperiment = get_idwiz_experiment($experiment['templateId']);

            // Check for experiment in database
            if ($wizExperiment) {
                // Mark existing rows for update
                if (!in_array($experiment, $records_to_update)) {
                    $records_to_update[] = $experiment;
                }

            } else {
                // experiment not in db, we'll add it
                $records_to_insert[] = $experiment;
            }
        }
    }

    // Does our wiz_logging and returns the returns data about the insert/update
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

    // Does our wiz_logging and returns the returns data about the insert/update
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

    $logChunk .= ucfirst($type) . " sync results: \n";

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

    $logInsert = '';
    $logUpdate = '';
    // Check and log insert results if available
    if (isset($insert_results)) {
        $logInsert = log_wiz_api_results($insert_results, 'insert');
    }

    // Check and log update results if available
    if (isset($update_results)) {
        $logUpdate = log_wiz_api_results($update_results, 'update');
    }

    // Build a quick string so we can check if it's blank (much faster than checking all the arrays)
    $logSync = $logInsert . $logUpdate;

    // If neither insert nor update results are available, there was nothing to sync or error, return early
    if (!$logSync) {
        $tableNameParts = explode('_', $table_name);
        $tableNameType = end($tableNameParts);
        return "The $tableNameType sync is up to date! No inserts or updates are needed.";
    }

    return $logInsert . "\n" . $logUpdate;
}

function log_wiz_api_results($results, $type)
{
    if (empty($results['success']) && empty($results['errors'])) {
        return ""; // Return an empty string if no operations performed
    }

    if (!isset($results['success'], $results['errors'])) {
        return "Invalid {$type} results structure.";
    }

    $return = '';
    $cntSuccess = 0;
    $cntErrors = 0;
    foreach ($results['success'] as $message) {
        $cntSuccess++;
        //$return .= "Success ({$type}): " . $message . "\n";
    }
    $return .= "Successful {$type} of $cntSuccess records.";
    foreach ($results['errors'] as $message) {
        $cntErrors++;
        $return .= "$cntErrors errors occurred:" . "\n" . "Error ({$type}): " . $message . "\n";
    }

    //returning an empty string is necessary here when no updates for return_insert_update_logging to handle the return value properly
    return rtrim($return); // Removing the trailing newline, if any
}



// Ajax handler for sync button
// Also creates and logs readable sync responses from response arrays
function idemailwiz_sync_non_triggered_metrics($campaignIds = null)
{
    $syncArgs = [];
    $response = [];

    if ($campaignIds) {
        $wizCampaignIds = get_idwiz_campaigns(array('campaignIds' => $campaignIds, 'fields' => 'id'));
    }

    $sync_dbs = ['campaigns', 'templates', 'metrics', 'purchases', 'experiments'];
    foreach ($sync_dbs as $db) {
        if ($campaignIds) {
            $syncArgs = array_column($wizCampaignIds, 'id');
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

    return $response;
}

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

    $campaignIds = null;
    if (isset($_POST['campaignIds'])) {
        $campaignIds = json_decode(stripslashes($_POST['campaignIds']), true) ?? false;
        if (!is_array($campaignIds)) {
            $campaignIds = array($campaignIds);
        }
    }

    $response = idemailwiz_sync_non_triggered_metrics($campaignIds);

    if (isset($response['error'])) {
        wp_send_json_error($response['error']);
    } else {
        wp_send_json_success($response);
    }
}
add_action('wp_ajax_idemailwiz_ajax_sync', 'idemailwiz_ajax_sync');











function wiz_log($something, $timestamp = true)
{

    // Get the current date and time in PST
    $date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
    $timestamp = $date->format('Y-m-d H:i:s');


    // Build the breaker bar with the timestamp
    $breakerBar = str_repeat('=', 40); // 40 is the length of the bar
    if ($timestamp) {
        $timestampPosition = (strlen($breakerBar) - strlen($timestamp)) / 2;
        $breakerBarWithTimestamp = substr_replace($breakerBar, "[$timestamp]", $timestampPosition, 0);

        // Build the log entry
        $logEntry = "{$breakerBarWithTimestamp}\n$something\n$breakerBar\n\n";
    } else {
        // Build the log entry
        $logEntry = "{$breakerBar}\n$something\n$breakerBar\n\n";
    }


    // Replace line breaks with <br/> tags
    $logEntry = nl2br($logEntry);

    // Get the path to the log file
    $logFile = dirname(plugin_dir_path(__FILE__)) . '/sync-log.txt';

    // Read the existing content of the file
    $existingContent = file_exists($logFile) ? file_get_contents($logFile) : '';

    // Prepend the new log entry to the existing content
    $combinedContent = $logEntry . $existingContent;

    // Write the combined content back to the file
    $writeToLog = file_put_contents($logFile, $combinedContent);

    return $writeToLog; // returns number of bytes logged or false on failure
}

function ajax_to_wiz_log()
{

    // Bail early without valid nonce
    if (
        check_ajax_referer('data-tables', 'security', false) ||
        check_ajax_referer('initiatives', 'security', false) ||
        check_ajax_referer('wiz-metrics', 'security', false) ||
        check_ajax_referer('id-general', 'security', false)
    ) {
        // Nonce is valid and belongs to one of the specified referers
    } else {
        // Invalid nonce or referer
        wp_die('Invalid action or nonce');
    }

    $logData = $_POST['log_data'] ?? '';
    $timestamp = $_POST['timestamp'] ?? false;

    $writeToLog = wiz_log($logData, $timestamp);

    wp_send_json_success($writeToLog);
}
add_action('wp_ajax_ajax_to_wiz_log', 'ajax_to_wiz_log');





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


// Schedule the non-triggered sync
if (!wp_next_scheduled('idemailwiz_hourly_sync')) {
    wp_schedule_event(time(), 'hourly', 'idemailwiz_hourly_sync');
}

// Schedule the twice daily sync
if (!wp_next_scheduled('idemailwiz_twice_daily_sync')) {
    wp_schedule_event(time(), 'twicedaily', 'idemailwiz_twice_daily_sync');
}

// Hooks
add_action('idemailwiz_hourly_sync', 'idemailwiz_hourly_sync_and_schedule_triggered');

function idemailwiz_hourly_sync_and_schedule_triggered()
{
    $wizSettings = get_option('idemailwiz_settings');
    $syncToggle = $wizSettings['iterable_sync_toggle'];

    if ($syncToggle == 'on') {
        // Perform the non-triggered sync
        idemailwiz_sync_non_triggered_metrics();

        // Schedule the triggered sync for 'send'
        if (!wp_next_scheduled('idemailwiz_hourly_triggered_sync_send')) {
            //wp_schedule_single_event(time() + 3 * MINUTE_IN_SECONDS, 'idemailwiz_hourly_triggered_sync_send');
        }

        // Schedule the triggered sync for 'open'
        if (!wp_next_scheduled('idemailwiz_hourly_triggered_sync_open')) {
            //wp_schedule_single_event(time() + 5 * MINUTE_IN_SECONDS, 'idemailwiz_hourly_triggered_sync_open');
        }

        // Schedule the triggered sync for 'click'
        if (!wp_next_scheduled('idemailwiz_hourly_triggered_sync_click')) {
            //wp_schedule_single_event(time() + 10 * MINUTE_IN_SECONDS, 'idemailwiz_hourly_triggered_sync_click');
        }
    } else {
        error_log('Cron sync was initiated but sync is disabled');
    }
}

add_action('idemailwiz_hourly_triggered_sync_send', function () {
    idemailwiz_sync_triggered_metrics('send');
});
add_action('idemailwiz_hourly_triggered_sync_open', function () {
    idemailwiz_sync_triggered_metrics('open');
});
add_action('idemailwiz_hourly_triggered_sync_click', function () {
    idemailwiz_sync_triggered_metrics('click');
});
add_action('idemailwiz_twice_daily_sync', 'sync_ga_campaign_revenue_data');



function idemailwiz_get_todays_triggered_send_ids()
{
    // Try to get data from the transient
    $uniqueCampaignIds = get_transient('idemailwiz_triggered_send_ids');

    if ($uniqueCampaignIds === false) {
        // Data not found in transient, fetch fresh data
        $uniqueCampaignIds = [];
        $iterableSendStart = new DateTimeImmutable('-3 hours', new DateTimeZone('UTC'));
        $sendStartFormatted = $iterableSendStart->format('Y-m-d H:i:s');
        $encodedDateTime = urlencode($sendStartFormatted);

        wiz_log("Checking for recent sends from Iterable...");
        try {
            $response = idemailwiz_iterable_curl_call('https://api.iterable.com/api/export/data.json?dataTypeName=emailSend&startDateTime=' . $encodedDateTime . '&onlyFields=campaignId');

            // Split the response string by lines
            $lines = explode("\n", trim($response['response']));

            // Loop through each line and decode the JSON object
            foreach ($lines as $line) {
                $campaignData = json_decode($line, true);
                if ($campaignData && isset($campaignData['campaignId'])) {
                    $wizCampaign = get_idwiz_campaign($campaignData['campaignId']);
                    if ($wizCampaign['type'] != 'Blast') {
                        $uniqueCampaignIds[$campaignData['campaignId']] = true;
                    }
                }
            }

            // Convert associative array keys to a normal indexed array
            $uniqueCampaignIds = array_keys($uniqueCampaignIds);

            // Store the result in a transient that expires after 24 hours (86400 seconds)
            set_transient('idemailwiz_triggered_send_ids', $uniqueCampaignIds, 86400);
        } catch (Exception $e) {
            wiz_log("Error getting past 24 hours sent campaigns: $e");
            return;
        }
    } else {
        $countIds = count($uniqueCampaignIds);
        wiz_log("Using cached data for last 24 hours of triggered sends. Requesting $countIds campaigns from Iterable (1 per second max)...");
    }

    return $uniqueCampaignIds;
}


function idemailwiz_sync_triggered_metrics($metricType)
{
    wiz_log("Starting triggered {$metricType}s sync...");
    global $wpdb;

    // Initialize or fetch $triggeredCampaigns
    //$triggeredCampaigns = $triggeredCampaigns ?? idemailwiz_get_todays_triggered_send_ids();
    $triggeredCampaigns = get_idwiz_campaigns(['type' => 'triggered', 'campaignState' => 'Running']);
    $cntTriggered = count($triggeredCampaigns);
    wiz_log("Requesting {$metricType}s for $cntTriggered triggered campaigns (max 1 per second)...");

    $jobIds = [];
    $logFetched = 0;
    $log400s = 0;
    foreach ($triggeredCampaigns as $campaign) {
        $campaignId = (int) $campaign['id'];
        $messageMedium = $campaign['messageMedium'];

        //$exportEvent = $messageMedium == 'Email' ? 'emailSend' : 'smsSend';
        $ucMetricType = ucfirst($metricType);
        if ($messageMedium == 'Email') {
            $exportEvent = 'email' . $ucMetricType;
        } else {
            if ($metricType != 'open') {
                $exportEvent = 'sms' . $ucMetricType;
            } else {
                // No open metrics for SMS
                continue;
            }
        }

        //$exportFetchStart = new DateTimeImmutable('November 1, 2021');
        $exportFetchStart = new DateTimeImmutable('-7 days');
        //$exportFetchEnd = new DateTimeImmutable('May 31, 2022');
        $exportStartData = [
            "outputFormat" => "application/x-json-stream",
            "dataTypeName" => $exportEvent,
            "campaignId" => $campaignId,
            "delimiter" => ",",
            "onlyFields" => "createdAt,campaignId,templateId,messageId",
            "startDateTime" => $exportFetchStart->format('Y-m-d'),
            //"EndDateTime" => $exportFetchEnd->format('Y-m-d'),
            //Reminder: The 'range' parameter doesn't work on this endpoint so we have to use start and end
        ];

        try {
            $response = idemailwiz_iterable_curl_call('https://api.iterable.com/api/export/start', $exportStartData);
            $jobId = $response['response']['jobId'];
            $jobIds[$campaignId] = $jobId;
            $logFetched++;
        } catch (Exception $e) {
            if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
                wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
                return;
            }
            $log400s++;
            continue;
        }
        sleep(1);
    }

    wiz_log("Requested $logFetched triggered campaign {$metricType}s from Iterable. $log400s errors encountered.\nRetrieving data...");

    $cntRecords = 0; // Counter for new records
    $tableName = $wpdb->prefix . 'idemailwiz_triggered_' . $metricType . 's';

    foreach ($jobIds as $campaignId => $jobId) {
        //error_log('Fetching triggered '.$metricType.' data for campaign: '. $campaignId . ' Job ID: ' . $jobId);
        while (true) {
            $apiResponse = idemailwiz_iterable_curl_call('https://api.iterable.com/api/export/' . $jobId . '/files');
            if (in_array($apiResponse['response']['jobState'], ['completed', 'failed'])) {
                break;
            }
            sleep(1);
        }
        if ($apiResponse['response']['jobState'] == "completed") {
            // Loop through each file URL
            foreach ($apiResponse['response']['files'] as $file) {
                // Fetch JSON data from the URL
                $jsonResponse = file_get_contents($file['url']);

                // Split the string by newline characters to get an array of lines
                $lines = explode("\n", $jsonResponse);

                // Loop through each line
                foreach ($lines as $line) {
                    // Skip empty lines
                    if (trim($line) === '')
                        continue;

                    // Decode each line individually
                    $decodedData = json_decode($line, true);

                    // Check for JSON errors
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log('json_decode error: ' . json_last_error_msg());
                        continue;
                    }

                    // Check if the record exists in the database
                    if (isset($decodedData['campaignId'], $decodedData['createdAt'], $decodedData['messageId'], $decodedData['templateId'])) {
                        $messageId = $decodedData['messageId'];
                        $exists = $wpdb->get_var($wpdb->prepare("SELECT messageId FROM $tableName WHERE messageId = %s", $messageId));

                        // If it doesn't exist, insert the record into the database
                        if (!$exists) {
                            $record = [
                                'messageId' => $messageId,
                                'campaignId' => $decodedData['campaignId'],
                                'templateId' => $decodedData['templateId'],
                                'startAt' => strtotime($decodedData['createdAt']) * 1000,
                            ];
                            idemailwiz_insert_triggered_metric_record($record, $metricType);
                            $cntRecords++;
                        }
                    }
                }
            }
        }
    }

    wiz_log("Finished updating $cntRecords triggered $metricType records!");
    return true;

}

function idemailwiz_insert_triggered_metric_record($record, $metricType)
{
    global $wpdb;
    $tableName = $wpdb->prefix . 'idemailwiz_triggered_' . $metricType . 's';

    if (!is_array($record) || empty($record) || !isset($record['messageId'])) {
        wiz_log("No new $metricType records found for campaign " . $record['campaignId'] . ".");
        return false;
    }

    // Check if a record with the same messageId already exists in the database
    $existingRecord = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $tableName WHERE messageId = %s",
            $record['messageId']
        ),
        ARRAY_A
    );

    // If an existing record is found and the current record has an earlier startAt date, update the record
    if ($existingRecord && $record['startAt'] < $existingRecord['startAt']) {
        $data = [
            'startAt' => $record['startAt'],
        ];
        $where = ['messageId' => $record['messageId']];
        $result = $wpdb->update($tableName, $data, $where);
    } elseif (!$existingRecord) {
        // If no existing record is found, insert the new record
        $data = [
            'messageId' => $record['messageId'],
            'campaignId' => $record['campaignId'],
            'templateId' => $record['templateId'],
            'startAt' => $record['startAt'],
        ];
        $result = $wpdb->insert($tableName, $data);
    } else {
        // The current record is older or the same as the existing record, so no action is needed
        $result = true;
    }

    // Log the outcome of the insert/update
    if ($result) {
        // Log success if a new record was inserted or an existing record was updated
        //wiz_log("Successfully inserted/updated record for messageId: " . $record['messageId']);
    } else {
        wiz_log("Failed to insert/update $metricType record for messageId: " . $record['messageId'] . ' on campaign ' . $record['campaignId']);
    }

    return $result;
}

function sync_single_triggered_campaign()
{
    $campaignId = $_POST['campaignId'];
    check_ajax_referer('id-general', 'security');

    idemailwiz_import_triggered_metrics_from_api([$campaignId], 'send');
    idemailwiz_import_triggered_metrics_from_api([$campaignId], 'open');
    idemailwiz_import_triggered_metrics_from_api([$campaignId], 'click');
}
add_action('wp_ajax_sync_single_triggered_campaign', 'sync_single_triggered_campaign');



//$syncFromCsvCampaigns = get_idwiz_campaigns(['type'=>'Triggered', 'sortBy'=>'startAt','limit'=> 20,]);
if (isset($syncFromCsvCampaigns)) {
    foreach ($syncFromCsvCampaigns as $campaign) {
        idemailwiz_import_triggered_metrics_from_api([$campaign['id']], 'send');
        idemailwiz_import_triggered_metrics_from_api([$campaign['id']], 'open');
        idemailwiz_import_triggered_metrics_from_api([$campaign['id']], 'click');
        sleep(15);

    }
}
function idemailwiz_import_triggered_metrics_from_api($campaignIds, $metricType)
{
    foreach ($campaignIds as $campaignId) {
        $apiEndpoint = 'https://api.iterable.com/api/export/data.csv?dataTypeName=email' . ucfirst($metricType) . '&range=All&delimiter=%2C&onlyFields=campaignId&onlyFields=createdAt&onlyFields=messageId&onlyFields=templateId&campaignId=' . $campaignId;
        // Use cURL call function to fetch the CSV data from the API
        $apiResponse = idemailwiz_iterable_curl_call($apiEndpoint);
        $apiCsv = $apiResponse['response'];

        if ($apiCsv && !in_array($apiResponse, [400, 401, 429])) {
            // Save the CSV data to a temporary file
            $tempCsvFilePath = tempnam(sys_get_temp_dir(), 'iterable_csv_');
            file_put_contents($tempCsvFilePath, $apiCsv);

            // Import the CSV data into the database using the existing function
            idemailwiz_import_triggered_metrics_from_csv($tempCsvFilePath, $metricType);

            // Delete the temporary CSV file
            unlink($tempCsvFilePath);
        }
    }
}




function idemailwiz_import_triggered_metrics_from_csv($localCsvFilePath, $metricType)
{
    // Open the local CSV file
    $tempFile = fopen($localCsvFilePath, 'r');

    if (!$tempFile) {
        error_log("Failed to open local CSV file.");
        return false;
    }

    $header = fgetcsv($tempFile);

    // Mapping of field to index
    $indexMapping = array_flip($header);

    // Create an array to store the records
    $uniqueRecords = [];

    while ($row = fgetcsv($tempFile)) {
        if (
            is_array($row) &&
            isset($indexMapping['campaignId'], $indexMapping['createdAt'], $indexMapping['messageId'], $indexMapping['templateId']) &&
            isset($row[$indexMapping['campaignId']], $row[$indexMapping['createdAt']], $row[$indexMapping['messageId']], $row[$indexMapping['templateId']])
        ) {
            $messageId = $row[$indexMapping['messageId']];
            $createdAt = strtotime($row[$indexMapping['createdAt']]) * 1000;

            // Check if the messageId already exists in $uniqueRecords
            if (isset($uniqueRecords[$messageId])) {
                // If the current row has an earlier createdAt date, update the record
                if ($createdAt < $uniqueRecords[$messageId]['startAt']) {
                    $uniqueRecords[$messageId] = [
                        'messageId' => $messageId,
                        'campaignId' => $row[$indexMapping['campaignId']],
                        'templateId' => $row[$indexMapping['templateId']],
                        'startAt' => $createdAt,
                    ];
                }
            } else {
                // If messageId doesn't exist in $uniqueRecords, add it as a new record
                $uniqueRecords[$messageId] = [
                    'messageId' => $messageId,
                    'campaignId' => $row[$indexMapping['campaignId']],
                    'templateId' => $row[$indexMapping['templateId']],
                    'startAt' => $createdAt,
                ];
            }
        }
    }

    fclose($tempFile);

    // Insert unique records into the database
    foreach ($uniqueRecords as $record) {
        idemailwiz_insert_triggered_metric_record($record, $metricType);
    }

    error_log("Finished inserting new $metricType records for triggered campaigns from CSV.");
    return true;
}