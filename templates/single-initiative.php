<?php acf_form_head(); ?>
<?php get_header(); ?>


<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); 

// Get the list of campaign IDs associated with the current initiative
$associated_campaign_ids = idemailwiz_get_campaign_ids_for_initiative(get_the_ID()) ?? array();
if (!empty($associated_campaign_ids)) {
    $purchases = get_idwiz_purchases(array('ids'=>$associated_campaign_ids));
}

?>
<article id="post-<?php the_ID(); ?>" data-initiativeid="<?php echo get_the_ID(); ?>" <?php post_class('has-wiz-chart'); ?>>
<header class="header">
        <div class="entry-pre-title">Initiative</div>
        <input type="text" id="initiative-title-editable" data-initUpdateType="title" value="<?php echo get_the_title(); ?>" />
        <?php
        if (!empty($associated_campaign_ids)) {
        $initDateRange = get_idwiz_initiative_daterange(get_the_ID()); 
        if (!isset($initDateRange['error'])) { ?>
        <h3><?php echo $initDateRange['startDate']; ?> - <?php echo $initDateRange['endDate']; ?></h3>
        <?php }
        } ?>
        <div id="wiztable_status_updates"><span class="wiztable_update"></span><span class="wiztable_view_sync_details">View sync log&nbsp;<i class="fa-solid fa-chevron-down"></i></span></div>
        <div id="wiztable_status_sync_details">Sync log will show here...</div>
