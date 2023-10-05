<?php get_header();

//Setup possible views
$reportViews = array(
    'cohort-purchases',

);
if (isset($_GET['view']) && in_array($_GET['view'], $reportViews)) {
    $currentView = $_GET['view'];
} else {
    $currentView = $reportViews[0];
}

?>
<article id="post-<?php the_ID(); ?>" <?php post_class('wiz_dashboard'); ?>>
    <header class="wizHeader">
        <div class="wizHeader-left">
            <h1 class="wizEntry-title" itemprop="name">
                Dashboard
            </h1>

            <?php

            $currentView = $currentView;
            $viewTabs = [];
            foreach ($reportViews as $view) {
                $viewTabs[] = ['title' => $view, 'view' => $view];
            }

            get_idwiz_header_tabs($viewTabs, $currentView);

            ?>
        </div>
        <div class="wizHeader-right">
            <div class="wizHeader-actions">


            </div>
        </div>
    </header>
    <div id="wiztable_status_updates"><span class="wiztable_update"></span><span class="wiztable_view_sync_details">View
            sync log&nbsp;<i class="fa-solid fa-chevron-down"></i></span></div>
    <div id="wiztable_status_sync_details">Sync log will show here...</div>
    <div class="entry-content" itemprop="mainContentOfPage">

       
    </div>



</article>
<?php get_footer(); ?>