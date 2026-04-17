<?php
/**
 * Register the idemailwiz_folder taxonomy used by the template library.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'idemailwiz_create_taxonomies', 10 );
function idemailwiz_create_taxonomies() {
	$folderLabels = array(
		'name'              => 'Folders',
		'singular_name'     => 'Folder',
		'public'            => true,
		'show_admin_column' => true,
	);

	$folderargs = array(
		'labels'       => $folderLabels,
		'public'       => true,
		'hierarchical' => true,
		'show_in_rest' => true,
		'default_term' => array(
			'name' => 'All Templates',
			'slug' => 'all',
		),
		'has_archive'  => true,
		'rewrite'      => array(
			'slug'         => 'templates',
			'hierarchical' => true,
			'with_front'   => false,
		),
		'query_var'    => true,
	);

	register_taxonomy( 'idemailwiz_folder', 'idemailwiz_template', $folderargs );
}
