<?php

// Add this function near the top with other utility functions
function generate_user_options_html($users, $sort_by_division = false) {
    $html = '<option value="">Select a test user...</option>';
    
    // Optionally sort users by division
    if ($sort_by_division && !empty($users)) {
        usort($users, function($a, $b) {
            return strcmp($a->division, $b->division);
        });
    }
    
    foreach ($users as $user) {
        $html .= sprintf(
            '<option value="%s">%s - %s (%s purchase on %s)</option>',
            esc_attr($user->StudentAccountNumber),
            esc_html($user->StudentAccountNumber),
            esc_html($user->StudentFirstName ?: ''),
            esc_html($user->division),
            esc_html($user->purchaseDate)
        );
    }
    return $html;
}

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
                'courseUrl' => $recCourse->courseUrl
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
 * Gets the most recent location for a student
 */
function get_last_location($student_data) {
    global $wpdb;
    
    // Get the student's most recent purchase with a location
    $purchase = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT shoppingCartItems_locationName 
            FROM {$wpdb->prefix}idemailwiz_purchases 
            WHERE shoppingCartItems_studentAccountNumber = %s 
            AND shoppingCartItems_locationName IS NOT NULL 
            AND shoppingCartItems_locationName != ''
            ORDER BY purchaseDate DESC 
            LIMIT 1",
            $student_data['studentAccountNumber']
        )
    );

    if (!$purchase || !$purchase->shoppingCartItems_locationName) {
        return null;
    }

    // Get location details from locations table
    $location = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, name, locationStatus, address 
            FROM {$wpdb->prefix}idemailwiz_locations 
            WHERE name = %s 
            AND locationStatus IN ('Open', 'Registration opens soon')",
            $purchase->shoppingCartItems_locationName
        )
    );

    if (!$location) {
        return null;
    }

    // Unserialize address if it exists
    $address = $location->address ? unserialize($location->address) : null;
    
    // Build location URL
    $url = 'https://www.idtech.com/locations/' . sanitize_title($location->name);

    return [
        'id' => $location->id,
        'name' => $location->name,
        'status' => $location->locationStatus,
        'url' => $url,
        'address' => $address
    ];
}

/**
 * Calculate distance between two points using Haversine formula
 * @param float $lat1 Latitude of first point
 * @param float $lon1 Longitude of first point
 * @param float $lat2 Latitude of second point
 * @param float $lon2 Longitude of second point
 * @return float Distance in miles
 */
function calculate_distance($lat1, $lon1, $lat2, $lon2) {
    // Convert degrees to radians
    $lat1 = deg2rad(floatval($lat1));
    $lon1 = deg2rad(floatval($lon1));
    $lat2 = deg2rad(floatval($lat2));
    $lon2 = deg2rad(floatval($lon2));
    
    // Earth radius in miles
    $r = 3959;
    
    // Haversine formula
    $dlon = $lon2 - $lon1;
    $dlat = $lat2 - $lat1;
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
    $c = 2 * asin(sqrt($a));
    $d = $r * $c;
    
    return $d;
}

/**
 * Get nearby locations within specified radius
 * @param array $student_data Student data including location coordinates
 * @param int $radius_miles Radius in miles to search
 * @return array Array of nearby locations
 */
