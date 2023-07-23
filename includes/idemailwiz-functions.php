<?php
//Insert overlay and spinner into header
function insert_overlay_loader() {
  if (is_single() && get_post_type() == 'idemailwiz_template') {
  ?>
  <div id="iDoverlay"></div>
  <div id="iDspinner" class="loader"></div>
  <?php
  }
  ?>
  <script type="text/javascript">
    // Function to show and hide overlays and spinners
  const toggleOverlay = (show = true) => {
    jQuery("#iDoverlay")[show ? "show" : "hide"]();
    jQuery("#iDspinner")[show ? "show" : "hide"]();
    };
    // Call toggleOverlay() as soon as the script is executed
      toggleOverlay();
  </script>
  <?php
}
add_action('wp_head', 'insert_overlay_loader');

function iD_generate_chunk($chunk) {
  // Convert the layout name from underscore to hyphen format
  $template_name = str_replace('_', '-', $chunk['acf_fc_layout']);

  // Construct the path to the template file
  $template_path = plugin_dir_path( dirname( __FILE__ ) ) . 'templates/chunks/' . $template_name . '.php';

  // Check if the template file exists
  if (!file_exists($template_path)) {
      return '';
  }

  // Include the template part and capture the output
  ob_start();
  include $template_path;
  $return = ob_get_clean();

  return $return;
}




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
	$trashTerm = get_option('templatefolderstrash');
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
		
		$trashedFolderId = (int) get_option('templatefolderstrash');
		$trashLink = get_term_link($trashedFolderId, 'idemailwiz_folder');
		if (!is_wp_error($trashLink)) {
			$folder_list .= '<ul style="margin-top: auto;"><li><a href="' . $trashLink . '"><i class="fa fa-trash"></i>&nbsp;&nbsp;Trash</a></li></ul>';
		} else {
			$folder_list .= '<ul style="margin-top: auto;"><li><em>Error! Trash term not detected.</em></li></ul>';
		}
		$templateRoot = (int) get_option('templatefoldersroot');
		if (!is_wp_error($templateRoot)) {
			$folder_list .= '<ul style="margin-top: auto;"><li>Template Root: ' . $templateRoot . '</li></ul>';
		} else {
			$folder_list .= '<ul style="margin-top: auto;"><li><em>Template Root: <em>None detected!</em></em></li></ul>';
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

// Determine if a template or folder is in the current user's favorites
function is_user_favorite( $object_id, $object_type ) {
  // Determine the meta key based on the object_type
  $meta_key = 'favorite_' . strtolower($object_type) . 's'; // either 'favorite_templates' or 'favorite_folders'

  $favorites = get_user_meta( get_current_user_id(), $meta_key, true );

  if ( ! is_array( $favorites ) ) {
    $favorites = array();
  }

  // Cast IDs in favorites to integers for consistent comparison
  $favorites = array_map( 'intval', $favorites );
  
  $object_id = intval( $object_id );  // Ensure object_id is an integer

  // Check if $object_id is in favorites
  if ( in_array( $object_id, $favorites ) ) {
    return true;
  }
  
  return false;
}

// Alter the main query on the category archive page
function id_pre_get_posts( $query ) {
    // Check if the current page is a post type archive
    if ( $query->is_post_type_archive('idemailwiz_template') && $query->is_main_query() ) {
        // Add pagination
        // Set the number of posts to display per page
        $posts_per_page = get_option( 'posts_per_page' );

        // Get the current page number
        $paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

        // Set the number of posts to display per page and the current page for the query
        $query->set( 'posts_per_page', $posts_per_page );
        $query->set( 'paged', $paged );

        // Modify the orderby and order parameters to sort by the modified date
        $query->set( 'orderby', 'modified' );
        $query->set( 'order', 'DESC' );
    }
    // Check if the current page is a taxonomy term archive
    elseif ( $query->is_tax('idemailwiz_folder') && $query->is_main_query() ) {
        // Add pagination
        // Set the number of posts to display per page
        $posts_per_page = get_option( 'posts_per_page' );

        // Get the current page number
        $paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

        // Set the number of posts to display per page and the current page for the query
        $query->set( 'posts_per_page', $posts_per_page );
        $query->set( 'paged', $paged );

        // Get the current term
		$term = $query->get_queried_object();

		// Exclude posts in child terms
		$children = get_terms( array( 
			'taxonomy' => 'idemailwiz_folder', 
			'parent'   => $term->term_id 
		) );
		$children_ids = array();
		foreach ( $children as $child ) {
			$children_ids[] = $child->term_id;
		}
		$query->set( 'tax_query', array(
			array(
				'taxonomy' => 'idemailwiz_folder',
				'terms'    => $children_ids,
				'field'    => 'term_id',
				'operator' => 'NOT IN',
			),
		));
		
		//include trashed posts, but only on the trash term page
		$queried_object = get_queried_object();
		$trashTerm = get_option('templatefolderstrash');
		if (!is_wp_error($trashTerm)) {
			if ($queried_object->term_id === (int) $trashTerm) {
				$query->set('post_status', array('trash'));
			}
		}

        // Get the current user ID
        $current_user_id = get_current_user_id();

        // Get an array of post IDs that the current user has marked as favorite templates
        $favorite_templates = get_user_meta( $current_user_id, 'favorite_templates', true );
        $favorite_templates = is_array($favorite_templates) ? $favorite_templates : [];

        // Filter the favorite templates array to contain only those post IDs 
        // which are associated exactly with the current term.
        $favorite_templates = array_filter($favorite_templates, function($post_id) use ($term) {
            $post_terms = wp_get_post_terms($post_id, 'idemailwiz_folder', array('fields' => 'ids')); // Get IDs of terms of the post
            return in_array($term->term_id, $post_terms); // Check if current term id is in the post's terms
        });
		
		//Get the posts for the term/folder we're in
        $term_posts = get_posts( array(
			'post_type' => 'idemailwiz_template',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'idemailwiz_folder',
                    'terms'    => $term->term_id,
                ),
            ),
            'post__not_in' => $favorite_templates,
            'post_status' => array('publish','trash'),
        ) );
        // Sort the term posts by modified date
        usort( $term_posts, function( $a, $b ) {
            return strtotime( $b->post_modified ) - strtotime( $a->post_modified );
        } );

        // Merge the filtered favorite templates array with the reversed term posts array
        $all_posts = array_merge( wp_list_pluck( $term_posts, 'ID' ), array_values($favorite_templates) );

        // Modify the orderby and order parameters to sort by the modified post__in parameter
        $query->set( 'post__in', $all_posts );
        $query->set( 'orderby', 'post__in' );
    }
}
add_action( 'pre_get_posts', 'id_pre_get_posts' );



