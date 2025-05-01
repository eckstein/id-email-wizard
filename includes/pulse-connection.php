<?php
function wizPulse_get_all_locations()
{
    // Gets Pulse location data for iD Tech Camps and iD Tech Academies
    //$apiURL = 'https://pulseapi.idtech.com/Locations/GetAll?companyID=1&experienceTypeIDs=357002&experienceTypeIDs=357005&api-version=2016-11-01.1.0';
    $apiURL = 'https://pulseapi.idtech.com/Locations/GetAll?companyID=1&api-version=2016-11-01.1.0';
    $response = idemailwiz_iterable_curl_call($apiURL);
    return $response['response']['results'];
}

function wizPulse_map_locations_to_database()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_locations';
    
    $locations = wizPulse_get_all_locations();
    $processed = 0;

    foreach ($locations as $location) {
        $id = $location['id'];
        $name = $location['name'];
        $abbreviation = $location['abbreviation'];
        $addressArea = $location['addressArea'];
        $firstSessionStartDate = date('Y-m-d', strtotime($location['firstSessionStartDate']));
        $lastSessionEndDate = date('Y-m-d', strtotime($location['lastSessionEndDate']));

        // Serialize courses and divisions
        $courses = !empty($location['courses']) ? serialize($location['courses']) : null;
        $divisions = !empty($location['divisions']) ? serialize($location['divisions']) : null;
        $soldOutCourses = !empty($location['soldOutCourses']) ? serialize($location['soldOutCourses']) : null;

        // Get locationStatus as text
        $locationStatus = $location['locationStatus']['name'];

        // Serialize address
        $address = !empty($location['address']) ? serialize($location['address']) : null;

        // Insert or update the data
        $result = $wpdb->replace(
            $table_name,
            array(
                'id' => $id,
                'name' => $name,
                'abbreviation' => $abbreviation,
                'addressArea' => $addressArea,
                'firstSessionStartDate' => $firstSessionStartDate,
                'lastSessionEndDate' => $lastSessionEndDate,
                'courses' => $courses,
                'divisions' => $divisions,
                'soldOutCourses' => $soldOutCourses,
                'locationStatus' => $locationStatus,
                'address' => $address
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            $processed++;
        }
    }
    
    return $processed;
}

// Function to refresh locations in the database
function wizPulse_refresh_locations()
{
    $pulse_locations = wizPulse_map_locations_to_database();
    $session_result = wizPulse_sync_location_sessions();
    
    return [
        'pulse_locations' => $pulse_locations,
        'session_sync' => $session_result
    ];
}

// Function to sync session weeks and location URLs from SheetDB
function wizPulse_sync_location_sessions()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_locations';
    
    // SheetDB API endpoint - updated to new URL
    $api_url = 'https://sheetdb.io/api/v1/ov2axr4kssf94';
    
    // Get data from SheetDB
    $response = idemailwiz_iterable_curl_call($api_url, null, false, 3, 5);
    $sheet_data = $response['response'];
    
    if (!$sheet_data || !is_array($sheet_data)) {
        return false;
    }
    
    // Define week dates mapping with start dates in YYYY-MM-DD format
    $week_dates = [
        'Cap Week 1' => '2025-05-25',
        'Cap Week 2' => '2025-06-01',
        'Cap Week 3' => '2025-06-08',
        'Cap Week 4' => '2025-06-15',
        'Cap Week 5' => '2025-06-22',
        'Cap Week 6' => '2025-06-29',
        'Cap Week 7' => '2025-07-06',
        'Cap Week 8' => '2025-07-13',
        'Cap Week 9' => '2025-07-20',
        'Cap Week 10' => '2025-07-27',
        'Cap Week 11' => '2025-08-03',
        'Cap Week 12' => '2025-08-10'
    ];
    
    // Process data by location
    $location_data = [];
    $processed_rows = 0;
    
    foreach ($sheet_data as $row) {
        // Get location data from spreadsheet - fields have changed in new format
        $division_code = $row['Division'] ?? '';
        $location_code_with_suffix = $row['Shortcode'] ?? '';
        $location_name = $row['Location Name'] ?? '';
        $location_url = $row['Location URL'] ?? '';
        
        // Skip rows without required location data
        if (empty($location_code_with_suffix) || empty($location_name)) {
            continue;
        }
        
        // Extract the location code without division suffix (TC or TA)
        $location_code = preg_replace('/(TC|TA)$/', '', $location_code_with_suffix);
        
        // Determine division type
        $division_type = '';
        if (stripos($division_code, 'iDTC') !== false || stripos($location_code_with_suffix, 'TC') !== false) {
            $division_type = 'camps';
        } elseif (stripos($division_code, 'iDTA') !== false || stripos($location_code_with_suffix, 'TA') !== false) {
            $division_type = 'academies';
        }
        
        if (empty($division_type)) {
            continue;
        }
        
        $processed_rows++;
        
        // Initialize location data if not exists
        if (!isset($location_data[$location_code])) {
            $location_data[$location_code] = [
                'url' => '',
                'name' => $location_name,
                'weeks' => [
                    'camps' => [],
                    'academies' => []
                ]
            ];
        }
        
        // Set location URL if available
        if (!empty($location_url) && empty($location_data[$location_code]['url'])) {
            // Clean up URL (remove escaped backslashes)
            $clean_url = str_replace('\\/', '/', $location_url);
            $location_data[$location_code]['url'] = $clean_url;
        }
        
        // Process session weeks
        foreach ($week_dates as $column => $date) {
            if (isset($row[$column]) && !empty($row[$column]) && $row[$column] !== '0') {
                // Add this week to the appropriate division array if not already present
                if (!in_array($date, $location_data[$location_code]['weeks'][$division_type])) {
                    $location_data[$location_code]['weeks'][$division_type][] = $date;
                }
            }
        }
    }
    
    // Update database with session weeks data
    $updated_count = 0;
    
    foreach ($location_data as $location_code => $data) {
        // Serialize the weeks data
        $session_weeks = serialize($data['weeks']);
        
        // Get the existing record to verify if it exists
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE abbreviation = %s", $location_code)
        );
        
        if ($existing) {
            // Update the location record
            $result = $wpdb->update(
                $table_name,
                [
                    'sessionWeeks' => $session_weeks,
                    'locationUrl' => $data['url']
                ],
                [
                    'abbreviation' => $location_code
                ]
            );
            
            if ($result !== false) {
                $updated_count++;
            }
        }
    }
    
    return $updated_count > 0;
}

