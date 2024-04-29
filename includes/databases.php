<?php


function idemailwiz_create_databases()
{
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$engagement_table_names = [
		'idemailwiz_blast_sends',
		'idemailwiz_blast_opens',
		'idemailwiz_blast_clicks',
		'idemailwiz_blast_unsubscribes',
		'idemailwiz_blast_sendskips',
		'idemailwiz_blast_complaints',
		'idemailwiz_blast_bounces',
		'idemailwiz_triggered_sends',
		'idemailwiz_triggered_opens',
		'idemailwiz_triggered_clicks',
		'idemailwiz_triggered_unsubscribes',
		'idemailwiz_triggered_sendskips',
		'idemailwiz_triggered_complaints',
		'idemailwiz_triggered_bounces'
	];

	$engagementTablesSql = [];
	foreach ($engagement_table_names as $table_name) {
		$full_table_name = $wpdb->prefix . $table_name;

		// Create table if not exists
		$sql = "CREATE TABLE IF NOT EXISTS {$full_table_name} (
		  messageId varchar(32) NOT NULL,
		  userId varchar(255) DEFAULT NULL,
		  campaignId int DEFAULT NULL,
		  templateId int DEFAULT NULL,
		  startAt bigint NOT NULL,
		  PRIMARY KEY (messageId, startAt),
		  KEY campaignId (campaignId),
		  KEY startAt (startAt),
		  KEY messageId (messageId)
		) ENGINE=InnoDB $charset_collate;";

		$engagementTablesSql[$full_table_name] = $sql;
		
	}
	
	
	$wizTablesSql = [];

	// Define Campaigns table
	$campaign_table_name = $wpdb->prefix . 'idemailwiz_campaigns';
	$wizTablesSql[$campaign_table_name] = "CREATE TABLE IF NOT EXISTS $campaign_table_name (
		id INT,
		createdAt BIGINT,
		updatedAt BIGINT,
		startAt BIGINT,
		wizSentAt BIGINT,
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
		initiativeIds VARCHAR(255),
		initiativeLinks VARCHAR(255),
		connectedCampaigns VARCHAR(255),
		last_wiz_update DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) ENGINE=InnoDB $charset_collate;";


	$workflows_table_name = $wpdb->prefix . 'idemailwiz_workflows';
	$wizTablesSql[$workflows_table_name] = "CREATE TABLE IF NOT EXISTS $workflows_table_name (
		workflowId INT,
		workflowName VARCHAR(255) NOT NULL,
		firstSendAt BIGINT,
		lastSendAt BIGINT,
		PRIMARY KEY  (workflowId),
		INDEX workflowId (workflowId)
	) ENGINE=InnoDB $charset_collate;";


	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';
	$wizTablesSql[$sync_jobs_table_name] = "CREATE TABLE IF NOT EXISTS $sync_jobs_table_name (
		
		jobId INT,
		deleteAfter DATETIME,
		jobState VARCHAR(50),
		retryAfter DATETIME,
		startAfter VARCHAR(255),
		campaignId INT,
		syncType VARCHAR(50),
		syncStatus VARCHAR(50),
		syncPriority INT DEFAULT 1,
		PRIMARY KEY (jobId),
		INDEX jobId (jobId),
		INDEX campaignId (campaignId),
		INDEX syncType (syncType)
	) ENGINE=InnoDB $charset_collate;";

	$ga_campaign_rev_table_name = $wpdb->prefix . 'idemailwiz_ga_campaign_revenue';
	$wizTablesSql[$ga_campaign_rev_table_name] = "CREATE TABLE IF NOT EXISTS $ga_campaign_rev_table_name (
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
    ) ENGINE=InnoDB $charset_collate;";


	$users_table_name = $wpdb->prefix . 'idemailwiz_users';
	$wizTablesSql[$users_table_name] = "CREATE TABLE IF NOT EXISTS $users_table_name (
        wizId VARCHAR(100),
        accountNumber VARCHAR(20),
        userId VARCHAR(40),
        signupDate VARCHAR(255),
        postalCode VARCHAR(20),
        timeZone VARCHAR(255),
        studentArray LONGTEXT,
        unsubscribedChannelIds MEDIUMTEXT,
        subscribedMessageTypeIds MEDIUMTEXT,
        unsubscribedMessageTypeIds MEDIUMTEXT,
        campaignSends LONGTEXT,
        campaignClicks LONGTEXT,
        campaignOpens LONGTEXT,
        wizSalt VARCHAR(255),
        INDEX wizId (wizId)
    ) ENGINE=InnoDB $charset_collate;";


	$wizTemplateTableName = $wpdb->prefix . 'wiz_templates';
	$wizTablesSql[$wizTemplateTableName] = "CREATE TABLE IF NOT EXISTS $wizTemplateTableName (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		post_id BIGINT UNSIGNED NOT NULL,
		user_id BIGINT UNSIGNED NOT NULL,
		template_data LONGTEXT NOT NULL,
		template_html LONGTEXT NOT NULL,
		PRIMARY KEY (id),
		FOREIGN KEY (post_id) REFERENCES {$wpdb->prefix}posts(ID) ON DELETE CASCADE,
		FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID)
	) ENGINE=InnoDB $charset_collate;";



	// Define Templates table
	$template_table_name = $wpdb->prefix . 'idemailwiz_templates';
	$wizTablesSql[$template_table_name] = "CREATE TABLE IF NOT EXISTS $template_table_name (
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
        heatmapFile VARCHAR(255),
        templateImage VARCHAR(255),
        PRIMARY KEY  (templateId)
    ) ENGINE=InnoDB $charset_collate;";

	// Define Metrics table
	$metrics_table_name = $wpdb->prefix . 'idemailwiz_metrics';
	$wizTablesSql[$metrics_table_name] = "CREATE TABLE IF NOT EXISTS $metrics_table_name (
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
    ) ENGINE=InnoDB $charset_collate;";

	// Define Experiments table
	$experiments_table_name = $wpdb->prefix . 'idemailwiz_experiments';
	$wizTablesSql[$experiments_table_name] = "CREATE TABLE IF NOT EXISTS $experiments_table_name (
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
    ) ENGINE=InnoDB $charset_collate;";

	// Define Purchases table
	$purchases_table_name = $wpdb->prefix . 'idemailwiz_purchases';
	$wizTablesSql[$purchases_table_name] = "CREATE TABLE IF NOT EXISTS $purchases_table_name (
        accountNumber VARCHAR(20),
        orderId VARCHAR(10),
        userId VARCHAR(20),
        id VARCHAR(40),
        campaignId INT,
		campaignStartAt BIGINT,
        createdAt VARCHAR(26),
        purchaseDate VARCHAR(26),
        shoppingCartItems TEXT,
        shoppingCartItems_discountAmount FLOAT,
        shoppingCartItems_discountCode VARCHAR(255),
        shoppingCartItems_discounts TEXT,
        shoppingCartItems_divisionId INT,
        shoppingCartItems_divisionName VARCHAR(255),
        shoppingCartItems_isSubscription VARCHAR(255),
        shoppingCartItems_locationName VARCHAR(255),
        shoppingCartItems_productCategory VARCHAR(255),
        shoppingCartItems_productSubcategory VARCHAR(255),
        shoppingCartItems_studentAccountNumber VARCHAR(20),
        shoppingCartItems_studentDob VARCHAR(26),
        shoppingCartItems_studentGender VARCHAR(10),
        shoppingCartItems_utmCampaign VARCHAR(255),
        shoppingCartItems_utmContents VARCHAR(255),
        shoppingCartItems_utmMedium VARCHAR(255),
        shoppingCartItems_utmSource VARCHAR(255),
        shoppingCartItems_utmTerm VARCHAR(255),
        shoppingCartItems_categories TEXT,
        shoppingCartItems_imageUrl VARCHAR(255),
        shoppingCartItems_name VARCHAR(255),
        shoppingCartItems_price FLOAT,
        shoppingCartItems_quantity INT,
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
    ) ENGINE=InnoDB $charset_collate;";

	$wiz_log_table_name = $wpdb->prefix . 'idemailwiz_wiz_log';
	$wizTablesSql[$wiz_log_table_name] = "CREATE TABLE IF NOT EXISTS $wiz_log_table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        timestamp DECIMAL(18, 6),
        message VARCHAR(255),
        PRIMARY KEY  (id)
    ) ENGINE=InnoDB $charset_collate;";



	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
	// Campaign metrics tables that all share the same columns
	foreach ($engagementTablesSql as $full_table_name => $engagementTableSql) {
		dbDelta($engagementTableSql);
	}

	foreach ($wizTablesSql as $full_table_name => $wizTableSql) {
		dbDelta($wizTableSql);
	}
	
	//Create our custom view for the campaigns datatable
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
            campaigns.campaignState as campaign_state,
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



