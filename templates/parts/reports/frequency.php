<?php
// If startDate and endDate aren't set, default them to this month's first and last day
if (!isset($_GET['startDate']) && !isset($_GET['endDate'])) {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
}


$startMonth = date('m', strtotime($startDate));
$endMonth = date('m', strtotime($endDate));
$startYear = date('Y', strtotime($startDate));
$endYear = date('Y', strtotime($endDate));

$currentMonth = $startMonth;
$currentYear = $startYear;

$frequencyView = $_GET['frequency-view'] ?? 'per-month';

// Generate the HTML table
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
            <?php

            $startMonth = date('m', strtotime($startDate));
            $endMonth = date('m', strtotime($endDate));
            $startYear = date('Y', strtotime($startDate));
            $endYear = date('Y', strtotime($endDate));

            $currentMonth = $startMonth;
            $currentYear = $startYear;

            while (($currentYear < $endYear) || (($currentYear == $endYear) && ($currentMonth <= $endMonth))) {
                $monthStartDate = $currentYear . '-' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . '-01';
                $monthEndDate = date('Y-m-t', strtotime($monthStartDate));

                if ($frequencyView === 'per-week') {
                    $data = get_sends_by_week_data($monthStartDate, $monthEndDate, 50, 0, 'weekly');
            ?>
                    <div class="month-wrapper">
                        <h3 class="month-header"><?php echo date('F Y', strtotime($monthStartDate)); ?></h3>
                        <?php
                        $weekRanges = [];
                        $currentDate = new DateTime($monthStartDate);
                        $monthEndDateTime = new DateTime($monthEndDate);

                        while ($currentDate <= $monthEndDateTime) {
                            $weekStart = $currentDate->format('Y-m-d');
                            $currentDate->modify('+6 days');
                            $weekEnd = $currentDate > $monthEndDateTime ? $monthEndDateTime->format('Y-m-d') : $currentDate->format('Y-m-d');
                            $weekRanges[] = [
                                'start' => $weekStart,
                                'end' => $weekEnd
                            ];
                            $currentDate->modify('+1 day');
                        }

                        foreach ($weekRanges as $weekRange) {
                            $weekData = get_sends_by_week_data($weekRange['start'], $weekRange['end'], 50, 0, 'weekly');
                            $weekStart = date('m/d', strtotime($weekRange['start']));
                            $weekEnd = date('m/d', strtotime($weekRange['end']));
                        ?>
                            <div class="week-row">
                                <div class="week-header"><?php echo $weekStart . ' - ' . $weekEnd; ?></div>
                                <div class="week-data">
                                    <?php
                                    // Calculate the percentages
                                    $sendCountPercentages = array_map(function ($userCount) use ($weekData) {
                                        return round(($userCount / $weekData['totalUsers']) * 50, 2);
                                    }, $weekData['weeklyData']);

                                    // Sort by percentage in descending order
                                    arsort($sendCountPercentages);

                                    // Get the top 3 send counts
                                    $topSendCounts = array_slice($sendCountPercentages, 0, 3, true);
                                    foreach ($topSendCounts as $sendCount => $percentage) {
                                        $userCount = $weekData['weeklyData'][$sendCount];
                                    ?>
                                        <div class="send-count-item">
                                            <div class="send-count-value"><?php echo number_format($sendCount); ?></div>
                                            <div class="send-count-users"><?php echo number_format($userCount); ?> users</div>
                                            <div class="send-count-percentage"><?php echo $percentage; ?>%</div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php
                } elseif ($frequencyView === 'per-month') {
                    $data = get_sends_by_week_data($monthStartDate, $monthEndDate, 1000, 0, 'monthly');
                ?>
                    <div class="month-wrapper">
                        <h3 class="month-header"><?php echo date('F Y', strtotime($monthStartDate)); ?></h3>
                        <div class="month-data">
                            <h6>Top 3 # of sends</h6>
                            <?php
                            $sendCountPercentages = array_map(function ($userCount) use ($data) {
                                return $data['totalUsers'] ? round(($userCount / $data['totalUsers']) * 100, 2) : 0;
                            }, $data['monthlyData']);

                            arsort($sendCountPercentages);

                            $topSendCounts = array_slice($sendCountPercentages, 0, 3, true);
                            foreach ($topSendCounts as $sendCount => $percentage) {
                                $userCount = $data['monthlyData'][$sendCount];
                            ?>
                                <div class="send-count-row">
                                    <div class="send-count-value"><?php echo number_format($sendCount); ?></div>
                                    <div class="send-count-users"><?php echo number_format($userCount); ?> users</div>
                                    <div class="send-count-percentage"><?php echo $percentage; ?>%</div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
            <?php
                }

                // Move to the next month
                $currentMonth++;
                if ($currentMonth > 12) {
                    $currentMonth = 1;
                    $currentYear++;
                }
            }
            ?>
        </div>

    </div>
</div>

<div class="wizcampaign-sections-row">
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Send Frequency Within Dates</h4>
        </div>
        <div class="tinyTableWrapper">
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
                    $data = get_sends_by_week_data($startDate, $endDate, 100, 0, 'monthly');

                    foreach ($data['monthlyData'] as $sendCount => $userCount) : ?>
                        <tr>
                            <td><?php echo $sendCount; ?></td>
                            <td><?php echo $userCount; ?></td>
                            <td><?php echo $data['totalUsers'] ? number_format($userCount / $data['totalUsers'] * 100, 2) : 0; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td><?php echo $data['totalUsers']; ?></td>
                        <td><?php echo number_format(100, 2); ?>%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="wizcampaign-section inset">
        <div class="wizcampaign-section-title-area">
            <h4>Cohort Frequency Within Dates</h4>
            <div class="wizcampaign-section-title-area-right">
                <?php
                $cohortMode = $_GET['cohort-mode'] ?? 'combine';
                if ($cohortMode == 'separate') {
                ?>
                    <a href="<?php echo add_query_arg('cohort-mode', 'combine'); ?>">Combine Cohorts</a>
                <?php
                } else {
                ?>
                    <a href="<?php echo add_query_arg('cohort-mode', 'separate'); ?>">Separate Cohorts</a>
                <?php
                }
                ?>
            </div>
        </div>
        <?php
        // Get campaigns within date
        $campaignsInDates = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate, 'messageMedium' => 'Email']);
        $cohortResults = sortCampaignsIntoCohorts($campaignsInDates, $cohortMode);
        $sendCohorts = $cohortResults['cohorts'];
        ?>
        <div class="tinyTableWrapper">
            <table class="wizcampaign-tiny-table tall">
                <thead>
                    <tr>
                        <th>Cohort</th>
                        <th>Number of Campaigns</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($sendCohorts as $cohort => $campaigns) :
                        $campaignCount = count($campaigns);
                        $percentage = $cohortResults['totalCampaigns'] > 0 ? number_format(($campaignCount / $cohortResults['totalCampaigns']) * 100, 2) : 0;
                    ?>
                        <tr>
                            <td><?php echo $cohort; ?></td>
                            <td><?php echo $campaignCount; ?></td>
                            <td><?php echo $percentage; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td><?php echo $cohortResults['totalCampaigns']; ?></td>
                        <td><?php echo number_format(100, 2); ?>%</td>
                    </tr>
                </tfoot>
                </tbody>
            </table>
        </div>
    </div>
</div>