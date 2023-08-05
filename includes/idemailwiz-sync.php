<?php
// Include WordPress' database functions
global $wpdb;


/**
 * Executes a cURL call to Iterable API for all endpoints.
 *
 * @param string $apiURL The URL for the API endpoint.
 * @param bool $verifySSL Whether to verify SSL. Default is false.
 *
 * @throws Exception If a cURL or HTTP error occurs.
 *
 * @return array|string The response from the API call. If the response is a JSON string, it is decoded into an array.
 *
 * @example
 * idemailwiz_iterable_curl_call('https://api.iterable.com/api/endpoint');
 */
function idemailwiz_iterable_curl_call($apiURL, $verifySSL = false) {
    // Fetch the API key
    $api_key = idwiz_itAPI();

    // Initialize cURL
    $ch = curl_init($apiURL);

    // Set SSL verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);

    // Set the HTTP headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Api-Key: $api_key"
    ));

    // Return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // Execute the request
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("Error while fetching campaigns: $error_msg");
    }

    // Get the HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close cURL
    curl_close($ch);

    // Check for HTTP errors
    if ($httpCode >= 400) {
        throw new Exception("HTTP Error: $httpCode");
    }

    $decodedResponse = json_decode($response, true);
    if (is_array($decodedResponse)) {
        // If decoding was successful and it's an array
        $response = $decodedResponse;
    }

    return $response;
}


/**
 * Fetches all campaigns from the Iterable API.
 *
 * @throws Exception If a cURL error occurs in idemailwiz_iterable_curl_call.
 *
 * @return array|string The campaigns from the API call, or an error message if no campaigns are found or if an error occurs.
 *
 * @example
 * idemailwiz_fetch_campaigns();
 */

function idemailwiz_fetch_campaigns() {
    $url = 'https://api.iterable.com/api/campaigns';
    try {
        $response = idemailwiz_iterable_curl_call($url);
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }

    // Check if campaigns exist in the API response
    if (!isset($response['campaigns'])) {
        return "Error: No campaigns found in the API response.";
    }

    // Return the campaigns array
    return $response['campaigns'];
}

/**
 * Fetches a single email template from the Iterable API by its ID.
 *
 * @param string $campaignId The ID of the campaign for which to fetch the template.
 *
 * @throws Exception If a cURL error occurs in idemailwiz_iterable_curl_call.
 *
 * @return array|string The email template in a simplified array format, or an error message if no templates are found or if an error occurs.
 *
 * @example
 * idemailwiz_fetch_template('1234');
 */
function idemailwiz_fetch_templates($campaignType = 'Blast') {
    // Get all campaigns
    $today = new DateTime();
    $oneMonthAgo = $today->modify('-1 month')->format('Y-m-d');
    // Note that triggered campaigns ignore the start_at parameter
    $campaignArgs = array(
        'fields'=>'templateId', 
        'startAt_start'=>$oneMonthAgo,
    );
    if ($campaignType == 'Triggered') {
        $campaignArgs['type'] = 'Triggered';
    }
    $last30DaysCampaigns = get_idwiz_campaigns($campaignArgs);
    foreach ($last30DaysCampaigns as $campaign) {
        if (!isset($campaign['templateId']) || !is_string($campaign['templateId'])) {
            continue; // Skip this campaign and move to the next one
        }
        
        $url = 'https://api.iterable.com/api/templates/email/get?templateId='.$campaign['templateId'];
        try {
            $response = idemailwiz_iterable_curl_call($url);
        } catch (Exception $e) {
            // Log the error message for debugging purposes, if needed
            error_log('Error: ' . $e->getMessage());
            continue; // Skip this campaign and move to the next one
        }
        

        // Check if templates exist in the API response
        if (!isset($response['metadata'])) {
            return "Error: No templates found in the API response.";
        }

        // Un-pack the array with special rules to get the proper headers and values in one simple array 
        $simplifiedTemplate = idemailwiz_simplify_templates_array($response);

        $allTemplates[] = $simplifiedTemplate;
        sleep(.1); // Sleep for 100ms to avoid hitting Iterable API rate limits
    }
    // Return the templates array
    return $allTemplates;
}



