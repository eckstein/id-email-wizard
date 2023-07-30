<?php
function idemailwiz_create_databases() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Define Campaigns table
    $campaign_table_name = $wpdb->prefix . 'idemailwiz_campaigns';
    $campaign_sql = "CREATE TABLE $campaign_table_name (
        id INT,
        createdAt BIGINT,
        updatedAt BIGINT,
        startAt BIGINT,
        endedAt BIGINT,
        name VARCHAR(255),
        templateId INT,
        messageMedium VARCHAR(20),
        labels VARCHAR(255),
        createdByUserId VARCHAR(255),
        updatedByUserId VARCHAR(255),
        campaignState VARCHAR(20),
        sendSize INT,
        recurringCampaignId INT,
        workflowId INT,
        listIds VARCHAR(255),
        suppressionListIds VARCHAR(255),
        type VARCHAR(20),
        PRIMARY KEY  (id)
    ) $charset_collate;";



    // Define Templates table
    $template_table_name = $wpdb->prefix . 'idemailwiz_templates';
    $template_sql = "CREATE TABLE $template_table_name (
        templateId INT,
        createdAt BIGINT,
        updatedAt BIGINT,
        name VARCHAR(255),
        creatorUserId VARCHAR(255),
        messageTypeId INT,
        campaignId INT,
        clientTemplateId VARCHAR(255),
        fromName VARCHAR(255),
        subject VARCHAR(500),
        preheaderText VARCHAR(500),
        PRIMARY KEY  (templateId)
    ) $charset_collate;";

    // Define Metrics table
    $metrics_table_name = $wpdb->prefix . 'idemailwiz_metrics';
    $metrics_sql = "CREATE TABLE $metrics_table_name (
        id INT,
        averageCustomConversionValue FLOAT,
        averageOrderValue FLOAT,
        purchasesMEmail FLOAT,
        revenue FLOAT,
        revenueMEmail FLOAT,
        sumOfCustomConversions FLOAT,
        totalComplaints FLOAT,
        totalCustomConversions FLOAT,
        totalEmailHoldout FLOAT,
        totalEmailOpens FLOAT,
        totalEmailOpensFiltered FLOAT,
        totalEmailSendSkips FLOAT,
        totalEmailSends FLOAT,
        totalEmailsBounced FLOAT,
        totalEmailsClicked FLOAT,
        totalEmailsDelivered FLOAT,
        totalPurchases FLOAT,
        totalUnsubscribes FLOAT,
        uniqueCustomConversions FLOAT,
        uniqueEmailClicks FLOAT,
        uniqueEmailOpens FLOAT,
        uniqueEmailOpensFiltered FLOAT,
        uniqueEmailOpensOrClicks FLOAT,
        uniqueEmailSends FLOAT,
        uniqueEmailsBounced FLOAT,
        uniqueEmailsDelivered FLOAT,
        uniquePurchases FLOAT,
        uniqueUnsubscribes FLOAT,
        purchasesMSms FLOAT,
        revenueMSms FLOAT,
        totalInboundSms FLOAT,
        totalSmsBounced FLOAT,
        totalSmsDelivered FLOAT,
        totalSmsHoldout FLOAT,
        totalSmsSendSkips FLOAT,
        totalSmsSent FLOAT,
        totalSmsClicks FLOAT,
        uniqueInboundSms FLOAT,
        uniqueSmsBounced FLOAT,
        uniqueSmsClicks FLOAT,
        uniqueSmsDelivered FLOAT,
        uniqueSmsSent FLOAT,
        lastWizUpdatelastWizUpdate BIGINT,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Define Purchases table
    $purchase_table_name = $wpdb->prefix . 'idemailwiz_purchases';
    $purchase_sql = "CREATE TABLE $purchase_table_name (
        accountNumber VARCHAR(20),
        orderId VARCHAR(10),
        id VARCHAR(40),
        campaignId INT,
        createdAt VARCHAR(26),
        currencyTypeId VARCHAR(20),
        eventName VARCHAR(255),
        purchaseDate VARCHAR(26),
        shoppingCartItems TEXT,
        shoppingCartItems_discountAmount FLOAT,
        shoppingCartItems_discountCode VARCHAR(255),
        shoppingCartItems_discounts TEXT,
        shoppingCartItems_divisionId INT,
        shoppingCartItems_divisionName VARCHAR(255),
        shoppingCartItems_isSubscription BOOLEAN,
        shoppingCartItems_locationName VARCHAR(255),
        shoppingCartItems_numberOfLessonsPurchasedOpl INT,
        shoppingCartItems_orderDetailId INT,
        shoppingCartItems_packageType VARCHAR(255),
        shoppingCartItems_parentOrderDetailId INT,
        shoppingCartItems_predecessorOrderDetailId INT,
        shoppingCartItems_productCategory VARCHAR(255),
        shoppingCartItems_productSubcategory VARCHAR(255),
        shoppingCartItems_sessionStartDateNonOpl VARCHAR(26),
        shoppingCartItems_studentAccountNumber VARCHAR(20),
        shoppingCartItems_studentDob VARCHAR(26),
        shoppingCartItems_studentGender VARCHAR(10),
        shoppingCartItems_subscriptionAutoRenewDate VARCHAR(26),
        shoppingCartItems_totalDaysOfInstruction INT,
        shoppingCartItems_utmCampaign VARCHAR(255),
        shoppingCartItems_utmContents VARCHAR(255),
        shoppingCartItems_utmMedium VARCHAR(255),
        shoppingCartItems_utmSource VARCHAR(255),
        shoppingCartItems_utmTerm VARCHAR(255),
        shoppingCartItems_categories TEXT,
        shoppingCartItems_financeUnitId INT,
        shoppingCartItems_id VARCHAR(40),
        shoppingCartItems_imageUrl VARCHAR(255),
        shoppingCartItems_name VARCHAR(255),
        shoppingCartItems_price FLOAT,
        shoppingCartItems_quantity INT,
        shoppingCartItems_subsidiaryId INT,
        shoppingCartItems_url VARCHAR(255),
        templateId VARCHAR(40),
        total VARCHAR(20),
        userId VARCHAR(40),
        PRIMARY KEY  (id)
    ) $charset_collate;";



    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $campaign_sql );
    dbDelta( $template_sql );
    dbDelta( $metrics_sql );
    dbDelta( $purchase_sql );
}
//Camel case for database headers
function to_camel_case($string) {
    $string = str_replace('.', '_', $string); // Replace periods with underscores
    $words = explode(' ', $string); // Split the string into words
    $words = array_map('ucwords', $words); // Capitalize the first letter of each word
    $camelCaseString = implode('', $words); // Join the words back together
    return lcfirst($camelCaseString); // Make the first letter lowercase and return
}

