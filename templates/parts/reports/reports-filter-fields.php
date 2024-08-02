<div id="wiz-report-filterbar" class="wizcampaign-sections-row">
    <form id="reports-filter-form" class="<?php if ($reportType != 'open-click-trends'){echo 'refresh-on-submit';}; ?>">
        <?php
        $cohortLabels = get_all_cohort_labels();

        $selectedCohorts = isset($_GET['cohorts']) ? explode(',', $_GET['cohorts']) : [];
        $excludedCohorts = isset($_GET['exclude_cohorts']) ? explode(',', $_GET['exclude_cohorts']) : [];
        ?>
        <fieldset class="wiz-report-controlset" id="wiz-report-date-controls">
            <div class="wiz-report-controlset-field">
                <?php $startDate = isset($_GET['startDate']) ? $_GET['startDate'] : date('Y-m-01'); ?>
                <input type="date" name="startDate" id="wizStartDate" value="<?php echo esc_attr($startDate); ?>">
                &nbsp;thru&nbsp;
                <?php $endDate = isset($_GET['endDate']) ? $_GET['endDate'] : date('Y-m-d'); ?>
                <input type="date" name="endDate" id="wizEndDate" value="<?php echo esc_attr($endDate); ?>">

            </div>

        </fieldset>


        <?php if ($reportType == 'open-click-trends') { ?>

        <fieldset class="wiz-report-controlset" id="wiz-report-sendsize-controls">
            <div class="wiz-report-controlset-field">
                <?php $setMin = isset($_GET['minSendSize']) ? $_GET['minSendSize'] : 1; ?>
                <label for="wiz-report-sendsize-min">Min sends</label><input name="minSendSize" type="number" min="1" step="1" value="<?php echo $setMin; ?>" id="wiz-report-sendsize-min" />
            </div>
            <div class="wiz-report-controlset-field">
                <?php $setMax = isset($_GET['maxSendSize']) ? $_GET['maxSendSize'] : 500000; ?>
                <label for="wiz-report-sendsize-max">Max sends</label><input name="maxSendSize" type="number" min="1" step="1" value="<?php echo $setMax; ?>" id="wiz-report-sendsize-max" />
            </div>
        </fieldset>
        <fieldset class="wiz-report-controlset" id="wiz-report-openrate-controls">
            <div class="wiz-report-controlset-field">
                <?php $setMinOpenRate = isset($_GET['minOpenRate']) ? $_GET['minOpenRate'] : 0; ?>
                <label for="wiz-report-openrate-min">Min open rate</label><input name="minOpenRate" type="number" min="0" step="1" value="<?php echo $setMinOpenRate; ?>" id="wiz-report-openrate-min" />
            </div>
            <div class="wiz-report-controlset-field">
                <?php $setMaxOpenRate = isset($_GET['maxOpenRate']) ? $_GET['maxOpenRate'] : 100; ?>
                <label for="wiz-report-openrate-max">Max open rate</label><input name="maxOpenRate" type="number" min="0" step="0.01" value="<?php echo $setMaxOpenRate; ?>" id="wiz-report-openrate-max" />
            </div>
        </fieldset>
        <fieldset class="wiz-report-controlset" id="wiz-report-clickrate-controls">
            <div class="wiz-report-controlset-field">
                <?php $setMinClickRate = isset($_GET['minClickRate']) ? $_GET['minClickRate'] : 0; ?>
                <label for="wiz-report-clickrate-min">Min click rate</label><input name="minClickRate" type="number" min="0" step="0.01" value="<?php echo $setMinClickRate; ?>" id="wiz-report-clickrate-min" />
            </div>
            <div class="wiz-report-controlset-field">
                <?php $setMaxClickRate = isset($_GET['maxClickRate']) ? $_GET['maxClickRate'] : 100; ?>
                <label for="wiz-report-clickrate-max">Max click rate</label><input name="maxClickRate" type="number" min="0" step="0.01" value="<?php echo $setMaxClickRate; ?>" id="wiz-report-clickrate-max" />
            </div>
        </fieldset>
        <fieldset class="wiz-report-controlset" id="wiz-report-ctorate-controls">
            <div class="wiz-report-controlset-field">
                <?php $setMinCtoRate = isset($_GET['minCtoRate']) ? $_GET['minCtoRate'] : 0; ?>
                <label for="wiz-report-ctorate-min">Min CTO</label><input name="minCtoRate" type="number" min="0" step="0.01" value="<?php echo $setMinCtoRate; ?>" id="wiz-report-ctorate-min" />
            </div>
            <div class="wiz-report-controlset-field">
                <?php $setMaxCtoRate = isset($_GET['maxCtoRate']) ? $_GET['maxCtoRate'] : 100; ?>
                <label for="wiz-report-ctorate-max">Max CTO</label><input name="maxCtoRate" type="number" min="0" step="0.01" value="<?php echo $setMaxCtoRate; ?>" id="wiz-report-ctorate-max" />
            </div>

        </fieldset>
        <fieldset class="wiz-report-controlset" id="wiz-report-cohort-controls">
            <div class="wiz-report-controlset-field">
                <label for="wiz-report-cohorts">Include:</label>
                <select id="wiz-report-cohorts" name="cohorts" class="cohort-select" multiple>
                    <option value="all">All</option>
                    <?php
                    foreach ($cohortLabels as $cohortLabel) {
                        $cohortSelected = in_array($cohortLabel, $selectedCohorts) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($cohortLabel) . '" ' . $cohortSelected . '>' . htmlspecialchars($cohortLabel) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="wiz-report-controlset-field">
                <label for="wiz-report-exclude_cohorts">Exclude</label>
                <select id="wiz-report-exclude_cohorts" name="exclude_cohorts" class="cohort-exclude" multiple>
                    <?php
                    foreach ($cohortLabels as $cohortLabel) {
                        $excludedCohortSelected = in_array($cohortLabel, $excludedCohorts) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($cohortLabel) . '" ' . $excludedCohortSelected . '>' . htmlspecialchars($cohortLabel) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </fieldset>
        <?php } ?>
        <fieldset class="wiz-report-controlset" id="wiz-report-ctorate-controls">
            <div class="wiz-report-controlset-field">
                <input id="reports-filter-submit" type="submit" value="Update" class="wiz-button green" />
            </div>
        </fieldset>
    </form>
</div>