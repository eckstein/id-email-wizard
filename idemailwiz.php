<?php
/**
 * Plugin Name: iD Email Wizard
 * Plugin URI: https://idtech.com
 * Description: This plugin provides an interface for designing and exporting email template HTML.
 * Version: 1.0
 * Author: Zac Eckstein for iD Tech
 * License: Private
 */
//define the path to the plugin file
define('IDEMAILWIZ_ROOT', __FILE__);


//Add ACF to header
function idwizacfheader()
{
    acf_form_head();
}
add_action('wp_head', 'idwizacfheader');


// Plugin Activation
register_activation_hook(__FILE__, 'idemailwiz_activate');
function idemailwiz_activate()
{

    //Create custom databases
    idemailwiz_create_databases();

    // Schedule an event to run on the next page load
    wp_schedule_single_event(time(), 'idemailwiz_on_next_page_load');

    //flush permalinks
    flush_rewrite_rules();

}

//delayed activation so it's after init
add_action('idemailwiz_on_next_page_load', 'idemailwiz_post_activate');
function idemailwiz_post_activate()
{
    //get the term id of the default term in the folder taxonomy
    idemailwiz_set_root_folder();
    //set the trash term
    idemailwiz_set_trash_term();
    //flush permalinks
    flush_rewrite_rules();
}
//Get the term ID of the default folder and save it to options (runs on first page load after activation)
function idemailwiz_set_root_folder()
{
    // Retrieve the current settings
    $options = get_option('idemailwiz_settings');

    // Check if the 'folder_base' setting is already set
    if (empty($options['folder_base'])) {
        // Create a new term for the root folder
        $trashTerm = wp_insert_term('Trash', 'idemailwiz_folder', array('slug' => 'trash'));

        // Check if the term was created successfully
        if (!is_wp_error($trashTerm)) {
            // Update the 'folder_base' setting with the newly created term ID
            $options['folder_base'] = $trashTerm['term_id'];

            // Save the updated options back to the database
            update_option('idemailwiz_settings', $options);
        }
    }
}

//Create a term for the trash folder (runs on first page load after activation)
function idemailwiz_set_trash_term()
{
    // Retrieve the current settings
    $options = get_option('idemailwiz_settings');

    // Check if the 'folder_trash' setting is already set
    if (empty($options['folder_trash'])) {
        // Create a new term for the trash folder
        $trashTerm = wp_insert_term('Trash', 'idemailwiz_folder', array('slug' => 'trash'));

        // Check if the term was created successfully
        if (!is_wp_error($trashTerm)) {
            // Update the 'folder_trash' setting with the newly created term ID
            $options['folder_trash'] = $trashTerm['term_id'];

            // Save the updated options back to the database
            update_option('idemailwiz_settings', $options);
        }
    }
}

//Options pages
include(plugin_dir_path(__FILE__) . 'includes/idemailwiz-options.php');


// Deactivation
register_deactivation_hook(__FILE__, 'idemailwiz_deactivate');
function idemailwiz_deactivate()
{
    flush_rewrite_rules();
}

//require files
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-functions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-shortcodes.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-databases.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-database-cleanup.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-initiatives.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-sync.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-manual-import.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idwiz-wiz-log.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idwiz-curl.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-data-tables.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-charts.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-wysiwyg.php');
require_once(plugin_dir_path(__FILE__) . 'includes/template-builder.php');
require_once(plugin_dir_path(__FILE__) . 'includes/chunk-helpers.php');
require_once(plugin_dir_path(__FILE__) . 'includes/folder-tree.php');
require_once(plugin_dir_path(__FILE__) . 'includes/folder-template-actions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/archive-query.php');
require_once(plugin_dir_path(__FILE__) . 'includes/iterable-functions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-google-sheets-api.php');



