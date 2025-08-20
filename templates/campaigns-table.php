<?php get_header(); ?>
<?php
// Initialize date variables
//date_default_timezone_set('America/Los_Angeles');
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
} else {
    // Default to current month if no parameters are provided
    $startDateObj = new DateTime('first day of this month', new DateTimeZone('America/Los_Angeles'));
    $startDate = $startDateObj->format('Y-m-d');
    $endDateObj = new DateTime('today', new DateTimeZone('America/Los_Angeles'));
    $endDate = $endDateObj->format('Y-m-d');
    $wizMonth = $startDateObj->format('m');
    $wizYear = $startDateObj->format('Y');

    // $startDate = date("Y-m-01");
    // $endDate = date("Y-m-t");
    // $wizMonth = date("m");
    // $wizYear = date("Y");
}
?>


<?php //print_r(get_idwiz_campaigns()); 
?>
<?php $activeTab = $_GET['view'] ?? 'Blast'; ?>
<header class="wizHeader">
    <h1 class="wizEntry-title single-wizcampaign-title" itemprop="name">
        Campaigns
    </h1>
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">
            <div id="header-tabs">
                <a href="<?php echo add_query_arg(['view' => 'Blast']); ?>" class="campaign-tab <?php if ($activeTab == 'Blast') {
                                                                                                    echo 'active';
                                                                                                } ?>">
                    Blast
                </a>
                <a href="<?php echo add_query_arg(['view' => 'Triggered']); ?>" class="campaign-tab <?php if ($activeTab == 'Triggered') {
                                                                                                        echo 'active';
                                                                                                    } ?>">
                    Triggered
                </a>
                <a href="<?php echo add_query_arg(['view' => 'Archive']); ?>" class="campaign-tab <?php if ($activeTab == 'Archive') {
                                                                                                        echo 'active';
                                                                                                    } ?>">
                    Archive
                </a>
            </div>
        </div>
        <div class="wizHeader-right">
            <div id="global-campaign-search">
                <div class="global-search-container">
                    <input type="text" id="global-search-input" placeholder="Search all campaigns..." />
                    <div id="global-search-results" class="global-search-results-dropdown"></div>
                </div>
            </div>
            <div class="wizHeader-actions">
                <button class="wiz-button green new-initiative"><i class="fa-regular fa-plus"></i>&nbsp;Create
                    Initiative</button>
                <button class="wiz-button green new-comparison"><i class="fa-regular fa-plus"></i>&nbsp;New
                    Comparison</button>
                <?php include plugin_dir_path(__FILE__) . 'parts/module-user-settings-form.php'; ?>
            </div>
        </div>
    </div>
</header>


<div class="entry-content idemailwiz_table_wrapper" itemprop="mainContentOfPage">
    <?php if (isset($_GET['view']) && $_GET['view'] == 'Blast' || !isset($_GET['view'])) { ?>
        <div class="dashboard-nav-area">
            <div class="dashboard-nav-area-left">

            </div>
            <div class="dashboard-nav-area-main">
                <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-date-pickers.php'; ?>
            </div>
            <div class="dashboard-nav-area-right">

            </div>
        </div>
        <div class="rollup_summary_wrapper" id="campaigns-table-rollup">
            <div class="rollup_summary_loader"><i class="fa-solid fa-spinner fa-spin"></i>&nbsp;&nbsp;Loading rollup summary...</div>
        </div>
    <?php } ?>

    <div class="idemailwiz_table_container">

        <table class="idemailwiz_table display" id="idemailwiz_campaign_table" style="width: 100%; vertical-align: middle" valign="middle" width="100%">
            <tr>
                <td>
                    <div id="idemailwiz_tablePreLoad"></div>
                </td>
            </tr>
        </table>
    </div>
    <div class="idemailwiz_bottom_spacer"></div>
</div>
<div id="campaign-table-selected-rollup">
    Loading...
</div>
<?php get_footer(); ?>