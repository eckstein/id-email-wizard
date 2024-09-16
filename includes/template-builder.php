<?php
add_action('template_redirect', 'idemailwiz_handle_builder_v2_request', 20);
function idemailwiz_handle_builder_v2_request()
{
	global $wp_query, $wp;

	// Handle build-template
	if (isset($wp_query->query_vars['build-template-v2'])) {



		$current_url = home_url(add_query_arg(array(), $wp->request));
		if (strpos($current_url, '/build-template-v2/') !== false && ! isset($_SERVER['HTTP_REFERER'])) {
			$dieMessage = 'Direct access to the template builder endpoint is not allowed!';
			wp_die($dieMessage);
			exit;
		}

		$templateId = $wp_query->query_vars['build-template-v2'];

		if (!$templateId) {
			$dieMessage = 'TemplateId is unset or invalid!';
			wp_die($dieMessage);
			exit;
		}

		// Start the session
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		// Check if template data exists in the transient
		$transientKey = 'template_data_' . $templateId;
		$templateData = get_transient($transientKey);

		if ($templateData === false) {
			// Fallback to fetching template data from the database
			$templateData = get_wiztemplate($templateId);
		}

		// Initialize flags and settings
		$mergeTags = false;
		$previewMode = 'desktop';

		if ($templateData && $templateData['template_options'] && !empty($templateData['template_options'])) {
			// Preview pane styles
			include dirname(plugin_dir_path(__FILE__)) . '/builder-v2/preview-pane-styles.html';

			// Directly output the template HTML
			echo generate_template_html($templateData, true);

			// Add spacer for proper scrolling in preview pane
			echo '<div style="height: 100vh; color: #cdcdcd; padding: 20px; font-family: Poppins, sans-serif; text-align: center; border-top: 2px dashed #fff;" class="scrollSpace"><em>The extra space below allows proper scrolling in the builder and will not appear in the template</em></div>';
		} else {
			echo '<div style="height: 100vh; color: #cdcdcd; padding: 20px; font-family: Poppins, sans-serif; text-align: center; border-top: 2px dashed #fff;">Start adding sections and your preview will show here.</div>';
		}
		exit;
	}
}

add_action('wp_ajax_idemailwiz_save_template_session_to_transient', 'idemailwiz_save_template_session_to_transient');
function idemailwiz_save_template_session_to_transient()
{
	// Validate AJAX and nonce
	if (! wp_doing_ajax() || ! check_ajax_referer('template-editor', 'security', false)) {
		wp_die();
	}

	// Validate POST action
	if ($_POST['action'] !== 'idemailwiz_save_template_session_to_transient') {
		wp_die();
	}

	$templateId = $_POST['templateid'];

	$transientKey = 'template_data_' . $templateId;

	delete_transient($transientKey);

	// Check if template data is passed in the request
	if (isset($_POST['template_data'])) {
		// Use the template data from the request
		$templateData = json_decode(stripslashes($_POST['template_data']), true);

		// Store the template data using a transient

		set_transient($transientKey, $templateData, 10);

		//error_log( print_r( $_SESSION, true ) );

		wp_send_json_success();
	} else {
		wp_send_json_error();
	}
}


add_action('wp_ajax_get_wiztemplate_with_ajax', 'get_wiztemplate_with_ajax');
function get_wiztemplate_with_ajax()
{
	check_ajax_referer('template-editor', 'security');
	$postId = $_POST['template_id'];
	if (! $postId) {
		wp_send_json_error(['message' => 'No post ID provided']);
		return;
	}
	$templateData = get_wiztemplate($postId);
	wp_send_json_success($templateData);
}

function get_wiztemplate($postId)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'wiz_templates';

	$templateData = [];

	// Fetch the entire template data JSON from the database
	$templateDataJSON = $wpdb->get_var($wpdb->prepare(
		"SELECT template_data FROM $table_name WHERE post_id = %d",
		$postId
	));

	// Decode JSON string into an associative array
	if ($templateDataJSON) {
		$templateData = json_decode($templateDataJSON, true);
	}

	return $templateData;
}

function get_wiztemplate_object($postId)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'wiz_templates';
	// Fetch the entire template data from the database
	$templateObject = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $table_name WHERE post_id = %d",
		$postId
	), ARRAY_A);

	return $templateObject;
}



add_action('wp_ajax_idemailwiz_save_template_title', 'idemailwiz_save_template_title');
function idemailwiz_save_template_title()
{
	// Check for nonce for security
	$nonce = $_POST['security'] ?? '';
	if (! wp_verify_nonce($nonce, 'template-editor')) {
		wp_send_json_error(['message' => 'Nonce verification failed']);
		return;
	}
	$templateId = $_POST['template_id'];
	$templateTitle = $_POST['template_title'];
	if (! $templateId || ! $templateTitle) {
		wp_send_json_error(['message' => 'Invalid template ID or template title']);
		return;
	}
	$updateTitle = wp_update_post(['ID' => $templateId, 'post_title' => $templateTitle, 'post_name' => sanitize_title($templateTitle)]);
	if ($updateTitle) {
		wp_send_json_success(['message' => 'Template title updated successfully']);
	} else {
		wp_send_json_error(['message' => 'Template title update failed']);
	}
}

