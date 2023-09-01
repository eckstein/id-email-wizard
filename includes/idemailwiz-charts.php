<?php
// Array of valid combinations
$valid_combinations = [
    'bar' => [
        'Division' => [
            ['label' => 'Purchases', 'type' => 'number'],
            ['label' => 'Revenue', 'type' => 'money']
        ],
        'Date' => [
            ['label' => 'Purchases', 'type' => 'number'],
            ['label' => 'Revenue', 'type' => 'money']
        ]
    ],
    'line' => [
        'Division' => [
            ['label' => 'Purchases', 'type' => 'number'],
            ['label' => 'Revenue', 'type' => 'money'],
            ['label' => 'Sends', 'type' => 'number'],
            ['label' => 'Opens', 'type' => 'number']
        ],
        'Date' => [
            ['label' => 'Purchases', 'type' => 'number'],
            ['label' => 'Sends', 'type' => 'number']
        ]
    ],
    'pie' => [
        'yAxis' => [
            ['label' => 'Purchases', 'type' => 'number'],
            ['label' => 'Revenue', 'type' => 'money']
        ]
    ]
];



function idwiz_fetch_flexible_chart_data() {
    // Check for nonce and security
    if (!check_ajax_referer('wiz-charts', 'security', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Fetch new data attributes
    $chartType = $_POST['chartType'];
    $xAxis = $_POST['xAxis'];
    $yAxis = $_POST['yAxis'];
    $dualYAxis = $_POST['dualYAxis'];
    $campaignIds = $_POST['campaignIds']; 

    // Validate the combination
    global $valid_combinations;  // Bring the global array into scope

    if ($chartType === 'pie') {
        $yAxisLabels = array_column($valid_combinations[$chartType]['yAxis'], 'label');
        if (!in_array($yAxis, $yAxisLabels)) {
            wp_send_json_error('Invalid combination of chart type and yAxis for pie chart');
            return;
        }
    } else {
        if (!isset($valid_combinations[$chartType][$xAxis])) {
            wp_send_json_error('Invalid x-axis for the given chart type');
            return;
        }

        $yAxisLabels = array_column($valid_combinations[$chartType][$xAxis], 'label');

        if (!in_array($yAxis, $yAxisLabels) ||
            ($dualYAxis && !in_array($dualYAxis, $yAxisLabels))) {
            wp_send_json_error('Invalid combination of chart type and axes');
            return;
        }
    }



    // Initialize the data variable
    $data = null;

    // Fetch the data types based on the configuration
    $yAxisType = $valid_combinations[$chartType][$xAxis][0]['type'];
    $dualYAxisType = $valid_combinations[$chartType][$xAxis][1]['type'];

    $data = idwiz_fetch_and_prepare_data($xAxis, $yAxis, $dualYAxis, $chartType, $campaignIds);

    // Add data types to the response
    $data['yAxisDataType'] = $yAxisType;
    if ($dualYAxis) {
        $data['dualYAxisDataType'] = $dualYAxisType;
    }

    // Send back the data
    wp_send_json_success($data);

}

add_action('wp_ajax_idwiz_fetch_flexible_chart_data', 'idwiz_fetch_flexible_chart_data');

function idwiz_fetch_and_prepare_data($xAxis, $yAxis, $dualYAxis, $chartType, $campaignIds) {
    
    // Filter out zeros from the $campaigns array, if any
    $campaignIds = array_filter($campaignIds, function($item) {
        return $item !== 0;
    });

    //error_log(print_r($campaignIds, true));
    // Initialize an empty data array
    $data = [];

    $campaigns = get_idwiz_campaigns(array('campaignIds' => $campaignIds, 'sortBy'=>'startAt', 'sort'=>'ASC'));
    $purchases = get_idwiz_purchases(array('campaignIds' => $campaignIds, 'sortBy'=> 'purchaseDate', 'sort'=>'ASC', 'fields'=>'campaignId, createdAt, purchaseDate, total, shoppingCartItems_divisionName'));
    $metrics = get_idwiz_metrics(array('campaignIds' => $campaignIds));


    // Handle chart type 'bar' and 'line'
    if ($chartType === 'bar' || $chartType === 'line') {
        // Switch based on xAxis
        switch ($xAxis) {
            case 'Division':
                $divisionData = []; // To hold summed up data for each division
                
                // Loop over purchases and sum them up based on division
                foreach ($purchases as $purchase) {
                    $division = $purchase['shoppingCartItems_divisionName'];

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


                    if ($divLabel && !array_key_exists($divLabel, $divisionData)) {
                        $divisionData[$divLabel] = ['Purchases' => 0, 'Revenue' => 0];
                    } else {
                        $divisionData[$divLabel]['Purchases'] += 1;
                        $divisionData[$divLabel]['Revenue'] += $purchase['total'];
                    }
                }

                $data['labels'] = array_keys($divisionData);
                $data['datasets'] = [
                    [
                        'label' => $yAxis,
                        'data' => array_column($divisionData, $yAxis),
                        'yAxisID' => 'y-axis-1'
                    ]
                ];

                // If dualYAxis is set, add it to datasets
                if ($dualYAxis) {
                    $data['datasets'][] = [
                        'label' => $dualYAxis,
                        'data' => array_column($divisionData, $dualYAxis),
                        'yAxisID' => 'y-axis-2'
                    ];
                }

                break;

            case 'Date':
                $dateData = [];

                // Loop over purchases and sum them up based on date
                foreach ($purchases as $purchase) {
                    // Check for 'purchaseDate', then fallback to 'createdAt'
                    $dateString = $purchase['purchaseDate'] ?? $purchase['createdAt'];
                    
                    // Create a DateTime object
                    $dateObject = new DateTime($dateString);

                    // If 'createdAt' was used, convert from UTC to Los Angeles time
                    if (isset($purchase['createdAt']) && !isset($purchase['purchaseDate'])) {
                        $utcTimeZone = new DateTimeZone('UTC');
                        $laTimeZone = new DateTimeZone('America/Los_Angeles');
                        
                        $dateObject->setTimezone($utcTimeZone); // Explicitly set to UTC
                        $dateObject->setTimezone($laTimeZone);  // Convert to Los Angeles time
                    }

                    // Format the date to 'm/d/Y'
                    $formattedDate = $dateObject->format('m/d/Y');

                    if (!isset($dateData[$formattedDate])) {
                        $dateData[$formattedDate] = ['Purchases' => 0, 'Revenue' => 0];
                    }
                    $dateData[$formattedDate]['Purchases'] += 1;
                    $dateData[$formattedDate]['Revenue'] += $purchase['total'];
                }

                $data['labels'] = array_keys($dateData);

                $data['datasets'] = [
                    [
                        'label' => $yAxis,
                        'data' => array_column($dateData, $yAxis),
                        'yAxisID' => 'y-axis-1'
                    ]
                ];

                // If dualYAxis is set, add it to datasets
                if ($dualYAxis) {
                    $data['datasets'][] = [
                        'label' => $dualYAxis,
                        'data' => array_column($dateData, $dualYAxis),
                        'yAxisID' => 'y-axis-2'
                    ];
                }
                break;
        }
    }

    // Handle chart type 'pie'
    if ($chartType === 'pie') {
        $divisionData = [];

        // Loop over purchases and sum them up based on yAxis
        foreach ($purchases as $purchase) {
            $division = $purchase['shoppingCartItems_divisionName'];
            if (!isset($divisionData[$division])) {
                $divisionData[$division] = 0;
            }
            $divisionData[$division] += 1;  // Pie chart will only consider whatever yAxis is
        }

        $data['labels'] = array_keys($divisionData);
        $data['datasets'] = [
            [
                'data' => array_values($divisionData)
            ]
        ];
    }

    return $data;
}


