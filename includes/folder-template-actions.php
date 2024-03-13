<?php


/**
 * Restore a post by ID from the trash.
 *
 * @param int $post_id The ID of the post to restore from the trash.
 * @return bool Whether the post was successfully restored.
 */
function id_restore_template( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post ) {
		$return = 'Template not found!';
	}

	if ( 'trash' !== $post->post_status ) {
		$return = 'This template is not trashed!';
	}

	$untrashTemplate = wp_untrash_post( $post_id );
	if ( is_wp_error( $untrashTemplate ) ) {
		$return = $untrashTemplate->get_error_message();
	} else {
		$return = true;
	}

	$republishTemplate = wp_publish_post( $post_id );
	if ( is_wp_error( $republishTemplate ) ) {
		$return = $republishTemplate->get_error_message();
	} else {
		$return = true;
	}

	return $return;

}

//ajax requests for template actions
//Delete, restore, duplicate 
function id_ajax_template_actions() {

	//check nonce
	if ( ! check_ajax_referer( 'template-actions', 'security', false ) ) {
		check_ajax_referer( 'id-general', 'security' );
	}




	//get global options
	$options = get_option( 'idemailwiz_settings' );
	$trashTerm = $options['folder_trash'];
	if ( ! isset( $options['folder_trash'] ) ) {
		wp_send_json( array( 'success' => false, 'actionResponse' => 'Trash folder term was not found!' ) );
		die();
	}

	$action = $_POST['template_action'];
	$post_id = $_POST['post_id'];
	if ( ! $post_id || ! $action ) {
		wp_send_json( false );
	}
	$doAction = false;
	$actionResponse = '';
	switch ( $action ) {
		case 'delete':
			$currentFolder = wp_get_post_terms( $post_id, 'idemailwiz_folder', array( 'fields' => 'ids' ) );
			if ( is_wp_error( $currentFolder ) ) {
				wp_send_json( array( 'success' => false, 'actionResponse' => $currentFolder->get_error_message() ) );
				die();
			}

			//Put this template in the trashed folder term
			if ( ! is_wp_error( $trashTerm ) ) {
				$setDeleteTerms = wp_set_post_terms( $post_id, array_merge( array( (int) $trashTerm ), $currentFolder ), 'idemailwiz_folder', false );
				if ( is_wp_error( $setDeleteTerms ) ) {
					wp_send_json( array( 'success' => false, 'actionResponse' => $setDeleteTerms->get_error_message() ) );
					die();
				}
			} else {
				wp_send_json( array( 'success' => false, 'actionResponse' => $trashTerm->get_error_message() ) );
				die();
			}

			//Trash it
			$trashPost = wp_trash_post( $post_id );
			if ( is_wp_error( $trashPost ) ) {
				wp_send_json( array( 'success' => false, 'actionResponse' => $trashPost->get_error_message() ) );
				die();
			}

			// Get all users
			$users = get_users();

			// Loop through the users
			foreach ( $users as $user ) {
				$favorites = get_user_meta( $user->ID, 'idwiz_favorite_templates', true );

				// If the template is in the user's favorites, remove it
				if ( is_array( $favorites ) && in_array( $post_id, $favorites ) ) {
					$key = array_search( $post_id, $favorites );
					unset( $favorites[ $key ] );
					update_user_meta( $user->ID, 'idwiz_favorite_templates', $favorites );
				}
			}

			$doAction = true;
			$actionResponse = $post_id;
			break;

		case 'restore':
			//Removed trashed term
			if ( ! is_wp_error( $trashTerm ) ) {
				$restoreTrashedTemplate = id_restore_template( $post_id );
				if ( $restoreTrashedTemplate === true ) {
					wp_send_json( array( 'success' => false, 'actionResponse' => $restoreTrashedTemplate ) );
					die();
				}
			} else {
				wp_send_json( array( 'success' => false, 'actionResponse' => $trashTerm->get_error_message() ) );
				die();
			}
			wp_remove_object_terms( $post_id, $trashTerm, 'idemailwiz_folder' );
			//wp_set_post_terms( $post_id, array( 1 ), 'idemailwiz_folder', false );
			$doAction = true;
			$actionResponse = $post_id;
			break;

		case 'duplicate':
			$duplicateTemplate = duplicate_wiz_template( $post_id );//returns new post id
			if ( isset( $duplicateTemplate['success'] ) && $duplicateTemplate['success'] == true ) {
				$doAction = true;
			}
			break;
		default:
		// code to handle any other actions
	}

	// return a response
	//wp_send_json automatically calls die();
	wp_send_json( array( 'success' => $doAction, 'actionResponse' => $actionResponse ) );
}
add_action( 'wp_ajax_id_ajax_template_actions', 'id_ajax_template_actions' );
add_action( 'wp_ajax_nopriv_id_ajax_template_actions', 'id_ajax_template_actions' );

