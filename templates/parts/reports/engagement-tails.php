<?php
$filteredCampaigns = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate, 'type' => ['Blast']]);
$maxHours = $_GET['maxHours'] ?? 72;
$openThreshold = $_GET['openThreshold'] ?? 10;
$clickThreshold = $_GET['clickThreshold'] ?? 10;

$byHourAttsString =
    'data-startdate="' . $startDate .
    '" data-enddate="' . $endDate .
    '" data-maxhours="' . $maxHours . '"';
?>
<div class="byHour-summary">
    Showing latest hours since campaign send with at least <?php echo $openThreshold; ?> Opens for <?php echo count($filteredCampaigns); ?> campaigns.
</div>
<div class="wizChartWrapper">
    <canvas id="opensByHourChart" class="engagementByHourChart" data-chartid="opensByHour" data-openthreshold="<?php echo $openThreshold; ?>" data-campaignids='<?php echo json_encode(array_column($filteredCampaigns, 'id')); ?>' <?php echo $byHourAttsString; ?>></canvas>
</div>
<div class="byHour-summary">
    Showing latest hours since campaign send with at least <?php echo $clickThreshold; ?> Clicks for <?php echo count($filteredCampaigns); ?> campaigns.
</div>
<div class="wizChartWrapper">
    <canvas id="clicksByHourChart" class="engagementByHourChart" data-chartid="clicksByHour" data-clickthreshold="<?php echo $clickThreshold; ?>" data-campaignids='<?php echo json_encode(array_column($filteredCampaigns, 'id')); ?>' <?php echo $byHourAttsString; ?>></canvas>
</div>