<?php
get_header();

global $wpdb;

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="wizHeader">
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">
                <h1 class="wizEntry-title" itemprop="name">
                    Playground
                </h1>
            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">

                </div>
            </div>
        </div>
    </header>
    <div class="entry-content" itemprop="mainContentOfPage">
        <pre><code><?php //print_r(idemailwiz_process_jobids([1149112], 'open')); ?></code></pre>
        <pre><code><?php //idemailwiz_sync_triggered_metric_from_transient('send'); ?></code></pre>

        <?php //updateDatabaseFromCSV('https://localhost/wp-content/uploads/2024/01/wiz-purchase-non-email-12-31-23.csv', 'idemailwiz_purchases'); ?>


    </div>
    </div>
</article>

<?php get_footer();