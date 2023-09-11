<?php get_header(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class('wiz_dashboard'); ?>>
<header class="header">
<h1 class="entry-title" itemprop="name"><?php the_title(); ?></h1> <?php edit_post_link(); ?>
</header>
<div class="entry-content" itemprop="mainContentOfPage">
    <div id="wizDashboard-sidebar" class="wizcampaign-section inset quarter">
        <div class="wizcampaign-section-title-area"><h3>Sidebar</h3></div>
    </div>
    <div id="wizDashboard-modules" class="wizmodules wizcampaign-section inset three-quarter">
        <div class="wizcampaign-section shadow">
            <div class="wizcampaign-section-title-area"><h3>1 Module</h3></div>
        </div>
        <div class="wizcampaign-section shadow">
            <div class="wizcampaign-section-title-area"><h3>2 Module</h3></div>
        </div>
        <div class="wizcampaign-section shadow">
            <div class="wizcampaign-section-title-area"><h3>3 Module</h3></div>
        </div>
        <div class="wizcampaign-section shadow">
            <div class="wizcampaign-section-title-area"><h3>4 Module</h3></div>
        </div>
    </div>
   
</div>
</article>
<?php get_footer(); ?>