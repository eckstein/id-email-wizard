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
	$default_term = get_term_by('slug', 'all', 'idemailwiz_folder');	
	if ( $default_term ) {
		update_option( 'templatefoldersroot', $default_term->term_id );
	} else {
		return false;
	}
}

//Create a term for the trashed posts (runs on first page load after activation)
function idemailwiz_set_trash_term() {
	$trashTerm = wp_insert_term('Trash', 'idemailwiz_folder', array('slug'=>'trash'));
	if (!is_wp_error($trashTerm)) {
		update_option( 'templatefolderstrash', $trashTerm['term_id'] );
	}
}

// Deactivation
register_deactivation_hook(__FILE__, 'idemailwiz_deactivate');
function idemailwiz_deactivate() {
    flush_rewrite_rules();
}

//require files
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-functions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/folder-template-actions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/idemailwiz-wysiwyg.php');
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


// Custom rewrite rule
function idemailwiz_custom_rewrite_rule() {
    add_rewrite_rule('^template/([0-9]+)/([^/]+)/?', 'index.php?post_type=idemailwiz_template&p=$matches[1]', 'top');
}
add_action('init', 'idemailwiz_custom_rewrite_rule', 10);


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




//Use our custom templates for the custom post type archives and the single custom templates
function idemailwiz_template_chooser($template) {
    global $wp_query;   
    $post_type = get_query_var('post_type');

    if( is_post_type_archive('idemailwiz_template') || is_tax('idemailwiz_folder') ) {
        return dirname(__FILE__) . '/templates/taxonomy-idemailwiz_folder.php';
    }
	
	if(get_post_type() == 'idemailwiz_template' && is_single()) {
		return dirname(__FILE__) . '/templates/single-idemailwiz_template.php';
	}

    return $template;   
}
add_filter('template_include', 'idemailwiz_template_chooser'); 

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
add_action('template_redirect', 'redirect_to_proper_url');


add_action( 'wp_enqueue_scripts', 'idemailwiz_enqueue_assets' );
function idemailwiz_enqueue_assets() {
    wp_enqueue_style( 'id-style',
        plugins_url( '/style.css', __FILE__ ), array()
    );

    //enqueue jquery
    wp_enqueue_script( 'jquery' );

    //custom swal pop-ups
    wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0', true );

    //general js stuff
    wp_enqueue_script( 'id-general', plugins_url( '/js/id-general.js', __FILE__ ), array( 'jquery' ), '1.0.0', false );

    //folder actions
    wp_enqueue_script( 'folder-actions', plugins_url( '/js/folder-actions.js', __FILE__ ), array( 'jquery', 'id-general' ), '1.0.0', true );

    //template editor
    wp_enqueue_script( 'template-editor', plugins_url( '/js/template-editor.js', __FILE__ ), array( 'jquery','id-general' ), '1.0.0', true );

    //template functions
    wp_enqueue_script( 'template-actions', plugins_url( '/js/template-actions.js', __FILE__ ), array( 'jquery','id-general' ), '1.0.0', true );

    //bulk actions functions
    wp_enqueue_script( 'bulk-actions', plugins_url( '/js/bulk-actions.js', __FILE__ ), array( 'jquery','id-general','folder-actions', 'template-actions' ), '1.0.0', true );

    //iterable ajax
    wp_enqueue_script( 'iterable-actions', plugins_url( '/js/iterable-actions.js', __FILE__ ), array( 'jquery','id-general','bulk-actions'), '1.0.0', true );

    //code highlighter
    wp_enqueue_script( 'highlighterjs', '//cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js', array('jquery'), '11.7.0', true );

    //code highlighter theme
    wp_enqueue_style( 'highlighter-agate', plugins_url( '/styles/agate.css', __FILE__ ), array(), '11.7.0' );
	
	 // Localize script with your data
		if ( function_exists( 'acf_form' ) ) {
			ob_start();
			acf_form(array(
				'post_id' => 'options',
				'fields' => array('field_640559a70cf51'), // Set the field(s) to be displayed in the form
				'submit_value' => 'Select Folder', // Set the label for the submit button
			));
			$acfCatsForm = ob_get_clean();
		} else {
			$acfCatsForm = '';
		}

		$id_ajax_settings = array(
			'plugin_url' => plugin_dir_url(__FILE__),
			'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'currentPost' => get_post( get_the_ID() ),
			'nonce' => wp_create_nonce( 'id-nonce' ),
			'stylesheet' => plugins_url( '', __FILE__ ),
			'acfCatList' => $acfCatsForm,
		);

		wp_localize_script( 'id-general', 'idAjax', $id_ajax_settings );
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




