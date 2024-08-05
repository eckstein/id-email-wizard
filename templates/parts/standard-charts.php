<?php

//If start and end date are set, we re-get purchases just for between the dates
//$fetchPurchases = idemailwiz_sync_purchases();
//echo count($fetchPurchases);
//print_r($fetchPurchases);


?>
<div class="wizcampaign-sections-row noWrap">

    <div class="wizcampaign-section inset span2" id="email-info">
        <?php
        //For purchases by date, we want to get purchases (not campaigns) by date.
        ?>
        <div class="wizcampaign-section-title-area">
            <h4>Purchases by Date</h4>
            <div class="wizcampaign-section-icons">
                
            </div>
        </diV>
        <div class="wizChartWrapper">
            <?php
            // Set up the data attributes
            $purchByDateAtts = [];


            $purchByDateAtts[] = 'data-chartid="purchasesByDate"';

            if ($standardChartCampaignIds) {
                $purchByDateAtts[] = 'data-campaignids=\'' . json_encode($standardChartCampaignIds) . '\'';
            } else {
                $purchByDateAtts[] = 'data-campaignids=\'' . json_encode('') . '\'';
            }

            //$purchByDateAtts[] = 'data-campaignids=\'' . json_encode([]) . '\'';
            
            if (isset($campaignTypes)) {
                $purchByDateAtts[] = 'data-campaigntypes=\'' . json_encode($campaignTypes) . '\'';
            }

            $purchByDateAtts[] = "data-startdate='{$startDate}'";
            $purchByDateAtts[] = "data-enddate='{$endDate}'";

            $purchByDateAtts[] = 'data-charttype="bar"';

            if (isset($campaignTypes)) {
                $purchByDateAtts[] = 'data-campaigntypes=\'' . json_encode($campaignTypes) . '\'';
            } else {
                $purchByDateAtts[] = 'data-campaigntypes=\'' . json_encode(['Blast', 'Triggered']) . '\'';
            }

            // Convert the array to a string for echoing
            $purchByDateAttsString = implode(' ', $purchByDateAtts);
            ?>

            <canvas class="purchByDate wiz-canvas" id="purchasesByDate" <?php echo $purchByDateAttsString; ?>></canvas>

        </div>
    </div>

    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Purchases by Division</h4>
            <div class="wizcampaign-section-icons">
                <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i
                    class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
            </div>
        </div>
        <div class="wizChartWrapper">
            <?php
            // Set up the data attributes
            $purchByDivisionAtts = [];

            $purchByDivisionAtts[] = 'data-campaignids=\'' . json_encode($standardChartCampaignIds) . '\'';

            $purchByDivisionAtts[] = "data-startdate='{$startDate}'";
            $purchByDivisionAtts[] = "data-enddate='{$endDate}'";

            $purchByDivisionAtts[] = 'data-charttype="bar"';

            if (isset($campaignTypes)) {
                $purchByDivisionAtts[] = 'data-campaigntypes=\'' . json_encode($campaignTypes) . '\'';
            } else {
                $purchByDivisionAtts[] = 'data-campaigntypes=\'' . json_encode(['Blast', 'Triggered']) . '\'';
            }

            // Convert the array to a string for echoing
            $purchByDivisionAttsString = implode(' ', $purchByDivisionAtts);
            ?>
            <canvas class="purchByDivision wiz-canvas" data-chartid="purchasesByDivision"
                data-campaignids='<?php echo json_encode($standardChartCampaignIds); ?>' <?php echo $purchByDivisionAttsString; ?> ></canvas>
        </div>
    </div>
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Purchases by Topic</h4>
            <div class="wizcampaign-section-icons">
                <i class="fa-solid fa-chart-simple chart-type-switcher" data-chart-type="bar"></i><i
                    class="fa-solid fa-chart-pie active chart-type-switcher" data-chart-type="pie"></i>
            </div>
        </div>
        <div class="wizChartWrapper">
            <?php
            // Set up the data attributes
            $purchByTopicAtts = [];

            $purchByTopicAtts[] = 'data-chartid="purchasesByTopic"';

            $purchByTopicAtts[] = 'data-campaignids=\'' . json_encode($standardChartCampaignIds) . '\'';

            $purchByTopicAtts[] = "data-startdate='{$startDate}'";
            $purchByTopicAtts[] = "data-enddate='{$endDate}'";

            $purchByTopicAtts[] = 'data-charttype="pie"';

            if (isset($campaignTypes)) {
                $purchByTopicAtts[] = 'data-campaigntypes=\'' . json_encode($campaignTypes) . '\'';
            } else {
                $purchByTopicAtts[] = 'data-campaigntypes=\'' . json_encode(['Blast', 'Triggered']) . '\'';
            }

            // Convert the array to a string for echoing
            $purchByTopicAttsString = implode(' ', $purchByTopicAtts);
            ?>
            <canvas class="purchByTopic wiz-canvas" <?php echo $purchByTopicAttsString; ?> ></canvas>
        </div>
    </div>

    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>New vs Returning</h4>
            <div class="wizcampaign-section-icons">
                > FY 21-22
            </div>
        </div>
        <div class="wizChartWrapper">
            <?php
            // Set up the data attributes
            $newVsReturningAtts = [];

            $newVsReturningAtts[] = 'data-chartid="customerTypesChart"';

            $newVsReturningAtts[] = 'data-campaignids=\'' . json_encode($standardChartCampaignIds) . '\'';

            $newVsReturningAtts[] = "data-startdate='{$startDate}'";
            $newVsReturningAtts[] = "data-enddate='{$endDate}'";

            $newVsReturningAtts[] = 'data-charttype="pie"';

            if (isset($campaignTypes)) {
                $newVsReturningAtts[] = 'data-campaigntypes=\'' . json_encode($campaignTypes) . '\'';
            } else {
                $newVsReturningAtts[] = 'data-campaigntypes=\'' . json_encode(['Blast']) . '\'';
            }

            // Convert the array to a string for echoing
            $newVsReturningAttsString = implode(' ', $newVsReturningAtts);
            ?>
            <canvas class="wiz-canvas" id="customerTypeChart" <?php echo $newVsReturningAttsString; ?> ></canvas>
        </div>
    </div>



