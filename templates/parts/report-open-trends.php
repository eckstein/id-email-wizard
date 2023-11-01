<?php
// Get query params
$campaignTypes = $_GET['campaignType'] ?? ['Blast'];
$sendAtStart = $_GET['sendAtStart'] ?? '2021-11-01';
$sendAtEnd = $_GET['sendAtEnd'] ?? date('Y-m-d', time());
$minSends = $_GET['minSends'] ?? 1000;
$maxSends = $_GET['maxSends'] ?? 500000;
$minOpenRate = $_GET['minOpenRate'] ?? 0;
$maxOpenRate = $_GET['maxOpenRate'] ?? 100;

$opensCampaignArgs = [
    'type' => $campaignTypes,
    'startAt_start' => $sendAtStart,
    'startAt_end' => $sendAtEnd,
];

$opensCampaigns = get_idwiz_campaigns($opensCampaignArgs);
$filteredOpenCampaigns = [];
foreach ($opensCampaigns as $campaign) {
    $openCampaignMetrics = get_idwiz_metric($campaign['id']);
    if ($openCampaignMetrics['uniqueEmailSends'] >= $minSends && $openCampaignMetrics['uniqueEmailSends'] <= $maxSends) {
        if ($openCampaignMetrics['wizOpenRate'] >= $minOpenRate && $openCampaignMetrics['wizOpenRate'] <= $maxOpenRate) {
            $filteredOpenCampaigns[] = $campaign;
        }
    }
}
$campaignIds = array_column($filteredOpenCampaigns, 'id');

include('parts/reports-filter-form.php');

?>
<div class="wizcampaign-sections-row">
    <div class="wizcampaign-section inset flexCol">
        <div class="wizcampaign-section">
            <div class="wizcampaign-section-title-area">
                <h4>Top Opens</h4>
                <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

                </div>
            </div>
            <div class="wizcampaign-section-content">
                <?php
                $openRate = [];

                foreach ($filteredOpenCampaigns as $filteredCampaign) {
                    $filteredCampaignMetric = get_idwiz_metric($filteredCampaign['id']);
                    $campaignOpenRates[] = [
                        'Campaign Name' => new RawHtml('<a href="'.get_bloginfo('url').'/metrics/campaign/?id=' . $filteredCampaign['id'] . '">' . $filteredCampaign['name'] . '</a>'),
                        'Open Rate' => number_format($filteredCampaignMetric['wizOpenRate'], 2) . '%',
                        'Date' => date('m/d/Y', $filteredCampaign['startAt'] / 1000),
                    ];
                }

                usort($campaignOpenRates, function ($a, $b) {
                    return floatval($b['Open Rate']) <=> floatval($a['Open Rate']);
                });


                $openRateHeaders = [
                    'Date' => '25%',
                    'Campaign Name' => '50%',
                    'Open Rate' => '25%'
                ];

                // Generate the table
                generate_mini_table($openRateHeaders, $campaignOpenRates);
                ?>
            </div>
        </div>

        
    </div>

    <div class="wizcampaign-section inset span3">
        <div class="wizcampaign-sections-row">
            <div class="wizcampaign-section">
                <div class="wizcampaign-section-title-area">
                    <h4>Opens by Date</h4>
                    <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

                    </div>
                </div>
                <div class="wizcampaign-section-content">
                    <div class="wizChartWrapper">
                        <canvas class="wiz-canvas" data-chartid="opensByDate" data-charttype="line" data-campaignids='<?php echo json_encode($campaignIds); ?>'
                            data-startdate="<?php echo $sendAtStart; ?>" data-enddate="<?php echo $sendAtEnd; ?>" data-campaigntypes='<?php echo json_encode($campaignTypes); ?>'></canvas>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>