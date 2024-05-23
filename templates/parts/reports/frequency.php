<?php
//if startDate and endDate aren't set, default them to this month's first and last day
if (!isset($_GET['startDate']) && !isset($_GET['endDate'])) {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
}



$sendByWeekData = get_sends_by_week_data($startDate, $endDate);

$sendCountGroups = $sendByWeekData['sendCountGroups'] ?? [];
$totalUsers = $sendByWeekData['totalUsers'] ?? 0;

// Generate the HTML table
?>
<div class="wizcampaign-sections-row">
    <div class="wizcampaign-section inset flex" id="send-count-trends">
        <div class="wizcampaign-section-title-area">
            <h4>Send Count Trends</h4>
            <div class="wizcampaign-section-title-area-right">
                <span><a href="<?php echo add_query_arg(['frequency-view'=>'per-month']); ?>"><i class="fa-regular fa-calendar-days"></i>&nbsp;&nbsp;View by Month</a>
                    | <a href="<?php echo add_query_arg(['frequency-view' => 'per-week']); ?>"><i class="fa-solid fa-calendar-week"></i>&nbsp;&nbsp;View by Week</a></span>
            </div>
        </div>
        <div id="send-count-trends-container">
            <?php
            $frequencyView = $_GET['frequency-view'] ?? 'per-week';
            // use get_sends_by_week_data function to get datasets for each week in the range by passing the date ranges for each week in the range
            $weekRanges = [];
            $currentDate = new DateTime($startDate);
            $endDateTime = new DateTime($endDate);
            while ($currentDate <= $endDateTime) {
                $weekStart = $currentDate->format('Y-m-d');
                $currentDate->modify('+6 days');
                $weekEnd = $currentDate->format('Y-m-d');
                $weekRanges[] = [
                    'start' => $weekStart,
                    'end' => $weekEnd
                ];
                $currentDate->modify('+1 day');
            }


            if ($frequencyView === 'per-week') {
                $currentMonth = '';
                foreach ($weekRanges as $weekRange) {
                    $data = get_sends_by_week_data($weekRange['start'], $weekRange['end']);
                    $weekMonth = date('Y-m', strtotime($weekRange['start']));

                    if ($weekMonth !== $currentMonth) {
                        if ($currentMonth !== '') {
                            echo '</div>'; // Close the previous month's wrapper
                        }
                        $currentMonth = $weekMonth;
                        echo '<div class="month-wrapper">';
                        echo '<h3 class="month-header">' . date('F Y', strtotime($weekRange['start'])) . '</h3>';
                    }
            ?>
                    <div class="week-row">
                        <div class="week-header"><?php echo date('m/d', strtotime($weekRange['start'])) . ' - ' . date('m/d', strtotime($weekRange['end'])); ?></div>
                        <div class="week-data">
                            <?php
                            $sendCountPercentages = array_map(function ($userCount) use ($data) {
                                return round(($userCount / $data['totalUsers']) * 100, 2);
                            }, $data['sendCountGroups']);

                            arsort($sendCountPercentages);

                            $topSendCounts = array_slice($sendCountPercentages, 0, 3, true);

                            foreach ($topSendCounts as $sendCount => $percentage) {
                                $userCount = $data['sendCountGroups'][$sendCount];
                            ?>
                                <div class="send-count-item">
                                    <div class="send-count-value"><?php echo number_format($sendCount); ?></div>
                                    <div class="send-count-users"><?php echo number_format($userCount); ?> users</div>
                                    <div class="send-count-percentage">(<?php echo $percentage; ?>%)</div>
                                </div>
                            <?php
                            }
                            ?>
                        </div>
                    </div>
                <?php
                }
                echo '</div>'; // Close the last month's wrapper
            } elseif ($frequencyView === 'per-month') {
                $monthRanges = [];
                $currentDate = new DateTime($startDate);
                $endDateTime = new DateTime($endDate);
                while ($currentDate <= $endDateTime) {
                    $monthStart = $currentDate->format('Y-m-01');
                    $currentDate->modify('last day of this month');
                    $monthEnd = $currentDate->format('Y-m-d');
                    $monthRanges[] = [
                        'start' => $monthStart,
                        'end' => $monthEnd
                    ];
                    $currentDate->modify('first day of next month');
                }
                foreach ($monthRanges as $monthRange) {
                    $data = get_sends_by_week_data($monthRange['start'], $monthRange['end']);
                ?>
                    <div class="month-wrapper">
                        <h3 class="month-header"><?php echo date('F Y', strtotime($monthRange['start'])); ?></h3>
                        <div class="month-data">
                            <h6>Top 3 # of sends</h6>
                            <?php
                            $sendCountPercentages = array_map(function ($userCount) use ($data) {
                                return round(($userCount / $data['totalUsers']) * 100, 2);
                            }, $data['sendCountGroups']);

                            arsort($sendCountPercentages);

                            $topSendCounts = array_slice($sendCountPercentages, 0, 3, true);
                            foreach ($topSendCounts as $sendCount => $percentage) {
                                $userCount = $data['sendCountGroups'][$sendCount];
                            ?>
                                <div class="send-count-row">
                                    <div class="send-count-value"><?php echo number_format($sendCount); ?></div>
                                    <div class="send-count-users"><?php echo number_format($userCount); ?> users</div>
                                    <div class="send-count-percentage">(<?php echo $percentage; ?>%)</div>
                                </div>
                            <?php
                            }
                            ?>
                        </div>
                    </div>
            <?php
                }
            }
            ?>
        </div>

    </div>
</div>
<div class=" wizcampaign-sections-row">
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
                    <?php foreach ($sendCountGroups as $sendCount => $userCount) : ?>
                        <tr>
                            <td><?php echo $sendCount; ?></td>
                            <td><?php echo $userCount; ?></td>
                            <td><?php echo number_format($userCount / $totalUsers * 100, 2); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td><?php echo $totalUsers; ?></td>
                        <td><?php echo number_format(100, 2); ?>%</td>
                    </tr>
                </tfoot>
                </tbody>
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
                <?php } else { ?>
                    <a href="<?php echo add_query_arg('cohort-mode', 'separate'); ?>">Separate Cohorts</a>
                <?php } ?>
            </div>
        </div>
        <?php
        // Get campaigns within date
        $campaignsInDates = get_idwiz_campaigns(['startAt_start' => $startDate, 'startAt_end' => $endDate, 'messageMedium' => 'Email']);
        //print_r($campaignsInDates);
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
                    $totalCampaigns = count($campaignsInDates);
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