<?php
acf_form_head();
get_header(); ?>

<div class="wizinitiative_single has-wiz-chart">
    <?php if (get_field('initiative_color')) {
        $initStyle = 'style="color:'.get_field('initiative_color') . '"';
    } else {
        $initStyle = '';
    }

    // Get the list of campaign IDs associated with the current initiative
    $serialized_campaign_ids = get_post_meta(get_the_ID(), 'wiz_campaigns', true);

    // Unserialize the data if it's serialized
    $associated_campaign_ids = maybe_unserialize($serialized_campaign_ids);

    ?>
    <div class="wizinitiative-title-area">
        <div class="wizinitiative-pretitle">Initiative</div>
        <h1 <?php echo $initStyle; ?>><?php echo get_the_title(); ?></h1>
        <?php
        $dateRange = get_field('date_range');
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];
        ?>
        <h3><?php echo get_idwiz_initiative_daterange(get_the_ID()); ?></h3>
    </div>

    <table class="wiztable_view_metrics_table">
        <tr>
            <td class="initiativeSends"><span class="metric_view_label">Sent</span><span class="metric_view_value"></span></td>
            <td class="initiativeOpenRate"><span class="metric_view_label">Open Rate</span><span class="metric_view_value"></span></td>
            <td class="initiativeCtr"><span class="metric_view_label">CTR</span><span class="metric_view_value"></span></td>
            <td class="initiativeCto"><span class="metric_view_label">CTO</span><span class="metric_view_value"></span></td>
            <td class="initiativePurchases"><span class="metric_view_label">Purchases</span><span class="metric_view_value"></span></td>
            <td class="initiativeRevenue"><span class="metric_view_label">Revenue</span><span class="metric_view_value"></span></td>
            <td class="initiativeCvr"><span class="metric_view_label">CVR</span><span class="metric_view_value"></span></td>
            <td class="initiativeAov"><span class="metric_view_label">AOV</span><span class="metric_view_value"></span></td>
            <td class="initiativeUnsubRate"><span class="metric_view_label">Unsub. Rate</span><span class="metric_view_value"></span></td>
        </tr>
    </table>

    <div class="wizcampaign-sections">
        <div class="wizcampaign-section third inset">
            <h4>About Initiative</h4>
            <?php the_content(); ?>
        </div>
        <div class="wizcampaign-section third inset">
            <h4>Purchases by Date</h4>

            <canvas class="purchByDate" data-campaignids="<?php echo htmlspecialchars(json_encode($associated_campaign_ids)); ?>" data-charttype="bar" data-chart-x-axis="Date" data-chart-y-axis="Purchases" data-chart-dual-y-axis="Revenue"></canvas>
        </div>
        <div class="wizcampaign-section third inset">
            <h4>Purchases by Division</h4>
            <canvas class="purchByLOB" data-campaignids="<?php echo htmlspecialchars(json_encode($associated_campaign_ids)); ?>" data-charttype="bar" data-chart-x-axis="Division" data-chart-y-axis="Purchases" data-chart-dual-y-axis="Revenue"></canvas>
            

        </div>
    </div>
    <div class="wizcampaign-sections">
            <div class="wizcampaign-section inset" id="initiative-campaigns-table">
                <div class="wizcampaign-section-title-area">
                    <h4>Campaigns</h4>
                    <div class="initiative-add-campaign">
                    <a href="#" class="show-add-to-campaign"><i class="fa fa-plus"></i>&nbsp;&nbsp;Add a campaign</a>
                    <div class="initiative-add-campaign-form">
                        <select class="initCampaignSelect" style="width: 300px;"></select>
                        <div class="add-to-table" data-initcampaignaction="add" data-initiativeid="<?php echo get_the_ID(); ?>"><button class="wiz-button green">Add Campaign</button></div>
                    </div>
                    </div>
                </div>
                <table class="idemailwiz-simple-table" id="idemailwiz_initiative_campaign_table" style="width: 100%; vertical-align: middle" valign="middle" width="100%" data-campaignids="<?php echo htmlspecialchars(json_encode($associated_campaign_ids)); ?>">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Campaign</th>
                        <th>Sent</th>
                        <th>Opened</th>
                        <th>Open Rate</th>
                        <th>Clicked</th>
                        <th>CTR</th>
                        <th>CTO</th>
                        <th>Purchases</th>
                        <th>Rev</th>
                        <th>CVR</th>
                        <th>Unsubs</th>
                        <th>Unsub. Rate</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                

                // If IDs exist, fetch campaigns
                if (!empty($associated_campaign_ids)) {
                    $initCampaigns = get_idwiz_campaigns(array(
                        'ids' => $associated_campaign_ids,
                        'sortBy' => 'startAt',
                        'sort' => 'DESC'
                    ));
                }

                foreach ($initCampaigns as $campaign) {
                    $campaignMetrics = get_idwiz_metric($campaign['id']);
                    $readableStartAt = date('m/d/Y', $campaign['startAt'] / 1000);
                ?>
                    <tr>
                        <td class="campaignDate"><?php echo $readableStartAt; ?></td>
                        <td class="campaignName"><a href="<?php echo get_bloginfo('wpurl'); ?>/metrics/campaign/?id=<?php echo $campaign['id']; ?>" target="_blank"><?php echo $campaign['name']; ?></a></td>
                        <td class="uniqueSends"><?php echo number_format($campaignMetrics['uniqueEmailSends']); ?></td>
                        <td class="uniqueOpens"><?php echo number_format($campaignMetrics['uniqueEmailOpens']); ?></td>
                        
                        <td class="openRate"><?php echo number_format($campaignMetrics['wizOpenRate'] * 1, '2'); ?>%</td>
                        <td class="uniqueClicks"><?php echo number_format($campaignMetrics['uniqueEmailClicks']); ?></td>
                        <td class="ctr"><?php echo number_format($campaignMetrics['wizCtr'] * 1, 2); ?>%</td>
                        <td class="cto"><?php echo number_format($campaignMetrics['wizCto'] * 1, 2); ?>%</td>
                        <td class="uniquePurchases"><?php echo number_format($campaignMetrics['uniquePurchases']); ?></td>
                        <td class="campaignRevenue"><?php echo '$'.number_format($campaignMetrics['revenue'] * 1, 2); ?></td>
                        <td class="cvr"><?php echo number_format($campaignMetrics['wizCvr'] * 1, 2); ?>%</td>
                        <td class="uniqueUnsubs"><?php echo number_format($campaignMetrics['uniqueUnsubscribes']); ?></td>
                        <td class="unsubRate"><?php echo number_format($campaignMetrics['wizUnsubRate'] * 1, 2); ?>%</td>
                        <td class="remove-init-campaign" data-initcampaignaction="remove" data-initiativeid="<?php echo get_the_ID(); ?>" data-campaignid="<?php echo $campaign['id'] ?>">x</td>
                    </tr>
                <?php } ?>
                </tbody>
                </table>
                
            </div>
    </div>
    
</div>

<?php
get_footer();
?>