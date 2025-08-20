<?php get_header();
updateCourseFiscalYears();
?>

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
        background-color: #f0f0f0;
        border: 1px solid #ccc;
    }
    
    .color-sample.not-current-fy {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
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
                    <label for="source-fy-select">Source fiscal years:</label>
                    <select id="source-fy-select" name="source-fy-select[]" multiple onchange="this.form.submit()">
                        <?php
                        $currentFiscalYear = date('Y') - 2 . '/' . (date('Y') - 1);
                        $fiscalYears = [];
                        for ($year = 2021; $year <= date('Y'); $year++) {
                            $fiscalYears[] = $year . '/' . ($year + 1);
                        }

                        $selectedSourceFiscals = isset($_GET['source-fy-select']) ? $_GET['source-fy-select'] : [$currentFiscalYear];
                        foreach ($fiscalYears as $fiscalYear) {
                            $fiscalsSelected = in_array($fiscalYear, $selectedSourceFiscals) ? 'selected' : '';
                            echo "<option value='$fiscalYear' $fiscalsSelected>" . strtoupper($fiscalYear) . "</option>";
                        }
                        ?>
                    </select>
                </fieldset>

                <fieldset>
                    <label for="division-select">Source divisions:</label>
                    <select id="division-select" name="division-select[]" multiple onchange="this.form.submit()">
                        <?php
                        //$divisions = ['idtc', 'idta', 'vtc', 'ota', 'opl'];
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

                // Get the selected source fiscal years from the form submission
                $selectedSourceFiscalYears = isset($_GET['source-fy-select']) ? $_GET['source-fy-select'] : [date('Y') - 2 . '/' . (date('Y') - 1)];

                // Set current fiscal year for availability checking (not for mapping requirements)
                $currentFiscalYear = date('Y') . '/' . (date('Y') + 1);

                // Use these selections to fetch the source courses
                $courses = get_idwiz_courses($selectedDivisions, $selectedSourceFiscalYears);
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
                    echo '<span class="legend-item"><span class="color-sample inactive"></span> Inactive course</span>';
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
                                    $activeClass = $recdCourse->wizStatus == 'active' ? 'active' : 'inactive';
                                    
                                    // Check if course is offered in current fiscal year
                                    $courseFiscalYears = maybe_unserialize($recdCourse->fiscal_years);
                                    $inCurrentFiscalYear = false;
                                    
                                    if (is_array($courseFiscalYears)) {
                                        $inCurrentFiscalYear = in_array($currentFiscalYear, $courseFiscalYears);
                                    }
                                    
                                    $fiscalYearClass = $inCurrentFiscalYear ? '' : 'not-current-fy';
                                    
                                    $warningTitle = '';
                                    if (!$inCurrentFiscalYear) {
                                        $availableFY = is_array($courseFiscalYears) ? implode(', ', $courseFiscalYears) : 'N/A';
                                        $warningTitle = 'This course is not offered in the current fiscal year (' . $currentFiscalYear . '). ' .
                                                       'It is available in: ' . $availableFY;
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
<?php get_footer(); ?>