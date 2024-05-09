<?php


// Ajax handler for the main campaigns datatable call
function idwiz_get_campaign_table_view() {
    global $wpdb;

    // Bail early without valid nonce
    if (!check_ajax_referer('data-tables', 'security')) return;

    $campaign_type = isset($_POST['campaign_type']) ? $_POST['campaign_type'] : 'Blast'; // Default to 'Blast'

    $sql = "SELECT * FROM idwiz_campaign_view";
    $prepare_args = [];

    if ($campaign_type == 'Triggered') {
		$sql .= " WHERE campaign_type = %s AND campaign_state = 'Running'";
        $prepare_args[] = 'Triggered';
    } elseif ($campaign_type == 'Blast') {
        $sql .= " WHERE campaign_type = %s";
        $prepare_args[] = 'Blast';
    } elseif ($campaign_type == 'Archive') {
		$sql .= " WHERE campaign_type = 'Triggered' AND campaign_state = 'Finished'";
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
    }

    // Return data in JSON format
    $response = ['data' => $results];
    echo json_encode($response);
    wp_die();
}


add_action('wp_ajax_idwiz_get_campaign_table_view', 'idwiz_get_campaign_table_view');