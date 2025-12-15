<?php
add_action('wp_ajax_get_wiztemplate_with_ajax', 'get_wiztemplate_with_ajax');
function get_wiztemplate_with_ajax()
{
    check_ajax_referer('template-editor', 'security');
    $postId = $_POST['template_id'];
    if (! $postId) {
        wp_send_json_error(['message' => 'No post ID provided']);
        return;
    }
    $templateData = get_wiztemplate($postId);
    wp_send_json_success($templateData);
}

function get_wiztemplate($postId)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wiz_templates';

    $templateData = [];

    // Fetch the entire template data JSON from the database
    $templateDataJSON = $wpdb->get_var($wpdb->prepare(
        "SELECT template_data FROM $table_name WHERE post_id = %d",
        $postId
    ));

    // Decode JSON string into an associative array
    if ($templateDataJSON) {
        $templateData = json_decode($templateDataJSON, true);
    }

    return $templateData;
}

function get_wiztemplate_object($postId)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wiz_templates';
    // Fetch the entire template data from the database
    $templateObject = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE post_id = %d",
        $postId
    ), ARRAY_A);

    return $templateObject;
}


add_action('wp_ajax_idemailwiz_save_template_title', 'idemailwiz_save_template_title');
function idemailwiz_save_template_title()
{
    // Check for nonce for security
    $nonce = $_POST['security'] ?? '';
    if (! wp_verify_nonce($nonce, 'template-editor')) {
        wp_send_json_error(['message' => 'Nonce verification failed']);
        return;
    }
    $templateId = $_POST['template_id'];
    $templateTitle = $_POST['template_title'];
    if (! $templateId || ! $templateTitle) {
        wp_send_json_error(['message' => 'Invalid template ID or template title']);
        return;
    }
    $updateTitle = wp_update_post(['ID' => $templateId, 'post_title' => $templateTitle, 'post_name' => sanitize_title($templateTitle)]);
    if ($updateTitle) {
        wp_send_json_success(['message' => 'Template title updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Template title update failed']);
    }
}



add_action('wp_ajax_idemailwiz_save_template_session_to_transient', 'idemailwiz_save_template_session_to_transient');
function idemailwiz_save_template_session_to_transient()
{
    // Validate AJAX and nonce
    if (! wp_doing_ajax() || ! check_ajax_referer('template-editor', 'security', false)) {
        wp_die();
    }

    // Validate POST action
    if ($_POST['action'] !== 'idemailwiz_save_template_session_to_transient') {
        wp_die();
    }

    $templateId = $_POST['templateid'];

    $transientKey = 'template_data_' . $templateId;

    delete_transient($transientKey);

    // Check if template data is passed in the request
    if (isset($_POST['template_data'])) {
        // Use the template data from the request
        $templateData = json_decode(stripslashes($_POST['template_data']), true);

        // Store the template data using a transient

        set_transient($transientKey, $templateData, 10);


        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}


add_action('wp_ajax_save_wiz_template_data', 'prepare_wiztemplate_for_save');
function prepare_wiztemplate_for_save()
{
    // Check nonce for security
    check_ajax_referer('template-editor', 'security');

    $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
    $userId = get_current_user_id();
    $templateData = isset($_POST['template_data']) ? json_decode(stripslashes($_POST['template_data']), true) : '';

    // Save the template data
    save_template_data($postId, $userId, $templateData);

    wp_send_json_success(['message' => 'Template saved successfully', 'templateData' => $templateData]);
}



// Save the template data from the ajax call
function save_template_data($postId, $userId, $templateData)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wiz_templates';

    // Convert to array if it's a JSON string
    if (is_string($templateData)) {
        $templateData = json_decode($templateData, true);
    }

    // Check for an existing record and preserve sync history
    $existingRecordId = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE post_id = %d", $postId));
    
    if ($existingRecordId) {
        // Get existing template data to preserve sync history
        $existingDataJSON = $wpdb->get_var($wpdb->prepare(
            "SELECT template_data FROM $table_name WHERE id = %d",
            $existingRecordId
        ));
        
        if ($existingDataJSON) {
            $existingData = json_decode($existingDataJSON, true);
            
            // Preserve iterable sync history (not collected from form fields)
            $existingSyncHistory = $existingData['template_options']['template_settings']['iterable-sync']['synced_templates_history'] ?? [];
            $existingPrimaryId = $existingData['template_options']['template_settings']['iterable-sync']['iterable_template_id'] ?? '';
            
            if (!empty($existingSyncHistory) || !empty($existingPrimaryId)) {
                // Ensure the nested structure exists
                if (!isset($templateData['template_options']['template_settings']['iterable-sync'])) {
                    $templateData['template_options']['template_settings']['iterable-sync'] = [];
                }
                
                // Preserve sync history if not already set in incoming data
                if (empty($templateData['template_options']['template_settings']['iterable-sync']['synced_templates_history'])) {
                    $templateData['template_options']['template_settings']['iterable-sync']['synced_templates_history'] = $existingSyncHistory;
                }
                
                // Preserve primary ID if incoming is empty but existing has a value
                if (empty($templateData['template_options']['template_settings']['iterable-sync']['iterable_template_id']) && !empty($existingPrimaryId)) {
                    $templateData['template_options']['template_settings']['iterable-sync']['iterable_template_id'] = $existingPrimaryId;
                }
            }
        }
    }

    // Prepare data for database insertion
    $data = [
        'last_updated' => date('Y-m-d H:i:s'),
        'post_id' => $postId,
        'template_data' => json_encode($templateData)
    ];

    if ($existingRecordId) {
        // Update existing record
        $wpdb->update($table_name, $data, ['id' => $existingRecordId], ['%s', '%d', '%s'], ['%d']);
    } else {
        // Insert new record
        $wpdb->insert($table_name, $data, ['%s', '%d', '%s']);
    }

    // Check for and log any database errors
    if (! empty($wpdb->last_error)) {
        error_log("WordPress database error: " . $wpdb->last_error);
    }
}
