<?php get_header(); ?>


<header class="wizHeader">
    <h1 class="wizEntry-title" itemprop="name">
        Course Mapping
    </h1>
    <div class="wizHeaderInnerWrap">
        <div class="wizHeader-left">


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
                $courses = get_idwiz_courses();
                if (is_wp_error($courses)) {
                    // Handle error.
                    echo 'Error retrieving courses: ' . $courses->get_error_message();
                } else {
                    echo '<table id="course-mapping-table">';
                    echo '<tr><th class="course-name">Course</th><th class="division">Division</th><th class="course-recs">IPC</th><th class="course-recs">IPC<br/>Age-Up</th><th class="course-recs">VTC</th><th class="course-recs">VTC<br/>Age-Up</th><th class="course-recs">iDTA</th><th>iDTA<br/>Age-Up</th><th>OTA</th><th class="course-recs">OTA<br/>Age-Up</th><th class="course-recs">OPL</th></tr>';

                    foreach ($courses as $course) {
                        echo '<tr>';
                        echo '<td class="course-name">' . esc_html($course->id . ' | ' . $course->age_start . '-' . $course->age_end . ' | ' . $course->name) . '</td>';
                        echo '<td class="division">' . esc_html($course->division) . '</td>';

                        $recTypes = ['ipc', 'ipc_ageup', 'vtc', 'vtc_ageup', 'idta', 'idta_ageup', 'ota', 'ota_ageup', 'opl'];
                        foreach ($recTypes as $recType) {

                            if (str_contains($recType, 'ipc')) {
                                $division = 'iD Tech Camps';
                            } else if (str_contains($recType, 'vtc')) {
                                $division = 'Virtual Tech Camps';
                            } else if (str_contains($recType, 'idta')) {
                                $division = 'iD Teen Academies';
                            } else if (str_contains($recType, 'ota')) {
                                $division = 'Online Teen Academies';
                            } else if (str_contains($recType, 'opl')) {
                                $division = 'Online Private Lessons';
                            } else {
                                $division = '';
                            }

                            $courseRecs = maybe_unserialize($course->course_recs);

                            // Access the course recommendations directly by recType
                            $courseRecIds = isset($courseRecs[$recType]) ? $courseRecs[$recType] : [];

                            echo "<td class='course-recs $recType' data-course-id='{$course->id}' data-rec-type='$recType' data-division='$division'><div class='course-recs-wrap'>";
                            foreach ($courseRecIds as $courseRecId) {
                                $recdCourse = get_course_details_by_id($courseRecId); // Fetch course details
                                // Check if course details are found
                                if ($recdCourse) {
                                    echo "<span class='course-blob' data-recd-course='{$recdCourse->id}'><span class='blob-meta'>" . esc_html($recdCourse->id . ' | ' . $recdCourse->age_start . '-' . $recdCourse->age_end) . '</span>' . $recdCourse->name . "<span title='Remove course rec' class='remove-course'><i class='fa-solid fa-circle-xmark'></i></span></span>";
                                }
                            }
                            echo '<span title="Click to add course" class="add-course" data-course-id="' . $course->id . '" data-rec-type="' . $recType . '"></span>';
                            echo "</td></div>";
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