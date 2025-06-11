<?php
add_action('admin_menu', 'idemailwiz_settings_page');
function idemailwiz_settings_page()
{
    add_menu_page('ID Email Wiz Settings', 'Wiz Settings', 'manage_options', 'idemailwiz_settings', 'idemailwiz_settings_page_content');
}

function idemailwiz_settings_page_content()
{
    ?>
    <div class="wrap">
        <h2>ID Email Wiz Settings</h2>
        <p>Editing these endpoint will not necessarily break anything, but some strange behavior may be encountered,
            especially related to trashed templates.
            <br />If you change these, make sure to go to Settings > Permalinks and hit the save button (sometimes twice) to
            refresh the permalinks cache.
        </p>
        
        <style>
        .idemailwiz-sync-settings {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .idemailwiz-sync-settings h3 {
            margin-top: 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .sync-setting-group {
            margin-bottom: 25px;
        }
        .sync-setting-group h4 {
            margin-bottom: 10px;
            color: #0073aa;
        }
        .sync-status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-left: 10px;
        }
        .sync-status-indicator.enabled {
            background-color: #46b450;
        }
        .sync-status-indicator.disabled {
            background-color: #dc3232;
        }
        </style>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('idemailwiz_settings_group');
            do_settings_sections('idemailwiz_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}


add_action('admin_init', 'idemailwiz_register_settings');
function idemailwiz_register_settings()
{
    register_setting('idemailwiz_settings_group', 'idemailwiz_settings', 'idemailwiz_settings_sanitize');
    add_settings_section('idemailwiz_main_section', 'Main Settings', null, 'idemailwiz_settings');
    add_settings_section('idemailwiz_sync_section', 'Data Sync Settings', 'idemailwiz_sync_section_callback', 'idemailwiz_settings');

    // WP Site Setup fields
    add_settings_field('folder_base', 'Folders Root', 'idemailwiz_render_dropdown_taxonomy_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'folder_base', 'taxonomy' => 'idemailwiz_folder'));
    add_settings_field('base_template_folder', 'Base Templates Folder', 'idemailwiz_render_dropdown_taxonomy_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'base_templates_term', 'taxonomy' => 'idemailwiz_folder'));
    add_settings_field('folder_trash', 'Trash Folder', 'idemailwiz_render_dropdown_taxonomy_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'folder_trash', 'taxonomy' => 'idemailwiz_folder'));
    add_settings_field('dashboard_page', 'Dashboard Page', 'idemailwiz_render_dropdown_pages_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'dashboard_page'));
    add_settings_field('campaigns_page', 'Campaigns Page', 'idemailwiz_render_dropdown_pages_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'campaigns_page'));
    add_settings_field('reports_page', 'Reports Page', 'idemailwiz_render_dropdown_pages_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'reports_page'));
    add_settings_field('experiments_page', 'Experiments Page', 'idemailwiz_render_dropdown_pages_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'experiments_page'));
    add_settings_field('external_cron_api', 'API key for external auth', 'idemailwiz_render_text_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'external_cron_api'));
    add_settings_field('wysiwyg_builder_version', 'WYSIWYG Builder Version', 'idemailwiz_render_radio_field', 'idemailwiz_settings', 'idemailwiz_main_section', array(
        'option_name' => 'wysiwyg_builder_version',
        'options' => array('v1' => 'V1', 'v2' => 'V2')
    ));

    // Data Sync Settings Section
    // API Keys and Credentials
    add_settings_field('iterable_api_key', 'Iterable API Key', 'idemailwiz_render_text_field', 'idemailwiz_settings', 'idemailwiz_sync_section', array(
        'option_name' => 'iterable_api_key',
        'description' => 'Enter your Iterable API key for campaign and engagement data sync'
    ));
    add_settings_field('pulse_marketing_api_key', 'Pulse Marketing API Key', 'idemailwiz_render_text_field', 'idemailwiz_settings', 'idemailwiz_sync_section', array(
        'option_name' => 'pulse_marketing_api_key',
        'description' => 'API key for Pulse course capacity endpoint (currently: ZacZac)',
        'default' => 'ZacZac'
    ));
    
    // Google Sheets URLs
    add_settings_field('ga_rev_sheet_url', 'GA Revenue Sheet URL', 'idemailwiz_render_text_field', 'idemailwiz_settings', 'idemailwiz_sync_section', array(
        'option_name' => 'ga_rev_sheet_url',
        'description' => 'SheetDB URL for Google Analytics revenue data'
    ));
    add_settings_field('ga_revenue_api_sheet_bearer_token', 'GA Revenue Bearer Token', 'idemailwiz_render_text_field', 'idemailwiz_settings', 'idemailwiz_sync_section', array(
        'option_name' => 'ga_revenue_api_sheet_bearer_token',
        'description' => 'Bearer token for GA revenue API authentication'
    ));
    add_settings_field('location_sessions_sheet_url', 'Location Sessions Sheet URL', 'idemailwiz_render_text_field', 'idemailwiz_settings', 'idemailwiz_sync_section', array(
        'option_name' => 'location_sessions_sheet_url',
        'description' => 'SheetDB URL for location session weeks and metadata (currently: https://sheetdb.io/api/v1/ov2axr4kssf94?sheet=raw)',
        'default' => 'https://sheetdb.io/api/v1/ov2axr4kssf94?sheet=raw'
    ));
    
    // Sync Toggles
    add_settings_field('iterable_sync_toggle', 'Blast Sync On/Off', 'idemailwiz_render_radio_field', 'idemailwiz_settings', 'idemailwiz_sync_section', array(
        'option_name' => 'iterable_sync_toggle',
        'options' => array('on' => 'On', 'off' => 'Off'),
        'description' => 'Enable/disable automatic syncing of blast campaign data from Iterable'
    ));
    add_settings_field('iterable_engagement_data_sync_toggle', 'Engagement Data Sync On/Off', 'idemailwiz_render_radio_field', 'idemailwiz_settings', 'idemailwiz_sync_section', array(
        'option_name' => 'iterable_engagement_data_sync_toggle',
        'options' => array('on' => 'On', 'off' => 'Off'),
        'description' => 'Enable/disable syncing of opens, clicks, and other engagement metrics'
    ));
    add_settings_field('user_send_sync_toggle', 'User Sends Sync On/Off', 'idemailwiz_render_radio_field', 'idemailwiz_settings', 'idemailwiz_sync_section', array(
        'option_name' => 'user_send_sync_toggle',
        'options' => array('on' => 'On', 'off' => 'Off'),
        'description' => 'Enable/disable syncing of user send data'
    ));
    add_settings_field('course_capacity_sync_toggle', 'Course Capacity Sync On/Off', 'idemailwiz_render_radio_field', 'idemailwiz_settings', 'idemailwiz_sync_section', array(
        'option_name' => 'course_capacity_sync_toggle',
        'options' => array('on' => 'On', 'off' => 'Off'),
        'description' => 'Enable/disable real-time course capacity and session data sync from Pulse'
    ));
    
    // Sync Configuration
    add_settings_field('triggered_sync_length', 'Days to sync triggered sends', 'idemailwiz_render_text_field', 'idemailwiz_settings', 'idemailwiz_sync_section', array(
        'option_name' => 'triggered_sync_length',
        'description' => 'Number of days to look back when syncing triggered campaign data',
        'default' => '30'
    ));
    add_settings_field('course_capacity_sync_frequency', 'Course Capacity Sync Frequency', 'idemailwiz_render_radio_field', 'idemailwiz_settings', 'idemailwiz_sync_section', array(
        'option_name' => 'course_capacity_sync_frequency',
        'options' => array(
            'hourly' => 'Hourly',
            'every_6_hours' => 'Every 6 Hours', 
            'daily' => 'Daily',
            'manual' => 'Manual Only'
        ),
        'description' => 'How often to sync course capacity data from Pulse API',
        'default' => 'hourly'
    ));


}

function idemailwiz_sync_section_callback()
{
    echo '<p>Configure data sync settings for various external sources including Iterable, Pulse APIs, and Google Sheets.</p>';
}

function idemailwiz_render_image_field($args)
{
    $options = get_option('idemailwiz_settings', array());
    $image_url = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    ?>
    <input type="text" name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]"
        value="<?php echo esc_url($image_url); ?>" id="<?php echo esc_attr($args['option_name']); ?>" />
    <input type="button" class="upload-image-button" value="Upload Image"
        data-target="<?php echo esc_attr($args['option_name']); ?>" />
    <?php if ($image_url): ?>
        <img src="<?php echo esc_url($image_url); ?>" style="max-width:100px;" />
    <?php endif; ?>
<?php
}

function idemailwiz_render_dropdown_taxonomy_field($args)
{
    $options = get_option('idemailwiz_settings', array());
    $selected = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    $terms = get_terms(array('taxonomy' => $args['taxonomy'], 'hide_empty' => false));
    ?>
    <select name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]">
        <?php foreach ($terms as $term): ?>
            <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($selected, $term->term_id); ?>>
                <?php echo esc_html($term->name); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

function idemailwiz_render_dropdown_pages_field($args)
{
    $options = get_option('idemailwiz_settings', array());
    $selected = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    $pages = get_pages(array('post_status' => 'publish'));
    ?>
    <select name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]">
        <?php foreach ($pages as $page): ?>
            <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($selected, $page->ID); ?>>
                <?php echo esc_html($page->post_title); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

function idemailwiz_render_radio_field($args)
{
    $options = get_option('idemailwiz_settings');
    if (!is_array($options)) {
        $options = array();
    }
    $selected = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    
    // Set default value if empty
    if (empty($selected) && isset($args['default'])) {
        $selected = $args['default'];
    }

    if (isset($args['options']) && is_array($args['options'])) { // Check if options are set and is an array
        foreach ($args['options'] as $value => $label):
            ?>
            <label>
                <input type="radio" name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]"
                    value="<?php echo esc_attr($value); ?>" <?php checked($selected, $value); ?> />
                <?php echo esc_html($label); ?>
            </label><br />
            <?php
        endforeach;
    }
    if (isset($args['description'])): ?>
        <p class="description">
            <?php echo esc_html($args['description']); ?>
        </p>
    <?php endif;
}


function idemailwiz_render_checkbox_field($args)
{
    $options = get_option('idemailwiz_settings');
    if (!is_array($options)) {
        $options = array();
    }
    $checked = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    ?>
    <input type="checkbox" name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]" value="1" <?php checked($checked, 1); ?> />
    <?php
}


function idemailwiz_render_text_field($args)
{
    $options = get_option('idemailwiz_settings', array());
    $value = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    
    // Set default value for specific fields if empty
    if (empty($value) && isset($args['default'])) {
        $value = $args['default'];
    }
    
    ?>
    <input type="text" name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]"
        value="<?php echo esc_attr($value); ?>" />
    <?php if (isset($args['description'])): ?> <!-- Check if description is set -->
        <p class="description">
            <?php echo esc_html($args['description']); ?>
        </p>
    <?php endif; ?>
<?php
}



function idemailwiz_settings_sanitize($input)
{
    // Sanitization code here
    return $input;
}

/**
 * Helper functions to get sync settings with defaults
 */
function get_sync_setting($setting_name, $default = null)
{
    $settings = get_option('idemailwiz_settings', array());
    return isset($settings[$setting_name]) && !empty($settings[$setting_name]) ? $settings[$setting_name] : $default;
}

function get_iterable_api_key()
{
    return get_sync_setting('iterable_api_key');
}

function get_pulse_marketing_api_key()
{
    return get_sync_setting('pulse_marketing_api_key', 'ZacZac');
}

function get_ga_revenue_sheet_url()
{
    return get_sync_setting('ga_rev_sheet_url');
}

function get_location_sessions_sheet_url()
{
    return get_sync_setting('location_sessions_sheet_url', 'https://sheetdb.io/api/v1/ov2axr4kssf94?sheet=raw');
}

function is_sync_enabled($sync_type)
{
    $setting_name = $sync_type . '_sync_toggle';
    return get_sync_setting($setting_name, 'off') === 'on';
}

function get_course_capacity_sync_frequency()
{
    return get_sync_setting('course_capacity_sync_frequency', 'every_6_hours');
}

function get_triggered_sync_length()
{
    return (int) get_sync_setting('triggered_sync_length', 30);
}

/**
 * Get a summary of all sync settings and their current status
 */
function get_sync_status_summary()
{
    $status = array();
    
    // API Keys Status
    $status['api_keys'] = array(
        'iterable_api_key' => !empty(get_iterable_api_key()) ? 'configured' : 'missing',
        'pulse_marketing_api_key' => !empty(get_pulse_marketing_api_key()) ? 'configured' : 'missing',
        'ga_revenue_bearer_token' => !empty(get_sync_setting('ga_revenue_api_sheet_bearer_token')) ? 'configured' : 'missing'
    );
    
    // Sheet URLs Status
    $status['sheet_urls'] = array(
        'ga_revenue_sheet_url' => !empty(get_ga_revenue_sheet_url()) ? 'configured' : 'missing',
        'location_sessions_sheet_url' => !empty(get_location_sessions_sheet_url()) ? 'configured' : 'missing'
    );
    
    // Sync Toggles Status
    $status['sync_toggles'] = array(
        'iterable_sync' => is_sync_enabled('iterable') ? 'enabled' : 'disabled',
        'iterable_engagement_data_sync' => is_sync_enabled('iterable_engagement_data') ? 'enabled' : 'disabled',
        'user_send_sync' => is_sync_enabled('user_send') ? 'enabled' : 'disabled',
        'course_capacity_sync' => is_sync_enabled('course_capacity') ? 'enabled' : 'disabled'
    );
    
    // Sync Configuration
    $status['sync_config'] = array(
        'triggered_sync_length' => get_triggered_sync_length() . ' days',
        'course_capacity_sync_frequency' => get_course_capacity_sync_frequency()
    );
    
    // Scheduled Events Status
    $status['scheduled_events'] = array(
        'location_sync_cron' => wp_next_scheduled('wizPulse_refresh_locations_cron') ? 'scheduled' : 'not_scheduled',
        'course_sync_cron' => wp_next_scheduled('wizPulse_refresh_courses_cron') ? 'scheduled' : 'not_scheduled',
        'user_sync_cron' => wp_next_scheduled('idemailwiz_sync_users') ? 'scheduled' : 'not_scheduled',
        'blast_sync_cron' => wp_next_scheduled('idemailwiz_process_blast_sync') ? 'scheduled' : 'not_scheduled'
    );
    
    return $status;
}

/**
 * Log sync status summary to wiz log
 */
function log_sync_status_summary()
{
    $status = get_sync_status_summary();
    
    wiz_log("=== Sync Status Summary ===");
    
    // API Keys
    $api_status = array();
    foreach ($status['api_keys'] as $key => $value) {
        $api_status[] = "$key: $value";
    }
    wiz_log("API Keys - " . implode(', ', $api_status));
    
    // Sync Toggles
    $toggle_status = array();
    foreach ($status['sync_toggles'] as $key => $value) {
        $toggle_status[] = "$key: $value";
    }
    wiz_log("Sync Toggles - " . implode(', ', $toggle_status));
    
    // Configuration
    wiz_log("Sync Config - triggered_length: " . $status['sync_config']['triggered_sync_length'] . 
           ", capacity_frequency: " . $status['sync_config']['course_capacity_sync_frequency']);
}
