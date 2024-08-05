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
include(plugin_dir_path(__FILE__) . 'includes/wiz-options.php');


// Deactivation
register_deactivation_hook(__FILE__, 'idemailwiz_deactivate');
function idemailwiz_deactivate()
{
    flush_rewrite_rules();
}

//require files
require_once(plugin_dir_path(__FILE__) . 'includes/functions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/databases.php');
require_once(plugin_dir_path(__FILE__) . 'includes/database-cleanup.php');
require_once(plugin_dir_path(__FILE__) . 'includes/initiatives.php');
require_once(plugin_dir_path(__FILE__) . 'includes/journeys.php');
require_once(plugin_dir_path(__FILE__) . 'includes/wizSnippets.php');
require_once(plugin_dir_path(__FILE__) . 'includes/comparisons.php');
require_once(plugin_dir_path(__FILE__) . 'includes/sync.php');
require_once(plugin_dir_path(__FILE__) . 'includes/manual-import.php');
require_once(plugin_dir_path(__FILE__) . 'includes/wiz-log.php');
require_once(plugin_dir_path(__FILE__) . 'includes/cUrl.php');
require_once(plugin_dir_path(__FILE__) . 'includes/wiz-rest.php');
require_once(plugin_dir_path(__FILE__) . 'includes/pulse-connection.php');
require_once(plugin_dir_path(__FILE__) . 'includes/data-tables.php');
require_once(plugin_dir_path(__FILE__) . 'includes/charts.php');
require_once(plugin_dir_path(__FILE__) . 'includes/reporting.php');
require_once(plugin_dir_path(__FILE__) . 'includes/promo-codes.php');

require_once(plugin_dir_path(__FILE__) . 'builder-v2/chunks.php');

//require_once(plugin_dir_path(__FILE__) . 'includes/wysiwyg.php');
require_once(plugin_dir_path(__FILE__) . 'includes/template-builder.php');
require_once(plugin_dir_path(__FILE__) . 'includes/chunk-helpers.php');
require_once(plugin_dir_path(__FILE__) . 'includes/folder-tree.php');
require_once(plugin_dir_path(__FILE__) . 'includes/folder-template-actions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/archive-query.php');
require_once(plugin_dir_path(__FILE__) . 'includes/iterable-functions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/google-sheets-api.php');


add_filter('post_type_link', 'custom_template_permalink', 10, 2);
function custom_template_permalink($post_link, $post) {
    if ($post->post_type === 'idemailwiz_template') {
        return home_url("/template/{$post->ID}/" . $post->post_name . '/');
    }
    return $post_link;
}

