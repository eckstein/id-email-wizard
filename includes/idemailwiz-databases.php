<?php
function idemailwiz_create_databases() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Define Campaigns table
    $campaign_table_name = $wpdb->prefix . 'idemailwiz_campaigns';
    $campaign_sql = "CREATE TABLE IF NOT EXISTS $campaign_table_name (
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
        messageMedium VARCHAR(20),
        PRIMARY KEY  (id)
    ) $charset_collate;";



    // Define Templates table
    $template_table_name = $wpdb->prefix . 'idemailwiz_templates';
    $template_sql = "CREATE TABLE IF NOT EXISTS $template_table_name (
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
    $metrics_sql = "CREATE TABLE IF NOT EXISTS $metrics_table_name (
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
        wizDeliveryRate FLOAT,
        wizOpenRate FLOAT,
        wizCtr FLOAT,
        wizCto FLOAT,
        wizUnsubRate FLOAT,
        wizCompRate FLOAT,
        wizCvr FLOAT,
        wizAov FLOAT,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Define Purchases table
    $purchase_table_name = $wpdb->prefix . 'idemailwiz_purchases';
    $purchase_sql = "CREATE TABLE IF NOT EXISTS $purchase_table_name (
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

    //Create our custom view
    idemailwiz_create_view();
}

function idemailwiz_create_view() {
    global $wpdb;
    
    $sql = "
    CREATE OR REPLACE VIEW idwiz_campaign_view AS
    SELECT 
        campaigns.id as campaign_id,
        campaigns.type as campaign_type,
        campaigns.messageMedium as message_medium,
        campaigns.name as campaign_name,
        campaigns.startAt as campaign_start,
        campaigns.labels as campaign_labels,
        templates.subject as template_subject,
        templates.preheaderText as template_preheader,
        metrics.uniqueEmailSends as unique_email_sends,
        metrics.uniqueEmailsDelivered as unique_delivered,
        metrics.wizDeliveryRate as wiz_delivery_rate,
        metrics.uniqueEmailOpens as unique_email_opens,
        metrics.wizOpenRate as wiz_open_rate,
        metrics.uniqueEmailClicks as unique_email_clicks,
        metrics.wizCtr as wiz_ctr,
        metrics.wizCto as wiz_cto,
        metrics.uniqueUnsubscribes as unique_unsubscribes,
        metrics.uniquePurchases as unique_purchases,
        metrics.wizCvr as wiz_cvr,
        metrics.revenue as revenue
    FROM 
        " . $wpdb->prefix . "idemailwiz_campaigns AS campaigns
    LEFT JOIN 
        " . $wpdb->prefix . "idemailwiz_metrics AS metrics ON campaigns.id = metrics.id
    LEFT JOIN 
        " . $wpdb->prefix . "idemailwiz_templates AS templates ON campaigns.templateId = templates.templateId
    WHERE metrics.uniqueEmailSends > 5
        ";

    $wpdb->query($sql);
}



//Camel case for database headers
function to_camel_case($string) {
    $string = str_replace('.', '_', $string); // Replace periods with underscores
    $words = explode(' ', $string); // Split the string into words
    $words = array_map('ucwords', $words); // Capitalize the first letter of each word
    $camelCaseString = implode('', $words); // Join the words back together
    return lcfirst($camelCaseString); // Make the first letter lowercase and return
}



// For the returned Template object from Iterable.
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










