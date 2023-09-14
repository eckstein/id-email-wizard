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
        experimentIds VARCHAR (255),
        listIds VARCHAR(255),
        suppressionListIds VARCHAR(255),
        type VARCHAR(20),
        messageMedium VARCHAR(20),
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
        wizCtr FLOAT,
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
        INDEX idx_campaignId (campaignId)
    ) $charset_collate;";


  
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $campaign_sql );
    dbDelta( $campaign_init_sql );
    dbDelta( $template_sql );
    dbDelta( $metrics_sql );
    dbDelta( $experiments_sql );
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
function build_idwiz_query($args, $table_name) {
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
        $args['id'] == null;
       }
    }
    if (isset($args['ids'])) {
        $args['ids'] = array_filter($args['ids'], function($id) {
            return $id != 0;
        });
    }
    if (isset($args['campaignIds'])) {
        $args['campaignIds'] = array_filter($args['campaignIds'], function($id) {
            return $id != 0;
        });
    }

    // Copy the args array and remove certain keys so they don't get used in the WHERE clause
    $where_args = $args;
    unset($where_args['fields']);
    unset($where_args['limit']);
    unset($where_args['sortBy']);
    unset($where_args['sort']);
    
    foreach ($where_args as $key => $value) {
        if ($value !== null && $value !== '') {
                
                if (($key === 'ids' || $key === 'campaignIds')) {  // Special case for array of campaign IDs
                    $campaignKey = 'id';
                    $placeholders = implode(',', array_fill(0, count($value), '%d'));
    
                    if ($table_name == $wpdb->prefix . 'idemailwiz_purchases' 
                        || $table_name == $wpdb->prefix . 'idemailwiz_templates' 
                        || $table_name == $wpdb->prefix . 'idemailwiz_experiments') {
                        $campaignKey = 'campaignId';
                    }
    
                    // Use call_user_func_array to dynamically pass an array of arguments to $wpdb->prepare
                    $sql .= call_user_func_array(array($wpdb, 'prepare'), array_merge(array(" AND $campaignKey IN ($placeholders)"), $value));
                } elseif ($key === 'startAt_start') {
                try {
                    $value = (new DateTime($value))->getTimestamp() * 1000;
                    $sql .= $wpdb->prepare(" AND startAt >= %d", $value);
                } catch (Exception $e) {
                    return ['error' => $e];
                }
            } elseif ($key === 'startAt_end') {
                try {
                    $value = (new DateTime($value))->getTimestamp() * 1000;
                    $sql .= $wpdb->prepare(" AND startAt <= %d", $value);
                } catch (Exception $e) {
                    return ['error' => $e];
                }
            } elseif ($key === 'serialized' && is_array($value)) {
                foreach ($value as $serialized_column => $serialized_value) {
                    // Check for string or integer to get a proper match
                    $like_value_int = '%i:' . $wpdb->esc_like($serialized_value) . ';%'; 
                    $like_value_str = '%"'.$wpdb->esc_like($serialized_value).'"%'; 
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

    if (isset($args['sortBy'])) {
        $sort = isset($args['sort']) && ($args['sort'] === 'ASC' || $args['sort'] === 'DESC') ? $args['sort'] : 'DESC';
        $sql .= " ORDER BY {$args['sortBy']} $sort";
    }

    if (isset($args['limit'])) {
        $sql .= $wpdb->prepare(" LIMIT %d", (int)$args['limit']);
    }
    return $sql;

    
}



// Does the sql query for the main get_ functions based on the passed parameters
function execute_idwiz_query($sql) {
    global $wpdb;
    $results = $wpdb->get_results($sql, ARRAY_A);

    if ($wpdb->last_error) {
        return false;
    }

    return $results;
}

function get_idwiz_campaigns($args = []) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_campaigns';
    $sql = build_idwiz_query($args, $table_name);
    return execute_idwiz_query($sql);
}

function get_idwiz_templates($args = []) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_templates';
    $sql = build_idwiz_query($args, $table_name);
    return execute_idwiz_query($sql);
}

function get_idwiz_purchases($args = []) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';
    $sql = build_idwiz_query($args, $table_name);
    return execute_idwiz_query($sql);
}

function get_idwiz_metrics($args = []) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_metrics';
    $sql = build_idwiz_query($args, $table_name);
    return execute_idwiz_query($sql);
}

function get_idwiz_experiments($args = []) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_experiments';
    $sql = build_idwiz_query($args, $table_name);
    return execute_idwiz_query($sql);
}

function get_idwiz_campaign($campaignID) {
    $campaigns = get_idwiz_campaigns(['id' => $campaignID]);
    return $campaigns ? $campaigns[0] : false;
}
function get_idwiz_template($templateID) {
    $templates = get_idwiz_templates(['templateId' => $templateID]);
    return $templates ? $templates[0] : false;
}
function get_idwiz_purchase($purchaseID) {
    $purchases = get_idwiz_purchases(['id' => $purchaseID]);
    return $purchases ? $purchases[0] : false;
}
function get_idwiz_metric($campaignID) {
    $metrics = get_idwiz_metrics(['id' => $campaignID]);
    return $metrics ? $metrics[0] : false;
}
function get_idwiz_experiment($templateId) {
    $experiments = get_idwiz_experiments(['templateId' => $templateId]);
    return $experiments ? $experiments[0] : false;
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

function get_idwiz_purchases_by_campaign($campaignId) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    // Query the database for purchases with the given campaign IDs
    $purchases = $wpdb->get_results("SELECT * FROM {$table_name} WHERE campaignId = $campaignId");
    if ($purchases) {
        return $purchases;
    } else {
        return $wpdb->last_error;
    }
}