// Set daily cron to refresh locations
if (!wp_next_scheduled('wizPulse_refresh_locations_cron')) {
    wp_schedule_event(strtotime('05:00:00'), 'daily', 'wizPulse_refresh_locations_cron');
}
add_action('wizPulse_refresh_locations_cron', 'wizPulse_refresh_locations');




function wizPulse_get_all_courses()
{
    // Gets Pulse location data for iD Tech Camps and iD Tech Academies
    $apiURL = 'https://pulseapi.idtech.com/Courses/GetAll?companyID=1&limit=1000&api-version=2016-11-01.1.0';
    $response = idemailwiz_iterable_curl_call($apiURL);
    return $response['response']['results'];
}

function wizPulse_map_courses_to_database()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_courses';

    // Get all courses
    $courses = wizPulse_get_all_courses();

    $processed_courses = [];
    $seen_abbreviations = [];

    foreach ($courses as $course) {

        // Skip if course abbreviation starts with "OLT" (OPL internal/old stuff)
        if (strpos($course['abbreviation'], 'OLT')) {
            continue;
        }

        // Clean and de-dupe abbreviation for OPL courses
        if ($course['division']['id'] == 41) {
            $clean_abbreviation = strstr($course['abbreviation'], '-', true) ?: $course['abbreviation'];
        } else {
            $clean_abbreviation = $course['abbreviation'];
        }
        

        if (!isset($seen_abbreviations[$clean_abbreviation])) {
            // Clean title
            $clean_title = strstr($course['title'], ' - ', true) ?: $course['title'];

            $id = $course['id'];
            $division_id = $course['division']['id'];

            // if division_id is not 22 or 25 (IPC), we set location to empty to indicate online
            $course['locations'] = in_array($division_id, [22, 25]) ? $course['locations'] : [];

            // Serialize locations, or set to NULL if empty
            $locations = !empty($course['locations']) ? serialize($course['locations']) : null;

            // Allow mustTurnMinAgeByDate to be null
            $mustTurnMinAgeByDate = !empty($course['mustTurnMinAgeByDate'])
                ? date('Y-m-d', strtotime($course['mustTurnMinAgeByDate']))
                : null;

            // Handle catelogDateRanges
            $startDate = null;
            $endDate = null;
            if (!empty($course['catelogDateRanges'])) {
                $startDate = date('Y-m-d', strtotime($course['catelogDateRanges'][0]['startDate']));
                $endDate = date('Y-m-d', strtotime($course['catelogDateRanges'][0]['endDate']));
            }

            // Handle genres
            $genres = array_column($course['genres'], 'id');
            $genres = !empty($genres) ? serialize($genres) : null;

            $pathwayLevelCredits = $course['pathwayLevelCredits'] ?? 0;

            if ($division_id == 41 && (!isset($pathwayLevelCredits) || $pathwayLevelCredits == 0)) {
                $pathwayLevelCredits = 67; // hard code opl to 67 credits because pulse doesn't give it to us
            }

            $minAge = $course['minAge'];
            $maxAge = $course['maxAge'];
            $isNew = $course['isNew'] ? 1 : 0;
            $isMostPopular = $course['isMostPopular'] ? 1 : 0;

            // Determine wizStatus
            $wizStatus = (empty($course['locations']) && empty($course['catelogDateRanges'])) ? 'inactive' : 'active';

            $processed_courses[] = [
                'id' => $id,
                'title' => $clean_title,
                'abbreviation' => $clean_abbreviation,
                'locations' => $locations,
                'mustTurnMinAgeByDate' => $mustTurnMinAgeByDate,
                'division_id' => $division_id,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'genres' => $genres,
                'pathwayLevelCredits' => $pathwayLevelCredits,
                'minAge' => $minAge,
                'maxAge' => $maxAge,
                'isNew' => $isNew,
                'isMostPopular' => $isMostPopular,
                'wizStatus' => $wizStatus
            ];

            $seen_abbreviations[$clean_abbreviation] = true;
        }
    }

    // Batch insert or update
    if (!empty($processed_courses)) {
        foreach ($processed_courses as $course) {
            // Check if the course already exists and has course_recs or courseUrl
            $existing_course = $wpdb->get_row($wpdb->prepare("SELECT course_recs, courseUrl FROM {$table_name} WHERE id = %d", $course['id']));
            
            // If the course exists and has course_recs, preserve that data
            if ($existing_course && !empty($existing_course->course_recs)) {
                $course['course_recs'] = $existing_course->course_recs;
            }
            
            // If the course exists and has a courseUrl, preserve that data
            if ($existing_course && !empty($existing_course->courseUrl)) {
                $course['courseUrl'] = $existing_course->courseUrl;
            }
            
            // Create placeholders array with the correct number of placeholders
            $placeholders = array_fill(0, count($course), '%s');
            
            $wpdb->replace(
                $table_name,
                $course,
                $placeholders
            );
        }
    }
}

