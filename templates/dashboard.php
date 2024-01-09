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

$startDateTime = new DateTime($startDate);

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

// Fetch all campaigns
$allCampaigns = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate]);

// Categorize campaigns into Blast and Triggered
$blastCampaigns = [];
$triggeredCampaigns = [];
foreach ($allCampaigns as $campaign) {
    if ($campaign['type'] == 'Blast') {
        $blastCampaigns[] = $campaign;
    } elseif ($campaign['type'] == 'Triggered') {
        $triggeredCampaigns[] = $campaign;
    }
}

// Create a map of all campaigns with campaign ID as key
$campaignMap = [];
foreach ($allCampaigns as $campaign) {
    $campaignMap[$campaign['id']] = $campaign;
}

// Fetch all purchases
$allPurchases = get_idwiz_purchases(['startAt_start' => $startDate, 'startAt_end' => $endDate]);

// Categorize purchases based on campaign type
$blastPurchases = [];
$triggeredPurchases = [];
foreach ($allPurchases as $purchase) {
    $purchaseCampaignId = $purchase['campaignId'];
    if (isset($campaignMap[$purchaseCampaignId])) {
        $purchaseCampaign = $campaignMap[$purchaseCampaignId];
        if ($purchaseCampaign['type'] == 'Blast') {
            $blastPurchases[] = $purchase;
        } elseif ($purchaseCampaign['type'] == 'Triggered') {
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
                // $currentView = $_GET['view'] ?? 'Month';
                // $viewTabs = [
                //     ['title' => 'This Month', 'view' => 'Month'],
                //     ['title' => 'Fiscal Year', 'view' => 'FY'],
                // ];

                // get_idwiz_header_tabs($viewTabs, $currentView);

                ?>
            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">

                    <button class="wiz-button green new-initiative"><i class="fa-regular fa-plus"></i>&nbsp;New
                        Initiative</button>
                    <button class="wiz-button green show-new-template-ui"><i class="fa fa-plus"></i>&nbsp;&nbsp;New
                        Template</button>
                    <button class="wiz-button green doWizSync"
                    data-metricTypes="<?php echo esc_attr(json_encode(array('blast'))); ?>"><i class="fa-solid fa-arrows-rotate"></i>&nbsp;&nbsp;Sync Metrics</button>
                    <a href="<?php echo get_bloginfo('url').'/sync-station'; ?>" class="wiz-button gray" id="viewSyncStation" title="View sync log">
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
                <?php //include plugin_dir_path(__FILE__) . 'parts/dashboard-month-nav.php'; ?>
                <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-date-pickers.php'; ?>
                <?php //include plugin_dir_path(__FILE__) . 'parts/dashboard-date-buttons.php'; ?>
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
        $lazyLoadCharts = false;
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
                                <th class="dtNumVal">Sent</th>
                                <th class="dtNumVal">Opens</th>
                                <th>Opened</th>
                                <th class="dtNumVal">Clicks</th>
                                <th>CTR</th>
                                <th>CTO</th>
                                <th class="dtNumVal">Prchs</th>
                                <th>Rev</th>
                                <th>GA Rev</th>
                                <th>CVR</th>
                                <th class="dtNumVal">Unsubs</th>
                                <th>Unsubed</th>
                                <th class="campaignId">ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php



                            if (!empty($blastCampaigns)) {
                                foreach ($blastCampaigns as $campaign) {
                                    $campaignMetrics = get_idwiz_metric($campaign['id']);
                                    $campaignStartStamp = (int)($campaign['startAt'] / 1000);
                                    $readableStartAt = date('m/d/Y', $campaignStartStamp);
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
                                                >
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