// Register custom post types
add_action('init', 'idemailwiz_create_template_post_types', 0);
function idemailwiz_create_template_post_types()
{
    $templateLabels = array(
        'name' => 'Templates',
        'singular_name' => 'Template',
        // Add other labels as needed
    );

    $templateArgs = array(
        'labels' => $templateLabels,
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
        'show_in_rest' => true,
        // This is required if you want to use this post type with Gutenberg

    );

    register_post_type('idemailwiz_template', $templateArgs);

    $initiativeLabels = array(
        'name' => _x('Initiatives', 'Post Type General Name', 'idemailwiz'),
        'singular_name' => _x('Initiative', 'Post Type Singular Name', 'idemailwiz'),
        'menu_name' => __('Initiatives', 'idemailwiz'),
        'name_admin_bar' => __('Initiative', 'idemailwiz'),
        'archives' => __('Initiative Archives', 'idemailwiz'),
        'attributes' => __('Initiative Attributes', 'idemailwiz'),
        'parent_item_colon' => __('Parent Initiative:', 'idemailwiz'),
        'all_items' => __('All Initiatives', 'idemailwiz'),
        'add_new_item' => __('Add New Initiative', 'idemailwiz'),
        'add_new' => __('Add New', 'idemailwiz'),
        'new_item' => __('New Initiative', 'idemailwiz'),
        'edit_item' => __('Edit Initiative', 'idemailwiz'),
        'update_item' => __('Update Initiative', 'idemailwiz'),
        'view_item' => __('View Initiative', 'idemailwiz'),
        'view_items' => __('View Initiatives', 'idemailwiz'),
        'search_items' => __('Search Initiative', 'idemailwiz'),
        'not_found' => __('Not found', 'idemailwiz'),
        'not_found_in_trash' => __('Not found in Trash', 'idemailwiz'),
        'featured_image' => __('Featured Image', 'idemailwiz'),
        'set_featured_image' => __('Set featured image', 'idemailwiz'),
        'remove_featured_image' => __('Remove featured image', 'idemailwiz'),
        'use_featured_image' => __('Use as featured image', 'idemailwiz'),
        'insert_into_item' => __('Insert into initiative', 'idemailwiz'),
        'uploaded_to_this_item' => __('Uploaded to this initiative', 'idemailwiz'),
        'items_list' => __('Initiatives list', 'idemailwiz'),
        'items_list_navigation' => __('Initiatives list navigation', 'idemailwiz'),
        'filter_items_list' => __('Filter initiatives list', 'idemailwiz'),
    );

    $initiativeArgs = array(
        'label' => __('Initiative', 'idemailwiz'),
        'description' => __('Initiative Description', 'idemailwiz'),
        'labels' => $initiativeLabels,
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields', 'revisions', 'page-attributes'),
        'taxonomies' => array('category', 'post_tag'),
        // Optional
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 5,
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => true,
        'can_export' => true,
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'rewrite' => ['slug' => 'initiative'],
        'has_archive' => 'initiatives',
        'capability_type' => 'post',
        'show_in_rest' => true,
        // Enable Gutenberg editor
    );

    register_post_type('idwiz_initiative', $initiativeArgs);


    register_post_type('journey', array(
        'labels' => array(
            'name' => 'Journeys',
            'singular_name' => 'Journey',
            'menu_name' => 'Journeys',
            'all_items' => 'All Journeys',
            'edit_item' => 'Edit Journey',
            'view_item' => 'View Journey',
            'view_items' => 'View Journeys',
            'add_new_item' => 'Add New Journey',
            'new_item' => 'New Journey',
            'parent_item_colon' => 'Parent Journey:',
            'search_items' => 'Search Journeys',
            'not_found' => 'No journeys found',
            'not_found_in_trash' => 'No journeys found in Trash',
            'archives' => 'Journey Archives',
            'attributes' => 'Journey Attributes',
            'insert_into_item' => 'Insert into journey',
            'uploaded_to_this_item' => 'Uploaded to this journey',
            'filter_items_list' => 'Filter journeys list',
            'filter_by_date' => 'Filter journeys by date',
            'items_list_navigation' => 'Journeys list navigation',
            'items_list' => 'Journeys list',
            'item_published' => 'Journey published.',
            'item_published_privately' => 'Journey published privately.',
            'item_reverted_to_draft' => 'Journey reverted to draft.',
            'item_scheduled' => 'Journey scheduled.',
            'item_updated' => 'Journey updated.',
            'item_link' => 'Journey Link',
            'item_link_description' => 'A link to a journey.',
        ),
        'public' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-schedule',
        'supports' => array(
            0 => 'title',
            1 => 'editor',
            2 => 'thumbnail',
            3 => 'custom-fields',
        ),
        'has_archive' => 'journeys',
        'rewrite' => array(
            'feeds' => false,
        ),
        'delete_with_user' => false,
    ));



}

add_post_type_support('idwiz_initiative', 'thumbnail');

function idemailwiz_custom_archive_templates($tpl)
{
    if (is_post_type_archive('idwiz_initiative')) {
        $tpl = plugin_dir_path(__FILE__) . 'templates/archive-initiative.php';
    }
    return $tpl;
}

