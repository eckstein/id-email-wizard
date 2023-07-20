<?php
//Get all the postdata needed to create or update a template in Iterable
function get_template_data_for_iterable() {
	
	 // Check that post_id is defined
    if (!isset($_POST['post_id'])) {
        wp_send_json(array(
            'status' => 'error',
            'message' => 'Post ID is missing',
        ));
    }
	
	$post_id = $_POST['post_id'];
	$emailSettings = get_field('email_settings', $post_id);
	$current_user = wp_get_current_user();
	
	$templateFields = array (
		'preheader' => $emailSettings['preview_text'],
		'fromName' => $emailSettings['from_name'],
		'utmTerm' => $emailSettings['utm_term'],
	);
	$reqTemplateFields = array (
		'templateName' => get_the_title($post_id),
		'emailSubject' => $emailSettings['subject_line'],
		'messageType' => $emailSettings['email_type'],
		'fromEmail' => 'info@idtechonline.com',
		'replyToEmail' => 'info@idtechonline.com',
		'createdBy' => $current_user->user_email,
		'postId' => $post_id,
	);
	
	$missing = array();
	$present = array();
	foreach ($reqTemplateFields as $key=>$field) {
		if (!$field) {
			$missing[] = $key;
			
		}
	}
	if (empty($missing)) {
		$templateFields = array_merge($reqTemplateFields, $templateFields);
		$response = array(
			'status' => 'success',
			'fields' => $templateFields,
		);
	} else {	
		$response = array(
			'status' => 'error',
			'message' => 'Required fields are missing: '.implode(',', $missing),
		);
	}
	
	
	
	wp_send_json($response);
}
add_action('wp_ajax_get_template_data_for_iterable', 'get_template_data_for_iterable');
add_action('wp_ajax_nopriv_get_template_data_for_iterable', 'get_template_data_for_iterable');


//Update the template after it syncs to Iterable
function update_template_after_sync() {
	$post_id = $_POST['post_id'];
	$template_id = $_POST['template_id'];
	//check for existing itTemplateId
	if (!get_post_meta($post_id,'itTemplateId', true)) {
		//add template_id to post meta if not existent yet
		delete_post_meta($post_id,'itTemplateId');
		update_post_meta($post_id,'itTemplateId',$template_id);
		$message = 'itTemplateId added to post meta!';
	} else {
		$message = 'itTemplateId already exists, moving on...';
	}
	$response = array(
		'status' => 'success',
		'message' => $message,
	);
	wp_send_json($response);
}
add_action('wp_ajax_update_template_after_sync', 'update_template_after_sync');
add_action('wp_ajax_nopriv_update_template_after_sync', 'update_template_after_sync');