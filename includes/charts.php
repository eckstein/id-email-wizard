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
        case 'retentionReport':
            $chartData = idwiz_get_report_chartdata($chartOptions);
            break;

        case 'sendsByDate':
        case 'openedByDate':
        case 'opensByDate':
        case 'clicksByDate':
            $chartData = idwiz_get_eventBydate_chartdata($chartOptions);
            break;

        case 'purchasesByDate':
        case 'purchasesByDivision':
        case 'purchasesByLocation':
        case 'purchasesByTopic':
        case 'purchasesByGender':
            $chartData = idwiz_get_byPurchaseField_chartdata($chartOptions);
            break;

        case 'opensByCampaign':
        case 'openRateByCampaign':
        case 'clicksByCampaign':
        case 'ctrByCampaign':
        case 'ctoByCampaign':
        case 'revenueByCampaign':
        case 'gaRevenueByCampaign':
        case 'purchasesByCampaign':
        case 'cvrByCampaign':
        case 'aovByCampaign':
        case 'unsubsByCampaign':
        case 'unsubRateByCampaign':
            $chartData = idwiz_get_byCampaign_chartdata($chartOptions);
            break;

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

function idwiz_get_report_chartdata($chartOptions)
{

    global $wpdb;

    $chartId = $chartOptions['chartId'];
    switch ($chartId) {
        case 'opensReport':
            $dbMetric = 'wizOpenRate';
            $minMetric = $chartOptions['minMetric'] ?? 0;
            $maxMetric = $chartOptions['maxMetric'] ?? 100;
            break;

        case 'ctrReport':
            $dbMetric = 'wizCtr';
            $minMetric = $chartOptions['minMetric'] * .01 ?? 0;
            $maxMetric = $chartOptions['maxMetric'] * .01 ?? 1;
            break;

        case 'ctoReport':
            $dbMetric = 'wizCto';
            $minMetric = $chartOptions['minMetric'] * .01 ?? 0;
            $maxMetric = $chartOptions['maxMetric'] * .01 ?? 1;
            break;

        case 'retentionReport':
            $dbMetric = 'wizUnsubRate';
            $minMetric = $chartOptions['minMetric'] ?? 0;
            $maxMetric = $chartOptions['maxMetric'] ?? 1;
            break;
    }

    $chartType = $chartOptions['chartType'];

    $minSends = $chartOptions['minSends'] ?? 1000;
    $maxSends = $chartOptions['maxSends'] ?? 500000;



    $startDate = $chartOptions['startDate'] ?? false;
    $endDate = $chartOptions['endDate'] ?? false;

    $campaignArgs = [
        'type' => ['Blast'],
        'startAt_start' => $startDate,
        'startAt_end' => $endDate,
        'sortBy' => 'startAt',
        'sort' => 'ASC',
    ];

    $campaigns = get_idwiz_campaigns($campaignArgs);

    $labels = [];
    $data = [];
    $toolTip = [];

    foreach ($campaigns as $campaign) {
        $campaignMetrics = get_idwiz_metric($campaign['id']);

        if ($campaignMetrics['uniqueEmailSends'] >= $minSends && $campaignMetrics['uniqueEmailSends'] <= $maxSends && $campaignMetrics[$dbMetric] > 0 && $campaignMetrics[$dbMetric] >= $minMetric && $campaignMetrics[$dbMetric] <= $maxMetric) {
            $campaignNameTrunc = wiz_truncate_string($campaign['name'], 50);
            $campaignStartStamp = (int)($campaign['startAt'] / 1000);
            $labels[] = date('m/d/Y', $campaignStartStamp);
            $toolTip[] = [
                date('D, M d, Y', $campaign['startAt'] / 1000),
                $campaignNameTrunc
            ];
            $data[] = $campaignMetrics[$dbMetric];
        }
    }

    // Structuring data for a line chart
    return [
        'type' => $chartType,
        'data' => [
            'labels' => $labels,
            'tooltipLabels' => $toolTip,
            // send the tooltip labels as a separate key
            'datasets' => [
                [
                    'label' => $dbMetric,
                    'data' => $data,
                    'yAxisID' => 'y-axis-1',
                    'trendlineLinear' => [
                        'colorMin' => "red",
                        'colorMax' => "green",
                        'lineStyle' => "dotted",
                        'width' => 2,
                        'projection' => false
                    ]
                ]
            ]
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'tooltip' => [
                    'enabled' => true,
                    'mode' => 'index',
                    'intersect' => false,
                    'displayColors' => false,
                    'callbacks' => [
                        'label' => function ($tooltipItem, $data) {
                            return number_format($tooltipItem->yLabel, 2) . '%'; // Only the Y-axis value formatted as percentage
                        }
                    ],
                ],
                'legend' => false,
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false
                    ]
                ],
                'y-axis-1' => [
                    'position' => 'left',
                    'beginAtZero' => true,
                    'grid' => [
                        'drawOnChartArea' => true,
                    ],
                    'ticks' => [
                        'callback' => function ($value, $index, $values) {
                            return number_format($value, 2) . '%'; // Formatting to 2 decimal places
                        }
                    ],
                    'dataType' => 'percent'
                ]
            ],
            'hideTooltipTitle' => true

        ]
    ];


}



