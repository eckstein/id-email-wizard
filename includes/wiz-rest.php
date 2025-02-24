<?php



function wiz_handle_user_courses_data_feed($data)
{
    $params = $data->get_params();
    $userId = $params['userId'];
    $mapping = $params['mapping'];

    list($fromDivision, $toDivision) = explode('_to_', $mapping);
    $fromDivisionId = get_division_id($fromDivision);

    $wizUser = get_idwiz_user_by_userID($userId);


    // Determine eligible students
    $studentArray = unserialize($wizUser['studentArray']);
    $eligibleStudents = get_eligible_students($studentArray);

    if (empty($eligibleStudents)) {
        return create_error_response('No eligible students found');
    }

    if (count($eligibleStudents) > 1) {
        return create_error_response('Multiple eligible students found', 400);
    }

    // Use the single eligible student
    $studentInfo = $eligibleStudents[0];

    $userPurchases = get_idwiz_purchases(['userId' => $userId, 'include_null_campaigns' => true, 'shoppingCartItems_divisionId' => $fromDivisionId, 'shoppingCartItems_studentAccountNumber' => $studentInfo['StudentAccountNumber']]);

    if (!$userPurchases || !$wizUser) {
        return create_error_response('No purchases found for this user or user not found');
    }

    if (empty($userPurchases)) {
        return create_error_response('No valid purchases found for this user in the specified from division');
    }

    $latestPurchase = get_latest_purchase($userPurchases);

    $course = get_course_details_by_id($latestPurchase['shoppingCartItems_id']);

    if (is_wp_error($course) || !isset($course->course_recs)) {
        return create_error_response('Unable to retrieve last course details');
    }

    $studentAge = calculate_student_age($studentInfo['StudentDOB']);
    $ageAtLastPurchase = calculate_age_at_purchase($studentInfo['StudentDOB'], $latestPurchase['purchaseDate']);

    if ($studentAge === false) {
        return create_error_response('Student date of birth not found');
    }

    $needsAgeUp = determine_age_up_need($studentAge, $ageAtLastPurchase, $course);
    $recommendations = get_course_recommendations($course, $toDivision, $needsAgeUp);

    if (empty($recommendations)) {
        return create_error_response("No recommendations found for the specified mapping. Previous course: {$course->id} Student: {$studentInfo['StudentAccountNumber']} Mapping: {$mapping}");
    }

    return new WP_REST_Response($recommendations, 200);
}

function get_eligible_students($studentArray)
{
    $eligibleStudents = [];
    foreach ($studentArray as $student) {
        $studentAge = calculate_student_age($student['StudentDOB']);
        if ($studentAge !== false && $studentAge < 18) {
            $eligibleStudents[] = $student;
        }
    }
    return $eligibleStudents;
}



function get_latest_purchase($purchases)
{
    usort($purchases, function ($a, $b) {
        return strtotime($b['purchaseDate']) - strtotime($a['purchaseDate']);
    });
    return $purchases[0];
}

function get_student_info($wizUser, $latestPurchase)
{
    $studentArray = unserialize($wizUser['studentArray']);
    return array_values(array_filter($studentArray, function ($student) use ($latestPurchase) {
        return $student['StudentAccountNumber'] === $latestPurchase['shoppingCartItems_studentAccountNumber'];
    }))[0] ?? null;
}

function calculate_student_age($dob)
{
    $studentDOB = $dob ? new DateTime($dob) : null;
    if (!$studentDOB) return false;
    $currentDate = new DateTime();
    return $studentDOB->diff($currentDate)->y;
}

function calculate_age_at_purchase($dob, $purchaseDate)
{
    $studentDOB = new DateTime($dob);
    $lastPurchaseDate = new DateTime($purchaseDate);
    return $studentDOB->diff($lastPurchaseDate)->y;
}

function determine_age_up_need($studentAge, $ageAtLastPurchase, $course)
{
    if ($studentAge > intval($course->maxAge)) {
        return true;
    }
    if (($ageAtLastPurchase < 10 && $studentAge >= 10) || ($ageAtLastPurchase < 13 && $studentAge >= 13)) {
        return true;
    }
    if ($studentAge >= 10 && intval($course->maxAge) <= 9) {
        return true;
    }
    return false;
}

