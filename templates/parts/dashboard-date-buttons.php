<div class="dashboard-date-buttons">
    <a class="wiz-button green"
        href="<?php echo add_query_arg(array('startDate' => date("Y-m-01"), 'endDate' => date("Y-m-d"))); ?>"
        data-start="<?php echo date("Y-m-01"); ?>" data-end="<?php echo date("Y-m-d"); ?>">This Month</a>
    <a class="wiz-button green"
        href="<?php echo add_query_arg(array('startDate' => date("Y-m-01", strtotime("-32 days")), 'endDate' => date("Y-m-t", strtotime("-32 days")))); ?>"
        data-start="<?php echo date("Y-m-01", strtotime("-32 days")); ?>"
        data-end="<?php echo date("Y-m-t", strtotime("-32 days")); ?>">Last Month</a>
    <a class="wiz-button green"
        href="<?php echo add_query_arg(array('startDate' => date("Y-m-01", strtotime("-370 days")), 'endDate' => date("Y-m-t", strtotime("-370 days")))); ?>"
        data-start="<?php echo date("Y-m-01", strtotime("-370 days")); ?>"
        data-end="<?php echo date("Y-m-t", strtotime("-370 days")); ?>">This Month, Last Year</a>
    <a class="wiz-button green"
        href="<?php echo add_query_arg(array('startDate' => '2021-11-01', 'endDate' => date("Y-m-d"))); ?>"
        data-start="2021-11-01" data-end="<?php echo date("Y-m-d"); ?>">All Time</a>
    <select class="month-year-select">
        <option></option>
        <?php
        $currentMonth = date('n');
        $currentYear = date('Y');

        for ($y = $currentYear; $y >= 2021; $y--) {
            $startMonth = ($y == $currentYear) ? $currentMonth : 12; // Start from the current month if it's the current year
        
            for ($m = $startMonth; $m >= 1; $m--) {
                if ($y == 2021 && $m < 11) {
                    break; // Stop the loop if it reaches before November 2021
                }
                $monthValue = date('Y-m', mktime(0, 0, 0, $m, 1, $y)); // Format for the value attribute
                $monthName = date('M Y', mktime(0, 0, 0, $m, 1, $y)); // Format for the display
                echo '<option value="' . $monthValue . '">' . $monthName . '</option>';
            }
        }
        ?>
    </select>

</div>