/**
 * Builds a SQL query for retrieving data from the idwiz database tables
 * 
 * @param array $args - Associative array of query parameters like filters, sorting etc. 
 * @param string $table_name - The name of the database table to query
 */
function build_idwiz_query($args, $table_name)
{
	global $wpdb;

	date_default_timezone_set('America/Los_Angeles');

	$fields = isset($args['fields']) ? $args['fields'] : '*';
	if (is_array($fields)) {
		$fields = implode(',', $fields);
	}

	$sql = "SELECT $fields FROM $table_name WHERE 1=1";


	// Filter out zero values from 'campaignIds' if they are present
	// Prevents huge data calls in the purchases database
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
	unset($where_args['not-campaigns']);
	unset($where_args['purchaseId']);



	// Setup special variable cases 
	$dateKey = 'startAt';

	if ($table_name == $wpdb->prefix . 'idemailwiz_purchases') {
		$dateKey = 'purchaseDate';

		//exclude terciary purchases (lunches, add-ons, etc)
		$sql .= $wpdb->prepare(" AND shoppingCartItems_productCategory != %s", '17004');
		$sql .= $wpdb->prepare(" AND shoppingCartItems_productCategory != %s", '17003');
		$sql .= $wpdb->prepare(" AND shoppingCartItems_productCategory != %s", '17001');

		// Set attribution mode
		$currentUser = wp_get_current_user();
		$currentUserId = $currentUser->ID;
		$userAttMode = get_user_meta($currentUserId, 'purchase_attribution_mode', true);
		$userAttLength = get_user_meta($currentUserId, 'purchase_attribution_length', true);

		if ($userAttLength && $userAttLength != 'allTime') {
			$interval = '';
			switch ($userAttLength) {
				case '72Hours':
					$interval = 'INTERVAL 72 HOUR';
					break;
				case '30Days':
					$interval = 'INTERVAL 30 DAY';
					break;
				case '60Days':
					$interval = 'INTERVAL 60 DAY';
					break;
				case '90Days':
					$interval = 'INTERVAL 90 DAY';
					break;
			}

			if ($interval) {
				// Convert campaignStartAt from milliseconds to a date
				$sql .= " AND purchaseDate <= DATE_ADD(DATE(FROM_UNIXTIME(campaignStartAt / 1000)), $interval)";
				$sql .= " AND purchaseDate >= DATE(FROM_UNIXTIME(campaignStartAt / 1000))"; // Ensure the purchase is after the campaign started
			}
		}



		// default mode is campaign-id, which gets no extra parameters here
		if ($userAttMode == 'broad-channel-match') {
			$placeholders = implode(', ', array_fill(0, count(['email', '']), '%s'));
			$sql .= $wpdb->prepare(" AND shoppingCartItems_utmMedium IN ($placeholders)", ['email', '']);
		} elseif ($userAttMode == 'email-channel-match') {
			$sql .= $wpdb->prepare(" AND shoppingCartItems_utmMedium = %s", 'email');
		}
	}

	// CampaignIds are under "id" for campaigns and metrics and under "campaignId" for everything else
	if ($table_name == $wpdb->prefix . 'idemailwiz_campaigns' || $table_name == $wpdb->prefix . 'idemailwiz_metrics') {
		$campaignKey = 'id';
	} else {
		$campaignKey = 'campaignId';
	}

	foreach ($where_args as $key => $value) {
		if ($value !== null && $value !== '') {



			if ($key === 'purchaseId') {
				$sql .= $wpdb->prepare(" AND id = %d", $value);
			}

			if ($key === 'campaignId') {
				$sql .= $wpdb->prepare(" AND $campaignKey = %d", $value);
			}

			if ($key === 'campaignIds') {
				// Direct placeholder generation
				$placeholders = rtrim(str_repeat('%d,', count($value)), ',');

				// Prepare statement with placeholders
				$sql .= $wpdb->prepare(" AND $campaignKey IN ($placeholders)", $value);
			} elseif ($key === 'purchaseIds') {
				$placeholders = implode(',', array_fill(0, count($value), '%s'));
				$sql .= call_user_func_array(array($wpdb, 'prepare'), array_merge(array(" AND id IN ($placeholders)"), $args['purchaseIds']));
			} elseif ($key === 'startAt_start') {
				$dt = DateTime::createFromFormat('Y-m-d', $value, new DateTimeZone('America/Los_Angeles'));
				if ($dt) {
					if ($dateKey === 'purchaseDate') {
						// If it's a purchase date, format as 'Y-m-d'
						$formattedValue = $dt->format('Y-m-d');
					} else {
						// For other dates, adjust the format as needed
						$dt->setTime(0, 0, 0); // Set the time to the beginning of the day
						$dt->setTimezone(new DateTimeZone('UTC')); // Convert to UTC
						$formattedValue = $dt->getTimestamp() * 1000; // For example, if they're stored as timestamps
					}
					$sql .= $wpdb->prepare(" AND $dateKey >= %s", $formattedValue);
				} else {
					return ['error' => 'Invalid date format for startAt_start'];
				}
			} elseif ($key === 'startAt_end') {
				$dt = DateTime::createFromFormat('Y-m-d', $value, new DateTimeZone('America/Los_Angeles'));
				if ($dt) {
					if ($dateKey === 'purchaseDate') {
						// If it's a purchase date, format as 'Y-m-d'
						//$dt->setTime( 23, 59, 59 ); // Set time to the end of the day 
						$formattedValue = $dt->format('Y-m-d');
					} else {
						// For other dates, adjust the format
						//$dt->setTime( 23, 59, 59 ); // Set the time to the end of the day
						$dt->setTimezone(new DateTimeZone('UTC')); // Convert to UTC
						$adjustedTimestamp = $dt->getTimestamp() + (7 * 60 * 60); // Add 7 hours offset
						$formattedValue = $adjustedTimestamp * 1000; // Adjusted for timestamps in milliseconds
					}
					$sql .= $wpdb->prepare(" AND $dateKey <= %s", $formattedValue);
				} else {
					return ['error' => 'Invalid date format for startAt_end'];
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
					$prepared_values = array_map(function ($item) use ($wpdb, $key) {
						return $wpdb->prepare("%s", $item);
					}, $value);
					$sql .= " AND $key IN (" . implode(',', $prepared_values) . ")";
				} else {
					$sql .= $wpdb->prepare(" AND $key = %s", $value);
				}
			}
		}
	}

	if (isset($args['not-ids']) && is_array($args['not-ids'])) {
		$placeholders = implode(',', array_fill(0, count($args['not-ids']), '%d'));
		$sql .= call_user_func_array(array($wpdb, 'prepare'), array_merge(array(" AND id NOT IN ($placeholders)"), $args['not-ids']));
	}

	if (isset($args['not-campaigns']) && is_array($args['not-campaigns'])) {
		$placeholders = implode(',', array_fill(0, count($args['not-campaigns']), '%d'));
		$sql .= call_user_func_array(array($wpdb, 'prepare'), array_merge(array(" AND $campaignKey NOT IN ($placeholders)"), $args['nnot-campaigns']));
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
function execute_idwiz_query($sql, $args = [], $batch_size = 20000)
{
	global $wpdb;
	$results = [];

	// Initialize offset and limit from $args if present, otherwise use defaults
	$limit = isset($args['limit']) ? (int) $args['limit'] : $batch_size;
	$offset = isset($args['offset']) ? (int) $args['offset'] : 0;

	// Adjust initial query only if 'limit' and 'offset' are not provided in $args
	if (!isset($args['limit']) && !isset($args['offset'])) {
		$sql .= " LIMIT $offset, $limit"; // Default pagination logic
	}

	do {
		$current_batch = $wpdb->get_results($sql, ARRAY_A);

		if ($wpdb->last_error) {
			error_log($wpdb->last_error);
			return false;
		}

		if (!empty($current_batch)) {
			$results = array_merge($results, $current_batch); // Merge batch results into the main results array
			$offset += $limit; // Prepare offset for the next batch

			// Modify the SQL for the next batch if we're in control of pagination
			if (!isset($args['limit']) && !isset($args['offset'])) {
				// Recalculate LIMIT clause for the next batch
				if (strpos($sql, 'LIMIT') !== false) {
					$sql = preg_replace('/LIMIT\s+\d+\s*,\s*\d+$/i', "LIMIT $offset, $limit", $sql);
				} else {
					$sql .= " LIMIT $offset, $limit";
				}
			}
		}
	} while (!empty($current_batch) && count($current_batch) == $limit && !isset($args['limit']) && !isset($args['offset']));

	return $results;
}




function get_idwiz_campaigns($args = [])
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_campaigns';
	$sql = build_idwiz_query($args, $table_name);
	return execute_idwiz_query($sql, $args);
}

function get_idwiz_templates($args = [])
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_templates';
	$sql = build_idwiz_query($args, $table_name);
	return execute_idwiz_query($sql, $args);
}

