<?php

function chart_wizpurchases_by_date() {
    $campaigns = $_POST['campaignIds'];

    // Bail early without valid nonce
    if (!check_ajax_referer('wiz-charts', 'security')) return;

    if (!$campaigns) {
        wp_send_json_error('No Campaign Ids set!');
    }

    $allPurchases = array();
    foreach ($campaigns as $campaignID) {
       $wizCampaign = get_idwiz_campaign($campaignID);
       $campaignPurchases = get_idwiz_purchases(array('campaignId'=>$wizCampaign['id'], 'fields'=>'purchaseDate,createdAt'));
       $allPurchases = array_merge($allPurchases, $campaignPurchases);
    }

    $purchasesByDate = array();
    foreach ($allPurchases as $purchase) {
        if (!empty($purchase['purchaseDate'])) {
            $date = new DateTime($purchase['purchaseDate']);
        } else {
            $date = new DateTime($purchase['createdAt'], new DateTimeZone('UTC'));
            $date->setTimezone(new DateTimeZone('America/Los_Angeles')); // Convert to Los Angeles time
        }
        $formattedDate = $date->format('m/d/y');
        
        if (!isset($purchasesByDate[$formattedDate])) {
            $purchasesByDate[$formattedDate] = 0;
        }
        $purchasesByDate[$formattedDate]++;
    }


    // Prepare data for the chart
    $data = array(
        'labels' => array_keys($purchasesByDate),
        'datasets' => array(
            array(
                'label' => '# of Purchases',
                'data' => array_values($purchasesByDate),
            )
        )
    );

    wp_send_json_success($data);
}
add_action('wp_ajax_chart_wizpurchases_by_date', 'chart_wizpurchases_by_date');


function chart_wizpurchases_by_division() {

    $campaigns = $_POST['campaignIds'];

    // Bail early without valid nonce
    if (!check_ajax_referer('wiz-charts', 'security')) return;

    if (!$campaigns) {
        //wp_send_json_success($data); 
        wp_send_json_error( 'No Campaign Ids set!' );
    }

    $allPurchases = array();
    foreach ($campaigns as $campaignID) {
       $wizCampaign = get_idwiz_campaign($campaignID);
       $campaignPurchases = get_idwiz_purchases(array('campaignId'=>$wizCampaign['id'], 'fields'=>'shoppingCartItems_divisionName, shoppingCartItems_price'));
       $allPurchases = array_merge($allPurchases, $campaignPurchases);
    }

    // Group purchases by division and count them
    $divisions = array();
    $divisionRevenue = array();
    foreach ($allPurchases as $purchase) {
        $division_name = $purchase['shoppingCartItems_divisionName'];
        if (!isset($divisions[$division_name])) {
            $divisions[$division_name] = 0;
            $divisionRevenue[$division_name] = 0; // Initialize revenue for the division
        }
        $divisions[$division_name]++;
        $divisionRevenue[$division_name] += $purchase['shoppingCartItems_price']; // Add the revenue for this purchase
    }

    // Prepare data for the chart
    $data = array(
        'labels' => array_keys($divisions),
        'datasets' => array(
            array(
                'label' => '# of Purchases',
                'data' => array_values($divisions),
                // Other properties for this dataset (e.g., color) can go here
            ),
            array(
                'label' => 'Purchases Total',
                'data' => array_values($divisionRevenue),
                'yAxisID' => 'y-axis-revenue', // This associates the dataset with the right y-axis
                // Other properties for this dataset (e.g., color) can go here
            )
        )
    );


    wp_send_json_success($data);
    //return $data;
}
add_action('wp_ajax_chart_wizpurchases_by_division', 'chart_wizpurchases_by_division');