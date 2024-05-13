<?php
global $wpdb;
$usersTable = $wpdb->prefix . 'idemailwiz_users';
$purchasesTable = $wpdb->prefix . 'idemailwiz_purchases';

$batchSize = 10000; // Number of users to fetch per batch
$offset = 0; // Starting offset for pagination
$users = [];
$userIds = [];

do {
    // Get a batch of users from the DB
    $userBatch = $wpdb->get_results("SELECT userId, signupDate FROM $usersTable 
    WHERE userId IS NOT NULL AND userId <> ''
    AND signupDate > '$startDate' AND signupDate < '$endDate' 
    LIMIT $offset, $batchSize", ARRAY_A);

    // Process the batch of users
    foreach ($userBatch as $user) {
        $users[$user['userId']] = $user['signupDate'];
        $userIds[] = $user['userId'];
    }

    // Increment the offset for the next batch
    $offset += $batchSize;
} while (count($userBatch) === $batchSize);

$totalSignups = count($users);

// Retrieve all purchases for the users in a single query
$userPurchases = $wpdb->get_results("SELECT userId, MIN(purchaseDate) AS firstPurchaseDate FROM $purchasesTable 
WHERE userId IN ('" . implode("','", $userIds) . "') 
AND campaignId IS NOT NULL AND campaignId <> ''
AND (shoppingCartItems_divisionName = 'iD Tech Camps'
OR shoppingCartItems_divisionName = 'iD Teen Academies')
GROUP BY userId", ARRAY_A);

$lengthToPurchaseData = [
    '0-7' => ['min' => 0, 'max' => 7, 'count' => 0],
    '8-30' => ['min' => 8, 'max' => 30, 'count' => 0],
    '31-60' => ['min' => 31, 'max' => 60, 'count' => 0],
    '61-90' => ['min' => 61, 'max' => 90, 'count' => 0],
    '91-120' => ['min' => 91, 'max' => 120, 'count' => 0],
    '121-150' => ['min' => 121, 'max' => 150, 'count' => 0],
    '151-180' => ['min' => 151, 'max' => 180, 'count' => 0],
    '181+' => ['min' => 181, 'max' => PHP_INT_MAX, 'count' => 0],
];

foreach ($userPurchases as $purchase) {
    $userId = $purchase['userId'];
    $signupDate = $users[$userId];
    $firstPurchaseDate = $purchase['firstPurchaseDate'];

    // Calculate the length of time between signup and first purchase
    $lengthToPurchase = round((strtotime($firstPurchaseDate) - strtotime($signupDate)) / (60 * 60 * 24)); // Convert seconds to days

    // Increment the count in the corresponding range
    foreach ($lengthToPurchaseData as &$range) {
        if ($lengthToPurchase >= $range['min'] && $lengthToPurchase <= $range['max']) {
            $range['count']++;
            break;
        }
    }
}

// Calculate the total number of purchasers
$totalPurchasers = array_sum(array_column($lengthToPurchaseData, 'count'));

// Calculate the number of non-purchasers
$nonPurchasers = $totalSignups - $totalPurchasers;

// Calculate the conversion rate
$conversionRate = ($totalSignups > 0) ? round(($totalPurchasers / $totalSignups) * 100, 2) : 0;
$nonConversionRate = ($nonPurchasers > 0) ? round(($nonPurchasers / $totalSignups) * 100, 2) : 0;

echo '<div class="wizcampaign-sections-row flex">';
echo '<div class="wizcampaign-section inset">';
echo '<div class="wizcampaign-section-title-area">';
echo '<h4>Days to first purchase within signup date range</h4>';
echo '</div>';
// Display the summary table
echo "<table class='wizcampaign-tiny-table static'>";
echo "<thead><tr><th>Length to Purchase (in days)</th><th>Number of Users</th><th>Percentage</th></tr></thead>";
echo "<tbody>";
foreach ($lengthToPurchaseData as $rangeLabel => $range) {
    $count = $range['count'];
    $percentage = ($totalPurchasers > 0) ? round(($count / $totalPurchasers) * 100, 2) : 0;
    echo "<tr><td>$rangeLabel</td><td>$count</td><td>$percentage%</td></tr>";
}
echo "</tbody>";
echo "<tfoot>";
echo "<tr><td><strong>Purchasers</strong></td><td><strong>$totalPurchasers</strong></td><td>$conversionRate%</td></tr>";
echo "<tr><td><strong>Non-purchasers</strong></td><td><strong>$nonPurchasers</strong></td><td>$nonConversionRate%</td></tr>";

echo "<tr><td><strong>Total Signups</strong></td><td><strong>$totalSignups</strong></td><td></td></tr>";
echo "</tfoot>";
echo "</table>";
echo "</div>";

// Display the monthly average time to purchase
echo '<div class="wizcampaign-section inset">';
echo '<div class="wizcampaign-section-title-area">';
echo '<h4>Monthly Average Time to Purchase</h4>';
echo '</div>';

// Create an array to store monthly data
$monthlyData = [];

// Loop through the user purchases and group them by month
foreach ($userPurchases as $purchase) {
    $userId = $purchase['userId'];
    $signupDate = $users[$userId];
    $firstPurchaseDate = $purchase['firstPurchaseDate'];

    // Extract the month and year from the signup date
    $signupMonth = date('Y-m', strtotime($signupDate));

    // Calculate the length of time between signup and first purchase
    $lengthToPurchase = round((strtotime($firstPurchaseDate) - strtotime($signupDate)) / (60 * 60 * 24)); // Convert seconds to days

    // Add the data to the monthly array
    if (!isset($monthlyData[$signupMonth])) {
        $monthlyData[$signupMonth] = [
            'totalPurchasers' => 0,
            'totalDays' => 0,
        ];
    }
    $monthlyData[$signupMonth]['totalPurchasers']++;
    $monthlyData[$signupMonth]['totalDays'] += $lengthToPurchase;
}
ksort($monthlyData);
// Display the monthly data table
echo "<table class='wizcampaign-tiny-table static'>";
echo "<thead><tr><th>Signup Month</th><th>Average Time to Purchase (days)</th></tr></thead>";
echo "<tbody>";
foreach ($monthlyData as $month => $data) {
    $averageTimeToPurchase = ($data['totalPurchasers'] > 0) ? round($data['totalDays'] / $data['totalPurchasers'], 2) : 0;
    echo "<tr><td>".date('m/Y', strtotime($month))."</td><td>$averageTimeToPurchase</td></tr>";
}
echo "</tbody>";
echo "</table>";

echo "</div>";

echo "</div>";