</header>
<div class="entry-content" itemprop="mainContentOfPage">

    <?php 

    

    // If IDs exist, fetch campaigns
    if (!empty($associated_campaign_ids)) {
        $initCampaigns = get_idwiz_campaigns(array(
            'ids' => $associated_campaign_ids,
            'sortBy' => 'startAt',
            'sort' => 'DESC'
        ));
    }
    ?>
    
    <?php 
    if (!empty($associated_campaign_ids)){
    echo generate_idwiz_rollup_row(
            $associated_campaign_ids, 
            array(
                'campaignCount'=>array(
                    'label'=>'Campaigns', 
                    'format'=>'num',
                ),
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
                'uniquePurchases'=>array(
                    'label'=>'Purchases', 
                    'format'=>'num',
                ),
                'revenue'=>array(
                    'label'=>'Revenue', 
                    'format'=>'money',
                ),
            ),
        '<button class="wiz-button sync-initiative" data-initids="' . htmlspecialchars(json_encode($associated_campaign_ids)). '"><i class="fa-solid fa-arrows-rotate"></i></button>'
        );
    }
    ?>
    <div class="wizmodules">
            <div id="initiative-campaigns-table" class="wizcampaign-section inset span3" >
                <div class="wizcampaign-section-title-area">
                    <h4>Campaigns</h4>
                    <div class="initiative-add-campaign">
                    <a href="#" class="show-add-to-campaign"><i class="fa fa-plus"></i>&nbsp;&nbsp;Add campaigns</a>
                    <div class="initiative-add-campaign-form">
                        <select class="initCampaignSelect" style="width: 300px;"></select>
                        <div class="add-init-campaign" data-initcampaignaction="add" data-initiativeid="<?php echo get_the_ID(); ?>"><button class="wiz-button green">Add Campaigns</button></div>
                    </div>
                    </div>
                </div>
                <table class="idemailwiz_table display idemailwiz-simple-table" id="idemailwiz_initiative_campaign_table" style="width: 100%; vertical-align: middle;" valign="middle" width="100%" data-campaignids='<?php echo json_encode($associated_campaign_ids); ?>'>
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
                    </tr>
                </thead>
                <tbody>
                <?php
                

                
                if (!empty($associated_campaign_ids)) {
                foreach ($initCampaigns as $campaign) {
                    $campaignMetrics = get_idwiz_metric($campaign['id']);
                    $readableStartAt = date('m/d/Y', $campaign['startAt'] / 1000);
                ?>
                    <tr data-campaignid="<?php echo $campaign['id']; ?>">
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
                    </tr>
                <?php }
                }
                ?>
                </tbody>
                </table>
                
            </div>


    
    <div class="wizcampaign-section inset">
       <div class="initiative-about">
            <h4>About <?php the_title(); ?></h4>
        </div>
        <textarea id="initiative-content-editable" data-initUpdateType="content"><?php echo strip_tags(get_the_content()); ?></textarea>
    </div>
    <div id="initiativeAssets" class="wizcampaign-section span2 inset">
            <div class="wizcampaign-section-title-area"> 
                <h4>Initiative Assets</h4>
                 <div class="wizcampaign-section-icons">
                   <button class="wiz-button green"><i class="fa-solid fa-plus"></i> New Initiative Template</button>
                </div>
            </div>
            <div id="initAssetsUI">
                <div class="initAssetsLibrary">
                <?php 
                $initAssets = get_post_meta($post->ID, 'wizinitiative_assets', true);
                if (is_array($initAssets)) {
                    foreach ($initAssets as $asset) {
                        echo '<div class="init_asset_wrap"><img src="' . $asset['src'] . '" alt="' . $asset['alt'] . '" /></div>';
                    }
                }
                ?>
                </div>
                
            </div>
    </div>
    </div>

    <div class="wizmodules">
    <div class="wizcampaign-section inset" id="email-info">
    <h4>Purchases by Date</h4>
        <?php if (!empty($associated_campaign_ids)) { ?>
        <canvas class="purchByDate" data-chartid="purchasesByDate" data-campaignids='<?php echo json_encode($associated_campaign_ids); ?>' data-charttype="bar"></canvas>
        <?php } ?>
    </div>
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Purchases by Division</h4>
            <div class="wizcampaign-section-icons">
                <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
            </div>
        </div>
        <?php if (!empty($associated_campaign_ids)) { ?>
        <canvas class="purchByDivision" data-chartid="purchasesByDivision" data-campaignids='<?php echo json_encode($associated_campaign_ids); ?>' data-charttype="bar"></canvas>
        <?php } ?>
    </div>
    <div class="wizcampaign-section inset">
        <h4>Purchases by Product</h4>
        <table class="wizcampaign-tiny-table-sticky-header">
            <thead>
                <tr>
                    <th width="50%">Product</th>
                    <th width="25%">Purchases</th>
                    <th width="25%">Revenue</th>
                </tr>
            </thead>
        </table>
        <div class="wizcampaign-section-scrollwrap">
        <?php
        if (!empty($associated_campaign_ids)) {
            $products = array();
            $productRevenue = array();
            foreach ($purchases as $purchase) {
                $product = $purchase['shoppingCartItems_name'];
                if (!isset($products[$product])) {
                    $products[$product] = 0;
                    $productRevenue[$product] = 0; // Initialize revenue for the division
                }
                $products[$product]++;
                $productRevenue[$product] += $purchase['shoppingCartItems_price']; // Add the revenue for this purchase
            }

            // Sort products by the number of purchases in descending order
            arsort($products);

            // Start building the table
            echo '<table class="wizcampaign-tiny-table">';
            echo '<tbody>';

            // Loop through the products and add rows to the table
            foreach ($products as $productName => $purchaseCount) {
                echo '<tr>';
                echo '<td width="50%">' . htmlspecialchars($productName) . '</td>'; // Product name
                echo '<td width="25%">' . $purchaseCount . '</td>'; // Number of purchases
                echo '<td width="25%">$' . number_format($productRevenue[$productName], 2) . '</td>'; // Revenue, formatted with 2 decimal places
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }
        ?>
        </div>
    </div>
    <div class="wizcampaign-section inset">
        
            <?php 
            if (!empty($associated_campaign_ids)) {
            // Initialize variables
            $promoCounts = [];
            $totalOrders = [];
            $ordersWithPromo = [];

            foreach ($purchases as $purchase) {
                $promo = $purchase['shoppingCartItems_discountCode'];
                $orderID = $purchase['id'];

                // Keep track of all unique order IDs
                $totalOrders[$orderID] = true;

                // Skip blank or null promo codes
                if (empty($promo)) {
                    continue;
                }

                // Keep track of unique order IDs with promo codes
                $ordersWithPromo[$orderID] = true;

                if (!isset($promoCounts[$promo])) {
                    $promoCounts[$promo] = [];
                }

                if (!isset($promoCounts[$promo][$orderID])) {
                    $promoCounts[$promo][$orderID] = 0;
                }

                $promoCounts[$promo][$orderID] += 1;
            }

            // Calculate the total number of times each promo code was used
            $promoUseCounts = [];
            foreach ($promoCounts as $promo => $orders) {
                $promoUseCounts[$promo] = count($orders);
            }

            // Calculate promo code usage statistics
            $totalOrderCount = count($totalOrders);
            $ordersWithPromoCount = count($ordersWithPromo);
            $percentageWithPromo = ($totalOrderCount > 0) ? ($ordersWithPromoCount / $totalOrderCount) * 100 : 0;

            }
            ?>

            <div class="wizcampaign-section-title-area">
                <h4>Promo Code Use</h4>
                <div>
                <?php 
                    if (!empty($associated_campaign_ids)) {
                        echo "{$ordersWithPromoCount} of {$totalOrderCount} orders (" . round($percentageWithPromo, 2) . "%)"; 
                    }
                    ?>
                </div>
            </div>
            <div class="wizcampaign-section-scrollwrap">

            <?php
            if (!empty($associated_campaign_ids)) {
                // Sort promo codes by usage
                arsort($promoUseCounts);

                // Start building the table
                echo '<table class="wizcampaign-tiny-table">';
                echo '<thead>';
                echo '<tr>';
                echo '<th>Promo Code</th>';
                echo '<th>Number of Orders</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';

                // Loop through the sorted promo codes and add rows to the table
                foreach ($promoUseCounts as $promo => $useCount) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($promo) . '</td>';
                    echo '<td>' . $useCount . '</td>';
                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';
            }
            ?>
        </div>
    </div>
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Purchases by Topic</h4>
            <div class="wizcampaign-section-icons">
                <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
            </div>
        </div>
    <?php if (!empty($associated_campaign_ids)) { ?>
    <canvas class="purchByTopic" data-chartid="purchasesByTopic" data-campaignids='<?php echo json_encode($associated_campaign_ids); ?>' data-charttype="bar"></canvas>
    <?php } ?>
    </div>
         <div class="wizcampaign-section inset">
            <div class="wizcampaign-section-title-area">
                <h4>Purchases by Campus</h4>
                <div class="wizcampaign-section-icons">
                    <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
                </div>
            </div>
        <?php if (!empty($associated_campaign_ids)) { ?>
        <canvas class="purchByLocation" data-chartid="purchasesByLocation" data-campaignids='<?php echo json_encode($associated_campaign_ids); ?>' data-charttype="pie"></canvas>
        <?php } ?>
        </div>


    </div>


    
    

</div>
</article>
<?php if ( comments_open() && !post_password_required() ) { comments_template( '', true ); } ?>
<?php endwhile; endif; ?>
<?php get_footer(); ?>