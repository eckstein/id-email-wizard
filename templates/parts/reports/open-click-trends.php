<?php
// Get cohorts from URL
$selectedCohorts = isset($_GET['cohorts']) ? explode(',', $_GET['cohorts']) : ['all'];

// Get campaigns for the current year range
$campaignsInRange = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate, 'messageMedium' => 'Email', 'sortBy' => 'startAt', 'sort' => 'ASC']);

// Get campaigns for the previous year range
$lastYearStart = date('Y-m-d', strtotime('-1 year', strtotime($startDate)));
$lastYearEnd = date('Y-m-d', strtotime('-1 year', strtotime($endDate)));

$lastYearCampaigns = get_idwiz_campaigns(['startAt_start' => $lastYearStart, 'startAt_end' => $lastYearEnd, 'messageMedium' => 'Email', 'sortBy' => 'startAt', 'sort' => 'ASC']);

?>
<div class="wizcampaign-section inset">
    <h2>Open rate per campaign (YoY)</h2>
    <div class="wizChartWrapper">
        <canvas class="opensReport wiz-canvas" data-chartid="opensReport" data-campaignids='<?php echo json_encode(array_column($campaignsInRange, 'id')); ?>' data-lastYearCampaignIds='<?php echo json_encode(array_column($lastYearCampaigns, 'id')); ?>' data-charttype="line" data-campaignType="blast" data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>" data-cohorts='<?php echo json_encode($selectedCohorts); ?>' data-minsends="<?php echo $setMin = isset($_GET['minSendSize']) ? $_GET['minSendSize'] : 1; ?>" data-maxsends="<?php echo $setMin = isset($_GET['maxSendSize']) ? $_GET['maxSendSize'] : 500000; ?>">
        </canvas>
    </div>
</div>
<div class="wizcampaign-section inset">
    <h2>Click rate per campaign (YoY)</h2>
    <div class="wizChartWrapper">
        <canvas class="ctrReport wiz-canvas" data-chartid="ctrReport" data-campaignids='<?php echo json_encode(array_column($campaignsInRange, 'id')); ?>' data-lastYearCampaignIds='<?php echo json_encode(array_column($lastYearCampaigns, 'id')); ?>' data-charttype="line" data-campaignType="blast" data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>" data-cohorts='<?php echo json_encode($selectedCohorts); ?>' data-max-y="3" data-minsends="<?php echo $setMin = isset($_GET['minSendSize']) ? $_GET['minSendSize'] : 1; ?>" data-maxsends="<?php echo $setMin = isset($_GET['maxSendSize']) ? $_GET['maxSendSize'] : 500000; ?>">
        </canvas>
    </div>
</div>
<div class="wizcampaign-section inset">
    <h2>Clicks-to-Opens per campaign (YoY)</h2>
    <div class="wizChartWrapper">
        <canvas class="ctoReport wiz-canvas" data-chartid="ctoReport" data-campaignids='<?php echo json_encode(array_column($campaignsInRange, 'id')); ?>' data-lastYearCampaignIds='<?php echo json_encode(array_column($lastYearCampaigns, 'id')); ?>' data-charttype="line" data-campaignType="blast" data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>" data-cohorts='<?php echo json_encode($selectedCohorts); ?>' data-max-y="5" data-minsends="<?php echo $setMin = isset($_GET['minSendSize']) ? $_GET['minSendSize'] : 1; ?>" data-maxsends="<?php echo $setMin = isset($_GET['maxSendSize']) ? $_GET['maxSendSize'] : 500000; ?>">
        </canvas>
    </div>
</div>