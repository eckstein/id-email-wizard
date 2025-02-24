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
                                                        <option value="user_feed" <?php selected($endpoint_details['base_data_source'], 'user_feed'); ?>>User Feed</option>
                                                        <option value="custom" <?php selected($endpoint_details['base_data_source'], 'custom'); ?>>Custom</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="test-user-selector">
                                                    <label>Test User:</label>
                                                    <select class="test-user-select wiz-select">
                                                        <option value="">Select a test user...</option>
                                                        <?php
                                                        // Get users with their most recent purchase info
                                                        $recent_users = $wpdb->get_results(
                                                            "SELECT DISTINCT 
                                                                uf.StudentAccountNumber,
                                                                uf.StudentFirstName,
                                                                p.shoppingCartItems_divisionName as division,
                                                                p.purchaseDate
                                                            FROM {$wpdb->prefix}idemailwiz_userfeed uf
                                                            JOIN {$wpdb->prefix}idemailwiz_purchases p 
                                                                ON uf.StudentAccountNumber = p.shoppingCartItems_studentAccountNumber
                                                            WHERE uf.StudentAccountNumber IS NOT NULL 
                                                            GROUP BY uf.StudentAccountNumber
                                                            ORDER BY p.purchaseDate DESC 
                                                            LIMIT 15"
                                                        );
                                                        foreach ($recent_users as $user) {
                                                            echo '<option value="' . esc_attr($user->StudentAccountNumber) . '">' . 
                                                                 esc_html($user->StudentAccountNumber) . 
                                                                 (!empty($user->StudentFirstName) ? ' - ' . esc_html($user->StudentFirstName) : '') .
                                                                 ' (' . esc_html($user->division) . ' purchase)' .
                                                                 '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                    <div class="user-id-input" style="display: none;">
                                                        <label>Or enter User ID:</label>
                                                        <input type="text" class="manual-user-id wiz-input" placeholder="Enter User ID">
                                                        <button class="load-user-data wiz-button">Load User</button>
                                                    </div>
                                                    <a href="#" class="toggle-user-input">Enter User ID manually</a>
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
                                                            <option value="most_recent_purchase" <?php selected($mapping['value'], 'most_recent_purchase'); ?>>Most Recent Purchase Date</option>
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
