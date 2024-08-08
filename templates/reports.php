<?php get_header();
global $wpdb;

// Check for dates, and default to current month if empty
$startDate = $_GET['startDate'] ?? date('Y-m-01');
$endDate = $_GET['endDate'] ?? date('Y-m-d');

// Define report type
$reportType = $_GET['reportType'] ?? 'home';
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="wizHeader">
        <h1 class="wizEntry-title" itemprop="name">
            Reports
        </h1>
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">

                <?php echo ucfirst($reportType); ?><br />


            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">

                </div>
            </div>
        </div>
    </header>




    <div class="entry-content" itemprop="mainContentOfPage">

        <div class="wizcampaign-sections-row flex">
            <div class="wizcampaign-section inset" id="wiz-report-chooser">
                <div class="wizcampaign-sections-row">
                    <div class="wizcampaign-section shadow">
                        <ul class="reports-nav">
                            <li class="<?php echo $reportType == 'home' ? 'current' : ''; ?>">
                                <div class=" nav-item"><i class="fa-solid fa-house"></i>&nbsp;&nbsp;Reports Home
                                </div>
                                <a href="<?php echo esc_url(get_permalink()); ?>?reportType=home"></a>
                            </li>
                            <li class="<?php echo $reportType == 'open-click-trends' ? 'current' : ''; ?>">
                                <div class="nav-item"><i class="fa-solid fa-chart-line"></i>&nbsp;&nbsp;Open & Click Trends</div>
                                <div class="nav-desc">See comparitive trends for open and click rates over time</div>
                                <a href="<?php echo get_bloginfo('url'); ?>/reports/?reportType=open-click-trends<?php echo (isset($_GET['startDate']) && isset($_GET['endDate'])) ? '&startDate=' . $_GET['startDate'] . '&endDate=' . $_GET['endDate'] : ''; ?>"></a>
                            </li>
                            <li class="<?php echo $reportType == 'purchase-explorer' ? 'current' : ''; ?>">
                                <div class="nav-item"><i class="fa-solid fa-chart-line"></i>&nbsp;&nbsp;Open & Click Trends</div>
                                <div class="nav-desc">Explore purchases based on date range and filters</div>
                                <a href="<?php echo get_bloginfo('url'); ?>/reports/?reportType=purchase-explorer<?php echo (isset($_GET['startDate']) && isset($_GET['endDate'])) ? '&startDate=' . $_GET['startDate'] . '&endDate=' . $_GET['endDate'] : ''; ?>"></a>
                            </li>
                            <li class="<?php echo $reportType == 'signup-to-purchase' ? 'current' : ''; ?>">
                                <div class="nav-item"><i class="fa-solid fa-chart-line"></i>&nbsp;&nbsp;Signup-To-Purchase</div>
                                <div class="nav-desc">See length to conversion from sign-up based on date range.</div>
                                <a href="<?php echo get_bloginfo('url'); ?>/reports/?reportType=signup-to-purchase<?php echo (isset($_GET['startDate']) && isset($_GET['endDate'])) ? '&startDate=' . $_GET['startDate'] . '&endDate=' . $_GET['endDate'] : ''; ?>"></a>
                            </li>
                            <li class="<?php echo $reportType == 'frequency' ? 'current' : ''; ?>">
                                <div class="nav-item"><i class="fa-solid fa-chart-line"></i>&nbsp;&nbsp;Campaign Send Frequency</div>
                                <div class="nav-desc">See frequency of campaign sends by cohort or by send count.</div>
                                <a href="<?php echo get_bloginfo('url'); ?>/reports/?reportType=frequency<?php echo (isset($_GET['startDate']) && isset($_GET['endDate'])) ? '&startDate=' . $_GET['startDate'] . '&endDate=' . $_GET['endDate'] : ''; ?>"></a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="wizcampaign-section shadow span4">


                <?php
                if ($reportType != 'home') {
                    include plugin_dir_path(__FILE__) . 'parts/reports/reports-filter-fields.php';
                }


                if ($reportType == 'home') {
                    include plugin_dir_path(__FILE__) . 'parts/reports/reports-home.php';
                } else if ($reportType == 'open-click-trends') {
                    include plugin_dir_path(__FILE__) . 'parts/reports/open-click-trends.php';
                } else if ($reportType == 'frequency') {
                    include plugin_dir_path(__FILE__) . 'parts/reports/frequency.php';
                } else if ($reportType == 'signup-to-purchase') {
                    include plugin_dir_path(__FILE__) . 'parts/reports/signup-to-purchase.php';
                }
                ?>
            </div>
        </div>





</article>
<?php get_footer(); ?>