/**
 * Fetches metrics for specific campaigns from the Iterable API.
 * 
 * The function fetches both 'Blast' and 'Triggered' campaign types. 
 * It batches campaign IDs in groups of up to 100 before making an API call to fetch the metrics for those campaigns.
 * The function does this because each API call can fetch up to 100 rows.
 * To respect the Iterable API rate limit of 10 requests per minute, the function pauses for 7 seconds between each API call.
 *
 * Metrics for each campaign are processed and additional percentage metrics are calculated using the `idemailwiz_calculate_metrics()` function.
 * All metrics are combined into a single array, which is then returned.
 *
 * @throws Exception If a cURL error occurs in idemailwiz_iterable_curl_call.
 *
 * @return array|string The metrics for the campaigns in an array format, or an error message if an error occurs.
 *
 * @example
 * $metrics = idemailwiz_fetch_metrics();
 * print_r($metrics);
 */

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
            return "Error: " . $e->getMessage();
        }

        // Split the CSV data into lines
        $lines = explode("\n", $response);

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

/**
 * Fetches all purchases made "Today" from the Iterable API.
 *
 * @throws Exception If a cURL error occurs in idemailwiz_iterable_curl_call.
 *
 * @return array|string The purchases made "Today" in an array format, or an error message if an error occurs.
 *
 * @example
 * idemailwiz_fetch_purchases();
 */
function idemailwiz_fetch_purchases() {
    $url = 'https://api.iterable.com/api/export/data.csv?dataTypeName=purchase&range=Today&delimiter=%2C&omitFields=shoppingCartItems.StudentFirstName%2CshoppingCartItems.StudentLastName%2Cemail%2CshoppingCartItems.StudentFirstName%2CshoppingCartItems.StudentLastName';

    try {
        $response = idemailwiz_iterable_curl_call($url);
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }

    // Split the CSV data into lines
    $lines = explode("\n", $response);

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


/**
 * Inserts or updates API call data in the database.
 *
 * @param array $items The items to insert or update in the database.
 * @param string $operation The operation to perform ("insert" or "update").
 * @param string $table_name The name of the table in which to insert or update data.
 *
 * @return array An array containing success and error messages for each item.
 *
 * @example
 * idemailwiz_update_insert_api_data($items, 'update', 'wp_idemailwiz_templates');
 */
function idemailwiz_update_insert_api_data($items, $operation, $table_name) {
    global $wpdb;
    $result = ['success' => [], 'errors' => []];
    $table_key = $table_name == 'wp_idemailwiz_templates' ? 'templateId' : 'id';

    foreach ($items as $key => $item) {
        $key = to_camel_case($key); // Convert key/header to camel case for db compatibility
        if (is_array($item)) {
            $items[$key] = serialize($item); // serialize values that are arrays
        }

        $fields = implode(",", array_keys($item));
        $values = "'" . implode("','", array_map('esc_sql', $item)) . "'";
        $updates = implode(",", array_map(function($field, $value) {
            return "{$field}='" . esc_sql($value) . "'";
        }, array_keys($item), $item));

        $sql = $operation === "insert" ? 
            "INSERT INTO {$table_name} ({$fields}) VALUES ({$values})" : 
            "UPDATE {$table_name} SET {$updates} WHERE {$table_key}={$item[$table_key]}";
        
        $query_result = $wpdb->query($sql);

        if ($query_result !== false) {
            $result['success'][] = ucfirst($operation) . " operation on item with id {$item[$table_key]} in {$table_name} was successful. Rows affected: " . $query_result . ".";
        } else {
            $result['errors'][] = "Failed to perform {$operation} operation on item with id {$item[$table_key]} in {$table_name}. MySQL Error: " . $wpdb->last_error;
        }
    }

    return $result;
}

/**
 * Syncs campaigns data with the database, inserting new campaigns and updating existing ones.
 *
 * @param array $campaigns The campaigns data to sync with the database.
 *
 * @return array An array containing results of the insert and update operations.
 *
 * @example
 * idemailwiz_sync_campaigns($campaigns);
 */
function idemailwiz_sync_campaigns($campaigns) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_campaigns';
    
    wiz_log( 'Syncing ' . count($campaigns) . ' campaigns...' );
    // Fetch the existing data from the database
    $existing_records_array = idemailwiz_fetch_existing_data($table_name, 'id');

    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];

    foreach($campaigns as $campaign) {        
        if(array_key_exists($campaign['id'], $existing_records_array)) {
            // Update the row if the "updatedAt" value is different
            if(strtotime($campaign['updatedAt']) != strtotime($existing_records_array[$campaign['id']])) {
                $records_to_update[] = $campaign;
            }
        } else {
            // campaign not in db, we'll add it
            $records_to_insert[] = $campaign;
        }
    }
    wiz_log( count($records_to_insert) . ' campaigns to insert.' );
    wiz_log( count($records_to_update) . ' campaigns to update.' );
    wiz_log('Adding/Updating campaigns...');
    // Process new and existing records
    $insert_results = idemailwiz_update_insert_api_data($records_to_insert, 'insert', $table_name);
    $update_results = idemailwiz_update_insert_api_data($records_to_update, 'update', $table_name);

    // Combine the results
    $result = [
        'type' => 'campaigns',
        'inserted' => $insert_results,
        'updated' => $update_results,
    ];
   
    wiz_log( 'Sync complete! See results below:' );
    wiz_log( json_encode($result) );
    wiz_log( '=====================================' );
    return $result;
}

