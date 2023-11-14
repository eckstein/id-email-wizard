<div class="dashboard-date-pickers">
    <?php
    include plugin_dir_path(__FILE__) . 'dashboard-date-buttons.php';

    // Retrieve startDate and endDate from GET parameters or set to default values
    $startDate = $startDate ?? '2021-11-01';
    $endDate = $endDate ?? date('Y-m-d');

    // Exclude startDate and endDate from the parameters to retain
    $parametersToRetain = array_diff_key($_GET, array_flip(['startDate', 'endDate']));
    ?>

    <form method="get" action="">
        <?php
        // Create hidden fields for each parameter to retain
        foreach ($parametersToRetain as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
        ?>
        <input type="date" name="startDate" id="wizStartDate" value="<?php echo esc_attr($startDate); ?>">
        &nbsp;thru&nbsp;
        <input type="date" name="endDate" id="wizEndDate" value="<?php echo esc_attr($endDate); ?>">
        <?php if (!is_page('campaigns')) { ?>
            &nbsp;<input type="submit" class="wiz-button green" value="Apply">
        <?php } ?>
    </form>
</div>
