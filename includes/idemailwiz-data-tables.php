<?php
add_action('wp_ajax_idwiz_handle_dt_states', 'idwiz_handle_dt_states');

function idwiz_handle_dt_states() {
    try {
        // Verify nonce
        if (!check_ajax_referer('data-tables', 'security', false)) {
            throw new Exception('Invalid nonce');
        }

        // Get current user
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not authenticated');
            wp_die();
        }

        // Get action type and stateRestore object from request
        $action = sanitize_text_field($_POST['dataTableAction']);
        $state_restore = isset($_POST['stateRestore']) ? $_POST['stateRestore'] : null;

        // Log state_restore object for debugging
        error_log(print_r($state_restore, true));

        // Fetch existing states from user meta
        $existing_states = get_user_meta($user_id, 'data_table_states', true) ?: [];

        // Handle based on action type
        switch ($action) {
            case 'load':
                if (empty($existing_states)) {
                    wp_send_json_error('No existing states');
                } else {
                    // JSON-decode each saved state
                    foreach ($existing_states as $name => $state) {
                        $existing_states[$name] = json_decode($state, true);
                    }
                    wp_send_json_success($existing_states);
                }
                wp_die();
                break;

            case 'save':
                $state_name = key($state_restore);
                $existing_states[$state_name] = json_encode($state_restore[$state_name]);  // JSON-encode before saving
                update_user_meta($user_id, 'data_table_states', $existing_states);
                wp_send_json_success('State saved successfully');
                wp_die();
                break;

            case 'rename':
                $old_state_name = key($state_restore);
                $new_state_name = $state_restore[$old_state_name];
                if (isset($existing_states[$old_state_name])) {
                    $existing_states[$new_state_name] = $existing_states[$old_state_name];
                    unset($existing_states[$old_state_name]);
                    update_user_meta($user_id, 'data_table_states', $existing_states);
                    wp_send_json_success('State renamed successfully');
                } else {
                    wp_send_json_error('Old state name not found');
                }
                wp_die();
                break;

            case 'remove':
                $state_name = key($state_restore);
                if (isset($existing_states[$state_name])) {
                    unset($existing_states[$state_name]);
                    update_user_meta($user_id, 'data_table_states', $existing_states);
                    wp_send_json_success('State removed successfully');
                } else {
                    wp_send_json_error('State name not found');
                }
                wp_die();
                break;

            default:
                wp_send_json_error('Invalid action');
                wp_die();
                break;
        }
    } catch (Exception $e) {
        // Log the error message for debugging
        error_log($e->getMessage());

        // Return the error message in the JSON response
        wp_send_json_error(['message' => $e->getMessage()]);
        wp_die();
    }
}