function get_idwiz_purchases($args = [])
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_purchases';
	$sql = build_idwiz_query($args, $table_name);
	return execute_idwiz_query($sql, $args);
}

function get_idwiz_metrics($args = [])
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_metrics';
	$sql = build_idwiz_query($args, $table_name);
	return execute_idwiz_query($sql, $args);
}

function get_idwiz_experiments($args = [])
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_experiments';
	$sql = build_idwiz_query($args, $table_name);
	return execute_idwiz_query($sql, $args);
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
	$purchases = get_idwiz_purchases(['purchaseId' => $purchaseID]);
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


function get_idemailwiz_triggered_data($database, $args = [], $batchSize = 20000, $offset = 0)
{

	if (!$database) {
		return false;
	}


	// Don't allow empty arguments array to avoid massive database calls
	if (!is_array($args) || empty($args)) {
		error_log('get_idemailwiz_triggered_data: WizError: Function called without arguments or with an empty array. Arguments are required to run this function to avoid memory-leaking database calls.');
		return false;
	}

	// Check if provided arguments have values
	foreach ($args as $key => $value) {
		if (isset($args[$key]) && empty($args[$key]) && $args[$key] !== 0) {
			error_log('get_idemailwiz_triggered_data: WizError: Query argument provided without a value - ' . $key);
			return false;
		}
	}

	global $wpdb;

	// Initialize query components
	$where_clauses = [];
	$query_params = [];

	// Determine which fields (columns) to select
	$fields = '*'; // Default is to select all columns
	if (isset($args['fields']) && !empty($args['fields'])) {
		if (is_array($args['fields'])) {
			// If fields are provided as an array, convert to a comma-separated string
			$fields = implode(', ', array_map('sanitize_text_field', $args['fields']));
		} else if (is_string($args['fields'])) {
			// If fields are provided as a string, use directly after sanitization
			$fields = sanitize_text_field($args['fields']);
		}
	}

	// Check if messageIds are provided
	if (isset($args['messageIds']) && is_array($args['messageIds']) && !empty($args['messageIds'])) {
		$placeholders = array_fill(0, count($args['messageIds']), '%s');
		$where_clauses[] = "messageId IN (" . implode(", ", $placeholders) . ")";
		$query_params = array_merge($query_params, $args['messageIds']);
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


	// Initialize results array
	$allResults = [];

	// Iterate through the data in batches
	do {
		// Construct the SQL query with limit and offset
		$sql = "SELECT $fields FROM " . $wpdb->prefix . $database;
		if (!empty($where_clauses)) {
			$sql .= " WHERE " . implode(" AND ", $where_clauses);
		}
		$sql .= " LIMIT %d OFFSET %d";


		// Add batch size and offset to query parameters
		$prepared_sql = $wpdb->prepare($sql, array_merge($query_params, [$batchSize, $offset]));
		//error_log( $prepared_sql );
		// Execute the query and fetch results
		$results = $wpdb->get_results($prepared_sql, ARRAY_A);

		// Debug: Print SQL error (if any)
		if ($wpdb->last_error) {
			echo "SQL Error: " . $wpdb->last_error . "<br>";
			break;
		}

		// Append each result directly to $allResults
		foreach ($results as $result) {
			$allResults[] = $result;
		}

		// Update offset
		$offset += $batchSize;
	} while (count($results) == $batchSize);

	return $allResults;
}



function get_engagement_data_by_campaign_id($campaignIds, $campaignType, $metricType)
{
	global $wpdb;
	$campaignType = strtolower($campaignType);
	$allowedCampaignTypes = ['blast', 'triggered'];
	if (!in_array($campaignType, $allowedCampaignTypes)) {
		error_log('Invalid campaignType passed to get_engagement_data_by_campaign_id: ' . $campaignType);
		return;
	}

	// Check if $campaignId is an array
	if (is_array($campaignIds)) {
		// Sanitize each element in the array to prevent SQL injection
		$safe_campaignIds = array_map('intval', $campaignIds);
		// Create a comma-separated string of the sanitized IDs for use in the SQL query
		$campaignId_list = implode(',', $safe_campaignIds);
		// SQL query to fetch rows from wp_idemailwiz_triggered_sends that match any of the campaignIds
		$sql = "SELECT * FROM " . $wpdb->prefix . "idemailwiz_{$campaignType}_{$metricType}s WHERE campaignId IN ($campaignId_list)";
	} else {
		// Sanitize the single campaignId to prevent SQL injection
		$safe_campaignId = (int) $campaignIds;
		// SQL query to fetch rows from wp_idemailwiz_triggered_sends that match the single campaignId
		$sql = $wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "idemailwiz_{$campaignType}_{$metricType}s WHERE campaignId = %d", $safe_campaignId);
	}

	// Execute the SQL query and fetch the results
	$results = $wpdb->get_results($sql, ARRAY_A);

	// Return the results
	return $results;
}
