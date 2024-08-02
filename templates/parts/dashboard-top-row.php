<?php

$currentDate = new DateTime();

$startDate = $_GET['startDate'] ?? date('Y-m-01');
$endDate = $_GET['endDate'] ?? date('Y-m-t'); // Assuming you want the default to be the end of the current month

$startDateTime = new DateTime($startDate, new DateTimeZone('America/Los_Angeles'));
$endDateTime = new DateTime($endDate, new DateTimeZone('America/Los_Angeles'));

$firstDayOfMonth = $startDateTime->format('Y-m-01');
$lastDayOfMonth = $endDateTime->format('Y-m-t');


?>
<div class="wizcampaign-sections-row">

    <div class="wizcampaign-section inset metrics-tower-group span2">
        <div class="wizcampaign-section-title-area">
            <h4>Purchases & Revenue</h4>
            <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">
                <?php
                if ($startDate === $firstDayOfMonth && ($endDate === $lastDayOfMonth || $endDate === $currentDate)) { ?>
                    <!--GA Goal: $-->
                    <?php 
                    if ($displayGoal && is_int($displayGoal)) {
                        echo number_format($displayGoal, 2); 
                    } ?>
                <?php } ?>
            </div>
        </div>
        <div class="wizcampaign-section-content">
            <?php
            // Clone DateTime object to calculate the last month and last year
            $lastMonthDateTime = clone $startDateTime;
            $lastYearDateTime = clone $startDateTime;

            // Subtract 1 month for last month
            $lastMonthDateTime->modify('-1 month');

            // Subtract 1 year for last year
            $lastYearDateTime->modify('-1 year');

            // Generate the start and end dates for last month
            $lastMonthStart = $lastMonthDateTime->format('Y-m-d');
            $lastMonthDateTime->modify('last day of this month');
            $lastMonthEnd = $lastMonthDateTime->format('Y-m-d');

            // Generate the start and end dates for last year
            $lastYearMonthStart = $lastYearDateTime->format('Y-m-d');
            $lastYearDateTime->modify('last day of this month');
            $lastYearMonthEnd = $lastYearDateTime->format('Y-m-d');

            $metricRates = get_idwiz_metric_rates([], $startDate, $endDate, $campaignTypes, 'campaignsInDate');
            $lastMonthMetricRates = get_idwiz_metric_rates([], $lastMonthStart, $lastMonthEnd, $campaignTypes, 'campaignsInDate');
            $lastYearMetricRates = get_idwiz_metric_rates([], $lastYearMonthStart, $lastYearMonthEnd, $campaignTypes, 'campaignsInDate');
            $revenueMetricsTowers = [
                [
                    'metricType' => 'revenue',
                    'thisMonthValue' => $metricRates['revenue'],
                    'lastMonthValue' => $lastMonthMetricRates['revenue'],
                    'lastYearValue' => $lastYearMetricRates['revenue'],
                    'metricFormat' => 'money',
                    'sectionTitle' => 'Rev',
                    'sectionID' => 'monthlyRev'
                ],
                [
                    'metricType' => 'gaRevenue',
                    'thisMonthValue' => $metricRates['gaRevenue'],
                    'lastMonthValue' => $lastMonthMetricRates['gaRevenue'],
                    'lastYearValue' => $lastYearMetricRates['gaRevenue'],
                    'metricFormat' => 'money',
                    'sectionTitle' => 'GA Rev',
                    'sectionID' => 'monthlyGaRev'
                ],
                [
                    'metricType' => 'uniquePurchases',
                    'thisMonthValue' => $metricRates['uniquePurchases'],
                    'lastMonthValue' => $lastMonthMetricRates['uniquePurchases'],
                    'lastYearValue' => $lastYearMetricRates['uniquePurchases'],
                    'metricFormat' => 'num',
                    'sectionTitle' => 'Purchases',
                    'sectionID' => 'purchases'
                ],
                [
                    'metricType' => 'wizCvr',
                    'thisMonthValue' => $metricRates['wizCvr'],
                    'lastMonthValue' => $lastMonthMetricRates['wizCvr'],
                    'lastYearValue' => $lastYearMetricRates['wizCvr'],
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'CVR',
                    'sectionID' => 'monthlyCvr'
                ],
                [
                    'metricType' => 'wizAov',
                    'thisMonthValue' => $metricRates['wizAov'],
                    'lastMonthValue' => $lastMonthMetricRates['wizAov'],
                    'lastYearValue' => $lastYearMetricRates['wizAov'],
                    'metricFormat' => 'money',
                    'sectionTitle' => 'AOV',
                    'sectionID' => 'monthlyAOV'
                ]
            ];

            // Loop through the array and include the template for each metrics tower
            foreach ($revenueMetricsTowers as $revMetricsTower) {
                $metricType = $revMetricsTower['metricType'];
                $metricValues = ['thisMonth' => $revMetricsTower['thisMonthValue'], 'lastMonth' => $revMetricsTower['lastMonthValue'], 'lastYear' => $revMetricsTower['lastYearValue']];
                $metricFormat = $revMetricsTower['metricFormat'];
                $sectionTitle = $revMetricsTower['sectionTitle'];
                $sectionID = $revMetricsTower['sectionID'];
                include plugin_dir_path(__FILE__) . 'metrics-tower.php';
            }


            ?>
        </div>
    </div>
    <div class="wizcampaign-section inset metrics-tower-group span3">
        <div class="wizcampaign-section-title-area">
            <h4>Engagement</h4>
            <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

            </div>
        </div>
        <div class="wizcampaign-section-content">
            <?php

            // Create an array with the options for each engagement metrics tower
            $engagementMetricsTowers = [
                [
                    'metricType' => 'uniqueEmailSends',
                    'thisMonthValue' => $metricRates['uniqueEmailSends'],
                    'lastMonthValue' => $lastMonthMetricRates['uniqueEmailSends'],
                    'lastYearValue' => $lastYearMetricRates['uniqueEmailSends'],
                    'metricFormat' => 'num',
                    'sectionTitle' => 'Sent',
                    'sectionID' => 'monthlySends'
                ],
                [
                    'metricType' => 'wizDeliveryRate',
                    'thisMonthValue' => $metricRates['wizDeliveryRate'],
                    'lastMonthValue' => $lastMonthMetricRates['wizDeliveryRate'],
                    'lastYearValue' => $lastYearMetricRates['wizDeliveryRate'],
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'Delivery',
                    'sectionID' => 'monthlyDelivered'
                ],
                [
                    'metricType' => 'uniqueEmailOpens',
                    'thisMonthValue' => $metricRates['uniqueEmailOpens'],
                    'lastMonthValue' => $lastMonthMetricRates['uniqueEmailOpens'],
                    'lastYearValue' => $lastYearMetricRates['uniqueEmailOpens'],
                    'metricFormat' => 'num',
                    'sectionTitle' => 'Opens',
                    'sectionID' => 'monthlyOpenRate'
                ],
                [
                    'metricType' => 'wizOpenRate',
                    'thisMonthValue' => $metricRates['wizOpenRate'],
                    'lastMonthValue' => $lastMonthMetricRates['wizOpenRate'],
                    'lastYearValue' => $lastYearMetricRates['wizOpenRate'],
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'Open Rate',
                    'sectionID' => 'monthlyOpenRate'
                ],
                [
                    'metricType' => 'uniqueEmailClicks',
                    'thisMonthValue' => $metricRates['uniqueEmailClicks'],
                    'lastMonthValue' => $lastMonthMetricRates['uniqueEmailClicks'],
                    'lastYearValue' => $lastYearMetricRates['uniqueEmailClicks'],
                    'metricFormat' => 'num',
                    'sectionTitle' => 'Clicks',
                    'sectionID' => 'monthlyClicks'
                ],
                [
                    'metricType' => 'wizCtr',
                    'thisMonthValue' => $metricRates['wizCtr'],
                    'lastMonthValue' => $lastMonthMetricRates['wizCtr'],
                    'lastYearValue' => $lastYearMetricRates['wizCtr'],
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'CTR',
                    'sectionID' => 'monthlyCtr'
                ],
                [
                    'metricType' => 'wizCto',
                    'thisMonthValue' => $metricRates['wizCto'],
                    'lastMonthValue' => $lastMonthMetricRates['wizCto'],
                    'lastYearValue' => $lastYearMetricRates['wizCto'],
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'CTO',
                    'sectionID' => 'monthlyCto'
                ],
                [
                    'metricType' => 'wizUnsubRate',
                    'thisMonthValue' => $metricRates['wizUnsubRate'],
                    'lastMonthValue' => $lastMonthMetricRates['wizUnsubRate'],
                    'lastYearValue' => $lastYearMetricRates['wizUnsubRate'],
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'Unsubs',
                    'sectionID' => 'monthlyUnsubs'
                ],
                [
                    'metricType' => 'wizCompRate',
                    'thisMonthValue' => $metricRates['wizCompRate'],
                    'lastMonthValue' => $lastMonthMetricRates['wizCompRate'],
                    'lastYearValue' => $lastYearMetricRates['wizCompRate'],
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'Complaints',
                    'sectionID' => 'monthlyComplaints'
                ]
            ];

            // Loop through the array and include the template for each engagement metrics tower
            foreach ($engagementMetricsTowers as $engMetricsTower) {
                $metricType = $engMetricsTower['metricType'];
                $metricValues = ['thisMonth' => $engMetricsTower['thisMonthValue'], 'lastMonth' => $engMetricsTower['lastMonthValue'], 'lastYear' => $engMetricsTower['lastYearValue']];
                $metricFormat = $engMetricsTower['metricFormat'];
                $sectionTitle = $engMetricsTower['sectionTitle'];
                $sectionID = $engMetricsTower['sectionID'];
                include plugin_dir_path(__FILE__) . 'metrics-tower.php';
            }

            ?>
        </div>
    </div>


</div>