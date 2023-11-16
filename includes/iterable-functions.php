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

	$emailSettings = get_field('email_settings', $post_id);
	$current_user = wp_get_current_user();

	$templateFields = array(
		'preheader' => $emailSettings['preview_text'],
		'fromName' => $emailSettings['from_name'],
		'utmTerm' => $emailSettings['utm_term'],
	);
	$reqTemplateFields = array(
		'templateName' => get_the_title($post_id),
		'emailSubject' => $emailSettings['subject_line'],
		'messageType' => $emailSettings['email_type'],
		'fromEmail' => 'info@idtechonline.com',
		'replyToEmail' => 'info@idtechonline.com',
		'createdBy' => $current_user->user_email,
		'postId' => $post_id,
	);

	$missing = array();

	foreach ($reqTemplateFields as $key => $field) {
		if (!$field) {
			$missing[] = $key;

		}
	}
	if (empty($missing)) {
		$templateFields = array_merge($reqTemplateFields, $templateFields);
		$response = array(
			'status' => 'success',
			'fields' => $templateFields,
			'alreadySent' => false,
		);

		// Iterable template ID
		$templateId = $_POST['template_id'] ?? false;

		// Get wiz campaign based on templateId
		$wizTemplate = get_idwiz_template($templateId);
		$wizCampaign = get_idwiz_campaign($wizTemplate['campaignId']);

		if ($wizCampaign && $wizCampaign['campaignState'] === 'Finished') {
			$response['alreadySent'] = true;
		}

	} else {
		$response = array(
			'status' => 'error',
			'message' => 'Required fields are missing: ' . implode(',', $missing),
		);
	}



	wp_send_json($response);
}
add_action('wp_ajax_idemailwiz_get_template_data_for_iterable', 'idemailwiz_get_template_data_for_iterable');

function check_duplicate_itTemplateId()
{
	// Iterable template ID
	$templateId = $_POST['template_id'] ?? false;
	// WizTemplate post ID
	$post_id = $_POST['post_id'] ?? false;

	if ($templateId) {
		// Check for existing itTemplateId
		$args = array(
			'post_type' => 'idemailwiz_template',
			'meta_key' => 'itTemplateId',
			'meta_value' => (int) $templateId,
			'post__not_in' => array($post_id),
			'posts_per_page' => 1
		);

		$existingTemplates = get_posts($args);

		if (!empty($existingTemplates)) {
			$existingTemplateId = $existingTemplates[0]->ID;
			$response = array(
				'status' => 'error',
				'message' => "The template ID you entered is already synced to template <a href='" . get_edit_post_link($existingTemplateId) . "'>" . $existingTemplateId . "</a>"
			);
			wp_send_json($response);
			return; // Exit function
		} else {
			wp_send_json(array('status' => 'success'));
			return; // Exit function
		}
	}
}
add_action('wp_ajax_check_duplicate_itTemplateId', 'check_duplicate_itTemplateId');


function update_template_after_sync()
{
	global $wpdb;

	//check nonce
	check_ajax_referer('iterable-actions', 'security');

	$post_id = $_POST['post_id'];
	$template_id = $_POST['template_id'];

	//check for existing itTemplateId
	if (get_post_meta($post_id, 'itTemplateId', true)) {
		//delete old template_id from post meta if it exists
		delete_post_meta($post_id, 'itTemplateId');
	}

	//add new template_id to post meta
	update_post_meta($post_id, 'itTemplateId', $template_id);

	//add last updated date/time to post meta
	$dateTime = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
	$formattedDateTime = $dateTime->format('n/j/Y \a\t g:ia');
	update_post_meta($post_id, 'lastIterableSync', $formattedDateTime);

	// Update the custom database table
	$wpdb->update(
		$wpdb->prefix . 'idemailwiz_templates',
		array('clientTemplateId' => $post_id),
		array('templateId' => $template_id),
		array('%d'),
		array('%d')
	);

	$response = array(
		'status' => 'success',
		'message' => 'itTemplateId updated in post meta and template database!',
	);
	wp_send_json($response);
}
add_action('wp_ajax_update_template_after_sync', 'update_template_after_sync');
add_action('wp_ajax_nopriv_update_template_after_sync', 'update_template_after_sync');






