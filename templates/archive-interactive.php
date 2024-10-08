<?php get_header(); ?>

<?php
$currentUser = wp_get_current_user();

$activeTab = $_GET['view'] ?? 'Active';
?>

<header class="wizHeader">
    <h1 class="wizEntry-title" itemprop="name">
        Snippets
    </h1>
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">

            <div id="header-tabs">

               
                </a>
            </div>
        </div>
        <div class="wizHeader-right">
            <div class="wizHeader-actions">

                <button class="wiz-button green new-interactive"><i class="fa-regular fa-plus"></i>&nbsp;New
                    Interactive</button>
            </div>
        </div>
    </div>
</header>
<div class="entry-content" itemprop="mainContentOfPage">
    <?php if (have_posts()) {
    ?>
        <table class="idemailwiz_table display" id="idemailwiz_snippets_table" style="width: 100%; vertical-align: middle" valign="middle" width="100%">
            <thead>
                <tr>

                    <th>
                        Interactive
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