<?php

function get_course_details_by_id($course_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_courses';

    // Sanitize the course ID to prevent SQL injection
    $course_id = sanitize_text_field($course_id);

    // Prepare the query to get a specific course by ID
    $query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %s", $course_id);

    // Execute the query
    $course = $wpdb->get_row($query);

    if (is_null($course)) {
        return new WP_Error('no_course', __('Course not found', 'text-domain'));
    }

    return $course;
}

add_action('wp_ajax_id_get_courses_options', 'id_get_courses_options_handler');

function id_get_courses_options_handler()
{
    if (!check_ajax_referer('id-general', 'security', false)) {
        error_log('Nonce check failed');
        wp_send_json_error('Nonce check failed');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_courses';
    $division = isset($_POST['division']) ? intval($_POST['division']) : '';
    $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
    $wizStatus = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

    // Building the query - need to select mustTurnMinAgeByDate for filtering
    $query = "SELECT id, title, abbreviation, minAge, maxAge, mustTurnMinAgeByDate FROM {$table_name} WHERE 1=1";
    $params = array();

    // Add division filter if provided
    if (!empty($division)) {
        $query .= " AND division_id = %d";
        $params[] = $division;
    }

    // Add search term filter if provided
    if (!empty($term)) {
        $query .= " AND (title LIKE %s OR abbreviation LIKE %s)";
        $params[] = '%' . $wpdb->esc_like($term) . '%';
        $params[] = '%' . $wpdb->esc_like($term) . '%';
    }

    // Add condition to check if 'locations' column is not empty/null (if not online)
    // Only apply location filtering for in-person divisions that require locations
    if (!empty($division) && $division != 41 && $division != 42 && $division != 47) {
        // For in-person courses, they should have locations, but don't exclude if locations is empty
        // as new courses from Pulse might not have location data populated yet
        // $query .= " AND locations IS NOT NULL AND locations != ''";
    }

    // Add status filter if provided
    if (!empty($wizStatus)) {
        $query .= " AND wizStatus = %s";
        $params[] = $wizStatus;
    }
    
    // Filter to only show courses active in current fiscal year
    // A course is active if mustTurnMinAgeByDate is not null and matches current FY
    $query .= " AND mustTurnMinAgeByDate IS NOT NULL";

    // Filter out specific course IDs
    //$excludeIds = [2569,470,2188,1958,2189,2570,1849,2190,2571,1850,2191,2572,1570,2192,2480,2369,];


    $courses = $wpdb->get_results($wpdb->prepare($query, $params));
    
    // Filter courses to only show those active in current OR next fiscal year
    $current_fy = wizPulse_get_current_fiscal_year();
    
    // Also get next fiscal year
    $current_fy_parts = explode('/', $current_fy);
    $next_fy_year = intval($current_fy_parts[0]) + 1;
    $next_fy = $next_fy_year . '/' . ($next_fy_year + 1);
    
    $filtered_courses = array();
    
    foreach ($courses as $course) {
        if (!empty($course->mustTurnMinAgeByDate)) {
            $course_fy = wizPulse_get_fiscal_year_from_date($course->mustTurnMinAgeByDate);
            // Include courses for current OR next fiscal year
            if ($course_fy === $current_fy || $course_fy === $next_fy) {
                $filtered_courses[] = $course;
            }
        }
    }
    
    // Debug logging
    wiz_log("Course Selection Query: Division=$division, Term='$term', Found " . count($filtered_courses) . " active courses (current FY: $current_fy, next FY: $next_fy)");
    if (count($filtered_courses) > 0) {
        $sample_course = $filtered_courses[0];
        wiz_log("Sample course: ID={$sample_course->id}, Title={$sample_course->title}, mustTurnMinAgeByDate={$sample_course->mustTurnMinAgeByDate}");
    }

    $results = array();
    foreach ($filtered_courses as $course) {
        $results[] = array(
            'id' => $course->id,
            'text' => $course->id . ' | ' . $course->abbreviation . ' | ' . $course->minAge . '-' . $course->maxAge . ' | ' . $course->title
        );
    }

    wp_send_json_success($results);
}



add_action('wp_ajax_id_add_course_to_rec', 'id_add_course_to_rec_handler');
function id_add_course_to_rec_handler()
{

    if (!check_ajax_referer('id-general', 'security', false)) {
        error_log('Nonce check failed');
        wp_send_json_error('Nonce check failed');
        return;
    }

    $course_id = isset($_POST['course_id']) ? sanitize_text_field($_POST['course_id']) : '';
    $rec_type = isset($_POST['rec_type']) ? sanitize_text_field($_POST['rec_type']) : '';
    $selected_courses = isset($_POST['selected_courses']) ? $_POST['selected_courses'] : [];

    // If selected_courses is a string (happens when only one course is selected), convert it to array
    if (!is_array($selected_courses) && !empty($selected_courses)) {
        $selected_courses = [$selected_courses];
    }

    if (empty($course_id) || empty($rec_type) || empty($selected_courses)) {
        wp_send_json_error('Missing data');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_courses';

    // Fetch the current recommendations
    $course = $wpdb->get_row($wpdb->prepare("SELECT course_recs FROM {$table_name} WHERE id = %s", $course_id));

    if (null === $course) {
        wp_send_json_error('Course not found');
        return;
    }

    // Ensure $course_recs is an array
    $course_recs = maybe_unserialize($course->course_recs);
    if (!is_array($course_recs)) {
        $course_recs = []; // Initialize as an empty array if it's not an array
    }

    // Check if the specific rec_type is an array, initialize if not
    if (!isset($course_recs[$rec_type]) || !is_array($course_recs[$rec_type])) {
        $course_recs[$rec_type] = [];
    }

    // Add the selected courses
    foreach ($selected_courses as $selected_course) {
        $sanitized_course = sanitize_text_field($selected_course);
        if (!in_array($sanitized_course, $course_recs[$rec_type])) {
            $course_recs[$rec_type][] = $sanitized_course;
        }
    }

    // Update the course recommendations
    $updated = $wpdb->update($table_name, ['course_recs' => maybe_serialize($course_recs)], ['id' => $course_id]);

    if (false === $updated) {
        wp_send_json_error('Database update failed');
    } else {
        wp_send_json_success('Courses added successfully');
    }
}

add_action('wp_ajax_id_remove_course_from_rec', 'id_remove_course_from_rec_handler');
function id_remove_course_from_rec_handler()
{
    // Check nonce
    if (!check_ajax_referer('id-general', 'security', false)) {
        error_log('Nonce check failed');
        wp_send_json_error('Nonce check failed');
        return;
    }

    $course_id = sanitize_text_field($_POST['course_id']);
    $rec_type = sanitize_text_field($_POST['rec_type']);
    $recd_course_id = sanitize_text_field($_POST['recd_course_id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_courses';

    // Fetch and modify the course recommendations
    $course = $wpdb->get_row($wpdb->prepare("SELECT course_recs FROM {$table_name} WHERE id = %s", $course_id));
    if (null === $course) {
        wp_send_json_error('Course not found');
        return;
    }

    $course_recs = maybe_unserialize($course->course_recs);
    if (isset($course_recs[$rec_type])) {
        $course_recs[$rec_type] = array_diff($course_recs[$rec_type], [$recd_course_id]);
    }

    // Update the database
    $updated = $wpdb->update($table_name, ['course_recs' => maybe_serialize($course_recs)], ['id' => $course_id]);
    if (false === $updated) {
        wp_send_json_error('Database update failed');
    } else {
        wp_send_json_success('Course removed successfully');
    }
}

add_action('wp_ajax_id_import_csv_mappings', 'id_import_csv_mappings_handler');
function id_import_csv_mappings_handler()
{
    // Check nonce
    if (!check_ajax_referer('id-general', 'security', false)) {
        error_log('Nonce check failed');
        wp_send_json_error('Nonce check failed');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_courses';

    $mappings_json = isset($_POST['mappings']) ? stripslashes($_POST['mappings']) : '';
    $clear_existing = isset($_POST['clear_existing']) && $_POST['clear_existing'] === '1';
    
    $mappings = json_decode($mappings_json, true);
    
    // Debug logging
    wiz_log('CSV Import: Received ' . strlen($mappings_json) . ' bytes of JSON data');
    
    if (!is_array($mappings)) {
        wiz_log('CSV Import Error: Failed to decode JSON - ' . json_last_error_msg());
        wp_send_json_error('Invalid mappings data: ' . json_last_error_msg());
        return;
    }
    
    wiz_log('CSV Import: Decoded ' . count($mappings) . ' course mappings');

    $total_processed = 0;
    $mappings_created = 0;
    $errors = 0;
    $details = [];

    // Clear existing mappings if requested
    if ($clear_existing) {
        $wpdb->query("UPDATE $table_name SET course_recs = NULL");
        $details[] = [
            'success' => true,
            'message' => 'Cleared all existing course mappings'
        ];
    }

    // Process each mapping
    foreach ($mappings as $mapping) {
        $course_abbreviation = $mapping['course_abbreviation'];
        $total_processed++;
        
        // Debug first few courses
        if ($total_processed <= 3) {
            wiz_log("CSV Import: Processing course $course_abbreviation - " . print_r($mapping, true));
        }
        
        // Find course by abbreviation
        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT id, course_recs FROM $table_name WHERE abbreviation = %s LIMIT 1",
            $course_abbreviation
        ));
        
        if (!$course) {
            $errors++;
            $details[] = [
                'success' => false,
                'message' => "Course not found: $course_abbreviation"
            ];
            wiz_log("CSV Import: Course not found in database: $course_abbreviation");
            continue;
        }

        // Get existing recs or create new array
        $course_recs = maybe_unserialize($course->course_recs);
        if (!is_array($course_recs)) {
            $course_recs = [];
        }

        $recs_added = 0;

        // Process each rec type
        foreach (['idtc', 'idtc_ageup', 'idta', 'vtc', 'vtc_ageup'] as $rec_type) {
            if (empty($mapping[$rec_type])) {
                continue;
            }

            // Initialize rec type array if not exists
            if (!isset($course_recs[$rec_type]) || !is_array($course_recs[$rec_type])) {
                $course_recs[$rec_type] = [];
            }

            // Add each recommended course
            foreach ($mapping[$rec_type] as $rec_abbreviation) {
                // Skip empty values
                if (empty($rec_abbreviation)) {
                    continue;
                }
                
                // Find the recommended course ID by abbreviation
                $rec_course = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, abbreviation FROM $table_name WHERE abbreviation = %s LIMIT 1",
                    $rec_abbreviation
                ));
                
                if ($rec_course) {
                    // Add if not already present
                    if (!in_array($rec_course->id, $course_recs[$rec_type])) {
                        $course_recs[$rec_type][] = $rec_course->id;
                        $recs_added++;
                        
                        // Log first few successful additions
                        if ($total_processed <= 3 && $recs_added <= 5) {
                            wiz_log("CSV Import: Added rec $rec_abbreviation (ID: {$rec_course->id}) to $course_abbreviation as $rec_type");
                        }
                    }
                } else {
                    // Log warning for missing courses but don't fail the whole import
                    if ($total_processed <= 10) {
                        wiz_log("CSV Import Warning: Recommended course not found: $rec_abbreviation (for $course_abbreviation in $rec_type)");
                    }
                    $details[] = [
                        'success' => false,
                        'message' => "Recommended course not found: $rec_abbreviation (for $course_abbreviation)"
                    ];
                }
            }
        }

        // Update the course if we added any recs
        if ($recs_added > 0) {
            $updated = $wpdb->update(
                $table_name,
                ['course_recs' => maybe_serialize($course_recs)],
                ['id' => $course->id]
            );
            
            if ($updated !== false) {
                $mappings_created += $recs_added;
                $details[] = [
                    'success' => true,
                    'message' => "Updated $course_abbreviation: added $recs_added recommendations"
                ];
            } else {
                $errors++;
                $details[] = [
                    'success' => false,
                    'message' => "Database update failed for $course_abbreviation"
                ];
            }
        }
    }

    // Log final summary
    wiz_log("CSV Import Complete: Processed $total_processed courses, created $mappings_created mappings, $errors errors");
    
    wp_send_json_success([
        'total_processed' => $total_processed,
        'mappings_created' => $mappings_created,
        'errors' => $errors,
        'details' => $details
    ]);
}

add_action('wp_ajax_id_clear_non_current_fy_mappings', 'id_clear_non_current_fy_mappings_handler');
function id_clear_non_current_fy_mappings_handler()
{
    // Check nonce
    if (!check_ajax_referer('id-general', 'security', false)) {
        error_log('Nonce check failed');
        wp_send_json_error('Nonce check failed');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_courses';

    // Get current fiscal year
    $current_fy = wizPulse_get_current_fiscal_year();

    // Get all courses with recommendations
    $courses = $wpdb->get_results("SELECT id, course_recs FROM {$table_name} WHERE course_recs IS NOT NULL AND course_recs != ''");

    $total_removed = 0;
    $courses_updated = 0;

    foreach ($courses as $course) {
        $course_recs = maybe_unserialize($course->course_recs);
        
        if (!is_array($course_recs)) {
            continue;
        }

        $has_changes = false;
        
        // Loop through each recommendation type
        foreach ($course_recs as $rec_type => $rec_ids) {
            if (!is_array($rec_ids)) {
                continue;
            }

            $filtered_recs = [];
            
            // Check each recommended course
            foreach ($rec_ids as $rec_id) {
                $recd_course = $wpdb->get_row($wpdb->prepare("SELECT mustTurnMinAgeByDate FROM {$table_name} WHERE id = %s", $rec_id));
                
                if ($recd_course) {
                    // Check if recommended course is active in current fiscal year
                    $is_active = wizPulse_is_course_active($recd_course->mustTurnMinAgeByDate);
                    
                    // Keep only courses that are active in the current fiscal year
                    if ($is_active) {
                        $filtered_recs[] = $rec_id;
                    } else {
                        $total_removed++;
                        $has_changes = true;
                    }
                } else {
                    // If course doesn't exist, remove it
                    $total_removed++;
                    $has_changes = true;
                }
            }
            
            $course_recs[$rec_type] = $filtered_recs;
        }

        // Update the course if there were changes
        if ($has_changes) {
            $wpdb->update($table_name, ['course_recs' => maybe_serialize($course_recs)], ['id' => $course->id]);
            $courses_updated++;
        }
    }

    wp_send_json_success([
        'message' => "Removed {$total_removed} non-current FY mappings from {$courses_updated} courses (current FY: {$current_fy})",
        'total_removed' => $total_removed,
        'courses_updated' => $courses_updated
    ]);
}