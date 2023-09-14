<?php get_header(); ?>

<?php echo generate_idwiz_rollup_row(
    array(7679874), 
        array(
            'uniqueEmailSends'=>array(
                'label'=>'Sends', 
                'format'=>'num',
            ),
            'uniqueEmailOpens'=>array(
                'label'=>'Opens', 
                'format'=>'num',
            ),
            'wizOpenRate'=>array(
                'label'=>'Open Rate', 
                'format'=>'perc',
            ),
            'uniqueEmailClicks'=>array(
                'label'=>'Clicks', 
                'format'=>'num',
            ),
            'wizCtr'=>array(
                'label'=>'CTR', 
                'format'=>'perc',
            ),
            'wizCto'=>array(
                'label'=>'CTO', 
                'format'=>'perc',
            ),
            'totalPurchases'=>array(
                'label'=>'Purchases', 
                'format'=>'num',
            ),
            'revenue'=>array(
                'label'=>'Revenue', 
                'format'=>'money',
            ),
        )
    ); 
?>


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