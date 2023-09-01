<?php get_header(); ?>



<?php //print_r(get_idwiz_campaigns()); ?>
<header class="header">
    <h1 id="wiztable_title" class="entry-title">Campaign Table</h1>
    <h2 id="saved_state_title"></h2>
    <div id="wiztable_status_updates"><span class="wiztable_update"></span><span class="wiztable_view_sync_details">View sync log&nbsp;<i class="fa-solid fa-chevron-down"></i></span></div>
    <div id="wiztable_status_sync_details">Sync log will show here...</div>
</header>
<div class="entry-content idemailwiz_table_wrapper" itemprop="mainContentOfPage">
    
    <div id="wiztable_view_metrics"></div>
    <div class="idemailwiz_table_container">
        <div id="idemailwiz_tableLoader">Loading table...<br/><img src="http://localhost/wp-content/uploads/2023/08/animated_loader_gif_n6b5x0.gif"></div>
        <table class="idemailwiz_table display" id="idemailwiz_campaign_table" style="width: 100%; vertical-align: middle" valign="middle" width="100%" ></table>
    </div>
    <div class="idemailwiz_bottom_spacer"></div>
</div>
<?php get_footer(); ?>




