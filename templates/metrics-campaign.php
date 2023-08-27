<?php
/**
 * Template Name: Metrics Campaign Template
 */

get_header();


$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$campaign = get_idwiz_campaign($campaign_id);
$metrics = get_idwiz_metric($campaign_id);
$template = get_idwiz_template($campaign['templateId']);
$purchases = get_idwiz_purchases(array('campaignId'=>$campaign_id));

?>

<div class="wizcampaign-single has-wiz-chart" data-campaignid="<?php echo $campaign_id; ?>">

  <h1 class="single-wizcampaign-title"><?php echo $campaign['name']; ?></h1>
  <?php

  date_default_timezone_set('America/Los_Angeles');
  $startAt = date('m/d/Y \a\t g:ia', $campaign['startAt'] / 1000);  ?>
  <h3 class="single-wizcampaign-startAt">Sent on <?php echo $startAt; ?></h3>
<table class="wiztable_view_metrics_table">
    <tr>
        <td><span class="metric_view_label">Sent</span><span class="metric_view_value"><?php echo number_format($metrics['uniqueEmailSends']); ?></span></td>
        <td><span class="metric_view_label">Open Rate</span><span class="metric_view_value"><?php echo number_format($metrics['wizOpenRate'], 2); ?>%</span></td>
        <td><span class="metric_view_label">CTR</span><span class="metric_view_value"><?php echo number_format($metrics['wizCtr'], 2); ?></span></td>
        <td><span class="metric_view_label">CTO</span><span class="metric_view_value"><?php echo number_format($metrics['wizCto'], 2); ?></span></td>
        <td><span class="metric_view_label">Purchases</span><span class="metric_view_value"><?php echo number_format($metrics['totalPurchases']); ?></span></td>
        <td><span class="metric_view_label">Revenue</span><span class="metric_view_value">$<?php echo number_format($metrics['revenue'], 2); ?></span></td>
        <td><span class="metric_view_label">CVR</span><span class="metric_view_value"><?php echo number_format($metrics['wizCvr'], 2); ?>%</span></td>
        <td><span class="metric_view_label">AOV</span><span class="metric_view_value">$<?php echo number_format($metrics['wizAov'], 2); ?></span></td>
        <td><span class="metric_view_label">Unsub. Rate</span><span class="metric_view_value"><?php echo number_format($metrics['wizUnsubRate'], 2); ?>%</span></td>
    </tr>
</table>
<div class="wizcampaign-sections">
    <div class="wizcampaign-section third inset" id="email-info">
    <h4>Purchases by Date</h4>
    <canvas class="purchByDate" data-campaignids="[<?php echo $campaign['id']; ?>]" data-charttype="bar" data-chart-x-axis="Date" data-chart-y-axis="Purchases" data-chart-dual-y-axis="Revenue"></canvas>
    </div>
    <div class="wizcampaign-section third inset">
    <h4>Purchases by Division</h4>
    <canvas class="purchByLOB" data-campaignids="[<?php echo $campaign['id']; ?>]" data-charttype="bar" data-chart-x-axis="Division" data-chart-y-axis="Purchases" data-chart-dual-y-axis="Revenue"></canvas>
    </div>
    <div class="wizcampaign-section third inset">
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
</div>
<?php
//Check for experiments
$experiments = get_idwiz_experiments(array('campaignId'=>$campaign['id']));
if ($experiments) {
    ?>
    <div class="wizcampaign-section inset wizcampaign-experiments">
    <h3>Experiment Variations</h3>
    <?php
    foreach ($experiments as $experiment) {
        ?>
        <div class="wizcampaign-experiment">
            <h4><?php echo $experiment['name']; ?></h4>
            <?php
                if ($experiment['type'] == 'Winner') {
                    echo 'Winner! Improvement: ' . $experiment['improvement'] . '. Confidence: ' . $experiment['confidence'];
                }
            ?>
            <table class="wiztable_view_metrics_table">
                <tr>
                    <td><span class="metric_view_label">Sent</span><span class="metric_view_value"><?php echo number_format($experiment['uniqueEmailSends']); ?></span></td>
                    <td><span class="metric_view_label">Open Rate</span><span class="metric_view_value"><?php echo number_format($experiment['wizOpenRate'], 2); ?>%</span></td>
                    <td><span class="metric_view_label">CTR</span><span class="metric_view_value"><?php echo number_format($experiment['wizCtr'], 2); ?></span></td>
                    <td><span class="metric_view_label">CTO</span><span class="metric_view_value"><?php echo number_format($experiment['wizCto'], 2); ?></span></td>
                    <td><span class="metric_view_label">Purchases</span><span class="metric_view_value"><?php echo number_format($experiment['totalPurchases']); ?></span></td>
                    <td><span class="metric_view_label">Revenue</span><span class="metric_view_value">$<?php echo number_format($experiment['revenue'], 2); ?></span></td>
                    <td><span class="metric_view_label">CVR</span><span class="metric_view_value"><?php echo number_format($experiment['wizCvr'], 2); ?>%</span></td>
                    <td><span class="metric_view_label">AOV</span><span class="metric_view_value">$<?php echo number_format($experiment['wizAov'], 2); ?></span></td>
                    <td><span class="metric_view_label">Unsub. Rate</span><span class="metric_view_value"><?php echo number_format($experiment['wizUnsubRate'], 2); ?>%</span></td>
                </tr>
            </table>
        </div>
        <?php
    }
    ?>
    </div>
    <?php
}
?>

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

foreach ($displayTemplates as $template) { ?>
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
?>
</div>

</div>

<?php get_footer(); ?>