function get_nearby_locations($student_data, $radius_miles = 30) {
    global $wpdb;
    
    // First try to get student's location coordinates
    $student_location = null;
    
    // Check if we have a last location with coordinates
    $last_location = get_last_location($student_data);
    if ($last_location && isset($last_location['address'])) {
        if (!empty($last_location['address']['latitude']) && !empty($last_location['address']['longitude'])) {
            $student_location = [
                'latitude' => $last_location['address']['latitude'],
                'longitude' => $last_location['address']['longitude']
            ];
        }
    }
    
    // If no location found from last purchase, check if coordinates provided in student data
    if (!$student_location) {
        if (!empty($student_data['latitude']) && !empty($student_data['longitude'])) {
            $student_location = [
                'latitude' => $student_data['latitude'],
                'longitude' => $student_data['longitude']
            ];
        }
    }
    
    // If we still don't have location data, return empty array
    if (!$student_location) {
        error_log("Nearby Locations: No coordinates found for student {$student_data['studentAccountNumber']}");
        return [];
    }
    
    // Get all active locations
    $locations = $wpdb->get_results(
        "SELECT id, name, locationStatus, address, addressArea 
         FROM {$wpdb->prefix}idemailwiz_locations 
         WHERE locationStatus IN ('Open', 'Registration opens soon')
         AND addressArea IS NOT NULL 
         AND addressArea != ''
         AND divisions IS NOT NULL",
        ARRAY_A
    );
    
    if (empty($locations)) {
        error_log("Nearby Locations: No active locations found in database");
        return [];
    }
    
    // Calculate distance for each location and filter by radius
    $nearby_locations = [];
    
    foreach ($locations as $location) {
        $address = unserialize($location['address']);
        
        // Skip locations without coordinates
        if (empty($address['latitude']) || empty($address['longitude'])) {
            continue;
        }
        
        // Calculate distance
        $distance = calculate_distance(
            $student_location['latitude'],
            $student_location['longitude'],
            $address['latitude'],
            $address['longitude']
        );
        
        // Add location if within radius
        if ($distance <= $radius_miles) {
            // Build location URL
            $url = 'https://www.idtech.com/locations/' . sanitize_title($location['name']);
            
            $nearby_locations[] = [
                'id' => $location['id'],
                'name' => $location['name'],
                'status' => $location['locationStatus'],
                'url' => $url,
                'address' => $address,
                'distance' => round($distance, 1), // Round to 1 decimal place
            ];
        }
    }
    
    // Sort by distance
    usort($nearby_locations, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });
    
    return [
        'total_count' => count($nearby_locations),
        'student_coordinates' => $student_location,
        'locations' => $nearby_locations
    ];
}

/**
 * Get all available presets and their metadata
 * This is the single source of truth for preset definitions
 */
function get_available_presets() {
    return [
        'most_recent_purchase' => [
            'name' => 'Most Recent Purchase Date',
            'group' => 'Purchase History',
            'description' => 'Returns the date of the student\'s most recent purchase. Returns null if the student has no purchases.'
        ],
        'last_location' => [
            'name' => 'Last Camp Location',
            'group' => 'Location History',
            'description' => 'Returns details about the student\'s most recent location including name, status, URL, and address. Only includes locations with status "Open" or "Registration opens soon". Returns null if no valid location is found.'
        ],
        'nearby_locations' => [
            'name' => 'Nearby Camp Locations (30 Miles)',
            'group' => 'Location History',
            'description' => 'Returns locations within 30 miles of the student\'s last known location, sorted by distance. Uses Haversine formula for accurate distance calculation. Returns empty array if no coordinates found for student or no locations within range.'
        ],
        'ipc_course_recs' => [
            'name' => 'IPC Course Recs',
            'group' => 'Course Recs',
            'description' => 'Returns up to 3 in-person course recommendations based on the student\'s previous purchases. Automatically handles age-up logic between iDTC and iDTA. Returns empty array if no recommendations found.'
        ],
        'idtc_course_recs' => [
            'name' => 'iDTC Course Recs',
            'group' => 'Course Recs',
            'description' => 'Returns up to 3 iD Tech Camps course recommendations based on the student\'s previous iDTC purchases. Returns empty array if no recommendations found or if student aged out.'
        ],
        'idta_course_recs' => [
            'name' => 'iDTA Course Recs',
            'group' => 'Course Recs',
            'description' => 'Returns up to 3 Teen Academy course recommendations based on the student\'s previous iDTA purchases. Returns empty array if no recommendations found or if student is not age-eligible.'
        ],
        'vtc_course_recs' => [
            'name' => 'VTC Course Recs',
            'group' => 'Course Recs',
            'description' => 'Returns up to 3 Virtual Tech Camps course recommendations based on the student\'s previous VTC purchases. Returns empty array if no recommendations found.'
        ],
        'ota_course_recs' => [
            'name' => 'OTA Course Recs',
            'group' => 'Course Recs',
            'description' => 'Returns up to 3 Online Teen Academy course recommendations based on the student\'s previous OTA purchases. Returns empty array if no recommendations found or if student is not age-eligible.'
        ],
        'opl_course_recs' => [
            'name' => 'OPL Course Recs',
            'group' => 'Course Recs',
            'description' => 'Returns up to 3 Online Private Lessons course recommendations based on the student\'s previous OPL purchases. Returns empty array if no recommendations found.'
        ],
        'nearby_locations_with_course_recs' => [
            'name' => 'Nearby Locations with Course Recs',
            'description' => 'Returns nearby locations with course recommendations nested within each location. Combines location proximity data with personalized course recommendations.',
            'group' => 'Location & Course Data'
        ],
    ];
}

