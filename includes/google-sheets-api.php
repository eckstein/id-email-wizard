<?php

function sync_ga_campaign_revenue_data()
{
    global $wpdb; 

    wiz_log("=== Starting GA campaign revenue data sync ===");

    $ga_campaign_rev_table_name = $wpdb->prefix . 'idemailwiz_ga_campaign_revenue';

    // Initialize counters
    $countUpdates = 0;
    $countInserts = 0;

    try {
        // Fetch GA data from SheetDB 
        $url = get_ga_revenue_sheet_url();
        
        if (empty($url)) {
            wiz_log("GA Revenue Sync Error: No GA revenue sheet URL configured in settings");
            return "Error: No GA revenue sheet URL configured.";
        }
        
        wiz_log("GA Revenue Sync: Using sheet URL: " . $url);
        
        $result = idemailwiz_iterable_curl_call($url, null, false, 3, 5);
        $ga_data = $result['response'];

        if (isset($result['httpCode']) && $result['httpCode'] >= 400) {
            wiz_log("GA Revenue Sync Error: HTTP " . $result['httpCode'] . " - Failed to fetch data from Google Sheets");
            return "Error fetching GA campaign revenue data.";
        }

        if (is_string($ga_data)) {
            wiz_log("GA Revenue Sync Error: Invalid response format - " . $ga_data);
            return $ga_data; 
        }
        
        if (empty($ga_data) || !is_array($ga_data)) {
            wiz_log("GA Revenue Sync Error: No valid data returned from Google Sheets");
            return "No data returned from GA revenue sheet.";
        }
        
        wiz_log("GA Revenue Sync: Retrieved " . count($ga_data) . " rows from Google Sheets");

        $campaignRevenueAccumulator = [];
        $processed_rows = 0;
        $skipped_rows = 0;

        // Loop GA data
        foreach ($ga_data as $row) {
            if (!isset($row['date']) || !isset($row['transactionId'])) {
                $skipped_rows++;
                continue;
            }
            
            $processed_rows++;
            $transactionId = (string) ($row['transactionId']);
            $date = (string) ($row['date']);
            $campaignId = (string) ($row['campaignId']);
            $division = (string) ($row['division']);

            
            // Remove commas from the revenue and purchases values
            $revenue = (float) str_replace(',', '', $row['revenue']) ?? 0;
            $purchases = (float) str_replace(',', '', $row['purchases']) ?? 0;

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
                        'purchases' => $purchases,
                        'date' => date('Y-m-d', strtotime($date)),
                    ],
                    [
                        'transactionId' => $transactionId,
                        'campaignId' => $campaignId,
                        'division' => $division,
                        
                    ]
                );
                $countUpdates++;
            } else {
                // Insert a new record
                $wpdb->insert(
                    $ga_campaign_rev_table_name,
                    [
                        'transactionId' => $transactionId,
                        'date' => date('Y-m-d', strtotime($date)),
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
        
        if ($skipped_rows > 0) {
            wiz_log("GA Revenue Sync: Processed $processed_rows rows, skipped $skipped_rows rows due to missing data");
        } else {
            wiz_log("GA Revenue Sync: Processed $processed_rows rows successfully");
        }

        // Update the metrics database with the accumulated revenue for each campaign
        $metrics_updated = 0;
        foreach ($campaignRevenueAccumulator as $campaignId => $totalRevenue) {
            $wizCampaign = get_idwiz_campaign($campaignId);
            if ($wizCampaign) {
                $wpdb->update(
                    $wpdb->prefix . 'idemailwiz_metrics',
                    ['gaRevenue' => $totalRevenue],
                    ['id' => $campaignId]
                );
                $metrics_updated++;
            }
        }
        
        wiz_log("GA Revenue Sync Complete: $countInserts inserts, $countUpdates updates, $metrics_updated campaign metrics updated");
        return "GA campaign revenue data synced successfully.";
        
    } catch (Exception $e) {
        wiz_log("GA Revenue Sync Error: " . $e->getMessage());
        return "Error during GA revenue sync: " . $e->getMessage();
    }
}





function idwiz_google_sheet_api_curl_call($url)
{
    $bearer_token = get_sync_setting('ga_revenue_api_sheet_bearer_token');

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
    if(curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }

    // Close cURL session
    curl_close($ch);

    return $response;
}

// Function to fetch GA data based on query args
function get_idwiz_ga_data($args = [])
{
    //error_log('get_idwiz_ga_data args: ' . json_encode($args));
    global $wpdb;
    date_default_timezone_set('America/Los_Angeles');
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
    //error_log($query);
    $results = $wpdb->get_results($query, ARRAY_A);
    //error_log(print_r($results, true));

    return $results;
}