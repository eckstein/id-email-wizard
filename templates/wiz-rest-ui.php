<?php
get_header();

global $wpdb;

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="wizHeader">
        <div class="wizHeaderInnerWrap">
            <div class="wizHeader-left">
                <h1 class="wizEntry-title" itemprop="name">
                    REST Endpoints
                </h1>
            </div>
            <div class="wizHeader-right">
                <div class="wizHeader-actions">
                    <button class="add-endpoint wiz-button green"><i class="fa-solid fa-plus"></i>&nbsp;Add Endpoint</button>
                </div>
            </div>
        </div>
    </header>
    <div class="entry-content" itemprop="mainContentOfPage">
        <div class="endpoints-manager">
            <div class="endpoints-tabs">
                <?php
                $endpoints = idwiz_get_all_endpoints();
                if (empty($endpoints)) {
                    echo '<div class="no-endpoints">No endpoints configured yet. Click "Add Endpoint" to create one.</div>';
                } else {
                    // Get the active endpoint from URL or default to first one
                    $active_endpoint = isset($_GET['endpoint']) ? sanitize_text_field($_GET['endpoint']) : $endpoints[0];
                    
                    ?>
                    <div class="endpoints-list">
                        <?php foreach ($endpoints as $endpoint) :
                            $endpoint_details = idwiz_get_endpoint($endpoint);
                            $is_active = $endpoint === $active_endpoint;
                            ?>
                            <div class="endpoint-tab <?php echo $is_active ? 'active' : ''; ?>" 
                                 data-endpoint="<?php echo esc_attr($endpoint); ?>">
                                <span class="endpoint-name"><?php echo esc_html($endpoint_details['name'] ?: $endpoint); ?></span>
                                <button class="remove-endpoint" data-endpoint="<?php echo esc_attr($endpoint); ?>">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php foreach ($endpoints as $endpoint) :
                        $endpoint_details = idwiz_get_endpoint($endpoint);
                        $is_active = $endpoint === $active_endpoint;
                        ?>
                        <div class="endpoint-content <?php echo $is_active ? 'active' : ''; ?>" 
                             id="endpoint-<?php echo esc_attr($endpoint); ?>">
                            <div class="endpoint-details">
                                <div class="endpoint-header">
                                    <div class="endpoint-route">
                                        <code>idemailwiz/v1/<?php echo esc_html($endpoint); ?></code>
                                        <?php 
                                        $wizSettings = get_option('idemailwiz_settings');
                                        $api_auth_token = $wizSettings['external_cron_api'];
                                        $site_url = get_bloginfo('url');
                                        $endpoint_url = $site_url . '/wp-json/idemailwiz/v1/' . $endpoint;
                                        if (!empty($api_auth_token)) {
                                            echo '<a href="#" class="test-endpoint" data-url="' . esc_url($endpoint_url) . '" data-token="' . esc_attr($api_auth_token) . '" title="Test Endpoint"><i class="fa-solid fa-external-link"></i></a>';
                                        }
                                        ?>
                                    </div>
                                    <button class="toggle-settings wiz-button outline" title="Toggle Basic Settings">
                                        <i class="fa-solid fa-gear"></i>
                                    </button>
                                </div>

                                <div class="basic-settings" style="display: none;">
                                    <div class="endpoint-field">
                                        <label>Name:</label>
                                        <input type="text" class="endpoint-name" 
                                               value="<?php echo esc_attr($endpoint_details['name'] ?: $endpoint); ?>" 
                                               data-endpoint="<?php echo esc_attr($endpoint); ?>">
                                    </div>
                                    <div class="endpoint-field">
                                        <label>Description:</label>
                                        <textarea class="endpoint-description" 
                                                  data-endpoint="<?php echo esc_attr($endpoint); ?>"><?php echo esc_textarea($endpoint_details['description']); ?></textarea>
                                    </div>
                                    <div class="endpoint-field">
                                        <label>Configuration:</label>
                                        <textarea class="endpoint-config" 
                                                  data-endpoint="<?php echo esc_attr($endpoint); ?>"><?php echo esc_textarea(is_array($endpoint_details['config']) ? json_encode($endpoint_details['config'], JSON_PRETTY_PRINT) : '{}'); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="endpoint-mapping-preview-wrapper">
                                    <div class="endpoint-mapping-interface">
                                        <!-- Data Mapping Section -->
                                        <div class="endpoint-field data-mapping-section">
                                            <div class="data-mapping-controls">
                                                <div class="data-source-selector">
                                                    <label>Base Data Source:</label>
                                                    <select class="endpoint-base-data-source wiz-select" 
                                                            data-endpoint="<?php echo esc_attr($endpoint); ?>">
                                                        <option value="student" <?php selected($endpoint_details['base_data_source'], 'student'); ?>>Student (account_number)</option>
                                                        <option value="parent" <?php selected($endpoint_details['base_data_source'], 'parent'); ?>>Parent (account_number)</option>
                                                        <option value="location" <?php selected($endpoint_details['base_data_source'], 'location'); ?>>Location (location_id)</option>
                                                        <option value="quiz_url" <?php selected($endpoint_details['base_data_source'], 'quiz_url'); ?>>Quiz URL String (courseRecUrl)</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="test-user-selector">
                                                    <?php if ($endpoint_details['base_data_source'] === 'location'): ?>
                                                    <label class="test-user-label">Test Location:</label>
                                                    <div class="location-id-input">
                                                        <input type="text" class="manual-location-id wiz-input" placeholder="Enter Location ID">
                                                        <button class="load-location-data wiz-button">Load Location</button>
                                                    </div>
                                                    <?php elseif ($endpoint_details['base_data_source'] === 'parent'): ?>
                                                    <label class="test-user-label">Test Parent Account:</label>
                                                    <select class="test-user-select wiz-select" data-type="parent">
                                                        <?php
                                                        // Get parent accounts for user profile data source
                                                        $users = idwiz_get_parent_accounts();
                                                        echo generate_parent_options_html($users);
                                                        ?>
                                                    </select>
                                                    <div class="user-id-input" style="display: none;">
                                                        <label>Or enter Parent Account Number:</label>
                                                        <input type="text" class="manual-user-id wiz-input" placeholder="Enter Parent Account Number">
                                                        <button class="load-user-data wiz-button">Load User</button>
                                                    </div>
                                                    <a href="#" class="toggle-user-input">Enter Parent Account Number manually</a>
                                                    <?php elseif ($endpoint_details['base_data_source'] === 'quiz_url'): ?>
                                                    <label class="test-user-label">Test Quiz URL:</label>
                                                    <div class="quiz-url-input">
                                                        <input type="text" class="manual-quiz-url wiz-input" placeholder="Paste quiz result URL (e.g., https://www.idtech.com/courses?interests=295003,295001&age=10&format=405000)">
                                                        <button class="load-quiz-url-data wiz-button">Load Quiz URL</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <label class="test-user-label">Test User:</label>
                                                    <select class="test-user-select wiz-select" data-type="student">
                                                        <?php
                                                        // Get users from previous fiscal year with at least one from each division
                                                        $users = idwiz_get_previous_year_users();
                                                        echo generate_user_options_html($users, true);
                                                        ?>
                                                    </select>
                                                    <div class="user-id-input" style="display: none;">
                                                        <label>Or enter Student Account Number:</label>
                                                        <input type="text" class="manual-user-id wiz-input" placeholder="Enter Student Account Number">
                                                        <button class="load-user-data wiz-button">Load User</button>
                                                    </div>
                                                    <a href="#" class="toggle-user-input">Enter Student Account Number manually</a>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="data-mapping-list">
                                                    <h3>Data Mapping</h3>
                                                    <?php 
                                                    $data_mapping = is_array($endpoint_details['data_mapping']) ? $endpoint_details['data_mapping'] : array();
                                                    foreach ($data_mapping as $key => $mapping): 
                                                    ?>
                                                    <div class="data-mapping-item">
                                                        <input type="text" class="mapping-key wiz-input" value="<?php echo esc_attr($key); ?>" placeholder="Key">
                                                        <select class="mapping-type wiz-select">
                                                            <option value="static" <?php selected($mapping['type'], 'static'); ?>>Static Value</option>
                                                            <option value="preset" <?php selected($mapping['type'], 'preset'); ?>>Preset Function</option>
                                                        </select>
                                                        <?php if ($mapping['type'] === 'static'): ?>
                                                        <input type="text" class="mapping-value wiz-input" value="<?php echo esc_attr($mapping['value']); ?>" placeholder="Value">
                                                        <?php else: ?>
                                                        <select class="mapping-preset wiz-select">
                                                            <option value="">Select Preset</option>
                                                            <?php
                                                            // Get all presets, then filter by data source compatibility
                                                            $all_presets = get_available_presets();
                                                            $base_data_source = $endpoint_details['base_data_source'] ?? 'student';
                                                            $compatible_preset_keys = get_compatible_presets($base_data_source);
                                                            
                                                            // Filter to only compatible presets
                                                            $presets = array_filter($all_presets, function($key) use ($compatible_preset_keys) {
                                                                return in_array($key, $compatible_preset_keys);
                                                            }, ARRAY_FILTER_USE_KEY);
                                                            
                                                            $current_group = null;
                                                            
                                                            // Sort presets by group then name
                                                            uasort($presets, function($a, $b) {
                                                                $group_compare = strcmp($a['group'] ?? '', $b['group'] ?? '');
                                                                if ($group_compare !== 0) return $group_compare;
                                                                return strcmp($a['name'], $b['name']);
                                                            });
                                                            
                                                            foreach ($presets as $preset_key => $preset) {
                                                                if (($preset['group'] ?? null) !== $current_group) {
                                                                    if ($current_group !== null) {
                                                                        echo '</optgroup>';
                                                                    }
                                                                    if (isset($preset['group'])) {
                                                                        echo '<optgroup label="' . esc_attr($preset['group']) . '">';
                                                                    }
                                                                    $current_group = $preset['group'] ?? null;
                                                                }
                                                                echo '<option value="' . esc_attr($preset_key) . '" ' . selected($mapping['value'], $preset_key, false) . '>' . esc_html($preset['name']) . '</option>';
                                                            }
                                                            if ($current_group !== null) {
                                                                echo '</optgroup>';
                                                            }
                                                            ?>
                                                        </select>
                                                        <?php endif; ?>
                                                        <button class="remove-mapping" title="Remove Mapping">×</button>
                                                    </div>
                                                    <?php endforeach; ?>
                                                    
                                                    <button class="add-mapping wiz-button">Add Mapping</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="endpoint-preview">
                                        <div class="endpoint-preview-header">
                                            <h3>Preview Payload</h3>
                                            <div class="preview-controls">
                                                <button class="refresh-preview wiz-button outline"><i class="fa-solid fa-refresh"></i>&nbsp;&nbsp;Refresh</button>
                                            </div>
                                        </div>
                                        <div class="endpoint-preview-content">
                                            <pre class="payload-preview">Select a test user to preview the payload</pre>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add preset definitions section -->
                                <div class="preset-definitions">
                                    <h3>Selected Preset Definitions</h3>
                                    <div class="preset-definitions-content">
                                        <?php
                                        $presets = get_available_presets();
                                        $used_presets = [];
                                        
                                        // Collect used presets
                                        foreach ($data_mapping as $mapping) {
                                            if ($mapping['type'] === 'preset' && !empty($mapping['value'])) {
                                                $preset_key = $mapping['value'];
                                                if (isset($presets[$preset_key])) {
                                                    $used_presets[$preset_key] = $presets[$preset_key];
                                                }
                                            }
                                        }

                                        if (empty($used_presets)) {
                                            echo '<p class="no-presets-message">No presets currently in use. Add a preset mapping above to see its definition here.</p>';
                                        } else {
                                            echo '<dl class="preset-list">';
                                            foreach ($used_presets as $key => $preset) {
                                                echo '<dt>' . esc_html($preset['name']) . '</dt>';
                                                echo '<dd>' . esc_html($preset['description']) . '</dd>';
                                            }
                                            echo '</dl>';
                                        }
                                        ?>
                                    </div>
                                </div>

                                <div class="endpoint-actions">
                                    <button class="save-endpoint wiz-button green" 
                                            data-endpoint="<?php echo esc_attr($endpoint); ?>">
                                        Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach;
                }
                ?>
            </div>
        </div>
    </div>
</article>

<?php get_footer();
