<?php
/**
 * Location Sessions Mapping Configuration
 * 
 * Admin interface for mapping Google Sheets columns to database fields
 * and configuring session week dates for location sync
 */

// Register the admin page
add_action('admin_menu', 'wizPulse_register_sessions_mapping_page', 20);
function wizPulse_register_sessions_mapping_page()
{
    add_submenu_page(
        'idemailwiz_settings', // Parent slug from wiz-options.php add_menu_page
        'Location Sessions Mapping',
        'Sessions Mapping',
        'manage_options',
        'idemailwiz_sessions_mapping',
        'wizPulse_sessions_mapping_page_content'
    );
}

// Register settings
add_action('admin_init', 'wizPulse_register_sessions_mapping_settings');
function wizPulse_register_sessions_mapping_settings()
{
    register_setting('wizPulse_sessions_mapping_group', 'wizPulse_sessions_mapping', 'wizPulse_sessions_mapping_sanitize');
    
    // Field Mapping Section
    add_settings_section(
        'wizPulse_field_mapping_section',
        'Spreadsheet Column Mapping',
        'wizPulse_field_mapping_section_callback',
        'idemailwiz_sessions_mapping'
    );
    
    // Week Dates Section
    add_settings_section(
        'wizPulse_week_dates_section',
        'Session Week Dates Configuration',
        'wizPulse_week_dates_section_callback',
        'idemailwiz_sessions_mapping'
    );
    
    // Field mapping fields
    $field_mappings = [
        'division_column' => 'Division Column Name',
        'shortcode_column' => 'Location Shortcode Column Name',
        'location_name_column' => 'Location Name Column Name',
        'location_url_column' => 'Location URL Column Name',
        'overnight_offered_column' => 'Overnight Offered Column Name'
    ];
    
    foreach ($field_mappings as $key => $label) {
        add_settings_field(
            $key,
            $label,
            'wizPulse_render_mapping_text_field',
            'idemailwiz_sessions_mapping',
            'wizPulse_field_mapping_section',
            ['option_name' => $key]
        );
    }
    
    // Week dates fields (12 weeks)
    for ($i = 1; $i <= 12; $i++) {
        add_settings_field(
            "week_{$i}_date",
            "Week $i Start Date",
            'wizPulse_render_week_date_field',
            'idemailwiz_sessions_mapping',
            'wizPulse_week_dates_section',
            ['option_name' => "week_{$i}_date", 'week_number' => $i]
        );
    }
}

function wizPulse_field_mapping_section_callback()
{
    echo '<p>Map the column names from your Google Sheets spreadsheet to the corresponding database fields. Column names are case-sensitive.</p>';
    
    $sheet_url = get_location_sessions_sheet_url();
    if (!empty($sheet_url)) {
        echo '<p><strong>Current Sheet URL:</strong> <a href="' . esc_url($sheet_url) . '" target="_blank">' . esc_html($sheet_url) . '</a></p>';
        echo '<button type="button" id="preview-sheet-data" class="button button-secondary">Preview Sheet Data</button>';
        echo '<div id="sheet-preview" style="margin-top: 15px; display: none;"></div>';
    }
}

function wizPulse_week_dates_section_callback()
{
    echo '<p>Configure the start dates for each session week. These dates will be used when syncing location session data. Format: YYYY-MM-DD</p>';
}

function wizPulse_render_mapping_text_field($args)
{
    $options = get_option('wizPulse_sessions_mapping', array());
    $value = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    
    // Set defaults if empty
    $defaults = [
        'division_column' => 'Division',
        'shortcode_column' => 'Shortcode',
        'location_name_column' => 'Location Name',
        'location_url_column' => 'Location URL',
        'overnight_offered_column' => 'ON Offered'
    ];
    
    if (empty($value) && isset($defaults[$args['option_name']])) {
        $value = $defaults[$args['option_name']];
    }
    
    ?>
    <input type="text" 
           name="wizPulse_sessions_mapping[<?php echo esc_attr($args['option_name']); ?>]"
           value="<?php echo esc_attr($value); ?>" 
           class="regular-text" />
    <?php
}

function wizPulse_render_week_date_field($args)
{
    $options = get_option('wizPulse_sessions_mapping', array());
    $value = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    
    // Set default dates (2025 summer season)
    $default_dates = [
        'week_1_date' => '2025-05-25',
        'week_2_date' => '2025-06-01',
        'week_3_date' => '2025-06-08',
        'week_4_date' => '2025-06-15',
        'week_5_date' => '2025-06-22',
        'week_6_date' => '2025-06-29',
        'week_7_date' => '2025-07-06',
        'week_8_date' => '2025-07-13',
        'week_9_date' => '2025-07-20',
        'week_10_date' => '2025-07-27',
        'week_11_date' => '2025-08-03',
        'week_12_date' => '2025-08-10'
    ];
    
    if (empty($value) && isset($default_dates[$args['option_name']])) {
        $value = $default_dates[$args['option_name']];
    }
    
    ?>
    <input type="date" 
           name="wizPulse_sessions_mapping[<?php echo esc_attr($args['option_name']); ?>]"
           value="<?php echo esc_attr($value); ?>" 
           class="regular-text" />
    <span class="description">Column name: Cap Week <?php echo $args['week_number']; ?></span>
    <?php
}

