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




function wizPulse_get_all_courses() {
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

    foreach ($courses as $course) {
        $id = $course['id'];
        $title = $course['title'];
        $abbreviation = $course['abbreviation'];

        // Serialize locations, or set to NULL if empty
        $locations = !empty($course['locations']) ? serialize($course['locations']) : null;

        // Allow mustTurnMinAgeByDate to be null
        $mustTurnMinAgeByDate = !empty($course['mustTurnMinAgeByDate'])
        ? date('Y-m-d', strtotime($course['mustTurnMinAgeByDate']))
        : null;

        $division_id = $course['division']['id'];

        // Handle catelogDateRanges
        $startDate = null;
        $endDate = null;
        if (!empty($course['catelogDateRanges'])) {
            $startDate = date('Y-m-d', strtotime($course['catelogDateRanges'][0]['startDate']));
            $endDate = date('Y-m-d', strtotime($course['catelogDateRanges'][0]['endDate']));
        }

        // Handle genres
        $genres = array();
        foreach ($course['genres'] as $genre) {
            $genres[] = $genre['id'];
        }
        // Serialize genres, or set to NULL if empty
        $genres = !empty($genres) ? serialize($genres) : null;

        $pathwayLevelCredits = $course['pathwayLevelCredits'];
        $minAge = $course['minAge'];
        $maxAge = $course['maxAge'];
        $isNew = $course['isNew'] ? 1 : 0;
        $isMostPopular = $course['isMostPopular'] ? 1 : 0;

        // Determine wizStatus
        $wizStatus = (empty($course['locations']) && empty($course['catelogDateRanges'])) ? 'inactive' : 'active';

        // Insert or update the data
        $wpdb->replace(
            $table_name,
            array(
                'id' => $id,
                'title' => $title,
                'abbreviation' => $abbreviation,
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
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s')
        );
    }
}