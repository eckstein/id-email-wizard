<?php get_header();

// Initialize date variables
date_default_timezone_set('America/Los_Angeles');
$startDate = '';
$endDate = '';
$wizMonth = '';
$wizYear = '';

// Check if startDate and endDate are provided
if (isset($_GET['startDate']) && $_GET['startDate'] !== '' && isset($_GET['endDate']) && $_GET['endDate'] !== '') {
    $startDate = $_GET['startDate'];
    $endDate = $_GET['endDate'];

    // Derive month and year from startDate
    $startDateTime = new DateTime($startDate);
    $wizMonth = $startDateTime->format('m');
    $wizYear = $startDateTime->format('Y');
} elseif (isset($_GET['view']) && $_GET['view'] === 'FY') {
    $currentDate = new DateTime();
    $currentYear = $currentDate->format('Y');
    $currentMonthAndDay = $currentDate->format('m-d');

    $startYear = ($currentMonthAndDay >= '11-01') ? $currentYear : $currentYear - 1;
    $endYear = $startYear + 1;

    $startDate = "{$startYear}-11-01";
    $endDate = "{$endYear}-10-31";
} else {
    // Default to current month if no parameters are provided
    $startDate = date("Y-m-01");
    $endDate = date("Y-m-t");
    $wizMonth = date("m");
    $wizYear = date("Y");
}

if (isset($_GET['view']) && $_GET['view'] === 'FY') {
    $fyProjections = get_field('fy_' . $endYear . '_projections', 'options');
    $displayGoal = 0;
    foreach ($fyProjections as $wizMonthName => $monthlyGoal) {
        $displayGoal += $monthlyGoal;
    }
} else {
    $monthDateObj = DateTime::createFromFormat('!m', $wizMonth);
    $wizMonthName = $monthDateObj->format('F');
    $monthNameLower = strtolower($wizMonthName);

    $fyProjections = get_field('fy_' . $wizYear . '_projections', 'options');
    if ($fyProjections) {
        $displayGoal = $fyProjections[$monthNameLower];
    } else {
        $displayGoal = 0;
    }
}

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

// Fetch campaigns
$campaigns = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate, 'type' => 'Blast']);
$triggeredCampaigns = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate, 'type' => 'Triggered']);

$blastCampaignIds = array_column($campaigns, 'id');
$triggeredCampaignIds = array_column($triggeredCampaigns, 'id');



$purchaseArgs = ['startAt_start' => $startDate, 'startAt_end' => $endDate, 'shoppingCartItems_utmMedium' => "Email"];
$blastPurchases = [];
$triggeredPurchases = [];
$allPurchases = get_idwiz_purchases($purchaseArgs);

foreach ($allPurchases as $purchase) {
    $purchaseCampaign = get_idwiz_campaign($purchase['campaignId']);
    if ($purchaseCampaign) {
        if ($purchaseCampaign['type'] == 'Blast') {
            $blastPurchases[] = $purchase;
        } else if ($purchaseCampaign['type'] == 'Triggered') {
            $triggeredPurchases[] = $purchase;
        }
    }
}

