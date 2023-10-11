<?php
//Insert overlay and spinner into header
function insert_overlay_loader()
{
  $options = get_option('idemailwiz_settings');
  if ((is_single() && get_post_type() == 'idemailwiz_template')) {
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
add_action('wp_head', 'idemailwiz_head');
function idemailwiz_head()
{
  // Preload overlay stuff so it happens fast
  insert_overlay_loader();

  //Add meta to prevent scaling on mobile (for DataTables)
  echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">';
}


// Determine if a template or folder is in the current user's favorites
function is_user_favorite($object_id, $object_type)
{
  // Determine the meta key based on the object_type
  $meta_key = 'idwiz_favorite_' . strtolower($object_type) . 's'; // either 'favorite_templates' or 'favorite_folders'

  $favorites = get_user_meta(get_current_user_id(), $meta_key, true);

  if (!is_array($favorites)) {
    $favorites = array();
  }

  // Cast IDs in favorites to integers for consistent comparison
  $favorites = array_map('intval', $favorites);

  $object_id = intval($object_id); // Ensure object_id is an integer

  // Check if $object_id is in favorites
  if (in_array($object_id, $favorites)) {
    return true;
  }

  return false;
}

//Category page breadcrumb
function display_folder_hierarchy()
{
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
function display_template_folder_hierarchy($post_id)
{
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
function id_generate_folders_select($parent_id = 0, $prefix = '')
{
  $options = '';

  $folders = get_terms(array('taxonomy' => 'idemailwiz_folder', 'parent' => $parent_id, 'hide_empty' => false));

  foreach ($folders as $folder) {
    //skips the trash folder if it exists
    $siteOptions = get_option('idemailwiz_settings');
    $trashTerm = (int) $siteOptions['folder_trash'];
    if ($folder->term_id == $trashTerm) {
      continue;
    }
    $name = $folder->name;
    $options .= '<option value="' . $folder->term_id . '">' . $prefix . $name . '</option>';
    $options .= id_generate_folders_select($folder->term_id, '&nbsp;&nbsp;' . $prefix . '-&nbsp;&nbsp;');
  }

  return $options;
}

function id_generate_folders_select_ajax()
{
  //check nonce (could be one of two files)
  $nonceCheck = check_ajax_referer('folder-actions', 'security', false);
  if (!$nonceCheck) {
    check_ajax_referer('template-actions', 'security');
  }

  $options = id_generate_folders_select();
  wp_send_json_success(array('options' => $options));
  wp_die();
}

add_action('wp_ajax_id_generate_folders_select_ajax', 'id_generate_folders_select_ajax');


// Template select2 ajax handler
function idemailwiz_get_templates_for_select()
{
  check_ajax_referer('id-general', 'security');

  $searchTerm = $_POST['q'];

  $allTemplates = get_posts(array('post_type' => 'idemailwiz_template', 'posts_per_page' => -1, 's' => $searchTerm));
  $data = [];
  $cnt = 0;
  foreach ($allTemplates as $template) {
    $data[$cnt]['id'] = $template->ID;
    $data[$cnt]['text'] = $template->post_title;
    $cnt++;
  }
  //error_log(print_r($data, true));
  echo json_encode(array_values($data));
  wp_die();
}
add_action('wp_ajax_idemailwiz_get_templates_for_select', 'idemailwiz_get_templates_for_select');


// Initiaves select2 ajax handler
function idemailwiz_get_initiatives_for_select()
{
  check_ajax_referer('data-tables', 'security');

  $searchTerm = $_POST['q'] ?? '';

  $allInitiatives = get_posts(array('post_type' => 'idwiz_initiative', 'posts_per_page' => -1, 's' => $searchTerm));
  $data = [];
  $cnt = 0;
  foreach ($allInitiatives as $initiative) {
    $data[$cnt]['id'] = $initiative->ID;
    $data[$cnt]['text'] = $initiative->post_title;
    $cnt++;
  }
  //error_log(print_r($data,true));
  echo json_encode(array_values($data));
  wp_die();
}
add_action('wp_ajax_idemailwiz_get_initiatives_for_select', 'idemailwiz_get_initiatives_for_select');





function idemailwiz_mergemap()
{

  $mergeMapping = array(
    '{{{snippet "FirstName" "your child"}}}' => 'Garfield',
    '{{{snippet "FirstName" "Your child"}}}' => 'Garfield',
    '{{{snippet "FirstName" "Your Child"}}}' => 'Garfield',
    '{{{snippet "pronoun" GenderCode "S"}}}' => 'he',
    '{{{snippet "pronoun" GenderCode "O"}}}' => 'him',
    '{{{snippet "pronoun" GenderCode "SP"}}}' => 'his',
    '{{{snippet "pronoun" GenderCode "OP"}}}' => 'his',
    '{{{snippet "Pronoun" GenderCode "S"}}}' => 'He',
    '{{{snippet "Pronoun" GenderCode "O"}}}' => 'Him',
    '{{{snippet "Pronoun" GenderCode "SP"}}}' => 'His',
    '{{{snippet "Pronoun" GenderCode "OP"}}}' => 'His',
  );

  return $mergeMapping;
}

//Generate HTML for a single chunk
add_action('wp_ajax_idemailwiz_generate_chunk_html', 'idemailwiz_generate_chunk_html');
function idemailwiz_generate_chunk_html()
{
  $template_id = $_POST['template_id'];
  $row_id = $_POST['row_id'];
  $emailSettings = get_field('field_63e898c6dcd23', $template_id);
  $externalUTMs = $emailSettings['external_utms'];
  $extUTMstring = $emailSettings['external_utm_string'];

  $chunks = get_field('field_63e3c7cfc01c6', $template_id);

  // Extract the row index from the row_id
  $row_index = str_replace('row-', '', $row_id);

  // Check if the chunk at row_index exists
  if (isset($chunks[$row_index])) {
    $chunk = $chunks[$row_index];
    $chunkFileName = str_replace('_', '-', $chunk['acf_fc_layout']);
    $file = dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/' . $chunkFileName . '.php';

    if (file_exists($file)) {
      ob_start();
      include($file);
      $html = ob_get_clean();

      //Add external UTMs, if active. Important: only generated when UTMs are saved to database, not on live preview
      if ($externalUTMs) {
        $html = idwiz_add_utms($html, $extUTMstring);
      }

      //Echo final HTML
      echo htmlspecialchars(html_entity_decode($html));
    }
  }

  wp_die();
}

//Generate all the HTML for a template
add_action('wp_ajax_idemailwiz_generate_template_html', 'idemailwiz_generate_template_html');
function idemailwiz_generate_template_html()
{

  if (check_ajax_referer('iterable-actions', 'security', false) || check_ajax_referer('template-editor', 'security', false)) {
    // One of the nonces is valid, proceed...
  } else {
    // Invalid nonce
    wp_die('Invalid nonce');
  }


  $template_id = $_POST['template_id'];
  $chunks = get_field('add_chunk', $template_id);
  $templateSettings = get_field('template_settings', $template_id);
  $templateStyles = get_field('template_styles', $template_id);
  $emailSettings = get_field('email_settings', $template_id);
  $externalUTMs = $emailSettings['external_utms'];
  $extUTMstring = $emailSettings['external_utm_string'];
  ob_start();

  //Start Template
  //Default email header (<head>, open tags, styles, etc)
  include(dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/email-top.php');
  include(dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/css.php');
  include(dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/end-email-top.php');


  //Standard email header snippet, if active
  if ($templateSettings['id_tech_header'] == true) {
    include(dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/standard-email-header.php');
    //include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/preview-header.html');
  }



  //Start Chunk Content (for HTML generation)
  $i = 0;
  foreach ($chunks as $chunk) {


    $chunkFileName = str_replace('_', '-', $chunk['acf_fc_layout']);
    $file = dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/' . $chunkFileName . '.php';
    if (file_exists($file)) {
      ob_start();
      include($file);
      $html = ob_get_clean();

      //Add external UTMs, if active. 
      if ($externalUTMs) {
        $html = idwiz_add_utms($html, $extUTMstring);
      }

      //echo final chunk HTML
      echo $html;
    }
    $i++;
  }

  //Email footer (close tags, disclaimer)
  if ($templateSettings['id_tech_footer'] == true) {
    //Standard email footer snippet (social links, unsub, etc)
    include(dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/standard-email-footer.php');
    //include(dirname(plugin_dir_path( __FILE__ ) ) . '/templates/chunks/preview-footer.html');
  }

  //Fine print/disclaimer text
  if (!empty($templateSettings['fine_print_disclaimer'])) {
    include(dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/email-before-disclaimer.php');
    include(dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/fine-print-disclaimer.php');
    include(dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/email-after-disclaimer.php');
  }

  $generatedHTML = ob_get_clean();
  echo htmlspecialchars(html_entity_decode($generatedHTML));
  wp_die();
}

//Replaced <p> tags with <br/> tags
function idwiz_pReplace($content)
{
  $content = preg_replace(array('/<p>/', '/<\/p>/'), array('', '<br><br>'), $content);
  return $content;
}

function idwiz_apply_mergemap($html)
{
  if (!function_exists('idemailwiz_mergemap')) {
    return $html;
  }
  $mergeMap = idemailwiz_mergemap();
  foreach ($mergeMap as $tag => $value) {
    $html = str_replace($tag, $value, $html);
  }
  return $html;
}
function idwiz_add_utms($html, $utms)
{
  if (empty($utms)) {
    // bail if empty UTMs
    return $html;
  }
  $html = preg_replace_callback(
    '/(href="[^"]*)/i',
    function ($matches) use ($utms) {
      return $matches[1] . '?' . $utms . '"';
    },
    $html
  );
  return $html;
}
function convert_keys_to_names($array)
{
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




//Add chunk element content to the acf layout chunk title area for easy IDing of content
function id_filter_acf_chunk_title($title, $field, $layout, $i)
{
  // Only modify title for specific layout
  if ($layout['name'] === 'full_width_image' || $layout['name'] === 'contained_image') {
    $image_url = get_sub_field('desktop_image_url', $layout['key']);
    if ($image_url) {
      if (!empty($image_url)) {
        $title = $title . '&nbsp;&nbsp;<img style="max-width: 60px;" src="' . $image_url . '"/>';
      }
    }
  } else if ($layout['name'] === 'two-column' || $layout['name'] === 'two-column-contained') {
    $twoColSettings = get_sub_field('chunk_settings');
    if ($twoColSettings) { //make sure this field has saved settings		
      $twoColLayout = $twoColSettings['layout'];
      $leftImage = get_sub_field('left_image', $layout['key']);
      $leftImageURL = $leftImage['left_image_url'];
      $leftContent = get_sub_field('left_text', $layout['key']);
      $leftText = $leftContent['text_content'] ?? '';
      $firstFiveLeft = strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($leftText)), 0, 10))) . '...';
      $rightImage = get_sub_field('right_image', $layout['key']);
      $rightImageUrl = $rightImage['right_image_url'];
      $rightContent = get_sub_field('right_text', $layout['key']);
      $rightText = $rightContent['text_content'] ?? '';
      $firstFiveRight = strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($rightText)), 0, 10))) . '...';
      if ($twoColLayout == 'ltr') {
        $title = $title . '&nbsp;&nbsp;<img style="max-width: 60px;" src="' . $leftImageURL . '"/>';
        $title = $title . '  <span style="font-weight:300; font-size: 12px;color: #666;">' . $firstFiveRight . '</span>';
      } else if ($twoColLayout == 'rtl') {
        $title = $title . '&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">' . $firstFiveLeft . '</span>';
        $title = $title . '  <img style="max-width: 60px;" src="' . $rightImageUrl . '"/>';
      } else if ($twoColLayout == 'txt') {
        $title = $title . '&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">' . $firstFiveLeft . '</span>';
        $title = $title . '  <span style="font-weight:300; font-size: 12px;color: #666;">' . $firstFiveRight . '</span>';
      } else if ($twoColLayout == 'img') {
        $title = $title . '&nbsp;&nbsp;<img style="max-width: 60px;" src="' . $leftImageURL . '"/>';
        $title = $title . '  <img style="max-width: 60px;" src="' . $rightImageUrl . '"/>';
      }
    }
  } else if ($layout['name'] === 'three-column') {
    $threeColSettings = get_sub_field('chunk_settings');
    if ($threeColSettings) { //make sure this field has saved settings	
      $leftContent = get_sub_field('left_content');
      $Ltype = $leftContent['content_type'];
      if ($Ltype == 'text') {
        $Ltext = $leftContent['left_text'];
        $LtextAlign = $Ltext['align'];
        $LtextColor = $Ltext['text_color'];
        $LtextContent = $Ltext['text_content'];
        $title = $title . '&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">' . strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($LtextContent)), 0, 5))) . '...</span>';
      } else {
        $Limage = $leftContent['left_image'];
        $LimageSrc = $Limage['left_image_url'];
        $LimageLink = $Limage['left_image_link'];
        $LimageAlt = $Limage['left_image_alt'];
        $title = $title . '&nbsp;&nbsp;<img style="max-width: 60px;" src="' . $LimageSrc . '"/>';
      }
      $middleContent = get_sub_field('middle_content');
      $Mtype = $middleContent['content_type'];
      if ($Mtype == 'text') {
        $Mtext = $middleContent['middle_text'];
        $MtextAlign = $Mtext['align'];
        $MtextColor = $Mtext['text_color'];
        $MtextContent = $Mtext['text_content'];
        $title = $title . '&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">' . strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($MtextContent)), 0, 5))) . '...</span>';
      } else {
        $Mimage = $middleContent['middle_image'];
        $MimageSrc = $Mimage['middle_image_url'];
        $MimageMink = $Mimage['middle_image_link'];
        $MimageAlt = $Mimage['middle_image_alt'];
        $title = $title . '&nbsp;&nbsp;<img style="max-width: 60px;" src="' . $MimageSrc . '"/>';
      }
      $rightContent = get_sub_field('right_content');
      $Rtype = $rightContent['content_type'];
      if ($Rtype == 'text') {
        $Rtext = $rightContent['right_text'];
        $RtextAlign = $Rtext['align'];
        $RtextColor = $Rtext['text_color'];
        $RtextContent = $Rtext['text_content'];
        $title = $title . '&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">' . strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($RtextContent)), 0, 5))) . '...</span>';
      } else {
        $Rimage = $rightContent['right_image'];
        $RimageSrc = $Rimage['right_image_url'];
        $RimageRink = $Rimage['right_image_link'];
        $RimageAlt = $Rimage['right_image_alt'];
        $title = $title . '&nbsp;&nbsp;<img style="max-width: 60px;" src="' . $RimageSrc . '"/>';
      }
    }
  } else if ($layout['name'] === 'plain_text') {
    $plainText = get_sub_field('plain_text_content', $layout['key']);
    if (isset($plainText)) {
      $textContent = strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($plainText)), 0, 10))) . '...';
      $title = $title . '&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-weight:300; font-size: 12px;color: #666;">' . $textContent . '</span>';
    }
  } else if ($layout['name'] === 'button') {
    $buttonCTA = get_sub_field('cta_text', $layout['key']);
    if ($buttonCTA) {
      $title = $title . '&nbsp;&nbsp;<button class="wiz-button gray">' . $buttonCTA . '</button>';
    }
  }

  return $title;
}

