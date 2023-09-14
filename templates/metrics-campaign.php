<?php
/**
 * Template Name: Metrics Campaign Template
 */
?>
<?php get_header(); ?>
<?php
global $wpdb;  // Declare the WordPress Database variable

//print_r(idemailwiz_sync_templates(array(7652831)));

$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$campaign = get_idwiz_campaign($campaign_id);
$metrics = get_idwiz_metric($campaign_id);
$template = get_idwiz_template($campaign['templateId']);
$purchases = get_idwiz_purchases(array('campaignId'=>$campaign_id));

?>
<article id="campaign-<?php echo $campaign_id; ?>" class="wizcampaign-single has-wiz-chart" data-campaignid="<?php echo $campaign_id; ?>">
<header class="header">
<div class="entry-pre-title">Campaign<?php if ($campaign['experimentIds']){ echo ' with experiment';} ?></div>
<h1 class="entry-title single-wizcampaign-title" itemprop="name"><?php echo $campaign['name']; ?></h1>
<?php
  date_default_timezone_set('America/Los_Angeles');
  $startAt = date('m/d/Y \a\t g:ia', $campaign['startAt'] / 1000);  ?>
  <h3 class="single-wizcampaign-startAt">Sent on <?php echo $startAt; ?></h3>
<div id="wiztable_status_updates"><span class="wiztable_update"></span><span class="wiztable_view_sync_details">View sync log&nbsp;<i class="fa-solid fa-chevron-down"></i></span></div>
<div id="wiztable_status_sync_details">Sync log will show here...</div>
</header>
<div class="entry-content" itemprop="mainContentOfPage">
    <?php
    echo generate_idwiz_rollup_row(
            array($campaign_id), 
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
                'uniquePurchases'=>array(
                    'label'=>'Purchases', 
                    'format'=>'num',
                ),
                'revenue'=>array(
                    'label'=>'Revenue', 
                    'format'=>'money',
                ),
            ),
        '<button class="wiz-button sync-campaign" data-campaignid="' . $campaign['id']. '"><i class="fa-solid fa-arrows-rotate"></i></button>'
        ); 
    ?>
    <?php
    //Check for experiments
    $experiments = get_idwiz_experiments(array('campaignId'=>$campaign['id']));
    
    if ($experiments) {
    ?>
    <div class="wizcampaign-section inset wizcampaign-experiments">
    <div class="wizcampaign-experiments-header">
    <h2>Experiment Results</h2>
    
    </div>
    <div class="wizcampaign-experiment-results">
        <div class="wizcampaign-experiment-metrics">
            <?php
            // Define the metrics to be used with types
            $metrics = [
                'uniqueEmailSends' => ['label' => 'Sent', 'type' => 'number'],
                'wizOpenRate' => ['label' => 'Open Rate', 'type' => 'percent'],
                'wizCtr' => ['label' => 'CTR', 'type' => 'percent'],
                'wizCto' => ['label' => 'CTO', 'type' => 'percent'],
                'totalPurchases' => ['label' => 'Purchases', 'type' => 'number'],
                'revenue' => ['label' => 'Revenue', 'type' => 'currency'],
                'wizCvr' => ['label' => 'CVR', 'type' => 'percent'],
                'wizAov' => ['label' => 'AOV', 'type' => 'currency'],
                'wizUnsubRate' => ['label' => 'Unsub. Rate', 'type' => 'percent'],
                'confidence' => ['label' => 'Confidence', 'type' => 'percent'],
                'improvement' => ['label' => 'Improvement', 'type' => 'percent']
            ];

            // Calculate max values for each metric
            $maxValues = [];
            $topTwoUniqueValues = [];
            foreach ($metrics as $key => $metric) {
                $values = array_column($experiments, $key);
                arsort($values);  // Sort in descending order
                $uniqueValues = array_unique($values);
                $topTwoUnique = array_slice($uniqueValues, 0, 2);
                $topTwoUniqueValues[$key] = $topTwoUnique;

                if (!in_array($key, array('uniqueEmailSends','wizUnsubRate'))) {
                    $maxValues[$key] = max(array_column($experiments, $key));
                } else if ($key == 'wizUnsubRate') {
                    // For unsubs, we flip the max so we highlight the lowest
                    $maxValues[$key] = min(array_column($experiments, $key));
                } 
            }

            foreach ($experiments as $experiment) {
                $winnerClass = $experiment['wizWinner'] ? 'winner' : '';
                ?>
                <div class="wizcampaign-experiment">
                    <h4><?php echo $experiment['name']; ?></h4>
                    <div class="experiment_var_wrapper <?php echo $winnerClass; ?>">
                        <div class="wiztable_view_metrics_div">
                            <?php
                            foreach ($metrics as $key => $metric) {
                                $value = $experiment[$key];
                                $formattedValue = "";

                                switch ($metric['type']) {
                                    case 'number':
                                        $formattedValue = number_format($value);
                                        break;
                                    case 'percent':
                                        $formattedValue = number_format($value, 2) . "%";
                                        break;
                                    case 'currency':
                                        $formattedValue = "$" . number_format($value, 0);
                                        break;
                                }

                                $epsilon = 0.01; // smallest number above what we'll display as zero
                                $highlightClass = '';
                                if (isset($maxValues[$key]) && count($topTwoUniqueValues[$key]) > 1) {
                                    // If both top two unique values are effectively zero, then don't highlight
                                    if ($topTwoUniqueValues[$key][0] < $epsilon && $topTwoUniqueValues[$key][1] < $epsilon) {
                                        $highlightClass = '';
                                    } else {
                                        $highlightClass = ($value == $maxValues[$key]) ? 'highlight' : '';
                                    }
                                }
                            ?>
                                <div class="metric-item <?php echo $highlightClass; ?>">
                                    <span class="metric-label"><?php echo $metric['label']; ?></span>
                                    <span class="metric-value"><?php echo $formattedValue; ?></span>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="mark_as_winner">
                            <button class="wiz-button" data-actiontype="<?php echo $winnerClass ? 'remove-winner' : 'add-winner'; ?>" data-experimentid="<?php echo $experiment['experimentId']; ?>" data-templateid="<?php echo $experiment['templateId']; ?>">
                                <?php echo $winnerClass ? 'Winner!' : 'Mark as winner'; ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php
            } // End of foreach loop
            ?>


        </div>
        <div class="wizcampaign-experiment-notes" data-experimentid="<?php echo $experiments[0]['experimentId']; ?>">
            <h3>Experiment Notes</h3>
            <?php $experimentNotes = $experiments[0]['experimentNotes'] ?? '';?>
                <textarea id="experimentNotes" placeholder="Enter some notes about this experiment..."><?php echo $experimentNotes; ?></textarea>
        </div>
    </div>
    </div>
    <?php
    }
    ?>
    <div class="wizmodules">
        
    <div class="wizcampaign-section inset" id="email-info">
    <h4>Purchases by Date</h4>
        <canvas class="purchByDate" data-chartid="purchasesByDate" data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>' data-charttype="bar"></canvas>
    </div>
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Purchases by Division</h4>
            <div class="wizcampaign-section-icons">
                <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
            </div>
        </div>
        <canvas class="purchByDivision" data-chartid="purchasesByDivision" data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>' data-charttype="bar"></canvas>
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
        ?>
        </div>
    </div>
    <div class="wizcampaign-section inset">
        
            <?php 
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
            ?>

            <div class="wizcampaign-section-title-area">
                <h4>Promo Code Use</h4>
                <div>
                <?php echo "{$ordersWithPromoCount} of {$totalOrderCount} orders (" . round($percentageWithPromo, 2) . "%)"; ?>
                </div>
            </div>
            <div class="wizcampaign-section-scrollwrap">

            <?php
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
    <canvas class="purchByTopic" data-chartid="purchasesByTopic" data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>' data-charttype="bar"></canvas>
    </div>
         <div class="wizcampaign-section inset">
            <div class="wizcampaign-section-title-area">
                <h4>Purchases by Campus</h4>
                <div class="wizcampaign-section-icons">
                    <i class="fa-solid fa-chart-simple active chart-type-switcher" data-chart-type="bar"></i><i class="fa-solid fa-chart-pie chart-type-switcher" data-chart-type="pie"></i>
                </div>
            </div>
        <canvas class="purchByLocation" data-chartid="purchasesByLocation" data-campaignids='<?php echo json_encode(array($campaign['id'])); ?>' data-charttype="pie"></canvas>
        </div>


    </div>
    

    <div class="wizcampaign-template-area">

    <?php
    $displayTemplates = [];
    if ($experiments) {
    foreach ($experiments as $experiment) {
        $displayTemplates[] = get_idwiz_template($experiment['templateId']);
    }
    } else {
    $displayTemplates = array($template);
    }

    if (!empty($displayTemplates)) {
        foreach ($displayTemplates as $template) {
            if (!$template) {
                continue;
            }
             ?>
        <div class="wizcampaign-template-html">
        <?php    
        
        if ($template['messageMedium'] == 'Email') {  
        $csv_file = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/heatmap.csv';
        $heatmap_div = generate_idwizcampaign_heatmap_overlay($csv_file);
        ?>
        <div class="wizcampaign-template-details">
            <ul>
                <li>
                    <strong><?php echo $template['subject']; ?></strong>
                </li>
                <li>
                    <?php echo $template['preheaderText']; ?>
                </li>
                <li>
                    <?php echo $template['fromName'] . ' &lt' . $template['fromEmail'] . '&gt'; ?>
                </li>
            </ul>
        </div>
        <div class="wizcampaign-template-preview-iframe-container"> <!-- Wrap iframe and heatmap in a container -->
            <iframe srcdoc="<?php echo htmlspecialchars($template['html'], ENT_QUOTES, 'UTF-8'); ?>" frameborder="0" class="templatePreviewIframe"></iframe>
            <div class="heatmap-container"> <!-- Position heatmap over iframe -->
                <?php echo $heatmap_div; ?>
            </div>
        </div>
        <?php } else {
            ?>
            <div class="wizcampaign-mobile-template">
            <img src="<?php echo $template['imageUrl']; ?>" />
            <?php echo $template['message']; ?>
            </div>
            <?php }?>
        </div>
    <?php 
    }
    }
    ?>
    </div>

    </div>

    </div>
    </article>
    <?php get_footer(); ?>