function get_course_recommendations($course, $toDivision, $needsAgeUp)
{
    $courseRecs = unserialize($course->course_recs);
    $recKey = $needsAgeUp && !in_array($toDivision, ['opl', 'ota', 'idta']) ? $toDivision . '_ageup' : $toDivision;

    if (!isset($courseRecs[$recKey]) || !is_array($courseRecs[$recKey]) || empty($courseRecs[$recKey])) {
        return [];
    }

    $recommendations = [];
    foreach ($courseRecs[$recKey] as $recCourseId) {
        $recCourse = get_course_details_by_id($recCourseId);
        if (!is_wp_error($recCourse)) {
            $recommendations[] = [
                'id' => $recCourse->id,
                'title' => $recCourse->title,
                'abbreviation' => $recCourse->abbreviation,
                'minAge' => $recCourse->minAge,
                'maxAge' => $recCourse->maxAge,
            ];
        }
    }
    return $recommendations;
}

function create_error_response($message, $code = 400)
{
    return new WP_REST_Response(['message' => $message], $code);
}

/**
 * Handles the processing of preset values
 */
function process_preset_value($preset_name, $student_data) {
    switch ($preset_name) {
        case 'most_recent_purchase':
            return get_most_recent_purchase_date($student_data);
        // Add more preset cases here
        default:
            return null;
    }
}

/**
 * Gets the most recent purchase date for a student
 */
function get_most_recent_purchase_date($student_data) {
    global $wpdb;
    
    // Get the most recent purchase
    $purchase = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT purchaseDate 
            FROM {$wpdb->prefix}idemailwiz_purchases 
            WHERE shoppingCartItems_studentAccountNumber = %s 
            ORDER BY purchaseDate DESC 
            LIMIT 1",
            $student_data['studentAccountNumber']
        )
    );

    return $purchase ? $purchase->purchaseDate : null;
}

function wiz_handle_user_data_feed($data)
{
    $wizSettings = get_option('idemailwiz_settings');
    $api_auth_token = $wizSettings['external_cron_api'];

    $token = $data->get_header('Authorization');
    if (empty($api_auth_token) || $token !== 'Bearer ' . $api_auth_token) {
        return new WP_REST_Response(['error' => 'Invalid or missing token'], 403);
    }

    $params = $data->get_params();
    
    if (empty($params['account_number'])) {
        return new WP_REST_Response(['error' => 'account_number parameter is required'], 400);
    }

    // Get user data from the user feed database
    global $wpdb;
    $feed_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}idemailwiz_userfeed WHERE studentAccountNumber = %s LIMIT 1",
            $params['account_number']
        ),
        ARRAY_A
    );

    if (!$feed_data) {
        return new WP_REST_Response(['error' => 'Student not found in feed'], 404);
    }

    // Process presets
    $presets = [
        'most_recent_purchase' => process_preset_value('most_recent_purchase', $feed_data)
    ];

    // Add presets to the response
    $feed_data['_presets'] = $presets;

    return new WP_REST_Response($feed_data, 200);
}

function map_division_to_abbreviation($division)
{
    $mapping = array(
        "iD Tech Camps" => "ipc",
        "iD Teen Academies" => "idta",
        "Online Teen Academies" => "ota",
        "iD Teen Academies - 2 weeks" => "ota",
        "Online Private Lessons" => "opl",
        "Virtual Tech Camps" => "vtc"
    );

    return isset($mapping[$division]) ? $mapping[$division] : null;
}

/**
 * Save all endpoints to the database
 *
 * @param array $endpoints Array of endpoints
 * @return bool True if all endpoints were successfully saved, false otherwise.
 */
function idwiz_save_all_endpoints($endpoints)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_endpoints';
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    // Clear existing endpoints
    $wpdb->query("TRUNCATE TABLE $table_name");
    
    // Insert new endpoints
    $success = true;
    foreach ($endpoints as $endpoint) {
        $result = $wpdb->insert(
            $table_name,
            array(
                'route' => $endpoint,
                'name' => $endpoint, // Default name to route
                'description' => '', // Empty description by default
                'config' => serialize(array()) // Empty config by default
            ),
            array('%s', '%s', '%s', '%s')
        );
        if ($result === false) {
            $success = false;
            break;
        }
    }
    
    // Commit or rollback based on success
    if ($success) {
        $wpdb->query('COMMIT');
        return true;
    } else {
        $wpdb->query('ROLLBACK');
        return false;
    }
}

