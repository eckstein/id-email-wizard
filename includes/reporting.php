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


            }
        }


        $offset += $batchSize;
    }

    // Prepare the return data based on the requested timeframe
    if ($return === 'all') {
        // Calculate the overall send count groups based on user total sends
        $allSendCountGroups = [];
        foreach ($userTotalSends as $totalSends) {
            $allSendCountGroups[$totalSends]++;
        }

        return ['allData' => $allSendCountGroups, 'totalUsers' => count($userTotalSends)];
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


function get_users_within_date_range($startDate, $endDate, $batchSize = 10000)
{
    global $wpdb;
    $usersTable = $wpdb->prefix . 'idemailwiz_users';
    $offset = 0;
    $users = [];
    $userIds = [];

    do {
        $userBatch = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT userId, signupDate FROM $usersTable 
                 WHERE userId IS NOT NULL AND userId <> '' AND signupDate > %s AND signupDate < %s 
                 LIMIT %d, %d",
                $startDate,
                $endDate,
                $offset,
                $batchSize
            ),
            ARRAY_A
        );

        foreach ($userBatch as $user) {
            $users[$user['userId']] = $user['signupDate'];
            $userIds[] = $user['userId'];
        }

        $offset += $batchSize;
    } while (count($userBatch) === $batchSize);

    return [$users, $userIds];
}

function get_user_purchases($userIds)
{
    global $wpdb;
    $purchasesTable = $wpdb->prefix . 'idemailwiz_purchases';

    return $wpdb->get_results(
        "SELECT userId, MIN(purchaseDate) AS firstPurchaseDate FROM $purchasesTable 
         WHERE userId IN ('" . implode("','", $userIds) . "') 
         AND campaignId IS NOT NULL AND campaignId <> ''
         GROUP BY userId",
        ARRAY_A
    );
}

function calculate_length_to_purchase_data($users, $userPurchases)
{
    $lengthToPurchaseData = [
        'Day 0' => ['min' => 0, 'max' => 0, 'count' => 0],
        '1-7 days' => ['min' => 1, 'max' => 7, 'count' => 0],
        '8-14 days' => ['min' => 8, 'max' => 14, 'count' => 0],
        '15-30 days' => ['min' => 15, 'max' => 30, 'count' => 0],
        'Month 2' => ['min' => 31, 'max' => 60, 'count' => 0],
        'Month 3' => ['min' => 61, 'max' => 90, 'count' => 0],
        'Month 4' => ['min' => 91, 'max' => 120, 'count' => 0],
        'Month 5' => ['min' => 121, 'max' => 150, 'count' => 0],
        'Month 6' => ['min' => 151, 'max' => 180, 'count' => 0],
        'Month 7' => ['min' => 181, 'max' => 210, 'count' => 0],
        'Month 8' => ['min' => 211, 'max' => 240, 'count' => 0],
        'Month 9' => ['min' => 241, 'max' => 270, 'count' => 0],
        'Month 10' => ['min' => 271, 'max' => 300, 'count' => 0],
        'Month 11' => ['min' => 301, 'max' => 330, 'count' => 0],
        'Month 12' => ['min' => 331, 'max' => 360, 'count' => 0],
        'By Year 2' => ['min' => 361, 'max' => 480, 'count' => 0],
        'Year 2' => ['min' => 481, 'max' => 540, 'count' => 0],
        '2 Years +' => ['min' => 541, 'max' => PHP_INT_MAX, 'count' => 0],
    ];

    foreach ($userPurchases as $purchase) {
        $userId = $purchase['userId'];
        $signupDate = $users[$userId];
        $firstPurchaseDate = $purchase['firstPurchaseDate'];

        $signupDatePST = new DateTime($signupDate, new DateTimeZone('UTC'));
        $signupDatePST->setTimezone(new DateTimeZone('America/Los_Angeles'));

        $lengthToPurchase = round((strtotime($firstPurchaseDate) - $signupDatePST->getTimestamp()) / (60 * 60 * 24));

        foreach ($lengthToPurchaseData as &$range) {
            if ($lengthToPurchase >= $range['min'] && $lengthToPurchase <= $range['max']) {
                $range['count']++;
                break;
            }
        }
    }

    return $lengthToPurchaseData;
}