/**
 * Handles the processing of preset values
 */
function process_preset_value($preset_name, $student_data) {
    $available_presets = get_available_presets();
    
    if (!isset($available_presets[$preset_name])) {
        return null;
    }
    
    switch ($preset_name) {
        case 'most_recent_purchase':
            return get_most_recent_purchase_date($student_data);
        case 'last_location':
            return get_last_location($student_data);
        case 'nearby_locations':
            return get_nearby_locations($student_data, 30); // 30 miles radius
        case 'ipc_course_recs':
            return get_division_course_recommendations($student_data, 'ipc');
        case 'idtc_course_recs':
            return get_division_course_recommendations($student_data, 'idtc');
        case 'idta_course_recs':
            return get_division_course_recommendations($student_data, 'idta');
        case 'vtc_course_recs':
            return get_division_course_recommendations($student_data, 'vtc');
        case 'ota_course_recs':
            return get_division_course_recommendations($student_data, 'ota');
        case 'opl_course_recs':
            return get_division_course_recommendations($student_data, 'opl');
        case 'nearby_locations_with_course_recs':
            global $wpdb;
            
            // Get nearby locations
            $nearby_data = get_nearby_locations($student_data);
            
            // If no nearby locations, return empty array
            if (empty($nearby_data['locations'])) {
                return [];
            }
            
            // Get all location IDs
            $location_ids = array_map(function($loc) {
                return $loc['id'];
            }, $nearby_data['locations']);
            
            // Get course data for all locations, including sessionWeeks
            $location_courses = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, courses, sessionWeeks
                     FROM {$wpdb->prefix}idemailwiz_locations 
                     WHERE id IN (" . implode(',', array_fill(0, count($location_ids), '%d')) . ")",
                    $location_ids
                ),
                ARRAY_A
            );
            
            // Create a map of location ID to courses and sessionWeeks
            $location_courses_map = [];
            $location_weeks_map = [];
            foreach ($location_courses as $loc) {
                $courses = maybe_unserialize($loc['courses']);
                if (!empty($courses) && is_array($courses)) {
                    $location_courses_map[$loc['id']] = $courses;
                }
                
                $session_weeks = $loc['sessionWeeks'] ? maybe_unserialize($loc['sessionWeeks']) : null;
                if ($session_weeks) {
                    $location_weeks_map[$loc['id']] = $session_weeks;
                }
            }
            
            // Get course recommendations for the student (only in-person courses)
            // Use ipc parameter which handles both iDTC and iDTA recommendations based on age
            $course_recs = get_division_course_recommendations($student_data, 'ipc');
            
            // Get all recommended course IDs to fetch their details
            $course_ids = array_map(function($rec) {
                return $rec['id'];
            }, $course_recs['recs'] ?? []);
            
            // Fetch course details to determine if each is a camp or academy
            $course_details = [];
            if (!empty($course_ids)) {
                $course_data = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, title, abbreviation, division_id 
                         FROM {$wpdb->prefix}idemailwiz_courses 
                         WHERE id IN (" . implode(',', array_fill(0, count($course_ids), '%d')) . ")",
                        $course_ids
                    ),
                    ARRAY_A
                );
                
                // Create a map of course details
                foreach ($course_data as $course) {
                    $is_academy = $course['division_id'] == 22;
                    
                    $course_details[$course['id']] = [
                        'title' => $course['title'],
                        'abbreviation' => $course['abbreviation'],
                        'division_id' => $course['division_id'],
                        'type' => $is_academy ? 'academies' : 'camps'
                    ];
                }
            }
            
            // Add course data to each location
            $locations_with_recs = [];
            foreach ($nearby_data['locations'] as $location) {
                $loc_id = $location['id'];
                $location_with_recs = $location;
                
                // Add personalized course recommendations if available
                if (!empty($course_recs['recs'])) {
                    // Filter recommendations to only include courses available at this location
                    $location_specific_recs = array_filter($course_recs['recs'], function($rec) use ($location_courses_map, $loc_id) {
                        return isset($location_courses_map[$loc_id]) && in_array($rec['id'], $location_courses_map[$loc_id]);
                    });
                    
                    if (!empty($location_specific_recs)) {
                        // Add sessionWeeks to each recommendation
                        foreach ($location_specific_recs as &$rec) {
                            $course_type = $course_details[$rec['id']]['type'] ?? 'camps';
                            
                            // Add sessionWeeks if available for this location and course type
                            if (isset($location_weeks_map[$loc_id]) && isset($location_weeks_map[$loc_id][$course_type])) {
                                $rec['sessionWeeks'] = $location_weeks_map[$loc_id][$course_type];
                            } else {
                                $rec['sessionWeeks'] = [];
                            }
                        }
                        
                        $location_with_recs['courses'] = array_values($location_specific_recs);
                    }
                }
                
                $locations_with_recs[] = $location_with_recs;
            }
            
            // Return a cleaner, flatter structure
            return [
                'metadata' => [
                    'total_locations' => count($locations_with_recs),
                    'student_coordinates' => $nearby_data['student_coordinates'],
                    'age_up' => $course_recs['age_up'] ?? false,
                    'student_age' => $course_recs['student_age'] ?? null,
                    'from_fiscal_year' => $course_recs['from_fiscal_year'] ?? null,
                    'to_fiscal_year' => $course_recs['to_fiscal_year'] ?? null
                ],
                'last_purchase' => $course_recs['last_purchase'] ?? null,
                'locations' => $locations_with_recs
            ];
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