// Register custom post types
add_action('init', 'idwiz_register_custom_post_types', 0);
function idwiz_register_custom_post_types()
{
    $promoCodeLabels = array(
        'name' => 'Promo Codes',
        'singular_name' => 'Promo Code',
        'menu_name' => __('Promo Codes', 'idemailwiz'),
        'name_admin_bar' => __('Promo Code', 'idemailwiz'),
        'archives' => __('Promo Code Archives', 'idemailwiz'),
        'attributes' => __('Promo Code Attributes', 'idemailwiz'),
        'parent_item_colon' => __('Parent Promo Code:', 'idemailwiz'),
        'all_items' => __('All Promo Codes', 'idemailwiz'),
        'add_new_item' => __('Add New Promo Code', 'idemailwiz'),
        'add_new' => __('Add New', 'idemailwiz'),
        'new_item' => __('New Promo Code', 'idemailwiz'),
        'edit_item' => __('Edit Promo Code', 'idemailwiz'),
        'update_item' => __('Update Promo Code', 'idemailwiz'),
        'view_item' => __('View Promo Code', 'idemailwiz'),
        'view_items' => __('View Promo Codes', 'idemailwiz'),
        'search_items' => __('Search Promo Code', 'idemailwiz'),
        'insert_into_item' => __('Insert into promo code', 'idemailwiz'),
        'uploaded_to_this_item' => __('Uploaded to this promo code', 'idemailwiz'),
        'items_list' => __('Promo codes list', 'idemailwiz'),
        'items_list_navigation' => __('Promo codes list navigation', 'idemailwiz'),
        'filter_items_list' => __('Filter promo codes list', 'idemailwiz'),
    );

    $promoCodeArgs = array(
        'labels' => $promoCodeLabels,
        'public' => true,
        'has_archive' => 'promo-codes',
        'supports' => array('title', 'custom-fields'),
        'rewrite' => array(
            'slug' => 'promo-code',
            'with_front' => false
        ),
    );
    register_post_type('wiz_promo_code', $promoCodeArgs);

    function custom_promo_code_rewrite_rules()
    {
        add_rewrite_rule(
            'promo-code/([0-9]+)/?$',
            'index.php?post_type=wiz_promo_code&p=$matches[1]',
            'top'
        );

        // Preserve the archive page rule
        add_rewrite_rule(
            'promo-codes/?$',
            'index.php?post_type=wiz_promo_code',
            'top'
        );
    }
    add_action('init', 'custom_promo_code_rewrite_rules', 10, 0);

    function custom_promo_code_post_link($post_link, $post)
    {
        if ($post->post_type === 'wiz_promo_code') {
            return home_url("promo-code/{$post->ID}/");
        }
        return $post_link;
    }
    add_filter('post_type_link', 'custom_promo_code_post_link', 10, 2);

    function custom_promo_code_request($query_vars)
    {
        if (
            isset($query_vars['post_type']) && $query_vars['post_type'] === 'wiz_promo_code'
            && isset($query_vars['name'])
        ) {
            $query_vars['p'] = $query_vars['name'];
            unset($query_vars['name']);
        }
        return $query_vars;
    }
    add_filter('request', 'custom_promo_code_request');

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
        'show_in_rest' => true, // This is required if you want to use this post type with Gutenberg
        'rewrite' => array(
            'slug' => 'template', // This is the base slug for your templates
            'with_front' => false, // This ensures that the slug is exactly what you specify, not prepended with a front base
        ),
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

    $comparisonLabels = array(
        'name' => _x('Comparisons', 'Post Type General Name', 'idemailwiz'),
        'singular_name' => _x('Comparison', 'Post Type Singular Name', 'idemailwiz'),
        'menu_name' => __('Comparisons', 'idemailwiz'),
        'name_admin_bar' => __('Comparison', 'idemailwiz'),
        'archives' => __('Comparison Archives', 'idemailwiz'),
        'attributes' => __('Comparison Attributes', 'idemailwiz'),
        'parent_item_colon' => __('Parent Comparison:', 'idemailwiz'),
        'all_items' => __('All Comparisons', 'idemailwiz'),
        'add_new_item' => __('Add New Comparison', 'idemailwiz'),
        'add_new' => __('Add New', 'idemailwiz'),
        'new_item' => __('New Comparison', 'idemailwiz'),
        'edit_item' => __('Edit Comparison', 'idemailwiz'),
        'update_item' => __('Update Comparison', 'idemailwiz'),
        'view_item' => __('View Comparison', 'idemailwiz'),
        'view_items' => __('View Comparisons', 'idemailwiz'),
        'search_items' => __('Search Comparison', 'idemailwiz'),
        'not_found' => __('Not found', 'idemailwiz'),
        'not_found_in_trash' => __('Not found in Trash', 'idemailwiz'),
        'featured_image' => __('Featured Image', 'idemailwiz'),
        'set_featured_image' => __('Set featured image', 'idemailwiz'),
        'remove_featured_image' => __('Remove featured image', 'idemailwiz'),
        'use_featured_image' => __('Use as featured image', 'idemailwiz'),
        'insert_into_item' => __('Insert into comparison', 'idemailwiz'),
        'uploaded_to_this_item' => __('Uploaded to this comparison', 'idemailwiz'),
        'items_list' => __('Comparisons list', 'idemailwiz'),
        'items_list_navigation' => __('Comparisons list navigation', 'idemailwiz'),
        'filter_items_list' => __('Filter comparisons list', 'idemailwiz'),
    );
     $comparisonArgs = array(
        'label' => __('Comparison', 'idemailwiz'),
        'description' => __('Comparison Description', 'idemailwiz'),
        'labels' => $comparisonLabels,
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
        'rewrite' => ['slug' => 'comparison'],
        'has_archive' => 'comparisons',
        'capability_type' => 'post',
        'show_in_rest' => true,
        // Enable Gutenberg editor
    );

    register_post_type('idwiz_comparison', $comparisonArgs);



    // register_post_type('journey', array(
    //     'labels' => array(
    //         'name' => 'Journeys',
    //         'singular_name' => 'Journey',
    //         'menu_name' => 'Journeys',
    //         'all_items' => 'All Journeys',
    //         'edit_item' => 'Edit Journey',
    //         'view_item' => 'View Journey',
    //         'view_items' => 'View Journeys',
    //         'add_new_item' => 'Add New Journey',
    //         'new_item' => 'New Journey',
    //         'parent_item_colon' => 'Parent Journey:',
    //         'search_items' => 'Search Journeys',
    //         'not_found' => 'No journeys found',
    //         'not_found_in_trash' => 'No journeys found in Trash',
    //         'archives' => 'Journey Archives',
    //         'attributes' => 'Journey Attributes',
    //         'insert_into_item' => 'Insert into journey',
    //         'uploaded_to_this_item' => 'Uploaded to this journey',
    //         'filter_items_list' => 'Filter journeys list',
    //         'filter_by_date' => 'Filter journeys by date',
    //         'items_list_navigation' => 'Journeys list navigation',
    //         'items_list' => 'Journeys list',
    //         'item_published' => 'Journey published.',
    //         'item_published_privately' => 'Journey published privately.',
    //         'item_reverted_to_draft' => 'Journey reverted to draft.',
    //         'item_scheduled' => 'Journey scheduled.',
    //         'item_updated' => 'Journey updated.',
    //         'item_link' => 'Journey Link',
    //         'item_link_description' => 'A link to a journey.',
    //     ),
    //     'public' => true,
    //     'show_in_rest' => true,
    //     'menu_icon' => 'dashicons-schedule',
    //     'supports' => ['title', 'editor', 'custom-fields', 'thumbnail'],
    //     'has_archive' => 'journeys',
    //     'rewrite' => ['slug' => 'journey'],
    //     'delete_with_user' => false,
    // )
    // );

    register_post_type('wysiwyg_snippet', [
        'labels' => ['name' => __('Snippets'), 'singular_name' => __('Snippet')],
        'public' => true,
        'has_archive' => 'snippets',
        'rewrite' => ['slug' => 'snippet'],
        'supports' => ['title', 'editor', 'custom-fields'], 
        'delete_with_user' => false,
        'capability_type' => 'post',
        'show_in_rest' => true,
        'show_in_menu' => 'edit.php?post_type=idemailwiz_template',
    ]);



}

