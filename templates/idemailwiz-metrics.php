<?php
$args = array(
    'campaignId' => '7346803',

);
$results = get_idwiz_purchases($args);
foreach ($results as $result) {
    echo $result['shoppingCartItems_divisionName'],'<br/>';
    echo $result['shoppingCartItems_name'],'<br/>';
}




