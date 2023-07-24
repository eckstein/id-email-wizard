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

  ?>