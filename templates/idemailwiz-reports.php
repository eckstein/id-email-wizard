<?php get_header();

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
        <?php
        $current_page_slug = get_post_field('post_name', get_post());

        switch ($current_page_slug) {
            case 'repeat-purchases-timing':
                include('parts/report-cohort-analysis.php');
                break;

            case 'open-trends':
                include('parts/report-open-trends.php');
                break;

            case 'click-trends':
                include('parts/report-click-trends.php');
                break;

            case 'retention-trends':
                include('parts/report-retention-trends.php');
                break;

            case 'top-performers':
                include('parts/report-top-performers.php');
                break;

            case 'subject-line-performance':
                include('parts/report-sl-performance.php');
                break;

            default:
                // Default content or report
                include('parts/reports-home.php');
                break;
        }
        ?>
    </div>




</article>
<?php get_footer(); ?>