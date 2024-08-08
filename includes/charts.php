<?php

add_action('wp_ajax_idwiz_catch_chart_request', 'idwiz_catch_chart_request');
function idwiz_catch_chart_request()
{
    // Check for a valid nonce
    if (!check_ajax_referer('wiz-charts', 'security', false)) {
        wp_send_json_error(['message' => 'Security check failed'], 403);
        exit;
    }

    $chartOptions = [];

    //error_log(print_r($_POST, true));
    foreach ($_POST as $key => $value) {

        // Try decoding as JSON
        $decoded = json_decode(stripslashes($value), true);

        // If it's valid JSON, use the decoded value; otherwise, sanitize as a string
        $chartOptions[$key] = (json_last_error() == JSON_ERROR_NONE) ? $decoded : sanitize_text_field($value);
    }

    // Route to handler function based on chartId
    switch ($chartOptions['chartId']) {

        case 'opensReport':
        case 'ctrReport':
        case 'ctoReport':
        case 'unsubReport':
        case 'revReport':
            $chartData = idwiz_get_report_chartdata($chartOptions);
            break;

        case 'sendsByDate':
            //case 'openedByDate':
        case 'opensByDate':
        case 'clicksByDate':
            $chartData = idwiz_get_eventBydate_chartdata($chartOptions);
            break;

        case 'purchasesByDate':
        case 'promoPurchasesByDate':
        case 'purchasesByDivision':
        case 'purchasesByLocation':
        case 'purchasesByTopic':
        case 'purchasesByGender':
        
            $chartData = idwiz_get_byPurchaseField_chartdata($chartOptions);
            break;


        case 'customerTypesChart':
            $chartData = idwiz_get_customer_types_chartdata($chartOptions);
            break;

            // case 'sendCountsByDate':
            //     $chartData = idwiz_get_sendsByWeek_chartdata($chartOptions);
            //     break;

        default:
            wp_send_json_error(['message' => 'Chart ID is not recognized!']);
            exit;
            break;
    }

    if (isset($chartData['error'])) {
        wp_send_json_error($chartData['error']);
    } else {
        wp_send_json_success($chartData);
    }
}

function idwiz_get_customer_types_chartdata($chartOptions)
{
    $purchaseArgs = [
        'fields' => 'id, campaignId, orderId, accountNumber, purchaseDate',
    ];

    $campaignIds = $chartOptions['campaignIds'] ?? [];

    if (!empty($campaignIds)) {
        $purchaseArgs['campaignIds'] = $campaignIds;
    }

    $startDate = $chartOptions['startDate'] ?? '2021-11-01';
    $endDate = $chartOptions['endDate'] ?? date('Y-m-d');

    $purchaseArgs['startAt_start'] = $startDate;
    $purchaseArgs['startAt_end'] = $endDate;

    $purchases = get_idwiz_purchases($purchaseArgs);
    $orderCounts = group_first_and_repeat_purchases($purchases);

    $newCount = $orderCounts['new'];
    $returningCount = $orderCounts['returning'];

    return [
        'type' => 'pie',
        'data' => [
            'labels' => ['New Customers', 'Returning Customers'],
            'datasets' => [
                [
                    'data' => [$newCount, $returningCount],
                    'backgroundColor' => ['#FF6384', '#36A2EB'],
                ],
            ],
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'FORMAT_LABEL_JS_FUNCTION' // Placeholder
                    ],
                ],
            ],
        ],
    ];
}


