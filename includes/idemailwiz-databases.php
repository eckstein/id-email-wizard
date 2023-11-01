<?php
function idemailwiz_create_databases()
{
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
        experimentIds VARCHAR (255),
        listIds VARCHAR(255),
        suppressionListIds VARCHAR(255),
        type VARCHAR(20),
        initiativeLinks VARCHAR(255),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $campaign_init_table_name = $wpdb->prefix . 'idemailwiz_init_campaigns';
    $campaign_init_sql = "CREATE TABLE IF NOT EXISTS $campaign_init_table_name (
        id INT AUTO_INCREMENT,
        initiativeId INT,
        campaignId INT,
        PRIMARY KEY  (id),
        INDEX idx_campaignId (campaignId),
        INDEX idx_initiativeId (initiativeId)
    ) $charset_collate;";

    $cohort_table_name = $wpdb->prefix . 'idemailwiz_cohorts';
    $cohorts_sql = "CREATE TABLE IF NOT EXISTS $cohort_table_name (
    id INT PRIMARY KEY AUTO_INCREMENT,
    orderId VARCHAR(10),
    medium VARCHAR(32),
    accountNumber VARCHAR(20),
    cohort_type VARCHAR(32),
    cohort_value VARCHAR(32),
    purchaseDate VARCHAR(26),
    INDEX accountNumber (accountNumber),
    INDEX orderId (orderId),
    INDEX medium (medium)
    ) $charset_collate;";


    $triggered_sends_table_name = $wpdb->prefix . 'idemailwiz_triggered_sends';
    $triggered_sends_sql = "CREATE TABLE IF NOT EXISTS $triggered_sends_table_name (
        messageId VARCHAR(32),
        campaignId INT,
        templateId INT,
        startAt BIGINT,
        PRIMARY KEY  (messageId),
        INDEX campaignId (campaignId),
        INDEX startAt (startAt)
    ) $charset_collate;";


    $triggered_opens_table_name = $wpdb->prefix . 'idemailwiz_triggered_opens';
    $triggered_opens_sql = "CREATE TABLE IF NOT EXISTS $triggered_opens_table_name (
        messageId VARCHAR(32),
        campaignId INT,
        templateId INT,
        startAt BIGINT,
        PRIMARY KEY  (messageId),
        INDEX campaignId (campaignId),
        INDEX startAt (startAt)
    ) $charset_collate;";

    $triggered_clicks_table_name = $wpdb->prefix . 'idemailwiz_triggered_clicks';
    $triggered_clicks_sql = "CREATE TABLE IF NOT EXISTS $triggered_clicks_table_name (
        messageId VARCHAR(32),
        campaignId INT,
        templateId INT,
        startAt BIGINT,
        PRIMARY KEY  (messageId),
        INDEX campaignId (campaignId),
        INDEX startAt (startAt)
    ) $charset_collate;";


    $ga_campaign_rev_table_name = $wpdb->prefix . 'idemailwiz_ga_campaign_revenue';
    $ga_campaign_rev_sql = "CREATE TABLE IF NOT EXISTS $ga_campaign_rev_table_name (
        transactionId VARCHAR(7),
        date VARCHAR(32),
        campaignId VARCHAR(32),
        division VARCHAR(32),
        revenue FLOAT,
        purchases INT,
        INDEX (transactionId),
        INDEX campaignId (campaignId),
        INDEX date (date),
        INDEX division (division)
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
        messageMedium VARCHAR(20),
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
        html MEDIUMTEXT,
        message MEDIUMTEXT,
        imageUrl VARCHAR(255),
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
        gaRevenue FLOAT,
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

    // Define Experiments table
    $experiments_table_name = $wpdb->prefix . 'idemailwiz_experiments';
    $experiments_sql = "CREATE TABLE IF NOT EXISTS $experiments_table_name (
        campaignId INT,
        experimentId INT,
        templateId INT,
        name VARCHAR(255),
        type VARCHAR(50),
        createdBy VARCHAR(50),
        creationDate BIGINT(20),
        lastModified BIGINT(20),
        subject VARCHAR(255),
        improvement FLOAT,
        confidence FLOAT,
        totalEmailSends INT,
        uniqueEmailSends INT,
        emailDeliveryRate FLOAT,
        totalEmailsDelivered INT,
        uniqueEmailsDelivered INT,
        totalEmailOpens INT,
        totalEmailOpensFiltered INT,
        uniqueEmailOpens INT,
        uniqueEmailOpensFiltered INT,
        uniqueEmailOpensOrClicks INT,
        emailOpenRate FLOAT,
        totalEmailsClicked INT,
        uniqueEmailClicks INT,
        clicksOpens FLOAT,
        emailClickRate FLOAT,
        totalHostedUnsubscribeClicks INT,
        uniqueHostedUnsubscribeClicks INT,
        totalComplaints INT,
        complaintRate FLOAT,
        totalEmailsBounced INT,
        uniqueEmailsBounced INT,
        emailBounceRate FLOAT,
        totalEmailHoldout INT,
        totalEmailSendSkips INT,
        totalUnsubscribes INT,
        uniqueUnsubscribes INT,
        emailUnsubscribeRate FLOAT,
        revenue FLOAT,
        totalPurchases INT,
        uniquePurchases INT,
        averageOrderValue FLOAT,
        purchasesMEmail FLOAT,
        revenueMEmail FLOAT,
        totalCustomConversions INT,
        uniqueCustomConversions INT,
        averageCustomConversionValue FLOAT,
        conversionsEmailHoldOuts FLOAT,
        conversionsUniqueEmailsDelivered FLOAT,
        sumOfCustomConversions FLOAT,
        wizDeliveryRate FLOAT,
        wizOpenRate FLOAT,
        uniqueEmailOpenRate FLOAT,
        wizCtr FLOAT,
        uniqueEmailClickRate FLOAT,
        wizCto FLOAT,
        wizUnsubRate FLOAT,
        wizCompRate FLOAT,
        wizCvr FLOAT,
        wizAov FLOAT,
        wizWinner BIT,
        experimentNotes MEDIUMTEXT,
        PRIMARY KEY (templateId),
        INDEX idx_experimentId (experimentId),
        INDEX idx_campaignId (campaignId)
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
        PRIMARY KEY  (id),
        INDEX campaignId (campaignId),
        INDEX purchaseDate (purchaseDate),
        INDEX shoppingCartItems_divisionName (shoppingCartItems_divisionName),
        INDEX orderId (orderId),
        INDEX accountNumber (accountNumber)
    ) $charset_collate;";



    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($campaign_sql);
    dbDelta($campaign_init_sql);
    dbDelta($cohorts_sql);
    dbDelta($triggered_sends_sql);
    dbDelta($triggered_opens_sql);
    dbDelta($triggered_clicks_sql);
    dbDelta($ga_campaign_rev_sql);
    dbDelta($template_sql);
    dbDelta($metrics_sql);
    dbDelta($experiments_sql);
    dbDelta($purchase_sql);


    //Create our custom view for the datatable
    idemailwiz_create_view();
}