// Create a new template
add_action( 'wp_ajax_create_new_wiz_template', 'create_new_wiz_template' );

function create_new_wiz_template() {
	global $wpdb; // Access to the WordPress database object

	// Verify nonce here (if not done elsewhere in your code)
	if ( ! wp_verify_nonce( $_POST['security'], 'template-actions' ) ) {
		wp_send_json_error( 'Nonce verification failed' );
		die();
	}

	// Sanitize and validate the template title
	if ( empty( $_POST['template_title'] ) ) {
		wp_send_json_error( 'Template title is required' );
		die();
	}
	$template_title = sanitize_text_field( $_POST['template_title'] );

	// Create new post of type 'idemailwiz_template'
	$post_data = array(
		'post_title' => $template_title,
		'post_status' => 'publish', // or 'draft' if you don't want it published immediately
		'post_type' => 'idemailwiz_template',
		'post_author' => get_current_user_id(), // Set the author to the current user
	);

	$post_id = wp_insert_post( $post_data );

	// Check for errors
	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( $post_id->get_error_message() );
		die();
	}

	// Set the post terms (folders)
	$options = get_option( 'idemailwiz_settings' );
	$folderRootTerm = $options['folder_base'] ?? '';
	if ( ! empty( $folderRootTerm ) ) {
		wp_set_post_terms( $post_id, array( $folderRootTerm ), 'idemailwiz_folder' );
	}

	$createDBrow = create_wiz_template_db_row( $post_id );


	// If everything was successful, send back the new post's URL
	if ( $post_id && $createDBrow ) {
		$new_url = get_permalink( $post_id );
		wp_send_json_success( array( 'newURL' => $new_url ) );
	} else {
		wp_send_json_error( array( 'message' => 'Failed to create new template' ) );
	}
	die();
}

function create_wiz_template_db_row( $post_id ) {
	global $wpdb;
	// Insert into custom database table
	$success = $wpdb->insert(
		$wpdb->prefix . 'wiz_templates',
		array(
			'last_updated' => current_time( 'mysql' ), // Use WordPress current time function
			'post_id' => $post_id,
			'user_id' => get_current_user_id(),
			'template_data' => json_encode( [ 'templateOptions' => [] ] )
		),
		array(
			'%s',
			'%d',
			'%d',
			'%s'
		)
	);

	// Check if insert was successful
	if ( ! $success ) {
		return false;
	}

	return true;
}


