<?php
// Include WordPress' database functions
global $wpdb;



function idemailwiz_iterable_curl_call($apiURL, $postData = null, $verifySSL = false, $retryAttempts = 3, $maxConsecutive400Errors = 5)
{
    // Fetch the API key
    $api_key = idwiz_itAPI();

    $attempts = 0;
    $consecutive400Errors = 0;

    do {
        // Initialize cURL
        $ch = curl_init($apiURL);

        // Set SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);

        // If POST data is provided, set up a POST request
        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }

        // Set the HTTP headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Api-Key: $api_key",
            "Content-Type: application/json"
        )
        );

        // Return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Execute the request
        $response = curl_exec($ch);

        // Get the HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close cURL
        curl_close($ch);

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

    } while ($httpCode === 400);

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
    $api_key = idwiz_itAPI();

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
    if ($table_name == 'wp_idemailwiz_templates' || $table_name == 'wp_idemailwiz_experiments') {
        $id_field = 'templateId';
    }
    foreach ($items as $key => $item) {
        // Add 'name' to the metrics array
        if ($table_name == 'wp_idemailwiz_metrics') {
            $metricCampaign = get_idwiz_campaign($item['id']);
            $metricName = $metricCampaign['name'];
        }

        // If this is a template record, we check if there's a wiz builder template with this template ID set as the sync-to
        if ($table_name == 'wp_idemailwiz_templates') {
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
                    "UPDATE wp_idemailwiz_templates SET clientTemplateId = %d WHERE templateId = %d",
                    $builderTemplates[0]->ID,
                    $wizDbTemplate['templateId']

                );
                $wpdb->query($sql);
            } else {
                $sql = $wpdb->prepare(
                    "UPDATE wp_idemailwiz_templates SET clientTemplateId = NULL WHERE templateId = %d",
                    $incomingTemplateId
                );
                $wpdb->query($sql);
            }

        }

        //If this is an experiment record, we check if the corrosponding campaign in the campaign table has the experiment ID present. If not, add it.
        if ($table_name == 'wp_idemailwiz_experiments') {
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
                "UPDATE wp_idemailwiz_campaigns SET experimentIds = %s WHERE id = %d",
                $serializedExperimentIds,
                $item['campaignId']
            );
            $wpdb->query($sql);
        }

        // Serialize values that are arrays
        foreach ($item as $childKey => $childValue) {
            if (is_array($childValue)) {
                if (empty($childValue)) {
                    $item[$childKey] = null;  // Set to NULL if the array is empty
                } else {
                    $serialized = serialize($childValue);
                    $item[$childKey] = $serialized;
                }
            }
        }

        // Convert key/header to camel case for db compatibility
        $key = to_camel_case($key);

        $fields = implode(",", array_keys($item));
        $values = "'" . implode("','", array_map('esc_sql', $item)) . "'";
        $updates = implode(",", array_map(function ($field, $value) {
            return "{$field}='" . esc_sql($value) . "'";
        }, array_keys($item), $item));

        $sql = $operation === "insert" ?
            "INSERT INTO {$table_name} ({$fields}) VALUES ({$values})" :
            "UPDATE {$table_name} SET {$updates} WHERE {$id_field}={$item[$id_field]}";

        // Do the insert/update
        $query_result = $wpdb->query($sql);

        // Extracting relevant details for logging
        $item_name = isset($item[$name_field]) ? $item[$name_field] : ''; // Item name
        if ($table_name == 'wp_idemailwiz_metrics') {
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
    $templateAPIurls = [];

    // Fetch campaigns based on whether IDs are provided
    $wizCampaigns = $campaignIds ? get_idwiz_campaigns(['ids' => $campaignIds]) : get_idwiz_campaigns();

    if ($campaignIds) {
        foreach ($wizCampaigns as $campaign) {
            $medium = $campaign['messageMedium'];
            $type = $campaign['type'];

            $key = strtolower($medium) . strtolower($type);
            $templateAPIurls[$key] = "https://api.iterable.com/api/templates?templateType=$type&messageMedium=$medium";
        }
    } else {
        // Initialize all 4 API URLs for fetching all types of templates
        $typesAndMediums = [
            'blastEmails' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=Email',
            'triggeredEmails' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=Email',
            'blastSMS' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=SMS',
            'triggeredSMS' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=SMS'
        ];
        $templateAPIurls = array_merge($templateAPIurls, $typesAndMediums);
    }

    // Perform the initial API calls and gather basic templates
    $getProjectTemplates = [];
    foreach ($templateAPIurls as $APIendpoint) {
        try {
            $response = idemailwiz_iterable_curl_call($APIendpoint);
            if (!empty($response['response']['templates'])) {
                $getProjectTemplates = array_merge($getProjectTemplates, $response['response']['templates']);
            }
        } catch (Exception $e) {
            wiz_log("Error during initial API call: " . $e->getMessage());
        }
    }

    // Determine which templates actually need to be fetched in detail
    $urlsToFetch = [];
    foreach ($getProjectTemplates as $template) {
        $templateId = $template['templateId'];
        $associatedCampaign = array_filter($wizCampaigns, function ($campaign) use ($templateId) {
            return $campaign['templateId'] == $templateId;
        });
        $associatedCampaign = array_shift($associatedCampaign); // Get the first matching campaign
        if ($associatedCampaign) {
            $mediumEndpoint = strtolower($associatedCampaign['messageMedium']);
            $wizTemplate = get_idwiz_template($templateId);

            if (!$wizTemplate || ($wizTemplate && $wizTemplate['updatedAt'] != $template['updatedAt'])) {
                $urlsToFetch[] = "https://api.iterable.com/api/templates/$mediumEndpoint/get?templateId=$templateId";
            }
        }
    }


    // Fetch the templates in batches using multi cURL
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
        $fetchCampArgs['ids'] = $campaignIds;
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
        $metricCampaignArgs['ids'] = $campaignIds;
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





function idemailwiz_fetch_purchases($campaignIds = null)
{

    // Get purchases for the specified campaigns or get all purchases from the past 30 days
    // If campaignIds are specified, we limit our API call by date based on the earliest campaign startAt date

    date_default_timezone_set('UTC'); // UTC to match Iterable
    $startDateTime = date('Y-m-d H:i:s', strtotime('-30 days'));
    $endDateTime = date('Y-m-d H:i:s'); // End date is always today
    $url = 'https://api.iterable.com/api/export/data.csv?dataTypeName=purchase&delimiter=%2C&omitFields=shoppingCartItems.StudentFirstName%2CshoppingCartItems.StudentLastName%2Cemail%2CshoppingCartItems.StudentFirstName%2CshoppingCartItems.StudentLastName';

    if ($campaignIds) {
        if (count($campaignIds) == 1) {
            $url = $url . '&campaignId=' . $campaignIds[0];
        } else {
            $campaigns = get_idwiz_campaigns(array('ids' => $campaignIds));
            $earliestDate = strtotime('today') * 1000;
            foreach ($campaigns as $campaign) {
                if ($campaign['startAt'] < $earliestDate) {
                    $earliestDate = $campaign['startAt'];
                }
            }
            $startDateTime = date('Y-m-d H:i:s', $earliestDate / 1000);
        }
    }

    // URL encode the date parameters
    $encodedStartDateTime = urlencode($startDateTime);
    $encodedEndDateTime = urlencode($endDateTime);

    // Append the new parameters to the existing URL
    $url = $url . "&startDateTime={$encodedStartDateTime}&endDateTime={$encodedEndDateTime}";

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

    // Swap in underscores for periods
    $headers = array_map(function ($header) {
        // Remove existing underscores (to handle the 'id' field)
        $header = str_replace('_', '', $header);
        // Replace periods with new underscores
        $header = str_replace('.', '_', $header);
        return lcfirst($header); // Lowercase first letter
    }, $headers);

    $allPurchases = []; // Initialize $data as an array

    // Iterate over the non-header lines
    for ($i = 1; $i < count($lines); $i++) {
        // Parse the line into values
        $values = str_getcsv($lines[$i]);

        // Check if the number of headers and values matches
        if (count($headers) != count($values)) {
            continue;
        }

        // Clean values
        $values = array_map(function ($value) {
            $value = str_replace(['[', ']', '"'], '', $value);
            return $value;
        }, $values);

        // Combine headers and values into an associative array
        $allPurchases[] = array_combine($headers, $values);
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

function get_latest_triggered_startAt($campaignId) {
    global $wpdb;

    $table_name = 'wp_idemailwiz_triggered_sends';
    
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

    // If a campaign IDs are passed to the fetch function, we'll only get purchases for dates on or after the earliest campaign
    // If campaign IDs is null, it will sync all purchases for the past 30 days
    $purchases = idemailwiz_fetch_purchases($campaignIds);

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    // Prepare array
    $records_to_insert = [];

    foreach ($purchases as $purchase) {

        $wizPurchase = get_idwiz_purchase($purchase['id']);

        if (!$wizPurchase) {
            // purchase not in db, we'll add it
            $records_to_insert[] = $purchase;
        } else {
            //error_log("Purchase with ID " . $purchase['id'] . " already exists in DB. Skipping.");
        }
    }

    // Does our wiz_logging and returns the returns data about the insert/update
    return idemailwiz_process_and_log_sync($table_name, $records_to_insert, null);

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
    
    $metrics = idemailwiz_fetch_metrics($passedCampaigns);// Gets all metrics if none are passed
    
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
function idemailwiz_sync_everything($campaignIds = null) {
    $syncArgs = [];
    $response = [];

    if ($campaignIds) {
        $wizCampaignIds = get_idwiz_campaigns(array('ids' => $campaignIds, 'fields' => 'id'));
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

function idemailwiz_ajax_sync() {
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

    $response = idemailwiz_sync_everything($campaignIds);

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

// Schedule the event, if not already scheduled
// Get current time in UTC
$currentUTC = new DateTime('now', new DateTimeZone('UTC'));

// Convert to Eastern Time
$currentET = $currentUTC->setTimezone(new DateTimeZone('America/New_York'));

$currentHour = (int) $currentET->format('G');
$currentMinute = (int) $currentET->format('i');

// Schedule or unschedule event based on time
if (($currentHour > 5 || ($currentHour === 5 && $currentMinute >= 30)) && $currentHour < 22) {
    // Schedule event if not already scheduled
    if (!wp_next_scheduled('idemailwiz_hourly_sync')) {
        wp_schedule_event(time(), 'hourly', 'idemailwiz_hourly_sync');
    }
} else {
    // Unschedule event if it is scheduled
    if ($timestamp = wp_next_scheduled('idemailwiz_hourly_sync')) {
        wp_unschedule_event($timestamp, 'idemailwiz_hourly_sync');
    }
}



// Hook into the custom action and run the function
// Sync_everything includes syncing triggered campaigns send records
add_action('idemailwiz_hourly_sync', 'idemailwiz_sync_everything', 1);
add_action('idemailwiz_hourly_sync', 'idemailwiz_sync_triggered_send_records', 2);


function idemailwiz_get_todays_triggered_send_ids() {
    // Try to get data from the transient
    $uniqueCampaignIds = get_transient('idemailwiz_triggered_send_ids');

    if ($uniqueCampaignIds === false) {
        // Data not found in transient, fetch fresh data
        $uniqueCampaignIds = [];
        $last24Hours = new DateTimeImmutable('-24 hours', new DateTimeZone('UTC'));
        $get24HourCampaigns = $last24Hours->format('Y-m-d H:i:s');
        $encodedDateTime = urlencode($get24HourCampaigns);

        wiz_log("Checking last 24 hours of triggered sends from Iterable...");
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
        wiz_log("Using cached data for last 24 hours of triggered sends.");
    }

    return $uniqueCampaignIds;
}


function idemailwiz_sync_triggered_send_records($triggeredCampaigns = null)
{       wiz_log("Starting triggered sends sync...");
        global $wpdb;

        // Initialize or fetch $triggeredCampaigns
        $triggeredCampaigns = $triggeredCampaigns ?? idemailwiz_get_todays_triggered_send_ids();

        // Filter out non-triggered campaigns
        $filteredTriggeredCampaigns = [];
        foreach ($triggeredCampaigns as $campaignId) {
            $wizCampaign = get_idwiz_campaign($campaignId);
            if ($wizCampaign['type'] == 'Triggered') {
                $filteredTriggeredCampaigns[] = $wizCampaign;
            }
        }
        $triggeredCampaigns = $filteredTriggeredCampaigns;

        $jobIds = [];
        $logFetched = 0;
        $log400s = 0;
        foreach ($triggeredCampaigns as $campaign) {
        $campaignId = (int) $campaign['id'];
        $messageMedium = $campaign['messageMedium'];

        $exportEvent = $messageMedium === 'Email' ? 'emailSend' : 'smsSend';

        $exportFetchStart = new DateTimeImmutable('-7 days');
        //$exportFetchEnd = new DateTimeImmutable('June 30, 2023');
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

    wiz_log("Requested $logFetched triggered campaigns from Iterable. $log400s errors encountered.\nRetrieving send data...");

    sleep(1);
    foreach ($jobIds as $campaignId => $jobId) {
        //error_log('Fetching triggered send data for campaign: '. $campaignId);
        while (true) {
            $apiResponse = idemailwiz_iterable_curl_call('https://api.iterable.com/api/export/' . $jobId . '/files');
            if (in_array($apiResponse['response']['jobState'], ['completed', 'failed'])) {
                break;
            }
            sleep(1);
        }
        if ($apiResponse['response']['jobState'] == "completed") {
            // Fetch JSON data from the URL
            $jsonResponse = file_get_contents($apiResponse['response']['files'][0]['url']);
    
            // Decode the JSON string into an associative array
            $decodedData = json_decode($jsonResponse, true) ?? array();

            // Initialize an empty array to store the records
            $records = array();

            // Iterate through the decoded data to populate the records
            foreach ($decodedData as $row) {
                if (
                    is_array($row) &&
                    isset($row['campaignId'], $row['createdAt'], $row['messageId'], $row['templateId'])
                ) {
                    $records[] = [
                        'messageId' => $row['messageId'],
                        'campaignId' => $row['campaignId'],
                        'templateId' => $row['templateId'],
                        'startAt' => strtotime($row['createdAt']) * 1000,
                    ];
                }
            }
        }

    }
    if (!empty($records)) {
        wiz_log('Checking received sends against existing records, and updating...');
        // Fetch all existing messageIds in one query
        $tableName = $wpdb->prefix . 'idemailwiz_triggered_sends';
        $existingRecords = $wpdb->get_col("SELECT messageId FROM $tableName");

        // Convert to associative array for quick look-up
        $existingRecordsMap = array_flip($existingRecords);

        // Loop through captured records from all exports
        foreach ($records as $record) {
            if (isset($existingRecordsMap[$record['messageId']])) {
                //wiz_log("Record with messageId " . $record['messageId'] . " already exists. Skipping.");
                continue;
            }
            idemailwiz_insert_triggered_send_record($record);
        }
       
    } else {
        wiz_log("No new triggered sends were found.");
    }

    wiz_log("Finished updating triggered send records!");
    return true;
}

function idemailwiz_insert_triggered_send_record($record)
{
    global $wpdb;
    $tableName = $wpdb->prefix . 'idemailwiz_triggered_sends';
    if (!is_array($record) || empty($record) || !isset($record['messageId'])) {
        //wiz_log("No new sends found for this campaign.");
        return false;
    }

    // Convert the timestamp to your preferred time zone if needed
   // $date = new DateTime('@' . ($record['startAt'] / 1000), new DateTimeZone('UTC'));
    //$date->setTimezone(new DateTimeZone('America/Los_Angeles'));
    //$timestampPST = $date->getTimestamp() * 1000;

    // Prepare the data array for wpdb
    $data = [
        'messageId' => $record['messageId'],
        'campaignId' => $record['campaignId'],
        'templateId' => $record['templateId'],
        //'startAt' => $timestampPST
        'startAt' => $record['startAt']
    ];

    // Insert the record into the database
    $result = $wpdb->insert($tableName, $data);


    // Log the outcome of the insert
    if ($result) {
        //wiz_log("Successfully inserted record for messageId: " . $record['messageId']);
    } else {
        wiz_log("Failed to insert record for messageId: " . $record['messageId'] . ' on campaign ' . $record['campaignId']);
    }

    return $result;
}



function idemailwiz_import_triggered_sends_from_csv($localCsvFilePath)
{
    // Open the local CSV file
    $tempFile = fopen($localCsvFilePath, 'r');

    if (!$tempFile) {
        wiz_log("Failed to open local CSV file.");
        return false;
    }

    $header = fgetcsv($tempFile);

    // Mapping of field to index
    $indexMapping = array_flip($header);

    $latestTimestampsPerCampaign = [];

    while ($row = fgetcsv($tempFile)) {
        if (
            is_array($row) &&
            isset($indexMapping['campaignId'], $indexMapping['createdAt'], $indexMapping['messageId'], $indexMapping['templateId']) &&
            isset($row[$indexMapping['campaignId']], $row[$indexMapping['createdAt']], $row[$indexMapping['messageId']], $row[$indexMapping['templateId']])
        ) {
            $timestamp = strtotime($row[$indexMapping['createdAt']]) * 1000;
            $campaignId = $row[$indexMapping['campaignId']];

            if (!isset($latestTimestampsPerCampaign[$campaignId]) || $timestamp > $latestTimestampsPerCampaign[$campaignId]) {
                $latestTimestampsPerCampaign[$campaignId] = $timestamp;
            }

            $record = [
                'messageId' => $row[$indexMapping['messageId']],
                'campaignId' => $row[$indexMapping['campaignId']],
                'templateId' => $row[$indexMapping['templateId']],
                'startAt' => strtotime($row[$indexMapping['createdAt']]) * 1000,
            ];
            idemailwiz_insert_triggered_send_record($record, $latestTimestampsPerCampaign);
        }
    }

    fclose($tempFile);
    wiz_log("Finished inserting new send records for triggered campaigns from CSV.");
    return true;
}
