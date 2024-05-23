<?php

function get_sends_by_week_data($startDate, $endDate, $batchSize = 50) {
    global $wpdb;

    // Convert the start and end dates to timestamps
    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);

    // Query the sends_by_week table to get the total number of rows
    $sends_by_week_table = $wpdb->prefix . 'idemailwiz_sends_by_week';
    $totalRows = $wpdb->get_var("SELECT COUNT(*) FROM $sends_by_week_table");

    // Initialize variables
    $sendCountGroups = array_fill(1, 25, 0);
    $totalUsers = 0;

    // Process the data in batches
    $offset = 0;
    while ($offset < $totalRows) {
        // Query the sends_by_week table to get a batch of rows
        $query = $wpdb->prepare(
            "SELECT sends, userIds, year, week FROM $sends_by_week_table LIMIT %d, %d",
            $offset,
            $batchSize
        );
        $results = $wpdb->get_results($query);

        foreach ($results as $row) {
            $rowTimestamp = strtotime($row->year . 'W' . str_pad($row->week, 2, '0', STR_PAD_LEFT));

            // Skip rows outside the date range
            if ($rowTimestamp < $startTimestamp || $rowTimestamp > $endTimestamp) {
                continue;
            }

            $sends = $row->sends;
            $userIds = unserialize($row->userIds);

            foreach ($userIds as $userId) {
                $totalUsers++;

                // Increment the send count group directly
                if ($sends <= 25) {
                    $sendCountGroups[$sends]++;
                }
            }
        }

        // Move to the next batch
        $offset += $batchSize;
    }

    // Remove empty send count groups
    $sendCountGroups = array_filter($sendCountGroups);

    return ['sendCountGroups' => $sendCountGroups, 'totalUsers' => $totalUsers];
}

function sortCampaignsIntoCohorts($campaigns, $mode = 'combine')
{
    $cohorts = array();
    $totalCampaigns = 0;
    foreach ($campaigns as $campaign) {
        //print_r($campaign);
        // Unserialize the labels array
        if (!isset($campaign['labels']) || !$campaign['labels']) {
            continue;
        }
        $totalCampaigns++;
        $labels = unserialize($campaign['labels']);
        //print_r($labels);

        if ($mode === 'combine') {
            // Sort the labels to ensure consistent concatenation order
            sort($labels);

            // Concatenate the labels into a unique cohort key
            $cohortKey = implode(', ', $labels);

            // Add the campaign to the corresponding cohort
            if (!isset($cohorts[$cohortKey])) {
                $cohorts[$cohortKey] = array();
            }
            $cohorts[$cohortKey][] = $campaign;
        } elseif ($mode === 'separate') {
            // Add the campaign to each label bucket separately
            foreach ($labels as $label) {
                if (!isset($cohorts[$label])) {
                    $cohorts[$label] = array();
                }
                $cohorts[$label][] = $campaign;
            }
        }
    }

    return ['cohorts' => $cohorts, 'totalCampaigns' => $totalCampaigns];
}