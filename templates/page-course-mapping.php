<?php get_header(); ?>

<style>
    /* Dropdown styling fixes */
    #course-mapping-options fieldset {
        margin-bottom: 10px;
        width: 100%;
    }
    
    #course-mapping-options label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .select2-container {
        min-width: 250px !important;
        width: 100% !important;
        max-width: 300px;
    }
    
    .wizHeader-left {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    /* Styling for course elements */
    .course-blob.inactive {
        background-color: #ffe0e0 !important;
        border: 1px solid #ffb3b3 !important;
    }
    
    .course-blob.not-current-fy {
        background-color: #fff3cd !important;
        border: 1px solid #ffeaa7 !important;
    }
    
    .course-blob.not-current-fy:after {
        content: '📅';
        position: absolute;
        top: 2px;
        right: 2px;
        font-size: 10px;
    }
    
    /* Legend styling */
    .legend-row {
        background-color: #f9f9f9;
        padding: 8px;
        text-align: right;
    }
    
    .mapping-legend {
        display: flex;
        justify-content: flex-end;
        gap: 20px;
        font-size: 12px;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .color-sample {
        display: inline-block;
        width: 16px;
        height: 16px;
        border-radius: 3px;
    }
    
    .color-sample.inactive {
        background-color: #ffe0e0;
        border: 1px solid #ffb3b3;
    }
    
    .color-sample.not-current-fy {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
    }
    
    /* Modal Styles */
    .wiz-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
    }
    
    .wiz-modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        border-radius: 4px;
        max-width: 600px;
        position: relative;
    }
    
    .wiz-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 20px;
    }
    
    .wiz-modal-close:hover,
    .wiz-modal-close:focus {
        color: #000;
    }
    
    .wiz-modal-body {
        clear: both;
        padding-top: 10px;
    }
</style>

<header class="wizHeader">
    <h1 class="wizEntry-title" itemprop="name">
        Course Mapping
    </h1>
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">

            <form method="get" id="course-mapping-options" name="course-mapping-options">
                <fieldset>
                    <label for="division-select">Division:</label>
                    <select id="division-select" name="division-select[]" multiple onchange="this.form.submit()">
                        <?php
                        $divisions = [
                            'iDTC' => 25,
                            'iDTA' => 22,
                            'VTC' => 42,
                            'OTA' => 47,
                            'OPL' => 41
                        ];
                        $selectedDivisions = isset($_GET['division-select']) ? $_GET['division-select'] : [25];
                        foreach ($divisions as $divAbbr => $divisionId) {
                            $divisionsSelected = in_array($divisionId, $selectedDivisions) ? 'selected' : '';
                            echo "<option value='$divisionId' $divisionsSelected>" . $divAbbr . "</option>";
                        }
                        ?>
                    </select>
                </fieldset>
            </form>


        </div>
        <div class="wizHeader-right">

            <div class="wizHeader-actions">
                <button id="upload-csv-mappings" class="button button-primary" type="button">
                    <i class="fa-solid fa-file-arrow-up"></i> Upload CSV Mappings
                </button>
                <button id="clear-non-current-mappings" class="button button-secondary" type="button">
                    <i class="fa-solid fa-broom"></i> Clear Non-Current FY Mappings
                </button>
            </div>
        </div>
    </div>