/**
 * Gets course recommendations for a student between fiscal years
 */
function get_course_recommendations_between_fiscal_years($student_data, $from_fiscal_year, $to_fiscal_year) {
    global $wpdb;
    
    // Get student account number from the data
    $student_account_number = $student_data['studentAccountNumber'];
    if (!$student_account_number) {
        error_log("Course Recs: No student account number found");
        return [];
    }
    
    // Define fiscal year ranges
    $fiscal_years = [
        'fy25' => ['start' => '2024-11-01', 'end' => '2025-10-31'],
        'fy24' => ['start' => '2023-11-01', 'end' => '2024-10-31'],
        'fy23' => ['start' => '2022-11-01', 'end' => '2023-10-31']
    ];
    
    if (!isset($fiscal_years[$from_fiscal_year]) || !isset($fiscal_years[$to_fiscal_year])) {
        error_log("Course Recs: Invalid fiscal year specified");
        return [];
    }
    
    $from_dates = $fiscal_years[$from_fiscal_year];
    
    error_log("Course Recs: Searching for purchases between {$from_dates['start']} and {$from_dates['end']} for student $student_account_number");
    
    // Get the student's most recent purchase within the FROM fiscal year
    $latestPurchase = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.*, uf.studentDOB 
            FROM {$wpdb->prefix}idemailwiz_purchases p
            JOIN {$wpdb->prefix}idemailwiz_userfeed uf ON p.shoppingCartItems_studentAccountNumber = uf.studentAccountNumber
            WHERE p.shoppingCartItems_studentAccountNumber = %s 
            AND p.shoppingCartItems_divisionId IN (22, 25)
            AND p.purchaseDate BETWEEN %s AND %s
            ORDER BY p.purchaseDate DESC 
            LIMIT 1",
            $student_account_number,
            $from_dates['start'],
            $from_dates['end']
        ),
        ARRAY_A
    );

    if (!$latestPurchase) {
        error_log("Course Recs: No purchases found for student $student_account_number in $from_fiscal_year");
        return [];
    }

    error_log("Course Recs: Found purchase from " . $latestPurchase['purchaseDate'] . " for course ID " . $latestPurchase['shoppingCartItems_id']);

    // Get the course details
    $course = get_course_details_by_id($latestPurchase['shoppingCartItems_id']);
    if (is_wp_error($course) || !isset($course->course_recs)) {
        error_log("Course Recs: Failed to get course details for ID " . $latestPurchase['shoppingCartItems_id']);
        return [];
    }

    // Calculate student age and age at last purchase using DOB from the joined query
    $studentDOB = $latestPurchase['studentDOB'];
    if (!$studentDOB) {
        error_log("Course Recs: No DOB found for student");
        return [];
    }

    $studentAge = calculate_student_age($studentDOB);
    $ageAtLastPurchase = calculate_age_at_purchase($studentDOB, $latestPurchase['purchaseDate']);

    if ($studentAge === false) {
        error_log("Course Recs: Invalid student DOB format");
        return [];
    }

    error_log("Course Recs: Student age: $studentAge, Age at last purchase: $ageAtLastPurchase");

    // Determine if student needs age-up recommendations
    $needsAgeUp = determine_age_up_need($studentAge, $ageAtLastPurchase, $course);

    // Get recommendations based on the student's current division
    $fromDivision = $latestPurchase['shoppingCartItems_divisionId'] == 25 ? 'idtc' : 'idta';
    $toDivision = $fromDivision; // Keep same division for recommendations

    // Get course recommendations
    $recommendations = get_course_recommendations($course, $toDivision, $needsAgeUp);

    if (empty($recommendations)) {
        error_log("Course Recs: No recommendations found for course ID " . $latestPurchase['shoppingCartItems_id']);
        return [];
    }

    error_log("Course Recs: Found " . count($recommendations) . " recommendations");

    // Limit to 3 recommendations and format for Iterable
    $formattedRecs = [];
    $count = 0;
    foreach ($recommendations as $rec) {
        if ($count >= 3) break;
        
        $formattedRecs[] = [
            'id' => $rec['id'],
            'title' => $rec['title'],
            'abbreviation' => $rec['abbreviation'],
            'age_range' => $rec['minAge'] . '-' . $rec['maxAge'],
            'url' => $rec['courseUrl'] ?? ''
        ];
        $count++;
    }

    // Only return data if we actually have recommendations
    if (empty($formattedRecs)) {
        return [];
    }

    return [
        'recs' => $formattedRecs,
        'total_count' => count($formattedRecs),
        'age_up' => $needsAgeUp,
        'student_age' => $studentAge,
        'from_fiscal_year' => $from_fiscal_year,
        'to_fiscal_year' => $to_fiscal_year,
        'last_purchase' => [
            'date' => $latestPurchase['purchaseDate'],
            'course_id' => $latestPurchase['shoppingCartItems_id'],
            'course_name' => $course->title,
            'abbreviation' => $course->abbreviation,
            'age_range' => $course->minAge . '-' . $course->maxAge,
            'courseUrl' => $course->courseUrl ?? '',
            'division' => $latestPurchase['shoppingCartItems_divisionName'],
            'division_id' => $latestPurchase['shoppingCartItems_divisionId'],
            'location' => $latestPurchase['shoppingCartItems_locationName'] ?? null
        ]
    ];
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
        'most_recent_purchase' => process_preset_value('most_recent_purchase', $feed_data),
        'last_location' => process_preset_value('last_location', $feed_data)
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
            "SELECT * FROM {$wpdb->prefix}idemailwiz_userfeed WHERE studentAccountNumber = %s LIMIT 1",
            $account_number
        ),
        ARRAY_A
    );

    if (!$feed_data) {
        return new WP_REST_Response(array(
            'error' => 'Student not found in feed',
        ), 404);
    }

    // OPTIMIZATION: Only process presets that are actually needed based on the data mapping
    $presets = [];
    $required_presets = [];
    
    // Determine which presets are actually required based on the data mapping
    if (!empty($endpoint_config['data_mapping'])) {
        foreach ($endpoint_config['data_mapping'] as $mapping) {
            if ($mapping['type'] === 'preset') {
                $required_presets[] = $mapping['value'];
            }
        }
    } else {
        // If no mappings, only include essential presets
        $required_presets = ['most_recent_purchase', 'last_location'];
    }
    
    // Only process required presets
    if (in_array('most_recent_purchase', $required_presets)) {
        $presets['most_recent_purchase'] = get_most_recent_purchase_date($feed_data);
    }
    
    if (in_array('last_location', $required_presets)) {
        $presets['last_location'] = process_preset_value('last_location', $feed_data);
    }
    
    if (in_array('nearby_locations', $required_presets)) {
        $presets['nearby_locations'] = process_preset_value('nearby_locations', $feed_data);
    }
    
    $course_rec_presets = [
        'ipc_course_recs',
        'idtc_course_recs',
        'idta_course_recs',
        'vtc_course_recs',
        'ota_course_recs',
        'opl_course_recs'
    ];
    
    foreach ($course_rec_presets as $preset) {
        if (in_array($preset, $required_presets)) {
            try {
                $presets[$preset] = process_preset_value($preset, $feed_data);
            } catch (Exception $e) {
                // Log error but continue processing
                error_log("Error processing preset $preset: " . $e->getMessage());
                $presets[$preset] = null;
            }
        }
    }
    
    // Handle complex combination preset separately with error handling
    if (in_array('nearby_locations_with_course_recs', $required_presets)) {
        try {
            $presets['nearby_locations_with_course_recs'] = process_preset_value('nearby_locations_with_course_recs', $feed_data);
        } catch (Exception $e) {
            error_log("Error processing nearby_locations_with_course_recs: " . $e->getMessage());
            $presets['nearby_locations_with_course_recs'] = null;
        }
    }

    // Build response data based on mappings
    $response_data = array();
    
    if (!empty($endpoint_config['data_mapping'])) {
        foreach ($endpoint_config['data_mapping'] as $key => $mapping) {
            if ($mapping['type'] === 'static') {
                $response_data[$key] = $mapping['value'];
            } else if ($mapping['type'] === 'preset') {
                // Only include the preset if it exists and is not null
                if (isset($presets[$mapping['value']]) && $presets[$mapping['value']] !== null) {
                    $response_data[$key] = $presets[$mapping['value']];
                }
            }
        }
    } else {
        // If no mappings, return basic feed data plus essential presets
        $response_data = $feed_data;
        if (!empty($presets)) {
            $response_data['_presets'] = $presets;
        }
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
            "SELECT * FROM {$wpdb->prefix}idemailwiz_userfeed WHERE studentAccountNumber = %s LIMIT 1",
            $account_number
        ),
        ARRAY_A
    );

    if (!$feed_data) {
        wp_send_json_error('Student not found in feed');
        return;
    }

    // Process presets
    $presets = [];
    
    // Most recent purchase preset
    $presets['most_recent_purchase'] = process_preset_value('most_recent_purchase', $feed_data);
    
    // Last location preset
    $presets['last_location'] = process_preset_value('last_location', $feed_data);
    
    // Nearby locations preset
    $presets['nearby_locations'] = process_preset_value('nearby_locations', $feed_data);
    
    // Process all course recommendation presets
    $preset_types = [
        'ipc_course_recs',
        'idtc_course_recs',
        'idta_course_recs',
        'vtc_course_recs',
        'ota_course_recs',
        'opl_course_recs',
        'nearby_locations_with_course_recs'
    ];

    foreach ($preset_types as $preset) {
        $result = process_preset_value($preset, $feed_data);
        if ($result !== null) {
            $presets[$preset] = $result;
        }
    }

    // Add presets to the response
    $feed_data['_presets'] = $presets;

    wp_send_json_success($feed_data);
}

