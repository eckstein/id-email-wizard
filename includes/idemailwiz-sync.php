<?php
// Include WordPress' database functions
global $wpdb;



function idemailwiz_iterable_curl_call($apiURL, $postData = null, $verifySSL = false, $retryAttempts = 3, $maxConsecutive400Errors = 5) {
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
        ));

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




function idemailwiz_update_insert_api_data($items, $operation, $table_name) {
    global $wpdb;
    $result = ['success' => [], 'errors' => []];

    // Determine the ID and name fields based on the table name
    $id_field = 'id'; // Default ID field
    $name_field = 'name'; // Default name field

    if ($table_name == 'wp_idemailwiz_templates') {
        $id_field = 'templateId';
    }
    

    foreach ($items as $key => $item) {
        if ($table_name == 'wp_idemailwiz_metrics') {
            $metricCampaign = get_idwiz_campaigns(array('id'=>$item['id']));
            $metricName = $metricCampaign[0]['name'];
        }
        // Serialize values that are arrays
        foreach ($item as $childKey => $childValue) {
            if (is_array($childValue)) {
                $serialized = serialize($childValue);
                $item[$childKey] = $serialized;
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
                    $result['success'][] = "Skipped '{$item_name}' (ID: {$item_id}); no updates needed.";
                }
            } else {
                // Error details
                $result['errors'][] = "Failed to perform {$operation} on '{$item_name}' (ID: {$item_id}). Database Error: {$wpdb->last_error}.";
            }
            
    }

    return $result;
}


function idemailwiz_fetch_campaigns() {
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

function idemailwiz_fetch_templates() {
    
    $templateAPIurls = array(
        'blastEmails' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=Email',
        'triggeredEmails' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=Email',
        'blastSMS' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=SMS',
        'triggeredSMS' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=SMS',
    );
    
    // Initialize empty templates array for best merging later
    $getProjectTemplates = array('templates' => array());
    foreach ($templateAPIurls as $campaignType => $APIendpoint) {
        try {
            $response = idemailwiz_iterable_curl_call($APIendpoint);
            
            if (isset($response['response']['templates']) && is_array($response['response']['templates'])) {
                // Extract messageMedium and campaignType from the API URL
                parse_str(parse_url($APIendpoint, PHP_URL_QUERY), $queryParameters);
                $messageMedium = $queryParameters['messageMedium'];
                $campaignType = $queryParameters['templateType'];
    
                // Add messageMedium and campaignType to each template object
                foreach ($response['response']['templates'] as &$template) {
                    $template['messageMedium'] = $messageMedium;
                    $template['campaignType'] = $campaignType;
                }
                
                // All blast and triggered project templates
                $getProjectTemplates['templates'] = array_merge($getProjectTemplates['templates'], $response['response']['templates']);
                //wiz_log(print_r($getProjectTemplates['templates'], true));
            }
        } catch (Exception $e) {
            if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
                // Stop execution if more than 5 consecutive 400 errors
                wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
                return;
            }
        }
    }

    // Get existing campaigns in the Wiz database
    $wizCampaigns = get_idwiz_campaigns(array('fields' => 'templateId'));
    $existingTemplateIds = [];
    foreach ($wizCampaigns as $wizCampaign) {
        $existingTemplateIds[] = $wizCampaign['templateId'];
    }

    // Get array of project template Ids
    $fetchTemplates = [];
    foreach ($getProjectTemplates['templates'] as $projectTemplate) {
        if (in_array($projectTemplate['templateId'], $existingTemplateIds)) {
            $wizTemplate = get_idwiz_templates(array('templateId'=>$projectTemplate['templateId']));
            // Check for existing template if database (returns false if none)
            if ($wizTemplate) {
                if ($wizTemplate[0]['updatedAt'] != $projectTemplate['updatedAt']) {
                    // Mark templates to fetch that have different updatedAt from the wiz database one
                    $fetchTemplates[] = $projectTemplate;
                }
            } else {
                // Also mark templates to fetch that need inserted (not present in the wiz database, but attached to a campaign)
                $fetchTemplates[] = $projectTemplate;
            }
        }

    }

    // Now that we've filtered the project templates down to just those that need updating, we go get the details
    $allTemplates = [];
    foreach ($fetchTemplates as $truncTemplate) {
        // Determine message medium
        // Remember, $trucTemplate only has a few datapoints, not all of them, which is why we're doing the 2nd API call below
        $mediumEndpoint = $truncTemplate['messageMedium'] == 'Email' ? 'email' : 'SMS';     

        $url = 'https://api.iterable.com/api/templates/' . $mediumEndpoint. '/get?templateId='.$truncTemplate['templateId'];
        try {
            $response = idemailwiz_iterable_curl_call($url);
        } catch (Exception $e) {
            if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
                // Stop execution if more than 5 consecutive 400 errors
                wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
                return;
            }
            continue; // Continue with the next campaign ID for other exceptions
        }
        

        // Check if templates exist in the API response
        if (!isset($response['response']['metadata'])) {
            return "Error: No templates found in the API response.";
        }

        // Un-pack the array with special rules to get the proper headers and values in one simple array 
        $simplifiedTemplate = idemailwiz_simplify_templates_array($response['response']);

        $allTemplates[] = $simplifiedTemplate;
        sleep(.1); // Sleep for 100ms to avoid hitting Iterable API rate limits
    }
    // Return the templates array
    return $allTemplates;
}