add_filter('acf/fields/flexible_content/layout_title', 'id_filter_acf_chunk_title', 10, 4);

function idemailwiz_save_template_title()
{
  $templateId = $_POST['template_id'];
  $templateTitle = $_POST['template_title'];

  $updateTemplate = array(
    'ID' => $templateId,
    'post_title' => $templateTitle
  );
  $updateTitle = wp_update_post($updateTemplate);
  if (is_wp_error($updateTitle)) {
    echo false;
    wp_die();
  }
  echo $updateTitle;
  wp_die();
}
add_action('wp_ajax_idemailwiz_save_template_title', 'idemailwiz_save_template_title');

function generate_idwizcampaign_heatmap_overlay($csv_file)
{
  // Read the CSV file
  $data = file_get_contents($csv_file);
  $rows = str_getcsv($data, "\n");
  $header = str_getcsv(array_shift($rows));

  // Start the heatmap overlay div
  $overlay_html = '<div class="heatmap-overlay">';
  $overlay_html .= '<div class="heatmap-tooltips"></div>';

  // Iterate through the rows and generate heatmap points
  foreach ($rows as $row) {
    // Create an associative array for the row
    $data = array_combine($header, str_getcsv($row));

    // Extract the required fields
    $x = floatval($data['x']) - 100;
    $y = floatval($data['y']) - 20;
    $opacity = $data['uniqueClickRate'];

    // Transform the opacity value, e.g., by applying a logarithmic scale or using a minimum threshold
    $adjusted_opacity = max(0.5, log($opacity + 1) * 0.5); // You can adjust the formula as needed

    // Create a heatmap point
    $unique_clicks = $data['uniqueCount'];
    $unique_click_rate = number_format($data['uniqueClickRate'] * 100, 2) . '%'; // Format as percentage with 2 decimal places
    $url = $data['url'];

    $overlay_html .= "<div class=\"heatmap-point\" style=\"left: {$x}px; top: {$y}px; opacity: {$adjusted_opacity};\" data-unique-clicks=\"{$unique_clicks}\" data-unique-click-rate=\"{$unique_click_rate}\" data-url=\"{$url}\"></div>";

  }

  // Close the heatmap overlay div
  $overlay_html .= '</div>';

  return $overlay_html;
}