function idemailwiz_create_view()
{
    global $wpdb;

    $sql = "
        CREATE OR REPLACE VIEW idwiz_campaign_view AS
        SELECT 
            campaigns.id as campaign_id,
            campaigns.type as campaign_type,
            campaigns.messageMedium as message_medium,
            campaigns.name as campaign_name,
            campaigns.initiativeLinks as initiative_links,
            campaigns.startAt as campaign_start,
            campaigns.labels as campaign_labels,
            campaigns.experimentIds as experiment_ids,
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
            metrics.wizUnsubRate as wiz_unsub_rate,
            metrics.uniquePurchases as unique_purchases,
            metrics.wizCvr as wiz_cvr,
            metrics.revenue as revenue,
            metrics.gaRevenue as ga_revenue,
            GROUP_CONCAT(init_campaigns.initiativeId) as initiative_ids
        FROM 
            " . $wpdb->prefix . "idemailwiz_campaigns AS campaigns
        LEFT JOIN 
            " . $wpdb->prefix . "idemailwiz_metrics AS metrics ON campaigns.id = metrics.id
        LEFT JOIN 
            " . $wpdb->prefix . "idemailwiz_templates AS templates ON campaigns.templateId = templates.templateId
        LEFT JOIN 
            " . $wpdb->prefix . "idemailwiz_init_campaigns AS init_campaigns ON campaigns.id = init_campaigns.campaignId
        WHERE metrics.uniqueEmailSends > 5
        GROUP BY campaigns.id;
        ";

    $wpdb->query($sql);
}



