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
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">
                <h1 class="wizEntry-title" itemprop="name">
                    Reports
                </h1>
                <?php echo ucfirst($reportType); ?><br/>
                <a href="<?php echo esc_url(get_permalink()); ?>?reportType=home">< reports Home</a>


            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">

                </div>
            </div>
        </div>
    </header>

    <div class="entry-content" itemprop="mainContentOfPage">
        <?php
        if ($reportType != 'home') {
            include plugin_dir_path(__FILE__) . 'parts/dashboard-date-pickers.php';
        }
        if ($reportType == 'home') {
            include plugin_dir_path( __FILE__  ) . 'parts/reports/reports-home.php'; 
        } else if ($reportType == 'frequency') {
            include plugin_dir_path(__FILE__) . 'parts/reports/frequency.php'; 
        } else if ($reportType == 'signup-to-purchase') {
            include plugin_dir_path(__FILE__) . 'parts/reports/signup-to-purchase.php';
        }
        ?>  


    </div>




</article>
<?php get_footer(); ?>