//Add custom meta metabox back to edit screens 	
add_filter('acf/settings/remove_wp_meta_box', '__return_false');


// Extract Image URLs and alt values from a set of campaigns
function idwiz_extract_campaigns_images($campaignIds = [])
{
  if (!$campaignIds || empty($campaignIds)) {
    return array();
  }
  // Initialize an array to store image data for all campaigns
  $allCampaignImageData = [];

  // Fetch templates for the given campaign IDs
  $templates = get_idwiz_templates(['campaignIds' => $campaignIds]);

  // Loop through each template to extract image information
  foreach ($templates as $template) {
    $templateHTML = $template['html'];

    // Load HTML content into a DOMDocument object
    $dom = new DOMDocument;
    @$dom->loadHTML($templateHTML);

    // Initialize an array to store image data for this specific template
    $templateImageData = [];

    // Loop through all the <img> tags in this template
    $images = $dom->getElementsByTagName('img');
    foreach ($images as $image) {
      $src = $image->getAttribute('src');
      $alt = $image->getAttribute('alt') ?? '';
      $templateImageData[] = ['src' => $src, 'alt' => $alt];
    }

    // Save the image data for this campaign
    $allCampaignImageData[$template['templateId']] = $templateImageData;
  }

  return $allCampaignImageData;
}

function idwiz_generate_dynamic_rollup()
{
  if (!isset($_POST['campaignIds'])) {
    return;
  }
  $syncButton = false;
  if (isset($_POST['syncButton']) && $_POST['syncButton'] != false) {
    $syncButton = true;
  }
  $fields = $_POST['fields'] ?? array();
  echo generate_idwiz_rollup_row($_POST['campaignIds'], $fields, $syncButton);
  wp_die();
}

add_action('wp_ajax_idwiz_generate_dynamic_rollup', 'idwiz_generate_dynamic_rollup');

