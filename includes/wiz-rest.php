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
                        'courseUrl' => $recCourse->courseUrl ?? '',
                        'courseDesc' => $recCourse->courseDesc ?? null // Add courseDesc
                    ];
                }
            }
        }
        
        // If no explicit IPC mappings, try to include both iDTA and iDTC recommendations
        if (empty($recommendations)) {
            // Add iDTA recommendations
            if (isset($courseRecs['idta']) && is_array($courseRecs['idta']) && !empty($courseRecs['idta'])) {
                foreach ($courseRecs['idta'] as $recCourseId) {
                    $recCourse = get_course_details_by_id($recCourseId);
                    if (!is_wp_error($recCourse)) {
                        $recommendations[] = [
                            'id' => $recCourse->id,
                            'title' => $recCourse->title,
                            'abbreviation' => $recCourse->abbreviation,
                            'minAge' => $recCourse->minAge,
                            'maxAge' => $recCourse->maxAge,
                            'courseUrl' => $recCourse->courseUrl ?? '',
                            'courseDesc' => $recCourse->courseDesc ?? null
                        ];
                    }
                }
            }
            
            // Add iDTC recommendations
            if (isset($courseRecs['idtc']) && is_array($courseRecs['idtc']) && !empty($courseRecs['idtc'])) {
                foreach ($courseRecs['idtc'] as $recCourseId) {
                    $recCourse = get_course_details_by_id($recCourseId);
                    if (!is_wp_error($recCourse)) {
                        $recommendations[] = [
                            'id' => $recCourse->id,
                            'title' => $recCourse->title,
                            'abbreviation' => $recCourse->abbreviation,
                            'minAge' => $recCourse->minAge,
                            'maxAge' => $recCourse->maxAge,
                            'courseUrl' => $recCourse->courseUrl ?? '',
                            'courseDesc' => $recCourse->courseDesc ?? null
                        ];
                    }
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
                        'courseUrl' => $recCourse->courseUrl ?? '',
                        'courseDesc' => $recCourse->courseDesc ?? null // Add courseDesc
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
                    'courseUrl' => $recCourse->courseUrl ?? '',
                    'courseDesc' => $recCourse->courseDesc ?? null // Add courseDesc
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
    
    // Try to get student account number from different possible fields
    $student_account_number = null;
    if (isset($student_data['studentAccountNumber'])) {
        $student_account_number = $student_data['studentAccountNumber'];
    } elseif (isset($student_data['StudentAccountNumber'])) {
        $student_account_number = $student_data['StudentAccountNumber'];
    } elseif (isset($student_data['accountNumber'])) {
        $student_account_number = $student_data['accountNumber'];
    }
    
    if (empty($student_account_number)) {
        return null;
    }
    
    // Get the student's most recent purchase with a location
    $purchase = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT shoppingCartItems_locationName, purchaseDate 
            FROM {$wpdb->prefix}idemailwiz_purchases 
            WHERE shoppingCartItems_studentAccountNumber = %s 
            AND shoppingCartItems_locationName IS NOT NULL 
            AND shoppingCartItems_locationName != ''
            AND shoppingCartItems_divisionId IN (22, 25) -- Only consider iDTA (22) and iDTC (25) divisions
            ORDER BY purchaseDate DESC 
            LIMIT 1",
            $student_account_number
        )
    );

    if (!$purchase || !$purchase->shoppingCartItems_locationName) {
        return null;
    }

    // Get location details from locations table
    $location = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, name, locationStatus, address, locationUrl, locationDesc, overnightOffered 
            FROM {$wpdb->prefix}idemailwiz_locations 
            WHERE name = %s 
            AND locationStatus IN ('Open', 'Registration opens soon')",
            $purchase->shoppingCartItems_locationName // Use location name from purchase directly
        )
    );

    if (!$location) {
        return null;
    }

    // Unserialize address if it exists
    $address = $location->address ? unserialize($location->address) : null;

    // Use locationUrl from database if available, otherwise fallback to generated URL
    $url = !empty($location->locationUrl) ? $location->locationUrl : null;

    // Always include all properties to match preview
    return [
        'id' => $location->id,
        'name' => $location->name,
        'status' => $location->locationStatus,
        'url' => $url,
        'address' => $address,
        'locationDesc' => $location->locationDesc ?? null,
        'overnightOffered' => $location->overnightOffered ?? 'No'
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
        } else {
            // Address found but no coordinates, proceed to check student_data
        }
    } else {
        // No last location found, proceed to check student_data
    }
    
    // If no location found from last purchase, check if coordinates provided in student data
    if (!$student_location) {
        if (!empty($student_data['latitude']) && !empty($student_data['longitude'])) {
            $student_location = [
                'latitude' => $student_data['latitude'],
                'longitude' => $student_data['longitude']
            ];
        } else {
            // No coordinates found in student_data either
        }
    }
    
    // If we still don't have location data, return empty array
    if (!$student_location) {
        return [];
    }
    
    // Get all active locations that host iDTC or iDTA, including their courses list and session weeks
    $locations_query = $wpdb->prepare(
        "SELECT id, name, locationStatus, address, addressArea, locationUrl, courses, sessionWeeks, locationDesc, overnightOffered
        FROM {$wpdb->prefix}idemailwiz_locations
        WHERE locationStatus IN ('Open', 'Registration opens soon')
        AND addressArea IS NOT NULL AND addressArea != ''
        AND divisions IS NOT NULL
        AND id != 324 -- Exclude Online Campus
        AND (divisions LIKE %s OR divisions LIKE %s)", // Check for iDTA or iDTC in divisions
        '%i:22;%', // Check for integer 22 (iDTA) in serialized array
        '%i:25;%'  // Check for integer 25 (iDTC) in serialized array
    );
    
    $locations = $wpdb->get_results($locations_query, ARRAY_A);
    
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
            $url = !empty($location['locationUrl']) ? $location['locationUrl'] : null;
            
            // Safer unserialize for courses
            $safe_unserialize = function($data, $default = []) {
                if (empty($data)) return $default;
                if (!is_string($data) || !preg_match('/^[aOs]:[0-9]+:/', $data)) return $data;
                $result = @unserialize($data);
                return ($result !== false) ? $result : $default;
            };
            $location_courses = $safe_unserialize($location['courses']);
            $location_session_weeks = $safe_unserialize($location['sessionWeeks'], null); // Unserialize session weeks
            
            $nearby_locations[] = [
                'id' => $location['id'],
                'name' => $location['name'],
                'status' => $location['locationStatus'],
                'url' => $url,
                'address' => $address,
                'distance' => round($distance, 1), // Round to 1 decimal place
                'courses' => $location_courses, // Include the unserialized courses list
                'sessionWeeks' => $location_session_weeks, // Include the unserialized session weeks
                'locationDesc' => $location['locationDesc'] ?? null,
                'overnightOffered' => $location['overnightOffered'] ?? 'No'
            ];
        }
    }
    
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
            'description' => 'Returns location data and available courses. For students, prioritizes their most recent camp location; falls back to the parent account\'s leadLocationId if no previous location found. For parent accounts, uses the leadLocationId directly. Includes location details, address, and courses information.'
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
            'description' => 'Returns personalized course recommendations with nearby locations nested within each course. Each course includes available locations, session weeks, and real-time availability data with capacity information.',
            'group' => 'Course & Location Data'
        ],
        'current_year_continuity_recs' => [
            'name' => 'Current Year Continuity Recommendations',
            'group' => 'Course Continuity',
            'description' => 'For current clients who bought camp in current fiscal year. Gets course recommendations using FY24 mapping assumptions for their most recent course, includes current course continuation and recommendations with capacity data for Iterable dynamic content.'
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
            
            // Get nearby locations (which now include course lists)
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
            $location_courses_query = $wpdb->prepare( // Renamed for clarity
                "SELECT id, courses, sessionWeeks, locationUrl, overnightOffered
                 FROM {$wpdb->prefix}idemailwiz_locations 
                 WHERE id IN (" . implode(',', array_fill(0, count($location_ids), '%d')) . ")
                 AND id != 324", // Extra safeguard to exclude Online Campus
                $location_ids
            );
            $location_courses_results = $wpdb->get_results($location_courses_query, ARRAY_A); // Renamed for clarity
            
            // Create a map of location ID to courses and sessionWeeks
            $location_courses_map = [];
            $location_weeks_map = [];
            
            // Get student age
            $student_age = get_student_age($student_data);
            
            // Get course recommendations based on the student's most recent *in-person* purchase
            $ipc_recommendations = get_division_course_recommendations($student_data, 'ipc');
            
            // Check if age-up is needed
            $needs_age_up = $ipc_recommendations['age_up'] ?? false;
            
            // Use the IPC recommendations and last purchase
            $all_recommendations = $ipc_recommendations['recs'] ?? [];
            $last_purchase = $ipc_recommendations['last_purchase'] ?? null;

            $metadata = [
                'student_age' => $student_age,
                'ipc_count' => count($all_recommendations),
                'age_up' => $needs_age_up // Include age_up flag in metadata
            ];
            
            // Check if we have any recommendations
            if (empty($all_recommendations)) {
                return null;
            }
            
            // Get the last course details to check age range
            $last_course_min_age = null;
            $last_course_max_age = null;
            
            if ($last_purchase && isset($last_purchase['age_range'])) {
                $age_range_parts = explode('-', $last_purchase['age_range']);
                if (count($age_range_parts) == 2) {
                    $last_course_min_age = intval($age_range_parts[0]);
                    $last_course_max_age = intval($age_range_parts[1]);
                }
            }
            
            // If student is 10+ but last course was for younger students, we should use age-up recommendations
            if ($student_age >= 10 && $last_course_max_age && $last_course_max_age <= 9) {
                $needs_age_up = true;
                $metadata['age_up'] = true;
                
                // Let's try to get idtc_ageup recommendations specifically
                $idtc_ageup_recs = get_division_course_recommendations($student_data, 'idtc');
                if (!empty($idtc_ageup_recs['recs'])) {
                    // Filter to include only courses with appropriate age ranges
                    $filtered_recs = array_filter($idtc_ageup_recs['recs'], function($rec) use ($student_age) {
                        return $rec['minAge'] <= $student_age && $rec['maxAge'] >= $student_age;
                    });
                    
                    if (!empty($filtered_recs)) {
                        $all_recommendations = array_values($filtered_recs);
                        $metadata['ipc_count'] = count($all_recommendations);
                    }
                }
            }
            // Check for 13-year-olds who need to transition from iDTC to iDTA
            else if ($student_age >= 13 && $last_course_max_age && $last_course_max_age < 13) {
                $needs_age_up = true;
                $metadata['age_up'] = true;
                
                // For 13-year-olds, we should consider both iDTC and iDTA options
                $combined_recs = [];
                
                // First try to get age-appropriate iDTC recommendations
                $idtc_recs = get_division_course_recommendations($student_data, 'idtc');
                if (!empty($idtc_recs['recs'])) {
                    $filtered_idtc = array_filter($idtc_recs['recs'], function($rec) use ($student_age) {
                        return $rec['minAge'] <= $student_age && $rec['maxAge'] >= $student_age;
                    });
                    
                    if (!empty($filtered_idtc)) {
                        $combined_recs = array_merge($combined_recs, array_values($filtered_idtc));
                    }
                }
                
                // Then try to get iDTA recommendations
                $idta_recs = get_division_course_recommendations($student_data, 'idta');
                if (!empty($idta_recs['recs'])) {
                    $filtered_idta = array_filter($idta_recs['recs'], function($rec) use ($student_age) {
                        return $rec['minAge'] <= $student_age && $rec['maxAge'] >= $student_age;
                    });
                    
                    if (!empty($filtered_idta)) {
                        $combined_recs = array_merge($combined_recs, array_values($filtered_idta));
                    }
                }
                
                // If we found any suitable recommendations from either division, use them
                if (!empty($combined_recs)) {
                    $all_recommendations = $combined_recs;
                    $metadata['ipc_count'] = count($all_recommendations);
                }
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
            
            $matches_found = 0; // Counter for successful matches
            
            // Now populate each course with its available locations
            foreach ($nearby_data['locations'] as $location) {
                $loc_id = $location['id'];
                $location_course_list = $location['courses'] ?? []; // Get course list directly from nearby_data
                
                // Skip locations without course data
                if (empty($location_course_list)) {
                    continue;
                }
                
                // For each recommended course, check if it's available at this location
                foreach ($course_recs_with_locations as $course_id => &$course_rec) {
                    if (in_array($course_id, $location_course_list)) { // Check against the direct list
                        // This course is available at this location
                        $course_type = $course_rec['course_type']; // 'academies' or 'camps'
                        
                        // Get the location URL from the database if available
                        $locationUrl = $location['url'];
                        
                        // Create a simplified location object with just what we need
                        $location_data = [
                            'id' => $location['id'],
                            'name' => $location['name'],
                            'status' => $location['status'],
                            'distance' => $location['distance'],
                            'url' => $locationUrl,
                            'address' => $location['address'],
                            'locationDesc' => $location['locationDesc'] ?? null,
                            'overnightOffered' => $location['overnightOffered'] ?? 'No'
                        ];
                        
                        // Retrieve session weeks directly from the $location object
                        $sessionWeeksData = $location['sessionWeeks'] ?? null;
                        if (isset($sessionWeeksData[$course_type])) {
                            $location_data['sessionWeeks'] = $sessionWeeksData[$course_type];
                        } else {
                            $location_data['sessionWeeks'] = []; // Default to empty if not found for this course type
                        }
                        
                        // Get capacity data for this course at this location
                        $capacity_data = get_idwiz_course_capacity([
                            'locationID' => $location['id'],
                            'productID' => $course_id,
                            'sortBy' => 'sessionStartDate',
                            'sort' => 'ASC'
                        ]);
                        
                        // Add capacity information to the location
                        if (!empty($capacity_data)) {
                            $sessions = [];
                            $low_availability_sessions = [];
                            
                            foreach ($capacity_data as $session) {
                                $session_info = [
                                    'sessionStartDate' => $session['sessionStartDate'],
                                    'courseStartDate' => $session['courseStartDate'],
                                    'minimumAge' => $session['minimumAge'],
                                    'maximumAge' => $session['maximumAge'],
                                    'courseCapacityTotal' => $session['courseCapacityTotal'],
                                    'courseSeatsLeft' => $session['courseSeatsLeft'],
                                    'availability_status' => get_availability_status($session['courseSeatsLeft'], $session['courseCapacityTotal'])
                                ];
                                
                                $sessions[] = $session_info;
                                
                                // Track sessions with low availability (less than 25% or under 3 seats)
                                $seats_left = intval($session['courseSeatsLeft']);
                                $total_capacity = intval($session['courseCapacityTotal']);
                                $availability_percentage = $total_capacity > 0 ? ($seats_left / $total_capacity) * 100 : 0;
                                
                                if ($seats_left <= 3 || $availability_percentage < 25) {
                                    $low_availability_sessions[] = $session_info;
                                }
                            }
                            
                            $location_data['capacity'] = [
                                'total_sessions' => count($sessions),
                                'low_availability_count' => count($low_availability_sessions),
                                'sessions' => $sessions,
                                'low_availability_sessions' => $low_availability_sessions
                            ];
                        } else {
                            $location_data['capacity'] = [
                                'total_sessions' => 0,
                                'low_availability_count' => 0,
                                'sessions' => [],
                                'low_availability_sessions' => []
                            ];
                        }
                        
                        $matches_found++;
                        
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
                    'student_age' => $ipc_recommendations['student_age'] ?? $student_age,
                    'from_fiscal_year' => $ipc_recommendations['from_fiscal_year'] ?? null,
                    'to_fiscal_year' => $ipc_recommendations['to_fiscal_year'] ?? null,
                    'age_up' => $needs_age_up, // Add age_up flag to top-level metadata
                    'recommendation_counts' => $metadata
                ],
                'last_purchase' => $last_purchase,
                'courses' => $course_recs_with_locations
            ];
        
        case 'current_year_continuity_recs':
            return get_current_year_continuity_recs($student_data);
        
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
 * Gets current year continuity recommendations for students who bought camp in current fiscal year
 * Uses FY24 mapping assumptions to get recommendations for their most recent course
 */
function get_current_year_continuity_recs($student_data) {
    global $wpdb;
    
    // Get student account number
    $student_account_number = $student_data['studentAccountNumber'] ?? $student_data['StudentAccountNumber'] ?? null;
    if (!$student_account_number) {
        return null;
    }
    
    // Calculate current fiscal year (FY25 runs from 2024-11-01 to 2025-10-31)
    $current_date = new DateTime();
    $year = intval($current_date->format('Y'));
    $month = intval($current_date->format('n'));
    $current_fy_year = ($month >= 11) ? $year + 1 : $year;
    $fy_start = ($current_fy_year - 1) . '-11-01';
    $fy_end = $current_fy_year . '-10-31';
    
    // Get the student's most recent in-person purchase in current fiscal year
    $current_purchase = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT p.*, uf.studentDOB, uf.StudentFirstName, p.shoppingCartItems_sessionStartDateNonOpl
            FROM {$wpdb->prefix}idemailwiz_purchases p
            JOIN {$wpdb->prefix}idemailwiz_userfeed uf ON p.shoppingCartItems_studentAccountNumber = uf.studentAccountNumber
            WHERE p.shoppingCartItems_studentAccountNumber = %s 
            AND p.shoppingCartItems_divisionId IN (22, 25)
            AND p.purchaseDate BETWEEN %s AND %s
            ORDER BY p.purchaseDate DESC 
            LIMIT 1",
            $student_account_number,
            $fy_start,
            $fy_end
        ),
        ARRAY_A
    );
    
    if (!$current_purchase) {
        return null; // No current fiscal year in-person purchase found
    }
    
    // Get the current course details
    $current_course = get_course_details_by_id($current_purchase['shoppingCartItems_id']);
    if (is_wp_error($current_course) || !isset($current_course->course_recs)) {
        return null;
    }
    
    // Calculate student age
    $student_age = get_student_age($student_data);
    
    // Get location information
    $location_name = $current_purchase['shoppingCartItems_locationName'];
    $location_info = null;
    
    if ($location_name) {
        $location_info = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, name, locationStatus, address, locationUrl, locationDesc, overnightOffered
                FROM {$wpdb->prefix}idemailwiz_locations 
                WHERE name = %s 
                AND locationStatus IN ('Open', 'Registration opens soon')",
                $location_name
            ),
            ARRAY_A
        );
    }
    
    // Determine age-up logic - treat as if this was a FY24 purchase
    $needs_age_up = determine_age_up_need($student_age, $student_age, $current_course);
    
    // Get recommendations using FY24 mapping logic
    $division_key = ($current_purchase['shoppingCartItems_divisionId'] == 22) ? 'idta' : 'idtc';
    $recommendations = get_course_recommendations($current_course, $division_key, $needs_age_up);
    
    // Get capacity data for current course (continuation)
    $current_course_capacity = [];
    if ($location_info) {
        $capacity_data = get_idwiz_course_capacity([
            'locationID' => $location_info['id'],
            'productID' => $current_course->id,
            'sortBy' => 'sessionStartDate',
            'sort' => 'ASC'
        ]);
        
        if (!empty($capacity_data)) {
            foreach ($capacity_data as $session) {
                if (!empty($session['sessionStartDate'])) {
                    $session_start = new DateTime($session['sessionStartDate']);
                    $monday_start = $session_start->format('Y-m-d');
                    
                    // Only include future sessions
                    if ($session_start > $current_date) {
                        $current_course_capacity[] = [
                            'monday_start' => $monday_start,
                            'seats_left' => intval($session['courseSeatsLeft']),
                            'total_capacity' => intval($session['courseCapacityTotal']),
                            'course_start_date' => $session['courseStartDate'],
                            'availability_status' => get_availability_status($session['courseSeatsLeft'], $session['courseCapacityTotal'])
                        ];
                    }
                }
            }
        }
    }
    
    // Get capacity data for recommended courses
    $recommendations_with_capacity = [];
    if (!empty($recommendations) && $location_info) {
        foreach ($recommendations as $rec_course) {
            $rec_capacity_data = get_idwiz_course_capacity([
                'locationID' => $location_info['id'],
                'productID' => $rec_course['id'],
                'sortBy' => 'sessionStartDate',
                'sort' => 'ASC'
            ]);
            
            $rec_capacity = [];
            if (!empty($rec_capacity_data)) {
                foreach ($rec_capacity_data as $session) {
                    if (!empty($session['sessionStartDate'])) {
                        $session_start = new DateTime($session['sessionStartDate']);
                        $monday_start = $session_start->format('Y-m-d');
                        
                        // Only include future sessions
                        if ($session_start > $current_date) {
                            $rec_capacity[] = [
                                'monday_start' => $monday_start,
                                'seats_left' => intval($session['courseSeatsLeft']),
                                'total_capacity' => intval($session['courseCapacityTotal']),
                                'course_start_date' => $session['courseStartDate'],
                                'availability_status' => get_availability_status($session['courseSeatsLeft'], $session['courseCapacityTotal'])
                            ];
                        }
                    }
                }
            }
            
            $recommendations_with_capacity[] = [
                'id' => $rec_course['id'],
                'name' => $rec_course['title'],
                'description' => $rec_course['courseDesc'],
                'abbreviation' => $rec_course['abbreviation'],
                'minAge' => $rec_course['minAge'],
                'maxAge' => $rec_course['maxAge'],
                'courseUrl' => $rec_course['courseUrl'],
                'capacity' => [
                    'available_sessions' => $rec_capacity,
                    'total_sessions' => count($rec_capacity)
                ]
            ];
        }
    }
    
    // Prepare location data
    $location_data = null;
    if ($location_info) {
        $address = !empty($location_info['address']) ? unserialize($location_info['address']) : null;
        $location_data = [
            'id' => $location_info['id'],
            'name' => $location_info['name'],
            'status' => $location_info['locationStatus'],
            'url' => $location_info['locationUrl'],
            'address' => $address,
            'locationDesc' => $location_info['locationDesc'],
            'overnightOffered' => $location_info['overnightOffered'] ?? 'No'
        ];
    }
    
    // Prepare response structure optimized for Iterable template
    return [
        'student_info' => [
            'name' => $current_purchase['StudentFirstName'] ?? '',
            'account_number' => $student_account_number,
            'age' => $student_age
        ],
        'current_course' => [
            'id' => $current_course->id,
            'name' => $current_course->title,
            'description' => $current_course->courseDesc,
            'abbreviation' => $current_course->abbreviation,
            'minAge' => $current_course->minAge,
            'maxAge' => $current_course->maxAge,
            'courseUrl' => $current_course->courseUrl,
            'purchase_date' => $current_purchase['purchaseDate'],
            'session_start_date' => $current_purchase['shoppingCartItems_sessionStartDateNonOpl'] ?? null,
            'capacity' => [
                'available_sessions' => $current_course_capacity,
                'total_sessions' => count($current_course_capacity)
            ]
        ],
        'location' => $location_data,
        'recommendations' => $recommendations_with_capacity,
        'metadata' => [
            'total_recommendations' => count($recommendations_with_capacity),
            'age_up' => $needs_age_up,
            'fiscal_year' => 'fy' . substr($current_fy_year, -2),
            'mapping_source' => 'fy24_assumptions'
        ]
    ];
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
    // --- START IPC FILTER ---
    if ($division === 'ipc') {
        $divisionIds = [22, 25]; // Specifically look for iDTA (22) and iDTC (25)
    }
    // --- END IPC FILTER ---    
    else if ($division === 'idtc') {
        $divisionIds = [25]; // iD Tech Camps
    } else if ($division === 'idta') {
        $divisionIds = [22]; // iD Teen Academy
    } else if ($division === 'vtc') {
        $divisionIds = [42]; // Virtual Tech Camps
    } else if ($division === 'ota') {
        $divisionIds = [47]; // Online Teen Academy
    } else if ($division === 'opl') {
        $divisionIds = [41]; // Online Private Lessons
    } else {
        // If no division specified, or an unknown one, include all relevant divisions
        $divisionIds = [22, 25, 42, 47, 41];
    }
    
    $divisionString = implode(', ', $divisionIds);
    
    // Get the student's most recent purchase within the FROM fiscal year for the specified division(s)
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
            'courseDesc' => $course->courseDesc ?? null, // Add courseDesc
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