/**
 * Retrieve all endpoints from the database
 *
 * @return array Array of endpoint routes
 */
function idwiz_get_all_endpoints()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_endpoints';
    
    $results = $wpdb->get_col("SELECT route FROM $table_name");
    return $results ?: array();
}

/**
 * Add a new endpoint
 *
 * @param string $endpoint The endpoint route
 * @param string $name Optional name for the endpoint
 * @param string $description Optional description for the endpoint
 * @param array $config Optional configuration for the endpoint
 * @param array $data_mapping Optional data mapping configuration
 * @param string $base_data_source Optional base data source (defaults to user_feed)
 * @return bool True if successful, false otherwise
 */
function idwiz_add_endpoint($endpoint, $name = '', $description = '', $config = array(), $data_mapping = array(), $base_data_source = 'user_feed')
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_endpoints';
    
    // Clean the endpoint route
    $endpoint = ltrim($endpoint, '/');
    
    // Use route as name if none provided
    if (empty($name)) {
        $name = $endpoint;
    }
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'route' => $endpoint,
            'name' => $name,
            'description' => $description,
            'config' => serialize($config),
            'data_mapping' => serialize($data_mapping),
            'base_data_source' => $base_data_source
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s')
    );
    
    return $result !== false;
}

/**
 * Remove an endpoint
 *
 * @param string $endpoint The endpoint route
 * @return bool True if successful, false otherwise
 */
function idwiz_remove_endpoint($endpoint)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_endpoints';
    
    $result = $wpdb->delete(
        $table_name,
        array('route' => $endpoint),
        array('%s')
    );
    
    return $result !== false;
}

/**
 * Get a single endpoint's details
 *
 * @param string $endpoint The endpoint route
 * @return array|false Endpoint details or false if not found
 */
function idwiz_get_endpoint($endpoint)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_endpoints';
    
    $result = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE route = %s",
            $endpoint
        ),
        ARRAY_A
    );
    
    if ($result) {
        $result['config'] = unserialize($result['config']);
        $result['data_mapping'] = unserialize($result['data_mapping']);
        return $result;
    }
    
    return false;
}

/**
 * Update an endpoint's details
 *
 * @param string $endpoint The endpoint route
 * @param array $data The data to update (name, description, config, data_mapping, base_data_source)
 * @return bool True if successful, false otherwise
 */
function idwiz_update_endpoint($endpoint, $data)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_endpoints';
    
    $update_data = array();
    $update_format = array();
    
    // Map of fields to their format specifiers
    $field_formats = array(
        'name' => '%s',
        'description' => '%s',
        'config' => '%s',
        'data_mapping' => '%s',
        'base_data_source' => '%s'
    );
    
    foreach ($field_formats as $field => $format) {
        if (isset($data[$field])) {
            $value = $data[$field];
            // Serialize arrays
            if (in_array($field, ['config', 'data_mapping']) && is_array($value)) {
                $value = serialize($value);
            }
            $update_data[$field] = $value;
            $update_format[] = $format;
        }
    }
    
    if (empty($update_data)) {
        return false;
    }
    
    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('route' => $endpoint),
        $update_format,
        array('%s')
    );
    
    return $result !== false;
}

add_action('rest_api_init', function () {
    $endpoints = idwiz_get_all_endpoints();

    foreach ($endpoints as $endpoint) {
        register_rest_route('idemailwiz/v1', $endpoint, array(
            'methods' => 'GET',
            'callback' => 'idwiz_endpoint_handler',
            //'permission_callback' => function() { return current_user_can('manage_options'); },
        ));
    }
});

function get_idwiz_rest_routes()
{
    return idwiz_get_all_endpoints();
}

add_action('wp_ajax_idwiz_remove_endpoint', 'idwiz_remove_endpoint_callback');

function idwiz_remove_endpoint_callback()
{
    if (!check_ajax_referer('id-general', 'security', false)) {
        wp_send_json_error('Nonce check failed');
        return;
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
    }
    $endpoint = sanitize_text_field($_POST['endpoint']);
    if (idwiz_remove_endpoint($endpoint)) {
        wp_send_json_success('Endpoint removed successfully');
    } else {
        wp_send_json_error('Endpoint not found');
    }
}