function generate_purchase_data($users, $userPurchases, $startDate, $endDate)
{
    $purchaseData = [];

    foreach ($userPurchases as $purchase) {
        $userId = $purchase['userId'];
        $signupDate = $users[$userId];
        $firstPurchaseDate = $purchase['firstPurchaseDate'];

        $signupDatePST = new DateTime($signupDate, new DateTimeZone('UTC'));
        $signupDatePST->setTimezone(new DateTimeZone('America/Los_Angeles'));

        if ($firstPurchaseDate >= $startDate && $firstPurchaseDate <= $endDate) {
            $lengthToPurchase = round((strtotime($firstPurchaseDate) - $signupDatePST->getTimestamp()) / (60 * 60 * 24));

            if ($lengthToPurchase < 0) {
                $lengthToPurchase = 0;
            }

            if (!isset($purchaseData[$lengthToPurchase])) {
                $purchaseData[$lengthToPurchase] = 0;
            }
            $purchaseData[$lengthToPurchase]++;
        }
    }

    ksort($purchaseData);

    return $purchaseData;
}

function calculate_metrics($users, $lengthToPurchaseData)
{
    $totalSignups = count($users);
    $totalPurchasers = array_sum(array_column($lengthToPurchaseData, 'count'));
    $nonPurchasers = $totalSignups - $totalPurchasers;
    $conversionRate = ($totalSignups > 0) ? round(($totalPurchasers / $totalSignups) * 100, 2) : 0;
    $nonConversionRate = ($nonPurchasers > 0) ? round(($nonPurchasers / $totalSignups) * 100, 2) : 0;

    return compact('totalSignups', 'totalPurchasers', 'nonPurchasers', 'conversionRate', 'nonConversionRate');
}


function get_monthly_iterations($startDate, $endDate)
{
    $startMonth = date('m', strtotime($startDate));
    $endMonth = date('m', strtotime($endDate));
    $startYear = date('Y', strtotime($startDate));
    $endYear = date('Y', strtotime($endDate));

    $iterations = [];
    $currentMonth = $startMonth;
    $currentYear = $startYear;

    while (($currentYear < $endYear) || (($currentYear == $endYear) && ($currentMonth <= $endMonth))) {
        $monthStartDate = $currentYear . '-' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . '-01';
        $monthEndDate = date('Y-m-t', strtotime($monthStartDate));

        $iterations[] = ['start' => $monthStartDate, 'end' => $monthEndDate];

        // Move to the next month
        $currentMonth++;
        if ($currentMonth > 12) {
            $currentMonth = 1;
            $currentYear++;
        }
    }

    return $iterations;
}

function get_weekly_data($startDate, $endDate)
{
    $weekRanges = [];
    $currentDate = new DateTime($startDate);
    $endDateTime = new DateTime($endDate);

    while ($currentDate <= $endDateTime) {
        $weekStart = $currentDate->format('Y-m-d');
        $currentDate->modify('+6 days');
        $weekEnd = $currentDate > $endDateTime ? $endDateTime->format('Y-m-d') : $currentDate->format('Y-m-d');
        $weekRanges[] = ['start' => $weekStart, 'end' => $weekEnd];
        $currentDate->modify('+1 day');
    }

    return $weekRanges;
}

function calculate_send_data($data, $totalUsers, $multiplier)
{
    $sendCountPercentages = array_map(function ($userCount) use ($totalUsers, $multiplier) {
        return $totalUsers ? round(($userCount / $totalUsers) * $multiplier, 2) : 0;
    }, $data);

    arsort($sendCountPercentages);
    $topSendCounts = array_slice($sendCountPercentages, 0, 3, true);

    return $topSendCounts;
}

function get_campaigns_within_dates($startDate, $endDate)
{
    // Replace this with the actual implementation to fetch campaigns
    return get_idwiz_campaigns([
        'startAt_start' => $startDate,
        'startAt_end' => $endDate,
        'messageMedium' => 'Email'
    ]);
}

function sort_campaigns_into_cohorts($campaigns, $mode)
{
    $cohorts = [];
    foreach ($campaigns as $campaign) {
        $cohort = $mode == 'separate' ? $campaign['cohortName'] : 'All Cohorts';
        if (!isset($cohorts[$cohort])) {
            $cohorts[$cohort] = [];
        }
        $cohorts[$cohort][] = $campaign;
    }

    return [
        'cohorts' => $cohorts,
        'totalCampaigns' => count($campaigns)
    ];
}