function duplicate_wiz_template($post_id, $returnPHP = false) {
	global $wpdb; // Make sure you have access to the WordPress database object
	
	// Get the current post that we're duplicating
	$post = get_post($post_id);
	$wizTemplateObject = get_wiztemplate_object($post_id); // Assuming this function returns the custom data for the template
	
	// Duplicate the post
	$duplicate = array(
		'post_title' => '(copy) ' . $post->post_title,
		'post_content' => $post->post_content,
		'post_status' => 'publish',
		'post_type' => 'idemailwiz_template',
		'post_author' => $post->post_author,
	);
	$dupedID = wp_insert_post($duplicate);
	
	if (is_wp_error($dupedID)) {
		return $dupedID->get_error_message();
	}
	
	// Duplicate the folders
	$folders = wp_get_object_terms($post_id, 'idemailwiz_folder');
	if (is_wp_error($folders)) {
		return $folders->get_error_message();
	}
		
	$folderIDs = empty($folders) ? [] : wp_list_pluck($folders, 'term_id');
	if (empty($folderIDs)) {
		$options = get_option('idemailwiz_settings');
		$folderIDs = array((int) $options['folder_base']);
	}
	$setTemplateTerms = wp_set_object_terms($dupedID, $folderIDs, 'idemailwiz_folder');
	if (is_wp_error($setTemplateTerms)) {
		return $setTemplateTerms->get_error_message();
	}
	
	// Duplicate the custom database row
	$wizTemplateRow = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}wiz_templates WHERE post_id = %d",
		$post_id
	), ARRAY_A);
	
	if ($wizTemplateRow) {
		// Clear out the template_data_draft template_html_draft columns
		$wizTemplateRow['template_data_draft'] = json_encode([]);
		$wizTemplateRow['template_html_draft'] = '';

		// Unpack the JSON in the template_data column
		$templateData = json_decode( $wizTemplateRow['template_data'], true );

		// Remove the iterable_template_id value
		unset( $templateData['template_options']['template_settings']['iterable-sync']['iterable_template_id'] );

		// Re-encode the JSON and save back to the column
		$wizTemplateRow['template_data'] = json_encode( $templateData );

		// Update the post_id to the ID of the new duplicated post
		$wizTemplateRow['post_id'] = $dupedID;
		// Remove the ID from the row to ensure a new row is inserted
		unset($wizTemplateRow['id']);
		// Update the last_updated to the current time
		$wizTemplateRow['last_updated'] = current_time('mysql');
	
		$inserted = $wpdb->insert("{$wpdb->prefix}wiz_templates", $wizTemplateRow);
	
		if (!$inserted) {
			return "Failed to duplicate the template data in the custom database.";
		}
	} else {
		return "Original template data not found in the custom database.";
	}
	
	$return = array(
		'success' => true,
		'newTemplate' => $dupedID,
		'newURL' => get_the_permalink($dupedID),
	);
	wp_send_json_success($return);
}	


// Delete a folder and move its contents to where the user specified
function id_delete_folder() {
	//check nonce
	check_ajax_referer( 'folder-actions', 'security' );


	$folder_ids = $_POST['this_folder']; // Can be an array
	$new_folder_id = (int) $_POST['move_into'];

	// Loop through folders
	foreach ( $folder_ids as $folder_id ) {
		// Get the folder object for the folder to be deleted
		$folder = get_term( $folder_id, 'idemailwiz_folder' );

		// Check if the folder exists
		if ( ! $folder ) {
			$error_message = 'folder does not exist.';
			wp_send_json_error( array( 'error' => $error_message ) );
			return;
		}



		// Get the templates that are in this folder
		$templates = get_posts( array(
			'tax_query' => array(
				array(
					'taxonomy' => 'idemailwiz_folder',
					'field' => 'term_id',
					'terms' => $folder_id,
					'include_children' => false
				)
			),
			'numberposts' => -1
		) );
		// Loop through the templates and move them to the new folder
		foreach ( $templates as $template ) {
			$moveTemplates = wp_set_post_terms( $template->ID, array( $new_folder_id ), 'idemailwiz_folder', true );
			if ( $moveTemplates instanceof WP_Error ) {
				$error_message = $moveTemplates->get_error_message();
				wp_send_json_error( array( 'Move templates error' => $error_message ) );
				return;
			}
		}

		// Get child folders
		$child_folders = get_terms( array(
			'taxonomy' => 'idemailwiz_folder',
			'child_of' => $folder_id,
			'hide_empty' => false
		) );
		if ( $child_folders instanceof WP_Error ) {
			$error_message = $child_folders->get_error_message();
			wp_send_json_error( array( 'Get terms error' => $error_message ) );
			return;
		}
		// Loop through child folders and move them to the new folder
		foreach ( $child_folders as $child_folder ) {
			// Use 'parent' field to set the parent of the term
			$args = array(
				'parent' => $new_folder_id
			);

			$updateTerm = wp_update_term( $child_folder->term_id, 'idemailwiz_folder', $args );
			if ( $updateTerm instanceof WP_Error ) {
				$error_message = $updateTerm->get_error_message();
				wp_send_json_error( array( 'Move children error' => $error_message ) );
				return;
			}
		}

		// Get all users
		$users = get_users();

		// Loop through the users
		foreach ( $users as $user ) {
			$favorites = get_user_meta( $user->ID, 'idwiz_favorite_folders', true );

			// If the folder is in the user's favorites, remove it
			if ( is_array( $favorites ) && in_array( $folder_id, $favorites ) ) {
				$key = array_search( $folder_id, $favorites );
				unset( $favorites[ $key ] );
				update_user_meta( $user->ID, 'idwiz_favorite_folders', $favorites );
			}
		}

		// Delete the folder
		$deleteTerm = wp_delete_term( $folder_id, 'idemailwiz_folder' );
		if ( $deleteTerm instanceof WP_Error ) {
			$error_message = $deleteTerm->get_error_message();
			wp_send_json_error( array( 'Delete error' => $error_message ) );
			return;
		}
	}

	// Send success response with additional data
	wp_send_json_success( array(
		'newFolderLink' => get_term_link( $new_folder_id, 'idemailwiz_folder' ),
	) );
}

