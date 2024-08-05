<?php
$startDate = $_GET['startDate'] ?? date('Y-d-01');
$endDate = $_GET['endDate'] ?? date('Y-m-d');
$frequencyView = $_GET['frequency-view'] ?? 'per-month';
$cohortMode = $_GET['cohort-mode'] ?? 'combine';

// Get the iterations for each month
$monthlyIterations = get_monthly_iterations($startDate, $endDate);
?>

<div class="wizcampaign-sections-row">
    <div class="wizcampaign-section inset flex" id="send-count-trends">
        <div class="wizcampaign-section-title-area">
            <h4>Send Count Summary (<?php echo htmlspecialchars($frequencyView); ?>)</h4>
            <div class="wizcampaign-section-title-area-right">
                <span>
                    <a href="<?php echo add_query_arg(['frequency-view' => 'per-month']); ?>">
                        <i class="fa-regular fa-calendar-days"></i>&nbsp;&nbsp;View by Month
                    </a> |
                    <a href="<?php echo add_query_arg(['frequency-view' => 'per-week']); ?>">
                        <i class="fa-solid fa-calendar-week"></i>&nbsp;&nbsp;View by Week
                    </a>
                </span>
            </div>
        </div>
        <div id="send-count-trends-container">
            <?php foreach ($monthlyIterations as $iteration) : ?>
                <div class="month-wrapper">
                    <h3 class="month-header"><?php echo date('F Y', strtotime($iteration['start'])); ?></h3>
                    <?php if ($frequencyView === 'per-week') : ?>
                        <?php
                        $weekRanges = get_weekly_data($iteration['start'], $iteration['end']);
                        foreach ($weekRanges as $weekRange) :
                            $weekData = get_sends_by_week_data($weekRange['start'], $weekRange['end'], 100, 'weekly');

                            $topSendCounts = calculate_send_data($weekData['weeklyData'], $weekData['totalUsers'], 50);
                        ?>
                            <div class="week-row">
                                <div class="week-header"><?php echo date('m/d', strtotime($weekRange['start'])); ?> - <?php echo date('m/d', strtotime($weekRange['end'])); ?></div>
                                <div class="week-data">
                                    <?php foreach ($topSendCounts as $sendCount => $percentage) : ?>
                                        <div class="send-count-item">
                                            <div class="send-count-value"><?php echo number_format($sendCount); ?></div>
                                            <div class="send-count-users"><?php echo number_format($weekData['weeklyData'][$sendCount]); ?> users</div>
                                            <div class="send-count-percentage"><?php echo $percentage; ?>%</div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <?php
                        $monthlyData = get_sends_by_week_data($iteration['start'], $iteration['end'], 100, 'monthly');

                        $topSendCounts = calculate_send_data($monthlyData['monthlyData'], $monthlyData['totalUsers'], 100);
                        ?>
                        <div class="month-data">
                            <h6>Top 3 # of sends</h6>
                            <?php foreach ($topSendCounts as $sendCount => $percentage) : ?>
                                <div class="send-count-row">
                                    <div class="send-count-value"><?php echo number_format($sendCount); ?></div>
                                    <div class="send-count-users"><?php echo number_format($monthlyData['monthlyData'][$sendCount]); ?> users</div>
                                    <div class="send-count-percentage"><?php echo $percentage; ?>%</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="wizcampaign-sections-row">
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Send Frequency Within Dates</h4>
        </div>
        <div class="tinyTableWrapper" id="send-frequency-table" data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>">
            <table class="wizcampaign-tiny-table tall">
                <thead>
                    <tr>
                        <th>Sends</th>
                        <th>Number of Users</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $allData = get_sends_by_week_data($startDate, $endDate, 100, 'all');;

                    foreach ($allData['allData'] as $sendCount => $userCount) :
                        if ($userCount > 0) : ?>
                            <tr>
                                <td><?php echo number_format($sendCount); ?></td>
                                <td><?php echo number_format($userCount); ?></td>
                                <td><?php echo $allData['totalUsers'] > 0 ? number_format($userCount / $allData['totalUsers'] * 100, 2) : 0; ?>%</td>
                            </tr>
                    <?php endif;
                    endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td><?php echo number_format($allData['totalUsers']); ?></td>
                        <td><?php echo number_format(100, 2); ?>%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Cohort Frequency Within Dates</h4>
            
        </div>
        <?php
        $campaignsInDates = get_idwiz_campaigns([
            'startAt_start' => $startDate,
            'startAt_end' => $endDate,
            'messageMedium' => 'Email'
        ]);
        $cohortResults = sort_campaigns_into_cohorts($campaignsInDates);
        ?>
        <div class="tinyTableWrapper" id="send-frequency-cohort-table" data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>">
            <table class="wizcampaign-tiny-table tall">
                <thead>
                    <tr>
                        <th>Cohort</th>
                        <th>Number of Campaigns</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cohortResults['cohorts'] as $cohort => $campaigns) : ?>
                        <tr>
                            <td><?php echo $cohort; ?></td>
                            <td><?php echo count($campaigns); ?></td>
                            <td><?php echo $cohortResults['totalCampaigns'] > 0 ? number_format((count($campaigns) / $cohortResults['totalCampaigns']) * 100, 2) : 0; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td><?php echo $cohortResults['totalCampaigns']; ?></td>
                        <td><?php echo number_format(100, 2); ?>%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>