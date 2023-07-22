<?php
function duplicate_email_template( $post_id, $returnPHP=false ) {
    // Get the current post that we're duplicating
    $post = get_post( $post_id );
	
    // Duplicate the post
    $duplicate = array(
        'post_title'     => $post->post_title.' (copy)',
        'post_content'   => $post->post_content,
        'post_status'    => 'publish',
        'post_type'      => 'idemailwiz_template',
        'post_author'    => $post->post_author,
    );
    $dupedID = wp_insert_post( $duplicate );
	if (is_wp_error($dupedID)) {
		return $dupedID->get_error_message();
	}
	
	
	
    // Duplicate the post's custom fields
    $meta_keys = get_post_custom_keys( $post_id );
    if ( ! empty( $meta_keys ) ) {
        foreach ( $meta_keys as $key ) {
            //don't duplicate the iterable template ID value
            if ($key != 'itTemplateId') {
                $meta_values = get_post_custom_values( $key, $post_id );
                foreach ( $meta_values as $value ) {
                    $dupeMeta = add_post_meta( $dupedID, $key, maybe_unserialize( $value ) );
					if (is_wp_error($dupeMeta)) {
						return $dupeMeta->get_error_message();
					}
                }
            }
        }
    }

    // Duplicate the post's ACF fields
    if ( function_exists( 'acf_get_field_groups' ) ) {
        $field_groups = acf_get_field_groups( array( 'post_id' => $post_id ) );
        if ( ! empty( $field_groups ) ) {
            foreach ( $field_groups as $field_group ) {
                $fields = acf_get_fields( $field_group );
                if ( ! empty( $fields ) ) {
                    foreach ( $fields as $field ) {
                        $value = get_field( $field['name'], $post_id );
                        update_field( $field['key'], $value, $dupedID );
                    }
                }
            }
        }
    }
	
	
    
	//Set the copied templates folders (folders) to the duped ones
	//IMPORTANT: we're doing this later because it doesn't seem to work earlier in the code

	$folders = wp_get_object_terms( $post_id, 'idemailwiz_folder');
	if (is_wp_error($folders)) {
		return $folders->get_error_message();
	}

	// If no folders, set folder to 1 as fallback (root)
	if ( empty( $folders ) ) {
			$folderIDs = array();
		foreach ($folders as $folder) {
			$folderIDs[] = $folder->term_id;
		}
	}
	if (empty($folderIDs)) {
		$folderIDs = array((int) get_option( 'templatefoldersroot' ));
	}
	$setTemplateTerms = wp_set_object_terms( $dupedID, $folderIDs, 'idemailwiz_folder' );
	if (is_wp_error($setTemplateTerms)) {
		return $setTemplateTerms->get_error_message();
	}
	
	
    $return = array();
	$return['success'] = true;
	$return['newTemplate'] = $dupedID;
	$return['newURL'] = get_the_permalink($dupedID);
    
	if (!$returnPHP) {
		wp_send_json($return);
	} else {
		return $return;
	}
}

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
	
    $untrashTemplate =  wp_untrash_post( $post_id );
	if (is_wp_error($untrashTemplate)) {
		$return = $untrashTemplate->get_error_message();
	} else {
		$return = true;
	}
	
	$republishTemplate = wp_publish_post($post_id);
	if (is_wp_error($republishTemplate)) {
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
    check_ajax_referer( 'template-actions', 'security' );

	$action = $_POST['template_action'];
	$post_id = $_POST['post_id'];
	if (!$post_id || !$action) {
		wp_send_json(false);
	}
	$doAction = false;
	$trashTerm = get_option('templatefolderstrash');
	$actionResponse = '';
	switch ($action) {
        case 'delete':			
			$currentFolder = wp_get_post_terms($post_id,'idemailwiz_folder',array('fields'=>'ids'));
			if (is_wp_error($currentFolder)) {
				wp_send_json(array('success'=>false, 'actionResponse'=>$currentFolder->get_error_message()));
				die();
			}
			
			//Put this template in the trashed folder term
			if (!is_wp_error($trashTerm)) {
				$setDeleteTerms = wp_set_post_terms($post_id, array_merge(array( (int) $trashTerm ),$currentFolder), 'idemailwiz_folder', false );
				if (is_wp_error($setDeleteTerms)) {
					wp_send_json(array('success'=>false, 'actionResponse'=>$setDeleteTerms->get_error_message()));
					die();
				}
			} else {
				wp_send_json(array('success'=>false, 'actionResponse'=>$trashTerm->get_error_message()));
				die();
			}
			
			//Trash it
			$trashPost = wp_trash_post($post_id);
			if (is_wp_error($trashPost)) {
				wp_send_json(array('success'=>false, 'actionResponse'=>$trashPost->get_error_message()));
				die();
			}
			
			//delete iterable template id if present
			$deletedIterableMeta = delete_post_meta($post_id, 'itTemplateId');
			if (is_wp_error($deletedIterableMeta)) {
				wp_send_json(array('success'=>false, 'actionResponse'=>$deletedIterableMeta->get_error_message()));
				die();
			}

			// Get all users
			$users = get_users();

			// Loop through the users
			foreach ($users as $user) {
				$favorites = get_user_meta($user->ID, 'favorite_templates', true);

				// If the template is in the user's favorites, remove it
				if (is_array($favorites) && in_array($post_id, $favorites)) {
					$key = array_search($post_id, $favorites);
					unset($favorites[$key]);
					update_user_meta($user->ID, 'favorite_templates', $favorites);
				}
			}

			$doAction = true;
			$actionResponse = $post_id;
			break;

		case 'restore':
			//Removed trashed term
			if (!is_wp_error($trashTerm)) {
				$restoreTrashedTemplate = id_restore_template($post_id);
				if ($restoreTrashedTemplate === true) {
					wp_send_json(array('success'=>false, 'actionResponse'=>$restoreTrashedTemplate));
					die();
				}
			} else {
				wp_send_json(array('success'=>false, 'actionResponse'=>$trashTerm->get_error_message()));
				die();
			}
			wp_remove_object_terms($post_id, get_option('templatefolderstrash'), 'idemailwiz_folder');
			//wp_set_post_terms( $post_id, array( 1 ), 'idemailwiz_folder', false );
			$doAction = true;
			$actionResponse = $post_id;
            break;
        case 'create_from_template':
            $template_title = $_POST['template_title'];
			$actionResponse = duplicate_email_template($post_id, true);//we set the 2nd parameter to true to return a php-friendly result
			if (isset($actionResponse['success']) && $actionResponse['success'] == true) {
				$dID = $actionResponse['newTemplate'];
			} else {
				wp_send_json(array('success'=>false, 'actionResponse'=>$actionResponse));
				die();
			}
			
			// Update the post title and slug
			$slug = sanitize_title($template_title); // Generate a slug from the post title
			$unique_slug = wp_unique_post_slug($slug, $post_id, get_post_status($post_id), 'idemailwiz_template', 0); // Generate a unique slug
			
			$post_data = array(
				'ID' => $dID,
				'post_title' => $template_title,
				'post_name' => $unique_slug,
			);
			$updateTemplate = wp_update_post($post_data);
			if (is_wp_error($updateTemplate)) {
				wp_send_json(array('success'=>false, 'actionResponse'=>$updateTemplate->get_error_message()));
				die();
			}
			
			//get our new permalink
			$actionResponse['newURL'] = get_the_permalink($dID);
			
			//update the folder to the root
			// Get the current folder terms for the post
			$current_folders = wp_get_post_terms($dID, 'idemailwiz_folder');
			$folderRootTerm = (int) get_option('templatefoldersroot');
			
			// Set the terms to the new folder
			$setFolders = wp_set_post_terms($dID, array($folderRootTerm), 'idemailwiz_folder');
			if (is_wp_error($setFolders)) {
				wp_send_json(array('success'=>false, 'actionResponse'=>$setFolders->get_error_message()));
				die();
			}

			// Remove the post from all other categories
			foreach ($current_folders as $folder_id) {	
				if (is_wp_error($folderRootTerm)) {
						wp_send_json(array('success'=>false, 'actionResponse'=>$folderRootTerm->get_error_message()));
						die();
					}
				if ($folder_id != $folderRootTerm) {
					$removeFromFolders = wp_remove_object_terms($dID, $folder_id, 'idemailwiz_folder');
					if (is_wp_error($removeFromFolders)) {
						wp_send_json(array('success'=>false, 'actionResponse'=>$removeFromFolders->get_error_message()));
						die();
					}
				}
			}
			
			$doAction = true;
            break;
		case 'duplicate':
			$duplicateTemplate = duplicate_email_template($post_id);//returns new post id
			if (isset($duplicateTemplate['success']) && $duplicateTemplate['success'] == true) {
				$doAction = true;
			}
			break;
        default:
            // code to handle any other actions
    }
	
    // return a response
	//wp_send_json automatically calls die();
    wp_send_json(array('success'=>$doAction, 'actionResponse'=>$actionResponse));
}
add_action('wp_ajax_id_ajax_template_actions', 'id_ajax_template_actions');
add_action('wp_ajax_nopriv_id_ajax_template_actions', 'id_ajax_template_actions');

