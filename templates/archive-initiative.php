<?php get_header(); ?>

<?php
$currentUser = wp_get_current_user();
$userFavInits = get_user_meta($currentUser->ID, 'idwiz_favorite_initiatives', true);
$activeTab = $_GET['view'] ?? 'Active';
?>

<header class="wizHeader">
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">
            <h1 class="wizEntry-title" itemprop="name">
                Initiatives
            </h1>
            <div id="header-tabs">

                <a href="<?php echo add_query_arg(['view' => 'Active']); ?>"
                    class="campaign-tab <?php if ($activeTab == 'Active') {
                        echo 'active';
                    } ?>">
                    Active
                </a>
                <a href="<?php echo add_query_arg(['view' => 'Archive']); ?>"
                    class="campaign-tab <?php if ($activeTab == 'Archive') {
                        echo 'active';
                    } ?>">
                    Archive
                </a>
            </div>
        </div>
        <div class="wizHeader-right">
            <div class="wizHeader-actions">

                <button class="wiz-button green new-initiative"><i class="fa-regular fa-plus"></i>&nbsp;New
                    Initiative</button>
            </div>
        </div>
    </div>
</header>
<div class="entry-content" itemprop="mainContentOfPage">
    <?php if (have_posts()) {
        ?>
        <table class="idemailwiz_table display" id="idemailwiz_initiatives_table"
            style="width: 100%; vertical-align: middle" valign="middle" width="100%">
            <thead>
                <tr>
                    <th>
                        Favorite
                    </th>
                    <th>
                        Initiative
                    </th>
                    <th>
                        Latest Send
                    </th>
                    <th>
                        First Send
                    </th>
                    <th>
                        Campaigns
                    </th>
                    <th>
                        Sends
                    </th>
                    <th>
                        Revenue
                    </th>
                </tr>
                <thead>
                <tbody>
                    <?php
                    while (have_posts()) {
                        the_post();
                        $associated_campaign_ids = idemailwiz_get_campaign_ids_for_initiative(get_the_ID()) ?? array('0');
                        // If IDs exist, fetch campaigns
                        if (!empty($associated_campaign_ids)) {
                            $campaignCount = count($associated_campaign_ids);
                            $initCampaigns = get_idwiz_campaigns(
                                array(
                                    'ids' => $associated_campaign_ids,
                                    'sortBy' => 'startAt',
                                    'sort' => 'DESC'
                                )
                            );

                            $totalSends = 0;
                            $totalRevenue = 0;
                            $lastSendDate = 0; // Initialize to 0
                            $firstSendDate = PHP_INT_MAX; // Initialize to maximum integer value
                


                            foreach ($initCampaigns as $campaign) {
                                $isFavorite = '';
                                if (is_array($userFavInits) && in_array(get_the_ID(), $userFavInits)) {
                                    $isFavorite = 'isFavorite';
                                }

                                $campaignMetrics = get_idwiz_metric($campaign['id']);
                                $totalSends += $campaignMetrics['uniqueEmailSends'];
                                $totalRevenue += $campaignMetrics['revenue'];
                                $sentAt = $campaign['startAt']; // timestamp in milliseconds
                

                                // Figure out first and last campaign
                                if ($sentAt < $firstSendDate) {
                                    $firstSendDate = $sentAt;
                                }
                                if ($sentAt > $lastSendDate) {
                                    $lastSendDate = $sentAt;
                                }
                            }
                        } else {
                            $campaignCount = 0;
                            $totalSends = 0;
                            $totalRevenue = 0;
                            $lastSendDate = 0;
                            $firstSendDate = 0;
                        }
                        ?>
                        <tr data-initid="<?php echo get_the_ID(); ?>" class="<?php echo $isFavorite; ?>">
                            <td>
                                <?php echo $isFavorite; ?>
                            </td>
                            <td>
                                <a href="<?php echo get_the_permalink(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($lastSendDate > 0) {
                                    echo floor($lastSendDate / 1000);
                                } ?>
                            </td>
                            <td>
                                <?php if ($firstSendDate > 0) {
                                    echo floor($firstSendDate / 1000);
                                } ?>
                            </td>
                            <td>
                                <?php
                                echo $campaignCount;
                                ?>
                            </td>
                            <td>
                                <?php
                                echo number_format($totalSends);
                                ?>
                            </td>
                            <td>
                                <?php
                                echo '$' . number_format($totalRevenue);
                                ?>
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