function generate_idwiz_rollup_row($campaignIds, $fields, $syncButton = false)
{
  if (!$campaignIds) {
    return false;
  }

  // Use the aggregate function to get the processed metrics
  $aggregatedMetrics = idemailwiz_calculate_aggregate_metrics($campaignIds);

  $displayMetrics = [];

  // Initialize $displayMetrics based on the order of $fields
  foreach ($fields as $column => $info) {
    if (array_key_exists($column, $aggregatedMetrics) || isset($fields[$column])) {
      $value = array_key_exists($column, $aggregatedMetrics) ? $aggregatedMetrics[$column] : 0;
      $displayMetrics[] = [
        'label' => $info['label'],
        'value' => $value,
        'format' => $info['format'] // valid values are num, perc, and money
      ];
    }
  }
  if ($syncButton) {
    $displayMetrics[] = [
      'label' => 'Sync',
      'value' => $syncButton,
      'format' => false
    ];
  }

  $html = '';
  $html .= '<div class="wiztable_view_metrics_div" id="campaigns-table-rollup">';
  foreach ($displayMetrics as $metric) {
    $formattedValue = idwiz_format_rollup_row_values($metric['value'], $metric['format']);
    $html .= '<div class="metric-item">';
    $html .= "<span class='metric-label'>{$metric['label']}</span>";
    $html .= "<span class='metric-value'>{$formattedValue}</span>";
    $html .= '</div>'; // End of metric-item
  }
  $html .= '</div>'; // End of wiztable_view_metrics_div


  return $html;
}



// Function to format the value based on its format type
function idwiz_format_rollup_row_values($value, $format)
{
  switch ($format) {
    case 'num':
      return number_format($value, 0, '.', ',');
    case 'perc':
      return number_format($value, 2) . '%';
    case 'money':
      return '$' . number_format($value, 2, '.', ',');
    default:
      return $value;
  }
}


// Calculate aggregate percentage metrics for multiple campaigns
function idemailwiz_calculate_aggregate_metrics($campaignIds)
{

  $metricsArray = get_idwiz_metrics(array('ids' => $campaignIds));

  // Initialize aggregate metrics
  $aggregateMetrics = [
    'uniqueEmailSends' => 0,
    'uniqueEmailsDelivered' => 0,
    'uniqueEmailOpens' => 0,
    'uniqueEmailClicks' => 0,
    'uniqueUnsubscribes' => 0,
    'totalComplaints' => 0,
    'uniquePurchases' => 0,
    'revenue' => 0,
    'gaRevenue' => 0
  ];

  // Sum up the metrics across all campaigns
  foreach ($metricsArray as $metrics) {
    foreach ($aggregateMetrics as $key => $value) {
      if (isset($metrics[$key])) {
        $aggregateMetrics[$key] += $metrics[$key];
      }
    }
  }

  // Perform calculations
  $sendValue = (float) $aggregateMetrics['uniqueEmailSends'];
  $deliveredValue = (float) $aggregateMetrics['uniqueEmailsDelivered'];
  $clicksValue = (float) $aggregateMetrics['uniqueEmailClicks'];
  $unsubscribesValue = (float) $aggregateMetrics['uniqueUnsubscribes'];
  $complaintsValue = (float) $aggregateMetrics['totalComplaints'];
  $purchasesValue = (float) $aggregateMetrics['uniquePurchases'];
  $revenueValue = (float) $aggregateMetrics['revenue'];

  if ($sendValue > 0) {
    $aggregateMetrics['wizDeliveryRate'] = ($deliveredValue / $sendValue) * 100;
    $aggregateMetrics['wizCtr'] = ($clicksValue / $sendValue) * 100;
    $aggregateMetrics['wizUnsubRate'] = ($unsubscribesValue / $sendValue) * 100;
    $aggregateMetrics['wizCompRate'] = ($complaintsValue / $sendValue) * 100;
    $aggregateMetrics['wizCvr'] = ($purchasesValue / $sendValue) * 100;
  } else {
    $aggregateMetrics['wizCtr'] = 0;
    $aggregateMetrics['wizUnsubRate'] = 0;
    $aggregateMetrics['wizCompRate'] = 0;
    $aggregateMetrics['wizCvr'] = 0;
  }

  if ($purchasesValue > 0) {
    $aggregateMetrics['wizAov'] = ($revenueValue / $purchasesValue);
  } else {
    $aggregateMetrics['wizAov'] = 0;
  }

  // Open metrics
  $opensValue = (float) $aggregateMetrics['uniqueEmailOpens'];
  if ($opensValue > 0) {
    $aggregateMetrics['wizOpenRate'] = ($opensValue / $sendValue) * 100;
    $aggregateMetrics['wizCto'] = ($clicksValue / $opensValue) * 100;
  } else {
    $aggregateMetrics['wizOpenRate'] = 0;
    $aggregateMetrics['wizCto'] = 0;
  }

  // Count total campaigns in this group
  $aggregateMetrics['campaignCount'] = count($campaignIds);

  return $aggregateMetrics;
}


add_action('wp_ajax_idwiz_fetch_base_templates', 'idwiz_fetch_base_templates');

function idwiz_fetch_base_templates()
{
  // Verify nonce
  if (!check_ajax_referer('id-general', 'security', false)) {
    wp_send_json_error(array('message' => 'Nonce verification failed.'));
    return;
  }

  // Initialize HTML strings for different types of templates
  $initiative_html = '';
  $layout_html = '';

  // Get the term by slug
  $base_template_term = get_term_by('slug', 'base-templates', 'idemailwiz_folder');

  // Check if the term exists and is not an error
  if ($base_template_term && !is_wp_error($base_template_term)) {

    // Define WP_Query arguments
    $args = array(
      'post_type' => 'idemailwiz_template',
      'tax_query' => array(
        array(
          'taxonomy' => 'idemailwiz_folder',
          'field' => 'term_id',
          'terms' => $base_template_term->term_id,
        ),
      ),
    );

    // Execute the query
    $query = new WP_Query($args);

    // Loop through the posts and construct the HTML
    if ($query->have_posts()) {
      while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $title = get_the_title();
        $mockups = get_field('template_mock-ups', $post_id);
        $initiative = get_field('base_template_for_initiative', $post_id);

        $dtMockup = $mockups['mock-up-image-desktop'] ?? '';
        $previewMockup = $dtMockup ? '<div class="create-from-template-mockup"><img src="' . $dtMockup . '"/></div>' : '';

        $template_html = "<div class='startTemplate' data-postid='{$post_id}'>
                                    <h4>{$title}</h4>
                                    {$previewMockup}
                                  </div>";

        if ($initiative) {
          $initiative_html .= $template_html;
        } else {
          $layout_html .= $template_html;
        }
      }
      wp_reset_postdata();
    }
  }

  $final_html = '<div class="templateTabs">
                     <ul>
                       <li><a href="#initiativeTemplates">Initiative Templates</a></li>
                       <li><a href="#layoutTemplates">Layout Templates</a></li>
                     </ul>
                     <div id="initiativeTemplates" class="templateSelectWrap">' . $initiative_html . '</div>
                     <div id="layoutTemplates" class="templateSelectWrap">' . $layout_html . '</div>
                   </div>';

  // Send the HTML as a successful AJAX response
  wp_send_json_success(array('html' => $final_html));
}



