<?php





add_action('wp_ajax_get_template_data_profile_ajax', 'get_template_data_profile_ajax');
function get_template_data_profile_ajax()
{
    // Verify the nonce for security.
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    if (!wp_verify_nonce($nonce, 'template-editor')) {
        wp_send_json_error(['message' => 'Nonce verification failed']);
        return;
    }

    $profileId = $_POST['profileId'];
    $profile = get_template_data_profile($profileId);
    if (!$profile) {
        wp_send_json_error('Profile not found');
    }
    wp_send_json_success(['templateData' => $profile]);
}

function get_template_data_profile($profileId)
{
    $allProfiles = get_template_data_profiles();
    foreach ($allProfiles as $profile) {
        if ($profile['WizProfileId'] == $profileId) {
            return $profile;
        }
    }
}

function get_template_data_profiles()
{
    // Use ABSPATH to get the WordPress root directory
    $path = ABSPATH . 'wp-content/plugins/id-email-wizard/builder-v2/json-test-data/profiles/';

    // Ensure forward slashes for consistency
    $path = str_replace('\\', '/', $path);

    // Find all JSON files in the profiles directory
    $json_files = glob($path . '*.json');

    $profiles = [];
    foreach ($json_files as $json_file) {
        $json_data = file_get_contents($json_file);
        if ($json_data === false) {
            error_log('Failed to read file: ' . $json_file);
            continue;
        }

        $decoded_data = json_decode($json_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Failed to decode JSON in file: ' . $json_file . '. Error: ' . json_last_error_msg());
            continue;
        }

        $profiles[] = $decoded_data;
    }

    return $profiles;
}
