<?php


// Ajax handler for the main campaigns datatable call
function idwiz_get_campaign_table_view() {
    global $wpdb;

    // Bail early without valid nonce
    if (!check_ajax_referer('data-tables', 'security')) return;

    $campaign_type = isset($_POST['campaign_type']) ? sanitize_text_field($_POST['campaign_type']) : 'Blast'; // Default to 'Blast'
    $startDate = isset($_POST['startDate']) ? sanitize_text_field($_POST['startDate']) : null;
    $endDate = isset($_POST['endDate']) ? sanitize_text_field($_POST['endDate']) : null;

    $sql = "SELECT * FROM idwiz_campaign_view";
    $prepare_args = [];
    $where_clauses = [];

    if ($campaign_type == 'Triggered') {
		$where_clauses[] = "campaign_type = %s AND campaign_state = 'Running'";
        $prepare_args[] = 'Triggered';
    } elseif ($campaign_type == 'Blast') {
		$where_clauses[] = "campaign_type = %s";
        $prepare_args[] = 'Blast';
    } elseif ($campaign_type == 'Archive') {
		$where_clauses[] = "campaign_type = 'Triggered' AND campaign_state = 'Finished'";
    }

    // Add date filtering if dates are provided
    // Assuming campaign_start is a BIGINT representing milliseconds since epoch (UTC)
    if ($startDate) {
        // Convert YYYY-MM-DD from Los_Angeles timezone to UTC start of day timestamp (ms)
        try {
            $dtStart = new DateTime($startDate . ' 00:00:00', new DateTimeZone('America/Los_Angeles'));
            $startTimestampMs = $dtStart->getTimestamp() * 1000;
            $where_clauses[] = "campaign_start >= %d";
            $prepare_args[] = $startTimestampMs;
        } catch (Exception $e) {
            // Handle invalid date format if necessary
            error_log('Invalid start date format received: ' . $startDate);
        }
    }

    if ($endDate) {
         // Convert YYYY-MM-DD from Los_Angeles timezone to UTC end of day timestamp (ms)
        try {
            $dtEnd = new DateTime($endDate . ' 23:59:59', new DateTimeZone('America/Los_Angeles'));
            $endTimestampMs = $dtEnd->getTimestamp() * 1000;
            $where_clauses[] = "campaign_start <= %d";
            $prepare_args[] = $endTimestampMs;
        } catch (Exception $e) {
             // Handle invalid date format if necessary
            error_log('Invalid end date format received: ' . $endDate);
        }
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    // Prepare and execute the SQL query
    if (!empty($prepare_args)) {
        $sql = $wpdb->prepare($sql, $prepare_args);
    }
    $results = $wpdb->get_results($sql, ARRAY_A);


    foreach ($results as &$row) {
        // Iterate through the results
        if ($row['ga_revenue'] === null || !$row['ga_revenue']) {
            $row['ga_revenue'] = '0';
        }
        // Replace purchases and revenue data to accomodate attribution settings
        $purchases = get_idwiz_purchases(['campaignIds' => [$row['campaign_id']]]);
        $campaignPurchaseCount = count($purchases);
        $campaignRev = array_sum(array_column($purchases, 'total'));
        $row['unique_purchases'] = $campaignPurchaseCount;
        $row['revenue'] = $campaignRev;

        // Unserialize specific columns
        $checkSerialized = ['campaign_labels', 'experiment_ids'];  // Add more column names as needed
        foreach ($checkSerialized as $columnName) {
            if (isset($row[$columnName]) && idwiz_is_serialized($row[$columnName])) {
                $unserializedData = maybe_unserialize($row[$columnName]);
                if (is_array($unserializedData)) {
                    if (empty($unserializedData)) {
                        $row[$columnName] = '';  // Set to an empty string if the array is empty
                    } else {
                        $row[$columnName] = implode(', ', $unserializedData);
                    }
                }
            }
        }
        //Add initiative IDs to the data
        $campaignInits = idemailwiz_get_initiative_ids_for_campaign($row['campaign_id']) ?? [];
        $row['initiative_links'] = '';
        if (!empty($campaignInits)) {
            $initLinks = [];
            foreach ($campaignInits as $initiativeId) {
                $initLinks[] = '<a href="'.get_the_permalink($initiativeId).'">'.get_the_title($initiativeId).'</a>';
            }
            
            $row['initiative_links'] = implode(', ', $initLinks);
        }
        //Add promo code IDs to the data
        $campaignPromos = get_promo_codes_for_campaign($row['campaign_id']) ?? [];
        $row['promo_links'] = '';
        if (!empty($campaignPromos)) {
            $promoLinks = [];
            foreach ($campaignPromos as $promoId) {
                $promoLinks[] = '<a href="'.get_the_permalink($promoId).'">'.get_the_title($promoId).'</a>';
            }
            
            $row['promo_links'] = implode(', ', $promoLinks);
        }

    }

    // Return data in JSON format
    $response = ['data' => $results];
    echo json_encode($response);
    wp_die();
}


add_action('wp_ajax_idwiz_get_campaign_table_view', 'idwiz_get_campaign_table_view');