// Delete a folder and move its contents to where the user specified
function id_delete_folder() {
	//check nonce
	check_ajax_referer( 'folder-actions', 'security' );


    $folder_ids = $_POST['this_folder']; // Can be an array
    $new_folder_id = (int) $_POST['move_into'];

    // Loop through folders
    foreach ($folder_ids as $folder_id) {
        // Get the folder object for the folder to be deleted
        $folder = get_term($folder_id, 'idemailwiz_folder');

        // Check if the folder exists
        if (!$folder) {
            $error_message = 'folder does not exist.';
            wp_send_json_error(array('error' => $error_message));
            return;
        }
		
		

        // Get the templates that are in this folder
        $templates = get_posts(array(
			'tax_query' => array(
				array(
				  'taxonomy' => 'idemailwiz_folder',
				  'field' => 'term_id', 
				  'terms' => $folder_id, 
				  'include_children' => false
				)
			  ),
            'numberposts' => -1
        ));
        // Loop through the templates and move them to the new folder
        foreach ($templates as $template) {
			$moveTemplates = wp_set_post_terms($template->ID, array($new_folder_id), 'idemailwiz_folder', true);
			if ($moveTemplates instanceof WP_Error) {
				$error_message = $moveTemplates->get_error_message();
				wp_send_json_error(array('Move templates error' => $error_message));
				return;
			}
        }

        // Get child folders
        $child_folders = get_terms(array(
			'taxonomy' => 'idemailwiz_folder',
            'child_of' => $folder_id,
			'hide_empty' => false
        ));	
		if ($child_folders instanceof WP_Error) {
			$error_message = $child_folders->get_error_message();
			wp_send_json_error(array('Get terms error' => $error_message));
			return;
		}
        // Loop through child folders and move them to the new folder
		foreach ($child_folders as $child_folder) {
			// Use 'parent' field to set the parent of the term
			$args = array(
				'parent' => $new_folder_id
			);

			$updateTerm = wp_update_term($child_folder->term_id, 'idemailwiz_folder', $args);
			if ($updateTerm instanceof WP_Error) {
				$error_message = $updateTerm->get_error_message();
				wp_send_json_error(array('Move children error' => $error_message));
				return;
			} 
		}

        // Get all users
        $users = get_users();

        // Loop through the users
        foreach ($users as $user) {
            $favorites = get_user_meta($user->ID, 'favorite_folders', true);

            // If the folder is in the user's favorites, remove it
            if (is_array($favorites) && in_array($folder_id, $favorites)) {
                $key = array_search($folder_id, $favorites);
                unset($favorites[$key]);
                update_user_meta($user->ID, 'favorite_folders', $favorites);
            }
        }
		
		// Delete the folder
        $deleteTerm = wp_delete_term($folder_id, 'idemailwiz_folder');
        if ($deleteTerm instanceof WP_Error) {
            $error_message = $deleteTerm->get_error_message();
            wp_send_json_error(array('Delete error' => $error_message));
            return;
        }    
    }

    // Send success response with additional data
    wp_send_json_success(array(
        'newFolderLink' => get_term_link($new_folder_id, 'idemailwiz_folder'),
    ));
}

