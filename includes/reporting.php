<?php

function get_sends_by_week_data($startDate, $endDate, $batchSize = 1000, $offset = 0, $return = 'all')
{
    global $wpdb;

    // Convert the start and end dates to timestamps
    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);

    // Initialize variables
    $sendCountGroups = array_fill(1, 25, ['count' => 0, 'userIds' => []]);
    $totalUsers = 0;

    // Query the sends_by_week table to get a batch of rows
    $sends_by_week_table = $wpdb->prefix . 'idemailwiz_sends_by_week';
    $query = $wpdb->prepare(
        "SELECT sends, userIds, year, month, week FROM $sends_by_week_table WHERE (year >= %d AND month >= %d) AND (year <= %d AND month <= %d) LIMIT %d, %d",
        date('Y', $startTimestamp),
        date('m', $startTimestamp),
        date('Y', $endTimestamp),
        date('m', $endTimestamp),
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
            // Increment the send count group directly and store user IDs
            $sendCountGroups[$sends]['count']++;
            $sendCountGroups[$sends]['userIds'][] = $userId;
        }
    }

    // Prepare the return data based on the requested timeframe
    if ($return === 'all') {
        return ['sendCountGroups' => $sendCountGroups, 'totalUsers' => $totalUsers];
    } elseif ($return === 'weekly') {
        $weeklyData = [];
        foreach ($sendCountGroups as $sendCount => $data) {
            if ($data['count'] > 0) {
                $weeklyData[$sendCount] = $data['count'];
            }
        }
        return ['weeklyData' => $weeklyData, 'totalUsers' => $totalUsers];
    } elseif ($return === 'monthly') {
        // Aggregate sends per user across all weeks within the month
        $userTotalSends = [];
        foreach ($sendCountGroups as $sendCount => $data) {
            foreach ($data['userIds'] as $userId) {
                if (!isset($userTotalSends[$userId])) {
                    $userTotalSends[$userId] = 0;
                }
                $userTotalSends[$userId] += $sendCount;
            }
        }

        // Calculate the overall send count groups based on user total sends
        $monthlySendCountGroups = array_fill(1, 25, 0);
        foreach ($userTotalSends as $totalSends) {
            if ($totalSends <= 25) {
                $monthlySendCountGroups[$totalSends]++;
            }
        }
        $monthlyTotalUsers = count($userTotalSends);

        return ['monthlyData' => $monthlySendCountGroups, 'totalUsers' => $monthlyTotalUsers];
    }
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