//Category page breadcrumb
function display_folder_hierarchy() {
  $queried_object = get_queried_object();

  if ($queried_object instanceof WP_Term) {
    // Handle term archives
    $term_links = array();

    while ($queried_object) {
      if (!is_wp_error($queried_object)) {
        if ($queried_object->term_id == get_queried_object_id()) {
          $term_links[] = '<span>' . $queried_object->name . '</span>';
        } else {
          $term_links[] = '<a href="' . get_term_link($queried_object->term_id) . '">' . $queried_object->name . '</a>';
        }
        $queried_object = get_term($queried_object->parent, 'idemailwiz_folder'); // Replace 'idemailwiz_folder' with your taxonomy slug
      } else {
        break;
      }
    }

    $term_links = array_reverse($term_links);
    echo implode(' > ', $term_links);
  } elseif ($queried_object instanceof WP_Post_Type) {
    // Handle post type archives
    echo '<span>' . $queried_object->labels->name . '</span>';
  }
}

//Single Template breadcrumb
function display_template_folder_hierarchy($post_id) {
  $terms = get_the_terms($post_id, 'idemailwiz_folder'); // Replace 'idemailwiz_folder' with your taxonomy slug
  if (is_wp_error($terms) || empty($terms)) {
    return;
  }
  $assigned_term = $terms[0];
  $term_links = array();

  while ($assigned_term) {
    if (!is_wp_error($assigned_term)) {
      $term_links[] = '<a href="' . get_term_link($assigned_term->term_id) . '">' . $assigned_term->name . '</a>';
      $assigned_term = get_term($assigned_term->parent, 'idemailwiz_folder'); // Replace 'idemailwiz_folder' with your taxonomy slug
    } else {
      break;
    }
  }

  $term_links = array_reverse($term_links);
  echo implode(' > ', $term_links);
}