add_action( 'wp_ajax_id_delete_folder', 'id_delete_folder' );
add_action( 'wp_ajax_nopriv_id_delete_folder', 'id_delete_folder' );





//Add a new folder to the folder tree
function id_add_new_folder() {
	//check nonce
	check_ajax_referer( 'folder-actions', 'security' );

	$folder_name = sanitize_text_field( $_POST['folder_name'] );
	$parent_folder = $_POST['parent_folder'];

	$response = wp_insert_term( $folder_name, 'idemailwiz_folder', array( 'parent' => $parent_folder ) );

	wp_send_json_success( $response );
	wp_die();
}
add_action( 'wp_ajax_id_add_new_folder', 'id_add_new_folder' );
add_action( 'wp_ajax_nopriv_id_add_new_folder', 'id_add_new_folder' );

//Move a template to another folder
function id_move_template() {
	//check nonce
	check_ajax_referer( 'template-actions', 'security' );

	// Set the post ID and the new folder ID
	$thisTemplate = $_POST['this_template'];

	foreach ( $thisTemplate as $template ) {
		$moveInto = (int) $_POST['move_into'];

		// Assign the post a new folder and remove previous ones
		$setfolders = wp_set_post_terms( $template, array( $moveInto ), 'idemailwiz_folder' );
	}

	// Get the new folder's link
	$newfolderLink = get_term_link( $moveInto, 'idemailwiz_folder' );

	$return = array(
		'moveTemplate' => $setfolders,
		'newFolderLink' => $newfolderLink,
	);


	wp_send_json_success( $return ); // respond is an array including term id and term taxonomy id
	wp_die();
}

add_action( 'wp_ajax_id_move_template', 'id_move_template' );
add_action( 'wp_ajax_nopriv_id_move_template', 'id_move_template' );


//Move a folder to another folder
function id_move_folder() {
	//check nonce
	check_ajax_referer( 'folder-actions', 'security' );

	$thisFolder = $_POST['this_folder'];
	$moveInto = (int) $_POST['move_into'];

	foreach ( $thisFolder as $folder ) {
		$moveFolder = wp_update_term( $folder, 'idemailwiz_folder', array(
			'parent' => $moveInto,
		) );
		// Check for any errors
		if ( is_wp_error( $moveFolder ) ) {
			$return = array(
				'error' => $moveFolder->get_error_message(), // Get the error message
			);
			wp_send_json_error( $return ); // Send the error message in the response
			wp_die();
		}
	}

	// Get the new parent folder's link
	$newParentLink = get_term_link( $moveInto, 'idemailwiz_folder' );

	// Use the new parent's link instead of the moved folder's link
	$return = array(
		'moveCat' => $moveFolder,
		'newFolderLink' => $newParentLink,
	);
	wp_send_json_success( $return ); // respond is an array including term id and term taxonomy id
	wp_die();
}


add_action( 'wp_ajax_id_move_folder', 'id_move_folder' );
add_action( 'wp_ajax_nopriv_id_move_folder', 'id_move_folder' );

