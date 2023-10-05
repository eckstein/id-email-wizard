<?php

// Create DateTime object for the first day of the current dashboard month and year
$currentMonthDateTime = new DateTime("first day of $monthName $year");

// Clone DateTime object to calculate the last month and last year
$lastMonthDateTime = clone $currentMonthDateTime;
$lastYearDateTime = clone $currentMonthDateTime;

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




$thisMonthMetricRate = get_wizcampaigns_metric_rate($campaignIds, $metricType, $startDate, $endDate);

$lastMonthCampaigns = get_idwiz_campaigns(['startAt_start'=>$lastMonthStart, 'startAt_end'=>$lastMonthEnd, 'type'=>'Blast']);
$lastMonthMetricRate = get_wizcampaigns_metric_rate(array_column($lastMonthCampaigns, 'id'), $metricType, $lastMonthStart, $lastMonthEnd);
$lastMonthDifference = parse_idwiz_metric_rate($thisMonthMetricRate) - parse_idwiz_metric_rate($lastMonthMetricRate);

$lastYearMonthCampaigns = get_idwiz_campaigns(['startAt_start'=>$lastYearMonthStart, 'startAt_end'=>$lastYearMonthEnd, 'type'=>'Blast']);
$lastYearMonthMetricRate = get_wizcampaigns_metric_rate(array_column($lastYearMonthCampaigns, 'id'), $metricType, $lastYearMonthStart, $lastYearMonthEnd);
$lastYearDifference = parse_idwiz_metric_rate($thisMonthMetricRate) - parse_idwiz_metric_rate($lastYearMonthMetricRate);

// Determine if a dollar sign should be prepended
$dollarSign = ($metricType === 'revenue' || $metricType === 'gaRevenue' || $metricType === 'aov') ? '$' : '';

$thisMonthMetricRateFormatted = formatTowerMetric($thisMonthMetricRate, $metricFormat, false);
$lastMonthMetricRateFormatted = formatTowerMetric($lastMonthMetricRate, $metricFormat, false);
$lastYearMonthMetricRateFormatted = formatTowerMetric($lastYearMonthMetricRate, $metricFormat, false);
$monthDifferenceFormatted = formatTowerMetric($lastMonthDifference, $metricFormat, true);
$yearDifferenceFormatted = formatTowerMetric($lastYearDifference, $metricFormat, true);

$monthDifferenceClass = ($lastMonthDifference >= 0) ? 'positive' : 'negative';
$yearDifferenceClass = ($lastYearDifference >= 0) ? 'positive' : 'negative';

// Reverse color coding for 'unsubs'
if ($metricType == 'unsubs') {
    $monthDifferenceClass = ($lastMonthDifference >= 0) ? 'negative' : 'positive';
    $yearDifferenceClass = ($lastYearDifference >= 0) ? 'negative' : 'positive';
}

?>
<div class="metrics-tower" id="<?php echo $sectionID; ?>">
    <h5>
        <?php echo $sectionTitle; ?>
    </h5>
    <div class="metric-item this-month">
        <div class="metric-label">
            <?php if (isset($_GET['view']) && $_GET['view'] != 'FY' || !isset($_GET['view'])) { ?>
            This Month
            <?php } ?>
        </div>
        <div class="metric-value">
            <?php
            echo $thisMonthMetricRateFormatted;
            ?>
        </div>
    </div>
    <?php if (isset($_GET['view']) && $_GET['view'] != 'FY' || !isset($_GET['view'])) { ?>
    <div class="metric-item">
        <div class="metric-label">
            Prev. Month
        </div>
        <div class="metric-value">
            <?php
            echo ($lastMonthMetricRateFormatted); ?>
            <div class="metric-difference <?php echo $monthDifferenceClass; ?>">
                <?php echo $monthDifferenceFormatted; ?>
            </div>
        </div>
    </div>
    <div class="metric-item">
        <div class="metric-label">
            Prev. Year
        </div>
        <div class="metric-value">
            <?php
            echo ($lastYearMonthMetricRateFormatted); ?>
            <div class="metric-difference <?php echo $yearDifferenceClass; ?>">
                <?php echo $yearDifferenceFormatted; ?>
            </div>
        </div>
    </div>
    <?php } ?>
</div>