function idwiz_get_report_chartdata($chartOptions)
{
    $chartId = $chartOptions['chartId'];
    switch ($chartId) {
        case 'opensReport':
            $dbMetric = 'wizOpenRate';
            $minMetric = $chartOptions['minMetric'] ?? 0;
            $maxMetric = $chartOptions['maxMetric'] ?? 100;
            $dataType = 'percent';
            break;
        case 'ctrReport':
            $dbMetric = 'wizCtr';
            $minMetric = isset($chartOptions['minMetric']) ? $chartOptions['minMetric'] * .001 : 0;
            $maxMetric = isset($chartOptions['maxMetric']) ? $chartOptions['maxMetric'] * .001 : 2;
            $dataType = 'percent';
            break;
        case 'ctoReport':
            $dbMetric = 'wizCto';
            $minMetric = isset($chartOptions['minMetric']) ? $chartOptions['minMetric'] * .001 : 0;
            $maxMetric = isset($chartOptions['maxMetric']) ? $chartOptions['maxMetric'] * .001 : 4;
            $dataType = 'percent';
            break;
        case 'unsubReport':
            $dbMetric = 'wizUnsubRate';
            $minMetric = $chartOptions['minMetric'] ?? 0;
            $maxMetric = $chartOptions['maxMetric'] ?? 5;
            $dataType = 'percent';
            break;
        case 'revReport':
            $dbMetric = 'revenue';
            $minMetric = $chartOptions['minMetric'] ?? 0;
            $maxMetric = $chartOptions['maxMetric'] ?? 100000;
            $dataType = 'money';
            break;
    }

    $chartType = $chartOptions['chartType'];

    $minSends = $chartOptions['minSends'] ?? 1000;
    $maxSends = $chartOptions['maxSends'] ?? 500000;

    $cohorts = $chartOptions['cohorts'] ?? false;

    // Ensure $cohorts is always an array:
    if ($cohorts && !is_array($cohorts)) {
        $cohorts = [$cohorts];
    }

    $excludedCohorts = $chartOptions['cohortsExclude'] ?? false;

    // Ensure $excludedCohorts is always an array:
    if ($excludedCohorts && !is_array($excludedCohorts)) {
        $excludedCohorts = [$excludedCohorts];
    }

    $startDate = $chartOptions['startDate'] ?? false;
    $endDate = $chartOptions['endDate'] ?? false;

    // Calculate the start date for the previous year
    $prevYearStartDate = date('Y-m-d', strtotime('-1 year', strtotime($startDate)));

    $campaignArgs = [
        'type' => ['Blast'],
        'startAt_start' => $prevYearStartDate, // Start from previous year
        'startAt_end' => $endDate,
        'sortBy' => 'startAt',
        'sort' => 'ASC',
    ];

    $allCampaigns = get_idwiz_campaigns($campaignArgs);

    // Split campaigns into current year and previous year
    $campaigns = [];
    $prevCampaigns = [];

    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);
    $oneYearBeforeStart = strtotime('-1 year', $startTimestamp);

    foreach ($allCampaigns as $campaign) {
        $campaignTimestamp = $campaign['startAt'] / 1000;
        if ($campaignTimestamp >= $startTimestamp && $campaignTimestamp <= $endTimestamp) {
            $campaigns[] = $campaign;
        } elseif ($campaignTimestamp >= $oneYearBeforeStart && $campaignTimestamp < $startTimestamp) {
            $prevCampaigns[] = $campaign;
        }
    }

    $labels = [];
    $currentYearData = [];
    $prevYearData = [];

    // Process current year campaigns
    $currentYearResult = get_campaigns_data_for_report($campaigns, $dbMetric, $minSends, $maxSends, $minMetric, $maxMetric, $cohorts, $excludedCohorts, true);
    $currentYearData = $currentYearResult['data'];
    $currentYearTooltips = $currentYearResult['tooltips'];

    // Process previous year campaigns
    $prevYearResult = get_campaigns_data_for_report($prevCampaigns, $dbMetric, $minSends, $maxSends, $minMetric, $maxMetric, $cohorts, $excludedCohorts, false);
    $prevYearData = $prevYearResult['data'];
    $prevYearTooltips = $prevYearResult['tooltips'];

    // Generate date range
    $dateRange = generate_date_range($startDate, $endDate);

    // Prepare final datasets
    $labels = $dateRange;
    $currentYearFinalData = array_fill(0, count($dateRange), null);
    $prevYearFinalData = array_fill(0, count($dateRange), null);

    foreach ($dateRange as $index => $date) {
        $currentYearFinalData[$index] = isset($currentYearData[$date]) ? $currentYearData[$date] : null;
        $prevYearFinalData[$index] = isset($prevYearData[$date]) ? $prevYearData[$date] : null;
    }

    // Structuring data for a line chart
    return [
        'type' => $chartType,
        'data' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Current Year',
                    'data' => $currentYearFinalData,
                    'tooltipData' => $currentYearTooltips, // Pass tooltip data
                    'yAxisID' => 'y-axis-1',
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'fill' => false,
                    'spanGaps' => true
                ],
                [
                    'label' => 'Previous Year',
                    'data' => $prevYearFinalData,
                    'tooltipData' => $prevYearTooltips, // Pass tooltip data
                    'yAxisID' => 'y-axis-1',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'fill' => false,
                    'spanGaps' => true
                ]
            ]
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'hideTooltipLabel' => true,
            'plugins' => [
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false
                ],
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false
                    ],
                ],
                'y-axis-1' => [
                    'type' => 'linear',
                    'position' => 'left',
                    'beginAtZero' => true,
                    'min' => $minMetric,
                    'max' => $maxMetric,
                    'stepSize' => 1,
                    'grid' => [
                        'drawOnChartArea' => true,
                    ],
                    'ticks' => [
                        'callback' => 'function(value, index, values) {
                            return new Intl.NumberFormat("en-US", {
                                style: '.$dataType.',
                                minimumFractionDigits: 2
                            }).format(value / 100);
                        }'
                    ],
                    'dataType' => $dataType
                ]
            ],
        ],
        'customTooltip' => true,
    ];
}