function idemailwiz_fetch_metrics() {

    $today = new DateTime();
    $oneMonthAgo = $today->modify('-1 month')->format('Y-m-d');

    $blastCampaigns = array(
        'fields' => array('id'),
        'startAt_start' => $oneMonthAgo,
        'type' => array('Blast'),
    );
    $triggeredCampaigns = array(
        'fields' => array('id'),
        'startAt_start' => $oneMonthAgo,
        'type' => 'Triggered'
    );

    $blast = get_idwiz_campaigns($blastCampaigns);
    $triggered = get_idwiz_campaigns($triggeredCampaigns);

    $campaigns = $blast + $triggered;
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

    $data = []; // Initialize $data as an array

    foreach ($batches as $batch) {
        $getString = '?campaignId='.implode('&campaignId=', $batch);

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
        $headers = array_map(function($header) {
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
        $data = $data + $data;
        sleep(7); // Respect Iterable's rate limit of 10 requests per minute
    }

    // Return the data array
    return $data;
}


function idemailwiz_fetch_purchases() {
    $url = 'https://api.iterable.com/api/export/data.csv?dataTypeName=purchase&range=Today&delimiter=%2C&omitFields=shoppingCartItems.StudentFirstName%2CshoppingCartItems.StudentLastName%2Cemail%2CshoppingCartItems.StudentFirstName%2CshoppingCartItems.StudentLastName';

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
    $headers = array_map(function($header) {
        // Remove existing underscores (to handle the 'id' field)
        $header = str_replace('_', '', $header);
        // Replace periods with new underscores
        $header = str_replace('.', '_', $header);
        return lcfirst($header); // Lowercase first letter
    }, $headers);

    $data = []; // Initialize $data as an array

    // Iterate over the non-header lines
    for ($i = 1; $i < count($lines); $i++) {
        // Parse the line into values
        $values = str_getcsv($lines[$i]);

        // Check if the number of headers and values matches
        if (count($headers) != count($values)) {
            continue;
        }

        // Clean values
        $values = array_map(function($value) {
            $value = str_replace(['[', ']', '"'], '', $value);
            return $value;
        }, $values);

        // Combine headers and values into an associative array
        $data[] = array_combine($headers, $values);
    }

    // Return the data array
    return $data;
}


function idemailwiz_sync_campaigns($campaigns=null) {
    wiz_log("Initializing Campaigns sync. Please wait...\n");

    if (!$campaigns) {
        $campaigns = idemailwiz_fetch_campaigns();
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_campaigns';

    // Fetch the existing data from the database
    $existing_records_array = idemailwiz_fetch_existing_data($table_name, 'id');

    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];

    foreach ($campaigns as $campaign) {
        if ($campaign['campaignState'] == 'Finished' || $campaign['campaignState'] == 'Running') {
            // Check for an existing campaign in the database
            if (array_key_exists($campaign['id'], $existing_records_array)) {
                // Update the row if the "updatedAt" value is different
                if (strtotime($campaign['updatedAt']) != strtotime($existing_records_array[$campaign['id']['updatedAt']])) {
                    
                    // If this is a triggered campaign, get the existing startAt value
                    if ($campaign['type'] == 'Triggered') {
                        $existingCampaign = get_idwiz_campaigns(['id' => $campaign['id']]);
                        if (!empty($existingCampaign)) {
                            $campaign['startAt'] = $existingCampaign[0]['startAt'];
                        }
                    }
                    
                    $records_to_update[] = $campaign;
                }
            } else {
                // campaign not in db, we'll add it
                $records_to_insert[] = $campaign;
            }
        }
    }

    // Does our wiz_logging and returns the returns data about the insert/update
   return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);

}

function idemailwiz_sync_templates($apiTemplates=null) {
    wiz_log("Initializing Templates sync. Please wait...\n");
    
    if (!$apiTemplates) {
        $apiTemplates = idemailwiz_fetch_templates();
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_templates';

    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];      

    foreach($apiTemplates as $template) {
        // See if the template exists in our database yet
        $wizTemplate = get_idwiz_templates(array('templateId'=>$template['templateId']));
        if ($wizTemplate) {
            $records_to_update[] = $template;
        } else {
            // template not in db, we'll add it
            $records_to_insert[] = $template;
        }
    }

   // Does our wiz_logging and returns the returns data about the insert/update
   return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);

}