add_filter('archive_template', 'idemailwiz_custom_archive_templates');

//Register folder taxonomy
add_action('init', 'idemailwiz_create_taxonomies', 10);
function idemailwiz_create_taxonomies()
{
    $folderLabels = array(
        'name' => 'Folders',
        'singular_name' => 'Folder',
        'public' => true,
        'show_admin_column' => true,
        // Add other labels as needed
    );

    $folderargs = array(
        'labels' => $folderLabels,
        'public' => true,
        'hierarchical' => true,
        'show_in_rest' => true,
        // This is required if you want to use this taxonomy with Gutenberg

        'default_term' => array(
            'name' => 'All Templates',
            'slug' => 'all',
        ),
        'has_archive' => true,
        'rewrite' => array(
            'slug' => 'templates',
            'hierarchical' => true,
            'with_front' => false,
        ),
        'query_var' => true,
    );

    register_taxonomy('idemailwiz_folder', 'idemailwiz_template', $folderargs);



}




// Custom rewrite rules and endpoints
function idemailwiz_custom_rewrite_rule()
{
    // Template editor rewrite
    add_rewrite_rule('^template/([0-9]+)/([^/]+)/?', 'index.php?post_type=idemailwiz_template&p=$matches[1]', 'top');

    // Add custom endpoints
    add_rewrite_endpoint('metrics/campaign', EP_ROOT);
    add_rewrite_endpoint('build-template', EP_ROOT);
    add_rewrite_endpoint('user-profile', EP_ROOT);
    add_rewrite_endpoint('settings', EP_ROOT);
    add_rewrite_endpoint('sync-station', EP_ROOT);
    add_rewrite_endpoint('external-cron', EP_ROOT); // New endpoint for external cron
}

add_action('init', 'idemailwiz_custom_rewrite_rule', 10);





//Use our custom templates for archives, single templates, etc
function idemailwiz_template_chooser($template)
{
    global $wp_query;
    $post_type = get_query_var('post_type');

    if (is_page('playground')) {
        return dirname(__FILE__) . '/templates/wiz-playground.php';
    }

    if (is_post_type_archive('idemailwiz_template') || is_tax('idemailwiz_folder')) {
        return dirname(__FILE__) . '/templates/taxonomy-idemailwiz_folder.php';
    }

    if (get_post_type() == 'idemailwiz_template' && is_single()) {
        return dirname(__FILE__) . '/templates/single-idemailwiz_template.php';
    }

    if (get_post_type() == 'idwiz_initiative' && is_single()) {
        return dirname(__FILE__) . '/templates/single-initiative.php';
    }

    if (get_post_type() == 'journey' && is_single()) {
        return dirname(__FILE__) . '/templates/single-journey.php';
    }

    if (strpos($_SERVER['REQUEST_URI'], '/metrics/campaign') !== false) {
        return dirname(__FILE__) . '/templates/metrics-campaign.php';
    }

    if (strpos($_SERVER['REQUEST_URI'], '/journeys') !== false) {
        return dirname(__FILE__) . '/templates/journeys.php';
    }

    // If user-profile endpoint is accessed
    if (isset($wp_query->query_vars['user-profile'])) {
        $userProfileTemplate = plugin_dir_path(__FILE__) . 'templates/idemailwiz-user-profile.php';

        // Use the custom template if it exists
        if (!empty($userProfileTemplate)) {
            return $userProfileTemplate;
        }
    }

    // If settings page endpoint is accessed
    if (isset($wp_query->query_vars['settings'])) {
        $wizSettingsTemplate = plugin_dir_path(__FILE__) . 'templates/idemailwiz-settings.php';

        // Use the custom template if it exists
        if (!empty($wizSettingsTemplate)) {
            return $wizSettingsTemplate;
        }
    }

    // If settings page endpoint is accessed
    if (isset($wp_query->query_vars['sync-station'])) {
        $syncStationTemplate = plugin_dir_path(__FILE__) . 'templates/idemailwiz-sync-station.php';

        // Use the custom template if it exists
        if (!empty($syncStationTemplate)) {
            return $syncStationTemplate;
        }
    }

    // Set templates based on plugin settings
    $options = get_option('idemailwiz_settings');
    $dashboard_page = isset($options['dashboard_page']) ? $options['dashboard_page'] : '';
    if ($dashboard_page) {
        if (is_page($dashboard_page)) {
            return dirname(__FILE__) . '/templates/idemailwiz-dashboard.php';
        }
    }

    $campaigns_page = isset($options['campaigns_page']) ? $options['campaigns_page'] : '';
    if ($campaigns_page) {
        if (is_page($campaigns_page)) {
            return dirname(__FILE__) . '/templates/idemailwiz-campaigns.php';
        }
    }

    $reports_page = isset($options['reports_page']) ? $options['reports_page'] : '';

    if ($reports_page) {

        // Check if the current page is either the reports page or a child of the reports page
        if (is_page($reports_page) || wp_get_post_parent_id(get_the_ID()) == $reports_page) {
            return dirname(__FILE__) . '/templates/idemailwiz-reports.php';
        }
    }


    return $template;
}
add_filter('template_include', 'idemailwiz_template_chooser');