// Function to process campaigns and collect data
function get_campaigns_data_for_report($campaigns, $dbMetric, $minSends, $maxSends, $minMetric, $maxMetric, $cohorts, $excludedCohorts, $isCurrentYear)
{
    $data = [];
    $tooltips = [];

    foreach ($campaigns as $campaign) {
        $campaignMetrics = get_idwiz_metric($campaign['id']);

        // Safely unserialize or decode the labels
        $campaignCohorts = [];
        if (!empty($campaign['labels'])) {
            if (is_string($campaign['labels'])) {
                $unserialized = @unserialize($campaign['labels']);
                if ($unserialized !== false) {
                    $campaignCohorts = $unserialized;
                } else {
                    // If unserialize fails, try json_decode
                    $decoded = json_decode($campaign['labels'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $campaignCohorts = $decoded;
                    }
                }
            } elseif (is_array($campaign['labels'])) {
                $campaignCohorts = $campaign['labels'];
            }
        }

        // Ensure $campaignCohorts is always an array
        $campaignCohorts = is_array($campaignCohorts) ? $campaignCohorts : [];

        // If cohorts are selected and not 'all', filter campaigns
        if ($cohorts && is_array($cohorts)) {
            if (!in_array('all', $cohorts)) {
                if (empty(array_intersect($cohorts, $campaignCohorts))) {
                    continue; // Skip this campaign if it doesn't have any of the selected cohorts
                }
            }
        }

        // Check for excluded cohorts
        if ($excludedCohorts && is_array($excludedCohorts)) {
            error_log('Comparing campaign cohorts: ' . json_encode($campaignCohorts) . ' with excluded cohorts: ' . json_encode($excludedCohorts));
            if (!empty(array_intersect($campaignCohorts, $excludedCohorts))) {
                error_log('Skipping campaign due to excluded cohorts. Campaign ID: ' . $campaign['id']);
                continue; // Skip this campaign if it has any of the excluded cohorts
            }
        }

        // Check campaign metrics against thresholds
        if (
            $campaignMetrics &&
            $campaignMetrics['uniqueEmailSends'] >= $minSends
            && $campaignMetrics['uniqueEmailSends'] <= $maxSends
            && $campaignMetrics[$dbMetric] > 0
            && $campaignMetrics[$dbMetric] >= $minMetric
            && $campaignMetrics[$dbMetric] <= $maxMetric
        ) {
            $campaignStartStamp = (int)($campaign['startAt'] / 1000);
            $originalDate = date('m/d/Y', $campaignStartStamp);

            // Adjust the date for previous year data
            if (!$isCurrentYear) {
                $adjustedDate = date('m/d/Y', strtotime('+1 year', $campaignStartStamp));
            } else {
                $adjustedDate = $originalDate;
            }

            $data[$adjustedDate] = $campaignMetrics[$dbMetric];
            $tooltips[$adjustedDate] = [
                'name' => $campaign['name'],
                'value' => $campaignMetrics[$dbMetric],
                'originalDate' => $originalDate
            ];
        }
    }
    return ['data' => $data, 'tooltips' => $tooltips];
}


function generate_date_range($start, $end)
{
    $start = new DateTime($start);
    $end = new DateTime($end);
    $end->modify('+1 day'); // Include the end date

    $range = new DatePeriod($start, new DateInterval('P1D'), $end);

    $dates = [];
    foreach ($range as $date) {
        $dates[] = $date->format('m/d/Y');
    }
    return $dates;
}


function idwiz_get_byPurchaseField_chartdata($chartOptions)
{

    $chartId = $chartOptions['chartId'];
    $chartType = $chartOptions['chartType'];

    $purchaseArgs = [
        'startAt_start' => $chartOptions['startDate'],
        'startAt_end' => $chartOptions['endDate'],
    ];
    if ($chartOptions['campaignIds']) {
        $purchaseArgs['campaignIds'] = $chartOptions['campaignIds'];
    }
    if (isset($chartOptions['promoCode'])) {
        $purchaseArgs['shoppingCartItems_discountCode'] = $chartOptions['promoCode'];
    }

    $campaignTypes = $chartOptions['campaignTypes'] ?? ['Blast', 'Triggered'];

    $timeScale = $chartOptions['timeScale'] ?? 'daily';

    // Limit fields to what we need
    $purchaseArgs['fields'] = ['campaignId', 'total', 'purchaseDate', 'shoppingCartItems_categories', 'shoppingCartItems_locationName', 'shoppingCartItems_divisionName', 'shoppingCartItems_discountCode'];

    $allPurchases = get_idwiz_purchases($purchaseArgs);
    if (!$allPurchases) {
        return ['error' => 'No purchases were returned from the query.'];
    }

    // Fetch all campaigns in one go
    $campaignIds = array_unique(array_column($allPurchases, 'campaignId'));
    $allCampaigns = get_idwiz_campaigns(['campaignIds' => $campaignIds]);
    $campaignMap = array_column($allCampaigns, null, 'id');

    $blastPurchases = [];
    $triggeredPurchases = [];
    foreach ($allPurchases as $purchase) {
        $purchaseCampaign = $campaignMap[$purchase['campaignId']] ?? null;

        if ($purchaseCampaign && $purchaseCampaign['type'] == 'Triggered') {
            $triggeredPurchases[] = $purchase;
        } elseif ($purchaseCampaign && $purchaseCampaign['type'] == 'Blast') {
            $blastPurchases[] = $purchase;
        }
    }

    if (in_array('Blast', $campaignTypes) && in_array('Triggered', $campaignTypes)) {
        $filteredPurchases = $allPurchases;
    } elseif (in_array('Blast', $campaignTypes)) {
        $filteredPurchases = $blastPurchases;
    } elseif (in_array('Triggered', $campaignTypes)) {
        $filteredPurchases = $triggeredPurchases;
    } else {
        return ['error' => 'No campaign types were selected.'];
    }

    if (empty($filteredPurchases)) {
        return ['error' => 'Zero purchases were returned from the query.'];
    }

    $groupedData = [];
    switch ($chartId) {
        case 'purchasesByDate':
            $groupedData = idwiz_group_purchases_by_date($filteredPurchases, $timeScale);
            break;

        case 'purchasesByDivision':
            $groupedData = idwiz_group_purchases_by_division($filteredPurchases);
            break;

        case 'purchasesByLocation':
            $groupedData = idwiz_group_purchases_by_location($filteredPurchases);
            break;

        case 'purchasesByTopic':
            $groupedData = idwiz_group_purchases_by_topic($filteredPurchases);
            break;

        case 'promoPurchasesByDate':
            $groupedData = idwiz_group_purchases_by_date($filteredPurchases, $timeScale);
            break;

        default:
            return ['error' => 'Invalid chart ID'];
    }

    $response = [
        'type' => $chartType,
        'data' => [
            'labels' => array_keys($groupedData)
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
        ]
    ];

    if ($chartType === 'bar') {
        $response['data']['datasets'] = [
            [
                'label' => 'Purchases',
                'data' => array_column($groupedData, 'Purchases'),
                'yAxisID' => 'y-axis-num'
            ],
            [
                'label' => 'Revenue',
                'data' => array_column($groupedData, 'Revenue'),
                'yAxisID' => 'y-axis-rev'
            ]
        ];

        $response['options']['scales'] = [
            'x' => [

                'grid' => [
                    'display' => false
                ]
            ],
            'y-axis-rev' => [
                'position' => 'right',
                'beginAtZero' => true,
                'grid' => [
                    'drawOnChartArea' => false,
                ],
                'dataType' => 'money',
            ],
            'y-axis-num' => [
                'position' => 'left',
                'beginAtZero' => true,
                'dataType' => 'number',
            ]
        ];

        $response['options']['yAxisDataType'] = 'money';
        $response['options']['dualYAxis'] = true;
        $response['options']['dualYAxisDataType'] = 'number';
        $response['options']['hideTooltipTitle'] = false;
    } elseif ($chartType === 'pie' || $chartType === 'doughnut') {
        $numberOfSegments = count($groupedData);
        $colors = generatePieChartColors('#FF6384', '#36A2EB', $numberOfSegments);
        $purchasesData = array_column($groupedData, 'Purchases');
        $revenuesData = array_column($groupedData, 'Revenue');

        $datasets = [
            'data' => $purchasesData,
            'backgroundColor' => $colors,
            'metaData' => $revenuesData // Include revenue data as meta data
        ];

        $response['data']['datasets'] = [$datasets]; // Chart.js expects datasets to be an array
    }


    return $response;
}



// Charts triggered event data by date
function idwiz_get_eventBydate_chartdata($chartOptions)
{
    $chartId = $chartOptions['chartId'];

    $chartType = $chartOptions['chartType'];

    $campaignIds = $chartOptions['campaignIds'] ?? false;

    $campaignType = $chartOptions['campaignType'] ?? 'triggered';

    $timeScale = $chartOptions['timeScale'] ?? 'daily';

    //$campaignTypes = $chartOptions['campaignTypes'] ?? ['Blast', 'Triggered'];

    $startDate = $chartOptions['startDate'] ?? false;
    $endDate = $chartOptions['endDate'] ?? date('Y-m-d');

    switch ($chartId) {
        case 'sendsByDate':
            $triggeredTableName = "idemailwiz_{$campaignType}_sends";
            $label = 'Sends';
            break;

        case 'opensByDate':
            $triggeredTableName = "idemailwiz_{$campaignType}_opens";
            $label = 'Opens';
            break;

        case 'clicksByDate':
            $triggeredTableName = "idemailwiz_{$campaignType}_clicks";
            $label = 'Clicks';
            break;

        default:
            return ['error' => 'Invalid chart ID'];
    }

    $triggeredDataArgs['campaignIds'] = $campaignIds;

    $triggeredDataArgs['fields'] = 'campaignId, startAt';

    if ($startDate) {
        $triggeredDataArgs['startAt_start'] = $startDate;
    }
    if ($endDate) {
        $triggeredDataArgs['startAt_end'] = $endDate;
    }

    //NOTE: uniqueMessageIds is set to false because we want to show all engagements on the charts, not just first ones
    $triggeredData = get_idemailwiz_triggered_data(database: $triggeredTableName, args: $triggeredDataArgs, uniqueMessageIds: false);

    if (!empty($triggeredData)) {
        $chartDates = [];

        foreach ($triggeredData as $event) {
            $utcTimestamp = $event['startAt'] / 1000;
            $utcDateTime = new DateTime('@' . $utcTimestamp);
            $utcDateTime->setTimezone(new DateTimeZone('America/Los_Angeles'));

            if ($timeScale === 'daily') {
                $eventDate = $utcDateTime->format('m/d/Y');
            } else if ($timeScale === 'hourly') {
                $eventDate = $utcDateTime->format('m/d/Y H:00');
            }

            if (!isset($chartDates[$eventDate])) {
                $chartDates[$eventDate] = 0;
            }
            $chartDates[$eventDate] += 1;
        }

        // Convert the dates from strings to date objects for sorting
        $chartDateObjects = array_map(function ($date) use ($timeScale) {
            if ($timeScale === 'daily') {
                return DateTime::createFromFormat('m/d/Y', $date);
            } else if ($timeScale === 'hourly') {
                return DateTime::createFromFormat('m/d/Y H:i', $date);
            }
        }, array_keys($chartDates));

        // Sort the dates
        usort($chartDateObjects, function ($a, $b) {
            return $a <=> $b;
        });

        // Convert back to strings if necessary and create the labels and data arrays
        $labels = [];
        $data = [];
        foreach ($chartDateObjects as $dateObject) {
            if ($timeScale === 'daily') {
                $date = $dateObject->format('m/d/Y');
                $displayDate = $dateObject->format('m/d/Y');
            } else if ($timeScale === 'hourly') {
                $displayDate = $dateObject->format('m/d g:ia');
                $date = $dateObject->format('m/d/Y H:i');
            }
            $labels[] = $displayDate;
            $data[] = $chartDates[$date];
        }

        return [
            'type' => $chartType,

            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => $label,
                        'data' => $data,
                        'yAxisID' => 'y-axis-1'
                    ]
                ]
            ],
            'options' => [
                'maintainAspectRatio' => false,
                'responsive' => true,
                'scales' => [
                    'x' => [
                        'ticks' => [
                            'maxRotation' => 0,
                        ]
                    ],
                    'y-axis-1' => [
                        'position' => 'left',
                        'beginAtZero' => true,
                        'ticks' => [
                            'callback' => function ($value, $index, $values) {
                                return number_format($value, 0);
                            }
                        ],
                        'dataType' => 'number'
                    ]

                ],
                'tooltips' => [
                    'callbacks' => [
                        'label' => function ($tooltipItem, $data) {
                            $value = $tooltipItem->yLabel;
                            return number_format($value);
                        }
                    ]
                ]
            ]
        ];
    } else {
        return ['error' => 'No data was returned from the query.'];
    }
}