add_action('wp_ajax_id_delete_folder', 'id_delete_folder');
add_action('wp_ajax_nopriv_id_delete_folder', 'id_delete_folder');


// Add or remove a favorite template or folder from a user's profile
function add_remove_user_favorite() {
	//check nonce
    check_ajax_referer( 'template-actions', 'security' );
	
  // Ensure object_id and object_type are set
  $object_id = isset( $_POST['object_id'] ) ? intval( $_POST['object_id'] ) : 0;
  $object_type = isset( $_POST['object_type'] ) ? sanitize_text_field( $_POST['object_type'] ) : '';

  if ( $object_id <= 0 || empty($object_type) ) {
    wp_send_json( array(
      'success' => false,
      'message' => 'Invalid object id or object type was sent!',
      'action' => null,
      'objectid' => $object_id,
    ) );
  }

  // Determine the meta key based on the object_type
  $meta_key = 'favorite_' . strtolower($object_type) . 's'; // either 'favorite_templates' or 'favorite_folders'

  $favorites = get_user_meta( get_current_user_id(), $meta_key, true );

  if ( ! is_array( $favorites ) ) {
    $favorites = array();
  }

  $success = false;
  $message = '';
  $action = '';

  $key = array_search( $object_id, $favorites );
  if ( false !== $key ) {
    unset( $favorites[ $key ] );
    $message = 'Favorite ' . $object_type . ' removed.';
    $action = 'removed';
  } else {
    $favorites[] = intval( $object_id );  // Ensure object_id is an integer
    $message = 'Favorite ' . $object_type . ' added.';
    $action = 'added';
  }
  $success = true;

  if ( $success ) {
    $update_status = update_user_meta( get_current_user_id(), $meta_key, $favorites );
    if ( $update_status === false ) {
      $success = false;
      $message = 'Failed to update user meta.';
    } else {
      $updated_favorites = get_user_meta( get_current_user_id(), $meta_key, true );
      if ( ! is_array( $updated_favorites ) ) {
        $success = false;
        $message = 'User meta was updated but the structure is incorrect.';
      } else {
		// Check if the object_id was correctly added or removed
		if ( $action === 'added' && ! in_array( $object_id, $updated_favorites ) ) {
			$success = false;
			$message = 'Object id was not added correctly to ' . $object_type . '.';
		} elseif ( $action === 'removed' && in_array( $object_id, $updated_favorites ) ) {
			$success = false;
			$message = 'Object id was not removed correctly from ' . $object_type . '.';
		}
      }
    }
  }

  wp_send_json( array(
    'success' => $success,
    'message' => $message,
    'action' => $action,
    'objectid' => $object_id,
  ) );
}

