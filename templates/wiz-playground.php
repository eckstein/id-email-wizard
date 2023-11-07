<?php
get_header();

global $wpdb;

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="wizHeader">
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">
                <h1 class="wizEntry-title" itemprop="name">
                    Playground
                </h1>
            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">

                </div>
            </div>
        </div>
    </header>
    <div class="entry-content" itemprop="mainContentOfPage">
        <div class="wizcampaign-sections-row grid">
            <div class="wizcampaign-section inset" id="averageTimeByLOB">
                <div class="wizcampaign-section-title-area">
                    <h4>Title here</h4>
                    <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

                    </div>
                </div>
                <div class="wizcampaign-section-content">
                    <?php
                    function getTotalSendsBetweenDates($startDate, $endDate)
                    {
                        // Step 1: Retrieve the campaigns between the two dates.
                        $campaigns = get_idwiz_campaigns([
                            'type' => 'Blast',
                            'fields' => 'startAt, id',
                            'startAt_start' => $startDate,
                            'startAt_end' => $endDate
                        ]);

                        // Step 2: Extract the campaignId and get the blast metrics.
                        $campaignIds = array_column($campaigns, 'id');
                        $blastMetrics = get_idwiz_metrics(['campaignIds' => $campaignIds, 'fields' => 'uniqueEmailSends']);

                        // Step 3: Count the blast sends.
                        $blastSendCount = 0;
                        foreach ($blastMetrics as $metric) {
                            $blastSendCount += $metric['uniqueEmailSends'];
                        }

                        // Step 4: Use SQL to retrieve the count of triggered sends between the two dates.
                        global $wpdb; // Assuming you are using WordPress's global $wpdb object for database operations.
                        $startDateInMillis = strtotime($startDate) * 1000;
                        $endDateInMillis = strtotime($endDate) * 1000;
                        $tableName = $wpdb->prefix . 'idemailwiz_triggered_sends';

                        $query = $wpdb->prepare(
                            "SELECT COUNT(*) FROM $tableName WHERE startAt BETWEEN %d AND %d",
                            $startDateInMillis,
                            $endDateInMillis
                        );
                        $triggeredSendCount = $wpdb->get_var($query);




                        // Step 5: Return the counts in an array.
                        return [
                            'blastSends' => $blastSendCount,
                            'triggeredSends' => $triggeredSendCount
                        ];
                    }

                    // Example usage:
                    $thisYear = getTotalSendsBetweenDates('2022-12-15', '2023-12-15');
                    $thisYearBlast = number_format($thisYear['blastSends']);
                    $thisYearTriggered = number_format($thisYear['triggeredSends']);
                    echo '12/15/22 - Today<br/>';
                    echo "Blast Sends: {$thisYearBlast}";
                    echo "<br/>"."Triggered Sends: {$thisYearTriggered}";

                     $lastYear = getTotalSendsBetweenDates('2021-12-15', '2022-12-15');
                    $lastYearBlast = number_format($lastYear['blastSends']);
                    $lastYearTriggered = number_format($lastYear['triggeredSends']);
                    echo '<br/><br/>12/15/21 - 12/15/22<br/>';
                    echo "Blast Sends: {$lastYearBlast}";
                    echo "<br/>"."Triggered Sends: {$lastYearTriggered}";

                     $oct_dec = getTotalSendsBetweenDates('2022-10-15', '2022-12-15');
                    $oct_decBlast = number_format($oct_dec['blastSends']);
                    $oct_decTriggered = number_format($oct_dec['triggeredSends']);
                    echo '<br/><br/>12/15/21 - 12/15/22<br/>';
                    echo "Blast Sends: {$oct_decBlast}";
                    echo "<br/>"."Triggered Sends: {$oct_decTriggered}";

                    ?>
                </div>
            </div>

        </div>


    </div>
    </div>
</article>

<?php get_footer();