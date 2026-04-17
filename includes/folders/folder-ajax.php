<?php
/**
 * Ajax handlers for folder CRUD: add, move, delete.
 *
 * All handlers require a logged-in user; the wp_ajax_nopriv_* variants that
 * previously existed have been removed because there is no legitimate use
 * case for anonymous callers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create a new folder (taxonomy term).
 */
function id_add_new_folder() {
	check_ajax_referer( 'folder-actions', 'security' );

	$folder_name   = sanitize_text_field( $_POST['folder_name'] );
	$parent_folder = $_POST['parent_folder'];

	$response = wp_insert_term(
		$folder_name,
		'idemailwiz_folder',
		array( 'parent' => $parent_folder )
	);

	wp_send_json_success( $response );
	wp_die();
}
add_action( 'wp_ajax_id_add_new_folder', 'id_add_new_folder' );

/**
 * Move one or more folders under a new parent folder.
 */
function id_move_folder() {
	check_ajax_referer( 'folder-actions', 'security' );

	$thisFolder = $_POST['this_folder'];
	$moveInto   = (int) $_POST['move_into'];

	foreach ( $thisFolder as $folder ) {
		$moveFolder = wp_update_term(
			$folder,
			'idemailwiz_folder',
			array( 'parent' => $moveInto )
		);
		if ( is_wp_error( $moveFolder ) ) {
			wp_send_json_error(
				array( 'error' => $moveFolder->get_error_message() )
			);
			wp_die();
		}
	}

	$newParentLink = get_term_link( $moveInto, 'idemailwiz_folder' );

	wp_send_json_success(
		array(
			'moveCat'       => $moveFolder,
			'newFolderLink' => $newParentLink,
		)
	);
	wp_die();
}
add_action( 'wp_ajax_id_move_folder', 'id_move_folder' );

/**
 * Delete one or more folders, reparenting their contents into a destination
 * folder selected by the user.
 */
function id_delete_folder() {
	check_ajax_referer( 'folder-actions', 'security' );

	$folder_ids    = $_POST['this_folder'];
	$new_folder_id = (int) $_POST['move_into'];

	foreach ( $folder_ids as $folder_id ) {
		$folder = get_term( $folder_id, 'idemailwiz_folder' );
		if ( ! $folder ) {
			wp_send_json_error( array( 'error' => 'folder does not exist.' ) );
			return;
		}

		$templates = get_posts(
			array(
				'tax_query'   => array(
					array(
						'taxonomy'         => 'idemailwiz_folder',
						'field'            => 'term_id',
						'terms'            => $folder_id,
						'include_children' => false,
					),
				),
				'numberposts' => -1,
			)
		);
		foreach ( $templates as $template ) {
			$moveTemplates = wp_set_post_terms( $template->ID, array( $new_folder_id ), 'idemailwiz_folder', true );
			if ( $moveTemplates instanceof WP_Error ) {
				wp_send_json_error( array( 'Move templates error' => $moveTemplates->get_error_message() ) );
				return;
			}
		}

		$child_folders = get_terms(
			array(
				'taxonomy'   => 'idemailwiz_folder',
				'child_of'   => $folder_id,
				'hide_empty' => false,
			)
		);
		if ( $child_folders instanceof WP_Error ) {
			wp_send_json_error( array( 'Get terms error' => $child_folders->get_error_message() ) );
			return;
		}
		foreach ( $child_folders as $child_folder ) {
			$updateTerm = wp_update_term(
				$child_folder->term_id,
				'idemailwiz_folder',
				array( 'parent' => $new_folder_id )
			);
			if ( $updateTerm instanceof WP_Error ) {
				wp_send_json_error( array( 'Move children error' => $updateTerm->get_error_message() ) );
				return;
			}
		}

		// Prune the deleted folder from everyone's favorites.
		$users = get_users();
		foreach ( $users as $user ) {
			$favorites = get_user_meta( $user->ID, 'idwiz_favorite_folders', true );
			if ( is_array( $favorites ) && in_array( $folder_id, $favorites ) ) {
				$key = array_search( $folder_id, $favorites );
				unset( $favorites[ $key ] );
				update_user_meta( $user->ID, 'idwiz_favorite_folders', $favorites );
			}
		}

		$deleteTerm = wp_delete_term( $folder_id, 'idemailwiz_folder' );
		if ( $deleteTerm instanceof WP_Error ) {
			wp_send_json_error( array( 'Delete error' => $deleteTerm->get_error_message() ) );
			return;
		}
	}

	wp_send_json_success(
		array(
			'newFolderLink' => get_term_link( $new_folder_id, 'idemailwiz_folder' ),
		)
	);
}
add_action( 'wp_ajax_id_delete_folder', 'id_delete_folder' );