// Ajax handler for the main campaigns datatable call
function idwiz_get_campaign_table_view() {
    global $wpdb;

    // Bail early without valid nonce
    if (!check_ajax_referer('data-tables', 'security')) return;
    
    // Fetch data from your view
    $results = $wpdb->get_results("SELECT * FROM idwiz_campaign_view", ARRAY_A);
    //wiz_log(print_r($results, true));

    

    foreach ($results as &$row) {
        // Iterate through the results
        // Unserialize specific columns
        $checkSerialized = ['campaign_labels', 'experiment_ids'];  // Add more column names as needed
        foreach ($checkSerialized as $columnName) {
            if (isset($row[$columnName]) && !empty($row[$columnName]) && idwiz_is_serialized($row[$columnName])) {
                $unserializedData = maybe_unserialize($row[$columnName]);
                if (is_array($unserializedData)) {
                    $row[$columnName] = implode(', ', $unserializedData);
                }
            }
        }
        
    }
    

    // Return data in JSON format
    $response = ['data' => $results];
    echo json_encode($response);
    wp_die();

}

add_action('wp_ajax_idwiz_get_campaign_table_view', 'idwiz_get_campaign_table_view');


function idwiz_is_serialized($value) {
    if (!is_string($value)) return false;
    if (trim($value) == "") return false;
    if (preg_match("/^(i|s|a|o|d):(.*);/si", $value)) return true;
    return false;
}


function handle_experiment_winner_toggle() {
    error_log('Made it to handler');

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_experiments';

    // Log POST data for debugging
    error_log('POST data: ' . print_r($_POST, true));

    // Security checks and validation
    if (!check_ajax_referer('wiz-metrics', 'security', false)) {
        error_log('Nonce check failed');
        wp_send_json_error('Nonce check failed');
        return;
    }

    $action = $_POST['actionType'];
    $templateId = intval($_POST['templateId']);
    $experimentId = intval($_POST['experimentId']);

    if (!$templateId || !$experimentId) {
        error_log('Invalid templateId or experimentId');
        wp_send_json_error('Invalid templateId or experimentId');
        return;
    }

    if ($action == 'add-winner') {
        error_log('Action is add-winner');

        // Clear existing winners for the same experimentId
        $result = $wpdb->update(
            $table_name,
            array('wizWinner' => null),
            array('experimentId' => $experimentId)
        );

        if ($result === false) {
            error_log("Database error while clearing winners: " . $wpdb->last_error);
            wp_send_json_error("Database error while clearing winners: " . $wpdb->last_error);
            return;
        }

        // Set new winner
        $result = $wpdb->update(
            $table_name,
            array('wizWinner' => 1),
            array('templateId' => $templateId)
        );

        if ($result === false) {
            error_log("Database error while setting new winner: " . $wpdb->last_error);
            wp_send_json_error("Database error while setting new winner: " . $wpdb->last_error);
            return;
        }

    } elseif ($action == 'remove-winner') {
        error_log('Action is remove-winner');

        // Remove winner
        $result = $wpdb->update(
            $table_name,
            array('wizWinner' => null),
            array('templateId' => $templateId)
        );

        if ($result === false) {
            error_log("Database error while removing winner: " . $wpdb->last_error);
            wp_send_json_error("Database error while removing winner: " . $wpdb->last_error);
            return;
        }

    } else {
        error_log('Invalid action: ' . $action);
        wp_send_json_error('Invalid action');
        return;
    }

    error_log('Action completed successfully');
    wp_send_json_success('Action completed successfully');
}

add_action('wp_ajax_handle_experiment_winner_toggle', 'handle_experiment_winner_toggle');



add_action('wp_ajax_save_experiment_notes', 'save_experiment_notes');

function save_experiment_notes() {
    // Security checks and validation
    if (!check_ajax_referer('wiz-metrics', 'security', false)) {
        error_log('Nonce check failed');
        wp_send_json_error('Nonce check failed');
        return;
    }

    // Get the experiment notes and ID
    $experimentId = isset($_POST['experimentId']) ? sanitize_text_field($_POST['experimentId']) : '';
    
    $allowed_tags = array(
        'br' => array(),
        // Add other tags if you wish to allow them
    );
    $experimentNotes = isset($_POST['experimentNotes']) ? wp_kses($_POST['experimentNotes'], $allowed_tags) : '';
    
    // Database update logic
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_experiments';

    // Update experimentNotes for all records with the same experiment ID
    $result = $wpdb->update(
        $table_name,
        array('experimentNotes' => $experimentNotes),
        array('experimentId' => (int)$experimentId)
    );

    if ($wpdb->last_error) {
        error_log("Database error: " . $wpdb->last_error);
        wp_send_json_error('Database error: ' . $wpdb->last_error);
        return;
    }

    if ($result !== false) {
        if ($result > 0) {
            wp_send_json_success('Data saved successfully');
        } else {
            wp_send_json_error('No data was updated, the new value may be the same as the existing value');
        }
    } else {
        wp_send_json_error('An error occurred while updating the database');
    }
}

























