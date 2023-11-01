<?php get_header(); ?>
<?php
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
} else {
    // Default to current month if no parameters are provided
    $startDate = date("Y-m-01");
    $endDate = date("Y-m-t");
    $wizMonth = date("m");
    $wizYear = date("Y");
}
?>


<?php //print_r(get_idwiz_campaigns()); ?>
<?php $activeTab = $_GET['view'] ?? 'Blast'; ?>
<header class="wizHeader">
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">
            <h1 class="wizEntry-title single-wizcampaign-title" itemprop="name">
                Campaigns
            </h1>

            <div id="header-tabs">

                <a href="<?php echo add_query_arg(['view' => 'Blast']); ?>"
                    class="campaign-tab <?php if ($activeTab == 'Blast') {
                        echo 'active';
                    } ?>">
                    Blast
                </a>
                <a href="<?php echo add_query_arg(['view' => 'Triggered']); ?>"
                    class="campaign-tab <?php if ($activeTab == 'Triggered') {
                        echo 'active';
                    } ?>">
                    Triggered
                </a>
                <a href="<?php echo add_query_arg(['view' => 'All']); ?>"
                    class="campaign-tab <?php if ($activeTab == 'All') {
                        echo 'active';
                    } ?>">
                    All
                </a>
            </div>
        </div>
        <div class="wizHeader-right">
            <div class="wizHeader-actions">
                <button class="wiz-button green sync-db sync-everything"><i class="fa-solid fa-rotate"></i>&nbsp;Sync
                    Databases</button>
                <button class="wiz-button green new-initiative"><i class="fa-regular fa-plus"></i>&nbsp;Add
                    Initiative</button>
            </div>
        </div>
    </div>
</header>

<div id="wiztable_status_updates"><span class="wiztable_update"></span><span class="wiztable_view_sync_details">View
        sync log&nbsp;<i class="fa-solid fa-chevron-down"></i></span></div>
<div id="wiztable_status_sync_details">Sync log will show here...</div>
<div class="entry-content idemailwiz_table_wrapper" itemprop="mainContentOfPage">
    <div class="dashboard-nav-area">
        <div class="dashboard-nav-area-left">

        </div>
        <div class="dashboard-nav-area-main">
            <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-date-pickers.php'; ?>
        </div>
        <div class="dashboard-nav-area-right">
            
        </div>
    </div>
    <div class="wiztable_view_metrics_div" id="campaigns-table-rollup">Loading rollup summary...</div>

    <div class="idemailwiz_table_container">

        <table class="idemailwiz_table display" id="idemailwiz_campaign_table"
            style="width: 100%; vertical-align: middle" valign="middle" width="100%">
            <tr>
                <td>
                    <div id="idemailwiz_tablePreLoad"></div>
                </td>
            </tr>
        </table>
    </div>
    <div class="idemailwiz_bottom_spacer"></div>
</div>
<?php get_footer(); ?>