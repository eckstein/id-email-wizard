<?php

function wiz_log($something, $timestamp = true) {
    // Get the current date and time in PST
    $date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
    $formattedTimestamp = $date->format('m/d/Y g:ia');

    // Build the log entry
    $logEntry = $timestamp ? "[$formattedTimestamp] $something\n" : "$something\n";

    // Get the path to the log file
    $logFile = dirname(plugin_dir_path(__FILE__)) . '/wiz-log.log';

    // Read the existing content of the file
    $existingContent = file_exists($logFile) ? file_get_contents($logFile) : '';

    // Prepend the new log entry to the existing content
    $combinedContent = $logEntry . $existingContent;

    // Write the combined content back to the file
    $writeToLog = file_put_contents($logFile, $combinedContent);

    return $writeToLog; // returns number of bytes logged or false on failure
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
    $includeTimestamp = $_POST['timestamp'] ?? false;

    $writeToLog = wiz_log($logData, $includeTimestamp);

    wp_send_json_success($writeToLog);
}
add_action('wp_ajax_ajax_to_wiz_log', 'ajax_to_wiz_log');