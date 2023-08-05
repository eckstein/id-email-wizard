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
        labels TEXT,
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
        creatorUserId VARCHAR(40),
        messageTypeId INT,
        campaignId INT,
        fromName VARCHAR(255),
        subject VARCHAR(255),
        preheaderText VARCHAR(255),
        fromEmail VARCHAR(50),
        replyToEmail VARCHAR(50),
        googleAnalyticsCampaignName VARCHAR(30),
        utmTerm VARCHAR(40),
        clientTemplateId INT,
        utmContent VARCHAR(40),
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
        totalHostedUnsubscribeClicks FLOAT,
        uniqueHostedUnsubscribeClicks FLOAT,
        lastWizUpdate BIGINT,
        wizOpenRate FLOAT,
        wizCtr FLOAT,
        wizCto FLOAT,
        wizUnsubRate FLOAT,
        wizCompRate FLOAT,
        wizCvr FLOAT,
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


    // Define Queue table
    $queue_table_name = $wpdb->prefix . 'idemailwiz_queue';
    $queue_sql = "CREATE TABLE $queue_table_name (
        campaignId INT,
        addedToQueue VARCHAR(32),
        type VARCHAR(20),
        PRIMARY KEY  (campaignId)
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



// Flattens a multi-dimensional array into a single-level array by concatenating keys into a prefix string.
// Skips values we don't want in the database. Limits 'linkParams' keys to 2 (typically utm_term and utm_content)
function idemailwiz_simplify_templates_array($template) {


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
        if (isset($template['metadata']['clientTemplateId'])) {
            $result['clientTemplateId'] = $template['metadata']['clientTemplateId'];
        }

        // Extract the desired keys from the 'linkParams' array
        if (isset($template['linkParams'])) {
            foreach ($template['linkParams'] as $linkParam) {
                if ($linkParam['key'] === 'utm_term') {
                    $result['utmTerm'] = $linkParam['value'];
                }
                if ($linkParam['key'] === 'utm_content') {
                    $result['utmContent'] = $linkParam['value'];
                }
            }
        }

        // Add the rest of the keys to the result
        foreach ($template as $key => $value) {
            // Skip the excluded keys and the keys we've already added
            $excludeKeys = array('html','plainText', 'cacheDataFeed', 'mergeDataFeedContext', 'utm_term', 'utm_content', 'createdAt', 'updatedAt', 'ccEmails', 'bccEmails', 'dataFeedIds');
            if ($key !== 'metadata' && $key !== 'linkParams' && !in_array($key, $excludeKeys)) {
                $result[$key] = $value;
            }
        }



    return $result;
}



// Calculate percentage metrics
// Takes a row of metrics data from the api call
function idemailwiz_calculate_metrics($metrics) {
    // Check that the necessary fields exist
    $requiredFields = ['uniqueEmailSends', 'uniqueEmailOpens', 'uniqueEmailClicks', 'uniqueUnsubscribes', 'totalComplaints', 'uniquePurchases'];
    foreach ($requiredFields as $field) {
        if (!isset($metrics[$field])) {
            $metrics[$field] = 0;
        }
    }

    if ($metrics['uniqueEmailSends'] > 0) {
        $metrics['wizOpenRate'] = ($metrics['uniqueEmailOpens'] / $metrics['uniqueEmailSends']) * 100;
        $metrics['wizCtr'] = ($metrics['uniqueEmailClicks'] / $metrics['uniqueEmailSends']) * 100;
        $metrics['wizUnsubRate'] = ($metrics['uniqueUnsubscribes'] / $metrics['uniqueEmailSends']) * 100;
        $metrics['wizCompRate'] = ($metrics['totalComplaints'] / $metrics['uniqueEmailSends']) * 100;
        $metrics['wizCvr'] = ($metrics['uniquePurchases'] / $metrics['uniqueEmailSends']) * 100;
    }

    if ($metrics['uniqueEmailOpens'] > 0) {
        $metrics['wizCto'] = ($metrics['uniqueEmailClicks'] / $metrics['uniqueEmailOpens']) * 100;
    }

    return $metrics;
}




