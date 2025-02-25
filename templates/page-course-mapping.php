<?php get_header();
updateCourseFiscalYears();
?>


<header class="wizHeader">
    <h1 class="wizEntry-title" itemprop="name">
        Course Mapping
    </h1>
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">

            <form method="get" id="course-mapping-options" name="course-mapping-options">
                <fieldset>
                    <label for="division-select">Division(s) to map to:</label>
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
                <fieldset>
                    <label for="fy-select">Fiscal years to map from:</label>
                    <select id="fy-select" name="fy-select[]" multiple onchange="this.form.submit()">
                        <?php

                        $currentFiscalYear = date('Y') - 2 . '/' . (date('Y') - 1);
                        $fiscalYears = [];
                        for ($year = 2021; $year <= date('Y'); $year++) {
                            $fiscalYears[] = $year . '/' . ($year + 1);
                        }

                        $selectedFiscals = isset($_GET['fy-select']) ? $_GET['fy-select'] : [$currentFiscalYear];
                        foreach ($fiscalYears as $fiscalYear) {
                            $fiscalsSelected = in_array($fiscalYear, $selectedFiscals) ? 'selected' : '';
                            echo "<option value='$fiscalYear' $fiscalsSelected>" . strtoupper($fiscalYear) . "</option>";
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

                // Get the selected fiscal years from the form submission
                $selectedFiscalYears = isset($_GET['fy-select']) ? $_GET['fy-select'] : [date('Y') - 2 . '/' . (date('Y') - 1)];

                // Use these selections to fetch the courses
                $courses = get_idwiz_courses($selectedDivisions, $selectedFiscalYears);
                if (is_wp_error($courses)) {
                    echo 'Error retrieving courses: ' . $courses->get_error_message();
                } else {
                    echo '<table id="course-mapping-table">';
                    echo '<tr>';
                    echo '<th class="super-header" colspan="2">Mapping ' . count($courses) . ' courses</th>';
                    echo '<th class="super-header" colspan="9">Mapped Recommendations (Click a square to add course recs. Red courses mean the course is no longer offered.)</th>';
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
                            $courseRecs = maybe_unserialize($course->course_recs);
                            $courseRecIds = isset($courseRecs[$recType]) ? $courseRecs[$recType] : [];

                            echo "<td class='course-recs $recType' data-course-id='{$course->id}' data-rec-type='$recType' data-division='$division'>";
                            echo "<div class='course-recs-wrap'>";
                            foreach ($courseRecIds as $courseRecId) {
                                $recdCourse = get_course_details_by_id($courseRecId);
                                if ($recdCourse) {
                                    $activeClass = $recdCourse->wizStatus == 'active' ? 'active' : 'inactive';
                                    echo "<span class='course-blob $activeClass' data-recd-course-id='{$recdCourse->id}'>";
                                    echo "<span class='blob-meta'>" . esc_html($recdCourse->id . ' | ' . $recdCourse->abbreviation . ' | ' . $recdCourse->minAge . '-' . $recdCourse->maxAge) . "</span>";
                                    echo esc_html($recdCourse->title);
                                    echo "<span title='Remove course rec' class='remove-course'><i class='fa-solid fa-circle-xmark'></i></span>";
                                    echo "</span>";
                                }
                            }
                            echo '<span title="Click to add course" class="add-course"></span>';
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