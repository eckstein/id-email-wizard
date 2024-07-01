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

function wiz_handle_user_data_feed($data)
{
    // $wizSettings = get_option('idemailwiz_settings');
    // $api_auth_token = $wizSettings['external_cron_api'];

    // $token = $data->get_header('Authorization');
    // if ($token !== $api_auth_token) {
    //     return new WP_REST_Response('Invalid or missing token', 403);
    // }

    $params = $data->get_params();

    $responseCode = 400; // default to fail

    //$encryptedUser = wiz_encrypt_email($params);
    $wizUser = get_idwiz_user_by_userID($params['userId']);

    if (!$wizUser) {
        $return = 'User ' . $params['userId'] . ' not found in Wizard!';
    } else {
        $return = [];
        $responseCode = 200;
        $userPurchases = get_idwiz_purchases(['userId' => $params['userId'], 'sortBy' => 'purchaseDate', 'sort' => 'DESC']);
        foreach ($userPurchases as $purchase) {
            $purchaseLocationName = $purchase['shoppingCartItems_locationName'];

            if ($purchaseLocationName && $purchaseLocationName !== 'Online Campus') {

                // query locations table for match by name
                global $wpdb;
                $locationData = $wpdb->get_results("SELECT * FROM wp_idemailwiz_locations WHERE name = '" . $purchaseLocationName . "' LIMIT 1");

                // return 400 on error
                if (empty($locationData)) {
                    return new WP_REST_Response('Error: Location not active.', 400);
                }

                $return['location']['name'] = $locationData[0]->name;

                $return['location']['firstSessionStartDate'] = $locationData[0]->firstSessionStartDate;
                break;
            }
        }
    }
    return new WP_REST_Response($return, $responseCode);
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
 * Save all endpoints to the options table
 *
 * @param array $endpoints Array of endpoints
 * @return bool True if option was successfully updated, false otherwise.
 */
function idwiz_save_all_endpoints($endpoints)
{
    return update_option('idwiz_rest_endpoints', $endpoints);
}

/**
 * Retrieve all endpoints from the options table
 *
 * @return array Array of all endpoints
 */
function idwiz_get_all_endpoints()
{
    return get_option('idwiz_rest_endpoints', array());
}

/**
 * Add a new endpoint
 *
 * @param string $endpoint The endpoint route
 * @return bool True if successful, false otherwise
 */
function idwiz_add_endpoint($endpoint)
{
    $endpoints = idwiz_get_all_endpoints();
    if (!in_array($endpoint, $endpoints)) {
        $endpoints[] = $endpoint;
        return idwiz_save_all_endpoints($endpoints);
    }
    return false;
}

/**
 * Remove an endpoint
 *
 * @param string $endpoint The endpoint route
 * @return bool True if successful, false otherwise
 */
function idwiz_remove_endpoint($endpoint)
{
    $endpoints = idwiz_get_all_endpoints();
    $key = array_search($endpoint, $endpoints);
    if ($key !== false) {
        unset($endpoints[$key]);
        return idwiz_save_all_endpoints(array_values($endpoints));
    }
    return false;
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

    if (idwiz_add_endpoint($endpoint)) {
        wp_send_json_success('Endpoint created successfully');
    } else {
        wp_send_json_error('Failed to create the endpoint or it already exists.');
    }
}

function idwiz_endpoint_handler($request)
{
    $route = $request->get_route();
    $endpoint = str_replace('/idemailwiz/v1', '', $route);

    switch ($endpoint) {
        case '/user_data':
           return wiz_handle_user_data_feed($request);
            break;
        case '/user_courses':
            return wiz_handle_user_courses_data_feed($request);
            break;
        default:
            return new WP_REST_Response(array(
                'message' => 'Endpoint accessed, but no handler exists.',
                'endpoint' => $endpoint,
            ), 200);
            break;
    }
    
}

