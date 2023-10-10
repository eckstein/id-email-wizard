<?php
// Get query params
$campaignType = $_GET['campaignType'] ?? ['Blast'];
$sendAtStart = $_GET['sendAtStart'] ?? '2021-10-01';
$sendAtEnd = $_GET['sendAtEnd'] ?? date('Y-m-d', time());
$minSends = $_GET['minSends'] ?? 1000;
$maxSends = $_GET['maxSends'] ?? 500000;

$topPerformerCampaignArts = [
    'type' => $campaignType,
    'startAt_start' => $sendAtStart,
    'startAt_end' => $sendAtEnd,
];

$topPerformerCampaigns = get_idwiz_campaigns($topPerformerCampaignArts);
$filteredTopPerformers = [];
foreach ($topPerformerCampaigns as $campaign) {
    $topPerformerMetrics = get_idwiz_metric($campaign['id']);
    if ($topPerformerMetrics['uniqueEmailSends'] >= $minSends && $topPerformerMetrics['uniqueEmailSends'] <= $maxSends) {
        $filteredTopPerformers[] = $campaign;
    }
}
$campaignIds = array_column($filteredTopPerformers, 'id');
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
        <button type="submit" class="wiz-button green">Update Chart</button>
    </div>
</form>

<div class="wizcampaign-sections-row">
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Top Repeat Purchases</h4>
            <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

            </div>
        </div>
        <div class="wizcampaign-section-content">

            <?php

            $topRepeatCampaignsData = get_campaigns_with_most_returning_customers($filteredTopPerformers);

            // Format the data for the mini table
            $topRepeatHeaders = [
                'Date' => '25%',
                'Campaign' => '50%',
                'Returning' => '25%'
            ];
            $topRepeatsData = [];
            foreach ($topRepeatCampaignsData as $topRepeatCampaignId => $topRepeatCount) {
                // Fetch the campaign name for the current campaign ID
                $topRepeatCampaign = get_idwiz_campaign($topRepeatCampaignId);
                $topRepeatCampaignName = isset($topRepeatCampaign['name']) ? $topRepeatCampaign['name'] : 'Unknown Campaign';

                // Construct the link
                $linkedTopRepeatCampaignName = '<a href="'.get_bloginfo('url').'/metrics/campaign/?id=' . $topRepeatCampaignId . '">' . htmlspecialchars($topRepeatCampaignName) . '</a>';

                $topRepeatsData[] = [
                    'Date' => date('m/d/Y', $topRepeatCampaign['startAt'] / 1000),
                    'Campaign' => new RawHtml($linkedTopRepeatCampaignName),
                    'Returning' => $topRepeatCount
                ];
            }

            // Generate the mini table
            generate_mini_table($topRepeatHeaders, $topRepeatsData);
            ?>
        </div>
    </div>
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Top Opened Campaigns</h4>
            <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

            </div>
        </div>
        <div class="wizcampaign-section-content">

            <?php

            $topOpenedCampaigns = get_campaigns_by_open_rate($filteredTopPerformers);

            // Format the data for the mini table
            $topOpenedHeaders = [
                'Date' => '25%',
                'Campaign' => '50%',
                'Open Rate' => '25%'
            ];
            $topOpenedData = [];
            foreach ($topOpenedCampaigns as $topOpenedCampaign) {
                // Fetch the campaign name for the current campaign ID
                $topOpenedCampaignName = $topOpenedCampaign['name'];
                $topOpenedCampaignMetrics = get_idwiz_metric($topOpenedCampaign['id']);

                // Construct the link
                $linkedTopOpenedCampaignName = '<a href="'.get_bloginfo('url').'/metrics/campaign/?id=' . $topOpenedCampaign['id'] . '">' . htmlspecialchars($topOpenedCampaignName) . '</a>';

                $topOpenedData[] = [
                    'Date' => date('m/d/Y', $topOpenedCampaign['startAt'] / 1000),
                    'Campaign' => new RawHtml($linkedTopOpenedCampaignName),
                    'Open Rate' => number_format($topOpenedCampaignMetrics['wizOpenRate'], 2) . '%',
                ];
            }

            // Generate the mini table
            generate_mini_table($topOpenedHeaders, $topOpenedData);
            ?>
        </div>
    </div>
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Top Clicked Campaigns</h4>
            <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

            </div>
        </div>
        <div class="wizcampaign-section-content">

            <?php

            $topCtrCampaigns = get_campaigns_by_ctr($filteredTopPerformers);

            // Format the data for the mini table
            $topCtrHeaders = [
                'Date' => '25%',
                'Campaign' => '50%',
                'CTR' => '25%'
            ];
            $topCtrData = [];
            foreach ($topCtrCampaigns as $topCtrCampaign) {
                // Fetch the campaign name for the current campaign ID
                $topCtrCampaignName = $topCtrCampaign['name'];
                $topCtrCampaignMetrics = get_idwiz_metric($topCtrCampaign['id']);

                // Construct the link
                $linkedTopCtrCampaignName = '<a href="'.get_bloginfo('url').'/metrics/campaign/?id=' . $topCtrCampaign['id'] . '">' . htmlspecialchars($topCtrCampaignName) . '</a>';

                $topCtrData[] = [
                    'Date' => date('m/d/Y', $topCtrCampaign['startAt'] / 1000),
                    'Campaign' => new RawHtml($linkedTopCtrCampaignName),
                    'CTR' => number_format($topCtrCampaignMetrics['wizCtr'], 2) . '%',
                ];
            }

            // Generate the mini table
            generate_mini_table($topCtrHeaders, $topCtrData);
            ?>
        </div>
    </div>

</div>