/**
 * Generate a consistent payload structure for endpoints
 * This function is the single source of truth for endpoint payload structure
 * 
 * @param string $endpoint The endpoint name/route
 * @param array $feed_data The user/student data
 * @param array $presets Processed preset values
 * @param array $endpoint_config Endpoint configuration
 * @return array The structured payload
 */
function generate_endpoint_payload($endpoint, $feed_data, $presets, $endpoint_config) {
    $payload = [
        'endpoint' => $endpoint,
        'data' => []
    ];
    
    // Start with the base feed_data
    $payload['data'] = $feed_data;
    
    // Apply data mappings if configured, potentially overwriting base data
    if (!empty($endpoint_config['data_mapping'])) {
        foreach ($endpoint_config['data_mapping'] as $key => $mapping) {
            if ($mapping['type'] === 'static') {
                $payload['data'][$key] = $mapping['value'];
            } else if ($mapping['type'] === 'preset' && isset($presets[$mapping['value']])) {
                $payload['data'][$key] = $presets[$mapping['value']];
            } else if ($mapping['type'] === 'preset') {
                // If a preset is mapped but not found/null, ensure the key exists with null value
                $payload['data'][$key] = null;
            }
        }
        // If data mapping IS defined, the _presets object should NOT be at the root of data.
        // Presets are individually mapped to keys if data_mapping is used.
        if (isset($payload['data']['_presets'])) {
            unset($payload['data']['_presets']);
        }
    } else {
        // If no mappings defined, include all base data (already done)
        // and include presets in the _presets property.
        if (!empty($presets)) {
            $payload['data']['_presets'] = $presets;
        }
    }
    
    return $payload;
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
        // Commented out: location_with_courses preset now has fallback logic (previous location -> leadLocationId -> null)
        // if (empty($feed_data['leadLocationId']) && in_array('location_with_courses', get_required_presets($endpoint_config))) {
        //     return new WP_REST_Response(['error' => 'User does not have a lead location assigned'], 400);
        // }
        
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
        
        // Add age and birthday flags for consistency with preview
        if (isset($feed_data['studentDOB'])) {
            $student_dob = new DateTime($feed_data['studentDOB']);
            $now = new DateTime();
            $six_months_ago = (new DateTime())->modify('-6 months')->setTime(0, 0, 0);
            
            // Calculate age
            $feed_data['age'] = $student_dob->diff($now)->y;
            
            // Calculate 10th and 13th birthdays
            $tenth_birthday = (clone $student_dob)->modify('+10 years');
            $thirteenth_birthday = (clone $student_dob)->modify('+13 years');
            
            // Check if turned 10 in last 6 months
            $feed_data['turned_10'] = ($tenth_birthday >= $six_months_ago && $tenth_birthday <= $now);
            
            // Check if turned 13 in last 6 months
            $feed_data['turned_13'] = ($thirteenth_birthday >= $six_months_ago && $thirteenth_birthday <= $now);
        } else {
            $feed_data['age'] = null;
            $feed_data['turned_10'] = false;
            $feed_data['turned_13'] = false;
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
                    // Since location_with_courses now has fallback logic (previous location -> leadLocationId -> null),
                    // we only return an error if the preset truly failed to find any location
                    return new WP_REST_Response([
                        'error' => 'No location found: student has no previous location and no lead location assigned',
                        'elapsed_time' => round((microtime(true) - $start_time) * 1000)
                    ], 400);
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

    // Use the new function to generate a consistent payload structure
    $payload = generate_endpoint_payload($endpoint, $feed_data, $presets, $endpoint_config);

    // Calculate execution time and return response
    $execution_time = round((microtime(true) - $start_time) * 1000);
    $data_size = strlen(json_encode($payload));
    
    error_log("ID Email Wiz REST API Success: Account Number=$account_number, Response Size=$data_size bytes, Execution Time={$execution_time}ms");

    $response = new WP_REST_Response($payload, 200);
    
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
    
    // Get endpoint name and other parameters
    $endpoint = isset($_POST['endpoint']) ? sanitize_text_field($_POST['endpoint']) : '';
    $base_data_source = isset($_POST['base_data_source']) ? sanitize_text_field($_POST['base_data_source']) : 'user_feed';
    $data_mapping = isset($_POST['data_mapping']) ? json_decode(stripslashes($_POST['data_mapping']), true) : [];
    
    // Create a temporary endpoint config for preview
    $endpoint_config = [
        'base_data_source' => $base_data_source,
        'data_mapping' => $data_mapping
    ];
    
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

        // Calculate and add age and recent birthday flags
        if (isset($feed_data['studentDOB'])) {
            $student_dob = new DateTime($feed_data['studentDOB']);
            $now = new DateTime();
            $six_months_ago = (new DateTime())->modify('-6 months')->setTime(0, 0, 0);
            
            // Calculate age
            $feed_data['age'] = $student_dob->diff($now)->y;
            
            // Calculate 10th and 13th birthdays
            $tenth_birthday = (clone $student_dob)->modify('+10 years');
            $thirteenth_birthday = (clone $student_dob)->modify('+13 years');
            
            // Check if turned 10 in last 6 months
            $feed_data['turned_10'] = ($tenth_birthday >= $six_months_ago && $tenth_birthday <= $now);
            
            // Check if turned 13 in last 6 months
            $feed_data['turned_13'] = ($thirteenth_birthday >= $six_months_ago && $thirteenth_birthday <= $now);
        } else {
            $feed_data['age'] = null;
            $feed_data['turned_10'] = false;
            $feed_data['turned_13'] = false;
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

            // Generate the payload using the same function as the real endpoint
            $payload = generate_endpoint_payload($endpoint, $feed_data, $presets, $endpoint_config);
            
            wp_send_json_success($payload);
            
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
    
    // Default to no age-up needed
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
        // Get recommendations for this division
        $recommendations = get_course_recommendations_between_fiscal_years($student_data, $from_fiscal_year, $fiscal_year, $division);
        
        // If recommendations found and age_up flag exists, use it
        if (!empty($recommendations) && isset($recommendations['age_up'])) {
            // Keep the age_up flag determined by determine_age_up_need() in get_course_recommendations_between_fiscal_years
            // This properly handles cases like 10-year-olds aging up from 7-9 courses
            
            // Additional division-specific age checks (these supplement but don't override)
            if (!$recommendations['age_up']) {
                if (($division === 'idta' || $division === 'ota') && $student_age < 13) {
                    $recommendations['age_up'] = true;
                } else if ($division === 'idtc' && $student_age >= 18) {
                    $recommendations['age_up'] = true;
                }
            }
        } else {
            // If no recommendations or no age_up flag, add default values
            if (empty($recommendations)) {
                $recommendations = [];
            }
            
            // Apply division-specific age checks
            $needs_age_up = false;
            if (($division === 'idta' || $division === 'ota') && $student_age < 13) {
                $needs_age_up = true;
            } else if ($division === 'idtc' && $student_age >= 18) {
                $needs_age_up = true;
            }
            
            $recommendations['age_up'] = $needs_age_up;
        }
        
        // Make sure student_age is set
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
 * Determine availability status based on seats left and total capacity
 */
function get_availability_status($seats_left, $total_capacity) {
    $seats_left = intval($seats_left);
    $total_capacity = intval($total_capacity);
    
    if ($total_capacity <= 0) {
        return 'unknown';
    }
    
    $availability_percentage = ($seats_left / $total_capacity) * 100;
    
    if ($seats_left <= 0) {
        return 'sold_out';
    } elseif ($seats_left <= 3 || $availability_percentage < 25) {
        return 'low_availability';
    } elseif ($availability_percentage < 50) {
        return 'moderate_availability';
    } else {
        return 'high_availability';
    }
}

/**
 * Clean up corrupted transients that might cause database errors
 */
function idwiz_cleanup_corrupted_transients() {
    global $wpdb;
    
    // List of transients that might cause issues
    $problematic_transients = [
        'idwiz_api_indexes_checked',
        'idwiz_api_request_times'
    ];
    
    foreach ($problematic_transients as $transient) {
        // Use direct database queries to avoid WordPress transient functions that might be causing issues
        try {
            // Delete all variants of this transient (including _transient_ and _transient_timeout_ prefixes)
            $patterns = [
                '_transient_' . $transient,
                '_transient_timeout_' . $transient
            ];
            
            foreach ($patterns as $pattern) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name = %s",
                    $pattern
                ));
            }
            
            // Also clean up any potential wildcards
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '%' . $transient . '%'
            ));
            
        } catch (Exception $e) {
            // Log but don't fail - this is a cleanup function
            error_log('ID Email Wiz: Error cleaning transient ' . $transient . ': ' . $e->getMessage());
        }
    }
    
    // Clear any WordPress object cache for these transients
    wp_cache_flush();
}

