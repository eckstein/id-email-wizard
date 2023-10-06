<?php get_header();

//Setup possible views
$reportViews = array(
    'overview' => 'Overview',
    'cohort-2nd-purchases' => 'Repeat Purchase Timing',

);
if (isset($_GET['view']) && array_key_exists($_GET['view'], $reportViews)) {
    $currentView = $_GET['view'];
} else {
    $currentView = 'overview';
}

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="wizHeader">
        <div class="wizHeader-left">
            <h1 class="wizEntry-title" itemprop="name">
                Reports
            </h1>

            <?php
            $viewTabs = [];
            foreach ($reportViews as $view=>$title) {
                $viewTabs[] = ['title' => $title, 'view' => $view];
            }

            get_idwiz_header_tabs($viewTabs, $currentView);

            ?>
        </div>
        <div class="wizHeader-right">
            <div class="wizHeader-actions">


            </div>
        </div>
    </header>

    <div class="entry-content" itemprop="mainContentOfPage">
        <?php if ($currentView == 'cohort-2nd-purchases') {
            include('parts/dashboard-cohort-analysis.php');
        }
        ?>
    </div>



</article>
<?php get_footer(); ?>