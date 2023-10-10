<?php

function idwiz_fetch_flexible_chart_data()
{
    // Check for a valid nonce
    if (!check_ajax_referer('wiz-charts', 'security', false)) {
        wp_send_json_error(['message' => 'Security check failed'], 403);
        exit;
    }

    $chartId = $_POST['chartId'];
    $chartType = $_POST['chartType'];
    $campaignIds = json_decode(stripslashes($_POST['campaignIds']), true);

    $function_name = 'idwiz_generate_' . $chartId . '_chart';

    if (function_exists($function_name)) {
        $chartData = call_user_func($function_name, $campaignIds, $chartType);

        if (!$chartData) {
            wp_send_json_error(['message' => 'Data function returned null or false'], 500);
            exit;
        }

        if (isset($chartData['error'])) {
            wp_send_json_error(['message' => $chartData['error']], 500);
            exit;
        }

        // Structured response
        $response = [
            'data' => [
                'labels' => $chartData['labels'],
                'datasets' => $chartData['datasets']
            ],
            'options' => [
                'yAxisDataType' => $chartData['yAxisDataType'],
                'dualYAxisDataType' => $chartData['dualYAxisDataType'],
                'dualYAxis' => $chartData['dualYAxis']
            ]
        ];

        wp_send_json_success($response);
    } else {
        wp_send_json_error(['message' => 'Invalid chartId'], 400);
    }

    exit;
}
add_action('wp_ajax_idwiz_fetch_flexible_chart_data', 'idwiz_fetch_flexible_chart_data');




function idwiz_get_axis_data_type($axis)
{
    // Initialize the data type variable
    $dataType = false;

    switch ($axis) {
        // Date-related metrics
        case 'PurchaseDate':
        case 'SendDate':
            $dataType = 'date';
            break;

        // Money-related metrics
        case 'Revenue':
        case 'revenue':
        case 'gaRevenue':
        case 'wizAov':
            $dataType = 'money';
            break;

        // Number-related metrics
        case 'Purchases':
        case 'Sends':
        case 'Opens':
        case 'Clicks':
        case 'Unsubs':
        case 'Complaints':
        case 'uniqueEmailOpens':
        case 'uniqueEmailSends':
        case 'uniqueEmailsDelivered':
        case 'uniqueEmailClicks':
        case 'uniquePurchases':
        case 'uniqueUnsubscribes':
            $dataType = 'number';
            break;

        // Percent-related metrics
        case 'wizOpenRate':
        case 'Open Rate':
        case 'wizCtr':
        case 'wizCto':
        case 'wizUnsubRate':
        case 'wizCvr':
        case 'wizCompRate':
        case 'wizDeliveryRate':
            $dataType = 'percent';
            break;
    }

    return $dataType;
}

function idwiz_prepare_chart_data($data, $yAxis, $dualYAxis, $chartType)
{
    $datasets = [
        [
            'label' => $yAxis,
            'data' => array_column($data, $yAxis),
            'yAxisID' => 'y-axis-1'
        ]
    ];


    
    $yAxisDataType = idwiz_get_axis_data_type($yAxis);
    $dualYAxisDataType = $dualYAxis ? idwiz_get_axis_data_type($dualYAxis) : false;

    if ($dualYAxis) {
        $datasets[] = [
            'label' => $dualYAxis,
            'data' => array_column($data, $dualYAxis),
            'yAxisID' => 'y-axis-2'
        ];
    }
    
    return [
        'labels' => array_keys($data),
        'datasets' => $datasets,
        'yAxisDataType' => $yAxisDataType,
        'dualYAxisDataType' => $dualYAxisDataType,
        'dualYAxis' => (bool) $dualYAxis,
    ];
}
function idwiz_generate_metricByDate_chart($campaignIds, $chartType, $metricName)
{
    if (!is_array($campaignIds)) {
        return ['error' => 'Invalid campaign ID array.'];
    }

    $campaignIds = array_filter($campaignIds, function ($item) {
        return $item !== 0;
    });

    $metrics = get_idwiz_metrics(array('ids' => $campaignIds));

    if (!$metrics) {
        return ['error' => 'No metrics found for the provided campaign IDs.'];
    }

    $dateData = [];

    foreach ($metrics as $metric) {
        $campaign = get_idwiz_campaign($metric['id']);
        $metricValue = $metric[$metricName] ?? 0;

        $sendDateTimestampInSeconds = floor($campaign['startAt'] / 1000);
        $dateObject = new DateTime("@{$sendDateTimestampInSeconds}");
        $formattedDate = $dateObject->format('Y-m-d');
        
        $dateData[$formattedDate] = [$metricName => $metricValue];
    }

    ksort($dateData);

    $reformattedDateData = [];
    foreach ($dateData as $formattedDate => $data) {
        $dateObject = DateTime::createFromFormat('Y-m-d', $formattedDate);
        $reformattedDate = $dateObject->format('m/d/Y');
        $reformattedDateData[$reformattedDate.' - '.$campaign['name']] = $data;
    }

    return idwiz_prepare_chart_data($reformattedDateData, $metricName, null, $chartType);
}