//Powers the sql query for the get_ functions for all databases
function build_idwiz_query($args, $table_name)
{
    global $wpdb;

    $fields = isset($args['fields']) ? $args['fields'] : '*';
    if (is_array($fields)) {
        $fields = implode(',', $fields);
    }

    $sql = "SELECT $fields FROM $table_name WHERE 1=1";

    // Filter out zero values from 'ids' and 'campaignIds' if they are present
    // Prevents huge data calls in the purchases database
    if (isset($args['id'])) {
        if ($args['id'] == 0) {
            $args['id'] = null;
        }
    }
    if (isset($args['ids'])) {
        $args['ids'] = array_filter($args['ids'], function ($id) {
            return $id != 0;
        });
    }
    if (isset($args['campaignIds'])) {
        $args['campaignIds'] = array_filter($args['campaignIds'], function ($id) {
            return $id != 0;
        });
    }

    // Copy the args array and remove certain keys so they don't get used in the WHERE clause
    $where_args = $args;
    unset($where_args['fields']);
    unset($where_args['limit']);
    unset($where_args['sortBy']);
    unset($where_args['sort']);
    unset($where_args['offset']);
    unset($where_args['not-ids']);

    // Setup special variable cases
    $campaignKey = 'id';
    $dateKey = 'startAt';

    if (
        $table_name == $wpdb->prefix . 'idemailwiz_purchases'
        || $table_name == $wpdb->prefix . 'idemailwiz_templates'
        || $table_name == $wpdb->prefix . 'idemailwiz_experiments'
    ) {
        $campaignKey = 'campaignId';
    }

    if ($table_name == $wpdb->prefix . 'idemailwiz_purchases') {
        $dateKey = 'purchaseDate';
        //exclude terciary purchases (lunches, add-ons, etc)
        $sql .= $wpdb->prepare(" AND shoppingCartItems_productCategory != %s", '17004');
        $sql .= $wpdb->prepare(" AND shoppingCartItems_productCategory != %s", '17003');
        $sql .= $wpdb->prepare(" AND shoppingCartItems_productCategory != %s", '17001');
    }

    foreach ($where_args as $key => $value) {
        if ($value !== null && $value !== '') {


            if (($key === 'ids' || $key === 'campaignIds')) { // Special case for array of campaign IDs

                $placeholders = implode(',', array_fill(0, count($value), '%d'));

                // Use call_user_func_array to dynamically pass an array of arguments to $wpdb->prepare
                $sql .= call_user_func_array(array($wpdb, 'prepare'), array_merge(array(" AND $campaignKey IN ($placeholders)"), $value));
            } elseif ($key === 'startAt_start') {
                try {
                    $dt = new DateTime($value, new DateTimeZone('America/Los_Angeles'));
                    $dt->setTime(0, 0, 0); // Set the time to the start of the day
                    $dt->setTimezone(new DateTimeZone('UTC'));

                    if ($dateKey === 'purchaseDate') {
                        $value = $dt->format('Y-m-d');
                        $sql .= $wpdb->prepare(" AND $dateKey >= %s", $value);
                    } else {
                        $value = $dt->getTimestamp() * 1000;
                        $sql .= $wpdb->prepare(" AND $dateKey >= %d", $value);
                    }

                } catch (Exception $e) {
                    return ['error' => $e];
                }
            } elseif ($key === 'startAt_end') {
                try {
                    $dt = new DateTime($value, new DateTimeZone('America/Los_Angeles'));
                    $dt->setTime(23, 59, 59); // Set the time to the end of the day
                    $dt->setTimezone(new DateTimeZone('UTC'));

                    if ($dateKey === 'purchaseDate') {
                        $value = $dt->format('Y-m-d');
                        $sql .= $wpdb->prepare(" AND $dateKey <= %s", $value);
                    } else {
                        $value = $dt->getTimestamp() * 1000;
                        $sql .= $wpdb->prepare(" AND $dateKey <= %d", $value);
                    }

                } catch (Exception $e) {
                    return ['error' => $e];
                }
            } elseif ($key === 'serialized' && is_array($value)) {
                foreach ($value as $serialized_column => $serialized_value) {
                    // Check for string or integer to get a proper match
                    $like_value_int = '%i:' . $wpdb->esc_like($serialized_value) . ';%';
                    $like_value_str = '%"' . $wpdb->esc_like($serialized_value) . '"%';
                    $sql .= $wpdb->prepare(" AND ($serialized_column LIKE %s OR $serialized_column LIKE %s)", $like_value_int, $like_value_str);
                }
            } else {
                if (is_array($value)) {
                    $placeholders = implode(',', array_fill(0, count($value), '%s'));
                    $flattened_values = implode("','", array_map([$wpdb, '_escape'], $value));
                    $sql .= " AND $key IN ('$flattened_values')";
                } else {
                    $sql .= $wpdb->prepare(" AND $key = %s", $value);
                }
            }

        }
    }

    if (isset($args['not-ids']) && is_array($args['not-ids'])) {
        $placeholders = implode(',', array_fill(0, count($args['not-ids']), '%d'));
        $sql .= call_user_func_array(array($wpdb, 'prepare'), array_merge(array(" AND $campaignKey NOT IN ($placeholders)"), $args['not-ids']));
    }

    if (isset($args['sortBy'])) {
        $sort = isset($args['sort']) && ($args['sort'] === 'ASC' || $args['sort'] === 'DESC') ? $args['sort'] : 'DESC';
        $sql .= " ORDER BY {$args['sortBy']} $sort";
    }

    if (isset($args['limit'])) {
        $sql .= $wpdb->prepare(" LIMIT %d", (int) $args['limit']);
    }

    if (isset($args['offset'])) {
        $sql .= $wpdb->prepare(" OFFSET %d", (int) $args['offset']);
    }

    return $sql;


}



