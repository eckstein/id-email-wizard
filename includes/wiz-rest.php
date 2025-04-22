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
    
    // If this is a request for 'ipc' (in-person courses that could be either idtc or idta)
    if ($toDivision === 'ipc') {
        // Check for explicit 'ipc' mapping first
        $recommendations = [];
        
        if (isset($courseRecs['ipc']) && is_array($courseRecs['ipc']) && !empty($courseRecs['ipc'])) {
            foreach ($courseRecs['ipc'] as $recCourseId) {
                $recCourse = get_course_details_by_id($recCourseId);
                if (!is_wp_error($recCourse)) {
                    $recommendations[] = [
                        'id' => $recCourse->id,
                        'title' => $recCourse->title,
                        'abbreviation' => $recCourse->abbreviation,
                        'minAge' => $recCourse->minAge,
                        'maxAge' => $recCourse->maxAge,
                        'courseUrl' => $recCourse->courseUrl ?? ''
                    ];
                }
            }
        }
        
        return $recommendations;
    }
    // If we're looking for iDTA or iDTC recommendations
    else if ($toDivision === 'idta' || $toDivision === 'idtc') {
        // For students 13+, we want to check both iDTC and iDTA mappings
        $recommendations = [];
        
        // Primary recommendation key (the one requested)
        $primaryKey = $needsAgeUp && $toDivision === 'idtc' ? $toDivision . '_ageup' : $toDivision;
        
        // First check the primary division
        if (isset($courseRecs[$primaryKey]) && is_array($courseRecs[$primaryKey]) && !empty($courseRecs[$primaryKey])) {
            foreach ($courseRecs[$primaryKey] as $recCourseId) {
                $recCourse = get_course_details_by_id($recCourseId);
                if (!is_wp_error($recCourse)) {
                    $recommendations[] = [
                        'id' => $recCourse->id,
                        'title' => $recCourse->title,
                        'abbreviation' => $recCourse->abbreviation,
                        'minAge' => $recCourse->minAge,
                        'maxAge' => $recCourse->maxAge,
                        'courseUrl' => $recCourse->courseUrl ?? ''
                    ];
                }
            }
        }
        
        return $recommendations;
    }
    // For specific division type requests or non-in-person courses (or when needing age up)
    else {
        $recKey = $needsAgeUp && !in_array($toDivision, ['opl', 'ota', 'idta']) ? $toDivision . '_ageup' : $toDivision;

        // If no key exists or it's empty, return an empty array
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
                    'courseUrl' => $recCourse->courseUrl ?? ''
                ];
            }
        }
        return $recommendations;
    }
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
            "SELECT id, name, locationStatus, address, locationUrl 
            FROM {$wpdb->prefix}idemailwiz_locations 
            WHERE name = %s 
            AND locationStatus IN ('Open', 'Registration opens soon')",
            $purchase->shoppingCartItems_locationName
        )
    );

    if (!$location) {
        return null;
    }
    
    // Return null if this is Online Campus (ID 324)
    if ($location->id == 324) {
        return null;
    }

    // Unserialize address if it exists
    $address = $location->address ? unserialize($location->address) : null;
    
    // Use locationUrl from database if available, otherwise fallback to generated URL
    $url = !empty($location->locationUrl) ? $location->locationUrl : null;

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
        return [];
    }
    
    // Get all active locations
    $locations = $wpdb->get_results(
        "SELECT id, name, locationStatus, address, addressArea, locationUrl
         FROM {$wpdb->prefix}idemailwiz_locations 
         WHERE locationStatus IN ('Open', 'Registration opens soon')
         AND addressArea IS NOT NULL 
         AND addressArea != ''
         AND divisions IS NOT NULL
         AND id != 324", // Exclude Online Campus with ID 324
        ARRAY_A
    );
    
    if (empty($locations)) {
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
            // Use locationUrl from database if available, otherwise set to null
            $url = !empty($location['locationUrl']) ? $location['locationUrl'] : null;
            
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
        'location_with_courses' => [
            'name' => 'Location With Courses',
            'group' => 'Location Details',
            'description' => 'Returns location data and available courses for a specific location ID using the leadLocationId field. Includes location details, address, and courses information.'
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
            'name' => 'Course Recs with Nearby Locations',
            'description' => 'Returns personalized course recommendations with nearby locations nested within each course. Each course includes available locations and their session weeks, making it easy to loop through course recommendations in templates.',
            'group' => 'Course & Location Data'
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
        case 'location_with_courses':
            return get_location_with_courses($student_data);
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
            
            // If no nearby locations, return null
            if (empty($nearby_data['locations'])) {
                return null;
            }
            
            // Get all location IDs
            $location_ids = array_map(function($loc) {
                return $loc['id'];
            }, $nearby_data['locations']);
            
            // Ensure Online Campus (ID 324) is excluded from locations
            $location_ids = array_filter($location_ids, function($id) {
                return $id !== 324;
            });
            
            if (empty($location_ids)) {
                return null;
            }
            
            // Get course data for all locations, including sessionWeeks
            $location_courses = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, courses, sessionWeeks, locationUrl
                     FROM {$wpdb->prefix}idemailwiz_locations 
                     WHERE id IN (" . implode(',', array_fill(0, count($location_ids), '%d')) . ")
                     AND id != 324", // Extra safeguard to exclude Online Campus
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
            
            // Get student age
            $student_age = get_student_age($student_data);
            
            // Get comprehensive course recommendations based on age
            $all_recommendations = [];
            $last_purchase = null;
            $metadata = [
                'student_age' => $student_age
            ];
            
            // Get recommendations based on age appropriate divisions
            if ($student_age >= 13) {
                // For students 13+, get both IDTA and IDTC recommendations
                
                // First try iDTA recommendations
                $idta_recs = get_division_course_recommendations($student_data, 'idta');
                if (!empty($idta_recs['recs'])) {
                    $all_recommendations = $idta_recs['recs'];
                    $last_purchase = $idta_recs['last_purchase'];
                    $metadata['idta_count'] = count($idta_recs['recs']);
                }
                
                // Then get iDTC recommendations
                $idtc_recs = get_division_course_recommendations($student_data, 'idtc');
                if (!empty($idtc_recs['recs'])) {
                    // Add unique recommendations from iDTC
                    foreach ($idtc_recs['recs'] as $rec) {
                        $exists = false;
                        foreach ($all_recommendations as $existing) {
                            if ($existing['id'] == $rec['id']) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $all_recommendations[] = $rec;
                        }
                    }
                    
                    // Use the iDTC purchase if no iDTA purchase
                    if (!$last_purchase) {
                        $last_purchase = $idtc_recs['last_purchase'];
                    }
                    
                    $metadata['idtc_count'] = count($idtc_recs['recs']);
                }
                
                // Check if student has a previous purchase and look for additional mappings
                if (!empty($idta_recs['last_purchase']) && !empty($idta_recs['last_purchase']['course_id'])) {
                    $previous_course_id = $idta_recs['last_purchase']['course_id'];
                    
                    $previous_course = get_course_details_by_id($previous_course_id);
                    if (!is_wp_error($previous_course) && !empty($previous_course->course_recs)) {
                        $mappings = unserialize($previous_course->course_recs);
                        
                        // Check all relevant mappings for a 13+ student who had an Academy course
                        $additional_mapping_keys = ['idtc_ageup', 'idtc', 'ipc'];
                        
                        foreach ($additional_mapping_keys as $key) {
                            if (isset($mappings[$key]) && is_array($mappings[$key]) && !empty($mappings[$key])) {
                                // Add these courses to our recommendations if they don't already exist
                                foreach ($mappings[$key] as $course_id) {
                                    // Check if this course is already in recommendations
                                    $already_exists = false;
                                    foreach ($all_recommendations as $existing) {
                                        if ($existing['id'] == $course_id) {
                                            $already_exists = true;
                                            break;
                                        }
                                    }
                                    
                                    if (!$already_exists) {
                                        $additional_course = get_course_details_by_id($course_id);
                                        if (!is_wp_error($additional_course)) {
                                            $all_recommendations[] = [
                                                'id' => $additional_course->id,
                                                'title' => $additional_course->title,
                                                'abbreviation' => $additional_course->abbreviation,
                                                'minAge' => $additional_course->minAge,
                                                'maxAge' => $additional_course->maxAge,
                                                'age_range' => $additional_course->minAge . '-' . $additional_course->maxAge,
                                                'url' => $additional_course->courseUrl ?? ''
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // For students under 13, just get iDTC recommendations
                $idtc_recs = get_division_course_recommendations($student_data, 'idtc');
                if (!empty($idtc_recs['recs'])) {
                    $all_recommendations = $idtc_recs['recs'];
                    $last_purchase = $idtc_recs['last_purchase'];
                    $metadata['idtc_count'] = count($idtc_recs['recs']);
                }
            }
            
            // Check if we have any recommendations
            if (empty($all_recommendations)) {
                return null;
            }
            
            // Get all recommended course IDs to fetch their details
            $course_ids = array_map(function($rec) {
                return $rec['id'];
            }, $all_recommendations);
            
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
            
            // Restructure data with courses as primary and locations nested inside
            $course_recs_with_locations = [];
            
            // Initialize course recommendations with empty locations array
            foreach ($all_recommendations as $course_rec) {
                $course_id = $course_rec['id'];
                $course_type = isset($course_details[$course_id]) ? $course_details[$course_id]['type'] : 'camps';
                
                $course_with_locations = $course_rec;
                $course_with_locations['locations'] = [];
                $course_with_locations['course_type'] = $course_type;
                
                $course_recs_with_locations[$course_id] = $course_with_locations;
            }
            
            // Now populate each course with its available locations
            foreach ($nearby_data['locations'] as $location) {
                $loc_id = $location['id'];
                
                // Skip locations without course data
                if (!isset($location_courses_map[$loc_id]) || empty($location_courses_map[$loc_id])) {
                    continue;
                }
                
                // For each recommended course, check if it's available at this location
                foreach ($course_recs_with_locations as $course_id => &$course_rec) {
                    if (in_array($course_id, $location_courses_map[$loc_id])) {
                        // This course is available at this location
                        $course_type = $course_rec['course_type']; // 'academies' or 'camps'
                        
                        // Get the location URL from the database if available
                        $locationUrl = null;
                        foreach ($location_courses as $loc_data) {
                            if ($loc_data['id'] == $loc_id && !empty($loc_data['locationUrl'])) {
                                $locationUrl = $loc_data['locationUrl'];
                                break;
                            }
                        }
                        
                        // Create a simplified location object with just what we need
                        $location_data = [
                            'id' => $location['id'],
                            'name' => $location['name'],
                            'status' => $location['status'],
                            'distance' => $location['distance'],
                            'url' => !empty($locationUrl) ? $locationUrl : $location['url'],
                            'address' => $location['address'],
                        ];
                        
                        // Add session weeks if available
                        if (isset($location_weeks_map[$loc_id]) && isset($location_weeks_map[$loc_id][$course_type])) {
                            $location_data['sessionWeeks'] = $location_weeks_map[$loc_id][$course_type];
                        } else {
                            $location_data['sessionWeeks'] = [];
                        }
                        
                        // Add this location to the course's locations array
                        $course_rec['locations'][] = $location_data;
                    }
                }
            }
            
            // Filter out courses with no available locations
            $course_recs_with_locations = array_filter($course_recs_with_locations, function($course) {
                return !empty($course['locations']);
            });
            
            // Convert from associative to indexed array for cleaner JSON
            $course_recs_with_locations = array_values($course_recs_with_locations);
            
            // Sort courses by number of available locations (most locations first)
            usort($course_recs_with_locations, function($a, $b) {
                return count($b['locations']) - count($a['locations']);
            });
            
            // Return the restructured data
            return [
                'metadata' => [
                    'total_courses' => count($course_recs_with_locations),
                    'student_coordinates' => $nearby_data['student_coordinates'],
                    'student_age' => $student_age,
                    'from_fiscal_year' => $idta_recs['from_fiscal_year'] ?? ($idtc_recs['from_fiscal_year'] ?? null),
                    'to_fiscal_year' => $idta_recs['to_fiscal_year'] ?? ($idtc_recs['to_fiscal_year'] ?? null),
                    'recommendation_counts' => $metadata
                ],
                'last_purchase' => $last_purchase,
                'courses' => $course_recs_with_locations
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
function get_course_recommendations_between_fiscal_years($student_data, $from_fiscal_year, $to_fiscal_year, $division = null)
{
    global $wpdb;
    
    // Get student account number from the data
    $student_account_number = $student_data['studentAccountNumber'];
    if (!$student_account_number) {
        return [];
    }
    
    // Define fiscal year ranges
    $fiscal_years = [
        'fy25' => ['start' => '2024-11-01', 'end' => '2025-10-31'],
        'fy24' => ['start' => '2023-11-01', 'end' => '2024-10-31'],
        'fy23' => ['start' => '2022-11-01', 'end' => '2023-10-31']
    ];
    
    if (!isset($fiscal_years[$from_fiscal_year]) || !isset($fiscal_years[$to_fiscal_year])) {
        return [];
    }
    
    $from_dates = $fiscal_years[$from_fiscal_year];
    
    // Define division IDs based on division parameter
    $divisionIds = [];
    if ($division === 'idtc') {
        $divisionIds = [25]; // iD Tech Camps
    } else if ($division === 'idta') {
        $divisionIds = [22]; // iD Teen Academy
    } else if ($division === 'vtc') {
        $divisionIds = [42]; // Virtual Tech Camps
    } else if ($division === 'ota') {
        $divisionIds = [47]; // Online Teen Academy
    } else if ($division === 'opl') {
        $divisionIds = [41]; // Online Private Lessons
    } else if ($division === 'ipc') {
        $divisionIds = [22, 25]; // Both iDTA and iDTC for in-person courses
    } else {
        // If no division specified, include all divisions
        $divisionIds = [22, 25, 42, 47, 41];
    }
    
    $divisionString = implode(', ', $divisionIds);
    
    // Get the student's most recent purchase within the FROM fiscal year
    $latestPurchase = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.*, uf.studentDOB 
            FROM {$wpdb->prefix}idemailwiz_purchases p
            JOIN {$wpdb->prefix}idemailwiz_userfeed uf ON p.shoppingCartItems_studentAccountNumber = uf.studentAccountNumber
            WHERE p.shoppingCartItems_studentAccountNumber = %s 
            AND p.shoppingCartItems_divisionId IN ($divisionString)
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
        return [];
    }

    // Get the course details
    $course = get_course_details_by_id($latestPurchase['shoppingCartItems_id']);
    if (is_wp_error($course) || !isset($course->course_recs)) {
        return [];
    }

    // Calculate student age and age at last purchase using DOB from the joined query
    $studentDOB = $latestPurchase['studentDOB'];
    if (!$studentDOB) {
        return [];
    }

    $studentAge = calculate_student_age($studentDOB);
    $ageAtLastPurchase = calculate_age_at_purchase($studentDOB, $latestPurchase['purchaseDate']);

    if ($studentAge === false) {
        return [];
    }

    // Determine if student needs age-up recommendations
    $needsAgeUp = determine_age_up_need($studentAge, $ageAtLastPurchase, $course);

    // Get recommendations based on the student's current division or specified division
    $fromDivision = '';
    $toDivision = '';
    
    // Map the division ID to string representation
    if ($latestPurchase['shoppingCartItems_divisionId'] == 25) {
        $fromDivision = 'idtc';
    } else if ($latestPurchase['shoppingCartItems_divisionId'] == 22) {
        $fromDivision = 'idta';
    } else if ($latestPurchase['shoppingCartItems_divisionId'] == 42) {
        $fromDivision = 'vtc';
    } else if ($latestPurchase['shoppingCartItems_divisionId'] == 47) {
        $fromDivision = 'ota';
    } else if ($latestPurchase['shoppingCartItems_divisionId'] == 41) {
        $fromDivision = 'opl';
    }
    
    // Use the specified division if provided, otherwise use the from division
    $toDivision = $division ?? $fromDivision;

    // Get course recommendations
    $recommendations = get_course_recommendations($course, $toDivision, $needsAgeUp);

    if (empty($recommendations)) {
        return [];
    }

    return [
        'recs' => $recommendations,
        'total_count' => count($recommendations),
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
        'last_location' => process_preset_value('last_location', $feed_data),
        'location_with_courses' => process_preset_value('location_with_courses', $feed_data)
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
    if (!check_ajax_referer('wiz-endpoints', 'security', false)) {
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
    if (!check_ajax_referer('wiz-endpoints', 'security', false)) {
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

/**
 * Track request rate for REST API endpoints
 */
function idwiz_track_request_rate() {
    $transient_key = 'idwiz_api_request_times';
    $request_times = get_transient($transient_key);
    
    if (!is_array($request_times)) {
        $request_times = array();
    }
    
    // Remove timestamps older than 60 seconds
    $cutoff_time = time() - 60;
    $request_times = array_filter($request_times, function($timestamp) use ($cutoff_time) {
        return $timestamp >= $cutoff_time;
    });
    
    // Add current timestamp
    $request_times[] = time();
    
    // Store updated timestamps for 2 minutes (longer than our window to ensure data isn't lost)
    set_transient($transient_key, $request_times, 120);
    
    // Calculate requests per minute
    $requests_per_minute = count($request_times);
    
    // Add throttling when high concurrency is detected
    // Check how many requests happened in the last 5 seconds
    $recent_cutoff = time() - 5;
    $recent_requests = array_filter($request_times, function($timestamp) use ($recent_cutoff) {
        return $timestamp >= $recent_cutoff;
    });
    $recent_count = count($recent_requests);
    
    // If we have high recent concurrency, add a small delay
    if ($recent_count > 5) { 
        // Calculate adaptive delay based on concurrency
        $delay_ms = min(200, $recent_count * 10); // Max 200ms, scales with concurrency
        
        // Log the throttling
        error_log("ID Email Wiz REST API: Throttling with {$delay_ms}ms delay due to concurrency ({$recent_count} requests in 5s)");
        
        // Apply the delay
        usleep($delay_ms * 1000);
    }
    
    // Log rate information
    if ($requests_per_minute > 1) {  // Only log if there's more than one request
        error_log("ID Email Wiz REST API Rate: $requests_per_minute requests in the last minute");
        
        // Check if we're over typical WordPress REST API rate limits
        if ($requests_per_minute > 40) {
            error_log("ID Email Wiz REST API WARNING: High request rate detected ($requests_per_minute/min) - may hit WordPress rate limits");
        }
    }
    
    return $requests_per_minute;
}

/**
 * Process multiple preset values in an optimized batch
 * This reduces the number of database queries by combining related presets
 */
function batch_process_preset_values($presets_to_process, $student_data) {
    if (empty($presets_to_process)) {
        return [];
    }
    
    global $wpdb;
    $results = [];
    $student_account_number = $student_data['studentAccountNumber'];
    
    // Group related presets that can be processed together
    $location_presets = array_intersect(['last_location', 'nearby_locations'], $presets_to_process);
    $course_rec_presets = array_filter($presets_to_process, function($preset) {
        return strpos($preset, 'course_recs') !== false;
    });
    $purchase_presets = array_intersect(['most_recent_purchase'], $presets_to_process);
    
    // Get location data if needed (in a single query)
    if (!empty($location_presets)) {
        // Get student's last location with one optimized query
        $last_location_query = $wpdb->prepare(
            "SELECT p.shoppingCartItems_locationName, l.id, l.name, l.locationStatus, l.address, l.locationUrl 
            FROM {$wpdb->prefix}idemailwiz_purchases p
            LEFT JOIN {$wpdb->prefix}idemailwiz_locations l ON p.shoppingCartItems_locationName = l.name
            WHERE p.shoppingCartItems_studentAccountNumber = %s 
            AND p.shoppingCartItems_locationName IS NOT NULL 
            AND p.shoppingCartItems_locationName != ''
            AND l.locationStatus IN ('Open', 'Registration opens soon')
            AND l.id != 324
            ORDER BY p.purchaseDate DESC 
            LIMIT 1",
            $student_account_number
        );
        
        $location_data = $wpdb->get_row($last_location_query, ARRAY_A);
        
        if ($location_data) {
            // Process the last location preset if requested
            if (in_array('last_location', $presets_to_process)) {
                $address = $location_data['address'] ? unserialize($location_data['address']) : null;
                $url = !empty($location_data['locationUrl']) ? $location_data['locationUrl'] : null;
                
                $results['last_location'] = [
                    'id' => $location_data['id'],
                    'name' => $location_data['name'],
                    'status' => $location_data['locationStatus'],
                    'url' => $url,
                    'address' => $address
                ];
            }
            
            // Process nearby locations preset if requested
            if (in_array('nearby_locations', $presets_to_process)) {
                $results['nearby_locations'] = get_nearby_locations($student_data, 30);
            }
        } else {
            // No location data found
            if (in_array('last_location', $presets_to_process)) {
                $results['last_location'] = null;
            }
            if (in_array('nearby_locations', $presets_to_process)) {
                $results['nearby_locations'] = [];
            }
        }
    }
    
    // Get purchase data if needed
    if (!empty($purchase_presets)) {
        $purchase_query = $wpdb->prepare(
            "SELECT purchaseDate 
            FROM {$wpdb->prefix}idemailwiz_purchases 
            WHERE shoppingCartItems_studentAccountNumber = %s 
            ORDER BY purchaseDate DESC 
            LIMIT 1",
            $student_account_number
        );
        
        $recent_purchase = $wpdb->get_row($purchase_query);
        $results['most_recent_purchase'] = $recent_purchase ? $recent_purchase->purchaseDate : null;
    }
    
    // Process course recommendation presets
    if (!empty($course_rec_presets)) {
        // Get student age once for all course rec functions
        $student_age = get_student_age($student_data);
        
        // Calculate fiscal year information once
        $current_date = new DateTime();
        $year = intval($current_date->format('Y'));
        $month = intval($current_date->format('n'));
        $current_fy_year = ($month >= 11) ? $year + 1 : $year;
        $fiscal_year = 'fy' . substr($current_fy_year, -2);
        $from_fiscal_year = 'fy' . substr($current_fy_year - 1, -2);
        
        // Process each course recommendation type
        foreach ($course_rec_presets as $preset) {
            // Extract division from preset name (e.g., 'idtc_course_recs' -> 'idtc')
            $division = str_replace('_course_recs', '', $preset);
            
            // For the special case of nearby_locations_with_course_recs
            if ($preset === 'nearby_locations_with_course_recs') {
                $results[$preset] = process_preset_value($preset, $student_data);
                continue;
            }
            
            // Get recommendations for this division
            $recommendations = get_course_recommendations_between_fiscal_years(
                $student_data, 
                $from_fiscal_year,
                $fiscal_year,
                $division
            );
            
            // Add student age and age-up info if not already present
            if (!isset($recommendations['student_age'])) {
                $recommendations['student_age'] = $student_age;
            }
            
            if (!isset($recommendations['age_up'])) {
                $needs_age_up = false;
                if (($division === 'idta' || $division === 'ota') && $student_age < 13) {
                    $needs_age_up = true;
                } else if ($division === 'idtc' && $student_age >= 18) {
                    $needs_age_up = true;
                }
                $recommendations['age_up'] = $needs_age_up;
            }
            
            $results[$preset] = $recommendations;
        }
    }
    
    // Process remaining presets individually
    $remaining_presets = array_diff($presets_to_process, array_merge(
        $location_presets, 
        $course_rec_presets,
        $purchase_presets,
        array_keys($results)
    ));
    
    foreach ($remaining_presets as $preset) {
        $results[$preset] = process_preset_value($preset, $student_data);
    }
    
    return $results;
}

/**
 * Determine which presets are required for an endpoint based on its configuration
 *
 * @param array $endpoint_config The endpoint configuration
 * @return array Array of required preset keys
 */
function get_required_presets($endpoint_config) {
    $required_presets = [];
    
    // Check data mapping for preset references
    if (!empty($endpoint_config['data_mapping'])) {
        foreach ($endpoint_config['data_mapping'] as $mapping) {
            if (isset($mapping['type']) && $mapping['type'] === 'preset' && 
                isset($mapping['value']) && !empty($mapping['value'])) {
                $required_presets[] = $mapping['value'];
            }
        }
    }
    
    // If no explicit mapping, consider all compatible presets for the data source as required
    if (empty($required_presets)) {
        $base_data_source = $endpoint_config['base_data_source'] ?? 'user_feed';
        $required_presets = get_compatible_presets($base_data_source);
    }
    
    return array_unique($required_presets);
}

function idwiz_endpoint_handler($request) {
    // Track request start time
    $start_time = microtime(true);
    
    // Set time limit to ensure we respond within 10 seconds
    set_time_limit(10);
    
    // Add response headers for better client handling
    header('X-Accel-Buffering: no'); // Disable nginx buffering
    header('Content-Type: application/json');
    
    // Track and log request rate
    $current_rate = idwiz_track_request_rate();
    
    $route = $request->get_route();
    $endpoint = str_replace('/idemailwiz/v1', '', $route);
    $endpoint = trim($endpoint, '/');
    
    // Get parameters
    $params = $request->get_params();
    $account_number = isset($params['account_number']) ? sanitize_text_field($params['account_number']) : '';
    
    if (empty($account_number)) {
        return new WP_REST_Response(['error' => 'Account number is required'], 400);
    }
    
    // Check for cached response
    $cache_key = 'idwiz_endpoint_' . md5($endpoint . '_' . $account_number . '_' . serialize($params));
    $cached_response = wp_cache_get($cache_key);
    
    if ($cached_response !== false) {
        // Log cache hit
        error_log("ID Email Wiz REST API: Cache hit for account $account_number on endpoint $endpoint");
        return $cached_response;
    }

    // Get endpoint configuration from database
    $endpoint_config = idwiz_get_endpoint($endpoint);
    if (!$endpoint_config) {
        return new WP_REST_Response(['error' => 'Endpoint configuration not found'], 404);
    }

    global $wpdb;
    
    // Get base data source
    $base_data_source = $endpoint_config['base_data_source'] ?? 'user_feed';
    $feed_data = [];

    // Handle different data sources
    if ($base_data_source === 'user_profile') {
        // Get data from users table (parent/lead account info)
        $feed_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}idemailwiz_users WHERE accountNumber = %s OR userId = %s LIMIT 1",
                $account_number, $account_number
            ),
            ARRAY_A
        );

        if (!$feed_data) {
            return new WP_REST_Response(['error' => 'User not found in database'], 404);
        }
        
        // Check if leadLocationId is empty or missing for user_profile endpoints
        if (empty($feed_data['leadLocationId']) && in_array('location_with_courses', get_required_presets($endpoint_config))) {
            return new WP_REST_Response(['error' => 'User does not have a lead location assigned'], 400);
        }
        
    } else if ($base_data_source === 'user_feed') {
        // Get data from userfeed table (student info)
        $feed_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}idemailwiz_userfeed WHERE studentAccountNumber = %s LIMIT 1",
                $account_number
            ),
            ARRAY_A
        );

        if (!$feed_data) {
            return new WP_REST_Response(['error' => 'Student not found in feed'], 404);
        }
    } else {
        // Custom data source - just create an empty container
        $feed_data = ['accountNumber' => $account_number];
    }

    // Get all required presets at once
    $required_presets = get_required_presets($endpoint_config);
    
    // Process all presets in optimized batches
    try {
        $presets = batch_process_preset_values($required_presets, $feed_data);
        
        // Check for any missing or empty required presets
        foreach ($required_presets as $preset) {
            if (!isset($presets[$preset]) || $presets[$preset] === null || 
                (is_array($presets[$preset]) && empty($presets[$preset]))) {
                
                // Skip non-essential presets
                if ($preset !== 'most_recent_purchase' && 
                    $preset !== 'last_location' && 
                    $preset !== 'location_with_courses') {
                    continue;
                }
                
                // Specific error for location_with_courses preset
                if ($preset === 'location_with_courses') {
                    if (empty($feed_data['leadLocationId'])) {
                        return new WP_REST_Response([
                            'error' => 'Lead location ID is missing for this user',
                            'elapsed_time' => round((microtime(true) - $start_time) * 1000)
                        ], 400);
                    } else {
                        return new WP_REST_Response([
                            'error' => 'Location is not active or does not exist',
                            'elapsed_time' => round((microtime(true) - $start_time) * 1000)
                        ], 400);
                    }
                }
                
                return new WP_REST_Response([
                    'error' => "Required preset '$preset' is null or empty",
                    'elapsed_time' => round((microtime(true) - $start_time) * 1000)
                ], 404);
            }
        }
    } catch (Exception $e) {
        return new WP_REST_Response([
            'error' => "Error processing presets: " . $e->getMessage(),
            'elapsed_time' => round((microtime(true) - $start_time) * 1000)
        ], 500);
    }

    // Build response data
    $response_data = [];
    if (!empty($endpoint_config['data_mapping'])) {
        foreach ($endpoint_config['data_mapping'] as $key => $mapping) {
            if ($mapping['type'] === 'static') {
                $response_data[$key] = $mapping['value'];
            } else if ($mapping['type'] === 'preset' && isset($presets[$mapping['value']])) {
                $response_data[$key] = $presets[$mapping['value']];
            }
        }
    } else {
        $response_data = $feed_data;
        if (!empty($presets)) {
            $response_data['_presets'] = $presets;
        }
    }

    // Calculate execution time and return response
    $execution_time = round((microtime(true) - $start_time) * 1000);
    $data_size = strlen(json_encode($response_data));
    
    error_log("ID Email Wiz REST API Success: Account Number=$account_number, Response Size=$data_size bytes, Execution Time={$execution_time}ms");

    $response = new WP_REST_Response([
        'endpoint' => $endpoint,
        'data' => $response_data
    ], 200);
    
    // Cache this response for 5 minutes
    wp_cache_set($cache_key, $response, '', 300);
    
    return $response;
}

// Add this new function to help with request batching
function process_batch_requests($requests, $max_concurrent = 5) {
    $results = [];
    $batch = [];
    $count = 0;
    
    foreach ($requests as $request) {
        $batch[] = $request;
        $count++;
        
        if ($count >= $max_concurrent) {
            // Process batch
            foreach ($batch as $req) {
                $result = idwiz_endpoint_handler($req);
                $results[] = $result;
            }
            
            // Clear batch and add small delay
            $batch = [];
            $count = 0;
            usleep(100000); // 100ms delay between batches
        }
    }
    
    // Process remaining requests
    if (!empty($batch)) {
        foreach ($batch as $req) {
            $result = idwiz_endpoint_handler($req);
            $results[] = $result;
        }
    }
    
    return $results;
}

/**
 * Detects and manages concurrent requests from the same IP
 * This helps prevent resource contention when external services hit the API
 */
function idwiz_detect_request_bursts() {
    static $ip_request_count = array();
    
    // Get client IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Initialize or increment counter
    if (!isset($ip_request_count[$ip])) {
        $ip_request_count[$ip] = 1;
    } else {
        $ip_request_count[$ip]++;
    }
    
    // If we detect a burst of requests from the same IP (likely Iterable)
    // add a small staggered delay to prevent resource contention
    if ($ip_request_count[$ip] > 3) {
        $delay_ms = min(300, $ip_request_count[$ip] * 15); // Scale up to 300ms max
        usleep($delay_ms * 1000);
        
        // Log this for debugging
        error_log("ID Email Wiz REST API: Burst detection - Request {$ip_request_count[$ip]} from IP {$ip}, adding {$delay_ms}ms delay");
        
        // Reset counter after a while to not penalize legitimate traffic
        if ($ip_request_count[$ip] > 20) {
            $ip_request_count[$ip] = 10;
        }
    }
}

// Hook the burst detection to WordPress init action
add_action('init', 'idwiz_detect_request_bursts', 1);

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
        wp_send_json_error('No account number provided');
        return;
    }
    
    error_log('Preview request for account number: ' . $account_number);
    
    // Check for base data source
    $base_data_source = isset($_POST['base_data_source']) ? sanitize_text_field($_POST['base_data_source']) : 'user_feed';
    error_log('Using base data source: ' . $base_data_source);

    global $wpdb;
    
    try {
        // Get user data from the appropriate database table based on data source
        if ($base_data_source === 'user_profile') {
            $feed_data = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}idemailwiz_users WHERE accountNumber = %s OR userId = %s LIMIT 1",
                    $account_number, $account_number
                ),
                ARRAY_A
            );
            
            if (!$feed_data) {
                wp_send_json_error('User not found in database');
                return;
            }
        } else {
            // Default to user feed
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
        }

        // Process presets
        try {
            $presets = [];
            
            // Get list of compatible presets for this data source
            $compatible_presets = get_compatible_presets($base_data_source);
            error_log('Compatible presets for ' . $base_data_source . ': ' . implode(', ', $compatible_presets));
            
            // Process each compatible preset
            foreach ($compatible_presets as $preset) {
                try {
                    $result = process_preset_value($preset, $feed_data);
                    if ($result !== null) {
                        $presets[$preset] = $result;
                    }
                } catch (Exception $e) {
                    error_log('Error processing preset ' . $preset . ': ' . $e->getMessage());
                    // Skip this preset but continue with others
                }
            }

            // Add presets to the response
            $feed_data['_presets'] = $presets;

            wp_send_json_success($feed_data);
            
        } catch (Exception $e) {
            error_log('Error processing presets: ' . $e->getMessage());
            wp_send_json_error('Error processing presets: ' . $e->getMessage());
            return;
        }
        
    } catch (Exception $e) {
        error_log('Error retrieving user data: ' . $e->getMessage());
        wp_send_json_error('Error retrieving user data: ' . $e->getMessage());
        return;
    }
}

/**
 * Gets course recommendations for a specific division
 */
function get_division_course_recommendations($student_data, $division)
{
    // Get student age
    $student_age = get_student_age($student_data);
    
    // Calculate fiscal year information consistently
    $current_date = new DateTime();
    $year = intval($current_date->format('Y'));
    $month = intval($current_date->format('n'));
    
    // For any date from November through October, the fiscal year is the next calendar year
    // So November 2024 - October 2025 is FY2025
    $current_fy_year = ($month >= 11) ? $year + 1 : $year;
    $fiscal_year = 'fy' . substr($current_fy_year, -2);
    $from_fiscal_year = 'fy' . substr($current_fy_year - 1, -2);
    
    // Check if student should receive age-up recommendation
    $needs_age_up = false;
    
    // Division-specific logic
    if ($division === 'ipc') {
        // For in-person courses, first check for direct IPC recommendations
        if ($student_age >= 13) {
            $ipc_recs = get_course_recommendations_between_fiscal_years($student_data, $from_fiscal_year, $fiscal_year, 'ipc');
            
            if (!empty($ipc_recs['recs'])) {
                return $ipc_recs;
            }
            
            // Try iDTA recommendations
            $idta_recs = get_course_recommendations_between_fiscal_years($student_data, $from_fiscal_year, $fiscal_year, 'idta');
            
            if (!empty($idta_recs['recs'])) {
                return $idta_recs;
            }
            
            // Also try iDTC recommendations regardless of previous purchases
            $idtc_recs = get_course_recommendations_between_fiscal_years($student_data, $from_fiscal_year, $fiscal_year, 'idtc');
            
            if (!empty($idtc_recs['recs'])) {
                return $idtc_recs;
            }
        }
        // For students under 13, check IPC recommendations (filter out any with min age 13+)
        else {
            $ipc_recs = get_course_recommendations_between_fiscal_years($student_data, $from_fiscal_year, $fiscal_year, 'ipc');
            
            if (!empty($ipc_recs['recs'])) {
                // Filter out any recommendations with minAge >= 13
                $filtered_recs = array_filter($ipc_recs['recs'], function($rec) {
                    return $rec['minAge'] < 13;
                });
                
                if (!empty($filtered_recs)) {
                    $ipc_recs['recs'] = array_values($filtered_recs);
                    return $ipc_recs;
                }
            }
            
            // Default to iDTC recommendations for under 13
            $idtc_recs = get_course_recommendations_between_fiscal_years($student_data, $from_fiscal_year, $fiscal_year, 'idtc');
            
            if (!empty($idtc_recs['recs'])) {
                return $idtc_recs;
            }
        }
        
        // If we get here, no recommendations were found
        return [
            'from_fiscal_year' => $from_fiscal_year,
            'to_fiscal_year' => $fiscal_year,
            'student_age' => $student_age,
            'age_up' => $needs_age_up,
            'recs' => []
        ];
    }
    // For division-specific recommendations (idta, idtc, etc.)
    else {
        // Division-specific age checks
        if (($division === 'idta' || $division === 'ota') && $student_age < 13) {
            $needs_age_up = true;
        } else if ($division === 'idtc' && $student_age >= 18) {
            $needs_age_up = true;
        }
        
        $recommendations = get_course_recommendations_between_fiscal_years($student_data, $from_fiscal_year, $fiscal_year, $division);
        
        // Add age-up flag
        $recommendations['age_up'] = $needs_age_up;
        $recommendations['student_age'] = $student_age;
        
        return $recommendations;
    }
}

// Add an AJAX endpoint to get available presets
add_action('wp_ajax_idwiz_get_available_presets', 'idwiz_get_available_presets_callback');

function idwiz_get_available_presets_callback() {
    if (!check_ajax_referer('wiz-endpoints', 'security', false)) {
        wp_send_json_error('Nonce check failed');
        return;
    }
    
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

/**
 * Gets student age from the student data array
 */
function get_student_age($student_data) {
    // Check if DOB exists in the data
    if (!isset($student_data['studentDOB']) || empty($student_data['studentDOB'])) {
        return 0;
    }
    
    // Use the existing calculate_student_age function to get age from DOB
    $age = calculate_student_age($student_data['studentDOB']);
    
    if ($age === false) {
        return 0;
    }
    
    return $age;
}

/**
 * Ensure necessary database indexes exist for API endpoints
 * This helps speed up commonly used queries
 */
function idwiz_ensure_api_indexes() {
    global $wpdb;
    
    // Only run this periodically, using a transient to limit frequency
    $indexes_checked = get_transient('idwiz_api_indexes_checked');
    if ($indexes_checked) {
        return;
    }
    
    // List of indexes to check and create if missing
    $indexes = [
        // Userfeed indexes
        [
            'table' => $wpdb->prefix . 'idemailwiz_userfeed',
            'name' => 'idx_student_account_number',
            'column' => 'studentAccountNumber',
            'check_query' => "SHOW INDEX FROM {$wpdb->prefix}idemailwiz_userfeed WHERE Key_name = 'idx_student_account_number'",
            'create_query' => "ALTER TABLE {$wpdb->prefix}idemailwiz_userfeed ADD INDEX idx_student_account_number (studentAccountNumber)"
        ],
        
        // Purchases indexes
        [
            'table' => $wpdb->prefix . 'idemailwiz_purchases',
            'name' => 'idx_student_purchase_date',
            'column' => 'shoppingCartItems_studentAccountNumber, purchaseDate',
            'check_query' => "SHOW INDEX FROM {$wpdb->prefix}idemailwiz_purchases WHERE Key_name = 'idx_student_purchase_date'",
            'create_query' => "ALTER TABLE {$wpdb->prefix}idemailwiz_purchases ADD INDEX idx_student_purchase_date (shoppingCartItems_studentAccountNumber, purchaseDate)"
        ],
        [
            'table' => $wpdb->prefix . 'idemailwiz_purchases',
            'name' => 'idx_location_student',
            'column' => 'shoppingCartItems_locationName, shoppingCartItems_studentAccountNumber',
            'check_query' => "SHOW INDEX FROM {$wpdb->prefix}idemailwiz_purchases WHERE Key_name = 'idx_location_student'",
            'create_query' => "ALTER TABLE {$wpdb->prefix}idemailwiz_purchases ADD INDEX idx_location_student (shoppingCartItems_locationName, shoppingCartItems_studentAccountNumber)"
        ],
        [
            'table' => $wpdb->prefix . 'idemailwiz_purchases',
            'name' => 'idx_division_student',
            'column' => 'shoppingCartItems_divisionId, shoppingCartItems_studentAccountNumber',
            'check_query' => "SHOW INDEX FROM {$wpdb->prefix}idemailwiz_purchases WHERE Key_name = 'idx_division_student'",
            'create_query' => "ALTER TABLE {$wpdb->prefix}idemailwiz_purchases ADD INDEX idx_division_student (shoppingCartItems_divisionId, shoppingCartItems_studentAccountNumber)"
        ],
        
        // Locations index
        [
            'table' => $wpdb->prefix . 'idemailwiz_locations',
            'name' => 'idx_location_name',
            'column' => 'name',
            'check_query' => "SHOW INDEX FROM {$wpdb->prefix}idemailwiz_locations WHERE Key_name = 'idx_location_name'",
            'create_query' => "ALTER TABLE {$wpdb->prefix}idemailwiz_locations ADD INDEX idx_location_name (name)"
        ]
    ];
    
    foreach ($indexes as $index) {
        // Check if index exists
        $exists = $wpdb->get_results($index['check_query']);
        
        if (empty($exists)) {
            // Index doesn't exist, create it
            $wpdb->query($index['create_query']);
            error_log("Created database index: {$index['name']} on {$index['table']} ({$index['column']})");
        }
    }
    
    // Set transient to prevent frequent checking
    set_transient('idwiz_api_indexes_checked', true, DAY_IN_SECONDS);
}

// Add index checking with low priority (runs after tables are created)
add_action('init', 'idwiz_ensure_api_indexes', 999);

/**
 * Disables unnecessary WordPress operations during API requests
 * This reduces memory usage and processing time
 */
function idwiz_optimize_wordpress_for_api() {
    // Only apply these optimizations to our REST API endpoints
    if (!defined('REST_REQUEST') || !REST_REQUEST || strpos($_SERVER['REQUEST_URI'], '/idemailwiz/v1/') === false) {
        return;
    }
    
    // Disable heartbeat API
    wp_deregister_script('heartbeat');
    
    // Disable post revisions for this request
    remove_action('pre_post_update', 'wp_save_post_revision');
    
    // Disable pingbacks
    add_filter('xmlrpc_enabled', '__return_false');
    
    // Disable emoji processing
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    
    // Disable embeds
    remove_action('rest_api_init', 'wp_oembed_register_route');
    
    // Disable wp-cron for this request (will still run on normal page loads)
    if (!defined('DOING_CRON')) {
        define('DOING_CRON', true);
    }
    
    // Set up high limits for database operations
    add_filter('pre_option_thread_comments_depth', function() { return 0; });
    
    // Increase memory limits if needed
    $current_limit = ini_get('memory_limit');
    $current_limit_int = intval($current_limit);
    if ($current_limit_int < 256 && $current_limit_int > 0) {
        ini_set('memory_limit', '256M');
    }
}

// Run this early to disable unnecessary features
add_action('init', 'idwiz_optimize_wordpress_for_api', 1);

/**
 * Detects and manages concurrent requests from the same IP
 * This helps prevent resource contention when external services hit the API
 */

/**
 * Gets location data and available courses for a specific location ID
 * Uses the leadLocationId field from parent account data
 */
function get_location_with_courses($user_data) {
    global $wpdb;
    
    // Debug output
    error_log('Location data function called with data type: ' . (isset($user_data['studentAccountNumber']) ? 'student' : 'parent'));
    
    $location_id = null;
    
    // If this is student data, we need to get the parent account to find leadLocationId
    if (isset($user_data['studentAccountNumber']) && !isset($user_data['leadLocationId'])) {
        error_log('Student data detected, looking for parent account');
        
        // Get parent account info using accountNumber from student data
        if (!empty($user_data['accountNumber'])) {
            $parent = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}idemailwiz_users WHERE accountNumber = %s LIMIT 1",
                    $user_data['accountNumber']
                ),
                ARRAY_A
            );
            
            if ($parent && isset($parent['leadLocationId'])) {
                $location_id = intval($parent['leadLocationId']);
                error_log('Found parent account with leadLocationId: ' . $location_id);
            } else {
                error_log('Parent account not found or missing leadLocationId');
            }
        } else {
            error_log('No accountNumber found in student data to look up parent');
        }
    } 
    // If this is parent data, get leadLocationId directly
    else if (isset($user_data['leadLocationId'])) {
        $location_id = intval($user_data['leadLocationId']);
        error_log('Using leadLocationId directly from parent data: ' . $location_id);
    }
    
    // Check if we have a valid location ID
    if (empty($location_id) || $location_id <= 0) {
        error_log('No valid leadLocationId found: ' . ($location_id ?? 'null'));
        return null;
    }
    
    error_log('Looking up location ID: ' . $location_id);
    
    // First check if the location exists at all, regardless of status
    $location_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}idemailwiz_locations WHERE id = %d",
            $location_id
        )
    );
    
    if (!$location_exists) {
        error_log("Location ID {$location_id} does not exist in the database");
        return null;
    }
    
    // Get location details from the database
    try {
        $query = $wpdb->prepare(
            "SELECT id, name, abbreviation, locationStatus, address, locationUrl, courses, sessionWeeks, divisions 
             FROM {$wpdb->prefix}idemailwiz_locations 
             WHERE id = %d",
            $location_id
        );
        
        error_log('Location query: ' . $query);
        $location = $wpdb->get_row($query, ARRAY_A);
        
        // Check if we found the location but it's not active
        if ($location && !in_array($location['locationStatus'], ['Open', 'Registration opens soon'])) {
            error_log("Location found but status '{$location['locationStatus']}' is not active");
            // Return null for inactive locations - changed from previous version
            return null;
        }
        
        if (!$location) {
            error_log('No matching location found for ID: ' . $location_id);
            return null;
        }
        
        // Return null if this is Online Campus (ID 324)
        if ($location['id'] == 324) {
            error_log('Online Campus detected, returning null');
            return null;
        }
        
        // Safer unserialize function that returns empty array/null on failure
        $safe_unserialize = function($data, $default = null) {
            if (empty($data)) return $default;
            
            // Check if data is already unserialized
            if (!is_string($data) || !preg_match('/^[aOs]:[0-9]+:/', $data)) {
                return $data;
            }
            
            $result = @unserialize($data);
            return ($result !== false) ? $result : $default;
        };
        
        // Unserialize data with safer approach
        $address = $safe_unserialize($location['address'], null);
        $courses_ids = $safe_unserialize($location['courses'], []);
        $session_weeks = $safe_unserialize($location['sessionWeeks'], null);
        $divisions = $safe_unserialize($location['divisions'], []);
        
        // Log what we found
        error_log('Unserialized data counts: ' . 
                 'address=' . (is_array($address) ? count($address) : 'null') . ', ' . 
                 'courses=' . count($courses_ids) . ', ' . 
                 'divisions=' . count($divisions));
        
        // Get course details for all courses at this location
        $courses_data = [];
        if (!empty($courses_ids)) {
            try {
                // Make sure all course IDs are integers
                $courses_ids = array_map('intval', $courses_ids);
                $courses_ids = array_filter($courses_ids, function($id) { return $id > 0; });
                
                if (empty($courses_ids)) {
                    error_log('No valid course IDs found after filtering');
                } else {
                    $placeholders = implode(',', array_fill(0, count($courses_ids), '%d'));
                    $courses_query = $wpdb->prepare(
                        "SELECT id, title, abbreviation, division_id, minAge, maxAge, fiscal_years, courseUrl, wizStatus
                         FROM {$wpdb->prefix}idemailwiz_courses 
                         WHERE id IN ($placeholders)
                         AND wizStatus = 'Active'",  // Only active courses
                        $courses_ids
                    );
                    
                    error_log('Courses query: ' . $courses_query);
                    $courses_data = $wpdb->get_results($courses_query, ARRAY_A);
                    
                    if (empty($courses_data)) {
                        error_log('No active course data found for location');
                    } else {
                        error_log('Found ' . count($courses_data) . ' active courses for location');
                    }
                }
            } catch (Exception $e) {
                error_log('Error in courses query: ' . $e->getMessage());
                $courses_data = [];
            }
        }
        
        // Prepare the result
        $result = [
            'id' => $location['id'],
            'name' => $location['name'],
            'abbreviation' => $location['abbreviation'],
            'status' => $location['locationStatus'],
            'url' => !empty($location['locationUrl']) ? $location['locationUrl'] : null,
            'address' => $address,
            'divisions' => $divisions,
            'session_weeks' => $session_weeks,
            'courses' => $courses_data
        ];
        
        error_log('Successfully built location data');
        return $result;
        
    } catch (Exception $e) {
        error_log('Error in location data function: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get presets compatible with the given data source
 * 
 * @param string $data_source The data source ('user_feed' or 'user_profile')
 * @return array Array of preset keys that are compatible with the data source
 */
function get_compatible_presets($data_source = 'user_feed') {
    // Presets that work with student data (user_feed)
    $student_presets = [
        'most_recent_purchase',
        'last_location',
        'nearby_locations',
        'nearby_locations_with_course_recs',
        'ipc_course_recs',
        'idtc_course_recs',
        'idta_course_recs',
        'vtc_course_recs',
        'ota_course_recs',
        'opl_course_recs'
    ];
    
    // Presets that work with parent/user data (user_profile)
    $parent_presets = [
        'location_with_courses'
    ];
    
    // Return appropriate presets based on data source
    if ($data_source === 'user_profile') {
        return $parent_presets;
    } else {
        return $student_presets;
    }
}

/**
 * Gets recent parent/user accounts for testing user profile-based endpoints
 * 
 * @return array Array of parent account data
 */
function idwiz_get_parent_accounts() {
    global $wpdb;
    
    // Get recently active parent accounts
    $users = $wpdb->get_results(
        "SELECT DISTINCT u.accountNumber, u.userId, u.postalCode, p.purchaseDate  
         FROM {$wpdb->prefix}idemailwiz_users u
         LEFT JOIN {$wpdb->prefix}idemailwiz_purchases p ON u.accountNumber = p.accountNumber
         WHERE u.accountNumber IS NOT NULL 
         AND u.accountNumber != ''
         GROUP BY u.accountNumber
         ORDER BY p.purchaseDate DESC
         LIMIT 100",
        ARRAY_A
    );
    
    return $users;
}

/**
 * Generate HTML options for parent accounts
 * 
 * @param array $users Array of user data
 * @return string HTML options for select element
 */
function generate_parent_options_html($users) {
    $options = '<option value="">Select a parent account</option>';
    
    if (!empty($users)) {
        foreach ($users as $user) {
            if (empty($user['accountNumber'])) continue;
            
            $label = $user['accountNumber'];
            if (!empty($user['postalCode'])) {
                $label .= ' (' . $user['postalCode'] . ')';
            }
            
            $options .= '<option value="' . esc_attr($user['accountNumber']) . '">' . esc_html($label) . '</option>';
        }
    }
    
    return $options;
}

add_action('wp_ajax_idwiz_get_test_accounts', 'idwiz_get_test_accounts_callback');

/**
 * AJAX handler to get test account options for endpoints UI
 */
function idwiz_get_test_accounts_callback() {
    if (!check_ajax_referer('wiz-endpoints', 'security', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'user_feed';
    
    if ($type === 'user_profile') {
        // Get parent accounts for user profile data source
        $users = idwiz_get_parent_accounts();
        $options = generate_parent_options_html($users);
    } else {
        // Default to student accounts for user feed
        $users = idwiz_get_previous_year_users();
        $options = generate_user_options_html($users, true);
    }
    
    wp_send_json_success($options);
}

