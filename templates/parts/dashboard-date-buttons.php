<?php
date_default_timezone_set('America/Los_Angeles');
?>

<div class="dashboard-date-buttons">
    <a class="wiz-button green" href="<?php $now = new DateTime();
                                        echo add_query_arg(array('startDate' => $now->format("Y-m-01"), 'endDate' => $now->format("Y-m-d"))); ?>" data-start="<?php echo $now->format("Y-m-01"); ?>" data-end="<?php echo $now->format("Y-m-d"); ?>">This Month</a>
    <a class="wiz-button green" href="<?php $lastMonth = new DateTime();
                                        $lastMonth->modify("-1 month");
                                        echo add_query_arg(array('startDate' => $lastMonth->format("Y-m-01"), 'endDate' => $lastMonth->format("Y-m-t"))); ?>" data-start="<?php echo $lastMonth->format("Y-m-01"); ?>" data-end="<?php echo $lastMonth->format("Y-m-t"); ?>">Last Month</a>
    <a class="wiz-button green" href="<?php $lastYear = new DateTime();
                                        $lastYear->modify("-1 year");
                                        echo add_query_arg(array('startDate' => $lastYear->format("Y-m-01"), 'endDate' => $lastYear->format("Y-m-t"))); ?>" data-start="<?php echo $lastYear->format("Y-m-01"); ?>" data-end="<?php echo $lastYear->format("Y-m-t"); ?>">This Month, Last Year</a>
    <a class="wiz-button green" href="<?php echo add_query_arg(array('startDate' => '2021-11-01', 'endDate' => $now->format("Y-m-d"))); ?>" data-start="2021-11-01" data-end="<?php echo $now->format("Y-m-d"); ?>">All Time</a>
    <select class="month-year-select">
        <option></option>
        <?php
        $currentDate = new DateTime();
        $currentYear = (int)$currentDate->format('Y');
        $currentMonth = (int)$currentDate->format('n');

        for ($y = $currentYear; $y >= 2021; $y--) {
            $startMonth = ($y == $currentYear) ? $currentMonth : 12; // Start from the current month if it's the current year

            for ($m = $startMonth; $m >= 1; $m--) {
                if ($y == 2021 && $m < 11) {
                    break; // Stop the loop if it reaches before November 2021
                }
                $monthDate = new DateTime();
                $monthDate->setDate($y, $m, 1);
                $monthValue = $monthDate->format('Y-m'); // Format for the value attribute
                $monthName = $monthDate->format('M Y'); // Format for the display
                echo '<option value="' . $monthValue . '">' . $monthName . '</option>';
            }
        }
        ?>
    </select>
</div>