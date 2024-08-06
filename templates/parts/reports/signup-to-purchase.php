<?php

list($users, $userIds) = get_users_within_date_range($startDate, $endDate);
$userPurchases = get_user_purchases($userIds);
$lengthToPurchaseData = calculate_length_to_purchase_data($users, $userPurchases);
$purchaseData = generate_length_to_purchase_data($users, $userPurchases, $startDate, $endDate);
$metrics = calculate_metrics($users, $lengthToPurchaseData);
?>

<div class="wizcampaign-sections-row flex">
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Days to first purchase within signup date range</h4>
            <p>For users who signed up between <?php echo date('m/d/Y', strtotime($startDate)); ?> and <?php echo date('m/d/Y', strtotime($endDate)); ?>, how long until their first purchase?</p>
        </div>
        <div class="tinyTableWrapper" id="days-to-first-purchase-table" data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>">
            <table class='wizcampaign-tiny-table static tall'>
                <thead>
                    <tr>
                        <th>Length to Purchase (in days)</th>
                        <th>Number of Purchasers</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lengthToPurchaseData as $rangeLabel => $range) : ?>
                        <tr>
                            <td><?php echo $rangeLabel; ?></td>
                            <td><?php echo $range['count']; ?></td>
                            <td><?php echo ($metrics['totalPurchasers'] > 0) ? round(($range['count'] / $metrics['totalPurchasers']) * 100, 2) : 0; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong>Purchasers</strong></td>
                        <td><strong><?php echo $metrics['totalPurchasers']; ?></strong></td>
                        <td><?php echo $metrics['conversionRate']; ?>%</td>
                    </tr>
                    <tr>
                        <td><strong>Non-purchasers</strong></td>
                        <td><strong><?php echo $metrics['nonPurchasers']; ?></strong></td>
                        <td><?php echo $metrics['nonConversionRate']; ?>%</td>
                    </tr>
                    <tr>
                        <td><strong>Total Signups</strong></td>
                        <td><strong><?php echo $metrics['totalSignups']; ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>First Purchases within Date Range</h4>
            <p>For <strong>first</strong> purchases between <?php echo date('m/d/Y', strtotime($startDate)); ?> and <?php echo date('m/d/Y', strtotime($endDate)); ?>, how long since the user signed up?</p>
        </div>
        <div class="tinyTableWrapper" id="days-from-signup-table" data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>">
            <table class='wizcampaign-tiny-table tall'>
                <thead>
                    <tr>
                        <th>Days from Signup to Purchase</th>
                        <th>Purchases</th>
                        <th>Percent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchaseData as $lengthToPurchase => $count) : ?>
                        <tr>
                            <td><?php echo $lengthToPurchase; ?></td>
                            <td><?php echo $count; ?></td>
                            <td><?php echo round(($count / $metrics['totalPurchasers']) * 100, 2); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>