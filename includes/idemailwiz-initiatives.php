<?php

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

  $all_campaigns = get_idwiz_campaigns();
  $search = isset($_POST['q']) ? $_POST['q'] : '';
  $exclude_ids = isset($_POST['exclude']) ? $_POST['exclude'] : array(); // Get the exclude parameter

  $filtered_campaigns = array_filter($all_campaigns, function($campaign) use ($search, $exclude_ids) {
    return strpos(strtolower(trim($campaign['name'])), strtolower(trim($search))) !== false
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
    $serialized_campaign_ids = get_post_meta($initiative_post_id, 'wiz_campaigns', true);
    $campaign_ids = unserialize($serialized_campaign_ids);

    if (empty($campaign_ids)) {
        return 'No campaigns found for this initiative.';
    }

    $campaigns = get_idwiz_campaigns(array('ids' => $campaign_ids));
    $start_dates = array_column($campaigns, 'startAt');
    $min_start_date = min($start_dates);
    $max_start_date = max($start_dates);

    $min_date_readable = date('m/d/Y', $min_start_date / 1000);
    $max_date_readable = date('m/d/Y', $max_start_date / 1000);

    return $min_date_readable . ' - ' . $max_date_readable;
}


function idemailwiz_add_campaign_to_initiative($campaignID, $initiativeID, $table_name) {
    global $wpdb;

    // Fetch current initiatives
    $current_initiatives = $wpdb->get_var($wpdb->prepare("SELECT initiative FROM $table_name WHERE id = %d", $campaignID));
    $initiatives_array = maybe_unserialize($current_initiatives);

    // Check if already added
    if (in_array($initiativeID, $initiatives_array)) {
        return 'This campaign has already been added!';
    }

    // Add initiative
    $initiatives_array[] = $initiativeID;

    // Re-serialize and update the database
    $serialized_initiatives = maybe_serialize($initiatives_array);
    $wpdb->update($table_name, array('initiative' => $serialized_initiatives), array('id' => $campaignID), array('%s'), array('%d'));

    // Fetch and update initiative's campaigns
    $current_campaigns = get_post_meta($initiativeID, 'wiz_campaigns', true);
    $campaigns_array = maybe_unserialize($current_campaigns) ?: [];
    if (!in_array($campaignID, $campaigns_array)) {
        $campaigns_array[] = $campaignID;
    }
    update_post_meta($initiativeID, 'wiz_campaigns', maybe_serialize($campaigns_array));

    return 'Campaign added successfully!';
}

function idemailwiz_remove_campaign_from_initiative($campaignID, $initiativeID, $table_name) {
    global $wpdb;

    // Fetch current initiatives
    $current_initiatives = $wpdb->get_var($wpdb->prepare("SELECT initiative FROM $table_name WHERE id = %d", $campaignID));
    $initiatives_array = maybe_unserialize($current_initiatives);

    // Remove initiative
    if (($key = array_search($initiativeID, $initiatives_array)) !== false) {
        unset($initiatives_array[$key]);
    }

    // Re-serialize and update the database
    $serialized_initiatives = maybe_serialize($initiatives_array);
    $wpdb->update($table_name, array('initiative' => $serialized_initiatives), array('id' => $campaignID), array('%s'), array('%d'));

    // Fetch and update initiative's campaigns
    $current_campaigns = get_post_meta($initiativeID, 'wiz_campaigns', true);
    $campaigns_array = maybe_unserialize($current_campaigns) ?: [];
    if (($key = array_search($campaignID, $campaigns_array)) !== false) {
        unset($campaigns_array[$key]);
    }
    update_post_meta($initiativeID, 'wiz_campaigns', maybe_serialize($campaigns_array));

    return 'Campaign removed successfully!';
}


function idemailwiz_add_remove_campaign_from_initiative() {
    global $wpdb;

    // Check nonce for security
    check_ajax_referer('initiatives', 'security');

    // Get the provided campaign ID, initiative ID, and action
    $campaignID = intval($_POST['campaign_id']);
    $initiativeID = intval($_POST['initiative_id']);
    $action = $_POST['campaignAction'];

    // Validate action
    if ($action != 'add' && $action != 'remove') {
        wp_send_json_error(array('message' => 'Invalid action.'));
        return;
    }

    // Prepare table name
    $table_name = $wpdb->prefix . "idemailwiz_campaigns";

    // Delegate to utility functions and get message
    $message = '';
    if ($action == 'add') {
        $message = idemailwiz_add_campaign_to_initiative($campaignID, $initiativeID, $table_name);
    } else { // remove
        $message = idemailwiz_remove_campaign_from_initiative($campaignID, $initiativeID, $table_name);
    }

    // Response handling based on the message
    if ($message === 'Campaign added successfully!' || $message === 'Campaign removed successfully!') {
        wp_send_json_success(array('message' => $message));
    } else {
        wp_send_json_error(array('message' => $message));
    }
}

add_action('wp_ajax_idemailwiz_add_remove_campaign_from_initiative', 'idemailwiz_add_remove_campaign_from_initiative');