add_action('wp_ajax_create_new_row', 'handle_create_new_row');
function handle_create_new_row()
{
	// Check for nonce for security
	$nonce = $_POST['security'] ?? '';
	if (! wp_verify_nonce($nonce, 'template-editor')) {
		wp_send_json_error(['message' => 'Nonce verification failed']);
		return;
	}

	// Get the post ID from the AJAX request
	$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
	if (! $post_id) {
		wp_send_json_error(['message' => 'Invalid post ID']);
		return;
	}

	// Get the row ID of the row above the new row
	$add_after = $_POST['row_above'] ?? $_POST['row_to_dupe'];

	// Check if row_above is 'false' (string) or actually false
	if ($add_after === 'false' || $add_after === false) {
		$row_id = 0;
	} else {
		$add_after = intval($add_after);
		$row_id = $add_after + 1;
	}

	// Get existing data (for duplicating) if passed
	$publishedTemplateData = get_wiztemplate($post_id);
	// Check for passed session data and use that, if present
	$sessionTemplateData = isset($_POST['session_data']) && $_POST['session_data'] ? json_decode(stripslashes($_POST['session_data']), true) : [];
	$templateData = $sessionTemplateData ?: $publishedTemplateData;

	// Initialize $chunkData to false
	$rowData = [];

	// Check if the row index is valid and the template data contains rows
	if (isset($_POST['row_to_dupe']) && $add_after >= 0 && isset($templateData['rows'][$add_after])) {
		// Retrieve the specific row data to duplicate
		$rowData = $templateData['rows'][$add_after];
	}


	// Generate the HTML for the new row
	$html = generate_builder_row($row_id, $rowData);

	// Return the HTML
	wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_create_new_columnset', 'handle_create_new_columnset');
function handle_create_new_columnset()
{
	$nonce = $_POST['security'] ?? '';
	if (! wp_verify_nonce($nonce, 'template-editor')) {
		wp_send_json_error(['message' => 'Nonce verification failed']);
		return;
	}

	$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
	$rowId = intval($_POST['row_id']);
	$colSetIndex = isset($_POST['colset_index']) ? intval($_POST['colset_index']) : 0;

	// Initialize $columnSetData to an empty array
	$columnSetData = [
		'columns' => []
	];

	// Check if we're duplication
	if (isset($_POST['colset_to_dupe'])) {
		// Get existing data (for duplicating) if passed
		$sessionTemplateData = isset($_POST['session_data']) && $_POST['session_data'] ? json_decode(stripslashes($_POST['session_data']), true) : [];
		// Check for passed session data and use that, if present, otherwise get the data from the database
		$templateData = $sessionTemplateData ?: get_wiztemplate($post_id);

		// Retrieve the specific columnSet data to duplicate using the original columnSet index
		$originalColSetIndex = intval($_POST['colset_to_dupe']);
		if (isset($templateData['rows'][$rowId]['columnSets'][$originalColSetIndex])) {
			$columnSetData = $templateData['rows'][$rowId]['columnSets'][$originalColSetIndex];
		}
	} else {
		// Generate a blank column for the new columnSet
		$columnData = [
			'title' => 'Column',
			'activation' => 'active',
			'chunks' => []
		];
		$columnSetData['columns'][] = $columnData;
	}

	// Generate the HTML for the new columnSet
	$html = generate_builder_columnset($colSetIndex, $columnSetData, $rowId);

	wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_create_new_column', 'handle_create_new_column');
function handle_create_new_column()
{
	$nonce = $_POST['security'] ?? '';
	if (! wp_verify_nonce($nonce, 'template-editor')) {
		wp_send_json_error(['message' => 'Nonce verification failed']);
		return;
	}

	$rowId = intval($_POST['row_id']);
	$colSetId = intval($_POST['colset_id']);
	$columnIndex = isset($_POST['column_index']) ? intval($_POST['column_index']) : 0;

	// Generate a new column HTML. We'll return a blank column structure.
	$html = generate_builder_column($rowId, $colSetId, [], $columnIndex);

	wp_send_json_success(['html' => $html]);
}


add_action('wp_ajax_add_new_chunk', 'add_or_duplicate_chunk');
function add_or_duplicate_chunk()
{
	$nonce = $_POST['security'] ?? '';
	if (! wp_verify_nonce($nonce, 'template-editor')) {
		wp_send_json_error(['message' => 'Nonce verification failed']);
		return;
	}

	$rowId = intval($_POST['row_id']);
	$chunkBeforeId = isset($_POST['chunk_before_id']) ? intval($_POST['chunk_before_id']) : null;
	$chunkType = sanitize_text_field($_POST['chunk_type']);

	// Generate a new chunk ID
	$newChunkId = $chunkBeforeId !== null ? $chunkBeforeId + 1 : 0;

	$columnId = isset($_POST['column_id']) ? intval($_POST['column_id']) : 0;

	if (isset($_POST['duplicate']) && $_POST['chunk_data'] !== null) {
		$chunkData = $_POST['chunk_data'];
	} else {
		$chunkData = [];
	}

	$html = generate_builder_chunk($newChunkId, $chunkType, $rowId, $columnId, $chunkData);

	wp_send_json_success(['html' => $html, 'chunk_id' => $newChunkId]);
}


function generate_builder_row($rowId, $rowData = [])
{

	$uniqueId = uniqid('wiz-row-');

	// Attempt to set columnSets from rowData, default to an empty array if not set or not an array
	$columnSets = isset($rowData['columnSets']) && is_array($rowData['columnSets']) ? $rowData['columnSets'] : [];

	// Check if columnSets is empty, indicating an older version of the template
	// if ( empty( $columnSets ) ) {
	// 	// Check if 'columns' key exists in rowData
	// 	if ( isset( $rowData['columns'] ) && is_array( $rowData['columns'] ) ) {
	// 		// Move 'stacked' and 'magic_wrap' keys from row level to columnSet level
	// 		$stacked = isset( $rowData['stacked'] ) ? $rowData['stacked'] : false;
	// 		$magic_wrap = isset( $rowData['magic_wrap'] ) ? $rowData['magic_wrap'] : "off";

	// 		// Determine the layout based on the number of columns
	// 		$columnCount = count( $rowData['columns'] );
	// 		$layout = '';
	// 		switch ( $columnCount ) {
	// 			case 1:
	// 				$layout = 'one-column';
	// 				break;
	// 			case 2:
	// 				$layout = 'two-column';
	// 				break;
	// 			case 3:
	// 				$layout = 'three-column';
	// 				break;
	// 			default:
	// 				$layout = 'one-column';
	// 		}

	// 		// Create a new columnSet with the columns from the older version
	// 		$columnSets = [ 
	// 			[ 
	// 				'columns' => $rowData['columns'],
	// 				'layout' => $layout,
	// 				'stacked' => $stacked,
	// 				'magic_wrap' => $magic_wrap,
	// 				'activation' => 'active',
	// 			]
	// 		];

	// 		// Remove the 'columns' key from $rowData to avoid extra columns
	// 		unset( $rowData['columns'] );
	// 	} else {
	// 		// If 'columns' key doesn't exist, initialize with a default empty column set
	// 		$columnSets = [ 
	// 			[ 
	// 				'columns' => [ 
	// 					[ 
	// 						'title' => 'Column',
	// 						'activation' => 'active',
	// 						'chunk' => [],
	// 					]
	// 				],
	// 				'layout' => 'one-column',
	// 				'stacked' => false,
	// 				'magic_wrap' => "off",
	// 				'activation' => 'active',
	// 			]
	// 		];
	// 	}
	// }

	$html = '';

	$rowCollapseState = $rowData['state'] ?? 'collapsed';

	$rowDesktopVisibility = isset($rowData['desktop_visibility']) && $rowData['desktop_visibility'] === 'false' ? 'false' : 'true';
	$rowMobileVisibility = isset($rowData['mobile_visibility']) && $rowData['mobile_visibility'] === 'false' ? 'false' : 'true';

	// Determine if the icons should have the 'disabled' class based on visibility
	$rowDesktopIconClass = $rowDesktopVisibility === 'false' ? 'disabled' : '';
	$rowMobileIconClass = $rowMobileVisibility === 'false' ? 'disabled' : '';

	$colsetFramesMode = isset($rowData['frames_mode']) && $rowData['frames_mode'] === 'false' ? 'false' : 'true';
	$colsetFramesModeClass = $colsetFramesMode === 'true' ? 'active' : '';


	$rowBackgroundSettings = $rowData['background_settings'] ?? [];


	$rowTitle = $rowData['title'] ?? 'Section';
	$rowNumber = $rowId + 1;

	$html .= '<div class="builder-row --' . $rowCollapseState . '" id="' . $uniqueId . '" data-row-id="' . $rowId . '" data-frames-mode="' . $colsetFramesMode . '">
				<div class="builder-header builder-row-header">
					<div class="builder-row-title exclude-from-toggle"><div class="builder-row-title-number" data-row-id-display="' . $rowNumber . '">' . $rowNumber . '</div>
					<div class="builder-row-title-text edit-row-title exclude-from-toggle" data-row-id="' . $rowId . '">' . $rowTitle . '</div>
					</div>
					<div class="builder-row-toggle builder-toggle">&nbsp;</div>
					<div class="builder-row-actions">
						<div class="builder-row-actions-button exclude-from-toggle show-on-desktop ' . $rowDesktopIconClass . '" data-show-on-desktop="' . $rowDesktopVisibility . '" title="Show on desktop">
						<i class="fas fa-desktop"></i>
						</div>
						<div class="builder-row-actions-button exclude-from-toggle show-on-mobile ' . $rowMobileIconClass . '" data-show-on-mobile="' . $rowMobileVisibility . '" title="Show on mobile">
						<i class="fas fa-mobile-alt" ></i>
						</div>
						<div class="builder-row-actions-button exclude-from-toggle toggle-frames-mode ' . $colsetFramesModeClass . '" data-frames-mode="' . $colsetFramesMode . '" title="Toggle Frames mode">
							<i class="fa-solid fa-film"></i>
						</div>
						<span>&nbsp;|&nbsp;</span>
						<div class="builder-row-actions-button exclude-from-toggle row-bg-settings-toggle" title="Background color">
							<i class="fa-solid fa-fill-drip"></i>
						</div>

						<div class="builder-row-actions-button exclude-from-toggle json-actions" data-json-element="row" title="Export/Import JSON data">
							<i class="fa-solid fa-share-nodes"></i>
						</div>

						<div class="builder-row-actions-button exclude-from-toggle duplicate-row" title="Duplicate row">
						<i class="fa-regular fa-copy"></i>
						</div>
						<div class="builder-row-actions-button remove-element remove-row exclude-from-toggle" title="Delete row">
						<i class="fas fa-times"></i>
						</div>
					</div>
					
				</div>
				<div class="builder-settings-section builder-row-settings-row">
				<form class="builder-row-settings">';
	$html .= generateBackgroundSettingsModule($rowBackgroundSettings, '');
	$html .= '</form>'; // row-settings form
	$html .= '</div>'; // row-settings

	$html .= '
				<div class="builder-row-content">
				<div class="builder-columnsets">
				';
	foreach ($columnSets as $colSetIndex => $columnSet) {

		$html .= generate_builder_columnset($colSetIndex, $columnSet, $rowId, $colsetFramesMode);
	}


	$html .= '</div>'; // columnsets
	$html .= '<div class="builder-row-footer">';
	$html .= '<button class="wiz-button outline add-columnset">Add Column Set</button>';
	$html .= '</div>'; // row-footer
	$html .= '</div>'; // Builder-row-content
	$html .= '</div>'; // Builder-row divs

	return $html;
}

function generate_builder_columnset($colSetIndex, $columnSet, $rowId, $framesMode = 'false')
{
	$uniqueId = uniqid('wiz-columnset-');

	$colsetDesktopVisibility = isset($columnSet['desktop_visibility']) && $columnSet['desktop_visibility'] === 'false' ? 'false' : 'true';
	$colsetMobileVisibility = isset($columnSet['mobile_visibility']) && $columnSet['mobile_visibility'] === 'false' ? 'false' : 'true';


	// Determine if the icons should have the 'disabled' class based on visibility
	$colsetDesktopIconClass = $colsetDesktopVisibility === 'false' ? 'disabled' : '';
	$colsetMobileIconClass = $colsetMobileVisibility === 'false' ? 'disabled' : '';

	

	$columns = $columnSet['columns'] ?? [];

	// Ensure there are always three columns available
	while (count($columns) < 3) {
		$columns[] = [
			'title' => 'Column',
			'activation' => 'inactive',
			'chunks' => []
		];
	}

	$countColumns = 0;
	foreach ($columns as $column) {
		if (! isset($column['activation']) || $column['activation'] === 'active') {
			$countColumns++;
		}
	}

	

	if ($countColumns > 1) {
		$magicWrap = $columnSet['magic_wrap'] ?? 'off';
		$mobileWrap = $columnSet['mobile_wrap'] ?? 'on';

		$magicWrapToggleClass = $magicWrap == 'on' ? 'active' : '';
		$mobileWrapToggleClass = $mobileWrap == 'on' ? 'active' : '';

		if ($magicWrap == 'on') {
			//$columns = array_reverse($columns);
		}
	} else {
		$magicWrap = 'off';
		$mobileWrap = 'off';

		$magicWrapToggleClass = 'disabled';
		$mobileWrapToggleClass = 'disabled';
	}

	

	$columnsStacked = $columnSet['stacked'] ?? false;
	$stackedClass = $columnsStacked ? 'fa-rotate-90' : '';

	$columnsetTitle = $columnSet['title'] ?? 'Column Set';

	$colsLayout = $columnSet['layout'] ?? 'one-column';

	$colsetBgSettings = $columnSet['background_settings'] ?? [];

	$colSetState = $columnSet['state'] ?? 'collapsed';

	$columnSetDisplayCnt = $colSetIndex + 1;

	$html = '';

	$html .= '<div class="builder-columnset --' . $colSetState . '" id="' . $uniqueId . '" data-columnset-id="' . $colSetIndex . '" data-layout="' . $colsLayout . '" data-magic-wrap="' . $magicWrap . '" data-mobile-wrap="' . $mobileWrap . '" data-show-on-desktop="' . $colsetDesktopVisibility . '" data-show-on-mobile="' . $colsetMobileVisibility . '">
			<div class="builder-header builder-columnset-header">
			<div class="builder-columnset-title exclude-from-toggle"><div class="builder-columnset-title-number" data-columnset-id-display="' . $columnSetDisplayCnt . '">' . $columnSetDisplayCnt . '</div>
			<div class="builder-columnset-title-text edit-columnset-title exclude-from-toggle" data-columnset-id="' . $colSetIndex . '">' . $columnsetTitle . '</div>
			</div>
			<div class="builder-toggle builder-columnset-toggle">&nbsp;</div>
			<div class="builder-columnset-actions">
				<div class="builder-columnset-actions-button exclude-from-toggle show-on-desktop ' . $colsetDesktopIconClass . '" data-show-on-desktop="' . $colsetDesktopVisibility . '" title="Show on desktop">
				<i class="fas fa-desktop"></i>
				</div>
				<div class="builder-columnset-actions-button exclude-from-toggle show-on-mobile ' . $colsetMobileIconClass . '" data-show-on-mobile="' . $colsetMobileVisibility . '" title="Show on mobile">
				<i class="fas fa-mobile-alt" ></i>
				</div>
				
				<span>&nbsp;|&nbsp;</span>
				<div class="builder-columnset-actions-button exclude-from-toggle colset-bg-settings-toggle" title="Background color">
					<i class="fa-solid fa-fill-drip"></i>
				</div>
				
				

				<div class="builder-columnset-actions-button columnset-column-settings exclude-from-toggle" data-columns="' . $countColumns . '" title="Change columns layout">
				<i class="fas fa-columns"></i>
				</div>

				

				<div class="builder-columnset-actions-button mobile-wrap-toggle columnset-columns-mobile-wrap exclude-from-toggle ' . $mobileWrapToggleClass . '" title="Toggle mobile column wrap">
					<i class="fa-solid fa-mobile-alt"></i> <i class="fa-solid fa-arrows-turn-right fa-rotate-180"></i></i></i>
				</div>

				<div class="builder-columnset-actions-button magic-wrap-toggle columnset-columns-magic-wrap exclude-from-toggle ' . $magicWrapToggleClass . '" title="Magic Wrap">
					<i class="fa-solid fa-wand-magic-sparkles"></i> <i class="fa-solid fa-arrow-right-arrow-left"></i>
				</div>

				<span>&nbsp;|&nbsp;</span>
				<div class="builder-columnset-actions-button exclude-from-toggle ' . $stackedClass . '" title="Stack/Unstack columns">
				<i class="fa-solid fa-bars rotate-columns" ></i>
				</div>

				<div class="builder-columnset-actions-button exclude-from-toggle json-actions" data-json-element="columnset" title="Export/Import JSON data">
					<i class="fa-solid fa-share-nodes"></i>
				</div>
				
				<div class="builder-columnset-actions-button exclude-from-toggle duplicate-columnset" title="Duplicate columnset">
				<i class="fa-regular fa-copy"></i>
				</div>
				
				<div class="builder-columnset-actions-button remove-element remove-columnset exclude-from-toggle" title="Delete columnset">
				<i class="fas fa-times"></i>
				</div>
			</div>
			</div>
			
			
			<div class="builder-settings-section builder-columnset-settings-row">
			<form class="builder-columnset-settings">';
	$html .= generateBackgroundSettingsModule($colsetBgSettings, '');
	$html .= '</form>'; // end columnset settings form
	$html .= '</div>'; // end columnset settings

	$html .= '<div class="builder-columnset-content">';
	if ($magicWrap == 'on') {
		$html .= '<div class="magic-wrap-indicator"><i class="fa-solid fa-wand-magic-sparkles"></i>&nbsp;&nbsp;Magic wrap is on! Columns will be reversed when wrapped for mobile.</div>';
	}
	if ($framesMode === 'true') {
		$html .= '<div class="builder-columnset-frame-settings">';
		$html .= '<div class="builder-columnset-frame-settings-title">Frame Settings</div>';
		$html .= '<div class="builder-columnset-frame-settings-content">';
		$html .= '</div>';
		$html .= '</div>';
	}
	$html .= '<div class="builder-columnset-columns" data-active-columns="' . $countColumns . '">';

	foreach ($columns as $columnIndex => $column) {

		$html .= generate_builder_column($rowId, $columnSet, $column, $columnIndex);
	}

	$html .= '</div>'; // builder-columnset-content
	$html .= '</div>'; // builder-columnset-columns

	$html .= '</div>'; // end columnset


	return $html;
}

function generate_builder_column($rowId, $columnSet, $columnData, $columnIndex)
{
	$uniqueId = uniqid('wiz-column-');

	$columnNumberDisplay = $columnIndex + 1;

	$colValign = $columnData['settings']['valign'] ?? 'top';

	$colBgSettings = $columnData['settings'] ?? [];

	$colActiveClass = isset($columnData['activation']) && $columnData['activation'] === 'inactive' ? 'inactive' : 'active';

	$html = '<div class="builder-column ' . $colActiveClass . '" id="' . $uniqueId . '" data-column-id="' . $columnIndex . '">';
	$html .= '<div class="builder-header builder-column-header">';

	$colTitle = $columnData['title'] ?? 'Column';


	$html .= '<div class="builder-column-title exclude-from-toggle"><div class="builder-column-title-number">' . $columnNumberDisplay . '</div>';
	$html .= '<div class="builder-column-title-text edit-column-title exclude-from-toggle" data-column-id="' . $columnIndex . '">' . $colTitle . '</div>';
	$html .= '</div>';
	$html .= '<div class="builder-column-toggle">&nbsp;</div>';
	$html .= '<div class="builder-column-actions">';
	$html .= '<div class="builder-column-actions-button show-column-settings">';
	$html .= '<i class="fas fa-cog" title="Column settings"></i>';
	$html .= '</div>';
	$html .= '</div>'; // close actions
	$html .= '</div>'; // Close header
	$html .= '<div class="builder-column-settings-row">';
	$html .= '<form class="builder-column-settings">';
	$html .= '<div class="builder-field-group">';
	$html .= '<div class="button-group-wrapper">';
	$html .= '<label class="button-group-label">Vertical Align</label>';
	$html .= '<div class="button-group radio">';
	$valignTopChecked = $colValign === 'top' ? 'checked' : '';
	$html .= '<input type="radio" id="' . $uniqueId . '_valign_top" name="valign" value="top" class="valign-type-select" ' . $valignTopChecked . '>';
	$html .= '<label class="button-label" for="' . $uniqueId . '_valign_top">Top</label>';
	$valignMiddleChecked = $colValign === 'middle' ? 'checked' : '';
	$html .= '<input type="radio" id="' . $uniqueId . '_valign_middle" name="valign" value="middle" class="valign-type-select" ' . $valignMiddleChecked . '>';
	$html .= '<label class="button-label" for="' . $uniqueId . '_valign_middle">Middle</label>';
	$valignBottomChecked = $colValign === 'bottom' ? 'checked' : '';
	$html .= '<input type="radio" id="' . $uniqueId . '_valign_bottom" name="valign" value="bottom" class="valign-type-select" ' . $valignBottomChecked . '>';
	$html .= '<label class="button-label" for="' . $uniqueId . '_valign_bottom">Bottom</label>';
	$html .= '</div>';
	$html .= '</div>';
	$html .= '</div>';

	$html .= generateBackgroundSettingsModule($colBgSettings, '');
	$html .= '</form>';
	$html .= '</div>'; // Close settings row


	// $collapsedMsgClass = 'hide';
	// if ( $collapsedState === 'collapsed' ) {
	// 	$collapsedMsgClass = 'show';
	// }
	// $html .= '<div class="collapsed-message ' . $collapsedMsgClass . '">Column is collapsed. Click here to show chunks.</div>';
	$html .= '<div class="builder-column-chunks">';
	$html .= '<div class="builder-column-chunks-body">'; // we need this extra wrapper to avoid slideup/slidedown from messing with our flex layout

	if (! empty($columnData['chunks'])) {
		foreach ($columnData['chunks'] as $chunkIndex => $chunk) {
			$chunkType = $chunk['field_type'] ?? 'text';
			$html .= generate_builder_chunk($chunkIndex, $chunkType, $rowId, $columnIndex, $chunk);
		}
	}
	$html .= '</div>';
	$html .= '<div class="builder-column-footer add-chunk-wrapper"><button class="wiz-button centered add-chunk">Add Chunk</button></div>';

	$html .= '</div>';

	$html .= '</div>';

	return $html;
}


function get_chunk_preview($chunkData = [], $chunkType = null)
{

	if (!$chunkType) {
		return;
	}
	$chunkPreview = ucfirst($chunkType);

	if ($chunkType == 'text' && isset($chunkData['fields'])) {
		$chunkPreview = $chunkData['fields']['plain_text_content'] ? mb_substr(strip_tags(stripslashes($chunkData['fields']['plain_text_content'])), 0, 32) . '...' : '';
	}

	if ($chunkType == 'html' && isset($chunkData['fields'])) {
		$chunkPreview = 'HTML Code';
	}

	if ($chunkType == 'image' && isset($chunkData['fields'])) {
		$image = $chunkData['fields']['image_url'] ?? 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/full-width-image.jpg';
		if ($image) {
			$chunkPreview = '<div class="image-chunk-preview-wrapper"><img src="' . $image . '" /></div>';
		}
	}

	if ($chunkType == 'button' && isset($chunkData['fields'])) {
		$buttonText = $chunkData['fields']['button_text'] ?? 'Click Here';
		$chunkPreview = '<div class="button-chunk-preview-wrapper"><button class="wiz-button">' . stripslashes($buttonText) . '</button></div>';
	}

	if ($chunkType == 'spacer' && isset($chunkData['fields'])) {
		$spacerHeight = $chunkData['fields']['spacer_height'] ?? '';
		$chunkPreview = '<div class="spacer-chunk-preview-wrapper"><em>— <span class="spacer-height-display">' . $spacerHeight . '</span> spacer —</em></div>';
	}

	if ($chunkType == 'snippet' && isset($chunkData['fields'])) {
		$snippetName = $chunkData['fields']['snippet_name'] ?? '<em>Select a snippet</em>';
		$chunkPreview = '<div class="snippet-chunk-preview-wrapper"><i class="fa-solid fa-code"></i>&nbsp;&nbsp;Snippet: <span class="snippet-name-display">' . $snippetName . '</span></div>';
	}

	return $chunkPreview ?? ucfirst($chunkData['field_type']) . ' chunk';
}


add_action('wp_ajax_get_chunk_preview', 'handle_get_chunk_preview');

function handle_get_chunk_preview()
{
	// Verify the nonce for security
	$nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
	if (! wp_verify_nonce($nonce, 'template-editor')) {
		wp_send_json_error(['message' => 'Nonce verification failed']);
		return;
	}

	$chunkData = isset($_POST['chunkData']) ? $_POST['chunkData'] : [];
	$chunkType = isset($_POST['chunkType']) ? sanitize_text_field($_POST['chunkType']) : '';

	$previewHtml = get_chunk_preview($chunkData, $chunkType);

	wp_send_json_success(['html' => $previewHtml]);
}


function generate_builder_chunk($chunkId, $chunkType, $rowId, $columnId, $chunkData = [])
{
	$uniqueId = $chunkData['id'] ?? uniqid('wiz-chunk-');
	$uniqueId = uniqid('wiz-chunk-');
	$chunkState = $chunkData['state'] ?? 'collapsed';

	$desktopVisibility = (isset($chunkData['settings']['desktop_visibility']) && $chunkData['settings']['desktop_visibility'] == 'false') ? 'false' : 'true';
	$mobileVisibility = (isset($chunkData['settings']['mobile_visibility']) && $chunkData['settings']['mobile_visibility'] == 'false') ? 'false' : 'true';


	$desktopIconClass = $desktopVisibility == 'false' ? 'disabled' : '';
	$mobileIconClass = $mobileVisibility == 'false' ? 'disabled' : '';


	$chunkPreview = get_chunk_preview($chunkData, $chunkType);


	$html = '<div class="builder-chunk --' . $chunkState . '" data-chunk-id="' . $chunkId . '" data-chunk-type="' . $chunkType . '" id="' . $uniqueId . '" data-chunk-data="' . htmlspecialchars(json_encode($chunkData), ENT_QUOTES, 'UTF-8') . '">
				<div class="builder-header builder-chunk-header">
					<div class="builder-chunk-title">' . $chunkPreview . '</div>
					<div class="builder-toggle builder-chunk-toggle">&nbsp;</div>
					<div class="builder-chunk-actions">
						<div class="builder-chunk-actions-button exclude-from-toggle show-on-desktop ' . $desktopIconClass . '" data-show-on-desktop="' . $desktopVisibility . '" title="Show on desktop">
						<i class="fas fa-desktop" ></i>
						</div>
						<div class="builder-chunk-actions-button exclude-from-toggle show-on-mobile ' . $mobileIconClass . '" data-show-on-mobile="' . $mobileVisibility . '" title="Show on mobile">
						<i class="fas fa-mobile-alt" ></i>
						</div>
						<span>&nbsp;|&nbsp;</span>
						<div class="builder-chunk-actions-button add-chunk-wrapper builder-add-new-chunk-above exclude-from-toggle" title="Add chunk below">
						<span class="add-chunk" data-chunk-id="' . $chunkId . '"><i class="fas fa-plus"></i></span>
						</div>
						<div class="builder-chunk-actions-button exclude-from-toggle duplicate-chunk" title="Duplicate chunk">
						<i class="fa-regular fa-copy"></i>
						</div>
						<div class="builder-chunk-actions-button remove-element remove-chunk exclude-from-toggle" title="Remove chunk">
						<i class="fas fa-times"></i>
						</div>
					</div>
				</div>
				<div class="builder-chunk-body">
					' . generate_chunk_form_interface($chunkType, $rowId, $columnId, $chunkId, $chunkData, $uniqueId) . '
				</div>
			</div>';
	return $html;
}





function generate_chunk_form_interface($chunkType, $rowId, $columnId, $chunkId, $chunkData, $uniqueId)
{
	// Start output buffering to capture HTML
	ob_start();

	$activeTab = $chunkData['activeTab'] ?? 'content';

	// Define tabs and their labels
	$tabs = array(
		'content' => 'Content',
		'settings' => 'Settings',
		'code' => 'HTML Code'
	);

	echo '<div class="chunk-tabs">';
	foreach ($tabs as $tab => $label) {
		$isActive = $tab === $activeTab ? 'active' : '';
		$additionalClasses = '';
		if ($tab == 'code') {
			$additionalClasses .= 'refresh-chunk-code';
		}
		echo "<div class=\"chunk-tab $isActive $additionalClasses\" data-target=\"#{$uniqueId}-chunk-{$tab}-container\">{$label}</div>";
	}

	echo '</div>';

	// Content tab content
	echo "<div class='tab-content chunk-content' id='{$uniqueId}-chunk-content-container' " . ($activeTab !== 'content' ? "style='display:none;'" : "") . ">";
	echo "<form id='{$uniqueId}-chunk-fields' class='chunk-fields-form'>";

	render_chunk_fields($chunkType, $chunkData, $uniqueId);
	echo "</form>";
	echo "</div>"; // Close chunk-content container

	// Settings tab content
	echo "<div class='tab-content chunk-settings' id='{$uniqueId}-chunk-settings-container' " . ($activeTab !== 'settings' ? "style='display:none;'" : "") . ">";
	echo "<form id='{$uniqueId}-chunk-settings' class='chunk-settings-form'>";

	echo render_chunk_settings($chunkType, $chunkData, $uniqueId);
	echo "</form>";
	echo "</div>"; // Close chunk-settings div

	// HTML Code tab content
	echo "<div class='tab-content chunk-code' id='{$uniqueId}-chunk-code-container' " . ($activeTab !== 'code' ? "style='display:none;'" : "") . ">";
	echo "<div class='tab-content-actions'>";
	echo "<button class='wiz-button green copy-chunk-code' title='Copy HTML Code' data-code-in='#{$uniqueId}-chunk-code'><i class='fa-regular fa-copy'></i>&nbsp;&nbsp;Copy Code</button>";
	echo "</div>"; // Close chunk-code-actions div
	echo "<form id='{$uniqueId}-chunk-code' class='chunk-code-form'>";
	echo "<div class='chunk-html-code'>";
	echo "<pre><code>";
	//echo render_chunk_code( $chunkData );
	echo 'Loading HTML code...';
	echo "</code></pre>";
	echo "</div>"; // Close chunk-html-code div
	echo "</form>";
	echo "</div>"; // Close chunk-settings div

	// Return the captured HTML
	return ob_get_clean();
}






function render_chunk_settings($chunkType, $chunkData, $uniqueId)
{
	$showChunkWrap = false;
	switch ($chunkType) {
		case 'text':
			$settings = array(
				'chunk_classes',
				'chunk_padding',
				'p_padding',
				'div',
				'base_text_color',
				'force_white_text_devices',
				'div',
				'background_settings'
			);

			break;
		case 'html':
			$settings = array(
				'chunk_classes',
				'chunk_padding',
				'div',
				'base_text_color',
				'force_white_text_devices',
				'div',
				'background_settings',
				'chunk_wrap_hide_end'
			);
			$showChunkWrap = true;
			break;
		case 'image':
			$settings = array(
				'chunk_classes',
				'chunk_padding',
				'div',
				'background_settings'
			);

			break;
		case 'button':
			$settings = array(
				'chunk_classes',
				'chunk_padding',
				'div',
				'background_settings'
			);
			break;
		case 'spacer':
			$settings = array(
				'chunk_classes',
				'div',
				'background_settings'
			);
			break;
		case 'snippet':
			$settings = array(
				'chunk_classes',
				'div',
				'background_settings'
			);
			break;
	}
	echo "<div class='chunk-inner-content'>";
	show_specific_chunk_settings($chunkData, $uniqueId, $settings, $chunkType, $showChunkWrap);
	echo "</div>"; // Close chunk-inner-content div

}

add_action('wp_ajax_get_utm_term_fieldset_ajax', 'get_utm_term_fieldset_ajax');
function get_utm_term_fieldset_ajax()
{
	// Verify the nonce for security
	$nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
	if (!wp_verify_nonce($nonce, 'template-editor')) {
		wp_send_json_error(['message' => 'Nonce verification failed']);
		return;
	}

	// Use isset() and is_numeric() to check if index is set and is a valid number
	if (!isset($_POST['index']) || !is_numeric($_POST['index'])) {
		wp_send_json_error(['message' => 'Missing or invalid required parameter: index']);
		return;
	}

	$index = intval($_POST['index']); // Convert to integer
	$key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
	$value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';

	$fieldsetHtml = get_utm_term_fieldset($index, $key, $value);
	wp_send_json_success(['fieldsetHtml' => $fieldsetHtml]);
}
function get_utm_term_fieldset($index, $key, $value)
{
	return '
	<div class="builder-field-wrapper flex utm_fields_wrapper">
		<input type="text" name="key_' . $index . '"
			class="builder-field" placeholder="utm_something"
			value="' . esc_attr($key) . '">
		<span>=</span>
		<input type="text" name="value_' . $index . '"
			class="builder-field" placeholder="value"
			value="' . esc_attr($value) . '">
		<i class="fa-solid fa-trash-can remove_utm_parameter"></i>
	</div>
	';
}

function show_specific_chunk_settings($chunkData, $uniqueId, $settings, $chunkType, $showChunkWrap = false)
{

	$settingsActive = true; // default to showing the chunk settings

	$chunkSettings = $chunkData['settings'] ?? [];

	if ($showChunkWrap) {


		echo "<div class='builder-field-group flex'>"; // Start the chunk wrap setting

		$chunkWrap = $chunkSettings['chunk_wrap'] ?? false;

		$settingsActive = $chunkWrap === true ? 'active' : '';
		echo "<div class='builder-field-wrapper flex'>";
		$uniqueIdchunkWrap = $uniqueId . 'chunk_wrap';
		$chunkWrapChecked = $chunkWrap ? 'checked' : '';
		$chunkWrapActive = $chunkWrap ? 'active' : '';
		$chunkWrapClass = $chunkWrap ? 'fa-solid' : 'fa-regular';


		echo "<div class='wiz-checkbox-toggle'>";

		echo "<input type='checkbox' class='wiz-check-toggle toggle-chunk-wrap-input' id='$uniqueIdchunkWrap' name='chunk_wrap' hidden $chunkWrapChecked>";
		echo "<label for='$uniqueIdchunkWrap' class='wiz-check-toggle-display toggle_chunk_wrap $chunkWrapActive'><i class='$chunkWrapClass fa-2x fa-square-check'></i></label>";
		echo "<label class='checkbox-toggle-label'>Standard Chunk Wrap</label>";
		echo "</div>";
		echo "</div>";
		echo "</div>";
	}

	if ($showChunkWrap) {
		echo "<div class='chunk-wrap-hide-settings $settingsActive'>";
	}

	echo "<div class='chunk-settings-section chunk-general-settings'>";
	echo "<div class='builder-field-group flex'>"; // Start the main wrapper

	foreach ($settings as $setting) {

		if ($setting !== 'div') {
			switch ($setting) {
				case 'chunk_classes':
					$chunkClasses = $chunkSettings['chunk_classes'] ?? '';
					echo "<div class='builder-field-wrapper chunk-classes'><label for='{$uniqueId}-chunk-classes'>Chunk Classes</label>";
					echo "<input type='text' name='chunk_classes' id='{$uniqueId}-chunk-classes' value='{$chunkClasses}'>";
					echo "</div>";
					break;
				case 'chunk_padding':
					$defaultPadding = $chunkType === 'text' ? '20px' : '0';
					$chunkPadding = $chunkSettings['chunk_padding'] ?? $defaultPadding;
					echo "<div class='builder-field-wrapper chunk-padding small-input'><label for='{$uniqueId}-chunk-padding'>Chunk Padding</label>";
					echo "<input type='text' name='chunk_padding' id='{$uniqueId}-chunk-padding' value='{$chunkPadding}'>";
					echo "</div>";
					break;
				case 'p_padding':
					$pPadding = $chunkSettings['p_padding'] ?? false;
					$uniqueIdPpadding = $uniqueId . 'p_padding';
					$pPaddingChecked = $pPadding ? 'checked' : '';
					$pPaddingActive = $pPadding ? 'active' : '';
					$npPaddingClass = $pPadding ? 'fa-solid' : 'fa-regular';

					echo "<div class='builder-field-wrapper'>";
					echo "<div class='wiz-checkbox-toggle'>";

					echo "<input type='checkbox' class='wiz-check-toggle' id='$uniqueIdPpadding' name='p_padding' hidden $pPaddingChecked>";
					echo "<label for='$uniqueIdPpadding' class='wiz-check-toggle-display $pPaddingActive'><i class='$npPaddingClass fa-2x fa-square-check'></i></label>";
					echo "<label class='checkbox-toggle-label'>Pad " . htmlentities('<p>') . "'s</label>";
					echo "</div>";
					echo "</div>";
					break;
				case 'base_text_color':
					$baseTextColor = $chunkSettings['text_base_color'] ?? '#000000';
					echo "<div class='builder-field-wrapper base-text-color centered'><label for='{$uniqueId}-text-base-color'>Base Text Color</label>";
					echo "<input class='builder-colorpicker' type='color' name='text_base_color' id='{$uniqueId}-text-base-color' data-color-value='{$baseTextColor}'>";
					echo "</div>";
					break;

				case 'force_white_text_devices':
					$forceWhiteTextDevices = [
						['id' => $uniqueId . '_force-white-text-desktop', 'name' => 'force_white_text_on_desktop', 'display' => 'desktop', 'label' => '<i class="fa-solid fa-desktop"></i>'],
						['id' => $uniqueId . '_force-white-text-mobile', 'name' => 'force_white_text_on_mobile', 'display' => 'mobile', 'label' => '<i class="fa-solid fa-mobile-screen-button"></i>']
					];

					echo "<div class='button-group-wrapper builder-field-wrapper chunk-force-white-text-devices'>";
					echo "<label class='button-group-label'>Force Gmail white text on:</label>";
					echo "<div class='button-group checkbox'>";
					foreach ($forceWhiteTextDevices as $opt) {
						$fieldID = $opt['id'];
						$isChecked = $chunkSettings[$opt['name']] ?? false;
						$checkVal = $isChecked ? 'true' : 'false';
						$checkedAtt = $isChecked ? 'checked' : '';

						echo "<input type='checkbox' id='{$fieldID}' name='{$opt['name']}'
							value='$checkVal' $checkedAtt>";
						echo "<label for='{$fieldID}' class='button-label' title='{$opt['display']}'>";
						echo $opt['label'];
						echo "</label>";
					}
					echo "</div>";
					echo "</div>";
					break;
				case 'background_settings':
					echo generateBackgroundSettingsModule($chunkSettings, '');
					break;
			}
		} else {
			echo "</div>
			<div class='builder-field-group flex'>";
		}
	}

	if ($showChunkWrap) {
		echo "</div>"; // Close the chunk wrap hide settings div
	}

	echo "
		</div>"; // Close the main wrapper div
	echo "</div>"; // Close the general settings div
}



function render_chunk_fields($chunkType, $chunkData, $uniqueId)
{
	// Chunk specific form fields
	echo "<div class='chunk-inner-content'>";
	echo "<form id='{$uniqueId}-chunk-fields-form'>";
	switch ($chunkType) {
		case 'text':
			$existingContent = isset($chunkData['fields']['plain_text_content']) ? $chunkData['fields']['plain_text_content'] : 'Enter your content here...';
			$editorMode = $chunkData['editor_mode'] ?? 'light';

			echo '<textarea class="wiz-wysiwyg" name="plain_text_content" id="' . $uniqueId . '-wiz-wysiwyg" data-editor-mode="' . $editorMode . '">' . $existingContent . '</textarea>';


			break;
		case 'html':
			$existingContent = isset($chunkData['fields']['raw_html_content']) ? $chunkData['fields']['raw_html_content'] : '<p>Enter your HTML here...</p>';
			echo '<textarea class="wiz-html-block" name="raw_html_content" id="' . $uniqueId . '-raw-html">' . $existingContent . '</textarea>';

			break;
		case 'image':
			$imageUrl = $chunkData['fields']['image_url'] ?? 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/full-width-image.jpg';
			$imageLink = $chunkData['fields']['image_link'] ?? 'https://www.idtech.com';
			$imageAlt = $chunkData['fields']['image_alt'] ?? '';

			echo "<div class='builder-field-group flex'>";
			echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-image-url'>Image URL</label><input type='text' name='image_url' id='{$uniqueId}-image-url' value='{$imageUrl}' placeholder='https://'></div>";
			echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-image-link'>Image Link</label><input type='text' name='image_link' id='{$uniqueId}-image-link' value='{$imageLink}' placeholder='https://'></div>";
			echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-image-alt'>Image Alt</label><input type='text' name='image_alt' id='{$uniqueId}-image-alt' value='{$imageAlt}' placeholder='Describe the image or leave blank'></div>";
			echo "</div>"; // close builder-field-group

			break;
		case 'button':
			$buttonBgColor = $chunkData['fields']['button_fill_color'] ?? '#343434';
			$buttonFontSize = $chunkData['fields']['button_font_size'] ?? '1.1em';
			$buttonTextColor = $chunkData['fields']['button_text_color'] ?? '#ffffff';
			$buttonBorderColor = $chunkData['fields']['button_border_color'] ?? '#343434';
			$buttonBorderSize = $chunkData['fields']['button_border_size'] ?? '1px';
			$buttonBorderRadius = $chunkData['fields']['button_border_radius'] ?? '30px';
			$buttonPadding = $chunkData['fields']['button_padding'] ?? '15px 60px';
			$buttonAlign = $chunkData['fields']['button_align'] ?? 'center';

			$buttonLink = $chunkData['fields']['button_link'] ?? 'https://www.idtech.com';
			$buttonCta = htmlspecialchars($chunkData['fields']['button_text'] ?? 'Click Here', ENT_QUOTES);



			// Button Background Color

			echo "<div class='builder-field-group button-options-group flex'>";
			echo "<div class='builder-field-wrapper background-color'><label for='{$uniqueId}-button-background-color'>Fill</label>";
			echo "<input class='builder-colorpicker' type='color' name='button_fill_color' id='{$uniqueId}-button-background-color' data-color-value='{$buttonBgColor}'>";
			echo "</div>";



			// Button Text Color
			echo "<div class='builder-field-wrapper button-text-color'><label for='{$uniqueId}-button-text-color'>Text</label>";
			echo "<input class='builder-colorpicker' type='color' name='button_text_color' id='{$uniqueId}-button-text-color' data-color-value='{$buttonTextColor}' value='{$buttonTextColor}'>";
			echo "</div>";

			// Button Padding
			echo "<div class='builder-field-wrapper button-padding small-input'><label for='{$uniqueId}-button-padding'>Btn Padding</label>";
			echo "<input type='text' name='button_padding' id='{$uniqueId}-button-padding' value='{$buttonPadding}'>";
			echo "</div>";

			// Button Font Size
			echo "<div class='builder-field-wrapper button-font-size tiny-input'><label for='{$uniqueId}-button-font-size'>Font Size</label>";
			echo "<input type='text' name='button_font_size' id='{$uniqueId}-button-font-size' value='{$buttonFontSize}'>";
			echo "</div>";

			// Alignment Options
			echo "<div class='button-group-wrapper builder-field-wrapper button-align'><label class='button-group-label'>Align</label>";
			echo "<div class='button-group radio'>";

			$alignOptions = [
				['id' => $uniqueId . '_btn_align_left', 'value' => 'left', 'label' => '<i class="fa-solid fa-align-left"></i>', 'checked' => 'checked'],
				['id' => $uniqueId . '_btn_align_center', 'value' => 'center', 'label' => '<i class="fa-solid fa-align-center"></i>'],
				['id' => $uniqueId . '_btn_align_right', 'value' => 'right', 'label' => '<i class="fa-solid fa-align-right"></i>'],


			];

			foreach ($alignOptions as $opt) {
				$isChecked = isset($buttonAlign) && $buttonAlign === $opt['value'] ? 'checked' : '';
				$fieldID = $opt['id'];
				$label = $opt['label'];
				$value = $opt['value'];

				echo "<input type='radio' id='{$fieldID}' name='button_align' value='{$value}' hidden {$isChecked}>";
				echo "<label for='{$fieldID}' class='button-label'>{$label}</label>";
			}

			echo "</div>";

			echo "</div>";

			// Button Border Color
			echo "<div class='builder-field-wrapper button-border-color'><label for='{$uniqueId}-button-border-color'>Stroke</label>";
			echo "<input class='builder-colorpicker' type='color' name='button_border_color' id='{$uniqueId}-button-border-color' data-color-value='{$buttonBorderColor}' value='{$buttonBorderColor}'>";
			echo "</div>";

			// Button Border Size
			echo "<div class='builder-field-wrapper button-border-size tiny-input'><label for='{$uniqueId}-button-border-size'>Border</label>";
			echo "<input type='text' name='button_border_size' id='{$uniqueId}-button-border-size' value='{$buttonBorderSize}'>";
			echo "</div>";

			// Button Border Radius
			echo "<div class='builder-field-wrapper button-border-radius tiny-input'><label for='{$uniqueId}-button-border-radius'>Radius</label>";
			echo "<input type='text' name='button_border_radius' id='{$uniqueId}-button-border-radius' value='{$buttonBorderRadius}'>";
			echo "</div>";



			echo "</div>";
			echo "<div class='builder-field-group flex'>";
			echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-button-text'>CTA Text</label><input type='text' name='button_text' id='{$uniqueId}-button-text' value='{$buttonCta}' placeholder='Click here now!'></div>";
			echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-button-link'>Button Link</label><input type='text' name='button_link' id='{$uniqueId}-button-link' value='{$buttonLink}' placeholder='https://'></div>";
			echo "</div>";



			break;
		case 'spacer':
			$spacerHeight = $chunkData['fields']['spacer_height'] ?? '60px';

			echo "<div class='builder-field-group'>";
			echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-spacer-height'>Spacer Height</label><input type='text' name='spacer_height' id='{$uniqueId}-spacer-height' value='{$spacerHeight}' placeholder='px, em, etc'></div>";
			echo "</div>"; // Close builder-field-group

			break;
		case 'snippet':
			$selectedSnippet = $chunkData['fields']['select_snippet'] ?? '';
			$snippetEditLink = get_permalink($selectedSnippet) ?? get_post_type_archive_link('wysiwyg_snippet');

			echo "<div class='builder-field-group flex'>";
			echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-snippet-id'>Select Snippet</label>";
			echo "<select id='{$uniqueId}-snippet-id' name='select_snippet'>";
			$snippetsForSelect = get_snippets_for_select();
			$noSelectionSelected = $selectedSnippet ? '' : 'selected';
			echo "<option value='' {$noSelectionSelected} disabled>Select a Snippet</option>";
			foreach ($snippetsForSelect as $snippetId => $snippetTitle) {
				echo "<option value='{$snippetId}' " . ($selectedSnippet == $snippetId ? 'selected' : '') . ">{$snippetTitle}</option>";
			}
			echo "</select>";
			echo "</div>";
			$showEditLinkClass = 'hidden';
			if ($selectedSnippet) {
				$showEditLinkClass = 'visible';
			}
			echo "<div class='snippet-edit-link $showEditLinkClass'><a href='".$snippetEditLink."' target='_blank'>Edit Snippet</a></div>";
			echo "</div>"; // Close chunk-field-group

			break;
		default:
			echo "No valid chunk type set!";
			break;
	}
	echo "</form>";
	echo "</div>"; // Close chunk-inner-content
}

function generateBackgroundSettingsModule($backgroundSettings, $uniqueId = '', $typeLabel = true)
{
	// If no unique ID is passed, generate one for use in ID/label attributes for repeated field names (like background settings)
	$uniqueTempId = $uniqueId != '' ? $uniqueId : '_' . uniqid();
	//echo ('Chunk Data for '.$uniqueId.': '. print_r($chunkData, true));
	$chunkBackgroundType = $backgroundSettings[$uniqueId . 'background-type'] ?? 'none';
	$chunkBackgroundColor = $backgroundSettings[$uniqueId . 'background-color'] ?? '#ffffff';
	$forceBackground = isset($backgroundSettings[$uniqueId . 'force-background']) && $backgroundSettings[$uniqueId . 'force-background'] == true;

	// Background Type Options
	$backgroundOptions = [
		['id' => $uniqueTempId . 'bg-none', 'value' => 'none', 'label' => '<i class="fas fa-ban"></i> None', 'checked' => 'checked'],
		['id' => $uniqueTempId . 'bg-solid', 'value' => 'solid', 'label' => '<i class="fas fa-fill"></i> Solid'],
		['id' => $uniqueTempId . 'bg-image', 'value' => 'image', 'label' => '<i class="fas fa-image"></i> Image'],
		['id' => $uniqueTempId . 'bg-custom', 'value' => 'custom', 'label' => '<i class="fa-solid fa-code"></i> Custom'],
	];

	ob_start();
?>
	<div class='chunk-settings-section chunk-background-settings'>

		<div class="chunk-background-type-wrapper">
			<div class='button-group-wrapper chunk-background-type'>
				<?php
				if ($typeLabel) { ?>
					<label class="button-group-label">Background Type</label>
				<?php } ?>
				<div class="button-group radio">
					<?php foreach ($backgroundOptions as $opt) : ?>
						<?php
						// Check if this option is selected
						$isChecked = isset($chunkBackgroundType) && $chunkBackgroundType === $opt['value'] ? 'checked' : '';
						$fieldID = $opt['id'];
						?>
						<input type='radio' id='<?php echo $fieldID; ?>' name='<?php echo $uniqueId . 'background-type'; ?>'
							value='<?php echo $opt['value']; ?>' hidden <?php echo $isChecked; ?>
							class="background-type-select">
						<label class="button-label" for='<?php echo $fieldID; ?>'>
							<?php echo $opt['label']; ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<?php
		$showClass = 'hidden';
		if (isset($chunkBackgroundType) && $chunkBackgroundType != 'none') {
			$showClass = '';
		}


		?>
		<div class='chunk-settings-section chunk-background-color-settings <?php echo $showClass; ?>'>
			<label>Background Color</label>
			<div class='background-color'>
				<div class="builder-field-wrapper background-color"><label
						for="<?php echo $uniqueId . 'background-color'; ?>"></label>
					<input class="builder-colorpicker" type="color" name="<?php echo $uniqueId . 'background-color'; ?>"
						id="<?php echo $uniqueId . 'background-color'; ?>"
						data-color-value="<?php echo $chunkBackgroundColor; ?>">
				</div>
				<div class="builder-field-wrapper">

					<div class="wiz-checkbox-toggle">
						<input type="checkbox" class="wiz-check-toggle" id="<?php echo $uniqueId . 'force-background'; ?>"
							name="<?php echo $uniqueId . 'force-background'; ?>" hidden <?php echo $forceBackground ? 'checked' : ''; ?>>
						<label for="<?php echo $uniqueId . 'force-background'; ?>"
							class="wiz-check-toggle-display <?php echo $forceBackground ? 'active' : ''; ?>"><i
								class="<?php echo $forceBackground ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
						<label class="checkbox-toggle-label">Force BG in all modes</label>
					</div>


				</div>
			</div>

		</div>
		<?php
		$showClass = 'hidden';
		if (
			isset($chunkBackgroundType) && $chunkBackgroundType == 'image'
		) {
			$showClass = '';
		}
		?>

		<div class='chunk-settings-section chunk-background-image-settings <?php echo $showClass; ?>'>

			<label>Background Image</label>
			<div class="chunk-settings-section-fields flex">

				<div class="builder-field-wrapper chunk-background-image-url">
					<label for="<?php echo $uniqueId . 'background-image-url'; ?>">Image URL</label>
					<input type="text" name="<?php echo $uniqueId . 'background-image-url'; ?>"
						id="<?php echo $uniqueId . 'background-image-url'; ?>" class="builder-text-input"
						value="<?php echo $backgroundSettings[$uniqueId . 'background-image-url'] ?? ''; ?>"
						placeholder="https://...">
				</div>
				<div class="builder-field-wrapper chunk-background-image-position">
					<label for="<?php echo $uniqueId . 'background-image-position'; ?>">Position</label>
					<input type="text" name="<?php echo $uniqueId . 'background-image-position'; ?>"
						id="<?php echo $uniqueId . 'background-image-position'; ?>" class="builder-text-input"
						value="<?php echo $backgroundSettings[$uniqueId . 'background-image-position'] ?? ''; ?>"
						placeholder="eg center center">
				</div>
				<div class="builder-field-wrapper chunk-background-image-size">
					<label for="<?php echo $uniqueId . 'background-image-size'; ?>">Size</label>
					<input type="text" name="<?php echo $uniqueId . 'background-image-size'; ?>"
						id="<?php echo $uniqueId . 'background-image-size'; ?>" class="builder-text-input"
						value="<?php echo $backgroundSettings[$uniqueId . 'background-image-size'] ?? ''; ?>"
						placeholder="eg 100% 100%">
				</div>

				<?php
				$imageRepeatOptions = [
					['id' => $uniqueId . 'bg-repeat-horizontal', 'value' => 'repeat-x', 'label' => '<i class="fa-solid fa-left-right"></i>'],
					['id' => $uniqueId . 'bg-repeat-vertical', 'value' => 'repeat-y', 'label' => '<i class="fa-solid fa-up-down"></i>']
				];
				?>

				<div class='button-group-wrapper builder-field-wrapper chunk-background-image-repeat'>
					<label class="button-group-label">Repeat</label>
					<div class="button-group checkbox">
						<?php foreach ($imageRepeatOptions as $opt) : ?>
							<?php
							$fieldID = $opt['id'];

							$isChecked = isset($backgroundSettings[$fieldID]) && $backgroundSettings[$fieldID] ? 'checked' : '';

							?>
							<input type='checkbox' id='<?php echo $uniqueTempId . $fieldID; ?>' name='<?php echo $fieldID; ?>'
								value='<?php echo $opt['value']; ?>' <?php echo $isChecked; ?>>
							<label for='<?php echo $uniqueTempId . $fieldID; ?>' class='button-label'>
								<?php echo $opt['label']; ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>


			</div>
		</div>

		<?php
		$showClass = 'hidden';
		if (isset($chunkBackgroundType) && $chunkBackgroundType == 'custom') {
			$showClass = '';
		}
		?>
		<div class="chunk-settings-section chunk-background-custom-settings <?php echo $showClass;
																			?>">
			<label for="<?php echo $uniqueId . 'custom-background-css'; ?>">Custom Background CSS</label>
			<div class="field-description"><strong style="font-style:normal;">Key:value;</strong> pairs, e.g., background-color:red;</div>
			<div class="chunk-settings-section-fields">
				<textarea name="<?php echo $uniqueId . 'custom-background-css'; ?>" class="custom-background-css-input" id="<?php echo $uniqueId . 'custom-background-css'; ?>"><?php echo isset($backgroundSettings[$uniqueId . 'custom-background-css']) ? trim($backgroundSettings[$uniqueId . 'custom-background-css']) : ''; ?></textarea>
			</div>
		</div>
	</div>

<?php

	return ob_get_clean();
}






add_action('wp_ajax_save_wiz_template_data', 'prepare_wiztemplate_for_save');
function prepare_wiztemplate_for_save()
{
	// Check nonce for security
	check_ajax_referer('template-editor', 'security');

	$postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
	$userId = get_current_user_id();
	$templateData = isset($_POST['template_data']) ? json_decode(stripslashes($_POST['template_data']), true) : '';

	// Save the template data
	save_template_data($postId, $userId, $templateData);

	wp_send_json_success(['message' => 'Template saved successfully', 'templateData' => $templateData]);
}



// Save the template data from the ajax call
function save_template_data($postId, $userId, $templateData)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'wiz_templates';

	// Ensure templateData is a JSON string
	if (is_array($templateData)) {
		$templateData = json_encode($templateData);
	}

	// Prepare data for database insertion
	$data = [
		'last_updated' => date('Y-m-d H:i:s'),
		'post_id' => $postId,
		'template_data' => $templateData
	];

	// Check for an existing record
	$existingRecordId = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE post_id = %d", $postId));

	if ($existingRecordId) {
		// Update existing record
		$wpdb->update($table_name, $data, ['id' => $existingRecordId], ['%s', '%d', '%s'], ['%d']);
	} else {
		// Insert new record
		$wpdb->insert($table_name, $data, ['%s', '%d', '%s']);
	}

	// Check for and log any database errors
	if (! empty($wpdb->last_error)) {
		error_log("WordPress database error: " . $wpdb->last_error);
	}
}





function get_snippets_for_select()
{
	$snippetArgs = [
		'post_type' => 'wysiwyg_snippet',
		'posts_per_page' => -1,
		'orderby' => 'post_title',
		'order' => 'ASC'
	];
	$snippets = get_posts($snippetArgs);

	$snippetsData = [];
	foreach ($snippets as $snippet) {
		$snippetsData[$snippet->ID] = $snippet->post_title;
	}

	if ($snippets) {
		return $snippetsData;
	} else {
		return 'No snippets found';
	}
}


function get_visibility_class_and_style($settingsObject)
{

	$desktopVisibility = filter_var($settingsObject['desktop_visibility'] ?? true, FILTER_VALIDATE_BOOLEAN);
	$mobileVisibility = filter_var($settingsObject['mobile_visibility'] ?? true, FILTER_VALIDATE_BOOLEAN);

	// Initialize class and inline style
	$classes = [];
	$inlineStyle = 'display: block;';

	// Determine classes and inline style based on visibility
	if ($desktopVisibility === true && $mobileVisibility === false) {
		// Visible on desktop only
		$classes[] = 'desktop-only';
	} elseif ($desktopVisibility === false && $mobileVisibility === true) {
		// Visible on mobile only
		$classes[] = 'mobile-only';
		$inlineStyle = 'display: none;'; // Hide by default, shown on mobile
	} elseif ($desktopVisibility === false && $mobileVisibility === false) {
		// Hidden on all devices
		$inlineStyle = 'display: none !important;';
	}

	// Join all classes into a single string
	$class = implode(' ', $classes);

	// Return the class and style as an associative array
	return [
		'class' => $class,
		'inlineStyle' => $inlineStyle
	];
}

add_action('wp_ajax_generate_template_html_from_ajax', 'generate_template_html_from_ajax');
function generate_template_html_from_ajax()
{
	$_POST = stripslashes_deep($_POST);

	$templateId = $_POST['template_id'] ?? '';
	$sessionData = isset($_POST['session_data']) ? $_POST['session_data'] : null;
	if ($sessionData) {
		// If session data is provided and valid, use it
		$sessionData = is_string($sessionData) ? json_decode($sessionData, true) : $sessionData;
		$templateData = $sessionData;
	} else {
		// Otherwise, fetch the template data from the database
		$templateData = get_wiztemplate($templateId);
	}
	$templateHtml = generate_template_html($templateData, false);

	if ($templateHtml) {
		// Encode HTML characters and return as plain text
		echo htmlspecialchars($templateHtml);
		die(); // We don't use wp_die here because we're using our custom ajax endpoint
	} else {
		die('Something went wrong');
	}
}



function generate_template_html($templateData, $forEditor = false)
{
	//convert string true/false in booleans through the template data
	convertStringBooleans($templateData);
	$rows = $templateData['rows'] ?? [];
	$templateOptions = $templateData['template_options'] ?? [];
	$message_settings = $templateOptions['message_settings'] ?? [];
	$templateStyles = $templateOptions['template_styles'] ?? [];

	$return = '';

	// Email top
	$return .= idwiz_get_email_top($message_settings, $templateStyles, $rows);

	// Email <body> start
	$return .= idwiz_get_email_body_top($templateStyles);

	// iD Logo Header
	$showIdHeader = filter_var($templateStyles['header-and-footer']['show_id_header'] ?? false, FILTER_VALIDATE_BOOLEAN);
	$showIdFooter = filter_var($templateStyles['header-and-footer']['show_id_footer'] ?? false, FILTER_VALIDATE_BOOLEAN);

	if ($showIdHeader) {
		$return .= idwiz_get_standard_header($templateOptions);
	}

	// Generates cols and chunks from row object
	$return .= get_allRows_html(null, $templateData, null, $forEditor);

	// Email Footer
	if ($showIdFooter) {
		$return .= idwiz_get_standard_footer($templateStyles);
	}

	// Fine print/disclaimer
	if (! empty($message_settings['fine_print_disclaimer'])) {
		$return .= idwiz_get_fine_print_disclaimer($templateOptions);
	}

	// Body Bottom
	$return .= idwiz_get_email_body_bottom();

	// Email Bottom
	$return .= idwiz_get_email_bottom();

	return $return;
}




function get_allRows_html($templateId = null, $templateData = null, $rowIndexes = null, $isEditor = false)
{

	if (!$templateData) {
		$templateData = get_wiztemplate($templateId);
	}
	$rows = $templateData['rows'] ?? [];
	$return = '';

	if ($rowIndexes === null) {
		$rowIndexes = array_keys($rows);
	} elseif (!is_array($rowIndexes)) {
		$rowIndexes = [$rowIndexes];
	}

	foreach ($rowIndexes as $rowIndex) {
		if (isset($rows[$rowIndex])) {
			$return .= get_row_html($templateId, $rowIndex, $templateData, $isEditor);
		}
	}

	return $return;
}

function get_row_html($templateId, $rowIndex, $templateData = null, $isEditor = false)
{
	if (!$templateData) {
		$templateData = get_wiztemplate($templateId);
	}
	$row = $templateData['rows'][$rowIndex] ?? null;
	if (!$row) return '';
	$return = '';

	$rowBackgroundCss = generate_background_css($row['background_settings']);
	$rowBackgroundCssMso = generate_background_css($row['background_settings'], '', true);

	// If this is showing in the editor, add a data attribute to the row
	$rowDataAttr = $isEditor ? 'data-row-index=' . $rowIndex : '';

	$return .= "<div class='row' $rowDataAttr style='font-size:0; width: 100%; margin: 0; padding: 0; " . $rowBackgroundCss . "'>";
	$return .= "<!--[if mso]><table role='presentation' class='row' role='presentation' width='100%' style='$rowBackgroundCssMso white-space:nowrap;width: 100%; border: 0; border-spacing: 0;margin: 0 auto;text-align:center; '><tr><td><![endif]-->";

	$columnSets = $row['columnSets'] ?? [];

	$columnSetsArray = [];
	foreach ($columnSets as $columnSetIndex => $columnSet) {
		//filter out empty columnsets
		// if (empty($columnSet['columns'])) {
		// 	continue;
		// }
		$columnSetsArray[] = get_columnset_html($templateId, $rowIndex, $columnSetIndex, $templateData, $isEditor);
	}
	$return .= implode('', $columnSetsArray);
	$return .= "<!--[if mso]></td></tr></table><![endif]-->";
	$return .= "</div>"; // Close the row layout div

	return $return;
}

function get_columnset_html($templateId, $rowIndex, $columnSetIndex, $templateData = null, $isEditor = false)
{
	if (!$templateData) {
		$templateData = get_idwiz_template($templateId);
	}

	$columnSet = $templateData['rows'][$rowIndex]['columnSets'][$columnSetIndex] ?? null;
	if (!$columnSet) return '';

	$return = '';

	$allColumns = $columnSet['columns'] ?? []; // includes inactive columns

	$magicRtl = '';
	$magicWrap = $columnSet['magic_wrap'] ?? 'off';
	if ($magicWrap == 'on') {
		$magicRtl = 'dir="rtl"';
	}

	$columns = [];
	foreach ($allColumns as $columnIndex => $allColumn) {
		if ($allColumn['activation'] !== 'active') {
			unset($allColumns[$columnIndex]);
		}
	}
	$columns = $allColumns;

	$numActiveColumns = count($allColumns);

	$colSetBackgroundCss = generate_background_css($columnSet['background_settings'], '', false);
	$colSetBackgroundCssMso = generate_background_css($columnSet['background_settings'], '', true);

	$layoutClass = $columnSet['layout'] ?? '';

	$colSetDataAttr = $isEditor ? 'data-columnset-index=' . $columnSetIndex : '';

	$displayTable = $numActiveColumns > 1 ? 'display: table;' : '';

	$return .= "<div class='columnSet $layoutClass' $colSetDataAttr $magicRtl style='$colSetBackgroundCss text-align: center; font-size: 0; width: 100%; " . $displayTable . "'>";
	$return .= "<!--[if mso]><table role='presentation' class='columnSet' role='presentation' width='100%' style='width: 100%; border: 0; border-spacing: 0;margin: 0 auto;text-align: center;'><tr><td style='$colSetBackgroundCssMso'><![endif]-->";
	$return .= "<table role='presentation' width='100%' style='width: 100%; border: 0; border-spacing: 0;margin: 0 auto;'><tr>";


	// If Magic Wrap is on, reverse the order of columns but keep the keys intact
	if ($magicWrap == 'on') {
		$columns = array_reverse($allColumns, true);
	}
	$colsArray = [];
	foreach ($columns as $columnIndex => $column) {
		$colsArray[] = get_column_html($templateId, $rowIndex, $columnSetIndex, $columnIndex, $templateData, $isEditor);
	}

	$return .= implode('', $colsArray);

	$return .= "</tr></table>";
	$return .= "<!--[if mso]></td></tr></table><![endif]-->";
	$return .= "</div>"; // Close the colset layout div

	return $return;
}



function get_column_html($templateId, $rowIndex, $columnSetIndex, $columnIndex, $templateData = null, $isEditor = false)
{
	if (!$templateData) {
		$templateData = get_idwiz_template($templateId);
	}

	$columnSet = $templateData['rows'][$rowIndex]['columnSets'][$columnSetIndex] ?? null;
	if (!$columnSet) return '';
	$layoutClass = $columnSet['layout'] ?? '';
	$mobileWrap = $columnSet['mobile_wrap'] ?? 'on';
	$mobileWrapClass = '';

	if ($mobileWrap == 'on') {
		$mobileWrapClass = 'wrap';
	}
	$columns = $columnSet['columns'];
	$column = $columns[$columnIndex] ?? null;

	if (!$column || $column['activation'] !== 'active') {
		return '';
	}

	$templateStyles = $templateData['template_options']['template_styles'];
	$colValign = $column['settings']['valign'] ? strtolower($column['settings']['valign']) : 'top';

	$colBackgroundCSS = generate_background_css($column['settings'], '');
	$msoColBackgroundCSS = generate_background_css($column['settings'], '', true);

	$columnChunks = $column['chunks'] ?? [];
	$templateWidth = $templateStyles['body-and-background']['template_width'];
	$templateWidth = (int) $templateWidth > 0 ? (int) $templateWidth : 648;

	// Determine column width based on layout and style index
	if ($layoutClass === 'two-col') {
		$columnWidthPx = round($templateWidth / 2, 0);
		$columnWidthPct = 50;
	} elseif ($layoutClass === 'three-col') {
		$columnWidthPx = round($templateWidth / 3, 0);
		$columnWidthPct = 33.33;
	} elseif ($layoutClass === 'sidebar-left') {
		if ($columnIndex === 0) {
			$columnWidthPx = round($templateWidth * 0.33, 0);
			$columnWidthPct = 33.33;
		} else {
			$columnWidthPx = round($templateWidth * 0.67, 0);
			$columnWidthPct = 66.67;
		}
	} elseif ($layoutClass === 'sidebar-right') {
		if ($columnIndex === 0) {
			$columnWidthPx = round($templateWidth * 0.67, 0);
			$columnWidthPct = 66.67;
		} else {
			$columnWidthPx = round($templateWidth * 0.33, 0);
			$columnWidthPct = 33.33;
		}
	} else {
		// Default layout (single column)
		$columnWidthPx = $templateWidth;
		$columnWidthPct = 100;
	}

	$columnStyle = "width: {$columnWidthPct}%; max-width: {$columnWidthPx}px; font-size: {$templateStyles['font-styles']['template_font_size']}; vertical-align: {$colValign}; text-align: left; display: inline-block;padding: 1px solid transparent;";

	$columnDataAttr = $isEditor ? 'data-column-index=' . $columnIndex : '';
	$return = "<div class='column $mobileWrapClass' $columnDataAttr style='$columnStyle $colBackgroundCSS' dir='ltr'>";
	$return .= "<!--[if mso]><td style='width:{$columnWidthPx}px; $msoColBackgroundCSS' width='{$columnWidthPx}' valign='{$colValign}'><![endif]-->";


	foreach ($columnChunks as $chunkIndex => $chunk) {
		$return .= idwiz_get_chunk_template($templateId, $rowIndex, $columnSetIndex, $columnIndex, $chunkIndex, $templateData, $isEditor);
	}

	$return .= "<!--[if mso]></td><![endif]-->";
	$return .= "</div>"; // Close .column div



	return $return;
}


function idwiz_get_chunk_template($templateId, $rowIndex, $columnSetIndex, $columnIndex, $chunkIndex, $templateData = null, $isEditor = false)
{
	if (!$templateData) {
		$templateData = get_idwiz_template($templateId);
	}

	$chunk = $templateData['rows'][$rowIndex]['columnSets'][$columnSetIndex]['columns'][$columnIndex]['chunks'][$chunkIndex] ?? null;
	if (!$chunk) return '';

	$chunkType = $chunk['field_type'];
	$templateOptions = $templateData['template_options'];
	$return = '';

	switch ($chunkType) {
		case 'text':
			$return = idwiz_get_plain_text_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
			break;
		case 'image':
			$return = idwiz_get_image_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
			break;
		case 'button':
			$return = idwiz_get_button_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
			break;
		case 'spacer':
			$return = idwiz_get_spacer_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
			break;
		case 'snippet':
			$return = idwiz_get_snippet_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
			break;
		case 'html':
			$return = idwiz_get_raw_html_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
			break;
	}

	return $return;
}



add_action('wp_ajax_get_wiztemplate_part_html', 'get_wiztemplate_part_html');

function get_wiztemplate_part_html()
{
	// Verify the nonce for security.
	$nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
	if (!wp_verify_nonce($nonce, 'template-editor')) {
		wp_send_json_error(['message' => 'Nonce verification failed']);
		return;
	}

	// Get the template data
	$templateData = json_decode(stripslashes($_POST['templateData']), true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		wp_send_json_error(['message' => 'Invalid template data']);
		return;
	}


	$templateOptions = $templateData['template_options'];
	$templateStyles = $templateOptions['template_styles'];
	$templateSettings = $templateOptions['message_settings'];
	$rows = $templateData['rows'];

	// Get and sanitize the input parameters
	$templateId = isset($_POST['templateId']) ? intval($_POST['templateId']) : 0;
	$isEditor = isset($_POST['isEditor']) ? ($_POST['isEditor'] === 'false' ? false : true) : false;
	$partType = isset($_POST['partType']) ? sanitize_text_field($_POST['partType']) : null;


	$rowIndex = isset($_POST['rowIndex']) ? intval($_POST['rowIndex']) : null;
	$columnSetIndex = isset($_POST['columnSetIndex']) ? intval($_POST['columnSetIndex']) : null;
	$columnIndex = isset($_POST['columnIndex']) ? intval($_POST['columnIndex']) : null;
	$chunkIndex = isset($_POST['chunkIndex']) ? intval($_POST['chunkIndex']) : null;



	// Generate the requested HTML based on the part type
	$html = '';
	switch ($partType) {
		case 'fullTemplate':
			$html = generate_template_html($templateData, true);
			break;
		case 'emailTop':
			$html = idwiz_get_email_top($templateSettings, $templateStyles, $rows);
			break;
		case 'emailBottom':
			$html = idwiz_get_email_bottom();
			break;
		case 'bodyWrap':
			$html = idwiz_get_email_body_top($templateStyles) . idwiz_get_email_body_bottom();
			break;
		case 'bodyTop':
			$html = idwiz_get_email_body_top($templateStyles);
			break;
		case 'bodyBottom':
			$html = idwiz_get_email_body_bottom();
			break;
		case 'footer':
			$html = idwiz_get_standard_footer($templateStyles);
			break;
		case 'finePrintDisclaimer':
			$html = idwiz_get_fine_print_disclaimer($templateOptions);
			break;
		case 'allRows':
			$html = get_allRows_html($templateId, $templateData, null, $isEditor);
			break;
		case 'row':
			$html = get_row_html($templateId, $rowIndex, $templateData, $isEditor);
			break;
		case 'columnset':
			$html = get_columnset_html($templateId, $rowIndex, $columnSetIndex, $templateData, $isEditor);
			break;
		case 'column':
			$html = get_column_html($templateId, $rowIndex, $columnSetIndex, $columnIndex, $templateData, $isEditor);
			break;
		case 'chunk':
			$html = idwiz_get_chunk_template($templateId, $rowIndex, $columnSetIndex, $columnIndex, $chunkIndex, $templateData, $isEditor);
			break;
		default:
			wp_send_json_error(['message' => 'Failed to generate HTML. Invalid part type passed: ' . $partType]);
	}

	// Send the response
	if ($html) {
		wp_send_json_success(['html' => $html]);
	} else {
		wp_send_json_error(['message' => 'Failed to generate HTML']);
	}
}


add_action('wp_ajax_generate_background_css_ajax', 'generate_background_css_ajax');
function generate_background_css_ajax()
{
	$backgroundSettings = $_POST['backgroundSettings'] ?? [];
	$prefix = $_POST['prefix'] ?? '';
	$css = generate_background_css($backgroundSettings, $prefix);
	echo json_encode($css);
	die();
}
function generate_background_css($backgroundSettings, $prefix = '', $forMso = false)
{
	$bg_type = $backgroundSettings[$prefix . 'background-type'] ?? 'none';
	$css = [];

	switch ($bg_type) {


		case 'custom':
			// No fallbacks, just send the custom CSS as-is
			$custom_css = $backgroundSettings[$prefix . 'custom-background-css'] ? trim($backgroundSettings[$prefix . 'custom-background-css']) : '';
			$css[] = $custom_css;
			break;
		case 'image':
			// Image properties
			$image_url = $backgroundSettings[$prefix . 'background-image-url'];
			$position = $backgroundSettings[$prefix . 'background-image-position'] != '' ? $backgroundSettings[$prefix . 'background-image-position'] : 'center';
			$size = $backgroundSettings[$prefix . 'background-image-size'] != '' ? $backgroundSettings[$prefix . 'background-image-size'] : 'cover';

			// Fallback color and additional properties
			$fallback_color = $backgroundSettings[$prefix . 'background-color'] ?? 'transparent';
			if ($fallback_color == 'rgba(0,0,0,0)') {
				$fallback_color = 'transparent';
			}

			// Only include fallback color for mso clients
			if ($forMso) {
				$css[] = "background-color: $fallback_color;";
			}
			if ($image_url) {
				$css[] = "background-image: url($image_url);";
				$css[] = "background-position: $position;";
				$css[] = "background-size: $size;";
			}



			// For outlook, exclude image position stuff since not necessary
			if (! $forMso) {
				// Background repeat
				$bgRepeatY = $backgroundSettings[$prefix . 'background-repeat-vertical'] ?? false;
				$bgRepeatX = $backgroundSettings[$prefix . 'background-repeat-horizontal'] ?? false;
				if ($bgRepeatY === true && $bgRepeatX === true) {
					$css[] = "background-repeat: repeat;";
				} else if ($bgRepeatY === true) {
					$css[] = "background-repeat: repeat-y;";
				} else if ($bgRepeatX === true) {
					$css[] = "background-repeat: repeat-x;";
				} else {
					$css[] = "background-repeat: no-repeat;";
				}
			}

			break;

		case 'solid':
			// Solid color background
			$color = $backgroundSettings[$prefix . 'background-color'] ?? 'transparent';
			if ($color == 'rgba(0,0,0,0)') {
				$color = 'transparent';
			}
			$css[] = "background-color: $color;";

			break;

		case 'none':
			// Transparent background
			$css[] = "background-color: transparent;";
			break;
	}

	// Check for forced background color
	$forceBackground = $backgroundSettings[$prefix . 'force-background'] ?? false;

	// If a background color is set and not transparent, force it using linear gradient
	if (
		$forceBackground == 'true'
		&& $bg_type != 'none'
		&& isset($backgroundSettings[$prefix . 'background-color'])
		&& $backgroundSettings[$prefix . 'background-color'] != 'transparent'
		&& ! $forMso
	) {
		$css[] = "background-image: linear-gradient({$backgroundSettings[$prefix . 'background-color']}, {$backgroundSettings[$prefix . 'background-color']});";
	}



	return implode(" ", $css);
}


add_action('wp_ajax_upload_mockup', 'handle_mockup_upload');
function handle_mockup_upload()
{
	// Define ABSPATH if not already defined
	if (!defined('ABSPATH')) {
		define('ABSPATH', dirname(__FILE__) . '/../../../');
	}

	// Include necessary WordPress files
	require_once(ABSPATH . 'wp-load.php');
	require_once(ABSPATH . 'wp-admin/includes/file.php');
	require_once(ABSPATH . 'wp-admin/includes/image.php');
	require_once(ABSPATH . 'wp-admin/includes/media.php');

	if (!isset($_FILES['file'])) {
		wp_send_json_error('No file uploaded');
		wp_die();
	}

	$file = $_FILES['file'];
	$upload = wp_handle_upload($file, array('test_form' => false));

	if (isset($upload['error'])) {
		wp_send_json_error($upload['error']);
		wp_die();
	}

	$attachment_id = wp_insert_attachment(array(
		'post_title' => sanitize_file_name($file['name']),
		'post_content' => '',
		'post_type' => 'attachment',
		'post_mime_type' => $file['type'],
		'guid' => $upload['url'],
	), $upload['file']);

	if (is_wp_error($attachment_id)) {
		wp_send_json_error($attachment_id->get_error_message());
		wp_die();
	}

	wp_send_json_success(array('url' => $upload['url']));
	wp_die();
}

function resize_image($file_path, $max_width = 1920, $max_height = 1080)
{
	$image = wp_get_image_editor($file_path);
	if (!is_wp_error($image)) {
		$size = $image->get_size();
		$ratio = min($max_width / $size['width'], $max_height / $size['height']);

		if ($ratio < 1) {
			$new_width = round($size['width'] * $ratio);
			$new_height = round($size['height'] * $ratio);
			$image->resize($new_width, $new_height, false);
			$image->save($file_path);
		}
	}
	return $file_path;
}

function get_preview_text_hack()
{
	$return = '';
	for ($i = 0; $i <= 10; $i++) {
		$return .= '&#847; &zwnj; &nbsp; &#8199; ';
	}
	return $return;
}


add_action('wp_ajax_get_template_data_profile_ajax', 'get_template_data_profile_ajax');
function get_template_data_profile_ajax()
{
	// Verify the nonce for security.
	$nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
	if (!wp_verify_nonce($nonce, 'template-editor')) {
		wp_send_json_error(['message' => 'Nonce verification failed']);
		return;
	}

	$profileId = $_POST['profileId'];
	$profile = get_template_data_profile($profileId);
	if (!$profile) {
		wp_send_json_error('Profile not found');
	}
	wp_send_json_success(['templateData' => $profile]);
}

function get_template_data_profile($profileId)
{
	$allProfiles = get_template_data_profiles();
	foreach ($allProfiles as $profile) {
		if ($profile['WizProfileId'] == $profileId) {
			return $profile;
		}
	}
}

function get_template_data_profiles()
{
	// Use ABSPATH to get the WordPress root directory
	$path = ABSPATH . 'wp-content/plugins/id-email-wizard/builder-v2/json-test-data/profiles/';

	// Ensure forward slashes for consistency
	$path = str_replace('\\', '/', $path);

	// Find all JSON files in the profiles directory
	$json_files = glob($path . '*.json');

	$profiles = [];
	foreach ($json_files as $json_file) {
		$json_data = file_get_contents($json_file);
		if ($json_data === false) {
			error_log('Failed to read file: ' . $json_file);
			continue;
		}

		$decoded_data = json_decode($json_data, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			error_log('Failed to decode JSON in file: ' . $json_file . '. Error: ' . json_last_error_msg());
			continue;
		}

		$profiles[] = $decoded_data;
	}

	return $profiles;
}

function get_image_aspect_ratio($imageSrc, $defaultRatio = 3)
{
	// Set up error handling
	set_error_handler(function ($errno, $errstr, $errfile, $errline) {
		throw new Exception($errstr, $errno);
	});

	// Try to get image size
	$status = 'error';
	try {
		// Get cached image data
		$cached_data = get_cached_image_data($imageSrc);

		if ($cached_data && isset($cached_data['data'])) {
			// Create a temporary file with the image data
			$temp_file = tempnam(sys_get_temp_dir(), 'img');
			file_put_contents($temp_file, $cached_data['data']);

			// Get image size from the temporary file
			$result = getimagesize($temp_file);

			// Remove the temporary file
			unlink($temp_file);

			if (is_array($result) && isset($result[0]) && isset($result[1]) && $result[1] != 0) {
				$imageAspectRatio = $result[0] / $result[1];
				$status = 'success';
			} else {
				throw new Exception("Unable to determine valid image dimensions");
			}
		} else {
			throw new Exception("Unable to retrieve cached image data");
		}
	} catch (Exception $e) {
		// Log the error
		error_log("Error getting image aspect ratio for $imageSrc: " . $e->getMessage());
		$imageAspectRatio = $defaultRatio;
	}

	// Restore the original error handler
	restore_error_handler();

	return [
		'status' => $status,
		'data' => $imageAspectRatio
	];
}

function check_link_ajax()
{
	if (!current_user_can('edit_posts')) {
		wp_send_json_error(['message' => 'Insufficient permissions']);
		return;
	}

	$url = $_POST['url'] ?? '';

	if (empty($url)) {
		wp_send_json_error(['message' => 'No URL provided']);
		return;
	}

	$utmParam = 'test_' . substr(md5(rand()), 0, 7);
	$urlWithUtm = add_query_arg('utm_campaign', $utmParam, $url);

	$startTime = microtime(true);
	$response = wp_remote_get($urlWithUtm, ['redirection' => 5]);
	$loadTime = microtime(true) - $startTime;

	if (is_wp_error($response)) {
		wp_send_json_error(['message' => $response->get_error_message()]);
		return;
	}

	$httpCode = wp_remote_retrieve_response_code($response);
	$finalUrl = wp_remote_retrieve_header($response, 'url') ?: $urlWithUtm;
	$redirectionCount = $response['redirection_count'] ?? 0;

	$isRedirected = ($redirectionCount > 0) || ($finalUrl !== $urlWithUtm);
	$utmAccepted = strpos($finalUrl, $utmParam) !== false;

	// If the response was not successful, try it without any $_GET requests
	$retryHttpCode = false;
	if ($httpCode >= 400) {
		$urlWithoutUtm = preg_replace('/utm_[^&]+/', '', $url);
		$retryResponse = wp_remote_get($urlWithoutUtm, ['redirection' => 5]);

		if (is_wp_error($retryResponse)) {
			wp_send_json_error(['message' => $retryResponse->get_error_message()]);
			return;
		}

		$retryHttpCode = wp_remote_retrieve_response_code($retryResponse);
		$retryFinalUrl = wp_remote_retrieve_header($retryResponse, 'url') ?: $urlWithoutUtm;
		$retryRedirectionCount = $retryResponse['redirection_count'] ?? 0;

		$isRedirected = ($retryRedirectionCount > 0) || ($retryFinalUrl !== $urlWithoutUtm);
		$utmAccepted = false;
	}

	$successObject = [
		'original_url' => $url,
		'final_url' => $finalUrl,
		'http_code' => $httpCode,
		'load_time' => round($loadTime, 2),
		'redirected' => $isRedirected,
		'redirection_count' => $redirectionCount,
		'utm_accepted' => $utmAccepted
	];

	if ($retryHttpCode) {
		$successObject['retry_http_code'] = $retryHttpCode;
	}

	wp_send_json_success($successObject);
}

add_action('wp_ajax_check_link_ajax', 'check_link_ajax');


// Gets image data either from the cashe or from the remote server
function get_cached_image_data($url, $cache_time = 1 * WEEK_IN_SECONDS, $max_size = 2097152)
{ 
	$cache_key = 'wiz_image_cache_' . md5($url);
	$cached_data = get_transient($cache_key);

	if (false === $cached_data) {
		$response = wp_remote_get($url, array('timeout' => 15));

		if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
			$image_data = wp_remote_retrieve_body($response);
			$size = strlen($image_data);

			if ($size <= $max_size) {
				$headers = wp_remote_retrieve_headers($response);
				$cached_data = array(
						'data' => $image_data,
						'type' => $headers['content-type'],
						'size' => $size,
						'time' => time()
					);
				set_transient($cache_key, $cached_data,
					$cache_time
				);
			} else {
				// Log or handle oversized image
				// Convert max size to megabytes
				$max_size_mb = $max_size / (1024 * 1024);
				error_log("Image caching error: Image exceeds maximum size of {$max_size_mb}mb: $url");
				return false;
			}
		} else {
			return false; // Failed to fetch image
		}
	}

	return $cached_data;
}