function idwiz_get_byPurchaseField_chartdata($chartOptions) {

    $chartId = $chartOptions['chartId'];
    $chartType = $chartOptions['chartType'];

    $purchaseArgs = [
        'startAt_start' => $chartOptions['startDate'],
        'startAt_end' => $chartOptions['endDate'],
    ];
    if ($chartOptions['campaignIds']) {
        $purchaseArgs['campaignIds'] = $chartOptions['campaignIds'];
    }

    // Limit fields to what we need
    $purchaseArgs['fields'] = ['campaignId', 'total', 'purchaseDate', 'shoppingCartItems_categories', 'shoppingCartItems_locationName', 'shoppingCartItems_divisionName'];

    $allPurchases = get_idwiz_purchases($purchaseArgs);
    if (!$allPurchases) {
        return ['error' => 'No purchases were returned from the query.'];
    }

    // Fetch all campaigns in one go
    $campaignIds = array_unique(array_column($allPurchases, 'campaignId'));
    $allCampaigns = get_idwiz_campaigns(['campaignIds'=>$campaignIds]);
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

    if (in_array('Blast', $chartOptions['campaignTypes']) && in_array('Triggered', $chartOptions['campaignTypes'])) {
        $filteredPurchases = $allPurchases;
    } elseif (in_array('Blast', $chartOptions['campaignTypes'])) {
        $filteredPurchases = $blastPurchases;
    } elseif (in_array('Triggered', $chartOptions['campaignTypes'])) {
        $filteredPurchases = $triggeredPurchases;
    } else {
        return ['error' => 'No campaign types were selected.'];
    }

    if (empty($filteredPurchases)) {
        return ['error' => 'No purchases were returned from the query.'];
    }

    $groupedData = [];
    switch ($chartId) {
        case 'purchasesByDate':
            $groupedData = idwiz_group_purchases_by_date($filteredPurchases);
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
    global $wpdb;

    $chartId = $chartOptions['chartId'];

    $chartType = $chartOptions['chartType'];

    $campaignIds = $chartOptions['campaignIds'] ?? false;

    $campaignType = $chartOptions['campaignType'] ?? 'triggered';

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

    $triggeredData = get_idemailwiz_triggered_data($triggeredTableName, $triggeredDataArgs);

    if (!empty($triggeredData)) {
        $chartDates = [];

        foreach ($triggeredData as $event) {
            $eventDate = date('m/d/Y', $event['startAt'] / 1000);

            if (!isset($chartDates[$eventDate])) {
                $chartDates[$eventDate] = 0;
            }
            $chartDates[$eventDate] += 1;
        }

        // Convert the dates from strings to date objects for sorting
        $chartDateObjects = array_map(function ($date) {
            return DateTime::createFromFormat('m/d/Y', $date);
        }, array_keys($chartDates));

        // Sort the dates
        usort($chartDateObjects, function ($a, $b) {
            return $a <=> $b;
        });

        // Convert back to strings if necessary and create the labels and data arrays
        $labels = [];
        $data = [];
        foreach ($chartDateObjects as $dateObject) {
            $date = $dateObject->format('m/d/Y');
            $labels[] = $date;
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


function idwiz_get_byCampaign_chartdata($chartOptions)
{
    global $wpdb;

    $chartId = $chartOptions['chartId'];

    $campaignTypes = $chartOptions['campaignTypes'] ?? ['Blast', 'Triggered'];
    $campaignIds = $chartOptions['campaignIds'] ?? false;
    $startDate = $chartOptions['startDate'] ?? false;
    $endDate = $chartOptions['endDate'] ?? false;

    $campaignArgs = [];

    if ($campaignIds) {
        $campaignArgs['campaignIds'] = $campaignIds;
    }
    if ($campaignTypes) {
        $campaignArgs['type'] = $campaignTypes;
    }
    if ($startDate) {
        $campaignArgs['startAt_start'] = $startDate;
    }
    if ($endDate) {
        $campaignArgs['startAt_end'] = $endDate;
    }

    $campaigns = get_idwiz_campaigns($campaignArgs);

    $chartMetric = convert_chartId_db_field($chartId);

    $chartData = [
        'labels' => [],
        'datasets' => [
            [
                'label' => $chartMetric,
                'data' => []
            ]
        ]
    ];

    $metricValue = null;
    foreach ($campaigns as $campaign) {
        $campaignDate = $campaign['startAt'];
        $dateLabel = date('m/d/Y', $campaignDate / 1000) . ' - ' . $campaign['name'];
        $campaignMetric = get_idwiz_metric([$campaign['id'], 'fields' => 'campaignId,' . $chartMetric]);

        $metricValue = $campaignMetric[$chartMetric] ?? null;

        // Append to chart data
        $chartData['labels'][] = $dateLabel;
        $chartData['datasets'][0]['data'][] = $metricValue;
    }

    // Determine the type of y-axis based on the chartMetric
    // You might want to adjust this based on your requirements
    $yAxisType = is_numeric($metricValue) ? 'num' : 'text';

    return [
        'type' => 'bar',
        // You can adjust this based on your requirements
        'data' => $chartData,
        'options' => [
            'yAxisDataType' => $yAxisType,
            'dualYAxis' => false,
            'dualYAxisDataType' => null
        ]
    ];
}


function convert_chartId_db_field($chartId)
{
    switch ($chartId) {
        case 'opensByCampaign':
            $dbField = 'uniqueEmailOpens';
            break;

        case 'openRateByCampaign':
            $dbField = 'wizOpenRate';
            break;

        case 'clicksByCampaign':
            $dbField = 'uniqueEmailClicks';
            break;

        case 'ctrByCampaign':
            $dbField = 'wizCtr';
            break;

        case 'ctoByCampaign':
            $dbField = 'wizCto';
            break;

        case 'revenueByCampaign':
            $dbField = 'revenue';
            break;

        case 'gaRevenueByCampaign':
            $dbField = 'gaRevenue';
            break;

        case 'purchasesByCampaign':
            $dbField = 'uniquePurchases';
            break;

        case 'cvrByCampaign':
            $dbField = 'wizCvr';
            break;

        case 'aovByCampaign':
            $dbField = 'wizAov';
            break;

        case 'unsubsByCampaign':
            $dbField = 'uniqueUnsubscribes';
            break;

        case 'unsubRateByCampaign':
            $dbField = 'wizUnsubRate';
            break;

        default:
            $dbField = false;
            break;
    }
    return $dbField;


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


function idwiz_group_purchases_by_date($purchases)
{
    $dateData = [];

    foreach ($purchases as $purchase) {
        $dateObject = new DateTime($purchase['purchaseDate']);

        // Use Y-m-d format for sorting purposes.
        $sortableDate = $dateObject->format('Y-m-d');

        if (!isset($dateData[$sortableDate])) {
            $dateData[$sortableDate] = ['Purchases' => 0, 'Revenue' => 0];
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
    foreach ($dateData as $date => $data) {
        $formattedDate = (new DateTime($date))->format('m/d/Y');
        $sortedDateData[$formattedDate] = $data;
    }

    return $sortedDateData;
}




add_action('wp_ajax_idwiz_handle_monthly_goal_chart_request', 'idwiz_handle_monthly_goal_chart_request');
function idwiz_handle_monthly_goal_chart_request()
{
    // Check nonce
    check_ajax_referer('dashboard', 'security');

    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];

    $startDateTime = new DateTime($startDate);
    $month_num = $startDateTime->format('m');
    $wizYear = $startDateTime->format('Y');

    // Convert month number to full lowercase word
    $dateObj = DateTime::createFromFormat('!m', $month_num);
    $wizMonth = strtolower($dateObj->format('F'));

    $data = idwiz_generate_monthly_to_goal_data($startDate, $endDate, $wizMonth, $wizYear);

    if (isset($data['error'])) {
        wp_send_json_error(array('message' => $data['error']));
    } else {
        wp_send_json_success($data);
    }


    wp_die();
}

function idwiz_generate_monthly_to_goal_data($startDate, $endDate, $wizMonth, $wizYear)
{
    // Initialize result array
    $result = array(
        'totalRevenue' => 0,
        'monthlyProjection' => 0,
        'percentToGoal' => 0,
        'error' => null
    );

    // Fetch monthly projections from ACF
    $projectionFieldGroup = 'fy_' . $wizYear . '_projections';
    $projections = get_field($projectionFieldGroup, 'options');

    // Check if projections exist for the month
    if (isset($projections[$wizMonth]) && $projections[$wizMonth] > 0) {
        $result['monthlyProjection'] = $projections[$wizMonth];
    } else {
        $result['error'] = "No projections found for month: $wizMonth";
        return $result;
    }

    $result['totalRevenue'] = get_idwiz_revenue($startDate, $endDate, ['Blast', 'Triggered'], null, true);
    $result['percentToGoal'] = round(($result['totalRevenue'] / $result['monthlyProjection']) * 100, 2);

    return $result;
}



function idwiz_fetch_customer_types_chart_data()
{
    check_ajax_referer('wiz-charts', 'security');



    $purchaseArgs = [
        'fields' => 'id, campaignId, orderId, accountNumber, purchaseDate',
        //'shoppingCartItems_utmMedium' => 'email',
    ];

    $campaignIds = isset($_POST['campaignIds']) ? json_decode(stripslashes($_POST['campaignIds']), true) : [];

    if (!empty($campaignIds)) {
        $purchaseArgs['campaignIds'] = $campaignIds;
    }

    $startDate = $_POST['startDate'] ?? '2021-11-01';
    $endDate = $_POST['endDate'] ?? date('y-m-d');

    $purchaseArgs['startAt_start'] = $startDate;
    $purchaseArgs['startAt_end'] = $endDate;

    $purchases = get_idwiz_purchases($purchaseArgs);
    $orderCounts = group_first_and_repeat_purchases($purchases);
    

    $newCount = $orderCounts['new'];
    $returningCount = $orderCounts['returning'];

    $data = [
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
                        'label' => 'FORMAT_LABEL_JS_FUNCTION' // A placeholder
                    ],
                ],
            ],
        ],
    ];

    wp_send_json_success($data);
}
add_action('wp_ajax_idwiz_fetch_customer_types_chart_data', 'idwiz_fetch_customer_types_chart_data');