// Add custom body classes
function idemailwiz_body_classes($classes)
{
    $options = get_option('idemailwiz_settings');
    $campaigns_page = $options['campaigns_page'];
    if (is_page($campaigns_page)) {
        $classes[] = 'wiz_metrics';
    }
    return $classes;
}

add_filter('body_class', 'idemailwiz_body_classes');


//Custom redirects
function redirect_to_proper_url()
{
    global $post;
    //Redirect any weird/wrong template URLs to the proper/current URL (handles cases where the slug changed but an old link was used)
    if (is_singular('idemailwiz_template')) {
        $post_slug = $post->post_name;
        if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] != "/template/{$post->ID}/{$post_slug}/") {
            wp_redirect(home_url("/template/{$post->ID}/{$post_slug}/"), 301);
            exit;
        }
    }
    //If someone lands on /templates, redirect them to /templates/all
    if (isset($_SERVER['REQUEST_URI']) && trim($_SERVER['REQUEST_URI'], '/') == 'templates') {
        wp_redirect(site_url('/templates/all'), 301);
        exit;
    }
}
add_action('template_redirect', 'redirect_to_proper_url', 11);


add_action('template_redirect', 'idemailwiz_handle_template_request', 20);
function idemailwiz_handle_template_request()
{
    global $wp_query, $wp;

    // Handle build-template
    if (isset($wp_query->query_vars['build-template'])) {

        $current_url = home_url(add_query_arg(array(), $wp->request));
        if (strpos($current_url, '/build-template/') !== false && !isset($_SERVER['HTTP_REFERER'])) {
            $dieMessage = 'Direct access to the template builder endpoint is not allowed!';
            wp_die($dieMessage);
            exit;
        }

        echo '<div style="padding: 30px; text-align: center; font-weight: bold; font-family: Poppins, sans-serif;"><i style="font-family: Font Awesome 5;" class="fas fa-spinner fa-spin"></i>  Loading template...<br/>';
        exit;
    }
}