// Include WordPress' database functions
global $wpdb;

function idwiz_get_campaign_by_id($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_campaigns';

    // Query the database for a campaign with the given ID
    $campaign = $wpdb->get_row("SELECT * FROM {$table_name} WHERE id = {$id}");
    if ($campaign) {
        return $campaign;
    } else {
        return $wpdb->last_error;
    }

   
}

function idwiz_get_template_by_id($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_templates';

    // Query the database for a template with the given ID
    $template = $wpdb->get_row("SELECT * FROM {$table_name} WHERE templateId = {$id}");
    if ($template) {
        return $template;
    } else {
        return $wpdb->last_error;
    }
}

function idwiz_get_metrics_by_campaign_id($campaign_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_metrics';

    // Query the database for metrics with the given campaign ID
    $metrics = $wpdb->get_results("SELECT * FROM {$table_name} WHERE id = {$campaign_id}");
    if ($metrics) {
        return $metrics;
    } else {
        return $wpdb->last_error;
    }
}

function idwiz_get_purchase_by_id($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    // Query the database for a purchase with the given ID
    $purchase = $wpdb->get_row("SELECT * FROM {$table_name} WHERE id = {$id}");
    if ($purchase) {
        return $purchase;
    } else {
        return $wpdb->last_error;
    }
}


function idemailwiz_iterable_curl_call($apiURL) {
    // Fetch the API key
    $api_key = idwiz_itAPI();

    // Initialize cURL
    $ch = curl_init($apiURL);

    // Disable SSL verification in the development environment
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

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
        return array(
            'success' => false,
            'error' => "Error while fetching campaigns: $error_msg",
            'response' => null,
        );
    }

    // Get the HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close cURL
    curl_close($ch);

    // Check for HTTP errors
    if ($httpCode >= 400) {
        return array(
            'success' => false,
            'error' => "HTTP Error: $httpCode",
            'response' => null,
        );
    }

       $decodedResponse = json_decode($response, true);
       if (is_array($decodedResponse)) {
            // If decoding was successful and it's an array
            $response = $decodedResponse;
        } 

    //print_r($response);

    return array(
        'success' => true,
        'error' => null,
        'response' => $response,
    );
}

