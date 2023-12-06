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
    '{{{snippet "pronoun" "S"}}}' => 'he',
    '{{{snippet "pronoun" "O"}}}' => 'him',
    '{{{snippet "pronoun" "SP"}}}' => 'his',
    '{{{snippet "pronoun" "OP"}}}' => 'his',
    '{{{snippet "Pronoun" "S"}}}' => 'He',
    '{{{snippet "Pronoun" "O"}}}' => 'Him',
    '{{{snippet "Pronoun" "SP"}}}' => 'His',
    '{{{snippet "Pronoun" "OP"}}}' => 'His',
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
  $content = preg_replace(array('/<p>/', '/<\/p>/'), array('', '<br/>'), $content);
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
const CHUNK_PREVIEW_STYLE = 'font-weight:300; font-size: 12px;color: #666;';
const CHUNK_PREVIEW_IMAGE_STYLE = 'max-width: 60px;';

function id_filter_acf_chunk_title($title, $field, $layout, $i)
{
  switch ($layout['name']) {
    case 'full_width_image':
    case 'contained_image':
      $title = handleImageLayout($title, $layout);
      break;
    case 'two-column':
    case 'two-column-contained':
      $title = handleTwoColumnLayout($title, $layout);
      break;
    case 'three-column':
      $title = handleThreeColumnLayout($title);
      break;
    case 'plain_text':
      $title = handlePlainTextLayout($title, $layout);
      break;
    case 'button':
      $title = handleButtonLayout($title, $layout);
      break;
    case 'spacer':
      $title = handleSpacerLayout($title, $layout);
      break;
  }

  return $title;
}

function handleSpacerLayout($title, $layout)
{
  $spacerSettings = get_sub_field('chunk_settings');
  $spacerHeight = get_sub_field('spacer_height');
  $visibility = getChunkVisibility($spacerSettings);
  $title .= "&nbsp;&nbsp;<span style='" . CHUNK_PREVIEW_STYLE . "'>{$spacerHeight}</span><div style='float: right; padding-right: 100px;'>&nbsp;&nbsp;<span style='" . CHUNK_PREVIEW_STYLE . "'>{$visibility}</span></div>";
  return $title;
}
function handleImageLayout($title, $layout)
{
  $image_url = get_sub_field('desktop_image_url', $layout['key']);
  if (!empty($image_url)) {
    $title .= "&nbsp;&nbsp;<img style='" . CHUNK_PREVIEW_IMAGE_STYLE . "' src='{$image_url}'/>";
  }
  return $title;
}

function getChunkVisibility($chunkSettings)
{
  $mobileVisible = isset($chunkSettings['mobile_visibility']) && $chunkSettings['mobile_visibility'];
  $desktopVisible = isset($chunkSettings['desktop_visibility']) && $chunkSettings['desktop_visibility'];

  if ($mobileVisible && $desktopVisible) {
    return "<i class='fa-solid fa-desktop'></i>&nbsp;&nbsp;<i class='fa-solid fa-mobile-screen'></i>";
  } elseif ($mobileVisible) {
    return "<i class='fa-solid fa-mobile-screen'></i>";
  } elseif ($desktopVisible) {
    return "<i class='fa-solid fa-desktop active'></i>";
  } else {
    return ""; // In case both are off, though it's unlikely based on your description
  }
}



function handleTwoColumnLayout($title, $layout)
{
  $twoColSettings = get_sub_field('chunk_settings');
  if ($twoColSettings) {
    $twoColLayout = $twoColSettings['layout'];
    $visibility = getChunkVisibility($twoColSettings);

    $leftTextPreview = getPreviewText('left_text', $layout, 10);
    $rightTextPreview = getPreviewText('right_text', $layout, 10);
    $leftImageURL = getImageURL('left_image', $layout);
    $rightImageUrl = getImageURL('right_image', $layout);

    switch ($twoColLayout) {
      case 'ltr':
        $title .= "&nbsp;&nbsp;<img style='" . CHUNK_PREVIEW_IMAGE_STYLE . "' src='{$leftImageURL}'/>";
        $title .= "  <span style='" . CHUNK_PREVIEW_STYLE . "'>{$rightTextPreview}</span>";
        break;
      case 'rtl':
        $title .= "&nbsp;&nbsp;<span style='" . CHUNK_PREVIEW_STYLE . "'>{$leftTextPreview}</span>";
        $title .= "  <img style='" . CHUNK_PREVIEW_IMAGE_STYLE . "' src='{$rightImageUrl}'/>";
        break;
      case 'txt':
        $title .= "&nbsp;&nbsp;<span style='" . CHUNK_PREVIEW_STYLE . "'>{$leftTextPreview}</span>";
        $title .= "  <span style='" . CHUNK_PREVIEW_STYLE . "'>{$rightTextPreview}</span>";
        break;
      case 'img':
        $title .= "&nbsp;&nbsp;<img style='" . CHUNK_PREVIEW_IMAGE_STYLE . "' src='{$leftImageURL}'/>";
        $title .= "  <img style='" . CHUNK_PREVIEW_IMAGE_STYLE . "' src='{$rightImageUrl}'/>";
        break;
    }
    $title .= "<div style='float: right; padding-right: 100px;'>&nbsp;&nbsp;<span style='" . CHUNK_PREVIEW_STYLE . "'>{$visibility}</span></div>";
  }
  return $title;
}

