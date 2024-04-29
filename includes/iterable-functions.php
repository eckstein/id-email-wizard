<?php



//Get all the postdata needed to create or update a template in Iterable
function idemailwiz_get_template_data_for_iterable()
{
	//check nonce
	check_ajax_referer('iterable-actions', 'security');

	// Check that post_id is defined
	if (!isset($_POST['post_id'])) {
		wp_send_json(
			array(
				'status' => 'error',
				'message' => 'Post ID is missing',
			)
		);
	}

	$post_id = $_POST['post_id'];

	$wizTemplate = get_wiztemplate($post_id);

	$iterableSyncSettings = $wizTemplate['template_settings']['iterable-sync'] ?? [];

	$messageSettings = $wizTemplate['template_options']['message_settings'];

	$current_user = wp_get_current_user();

	$templateFields = array(
		'preheader' => html_entity_decode($messageSettings['preview_text']) ?? '',
		'fromName' => $messageSettings['from_name'] ?? 'iD Tech Camps',
		'utmTerm' => $messageSettings['utm_term'] ?? '',
	);
	$reqTemplateFields = array(
		'templateName' => html_entity_decode(get_the_title($post_id)),
		'emailSubject' =>  html_entity_decode($messageSettings['subject_line'])  ?? '',

		'messageType' => $messageSettings['email_type'] ?? 'promotional',
		'fromEmail' => $messageSettings['from_email'] ?? 'info@idtechonline.com',
		'replyToEmail' => $messageSettings['reply_to'] ?? 'hello@idtechonline.com',
		'createdBy' => $current_user->user_email,
		'postId' => $post_id,
	);

	$missing = array();

	foreach ($reqTemplateFields as $key => $field) {
		if (!$field) {
			$missing[] = $key;

		}
	}

	$response['alreadySent'] = false;
	if (empty($missing)) {
		$templateFields = array_merge($reqTemplateFields, $templateFields);
		$response = array(
			'status' => 'success',
			'fields' => $templateFields,
		);

		// Iterable template ID
		//$templateId = $_POST['template_id'] ?? false;
		$templateId = $iterableSyncSettings['iterable_template_id'] ?? '';

		if ($templateId) {
			// Get wiz campaign based on templateId
			$wizTemplate = get_idwiz_template($templateId);
			if ($wizTemplate) {
				$wizCampaign = get_idwiz_campaign($wizTemplate['campaignId']);
				if ($wizCampaign && $wizCampaign['campaignState'] === 'Finished') {
					$response['alreadySent'] = true;
				}
			}
			
			
		}

	} else {
		$response = array(
			'status' => 'error',
			'message' => 'Required fields are missing: ' . implode(',', $missing),
			'alreadySent' => false
		);
	}



	wp_send_json($response);
}
add_action('wp_ajax_idemailwiz_get_template_data_for_iterable', 'idemailwiz_get_template_data_for_iterable');

function check_duplicate_itTemplateId() {
	// Iterable template ID from POST request
	$templateId = $_POST['template_id'] ?? false;
	// WizTemplate post ID from POST request
	$post_id = $_POST['post_id'] ?? false;

	if ($post_id) {
		// Fetch all wizTemplate posts
		$args = array(
			'post_type' => 'idemailwiz_template', // Ensure this is your correct custom post type
			'posts_per_page' => -1, // Consider limiting this if you have many posts
			'post__not_in' => array($post_id), // Exclude the current post 
			//TODO: This won't work when we need to look at variations from the same post
		);

		$wizTemplates = get_posts($args);
		foreach ($wizTemplates as $wizTemplate) {
			// Get the wizTemplate data
			$wizData = get_wiztemplate($wizTemplate->ID);
			$wizTemplateControlId = $wizData['template_options']['template_settings']['iterable-sync']['iterable_template_id'] ?? false;
			//TODO: also look for variations template IDs

			if ($wizTemplateControlId && $wizTemplateControlId == $templateId) {
				// Duplicate found
				$response = array(
					'status' => 'error',
					'message' => "The template ID you entered is already synced to template <a href='" . get_edit_post_link($wizTemplate->ID) . "'>" . $wizTemplate->ID . "</a>"
				);
				wp_send_json($response);
				return; // Exit function
			}
		}

		// If no duplicates are found
		wp_send_json(array('status' => 'success'));
		return; // Exit function
	} else {
		if ($templateId == 'new') {
			wp_send_json(array('status' => 'success'));
			return;
		}
		// Handle case where necessary POST data is not set
		wp_send_json(array('status' => 'error', 'message' => 'Missing template ID or post ID.'));
		return; // Exit function
	}
}
add_action('wp_ajax_check_duplicate_itTemplateId', 'check_duplicate_itTemplateId');



function update_template_after_sync()
{
	global $wpdb;

	//check nonce
	check_ajax_referer('iterable-actions', 'security');

	
	$template_id = $_POST['template_id'];
	$post_id = $_POST['post_id'] ?? null;

	$wizTemplate = get_idwiz_template($template_id);

	$template_name = $_POST['template_name'] ?? $wizTemplate['name'];

	

	// Update the custom database table
	$wpdb->update(
		$wpdb->prefix . 'idemailwiz_templates',
		array('clientTemplateId' => $post_id, 'name' => $template_name),
		array('templateId' => $template_id),
		array('%d', '%s'),
		array('%d')
	);

	$response = array(
		'status' => 'success',
		'message' => 'WizTemplate updated!',
	);
	wp_send_json($response);
}
add_action('wp_ajax_update_template_after_sync', 'update_template_after_sync');






