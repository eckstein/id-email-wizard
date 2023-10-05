<?php get_header();

// Initialize date variables
date_default_timezone_set('America/Los_Angeles');
$startDate = '';
$endDate = '';

// Set up date range
if (isset($_GET['wizMonth']) && $_GET['wizMonth'] !== '' && isset($_GET['wizYear']) && $_GET['wizYear'] !== '') {
    // When month and year are provided
    $month = intval($_GET['wizMonth']);
    $year = intval($_GET['wizYear']);
    $startDate = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $endDate = date("Y-m-t", strtotime($startDate));
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
    $month = date("m");
    $year = date("Y");
}

if (isset($_GET['view']) && $_GET['view'] === 'FY') {
    $fyProjections = get_field('fy_' . $endYear . '_projections', 'options');
    $displayGoal = 0;
    foreach ($fyProjections as $monthName => $monthlyGoal) {
        $displayGoal += $monthlyGoal;
    }
} else {
    $monthDateObj = DateTime::createFromFormat('!m', $month);
    $monthName = $monthDateObj->format('F');
    $monthNameLower = strtolower($monthName);

    $fyProjections = get_field('fy_' . $year . '_projections', 'options');
    $displayGoal = $fyProjections[$monthNameLower];
}


// Fetch campaigns
$campaigns = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate, 'type' => 'Blast']);
$triggeredCampaigns = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate, 'type' => 'Triggered']);

$campaignIds = [];
$purchases = [];
$gaPurchases = [];
$gaRevenue = [];

// Extract campaign IDs and fetch purchases
if ($campaigns) {
    $campaignIds = array_column($campaigns, 'id');
    $purchases = get_idwiz_purchases_by_campaign($campaignIds, $startDate, $endDate) ?? [];
}

if ($triggeredCampaigns) {
    $triggeredCampaignIds = array_column($triggeredCampaigns, 'id');
    $triggeredPurchases = get_idwiz_purchases_by_campaign($triggeredCampaigns, $startDate, $endDate);
}

?>
<article id="post-<?php the_ID(); ?>" <?php post_class('wiz_dashboard'); ?>>
    <header class="wizHeader">
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
                <button class="wiz-button green sync-db sync-everything"><i class="fa-solid fa-rotate"></i>&nbsp;Sync
                    Databases</button>
            </div>
        </div>
    </header>
    <div id="wiztable_status_updates"><span class="wiztable_update"></span><span class="wiztable_view_sync_details">View
            sync log&nbsp;<i class="fa-solid fa-chevron-down"></i></span></div>
    <div id="wiztable_status_sync_details">Sync log will show here...</div>
    <div class="entry-content" itemprop="mainContentOfPage">

        <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-month-nav.php'; ?>

        <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-top-row.php'; ?>

        <div class="wizcampaign-sections-row grid">

            <div class="wizcampaign-section inset" id="revByLOB">
                <div class="wizcampaign-section-title-area">
                    <h4>Revenue by LOB</h4>
                    <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">
                        <em>Direct Revenue</em>
                    </div>
                </div>
                <div class="wizcampaign-section-content">
                    <div class="wizChartWrapper purchasesByDivision">
                        <canvas class="purchByDivision wiz-canvas" data-chartid="purchasesByDivision"
                            data-campaignids='<?php echo json_encode(array_column($campaigns, 'id')); ?>'
                            data-charttype="bar"></canvas>
                    </div>
                </div>
            </div>
            <div class="wizcampaign-section short inset">
                <div class="wizcampaign-section-title-area">
                    <h4>Purchases by Product</h4>
                    <div class="wizcampaign-section-icons">

                    </div>
                </div>

                <?php
                $headers = [
                    'Product' => '50%',
                    'Purchases' => '25%',
                    'Revenue' => '25%'
                ];

                $data = [];
                $products = array();
                $productRevenue = array();

                foreach ($purchases as $purchase) {
                    $product = $purchase['shoppingCartItems_name'];
                    if (!isset($products[$product])) {
                        $products[$product] = 0;
                        $productRevenue[$product] = 0;
                    }
                    $products[$product]++;
                    $productRevenue[$product] += $purchase['shoppingCartItems_price'];
                }

                // Sort products by the number of purchases in descending order
                arsort($products);

                // Prepare the data for the table
                foreach ($products as $productName => $purchaseCount) {
                    $data[] = [
                        'Product' => $productName,
                        'Purchases' => $purchaseCount,
                        'Revenue' => '$' . number_format($productRevenue[$productName], 2)
                    ];
                }

                generate_mini_table($headers, $data);
                ?>
            </div>
            <div class="wizcampaign-section inset">
                <div class="wizcampaign-section-title-area">
                    <h4>Purchases by Topic</h4>
                    <div class="wizcampaign-section-icons">
                        <i class="fa-solid fa-chart-simple chart-type-switcher" data-chart-type="bar"></i><i
                            class="fa-solid fa-chart-pie active chart-type-switcher" data-chart-type="pie"></i>
                    </div>
                </div>
                <div class="wizChartWrapper">
                    <canvas class="purchByTopic wiz-canvas" data-chartid="purchasesByTopic"
                        data-campaignids='<?php echo json_encode($campaignIds); ?>' data-charttype="pie"></canvas>
                </div>
            </div>
            <div class="wizcampaign-section short inset">
                <div class="wizcampaign-section-title-area">
                    <h4>Purchases by Campus</h4>
                    <div class="wizcampaign-section-icons">
                        <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i
                            class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
                    </div>
                </div>
                <div class="wizChartWrapper">
                    <canvas class="purchByLocation wiz-canvas" data-chartid="purchasesByLocation"
                        data-campaignids='<?php echo json_encode($campaignIds); ?>' data-charttype="pie"></canvas>
                </div>
            </div>
        </div>
        <div class="wizcampaign-sections-row">
            <div class="wizcampaign-section inset" id="dashboard-campaigns-table">
                <div class="wizcampaign-section-title-area">
                    <h4>Campaigns</h4>
                    <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">
                        <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i
                            class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
                    </div>
                </div>
                <div class="wizcampaign-section-content">
                    <table class="idemailwiz_table display" id="dashboard-campaigns"
                        style="width: 100%; vertical-align: middle;" valign="middle" width="100%"
                        data-campaignids='<?php echo json_encode($campaignIds); ?>'>
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