//Get iterable campaigns
function idemailwiz_fetch_campaigns() {
    $url = 'https://api.iterable.com/api/campaigns';
    $response = idemailwiz_iterable_curl_call($url);
    
    // Check if the API call was successful
    if (!$response['success']) {
        return $response['error'];
    }

    // Check if campaigns exist in the API response
    if (!isset($response['response']['campaigns'])) {
        return "Error: No campaigns found in the API response.";
    }

    // Return the campaigns array
    return $response['response']['campaigns'];
}

//Get iterable Templates
function idemailwiz_fetch_templates() {
    $url = 'https://api.iterable.com/api/templates?messageMedium=Email&messageMedium=SMS&templateType=Blast&templateType=Triggered';
    $response = idemailwiz_iterable_curl_call($url);

    // Check if the API call was successful
    if (!$response['success']) {
        return $response['error'];
    }

    // Check if templates exist in the API response
    if (!isset($response['response']['templates'])) {
        return "Error: No templates found in the API response.";
    }

    // Return the templates array
    return $response['response']['templates'];
}

//Get iterable Metrics
function idemailwiz_fetch_metrics() {
    //$url = "https://api.iterable.com/api/campaigns/metrics?campaignId=$campaignId";
    $url = "https://api.iterable.com/api/campaigns/metrics?campaignId=7346803";
    $response = idemailwiz_iterable_curl_call($url);

    // Check if the API call was successful
    if (!$response['success']) {
        return $response['error'];
    }

    // Check if metrics exist in the API response
    if (!isset($response)) {
        return "Error: No metrics found in the API response.";
    }

    // Return the metrics
    return $response;
}

function idemailwiz_fetch_purchases() {
    $url = 'https://api.iterable.com/api/export/data.csv?dataTypeName=purchase&range=Today&delimiter=%2C&omitFields=shoppingCartItems.StudentFirstName%2CshoppingCartItems.StudentLastName%2Cemail%2CshoppingCartItems.StudentFirstName%2CshoppingCartItems.StudentLastName';
    $response = idemailwiz_iterable_curl_call($url);
    //print_r($response);

    // Check if the API call was successful
    if (!$response['success']) {
        return $response['error'];
    }

    // Split the CSV data into lines
    $lines = explode("\n", $response['response']);

    // Parse the header line into headers
    $headers = str_getcsv($lines[0]);

    //swap in underscores for periods
    $headers = array_map(function($header) {
        //remove existing underscores (to handle the stupid _in field)
        $header = str_replace('_', '', $header);
        //replace periods with new underscores
        $header = str_replace('.', '_', $header);
        return lcfirst($header);//lowercase first letter
    }, $headers);

    $data = []; // Initialize $data as an array

    // Iterate over the non-header lines
    for ($i = 1; $i < count($lines); $i++) {
        // Parse the line into values
        $values = str_getcsv($lines[$i]);

        // Check if the number of headers and values matches
        if (count($headers) != count($values)) {
            error_log("Number of headers and values does not match on line $i");
            continue;
        }

        // Clean values
        $values = array_map(function($value) {
            $value = str_replace(['[', ']', '"'], '', $value);
            return $value;
        }, $values);

        // Combine headers and values into an associative array
        $data[] = array_combine($headers, $values); // Append each line's data to the $data array
    }
    return $data;
}


function idemailwiz_fetch_existing_data($table_name, $table_key) {
    global $wpdb;
    if ($table_name == 'wp_idemailwiz_campaigns' || $table_name == 'wp_idemailwiz_templates') {
        $existing_data = $wpdb->get_results("SELECT $table_key, updatedAt FROM $table_name", OBJECT_K);
        $existing_data_array = [];
        foreach($existing_data as $data){
            $existing_data_array[$data->$table_key] = $data->updatedAt;
        }
    } else {
        // For purchases, we have no updatedAt column so we just get put all the ids into the keys and assign an empty array as the values
        $existing_data = $wpdb->get_results("SELECT $table_key FROM $table_name", OBJECT_K);
        $existing_data_array = [];
        foreach($existing_data as $data){
            $existing_data_array[$data->$table_key] = array();
        }
    }
    
    return $existing_data_array;
}
//prepare array data to go into the database
function idemailwiz_prepare_array_data($data) {
    // Handle array values
    foreach ($data as $key => $value) {
        $key = to_camel_case($key); // Convert key/header to camel case for db compatability
        if (is_array($value)) {
            $data[$key] = implode(',', $value); //convert values that are arrays to comma separated string
        }
    }
    return $data;
}

