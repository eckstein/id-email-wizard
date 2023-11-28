<?php get_header(); 

// Get query params
//$reportId = $_GET['reportId'] ?? 'opensReport';
$campaignTypes = $_GET['campaignType'] ?? ['Blast'];
$sendAtStart = $_GET['sendAtStart'] ?? '2021-11-01';
$sendAtEnd = $_GET['sendAtEnd'] ?? date('Y-m-d', time());
$minSends = $_GET['minSends'] ?? 1000;
$maxSends = $_GET['maxSends'] ?? 500000;
$minMetric = $_GET['minMetric'] ?? 0;
$maxMetric = $_GET['maxMetric'] ?? 100;

// Fetch child pages $reports_page
$options = get_option('idemailwiz_settings');
$reports_page = isset($options['reports_page']) ? $options['reports_page'] : '';
$args = array(
    'post_parent' => $reports_page,
    'post_type' => 'page',
    'numberposts' => -1,
    'post_status' => 'publish',
    'orderby' => 'menu_order',
    'order' => 'ASC'
);
$child_pages = get_children($args);
$current_page_id = get_the_ID();
$reports_home_link = get_permalink($reports_page);

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="wizHeader">
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">
                <h1 class="wizEntry-title" itemprop="name">
                    Reports
                </h1>

                

            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">

                </div>
            </div>
        </div>
    </header>

    <div class="entry-content" itemprop="mainContentOfPage">
         <?php 
         include plugin_dir_path( __FILE__  ) . 'parts/reports-home.php'; ?>
    </div>




</article>
<?php get_footer(); ?>