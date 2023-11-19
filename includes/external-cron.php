<?php

// Parse request for the external cron endpoint
function idemailwiz_parse_external_cron_request($wp) {
    //error_log('Parsing external cron request...');
    
    $wizOptions = get_option('idemailwiz_settings');
    $cronApi = $wizOptions['external_cron_api'] ?? ''; 
    $cronSyncType = $wizOptions['sync_method'] ?? 'wp_cron'; 

    // Check if the external-cron endpoint is being accessed
    if (isset($wp->query_vars['external-cron'])) {
        // Check if the API key matches
        //$incomingApiKey = $_GET['Api-Key'] ?? ''; 
        $incomingApiKey = $_SERVER['WIZ_API_KEY'] ?? ''; 

        if ($incomingApiKey == $cronApi) {
            if ($cronSyncType == 'ext_cron') {
                do_idwiz_external_cron_actions($_GET);
            } else {
               wiz_log('External cron sync is turned off in the settings. Sync should be occuring via wp_cron instead');
            }
        } else {
            error_log('Invalid API key for external cron sync.');
            status_header(403); 
            exit;
        }
    }
}
add_action('parse_request', 'idemailwiz_parse_external_cron_request', 0);

function do_idwiz_external_cron_actions($args) {
    // Default to the sync action unless otherwise specified
    $action = $args['action'] ?? 'sync';

    // Initiate database sync, if cron sync is on
    wiz_log('Cron sync initiated, starting sync sequence...');
    error_log('Cron sync initiated, starting sync sequence...');
    idemailwiz_process_sync_sequence();
}