/**
 * Gets course recommendations for a specific division
 */
function get_division_course_recommendations($student_data, $division_type) {
    global $wpdb;
    
    // Add timeout protection
    $start_time = microtime(true);
    $timeout_seconds = 5; // Set a reasonable timeout limit
    
    // Get student account number from the data
    $student_account_number = $student_data['studentAccountNumber'];
    if (!$student_account_number) {
        error_log("Course Recs: No student account number found");
        return [];
    }
    
    // Define current fiscal year
    $current_date = new DateTime();
    $year = $current_date->format('Y');
    $month = $current_date->format('n');
    
    // For any date from November through October, the fiscal year is the next calendar year
    // So March 2025 is in FY2025 (which runs 11/1/2024 - 10/31/2025)
    $current_fy_year = ($month >= 11) ? $year + 1 : $year;
    
    $current_fy = 'fy' . substr($current_fy_year, -2);
    $current_fy_start = new DateTime(($current_fy_year - 1) . '-11-01');
    $current_fy_end = new DateTime($current_fy_year . '-10-31');
    
    error_log("Course Recs: Current fiscal year is $current_fy ({$current_fy_start->format('Y-m-d')} to {$current_fy_end->format('Y-m-d')})");
    
    // Define division IDs based on division type
    $fromDivisionIds = [];
    $toDivision = '';
    
    switch($division_type) {
        case 'ipc':
            $fromDivisionIds = [22, 25]; // Both iDTA and iDTC
            $toDivision = 'idtc'; // Default to iDTC, age-up logic will handle iDTA if needed
            break;
        case 'idtc':
            $fromDivisionIds = [25];
            $toDivision = 'idtc';
            break;
        case 'idta':
            $fromDivisionIds = [22];
            $toDivision = 'idta';
            break;
        case 'vtc':
            $fromDivisionIds = [42];
            $toDivision = 'vtc';
            break;
        case 'ota':
            $fromDivisionIds = [47];
            $toDivision = 'ota';
            break;
        case 'opl':
            $fromDivisionIds = [41];
            $toDivision = 'opl';
            break;
        default:
            error_log("Course Recs: Invalid division type: $division_type");
            return [];
    }
    
    // Check timeout
    if ((microtime(true) - $start_time) > $timeout_seconds) {
        error_log("Course Recs: Timeout exceeded before database query for student $student_account_number");
        return [];
    }
    
    try {
        // Get the student's most recent purchase from the previous fiscal year
        $prev_fy_start = new DateTime(($current_fy_year - 2) . '-11-01');
        $prev_fy_end = new DateTime(($current_fy_year - 1) . '-10-31');
        
        error_log("Course Recs: Looking for purchases between {$prev_fy_start->format('Y-m-d')} and {$prev_fy_end->format('Y-m-d')}");
        
        $latestPurchase = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.*, uf.studentDOB 
                FROM {$wpdb->prefix}idemailwiz_purchases p
                JOIN {$wpdb->prefix}idemailwiz_userfeed uf ON p.shoppingCartItems_studentAccountNumber = uf.studentAccountNumber
                WHERE p.shoppingCartItems_studentAccountNumber = %s 
                AND p.shoppingCartItems_divisionId IN (" . implode(',', $fromDivisionIds) . ")
                AND p.purchaseDate BETWEEN %s AND %s
                ORDER BY p.purchaseDate DESC 
                LIMIT 1",
                $student_account_number,
                $prev_fy_start->format('Y-m-d'),
                $prev_fy_end->format('Y-m-d')
            ),
            ARRAY_A
        );
    } catch (Exception $e) {
        error_log("Course Recs: Database error when getting purchase data: " . $e->getMessage());
        return [];
    }

    if (!$latestPurchase) {
        error_log("Course Recs: No previous purchases found for student $student_account_number");
        return [];
    }

    error_log("Course Recs: Found purchase from " . $latestPurchase['purchaseDate'] . " for course ID " . $latestPurchase['shoppingCartItems_id']);

    // Check timeout
    if ((microtime(true) - $start_time) > $timeout_seconds) {
        error_log("Course Recs: Timeout exceeded before course details for student $student_account_number");
        return [];
    }
    
    try {
        // Get the course details
        $course = get_course_details_by_id($latestPurchase['shoppingCartItems_id']);
        if (is_wp_error($course) || !isset($course->course_recs)) {
            error_log("Course Recs: Failed to get course details for ID " . $latestPurchase['shoppingCartItems_id']);
            return [];
        }
    } catch (Exception $e) {
        error_log("Course Recs: Error getting course details: " . $e->getMessage());
        return [];
    }

    // Calculate student age and age at last purchase using DOB from the joined query
    $studentDOB = $latestPurchase['studentDOB'];
    if (!$studentDOB) {
        error_log("Course Recs: No DOB found for student");
        return [];
    }

    try {
        $studentAge = calculate_student_age($studentDOB);
        $ageAtLastPurchase = calculate_age_at_purchase($studentDOB, $latestPurchase['purchaseDate']);

        if ($studentAge === false) {
            error_log("Course Recs: Invalid student DOB format");
            return [];
        }
    } catch (Exception $e) {
        error_log("Course Recs: Error calculating age: " . $e->getMessage());
        return [];
    }

    error_log("Course Recs: Student age: $studentAge, Age at last purchase: $ageAtLastPurchase");

    // Determine if student needs age-up recommendations
    $needsAgeUp = determine_age_up_need($studentAge, $ageAtLastPurchase, $course);

    // For IPC recommendations, if student needs age-up and is 13+, switch to iDTA
    if ($division_type === 'ipc' && $needsAgeUp && $studentAge >= 13) {
        $toDivision = 'idta';
    }

    // Check timeout
    if ((microtime(true) - $start_time) > $timeout_seconds) {
        error_log("Course Recs: Timeout exceeded before getting recommendations for student $student_account_number");
        return [];
    }
    
    try {
        // Get course recommendations
        $recommendations = get_course_recommendations($course, $toDivision, $needsAgeUp);

        if (empty($recommendations)) {
            error_log("Course Recs: No recommendations found for course ID " . $latestPurchase['shoppingCartItems_id']);
            return [];
        }
    } catch (Exception $e) {
        error_log("Course Recs: Error getting recommendations: " . $e->getMessage());
        return [];
    }

    error_log("Course Recs: Found " . count($recommendations) . " recommendations");

    // Limit to 3 recommendations and format for Iterable
    $formattedRecs = [];
    $count = 0;
    foreach ($recommendations as $rec) {
        if ($count >= 3) break;
        
        $formattedRecs[] = [
            'id' => $rec['id'],
            'title' => $rec['title'],
            'abbreviation' => $rec['abbreviation'],
            'age_range' => $rec['minAge'] . '-' . $rec['maxAge'],
            'url' => $rec['courseUrl'] ?? ''
        ];
        $count++;
    }

    // Only return data if we actually have recommendations
    if (empty($formattedRecs)) {
        return [];
    }

    // Get fiscal year info for the last purchase
    $purchase_date = new DateTime($latestPurchase['purchaseDate']);
    $purchase_year = $purchase_date->format('Y');
    $purchase_month = $purchase_date->format('n');
    
    // Determine the fiscal year of the purchase
    if ($purchase_month >= 11) {
        $purchase_fy_year = $purchase_year + 1;
    } else {
        $purchase_fy_year = $purchase_year;
    }
    
    $from_fiscal_year = 'fy' . substr($purchase_fy_year, -2);
    // We already calculated the current fiscal year above
    $to_fiscal_year = $current_fy;

    return [
        'recs' => $formattedRecs,
        'total_count' => count($formattedRecs),
        'age_up' => $needsAgeUp,
        'student_age' => $studentAge,
        'from_fiscal_year' => $from_fiscal_year,
        'to_fiscal_year' => $to_fiscal_year,
        'last_purchase' => [
            'date' => $latestPurchase['purchaseDate'],
            'course_id' => $latestPurchase['shoppingCartItems_id'],
            'course_name' => $course->title,
            'abbreviation' => $course->abbreviation,
            'age_range' => $course->minAge . '-' . $course->maxAge,
            'courseUrl' => $course->courseUrl ?? '',
            'division' => $latestPurchase['shoppingCartItems_divisionName'],
            'division_id' => $latestPurchase['shoppingCartItems_divisionId'],
            'location' => $latestPurchase['shoppingCartItems_locationName'] ?? null
        ]
    ];
}

