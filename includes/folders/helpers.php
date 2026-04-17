<?php
/**
 * Helpers used across the folder feature: breadcrumbs, parent walking,
 * the hierarchical <select> options renderer, and its ajax wrapper.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Breadcrumb used on a folder archive page.
 */
function display_folder_hierarchy() {
	$queried_object = get_queried_object();

	if ( $queried_object instanceof WP_Term ) {
		$term_links = array();

		while ( $queried_object ) {
			if ( ! is_wp_error( $queried_object ) ) {
				if ( $queried_object->term_id == get_queried_object_id() ) {
					$term_links[] = '<span>' . $queried_object->name . '</span>';
				} else {
					$term_links[] = '<a href="' . get_term_link( $queried_object->term_id ) . '">' . $queried_object->name . '</a>';
				}
				$queried_object = get_term( $queried_object->parent, 'idemailwiz_folder' );
			} else {
				break;
			}
		}

		$term_links = array_reverse( $term_links );
		echo implode( ' > ', $term_links );
	} elseif ( $queried_object instanceof WP_Post_Type ) {
		echo '<span>' . $queried_object->labels->name . '</span>';
	}
}

/**
 * Breadcrumb used on a single template page.
 */
function display_template_folder_hierarchy( $post_id ) {
	$terms = get_the_terms( $post_id, 'idemailwiz_folder' );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return;
	}
	$assigned_term = $terms[0];
	$term_links    = array();

	while ( $assigned_term ) {
		if ( ! is_wp_error( $assigned_term ) ) {
			$term_links[]  = '<a href="' . get_term_link( $assigned_term->term_id ) . '">' . $assigned_term->name . '</a>';
			$assigned_term = get_term( $assigned_term->parent, 'idemailwiz_folder' );
		} else {
			break;
		}
	}

	$term_links = array_reverse( $term_links );
	echo implode( ' > ', $term_links );
}

/**
 * Walk up the tree to check ancestry.
 */
function is_parent( $parent, $child ) {
	if ( ! $parent || ! $child ) {
		return false;
	}

	if ( isset( $child->parent ) ) {
		if ( $child->parent == $parent->term_id ) {
			return true;
		}
	} else {
		return false;
	}

	if ( $child->parent != 0 ) {
		return is_parent( $parent, get_term( $child->parent, 'idemailwiz_folder' ) );
	}

	return false;
}

/**
 * Build an indented <option> list of the folder tree, skipping the trash folder.
 * Used inside the "move to folder" / "delete & move contents" modals.
 */
function id_generate_folders_select( $parent_id = 0, $prefix = '' ) {
	$options = '';

	$folders = get_terms(
		array(
			'taxonomy'   => 'idemailwiz_folder',
			'parent'     => $parent_id,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $folders ) || empty( $folders ) ) {
		return $options;
	}

	$siteOptions = get_option( 'idemailwiz_settings' );
	$trashTerm   = isset( $siteOptions['folder_trash'] ) ? (int) $siteOptions['folder_trash'] : 0;

	foreach ( $folders as $folder ) {
		if ( $folder->term_id == $trashTerm ) {
			continue;
		}
		$options .= '<option value="' . $folder->term_id . '">' . $prefix . $folder->name . '</option>';
		$options .= id_generate_folders_select( $folder->term_id, '&nbsp;&nbsp;' . $prefix . '-&nbsp;&nbsp;' );
	}

	return $options;
}

function id_generate_folders_select_ajax() {
	// Called from both the folder-actions and template-actions modals, so
	// accept either nonce.
	$nonceCheck = check_ajax_referer( 'folder-actions', 'security', false );
	if ( ! $nonceCheck ) {
		check_ajax_referer( 'template-actions', 'security' );
	}

	$options = id_generate_folders_select();
	wp_send_json_success( array( 'options' => $options ) );
	wp_die();
}
add_action( 'wp_ajax_id_generate_folders_select_ajax', 'id_generate_folders_select_ajax' );
