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
        $wpdb->replace(
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
    }
}

// Function to refresh locations in the database
function wizPulse_refresh_locations()
{
    wizPulse_map_locations_to_database();
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
            // Check if the course already exists and has course_recs
            $existing_course = $wpdb->get_row($wpdb->prepare("SELECT course_recs FROM {$table_name} WHERE id = %d", $course['id']));
            
            // If the course exists and has course_recs, preserve that data
            if ($existing_course && !empty($existing_course->course_recs)) {
                $course['course_recs'] = $existing_course->course_recs;
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