// Add or remove a favorite template or folder from a user's profile
function add_remove_user_favorite()
{
  //check nonce
  if (
    check_ajax_referer('template-actions', 'security', false)
    || check_ajax_referer('user-favorites', 'security', false)
    || check_ajax_referer('initiatives', 'security', false)
  ) {
  } else {
    wp_die('Invalid nonce');
  }
  ;

  // Ensure object_id and object_type are set
  $object_id = isset($_POST['object_id']) ? intval($_POST['object_id']) : 0;
  $object_type = isset($_POST['object_type']) ? sanitize_text_field($_POST['object_type']) : '';

  if ($object_id <= 0 || empty($object_type)) {
    wp_send_json(
      array(
        'success' => false,
        'message' => 'Invalid object id or object type was sent!',
        'action' => null,
        'objectid' => $object_id,
      )
    );
  }

  // Determine the meta key based on the object_type
  $meta_key = 'idwiz_favorite_' . strtolower($object_type) . 's'; // either 'idwiz_favorite_templates' or 'idwiz_favorite_folders'

  $favorites = get_user_meta(get_current_user_id(), $meta_key, true);

  if (!is_array($favorites)) {
    $favorites = array();
  }

  $success = false;
  $message = '';
  $action = '';

  $key = array_search($object_id, $favorites);
  if (false !== $key) {
    unset($favorites[$key]);
    $message = 'Favorite ' . $object_type . ' removed.';
    $action = 'removed';
  } else {
    $favorites[] = intval($object_id); // Ensure object_id is an integer
    $message = 'Favorite ' . $object_type . ' added.';
    $action = 'added';
  }
  $success = true;

  if ($success) {
    $update_status = update_user_meta(get_current_user_id(), $meta_key, $favorites);
    if ($update_status === false) {
      $success = false;
      $message = 'Failed to update user meta.';
    } else {
      $updated_favorites = get_user_meta(get_current_user_id(), $meta_key, true);
      if (!is_array($updated_favorites)) {
        $success = false;
        $message = 'User meta was updated but the structure is incorrect.';
      } else {
        // Check if the object_id was correctly added or removed
        if ($action === 'added' && !in_array($object_id, $updated_favorites)) {
          $success = false;
          $message = 'Object id was not added correctly to ' . $object_type . '.';
        } elseif ($action === 'removed' && in_array($object_id, $updated_favorites)) {
          $success = false;
          $message = 'Object id was not removed correctly from ' . $object_type . '.';
        }
      }
    }
  }

  wp_send_json(
    array(
      'success' => $success,
      'message' => $message,
      'action' => $action,
      'objectid' => $object_id,
    )
  );
}

add_action('wp_ajax_add_remove_user_favorite', 'add_remove_user_favorite');

function generate_mini_table(
  array $headers,
  array $data,
  string $tableClass = '',
  string $scrollWrapClass = ''
) {
  // Table with sticky header
  echo '<table class="wizcampaign-tiny-table-sticky-header">';
  echo '<thead><tr>';
  foreach ($headers as $col => $width) {
    echo '<th width="' . $width . '">' . $col . '</th>';
  }
  echo '</tr></thead>';
  echo '</table>';

  // Scroll wrap and main table
  echo '<div class="wizcampaign-section-scrollwrap ' . $scrollWrapClass . '">';
  echo '<table class="wizcampaign-tiny-table ' . $tableClass . '">';
  echo '<tbody>';

  if (empty($data)) {
    echo '<tr><td class="wizsection-error-message" colspan="' . count($headers) . '">No data available</td></tr>';
  } else {
    // Table rows
    foreach ($data as $row) {
      echo '<tr>';
      foreach ($headers as $col => $width) {
        $value = $row[$col] instanceof RawHtml ? (string) $row[$col] : htmlspecialchars($row[$col]);
        echo '<td width="' . $width . '">' . $value . '</td>';

      }
      echo '</tr>';
    }
  }

  echo '</tbody>';
  echo '</table>';
  echo '</div>'; // End scroll wrap
}


function prepare_promo_code_summary_data($purchases)
{
  // Initialize variables and prepare data based on your existing logic for promo codes
  $promoCounts = [];
  $totalOrders = [];
  $ordersWithPromo = [];

  foreach ($purchases as $purchase) {
    $promo = $purchase['shoppingCartItems_discountCode'];
    $orderID = $purchase['id'];

    // Keep track of all unique order IDs
    $totalOrders[$orderID] = true;

    // Skip blank or null promo codes
    if (empty($promo)) {
      continue;
    }

    // Keep track of unique order IDs with promo codes
    $ordersWithPromo[$orderID] = true;

    if (!isset($promoCounts[$promo])) {
      $promoCounts[$promo] = [];
    }

    if (!isset($promoCounts[$promo][$orderID])) {
      $promoCounts[$promo][$orderID] = 0;
    }

    $promoCounts[$promo][$orderID] += 1;
  }

  // Calculate the total number of times each promo code was used
  $promoUseCounts = [];
  foreach ($promoCounts as $promo => $orders) {
    $promoUseCounts[$promo] = count($orders);
  }

  // Sort promo codes by usage
  arsort($promoUseCounts);

  // Calculate promo code usage statistics
  $totalOrderCount = count($totalOrders);
  $ordersWithPromoCount = count($ordersWithPromo);
  $percentageWithPromo = ($totalOrderCount > 0) ? ($ordersWithPromoCount / $totalOrderCount) * 100 : 0;

  // Headers for the promo code table
  $promoHeaders = [
    'Promo Code' => '80%',
    'Orders' => '20%'
  ];

  $promoData = [];
  foreach ($promoUseCounts as $promo => $useCount) {
    $promoData[] = [
      'Promo Code' => htmlspecialchars($promo),
      'Orders' => $useCount
    ];
  }

  return [
    'ordersWithPromoCount' => $ordersWithPromoCount,
    'totalOrderCount' => $totalOrderCount,
    'percentageWithPromo' => number_format($percentageWithPromo),
    // Not rounding here
    'promoHeaders' => $promoHeaders,
    'promoData' => $promoData
  ];
}



