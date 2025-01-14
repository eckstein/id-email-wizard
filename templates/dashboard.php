<?php get_header();

// Initialize date variables
date_default_timezone_set('UTC');
$startDate = '';
$endDate = '';
$wizMonth = '';
$wizYear = '';

// Check if startDate and endDate are provided
if (isset($_GET['startDate']) && $_GET['startDate'] !== '' && isset($_GET['endDate']) && $_GET['endDate'] !== '') {
    $startDate = $_GET['startDate'];
    $endDate = $_GET['endDate'];

    // Derive month and year from startDate
    $startDateTime = new DateTime($startDate, new DateTimeZone('UTC'));
    $startDateTime->setTimezone(new DateTimeZone('America/Los_Angeles'));
    $wizMonth = $startDateTime->format('m');
    $wizYear = $startDateTime->format('Y');
} else {
    // Default to current month if no parameters are provided
    $currentDate = new DateTime('now', new DateTimeZone('UTC'));
    $currentDate->setTimezone(new DateTimeZone('America/Los_Angeles'));
    $startDate = $currentDate->format('Y-m-01');
    $endDate = $currentDate->format('Y-m-t');

    $wizMonth = $currentDate->format('m');
    $wizYear = $currentDate->format('Y');
}

$startDateTime = new DateTime($startDate, new DateTimeZone('UTC'));
$startDateTime->setTimezone(new DateTimeZone('America/Los_Angeles'));




$monthDateObj = DateTime::createFromFormat('!m', $wizMonth);
$wizMonthName = $monthDateObj->format('F');
$monthNameLower = strtolower($wizMonthName);

if (isset($_GET['showTriggered']) && $_GET['showTriggered'] === 'true') {
    $campaignTypes = ['Blast', 'Triggered'];
} else {
    $campaignTypes = ['Blast'];
}

$blastCampaignIds = [];
$triggeredCampaignIds = [];
$allPurchases = [];
$gaPurchases = [];
$gaRevenue = [];

// Adjust the start date to include campaigns within the first 7 hours of the day
$adjustedStartDate = new DateTime($startDate, new DateTimeZone('America/Los_Angeles'));
$adjustedStartDate->setTime(0, 0, 0); // Set the time to the beginning of the day
$adjustedStartDateFormatted = $adjustedStartDate->format('Y-m-d');

// Add type filtering at query level
$campaignArgs = [
    'startAt_start' => $adjustedStartDateFormatted, 
    'startAt_end' => $endDate
];

if (!isset($_GET['showTriggered']) || $_GET['showTriggered'] !== 'true') {
    $campaignArgs['type'] = 'Blast';
}

// Fetch all campaigns using the adjusted start date and the original end date
$allCampaigns = get_idwiz_campaigns($campaignArgs);

// Fetch all purchases
$purchaseArgs = [
    'startAt_start' => $startDate,
    'startAt_end' => $endDate
];

// Only fetch purchases for the campaign types we need
if (!isset($_GET['showTriggered']) || $_GET['showTriggered'] !== 'true') {
    $purchaseArgs['campaign_type'] = 'Blast';
}

$allPurchases = get_idwiz_purchases($purchaseArgs);

// Create a map of all campaigns with campaign ID as key
$campaignMap = [];
foreach ($allCampaigns as $campaign) {
    $campaignMap[$campaign['id']] = $campaign;
}

if (isset($_GET['showTriggered']) && $_GET['showTriggered'] === 'true') {
    $displayCampaigns = $allCampaigns;
} else {
    $displayCampaigns = array_filter($allCampaigns, function($campaign) {
        return $campaign['type'] === 'Blast';
    });
}