function idwiz_generate_opensByDate_chart($campaignIds, $chartType)
{
    return idwiz_generate_metricByDate_chart($campaignIds, $chartType, 'wizOpenRate');
}

function idwiz_generate_ctrByDate_chart($campaignIds, $chartType)
{
    return idwiz_generate_metricByDate_chart($campaignIds, $chartType, 'wizCtr');
}

function idwiz_generate_ctoByDate_chart($campaignIds, $chartType)
{
    return idwiz_generate_metricByDate_chart($campaignIds, $chartType, 'wizCto');
}





function idwiz_generate_sendsByDate_chart($campaignIds, $chartType)
{
    // Validate that $campaignIds is an array
    if (!is_array($campaignIds)) {
        return ['error' => 'Invalid campaign ID array.'];
    }

    // Initialize the $dateData array to store the aggregated data
    $dateData = [];

    // Loop over each campaignId and fetch the corresponding triggered sends
    foreach ($campaignIds as $campaignId) {
        $triggeredSends = get_triggered_sends_by_campaign_id($campaignId);

        // Loop over each triggered send and aggregate the data by date
        foreach ($triggeredSends as $send) {
            // Convert the 'startAt' field from milliseconds to seconds and create a DateTime object
            $dateObject = new DateTime();
            $dateObject->setTimestamp($send['startAt'] / 1000);

            // Optional: Set the time zone if needed
            // $dateObject->setTimezone(new DateTimeZone('Your/Timezone'));

            // Format the date
            $formattedDate = $dateObject->format('Y-m-d');

            // Initialize the date in $dateData if it's not already set
            if (!isset($dateData[$formattedDate])) {
                $dateData[$formattedDate] = ['Sends' => 0]; // No Revenue as we're dealing with sends
            }

            // Increment the send count for the date
            $dateData[$formattedDate]['Sends'] += 1;
        }
        // Sort the $dateData array by date in ascending order
        ksort($dateData);

        // Initialize a new array to hold the reformatted keys and data
        $reformattedDateData = [];

        // Loop through the sorted array to reformat the date keys
        foreach ($dateData as $formattedDate => $data) {
            // Create a DateTime object from the 'Y-m-d' formatted date
            $dateObject = DateTime::createFromFormat('Y-m-d', $formattedDate);

            // Reformat the date to 'm/d/Y'
            $reformattedDate = $dateObject->format('m/d/Y');

            // Move the data to the new array with the reformatted date as the key
            $reformattedDateData[$reformattedDate] = $data;
        }
    }



    return idwiz_prepare_chart_data($reformattedDateData, 'Sends', null, $chartType);
}



function idwiz_generate_purchasesByDate_chart($campaignIds, $chartType)
{

    if (!is_array($campaignIds)) {
        return ['error' => 'Invalid campaign ID array.'];
    }

    $campaignIds = array_filter($campaignIds, function ($item) {
        return $item !== 0;
    });

    $purchases = get_idwiz_purchases(array('campaignIds' => $campaignIds, 'sortBy' => 'purchaseDate', 'sort' => 'ASC'));

    if (!$purchases) {
        return ['error' => 'No purchases found for the provided campaign IDs.'];
    }

    $dateData = [];

    foreach ($purchases as $purchase) {
        $dateString = $purchase['purchaseDate'] ?? $purchase['createdAt'];
        $dateObject = new DateTime($dateString);

        if (isset($purchase['createdAt']) && !isset($purchase['purchaseDate'])) {
            $utcTimeZone = new DateTimeZone('UTC');
            $laTimeZone = new DateTimeZone('America/Los_Angeles');

            $dateObject->setTimezone($utcTimeZone);
            $dateObject->setTimezone($laTimeZone);
        }

        $formattedDate = $dateObject->format('m/d/Y');

        if (!isset($dateData[$formattedDate])) {
            $dateData[$formattedDate] = ['Purchases' => 0, 'Revenue' => 0];
        }

        $dateData[$formattedDate]['Purchases'] += 1;
        $dateData[$formattedDate]['Revenue'] += $purchase['total'];
    }

    $yAxis = 'Purchases';
    $dualYAxis = null;

    if ($chartType !== 'pie') {
        $dualYAxis = 'Revenue';
    }

    return idwiz_prepare_chart_data($dateData, $yAxis, $dualYAxis, $chartType);
}

