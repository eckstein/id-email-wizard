<?php
function idemailwiz_create_new_initiative() {
    // Check for nonce and security
    if (!check_ajax_referer('initiatives', 'security', false)) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    // Fetch title from POST
    $title = $_POST['newInitTitle'];

    // Validate that the title is not empty
    if (empty($title)) {
        wp_send_json_error(array('message' => 'The title cannot be empty'));
        return;
    }

    // Create new initiative post
    $post_id = wp_insert_post(array(
        'post_title'    => $title,
        'post_type'     => 'idwiz_initiative',
        'post_status'   => 'publish',
    ));

    if ($post_id > 0) {
        wp_send_json_success(array('message' => 'Initiative created successfully', 'post_id' => $post_id));
    } else {
        wp_send_json_error(array('message' => 'Failed to create the initiative'));
    }
}
add_action('wp_ajax_idemailwiz_create_new_initiative', 'idemailwiz_create_new_initiative');

function idemailwiz_delete_initiative() {
    // Check for nonce and security
    if (!check_ajax_referer('initiatives', 'security', false)) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    // Fetch selected IDs from POST
    $selectedIds = $_POST['selectedIds'];

    foreach ($selectedIds as $post_id) {
        wp_delete_post($post_id, true);  // Set second parameter to false if you don't want to force delete
    }

    wp_send_json_success(array('message' => 'Initiatives deleted successfully'));
}
add_action('wp_ajax_idemailwiz_delete_initiative', 'idemailwiz_delete_initiative');


