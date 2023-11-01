<?php get_header(); ?>


<article id="post-<?php the_ID(); ?>" data-initiativeid="<?php echo get_the_ID(); ?>" <?php post_class('has-wiz-chart'); ?>>
<header class="header">
        <h1 class="entry-title">Your Profile</h1>
</header>

    <div class="entry-content" itemprop="mainContentOfPage">
        <div id="user-profile">
            <?php $currentUserId = get_current_user_id(); ?>
            <div class="user-profile-tabs">
                <ul>
                    <li>
                    Tab 1
                    </li>
                    <li>
                    Tab 2
                    </li>
                    <li>
                    Tab 2
                    </li>
                </ul>
            </div>
            <div class="user-profile-section">
                Logged in as user ID: <?php echo $currentUserId; ?><br/>
                User Meta:<br/>
                <?php print_r(get_user_meta($currentUserId)); ?>
            </div>
        </div>
    </div>
</article>

<?php get_footer(); ?>