<?php
add_action('admin_menu', 'idemailwiz_settings_page');
function idemailwiz_settings_page() {
    add_menu_page('ID Email Wiz Settings', 'Wiz Settings', 'manage_options', 'idemailwiz_settings', 'idemailwiz_settings_page_content');
}

function idemailwiz_settings_page_content() {
    ?>
    <div class="wrap">
        <h2>ID Email Wiz Settings</h2>
        <p>Editing these endpoint will not necessarily break anything, but some strange behavior may be encountered, especially related to trashed templates.
            <br/>If you change these, make sure to go to Settings > Permalinks and hit the save button (sometimes twice) to refresh the permalinks cache.</p>
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
function idemailwiz_register_settings() {
    register_setting('idemailwiz_settings_group', 'idemailwiz_settings', 'idemailwiz_settings_sanitize');
    add_settings_section('idemailwiz_main_section', 'Main Settings', null, 'idemailwiz_settings');

    // WP Site Setup fields
    add_settings_field('folder_base', 'Folders Root', 'idemailwiz_render_dropdown_taxonomy_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'folder_base', 'taxonomy' => 'idemailwiz_folder'));
    add_settings_field('base_template_folder', 'Base Templates Folder', 'idemailwiz_render_dropdown_taxonomy_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'base_templates_term', 'taxonomy' => 'idemailwiz_folder'));
    add_settings_field('folder_trash', 'Trash Folder', 'idemailwiz_render_dropdown_taxonomy_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'folder_trash', 'taxonomy' => 'idemailwiz_folder'));
    add_settings_field('dashboard_page', 'Dashboard Page', 'idemailwiz_render_dropdown_pages_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'dashboard_page'));
    add_settings_field('campaigns_page', 'Campaigns Page', 'idemailwiz_render_dropdown_pages_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'campaigns_page'));
    add_settings_field('reports_page', 'Reports Page', 'idemailwiz_render_dropdown_pages_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'reports_page'));
    add_settings_field('wizbuilder_field_group', 'WizBuilder ACF Field Group ID', 'idemailwiz_render_text_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'wizbuilder_field_group'));
    
    
    add_settings_field('sync_method', 'Sync Method', 'idemailwiz_render_radio_field', 'idemailwiz_settings', 'idemailwiz_main_section', array(
        'option_name' => 'sync_method',
        'options' => array('wp_cron' => 'WP Cron', 'ext_cron' => 'External Cron') 
    ));
    add_settings_field('external_cron_api', 'API key for external cron auth', 'idemailwiz_render_text_field', 'idemailwiz_settings', 'idemailwiz_main_section', array('option_name' => 'external_cron_api'));
    
    add_settings_field('iterable_sync_toggle', 'Blast Sync On/Off', 'idemailwiz_render_radio_field', 'idemailwiz_settings', 'idemailwiz_main_section', array(
        'option_name' => 'iterable_sync_toggle',
        'options' => array('on' => 'On', 'off' => 'Off') 
    ));

    add_settings_field('iterable_triggered_sync_toggle', 'Triggered Sync On/Off', 'idemailwiz_render_radio_field', 'idemailwiz_settings', 'idemailwiz_main_section', array(
        'option_name' => 'iterable_triggered_sync_toggle',
        'options' => array('on' => 'On', 'off' => 'Off') 
    ));

  
}

function idemailwiz_render_image_field($args) {
    $options = get_option('idemailwiz_settings', array());
    $image_url = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    ?>
    <input type="text" name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]" value="<?php echo esc_url($image_url); ?>" id="<?php echo esc_attr($args['option_name']); ?>" />
    <input type="button" class="upload-image-button" value="Upload Image" data-target="<?php echo esc_attr($args['option_name']); ?>" />
    <?php if ($image_url) : ?>
    <img src="<?php echo esc_url($image_url); ?>" style="max-width:100px;" />
    <?php endif; ?>
    <?php
}

function idemailwiz_render_dropdown_taxonomy_field($args) {
    $options = get_option('idemailwiz_settings', array());
    $selected = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    $terms = get_terms(array('taxonomy' => $args['taxonomy'], 'hide_empty' => false));
    ?>
    <select name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]">
        <?php foreach ($terms as $term) : ?>
        <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($selected, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}

function idemailwiz_render_dropdown_pages_field($args) {
    $options = get_option('idemailwiz_settings', array());
    $selected = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    $pages = get_pages(array('post_status' => 'publish'));
    ?>
    <select name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]">
        <?php foreach ($pages as $page) : ?>
        <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($selected, $page->ID); ?>><?php echo esc_html($page->post_title); ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}

function idemailwiz_render_radio_field($args) {
    $options = get_option('idemailwiz_settings');
    if (!is_array($options)) {
        $options = array();
    }
    $selected = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';

    if (isset($args['options']) && is_array($args['options'])) { // Check if options are set and is an array
        foreach ($args['options'] as $value => $label) :
            ?>
            <label>
                <input type="radio" name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]" value="<?php echo esc_attr($value); ?>" <?php checked($selected, $value); ?> />
                <?php echo esc_html($label); ?>
            </label><br/>
            <?php
        endforeach;
    }
}


function idemailwiz_render_checkbox_field($args) {
    $options = get_option('idemailwiz_settings');
    if (!is_array($options)) {
        $options = array();
    }
    $checked = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    ?>
    <input type="checkbox" name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]" value="1" <?php checked($checked, 1); ?> />
    <?php
}


function idemailwiz_render_text_field($args) {
    $options = get_option('idemailwiz_settings', array());
    $value = isset($options[$args['option_name']]) ? $options[$args['option_name']] : '';
    ?>
    <input type="text" name="idemailwiz_settings[<?php echo esc_attr($args['option_name']); ?>]" value="<?php echo esc_attr($value); ?>" />
    <?php if (isset($args['description'])) : ?> <!-- Check if description is set -->
        <p class="description"><?php echo esc_html($args['description']); ?></p>
    <?php endif; ?>
    <?php
}



function idemailwiz_settings_sanitize($input) {
    // Sanitization code here
    return $input;
}
