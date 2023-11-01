<div class="dashboard-date-pickers">
    <?php include plugin_dir_path(__FILE__) . 'dashboard-date-buttons.php'; ?>
    <form method="get" action="<?php echo esc_url(remove_query_arg(['startDate', 'endDate'])); ?>">

        <!-- Add other $_GET parameters as hidden fields (except startDate and endDate) -->
        <?php
        foreach ($_GET as $key => $value) {
            if ($key !== 'startDate' && $key !== 'endDate') {
                echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
            }
        }
        ?>

        <input type="date" name="startDate" id="wizStartDate" value="<?php echo $startDate; ?>">
        &nbsp;thru&nbsp;
        <input type="date" name="endDate" id="wizEndDate" value="<?php echo $endDate; ?>">
        <?php if (!is_page('campaigns')) { ?>
        &nbsp;<input type="submit" class="wiz-button green" value="Apply">
        <?php } ?>
    </form>
</div>
