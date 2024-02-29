<?php get_header(); ?>

<?php
$currentUser = wp_get_current_user();

$activeTab = $_GET['view'] ?? 'Active';
?>

<header class="wizHeader">
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">
            <h1 class="wizEntry-title" itemprop="name">
                Comparisons
            </h1>
            <div id="header-tabs">

                <a href="<?php echo add_query_arg(['view' => 'Active']); ?>" class="campaign-tab <?php if ($activeTab == 'Active') {
                         echo 'active';
                     } ?>">
                    Active
                </a>
                <a href="<?php echo add_query_arg(['view' => 'Archive']); ?>" class="campaign-tab <?php if ($activeTab == 'Archive') {
                         echo 'active';
                     } ?>">
                    Archive
                </a>
            </div>
        </div>
        <div class="wizHeader-right">
            <div class="wizHeader-actions">

                <button class="wiz-button green new-comparison"><i class="fa-regular fa-plus"></i>&nbsp;New
                    Comparison</button>
            </div>
        </div>
    </div>
</header>
<div class="entry-content" itemprop="mainContentOfPage">
    <?php if (have_posts()) {
        ?>
        <table class="idemailwiz_table display" id="idemailwiz_comparisons_table"
            style="width: 100%; vertical-align: middle" valign="middle" width="100%">
            <thead>
                <tr>
                    
                    <th>
                        Name
                    </th>
                    <th>
                        Set 1
                    </th>
                    <th>
                        Set 2
                    </th>


                </tr>
                </thead>
                <tbody>
                    <?php
                    while (have_posts()) {
                        the_post(); ?>
                        <?php
                        $campaignSets = get_post_meta( $post->ID, 'compare_campaign_sets', true );
?>
                        <tr data-comparisonid="<?php echo get_the_ID(); ?>">

                            <td>
                                <a href="<?php echo get_the_permalink(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo $campaignSets['sets']['0']['setName'] ?? 'Campaign Set 1'; ?>
                            </td>
                            <td>
                            <?php echo $campaignSets['sets']['1']['setName'] ?? 'Campaign Set 2'; ?>
                            </td>
                            
                        </tr>
                    <?php } ?>
                </tbody>
        </table>
        <?php

    } else {
        // No initiatives found
    } ?>
</div>
<?php get_footer(); ?>