function get_idemailwiz_triggered_sends($args = [])
{
  global $wpdb;

  // Initialize query components
  $where_clauses = [];
  $query_params = [];

  // Check if campaignIds are provided
  if (isset($args['campaignIds']) && is_array($args['campaignIds']) && !empty($args['campaignIds'])) {
    $placeholders = array_fill(0, count($args['campaignIds']), '%d');
    $where_clauses[] = "campaignId IN (" . implode(", ", $placeholders) . ")";
    $query_params = array_merge($query_params, $args['campaignIds']);
  }

  // Check if startAt_start is provided
  if (isset($args['startAt_start'])) {
    $where_clauses[] = "startAt >= %d";
    $query_params[] = $args['startAt_start'];
  }

  // Check if startAt_end is provided
  if (isset($args['startAt_end'])) {
    $where_clauses[] = "startAt <= %d";
    $query_params[] = $args['startAt_end'];
  }

  // Construct the SQL query
  $sql = "SELECT * FROM " . $wpdb->prefix . "idemailwiz_triggered_sends";
  if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
  }

  // Prepare the SQL query with parameters
  $prepared_sql = $wpdb->prepare($sql, $query_params);

  // Execute the query and fetch results
  $results = $wpdb->get_results($prepared_sql, ARRAY_A);

  return $results;
}


function get_wizcampaigns_metric_rate($campaignIds, $metricType, $startDate = null, $endDate = null)
{
  // Check if $campaignIds is a valid array
  if (!is_array($campaignIds) || empty($campaignIds)) {
    return 'N/A';
  }

  // Fetch metrics for the given campaign IDs
  $metrics = get_idwiz_metrics(['ids' => $campaignIds]);

  // If metrics couldn't be fetched or returned an empty array, return 'N/A'
  if (!$metrics || empty($metrics)) {
    return 'N/A';
  }

  // Map metric types to corresponding DB fields and divisor fields
  $metricConfig = [
    'sends' => ['field' => 'uniqueEmailSends', 'divisor' => null],
    'delRate' => ['field' => 'uniqueEmailsDelivered', 'divisor' => 'uniqueEmailSends'],
    'opens' => ['field' => 'uniqueEmailOpens', 'divisor' => 'totalEmailSends'],
    'clicks' => ['field' => 'uniqueEmailClicks', 'divisor' => null],
    'ctr' => ['field' => 'uniqueEmailClicks', 'divisor' => 'totalEmailSends'],
    'cto' => ['field' => 'uniqueEmailClicks', 'divisor' => 'uniqueEmailOpens'],
    'unsubs' => ['field' => 'uniqueUnsubscribes', 'divisor' => 'totalEmailSends'],
    'revenue' => ['field' => 'revenue', 'divisor' => null],
    'gaRevenue' => ['field' => 'gaRevenue', 'divisor' => null],
    'purchases' => ['field' => 'uniquePurchases', 'divisor' => null],
    'cvr' => ['field' => 'uniquePurchases', 'divisor' => 'totalEmailSends'],
    'aov' => ['field' => 'revenue', 'divisor' => 'uniquePurchases']
  ];

  // Retrieve the DB field and divisor field based on metric type
  if (!array_key_exists($metricType, $metricConfig)) {
    return 'Invalid Metric Type';
  }

  // if ($metricType == 'revenue') {
  // if ($startDate && $endDate) {
  //   return get_idwiz_revenue($startDate, $endDate, ['Triggered', 'Blast'], false);
  //  } else {
  //   return 'Start date and end date required for revenue metrics';
  //}
  //}

  if ($metricType == 'gaRevenue') {
    if ($startDate && $endDate) {
      return get_idwiz_revenue($startDate, $endDate, ['Triggered', 'Blast'], true);
    } else {
      return 'Start date and end date required for revenue metrics';
    }
  }

  $dbField = $metricConfig[$metricType]['field'];
  $divideBy = $metricConfig[$metricType]['divisor'];

  // Sum up the metrics
  $allDbField = array_sum(array_column($metrics, $dbField));


  if ($divideBy) {
    // Calculate the total number for the divisor
    $allDivideBy = array_sum(array_column($metrics, $divideBy));

    // Check for division by zero
    if ($allDivideBy == 0) {
      return 'N/A';
    }
    // Calculate and return the metric rate
    $metricRate = $allDbField / $allDivideBy;

    if ($metricType != 'aov') {
      return $metricRate * 100;
    } else {
      return $metricRate;
    }
  }

  return $allDbField;
}
function parse_idwiz_metric_rate($rate)
{
  return floatval(str_replace(['%', ',', '$'], '', $rate));
}


function get_monthly_dashboard_rollup_row($campaignIds)
{
  return generate_idwiz_rollup_row(
    $campaignIds,
    array(
      'uniqueEmailSends' => array(
        'label' => 'Sends',
        'format' => 'num',
      ),
      'wizDeliveryRate' => array(
        'label' => 'Delivery Rate',
        'format' => 'perc',
      ),
      'uniqueEmailOpens' => array(
        'label' => 'Opens',
        'format' => 'num',
      ),
      'wizOpenRate' => array(
        'label' => 'Open Rate',
        'format' => 'perc',
      ),
      'uniqueEmailClicks' => array(
        'label' => 'Clicks',
        'format' => 'num',
      ),
      'wizCtr' => array(
        'label' => 'CTR',
        'format' => 'perc',
      ),
      'wizCto' => array(
        'label' => 'CTO',
        'format' => 'perc',
      ),
      'uniquePurchases' => array(
        'label' => 'Purchases',
        'format' => 'num',
      ),
      'revenue' => array(
        'label' => 'Dir. Rev.',
        'format' => 'money',
      ),
      'gaRevenue' => array(
        'label' => 'GA Rev.',
        'format' => 'money',
      ),
      'wizCvr' => array(
        'label' => 'CVR.',
        'format' => 'perc',
      ),
      'wizAov' => array(
        'label' => 'AOV.',
        'format' => 'money',
      ),

    ),

  );
}


