<?php get_header(); ?>

<?php
$currentUser = wp_get_current_user();

$activeTab = $_GET['view'] ?? 'Active';
?>

<header class="wizHeader">
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">
            <h1 class="wizEntry-title" itemprop="name">
                Snippets
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

                <button class="wiz-button green new-snippet"><i class="fa-regular fa-plus"></i>&nbsp;New
                    Snippet</button>
            </div>
        </div>
    </div>
</header>
<div class="entry-content" itemprop="mainContentOfPage">
    <?php if (have_posts()) {
        ?>
        <table class="idemailwiz_table display" id="idemailwiz_snippets_table"
            style="width: 100%; vertical-align: middle" valign="middle" width="100%">
            <thead>
                <tr>
                    
                    <th>
                        Snippet
                    </th>
                    <th>
                        Author
                    </th>
                    <th>
                        Used In XX Templates
                    </th>


                </tr>
                </thead>
                <tbody>
                    <?php
                    while (have_posts()) {
                        the_post(); ?>
                        <tr data-comparisonid="<?php echo get_the_ID(); ?>">

                            <td>
                                <a href="<?php echo get_the_permalink(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </td>
                            <td>
                               <?php echo get_the_author(); ?>
                            </td>
                            <td>
                            <em>coming soon</em>
                            </td>
                            
                        </tr>
                    <?php } ?>
                </tbody>
        </table>
        <?php

    } else {
        // No snippets found
    } ?>
</div>
<?php get_footer(); ?>