function idemailwiz_sync_purchases($purchases=null) {
    wiz_log("Initializing Purchase sync. Please wait...\n");

    if (!$purchases) {
        $purchases = idemailwiz_fetch_purchases();
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    

    // Fetch the existing data from the database
    $existing_records_array = idemailwiz_fetch_existing_data($table_name, 'id');

    // Prepare array
    $records_to_insert = [];

    foreach($purchases as $purchase) {        
        if(!array_key_exists($purchase['id'], $existing_records_array)) {
            // purchase not in db, we'll add it
            $records_to_insert[] = $purchase;
        }
    }

    // Does our wiz_logging and returns the returns data about the insert/update
    return idemailwiz_process_and_log_sync($table_name, $records_to_insert, null);
  
}

function idemailwiz_sync_metrics($metrics=null) {
    wiz_log("Initializing Metrics sync. Please wait...\n");

    if (!$metrics) {
        $metrics = idemailwiz_fetch_metrics();
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_metrics';

    
    

    // Fetch the existing data from the database
    $existing_records_array = idemailwiz_fetch_existing_data($table_name, 'id');

    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];

    foreach($metrics as $metric) {        
        if(array_key_exists($metric['id'], $existing_records_array)) {
            // Update the existing metric row 
             $records_to_update[] = $metric;
        } else {
            // metric not in db, we'll add it
            $records_to_insert[] = $metric;
        }
    }

    // Does our wiz_logging and returns the returns data about the insert/update
    return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}

function idemailwiz_process_and_log_sync($table_name, $records_to_insert=null, $records_to_update=null ) {
    
    // Extracting the type (e.g., 'campaign', 'template', etc.) from the table name
    $type = substr($table_name, strrpos($table_name, '_') + 1);
    
    $insert_results = '';
    $update_results = '';
    $logChunk = "";
    $return = array();
    
    if ($records_to_insert) {
        $logChunk .= count($records_to_insert) . " $type" . "to insert.\n";
        $insert_results = idemailwiz_update_insert_api_data($records_to_insert, 'insert', $table_name);
    }
    if ($records_to_update) {
        $logChunk .= count($records_to_update) . " $type" . "to update.\n";
        $update_results = idemailwiz_update_insert_api_data($records_to_update, 'update', $table_name);
    }

    $logInsertUpdate = return_insert_update_logging($insert_results, $update_results);

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



function return_insert_update_logging($insert_results = null, $update_results = null) {

    if ($insert_results === null && $update_results === null) {
        return false;
    }

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
    $logSync = $logInsert.$logUpdate;

    // If neither insert nor update results are available, there was nothing to sync or error, return early
    if (!$logSync) {
        return 'No inserts or updates needed.';
    }

    return $logInsert . "\n" .$logUpdate;
}

function log_wiz_api_results($results, $type) {
    if (empty($results['success']) && empty($results['errors'])) {
        return ""; // Return an empty string if no operations performed
    }

    if (!isset($results['success'], $results['errors'])) {
        return "Invalid {$type} results structure.";
    }

    $return = '';
    foreach ($results['success'] as $message) { 
        $return .= "Success ({$type}): " . $message . "\n";
    }
    foreach ($results['errors'] as $message) { 
        $return .= "Error ({$type}): " . $message . "\n";
    }

    //returning an empty string is necessary here when no updates for return_insert_update_logging to handle the return value properly
    return rtrim($return); // Removing the trailing newline, if any
}

// defunct ajax handler in favor of wp-cron
// function idemailwiz_update_triggered_sends() {
//     $sevenAgo = new DateTime();
//     $sevenAgo->setTimestamp(strtotime('-7 days')); 

//     $triggeredCampaigns = get_idwiz_campaigns(array('type'=>'Triggered','startAt_start' => $sevenAgo->format('m/d/Y')));
//     $syncTriggeredDates = idemailwiz_sync_triggered_send_timestamps($triggeredCampaigns);
//     wp_send_json_success($syncTriggeredDates);
    
// }
// add_action('wp_ajax_idemailwiz_update_triggered_sends', 'idemailwiz_update_triggered_sends');


// Schedule the event, if not already scheduled
if (!wp_next_scheduled('idemailwiz_hourly_triggered_sync')) {
    wp_schedule_event(time(), 'hourly', 'idemailwiz_hourly_triggered_sync');
}

// Hook into the custom action and run the function
add_action('idemailwiz_hourly_triggered_sync', 'idemailwiz_sync_triggered_send_timestamps');

function idemailwiz_sync_triggered_send_timestamps($triggeredCampaigns = null) {
    
    if (!$triggeredCampaigns) {
        $sevenAgo = new DateTime('-1 days');
        $triggeredCampaigns = get_idwiz_campaigns(['type'=>'Triggered','startAt_start' => $sevenAgo->format('m/d/Y')]);
    }

    wiz_log('Starting emailSend API calls (this will take a few minutes due to API rate limits).');

    $jobIds = [];
    $logFetched = 0;
    $log400s = 0;
    foreach ($triggeredCampaigns as $campaign) {
        $campaignId = (int)$campaign['id'];
        $data = [
            "outputFormat" => "text/csv",
            "dataTypeName" => "emailSend",
            "campaignId" => $campaignId,
            "delimiter" => ",",
            "onlyFields" => "createdAt",
            "range" => 'Today'
        ];
        
        try {
            $response = idemailwiz_iterable_curl_call('https://api.iterable.com/api/export/start', $data);
            $jobId = $response['response']['jobId'];
            $jobIds[$campaignId] = $jobId;
            //$logFetched .= "Fetched campaign $campaignId (job ID: $jobId)\n";
            $logFetched++;
        } catch (Exception $e) {
            if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
                // Stop execution if more than 5 consecutive 400 errors
                wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
                return;
            }
            $log400s++;
            continue; // Continue with the next campaign ID for other exceptions
        }
        sleep(1);
    }

    wiz_log("Requested $logFetched triggered campaigns from Iterable. $log400s errors encountered.\nProceeding to retrieve campaign data (another few minutes here).");

    $latestTimestamps = [];
    $countUpdates = 0;
    $logUpdates = '';
    sleep(3);
    foreach ($jobIds as $campaignId => $jobId) {
        while (true) {
            $apiResponse = idemailwiz_iterable_curl_call('https://api.iterable.com/api/export/' . $jobId . '/files');
            if (in_array($apiResponse['response']['jobState'], ['completed', 'failed'])) {
                break;
            }
            sleep(3);
        }
        if ($apiResponse['response']['jobState'] == "completed") {
            $csvResponse = file_get_contents($apiResponse['response']['files'][0]['url']);
            $tempFile = tmpfile();
            fwrite($tempFile, $csvResponse);
            rewind($tempFile);
            $header = fgetcsv($tempFile);
            $createdAtIndex = array_search('createdAt', $header);
            $latestTimestamp = null; //initialize variable
            

            while ($row = fgetcsv($tempFile)) {
                $timestamp = strtotime($row[$createdAtIndex]) * 1000;
                if ($latestTimestamp === null || $timestamp > $latestTimestamp) {
                    $latestTimestamp = $timestamp; 
                }
                
            }
            fclose($tempFile);

            $thisCampaign = get_idwiz_campaigns(['id'=>$campaignId]); //returns array
            $lastSend = $thisCampaign[0]['startAt'];
            if ($lastSend < $latestTimestamp) {
                $countUpdates++;
                //Set the value with the array
                $latestTimestamps[$campaignId] = $latestTimestamp;
                //Log results
                $apiReadable = date('m/d/y g:ia', ($latestTimestamp/1000));
                $lastSendReadable = date('m/d/y g:ia', ($lastSend/1000));
                $logUpdates .= "Campaign $campaignId send date/time needs updated from $lastSendReadable to $apiReadable \n";
            }
            
        }
    }

    if ($countUpdates == 0) {
        wiz_log('All triggered send times are already up to date.');
        return false; //End execution since there's no updates to do
    } else {
        wiz_log($logUpdates);
    }

    $return = idemailwiz_update_triggered_startAt($latestTimestamps);
    $logStartAtUpdate = 'Updated ' . $return['success'] . ' send times.'."\n";
    $logStartAtUpdate .= 'Skipped ' . $return['skipped'] . ' campaigns.'."\n";
    $logStartAtUpdate .= 'Encountered ' . $return['errors'] . ' errors';

    wiz_log($logStartAtUpdate);
    return $return;

    
}

function idemailwiz_update_triggered_startAt($campaignTimestamps) {
    global $wpdb;
    $result = ['success' => 0, 'errors' => 0, 'skipped' => 0];

    foreach ($campaignTimestamps as $campaignId => $timestamp) {
        if (!$timestamp || $timestamp == 0) {
            $result['skipped']++;
            continue;
        }

        $date = new DateTime('@' . ($timestamp / 1000), new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('America/Los_Angeles'));
        $timestampPST = $date->getTimestamp() * 1000;

        $sql = $wpdb->prepare("UPDATE wp_idemailwiz_campaigns SET startAt = %d WHERE id = %d", $timestampPST, $campaignId);
        $query_result = $wpdb->query($sql);

        if ($query_result === false) {
            $result['errors']++;
        } else if ($query_result > 0) {
            $result['success']++;
        }
    }

    return $result;
}




// Ajax handler for sync buttons on DataTable
function idemailwiz_ajax_sync() {

    $dbs = json_decode(stripslashes($_POST['dbs']), true);
    $allowed_dbs = ['campaigns', 'templates', 'metrics', 'purchases'];

    if(empty($dbs) || !is_array($dbs) || !array_diff($dbs, $allowed_dbs) === []) {
        wp_send_json_error('Invalid database list'); 
    }

    $response = [];
    foreach ($allowed_dbs as $db) {
        if (in_array($db, $dbs)) {
            $function_name = 'idemailwiz_sync_' . $db;
            if (!function_exists($function_name)) {
                wp_send_json_error('Sync function does not exist for ' . $db);
            }
            $args = [];
            $result = call_user_func($function_name, $args);

            if ($result === false) {
                wp_send_json_error('Sync failed for ' . $db);
            }
            $response[$db] = $result;
        }
    }

    wp_send_json_success($response);
}
add_action('wp_ajax_idemailwiz_ajax_sync', 'idemailwiz_ajax_sync');









function wiz_log($something, $before = '', $after = '') {
    // Get the current date and time in PST
    $date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
    $timestamp = $date->format('Y-m-d H:i:s');


    // Build the breaker bar with the timestamp
    $breakerBar = str_repeat('=', 40); // 40 is the length of the bar
    $timestampPosition = (strlen($breakerBar) - strlen($timestamp)) / 2;
    $breakerBarWithTimestamp = substr_replace($breakerBar, "[$timestamp]", $timestampPosition, 0);

    // Build the log entry
    $logEntry = "{$breakerBarWithTimestamp}\n$before$something$after\n$breakerBar\n\n";

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

// Get existing data from a database and into an array of $table_Key['updatedAt']
// For purchases, there is no updatedAt so we put an empty array as the value of each item
function idemailwiz_fetch_existing_data($table_name, $table_key) {
    global $wpdb;
    if ($table_name != 'wp_idemailwiz_purchases' && $table_name != 'wp_idemailwiz_metrics') {
        $existing_data = $wpdb->get_results("SELECT $table_key, updatedAt, fromName FROM $table_name", OBJECT_K);
        $existing_data_array = [];
        foreach($existing_data as $data){
            $existing_data_array[$data->$table_key]['updatedAt'] = $data->updatedAt;
            $existing_data_array[$data->$table_key]['fromName'] = $data->fromName;
        }
    } else {
        // For purchases and metrics, we have no updatedAt column so we just get put all the ids into the keys and assign an empty array as the values
        $existing_data = $wpdb->get_results("SELECT $table_key FROM $table_name", OBJECT_K);
        $existing_data_array = [];
        foreach($existing_data as $data){
            $existing_data_array[$data->$table_key] = array();
        }
    }
    
    return $existing_data_array;
}

function ajax_to_wiz_log() {
    $logData = $_POST['log_data'];
    $before = $_POST['before'];
    $after = $_POST['after'];

    $writeToLog = wiz_log($logData, $before, $after);

    wp_send_json_success($writeToLog);
}
add_action('wp_ajax_ajax_to_wiz_log', 'ajax_to_wiz_log');