function handleThreeColumnLayout($title)
{
  $threeColSettings = get_sub_field('chunk_settings');
  $visibility = getChunkVisibility($threeColSettings);
  if ($threeColSettings) {
    foreach (['left', 'middle', 'right'] as $position) {
      $content = get_sub_field("{$position}_content");
      $type = $content['content_type'];

      if ($type === 'text') {
        $textContent = $content["{$position}_text"]['text_content'];
        $previewText = getPreviewTextFromPlain($textContent, 5);
        $title .= "&nbsp;&nbsp;<span style='" . CHUNK_PREVIEW_STYLE . "'>{$previewText}</span>";
      } else {
        $imageSrc = $content["{$position}_image"]["{$position}_image_url"];
        $title .= "&nbsp;&nbsp;<img style='" . CHUNK_PREVIEW_IMAGE_STYLE . "' src='{$imageSrc}'/>";
      }
    }
  }
  $title .= "<div style='float: right; padding-right: 100px;'>&nbsp;&nbsp;<span style='" . CHUNK_PREVIEW_STYLE . "'>{$visibility}</span></div>";
  return $title;
}


function handlePlainTextLayout($title, $layout)
{
  $plainText = get_sub_field('plain_text_content', $layout['key']);
  $plainTextSettings = get_sub_field('chunk_settings');
  $visibility = getChunkVisibility($plainTextSettings);
  if (isset($plainText)) {
    $textContent = getPreviewTextFromPlain($plainText, 10);
    $title .= stripslashes("&nbsp;&nbsp;&nbsp;&nbsp;<span style='" . CHUNK_PREVIEW_STYLE . "'>{$textContent}</span>");
  }
  $title .= "<div style='float: right; padding-right: 100px;'>&nbsp;&nbsp;<span style='" . CHUNK_PREVIEW_STYLE . "'>{$visibility}</span></div>";
  return $title;
}

function handleButtonLayout($title, $layout)
{
  $buttonCTA = get_sub_field('cta_text', $layout['key']);
  $buttonSettings = get_sub_field('chunk_settings');
  $visibility = getChunkVisibility($buttonSettings);
  if ($buttonCTA) {
    $title .= "&nbsp;&nbsp;<button class='wiz-button gray' style='border-radius: 2em;'>{$buttonCTA}</button>";
  }
  $title .= "<div style='float: right; padding-right: 100px;'>&nbsp;&nbsp;<span style='" . CHUNK_PREVIEW_STYLE . "'>{$visibility}</span></div>";
  return $title;
}

function getPreviewText($fieldName, $layout, $wordCount)
{
  $content = get_sub_field($fieldName, $layout['key']);
  $text = $content['text_content'] ?? '';
  return strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($text)), 0, $wordCount))) . '...';
}

function getImageURL($fieldName, $layout)
{
  $image = get_sub_field($fieldName, $layout['key']);
  return $image[$fieldName . '_url'];
}

function getPreviewTextFromPlain($plainText, $wordCount)
{
  return strip_tags(implode(" ", array_slice(explode(" ", html_entity_decode($plainText)), 0, $wordCount))) . '...';
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
  if (!$csv_file) {
    return false;
  }
  // Read the CSV file
  $data = file_get_contents($csv_file);
  $rows = str_getcsv($data, "\n");
  $header = str_getcsv(array_shift($rows));

  // Find the min and max unique clicks for color scaling
  $min_clicks = PHP_INT_MAX;
  $max_clicks = 0;
  foreach ($rows as $row) {
    $data = array_combine($header, str_getcsv($row));
    $clicks = intval($data['uniqueCount']);
    $min_clicks = min($min_clicks, $clicks);
    $max_clicks = max($max_clicks, $clicks);
  }

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
    $unique_clicks = intval($data['uniqueCount']);
    $unique_click_rate = number_format($data['uniqueClickRate'] * 100, 2) . '%';
    $url = $data['url'];

    // Map the unique clicks to a color from yellow to red
    $color = map_clicks_to_color($unique_clicks, $min_clicks, $max_clicks);

    // Create a heatmap point with unique clicks displayed
    $overlay_html .= "<div class=\"heatmap-point\" style=\"left: {$x}px; top: {$y}px; background-color: {$color};\" data-unique-click-rate =\"{$unique_click_rate}\" data-unique-clicks=\"{$unique_clicks}\" data-url=\"{$url}\">{$unique_clicks}</div>";
  }

  // Close the heatmap overlay div
  $overlay_html .= '</div>';

  return $overlay_html;
}