function idwiz_group_purchases_by_division($purchases)
{
    $divisionData = [];

    foreach ($purchases as $purchase) {
        $division = $purchase['shoppingCartItems_divisionName'] ?? 'Other';

        // Transform divisions into shorter versions
        if ($division == 'iD Tech Camps') {
            $divLabel = 'IPC';
        } else if ($division == 'Online Private Lessons') {
            $divLabel = 'OPL';
        } else if ($division == 'Virtual Tech Camps') {
            $divLabel = 'VTC';
        } else if ($division == 'iD Teen Academies') {
            $divLabel = 'iDTA';
        } else if (strpos($division, 'Online Teen Academies') !== false) {
            $divLabel = 'OTA';
        } else if (strpos($division, 'AHEAD') !== false) {
            $divLabel = 'AHEAD';
        } else {
            $divLabel = 'Other';
        }

        if (!isset($divisionData[$divLabel])) {
            $divisionData[$divLabel] = ['Purchases' => 0, 'Revenue' => 0];
        }

        $divisionData[$divLabel]['Purchases'] += 1;
        $divisionData[$divLabel]['Revenue'] += $purchase['total'];
    }

    return $divisionData;
}


function generatePieChartColors($startColor, $endColor, $count)
{
    $startRGB = sscanf($startColor, "#%02x%02x%02x");
    $endRGB = sscanf($endColor, "#%02x%02x%02x");
    $diffRGB = [
        $endRGB[0] - $startRGB[0],
        $endRGB[1] - $startRGB[1],
        $endRGB[2] - $startRGB[2]
    ];

    $colors = [];
    for ($i = 0; $i < $count; $i++) {
        $colors[] = sprintf(
            "#%02x%02x%02x",
            $startRGB[0] + ($diffRGB[0] * $i) / max($count - 1, 1),
            $startRGB[1] + ($diffRGB[1] * $i) / max($count - 1, 1),
            $startRGB[2] + ($diffRGB[2] * $i) / max($count - 1, 1)
        );
    }
    return $colors;
}
function idwiz_group_purchases_by_topic($purchases)
{
    $topicData = [];

    foreach ($purchases as $purchase) {
        $topics = $purchase['shoppingCartItems_categories'] ?? '';
        if (empty($topics)) {
            continue;
        }
        $topics = explode(',', $topics);

        foreach ($topics as $topic) {
            $topic = trim($topic);

            if (!isset($topicData[$topic])) {
                // Start new topic array if not yet in our array
                $topicData[$topic] = ['Purchases' => 0, 'Revenue' => 0];
            }

            $topicData[$topic]['Purchases'] += 1;
            $topicData[$topic]['Revenue'] += $purchase['total'];
        }
    }

    return $topicData;
}
function idwiz_group_purchases_by_promo($purchases)
{
    $promoData = [];

    foreach ($purchases as $purchase) {
        $promo = $purchase['shoppingCartItems_discountCode'] ?? '';

        $promo = trim($promo);

        if (!isset($promoData[$promo])) {
            // Start new promo array if not yet in our array
            $promoData[$promo] = ['Purchases' => 0, 'Revenue' => 0];
        }

        $promoData[$promo]['Purchases'] += 1;
        $promoData[$promo]['Revenue'] += $purchase['total'];
    }

    return $promoData;
}

