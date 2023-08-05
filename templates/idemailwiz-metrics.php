
<?php
global $wpdb;

$campaigns = idemailwiz_fetch_campaigns();
$templates = idemailwiz_fetch_templates();
$purchases = idemailwiz_fetch_purchases();
$metrics = idemailwiz_fetch_metrics();

$syncCampaigns = idemailwiz_sync_campaigns($campaigns);
$syncTemplate = idemailwiz_sync_templates($templates);
$syncPurchases = idemailwiz_sync_purchases($purchases);
$syncMetrics = idemailwiz_sync_metrics($metrics);
?>

<form name="wiz_table_filter" method="get">
    <input type="date" id="start_date_start" name="start_date_start">
    <input type="date" id="start_date_end" name="start_date_end">
    <input type="submit" id="submit_filters" name="submit_filters" value="Filter"/>
</form>
<?php

//$_GET values
if (isset($_GET)) {
    if (isset($_GET['start_date_start'])) {
        $startAt = strtotime($_GET['start_date_start']) * 1000;
    } else {
        $startAt = strtotime('2023/07/01') * 1000;
    }
}



//Set default columns
$defaultCols = array(
        'campaigns.startAt',
        'campaigns.name',
        'campaigns.labels',
        'templates.subject',
        'templates.preheaderText',
        'metrics.uniqueEmailSends',
        'metrics.uniqueEmailOpens',
        'metrics.wizOpenRate',
        'metrics.uniqueEmailClicks',
        'metrics.wizCtr',
        'metrics.wizCto',
        'metrics.uniquePurchases',
        'metrics.wizCvr',
        'metrics.revenue',
    );

    $sqlCols = implode(',', $defaultCols);

// Define the base SQL statement
$sql = "
SELECT $sqlCols
FROM wp_idemailwiz_campaigns AS campaigns
INNER JOIN wp_idemailwiz_metrics AS metrics ON campaigns.id = metrics.id
INNER JOIN wp_idemailwiz_templates AS templates ON campaigns.templateId = templates.templateId
WHERE campaigns.startAt > $startAt";

// Initialize an array to hold the placeholders
$placeholders = array();

// Execute the query
$results = $wpdb->get_results($sql);





echo idemailwiz_generate_table($results);
function idemailwiz_generate_table($tableData) {
    if(empty($tableData)) {
        return 'No table data!';
    }

    $tableData = idemailwiz_table_value_mapping($tableData);

    // Initialize table
    $html = '<table class="idemailwiz_table">';

    // Generate the header
    $html .= '<thead><tr>';
    foreach($tableData[0] as $key => $value) {
        $html .= '<th>' . htmlspecialchars($key) . '</th>';
    }
    $html .= '</tr></thead>';

    // Generate the body
    $html .= '<tbody>';
    foreach($tableData as $row) {
        $html .= '<tr>';
        foreach($row as $value) {
            $html .= '<td>' . htmlspecialchars($value) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>';

    // Close table
    $html .= '</table>';

    // Return the generated table
    return $html;
}