function get_idwiz_revenue($startDate, $endDate, $campaignTypes = ['Triggered', 'Blast'], $useGa = false)
{
  $totalRevenue = 0;
  $checkCampaignArgs = ['type' => $campaignTypes, 'fields' => 'id'];

  $wizCampaigns = get_idwiz_campaigns($checkCampaignArgs);
  $wizCampaignIds = array_column($wizCampaigns, 'id');

  if ($useGa) {
    $allChannelPurchases = get_idwiz_ga_data(['startDate' => $startDate, 'endDate' => $endDate]);
    $purchases = array_filter($allChannelPurchases, fn($purchase) => in_array($purchase['campaignId'], $wizCampaignIds));
    if (!$purchases) {
      return 0;
    }
    $revenue = array_sum(array_column($purchases, 'revenue'));
  } else {
    $purchaseArgs = ['startAt_start' => $startDate, 'startAt_end' => $endDate, 'fields' => 'id,campaignId,purchaseDate,total'];
    $purchases = get_idwiz_purchases($purchaseArgs);

    if (!$purchases) {
      return 0;
    }

    $uniqueIds = [];

    $revenue = 0;


    foreach ($purchases as $purchase) {
      if (in_array($purchase['id'], $uniqueIds)) {
        continue;
      }

      if (!in_array($purchase['campaignId'], $wizCampaignIds)) {
        continue;
      }

      $wizCampaign = get_idwiz_campaign($purchase['campaignId']);

      if (!in_array($wizCampaign['type'], $campaignTypes)) {
        continue;
      }

      $wizAttributionLength = get_field('attribution_length', 'options');
      $attributeUntil = strtotime(date('Y-m-d', strtotime(date('Y-m-d', $wizCampaign['startAt'] / 1000) . ' +' . $wizAttributionLength . 'days')));
      $purchaseDate = strtotime(date('Y-m-d', strtotime($purchase['purchaseDate'])));

      if ($purchaseDate <= $attributeUntil) {
        $revenue += $purchase['total'];
        $uniqueIds[] = $purchase['id'];
      }
    }
  }

  $totalRevenue += $revenue;

  return $totalRevenue;
}




function formatTowerMetric($value, $format, $includeSign = false)
{
  $formattedValue = '';
  $sign = ($value >= 0) ? '+' : '-';

  switch ($format) {
    case 'money':
      $formattedValue = ($includeSign ? $sign : '') . '$' . number_format(abs($value), 0);
      break;
    case 'perc':
      $formattedValue = ($includeSign ? $sign : '') . number_format(abs($value), 2) . '%';
      break;
    case 'num':
      $formattedValue = ($includeSign ? $sign : '') . number_format(abs($value), 0);
      break;
    default:
      $formattedValue = $value;
  }

  return $formattedValue;
}


function get_idwiz_header_tabs($tabs, $currentActiveItem)
{
  echo '<div id="header-tabs">';
  foreach ($tabs as $tab) {
    $title = $tab['title'];
    $view = $tab['view'];
    $isActive = ($currentActiveItem == $view) ? 'active' : '';
    $url = add_query_arg(['view' => $view, 'wizMonth' => false, 'wizYear' => false]);
    echo "<a href=\"{$url}\" class=\"campaign-tab {$isActive}\">{$title}</a>";
  }
  echo '</div>';
}




function handle_experiment_winner_toggle()
{
  error_log('Made it to handler');

  global $wpdb;
  $table_name = $wpdb->prefix . 'idemailwiz_experiments';

  // Log POST data for debugging
  error_log('POST data: ' . print_r($_POST, true));

  // Security checks and validation
  if (!check_ajax_referer('wiz-metrics', 'security', false)) {
    error_log('Nonce check failed');
    wp_send_json_error('Nonce check failed');
    return;
  }

  $action = $_POST['actionType'];
  $templateId = intval($_POST['templateId']);
  $experimentId = intval($_POST['experimentId']);

  if (!$templateId || !$experimentId) {
    error_log('Invalid templateId or experimentId');
    wp_send_json_error('Invalid templateId or experimentId');
    return;
  }

  if ($action == 'add-winner') {
    error_log('Action is add-winner');

    // Clear existing winners for the same experimentId
    $result = $wpdb->update(
      $table_name,
      array('wizWinner' => null),
      array('experimentId' => $experimentId)
    );

    if ($result === false) {
      error_log("Database error while clearing winners: " . $wpdb->last_error);
      wp_send_json_error("Database error while clearing winners: " . $wpdb->last_error);
      return;
    }

    // Set new winner
    $result = $wpdb->update(
      $table_name,
      array('wizWinner' => 1),
      array('templateId' => $templateId)
    );

    if ($result === false) {
      error_log("Database error while setting new winner: " . $wpdb->last_error);
      wp_send_json_error("Database error while setting new winner: " . $wpdb->last_error);
      return;
    }

  } elseif ($action == 'remove-winner') {
    error_log('Action is remove-winner');

    // Remove winner
    $result = $wpdb->update(
      $table_name,
      array('wizWinner' => null),
      array('templateId' => $templateId)
    );

    if ($result === false) {
      error_log("Database error while removing winner: " . $wpdb->last_error);
      wp_send_json_error("Database error while removing winner: " . $wpdb->last_error);
      return;
    }

  } else {
    error_log('Invalid action: ' . $action);
    wp_send_json_error('Invalid action');
    return;
  }

  error_log('Action completed successfully');
  wp_send_json_success('Action completed successfully');
}

add_action('wp_ajax_handle_experiment_winner_toggle', 'handle_experiment_winner_toggle');



add_action('wp_ajax_save_experiment_notes', 'save_experiment_notes');

function save_experiment_notes()
{
  // Security checks and validation
  if (!check_ajax_referer('wiz-metrics', 'security', false)) {
    error_log('Nonce check failed');
    wp_send_json_error('Nonce check failed');
    return;
  }

  // Get the experiment notes and ID
  $experimentId = isset($_POST['experimentId']) ? sanitize_text_field($_POST['experimentId']) : '';

  $allowed_tags = array(
    'br' => array(),
    // Add other tags if you wish to allow them
  );
  $experimentNotes = isset($_POST['experimentNotes']) ? wp_kses($_POST['experimentNotes'], $allowed_tags) : '';

  // Database update logic
  global $wpdb;
  $table_name = $wpdb->prefix . 'idemailwiz_experiments';

  // Update experimentNotes for all records with the same experiment ID
  $result = $wpdb->update(
    $table_name,
    array('experimentNotes' => $experimentNotes),
    array('experimentId' => (int) $experimentId)
  );

  if ($wpdb->last_error) {
    error_log("Database error: " . $wpdb->last_error);
    wp_send_json_error('Database error: ' . $wpdb->last_error);
    return;
  }

  if ($result !== false) {
    if ($result > 0) {
      wp_send_json_success('Data saved successfully');
    } else {
      wp_send_json_error('No data was updated, the new value may be the same as the existing value');
    }
  } else {
    wp_send_json_error('An error occurred while updating the database');
  }
}


