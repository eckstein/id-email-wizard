<div class="dashboard-date-buttons">
    <a class="wiz-button green"
        href="<?php echo add_query_arg(array('startDate' => date("Y-m-01"), 'endDate' => date("Y-m-d"))); ?>"
        data-start="<?php echo date("Y-m-01"); ?>" data-end="<?php echo date("Y-m-d"); ?>">This Month</a>
    <a class="wiz-button green"
        href="<?php echo add_query_arg(array('startDate' => date("Y-m-01", strtotime("-1 month")), 'endDate' => date("Y-m-t", strtotime("-1 month")))); ?>"
        data-start="<?php echo date("Y-m-01", strtotime("-1 month")); ?>"
        data-end="<?php echo date("Y-m-t", strtotime("-1 month")); ?>">Last Month</a>
    <a class="wiz-button green"
        href="<?php echo add_query_arg(array('startDate' => date("Y-m-01", strtotime("-1 year")), 'endDate' => date("Y-m-t", strtotime("-1 year")))); ?>"
        data-start="<?php echo date("Y-m-01", strtotime("-1 year")); ?>"
        data-end="<?php echo date("Y-m-t", strtotime("-1 year")); ?>">This Month, Last Year</a>
    <a class="wiz-button green"
        href="<?php echo add_query_arg(array('startDate' => '2021-11-01', 'endDate' => date("Y-m-d"))); ?>"
        data-start="2021-11-01" data-end="<?php echo date("Y-m-d"); ?>">All Time</a>
</div>