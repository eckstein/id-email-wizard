<h2 class="wizArticle-title">Choose a report to view</h2>
<div class="wizcampaign-sections-row grid">

    <?php
    foreach ($child_pages as $page) {
        ?>
        <div class="wizcampaign-section inset" id="reportsOverview">
            <div class="wizcampaign-section-title-area">
                <h4>
                    <?php echo get_field('report_icon', $page->ID) . '&nbsp;&nbsp;' . $page->post_title; ?>
                </h4>
            </div>
            <div class="wizcampaign-section-content">
                <?php echo $page->post_content; ?>
                <a href="<?php echo get_the_permalink($page->ID); ?>" class="wiz-button green">View Report</a>
            </div>
        </div>
    <?php } ?>
</div>