function get_second_purchases_within_week($purchaseMonth, $purchaseMonthDay, $purchaseWindowDays, $divisions)
{
  $all_orders = get_orders_grouped_by_customers();
  $second_purchases = [];

  $specified_date = new DateTime();
  $specified_date->setDate($specified_date->format("Y"), $purchaseMonth, $purchaseMonthDay);
  $week_start = (clone $specified_date)->modify('this week');
  $purchase_window_end = (clone $week_start)->modify("+$purchaseWindowDays days");

  foreach ($all_orders as $accountNumber => $orders) {
    $qualifying_purchase_date = null;

    foreach ($orders as $order) {
      $purchase_date = new DateTime($order['purchaseDate']);

      if (
        !$qualifying_purchase_date &&
        $purchase_date->format('z') >= $specified_date->format('z') &&
        $purchase_date->format('z') <= $purchase_window_end->format('z') &&
        in_array($order['division'], $divisions)
      ) {
        $qualifying_purchase_date = $purchase_date;
        continue;
      }

      if ($qualifying_purchase_date) {
        $end_of_qualifying_year = (clone $qualifying_purchase_date)->setDate($qualifying_purchase_date->format("Y"), 12, 31);
        $days_until_end_of_year = $qualifying_purchase_date->diff($end_of_qualifying_year)->days;
        $days_from_start_of_next_year = (new DateTime($order['purchaseDate']))->format('z');
        $days_since_qualifying_order = $days_until_end_of_year + $days_from_start_of_next_year;

        $is_leap_year = ($qualifying_purchase_date->format('L') == 1 && $qualifying_purchase_date->format('m') <= 2) ? true : false;
        $days_limit = $is_leap_year ? 366 : 365;
        $days_limit = $days_limit - (int) $purchaseWindowDays;

        if ($days_since_qualifying_order <= $days_limit) {
          $order['day_of_year'] = $days_since_qualifying_order;
          $second_purchases[] = $order;
        }
      }
    }
  }

  return $second_purchases;
}







function transfigure_purchases_by_product($purchases)
{
  $data = [];
  $products = array();
  $productRevenue = array();
  $productTopics = array();

  foreach ($purchases as $purchase) {
    $product = $purchase['shoppingCartItems_name'];

    if (!isset($products[$product])) {
      $products[$product] = 0;
      $productRevenue[$product] = 0;
      $productTopics[$product] = str_replace(',', ', ', $purchase['shoppingCartItems_categories']); // Add spaces after commas
    }

    $products[$product]++;
    $productRevenue[$product] += $purchase['shoppingCartItems_price'];
  }

  // Sort products by the number of purchases in descending order
  arsort($products);

  // Prepare the data for the table
  foreach ($products as $productName => $purchaseCount) {
    $data[] = [
      'Product' => $productName,
      'Topics' => $productTopics[$productName],
      'Purchases' => $purchaseCount,
      'Revenue' => '$' . number_format($productRevenue[$productName], 2)
    ];
  }

  return $data;
}

class RawHtml
{
  private $html;

  public function __construct($html)
  {
    $this->html = $html;
  }

  public function __toString()
  {
    return $this->html;
  }
}

function get_orders_grouped_by_customers()
{
  global $wpdb;

  $batch_size = 25000; // Define a reasonable batch size. You can adjust this based on your server's capabilities.
  $offset = 0;

  $grouped_orders = [];

  while (true) {
    $query = $wpdb->prepare("SELECT accountNumber, orderId, purchaseDate, cohort_value as division FROM {$wpdb->prefix}idemailwiz_cohorts WHERE cohort_type = 'division' ORDER BY accountNumber, purchaseDate ASC LIMIT %d OFFSET %d", $batch_size, $offset);
    $results = $wpdb->get_results($query, ARRAY_A);

    // If no results, break out of the loop
    if (empty($results)) {
      break;
    }

    foreach ($results as $row) {
      $grouped_orders[$row['accountNumber']][] = $row;
    }

    $offset += $batch_size; // Increase the offset for the next batch
  }

  return $grouped_orders;
}
function get_campaigns_with_most_returning_customers($campaigns) {
    $campaignsCount = [];

    foreach ($campaigns as $campaign) {
        $campaignId = $campaign['id'];
        $purchasesForCampaign = get_idwiz_purchases(['ids' => [$campaignId], 'fields' => 'campaignId,orderId,accountNumber,purchaseDate']);
        
        $customerCounts = return_new_and_returning_customers($purchasesForCampaign);

        $campaignsCount[$campaignId] = $customerCounts['returning'];
    }

    // Sort campaigns by number of returning customers in descending order
    arsort($campaignsCount);

    return $campaignsCount;
}


function get_campaigns_by_open_rate($campaigns) {
    $openRates = [];

    foreach ($campaigns as $campaign) {
        $campaignMetrics = get_idwiz_metric($campaign['id']);
        if ($campaignMetrics['wizOpenRate'] != 0) {
        $openRates[$campaign['id']] = $campaignMetrics['wizOpenRate'];
        }
    }

    // Sort by open rate in descending order
    arsort($openRates);

    $sortedCampaigns = [];
    foreach ($openRates as $campaignId => $openRate) {
        foreach ($campaigns as $campaign) {
            if ($campaign['id'] == $campaignId) {
                $sortedCampaigns[] = $campaign;
                break;
            }
        }
    }

    return $sortedCampaigns;
}

function get_campaigns_by_ctr($campaigns) {
    $ctrs = [];

    foreach ($campaigns as $campaign) {
        $campaignMetrics = get_idwiz_metric($campaign['id']);
        $ctrs[$campaign['id']] = floatval($campaignMetrics['wizCtr']);  // Convert to float
    }

    // Sort by open rate in descending order
    arsort($ctrs);

    $sortedCampaigns = [];
    foreach ($ctrs as $campaignId => $ctr) {
        foreach ($campaigns as $campaign) {
            if ($campaign['id'] == $campaignId) {
                $sortedCampaigns[] = $campaign;
                break;
            }
        }
    }

    return $sortedCampaigns;
}

function get_campaigns_by_cto($campaigns) {
    $ctos = [];

    foreach ($campaigns as $campaign) {
        $campaignMetrics = get_idwiz_metric($campaign['id']);
        $ctos[$campaign['id']] = floatval($campaignMetrics['wizCto']);  // Convert to float
    }

    // Sort by open rate in descending order
    arsort($ctos);

    $sortedCampaigns = [];
    foreach ($ctos as $campaignId => $cto) {
        foreach ($campaigns as $campaign) {
            if ($campaign['id'] == $campaignId) {
                $sortedCampaigns[] = $campaign;
                break;
            }
        }
    }

    return $sortedCampaigns;
}



?>