add_post_type_support('idwiz_initiative', 'thumbnail');

function idemailwiz_custom_archive_templates($tpl)
{
    if (is_post_type_archive('idwiz_initiative')) {
        $tpl = plugin_dir_path(__FILE__) . 'templates/archive-initiative.php';
    }

    if (is_post_type_archive('idwiz_comparison')) {
        $tpl = plugin_dir_path(__FILE__) . 'templates/archive-comparison.php';
    }

    if (is_post_type_archive('wiz_promo_code')) {
        $tpl = plugin_dir_path(__FILE__) . 'templates/archive-promo-code.php';
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
    add_rewrite_endpoint('metrics/journey', EP_ROOT);
    //add_rewrite_endpoint('build-template', EP_ROOT);
    add_rewrite_endpoint('build-template-v2', EP_ROOT);
    add_rewrite_endpoint('user-profile', EP_ROOT);
    add_rewrite_endpoint('settings', EP_ROOT);
    add_rewrite_endpoint('sync-station', EP_ROOT);
    add_rewrite_endpoint('campaign-monitor', EP_ROOT);
    add_rewrite_endpoint('course-mapping', EP_ROOT);

    add_rewrite_endpoint('endpoints/iterable-triggeredSend', EP_ROOT);
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
        return dirname(__FILE__) . '/templates/single-idemailwiz_template-v2.php';
    }

    if (get_post_type() == 'idwiz_initiative' && is_single()) {
        return dirname(__FILE__) . '/templates/single-initiative.php';
    }
    if (get_post_type() == 'wiz_promo_code' && is_single()) {
        return dirname(__FILE__) . '/templates/single-promo-code.php';
    }

    if (get_post_type() == 'idwiz_comparison' && is_single()) {
        return dirname(__FILE__) . '/templates/single-comparison.php';
    }

    // if (get_post_type() == 'journey' && is_single()) {
    //     return dirname(__FILE__) . '/templates/single-journey.php';
    // }
    
    if (get_post_type() == 'wysiwyg_snippet' && is_single()) {
        return dirname(__FILE__) . '/templates/single-snippet.php';
    }

    if (strpos($_SERVER['REQUEST_URI'], '/metrics/campaign') !== false) {
        return dirname(__FILE__) . '/templates/single-campaign.php';
    }


    if (strpos($_SERVER['REQUEST_URI'], '/journeys') !== false) {
        return dirname(__FILE__) . '/templates/archive-journeys.php';
    }

	if ( strpos( $_SERVER['REQUEST_URI'], '/metrics/journey' ) !== false ) {
		return dirname( __FILE__ ) . '/templates/single-journey.php';
	}

    if (strpos($_SERVER['REQUEST_URI'], '/snippets') !== false) {
        return dirname(__FILE__) . '/templates/archive-snippet.php';
    }

	if ( strpos( $_SERVER['REQUEST_URI'], '/tools/subject-line-builder' ) !== false ) {
		return dirname( __FILE__ ) . '/templates/subject-line-builder.php';
	}

    if (strpos($_SERVER['REQUEST_URI'], '/wiz-rest') !== false) {
        return dirname(__FILE__) . '/templates/wiz-rest-ui.php';
    }

    


    // If user-profile endpoint is accessed
    if (isset($wp_query->query_vars['user-profile'])) {
        $userProfileTemplate = plugin_dir_path(__FILE__) . 'templates/user-profile.php';

        // Use the custom template if it exists
        if (!empty($userProfileTemplate)) {
            return $userProfileTemplate;
        }
    }

    // If settings page endpoint is accessed
    if (isset($wp_query->query_vars['settings'])) {
        $wizSettingsTemplate = plugin_dir_path(__FILE__) . 'templates/wiz-settings.php';

        // Use the custom template if it exists
        if (!empty($wizSettingsTemplate)) {
            return $wizSettingsTemplate;
        }
    }

    // If sync station page endpoint is accessed
    if (isset($wp_query->query_vars['sync-station'])) {
        $syncStationTemplate = plugin_dir_path(__FILE__) . 'templates/sync-station.php';

        // Use the custom template if it exists
        if (!empty($syncStationTemplate)) {
            return $syncStationTemplate;
        }
    }

    // If campaign monitor page endpoint is accessed
    if (isset($wp_query->query_vars['campaign-monitor'])) {
        $campaignMonitorTemplate = plugin_dir_path(__FILE__) . 'templates/campaign-monitor.php';

        // Use the custom template if it exists
        if (!empty($campaignMonitorTemplate)) {
            return $campaignMonitorTemplate;
        }
    }

    // If Iterable triggered send endpoint is accessed
    if (isset($wp_query->query_vars['endpoints/iterable-triggeredSend'])) {
        $endpointTriggeredSendTemplate = plugin_dir_path(__FILE__) . 'endpoints/iterable-triggeredSend.php';

        // Use the custom template if it exists
        if (!empty($endpointTriggeredSendTemplate)) {
            return $endpointTriggeredSendTemplate;
        }
    }

    // If course mapping page endpoint is accessed
    if (isset($wp_query->query_vars['course-mapping'])) {
        $courseMappingTemplate = plugin_dir_path(__FILE__) . 'templates/course-mapping.php';

        // Use the custom template if it exists
        if (!empty($courseMappingTemplate)) {
            return $courseMappingTemplate;
        }
    }

    // Set templates based on plugin settings
    $options = get_option('idemailwiz_settings');
    $dashboard_page = isset($options['dashboard_page']) ? $options['dashboard_page'] : '';
    if ($dashboard_page) {
        if (is_page($dashboard_page)) {
            return dirname(__FILE__) . '/templates/dashboard.php';
        }
    }

    $campaigns_page = isset($options['campaigns_page']) ? $options['campaigns_page'] : '';
    if ($campaigns_page) {
        if (is_page($campaigns_page)) {
            return dirname(__FILE__) . '/templates/campaigns-table.php';
        }
    }

    $experiments_page = isset($options['experiments_page']) ? $options['experiments_page'] : '';
    if ($experiments_page) {
        if (is_page($experiments_page)) {
            return dirname(__FILE__) . '/templates/experiments.php';
        }
    }

    $reports_page = isset($options['reports_page']) ? $options['reports_page'] : '';

    if ($reports_page) {

        // Check if the current page is either the reports page or a child of the reports page
        if (is_page($reports_page)) {
            return dirname(__FILE__) . '/templates/reports.php';
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
    // if (is_singular('idemailwiz_template')) {
    //     $post_slug = $post->post_name;
    //     if ( isset( $_SERVER['REQUEST_URI'] ) && $_SERVER['REQUEST_URI'] != "/template/{$post->ID}/{$post_slug}/" ) {
	// 		wp_redirect( home_url( "/template/{$post->ID}/{$post_slug}/" ) . $_SERVER['QUERY_STRING'], 301 );
	// 		exit;
	// 	}

    // }
    //If someone lands on /templates, redirect them to /templates/all
    if (isset($_SERVER['REQUEST_URI']) && trim($_SERVER['REQUEST_URI'], '/') == 'templates') {
        wp_redirect(site_url('/templates/all'), 301);
        exit;
    }
}
add_action('template_redirect', 'redirect_to_proper_url', 11);









//Enqueue stuff
add_action('wp_enqueue_scripts', 'idemailwiz_enqueue_assets');
function idemailwiz_enqueue_assets()
{

    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui');
    wp_enqueue_script('jquery-ui-sortable', null, array('jquery'));
    wp_enqueue_script('jquery-ui-resizable', null, array('jquery','jquery-ui'));


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

    //wp_enqueue_script('gradientGenerator', plugin_dir_url(__FILE__) . 'vendors/eckstein/gradientGenerator/gradientGeneratorFinal.js', array('jquery'), null, true);
    wp_enqueue_script('gradx', plugin_dir_url(__FILE__) . 'vendors/gradx/gradX.js', array('jquery'), null, true);

    wp_enqueue_script('crush', 'https://cdn.jsdelivr.net/npm/html-crush/dist/html-crush.umd.js', array(), null, true);


    //wp_enqueue_script('DataTables', plugin_dir_url(__FILE__) . 'vendors/DataTables/datatables.min.js', array());
    wp_enqueue_script('DataTables', 'https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-1.13.6/b-2.4.2/b-colvis-2.4.2/b-html5-2.4.2/cr-1.7.0/date-1.5.1/fc-4.3.0/fh-3.4.0/rr-1.4.1/sc-2.2.0/sb-1.5.0/sl-1.7.0/sr-1.3.0/datatables.min.js', array());
    wp_enqueue_script('DataTablesScrollResize', plugin_dir_url(__FILE__) . 'vendors/DataTables/ScrollResize/dataTables.scrollResize.min.js', array());
    wp_enqueue_script('DataTablesEllips', '//cdn.datatables.net/plug-ins/1.13.6/dataRender/ellipsis.js', array());

    wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array());

    wp_enqueue_script('spectrum', plugin_dir_url(__FILE__) . 'vendors/spectrum/spectrum.js', array());

    wp_enqueue_script('tinymce', plugin_dir_url( __FILE__ ) . 'vendors/tinymce/js/tinymce/tinymce.min.js');


    wp_enqueue_script('editable', plugin_dir_url(__FILE__) . 'vendors/tiny-edit-in-place/jquery.editable.min.js', array());

    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

    wp_enqueue_style('font-awesome-6', plugin_dir_url(__FILE__) . 'vendors/Font Awesome/css/all.css', array());

    wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array());

    wp_enqueue_style('spectrum-styles', plugin_dir_url(__FILE__) . 'vendors/spectrum/spectrum.css', array());


    //wp_enqueue_style('gradientGeneratorStyle', plugin_dir_url(__FILE__) . 'vendors/eckstein/gradientGenerator/gradientGeneratorFinal.css', array());
    wp_enqueue_style('gradx-css', plugin_dir_url(__FILE__) . 'vendors/gradx/gradX.css', array());


    wp_enqueue_style('DataTablesCss', plugin_dir_url(__FILE__) . 'vendors/DataTables/datatables.css', array());
    //wp_enqueue_style('DataTablesCss', 'https://cdn.datatables.net/v/dt/jszip-3.10.1/dt-1.13.6/b-2.4.2/b-colvis-2.4.2/b-html5-2.4.2/cr-1.7.0/date-1.5.1/fc-4.3.0/fh-3.4.0/rr-1.4.1/sc-2.2.0/sb-1.5.0/sl-1.7.0/sr-1.3.0/datatables.min.css', array());
    wp_enqueue_style('select2css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array());

    // Activate wordpress image uploader for settings pages
    if (isset($_GET['page']) && $_GET['page'] == 'idemailwiz_settings') {
        wp_enqueue_media();
        wp_enqueue_script('idemailwiz-image-upload', plugin_dir_url(__FILE__) . 'js/image-upload.js', array('jquery'), null, true);
    }


    $codemirror_path = plugin_dir_url(__FILE__) . 'vendors/codemirror-5.65.16/';

    $codemirror_files = array(
        
        array('codemirror', 'lib/codemirror.js', array('jquery', 'csslint'), '', true),
        array('codemirror-mode-css', 'mode/css/css.js', array('jquery', 'codemirror'), '', true),
        array('codemirror-lint', 'addon/lint/lint.js', array('jquery', 'codemirror'), '', true),
        array('codemirror-lint-css', 'addon/lint/css-lint.js', array('jquery', 'codemirror', 'codemirror-lint'), '', true),
        array('codemirror-lint-html', 'addon/lint/html-lint.js', array('jquery', 'codemirror', 'codemirror-lint'), '', true),
        array('codemirror-addon-hint', 'addon/hint/show-hint.js', array('jquery', 'codemirror'), '', true),
        array('codemirror-addon-hint-css', 'addon/hint/css-hint.js', array('jquery', 'codemirror', 'codemirror-addon-hint'), '', true),
    );

    foreach ($codemirror_files as $file) {
        wp_enqueue_script($file[0], $codemirror_path . $file[1], $file[2], '', $file[4]);
    }

    $codemirror_styles = array(
        array('codemirror', 'lib/codemirror.css'),
        array('codemirror-theme', 'theme/mbo.css', array('codemirror')),
        array('codemirror-lint-style', 'addon/lint/lint.css', array('codemirror')),
        array('codemirror-hint-style', 'addon/hint/show-hint.css', array('codemirror')),
    );

    foreach ($codemirror_styles as $style) {
        wp_enqueue_style($style[0], $codemirror_path . $style[1], isset($style[2]) ? $style[2] : array(), '', 'all');
    }

    $scripts = array(
        'moment-js' => array('/js/libraries/moment.min.js', array()),
        'dt-date-col-sort' => array('/js/dt-date-col-sort.js', array('moment-js')),
        'id-general' => array('/js/id-general.js', array('jquery')),
        'mergeTags' => array('/js/mergeTags.js', array()),

		'wiz-inits' => array( '/builder-v2/js/wiz-inits.js', array( 'jquery', 'id-general', 'jquery-ui-resizable', 'editable', 'spectrum', 'tinymce', 'gradx', 'crush', 'mergeTags') ),
		'utilities' => array( '/builder-v2/js/utilities.js', array( 'wiz-inits') ),
		'builder-functions' => array( '/builder-v2/js/builder-functions.js', array( 'wiz-inits', 'utilities' ) ),
            'template-editor' => array( '/builder-v2/js/template-editor.js', array( 'builder-functions' ) ),
            'template-actions' => array('/builder-v2/js/template-actions.js', array('builder-functions')),        
            'save-functions' => array('/builder-v2/js/save-functions.js', array('builder-functions')),        
            'import-export' => array('/builder-v2/js/import-export.js', array('builder-functions')),        
            'tiny-mce-editor' => array('/builder-v2/js/tiny-mce-editor.js', array('builder-functions')),        
                
        'preview-pane' => array('/builder-v2/js/preview-pane.js', array('builder-functions')),        

        'wizSnippets' => array('/js/wizSnippets.js', array('jquery', 'id-general', 'codemirror')),
        'folder-actions' => array('/js/folder-actions.js', array('jquery', 'id-general')),
        'user-favorites' => array('/js/user-favorites.js', array('jquery', 'id-general')),
        'bulk-actions' => array('/js/bulk-actions.js', array('jquery', 'id-general', 'folder-actions', 'template-actions')),
        'iterable-actions' => array('/js/iterable-actions.js', array('jquery', 'id-general', 'bulk-actions', 'template-editor')),
        'data-tables' => array('/js/data-tables.js', array('jquery', 'id-general')),
        'wiz-charts' => array('/js/wiz-charts.js', array('jquery', 'id-general', 'charts-js')),
        'wiz-metrics' => array('/js/metrics.js', array('jquery', 'id-general', 'wiz-charts', 'data-tables')),
        'initiatives' => array('/js/initiatives.js', array('jquery', 'id-general', 'wiz-charts', 'data-tables')),
        'comparisons' => array('/js/comparisons.js', array('jquery', 'jquery-ui-sortable', 'id-general', 'wiz-charts', 'data-tables')),
        'journeys' => array('/js/journeys.js', array('jquery', 'jquery-ui-sortable', 'id-general', 'wiz-charts', 'data-tables')),
        'dashboard' => array('/js/idwiz-dashboard.js', array('jquery', 'id-general', 'wiz-charts', 'data-tables')),
        'google-sheets-api' => array('/js/google-sheets-api.js', array('jquery', 'id-general')),
        'wiz-endpoints' => array('/js/endpoints.js', array('jquery', 'id-general')),
        'promo-codes' => array('/js/promo-codes.js', array('jquery', 'id-general')),
        'reporting' => array('/js/reporting.js', array('jquery', 'id-general', 'wiz-charts', 'data-tables')),
        

    );
    $stylesheetCacheBuster = time();
    wp_enqueue_style(
        'id-style',
        plugins_url('/style.css?v='. $stylesheetCacheBuster, __FILE__),
        array()
    );

    $wizSettings = get_option( 'idemailwiz_settings' );

    $iterableApiKey = $wizSettings['iterable_api_key'] ?? false;

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
                'current_user' => wp_get_current_user(),
                'iterable_api_key' => $iterableApiKey
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
            'current_user' => wp_get_current_user(),
        )
    );

    wp_enqueue_script('highlighterjs', '//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js', array('jquery'), '11.7.0', true);
    wp_enqueue_style('highlighter-agate', plugins_url('/styles/agate.css', __FILE__), array(), '11.7.0');
}