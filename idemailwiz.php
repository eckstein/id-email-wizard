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
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-databases.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-initiatives.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-sync.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-data-tables.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-charts.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-wysiwyg.php');
require_once(plugin_dir_path(__FILE__) . 'includes/template-builder.php');
require_once(plugin_dir_path(__FILE__) . 'includes/chunk-helpers.php');
require_once(plugin_dir_path(__FILE__) . 'includes/folder-tree.php');
require_once(plugin_dir_path(__FILE__) . 'includes/folder-template-actions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/iterable-functions.php');


// Register custom post types
add_action( 'init', 'idemailwiz_create_template_post_types', 0 );
function idemailwiz_create_template_post_types() {
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
		
    );

    register_post_type('idemailwiz_template', $templateArgs);

    $initiativeLabels = array(
        'name'                  => _x('Initiatives', 'Post Type General Name', 'idemailwiz'),
        'singular_name'         => _x('Initiative', 'Post Type Singular Name', 'idemailwiz'),
        'menu_name'             => __('Initiatives', 'idemailwiz'),
        'name_admin_bar'        => __('Initiative', 'idemailwiz'),
        'archives'              => __('Initiative Archives', 'idemailwiz'),
        'attributes'            => __('Initiative Attributes', 'idemailwiz'),
        'parent_item_colon'     => __('Parent Initiative:', 'idemailwiz'),
        'all_items'             => __('All Initiatives', 'idemailwiz'),
        'add_new_item'          => __('Add New Initiative', 'idemailwiz'),
        'add_new'               => __('Add New', 'idemailwiz'),
        'new_item'              => __('New Initiative', 'idemailwiz'),
        'edit_item'             => __('Edit Initiative', 'idemailwiz'),
        'update_item'           => __('Update Initiative', 'idemailwiz'),
        'view_item'             => __('View Initiative', 'idemailwiz'),
        'view_items'            => __('View Initiatives', 'idemailwiz'),
        'search_items'          => __('Search Initiative', 'idemailwiz'),
        'not_found'             => __('Not found', 'idemailwiz'),
        'not_found_in_trash'    => __('Not found in Trash', 'idemailwiz'),
        'featured_image'        => __('Featured Image', 'idemailwiz'),
        'set_featured_image'    => __('Set featured image', 'idemailwiz'),
        'remove_featured_image' => __('Remove featured image', 'idemailwiz'),
        'use_featured_image'    => __('Use as featured image', 'idemailwiz'),
        'insert_into_item'      => __('Insert into initiative', 'idemailwiz'),
        'uploaded_to_this_item' => __('Uploaded to this initiative', 'idemailwiz'),
        'items_list'            => __('Initiatives list', 'idemailwiz'),
        'items_list_navigation' => __('Initiatives list navigation', 'idemailwiz'),
        'filter_items_list'     => __('Filter initiatives list', 'idemailwiz'),
    );

    $initiativeArgs = array(
        'label'                 => __('Initiative', 'idemailwiz'),
        'description'           => __('Initiative Description', 'idemailwiz'),
        'labels'                => $initiativeLabels,
        'supports'              => array('title', 'editor', 'thumbnail', 'custom-fields', 'revisions', 'page-attributes'),
        'taxonomies'            => array('category', 'post_tag'), // Optional
        'hierarchical'          => true,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'rewrite' =>            ['slug' => 'initiative'],
        'has_archive'           => 'initiatives',
        'capability_type'       => 'post',
        'show_in_rest'          => true, // Enable Gutenberg editor
    );

    register_post_type('idwiz_initiative', $initiativeArgs);


    
}

add_post_type_support( 'idwiz_initiative', 'thumbnail' ); 

function idemailwiz_custom_archive_templates($tpl){
  if ( is_post_type_archive ( 'idwiz_initiative' ) ) {
    $tpl = plugin_dir_path( __FILE__ ) . 'templates/archive-initiative.php';
  }
  return $tpl;
}

