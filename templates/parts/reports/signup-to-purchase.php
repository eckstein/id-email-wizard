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

GROUP BY userId", ARRAY_A);

$lengthToPurchaseData = [
    'Day 0' => ['min' => 0, 'max' => 0, 'count' => 0],
    '1-7 days' => ['min' => 1, 'max' => 7, 'count' => 0],
    '8-14 days' => ['min' => 8, 'max' => 14, 'count' => 0],
    '15-30 days' => ['min' => 15, 'max' => 30, 'count' => 0],
    'Month 2' => ['min' => 31, 'max' => 60, 'count' => 0],
    'Month 3' => ['min' => 61, 'max' => 90, 'count' => 0],
    'Month 4' => ['min' => 91, 'max' => 120, 'count' => 0],
    'Month 5' => ['min' => 121, 'max' => 150, 'count' => 0],
    'Month 6' => ['min' => 151, 'max' => 180, 'count' => 0],
    'Month 7' => ['min' => 181, 'max' => 210, 'count' => 0],
    'Month 8' => ['min' => 211, 'max' => 240, 'count' => 0],
    'Month 9' => ['min' => 241, 'max' => 270, 'count' => 0],
    'Month 10' => ['min' => 271, 'max' => 300, 'count' => 0],
    'Month 11' => ['min' => 301, 'max' => 330, 'count' => 0],
    'Month 12' => ['min' => 331, 'max' => 360, 'count' => 0],
    'By Year 2' => ['min' => 361, 'max' => 480, 'count' => 0],
    'Year 2' => ['min' => 481, 'max' => 540, 'count' => 0],
    '2 Years +' => ['min' => 541, 'max' => PHP_INT_MAX, 'count' => 0],
];

foreach ($userPurchases as $purchase) {
    $userId = $purchase['userId'];
    $signupDate = $users[$userId];
    $firstPurchaseDate = $purchase['firstPurchaseDate'];

    // Convert signup date from UTC to PST
    $signupDatePST = new DateTime($signupDate, new DateTimeZone('UTC'));
    $signupDatePST->setTimezone(new DateTimeZone('America/Los_Angeles'));

    // Calculate the length of time between signup and first purchase
    $lengthToPurchase = round((strtotime($firstPurchaseDate) - $signupDatePST->getTimestamp()) / (60 * 60 * 24)); // Convert seconds to days

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
echo '<div class="tinyTableWrapper">';
echo "<table class='wizcampaign-tiny-table static tall'>";
echo "<thead><tr><th>Length to Purchase (in days)</th><th>Number of Purchasers</th><th>Percentage</th></tr></thead>";
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
echo "</div>";

// Display the purchases within the date range
echo '<div class="wizcampaign-section inset">';
echo '<div class="wizcampaign-section-title-area">';
echo '<h4>First Purchases within Date Range</h4>';
echo '</div>';

// Create an array to store purchase data
$purchaseData = [];

// Loop through the user purchases and filter by date range
foreach ($userPurchases as $purchase) {
    $userId = $purchase['userId'];
    $signupDate = $users[$userId];
    $firstPurchaseDate = $purchase['firstPurchaseDate'];

    // Convert signup date from UTC to PST
    $signupDatePST = new DateTime($signupDate, new DateTimeZone('UTC'));
    $signupDatePST->setTimezone(new DateTimeZone('America/Los_Angeles'));

    // Check if the first purchase date is within the specified date range
    if ($firstPurchaseDate >= $startDate && $firstPurchaseDate <= $endDate) {
        // Calculate the length of time between signup and first purchase
        $lengthToPurchase = round((strtotime($firstPurchaseDate) - $signupDatePST->getTimestamp()) / (60 * 60 * 24)); // Convert seconds to days

        // Handle negative values
        if ($lengthToPurchase < 0) {
            $lengthToPurchase = 0;
        }

        // Add the data to the purchase array
        if (!isset($purchaseData[$lengthToPurchase])) {
            $purchaseData[$lengthToPurchase] = 0;
        }
        $purchaseData[$lengthToPurchase]++;
    }
}

// Sort the purchase data by days to purchase
ksort($purchaseData);

// Display the purchase data table
echo '<div class="tinyTableWrapper">';
echo "<table class='wizcampaign-tiny-table tall'>";
echo "<thead><tr><th>Days from Signup to Purchase</th><th>Purchases</th><th>Percent</th></tr></thead>";
echo "<tbody>";
foreach ($purchaseData as $lengthToPurchase => $count) {
    echo "<tr>";
    echo "<td>" . $lengthToPurchase . "</td>";
    echo "<td>" . $count . "</td>";
    echo "<td>" . round(($count / $totalPurchasers) * 100, 2) . "%</td>";
    echo "</tr>";
}
echo "</tbody>";
echo "</table>";

echo "</div>";
echo "</div>";

echo "</div>";