//Enqueue stuff
add_action('wp_enqueue_scripts', 'idemailwiz_enqueue_assets');
function idemailwiz_enqueue_assets()
{

    wp_enqueue_script('jquery');

    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0', true);
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array(), '4.1.0', true);

    // Enqueue Luxon
    wp_enqueue_script('luxon', 'https://cdn.jsdelivr.net/npm/luxon@2.x/build/global/luxon.min.js', array('jquery'), null, true);

    // Enqueue Chart.js, dependent on Luxon because we will be using the Luxon adapter.
    wp_enqueue_script('charts-js', 'https://cdn.jsdelivr.net/npm/chart.js', array('jquery', 'luxon'), null, true);
    wp_enqueue_script('charts-js-trendline', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-trendline', array('jquery', 'luxon', 'charts-js'), null, true);

    // Enqueue the Luxon adapter for Chart.js. Dependent on both Chart.js and Luxon.
    wp_enqueue_script('charts-js-luxon-adapter', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1.x/dist/chartjs-adapter-luxon.min.js', array('charts-js', 'luxon'), null, true);

    // Enqueue the data labels plugin for Chart.js. Only dependent on Chart.js.
    wp_enqueue_script('charts-js-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels', array('charts-js'), null, true);


    //wp_enqueue_script('DataTables', plugin_dir_url(__FILE__) . 'vendors/DataTables/datatables.min.js', array());
    wp_enqueue_script('DataTables', 'https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-1.13.6/b-2.4.2/b-colvis-2.4.2/b-html5-2.4.2/cr-1.7.0/date-1.5.1/fc-4.3.0/fh-3.4.0/rr-1.4.1/sc-2.2.0/sb-1.5.0/sl-1.7.0/sr-1.3.0/datatables.min.js', array());
    wp_enqueue_script('DataTablesScrollResize', plugin_dir_url(__FILE__) . 'vendors/DataTables/ScrollResize/dataTables.scrollResize.min.js', array());
    wp_enqueue_script('DataTablesEllips', '//cdn.datatables.net/plug-ins/1.13.6/dataRender/ellipsis.js', array());
    wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array());

    wp_enqueue_style('font-awesome-6', plugin_dir_url(__FILE__) . 'vendors/Font Awesome/css/all.css', array());
    wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array());



    wp_enqueue_style('DataTablesCss', plugin_dir_url(__FILE__) . 'vendors/DataTables/datatables.css', array());
    //wp_enqueue_style('DataTablesCss', 'https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-1.13.6/b-2.4.2/b-colvis-2.4.2/b-html5-2.4.2/cr-1.7.0/date-1.5.1/fc-4.3.0/fh-3.4.0/rr-1.4.1/sc-2.2.0/sb-1.5.0/sl-1.7.0/sr-1.3.0/datatables.min.css', array());
    wp_enqueue_style('select2css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array());

    // Activate wordpress image uploader for settings pages
    if (isset($_GET['page']) && $_GET['page'] == 'idemailwiz_settings') {
        wp_enqueue_media();
        wp_enqueue_script('idemailwiz-image-upload', plugin_dir_url(__FILE__) . 'js/image-upload.js', array('jquery'), null, true);
    }

    $scripts = array(
        'moment-js' => array('/js/libraries/moment.min.js', array()),
        'dt-date-col-sort' => array('/js/dt-date-col-sort.js', array('moment-js')),
        'id-general' => array('/js/id-general.js', array('jquery')),
        'template-editor' => array('/js/template-editor.js', array('jquery', 'id-general')),
        'template-actions' => array('/js/template-actions.js', array('jquery', 'id-general')),
        'folder-actions' => array('/js/folder-actions.js', array('jquery', 'id-general')),
        'user-favorites' => array('/js/user-favorites.js', array('jquery', 'id-general')),
        'bulk-actions' => array('/js/bulk-actions.js', array('jquery', 'id-general', 'folder-actions', 'template-actions')),
        'iterable-actions' => array('/js/iterable-actions.js', array('jquery', 'id-general', 'bulk-actions')),
        'data-tables' => array('/js/data-tables.js', array('jquery', 'id-general')),
        'wiz-charts' => array('/js/wiz-charts.js', array('jquery', 'id-general', 'charts-js')),
        'wiz-metrics' => array('/js/metrics.js', array('jquery', 'id-general', 'wiz-charts', 'data-tables')),
        'initiatives' => array('/js/initiatives.js', array('jquery', 'id-general', 'wiz-charts', 'data-tables')),
        'dashboard' => array('/js/idwiz-dashboard.js', array('jquery', 'id-general', 'wiz-charts', 'data-tables')),
        'google-sheets-api' => array('/js/google-sheets-api.js', array('jquery', 'id-general')),

    );

    wp_enqueue_style(
        'id-style',
        plugins_url('/style.css', __FILE__),
        array()
    );



    foreach ($scripts as $handle => $script) {
        wp_enqueue_script($handle, plugins_url($script[0], __FILE__), $script[1], '1.0.0', true);
        $handle_underscore = str_replace('-', '_', $handle);
        wp_localize_script(
            $handle,
            'idAjax_' . $handle_underscore,
            array(
                'nonce' => wp_create_nonce($handle),
                'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
                'currentPost' => get_post(get_the_ID()),
                'stylesheet' => plugins_url('', __FILE__),
                'plugin_url' => plugin_dir_url(__FILE__),
                'site_url' => get_bloginfo('url'),
            )
        );
    }

    wp_localize_script(
        'id-general',
        'idAjax',
        array(
            'plugin_url' => plugin_dir_url(__FILE__),
            'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
            'currentPost' => get_post(get_the_ID()),
            'stylesheet' => plugins_url('', __FILE__),
            'site_url' => get_bloginfo('url'),
        )
    );

    wp_enqueue_script('highlighterjs', '//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js', array('jquery'), '11.7.0', true);
    wp_enqueue_style('highlighter-agate', plugins_url('/styles/agate.css', __FILE__), array(), '11.7.0');
}