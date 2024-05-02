<?php get_header();
global $wpdb;

// Define default values
$startDate = date('Y-m-01'); // Default start date is the first day of the current month
$endDate = date('Y-m-d');    // Default end date is today

// Check if the startDate and endDate parameters are present in the $_GET array
if (isset($_GET['startDate']) && $_GET['startDate'] !== '') {
    $startDate = $_GET['startDate'];
} else {
    $startDate = date('Y-m-01');
}
if (isset($_GET['endDate']) && $_GET['endDate'] !== '') {
    $endDate = $_GET['endDate'];
} else {
    $endDate = date('Y-m-d'); // End date is today
}
echo $startDate . ' - ' . $endDate;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="wizHeader">
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">
                <h1 class="wizEntry-title" itemprop="name">
                    Reports
                </h1>



            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">

                </div>
            </div>
        </div>
    </header>

    <div class="entry-content" itemprop="mainContentOfPage">
        <?php //include plugin_dir_path( __FILE__  ) . 'parts/reports-home.php'; 
        ?>
        <?php include plugin_dir_path(__FILE__) . 'parts/dashboard-date-pickers.php'; ?>
        <?php
        $tableCampaigns = array(7483526, 7483524, 7483527, 8134806, 7483528, 7483523, 8134829);
        $metrics = ['sends', 'open', 'clicks', 'unsubscribes'];
        $args = [
            'campaignIds' => $tableCampaigns,
            'startAt_start' => $startDate,
            'startAt_end' => $endDate,
            'fields' => 'campaignId'
        ];

        $campaignsSends = get_idemailwiz_triggered_data('idemailwiz_triggered_sends', $args);
        $campaignsOpens = get_idemailwiz_triggered_data('idemailwiz_triggered_opens', $args);
        $campaignsClicks = get_idemailwiz_triggered_data('idemailwiz_triggered_clicks', $args);
        $campaignsUnsubscribes = get_idemailwiz_triggered_data('idemailwiz_triggered_unsubscribes', $args);

        $purchaseArgs = $args + ['fields' => 'total'];
        $campaignsPurchases = get_idwiz_purchases($purchaseArgs);

        //print_r($campaignsSends);
        ?>
        <table class="idemailwiz_table">
            <th>Campaign</th>
            <th>Sent</th>
            <th>Delivered</th>
            <th>Opens</th>
            <th>Open Rate</th>
            <th>Clicks</th>
            <th>Click Rate</th>
            <th>CTO</th>
            <th>Regs</th>
            <th>CVR</th>
            <th>Bookings</th>
            <th>Unsub Rate</th>
            <?php foreach ($tableCampaigns as $campaign) {
                $wizCampaign = get_idwiz_campaign($campaign);
                // Gather the data for this campaign from the databases
                $tableData = [
                    'purchases' => array_filter($campaignsPurchases, function ($purchase) use ($campaign) {
                        return $purchase['campaignId'] == $campaign;
                    }),
                    'sent' => count(array_filter($campaignsSends, function ($send) use ($campaign) {
                        return $send['campaignId'] == $campaign;
                    })),
                    'opens' => count(array_filter($campaignsOpens, function ($open) use ($campaign) {
                        return $open['campaignId'] == $campaign;
                    })),
                    'clicks' => count(array_filter($campaignsClicks, function ($click) use ($campaign) {
                        return $click['campaignId'] == $campaign;
                    })),
                    'unsubscribes' => count(array_filter($campaignsUnsubscribes, function ($unsubscribe) use ($campaign) {
                        return $unsubscribe['campaignId'] == $campaign;
                    })),
                ];

                // Calculate the metrics
                $delivered = $tableData['sent'];
                $openRate = $delivered > 0 ? round(($tableData['opens'] / $delivered) * 100, 2) : 0;
                $clickRate = $delivered > 0 ? round(($tableData['clicks'] / $delivered) * 100, 2) : 0;
                $cto = $tableData['opens'] > 0 ? round(($tableData['clicks'] / $tableData['opens']) * 100, 2) : 0;
                $regs = count($tableData['purchases']);
                $cvr = $delivered > 0 ? round(($regs / $delivered) * 100, 2) : 0;
                $bookings = array_sum(array_column($tableData['purchases'], 'total'));
                $unsubRate = $delivered > 0 ? round(($tableData['unsubscribes'] / $delivered) * 100, 2) : 0;
            ?>
                <tr>
                    <td><?php echo $wizCampaign['name']; ?></td>
                    <td><?php echo number_format($tableData['sent']); ?></td>
                    <td><?php echo number_format($delivered); ?></td>
                    <td><?php echo number_format($tableData['opens']); ?></td>
                    <td><?php echo $openRate; ?>%</td>
                    <td><?php echo number_format($tableData['clicks']); ?></td>
                    <td><?php echo $clickRate; ?>%</td>
                    <td><?php echo $cto; ?>%</td>
                    <td><?php echo $regs; ?></td>
                    <td><?php echo $cvr; ?>%</td>
                    <td><?php echo '$' . number_format($bookings, 2); ?></td>
                    <td><?php echo $unsubRate; ?>%</td>
                </tr>
            <?php } ?>
        </table>


    </div>




</article>
<?php get_footer(); ?>