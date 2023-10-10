<div class="wizcampaign-sections-row">

    <div class="wizcampaign-section inset span2" id="email-info">
        <div class="wizcampaign-section-title-area">
        <h4>Purchases by Date</h4>
        </diV>
        <div class="wizChartWrapper">
            <canvas class="purchByDate wiz-canvas" data-chartid="purchasesByDate"
                data-campaignids='<?php echo json_encode($standardChartCampaignIds); ?>' data-charttype="bar"></canvas>
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
            <canvas class="purchByDivision wiz-canvas" data-chartid="purchasesByDivision"
                data-campaignids='<?php echo json_encode($standardChartCampaignIds); ?>' data-charttype="bar"></canvas>
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
            <canvas class="purchByTopic wiz-canvas" data-chartid="purchasesByTopic"
                data-campaignids='<?php echo json_encode($standardChartCampaignIds); ?>' data-charttype="pie"></canvas>
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

        <?php
        $byProductHeaders = [
            'Product' => '30%',
            'Topics' => '30%',
            'Purchases' => '15%',
            'Revenue' => '15%'
        ];

        $purchasesByProduct = transfigure_purchases_by_product($standardChartPurchases);

        generate_mini_table($byProductHeaders, $purchasesByProduct);

        ?>
    </div>
    <div class="wizcampaign-section inset span2">
        <div class="wizcampaign-section-title-area">
            <h4>Purchases by Location</h4>
            <div class="wizcampaign-section-icons">
                <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i
                    class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
            </div>
        </div>
        <div class="wizChartWrapper">
            <canvas class="purchByLocation wiz-canvas" data-chartid="purchasesByLocation"
                data-campaignids='<?php echo json_encode($standardChartCampaignIds); ?>' data-charttype="bar"></canvas>
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
            <canvas class="wiz-canvas" id="customerTypeChart" data-charttype="pie" data-chartid="customerTypesChart"
                data-campaignids='<?php echo json_encode($standardChartCampaignIds); ?>'></canvas>
        </div>
    </div>

    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Promo Code Use</h4>
            <div>
                <?php $promoCodeData = prepare_promo_code_summary_data($standardChartPurchases); ?>
                <?php echo $promoCodeData['ordersWithPromoCount'] ?>/<?php echo $promoCodeData['totalOrderCount'] ?> (
                <?php echo $promoCodeData['percentageWithPromo'] ?>%)
            </div>
        </div>
        <div class="wizcampaign-section-scrollwrap">
            <?php generate_mini_table($promoCodeData['promoHeaders'], $promoCodeData['promoData']); ?>
        </div>
    </div>
</div>
<div class="wizcampaign-sections-row noWrap">



</div>