add_action('wp_ajax_idwiz_create_endpoint', 'idwiz_create_endpoint_callback');

function idwiz_create_endpoint_callback()
{
    if (!check_ajax_referer('id-general', 'security', false)) {
        wp_send_json_error('Nonce check failed');
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $endpoint = sanitize_text_field($_POST['endpoint']);
    $endpoint = ltrim($endpoint, '/');
    
    $name = sanitize_text_field($_POST['name'] ?? $endpoint);
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $config = isset($_POST['config']) ? json_decode(stripslashes($_POST['config']), true) : array();

    if (idwiz_add_endpoint($endpoint, $name, $description, $config)) {
        wp_send_json_success('Endpoint created successfully');
    } else {
        wp_send_json_error('Failed to create the endpoint or it already exists.');
    }
}

function idwiz_endpoint_handler($request)
{
    $route = $request->get_route();
    $endpoint = str_replace('/idemailwiz/v1', '', $route);
    $endpoint = trim($endpoint, '/');

    // Get endpoint configuration from database
    $endpoint_config = idwiz_get_endpoint($endpoint);
    if (!$endpoint_config) {
        return new WP_REST_Response(array(
            'error' => 'Endpoint configuration not found',
        ), 404);
    }

    // Get user data based on the request
    $params = $request->get_params();
    $account_number = isset($params['account_number']) ? sanitize_text_field($params['account_number']) : '';
    
    if (empty($account_number)) {
        return new WP_REST_Response(array(
            'error' => 'Account number is required',
        ), 400);
    }

    global $wpdb;
    // Get user data from the user feed database
    $feed_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}idemailwiz_userfeed WHERE StudentAccountNumber = %s LIMIT 1",
            $account_number
        ),
        ARRAY_A
    );

    if (!$feed_data) {
        return new WP_REST_Response(array(
            'error' => 'Student not found in feed',
        ), 404);
    }

    // Build response data based on mappings
    $response_data = array();
    
    if (!empty($endpoint_config['data_mapping'])) {
        foreach ($endpoint_config['data_mapping'] as $key => $mapping) {
            if ($mapping['type'] === 'static') {
                $response_data[$key] = $mapping['value'];
            } else if ($mapping['type'] === 'preset') {
                $response_data[$key] = process_preset_value($mapping['value'], $feed_data);
            }
        }
    } else {
        // If no mappings, return all feed data
        $response_data = $feed_data;
    }

    // Return in the same format as preview
    return new WP_REST_Response(array(
        'endpoint' => $endpoint,
        'data' => $response_data
    ), 200);
}

add_action('wp_ajax_idwiz_update_endpoint', 'idwiz_update_endpoint_callback');

function idwiz_update_endpoint_callback()
{
    if (!check_ajax_referer('wiz-endpoints', 'security', false)) {
        wp_send_json_error('Nonce check failed');
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $endpoint = sanitize_text_field($_POST['endpoint']);
    $data = json_decode(stripslashes($_POST['data']), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error('Invalid JSON data provided');
        return;
    }

    if (idwiz_update_endpoint($endpoint, $data)) {
        wp_send_json_success('Endpoint updated successfully');
    } else {
        wp_send_json_error('Failed to update the endpoint');
    }
}

// Add AJAX handler for getting user data for endpoint preview
add_action('wp_ajax_idwiz_get_user_data', 'idwiz_get_user_data_for_preview');

function idwiz_get_user_data_for_preview() {
    // Verify nonce
    if (!check_ajax_referer('wiz-endpoints', 'security', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Get account number
    $account_number = isset($_POST['account_number']) ? sanitize_text_field($_POST['account_number']) : '';
    if (empty($account_number)) {
        wp_send_json_error('No student account number provided');
        return;
    }

    global $wpdb;
    // Get user data from the user feed database
    $feed_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}idemailwiz_userfeed WHERE StudentAccountNumber = %s LIMIT 1",
            $account_number
        ),
        ARRAY_A
    );

    if (!$feed_data) {
        wp_send_json_error('Student not found in feed');
        return;
    }

    // Process presets
    $presets = [
        'most_recent_purchase' => process_preset_value('most_recent_purchase', $feed_data)
    ];

    // Add presets to the response
    $feed_data['_presets'] = $presets;

    wp_send_json_success($feed_data);
}

