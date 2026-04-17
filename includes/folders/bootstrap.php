<?php
/**
 * Folder taxonomy bootstrap.
 *
 * Called from idemailwiz_post_activate() in the main plugin file the first
 * time the plugin runs. Ensures the 'folder_base' (root) and 'folder_trash'
 * options are pointing at real terms.
 *
 * Historically the root-folder setup here accidentally created a "Trash"
 * term for folder_base and, because wp_insert_term returns a WP_Error for
 * duplicate slugs, the subsequent trash-term setup could silently no-op.
 * This version prefers the default 'all' term that taxonomy registration
 * creates and falls back to get_term_by() before inserting, so both options
 * end up pointing at the correct terms on a fresh install.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function idemailwiz_set_root_folder() {
	$options = get_option( 'idemailwiz_settings' );
	if ( ! empty( $options['folder_base'] ) ) {
		return;
	}

	$termId = idemailwiz_get_or_create_folder_term( 'All Templates', 'all' );
	if ( $termId ) {
		$options                = is_array( $options ) ? $options : array();
		$options['folder_base'] = $termId;
		update_option( 'idemailwiz_settings', $options );
	}
}

function idemailwiz_set_trash_term() {
	$options = get_option( 'idemailwiz_settings' );
	if ( ! empty( $options['folder_trash'] ) ) {
		return;
	}

	$termId = idemailwiz_get_or_create_folder_term( 'Trash', 'trash' );
	if ( $termId ) {
		$options                 = is_array( $options ) ? $options : array();
		$options['folder_trash'] = $termId;
		update_option( 'idemailwiz_settings', $options );
	}
}

function idemailwiz_get_or_create_folder_term( $name, $slug ) {
	$existing = get_term_by( 'slug', $slug, 'idemailwiz_folder' );
	if ( $existing && ! is_wp_error( $existing ) ) {
		return (int) $existing->term_id;
	}

	$inserted = wp_insert_term( $name, 'idemailwiz_folder', array( 'slug' => $slug ) );
	if ( is_wp_error( $inserted ) ) {
		return 0;
	}

	return (int) $inserted['term_id'];
}
