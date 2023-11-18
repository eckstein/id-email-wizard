<?php

function wiz_log($something, $timestamp = true)
{

    // Get the current date and time in PST
    $date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
    $timestamp = $date->format('Y-m-d H:i:s');


    // Build the breaker bar with the timestamp
    $breakerBar = str_repeat('=', 40); // 40 is the length of the bar
    if ($timestamp) {
        $timestampPosition = (strlen($breakerBar) - strlen($timestamp)) / 2;
        $breakerBarWithTimestamp = substr_replace($breakerBar, "[$timestamp]", $timestampPosition, 0);

        // Build the log entry
        $logEntry = "{$breakerBarWithTimestamp}\n$something\n$breakerBar\n\n";
    } else {
        // Build the log entry
        $logEntry = "{$breakerBar}\n$something\n$breakerBar\n\n";
    }


    // Replace line breaks with <br/> tags
    $logEntry = nl2br($logEntry);

    // Get the path to the log file
    $logFile = dirname(plugin_dir_path(__FILE__)) . '/sync-log.txt';

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
    $timestamp = $_POST['timestamp'] ?? false;

    $writeToLog = wiz_log($logData, $timestamp);

    wp_send_json_success($writeToLog);
}
add_action('wp_ajax_ajax_to_wiz_log', 'ajax_to_wiz_log');