</div>
<div class="wizcampaign-sections-row">
    <div class="wizcampaign-section inset span2">
        <div class="wizcampaign-section-title-area">
            <h4>Purchases by Product</h4>
            <div class="wizcampaign-section-icons">

            </div>
        </div>
        <div class="tinyTableWrapper">
            <?php
            $byProductHeaders = [
                'Product' => '45%',
                'Topics' => '25%',
                'Purchases' => '10%',
                'Revenue' => '20%',
            ];

            $purchasesByProduct = transfigure_purchases_by_product($standardChartPurchases);

            generate_mini_table($byProductHeaders, $purchasesByProduct);

            ?>
        </div>
    </div>
    <div class="wizcampaign-section inset span2">
        <div class="wizcampaign-section-title-area">
            <h4>Purchases by Location</h4>
            <div class="wizcampaign-section-icons">
                
            </div>
        </div>
        <div class="tinyTableWrapper">
            <?php 
            // Group purchases by location
            $locationData = idwiz_group_purchases_by_location($standardChartPurchases);

            // Convert the grouped data into a format suitable for the table generator
            $tableData = [];
            foreach ($locationData as $location => $data) {
                $tableData[] = [
                    'Location' => $location,
                    'Purchases' => $data['Purchases'],
                    'Revenue' => '$' . number_format($data['Revenue'], 2)
                ];
            }

            // Define headers for the table
            $headers = [
                'Location' => 'auto',
                'Purchases' => 'auto',
                'Revenue' => 'auto'
            ];

            // Generate the table
            generate_mini_table($headers, $tableData);
             ?>
        </div>
    </div>
    

    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Promo Code Use</h4>
            <div>
                <?php

                $promoCodeData = prepare_promo_code_summary_data($standardChartPurchases); ?>
                <?php echo $promoCodeData['ordersWithPromoCount'] ?>/
                <?php echo $promoCodeData['totalOrderCount'] ?> (
                <?php echo $promoCodeData['percentageWithPromo'] ?>%)
            </div>
        </div>
        <div class="tinyTableWrapper">
            <?php generate_mini_table($promoCodeData['promoHeaders'], $promoCodeData['promoData']); ?>
        </div>
    </div>
</div>