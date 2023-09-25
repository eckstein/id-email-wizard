<?php get_header(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class('wiz_dashboard'); ?>>
    <header class="wizHeader">
        <div class="wizHeader-left">
            <h1 class="wizEntry-title" itemprop="name">
                Dashboard
            </h1>
            <div id="header-tabs">
                <?php $activeTab = $_GET['view'] ?? 'Today'; ?>
                <a href="<?php echo add_query_arg(['view' => 'Today']); ?>"
                    class="campaign-tab <?php if ($activeTab == 'Today') {
                        echo 'active';
                    } ?>">
                    Today
                </a>
                <a href="<?php echo add_query_arg(['view' => 'Month']); ?>"
                    class="campaign-tab <?php if ($activeTab == 'Month') {
                        echo 'active';
                    } ?>">
                    This Month
                </a>
                <a href="<?php echo add_query_arg(['view' => 'YoY']); ?>"
                    class="campaign-tab <?php if ($activeTab == 'YoY') {
                        echo 'active';
                    } ?>">
                    YoY
                </a>
            </div>
        </div>
        <div class="wizHeader-right">
            <div class="wizHeader-actions">

                <button class="wiz-button green new-initiative"><i class="fa-regular fa-plus"></i>&nbsp;New
                    Initiative</button>
                <button class="wiz-button green show-new-template-ui"><i class="fa fa-plus"></i>&nbsp;&nbsp;New
                    Template</button>
                <button class="wiz-button green sync-db sync-everything"><i class="fa-solid fa-rotate"></i>&nbsp;Sync
                    Databases</button>
            </div>
        </div>
    </header>
    <div class="entry-content" itemprop="mainContentOfPage">
        <div class="wizmodules">
            <div class="wizcampaign-section inset">
                a
            </div>
            <div class="wizcampaign-section inset">
                b
            </div>
            <div class="wizcampaign-section inset">
                c
            </div>
            <div class="wizcampaign-section inset">
                d
            </div>
        </div>

    </div>
</article>
<?php get_footer(); ?>