function idwiz_group_purchases_by_location($purchases)
{
    $locationData = [];

    foreach ($purchases as $purchase) {
        $location = $purchase['shoppingCartItems_locationName'];
        if (!$location || str_contains($location, 'Online')) {
            $location = 'Online';
        }

        if (!isset($locationData[$location])) {
            $locationData[$location] = ['Purchases' => 0, 'Revenue' => 0];
        }

        $locationData[$location]['Purchases'] += 1;
        $locationData[$location]['Revenue'] += $purchase['total'];
    }

    return $locationData;
}


function idwiz_group_purchases_by_date($purchases, $timeScale)
{
    $dateData = [];

    foreach ($purchases as $purchase) {
        // Using purchaseDate here means we only get the day (not the time).
        // In the future, if we start syncing the time, we can use the timeScale option to switch these on the chart
        $dateObject = new DateTime($purchase['purchaseDate']);

        if ($timeScale === 'daily') {
            // Use Y-m-d format for sorting purposes.
            $sortableDate = $dateObject->format('Y-m-d');
            $formattedDate = $dateObject->format('m/d/Y');
        } else {
            // Use Y-m-d H:i format for sorting purposes.
            $sortableDate = $dateObject->format('Y-m-d H:i');
            $formattedDate = $dateObject->format('m/d/Y H:i');
        }

        if (!isset($dateData[$sortableDate])) {
            $dateData[$sortableDate] = ['Purchases' => 0, 'Revenue' => 0, 'FormattedDate' => $formattedDate];
        }

        $dateData[$sortableDate]['Purchases'] += 1;
        $dateData[$sortableDate]['Revenue'] += $purchase['total'];
    }

    // Sort the dates using uksort and a custom comparison function.
    uksort($dateData, function ($a, $b) {
        return strtotime($a) - strtotime($b);
    });

    // Convert the sorted dates back to the desired format for display.
    $sortedDateData = [];
    foreach ($dateData as $data) {
        $sortedDateData[$data['FormattedDate']] = [
            'Purchases' => $data['Purchases'],
            'Revenue' => $data['Revenue']
        ];
    }

    return $sortedDateData;
}

