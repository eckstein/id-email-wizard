<?php

function get_sends_by_week_data($startDate, $endDate) {
    global $wpdb;

    // Convert the start and end dates to timestamps
    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);

    // Calculate the start and end weeks based on the timestamps
    $startWeek = date('W', $startTimestamp);
    $endWeek = date('W', $endTimestamp);
    $startYear = date('Y', $startTimestamp);
    $endYear = date('Y', $endTimestamp);

    // Query the sends_by_week table to get the relevant rows
    $sends_by_week_table = $wpdb->prefix . 'idemailwiz_sends_by_week';
    $query = $wpdb->prepare(
        "SELECT sends, userIds
        FROM $sends_by_week_table
        WHERE (year = %d AND week >= %d) OR (year > %d AND year < %d) OR (year = %d AND week <= %d)",
        $startYear,
        $startWeek,
        $startYear,
        $endYear,
        $endYear,
        $endWeek
    );
    $results = $wpdb->get_results($query);

    // Process the results and calculate the total send count for each user
    $userTotalSendCounts = [];

    foreach ($results as $row) {
        $sends = $row->sends;
        $userIds = unserialize($row->userIds);

        foreach ($userIds as $userId) {
            if (!isset($userTotalSendCounts[$userId])) {
                $userTotalSendCounts[$userId] = 0;
            }
            $userTotalSendCounts[$userId] += $sends;
        }
    }

    // Group users by their total send count
    $sendCountGroups = [];
    $totalUsers = count($userTotalSendCounts);

    foreach ($userTotalSendCounts as $userId => $totalSendCount) {
        if ($totalSendCount > 25) {
            continue;
        }
        if (!isset($sendCountGroups[$totalSendCount])) {
            $sendCountGroups[$totalSendCount] = 0;
        }
        $sendCountGroups[$totalSendCount]++;
    }

    // Sort the send count groups in ascending order
    ksort($sendCountGroups);

    return ['sendCountGroups'=>$sendCountGroups, 'totalUsers'=>$totalUsers];
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