// Does the sql query for the main get_ functions based on the passed parameters
function execute_idwiz_query($sql)
{
    global $wpdb;
    $results = $wpdb->get_results($sql, ARRAY_A);

    if ($wpdb->last_error) {
        error_log($wpdb->last_error);
        return false;
    }

    return $results;
}

function get_idwiz_campaigns($args = [])
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_campaigns';
    $sql = build_idwiz_query($args, $table_name);
    return execute_idwiz_query($sql);
}

function get_idwiz_templates($args = [])
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_templates';
    $sql = build_idwiz_query($args, $table_name);
    return execute_idwiz_query($sql);
}

function get_idwiz_purchases($args = [])
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';
    $sql = build_idwiz_query($args, $table_name);
    return execute_idwiz_query($sql);
}

function get_idwiz_metrics($args = [])
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_metrics';
    $sql = build_idwiz_query($args, $table_name);
    return execute_idwiz_query($sql);
}

function get_idwiz_experiments($args = [])
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_experiments';
    $sql = build_idwiz_query($args, $table_name);
    return execute_idwiz_query($sql);
}

function get_idwiz_campaign($campaignID)
{
    $campaigns = get_idwiz_campaigns(['id' => $campaignID]);
    return $campaigns ? $campaigns[0] : false;
}
function get_idwiz_template($templateID)
{
    $templates = get_idwiz_templates(['templateId' => $templateID]);
    return $templates ? $templates[0] : false;
}
function get_idwiz_purchase($purchaseID)
{
    $purchases = get_idwiz_purchases(['id' => $purchaseID]);
    return $purchases ? $purchases[0] : false;
}
function get_idwiz_metric($campaignID)
{
    $metrics = get_idwiz_metrics(['id' => $campaignID]);
    return $metrics ? $metrics[0] : false;
}
function get_idwiz_experiment($templateId)
{
    $experiments = get_idwiz_experiments(['templateId' => $templateId]);
    return $experiments ? $experiments[0] : false;
}






function idwiz_is_serialized($value)
{
    if (!is_string($value))
        return false;
    if (trim($value) == "")
        return false;
    if (preg_match("/^(i|s|a|o|d):(.*);/si", $value))
        return true;
    return false;
}


