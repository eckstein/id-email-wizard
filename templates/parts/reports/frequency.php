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
        $sendCohorts = sortCampaignsIntoCohorts($campaignsInDates, $cohortMode);
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
                        $percentage = $totalCampaigns > 0 ? number_format(($campaignCount / $totalCampaigns) * 100, 2) : 0;
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
                        <td><?php echo $totalCampaigns; ?></td>
                        <td><?php echo number_format(100, 2); ?>%</td>
                    </tr>
                </tfoot>
                </tbody>
            </table>
        </div>
    </div>
</div>