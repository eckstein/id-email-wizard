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
  <table id="wiztable_view_metrics_table"><tr>
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
    <canvas id="purchByDate"></canvas>
    </div>
    <div class="wizcampaign-section third inset">
    <h4>Purchases by Division</h4>
    <canvas id="purchByLOB"></canvas>
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
<div class="wizcampaign-template-html">
    <?php if (!$template) {
        echo '<div style="text-align: center; padding: 30px;">Whoops! This template can\'t be found in the database.</div>';
    } else {
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
    <iframe srcdoc="<?php echo htmlspecialchars($template['html'], ENT_QUOTES, 'UTF-8'); ?>" frameborder="0"></iframe>
    <?php } ?>
</div>

</div>

<?php get_footer(); ?>
