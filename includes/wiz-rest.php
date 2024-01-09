<?php
add_action('rest_api_init', function () {
    register_rest_route('idemailwiz/v1', '/iterable-link', array(
        'methods' => 'GET',
        'callback' => 'wiz_handle_iterable_data_feed',
        //  'permission_callback' => function () {
        //      return current_user_can( 'edit_others_posts' );
        //  }
    ));
});

function map_division_to_abbreviation($division) {
    $mapping = array(
        "iD Tech Camps" => "ipc",
        "iD Tech Academies" => "idta",
        "iD Teen Academies" => "ota",
        "iD Teen Academies - 2 weeks" => "ota",
        "Online Private Lessons" => "opl",
        "Virtual Tech Camps" => "vtc"
    );

    return isset($mapping[$division]) ? $mapping[$division] : null;
}

function wiz_handle_iterable_data_feed($data) {
    // $wizSettings = get_option('idemailwiz_settings');
    // $api_auth_token = $wizSettings['external_cron_api'];

    // $token = $data->get_header('Authorization');
    // if ($token !== $api_auth_token) {
    //     return new WP_REST_Response('Invalid or missing token', 403);
    // }

    $params = $data->get_params();
    $course_name = $params['course_name'];
    $division = $params['division'];
    $student_dob = $params['student_dob'];

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_courses';

    // Find the course by name and division
    $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE name = %s AND division = %s LIMIT 1", $course_name, $division));

    if (!$course) {
        return new WP_REST_Response(['message' => 'Course not found', 'recommendations' => []], 200);
    }

    // Determine student's age
    $today = new DateTime();
    $dob = new DateTime($student_dob);
    $age = $today->diff($dob)->y;
    $has_aged_up = $age > $course->age_end;

    $division_abbreviation = map_division_to_abbreviation($course->division);
    $rec_types = ['ipc', 'idta', 'ota', 'opl', 'vtc']; // List all possible rec_types

    $course_recs = maybe_unserialize($course->course_recs);
    $recommendations = array();

    foreach ($rec_types as $type) {
        $type_with_ageup = $has_aged_up ? $type . '_ageup' : $type;
        if (isset($course_recs[$type_with_ageup]) && is_array($course_recs[$type_with_ageup])) {
            foreach ($course_recs[$type_with_ageup] as $recId) {
                $recCourse = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$table_name} WHERE id = %s", $recId));
                if ($recCourse) {
                    $recommendations[$type_with_ageup][] = $recCourse;
                }
            }
        }
    }

    return new WP_REST_Response(['message' => 'Success', 'recommendations' => $recommendations], 200);
}