function idwiz_generate_purchasesByDivision_chart($campaignIds, $chartType)
{

    if (!is_array($campaignIds)) {
        return ['error' => 'Invalid campaign ID array.'];
    }

    // Filter out zeros from the $campaigns array, if any
    $campaignIds = array_filter($campaignIds, function ($item) {
        return $item !== 0;
    });

    // Fetch purchases data based on the campaign IDs
    $purchases = get_idwiz_purchases(array('campaignIds' => $campaignIds, 'sortBy' => 'purchaseDate', 'sort' => 'ASC'));

    if (!$purchases) {
        return ['error' => 'No purchases were found!'];
    }

    $divisionData = [];

    // Loop through each purchase and group them by division
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

    // Prepare the data for the chart
    $yAxis = 'Revenue';
    $dualYAxis = null;

    if ($chartType !== 'pie') {
        $dualYAxis = 'Purchases';
    }

    return idwiz_prepare_chart_data($divisionData, $yAxis, $dualYAxis, $chartType);
}


function idwiz_generate_purchasesByTopic_chart($campaignIds, $chartType)
{
    if (!is_array($campaignIds)) {
        return ['error' => 'Invalid campaign ID array.'];
    }

    // Filter out zeros from the $campaigns array, if any
    $campaignIds = array_filter($campaignIds, function ($item) {
        return $item !== 0;
    });

    // Fetch purchases data based on the campaign IDs
    $purchases = get_idwiz_purchases(array('campaignIds' => $campaignIds, 'sortBy' => 'purchaseDate', 'sort' => 'ASC'));

    if (!$purchases) {
        return ['error' => 'No purchases found for the provided campaign IDs.'];
    }

    $topicData = [];

    // Loop through each purchase and group them by topic
    foreach ($purchases as $purchase) {
        $topics = $purchase['shoppingCartItems_categories'] ?? '';
        if (empty($topics)) {
            continue; // Skip this purchase if no topics are associated
        }
        $topics = explode(',', $topics);

        foreach ($topics as $topic) {
            $topic = trim($topic); // Remove any extra spaces around topic names

            if (!isset($topicData[$topic])) {
                $topicData[$topic] = ['Purchases' => 0, 'Revenue' => 0];
            }

            $topicData[$topic]['Purchases'] += 1;
            $topicData[$topic]['Revenue'] += $purchase['total'];
        }
    }

    // Prepare the data for the chart
    $yAxis = 'Purchases';
    $dualYAxis = null;

    if ($chartType !== 'pie') {
        $dualYAxis = 'Revenue';
    }

    return idwiz_prepare_chart_data($topicData, $yAxis, $dualYAxis, $chartType);
}


function idwiz_generate_purchasesByLocation_chart($campaignIds, $chartType)
{

    if (!is_array($campaignIds)) {
        return ['error' => 'Invalid campaign ID array.'];
    }

    // Filter out zeros from the $campaigns array, if any
    $campaignIds = array_filter($campaignIds, function ($item) {
        return $item !== 0;
    });

    // Fetch purchases data based on the campaign IDs
    $purchases = get_idwiz_purchases(array('campaignIds' => $campaignIds, 'sortBy' => 'purchaseDate', 'sort' => 'ASC'));

    if (!$purchases) {
        return ['error' => 'No purchases found for the provided campaign IDs.'];
    }

    $locationData = [];

    // Loop through each purchase and group them by location
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

    // Prepare the data for the chart
    $yAxis = 'Purchases';
    $dualYAxis = null;

    if ($chartType !== 'pie') {
        $dualYAxis = 'Revenue';
    }

    return idwiz_prepare_chart_data($locationData, $yAxis, $dualYAxis, $chartType);
}