function get_idemailwiz_triggered_data($database, $args = []) {
    if (!$database) {
        return false;
    }
    global $wpdb;

    // Initialize query components
    $where_clauses = [];
    $query_params = [];

    // Determine which fields (columns) to select
    $fields = '*'; // Default is to select all columns
    if (isset($args['fields']) && !empty($args['fields'])) {
        $fields = sanitize_text_field($args['fields']); // Basic sanitization
    }

    // Check if campaignIds are provided
    if (isset($args['campaignIds']) && is_array($args['campaignIds']) && !empty($args['campaignIds'])) {
        $placeholders = array_fill(0, count($args['campaignIds']), '%d');
        $where_clauses[] = "campaignId IN (" . implode(", ", $placeholders) . ")";
        $query_params = array_merge($query_params, $args['campaignIds']);
    }

    // Check if startAt_start is provided
    if (isset($args['startAt_start'])) {
        $timestampStart = strtotime($args['startAt_start']) * 1000; // Convert to millisecond timestamp
        $where_clauses[] = "startAt >= %d";
        $query_params[] = $timestampStart;
    }

    // Check if startAt_end is provided
    if (isset($args['startAt_end'])) {
        // Add a day (86400 seconds) to include the entire end day and then convert to milliseconds
        $timestampEnd = (strtotime($args['startAt_end']) + 86400) * 1000;
        $where_clauses[] = "startAt <= %d";
        $query_params[] = $timestampEnd;
    }

    // Construct the SQL query
    $sql = "SELECT $fields FROM " . $wpdb->prefix . $database;
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    // Prepare the SQL query with parameters
    $prepared_sql = $wpdb->prepare($sql, $query_params);

    // Execute the query and fetch results
    $results = $wpdb->get_results($prepared_sql, ARRAY_A);

    // Debug: Print SQL error (if any)
    if ($wpdb->last_error) {
        echo "SQL Error: " . $wpdb->last_error . "<br>";
    }

    return $results;
}




function get_triggered_data_by_campaign_id($campaignId, $metricType)
{
    global $wpdb;

    // Sanitize the input to prevent SQL injection
    $safe_campaignId = (int) $campaignId;

    // SQL query to fetch rows from wp_idemailwiz_triggered_sends that match the campaignId
    $sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "idemailwiz_triggered_{$metricType}s WHERE campaignId = %d", $safe_campaignId);

    // Execute the SQL query and fetch the results
    $results = $wpdb->get_results($sql, ARRAY_A);

    // Return the results
    return $results;
}

function get_idwiz_campaigns_by_dates($startDate, $endDate, $triggered = false)
{
    $getCampaigns = array(
        'startAt_start' => $startDate,
        'startAt_end' => $endDate,
    );
    if (!$triggered) {
        $getCampaigns['type'] = 'Blast';
    }
    $campaigns = get_idwiz_campaigns($getCampaigns);

    return $campaigns;
}


function get_cohort_value_for_division($purchase)
{
    return $purchase['shoppingCartItems_divisionName'];
}

function get_cohort_value_for_day_of_year($purchase)
{
    $date = new DateTime($purchase['purchaseDate']);
    $startOfYear = new DateTime($date->format('Y-01-01'));
    $interval = $startOfYear->diff($date);
    $dayOfYearCohort = $interval->days + 1; // Adding 1 to start counting from Day 1

    $isLeapYear = date('L', strtotime($purchase['purchaseDate']));
    if (!$isLeapYear && $dayOfYearCohort >= 60) {
        $dayOfYearCohort -= 1;
    }

    return $dayOfYearCohort;
}

