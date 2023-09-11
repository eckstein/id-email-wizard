<?php
function idwiz_fetch_flexible_chart_data() {
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

function idwiz_prepare_chart_data($data, $yAxis, $dualYAxis, $chartType) {
    $datasets = [
        [
            'label' => $yAxis,
            'data' => array_column($data, $yAxis),
            'yAxisID' => 'y-axis-1'
        ]
    ];

    $yAxisDataType = idwiz_get_axis_data_type($yAxis);
    $dualYAxisDataType = $dualYAxis ? idwiz_get_axis_data_type($dualYAxis) : null;

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
        'dualYAxis' => (bool)$dualYAxis  // Cast to boolean to indicate the presence of a dual axis
    ];
}

function idwiz_get_axis_data_type($axis) {
    if (!$axis) {
        return false;
    }
    switch ($axis) {
        case 'PurchaseDate':
        case 'SendDate':
            $dataType = 'date';
            break;
        case 'Revenue':
            $dataType = 'money';
            break;
        case 'Purchases':
        case 'Opens':
        case 'Clicks':
        case 'Unsubs':
        case 'Complaints':
            $dataType = 'number';
            break;
        case 'Opens':
        case 'CTO':
        case 'CTR':
        case 'AOV':
        case 'UnsubRate':
        case 'CompRate':
            $dataType = 'percent';
            break;
    }

    return $dataType;
}

function idwiz_generate_purchasesByDate_chart($campaignIds, $chartType) {

    if (!is_array($campaignIds)) {
        return ['error' => 'Invalid campaign ID array.'];
    }

    $campaignIds = array_filter($campaignIds, function($item) {
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

function idwiz_generate_purchasesByDivision_chart($campaignIds, $chartType) {

    if (!is_array($campaignIds)) {
        return ['error' => 'Invalid campaign ID array.'];
    }

    // Filter out zeros from the $campaigns array, if any
    $campaignIds = array_filter($campaignIds, function($item) {
        return $item !== 0;
    });
    
    // Fetch purchases data based on the campaign IDs
    $purchases = get_idwiz_purchases(array('campaignIds' => $campaignIds, 'sortBy' => 'purchaseDate', 'sort' => 'ASC'));

    if (!$purchases) {
        return ['error' => 'No purchases found for the provided campaign IDs.'];
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
    $yAxis = 'Purchases';
    $dualYAxis = null;

    if ($chartType !== 'pie') {
        $dualYAxis = 'Revenue';
    }

    return idwiz_prepare_chart_data($divisionData, $yAxis, $dualYAxis, $chartType);
}


function idwiz_generate_purchasesByTopic_chart($campaignIds, $chartType) {
    if (!is_array($campaignIds)) {
        return ['error' => 'Invalid campaign ID array.'];
    }

    // Filter out zeros from the $campaigns array, if any
    $campaignIds = array_filter($campaignIds, function($item) {
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
            continue;  // Skip this purchase if no topics are associated
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


function idwiz_generate_purchasesByLocation_chart($campaignIds, $chartType) {

    if (!is_array($campaignIds)) {
        return ['error' => 'Invalid campaign ID array.'];
    }

    // Filter out zeros from the $campaigns array, if any
    $campaignIds = array_filter($campaignIds, function($item) {
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
        if (!$location || str_contains($location,'Online')) {
            continue;
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

