<?php
$fy24Campaigns = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate, 'type' => ['Blast']]);

$byHourAttsString = 'data-startdate="' . $startDate . '" data-enddate="' . $endDate . '" data-maxhours="72" data-threshold="10"';
?>
<div class="wizChartWrapper">
    <canvas id="opensByHourChart" class="engagementByHourChart" data-chartid="engagementByHour" data-campaignids='<?php echo json_encode(array_column($fy24Campaigns, 'id')); ?>' <?php echo $byHourAttsString; 
                                                                                                                                                                                    ?>></canvas>
</div>
<div class="wizChartWrapper">
    <canvas id="clicksByHourChart" class="engagementByHourChart" data-chartid="engagementByHour" data-campaignids='<?php echo json_encode(array_column($fy24Campaigns, 'id')); ?>' <?php echo $byHourAttsString; 
                                                                                                                                                                                    ?>></canvas>
</div>