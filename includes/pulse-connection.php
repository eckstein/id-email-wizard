<?php
function wizPulse_get_all_locations()
{
    // Gets Pulse location data for iD Tech Camps and iD Tech Academies
    $apiURL = get_pulse_locations_api_url();
    $response = idemailwiz_iterable_curl_call($apiURL);
    return $response['response']['results'];
}

// Helper function to log when manual values are preserved
function wizPulse_log_preserved_manual_values($location_id, $field_name, $manual_value)
{
    wiz_log("Location Sync: Preserved manual $field_name value for location ID $location_id: " . substr($manual_value, 0, 50) . (strlen($manual_value) > 50 ? '...' : ''));
}

/**
 * Sync locations from Pulse API to database
 * 
 * This function preserves manually set locationDesc values by only updating
 * fields that have actually changed from the API data. It will not overwrite
 * any manually entered locationDesc content.
 */
function wizPulse_map_locations_to_database()
{
    wiz_log("Starting Pulse locations sync from API...");
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_locations';
    
    try {
        $locations = wizPulse_get_all_locations();
        
        if (empty($locations)) {
            wiz_log("Location Sync: No locations returned from Pulse API");
            return 0;
        }
        
        wiz_log("Location Sync: Retrieved " . count($locations) . " locations from Pulse API");
        
        // Get all existing locations in one query to avoid N+1 problem
        $existing_locations = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        $existing_locations_by_id = array();
        foreach ($existing_locations as $existing) {
            $existing_locations_by_id[$existing['id']] = $existing;
        }
        
        $processed = 0;
        $updated = 0;
        $inserted = 0;
        $preserved_manual_values = 0;
        
        // Start transaction for better performance
        $wpdb->query('START TRANSACTION');

        try {
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

                // Check if location already exists using the lookup array
                if (isset($existing_locations_by_id[$id])) {
                    $existing_location = $existing_locations_by_id[$id];
                    
                    // Log if we're preserving a manual locationDesc value
                    if (!empty($existing_location['locationDesc'])) {
                        wizPulse_log_preserved_manual_values($id, 'locationDesc', $existing_location['locationDesc']);
                        $preserved_manual_values++;
                    }
                    
                    // Only update fields that have actually changed
                    $update_data = array();
                    $update_formats = array();
                    
                    if ($existing_location['name'] !== $name) {
                        $update_data['name'] = $name;
                        $update_formats[] = '%s';
                    }
                    if ($existing_location['abbreviation'] !== $abbreviation) {
                        $update_data['abbreviation'] = $abbreviation;
                        $update_formats[] = '%s';
                    }
                    if ($existing_location['addressArea'] !== $addressArea) {
                        $update_data['addressArea'] = $addressArea;
                        $update_formats[] = '%s';
                    }
                    if ($existing_location['firstSessionStartDate'] !== $firstSessionStartDate) {
                        $update_data['firstSessionStartDate'] = $firstSessionStartDate;
                        $update_formats[] = '%s';
                    }
                    if ($existing_location['lastSessionEndDate'] !== $lastSessionEndDate) {
                        $update_data['lastSessionEndDate'] = $lastSessionEndDate;
                        $update_formats[] = '%s';
                    }
                    if ($existing_location['courses'] !== $courses) {
                        $update_data['courses'] = $courses;
                        $update_formats[] = '%s';
                    }
                    if ($existing_location['divisions'] !== $divisions) {
                        $update_data['divisions'] = $divisions;
                        $update_formats[] = '%s';
                    }
                    if ($existing_location['soldOutCourses'] !== $soldOutCourses) {
                        $update_data['soldOutCourses'] = $soldOutCourses;
                        $update_formats[] = '%s';
                    }
                    if ($existing_location['locationStatus'] !== $locationStatus) {
                        $update_data['locationStatus'] = $locationStatus;
                        $update_formats[] = '%s';
                    }
                    if ($existing_location['address'] !== $address) {
                        $update_data['address'] = $address;
                        $update_formats[] = '%s';
                    }
                    
                    // Only perform update if there are changes
                    if (!empty($update_data)) {
                        $result = $wpdb->update(
                            $table_name,
                            $update_data,
                            array('id' => $id),
                            $update_formats,
                            array('%d')
                        );
                        
                        if ($result !== false) {
                            $processed++;
                            $updated++;
                        }
                    } else {
                        // No changes needed, but still count as processed
                        $processed++;
                    }
                } else {
                    // Location doesn't exist - insert new record
                    $result = $wpdb->insert(
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
                        $inserted++;
                    }
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        
        $log_message = "Location Sync: Successfully processed $processed locations from Pulse API";
        if ($updated > 0) $log_message .= " ($updated updated";
        if ($inserted > 0) $log_message .= ($updated > 0 ? ", $inserted inserted" : " ($inserted inserted");
        if ($updated > 0 || $inserted > 0) $log_message .= ")";
        if ($preserved_manual_values > 0) $log_message .= " - Preserved $preserved_manual_values manual locationDesc values";
        
        wiz_log($log_message);
        return $processed;
        
    } catch (Exception $e) {
        wiz_log("Location Sync Error: " . $e->getMessage());
        return 0;
    }
}

// Function to refresh locations in the database
function wizPulse_refresh_locations()
{
    wiz_log("=== Starting Complete Location Refresh Process ===");
    
    // Set a reasonable timeout for the entire process
    set_time_limit(300); // 5 minutes
    
    try {
        // Clear any existing database locks
        global $wpdb;
        $wpdb->query("SET SESSION wait_timeout = 300");
        $wpdb->query("SET SESSION interactive_timeout = 300");
        
        $pulse_locations = wizPulse_map_locations_to_database();
        
        // Add a small delay between operations to prevent overwhelming the database
        usleep(100000); // 100ms delay
        
        $session_result = wizPulse_sync_location_sessions();
        
        $success_message = "Location Refresh Complete: ";
        $success_message .= "Pulse API processed $pulse_locations locations, ";
        $success_message .= "Google Sheets sync " . ($session_result ? "successful" : "failed");
        
        wiz_log($success_message);
        
        return [
            'pulse_locations' => $pulse_locations,
            'session_sync' => $session_result
        ];
        
    } catch (Exception $e) {
        wiz_log("Location Refresh Error: " . $e->getMessage());
        return [
            'pulse_locations' => 0,
            'session_sync' => false
        ];
    }
}

/**
 * Helper functions to get mapping configuration
 * These need to be available in all contexts (admin and cron)
 */
function wizPulse_get_column_mapping($column_key)
{
    $options = get_option('wizPulse_sessions_mapping', array());
    
    $defaults = [
        'division_column' => 'Division',
        'shortcode_column' => 'Shortcode',
        'location_name_column' => 'Location Name',
        'location_url_column' => 'Location URL',
        'overnight_offered_column' => 'ON Offered'
    ];
    
    return isset($options[$column_key]) && !empty($options[$column_key]) 
        ? $options[$column_key] 
        : ($defaults[$column_key] ?? '');
}

function wizPulse_get_week_dates()
{
    $options = get_option('wizPulse_sessions_mapping', array());
    
    $default_dates = [
        '2025-05-25',
        '2025-06-01',
        '2025-06-08',
        '2025-06-15',
        '2025-06-22',
        '2025-06-29',
        '2025-07-06',
        '2025-07-13',
        '2025-07-20',
        '2025-07-27',
        '2025-08-03',
        '2025-08-10'
    ];
    
    $week_dates = [];
    
    for ($i = 1; $i <= 12; $i++) {
        $option_key = "week_{$i}_date";
        $date = isset($options[$option_key]) && !empty($options[$option_key]) 
            ? $options[$option_key] 
            : $default_dates[$i - 1];
        
        $week_dates["Cap Week $i"] = $date;
    }
    
    return $week_dates;
}

// Function to sync session weeks and location URLs from SheetDB
function wizPulse_sync_location_sessions()
{
    wiz_log("Starting location sessions sync from Google Sheets...");
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_locations';
    
    try {
        // Get SheetDB URL from settings
        $api_url = get_location_sessions_sheet_url();
        wiz_log("Location Sessions Sync: Using SheetDB URL: " . $api_url);
        
        // Get data from SheetDB
        $response = idemailwiz_iterable_curl_call($api_url, null, false, 3, 5);
        $sheet_data = $response['response'];
        
        if (!$sheet_data || !is_array($sheet_data)) {
            wiz_log("Location Sessions Sync Error: No valid data returned from Google Sheets");
            return false;
        }
        
        wiz_log("Location Sessions Sync: Retrieved " . count($sheet_data) . " rows from Google Sheets");
        
        // Get week dates from configuration (dynamically set in admin)
        $week_dates = wizPulse_get_week_dates();
        wiz_log("Location Sessions Sync: Using " . count($week_dates) . " configured week dates");
        
        // Get column name mappings from configuration
        $division_col = wizPulse_get_column_mapping('division_column');
        $shortcode_col = wizPulse_get_column_mapping('shortcode_column');
        $location_name_col = wizPulse_get_column_mapping('location_name_column');
        $location_url_col = wizPulse_get_column_mapping('location_url_column');
        $overnight_col = wizPulse_get_column_mapping('overnight_offered_column');
        
        wiz_log("Location Sessions Sync: Using column mappings - Division: '$division_col', Shortcode: '$shortcode_col', Name: '$location_name_col'");
        
        // Process data by location
        $location_data = [];
        $processed_rows = 0;
        
        foreach ($sheet_data as $row) {
            // Get location data from spreadsheet using configured column names
            $division_code = $row[$division_col] ?? '';
            $location_code_with_suffix = $row[$shortcode_col] ?? '';
            $location_name = $row[$location_name_col] ?? '';
            $location_url = $row[$location_url_col] ?? '';
            $overnightOffered = $row[$overnight_col] ?? 'No';
            if ($overnightOffered == 'Division Included') {
                $overnightOffered = 'Yes';
            }
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
                    'overnightOffered' => $overnightOffered,
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
        
        wiz_log("Location Sessions Sync: Processed $processed_rows rows, found data for " . count($location_data) . " unique locations");
        
        if (empty($location_data)) {
            wiz_log("Location Sessions Sync: No location data to process");
            return false;
        }
        
        // Get all existing locations by abbreviation in one query
        $location_codes = array_keys($location_data);
        $placeholders = implode(',', array_fill(0, count($location_codes), '%s'));
        $existing_locations = $wpdb->get_results(
            $wpdb->prepare("SELECT abbreviation FROM $table_name WHERE abbreviation IN ($placeholders)", $location_codes),
            ARRAY_A
        );
        $existing_abbreviations = array_column($existing_locations, 'abbreviation');
        
        // Update database with session weeks data
        $updated_count = 0;
        $error_count = 0;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($location_data as $location_code => $data) {
                // Check if location exists using the lookup array
                if (!in_array($location_code, $existing_abbreviations)) {
                    wiz_log("Location Sessions Sync Warning: Location code '$location_code' not found in database");
                    continue;
                }
                
                // Serialize the weeks data
                $session_weeks = serialize($data['weeks']);
                
                // Update the location record
                $result = $wpdb->update(
                    $table_name,
                    [
                        'sessionWeeks' => $session_weeks,
                        'locationUrl' => $data['url'],
                        'overnightOffered' => $data['overnightOffered']
                    ],
                    [
                        'abbreviation' => $location_code
                    ]
                );
                
                if ($result !== false) {
                    $updated_count++;
                } else {
                    $error_count++;
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        
        if ($error_count > 0) {
            wiz_log("Location Sessions Sync: Updated $updated_count locations, $error_count errors encountered");
        } else {
            wiz_log("Location Sessions Sync: Successfully updated $updated_count locations");
        }
        
        return $updated_count > 0;
        
    } catch (Exception $e) {
        wiz_log("Location Sessions Sync Error: " . $e->getMessage());
        return false;
    }
}

// Set daily cron to refresh locations
if (!wp_next_scheduled('wizPulse_refresh_locations_cron')) {
    wp_schedule_event(strtotime('05:00:00'), 'daily', 'wizPulse_refresh_locations_cron');
}
add_action('wizPulse_refresh_locations_cron', 'wizPulse_refresh_locations');




function wizPulse_get_all_courses()
{
    // Gets Pulse course data for iD Tech Camps and iD Tech Academies
    $apiURL = get_pulse_courses_api_url();
    $response = idemailwiz_iterable_curl_call($apiURL);
    return $response['response']['results'];
}

/**
 * Calculate the current fiscal year
 * Fiscal year runs from November to October (e.g., FY25/26 = Nov 2025 - Oct 2026)
 */
function wizPulse_get_current_fiscal_year()
{
    $current_date = new DateTime();
    $year = intval($current_date->format('Y'));
    $month = intval($current_date->format('n'));
    
    // If we're in November or December, FY is current_year / (current_year + 1)
    // If we're in January - October, FY is (current_year - 1) / current_year
    if ($month >= 11) {
        return $year . '/' . ($year + 1);
    } else {
        return ($year - 1) . '/' . $year;
    }
}

/**
 * Determine fiscal year from mustTurnMinAgeByDate
 * If date is 12/31/2025, course is for FY 2025/2026
 * 
 * @param string $date Date string in Y-m-d format
 * @return string|null Fiscal year string like "2025/2026" or null if invalid
 */
function wizPulse_get_fiscal_year_from_date($date)
{
    if (empty($date)) {
        return null;
    }
    
    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$date_obj) {
        return null;
    }
    
    // Extract the year from the date (should be end of year, 12/31/YYYY)
    $year = intval($date_obj->format('Y'));
    return $year . '/' . ($year + 1);
}

/**
 * Check if a course is active based on mustTurnMinAgeByDate
 * A course is active if its mustTurnMinAgeByDate matches the current fiscal year
 * 
 * @param string $mustTurnMinAgeByDate Date string
 * @return bool True if active, false otherwise
 */
function wizPulse_is_course_active($mustTurnMinAgeByDate)
{
    if (empty($mustTurnMinAgeByDate)) {
        return false;
    }
    
    $course_fy = wizPulse_get_fiscal_year_from_date($mustTurnMinAgeByDate);
    $current_fy = wizPulse_get_current_fiscal_year();
    
    return $course_fy === $current_fy;
}

/**
 * Sync courses from Pulse API to database
 * 
 * This function preserves manually set course_recs, courseUrl, and courseDesc values
 * by only updating fields that have actually changed from the API data.
 */
function wizPulse_map_courses_to_database()
{
    wiz_log("Starting Pulse courses sync from API...");
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_courses';

    try {
        // Get all courses
        $courses = wizPulse_get_all_courses();
        
        if (empty($courses)) {
            wiz_log("Course Sync: No courses returned from Pulse API");
            return;
        }
        
        wiz_log("Course Sync: Retrieved " . count($courses) . " courses from Pulse API");

        $processed_courses = [];
        $seen_abbreviations = [];
        $skipped_count = 0;

        foreach ($courses as $course) {

            // Skip if course abbreviation starts with "OLT" (OPL internal/old stuff)
            if (strpos($course['abbreviation'], 'OLT')) {
                $skipped_count++;
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
                    $firstRange = $course['catelogDateRanges'][0];
                    $startDate = date('Y-m-d', strtotime($firstRange['startDate']));
                    $endDate = date('Y-m-d', strtotime($firstRange['endDate']));
                }

                // Serialize genres, or set to NULL if empty
                $genres = !empty($course['genres']) ? serialize($course['genres']) : null;

                // Determine wizStatus based on mustTurnMinAgeByDate
                // If mustTurnMinAgeByDate matches current fiscal year, course is Active
                // Otherwise, it's Inactive
                $isActive = wizPulse_is_course_active($mustTurnMinAgeByDate);
                $wizStatus = $isActive ? 'Active' : 'Inactive';
                
                // Debug logging for first few courses
                static $logged_count = 0;
                if ($logged_count < 5) {
                    $course_fy = wizPulse_get_fiscal_year_from_date($mustTurnMinAgeByDate);
                    wiz_log("Course Sync Debug: {$clean_abbreviation} - mustTurnMinAgeByDate: " . ($mustTurnMinAgeByDate ?? 'NULL') . ", Course FY: " . ($course_fy ?? 'NULL') . ", wizStatus: {$wizStatus}");
                    $logged_count++;
                }

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
                    'pathwayLevelCredits' => $course['pathwayLevelCredits'],
                    'minAge' => $course['minAge'],
                    'maxAge' => $course['maxAge'],
                    'isNew' => $course['isNew'] ? 1 : 0,
                    'isMostPopular' => $course['isMostPopular'] ? 1 : 0,
                    'wizStatus' => $wizStatus
                ];

                $seen_abbreviations[$clean_abbreviation] = true;
            }
        }
        
        wiz_log("Course Sync: Processed " . count($processed_courses) . " unique courses, skipped $skipped_count OLT courses");
        wiz_log("Course Sync: Current fiscal year is: " . wizPulse_get_current_fiscal_year());

        // Insert/update courses in database
        if (!empty($processed_courses)) {
            // Get all existing courses in one query to avoid N+1 problem
            $course_ids = array_column($processed_courses, 'id');
            $placeholders = implode(',', array_fill(0, count($course_ids), '%d'));
            $existing_courses = $wpdb->get_results(
                $wpdb->prepare("SELECT id, course_recs, courseUrl, courseDesc FROM {$table_name} WHERE id IN ($placeholders)", $course_ids),
                ARRAY_A
            );
            
            // Create lookup array for existing courses
            $existing_courses_by_id = array();
            foreach ($existing_courses as $existing) {
                $existing_courses_by_id[$existing['id']] = $existing;
            }
            
            $insert_count = 0;
            $update_count = 0;
            $preserved_manual_values = 0;
            
            // Start transaction for better performance
            $wpdb->query('START TRANSACTION');
            
            try {
                foreach ($processed_courses as $course) {
                    $course_id = $course['id'];
                    
                    // Check if the course already exists and preserve manual values
                    if (isset($existing_courses_by_id[$course_id])) {
                        $existing_course = $existing_courses_by_id[$course_id];
                        
                        // Preserve manually set values
                        if (!empty($existing_course['course_recs'])) {
                            $course['course_recs'] = $existing_course['course_recs'];
                            $preserved_manual_values++;
                        }
                        
                        if (!empty($existing_course['courseUrl'])) {
                            $course['courseUrl'] = $existing_course['courseUrl'];
                            $preserved_manual_values++;
                        }
                        
                        if (!empty($existing_course['courseDesc'])) {
                            $course['courseDesc'] = $existing_course['courseDesc'];
                            $preserved_manual_values++;
                        }
                        
                        // Build update data with proper format specifiers
                        $update_data = array();
                        $update_formats = array();
                        
                        foreach ($course as $field => $value) {
                            if ($field !== 'id') { // Don't update the primary key
                                $update_data[$field] = $value;
                                
                                // Use proper format specifier based on field type
                                // Integer fields
                                if (in_array($field, ['isNew', 'isMostPopular', 'pathwayLevelCredits', 'minAge', 'maxAge', 'division_id'])) {
                                    $update_formats[] = '%d';
                                } else {
                                    // String fields (including NULL values - wpdb handles NULL correctly with %s)
                                    $update_formats[] = '%s';
                                }
                            }
                        }
                        
                        $result = $wpdb->update(
                            $table_name,
                            $update_data,
                            array('id' => $course_id),
                            $update_formats,
                            array('%d')
                        );
                        
                        if ($result !== false) { // FALSE means error, 0 means no rows affected (data was same)
                            $update_count++;
                            
                            // Debug logging for first update
                            static $update_logged = false;
                            if (!$update_logged && isset($update_data['wizStatus'])) {
                                wiz_log("Course Sync Debug: First update for course ID {$course_id} - wizStatus in update_data: {$update_data['wizStatus']}");
                                $update_logged = true;
                            }
                        } elseif ($result === false) {
                            wiz_log("Course Sync Error: Failed to update course ID {$course_id} - " . $wpdb->last_error);
                        }
                    } else {
                        // New course - insert directly
                        $result = $wpdb->insert($table_name, $course);
                        
                        if ($result) {
                            $insert_count++;
                        }
                    }
                }
                
                // Commit transaction
                $wpdb->query('COMMIT');
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $wpdb->query('ROLLBACK');
                throw $e;
            }
            
            $log_message = "Course Sync: Successfully processed courses - $insert_count new, $update_count updated";
            if ($preserved_manual_values > 0) {
                $log_message .= " - Preserved $preserved_manual_values manual values (course_recs, courseUrl, courseDesc)";
            }
            wiz_log($log_message);
        } else {
            wiz_log("Course Sync Warning: No courses to process after filtering");
        }
        
    } catch (Exception $e) {
        wiz_log("Course Sync Error: " . $e->getMessage());
    }
}

//Run cron daily to refresh courses
if (!wp_next_scheduled('wizPulse_refresh_courses_cron')) {
    wp_schedule_event(strtotime('05:00:00'), 'daily', 'wizPulse_refresh_courses_cron');
}
add_action('wizPulse_refresh_courses_cron', 'wizPulse_refresh_courses');

function wizPulse_refresh_courses()
{
    wiz_log("=== Starting Course Refresh Process ===");
    
    // Set a reasonable timeout for the entire process
    set_time_limit(300); // 5 minutes
    
    try {
        // Clear any existing database locks
        global $wpdb;
        $wpdb->query("SET SESSION wait_timeout = 300");
        $wpdb->query("SET SESSION interactive_timeout = 300");
        
        wizPulse_map_courses_to_database();
        
        wiz_log("Course Refresh Complete: Successfully updated courses");
    } catch (Exception $e) {
        wiz_log("Course Refresh Error: " . $e->getMessage());
    }
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
    return wizPulse_refresh_locations();
}

// Function to sync course capacity data from Pulse Marketing API
function wizPulse_sync_course_capacity($location_ids = [], $mic_start_date = null)
{
    wiz_log("=== Starting Course Capacity Sync ===");
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_course_capacity';
    
    try {
        // Check if the course capacity table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            wiz_log("Course Capacity Sync Error: Database table '$table_name' does not exist. Please reactivate the plugin to create the required database tables.");
            return [
                'error' => 'Database table missing',
                'message' => 'Course capacity table does not exist. Plugin reactivation required.'
            ];
        }
        
        // Get API settings
        $api_key = get_pulse_marketing_api_key();
        if (empty($api_key)) {
            wiz_log("Course Capacity Sync Error: No Pulse Marketing API key configured");
            return false;
        }
        
        // Set default date if not provided (today)
        if (empty($mic_start_date)) {
            $today = new DateTime('today');
            $mic_start_date = $today->format('Y-m-d');
        }
        
        wiz_log("Course Capacity Sync: Using start date $mic_start_date with API key");
        
        // If no location IDs provided, get all location IDs from our database
        if (empty($location_ids)) {
            $locations_table = $wpdb->prefix . 'idemailwiz_locations';
            
            // Check if locations table exists too
            $locations_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$locations_table'");
            if (!$locations_table_exists) {
                wiz_log("Course Capacity Sync Error: Locations table '$locations_table' does not exist. Please reactivate the plugin.");
                return [
                    'error' => 'Locations table missing', 
                    'message' => 'Locations table does not exist. Plugin reactivation required.'
                ];
            }
            
            $locations = $wpdb->get_results("SELECT id FROM $locations_table WHERE locationStatus != 'Closed'", ARRAY_A);
            $location_ids = array_column($locations, 'id');
        }
        
        if (empty($location_ids)) {
            wiz_log("Course Capacity Sync Error: No location IDs found to sync");
            return false;
        }
        
        wiz_log("Course Capacity Sync: Processing " . count($location_ids) . " locations");
        
        $total_sessions = 0;
        $total_errors = 0;
        $processed_locations = 0;
        $total_deleted = 0;
        
        // Delete ALL existing course capacity data
        // This ensures we start with a completely clean slate
        $delete_result = $wpdb->query("DELETE FROM $table_name");
        
        if ($delete_result !== false) {
            $total_deleted = $delete_result;
            wiz_log("Course Capacity Sync: Deleted ALL $total_deleted existing sessions from the table");
        } else {
            wiz_log("Course Capacity Sync Warning: Delete operation failed - " . $wpdb->last_error);
        }
        
        foreach ($location_ids as $location_id) {
            try {
                // Build API URL
                $api_url = get_pulse_course_capacity_api_url();
                $params = [
                    'marketingApiKey' => $api_key,
                    'locationID' => $location_id,
                    'minStartDate' => $mic_start_date
                ];
                $full_url = $api_url . '?' . http_build_query($params);
                
                // Make API call
                $response = idemailwiz_iterable_curl_call($full_url, null, false, 3, 5);
                
                if (!$response || !isset($response['response'])) {
                    wiz_log("Course Capacity Sync Warning: No response for location ID $location_id");
                    $total_errors++;
                    continue;
                }
                
                $capacity_data = $response['response'];
                
                // Track which sessions are returned by the API for this location
                $location_sessions = 0;
                
                if (!empty($capacity_data) && is_array($capacity_data)) {
                    foreach ($capacity_data as $session) {
                        try {
                            // Normalize the session start date for consistent key generation
                            $normalized_session_date = null;
                            if (!empty($session['sessionStartDate'])) {
                                $normalized_session_date = date('Y-m-d H:i:s', strtotime($session['sessionStartDate']));
                            }
                            
                            // Prepare session data for database
                            $session_data = [
                                'divisionID' => $session['divisionID'] ?? null,
                                'divisionName' => $session['divisionName'] ?? '',
                                'locationID' => $session['locationID'] ?? $location_id,
                                'locationName' => $session['locationName'] ?? '',
                                'locationShortName' => $session['locationShortName'] ?? '',
                                'locationPageURL' => $session['locationPageURL'] ?? '',
                                'city' => $session['city'] ?? '',
                                'state' => $session['state'] ?? '',
                                'postalCode' => $session['postalCode'] ?? '',
                                'country' => $session['country'] ?? '',
                                'termID' => $session['termID'] ?? null,
                                'termName' => $session['termName'] ?? '',
                                'productID' => $session['productID'] ?? null,
                                'productName' => $session['productName'] ?? '',
                                'coursePageURL' => $session['coursePageURL'] ?? '',
                                'sessionStartDate' => $normalized_session_date,
                                'courseStartDate' => !empty($session['courseStartDate']) ? date('Y-m-d H:i:s', strtotime($session['courseStartDate'])) : null,
                                'minimumAge' => $session['minimumAge'] ?? null,
                                'maximumAge' => $session['maximumAge'] ?? null,
                                'courseCapacityTotal' => $session['courseCapacityTotal'] ?? 0,
                                'courseSeatsLeft' => $session['courseSeatsLeft'] ?? 0,
                                'sync_date' => date('Y-m-d')
                            ];
                            
                            // Use REPLACE to handle unique constraint conflicts
                            $result = $wpdb->replace(
                                $table_name,
                                $session_data,
                                [
                                    '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
                                    '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d',
                                    '%d', '%s'
                                ]
                            );
                            
                            if ($result === false) {
                                wiz_log("Course Capacity Sync Error: Database insert failed for location $location_id session - " . $wpdb->last_error);
                                $total_errors++;
                            } else {
                                $location_sessions++;
                                $total_sessions++;
                            }
                            
                        } catch (Exception $e) {
                            wiz_log("Course Capacity Sync Error processing session for location $location_id: " . $e->getMessage());
                            $total_errors++;
                        }
                    }
                }
                
                // Log summary for this location
                if ($location_sessions > 0) {
                    wiz_log("Course Capacity Sync: Location $location_id: $location_sessions sessions synced");
                    $processed_locations++;
                }
                
            } catch (Exception $e) {
                wiz_log("Course Capacity Sync Error for location $location_id: " . $e->getMessage());
                $total_errors++;
            }
        }
        
        $success_message = "Course Capacity Sync Complete: ";
        $success_message .= "Processed $processed_locations locations, ";
        $success_message .= "synced $total_sessions sessions, ";
        $success_message .= "deleted $total_deleted sold-out sessions";
        
        if ($total_errors > 0) {
            $success_message .= ", $total_errors errors encountered";
        }
        
        wiz_log($success_message);
        
        return [
            'locations_processed' => $processed_locations,
            'sessions_synced' => $total_sessions,
            'sessions_deleted' => $total_deleted,
            'errors' => $total_errors
        ];
        
    } catch (Exception $e) {
        wiz_log("Course Capacity Sync Error: " . $e->getMessage());
        return false;
    }
}

// Function to get locations with low seat availability
function wizPulse_get_low_availability_sessions($seats_threshold = 3, $days_ahead = 30)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_course_capacity';
    
    $future_date = date('Y-m-d', strtotime("+$days_ahead days"));
    
    $query = $wpdb->prepare("
        SELECT *
        FROM $table_name 
        WHERE courseSeatsLeft <= %d 
        AND courseSeatsLeft > 0
        AND sessionStartDate >= NOW()
        AND sessionStartDate <= %s
        AND sync_date = CURDATE()
        ORDER BY sessionStartDate ASC, courseSeatsLeft ASC
    ", $seats_threshold, $future_date);
    
    return $wpdb->get_results($query, ARRAY_A);
}

// Set up cron job for course capacity sync based on settings
function wizPulse_schedule_course_capacity_sync()
{
    $hook = 'wizPulse_course_capacity_sync_cron';
    
    if (!is_sync_enabled('course_capacity')) {
        // If sync is disabled, clear any existing schedule
        if (wp_next_scheduled($hook)) {
            wp_clear_scheduled_hook($hook);
            wiz_log("Course Capacity Sync: Disabled in settings, clearing schedule");
        }
        return;
    }
    
    $frequency = get_course_capacity_sync_frequency();
    
    // Only schedule if not already scheduled
    if (!wp_next_scheduled($hook)) {
        wiz_log("Course Capacity Sync: Scheduling with frequency: $frequency");
        
        // Schedule based on frequency setting
        switch ($frequency) {
            case 'hourly':
                wp_schedule_event(time(), 'hourly', $hook);
                break;
            case 'every_6_hours':
                wp_schedule_event(time(), 'every_six_hours', $hook);
                break;
            case 'daily':
                wp_schedule_event(time(), 'daily', $hook);
                break;
            case 'manual':
            default:
                wiz_log("Course Capacity Sync: Set to manual mode, no automatic scheduling");
                break;
        }
    }
}

// Function to manually trigger course capacity sync (for testing)
function wizPulse_manual_course_capacity_sync()
{
    wiz_log("Manual Course Capacity Sync triggered");
    return wizPulse_sync_course_capacity();
}

// Cron action hook
add_action('wizPulse_course_capacity_sync_cron', 'wizPulse_sync_course_capacity');

// Initialize scheduling on plugin load
add_action('init', 'wizPulse_schedule_course_capacity_sync');

// Reschedule when settings are updated
add_action('update_option_idemailwiz_settings', 'wizPulse_reschedule_course_capacity_sync');
function wizPulse_reschedule_course_capacity_sync()
{
    $hook = 'wizPulse_course_capacity_sync_cron';
    
    // Clear existing schedule to force rescheduling with new settings
    if (wp_next_scheduled($hook)) {
        wp_clear_scheduled_hook($hook);
    }
    
    // Reschedule with new settings
    wizPulse_schedule_course_capacity_sync();
}
