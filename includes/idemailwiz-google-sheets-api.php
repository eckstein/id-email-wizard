<?php



function sync_ga_campaign_revenue_data()
{
    global $wpdb; 

    wiz_log("Starting GA campaign revenue data sync.");

    $ga_campaign_rev_table_name = $wpdb->prefix . 'idemailwiz_ga_campaign_revenue';

    // Initialize counters
    $countUpdates = 0;
    $countInserts = 0;

    // Fetch GA data from SheetDB 
    $url = "https://sheetdb.io/api/v1/9mvdxfdluq3iv?sheet=Purchases by date with campaign and LOB Clean";
    $response = idwiz_google_sheet_api_curl_call($url);
    $ga_data = json_decode($response, true);

    error_log(print_r($ga_data, true));

    if (is_string($ga_data)) {
        return $ga_data; 
    }

    $campaignRevenueAccumulator = [];

    // Loop GA data
    foreach ($ga_data as $row) {
        if (!isset($row['Date']) || !isset($row['Transaction ID'])) {
            continue;
        }
        $transactionId = (string) ($row['Transaction ID']);
        $date = (string) ($row['Date']);
        $campaignId = (string) ($row['Campaign']);
        $division = (string) ($row['ga:dimension2']);
        $revenue = $row['Product Revenue'] ?? 0;
        $purchases = $row['Unique Purchases'] ?? 0;

        // Check if a record with the same transactionId exists in the table
        $existing_record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $ga_campaign_rev_table_name WHERE transactionId = %s AND campaignId = %s AND division = %s",
                $transactionId,
                $campaignId,
                $division
            )
        );

        if ($existing_record) {
            // Update the existing record
            $wpdb->update(
                $ga_campaign_rev_table_name,
                [   
                    'revenue' => $revenue,
                    'purchases' => $purchases
                ],
                [
                    'transactionId' => $transactionId,
                    'campaignId' => $campaignId,
                    'division' => $division,
                    'date' => $date,
                ]
            );
            $countUpdates++;
        } else {
            // Insert a new record
            $wpdb->insert(
                $ga_campaign_rev_table_name,
                [
                    'transactionId' => $transactionId,
                    'date' => $date,
                    'campaignId' => $campaignId,
                    'division' => $division,
                    'revenue' => $revenue,
                    'purchases' => $purchases
                ]
            );
            $countInserts++;
        }

        // Accumulate the revenue for this campaign
        if (isset($campaignRevenueAccumulator[$campaignId])) {
            $campaignRevenueAccumulator[$campaignId] += $revenue;
        } else {
            $campaignRevenueAccumulator[$campaignId] = $revenue;
        }

    }

    // Update the metrics database with the accumulated revenue for each campaign
    foreach ($campaignRevenueAccumulator as $campaignId => $totalRevenue) {
        $wizCampaign = get_idwiz_campaign($campaignId);
        if ($wizCampaign) {
            $wpdb->update(
                $wpdb->prefix . 'idemailwiz_metrics',
                ['gaRevenue' => $totalRevenue],
                ['id' => $campaignId]
            );
        }
    }

    wiz_log("GA campaign revenue data synced successfully. $countUpdates updates and $countInserts inserts performed");
    return "GA campaign revenue data synced successfully.";
}





function idwiz_google_sheet_api_curl_call($url)
{
    $bearer_token = get_field('ga_revenue_api_sheet_bearer_token', 'options');

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            "Authorization: Bearer $bearer_token"
        )
    );

    // Execute cURL session and get the response
    $response = curl_exec($ch);

    // Close cURL session
    curl_close($ch);

    return $response;
}

// Function to fetch GA data based on query args
function get_idwiz_ga_data($args = [])
{
    global $wpdb;
    $ga_campaign_rev_table_name = $wpdb->prefix . 'idemailwiz_ga_campaign_revenue';

    // Initialize the WHERE clause
    $where_clauses = [];

    // Filter by divisions
    if (isset($args['transactionIds'])) {
        $where_clauses[] = 'transactionId IN (' . implode(', ', array_map([$wpdb, 'prepare'], array_fill(0, count($args['transactionIds']), '%s'), $args['transactionIds'])) . ')';
    }

    // Filter by divisions
    if (isset($args['divisions'])) {
        $where_clauses[] = 'division IN (' . implode(', ', array_map([$wpdb, 'prepare'], array_fill(0, count($args['divisions']), '%s'), $args['divisions'])) . ')';
    }

    // Filter by campaignIds
    if (isset($args['campaignIds'])) {
        $where_clauses[] = 'campaignId IN (' . implode(', ', array_map([$wpdb, 'prepare'], array_fill(0, count($args['campaignIds']), '%s'), $args['campaignIds'])) . ')';
    }

    // Filter by date range
    if (isset($args['startDate']) && isset($args['endDate'])) {
        // Normalize date strings to 'Y-m-d' format
        $start_date = new DateTime($args['startDate']);
        $end_date = new DateTime($args['endDate']);

        if ($start_date && $end_date) {
            $formatted_start_date = $start_date->format('Y-m-d');
            $formatted_end_date = $end_date->format('Y-m-d');
            $where_clauses[] = $wpdb->prepare('date BETWEEN %s AND %s', $formatted_start_date, $formatted_end_date);
        }
    }


    // Build the WHERE clause
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }

    // Execute the query
    $query = "SELECT * FROM $ga_campaign_rev_table_name $where_sql";
    $results = $wpdb->get_results($query, ARRAY_A);

    return $results;
}