add_action('wp_ajax_idwiz_handle_monthly_goal_chart_request', 'idwiz_handle_monthly_goal_chart_request');

function idwiz_handle_monthly_goal_chart_request()
{
    // Check nonce
    check_ajax_referer('dashboard', 'security');

    // Check if wizMonth and wizYear are set in the AJAX request
    if (isset($_POST['wizMonth']) && isset($_POST['wizYear'])) {
        $month_num = intval($_POST['wizMonth']); // Convert to integer
        $wizYear = sanitize_text_field($_POST['wizYear']);

        // Convert month number to full lowercase word
        $dateObj = DateTime::createFromFormat('!m', $month_num);
        $month = strtolower($dateObj->format('F'));

        $data = idwiz_generate_monthly_to_goal_data($month, $wizYear);

        if (isset($data['error'])) {
            wp_send_json_error(array('message' => $data['error']));
        } else {
            wp_send_json_success($data);
        }
    } else {
        wp_send_json_error(array('message' => 'Month and fiscal year are required.'));
    }


    wp_die();
}


function idwiz_generate_monthly_to_goal_data($month, $wizYear)
{
    // Set dates
    $startOfMonth = date('Y-m-d', strtotime('first day of ' . $month));
    $endOfMonth = date('Y-m-d', strtotime('last day of ' . $month));

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
    if (isset($projections[$month])) {
        $result['monthlyProjection'] = $projections[$month];
    } else {
        $result['error'] = "No projections found for month: $month";
        return $result;
    }



    $blastCampaigns = get_idwiz_campaigns(
        array(
            'startAt_start' => $startOfMonth,
            'startAt_end' => $endOfMonth,
            'type' => 'Blast',
            'fields' => 'id'
        )
    );
    $blastMetrics = get_idwiz_metrics(['campaignIds' => array_column($blastCampaigns, 'id')]);

    // Fetch and sum revenue

    $campaignTypes = ['Blast', 'Triggered'];

    $totalRevenue = get_idwiz_revenue($startOfMonth, $endOfMonth, $campaignTypes, true);


    // Check if campaigns data is valid
    if (is_array($blastMetrics)) {
        $result['totalRevenue'] = $totalRevenue;
        $result['percentToGoal'] = round(($totalRevenue / $result['monthlyProjection']) * 100, 2);
    } else {
        $result['error'] = "Failed to fetch metric or invalid data format.";
    }

    return $result;
}

add_action('wp_ajax_idwiz_handle_stacked_campaign_rev_request', 'idwiz_handle_stacked_campaign_rev_request');


add_action('wp_ajax_idwiz_generate_cohort_chart', 'idwiz_generate_cohort_chart');