//Run cron daily to refresh courses
if (!wp_next_scheduled('wizPulse_refresh_courses_cron')) {
    wp_schedule_event(strtotime('05:00:00'), 'daily', 'wizPulse_refresh_courses_cron');
}
add_action('wizPulse_refresh_courses_cron', 'wizPulse_refresh_courses');

function wizPulse_refresh_courses()
{
    wizPulse_map_courses_to_database();
    updateCourseFiscalYears();
}

// Function to get location session weeks
function get_location_session_weeks($location_id = null, $location_abbreviation = null, $formatted = false)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_locations';
    
    // Query by ID or abbreviation
    if ($location_id) {
        $location = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $location_id),
            ARRAY_A
        );
    } elseif ($location_abbreviation) {
        $location = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE abbreviation = %s", $location_abbreviation),
            ARRAY_A
        );
    } else {
        return false;
    }
    
    if (!$location) {
        return false;
    }
    
    // Return the session weeks if available
    if (!empty($location['sessionWeeks'])) {
        $session_weeks = unserialize($location['sessionWeeks']);
        
        // Return formatted sessions if requested
        if ($formatted) {
            return format_session_weeks_for_display($session_weeks);
        }
        
        return $session_weeks;
    }
    
    // Return default structure if no session weeks data
    $default = [
        'camps' => [],
        'academies' => []
    ];
    
    return $formatted ? format_session_weeks_for_display($default) : $default;
}

