<?php
// Get query params
$campaignType = $_GET['campaignType'] ?? ['Blast'];
$sendAtStart = $_GET['sendAtStart'] ?? '2021-10-01';
$sendAtEnd = $_GET['sendAtEnd'] ?? date('Y-m-d', time());
$minSends = $_GET['minSends'] ?? 1000;
$maxSends = $_GET['maxSends'] ?? 500000;
$minOpenRate = $_GET['minOpenRate'] ?? 0;
$maxOpenRate = $_GET['maxOpenRate'] ?? 100;

$opensCampaignArgs = [
    'type' => $campaignType,
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
?>

<form action="" method="GET" class="report-controls-form">
    <div class="form-group">
        <label>Campaign Type:</label>
        <div class="check-group">
            <input type="checkbox" id="Blast" name="campaignType[]" value="Blast" <?php echo in_array('Blast', $campaignType) ? 'checked' : ''; ?>>
            <label for="Blast">Blast</label><br>

            <input type="checkbox" id="Triggered" name="campaignType[]" value="Triggered" <?php echo in_array('Triggered', $campaignType) ? 'checked' : ''; ?>>
            <label for="Triggered">Triggered</label>
        </div>
    </div>


    <div class="form-group">
        <label for="sendAtStart">Start Date:</label>
        <input type="date" id="sendAtStart" name="sendAtStart" value="<?php echo $sendAtStart; ?>" class="form-control">
    </div>

    <div class="form-group">
        <label for="sendAtEnd">End Date:</label>
        <input type="date" id="sendAtEnd" name="sendAtEnd" value="<?php echo $sendAtEnd; ?>" class="form-control">
    </div>

    <div class="form-group">
        <label for="minSends">Min Sends:</label>
        <input type="number" id="minSends" name="minSends" value="<?php echo $minSends; ?>" class="form-control">
    </div>

    <div class="form-group">
        <label for="maxSends">Max Sends:</label>
        <input type="number" id="maxSends" name="maxSends" value="<?php echo $maxSends; ?>" class="form-control">
    </div>

    <div class="form-group">
        <label for="minOpenRate">Min Open %:</label>
        <input type="number" id="minOpenRate" name="minOpenRate" value="<?php echo $minOpenRate; ?>"
            class="form-control">
    </div>

    <div class="form-group">
        <label for="maxOpenRate">Max Open %:</label>
        <input type="number" id="maxOpenRate" name="maxOpenRate" value="<?php echo $maxOpenRate; ?>"
            class="form-control">
    </div>
    <div class="form-group">
        <button type="submit" class="wiz-button green">Update Chart</button>
    </div>
</form>

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
                        'Campaign Name' => new RawHtml('<a href="https://localhost/metrics/campaign/?id=' . $filteredCampaign['id'] . '">' . $filteredCampaign['name'] . '</a>'),
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
                        <canvas class="wiz-canvas" data-chartid="opensByDate" data-charttype="line"
                            data-campaignids='<?php echo json_encode($campaignIds); ?>' data-xAxisDate="true"></canvas>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>