// General function to get existing data from a database and into an array of $table_Key['updatedAt']
// For purchases, there is no updatedAt so we put an empty array as the value of each item
function idemailwiz_fetch_existing_data($table_name, $table_key) {
    global $wpdb;
    if ($table_name != 'wp_idemailwiz_purchases' && $table_name != 'wp_idemailwiz_metrics') {
        $existing_data = $wpdb->get_results("SELECT $table_key, updatedAt FROM $table_name", OBJECT_K);
        $existing_data_array = [];
        foreach($existing_data as $data){
            $existing_data_array[$data->$table_key] = $data->updatedAt;
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


// Function to query campaigns from the database based on args
function get_idwiz_campaigns($args = []) {
    global $wpdb;

    $type = isset($args['type']) ? $args['type'] : null;
    $templateId = isset($args['templateId']) ? $args['templateId'] : null;
    $messageMedium = isset($args['messageMedium']) ? $args['messageMedium'] : null;
    $campaignState = isset($args['campaignState']) ? $args['campaignState'] : null;
    $startAtStart = isset($args['startAt_start']) ? $args['startAt_start'] : null;
    $startAtEnd = isset($args['startAt_end']) ? $args['startAt_end'] : null;
    $limit = isset($args['limit']) ? (int)$args['limit'] : null;
    $sortBy = isset($args['sortBy']) ? $args['sortBy'] : null;
    $sort = isset($args['sort']) ? $args['sort'] : null;
    $fields = isset($args['fields']) ? $args['fields'] : '*';
    
    if (is_array($fields)) {
        $fields = implode(',', $fields);
    }
    

    $table_name = $wpdb->prefix . 'idemailwiz_campaigns';

    $sql = "SELECT $fields FROM $table_name WHERE 1=1";

    if ($type) {
        $sql .= $wpdb->prepare(" AND type = %s", $type);
    }
    if ($templateId) {
        $sql .= $wpdb->prepare(" AND templateId = %s", $templateId);
    }
    if ($messageMedium) {
        $sql .= $wpdb->prepare(" AND messageMedium = %s", $messageMedium);
    }
    if ($campaignState) {
        $sql .= $wpdb->prepare(" AND campaignState = %s", $campaignState);
    }
    if ($startAtStart) {
        if ($type != 'Triggered'){
            //For triggered campaigns, there's no start or end date, so we ignore
            //Reminder: dates are stored as milliseconds in the database
            try {
                $startAtStart = (new DateTime($startAtStart))->getTimestamp() * 1000;
                $sql .= $wpdb->prepare(" AND startAt >= %d", $startAtStart);
            } catch (Exception $e) {
                return array('error'=> $e);
            }
        }
    }
    if ($startAtEnd) {
        if ($type != 'Triggered'){
            //For triggered campaigns, there's no start or end date, so we ignore
            try {
                $startAtEnd = (new DateTime($startAtEnd))->getTimestamp() * 1000;
                $sql .= $wpdb->prepare(" AND startAt <= %d", $startAtEnd);
            } catch (Exception $e) {
                return array('error'=> $e);
            }
        }
    }

    // Add orderBy clause based on sortBy parameter
    if ($sortBy === 'startAt' || $sortBy === 'id') {
        if ($sort === 'ASC' || $sortBy === 'DESC') {
            $sql .= " ORDER BY $sortBy $sort";
        } else {
            $sql .= " ORDER BY $sortBy DESC";
        }
    }
    
    if ($limit) {
        $sql .= $wpdb->prepare(" LIMIT %d", $limit);
    }

    $campaigns = $wpdb->get_results($sql, ARRAY_A);

    return $campaigns;
    //return $sql;
}

// Function the query templates from the database based on args
function get_idwiz_templates($args = []) {
    global $wpdb;

    $templateId = isset($args['templateId']) ? $args['templateId'] : null;
    $campaignId = isset($args['campaignId']) ? $args['campaignId'] : null;
    $limit = isset($args['limit']) ? (int)$args['limit'] : null;
    $sortBy = isset($args['sortBy']) ? $args['sortBy'] : null;
    $sort = isset($args['sort']) ? $args['sort'] : null;
    $field = isset($args['field']) ? $args['field'] : '*';

    $table_name = $wpdb->prefix . 'idemailwiz_campaigns';

    $sql = "SELECT $field FROM $table_name WHERE 1=1";

    if ($templateId) {
        $sql .= $wpdb->prepare(" AND templateId = %s", $templateId);
    }
    if ($campaignId) {
        $sql .= $wpdb->prepare(" AND campaignId = %s", $campaignId);
    }

    // Add orderBy clause based on sortBy parameter
    if ($sortBy === 'templateId' || $sortBy === 'campaignId' || $sortBy === 'fromName') {
        if ($sort === 'ASC' || $sortBy === 'DESC') {
            $sql .= " ORDER BY $sortBy $sort";
        } else {
            $sql .= " ORDER BY $sortBy DESC";
        }
    }
    
    if ($limit) {
        $sql .= $wpdb->prepare(" LIMIT %d", $limit);
    }

    $templates = $wpdb->get_results($sql, ARRAY_A);

    return $templates;
    //return $sql;
}

// Function the query purchases from the database based on args
function get_idwiz_purchases($args = []) {
    global $wpdb;

    $purchaseId = isset($args['id']) ? $args['id'] : null;
    $orderId = isset($args['orderId']) ? $args['orderId'] : null;
    $campaignId = isset($args['campaignId']) ? $args['campaignId'] : null;
    $purchaseDateStart = isset($args['purchaseDateStart']) ? $args['purchaseDateStart'] : null;
    $purchaseDateEnd = isset($args['purchaseDateEnd']) ? $args['purchaseDateEnd'] : null;
    $discountCode = isset($args['discountCode']) ? $args['discountCode'] : null;
    $divisionName = isset($args['divisionName']) ? $args['divisionName'] : null;
    $userId = isset($args['userId']) ? $args['userId'] : null;

    $limit = isset($args['limit']) ? (int)$args['limit'] : null;
    $sortBy = isset($args['sortBy']) ? $args['sortBy'] : null;
    $sort = isset($args['sort']) ? $args['sort'] : null;

    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    $sql = "SELECT * FROM $table_name WHERE 1=1";

    if ($purchaseId) {
        $sql .= $wpdb->prepare(" AND purchaseId = %s", $purchaseId);
    }
    if ($orderId) {
        $sql .= $wpdb->prepare(" AND orderId = %s", $orderId);
    }
    if ($campaignId) {
        $sql .= $wpdb->prepare(" AND campaignId = %s", $campaignId);
    }
    if ($discountCode) {
        $sql .= $wpdb->prepare(" AND shoppingCartItems_discountCode = %s", $discountCode);
    }
    if ($divisionName) {
        $sql .= $wpdb->prepare(" AND shoppingCartItems_divisionName = %s", $divisionName);
    }
    if ($userId) {
        $sql .= $wpdb->prepare(" AND userId = %s", $userId);
    }

    if ($purchaseDateStart) {
        try {
            $purchaseDateStart = (new DateTime($purchaseDateStart))->format('Y-m-d');
            $sql .= $wpdb->prepare(" AND purchaseDate >= %s", $purchaseDateStart);
        } catch (Exception $e) {
            return array('error'=> $e);
        }
    }
    if ($purchaseDateEnd) {
        try {
            $purchaseDateEnd = (new DateTime($purchaseDateEnd))->format('Y-m-d');
            $sql .= $wpdb->prepare(" AND purchaseDate <= %s", $purchaseDateEnd);
        } catch (Exception $e) {
            return array('error'=> $e);
        }
    }
    
   

    // Add orderBy clause based on sortBy parameter
    if ($sortBy === 'templateId' || $sortBy === 'campaignId' || $sortBy === 'fromName') {
        if ($sort === 'ASC' || $sortBy === 'DESC') {
            $sql .= " ORDER BY $sortBy $sort";
        } else {
            $sql .= " ORDER BY $sortBy DESC";
        }
    }
    
    if ($limit) {
        $sql .= $wpdb->prepare(" LIMIT %d", $limit);
    }

    $purchases = $wpdb->get_results($sql, ARRAY_A);

    return $purchases;
    //return $sql;
}

// Function to get all metrics or get one campaign's metric, if campaignId is present
function get_idwiz_campaign_metrics($campaignId=null) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'idemailwiz_metrics';

    $sql = "SELECT * FROM $table_name WHERE 1=1";
    if ($campaignId) {
        $sql .= $wpdb->prepare(" AND id = %s", $campaignId);
    }

    $metrics = $wpdb->get_results($sql, ARRAY_A);

    return $metrics;
}




function idwiz_metrics_by_campaigns($campaign_ids) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_metrics';

    // Convert the array of IDs into a string
    $ids = implode(',', $campaign_ids);

    // Query the database for metrics with the given campaign IDs
    $metrics = $wpdb->get_results("SELECT * FROM {$table_name} WHERE id IN ({$ids})");

    if ($metrics) {
        return $metrics;
    } else {
        return $wpdb->last_error;
    }
}





function idemailwiz_table_value_mapping($sql_response) {
    // Define the associative arrays for each table with the updated structure.
    $table_mapping = idemailwiz_table_map();

    // Create a new array for the formatted data
    $formatted_data = [];

    // Loop through each item in the $sql_response array and update the values.
    foreach ($sql_response as $item) {
        $new_item = [];
        foreach ($item as $key => $value) {
            // Check if the key exists in the $table_mapping and 'tableHeader' and 'fieldFormat' keys exist.
            if (isset($table_mapping[$key])) {
                // Update the header
                $new_key = $table_mapping[$key]['tableHeader'];
                
                // Check if the 'fieldFormat' key exists and format the value accordingly
                if (isset($table_mapping[$key]['fieldFormat'])) {
                    $fieldformat = $table_mapping[$key]['fieldFormat'];
                    $new_value = idwiz_format_table_field($value, $fieldformat);
                } else {
                    $new_value = $value;
                }

                $new_item[$new_key] = $new_value;
            } else {
                $new_item[$key] = $value;
            }           
        }
        $formatted_data[] = $new_item;
    }

    // Return the formatted data array.
    return $formatted_data;
}


function idwiz_format_table_field($value, $format) {
    if ($format == 'mills_date') {
        $value = $value / 1000;
        $value = date("m/d/Y", $value);
    }
    if ($format == 'money') {
        $value = '$' . number_format($value, 2);
    }
    if ($format == 'percentage') {
        $value = number_format($value, 2) . '%';
    }
    if ($format == 'number') {
        $value = number_format($value);
    }
    return $value;
}








function wiz_log($something) {
    // Get the current date and time
    $date = new DateTime();
    $timestamp = $date->format('Y-m-d H:i:s');

    // Build the log entry
    $logEntry = "[$timestamp]\n$something\n";

    // Get the path to the log file
    $logFile = dirname(plugin_dir_path( __FILE__ ) ).'/sync-log.txt';

    // Append the log entry to the log file
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}