/**
 * Syncs templates data with the database, inserting new templates and updating existing ones.
 *
 * @param array $templates The templates data to sync with the database.
 *
 * @return array An array containing results of the insert and update operations.
 *
 * @example
 * idemailwiz_sync_templates($templates);
 */
function idemailwiz_sync_templates($templates) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_templates';
    
    wiz_log( 'Syncing ' . count($templates) . ' templates...' );

    // Fetch the existing data from the database
    $existing_records_array = idemailwiz_fetch_existing_data($table_name, 'templateId');

    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];

    foreach($templates as $template) {        
        if(array_key_exists($template['templateId'], $existing_records_array)) {
            // Update the row if the "updatedAt" value is different
            if(strtotime($template['updatedAt']) != strtotime($existing_records_array[$template['templateId']])) {
                $records_to_update[] = $template;
            }
        } else {
            // template not in db, we'll add it
            $records_to_insert[] = $template;
        }
    }

    wiz_log( count($records_to_insert) . ' templates to insert.' );
    wiz_log( count($records_to_update) . ' templates to update.' );
    // Process new and existing records
    $insert_results = idemailwiz_update_insert_api_data($records_to_insert, 'insert', $table_name);
    $update_results = idemailwiz_update_insert_api_data($records_to_update, 'update', $table_name);

    // Combine the results
    $result = [
        'type' => 'templates',
        'inserted' => $insert_results,
        'updated' => $update_results,
    ];
   
    wiz_log( 'Sync complete! See results below:' );
    wiz_log( json_encode($result) );
    wiz_log( '=====================================' );
    return $result;
}

/**
 * Syncs purchases data with the database, inserting new purchases.
 *
 * @param array $purchases The purchases data to sync with the database.
 *
 * @return array An array containing results of the insert operation.
 *
 * @example
 * idemailwiz_sync_purchases($purchases);
 */
function idemailwiz_sync_purchases($purchases) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';
    
    wiz_log( 'Retrieved ' . count($purchases) . ' purchases...' );

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

    wiz_log( count($records_to_insert) . ' purchases to insert.' );

    // Process new records
    $insert_results = idemailwiz_update_insert_api_data($records_to_insert, 'insert', $table_name);

    // Combine the results
    $result = [
        'type' => 'purchases',
        'inserted' => $insert_results,
    ];
   
    wiz_log( 'Sync complete! See results below:' );
    wiz_log( json_encode($result) );
    wiz_log( '=====================================' );
    return $result;
}

/**
 * Syncs metrics data with the database, inserting new metrics and updating existing ones.
 *
 * @param array $metrics The metrics data to sync with the database.
 *
 * @return array An array containing results of the insert and update operations.
 *
 * @example
 * idemailwiz_sync_metrics($metrics);
 */
function idemailwiz_sync_metrics($metrics) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_metrics';
    
    wiz_log( 'Syncing ' . count($metrics) . ' metrics...' );

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

    wiz_log( count($records_to_insert) . ' metrics to insert.' );
    wiz_log( count($records_to_update) . ' metrics to update.' );

    // Process new and existing records
    $insert_results = idemailwiz_update_insert_api_data($records_to_insert, 'insert', $table_name);
    $update_results = idemailwiz_update_insert_api_data($records_to_update, 'update', $table_name);

    // Combine the results
    $result = [
        'type' => 'metrics',
        'inserted' => $insert_results,
        'updated' => $update_results,
    ];
   
    wiz_log( 'Sync complete! See results below:' );
    wiz_log( json_encode($result) );
    wiz_log( '=====================================' );
    
    return $result;
}




