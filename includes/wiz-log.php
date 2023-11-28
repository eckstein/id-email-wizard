<?php

// function wiz_log($something, $timestamp = true) {
//     // Get the current date and time in PST
//     $date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
//     $formattedTimestamp = $date->format('m/d/Y g:ia');

//     // Build the log entry
//     $logEntry = $timestamp ? "[$formattedTimestamp] $something\n" : "$something\n";

//     // Get the path to the log file
//     $logFile = dirname(plugin_dir_path(__FILE__)) . '/wiz-log.log';

//     // Read the existing content of the file
//     $existingContent = file_exists($logFile) ? file_get_contents($logFile) : '';

//     // Prepend the new log entry to the existing content
//     $combinedContent = $logEntry . $existingContent;

//     // Write the combined content back to the file
//     $writeToLog = file_put_contents($logFile, $combinedContent);

//     return $writeToLog; // returns number of bytes logged or false on failure
// }


function wiz_log($message) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_wiz_log';

    // Prepare data for insertion
    $data = array(
        'timestamp' => microtime(true), // Unix timestamp with microseconds
        'message'   => $message
    );

    // Insert data into the database
    $insertLog = $wpdb->insert($table_name, $data);
    return $insertLog;
}


function get_wiz_log($limit = 1000, $startTimestamp = null, $endTimestamp = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_wiz_log';

    // Prepare the query
    $query = "SELECT * FROM $table_name";

    // Add time range filters if provided
    $conditions = [];
    if ($startTimestamp) {
        $conditions[] = $wpdb->prepare("timestamp >= %d", $startTimestamp);
    }
    if ($endTimestamp) {
        $conditions[] = $wpdb->prepare("timestamp <= %d", $endTimestamp);
    }
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    // Add order and limit
    $query .= " ORDER BY timestamp DESC LIMIT %d";

    // Fetch log entries
    $logs = $wpdb->get_results($wpdb->prepare($query, $limit), ARRAY_A);

    // Format the output
    $output = '';
    if (!empty($logs)) {
        foreach ($logs as $log) {
            // Split the timestamp into seconds and microseconds
            list($secs, $microsecs) = explode('.', $log['timestamp']);
            $date = new DateTime("@$secs"); // @ symbol tells DateTime it's a Unix timestamp
            $date->setTimeZone(new DateTimeZone('America/Los_Angeles')); // Adjust timezone as needed

            // Format the date and append microseconds
            $formattedDate = $date->format('m-d-Y g:ia') . sprintf('.%06d', $microsecs);

            $output .= $formattedDate . " - " . $log['message'] . "\n";
        }
    } else {
        $output = "Log is empty!";
    }

    return $output;

}

add_action('wp_ajax_refresh_wiz_log', 'refresh_wiz_log_callback');

function refresh_wiz_log_callback() {
    check_ajax_referer('id-general', 'security');

    $logContent = get_wiz_log();
    wp_send_json_success($logContent); // Send JSON response
}



function ajax_to_wiz_log()
{

    // Bail early without valid nonce
    if (
        check_ajax_referer('data-tables', 'security', false) ||
        check_ajax_referer('initiatives', 'security', false) ||
        check_ajax_referer('wiz-metrics', 'security', false) ||
        check_ajax_referer('id-general', 'security', false)
    ) {
        // Nonce is valid and belongs to one of the specified referers
    } else {
        // Invalid nonce or referer
        wp_die('Invalid action or nonce');
    }

    $logData = $_POST['log_data'] ?? '';

    $writeToLog = wiz_log($logData);

    if ($writeToLog === false) {
        wp_send_json_error('Error writing to wiz log database');
    } else {
        wp_send_json_success($writeToLog);
    }
}
add_action('wp_ajax_ajax_to_wiz_log', 'ajax_to_wiz_log');