// Generate a drop-down list of folders
function id_generate_folders_select($parent_id = 0, $prefix = '') {
    $options = '';

    $folders = get_terms(array('taxonomy' => 'idemailwiz_folder', 'parent' => $parent_id, 'hide_empty' => false));
    
    foreach ($folders as $folder) {
		//skips the trash folder if it exists
    if (!is_wp_error(get_option('templatefolderstrash'))) {
        if ($folder->term_id == get_option('templatefolderstrash')) {
          continue;
        }
    }
        $name = $folder->name;
        $options .= '<option value="' . $folder->term_id . '">' . $prefix . $name . '</option>';
        $options .= id_generate_folders_select($folder->term_id, '&nbsp;&nbsp;'.$prefix . '-&nbsp;&nbsp;');
    }

    return $options;
}

function id_generate_folders_select_ajax() {
    //check nonce (could be one of two files)
    $nonceCheck = check_ajax_referer( 'folder-actions', 'security' , false);
    if (!$nonceCheck) {
      check_ajax_referer( 'template-actions', 'security' );
    }

    $options = id_generate_folders_select();
    wp_send_json_success(array('options' => $options));
    wp_die();
}

add_action('wp_ajax_id_generate_folders_select_ajax', 'id_generate_folders_select_ajax');
add_action('wp_ajax_nopriv_id_generate_folders_select_ajax', 'id_generate_folders_select_ajax');