function wizPulse_sessions_mapping_sanitize($input)
{
    $sanitized = array();
    
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            if (strpos($key, '_date') !== false) {
                // Sanitize date fields
                $sanitized[$key] = sanitize_text_field($value);
            } else {
                // Sanitize text fields
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
    }
    
    return $sanitized;
}

// Main page content
function wizPulse_sessions_mapping_page_content()
{
    ?>
    <div class="wrap">
        <h1>Location Sessions Mapping Configuration</h1>
        
        <p class="description">
            Configure how data from your Google Sheets is mapped to the database, and set the session week dates for the current season.
        </p>
        
        <?php 
        // Check if sheet URL is configured
        $sheet_url = get_location_sessions_sheet_url();
        if (empty($sheet_url)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>Notice:</strong> No Location Sessions Sheet URL configured. 
                    Please configure the sheet URL in 
                    <a href="<?php echo admin_url('admin.php?page=idemailwiz_settings'); ?>">Wiz Settings</a> first.
                </p>
            </div>
            <?php
        }
        ?>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('wizPulse_sessions_mapping_group');
            do_settings_sections('idemailwiz_sessions_mapping');
            submit_button('Save Configuration');
            ?>
        </form>
        
        <?php if (!empty($sheet_url)): ?>
        <hr style="margin: 30px 0;">
        
        <h2>Test Sync</h2>
        <p>Test your configuration by running a sync with the current settings.</p>
        <button type="button" id="test-sync" class="button button-primary">Run Test Sync</button>
        <div id="sync-results" style="margin-top: 15px;"></div>
        <?php endif; ?>
    </div>
    
    <style>
        .form-table th {
            width: 250px;
        }
        
        #sheet-preview {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            max-height: 400px;
            overflow: auto;
        }
        
        #sheet-preview table {
            width: 100%;
            border-collapse: collapse;
        }
        
        #sheet-preview th,
        #sheet-preview td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        #sheet-preview th {
            background: #0073aa;
            color: white;
            font-weight: bold;
        }
        
        #sync-results {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
        }
        
        .notice {
            padding: 12px;
            margin: 20px 0;
            border-left: 4px solid;
        }
        
        .notice-warning {
            background: #fff8e5;
            border-left-color: #ffb900;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Preview sheet data
        $('#preview-sheet-data').on('click', function() {
            const button = $(this);
            const preview = $('#sheet-preview');
            
            button.prop('disabled', true).text('Loading...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wizPulse_preview_sheet_data'
                },
                success: function(response) {
                    if (response.success) {
                        let html = '<h3>First 5 Rows from Sheet:</h3>';
                        html += '<table>';
                        
                        // Headers
                        if (response.data.length > 0) {
                            html += '<tr>';
                            for (let key in response.data[0]) {
                                html += '<th>' + key + '</th>';
                            }
                            html += '</tr>';
                            
                            // Data rows
                            response.data.forEach(function(row) {
                                html += '<tr>';
                                for (let key in row) {
                                    html += '<td>' + (row[key] || '') + '</td>';
                                }
                                html += '</tr>';
                            });
                        }
                        
                        html += '</table>';
                        preview.html(html).slideDown();
                    } else {
                        preview.html('<p style="color: red;">Error: ' + response.data.message + '</p>').slideDown();
                    }
                },
                error: function() {
                    preview.html('<p style="color: red;">Error loading sheet data</p>').slideDown();
                },
                complete: function() {
                    button.prop('disabled', false).text('Preview Sheet Data');
                }
            });
        });
        
        // Test sync
        $('#test-sync').on('click', function() {
            const button = $(this);
            const results = $('#sync-results');
            
            button.prop('disabled', true).text('Running Sync...');
            results.html('<p><strong>Running sync, please wait...</strong><br>This may take 30-60 seconds depending on the amount of data.</p>').show();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 120000, // 120 second timeout
                data: {
                    action: 'wizPulse_test_sessions_sync'
                },
                success: function(response) {
                    if (response.success) {
                        let html = '<h3 style="color: green;">✓ Sync Completed Successfully</h3>';
                        html += '<ul>';
                        html += '<li><strong>Processed Rows:</strong> ' + response.data.processed_rows + '</li>';
                        html += '<li><strong>Locations with Sessions:</strong> ' + response.data.updated_count + '</li>';
                        html += '<li><strong>Week Dates Configured:</strong> ' + response.data.week_dates_count + '</li>';
                        if (response.data.message) {
                            html += '<li><strong>Status:</strong> ' + response.data.message + '</li>';
                        }
                        html += '</ul>';
                        results.html(html);
                    } else {
                        let html = '<h3 style="color: red;">✗ Sync Failed</h3>';
                        html += '<p><strong>Error:</strong> ' + response.data.message + '</p>';
                        if (response.data.available_columns) {
                            html += '<p><strong>Available columns in your sheet:</strong></p>';
                            html += '<ul>';
                            response.data.available_columns.forEach(function(col) {
                                html += '<li>' + col + '</li>';
                            });
                            html += '</ul>';
                            html += '<p><em>Please update your field mappings to match the available columns.</em></p>';
                        }
                        results.html(html);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = '<h3 style="color: red;">✗ Error</h3>';
                    if (status === 'timeout') {
                        errorMsg += '<p><strong>Request timed out.</strong> The sync operation took longer than expected. This could be due to:</p>';
                        errorMsg += '<ul><li>Large amount of data in the spreadsheet</li><li>Slow network connection to Google Sheets</li><li>Server timeout limits</li></ul>';
                        errorMsg += '<p>Check the Wiz Log to see if the sync completed anyway. You may need to run the sync from the Sync Station instead.</p>';
                    } else {
                        errorMsg += '<p>Failed to complete sync: ' + error + '</p>';
                        errorMsg += '<p>Status: ' + status + '</p>';
                    }
                    results.html(errorMsg);
                },
                complete: function() {
                    button.prop('disabled', false).text('Run Test Sync');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Note: Helper functions wizPulse_get_column_mapping() and wizPulse_get_week_dates() 
 * are defined in pulse-connection.php so they're available in all contexts (admin and cron)
 */

/**
 * AJAX handlers
 */
add_action('wp_ajax_wizPulse_preview_sheet_data', 'wizPulse_ajax_preview_sheet_data');
function wizPulse_ajax_preview_sheet_data()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }
    
    try {
        $api_url = get_location_sessions_sheet_url();
        
        if (empty($api_url)) {
            wp_send_json_error(['message' => 'No sheet URL configured']);
        }
        
        $response = idemailwiz_iterable_curl_call($api_url, null, false, 3, 5);
        $sheet_data = $response['response'];
        
        if (!$sheet_data || !is_array($sheet_data)) {
            wp_send_json_error(['message' => 'No valid data returned from sheet']);
        }
        
        // Return first 5 rows for preview
        $preview_data = array_slice($sheet_data, 0, 5);
        
        wp_send_json_success($preview_data);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

add_action('wp_ajax_wizPulse_test_sessions_sync', 'wizPulse_ajax_test_sessions_sync');
function wizPulse_ajax_test_sessions_sync()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }
    
    // Increase timeout and allow script to continue even if user aborts
    set_time_limit(120);
    ignore_user_abort(true);
    
    wiz_log("=== Manual Test Sync Started from Admin Interface ===");
    
    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'idemailwiz_locations';
        
        // Get the sheet URL
        $api_url = get_location_sessions_sheet_url();
        if (empty($api_url)) {
            wp_send_json_error(['message' => 'No Location Sessions Sheet URL configured']);
            return;
        }
        
        // Get data from SheetDB
        $response = idemailwiz_iterable_curl_call($api_url, null, false, 3, 10);
        $sheet_data = $response['response'];
        
        if (!$sheet_data || !is_array($sheet_data)) {
            wp_send_json_error(['message' => 'No valid data returned from Google Sheets']);
            return;
        }
        
        $total_rows = count($sheet_data);
        
        // Get configuration
        $week_dates = wizPulse_get_week_dates();
        $division_col = wizPulse_get_column_mapping('division_column');
        $shortcode_col = wizPulse_get_column_mapping('shortcode_column');
        $location_name_col = wizPulse_get_column_mapping('location_name_column');
        
        // Process first few rows to validate configuration
        $sample_row = $sheet_data[0] ?? [];
        $missing_columns = [];
        
        if (!isset($sample_row[$division_col])) $missing_columns[] = $division_col;
        if (!isset($sample_row[$shortcode_col])) $missing_columns[] = $shortcode_col;
        if (!isset($sample_row[$location_name_col])) $missing_columns[] = $location_name_col;
        
        if (!empty($missing_columns)) {
            wp_send_json_error([
                'message' => 'Missing columns in spreadsheet: ' . implode(', ', $missing_columns),
                'available_columns' => array_keys($sample_row)
            ]);
            return;
        }
        
        // Run the actual sync
        $result = wizPulse_sync_location_sessions();
        
        // Count updated locations
        $locations_with_sessions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE sessionWeeks IS NOT NULL AND sessionWeeks != ''");
        
        if ($result) {
            wiz_log("Manual Test Sync: Completed successfully - $total_rows rows processed, $locations_with_sessions locations updated");
            wp_send_json_success([
                'processed_rows' => $total_rows,
                'unique_locations' => 'Multiple',
                'updated_count' => $locations_with_sessions,
                'message' => 'Sync completed successfully! Check Wiz Log for detailed results.',
                'week_dates_count' => count($week_dates)
            ]);
        } else {
            wiz_log("Manual Test Sync: Failed - result was false");
            wp_send_json_error(['message' => 'Sync failed. Check the Wiz Log for details.']);
        }
        
    } catch (Exception $e) {
        wiz_log("Manual Test Sync Error: " . $e->getMessage());
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
}

