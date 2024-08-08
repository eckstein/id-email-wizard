<?php
$fy24Campaigns = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate, 'type' => ['Blast']]);
$maxHours = $_GET['maxhours'] ?? 72;
$openThreshold = $_GET['threshold'] ?? 10;
$clickThreshold = $openThreshold * .1;

$byHourAttsString =
    'data-startdate="' . $startDate .
    '" data-enddate="' . $endDate .
    '" data-maxhours="' . $maxHours . '"';
?>
<div class="wizChartWrapper">
    <canvas id="opensByHourChart" class="engagementByHourChart" data-chartid="engagementByHour" data-threshold="<?php echo $openThreshold; ?>" data-campaignids='<?php echo json_encode(array_column($fy24Campaigns, 'id')); ?>' <?php echo $byHourAttsString;
                                                                                                                                                                                                                                    ?>></canvas>
</div>
<div class="wizChartWrapper">
    <canvas id="clicksByHourChart" class="engagementByHourChart" data-chartid="engagementByHour" data-threshold="<?php echo $clickThreshold; ?>" data-campaignids='<?php echo json_encode(array_column($fy24Campaigns, 'id')); ?>' <?php echo $byHourAttsString;
                                                                                                                                                                                                                                    ?>></canvas>
</div>