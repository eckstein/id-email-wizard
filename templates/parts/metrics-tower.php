<?php


$lastMonthDifference = parse_idwiz_metric_rate($metricValues['thisMonth']) - parse_idwiz_metric_rate($metricValues['lastMonth']);
$lastYearDifference = parse_idwiz_metric_rate($metricValues['thisMonth']) - parse_idwiz_metric_rate($metricValues['lastYear']);


// Determine if a dollar sign should be prepended
$dollarSign = ($metricType === 'revenue' || $metricType === 'gaRevenue' || $metricType === 'aov') ? '$' : '';

$thisMonthMetricRateFormatted = formatRollupMetric($metricValues['thisMonth'], $metricFormat, false);
$lastMonthMetricRateFormatted = formatRollupMetric($metricValues['lastMonth'], $metricFormat, false);
$lastYearMonthMetricRateFormatted = formatRollupMetric($metricValues['lastYear'], $metricFormat, false);

$monthDifferenceFormatted = formatRollupMetric($lastMonthDifference, $metricFormat, true);
$yearDifferenceFormatted = formatRollupMetric($lastYearDifference, $metricFormat, true);

$monthDifferenceClass = ($lastMonthDifference >= 0) ? 'positive' : 'negative';
$yearDifferenceClass = ($lastYearDifference >= 0) ? 'positive' : 'negative';

// Reverse color coding for 'unsubs'
if ($metricType == 'wizUnsubRate' || $metricType == 'wizCompRate') {
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