add_action('wp_ajax_add_remove_user_favorite', 'add_remove_user_favorite');
add_action('wp_ajax_nopriv_add_remove_user_favorite', 'add_remove_user_favorite');


//Add a new folder to the folder tree
function id_add_new_folder() {
	//check nonce
    check_ajax_referer( 'folder-actions', 'security' );
	
	$folder_name = sanitize_text_field( $_POST['folder_name'] );
	$parent_folder = $_POST['parent_folder'];
	
    $response = wp_insert_term( $folder_name, 'idemailwiz_folder', array('parent'=>$parent_folder)); 
	
    wp_send_json_success( $response );
    wp_die();
}
add_action('wp_ajax_id_add_new_folder', 'id_add_new_folder');
add_action('wp_ajax_nopriv_id_add_new_folder', 'id_add_new_folder');

//Move a template to another folder
function id_move_template() {
	//check nonce
    check_ajax_referer( 'template-actions', 'security' );
	
	// Set the post ID and the new folder ID
	$thisTemplate = $_POST['this_template'];
	
	foreach ($thisTemplate as $template) {
		$moveInto = (int) $_POST['move_into'];

		// Assign the post a new folder and remove previous ones
		$setfolders = wp_set_post_terms($template, array($moveInto), 'idemailwiz_folder');
	}

	// Get the new folder's link
	$newfolderLink = get_term_link($moveInto, 'idemailwiz_folder');

	$return = array(
		'moveTemplate' => $setfolders,
		'newFolderLink' => $newfolderLink,
	);
	
	
	wp_send_json_success( $return ); // respond is an array including term id and term taxonomy id
	wp_die();
}

add_action('wp_ajax_id_move_template', 'id_move_template');
add_action('wp_ajax_nopriv_id_move_template', 'id_move_template');


//Move a folder to another folder
function id_move_folder() {
	//check nonce
    check_ajax_referer( 'folder-actions', 'security' );
	
    $thisFolder = $_POST['this_folder'];
    $moveInto = (int) $_POST['move_into'];
	
    foreach ($thisFolder as $folder) {
        $moveFolder = wp_update_term($folder, 'idemailwiz_folder', array(
            'parent' => $moveInto,
        ));
        // Check for any errors
        if (is_wp_error($moveFolder)) {
            $return = array(
                'error' => $moveFolder->get_error_message(), // Get the error message
            );
            wp_send_json_error($return); // Send the error message in the response
            wp_die();
        }
    }

    // Get the new parent folder's link
    $newParentLink = get_term_link($moveInto, 'idemailwiz_folder');

    // Use the new parent's link instead of the moved folder's link
    $return = array(
        'moveCat' => $moveFolder,
        'newFolderLink' => $newParentLink,
    );
    wp_send_json_success($return); // respond is an array including term id and term taxonomy id
    wp_die();
}


add_action('wp_ajax_id_move_folder', 'id_move_folder');
add_action('wp_ajax_nopriv_id_move_folder', 'id_move_folder');

