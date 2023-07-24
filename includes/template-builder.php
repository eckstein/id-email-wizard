<?php

add_action('wp_ajax_idemailwiz_build_template', 'idemailwiz_build_template');
function idemailwiz_build_template() {
  //Check for ajax updates from live form editor
  if ($_POST && isset($_POST['action']) && $_POST['action'] == 'idemailwiz_build_template') {
    
      $formAction = $_POST['action']; //should be idemailwiz_build_template
      //Strip slashes from text (since we're not making sql requests or anything dangerous)
      //Prevents slashes from showing in editor during ajax update to text fields.
      $formData = array_map('stripslashes_deep', $_POST);
        //convert the acf keys to the field slugs so they will work in our chunk templates
        $formData = convert_keys_to_names($formData);
      $template_id = $formData['_acf_post_id'];
      $validated = $formData['_acf_validation'];
      $fields = $formData['acf'];
      //echo '<pre>';print_r($fields);echo '</pre>';
      $chunks = $fields['add_chunk'];
      $templateSettings = $fields['template_settings'];
      $templateFonts = $fields['template_styles'];
      $emailSettings = $fields['email_settings'];

  } else {
    //Not a live update, just a regular page load, so we get the database values
      global $wp_query;
      $template_id = intval($wp_query->query_vars['build-template']);
      //TODO: Add settings fields to map settings to fields for better flexibility
      $chunks = get_field('field_63e3c7cfc01c6', $template_id);
      $templateSettings = get_field('field_63e3d8d8cfd3a', $template_id);
      $templateFonts = get_field('field_63e3d784ed5b5', $template_id);
      $emailSettings = get_field('field_63e898c6dcd23', $template_id);

      //$chunks = convert_keys_to_names($chunks);
  }

   //Preview pane styles
   include( dirname( plugin_dir_path( __FILE__ ) ) . '/styles/preview-pane-styles.html' );
  
  
  ob_start();

  //Start Template
  //Default email header (<head>, open tags, styles, etc)
  include( dirname( plugin_dir_path( __FILE__ ) ) . '/templates/chunks/email-top.php' );
  include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/css.php');
  include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/end-email-top.php');
  
  
  //Standard email header snippet, if active
  if ($templateSettings['id_tech_header'] == true) {
      //include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/standard-email-header.php');
      include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/preview-header.html');
  }
  
  

  //Start Chunk Content
  $i=0;
  foreach ($chunks as $chunk) {
    
    
     $chunkFileName = str_replace('_', '-', $chunk['acf_fc_layout']);
     $file = dirname(plugin_dir_path( __FILE__ )) . '/templates/chunks/' . $chunkFileName . '.php';
      if (file_exists($file)) {
          echo '<div class="chunkWrap" data-id="row-'.$i.'" data-chunk-layout="'.$chunk['acf_fc_layout'].'">';
          ob_start();
          include($file);
          $html = ob_get_contents();
          ob_end_flush();
          echo '<div class="chunkOverlay"><span class="chunk-label">Chunk Type: '.$chunk['acf_fc_layout'].'</span><button class="showChunkCode" data-id="row-'.$i.'">Get Code</button></div>';
          echo '<div class="chunkCode">'.htmlspecialchars($html).'</div>';
          echo '</div>';
      }
      $i++;
  }
  
  //Email footer (close tags, disclaimer)
  if ($templateSettings['id_tech_footer'] == true) {
      //Standard email footer snippet (social links, unsub, etc)
      //include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/standard-email-footer.php');
      include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/preview-footer.html');
  }
  
  //Fine print/disclaimer text
  if (!empty($templateSettings['fine_print_disclaimer'])) {
      include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/email-before-disclaimer.php');
      include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/fine-print-disclaimer.php');
      include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/email-after-disclaimer.php');
  }
  
  
  ?>
  <div class="preview-scrollspace"></div>
  <?php 
  echo ob_get_clean();
  wp_die();
  }
  
  
  function convert_keys_to_names($array) {
    $new_array = array();
    foreach ($array as $key => $value) {
        if (substr($key, 0, 6) === 'field_') {
            $field = acf_get_field($key);
            if ($field) {
                $new_key = $field['name'];
            } else {
                $new_key = $key;
            }
        } else {
            $new_key = $key;
        }

        if (is_array($value)) {
            $new_value = convert_keys_to_names($value);
        } else {
            $new_value = $value;
        }

        $new_array[$new_key] = $new_value;
    }
    return $new_array;
}


//Generate all the HTML for a template
add_action('wp_ajax_idemailwiz_generate_template_html', 'idemailwiz_generate_template_html');
function idemailwiz_generate_template_html() {
    $template_id = $_POST['template_id'];
    $chunks = get_field('field_63e3c7cfc01c6', $template_id);
    $templateSettings = get_field('field_63e3d8d8cfd3a', $template_id);
    $templateFonts = get_field('field_63e3d784ed5b5', $template_id);
    $emailSettings = get_field('field_63e898c6dcd23', $template_id);
    ob_start();

    //Start Template
    //Default email header (<head>, open tags, styles, etc)
    include( dirname( plugin_dir_path( __FILE__ ) ) . '/templates/chunks/email-top.php' );
    include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/css.php');
    include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/end-email-top.php');
    
    
    //Standard email header snippet, if active
    if ($templateSettings['id_tech_header'] == true) {
        include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/standard-email-header.php');
        //include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/preview-header.html');
    }
    
    

    //Start Chunk Content
    $i=0;
    foreach ($chunks as $chunk) {
        
        
        $chunkFileName = str_replace('_', '-', $chunk['acf_fc_layout']);
        $file = dirname(plugin_dir_path( __FILE__ )) . '/templates/chunks/' . $chunkFileName . '.php';
        if (file_exists($file)) {
            echo '<div class="chunkWrap" data-id="row-'.$i.'" data-chunk-layout="'.$chunk['acf_fc_layout'].'">';
            ob_start();
            include($file);
            $html = ob_get_contents();
            ob_end_flush();
            echo '<div class="chunkOverlay"><span class="chunk-label">Chunk Type: '.$chunk['acf_fc_layout'].'</span><button class="showChunkCode" data-id="row-'.$i.'">Get Code</button></div>';
            echo '<div class="chunkCode">'.htmlspecialchars($html).'</div>';
            echo '</div>';
        }
        $i++;
    }
    
    //Email footer (close tags, disclaimer)
    if ($templateSettings['id_tech_footer'] == true) {
        //Standard email footer snippet (social links, unsub, etc)
        include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/standard-email-footer.php');
        //include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/preview-footer.html');
    }
    
    //Fine print/disclaimer text
    if (!empty($templateSettings['fine_print_disclaimer'])) {
        include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/email-before-disclaimer.php');
        include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/fine-print-disclaimer.php');
        include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/email-after-disclaimer.php');
    }
    
    
    ?>
    <div class="preview-scrollspace"></div>
    <?php
    $generatedHTML = ob_get_clean();
    echo htmlspecialchars(html_entity_decode($generatedHTML));
    wp_die();
}

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