</header>
<div class="entry-content" itemprop="mainContentOfPage">
    <div class="wizcampaign-sections-row">
        <div class="wizcampaign-section">
            <div id="course-mapping-table-wrap">
                <?php
                // Get the selected divisions from the form submission
                $selectedDivisions = isset($_GET['division-select']) ? array_map('intval', $_GET['division-select']) : [25];

                // Get current fiscal year for display
                $currentFiscalYear = wizPulse_get_current_fiscal_year();

                // Fetch ALL courses for selected divisions (active_only = false)
                // We show all courses here because these are courses we're mapping FROM
                // It doesn't matter if they're not currently active
                $courses = get_idwiz_courses($selectedDivisions, false);
                if (is_wp_error($courses)) {
                    echo 'Error retrieving courses: ' . $courses->get_error_message();
                } else {
                    echo '<table id="course-mapping-table">';
                    echo '<tr>';
                    echo '<th class="super-header" colspan="2">Mapping ' . count($courses) . ' courses</th>';
                    echo '<th class="super-header" colspan="9">Mapped Recommendations (Click a square to add course recs)</th>';
                    echo '</tr>';
                    echo '<tr>';
                    echo '<td colspan="11" class="legend-row">';
                    echo '<div class="mapping-legend">';
                    echo '<span class="legend-item"><span class="color-sample inactive"></span> Inactive course (wizStatus = Inactive)</span>';
                    echo '<span class="legend-item"><span class="color-sample not-current-fy"></span> Course not offered in current fiscal year (' . $currentFiscalYear . ')</span>';
                    echo '</div>';
                    echo '</td>';
                    echo '</tr>';
                    echo '<tr>
                        <th class="course-name">Course</th>
                        <th class="division">Division</th>
                        <th class="course-recs">iDTC</th>
                        <th class="course-recs">iDTC Age-Up</th>
                        <th class="course-recs">VTC</th>
                        <th class="course-recs">VTC Age-Up</th>
                        <th class="course-recs">iDTA</th>
                        
                        <th class="course-recs">OTA</th>
                        
                        <th class="course-recs">OPL</th>
                    </tr>';

                    foreach ($courses as $course) {
                        echo '<tr>';
                        echo '<td class="course-name">' . $course->title . '</br>' . $course->abbreviation . ' | ID: ' . $course->id . ' | Ages: ' . $course->minAge . '-' . $course->maxAge . '</td>';

                        $ogDivision = get_division_name($course->division_id);
                        echo '<td class="division">' . esc_html($ogDivision) . '</td>';

                        $recTypes = ['idtc', 'idtc_ageup', 'vtc', 'vtc_ageup', 'idta', 'ota', 'opl'];
                        foreach ($recTypes as $recType) {
                            $division = get_division_id($recType);
                            $divisionName = get_division_name($division);
                            $courseRecs = maybe_unserialize($course->course_recs);
                            $courseRecIds = isset($courseRecs[$recType]) ? $courseRecs[$recType] : [];

                            echo "<td class='course-recs $recType' data-course-id='{$course->id}' data-rec-type='$recType' data-division='$division' data-current-fiscal='" . esc_attr($currentFiscalYear) . "'>";
                            echo "<div class='course-recs-wrap'>";
                            foreach ($courseRecIds as $courseRecId) {
                                $recdCourse = get_course_details_by_id($courseRecId);
                                if ($recdCourse) {
                                    // Check wizStatus (Active/Inactive)
                                    $activeClass = (strtolower($recdCourse->wizStatus) == 'active') ? 'active' : 'inactive';
                                    
                                    // Check if course is offered in current fiscal year based on mustTurnMinAgeByDate
                                    $inCurrentFiscalYear = wizPulse_is_course_active($recdCourse->mustTurnMinAgeByDate);
                                    $fiscalYearClass = $inCurrentFiscalYear ? '' : 'not-current-fy';
                                    
                                    $warningTitle = '';
                                    if (!$inCurrentFiscalYear) {
                                        $courseFY = wizPulse_get_fiscal_year_from_date($recdCourse->mustTurnMinAgeByDate);
                                        $warningTitle = 'This course is not offered in the current fiscal year (' . $currentFiscalYear . '). ';
                                        if ($courseFY) {
                                            $warningTitle .= 'It is available in: ' . $courseFY;
                                        } else {
                                            $warningTitle .= 'No fiscal year assigned (mustTurnMinAgeByDate is empty).';
                                        }
                                    }
                                    
                                    if (strtolower($recdCourse->wizStatus) == 'inactive') {
                                        $warningTitle = 'This course is marked as Inactive in the database. ' . $warningTitle;
                                    }
                                    
                                    echo "<span class='course-blob $activeClass $fiscalYearClass' data-recd-course-id='{$recdCourse->id}' title='" . esc_attr($warningTitle) . "'>";
                                    echo "<span class='blob-meta'>" . esc_html($recdCourse->id . ' | ' . $recdCourse->abbreviation . ' | ' . $recdCourse->minAge . '-' . $recdCourse->maxAge) . "</span>";
                                    echo esc_html($recdCourse->title);
                                    echo "<span title='Remove course rec' class='remove-course'><i class='fa-solid fa-circle-xmark'></i></span>";
                                    echo "</span>";
                                }
                            }
                            echo '<span title="Click to add ' . $divisionName . ' course" class="add-course"></span>';
                            echo "</div></td>";
                        }
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                ?>

            </div>
        </div>
    </div>
</div>

<!-- CSV Upload Modal -->
<div id="csv-upload-modal" class="wiz-modal" style="display:none;">
    <div class="wiz-modal-content" style="max-width: 600px;">
        <span class="wiz-modal-close">&times;</span>
        <h2>Upload Course Recommendations CSV</h2>
        <div class="wiz-modal-body">
            <p>Upload a CSV file with course recommendations. The CSV should have the following columns:</p>
            <ul style="font-size: 12px; margin: 10px 0;">
                <li><strong>Last Course Shortcode</strong> - Course abbreviation</li>
                <li><strong>Rec 1/2/3 Shortcode</strong> - iDTC recommendations</li>
                <li><strong>Rec Age Up 1/2/3 Shortcode</strong> - iDTC age-up recommendations</li>
                <li><strong>VTC Rec 1/2/3 Shortcode</strong> - VTC recommendations</li>
                <li><strong>VTC Rec Age Up 1/2/3 Shortcode</strong> - VTC age-up recommendations</li>
                <li><strong>IDTA Rec Age Up 1 Shortcode</strong> - iDTA recommendations</li>
            </ul>
            
            <form id="csv-upload-form" enctype="multipart/form-data">
                <div style="margin: 20px 0;">
                    <label for="csv-file" style="display: block; margin-bottom: 10px; font-weight: 600;">
                        Select CSV File:
                    </label>
                    <input type="file" id="csv-file" name="csv-file" accept=".csv" required style="width: 100%;">
                </div>
                
                <div style="margin: 20px 0;">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="clear-existing" name="clear-existing">
                        <span>Clear existing mappings before import</span>
                    </label>
                </div>
                
                <div id="upload-progress" style="display:none; margin: 20px 0;">
                    <div style="background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                        <div id="upload-progress-bar" style="background: #2271b1; height: 30px; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">
                            0%
                        </div>
                    </div>
                    <div id="upload-status" style="margin-top: 10px; font-size: 12px; color: #666;">
                        Preparing upload...
                    </div>
                </div>
                
                <div class="wiz-modal-actions" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="button button-secondary cancel-upload">Cancel</button>
                    <button type="submit" class="button button-primary">Upload & Process</button>
                </div>
            </form>
            
            <div id="upload-results" style="display:none; margin-top: 20px;">
                <h3>Import Results</h3>
                <div id="results-summary" style="padding: 15px; background: #f0f0f0; border-radius: 4px; margin-bottom: 15px;"></div>
                <div id="results-details" style="max-height: 300px; overflow-y: auto; font-size: 12px;"></div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>