// Save initiative title when edited on the front end
function idemailwiz_save_initiative_update() {
    // Check for nonce and security
    if (!check_ajax_referer('initiatives', 'security', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Fetch data from POST
    $initiative = $_POST['initID'];
    $updateType = $_POST['updateType'];

    $updateContent = $_POST['updateContent'];

    // Validate that the new title is not empty
    if (empty($updateContent)) {
        wp_send_json_error('The title/content cannot be empty');
        return;
    }

    // Start the post data array with the ID
    $post_data = array(
        'ID' => $initiative,
    );
    if ($updateType == 'title') {
        $post_data['post_title'] = $updateContent;
    }

    if ($updateType == 'content') {
        $post_data['post_content'] = $updateContent;
    }

    $update_status = wp_update_post($post_data);

    // Check if the update was successful
    if ($update_status > 0) {
        wp_send_json_success('Initiative updated successfully');
    } else {
        wp_send_json_error('Failed to update the initiative');
    }
}
add_action('wp_ajax_idemailwiz_save_initiative_update', 'idemailwiz_save_initiative_update');

function idemailwiz_get_campaigns_for_select() {
  check_ajax_referer('initiatives', 'security');

  $all_campaigns = get_idwiz_campaigns(array('sortBy'=>'startAt', 'sort'=>'DESC'));
  $search = isset($_POST['q']) ? $_POST['q'] : '';
  $exclude_ids = isset($_POST['exclude']) ? $_POST['exclude'] : array(); // Get the exclude parameter

$filtered_campaigns = array_filter($all_campaigns, function($campaign) use ($search, $exclude_ids) {
    return ($search === '' || strpos(strtolower(trim($campaign['name'])), strtolower(trim($search))) !== false)
        && !in_array($campaign['id'], $exclude_ids); // Exclude campaigns with specified IDs
});


  $data = array_map(function($campaign) {
    return array('id' => $campaign['id'], 'text' => $campaign['name']);
  }, $filtered_campaigns);

  echo json_encode(array_values($data));
  wp_die();
}
add_action('wp_ajax_idemailwiz_get_campaigns_for_select', 'idemailwiz_get_campaigns_for_select');


function get_idwiz_initiative_daterange($initiative_post_id) {
    $campaign_ids = idemailwiz_get_campaign_ids_for_initiative($initiative_post_id);

    if (empty($campaign_ids)) {
        $return['error'] = 'No campaigns found!';
        return $return;
    }

    $campaigns = get_idwiz_campaigns(array('ids' => $campaign_ids));
    $start_dates = array_column($campaigns, 'startAt');
    $min_start_date = min($start_dates);
    $max_start_date = max($start_dates);

    $return['startDate'] = date('m/d/Y', $min_start_date / 1000);
    $return['endDate'] = date('m/d/Y', $max_start_date / 1000);

    return $return;
}


// Utility function to add a new campaign-initiative relationship to the database
function idemailwiz_add_campaign_initiative_relationship($campaignID, $initiativeID) {
    global $wpdb;
    $table_name = $wpdb->prefix . "idemailwiz_init_campaigns";
    return $wpdb->insert($table_name, ['campaignId' => $campaignID, 'initiativeId' => $initiativeID], ['%d', '%d']);
}

// Utility function to remove a campaign-initiative relationship from the database
function idemailwiz_remove_campaign_initiative_relationship($campaignID, $initiativeID) {
    global $wpdb;
    $table_name = $wpdb->prefix . "idemailwiz_init_campaigns";
    return $wpdb->delete($table_name, ['campaignId' => $campaignID, 'initiativeId' => $initiativeID], ['%d', '%d']);
}

// Utility function to check if a campaign-initiative relationship exists
function idemailwiz_check_campaign_initiative_relationship($campaignID, $initiativeID) {
    global $wpdb;
    $table_name = $wpdb->prefix . "idemailwiz_init_campaigns";
    return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE campaignId = %d AND initiativeId = %d", $campaignID, $initiativeID)) > 0;
}

// Utility function to get all campaign IDs for a given initiative ID
function idemailwiz_get_campaign_ids_for_initiative($initiativeID) {
    global $wpdb;
    $table_name = $wpdb->prefix . "idemailwiz_init_campaigns";
    return $wpdb->get_col($wpdb->prepare("SELECT campaignId FROM $table_name WHERE initiativeId = %d", $initiativeID));
}

// Utility function to get the initiative ID for a given campaign ID
function idemailwiz_get_initiative_id_for_campaign($campaignID) {
    global $wpdb;
    $table_name = $wpdb->prefix . "idemailwiz_init_campaigns";
    return $wpdb->get_var($wpdb->prepare("SELECT initiativeId FROM $table_name WHERE campaignId = %d", $campaignID));
}


// Main function to handle adding or removing a campaign to/from an initiative
function idemailwiz_manage_campaign_initiative_relationship($action, $campaignID, $initiativeID) {
    $response = [
        'success' => false,
        'message' => ''
    ];

    $relationship_exists = idemailwiz_check_campaign_initiative_relationship($campaignID, $initiativeID);

    if ($action === 'add') {
        if (!$relationship_exists) {
            if (idemailwiz_add_campaign_initiative_relationship($campaignID, $initiativeID)) {
                $response['success'] = true;
                $response['message'] = 'Successfully added campaign to initiative.';
                // Update the image meta field
                idwiz_save_campaign_assets_to_initiative($initiativeID);
            } else {
                $response['message'] = 'Failed to add campaign to initiative.';
            }
        } else {
            $response['message'] = 'This campaign has already been added to the initiative!';
        }
    } elseif ($action === 'remove') {
        if ($relationship_exists) {
            if (idemailwiz_remove_campaign_initiative_relationship($campaignID, $initiativeID)) {
                $response['success'] = true;
                $response['message'] = 'Successfully removed campaign from initiative.';
                // Update the image meta field
                idwiz_save_campaign_assets_to_initiative($initiativeID);
            } else {
                $response['message'] = 'Failed to remove campaign from initiative.';
            }
        } else {
            $response['message'] = 'This campaign is not part of the initiative.';
        }
    }

    return $response;
}



function idemailwiz_add_remove_campaign_from_initiative() {
    global $wpdb;
    $response = [
        'success' => true,
        'message' => '',
        'data' => []
    ];

    $isValidNonce = check_ajax_referer('data-tables', 'security', false) || check_ajax_referer('initiatives', 'security', false);
    if (!$isValidNonce) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
        return;
    }

    $campaignIDs = $_POST['campaign_ids'];
    if (!is_array($campaignIDs)) {
        $campaignIDs = [$campaignIDs];
    }
    $initiativeID = intval($_POST['initiative_id']);
    $action = $_POST['campaignAction'];

    if ($action != 'add' && $action != 'remove') {
        wp_send_json_error(['message' => 'Invalid action.']);
        return;
    }

    $messages = [];
    $successCount = 0;

    foreach ($campaignIDs as $campaignID) {
        $result = idemailwiz_manage_campaign_initiative_relationship($action, $campaignID, $initiativeID);
        if ($result['success']) {
            $successCount++;
        }
        $messages[] = $result['message'];
    }

    if ($successCount == count($campaignIDs)) {
        $response['message'] = "All campaigns successfully processed for action: {$action}.";
    } else {
        $response['success'] = false;
        $response['message'] = "Some campaigns could not be processed for action: {$action}.";
    }

    $response['data'] = [
        'successCount' => $successCount,
        'totalCount' => count($campaignIDs),
        'messages' => $messages
    ];

    wp_send_json($response);
}

add_action('wp_ajax_idemailwiz_add_remove_campaign_from_initiative', 'idemailwiz_add_remove_campaign_from_initiative');

// Runs when a campaign is added or removed from an initiative.
// Adds or removes images URLs based on images found in each campaign template
  function idwiz_save_campaign_assets_to_initiative($initiativeId) {
      // Get the campaign IDs for the given initiative ID
      $campaignIds = idemailwiz_get_campaign_ids_for_initiative($initiativeId);

      // Extract image data for these campaign IDs
      $allCampaignImageData = idwiz_extract_campaigns_images($campaignIds);

      // De-duplicate the image data and flatten it into a single array
      $uniqueImageData = [];
      foreach ($allCampaignImageData as $campaignId => $imageData) {
          foreach ($imageData as $data) {
              $uniqueImageData[md5($data['src'] . $data['alt'])] = $data;
          }
      }

      // Get the existing meta data, if any
      $existingMetaData = get_post_meta($initiativeId, 'wizinitiative_assets', true);
      if (!is_array($existingMetaData)) {
          $existingMetaData = [];
      }

      // Compare existing meta data and new data, and update accordingly
      $newKeys = array_keys($uniqueImageData);
      $existingKeys = array_keys($existingMetaData);
      $keysToAdd = array_diff($newKeys, $existingKeys);
      $keysToRemove = array_diff($existingKeys, $newKeys);

      // Remove entries
      foreach ($keysToRemove as $key) {
          unset($existingMetaData[$key]);
      }

      // Add new entries
      foreach ($keysToAdd as $key) {
          $existingMetaData[$key] = $uniqueImageData[$key];
      }

      // Update the custom meta field
      update_post_meta($initiativeId, 'wizinitiative_assets', $existingMetaData);

      return true;
  }