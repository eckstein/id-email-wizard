<?php get_header(); ?>

<?php

?>

<header class="header">
<h1 class="entry-title" itemprop="name">Initiatives</h1>
</header>
<div class="entry-content" itemprop="mainContentOfPage">
 <?php if ( have_posts() ) { 
     ?>
    <table class="idemailwiz_table display" id="idemailwiz_initiatives_table" style="width: 100%; vertical-align: middle" valign="middle" width="100%" >
    <thead>
        <tr>
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
        while ( have_posts() ) { the_post(); 
        // Get the list of campaign IDs associated with the current initiative
        $serialized_campaign_ids = get_post_meta(get_the_ID(), 'wiz_campaigns', true);
        // Unserialize the data if it's serialized
        $associated_campaign_ids = maybe_unserialize($serialized_campaign_ids);
        // If IDs exist, fetch campaigns
        if (!empty($associated_campaign_ids)) {
            $campaignCount = count($associated_campaign_ids);
            $initCampaigns = get_idwiz_campaigns(array(
                'ids' => $associated_campaign_ids,
                'sortBy' => 'startAt',
                'sort' => 'DESC'
            ));
            
            $totalSends = 0;
            $totalRevenue = 0;
            $lastSendDate = 0; // Initialize to 0
            $firstSendDate = PHP_INT_MAX; // Initialize to maximum integer value

            foreach ($initCampaigns as $campaign) {
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
        <tr>
            <td>
                <a href="<?php echo get_the_permalink(); ?>"><?php the_title(); ?></a>
            </td>
            <td>
                <?php if ($lastSendDate > 0) {echo date('m/d/Y', $lastSendDate / 1000); } ?>
            </td>
            <td>
                <?php if ($firstSendDate > 0) {echo date('m/d/Y', $firstSendDate / 1000);} ?>
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
            echo '$'.number_format($totalRevenue);
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