//Add chunk elements to the acf chunk title area for easy IDing of content
function id_filter_acf_chunk_title($title, $field, $layout, $i) {
    // Only modify title for specific layout
    if ($layout['name'] === 'full_width_image' || $layout['name'] === 'contained_image') {
        $image_url = get_sub_field('desktop_image_url', $layout['key']);
		if ($image_url) {
			if (!empty($image_url)) {
				$title = $title.'&nbsp;&nbsp;<img style="max-width: 60px;" src="'.$image_url.'"/>';
			}
		}
    } else if ($layout['name'] === 'two-column' || $layout['name'] === 'two-column-contained') {
		$twoColSettings = get_sub_field('chunk_settings');
		if ($twoColSettings) {//make sure this field has saved settings		
			$twoColLayout = $twoColSettings['layout'];
			$leftImage = get_sub_field('left_image', $layout['key']);
				$leftImageURL = $leftImage['left_image_url'];
			$leftContent = get_sub_field('left_text', $layout['key']);
				$leftText = $leftContent['text_content'] ?? '';
				$firstFiveLeft = strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($leftText)), 0, 10))).'...';
			$rightImage = get_sub_field('right_image', $layout['key']);
				$rightImageUrl = $rightImage['right_image_url'];
			$rightContent = get_sub_field('right_text', $layout['key']);
				$rightText = $rightContent['text_content'] ?? '';
				$firstFiveRight = strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($rightText)), 0, 10))).'...';
			if ($twoColLayout == 'ltr') {
				$title = $title.'&nbsp;&nbsp;<img style="max-width: 60px;" src="'.$leftImageURL.'"/>';
				$title = $title.'  <span style="font-weight:300; font-size: 12px;color: #666;">'.$firstFiveRight.'</span>';
			} else if ($twoColLayout == 'rtl') {
				$title = $title.'&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">'.$firstFiveLeft.'</span>';
				$title = $title.'  <img style="max-width: 60px;" src="'.$rightImageUrl.'"/>';
			} else if ($twoColLayout == 'txt') {
				$title = $title.'&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">'.$firstFiveLeft.'</span>';
				$title = $title.'  <span style="font-weight:300; font-size: 12px;color: #666;">'.$firstFiveRight.'</span>';
			} else if ($twoColLayout == 'img') {
				$title = $title.'&nbsp;&nbsp;<img style="max-width: 60px;" src="'.$leftImageURL.'"/>';
				$title = $title.'  <img style="max-width: 60px;" src="'.$rightImageUrl.'"/>';
			}
		}
	} else if ($layout['name'] === 'three-column') {
		$threeColSettings = get_sub_field('chunk_settings');
		if ($threeColSettings) {//make sure this field has saved settings	
			$leftContent = get_sub_field('left_content');
				$Ltype = $leftContent['content_type'];
				if ($Ltype == 'text') {
				$Ltext = $leftContent['left_text'];
					$LtextAlign = $Ltext['align'];
					$LtextColor = $Ltext['text_color'];
					$LtextContent = $Ltext['text_content'];
					$title = $title.'&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">'.strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($LtextContent)), 0, 5))).'...</span>';
				} else {
				$Limage = $leftContent['left_image'];
					$LimageSrc = $Limage['left_image_url'];
					$LimageLink = $Limage['left_image_link'];
					$LimageAlt = $Limage['left_image_alt'];
					$title = $title.'&nbsp;&nbsp;<img style="max-width: 60px;" src="'.$LimageSrc.'"/>';
				}
			$middleContent = get_sub_field('middle_content');
				$Mtype = $middleContent['content_type'];
				if ($Mtype == 'text') {
				$Mtext = $middleContent['middle_text'];
					$MtextAlign = $Mtext['align'];
					$MtextColor = $Mtext['text_color'];
					$MtextContent = $Mtext['text_content'];
					$title = $title.'&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">'.strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($MtextContent)), 0, 5))).'...</span>';
				} else {
				$Mimage = $middleContent['middle_image'];
					$MimageSrc = $Mimage['middle_image_url'];
					$MimageMink = $Mimage['middle_image_link'];
					$MimageAlt = $Mimage['middle_image_alt'];
					$title = $title.'&nbsp;&nbsp;<img style="max-width: 60px;" src="'.$MimageSrc.'"/>';
				}
			$rightContent = get_sub_field('right_content');
				$Rtype = $rightContent['content_type'];
				if ($Rtype == 'text') {
				$Rtext = $rightContent['right_text'];
					$RtextAlign = $Rtext['align'];
					$RtextColor = $Rtext['text_color'];
					$RtextContent = $Rtext['text_content'];
					$title = $title.'&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">'.strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($RtextContent)), 0, 5))).'...</span>';
				} else {
				$Rimage = $rightContent['right_image'];
					$RimageSrc = $Rimage['right_image_url'];
					$RimageRink = $Rimage['right_image_link'];
					$RimageAlt = $Rimage['right_image_alt'];
					$title = $title.'&nbsp;&nbsp;<img style="max-width: 60px;" src="'.$RimageSrc.'"/>';
				}
		}
	} else if ($layout['name'] === 'plain_text') {
		$plainText = get_sub_field('plain_text_content', $layout['key']);
		if (isset($plainText)) {
			$textContent = strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($plainText)), 0, 10))).'...';
			$title = $title.'&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">'.$textContent.'</span>';
		}
	} else if ($layout['name'] === 'button') {
		$buttonCTA = get_sub_field('cta_text', $layout['key']);
		if ($buttonCTA) {
			$title = $title.'&nbsp;&nbsp;<button>'.$buttonCTA.'</button>';
		}
	}

    return $title;
}

add_filter('acf/fields/flexible_content/layout_title', 'id_filter_acf_chunk_title', 10, 4);

function code_repository_shortcode() {
	ob_start();
	echo '<div id="codeRepository">';
	if (have_rows('repo_block', 'options')) {
		while (have_rows('repo_block', 'options')) {
			the_row();
			?>
			<div class="two-col-wrap">
				<div class="left">
					<h2 class="repo-block-title"><?php echo get_sub_field('block_title'); ?></h2>
					<?php echo get_sub_field('block_info'); ?>
				</div>
				<div class="right">
					<?php
					ob_start();
					//get_template_part('template-parts/chunks/' . get_sub_field('block_slug'));
					include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/chunks/' . get_sub_field('block_slug') . '.php';
					$plainTextHTML = ob_get_clean();
					?>
					<pre style="white-space: pre; " tabsize="1" wrap="soft">
						<code class="language-html">
							<?php echo htmlspecialchars($plainTextHTML); ?>
						</code>
					</pre>
				</div>
			</div>
			<?php
		}
	} else {
		// Handle the case when there are no rows.
		echo 'No code repository blocks found.';
	}
	echo '</div>';
	return ob_get_clean();
}
add_shortcode('code_repo', 'code_repository_shortcode');


