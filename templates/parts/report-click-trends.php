<?php
// Get query params
$campaignType = $_GET['campaignType'] ?? ['Blast'];
$sendAtStart = $_GET['sendAtStart'] ?? '2021-10-01';
$sendAtEnd = $_GET['sendAtEnd'] ?? date('Y-m-d', time());
$minSends = $_GET['minSends'] ?? 1000;
$maxSends = $_GET['maxSends'] ?? 500000;

// Report specific
$minCtr = $_GET['minCtr'] ?? 0;
$maxCtr = $_GET['maxCtr'] ?? 100;

$opensCampaignArgs = [
    'type' => $campaignType,
    'startAt_start' => $sendAtStart,
    'startAt_end' => $sendAtEnd,
];

$clicksCampaigns = get_idwiz_campaigns($opensCampaignArgs);
$filteredClicksCampaigns = [];
foreach ($clicksCampaigns as $campaign) {
    $clickCampaignMetrics = get_idwiz_metric($campaign['id']);
    if ($clickCampaignMetrics['uniqueEmailSends'] >= $minSends && $clickCampaignMetrics['uniqueEmailSends'] <= $maxSends) {
        if ($clickCampaignMetrics['wizCtr'] >= $minCtr && $clickCampaignMetrics['wizCtr'] <= $maxCtr) {
            $filteredClicksCampaigns[] = $campaign;
        }
    }
}
$campaignIds = array_column($filteredClicksCampaigns, 'id');
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
        <label for="minCtr">Min CTR %:</label>
        <input type="number" id="minCtr" name="minCtr" value="<?php echo $minCtr; ?>"
            class="form-control">
    </div>

    <div class="form-group">
        <label for="maxCtr">Max CTR %:</label>
        <input type="number" id="maxCtr" name="maxCtr" value="<?php echo $maxCtr; ?>"
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
                <h4>Top CTR</h4>
                <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

                </div>
            </div>
            <div class="wizcampaign-section-content">
                <?php
                $campaignClickRates = [];

                foreach ($filteredClicksCampaigns as $filteredCampaign) {
                    $filteredCampaignMetric = get_idwiz_metric($filteredCampaign['id']);
                    $campaignClickRates[] = [
                        'Campaign Name' => new RawHtml('<a href="https://localhost/metrics/campaign/?id=' . $filteredCampaign['id'] . '">' . $filteredCampaign['name'] . '</a>'),
                        'Click Rate' => number_format($filteredCampaignMetric['wizCtr'], 2) . '%',
                        'Date' => date('m/d/Y', $filteredCampaign['startAt'] / 1000),
                    ];
                }

                usort($campaignClickRates, function ($a, $b) {
                    return floatval($b['Click Rate']) <=> floatval($a['Click Rate']);
                });


                $ctrHeaders = [
                    'Date' => '25%',
                    'Campaign Name' => '50%',
                    'Click Rate' => '25%'
                ];

                // Generate the table
                generate_mini_table($ctrHeaders, $campaignClickRates);
                ?>
            </div>
        </div>

        <div class="wizcampaign-section ">
            <div class="wizcampaign-section-title-area">
                <h4>Top CTO</h4>
                <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

                </div>
            </div>
            <div class="wizcampaign-section-content">
                <?php
                $campaignCtoRates = [];

                foreach ($filteredClicksCampaigns as $filteredCampaign) {
                    $filteredCampaignMetric = get_idwiz_metric($filteredCampaign['id']);
                    $campaignCtoRates[] = [
                        'Campaign Name' => new RawHtml('<a href="https://localhost/metrics/campaign/?id=' . $filteredCampaign['id'] . '">' . $filteredCampaign['name'] . '</a>'),
                        'Click Rate' => number_format($filteredCampaignMetric['wizCto'], 2) . '%',
                        'Date' => date('m/d/Y', $filteredCampaign['startAt'] / 1000),
                    ];
                }

                usort($campaignCtoRates, function ($a, $b) {
                    return floatval($b['Click Rate']) <=> floatval($a['Click Rate']);
                });


                $ctoHeaders = [
                    'Date' => '25%',
                    'Campaign Name' => '50%',
                    'Click Rate' => '25%'
                ];

                // Generate the table
                generate_mini_table($ctoHeaders, $campaignCtoRates);
                ?>
            </div>
        </div>
    </div>

    <div class="wizcampaign-section inset span3">
        <div class="wizcampaign-sections-row">
            <div class="wizcampaign-section">
                <div class="wizcampaign-section-title-area">
                    <h4>CTR by Date</h4>
                    <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

                    </div>
                </div>
                <div class="wizcampaign-section-content">
                    <div class="wizChartWrapper">
                        <canvas class="wiz-canvas" data-chartid="ctrByDate" data-charttype="line"
                            data-campaignids='<?php echo json_encode($campaignIds); ?>' data-xAxisDate="true"></canvas>

                    </div>
                </div>
            </div>

        </div>

        <div class="wizcampaign-sections-row">
            <div class="wizcampaign-section">
                <div class="wizcampaign-section-title-area">
                    <h4>CTO by Date</h4>
                    <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

                    </div>
                </div>
                <div class="wizcampaign-section-content">
                    <div class="wizChartWrapper">
                        <canvas class="wiz-canvas" data-chartid="ctoByDate" data-charttype="line"
                            data-campaignids='<?php echo json_encode($campaignIds); ?>' data-xAxisDate="true"></canvas>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>