<?php

add_action('wp_ajax_idemailwiz_build_template', 'idemailwiz_build_template');
function idemailwiz_build_template() {
  //Check for ajax updates from live form editor
  if (!wp_doing_ajax() && !get_query_var('build-template')) {
        return;
    }
  if (!$_POST || !isset($_POST['action']) || !$_POST['action'] == 'idemailwiz_build_template') {
     //Go back, nothing to see here yet!     
     return false;
  }
  $formAction = $_POST['action']; //should be idemailwiz_build_template
    if ($formAction != 'idemailwiz_build_template') {
        return false;
    }

    //Merge Tags Toggle
    $mergeTags = false;
    if (isset($_POST['mergetags']) && $_POST['mergetags'] == 'true') {
        $mergeTags = true;
    }

    //Chunk separators Toggle
    $chunkSeps = false;
    if (isset($_POST['showseps']) && $_POST['showseps'] == 'true') {
        $chunkSeps = true;
    }
    
    // Determine whether to show separators in template preview
    $chunkSepsClass = '';
    if (!$chunkSeps) {
        $chunkSepsClass = 'hide-seps';
    }
    

  //We've got ajax post data, let's build the template...   
    //Strip slashes from text (since we're not making sql requests or anything dangerous)
    //Prevents slashes from showing in editor during ajax update to text fields.
    $formData = array_map('stripslashes_deep', $_POST);
    //convert the acf keys to the field slugs so they will work in our chunk templates
    $formData = convert_keys_to_names($formData);
    $template_id = $formData['_acf_post_id'];
    $validated = $formData['_acf_validation'];
    $fields = $formData['acf'];
    
    $chunks = $fields['add_chunk'];
    //echo '<pre>';print_r($chunks);echo '</pre>';
    $templateSettings = $fields['template_settings'];
    $templateFonts = $fields['template_styles'];
    $emailSettings = $fields['email_settings'];
    $externalUTMS = $emailSettings['external_utms'] ?? false;
    $externalUTMstring = $emailSettings['external_utm_string'] ?? '';
    
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
if (isset($chunks) && !empty($chunks)) {
    $i=0;
    foreach ($chunks as $chunkId => $chunk) {
        $chunkFileName = str_replace('_', '-', $chunk['acf_fc_layout']);
        $file = dirname(plugin_dir_path( __FILE__ )) . '/templates/chunks/' . $chunkFileName . '.php';
        if (file_exists($file)) {
            echo '<div class="chunkWrap ' . $chunkSepsClass .'" data-id="'.$chunkId.'" data-templateid="'.$template_id.'" data-chunk-layout="'.$chunk['acf_fc_layout'].'">';
            ob_start();
            include($file);
            $html = ob_get_clean(); // Get the buffer contents and delete the buffer

            //Do merge tags, if active
            if ($mergeTags) {
                $html = idwiz_apply_mergemap($html);
            }

            //Add external UTMs, if active            
            if ($externalUTMS) {
                $html = idwiz_add_utms($html, $externalUTMstring);       
            }

            //Echo the final HTML
            echo $html;

            //Add chunko overlay UI
            echo '<div class="chunkOverlay"><span class="chunk-label">Chunk Type: '.$chunk['acf_fc_layout'].'</span><button class="showChunkCode" data-id="'.$chunkId.'" data-templateid="'.$template_id.'">Get Code</button></div>';
            
            //End chunk wrap
            echo '</div>';
        }
        $i++;
    }
} else {
    echo '<div style="background-color: #fff; font-family: Poppins, Arial, Sans Serif; padding: 20px; font-size: 18px; text-align: center;"><strong>Choose a chunk to start building your layout here.</strong><br/><em>Hint: You can turn off the default header and footer sections from the settings tab.</em></div>';
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
  
  echo ob_get_clean();
  }
  

