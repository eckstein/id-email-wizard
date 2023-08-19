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
function idwizacfheader() {
	acf_form_head();
}
add_action('wp_head', 'idwizacfheader');

// Plugin Activation
register_activation_hook(__FILE__, 'idemailwiz_activate');
function idemailwiz_activate() {

    //Create custom databases
    idemailwiz_create_databases();

	// Schedule an event to run on the next page load
    wp_schedule_single_event( time(), 'idemailwiz_on_next_page_load' );
	
	//flush permalinks
    flush_rewrite_rules();
	
}

//delayed activation so it's after init
add_action('idemailwiz_on_next_page_load', 'idemailwiz_post_activate');
function idemailwiz_post_activate() {
	//get the term id of the default term in the folder taxonomy
	idemailwiz_set_root_folder();
	//set the trash term
	idemailwiz_set_trash_term();
	//flush permalinks
    flush_rewrite_rules(); 
}
//Get the term ID of the default folder and save it to options (runs on first page load after activation)
function idemailwiz_set_root_folder() {
	 // Retrieve the current settings
     $options = get_option('idemailwiz_settings');

     // Check if the 'folder_base' setting is already set
     if (empty($options['folder_base'])) {
         // Create a new term for the root folder
         $trashTerm = wp_insert_term('Trash', 'idemailwiz_folder', array('slug'=>'trash'));
 
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
function idemailwiz_set_trash_term() {
    // Retrieve the current settings
    $options = get_option('idemailwiz_settings');

    // Check if the 'folder_trash' setting is already set
    if (empty($options['folder_trash'])) {
        // Create a new term for the trash folder
        $trashTerm = wp_insert_term('Trash', 'idemailwiz_folder', array('slug'=>'trash'));

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
function idemailwiz_deactivate() {
    flush_rewrite_rules();
}

//require files
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-functions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-shortcodes.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-table-mapping.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-databases.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-sync.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-wysiwyg.php');
require_once(plugin_dir_path(__FILE__) . 'includes/template-builder.php');
require_once(plugin_dir_path(__FILE__) . 'includes/chunk-helpers.php');
require_once(plugin_dir_path(__FILE__) . 'includes/folder-tree.php');
require_once(plugin_dir_path(__FILE__) . 'includes/folder-template-actions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/iterable-functions.php');


// Register custom post type 
add_action( 'init', 'idemailwiz_create_template_post_type', 0 );
function idemailwiz_create_template_post_type() {
    $labels = array(
        'name' => 'Templates',
		'singular_name' => 'Template',
        // Add other labels as needed
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
        'show_in_rest' => true, // This is required if you want to use this post type with Gutenberg
		
    );

    register_post_type('idemailwiz_template', $args);
}

//Register folder taxonomy
add_action( 'init', 'idemailwiz_create_folder_taxonomy', 10 );
function idemailwiz_create_folder_taxonomy() {
    $labels = array(
        'name' => 'Folders',
		'singular_name' => 'Folder',
		'public' => true,
		'show_admin_column' => true,
        // Add other labels as needed
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'hierarchical' => true, 
        'show_in_rest' => true, // This is required if you want to use this taxonomy with Gutenberg

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

    register_taxonomy('idemailwiz_folder', 'idemailwiz_template', $args);
}

// Custom rewrite rule
function idemailwiz_custom_rewrite_rule() {
    // Template editor re-write
    add_rewrite_rule('^template/([0-9]+)/([^/]+)/?', 'index.php?post_type=idemailwiz_template&p=$matches[1]', 'top');
    
    // Campaign metrics rewrite
    add_rewrite_endpoint('metrics/campaign', EP_ROOT);
}
add_action('init', 'idemailwiz_custom_rewrite_rule', 10);




//Use our custom templates for archives, single templates, etc
function idemailwiz_template_chooser($template) {
    global $wp_query;   
    $post_type = get_query_var('post_type');

    if( is_post_type_archive('idemailwiz_template') || is_tax('idemailwiz_folder') ) {
        return dirname(__FILE__) . '/templates/taxonomy-idemailwiz_folder.php';
    }
	
	if(get_post_type() == 'idemailwiz_template' && is_single()) {
		return dirname(__FILE__) . '/templates/single-idemailwiz_template.php';
	}

    if (strpos($_SERVER['REQUEST_URI'], '/metrics/campaign') !== false) {
        return dirname(__FILE__) . '/templates/metrics-campaign.php';
    }
    

    // Set templates based on plugin settings
    $options = get_option('idemailwiz_settings');
    $dashboard_page = isset($options['dashboard_page']) ? $options['dashboard_page'] : '';
    if ($dashboard_page) {
        if ( is_page($dashboard_page) ) {
            return dirname(__FILE__) . '/templates/idemailwiz-dashboard.php';
        }
    }

    $metrics_page = isset($options['metrics_page']) ? $options['metrics_page'] : '';
    if ($metrics_page) {
        if ( is_page($metrics_page) ) {
            return dirname(__FILE__) . '/templates/idemailwiz-metrics.php';
        }
    }

    return $template;   
}
add_filter('template_include', 'idemailwiz_template_chooser'); 

// Add custom body classes
function idemailwiz_body_classes($classes) {
    $options = get_option('idemailwiz_settings');
    $metricsPage = $options['metrics_page'];
    if (is_page($metricsPage)) {
        $classes[] = 'wiz_metrics';
    }
    return $classes;
    }

add_filter('body_class','idemailwiz_body_classes');


//Custom redirects
function redirect_to_proper_url() {
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


function idemailwiz_check_direct_access() {
    global $wp;
    $current_url = home_url(add_query_arg(array(), $wp->request));

    //Check if the current URL contains '/build-template/'
    if (strpos($current_url, '/build-template/') !== false && !isset($_SERVER['HTTP_REFERER'])) {
        $dieMessage = 'Direct access to the template builder enpoint is not allowed!';
        wp_die($dieMessage);
    }
}

add_action('template_redirect', 'idemailwiz_check_direct_access');

function idemailwiz_custom_template_preview_endpoint() {
    add_rewrite_endpoint('build-template', EP_ROOT);
}
add_action('init', 'idemailwiz_custom_template_preview_endpoint');


//Handle what happens when the custom endpoint is called (which is via the src parameter of the preview iframe)
function idemailwiz_handle_build_template_request() {
    global $wp_query;
    
    // If this is not a request for build-template then bail
    if (!isset($wp_query->query_vars['build-template'])) {
        return;
    }

    // Display the template
    idemailwiz_build_template();

    // Stop execution
    exit;
}
add_action('template_redirect', 'idemailwiz_handle_build_template_request');



//Enqueue stuff
add_action( 'wp_enqueue_scripts', 'idemailwiz_enqueue_assets' );
function idemailwiz_enqueue_assets() {

    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0', true );
    //wp_enqueue_script( 'DataTables', '//cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array(), '1.13.6', true );
    //wp_enqueue_script( 'DataTables', 'https://cdn.datatables.net/v/ju/jszip-3.10.1/dt-1.13.6/b-2.4.1/b-colvis-2.4.1/b-html5-2.4.1/cr-1.7.0/date-1.5.1/fc-4.3.0/fh-3.4.0/kt-2.10.0/r-2.5.0/rg-1.4.0/rr-1.4.1/sc-2.2.0/sb-1.5.0/sl-1.7.0/sr-1.3.0/datatables.min.js', array() );
    wp_enqueue_script( 'DataTables', plugin_dir_url(__FILE__) . 'vendors/DataTables/datatables.min.js', array() );
    wp_enqueue_script( 'DataTablesScrollResize', plugin_dir_url(__FILE__) . 'vendors/DataTables/ScrollResize/dataTables.scrollResize.min.js', array() );
    wp_enqueue_script( 'DataTablesEllips', '//cdn.datatables.net/plug-ins/1.13.6/dataRender/ellipsis.js', array() );
    
    //wp_enqueue_style( 'DataTablesCss', 'https://cdn.datatables.net/v/ju/jszip-3.10.1/dt-1.13.6/b-2.4.1/b-colvis-2.4.1/b-html5-2.4.1/cr-1.7.0/date-1.5.1/fc-4.3.0/fh-3.4.0/kt-2.10.0/r-2.5.0/rg-1.4.0/rr-1.4.1/sc-2.2.0/sb-1.5.0/sl-1.7.0/sr-1.3.0/datatables.min.css', array());
    wp_enqueue_style( 'DataTablesCss', plugin_dir_url(__FILE__) . 'vendors/DataTables/datatables.css', array());

    // Activate wordpress image uploader for settings pages
    if (isset($_GET['page']) && $_GET['page'] == 'idemailwiz_settings') {
        wp_enqueue_media();
        wp_enqueue_script('idemailwiz-image-upload', plugin_dir_url(__FILE__) . 'js/image-upload.js', array('jquery'), null, true);
    }

    $scripts = array(
        'moment-js' => array('/js/libraries/moment.min.js', array()),
        'dt-date-col-sort' => array('/js/dt-date-col-sort.js', array('moment-js')),
        'id-general' => array('/js/id-general.js', array('jquery')),
        'folder-actions' => array('/js/folder-actions.js', array('jquery', 'id-general')),
        'template-editor' => array('/js/template-editor.js', array('jquery', 'id-general')),
        'template-actions' => array('/js/template-actions.js', array('jquery', 'id-general')),
        'bulk-actions' => array('/js/bulk-actions.js', array('jquery', 'id-general', 'folder-actions', 'template-actions')),
        'iterable-actions' => array('/js/iterable-actions.js', array('jquery', 'id-general', 'bulk-actions')),
        'data-tables' => array('/js/data-tables.js', array('jquery', 'id-general')),
        
    );

    wp_enqueue_style( 'id-style',
        plugins_url( '/style.css', __FILE__ ), array()
    );

    

    foreach($scripts as $handle => $script) {
		wp_enqueue_script( $handle, plugins_url( $script[0], __FILE__ ), $script[1], '1.0.0', true );
		$handle_underscore = str_replace('-', '_', $handle);
		wp_localize_script( $handle, 'idAjax_' . $handle_underscore, array(
			'nonce' => wp_create_nonce( $handle ),
			'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'currentPost' => get_post( get_the_ID() ),
			'stylesheet' => plugins_url( '', __FILE__ ),
            'plugin_url' => plugin_dir_url(__FILE__),
		));
	}
	
    wp_localize_script( 'id-general', 'idAjax', array(
        'plugin_url' => plugin_dir_url(__FILE__),
        'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
        'currentPost' => get_post( get_the_ID() ),
        'stylesheet' => plugins_url( '', __FILE__ ),
    ));

    wp_enqueue_script( 'highlighterjs', '//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js', array('jquery'), '11.7.0', true );
    wp_enqueue_style( 'highlighter-agate', plugins_url( '/styles/agate.css', __FILE__ ), array(), '11.7.0');
}


//Add ACF options pages
	if( function_exists('acf_add_options_page') ) {
		
		acf_add_options_page(array(
			'page_title'    => 'Site Settings',
			'menu_title'    => 'Site Settings',
			'menu_slug'     => 'site-settings',
			'capability'    => 'edit_posts',
			'redirect'      => false
		));
		 acf_add_options_page(array(
			'page_title'    => 'Code Repo',
			'menu_title'    => 'Code Repo',
			'menu_slug'     => 'code-repo',
			'capability'    => 'edit_posts',
			'redirect'      => false
		));
	}




