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

            // Initialize and increment send count groups for weekly data
            if (!isset($sendCountGroups[$sends])) {
                $sendCountGroups[$sends] = ['count' => 0];
            }
            $sendCountGroups[$sends]['count'] += count($userIds);
            
            foreach ($userIds as $userId) {
                // Store user total sends for monthly data calculation
                if (!isset($userTotalSends[$userId])) {
                    $userTotalSends[$userId] = 0;
                }
                
                $userTotalSends[$userId] += $sends;
            }
            
            // Count total users (for weekly data) - this counts user-week combinations
            $totalUsers += count($userIds);
        }


        $offset += $batchSize;
    }

    // Prepare the return data based on the requested timeframe
    if ($return === 'all') {
        $allSendCountGroups = [];
        foreach ($userTotalSends as $totalSends) {
            if (!isset($allSendCountGroups[$totalSends])) {
                $allSendCountGroups[$totalSends] = 0;
            }
            $allSendCountGroups[$totalSends]++;
        }
        ksort($allSendCountGroups); // Sort the array by key (number of sends)

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
        $monthlySendCountGroups = [];
        foreach ($userTotalSends as $totalSends) {
            if (!isset($monthlySendCountGroups[$totalSends])) {
                $monthlySendCountGroups[$totalSends] = 0;
            }
            $monthlySendCountGroups[$totalSends]++;
        }
        ksort($monthlySendCountGroups); // Sort the array by key (number of sends)
        $monthlyTotalUsers = count($userTotalSends);

        return ['monthlyData' => $monthlySendCountGroups, 'totalUsers' => $monthlyTotalUsers];
    }
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

function generate_length_to_purchase_data($users, $userPurchases, $startDate, $endDate)
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

function get_week_ranges($startDate, $endDate)
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



function sort_campaigns_into_cohorts($campaigns)
{
    $cohorts = [];
    $totalCampaigns = 0;

    foreach ($campaigns as $campaign) {
        $campaignCohorts = $campaign['labels'] ? $campaign['labels'] : [];

        $campaignCohorts = $campaign['labels'] ? unserialize($campaign['labels']) : [];

        if (!empty($campaignCohorts)) {
            $totalCampaigns++;
            foreach ($campaignCohorts as $cohort) {
                if (!isset($cohorts[$cohort])) {
                    $cohorts[$cohort] = [];
                }
                $cohorts[$cohort][] = $campaign;
            }
        }
    }

    return [
        'cohorts' => $cohorts,
        'totalCampaigns' => $totalCampaigns
    ];
}


function idwiz_get_hourly_metrics($campaignIds, $metrics = ['opensByHour', 'clicksByHour'], $maxHours = 72)
{
    global $wpdb;

    if (!is_array($campaignIds) || empty($campaignIds)) {
        return false;
    };

    $metricsTableName = $wpdb->prefix . 'idemailwiz_metrics';
    $metricsString = implode(', ', $metrics);

    $return = [];

    $wizCampaigns = get_idwiz_campaigns(['campaignIds' => $campaignIds, 'fields' => ['id', 'startAt']]);
    foreach ($wizCampaigns as $campaign) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT $metricsString FROM $metricsTableName WHERE id = %d",
                $campaign['id']
            )
        );

        if ($row) {
            foreach ($metrics as $metric) {
                if ($row->$metric) {
                    $metricRow = unserialize($row->$metric);
                    if (!is_array($metricRow)) {
                        error_log("Invalid data for campaign {$campaign['id']}, metric $metric: " . print_r($metricRow, true));
                        continue;
                    }
                    $metricRow = array_filter($metricRow, function ($value, $hour) use ($maxHours) {
                        return $hour <= $maxHours && $value > 0; // Only keep non-zero values within maxHours
                    }, ARRAY_FILTER_USE_BOTH);

                    if (!empty($metricRow)) { // Only add if there's any non-zero data
                        $return[$metric][$campaign['id']] = $metricRow;
                    }
                }
            }
        }
    }

    return $return;
}

function group_by_hour_metrics($metrics = [], $openThreshold = 50, $clickThreshold = 10)
{
    $grouped_campaigns = [];

    foreach ($metrics as $metric_type => $campaigns) {
        if (!isset($grouped_campaigns[$metric_type])) {
            $grouped_campaigns[$metric_type] = [];
        }
        foreach ($campaigns as $campaign_id => $hourly_data) {
            if (array_sum($hourly_data) == 0) continue; // Skip campaigns with all-zero data

            ksort($hourly_data); // Ensure data is sorted by hour

            foreach ($hourly_data as $hour => $count) {
                $threshold = ($metric_type == 'opensByHour') ? $openThreshold : $clickThreshold;
                if ($count >= $threshold) {
                    $grouped_campaigns[$metric_type][$hour][] = $campaign_id;
                }
            }
        }
    }

    // Sort groups by hour (key) in ascending order
    foreach ($grouped_campaigns as $metric_type => &$groups) {
        ksort($groups);
    }

    return $grouped_campaigns;
}
