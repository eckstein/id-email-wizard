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

    // Building the query
    $query = "SELECT id, title, abbreviation, minAge, maxAge FROM {$table_name} WHERE 1=1";
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
    if (!empty($division) && $division != 41) {
        $query .= " AND locations IS NOT NULL AND locations != ''";
    }

    // Add status filter if provided
    if (!empty($wizStatus)) {
        $query .= " AND wizStatus = %s";
        $params[] = $wizStatus;
    }

    // Filter out specific course IDs
    //$excludeIds = [2569,470,2188,1958,2189,2570,1849,2190,2571,1850,2191,2572,1570,2192,2480,2369,];


    $courses = $wpdb->get_results($wpdb->prepare($query, $params));

    $results = array();
    foreach ($courses as $course) {
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
    $selected_course = isset($_POST['selected_course']) ? sanitize_text_field($_POST['selected_course']) : '';

    if (empty($course_id) || empty($rec_type) || empty($selected_course)) {
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

    // Add the selected course
    $course_recs[$rec_type][] = $selected_course;

    // Update the course recommendations
    $updated = $wpdb->update($table_name, ['course_recs' => maybe_serialize($course_recs)], ['id' => $course_id]);

    if (false === $updated) {
        wp_send_json_error('Database update failed');
    } else {
        wp_send_json_success('Course added successfully');
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