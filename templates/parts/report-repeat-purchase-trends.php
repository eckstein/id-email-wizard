<?php

$purchases = get_idwiz_purchases(['not-ids'=>[0], 'fields' => 'id,orderId,accountNumber,campaignId,purchaseDate,total,shoppingCartItems_name,shoppingCartItems_categories, shoppingCartItems_price']);

?>
<div class="wizcampaign-sections-row">
    <div class="wizcampaign-section">
        <div class="wizcampaign-section-title-area">
            <h4>Top Repeat Purchase Products</h4>
            <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">
                <!-- You can add any icons or additional markup here -->
            </div>
        </div>
        <div class="wizcampaign-section-content">
            <?php
            $groupedPurchases = group_first_and_repeat_purchases($purchases);

            // Extract only repeat purchases
            $repeatPurchases = [];
            foreach ($groupedPurchases['groupedPurchases'] as $orderPurchases) {
                if (count($orderPurchases) > 1) {
                    $repeatPurchases = array_merge($repeatPurchases, $orderPurchases);
                }
            }

            // Group repeat purchases by product
            $productData = transfigure_purchases_by_product($repeatPurchases);

            // Sort products by the number of repeat purchases in descending order
            usort($productData, function ($a, $b) {
                return $b['Purchases'] <=> $a['Purchases'];
            });

            $repeatPurchaseHeaders = [
                'Product' => '40%',
                'Topics' => '20%',
                'Purchases' => '20%',
                'Revenue' => '20%'
            ];

            // Generate the table
            generate_mini_table($repeatPurchaseHeaders, $productData);
            ?>
        </div>
    </div>


</div>