?>
<article id="post-<?php the_ID(); ?>" <?php post_class('wiz_dashboard'); ?>>
    <header class="wizHeader">
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">
                <h1 class="wizEntry-title" itemprop="name">
                    Dashboard
                </h1>

                <?php
                $currentView = $_GET['view'] ?? 'Month';
                $viewTabs = [
                    ['title' => 'This Month', 'view' => 'Month'],
                    ['title' => 'Fiscal Year', 'view' => 'FY'],
                ];

                get_idwiz_header_tabs($viewTabs, $currentView);

                ?>
            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">

                    <button class="wiz-button green new-initiative"><i class="fa-regular fa-plus"></i>&nbsp;New
                        Initiative</button>
                    <button class="wiz-button green show-new-template-ui"><i class="fa fa-plus"></i>&nbsp;&nbsp;New
                        Template</button>
                    <button class="wiz-button green sync-db sync-everything"><i
                            class="fa-solid fa-rotate"></i>&nbsp;Sync
                        Databases</button>
                </div>
            </div>
        </div>
    </header>
    <div id="wiztable_status_updates"><span class="wiztable_update"></span><span class="wiztable_view_sync_details">View
            sync log&nbsp;<i class="fa-solid fa-chevron-down"></i></span></div>
    <div id="wiztable_status_sync_details">Sync log will show here...</div>
    <div class="entry-content" itemprop="mainContentOfPage">
        <div class="dashboard-nav-area">
            <div class="dashboard-nav-area-left">

            </div>
            <div class="dashboard-nav-area-main">
                <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-month-nav.php'; ?>
                <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-date-buttons.php'; ?>
            </div>
            <div class="dashboard-nav-area-right">
                <div class="wizToggle-container">
                    <span class="wizToggle-text">Include Triggered:</span>
                    <div class="wizToggle-switch">
                        <input name="showTriggered" type="checkbox" id="toggleTriggeredDash"
                            class="wizToggle-input wizDashControl">
                        <label for="toggleTriggeredDash" class="wizToggle-label"></label>
                    </div>
                </div>
            </div>
        </div>

        <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-top-row.php'; ?>

        <?php
        // Setup standard chart variables
        //$standardChartCampaignIds = array_column($campaigns, 'id');
        $standardChartCampaignIds = false;
        $standardChartPurchases = array_merge($blastPurchases, $triggeredPurchases);
        include plugin_dir_path(__FILE__) . 'parts/standard-charts.php';
        ?>

        <div class="wizcampaign-sections-row">
            <div class="wizcampaign-section inset" id="dashboard-campaigns-table">
                <div class="wizcampaign-section-content">
                    <table class="idemailwiz_table display" id="dashboard-campaigns"
                        style="width: 100%; vertical-align: middle;" valign="middle" width="100%"
                        data-campaignids='<?php echo json_encode($blastCampaignIds); ?>'>
                        <thead>
                            <tr>
                                <th class="campaignDate">Date</th>

                                <th>Medium</th>
                                <th class="campaignName">Campaign</th>
                                <th>Sent</th>
                                <th>Opens</th>
                                <th>Opened</th>
                                <th>Clicks</th>
                                <th>CTR</th>
                                <th>CTO</th>
                                <th>Prchs</th>
                                <th>Rev</th>
                                <th>GA Rev</th>
                                <th>CVR</th>
                                <th>Unsubs</th>
                                <th>Unsubed</th>
                                <th class="campaignId">ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php



                            if (!empty($campaigns)) {
                                foreach ($campaigns as $campaign) {
                                    $campaignMetrics = get_idwiz_metric($campaign['id']);
                                    $readableStartAt = date('m/d/Y', $campaign['startAt'] / 1000);
                                    ?>
                                    <tr data-campaignid="<?php echo $campaign['id']; ?>">
                                        <td class="campaignDate">
                                            <?php echo $readableStartAt; ?>
                                        </td>
                                        <td class="campaignType">
                                            <?php echo $campaign['messageMedium']; ?>
                                        </td>
                                        <td class="campaignName"><a
                                                href="<?php echo get_bloginfo('wpurl'); ?>/metrics/campaign/?id=<?php echo $campaign['id']; ?>"
                                                target="_blank">
                                                <?php echo $campaign['name']; ?>
                                            </a></td>
                                        <td class="uniqueSends">
                                            <?php echo number_format($campaignMetrics['uniqueEmailSends']); ?>
                                        </td>
                                        <td class="uniqueOpens">
                                            <?php echo number_format($campaignMetrics['uniqueEmailOpens']); ?>
                                        </td>

                                        <td class="openRate">
                                            <?php echo number_format($campaignMetrics['wizOpenRate'] * 1, '2'); ?>%
                                        </td>
                                        <td class="uniqueClicks">
                                            <?php echo number_format($campaignMetrics['uniqueEmailClicks']); ?>
                                        </td>
                                        <td class="ctr">
                                            <?php echo number_format($campaignMetrics['wizCtr'] * 1, 2); ?>%
                                        </td>
                                        <td class="cto">
                                            <?php echo number_format($campaignMetrics['wizCto'] * 1, 2); ?>%
                                        </td>
                                        <td class="uniquePurchases">
                                            <?php echo number_format($campaignMetrics['uniquePurchases']); ?>
                                        </td>
                                        <td class="campaignRevenue">
                                            <?php echo '$' . number_format($campaignMetrics['revenue'] * 1, 2); ?>
                                        </td>
                                        <td class="gaRevenue">
                                            <?php echo '$' . number_format($campaignMetrics['gaRevenue'] * 1, 2); ?>
                                        </td>
                                        <td class="cvr">
                                            <?php echo number_format($campaignMetrics['wizCvr'] * 1, 2); ?>%
                                        </td>
                                        <td class="uniqueUnsubs">
                                            <?php echo number_format($campaignMetrics['uniqueUnsubscribes']); ?>
                                        </td>
                                        <td class="unsubRate">
                                            <?php echo number_format($campaignMetrics['wizUnsubRate'] * 1, 2); ?>%
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