function cleanup_image_cache($max_size = 100 * 1024 * 1024, $max_age = 604800)
{
	global $wpdb;

	// Get all our image cache transients
	$transients = $wpdb->get_results(
		"SELECT option_name, option_value 
		FROM {$wpdb->options} 
		WHERE option_name LIKE '_transient_wiz_image_cache_%'
		ORDER BY option_id DESC"
	);

	$total_size = 0;
	$deleted = 0;

	foreach ($transients as $transient) {
		$$image_data = maybe_unserialize($transient->option_value);
		if (!is_array($image_data) || !isset($image_data['size'])) continue;

		$total_size += $image_data['size'];
		$age = time() - (int)$image_data['time'];

		// Delete if too old or if we're over the size limit
		if ($age > $max_age || $total_size > $max_size) {
			$transient_name = str_replace('_transient_', '', $transient->option_name);
			delete_transient($transient_name);
			$deleted++;
			$total_size -= $image_data['size'];
		}
	}

	return $deleted;
}

// Usage:
add_action('wp_scheduled_delete', 'cleanup_wiz_image_cache');
if (!wp_next_scheduled('cleanup_wiz_image_cache')) {
	wp_schedule_event(time(), 'daily', 'cleanup_wiz_image_cache');
}

function get_wizbuilder_image_src($image_url, $isEditor)
{
	if ($isEditor) {
		// We're in editor mode
		$cached_data = get_cached_image_data($image_url);
		if ($cached_data && isset($cached_data['data'])) {
			// Create a data URI from the cached image data
			$base64 = base64_encode($cached_data['data']);
			return 'data:' . $cached_data['type'] . ';base64,' . $base64;
		}
	}

	// If we're not in editor mode or if caching failed, return the original URL
	return $image_url;
}

function convertStringBooleans(&$data)
{
	if (is_array($data) || is_object($data)) {
		foreach ($data as &$value) {
			if (is_array($value) || is_object($value)) {
				convertStringBooleans($value);
			} elseif ($value === 'true') {
				$value = true;
			} elseif ($value === 'false') {
				$value = false;
			}
		}
		unset($value); // Unset the reference to avoid potential issues
	} elseif ($data === 'true') {
		$data = true;
	} elseif ($data === 'false') {
		$data = false;
	}
}