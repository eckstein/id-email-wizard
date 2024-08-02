<?php
// Get cohorts from URL
$selectedCohorts = isset($_GET['cohorts']) ? explode(',', $_GET['cohorts']) : ['all'];
$excludedCohorts = isset($_GET['exclude_cohorts']) ? explode(',', $_GET['exclude_cohorts']) : [];

// Get campaigns for the current year range
$campaignsInRange = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate, 'messageMedium' => 'Email', 'sortBy' => 'startAt', 'sort' => 'ASC']);

// Get campaigns for the previous year range
$lastYearStart = date('Y-m-d', strtotime('-1 year', strtotime($startDate)));
$lastYearEnd = date('Y-m-d', strtotime('-1 year', strtotime($endDate)));

$lastYearCampaigns = get_idwiz_campaigns(['startAt_start' => $lastYearStart, 'startAt_end' => $lastYearEnd, 'messageMedium' => 'Email', 'sortBy' => 'startAt', 'sort' => 'ASC']);

$showCharts = [
    'opensReport' => [
        'title' => 'Open rate per campaign (YoY)',
        'minMetric' => $_GET['minOpenRate'] ?? 0,
        'maxMetric' => $_GET['maxOpenRate'] ?? 100

    ],
    'ctrReport' => [
        'title' => 'Click rate per campaign (YoY)',
        'minMetric' => isset($_GET['minClickRate']) ? $_GET['minClickRate'] * 100 : 0,
        'maxMetric' => isset($_GET['maxClickRate']) ? $_GET['maxClickRate'] * 100 : 2000

    ],
    'ctoReport' => [
        'title' => 'Clicks-to-Opens (YoY)',
        'minMetric' => isset($_GET['minCtoRate']) ? $_GET['minCtoRate'] * 100 : 0,
        'maxMetric' => isset($_GET['maxCtoRate']) ? $_GET['maxCtoRate'] * 100 : 4000

    ]
];

foreach ($showCharts as $chartId => $chartOptions) {
?>
    <div class="engagement-chart" id="<?php echo $chartId; ?>-chart">
        <div class="wizcampaign-section inset">
            <h2><?php echo $chartOptions['title'] ?? 'Untitled Chart'; ?></h2>
            <div class="wizChartWrapper">
                <canvas class="<?php echo $chartId; ?> wiz-canvas" data-chartid="<?php echo $chartId; ?>" data-campaignids='<?php echo json_encode(array_column($campaignsInRange, 'id')); ?>' data-lastYearCampaignIds='<?php echo json_encode(array_column($lastYearCampaigns, 'id')); ?>' data-charttype="line" data-campaignType="blast" data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>" data-cohorts='<?php echo json_encode($selectedCohorts); ?>' data-cohorts-exclude='<?php echo json_encode($excludedCohorts); ?>' data-minsends="<?php echo isset($_GET['minSendSize']) ? $_GET['minSendSize'] : 1; ?>" data-maxsends="<?php echo isset($_GET['maxSendSize']) ? $_GET['maxSendSize'] : 500000; ?>" data-minmetric="<?php echo $chartOptions['minMetric']; ?>" data-maxmetric="<?php echo $chartOptions['maxMetric']; ?>">
                </canvas>
            </div>
        </div>
    </div>
<?php
}
?>