<div class="wizcampaign-sections-row">
    <?php if ($displayGoal) { ?>
        <div class="wizcampaign-section inset" id="monthly-goal-section">
            <div class="wizcampaign-section-title-area">
                <h4>To GA Goal

                </h4>
                <div class="wizcampaign-section-title-area-right">
                    $
                    <?php echo number_format($displayGoal, 2); ?>
                </div>
            </div>
            <?php if (isset($_GET['view']) && $_GET['view'] != 'FY' || !isset($_GET['view'])) { ?>
                <div class="wizcampaign-section-content monthlyGoalTracker">
                    <div class="wizChartWrapper">
                        <canvas id="monthlyGoalTracker" data-startDate="<?php echo $_GET['startDate'] ?? date('Y-m-01'); ?>"
                            data-endDate="<?php echo $_GET['endDate'] ?? date('Y-m-d'); ?>"></canvas>
                    </div>

                </div>
            <?php } ?>
        </div>
    <?php } ?>
    <div class="wizcampaign-section inset metrics-tower-group span2">
        <div class="wizcampaign-section-title-area">
            <h4>Purchases & Revenue</h4>
            <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

            </div>
        </div>
        <div class="wizcampaign-section-content">
            <?php
            $revenueMetricsTowers = [
                [
                    'metricType' => 'revenue',
                    'metricFormat' => 'money',
                    'sectionTitle' => 'Rev',
                    'sectionID' => 'monthlyRev'
                ],
                [
                    'metricType' => 'gaRevenue',
                    'metricFormat' => 'money',
                    'sectionTitle' => 'GA Rev',
                    'sectionID' => 'monthlyGaRev'
                ],
                [
                    'metricType' => 'purchases',
                    'metricFormat' => 'num',
                    'sectionTitle' => 'Purchases',
                    'sectionID' => 'purchases'
                ],
                [
                    'metricType' => 'cvr',
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'CVR',
                    'sectionID' => 'monthlyCvr'
                ],
                [
                    'metricType' => 'aov',
                    'metricFormat' => 'money',
                    'sectionTitle' => 'AOV',
                    'sectionID' => 'monthlyAOV'
                ]
            ];

            // Loop through the array and include the template for each metrics tower
            foreach ($revenueMetricsTowers as $revMetricsTower) {
                $metricType = $revMetricsTower['metricType'];
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
                    'metricType' => 'sends',
                    'metricFormat' => 'num',
                    'sectionTitle' => 'Sent',
                    'sectionID' => 'monthlySends'
                ],
                [
                    'metricType' => 'delRate',
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'Delivery',
                    'sectionID' => 'monthlyDelivered'
                ],
                [
                    'metricType' => 'opens',
                    'metricFormat' => 'num',
                    'sectionTitle' => 'Opens',
                    'sectionID' => 'monthlyOpenRate'
                ],
                [
                    'metricType' => 'openRate',
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'Open Rate',
                    'sectionID' => 'monthlyOpenRate'
                ],
                [
                    'metricType' => 'clicks',
                    'metricFormat' => 'num',
                    'sectionTitle' => 'Clicks',
                    'sectionID' => 'monthlyClicks'
                ],
                [
                    'metricType' => 'ctr',
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'CTR',
                    'sectionID' => 'monthlyCtr'
                ],
                [
                    'metricType' => 'cto',
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'CTO',
                    'sectionID' => 'monthlyCto'
                ],
                [
                    'metricType' => 'unsubs',
                    'metricFormat' => 'perc',
                    'sectionTitle' => 'Unsubs',
                    'sectionID' => 'monthlyUnsubs'
                ]
            ];

            // Loop through the array and include the template for each engagement metrics tower
            foreach ($engagementMetricsTowers as $engMetricsTower) {
                $metricType = $engMetricsTower['metricType'];
                $metricFormat = $engMetricsTower['metricFormat'];
                $sectionTitle = $engMetricsTower['sectionTitle'];
                $sectionID = $engMetricsTower['sectionID'];
                include plugin_dir_path(__FILE__) . 'metrics-tower.php';
            }

            ?>
        </div>
    </div>


</div>