/**
 * Ensure necessary database indexes exist for API endpoints
 * This helps speed up commonly used queries
 */
function idwiz_ensure_api_indexes() {
    global $wpdb;
    
    // Only run this periodically, using a transient to limit frequency
    // Use try-catch to handle transient errors gracefully
    try {
        $indexes_checked = get_transient('idwiz_api_indexes_checked');
        if ($indexes_checked) {
            return;
        }
    } catch (Exception $e) {
        // If transient still fails after cleanup, just continue without transient check
        error_log('ID Email Wiz: Transient error in idwiz_ensure_api_indexes after cleanup: ' . $e->getMessage());
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
    
    // Set transient to prevent frequent checking - only check once per week now
    try {
        set_transient('idwiz_api_indexes_checked', true, WEEK_IN_SECONDS);
    } catch (Exception $e) {
        // If setting transient fails, just log and continue
        error_log('ID Email Wiz: Unable to set transient: ' . $e->getMessage());
    }
}

// Add index checking - much safer approach, only when really needed
add_action('admin_init', function() {
    // Only run in admin area, and only once per day maximum
    if (current_user_can('manage_options')) {
        idwiz_ensure_api_indexes();
    }
}, 999);

// Also run when REST API endpoints are first registered (much less frequent)
add_action('rest_api_init', function() {
    // Only run during REST API initialization, with transient protection
    idwiz_ensure_api_indexes();
}, 999);

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
 * Prioritizes student's previous location, falls back to leadLocationId from parent account data
 */
function get_location_with_courses($user_data) {
    global $wpdb;
    
    $location_id = null;
    $location_source = 'unknown';
    
    // First priority: Try to get the student's last location
    if (isset($user_data['studentAccountNumber']) || isset($user_data['StudentAccountNumber'])) {
        $last_location = get_last_location($user_data);
        
        if ($last_location && isset($last_location['id']) && $last_location['id'] > 0) {
            $location_id = intval($last_location['id']);
            $location_source = 'student_previous_location';
        } else {
            // Fall back to parent's leadLocationId if student has no previous location
            if (!empty($user_data['accountNumber'])) {
                $parent = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT leadLocationId FROM {$wpdb->prefix}idemailwiz_users WHERE accountNumber = %s LIMIT 1",
                        $user_data['accountNumber']
                    ),
                    ARRAY_A
                );
                
                if ($parent && isset($parent['leadLocationId']) && $parent['leadLocationId'] > 0) {
                    $location_id = intval($parent['leadLocationId']);
                    $location_source = 'parent_lead_location';
                }
            }
        }
    } 
    // Handle parent account data
    else {
        // First try leadLocationId if it's valid
        if (isset($user_data['leadLocationId']) && $user_data['leadLocationId'] > 0) {
            $location_id = intval($user_data['leadLocationId']);
            $location_source = 'parent_lead_location';
        } 
        // If leadLocationId is 0 or missing, check students' previous locations
        else {
            if (isset($user_data['studentArray']) && !empty($user_data['studentArray'])) {
                $student_array = is_string($user_data['studentArray']) ? unserialize($user_data['studentArray']) : $user_data['studentArray'];
                
                if (is_array($student_array) && !empty($student_array)) {
                    // Check each student for previous locations
                    foreach ($student_array as $student_info) {
                        if (isset($student_info['StudentAccountNumber'])) {
                            // Create student data for get_last_location
                            $temp_student_data = [
                                'studentAccountNumber' => $student_info['StudentAccountNumber'],
                                'StudentAccountNumber' => $student_info['StudentAccountNumber']
                            ];
                            
                            $student_last_location = get_last_location($temp_student_data);
                            
                            if ($student_last_location && isset($student_last_location['id']) && $student_last_location['id'] > 0) {
                                $location_id = intval($student_last_location['id']);
                                $location_source = 'student_previous_location';
                                break; // Use the first valid location found
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Check if we have a valid location ID
    if (empty($location_id) || $location_id <= 0) {
        return null;
    }
    
    // First check if the location exists at all, regardless of status
    $location_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}idemailwiz_locations WHERE id = %d",
            $location_id
        )
    );
    
    if (!$location_exists) {
        return null;
    }
    
    // Get location details from the database
    try {
        $query = $wpdb->prepare(
            "SELECT id, name, abbreviation, locationStatus, address, locationUrl, courses, sessionWeeks, divisions, locationDesc, overnightOffered 
             FROM {$wpdb->prefix}idemailwiz_locations 
             WHERE id = %d",
            $location_id
        );
        
        $location = $wpdb->get_row($query, ARRAY_A);
        
        // Check if we found the location but it's not active
        if ($location && !in_array($location['locationStatus'], ['Open', 'Registration opens soon'])) {
            return null;
        }
        
        if (!$location) {
            return null;
        }
        
        // Return null if this is Online Campus (ID 324)
        if ($location['id'] == 324) {
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
        
        // Get course details for all courses at this location
        $courses_data = [];
        if (!empty($courses_ids)) {
            try {
                // Make sure all course IDs are integers
                $courses_ids = array_map('intval', $courses_ids);
                $courses_ids = array_filter($courses_ids, function($id) { return $id > 0; });
                
                if (!empty($courses_ids)) {
                    $placeholders = implode(',', array_fill(0, count($courses_ids), '%d'));
                    $courses_query = $wpdb->prepare(
                        "SELECT id, title, abbreviation, division_id, minAge, maxAge, fiscal_years, courseUrl, wizStatus, courseDesc
                         FROM {$wpdb->prefix}idemailwiz_courses 
                         WHERE id IN ($placeholders)
                         AND wizStatus = 'Active'",  // Only active courses
                        $courses_ids
                    );
                    
                    $courses_data = $wpdb->get_results($courses_query, ARRAY_A);
                }
            } catch (Exception $e) {
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
            'locationDesc' => $location['locationDesc'] ?? null,
            'overnightOffered' => $location['overnightOffered'] ?? 'No',
            'divisions' => $divisions,
            'session_weeks' => $session_weeks,
            'courses' => $courses_data,
            'location_source' => $location_source // Add metadata about where we got the location from
        ];
        
        return $result;
        
    } catch (Exception $e) {
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
        'opl_course_recs',
        'current_year_continuity_recs'
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



