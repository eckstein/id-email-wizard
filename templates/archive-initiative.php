<?php get_header(); ?>

<?php
$currentUser = wp_get_current_user();
$userFavInits = get_user_meta($currentUser->ID, 'idwiz_favorite_initiatives', true);
$activeTab = $_GET['view'] ?? 'Active';

// Set up the query arguments based on the active tab
$query_args = array(
    'post_type' => 'idwiz_initiative',
    'posts_per_page' => -1,
);

if ($activeTab == 'Archive') {
    $query_args['meta_query'] = array(
        array(
            'key' => 'is_archived',
            'value' => 'true',
            'compare' => '='
        )
    );
} else {
    $query_args['meta_query'] = array(
        'relation' => 'OR',
        array(
            'key' => 'is_archived',
            'value' => 'true',
            'compare' => '!='
        ),
        array(
            'key' => 'is_archived',
            'compare' => 'NOT EXISTS'
        )
    );
}

// Run the query
$initiative_query = new WP_Query($query_args);
?>

<header class="wizHeader">
    <h1 class="wizEntry-title" itemprop="name">
        Initiatives
    </h1>
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">
            <div id="header-tabs">
                <a href="<?php echo add_query_arg(['view' => 'Active']); ?>" class="campaign-tab <?php echo ($activeTab == 'Active') ? 'active' : ''; ?>">
                    Active
                </a>
                <a href="<?php echo add_query_arg(['view' => 'Archive']); ?>" class="campaign-tab <?php echo ($activeTab == 'Archive') ? 'active' : ''; ?>">
                    Archive
                </a>
            </div>
        </div>
        <div class="wizHeader-right">
            <div class="wizHeader-actions">
                <button class="wiz-button green new-initiative"><i class="fa-regular fa-plus"></i>&nbsp;New Initiative</button>
            </div>
        </div>
    </div>
</header>

<div class="entry-content" itemprop="mainContentOfPage">
    <?php if ($initiative_query->have_posts()) : ?>
        <table class="idemailwiz_table display" id="idemailwiz_initiatives_table" style="width: 100%; vertical-align: middle" valign="middle" width="100%">
            <thead>
                <tr>
                    <th>Favorite</th>
                    <th>Initiative</th>
                    <th>Latest Send</th>
                    <th>First Send</th>
                    <th>Campaigns</th>
                    <th>Sends</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ($initiative_query->have_posts()) :
                    $initiative_query->the_post();
                    
                    $associated_campaign_ids = idemailwiz_get_campaign_ids_for_initiative(get_the_ID()) ?? array('0');
                    if (!empty($associated_campaign_ids)) {
                        $campaign_count = count($associated_campaign_ids);
                        $initCampaigns = get_idwiz_campaigns(
                            array(
                                'campaignIds' => $associated_campaign_ids,
                                'sortBy' => 'startAt',
                                'sort' => 'DESC'
                            )
                        );

                        $total_sends = 0;
                        $total_revenue = 0;
                        $last_send_date = 0;
                        $first_send_date = PHP_INT_MAX;

                        foreach ($initCampaigns as $campaign) {
                            $is_favorite = is_array($userFavInits) && in_array(get_the_ID(), $userFavInits);
                            
                            $campaign_metrics = get_idwiz_metric($campaign['id']);
                            $total_sends += $campaign_metrics['uniqueEmailSends'];
                            $total_revenue += $campaign_metrics['revenue'];
                            $campaign_start = $campaign['startAt'];

                            if ($campaign_start < $first_send_date) {
                                $first_send_date = $campaign_start;
                            }
                            if ($campaign_start > $last_send_date) {
                                $last_send_date = $campaign_start;
                            }
                        }
                    } else {
                        $campaign_count = 0;
                        $total_sends = 0;
                        $total_revenue = 0;
                        $last_send_date = 0;
                        $first_send_date = 0;
                    }
                ?>
                    <tr data-initid="<?php echo get_the_ID(); ?>">
                        <td><?php echo $is_favorite ? 'Favorite' : ''; ?></td>
                        <td><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></td>
                        <td><?php echo $last_send_date > 0 ? floor($last_send_date / 1000) : ''; ?></td>
                        <td><?php echo $first_send_date < PHP_INT_MAX ? floor($first_send_date / 1000) : ''; ?></td>
                        <td><?php echo $campaign_count; ?></td>
                        <td><?php echo number_format($total_sends); ?></td>
                        <td><?php echo '$' . number_format($total_revenue); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php
    else :
        if ($activeTab != 'Archive') {
            echo 'No active initiatives found. <a href="#" class="new-initiative">Create one!</a>';
        } else {
            echo 'No archived initiatives were found.';
        }
    endif;
    
    wp_reset_postdata(); // Reset the post data
    ?>
</div>

<?php get_footer(); ?>