add_filter( 'archive_template', 'idemailwiz_custom_archive_templates' ) ;

//Register folder taxonomy
add_action( 'init', 'idemailwiz_create_taxonomies', 10 );
function idemailwiz_create_taxonomies() {
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

    register_taxonomy('idemailwiz_folder', 'idemailwiz_template', $folderargs);

    
    
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

    if(get_post_type() == 'idwiz_initiative' && is_single()) {
		return dirname(__FILE__) . '/templates/single-initiative.php';
	}

    if (strpos($_SERVER['REQUEST_URI'], '/metrics/campaign') !== false) {
        return dirname(__FILE__) . '/templates/metrics-campaign.php';
    }

    // If user-profile endpoint is accessed
    if (isset($wp_query->query_vars['user-profile'])) {
        // Path to your custom template
        $userProfileTemplate = plugin_dir_path(__FILE__) . 'templates/idemailwiz-user-profile.php';

        // Use the custom template if it exists
        if (!empty($userProfileTemplate)) {
            return $userProfileTemplate;
        }
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


add_action('template_redirect', 'idemailwiz_handle_template_request', 20);
function idemailwiz_handle_template_request() {
    global $wp_query, $wp;

    // Handle build-template
    if (isset($wp_query->query_vars['build-template'])) {

        $current_url = home_url(add_query_arg(array(), $wp->request));
        if (strpos($current_url, '/build-template/') !== false && !isset($_SERVER['HTTP_REFERER'])) {
            $dieMessage = 'Direct access to the template builder endpoint is not allowed!';
            wp_die($dieMessage);
            exit;
        }

        echo '<div style="padding: 30px; text-align: center; font-weight: bold; font-family: Poppins, sans-serif;"><i style="font-family: Font Awesome 5;" class="fas fa-spinner fa-spin"></i>  Loading template...<br/><img style="margin: 20px auto;" src="http://localhost/wp-content/uploads/2023/08/animated_loader_gif_n6b5x0.gif">';
        exit;
    }
}

add_action('init', 'idemailwiz_custom_endpoints');
function idemailwiz_custom_endpoints() {
    add_rewrite_endpoint('build-template', EP_ROOT);
    add_rewrite_endpoint('user-profile', EP_ROOT);
}




//Enqueue stuff
add_action( 'wp_enqueue_scripts', 'idemailwiz_enqueue_assets' );
function idemailwiz_enqueue_assets() {

    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0', true );
    wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array(), '4.1.0', true );
    wp_enqueue_script('charts-js', 'https://cdn.jsdelivr.net/npm/chart.js', array('jquery'), null, true);
    wp_enqueue_script('charts-js-datalabels', 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels', array('jquery', 'charts-js'), null, true);
    wp_enqueue_script( 'DataTables', plugin_dir_url(__FILE__) . 'vendors/DataTables/datatables.min.js', array() );
    wp_enqueue_script( 'DataTablesScrollResize', plugin_dir_url(__FILE__) . 'vendors/DataTables/ScrollResize/dataTables.scrollResize.min.js', array() );
    wp_enqueue_script( 'DataTablesEllips', '//cdn.datatables.net/plug-ins/1.13.6/dataRender/ellipsis.js', array() );

    wp_enqueue_style( 'DataTablesCss', plugin_dir_url(__FILE__) . 'vendors/DataTables/datatables.css', array());
    wp_enqueue_style( 'select2css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array());

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
        'wiz-charts' => array('/js/wiz-charts.js', array('jquery', 'id-general', 'charts-js')),
        'wiz-metrics' => array('/js/metrics.js', array('jquery', 'id-general', 'wiz-charts', 'data-tables')),
        'initiatives' => array('/js/initiatives.js', array('jquery', 'id-general', 'wiz-charts', 'data-tables')),
        
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
            'site_url' => get_bloginfo('url'),
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