//Chunk Functions

//set up standard cols
function fillImage($imgUrl,$imgLink,$imgAlt,$mobileImgs,$mobImgUrl,$imageWidth) {
	if (!$imgUrl) {
		return false;
	}
	$dtClass='';
	$htmlComment = $imageWidth.' Image -->';
	if ($mobileImgs == 'alt' || $mobileImgs == 'hide') {
		$dtClass = 'hide-mobile';
		$htmlComment = $imageWidth.' Image Desktop-->';
	}
$colImage = '
                                          <!-- '.$htmlComment.'
                                          <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" style="width:100%;max-width:100%;" class="'.$dtClass.'">
                                            <tr>
                                              <td align="center" valign="top" class="img-responsive">
                                                <a href="'.$imgLink.'">
                                                  <img style="display:block;width:100%;max-width:'.$imageWidth.'px;display:block;border:0px;" width="'.$imageWidth.'" src="'.$imgUrl.'" border="0" alt="'.$imgAlt.'" />
                                                </a>
                                              </td>
                                            </tr>
                                          </table>
                                          <!-- / End '.$htmlComment;
if ($mobileImgs == 'alt') {
$colImage .= '
                                          
                                          <!-- '.$imageWidth.' Image Mobile Start -->
                                          <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" style="width:100%;max-width:100%;" class="hide-desktop">
                                              <tr>
                                                <td align="center" valign="top" class="img-responsive">
                                                  <a href="'.$imgLink.'">
                                                   <img style="display:block;width:100%;max-width:'.$imageWidth.'px;display:block;border:0px;" width="'.$imageWidth.'" src="'.$mobImgUrl.'" border="0" alt="'.$imgAlt.'" />
                                                  </a>
                                                </td>
                                              </tr>
                                            </table>
                                          <!-- /End '.$imageWidth.' Image Mobile -->
                                          
';
	}
	return $colImage;
}

function fillText($textContent,$align,$fontColor,$bgColor, $centerOnMobile,$spacing=array('top','bottom'), $padText = false) {
	if (!$textContent) {
		return false;
	}
$centerMobile = '';
if ($centerOnMobile == true) {
	$centerMobile = 'center-on-mobile';
}
$topSpacing = false;
$btmSpacing = false;
if (in_array('top',$spacing)) {
$topSpacing = true;
}
if (in_array('bottom',$spacing)) {
$btmSpacing = true;
}
if ($padText) {
	$textPadding = 'padding: 20px;';
} else {
	$textPadding = '';
}

$colText = '';
if($topSpacing) {
                              $colText .= '
                                          <!-- Optional Top Space -->
                                          <table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:'.$bgColor.'">
                                            <tr>
                                              <td class="space-control" valign="middle" align="center" height="20"></td>
                                            </tr>
                                          </table>
                                          <!-- / End Optional Top Space -->
                                          ';
}
$colText .= '
                                          <!-- Text Start -->
                                          <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" style="width:100%;max-width:100%;">
                                            <tr>
                                              <td class="text responsive-text '.$align.'-text '.$centerMobile.'" valign="middle" align="'.$align.'" style="'.$textPadding.' font-family:Poppins, sans-serif;color:'.$fontColor.' !important;text-decoration:none;">
                                                '.$textContent.'
                                              </td>
                                            </tr>
                                          </table>
                                          <!-- /End Text -->
                                          
';
if($btmSpacing) {
                              $colText .= '
                                          <!-- Optional Top Space -->
                                          <table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:'.$bgColor.'">
                                            <tr>
                                              <td class="space-control" valign="middle" align="center" height="20"></td>
                                            </tr>
                                          </table>
                                          <!-- / End Optional Top Space -->
                                          ';
}
	return $colText;
}