function map_clicks_to_color($clicks, $min_clicks, $max_clicks)
{
  // Normalize clicks value between 0 and 1
  $normalized = ($clicks - $min_clicks) / ($max_clicks - $min_clicks);

  // Convert normalized value to a color on the yellow-to-red gradient
  $red = 255;
  $green = 255 - $normalized * 255; // Decrease green to move from yellow to red
  $blue = 0;

  return sprintf("#%02x%02x%02x", $red, $green, $blue);
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
  if (empty($data)) {
    echo 'No data available';
  } else {
    // Table with sticky header
    echo '<table class="wizcampaign-tiny-table ' . $tableClass . '">';
    echo '<thead><tr>';
    foreach ($headers as $col => $width) {
      echo '<th width="' . $width . '">' . $col . '</th>';
    }
    echo '</tr></thead>';

    echo '<tbody>';


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

function idwiz_get_orders_from_purchases($purchases)
{
  $orders = [];
  foreach ($purchases as $purchase) {
    if (isset($orders[$purchase['orderId']])) {
      $orders[$purchase['orderId']][] = $purchase;
    } else {
      $orders[$purchase['orderId']] = [$purchase];
    }
  }

  return $orders;
}

function get_idwiz_revenue($startDate, $endDate, $campaignTypes = ['Triggered', 'Blast'], $wizCampaignIds = null, $useGa = false)
{


  if (!is_array($wizCampaignIds) || empty($wizCampaignIds)) {
    $checkCampaignArgs = ['type' => $campaignTypes, 'fields' => 'id'];
    $wizCampaigns = get_idwiz_campaigns($checkCampaignArgs);
    $wizCampaignIds = array_column($wizCampaigns, 'id');
  }

  $totalRevenue = 0;

  if ($useGa) {
    $allChannelPurchases = get_idwiz_ga_data(['startDate' => $startDate, 'endDate' => $endDate]);
    $purchases = array_filter($allChannelPurchases, fn($purchase) => in_array($purchase['campaignId'], $wizCampaignIds));
    if (!$purchases) {
      return 0;
    }
    $revenue = array_sum(array_column($purchases, 'revenue'));
  } else {
    $purchaseArgs = ['startAt_start' => $startDate, 'startAt_end' => $endDate, 'campaignIds' => $wizCampaignIds, 'fields' => 'id,campaignId,purchaseDate,total'];
    $purchases = get_idwiz_purchases($purchaseArgs);

    if (!$purchases) {
      return 0;
    }

    $uniqueIds = [];

    $revenue = 0;
    //error_log(print_r($purchases, true));

    foreach ($purchases as $purchase) {
      if (in_array($purchase['id'], $uniqueIds)) {
        continue;
      }

      if (!isset($purchase['campaignId'])) {
        continue;
      }

      $wizCampaign = get_idwiz_campaign($purchase['campaignId']);

      if (!$wizCampaign) {
        continue;
      }

      if (isset($campaignTypes) && !in_array($wizCampaign['type'], $campaignTypes)) {
        continue;
      }

      $revenue += $purchase['total'];
      $uniqueIds[] = $purchase['id'];
    }
  }

  $totalRevenue += $revenue;

  return $totalRevenue;
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

  global $wpdb;
  $table_name = $wpdb->prefix . 'idemailwiz_experiments';


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




function get_idwiz_metric_rates($campaignIds = [], $startDate = null, $endDate = null, $campaignTypes = ['Blast', 'Triggered'], $purchaseMode = 'campaignsInDate')
{

  $startDate = $startDate ? $startDate : '2021-11-01';
  $endDate = $endDate ? $endDate : date('Y-m-d');


  // Determine campaign IDs for Blast and Triggered campaigns
  if (empty($campaignIds)) {
    $blastCampaigns = get_idwiz_campaigns(['type' => 'Blast', 'fields' => 'id', 'startAt_start' => $startDate, 'startAt_end' => $endDate]);
    $blastCampaignIds = array_column($blastCampaigns, 'id');

    $triggeredCampaigns = get_idwiz_campaigns(['type' => 'Triggered', 'fields' => 'id']);
    $triggeredCampaignIds = array_column($triggeredCampaigns, 'id');

    $allIncludedIds = array_merge($blastCampaignIds, $triggeredCampaignIds);
  } else {
    $blastCampaignIds = $triggeredCampaignIds = $allIncludedIds = [];
    foreach ($campaignIds as $campaignId) {
      $campaign = get_idwiz_campaign($campaignId);
      if ($campaign['type'] == 'Blast') {
        $blastCampaignIds[] = $campaignId;
      } else {
        $triggeredCampaignIds[] = $campaignId;
      }
      $allIncludedIds[] = $campaignId;
    }
  }
  // Retrieve metrics for Blast and (optional) Triggered campaigns
  $blastMetrics = in_array('Blast', $campaignTypes) && !empty($blastCampaignIds) ? get_idwiz_metrics(['campaignIds' => $blastCampaignIds]) : [];
  $triggeredMetrics = in_array('Triggered', $campaignTypes) && !empty($triggeredCampaignIds) ? get_triggered_campaign_metrics($triggeredCampaignIds, $startDate, $endDate) : [];
  $purchaseArgs = [];


  $purchaseArgs = [
    'startAt_start' => $startDate,
    'startAt_end' => $endDate,
    'fields' => 'accountNumber,OrderId' // limit fields for faster query
  ];

  // Set attribution mode
  $currentUser = wp_get_current_user();
  $currentUserId = $currentUser->ID;
  $userAttMode = get_user_meta($currentUserId, 'purchase_attribution_mode', true);
  // default mode is campaign-id, which gets no extra parameters here
  if ($userAttMode == 'broad-channel-match') {
    $purchaseArgs['shoppingCartItems_utmMedium'] = ['email', ''];
  } elseif ($userAttMode == 'email-channel-match') {
    $purchaseArgs['shoppingCartItems_utmMedium'] = ['email'];
  }

  // If the mode is set to getting purchases only for specific campaigns, we pass the campaignIds
  if ($purchaseMode == 'campaignsInDate') {
    if (in_array('Triggered', $campaignTypes)) {
      $purchaseArgs['campaignIds'] = $allIncludedIds;
      $purchaseArgs['campaignIds'] = $allIncludedIds;
    } else {
      $purchaseArgs['campaignIds'] = $blastCampaignIds;
    }
  } else {
    // If the mode is set to getting purchases between dates (without regard to campaign) we don't pass campaignIds
  }


  $purchases = get_idwiz_purchases($purchaseArgs);

  //$uniquePurchasers = array_unique(array_column($purchases, 'accountNumber'));
  //$totalOrders = array_unique(array_column($purchases, 'OrderId'));

  // Initialize variables for summable metrics
  $totalSends = $totalOpens = $totalClicks = $totalUnsubscribes = $totalDeliveries = $totalPurchases = $totalComplaints = $totalRevenue = 0;

  $totalPurchases = is_array($purchases) ? count($purchases) : 0;

  $purchaseCampaigns = $purchaseArgs['campaignIds'] ?? null;
  $totalRevenue = get_idwiz_revenue($startDate, $endDate, $campaignTypes, $purchaseCampaigns);

  $gaRevenue = get_idwiz_revenue($startDate, $endDate, $campaignTypes, $purchaseCampaigns, true);


  // Process Blast metrics
  foreach ($blastMetrics as $blastMetricSet) {
    $totalSends += $blastMetricSet['uniqueEmailSends'] ?? 0;
    $totalOpens += $blastMetricSet['uniqueEmailOpens'] ?? 0;
    $totalClicks += $blastMetricSet['uniqueEmailClicks'] ?? 0;
    $totalUnsubscribes += $blastMetricSet['uniqueUnsubscribes'] ?? 0;
    $totalComplaints += $blastMetricSet['totalComplaints'] ?? 0;
    $totalDeliveries += $blastMetricSet['uniqueEmailsDelivered'] ?? 0;
    //$totalPurchases += $blastMetricSet['uniquePurchases'] ?? 0;
  }

  // Add Triggered campaign metrics, if applicable
  if (!empty($triggeredMetrics)) {
    $totalSends += $triggeredMetrics['uniqueEmailSends'] ?? 0;
    $totalOpens += $triggeredMetrics['uniqueEmailOpens'] ?? 0;
    $totalClicks += $triggeredMetrics['uniqueEmailClicks'] ?? 0;
    $totalUnsubscribes += $triggeredMetrics['uniqueUnsubscribes'] ?? 0;
    $totalComplaints += $triggeredMetrics['totalComplaints'] ?? 0;
    $totalDeliveries += $triggeredMetrics['uniqueEmailsDelivered'] ?? 0;
    //$totalPurchases += $triggeredMetrics['uniquePurchases'] ?? 0;
  }

  // Calculate and return all metrics
  return [
    'uniqueEmailSends' => $totalSends,
    'uniqueEmailOpens' => $totalOpens,
    'uniqueEmailClicks' => $totalClicks,
    'uniqueUnsubscribes' => $totalUnsubscribes,
    'totalComplaints' => $totalUnsubscribes,
    'uniqueEmailsDelivered' => $totalDeliveries,
    'uniquePurchases' => $totalPurchases,
    'wizDeliveryRate' => ($totalSends > 0) ? ($totalDeliveries / $totalSends) * 100 : 0,
    'wizOpenRate' => ($totalOpens > 0) ? ($totalOpens / $totalSends) * 100 : 0,
    'wizCtr' => ($totalClicks > 0) ? ($totalClicks / $totalSends) * 100 : 0,
    'wizCto' => ($totalOpens > 0) ? ($totalClicks / $totalOpens) * 100 : 0,
    'wizCvr' => ($totalPurchases > 0 && $totalDeliveries > 0) ? ($totalPurchases / $totalDeliveries) * 100 : 0,
    'wizAov' => ($totalPurchases > 0 && $totalRevenue > 0) ? ($totalRevenue / $totalPurchases) : 0,
    'wizUnsubRate' => ($totalSends > 0) ? ($totalUnsubscribes / $totalSends) * 100 : 0,
    'wizCompRate' => ($totalSends > 0) ? ($totalComplaints / $totalSends) * 100 : 0,
    'revenue' => $totalRevenue,
    'gaRevenue' => $gaRevenue
  ];
}

function get_triggered_campaign_metrics($campaignIds = [], $startDate = null, $endDate = null)
{
  if (!$startDate) {
    $startDate = '2021-11-01';
  }
  if (!$endDate) {
    $endDate = date('Y-m-d');
  }

  $purchasesOptions = [
    'startAt_start' => $startDate,
    'startAt_end' => $endDate,
  ];

  if (!empty($campaignIds)) {
    $purchasesOptions['campaignIds'] = $campaignIds;
  }
  $allPurchases = get_idwiz_purchases($purchasesOptions);

  // Prepare arguments for triggered database queries
  $campaignDataArgs = [
    'campaignIds' => $campaignIds,
    'startAt_start' => $startDate,
    'startAt_end' => $endDate,
    'fields' => 'campaignId' //just getting one field since all we're doing is counting values
  ];

  // Fetch base data for each metric and map it to the same structure as the Blast campaigns
  $metrics = [];
  $databases = [
    'uniqueEmailSends' => 'idemailwiz_triggered_sends',
    'uniqueEmailOpens' => 'idemailwiz_triggered_opens',
    'uniqueEmailClicks' => 'idemailwiz_triggered_clicks',
    'uniqueUnsubscribes' => 'idemailwiz_triggered_unsubscribes',
    'totalComplaints' => 'idemailwiz_triggered_complaints',
    'emailSendSkips' => 'idemailwiz_triggered_sendskips',
    'emailBounces' => 'idemailwiz_triggered_bounces'
  ];

  foreach ($databases as $metricKey => $database) {
    $transient_key = 'count_of_' . $database;

    // Try to get the metric count from transient
    $metric_count = get_transient($transient_key);

    if ($metric_count === false) {
      // Transient expired or not set, fetch the data
      $metric_data = get_idemailwiz_triggered_data($database, $campaignDataArgs);
      $metric_count = count($metric_data);

      // Set the transient with a staggered expiration time
      set_transient($transient_key, $metric_count, HOUR_IN_SECONDS + rand(0, 1800)); // Staggered expiry between 1 to 1.5 hours
    }

    $metrics[$metricKey] = $metric_count;
  }

  $metrics['uniqueEmailsDelivered'] = $metrics['uniqueEmailSends'] - $metrics['emailSendSkips'] - $metrics['emailBounces'];

  // Calculate rate metrics
  $metrics['wizDeliveryRate'] = $metrics['uniqueEmailsDelivered'] > 0 ? ($metrics['uniqueEmailsDelivered'] / $metrics['uniqueEmailSends']) * 100 : 'N/A';
  $metrics['wizOpenRate'] = $metrics['uniqueEmailOpens'] > 0 ? ($metrics['uniqueEmailOpens'] / $metrics['uniqueEmailSends']) * 100 : 'N/A';
  $metrics['wizCtr'] = $metrics['uniqueEmailClicks'] > 0 ? ($metrics['uniqueEmailClicks'] / $metrics['uniqueEmailSends']) * 100 : 'N/A';
  $metrics['wizCto'] = $metrics['uniqueEmailClicks'] > 0 && $metrics['uniqueEmailOpens'] > 0 ? ($metrics['uniqueEmailClicks'] / $metrics['uniqueEmailOpens']) * 100 : 'N/A';
  $metrics['wizUnsubRate'] = $metrics['uniqueUnsubscribes'] > 0 && $metrics['uniqueEmailSends'] > 0 ? ($metrics['uniqueUnsubscribes'] / $metrics['uniqueEmailSends']) * 100 : 'N/A';

  $metrics['uniquePurchases'] = count($allPurchases);
  $metrics['revenue'] = array_sum(array_column($allPurchases, 'total'));

  $metrics['gaRevenue'] = get_idwiz_revenue($startDate, $endDate, ['Triggered'], null, true);

  $metrics['wizCvr'] = $metrics['uniquePurchases'] > 0 && $metrics['uniqueEmailsDelivered'] > 0 ? $metrics['uniquePurchases'] / $metrics['uniqueEmailsDelivered'] * 100 : 0;
  $metrics['wizAov'] = $metrics['revenue'] > 0 && $metrics['uniquePurchases'] > 0 ? $metrics['revenue'] / $metrics['uniquePurchases'] : 0;

  return $metrics;
}


function parse_idwiz_metric_rate($rate)
{
  return floatval(str_replace(['%', ',', '$'], '', $rate));
}

function formatRollupMetric($value, $format, $includeDifSign = false)
{
  $formattedValue = '';
  $sign = ($value >= 0) ? '+' : '-';

  switch ($format) {
    case 'money':
      $formattedValue = ($includeDifSign ? $sign : '') . '$' . number_format(abs($value), 0);
      break;
    case 'perc':
      $formattedValue = ($includeDifSign ? $sign : '') . number_format(abs($value), 2) . '%';
      break;
    case 'num':
      $formattedValue = ($includeDifSign ? $sign : '') . number_format(abs($value), 0);
      break;
    default:
      $formattedValue = $value;
  }

  return $formattedValue;
}

function idwiz_generate_dynamic_rollup()
{

  if (isset($_POST['campaignIds'])) {
    $startDate = isset($_POST['startdate']) ? $_POST['startDate'] : '2021-11-01';
    $endDate = isset($_POST['endDate']) ? $_POST['endDate'] : date('Y-m-d');
    $metricRates = get_idwiz_metric_rates($_POST['campaignIds'], $startDate, $endDate);

    echo get_idwiz_rollup_row($metricRates); //include/exclude metrics here if needed with 2nd and 3rd argument
  }
  wp_die();
}

add_action('wp_ajax_idwiz_generate_dynamic_rollup', 'idwiz_generate_dynamic_rollup');


function idemailwiz_update_user_attribution_setting()
{

  if (!check_ajax_referer('id-general', 'security', false)) {
    error_log('Nonce check failed');
    wp_send_json_error('Nonce check failed');
    return;
  }

  $field = $_POST['field'] ?? null;
  $newValue = $_POST['value'] ?? null;

  $currentUser = wp_get_current_user();
  $currentUserId = $currentUser->ID;
  if ($field && $newValue) {
    $updateAttribution = update_user_meta($currentUserId, $field, $newValue);
  }

  wp_send_json_success($updateAttribution);
  wp_die();
}

add_action('wp_ajax_idemailwiz_update_user_attribution_setting', 'idemailwiz_update_user_attribution_setting');





function get_idwiz_rollup_row($metricRates, $include = [], $exclude = [])
{
  $defaultRollupFields = array(
    'uniqueEmailSends' => array(
      'label' => 'Sends',
      'format' => 'num',
      'value' => $metricRates['uniqueEmailSends']
    ),
    'uniqueEmailsDelivered' => array(
      'label' => 'Delivered',
      'format' => 'num',
      'value' => $metricRates['uniqueEmailsDelivered']
    ),
    'wizDeliveryRate' => array(
      'label' => 'Delivery',
      'format' => 'perc',
      'value' => $metricRates['wizDeliveryRate']
    ),
    'uniqueEmailOpens' => array(
      'label' => 'Opens',
      'format' => 'num',
      'value' => $metricRates['uniqueEmailOpens']
    ),
    'wizOpenRate' => array(
      'label' => 'Open Rate',
      'format' => 'perc',
      'value' => $metricRates['wizOpenRate']
    ),
    'uniqueEmailClicks' => array(
      'label' => 'Clicks',
      'format' => 'num',
      'value' => $metricRates['uniqueEmailClicks']
    ),
    'wizCtr' => array(
      'label' => 'CTR',
      'format' => 'perc',
      'value' => $metricRates['wizCtr']
    ),
    'wizCto' => array(
      'label' => 'CTO',
      'format' => 'perc',
      'value' => $metricRates['wizCto']
    ),
    'uniquePurchases' => array(
      'label' => 'Purch.',
      'format' => 'num',
      'value' => $metricRates['uniquePurchases']
    ),
    'revenue' => array(
      'label' => 'Dir. Rev.',
      'format' => 'money',
      'value' => $metricRates['revenue']
    ),
    'gaRevenue' => array(
      'label' => 'GA Rev.',
      'format' => 'money',
      'value' => $metricRates['gaRevenue']
    ),
    'wizCvr' => array(
      'label' => 'CVR',
      'format' => 'perc',
      'value' => $metricRates['wizCvr']
    ),
    'wizAov' => array(
      'label' => 'AOV',
      'format' => 'money',
      'value' => $metricRates['wizAov']
    ),
    'uniqueUnsubscribes' => array(
      'label' => 'Unsubs',
      'format' => 'num',
      'value' => $metricRates['uniqueUnsubscribes']
    ),
    'wizUnsubRate' => array(
      'label' => 'Unsub. Rate',
      'format' => 'perc',
      'value' => $metricRates['wizUnsubRate']
    ),
    'totalComplaints' => array(
      'label' => 'Comp.',
      'format' => 'num',
      'value' => $metricRates['totalComplaints']
    ),
    'wizCompRate' => array(
      'label' => 'Comp. Rate',
      'format' => 'perc',
      'value' => $metricRates['wizCompRate']
    ),
  );

  $rollupFields = $defaultRollupFields;

  if (!empty($include)) {
    //include and exclude shouldn't be used together, so we let include take precedence
    foreach ($defaultRollupFields as $rollupField) {
      if (isset($rollupFields[$include])) {
        $rollupFields[] = $rollupField;
      }
    }
  } else if (!empty($exclude)) {
    foreach ($defaultRollupFields as $rollupField) {
      if (!isset($exclude[$rollupField])) {
        $rollupFields[] = $rollupField;
      }
    }
  }

  $html = '';
  $html .= '<div class="rollup_summary_wrapper">';
  foreach ($rollupFields as $metric) {
    $formattedValue = formatRollupMetric($metric['value'], $metric['format']);
    $html .= '<div class="metric-item">';
    $html .= "<span class='metric-label'>{$metric['label']}</span>";
    $html .= "<span class='metric-value'>{$formattedValue}</span>";
    $html .= '</div>'; // End of metric-item
  }
  $html .= '</div>';

  return $html;
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
function group_first_and_repeat_purchases($purchases)
{
  $ordersGroupedByOrderId = [];
  foreach ($purchases as $purchase) {
    $orderId = $purchase['orderId'];
    if (!isset($ordersGroupedByOrderId[$orderId])) {
      $ordersGroupedByOrderId[$orderId] = [];
    }
    $ordersGroupedByOrderId[$orderId][] = $purchase;
  }

  $newOrdersCount = 0;
  $returningOrdersCount = 0;
  $processedCustomers = [];

  foreach ($ordersGroupedByOrderId as $orderId => $orderPurchases) {
    $firstPurchase = reset($orderPurchases);
    $accountId = $firstPurchase['accountNumber'];

    if (in_array($accountId, $processedCustomers)) {
      continue;
    }

    $processedCustomers[] = $accountId;

    $allCustomerPurchases = get_idwiz_purchases([
      'fields' => 'accountNumber, orderId, purchaseDate',
      'accountNumber' => $accountId
    ]);

    usort($allCustomerPurchases, function ($a, $b) {
      return strcmp($a['purchaseDate'], $b['purchaseDate']);
    });

    $firstPurchaseDate = $allCustomerPurchases[0]['purchaseDate'];

    if ($firstPurchase['purchaseDate'] == $firstPurchaseDate) {
      $newOrdersCount++;
    } else {
      $returningOrdersCount++;
    }
  }

  return [
    'groupedPurchases' => $ordersGroupedByOrderId,
    'counts' => [
      'new' => $newOrdersCount,
      'returning' => $returningOrdersCount
    ]
  ];
}

function return_new_and_returning_customers($purchases)
{
  $results = group_first_and_repeat_purchases($purchases);
  return $results['counts'];
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
function get_campaigns_with_most_returning_customers($campaigns)
{
  $campaignsCount = [];

  foreach ($campaigns as $campaign) {
    $campaignId = $campaign['id'];
    $purchasesForCampaign = get_idwiz_purchases(['campaignIds' => [$campaignId], 'fields' => 'campaignId,orderId,accountNumber,purchaseDate']);

    $customerCounts = return_new_and_returning_customers($purchasesForCampaign);

    $campaignsCount[$campaignId] = $customerCounts['returning'];
  }

  // Sort campaigns by number of returning customers in descending order
  arsort($campaignsCount);

  return $campaignsCount;
}


function get_campaigns_by_open_rate($campaigns)
{
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

function get_campaigns_by_ctr($campaigns)
{
  $ctrs = [];

  foreach ($campaigns as $campaign) {
    $campaignMetrics = get_idwiz_metric($campaign['id']);
    $ctrs[$campaign['id']] = floatval($campaignMetrics['wizCtr']); // Convert to float
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

function get_campaigns_by_cto($campaigns)
{
  $ctos = [];

  foreach ($campaigns as $campaign) {
    $campaignMetrics = get_idwiz_metric($campaign['id']);
    $ctos[$campaign['id']] = floatval($campaignMetrics['wizCto']); // Convert to float
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

function wiz_truncate_string($string, $length)
{
  if (strlen($string) > $length) {
    return substr($string, 0, $length - 3) . '...';
  }
  return $string;
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


function wiz_notifications()
{
  // Insert notifications wrapper into the footer
  echo '<div class="wizNotifs" aria-live="assertive" aria-atomic="true"></div>';
}
add_action('wp_footer', 'wiz_notifications');



if (!function_exists('wfu_after_file_loaded_handler')) {
  /** Function syntax
   *  The function takes two parameters, $changable_data and $additional_data.
   *  - $changable_data is an array that can be modified by the filter and
   *    contains the items:
   *    > error_message: initially it is set to an empty value, if the handler
   *      sets a non-empty value then upload of the file will be cancelled
   *      showing this error message
   *    > admin_message: initially it is set to an empty value, if the handler
   *      sets a non-empty value then this value will be shown to
   *      administrators if adminmessages attribute has been activated,
   *      provided that error_message is also set. You can use it to display
   *      more information about the error, visible only to admins.
   *  - $additional_data is an array with additional data to be used by the
   *    filter (but cannot be modified) as follows:
   *    > file_unique_id: this id is unique for each individual file upload
   *      and can be used to identify each separate upload
   *    > file_path: the full path of the uploaded file
   *    > shortcode_id: this is the id of the plugin, as set using uploadid
   *      attribute; it can be used to apply this filter only to a specific
   *      instance of the plugin (if it is used in more than one pages or
   *      posts)
   *  The function must return the final $changable_data. */
  function wfu_after_file_loaded_handler($changable_data, $additional_data)
  {
    global $wpdb;
    $templateId = $additional_data['shortcode_id'];

    $filePath = $additional_data['file_path'];

    $wpdb->query(
      $wpdb->prepare(
        "UPDATE {$wpdb->prefix}idemailwiz_templates SET heatmapFile = %s WHERE templateId = %d",
        $filePath,
        $templateId
      )
    );

    return $changable_data;
  }
  add_filter('wfu_after_file_loaded', 'wfu_after_file_loaded_handler', 10, 2);
}


/*
  This filter is executed after the upload process for each individual file has
  finished, in order to allow additional tasks to be executed and define custom
  javascript code to run in clientâ  s browser. 
*/
if (!function_exists('wfu_after_file_upload_handler')) {
  /** Function syntax
   *  The function takes two parameters, $changable_data and $additional_data.
   *  - $changable_data is an array that can be modified by the filter and
   *    contains the items:
   *    > ret_value: not used for the moment, it exists for future additions
   *    > js_script: javascript code to be executed on the client's browser
   *      after each file is uploaded
   *  - $additional_data is an array with additional data to be used by the
   *    filter (but cannot be modified) as follows:
   *    > shortcode_id: this is the id of the plugin, as set using uploadid
   *      attribute; it can be used to apply this filter only to a specific
   *      instance of the plugin (if it is used in more than one pages or
   *      posts)
   *    > file_unique_id: this id is unique for each individual file upload
   *      and can be used to identify each separate upload
   *    > upload_result: it is the result of the upload process, taking the
   *      following values:
   *        success: the upload was successful
   *        warning: the upload was successful but with warning messages
   *        error: the upload failed
   *    > error_message: contains warning or error messages generated during
   *      the upload process
   *    > admin_messages: contains detailed error messages for administrators
   *      generated during the upload process
   *  The function must return the final $changable_data. */
  function wfu_after_file_upload_handler($changable_data, $additional_data)
  {
    $changable_data['js_script'] = "location.reload()";
    return $changable_data;
  }
  add_filter('wfu_after_file_upload', 'wfu_after_file_upload_handler', 10, 2);
}

/*
  This filter runs right before the uploaded file starts to be uploaded in order
  to make modifications of its filename.
*/
if (!function_exists('wfu_before_file_upload_handler')) {
  /** Function syntax
   *  The function takes two parameters, $file_path and $file_unique_id.
   *  - $file_path is the filename of the uploaded file (after all internal
   *    checks have been applied) and can be modified by the filter.
   *  - $file_unique_id is is unique for each individual file upload and can
   *    be used to identify each separate upload.
   *  The function must return the final $file_path.
   *  If additional data are required (such as user id or userdata) you can
   *  get them by implementing the previous filter wfu_before_file_check and
   *  link both filters by $file_unique_id parameter. Please note that no
   *  filename validity checks will be performed after the filter. The filter
   *  must ensure that filename is valid. */
  function wfu_before_file_upload_handler($file_path, $file_unique_id)
  {
    // Extract the directory part of the file path
    $directory = dirname($file_path);

    // Create the new file name
    $new_file_name = "heatmap_" . $file_unique_id . "_" . time() . ".csv";

    // Concatenate the directory with the new file name
    $new_file_path = $directory . DIRECTORY_SEPARATOR . $new_file_name;

    return $new_file_path;
  }

  add_filter('wfu_before_file_upload', 'wfu_before_file_upload_handler', 10, 2);
}

add_action('wp_ajax_idemailwiz_remove_heatmap', 'idemailwiz_remove_heatmap');
function idemailwiz_remove_heatmap()
{
  global $wpdb;
  $templateId = $_POST['templateId'];
  $wpdb->query(
    $wpdb->prepare(
      "UPDATE {$wpdb->prefix}idemailwiz_templates SET heatmapFile = NULL WHERE templateId = %d",
      $templateId
    )
  );
  wp_send_json_success(true);
}