add_action('wp_ajax_idwiz_get_engagement_by_hour_chart_data', 'idwiz_get_engagement_by_hour_chart_data');
function idwiz_get_engagement_by_hour_chart_data()
{
    // Check for a valid nonce
    if (!check_ajax_referer('wiz-charts', 'security', false)) {
        wp_send_json_error(['message' => 'Security check failed'], 403);
        exit;
    }

    // Get parameters from the request
    $campaign_ids = isset($_POST['campaignIds']) ? json_decode(stripslashes($_POST['campaignIds'])) : [];
    $threshold = isset($_POST['threshold']) ? intval($_POST['threshold']) : 10;
    $max_hours = isset($_POST['maxHours']) ? intval($_POST['maxHours']) : 72;

    // Get the hourly metrics
    $hourly_metrics = idwiz_get_hourly_metrics($campaign_ids, ['opensByHour', 'clicksByHour'], $max_hours);

    // Group the metrics
    $grouped_metrics = group_by_hour_metrics($hourly_metrics, $threshold);


    // Prepare the chart data
    $chart_data = [];
    foreach (['opensByHour', 'clicksByHour'] as $metric_type) {
        $hours = range(0, $max_hours);
        $counts = array_fill(0, $max_hours + 1, 0);

        if (isset($grouped_metrics[$metric_type])) {
            foreach ($grouped_metrics[$metric_type] as $hour => $campaigns) {
                if ($hour <= $max_hours) {
                    $counts[$hour] = count($campaigns);
                }
            }
        }

        $chart_data[$metric_type] = [
            'labels' => $hours,
            'datasets' => [
                [
                    'label' => ucfirst(str_replace('ByHour', '', $metric_type)),
                    'data' => $counts,
                    'backgroundColor' => $metric_type === 'opensByHour' ? 'rgba(75, 192, 192, 0.6)' : 'rgba(255, 99, 132, 0.6)',
                    'borderColor' => $metric_type === 'opensByHour' ? 'rgba(75, 192, 192, 1)' : 'rgba(255, 99, 132, 1)',
                    'borderWidth' => 1
                ]
            ]
        ];
    }

    wp_send_json_success($chart_data);
}