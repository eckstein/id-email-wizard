<?php

// Parse request for the external cron endpoint
function idemailwiz_parse_external_cron_request($wp)
{
    //error_log('Parsing external cron request...');

    $wizOptions = get_option('idemailwiz_settings');
    $cronApi = $wizOptions['external_cron_api'] ?? '';
    $cronSyncType = $wizOptions['sync_method'] ?? 'wp_cron';

    // Check if the external-cron endpoint is being accessed
    if (isset($wp->query_vars['external-cron'])) {
        // Check if the API key matches
        $incomingApiKey = $_SERVER['HTTP_WIZ_API_KEY'] ?? '';

        if ($incomingApiKey == $cronApi) {
            if ($cronSyncType == 'ext_cron') {
                do_idwiz_external_cron_actions($_GET);
            } else {
                wiz_log('External cron sync is turned off in the settings. Sync should be occuring via wp_cron instead');
                // Sync disabled response
                echo "External cron sync is disabled in settings.";
                status_header(503); // Service Unavailable
                exit;
            }
        } else {
            error_log('Invalid API key for external cron sync.');
            // Invalid API key response
            echo "Invalid API key for external cron sync.";
            status_header(403); // Forbidden
            exit;
        }
    }
}
add_action('parse_request', 'idemailwiz_parse_external_cron_request', 0);


function do_idwiz_external_cron_actions($args)
{
    // Determine action
    if ($args['action'] == 'sync') {
        // Initiate database sync, if external cron sync is on
        $startSync = idemailwiz_process_sync_sequence();
        if ($startSync) {
            wiz_log('External cron sync initiated, checking sync settings...');
            // Send a success response
            status_header(200);
            exit;
        } else {
            wiz_log('External cron sync initiated but a previous sync is already in progress. Skipping sync sequence...');
            status_header(503); // Service Unavailable
            exit;
        }
    } else if ($args['action'] == 'startJobs') {
        // Load up our transients with job Ids for the next triggered sync
        idemailwiz_start_export_jobs();
        status_header(200);
        exit;
    }

}