function idwiz_generate_cohort_chart()
{
    global $wpdb;
    $purchaseMonth = isset($_POST['purchaseMonth']) ? intval($_POST['purchaseMonth']) : null;
    $purchaseMonthDay = isset($_POST['purchaseMonthDay']) ? intval($_POST['purchaseMonthDay']) : null;
    $divisions = isset($_POST['divisions']) ? $_POST['divisions'] : [];
    $purchaseWindowDays = isset($_POST['purchaseWindow']) ? $_POST['purchaseWindow'] : 30;

    // Construct the specified date using the purchaseMonth and purchaseMonthDay
    $specified_date = new DateTime();
    $specified_date->setDate($specified_date->format("Y"), $purchaseMonth, $purchaseMonthDay);

    // Clone the specified_date and add one year to get next_year_specified_date
    $next_year_specified_date = (clone $specified_date)->modify('+1 year');

    // Get the second purchases for the given date
    $second_purchases = get_second_purchases_within_week($purchaseMonth, $purchaseMonthDay, $purchaseWindowDays, $divisions);
    if (empty($second_purchases)) {
        wp_send_json_error(['message' => 'No second purchases found.']);
    }

    $grouped_chart_data = [];
    foreach ($second_purchases as $purchase) {
        $purchase_date = new DateTime($purchase['purchaseDate']);

        if ($purchase_date->format("Y") == $specified_date->format("Y")) {
            // If the purchase is within the same year as the specified_date
            $days_since_initial = $specified_date->diff($purchase_date)->days;
        } else {
            // If the purchase is in the next year
            $remaining_days_in_specified_year = 365 - $specified_date->format('z'); // Subtracting day of year from total days
            $days_in_next_year = $purchase_date->format('z'); // Days passed in the next year

            $days_since_initial = $remaining_days_in_specified_year + $days_in_next_year;

            // Adjust for leap year
            if ($specified_date->format("L") == 1 && $specified_date->format("m") <= 2) {
                $days_since_initial += 1;
            }
        }

        // Convert days_since_initial to the format "50 - 7/3"
        $date_from_day = (clone $specified_date)->add(new DateInterval("P{$days_since_initial}D"));
        $formatted_date = $date_from_day->format('n/j');
        $label = $days_since_initial . " - " . $formatted_date;

        $key = $label . "_" . $purchase['division'];
        if (!isset($grouped_chart_data[$key])) {
            $grouped_chart_data[$key] = [
                'day_of_year' => $label,
                'division' => $purchase['division'],
                'count' => 0
            ];
        }
        $grouped_chart_data[$key]['count']++;
    }

    // Sort the data based on the day of the year
    usort($grouped_chart_data, function ($a, $b) {
        return explode(' - ', $a['day_of_year'])[0] - explode(' - ', $b['day_of_year'])[0];
    });

    $chart_data = array_values($grouped_chart_data);

    // Send the JSON response
    wp_send_json_success($chart_data);
}

function return_new_and_returning_customers($purchases) {
    // Group purchases by orderId
    $ordersGroupedByOrderId = [];
    foreach ($purchases as $purchase) {
        $orderId = $purchase['orderId'];
        if (!isset($ordersGroupedByOrderId[$orderId])) {
            $ordersGroupedByOrderId[$orderId] = [];
        }
        $ordersGroupedByOrderId[$orderId][] = $purchase;
    }

    $newOrdersCount = 0;
    $returningOrdersCount = 0;
    $processedCustomers = []; // To track which customers have been processed

    // Loop through each unique order and categorize as 'new' or 'returning'
    foreach ($ordersGroupedByOrderId as $orderId => $orderPurchases) {
        $firstPurchase = reset($orderPurchases); // Get the first purchase from this order
        $accountId = $firstPurchase['accountNumber'];

        // If this customer has already been processed, skip the loop iteration
        if (in_array($accountId, $processedCustomers)) {
            continue;
        }

        $processedCustomers[] = $accountId; // Mark this customer as processed

        // Query all purchases for this account
        $allCustomerPurchases = get_idwiz_purchases([
            'fields' => 'accountNumber, orderId, purchaseDate',
            'accountNumber' => $accountId
        ]);

        usort($allCustomerPurchases, function ($a, $b) {
            return strcmp($a['purchaseDate'], $b['purchaseDate']);
        });

        // The first purchase date for this account
        $firstPurchaseDate = $allCustomerPurchases[0]['purchaseDate'];

        if ($firstPurchase['purchaseDate'] == $firstPurchaseDate) {
            $newOrdersCount++;
        } else {
            $returningOrdersCount++;
        }
    }

    return [
        'new' => $newOrdersCount,
        'returning' => $returningOrdersCount
    ];
}





function idwiz_fetch_customer_types_chart_data()
{
    check_ajax_referer('wiz-charts', 'security');

    $campaignIds = isset($_POST['campaignIds']) ? json_decode(stripslashes($_POST['campaignIds']), true) : [];

    $purchaseArgs = [
        'fields' => 'id, campaignId, orderId, accountNumber, purchaseDate',
        'ids' => $campaignIds
    ];
    $purchases = get_idwiz_purchases($purchaseArgs);
    $orderCounts = return_new_and_returning_customers($purchases);

    $newCount = $orderCounts['new'];
    $returningCount = $orderCounts['returning'];

    $data = [
        'labels' => ['New Customers', 'Returning Customers'],
        'datasets' => [
            [
                'data' => [$newCount, $returningCount],
                'backgroundColor' => ['#FF6384', '#36A2EB'],
            ],
        ],
    ];

    wp_send_json_success(['data' => $data]);
}
add_action('wp_ajax_idwiz_fetch_customer_types_chart_data', 'idwiz_fetch_customer_types_chart_data');