function get_idwiz_campaign($campaignID) {
    $campaign = get_idwiz_campaigns(array('id'=>$campaignID));
    if ($campaign) {
        return $campaign[0];
    } else {
        return false;
    }
}
// Function to query campaigns from the database based on args
function get_idwiz_campaigns($args = []) {
    global $wpdb;

    $campaignId = isset($args['id']) ? $args['id'] : null;
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

    if ($campaignId) {
        $sql .= $wpdb->prepare(" AND id = %s", $campaignId);
    }
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

        //Reminder: dates are stored as milliseconds in the database
        try {
            $startAtStart = (new DateTime($startAtStart))->getTimestamp() * 1000;
            $sql .= $wpdb->prepare(" AND startAt >= %d", $startAtStart);
        } catch (Exception $e) {
            return array('error'=> $e);
        }

    }
    if ($startAtEnd) {

        try {
            $startAtEnd = (new DateTime($startAtEnd))->getTimestamp() * 1000;
            $sql .= $wpdb->prepare(" AND startAt <= %d", $startAtEnd);
        } catch (Exception $e) {
            return array('error'=> $e);
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

    if ( $wpdb->last_error ) {
        return false;
    }

    return $campaigns;
    //return $sql;
}

function get_idwiz_template($templateID) {
    $template = get_idwiz_templates(array('templateId'=>$templateID));
    if ($template) {
        return $template[0];
    } else {
        return false;
    }
}
// Function the query templates from the database based on args
function get_idwiz_templates($args = []) {
    global $wpdb;

    $templateId = isset($args['templateId']) ? $args['templateId'] : null;
    $campaignId = isset($args['campaignId']) ? $args['campaignId'] : null;
    $limit = isset($args['limit']) ? (int)$args['limit'] : null;
    $sortBy = isset($args['sortBy']) ? $args['sortBy'] : null;
    $sort = isset($args['sort']) ? $args['sort'] : null;
    $fields = isset($args['fields']) ? $args['fields'] : '*';

    if (is_array($fields)) {
        $fields = implode(',', $fields);
    }

    $table_name = $wpdb->prefix . 'idemailwiz_templates';

    $sql = "SELECT $fields FROM $table_name WHERE 1=1";

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

    if ( $wpdb->last_error ) {
        return false;
    }

    return $templates;
    //return $sql or false
}

function get_idwiz_purchase($purchaseID) {
    $purchase = get_idwiz_purchases(array('id'=>$purchaseID));
    if ($purchase) {
        return $purchase[0];
    } else {
        return false;
    }
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
    $fields = isset($args['fields']) ? $args['fields'] : '*';

    if (is_array($fields)) {
        $fields = implode(',', $fields);
    }

    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    $sql = "SELECT $fields FROM $table_name WHERE 1=1";
    
    if ($purchaseId) {
        $sql .= $wpdb->prepare(" AND id = %s", $purchaseId);
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
    //return $sql or false;
}

function get_idwiz_metric($campaignID) {
    $metric = get_idwiz_metrics(array('id'=>$campaignID));
    if ($metric) {
        return $metric[0];
    } else {
        return false;
    }
}
// Function to get all metrics or get one campaign's metric, if campaignId is present
function get_idwiz_metrics($args=[]) {
    global $wpdb;

    $campaignId = isset($args['id']) ? $args['id'] : null;
    $limit = isset($args['limit']) ? (int)$args['limit'] : null;
    $fields = isset($args['fields']) ? $args['fields'] : '*';

    $table_name = $wpdb->prefix . 'idemailwiz_metrics';

    $sql = "SELECT $fields FROM $table_name WHERE 1=1";
    
    if ($campaignId) {
        $sql .= $wpdb->prepare(" AND id = %s", $campaignId);
    }
    if ($limit) {
        $sql .= $wpdb->prepare(" LIMIT %d", $limit);
    }

    $metrics = $wpdb->get_results($sql, ARRAY_A);

    return $metrics;
    //return $sql or false
}




function get_idwiz_metrics_by_campaigns($campaign_ids) {
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


function idwiz_get_campaign_table_view() {
    global $wpdb;

    // Bail early without valid nonce
    if (!check_ajax_referer('data-tables', 'security')) return;
    
    // Fetch data from your view
    $results = $wpdb->get_results("SELECT * FROM idwiz_campaign_view", ARRAY_A);
    //wiz_log(print_r($results, true));

    // Iterate through the results and unserialize specific columns
    foreach ($results as &$row) {
        if (isset($row['campaign_labels']) && !empty($row['campaign_labels'])) {
            $unserializedLabels = maybe_unserialize($row['campaign_labels']);
            if (is_array($unserializedLabels)) {
                $row['campaign_labels'] = implode(', ', $unserializedLabels);
            }
        }
    }

    // Return data in JSON format
    echo json_encode($results);
    wp_die();
}

add_action('wp_ajax_idwiz_get_campaign_table_view', 'idwiz_get_campaign_table_view');
add_action('wp_ajax_nopriv_idwiz_get_campaign_table_view', 'idwiz_get_campaign_table_view');

