// Function to get all locations with session data
function get_all_locations_with_sessions($args = [])
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_locations';
    
    // Initialize query conditions
    $where_clauses = [];
    $query_params = [];
    
    // Filter by location status if provided
    if (isset($args['status'])) {
        $where_clauses[] = "locationStatus = %s";
        $query_params[] = $args['status'];
    }
    
    // Filter by having session weeks if required
    if (isset($args['has_sessions']) && $args['has_sessions']) {
        $where_clauses[] = "sessionWeeks IS NOT NULL AND sessionWeeks != ''";
    }
    
    // Build the WHERE clause
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    // Execute query
    $query = "SELECT * FROM $table_name $where_sql ORDER BY name ASC";
    
    if (!empty($query_params)) {
        $query = $wpdb->prepare($query, $query_params);
    }
    
    $locations = $wpdb->get_results($query, ARRAY_A);
    
    // Process results to unserialize session weeks
    foreach ($locations as &$location) {
        if (!empty($location['sessionWeeks'])) {
            $location['sessionWeeks'] = unserialize($location['sessionWeeks']);
        } else {
            $location['sessionWeeks'] = [
                'camps' => [],
                'academies' => []
            ];
        }
        
        if (!empty($location['divisions'])) {
            $location['divisions'] = unserialize($location['divisions']);
        }
        
        if (!empty($location['courses'])) {
            $location['courses'] = unserialize($location['courses']);
        }
        
        if (!empty($location['soldOutCourses'])) {
            $location['soldOutCourses'] = unserialize($location['soldOutCourses']);
        }
        
        if (!empty($location['address'])) {
            $location['address'] = unserialize($location['address']);
        }
    }
    
    return $locations;
}

// Helper function to convert session week dates to human-readable format
function format_session_weeks_for_display($session_weeks)
{
    if (empty($session_weeks)) {
        return [
            'camps' => [],
            'academies' => []
        ];
    }
    
    // If session_weeks is a string, unserialize it
    if (is_string($session_weeks)) {
        $session_weeks = unserialize($session_weeks);
    }
    
    $formatted = [
        'camps' => [],
        'academies' => []
    ];
    
    // Convert date strings to formatted dates
    foreach (['camps', 'academies'] as $division) {
        if (!empty($session_weeks[$division])) {
            foreach ($session_weeks[$division] as $date) {
                $start_date = new DateTime($date);
                
                // Calculate end date (5 days after start date for standard week)
                $end_date = clone $start_date;
                $end_date->modify('+5 days');
                
                // Format dates
                $formatted[$division][] = [
                    'date_raw' => $date,
                    'start_date' => $start_date->format('Y-m-d'),
                    'end_date' => $end_date->format('Y-m-d'),
                    'display' => $start_date->format('M j') . ' - ' . $end_date->format('M j, Y'),
                    'week_number' => get_week_number_from_date($date)
                ];
            }
            
            // Sort by date
            usort($formatted[$division], function($a, $b) {
                return strtotime($a['date_raw']) - strtotime($b['date_raw']);
            });
        }
    }
    
    return $formatted;
}

// Helper function to get week number from date
function get_week_number_from_date($date)
{
    $reference_weeks = [
        '2025-05-25' => 1,
        '2025-06-01' => 2,
        '2025-06-08' => 3,
        '2025-06-15' => 4,
        '2025-06-22' => 5,
        '2025-06-29' => 6,
        '2025-07-06' => 7,
        '2025-07-13' => 8,
        '2025-07-20' => 9,
        '2025-07-27' => 10,
        '2025-08-03' => 11,
        '2025-08-10' => 12
    ];
    
    return $reference_weeks[$date] ?? false;
}

// Add manual way to trigger sync via admin
function wizPulse_manual_locations_sync()
{
    $result = wizPulse_refresh_locations();
    
    // Get summary of locations with session weeks
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_locations';
    
    $locations_with_sessions = $wpdb->get_var("
        SELECT COUNT(*) FROM $table_name 
        WHERE sessionWeeks IS NOT NULL AND sessionWeeks != ''
    ");
    
    $total_locations = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    // Build response message
    $message = "Location data synced successfully.\n";
    $message .= "- Processed " . $result['pulse_locations'] . " Pulse locations\n";
    $message .= "- Session data sync: " . ($result['session_sync'] ? 'Success' : 'Failed') . "\n";
    $message .= "- " . $locations_with_sessions . " of " . $total_locations . " locations have session data\n";
    
    // Sample a few locations to verify data
    $sample_locations = $wpdb->get_results("
        SELECT id, name, abbreviation, sessionWeeks 
        FROM $table_name 
        WHERE sessionWeeks IS NOT NULL AND sessionWeeks != '' 
        ORDER BY name ASC LIMIT 3
    ", ARRAY_A);
    
    if (!empty($sample_locations)) {
        $message .= "\nSample locations with session data:\n";
        
        foreach ($sample_locations as $location) {
            $weeks = unserialize($location['sessionWeeks']);
            $camp_weeks = !empty($weeks['camps']) ? count($weeks['camps']) : 0;
            $academy_weeks = !empty($weeks['academies']) ? count($weeks['academies']) : 0;
            
            $message .= "- " . $location['name'] . " (" . $location['abbreviation'] . "): ";
            $message .= $camp_weeks . " camp weeks, " . $academy_weeks . " academy weeks\n";
        }
    }
    
    return $message;
}