?>
<article id="post-<?php the_ID(); ?>" <?php post_class('wiz_dashboard'); ?>>
    <header class="wizHeader">
        <h1 class="wizEntry-title" itemprop="name">
            Dashboard
        </h1>
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">

            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">

                    <button class="wiz-button green new-initiative"><i class="fa-regular fa-plus"></i>&nbsp;New
                        Initiative</button>
                    <button class="wiz-button green show-new-template-ui"><i class="fa fa-plus"></i>&nbsp;&nbsp;New
                        Template</button>
                    <button class="wiz-button green doWizSync" data-metricTypes="<?php echo esc_attr(json_encode(array('blast'))); ?>"><i class="fa-solid fa-arrows-rotate"></i>&nbsp;&nbsp;Sync Metrics</button>
                    <a href="<?php echo get_bloginfo('url') . '/sync-station'; ?>" class="wiz-button gray" id="viewSyncStation" title="View sync log">
                        <i class="fa-regular fa-rectangle-list"></i></a>
                    <?php include plugin_dir_path(__FILE__) . 'parts/module-user-settings-form.php'; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="entry-content" itemprop="mainContentOfPage">
        <div class="dashboard-nav-area">
            <div class="dashboard-nav-area-left">

            </div>
            <div class="dashboard-nav-area-main">
                <?php //include plugin_dir_path(__FILE__) . 'parts/dashboard-month-nav.php'; 
                ?>
                <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-date-pickers.php'; ?>
                <?php //include plugin_dir_path(__FILE__) . 'parts/dashboard-date-buttons.php'; 
                ?>
            </div>
            <div class="dashboard-nav-area-right">
                <div class="wizToggle-container">
                    <span class="wizToggle-text">Include Triggered:</span>
                    <div class="wizToggle-switch">
                        <input name="showTriggered" type="checkbox" id="toggleTriggeredDash" class="wizToggle-input wizDashControl">
                        <label for="toggleTriggeredDash" class="wizToggle-label"></label>
                    </div>
                </div>
            </div>
        </div>

        <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-top-row.php'; ?>

        <?php
        // Setup standard chart variables
        $standardChartCampaignIds = array_column($allCampaigns, 'id');
        $standardChartPurchases = $allPurchases;
        $lazyLoadCharts = false;
        include plugin_dir_path(__FILE__) . 'parts/standard-charts.php';
        ?>

        <div class="wizcampaign-sections-row">
            <div class="wizcampaign-section inset" id="dashboard-campaigns-table">
                <div class="wizcampaign-section-content">
                    <table class="idemailwiz_table display" id="dashboard-campaigns" style="width: 100%; vertical-align: middle;" valign="middle" width="100%" data-campaignids='<?php echo json_encode($blastCampaignIds); ?>'>
                        <thead>
                            <tr>
                                <th class="campaignDate">Date</th>

                                <th>Medium</th>
                                <th class="campaignName">Campaign</th>
                                <th class="dtNumVal uniqueSends">Sent</th>
                                <th class="dtNumVal uniqueOpens">Opens</th>
                                <th>Opened</th>
                                <th class="dtNumVal uniqueClicks">Clicks</th>
                                <th>CTR</th>
                                <th>CTO</th>
                                <th class="dtNumVal uniquePurchases">Prchs</th>
                                <th class="campaignRevenue">Rev</th>
                                <th class="gaRevenue">GA Rev</th>
                                <th>CVR</th>
                                <th class="dtNumVal uniqueUnsubs">Unsubs</th>
                                <th>Unsubed</th>
                                <th class="campaignId">ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php



                            if (!empty($displayCampaigns)) {
                                foreach ($displayCampaigns as $campaign) {
                                    $campaignMetrics = get_idwiz_metric($campaign['id']);
                                    $campaignStartStamp = (int)($campaign['startAt'] / 1000);
                                    $readableStartAt = date('m/d/Y', $campaignStartStamp);

                                    $uniqueEmailSends = $campaignMetrics['uniqueEmailSends'] ?? 0;
                                    $uniqueEmailOpens = $campaignMetrics['uniqueEmailOpens'] ?? 0;
                                    $wizOpenRate = $campaignMetrics['wizOpenRate'] ?? 0;
                                    $uniqueEmailClicks = $campaignMetrics['uniqueEmailClicks'] ?? 0;
                                    $wizCtr = $campaignMetrics['wizCtr'] ?? 0;
                                    $wizCto = $campaignMetrics['wizCto'] ?? 0;
                                    $uniquePurchases = $campaignMetrics['uniquePurchases'] ?? 0;
                                    $revenue = $campaignMetrics['revenue'] ?? 0;
                                    $gaRevenue = $campaignMetrics['gaRevenue'] ?? 0;
                                    $wizCvr = $campaignMetrics['wizCvr'] ?? 0;
                                    $uniqueUnsubscribes = $campaignMetrics['uniqueUnsubscribes'] ?? 0;
                                    $wizUnsubRate = $campaignMetrics['wizUnsubRate'] ?? 0;

                            ?>
                                    <tr data-campaignid="<?php echo $campaign['id']; ?>">
                                        <td class="campaignDate">
                                            <?php echo $readableStartAt; ?>
                                        </td>
                                        <td class="campaignType">
                                            <?php echo $campaign['messageMedium']; ?>
                                        </td>
                                        <td class="campaignName"><a href="<?php echo get_bloginfo('wpurl'); ?>/metrics/campaign/?id=<?php echo $campaign['id']; ?>">
                                                <?php echo $campaign['name']; ?>
                                            </a></td>
                                        <td class="uniqueSends">
                                            <?php echo $uniqueEmailSends; ?>
                                        </td>
                                        <td class="uniqueOpens">
                                            <?php echo $uniqueEmailOpens; ?>
                                        </td>

                                        <td class="openRate">
                                            <?php echo number_format($wizOpenRate * 1, '2'); ?>%
                                        </td>
                                        <td class="uniqueClicks">
                                            <?php echo $uniqueEmailClicks; ?>
                                        </td>
                                        <td class="ctr">
                                            <?php echo number_format($wizCtr * 1, 2); ?>%
                                        </td>
                                        <td class="cto">
                                            <?php echo number_format($wizCto * 1, 2); ?>%
                                        </td>
                                        <td class="uniquePurchases">
                                            <?php echo $uniquePurchases; ?>
                                        </td>
                                        <td class="campaignRevenue">
                                            <?php echo $revenue; ?>
                                        </td>
                                        <td class="gaRevenue">
                                            <?php echo $gaRevenue; ?>
                                        </td>
                                        <td class="cvr">
                                            <?php echo number_format($wizCvr * 1, 2); ?>%
                                        </td>
                                        <td class="uniqueUnsubs">
                                            <?php echo $uniqueUnsubscribes; ?>
                                        </td>
                                        <td class="unsubRate">
                                            <?php echo number_format($wizUnsubRate * 1, 2); ?>%
                                        </td>
                                        <td class="unsubRate">
                                            <?php echo $campaign['id'] ?>
                                        </td>
                                    </tr>
                            <?php }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>



</article>
<?php get_footer(); ?>