function idemailwiz_prepare_sql($data, $operation, $table_name, $table_key) {
    global $wpdb;
    $sql = "";

    if($operation === "insert") {
        $fields = implode(",", array_keys($data));
        $values = "'" . implode("','", array_map('esc_sql', $data)) . "'";
        $sql = "INSERT INTO {$table_name} ({$fields}) VALUES ({$values})";
    }
    
    if($operation === "update") {
        $updates = [];
        foreach($data as $field => $value) {
            $updates[] = "{$field}='" . esc_sql($value) . "'";
        }
        $updates_str = implode(",", $updates);
        $sql = "UPDATE {$table_name} SET {$updates_str} WHERE {$table_key}={$data[$table_key]}";
    }

    return $sql;
}



function idemailwiz_process_records($items, $operation, $table_name, $key_column) {
    global $wpdb;
    $count = 0;
    $errors = [];
    
    foreach($items as $item) {
        $result = $wpdb->query(idemailwiz_prepare_sql($item, $operation, $table_name, $key_column));

        if($result !== false) {
            $count++;
        } else {
            $errors[] = "Failed to {$operation} item with id {$item[$key_column]}. MySQL Error: " . $wpdb->last_error;
        }
    }

    return ['count' => $count, 'errors' => $errors];
}

//converts csv data (like from the metrics api call) to an array
function idemailwiz_csv_to_array($csv_data, $delimiter = ',') {
    $lines = explode(PHP_EOL, $csv_data);
    $headers = str_getcsv(array_shift($lines), $delimiter);
    $data = [];
    foreach ($lines as $line) {
        $data[] = array_combine($headers, str_getcsv($line, $delimiter));
    }
    return $data;
}

//Main function for updating a record (campaign, template, metric, or purchase) in our databases
function idemailwiz_update_records($fetch_function, $table_name, $table_key) {
    global $wpdb;
    $table_name = $wpdb->prefix . $table_name;
    
    // Fetch the existing data from the database
    $existing_records_array = idemailwiz_fetch_existing_data($table_name, $table_key);
    

    // Fetch all records from API
    $apiRecords = call_user_func($fetch_function);    

    //print_r($apiRecords);
    
    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];

    foreach($apiRecords as $record) {
        // Handle array values and camelCase
        $record = idemailwiz_prepare_array_data($record);
        //print_r($record);
        
        if(array_key_exists($record[$table_key], $existing_records_array)) {
            // Check if an "updatedAt" column exists. This will skip purchases since we don't want to update those
            if (isset($record['updatedAt'])) {
                // Update the row if the "updated at" value is different
                if(strtotime($record['updatedAt']) != strtotime($existing_records_array[$record[$table_key]])) {
                    $records_to_update[] = $record;
                }
            } else {
                if (!empty($existing_records_array[$record[$table_key]])) {
                // If "updatedAt" does not exist and the updatedAt value associated with this record id is NOT a blank array, we always update it
                // This skips updating records that don't have an updatedAt (ie the blank array), meaning they should only ever be added, not updated
                $records_to_update[] = $record;
                }
            }
            
        } else {
            $records_to_insert[] = $record;
        }
    }

    // If no updates or inserts are needed, return a message
    if(empty($records_to_update) && empty($records_to_insert)) {
        return ['message' => 'No updates are needed.', 'inserted' => 0, 'updated' => 0, 'errors' => []];
    }

    // Process new and existing records
    $insert_result = idemailwiz_process_records($records_to_insert, 'insert', $table_name, $table_key);
    $update_result = idemailwiz_process_records($records_to_update, 'update', $table_name, $table_key);

    // Combine the results
    $result = [
        'inserted' => $insert_result['count'],
        'updated' => $update_result['count'],
        'errors' => array_merge($insert_result['errors'], $update_result['errors'])
    ];

    if (count($result['errors']) > 0) {
        return 'There were errors during the operation:<br>';
        foreach ($result['errors'] as $error) {
            return $error . '<br>';
        }
    } else {
        return "Operation completed successfully.<br>Inserted: " . $result['inserted'] . " records.<br>Updated: " . $result['updated'] . " records.<br>";
    }
}


























