<?php get_header(); ?>



<?php //print_r(get_idwiz_campaigns()); ?>
<?php $activeTab = $_GET['view'] ?? 'Blast'; ?>
<header class="wizHeader">
    <div class="wizHeader-left">
        <h1 class="wizEntry-title single-wizcampaign-title" itemprop="name">
            Campaigns
        </h1>
        
        <div id="header-tabs">
            
            <a href="<?php echo add_query_arg(['view'=>'Blast']); ?>" class="campaign-tab <?php if ($activeTab == 'Blast') {  echo 'active'; } ?>">
                Blast
            </a>
            <a href="<?php echo add_query_arg(['view'=>'Triggered']); ?>" class="campaign-tab <?php if ($activeTab == 'Triggered') {  echo 'active'; } ?>">
                Triggered
            </a>
            <a href="<?php echo add_query_arg(['view'=>'All']); ?>" class="campaign-tab <?php if ($activeTab == 'All') {  echo 'active'; } ?>" >
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
</header>

<div id="wiztable_status_updates"><span class="wiztable_update"></span><span class="wiztable_view_sync_details">View
        sync log&nbsp;<i class="fa-solid fa-chevron-down"></i></span></div>
<div id="wiztable_status_sync_details">Sync log will show here...</div>
<div class="entry-content idemailwiz_table_wrapper" itemprop="mainContentOfPage">
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