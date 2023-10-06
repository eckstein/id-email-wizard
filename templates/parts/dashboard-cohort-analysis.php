<form id="cohort-selection-form" method="GET" class="cohort-form">

    <div class="form-group">
        <label for="purchaseMonth" class="form-label">Purchase Month & Day</label>
        <div class="field-group-wrap flex">

            <select name="purchaseMonth" id="purchaseMonth" class="form-select">
                <?php
                $selectedMonth = $_GET['purchaseMonth'] ?? date('m');
                $monthNames = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
                $monthNumber = 1;
                $thisMonthSelected = '';
                foreach ($monthNames as $monthName) {
                    if ($selectedMonth == $monthNumber) {
                        $thisMonthSelected = 'selected';
                    }
                    echo '<option value="' . $monthNumber . '" ' . $thisMonthSelected . '>' . $monthName . '</option>';
                    $monthNumber++;
                    $thisMonthSelected = '';
                }
                ?>
            </select>
            <select name="purchaseMonthDay" id="purchaseMonthDay" class="form-select">
                <?php
                $monthDay = 1;
                $selectedDay = $_GET['purchaseMonthDay'] ?? 1;
                $thisDaySelected = '';
                while ($monthDay <= 31) {
                    if ($selectedDay == $monthDay) {
                        $thisDaySelected = 'selected';
                    }
                    echo '<option value="' . $monthDay . '" ' . $thisDaySelected . '>' . $monthDay . '</option>';
                    $monthDay++;
                    $thisDaySelected = '';
                }
                ?>
            </select>
        </div>
        <div class="field-group-wrap">
            <label for="purchaseWindowDays" class="form-label">Purchase Window (days)</label>
            <?php $purchaseWindowDays = $_GET['purchaseWindowDays'] ?? 30; ?>
            <input type="number" name="purchaseWindowDays" id="purchaseWindowDays"
                value="<?php echo $purchaseWindowDays; ?>" class="form-number" />
        </div>
    </div>

    <div class="form-group">
        <label for="divisionsSelect" class="form-label">Purchased Divisions (initial purchase):</label>
        <select name="divisions[]" id="divisionsSelect" multiple="multiple" class="form-select">
            <?php
            $query = "SELECT DISTINCT cohort_value FROM {$wpdb->prefix}idemailwiz_cohorts WHERE cohort_type = 'division'";
            $result = $wpdb->get_results($query);

            // Get divisions from GET parameters
            $selectedDivisions = $_GET['divisions'] ?? ['iD Tech Camps'];
            foreach ($result as $row) {
                $divisionName = $row->cohort_value;
                $isSelected = in_array($divisionName, $selectedDivisions) ? 'selected' : '';
                echo '<option value="' . esc_attr($divisionName) . '" ' . $isSelected . '>' . esc_html($divisionName) . '</option>';
            }
            ?>
        </select>
    </div>

    <input type="hidden" name="view" value="cohort-2nd-purchases" />

    <div class="form-group">
        <input type="submit" value="Generate Chart" class="wiz-button green">
    </div>
</form>

<div class="wizcampaign-sections-row">
    <div class="wizcampaign-section inset" id="averageTimeByLOB">
        <div class="wizcampaign-section-title-area">
            <h4>Next Purchase by Purchase Date Cohort</h4>
            <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

            </div>
        </div>
        <div class="wizcampaign-section-content">
            <div class="wizChartWrapper cohort2ndPurchases">
                <canvas id="cohortChart" data-PurchaseMonth="<?php echo $selectedMonth; ?>"
                    data-purchaseMonthDay="<?php echo $selectedDay; ?>"
                    data-purchaseWindowDays="<?php echo $purchaseWindowDays; ?>"
                    data-divisions='<?php echo json_encode($selectedDivisions); ?>'></canvas>
            </div>
        </div>
    </div>

</div>

<div class="wizcampaign-sections-row">
    <div class="wizcampaign-section inset" id="averageTimeByLOB">
        <div class="wizcampaign-section-title-area">
            <h4>What does this report show?</h4>
            <div class="wizcampaign-section-title-area-right wizcampaign-section-icons">

            </div>
        </div>
        <div class="wizcampaign-section-content">
            <p>This report takes a month and day input and gathers all purchases from that day of the year and X number
                of days following (specified by the purchase window you define).
                It then locates the next purchase from that same customer any time in the following year and plots it by
                date and division.</p>
            <p>The divisions specified in the filters refer to the division of the initial purchase. The divisions
                specified
                in the chart legend (which can be toggled by clicking) refer to the division of the 2nd purchase</p>
            <p>The filter selection is <strong>year agnostic</strong>, meaning purchases found can have occured within
                any year on record (currently FY 2021-22 onward),
                however the 2nd purchase is always ensured to be <em>after</em> the first one, taking year into account.
            </p>

        </div>
    </div>

</div>