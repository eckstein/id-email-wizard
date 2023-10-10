<?php
// Get query params
$campaignType = $_GET['campaignType'] ?? ['Blast'];
$currentFyStart = strtotime('November 1 last year');
$sendAtStart = $_GET['sendAtStart'] ?? date('Y-m-d', $currentFyStart);
$sendAtEnd = $_GET['sendAtEnd'] ?? date('Y-m-d', time());
$minSends = $_GET['minSends'] ?? 1000;
$maxSends = $_GET['maxSends'] ?? 500000;
$minOpenRate = $_GET['minOpenRate'] ?? 0;
$maxOpenRate = $_GET['maxOpenRate'] ?? 100;

$slCampaignArgs = [
    'type' => $campaignType,
    'startAt_start' => $sendAtStart,
    'startAt_end' => $sendAtEnd,
    'messageMedium' => 'Email'
];

$slCampaigns = get_idwiz_campaigns($slCampaignArgs);
$filteredSlCampaigns = [];
foreach ($slCampaigns as $campaign) {
    $slCampaignMetrics = get_idwiz_metric($campaign['id']);
    if ($slCampaignMetrics['uniqueEmailSends'] >= $minSends && $slCampaignMetrics['uniqueEmailSends'] <= $maxSends) {
        if ($slCampaignMetrics['wizOpenRate'] >= $minOpenRate && $slCampaignMetrics['wizOpenRate'] <= $maxOpenRate) {
            $filteredSlCampaigns[] = $campaign;
        }
    }
}
$campaignIds = array_column($filteredSlCampaigns, 'id');
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
        <button type="submit" class="wiz-button green">Update Table</button>
    </div>
</form>

<div class="wizcampaign-sections-row">
    <div class="wizcampaign-section">
        <div class="wizcampaign-section-content">
            <table class="report-table idemailwiz_table">
                <thead>
                    <tr>
                        <th class="campaignDate">Send Date</th>
                        <th>Campaign Name</th>
                        <th>Subject Line</th>
                        <th>Preview Text</th>
                        <th>Open Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($filteredSlCampaigns as $filteredSlCampaign) { 
                        $slMetric = get_idwiz_metric($filteredSlCampaign['id']);
                        $slTemplate = get_idwiz_templates(['ids'=>[$filteredSlCampaign['id']]]);
                        ?>
                    <tr>
                        <td><?php echo date('m/d/Y', $filteredSlCampaign['startAt'] / 1000); ?></td>
                        <td><a href="<?php get_bloginfo('url'); ?>/metrics/campaign?id=<?php echo $filteredSlCampaign['id']; ?>"><?php echo $filteredSlCampaign['name']; ?></a></td>
                        <td><?php echo $slTemplate[0]['subject']; ?></td>
                        <td><?php echo $slTemplate[0]['preheaderText']; ?></td>
                        <td><?php echo number_format($slMetric['wizOpenRate']) . '%'; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>