function idwiz_populate_cohort($cohort_type, $cohort_value_callback)
{
    global $wpdb;

    // Fetch all purchases using your existing method
    $campaigns = get_idwiz_campaigns(['fields' => 'id']);
    //$purchases = get_idwiz_purchases(['shoppingCartItems_utmMedium'=>'email']);
    $purchases = get_idwiz_purchases(['fields' => 'accountNumber, orderId, purchaseDate, shoppingCartItems_utmMedium, shoppingCartItems_divisionName']);

    // Loop through each purchase
    foreach ($purchases as $purchase) {
        $accountNumber = $purchase['accountNumber'];
        $orderId = $purchase['orderId'];
        $purchaseDate = $purchase['purchaseDate'];
        $purchaseMedium = $purchase['shoppingCartItems_utmMedium'];
        $cohort_value = $cohort_value_callback($purchase);

        // Skip if either account number or cohort value is empty
        if (empty($accountNumber) || empty($cohort_value)) {
            continue;
        }

        // Check if this cohort already exists in the table
        $existing_cohort = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . $wpdb->prefix . "idemailwiz_cohorts WHERE accountNumber = %s AND cohort_type = %s AND orderId = %s AND medium = %s",
                $accountNumber,
                $cohort_type,
                $orderId,
                $purchaseMedium
            )
        );

        // Insert or update the cohort entry
        if (null === $existing_cohort) {
            $wpdb->insert(
                $wpdb->prefix . 'idemailwiz_cohorts',
                [
                    'accountNumber' => $accountNumber,
                    'orderId' => $orderId,
                    'purchaseDate' => $purchaseDate,
                    'cohort_type' => $cohort_type,
                    'cohort_value' => $cohort_value,
                    'medium' => $purchaseMedium
                ]
            );
        } else if ($existing_cohort->cohort_value != $cohort_value) {
            $wpdb->update(
                $wpdb->prefix . 'idemailwiz_cohorts',
                ['cohort_value' => $cohort_value],
                ['id' => $existing_cohort->id]
            );
        }
    }
}

//idwiz_populate_day_of_year_cohort();
function idwiz_populate_division_cohort()
{
    idwiz_populate_cohort('division', 'get_cohort_value_for_division');
}

function idwiz_populate_day_of_year_cohort()
{
    idwiz_populate_cohort('day_of_year', 'get_cohort_value_for_day_of_year');
}



function idwiz_find_matching_purchases($options = []) {
    global $wpdb;

    $campaign_ids = $options['campaignIds'] ?? [];
    $campaign_types = $options['campaignTypes'] ?? ['Blast', 'Triggered'];
    error_log(print_r($campaign_types, true));
    $start_date = $options['startDate'] ?? false;
    $end_date = $options['endDate'] ?? false;

    $utm_terms = [];
    $utm_campaigns = [];

    if ($campaign_ids && !empty($campaign_ids)) {
        // Fetch the corresponding templates
        $templates = get_idwiz_templates(array('ids' => $campaign_ids));

        // Create arrays for utmTerms and utmCampaigns for lookup
        $utm_terms = array_column($templates, 'utmTerm');
        $utm_campaigns = array_column($templates, 'utmCampaign');
    }

    // Build the SQL query
    $sql = "SELECT * FROM {$wpdb->prefix}idemailwiz_purchases WHERE 1=1";

    // Add conditions for campaign_ids, utm_terms, and utm_campaigns
    $conditions = [];
    if (!empty($campaign_ids)) {
        $conditions[] = "campaignId IN (" . implode(',', array_map('intval', $campaign_ids)) . ")";
    } else {
        $conditions[] = "shoppingCartItems_utmMedium = 'email'";
    }
    if (!empty($utm_terms)) {
        $conditions[] = "shoppingCartItems_utmTerm IN ('" . implode("','", array_map([$wpdb, '_escape'], $utm_terms)) . "')";
    }
    if (!empty($utm_campaigns)) {
        $conditions[] = "shoppingCartItems_utmCampaign IN ('" . implode("','", array_map([$wpdb, '_escape'], $utm_campaigns)) . "')";
    }

    if (!empty($conditions)) {
        $sql .= " AND (" . implode(" OR ", $conditions) . ")";
    }

    // Add date conditions if provided
    if ($start_date) {
        $sql .= $wpdb->prepare(" AND purchaseDate >= %s", $start_date);
    }
    if ($end_date) {
        $sql .= $wpdb->prepare(" AND purchaseDate <= %s", $end_date);
    }

    // Execute the query
    $all_purchases = $wpdb->get_results($sql, ARRAY_A);
    
    // Filter the purchases by campaign type
    $filtered_purchases = [];
    foreach ($all_purchases as $purchase) {
        $purchase_campaign = get_idwiz_campaign($purchase['campaignId']);
        if ($purchase_campaign && in_array($purchase_campaign['type'], $campaign_types)) {
            $filtered_purchases[] = $purchase;
        }
    }

    return $filtered_purchases;
}


