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

                <div id="header-tabs">

                    <?php
                    if ($current_page_id == $reports_page) {
                        $homeActive = 'active';
                    } else {
                        $homeActive = '';
                    }
                    echo "<a href=\"{$reports_home_link}\" class=\"campaign-tab {$homeActive}\">Reports</a>";
                    // Display child pages as tabs
                    foreach ($child_pages as $page) {
                        $page_title = $page->post_title;
                        $page_link = get_permalink($page->ID);
                        $isActive = ($current_page_id == $page->ID) ? 'active' : '';
                        echo "<a href=\"{$page_link}\" class=\"campaign-tab {$isActive}\">{$page_title}</a>";
                    }
                    ?>
                </div>

            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">

                </div>
            </div>
        </div>
    </header>

    <div class="entry-content" itemprop="mainContentOfPage">
        <div class="wizcampaign-sections-row">
            <div class="wizcampaign-section span1" id="reportSidebar">
                <div class="reports-sidebar-nav">
                    <ul>
                        <?php
                        if ($current_page_id == $reports_page) {
                            $homeActive = 'active';
                        } else {
                            $homeActive = '';
                        }
                        echo "<li><a href=\"{$reports_home_link}\" class=\"campaign-tab {$homeActive}\">Reports Home</a></li>";
                        // Display child pages as tabs
                        foreach ($child_pages as $page) {
                            $page_title = $page->post_title;
                            $page_link = get_permalink($page->ID);
                            $isActive = ($current_page_id == $page->ID) ? 'active' : '';
                            echo "<li><a href=\"{$page_link}\" class=\"campaign-tab {$isActive}\">{$page_title}</a></li>";
                        }
                        ?>
                    </ul>
                </div>
            </div>
            <div class="wizcampaign-section span4 inset">
                <div class="wizcampaign-section">
                    <div class="wizcampaign-section-content">
                        <?php include('parts/reports-filter-form.php'); ?>
                        <div class="wizChartWrapper">
                            <h5 class="wizChart-title">Open Rate</h5>
                            <canvas class="wiz-canvas" 
                                    data-chartid="opensReport"
                                    data-charttype="line"
                                    data-startdate="<?php echo esc_attr($sendAtStart); ?>" 
                                    data-enddate="<?php echo esc_attr($sendAtEnd); ?>"
                                    data-minsends="<?php echo esc_attr($minSends); ?>"
                                    data-maxsends="<?php echo esc_attr($maxSends); ?>"
                                    data-minmetric="<?php echo esc_attr($minMetric); ?>"
                                    data-maxmetric="<?php echo esc_attr($maxMetric); ?>">
                            </canvas>
                        </div>
                        <div class="wizChartWrapper">
                            <h5 class="wizChart-title">CTR</h5>
                            <canvas class="wiz-canvas" 
                                    data-chartid="ctrReport"
                                    data-charttype="line"
                                    data-startdate="<?php echo esc_attr($sendAtStart); ?>" 
                                    data-enddate="<?php echo esc_attr($sendAtEnd); ?>"
                                    data-minsends="<?php echo esc_attr($minSends); ?>"
                                    data-maxsends="<?php echo esc_attr($maxSends); ?>"
                                    data-minmetric="<?php echo esc_attr($minMetric); ?>"
                                    data-maxmetric="<?php echo esc_attr($maxMetric); ?>">
                            </canvas>
                        </div>
                        <div class="wizChartWrapper">
                            <h5 class="wizChart-title">CTO</h5>
                            <canvas class="wiz-canvas" 
                                    data-chartid="ctoReport"
                                    data-charttype="line"
                                    data-startdate="<?php echo esc_attr($sendAtStart); ?>" 
                                    data-enddate="<?php echo esc_attr($sendAtEnd); ?>"
                                    data-minsends="<?php echo esc_attr($minSends); ?>"
                                    data-maxsends="<?php echo esc_attr($maxSends); ?>"
                                    data-minmetric="<?php echo esc_attr($minMetric); ?>"
                                    data-maxmetric="<?php echo esc_attr($maxMetric); ?>">
                            </canvas>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>




</article>
<?php get_footer(); ?>