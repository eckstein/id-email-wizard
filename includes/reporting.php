<?php

function get_sends_by_week_data($startDate, $endDate, $batchSize = 1000, $return = 'all')
{
    global $wpdb;

    // Convert the start and end dates to timestamps
    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);

    // Initialize variables
    $sendCountGroups = [];
    $totalUsers = 0;
    $userTotalSends = [];
    $userMaxSends = [];

    // Get year and week parts for start and end dates
    $startYear = date('Y', $startTimestamp);
    $startWeek = date('W', $startTimestamp);
    $endYear = date('Y', $endTimestamp);
    $endWeek = date('W', $endTimestamp);

    $offset = 0;
    $hasMoreRecords = true;

    while ($hasMoreRecords) {
        // Adjust the SQL query to handle date ranges spanning multiple years
        $query = $wpdb->prepare(
            "SELECT sends, userIds, year, week 
             FROM {$wpdb->prefix}idemailwiz_sends_by_week 
             WHERE (year = %d AND week >= %d) 
                OR (year > %d AND year < %d)
                OR (year = %d AND week <= %d)
             ORDER BY year, week
             LIMIT %d, %d",
            $startYear,
            $startWeek,
            $startYear,
            $endYear,
            $endYear,
            $endWeek,
            $offset,
            $batchSize
        );

        $results = $wpdb->get_results($query);

        if (empty($results)) {
            $hasMoreRecords = false;
            break;
        }

        foreach ($results as $row) {
            $rowTimestamp = strtotime("{$row->year}-W" . str_pad($row->week, 2, '0', STR_PAD_LEFT));

            // Skip rows outside the date range
            if ($rowTimestamp < $startTimestamp || $rowTimestamp > $endTimestamp) {
                continue;
            }

            $sends = $row->sends;
            $userIds = unserialize($row->userIds);

            foreach ($userIds as $userId) {
                $totalUsers++;
                // Increment the send count group directly
                $sendCountGroups[$sends]['count']++;

                // Store user total sends for monthly data calculation
                if (!isset($userTotalSends[$userId])) {
                    $userTotalSends[$userId] = 0;
                }
                $userTotalSends[$userId] += $sends;

                if (!isset($userMaxSends[$userId])) {
                    $userMaxSends[$userId] = 0;
                }
                $userMaxSends[$userId] ++;
            }
        }

        $offset += $batchSize;
    }

    // Prepare the return data based on the requested timeframe
    if ($return === 'all') {
        // Calculate the overall send count groups based on user total sends
        $allSendCountGroups = [];
        foreach ($userMaxSends as $totalSends) {
            $allSendCountGroups[$totalSends]++;
        }

        return ['allData' => $allSendCountGroups, 'totalUsers' => count($userMaxSends)];
    } elseif ($return === 'weekly') {
        $weeklyData = [];
        foreach ($sendCountGroups as $sendCount => $data) {
            if ($data['count'] > 0) {
                $weeklyData[$sendCount] = $data['count'];
            }
        }
        return ['weeklyData' => $weeklyData, 'totalUsers' => $totalUsers];
    } elseif ($return === 'monthly') {
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
