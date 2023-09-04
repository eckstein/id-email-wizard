<?php
// Generates the list of folders on the template table sidebar
function get_folder_list($current_folder_id=null) {
    

    $favorite_folders = get_user_meta(get_current_user_id(), 'favorite_folders', true);
    $favorite_folder_cat_ids = !empty($favorite_folders) ? $favorite_folders : array();
	
	$favorite_templates = get_user_meta(get_current_user_id(), 'favorite_templates', true);
    $favorite_template_cat_ids = !empty($favorite_templates) ? $favorite_templates : array();

    $faves_list = '';
        
	if (!empty($favorite_folders)) {
		$faves_list .= '<h5>Favorite Folders</h5>';
		$faves_list .= '<ul>';
		foreach ($favorite_folder_cat_ids as $fav_folder_cat_id) {
			$term = get_term($fav_folder_cat_id, 'idemailwiz_folder');
			if ($term && !is_wp_error($term)) {
				$faves_list .= '<li class="favItem"><a href="' . get_term_link($term) . '"><i class="fa-solid fa-folder"></i>&nbsp;&nbsp;' . $term->name . '</a>&nbsp;&nbsp;<i title="Remove Favorite" class="fa-solid fa-circle-minus addRemoveFavorite" data-objecttype="Folder" data-objectid="'.$fav_folder_cat_id.'"></i></li>';
			}
		}
		$faves_list .= '</ul>';
	}
	if (!empty($favorite_templates)) {
			$faves_list .= '<h5>Favorite Templates</h5>';
			$faves_list .= '<ul>';
			foreach ($favorite_template_cat_ids as $fav_template_id) {
				$template = get_post($fav_template_id);
				if ($template && !is_wp_error($template)) {
					$faves_list .= '<li class="favItem"><a href="' . get_the_permalink($fav_template_id) . '"><i class="fa-solid fa-file"></i>&nbsp;&nbsp;' . get_the_title($fav_template_id) . '</a>&nbsp;&nbsp;<i title="Remove Favorite" class="fa-solid fa-circle-minus addRemoveFavorite" data-objecttype="Template" data-objectid="'.$fav_template_id.'"></i></li>';
				}
			}
		$faves_list .= '</ul>';
	}
	
	$args = array(
        'orderby' => 'name',
        'hide_empty' => false,
        'hierarchical' => false,
        'depth' => 0,
        'child_of' => 0,
        'taxonomy' => 'idemailwiz_folder',
    );
	
	//skips the trash folder if it exists
	$options = get_option('idemailwiz_settings');
	  $trashTerm = (int) $options['folder_trash'];
	if (!is_wp_error($trashTerm)) {
		$args['exclude'] = array((int) $trashTerm);
	}
	

    $folders = get_terms($args);
	//print_r($folders);
    $terms_list = '';
    if (!empty($folders)) {
        $terms_list .= '<h5>All Folders<div id="addNewFolder"><i title="Add new folder" class="fa-solid fa-circle-plus"></i></div></h5>';
		
		$terms_list .= '<ul>';
        foreach ($folders as $folder) {
            if ($folder->parent == 0) {
                $terms_list .= get_folder_tree($folder, $current_folder_id);
            }
        }

		
		$terms_list .= '</ul>';
        
		
		$folder_list = $terms_list . $faves_list;
		
		//Add trashed
		
		$options = get_option('idemailwiz_settings');
		$trashedFolderId = (int) $options['folder_trash'];
		$trashLink = get_term_link($trashedFolderId, 'idemailwiz_folder');
		if (!is_wp_error($trashLink)) {
			$folder_list .= '<ul style="margin-top: auto;"><li><a href="' . $trashLink . '"><i class="fa fa-trash"></i>&nbsp;&nbsp;Trash</a></li></ul>';
		} else {
			$folder_list .= '<ul style="margin-top: auto;"><li><em>Error! Trash term not detected.</em></li></ul>';
		}
		$templateRoot = (int) $options['folder_base'];
		if (is_wp_error($templateRoot)) {
			$folder_list .= '<ul style="margin-top: auto;"><li><em>No template root detected!</em></li></ul>';
		}
		
		
        
    }

    echo $folder_list;
}

// Helper function to check if a folder is an ancestor of the current folder
function is_parent($parent, $child) {
	if (!$parent || !$child) {
		return false;
	}
    // Check if the parent is the direct parent of the child
	if (isset($child->parent)) {
		if ($child->parent == $parent->term_id) {
			return true;
		}
	} else {
		return false;
	}

    // If the child has a parent, and it's not the parent we're checking for,
    // Get the parent of the child and check again
    if ($child->parent != 0) {
        return is_parent($parent, get_term($child->parent, 'idemailwiz_folder'));
    }

    // If no ancestors were the parent we're checking for, return false
    return false;
}


//Generate the folder tree
function get_folder_tree($folder, $current_folder_id = null) {
    $link = get_term_link($folder, 'idemailwiz_folder');
    $name = $folder->name;
    $current_class = ($current_folder_id == $folder->term_id) ? ' current-cat' : '';
    $has_sub_cats_class = '';
    $sub_cats_collapse_toggle = '';

    $sub_categories = get_terms(array('taxonomy' => 'idemailwiz_folder', 'parent' => $folder->term_id, 'hide_empty' => false));
    if ($sub_categories) {
        $has_sub_cats_class = ' has-sub-cats';
        $sub_cats_collapse_toggle = '<i class="fa-solid fa-angle-up showHideSubs"></i>';
    }

    $html = '<li class="cat-item cat-item-' . $folder->term_id . $current_class . $has_sub_cats_class . '"><a href="' . $link . '">';
    $html .= ($current_folder_id == $folder->term_id || is_parent($folder, get_term($current_folder_id, 'idemailwiz_folder')) || $folder->term_id == 1) ? '<i class="fa-regular fa-folder-open"></i>&nbsp;&nbsp;' : '<i class="fa-solid fa-folder"></i>&nbsp;&nbsp;';
    $html .= $name . '</a>' . $sub_cats_collapse_toggle;

    if ($sub_categories) {
        $html .= '<ul class="sub-categories">';
        foreach ($sub_categories as $sub_category) {
            $html .= get_folder_tree($sub_category, $current_folder_id);
        }
        $html .= '</ul>';
    }

    $html .= '</li>';

    return $html;
}
