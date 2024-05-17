<?php
global $wpdb;
$blastSendsTable = $wpdb->prefix . 'idemailwiz_blast_sends';
$triggeredSendsTable = $wpdb->prefix . 'idemailwiz_triggered_sends';
$usersTable = $wpdb->prefix . 'idemailwiz_users';

// Convert $startDate and $endDate to millisecond timestamps in UTC
$startTimestamp = strtotime($startDate . ' America/Los_Angeles') * 1000;
$endTimestamp = strtotime($endDate . ' America/Los_Angeles') * 1000;

$batchSize = 50000; // Adjust the batch size as needed
$offset = 0;
$totalSends = 0;
$userSendCounts = [];

do {
    // Retrieve sends data in batches
    $sendsBatch = $wpdb->get_results("
        SELECT userId, COUNT(*) AS send_count
        FROM (
            SELECT userId
            FROM $blastSendsTable 
            WHERE startAt BETWEEN $startTimestamp AND $endTimestamp
            AND userId IS NOT NULL AND userId <> ''
            UNION ALL
            SELECT userId
            FROM $triggeredSendsTable 
            WHERE startAt BETWEEN $startTimestamp AND $endTimestamp
            AND userId IS NOT NULL AND userId <> ''
        ) AS sends
        GROUP BY userId
        LIMIT $offset, $batchSize
    ", ARRAY_A);

    foreach ($sendsBatch as $send) {
        $userId = $send['userId'];
        $sendCount = $send['send_count'];
        $userSendCounts[$sendCount] = ($userSendCounts[$sendCount] ?? 0) + 1;
        $totalSends += $sendCount;
    }

    $offset += $batchSize;
} while (count($sendsBatch) === $batchSize);

$totalUniqueUsers = array_sum($userSendCounts);

echo "Total sends: $totalSends<br />";
echo "Total unique users: $totalUniqueUsers<br /><br />";

echo "<table class='wizcampaign-tiny-table tall'>";
echo "<thead><tr><th>Sends</th><th>Number of Users</th><th>Percentage</th></tr></thead>";
ksort($userSendCounts); // Sort the array by the number of sends
foreach ($userSendCounts as $sends => $userCount) {
    $percentage = ($userCount / $totalUniqueUsers) * 100;
    echo "<tr><td>$sends</td><td>$userCount</td><td>" . number_format($percentage, 2) . "%</td></tr>";
}
echo "</table>";