// Add an AJAX endpoint to get available presets
add_action('wp_ajax_idwiz_get_available_presets', 'idwiz_get_available_presets_callback');

function idwiz_get_available_presets_callback() {
    wp_send_json_success(get_available_presets());
}

/**
 * Get users from the previous fiscal year with at least one from each division
 */
function idwiz_get_previous_year_users() {
    global $wpdb;
    
    // Define current fiscal year
    $current_date = new DateTime();
    $year = $current_date->format('Y');
    $month = $current_date->format('n');
    
    // If we're in Nov-Dec, we're in the next fiscal year
    if ($month >= 11) {
        $current_fy_year = $year + 1;
    } else {
        $current_fy_year = $year;
    }
    
    // Define previous fiscal year date range
    $prev_fy_year = $current_fy_year - 1;
    $prev_fy_start = ($prev_fy_year - 1) . '-11-01';
    $prev_fy_end = $prev_fy_year . '-10-31';
    
    // Get one recent user from each division
    $division_ids = [22, 25, 42, 47, 41]; // IDTA, IDTC, VTC, OTA, OPL
    $users = [];
    
    foreach ($division_ids as $division_id) {
        $division_users = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT 
                    uf.StudentAccountNumber,
                    uf.StudentFirstName,
                    p.shoppingCartItems_divisionName as division,
                    p.purchaseDate
                FROM {$wpdb->prefix}idemailwiz_userfeed uf
                JOIN (
                    SELECT 
                        shoppingCartItems_studentAccountNumber,
                        MAX(purchaseDate) as latest_purchase
                    FROM {$wpdb->prefix}idemailwiz_purchases
                    WHERE purchaseDate BETWEEN %s AND %s
                    AND shoppingCartItems_divisionId = %d
                    GROUP BY shoppingCartItems_studentAccountNumber
                ) latest ON uf.StudentAccountNumber = latest.shoppingCartItems_studentAccountNumber
                JOIN {$wpdb->prefix}idemailwiz_purchases p 
                    ON p.shoppingCartItems_studentAccountNumber = latest.shoppingCartItems_studentAccountNumber
                    AND p.purchaseDate = latest.latest_purchase
                GROUP BY uf.StudentAccountNumber
                ORDER BY purchaseDate DESC 
                LIMIT 3", // Get 3 per division to ensure we have enough
                $prev_fy_start,
                $prev_fy_end,
                $division_id
            )
        );
        
        if (!empty($division_users)) {
            $users = array_merge($users, $division_users);
        }
    }
    
    return $users;
}