function inline_button($inlineButton=false) {
	if (!$inlineButton) {
		return;
	}
	
	$buttonText = $inlineButton['button_text'];
	$buttonUrl = $inlineButton['button_url'];
	$buttonSettings = $inlineButton['button_settings'];
		$bgColor = $buttonSettings['button_background_color'] ?? '#94d500';
		$chunkBgColor = $buttonSettings['chunk_background_color'] ?? '#FFFFFF';
		$textColor = $buttonSettings['text_color'] ?? '#FFFFFF';
		$borderColor = $buttonSettings['border_color'] ?? '#94d500';
		$borderSize = $buttonSettings['border_size'] ?? '1px';
		$borderRad = $buttonSettings['border_radius'] ?? '3px';
		$mobileVis = $buttonSettings['mobile_visibility'] ?? true;
		$spacing = $buttonSettings['spacing'] ?? array('top','bottom');
		$hideMobile = '';
		if ($mobileVis == false) {
			$hideMobile = 'hide-mobile';
		}
		$topSpacing = false;
		$btmSpacing = false;
		if (in_array('top',$spacing)) {
			$topSpacing = true;
		}
		if (in_array('bottom',$spacing)) {
		$btmSpacing = true;
		}
		
	ob_start();
	 if($topSpacing) {?>
                                               <!-- Optional Top Space -->
                                               <table class="<?php echo $hideMobile; ?>" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:<?php echo $chunkBgColor; ?>;">
                                                  <tbody>
                                                    <tr>
                                                      <td class="space-control" valign="middle" align="center" height="20">
                                                      </td>
                                                    </tr>
                                                  </tbody>
                                                </table>
                                               <!-- / End Optional Top Space -->
<?php } ?>
                                               
                                                <table width="100%" border="0" cellspacing="0" cellpadding="0" class="<?php echo $hideMobile; ?>" >
                                                  <tbody>
                                                    <tr>
                                                      <td>
                                                        <table border="0" cellspacing="0" cellpadding="0" align="center" style="margin: 0 auto;">
                                                          <tbody>
                                                            <tr style="color:<?php echo $textColor; ?>;">
                                                            
                                                            <!--Button Content Start-->
                                                              <td align="center" bgcolor="<?php echo $bgColor; ?>" style="border-radius:<?php echo $borderRad; ?>; color:<?php echo $textColor; ?>;">
                                                              <a target="_blank"
                                                                class="button-link" 
                                                                style="
                                                                  font-size:19px;font-family:Poppins, sans-serif;line-height:24px;font-weight: bold;text-decoration:none;
                                                                  display:inline-block;
																  margin: 0 auto;
																  padding:14px 30px;
                                                                  color:<?php echo $textColor; ?>;
                                                                  border-radius:<?php echo $borderRad; ?>;
                                                                  border:<?php echo $borderSize; ?> solid <?php echo $borderColor; ?>;
                                                                "
                                                                href="<?php echo $buttonUrl; ?>">
                                                                <span style="color:<?php echo $textColor; ?>;"><?php echo $buttonText; ?></span>
                                                              </a>
                                                              </td>
                                                            <!--/End Button Content-->
                                                            
                                                            </tr>
                                                          </tbody>
                                                        </table>
                                                      </td>
                                                    </tr>
                                                  </tbody>
                                                </table>
<?php if($btmSpacing) {?>
                                               <!-- Optional Bottom Space -->
                                               <table class="<?php echo $hideMobile; ?>" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:<?php echo $chunkBgColor; ?>;">
                                                  <tbody>
                                                    <tr>
                                                      <td class="space-control" valign="middle" align="center" height="20">
                                                      </td>
                                                    </tr>
                                                  </tbody>
                                                </table>
                                               <!-- / End Optional Bottom Space -->
<?php }
 
return ob_get_clean();
}

function load_mobile_css() {
  //check nonce
  check_ajax_referer( 'template-editor', 'security' );

  $css_file = $_POST['css_file'];
  $output = '<link rel="stylesheet" href="' . $css_file . '" type="text/css" />';
  echo $output;
  die();
}
add_action('wp_ajax_load_mobile_css', 'load_mobile_css');
add_action('wp_ajax_nopriv_load_mobile_css', 'load_mobile_css');