<div id="wiz-report-filterbar" class="wizcampaign-sections-row">
    <form id="reports-filter-form" class="<?php if ($reportType != 'open-click-trends') {
                                                echo 'refresh-on-submit';
                                            }; ?>">
        <?php
        $cohortLabels = get_all_cohort_labels();

        $selectedCohorts = isset($_GET['cohorts']) ? explode(',', $_GET['cohorts']) : [];
        $excludedCohorts = isset($_GET['exclude_cohorts']) ? explode(',', $_GET['exclude_cohorts']) : [];
        ?>
        
        <!-- Date Range - Common to all reports -->
        <fieldset class="wiz-report-controlset" id="wiz-report-date-controls">
                    <legend>Date Range</legend>
                    <div class="wiz-report-controlset-field">
                        <?php $startDate = isset($_GET['startDate']) ? $_GET['startDate'] : date('Y-m-01'); ?>
                        <input type="date" name="startDate" id="wizStartDate" value="<?php echo esc_attr($startDate); ?>">
                        <span class="date-separator">thru</span>
                        <?php $endDate = isset($_GET['endDate']) ? $_GET['endDate'] : date('Y-m-d'); ?>
                        <input type="date" name="endDate" id="wizEndDate" value="<?php echo esc_attr($endDate); ?>">
                    </div>
                </fieldset>

                <?php if ($reportType == 'open-click-trends') { ?>
                    <fieldset class="wiz-report-controlset" id="wiz-report-campaign-filters">
                        <legend>Campaign Filters</legend>
                        <div class="wiz-report-controlset-field">
                            <?php $selectedCampaignType = isset($_GET['campaignType']) ? $_GET['campaignType'] : 'all'; ?>
                            <label for="wiz-report-campaign-type">Campaign Type</label>
                            <select name="campaignType" id="wiz-report-campaign-type">
                                <option value="all" <?php echo $selectedCampaignType == 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="Blast" <?php echo $selectedCampaignType == 'Blast' ? 'selected' : ''; ?>>Blast</option>
                                <option value="Triggered" <?php echo $selectedCampaignType == 'Triggered' ? 'selected' : ''; ?>>Triggered</option>
                            </select>
                        </div>
                        <div class="wiz-report-controlset-field">
                            <?php $selectedMessageMedium = isset($_GET['messageMedium']) ? $_GET['messageMedium'] : 'all'; ?>
                            <label for="wiz-report-message-medium">Message Medium</label>
                            <select name="messageMedium" id="wiz-report-message-medium">
                                <option value="all" <?php echo $selectedMessageMedium == 'all' ? 'selected' : ''; ?>>All Mediums</option>
                                <option value="Email" <?php echo $selectedMessageMedium == 'Email' ? 'selected' : ''; ?>>Email</option>
                                <option value="Sms" <?php echo $selectedMessageMedium == 'Sms' ? 'selected' : ''; ?>>SMS</option>
                            </select>
                        </div>
                    </fieldset>

                    <fieldset class="wiz-report-controlset" id="wiz-report-sendsize-controls">
                        <legend>Send Volume</legend>
                        <div class="wiz-report-controlset-field">
                            <?php $setMin = isset($_GET['minSendSize']) ? $_GET['minSendSize'] : 1; ?>
                            <label for="wiz-report-sendsize-min">Min</label>
                            <input name="minSendSize" type="number" min="1" step="1" value="<?php echo $setMin; ?>" id="wiz-report-sendsize-min" />
                        </div>
                        <div class="wiz-report-controlset-field">
                            <?php $setMax = isset($_GET['maxSendSize']) ? $_GET['maxSendSize'] : 500000; ?>
                            <label for="wiz-report-sendsize-max">Max</label>
                            <input name="maxSendSize" type="number" min="1" step="1" value="<?php echo $setMax; ?>" id="wiz-report-sendsize-max" />
                        </div>
                    </fieldset>

                    <fieldset class="wiz-report-controlset" id="wiz-report-cohort-controls">
                        <legend>Campaign Labels</legend>
                        <div class="wiz-report-controlset-field">
                            <label for="wiz-report-cohorts">Include</label>
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

                    <fieldset class="wiz-report-controlset" id="wiz-report-global-update">
                        <legend>Actions</legend>
                        <div class="wiz-report-controlset-field">
                            <input id="reports-filter-submit" type="submit" value="Update All Charts" class="wiz-button green" />
                        </div>
                    </fieldset>
                <?php } ?>
        <?php if ($reportType == 'engagement-tails') { ?>
            <fieldset class="wiz-report-controlset" id="wiz-report-maxhours-controls">
                <legend>Engagement Tails Settings</legend>
                <div class="wiz-report-controlset-field">
                    <?php $setMaxhours = isset($_GET['maxHours']) ? $_GET['maxHours'] : 72; ?>
                    <label for="wiz-report-maxhours">Max Hours</label>
                    <input name="maxHours" type="number" min="0" step="1" value="<?php echo $setMaxhours; ?>" id="wiz-report-maxhours" />
                </div>
                <div class="wiz-report-controlset-field">
                    <?php $setOpenThreshold = isset($_GET['openThreshold']) ? $_GET['openThreshold'] : 50; ?>
                    <label for="wiz-report-open-threshold">Open Threshold</label>
                    <input name="openThreshold" type="number" min="0" step="1" value="<?php echo $setOpenThreshold; ?>" id="wiz-report-open-threshold" />
                </div>
                <div class="wiz-report-controlset-field">
                    <?php $setClickThreshold = isset($_GET['clickThreshold']) ? $_GET['clickThreshold'] : 10; ?>
                    <label for="wiz-report-click-threshold">Click Threshold</label>
                    <input name="clickThreshold" type="number" min="0" step="1" value="<?php echo $setClickThreshold; ?>" id="wiz-report-click-threshold" />
                </div>
            </fieldset>
        <?php } ?>
        <fieldset class="wiz-report-controlset" id="wiz-report-other-controls">
            <div class="wiz-report-controlset-field">
                <?php if ($reportType != 'open-click-trends') { ?>
                    <input id="reports-filter-submit" type="submit" value="Update" class="wiz-button green" />
                <?php } ?>
            </div>
        </fieldset>
    </form>
</div>