<?php
add_action( 'template_redirect', 'idemailwiz_handle_builder_v2_request', 20 );
function idemailwiz_handle_builder_v2_request() {
	global $wp_query, $wp;

	// Handle build-template
	if ( isset( $wp_query->query_vars['build-template-v2'] ) ) {

		$current_url = home_url( add_query_arg( array(), $wp->request ) );
		if ( strpos( $current_url, '/build-template-v2/' ) !== false && ! isset( $_SERVER['HTTP_REFERER'] ) ) {
			$dieMessage = 'Direct access to the template builder endpoint is not allowed!';
			wp_die( $dieMessage );
			exit;
		}

		echo '<div style="padding: 30px; text-align: center; font-weight: bold; font-family: Poppins, sans-serif;"><i style="font-family: Font Awesome 5;" class="fas fa-spinner fa-spin"></i>  Loading template...<br/>';
		exit;
	}
}

add_action( 'wp_ajax_idemailwiz_build_template', 'idemailwiz_build_template' );
function idemailwiz_build_template() {
	// Validate AJAX and nonce
	if ( ! wp_doing_ajax() || ! check_ajax_referer( 'template-editor', 'security', false ) ) {
		wp_die();
	}

	// Validate POST action
	if ( $_POST['action'] !== 'idemailwiz_build_template' ) {
		wp_die();
	}

	$userId = get_current_user_id();
	$templateId = $_POST['templateid'];

	// Check if template data is passed in the request
	if ( isset( $_POST['template_data'] ) ) {
		// Use the template data from the request
		$templateData = json_decode( stripslashes( $_POST['template_data'] ), true );
	} else {
		// Fallback to fetching template data from the database
		$templateData = get_wizTemplate( $templateId );
	}

	include dirname( plugin_dir_path( __FILE__ ) ) . '/builder-v2/preview-pane.php';
}


add_action( 'wp_ajax_get_wiztemplate_with_ajax', 'get_wiztemplate_with_ajax' );
function get_wiztemplate_with_ajax() {
	check_ajax_referer( 'template-editor', 'security' );
	$postId = $_POST['template_id'];
	if ( ! $postId ) {
		wp_send_json_error( [ 'message' => 'No post ID provided' ] );
		return;
	}
	$templateData = get_wiztemplate( $postId );
	wp_send_json_success( $templateData );
}

function get_wiztemplate( $postId, $status = 'publish' ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'wiz_templates';
	$column = 'template_data';
	if ( $status == 'draft' ) {
		$column = 'template_data_draft';
	}

	$templateDataJSON = $wpdb->get_var( $wpdb->prepare(
		"SELECT $column FROM $table_name WHERE post_id = %d",
		$postId
	) );

	return json_decode( $templateDataJSON, true ); // Decode JSON string into an associative array
}



add_action( 'wp_ajax_create_new_row', 'handle_create_new_row' );
function handle_create_new_row() {
	// Check for nonce for security
	$nonce = $_POST['security'] ?? '';
	if ( ! wp_verify_nonce( $nonce, 'template-editor' ) ) {
		wp_send_json_error( [ 'message' => 'Nonce verification failed' ] );
		return;
	}

	// Get the post ID from the AJAX request
	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	if ( ! $post_id ) {
		wp_send_json_error( [ 'message' => 'Invalid post ID' ] );
		return;
	}

	// Get the row ID of the row above the new row
	$row_above = $_POST['row_above'];

	// Check if row_above is 'false' (string) or actually false
	if ( $row_above === 'false' || $row_above === false ) {
		$row_id = 0;
	} else {
		$row_above = intval( $row_above );
		$row_id = $row_above + 1;
	}

	// Generate the HTML for the new row
	$html = generate_builder_row( $row_id );

	// Return the HTML
	wp_send_json_success( [ 'html' => $html ] );
}

add_action( 'wp_ajax_add_new_chunk', 'handle_add_new_chunk' );
function handle_add_new_chunk() {
	$nonce = $_POST['security'] ?? '';
	if ( ! wp_verify_nonce( $nonce, 'template-editor' ) ) {
		wp_send_json_error( [ 'message' => 'Nonce verification failed' ] );
		return;
	}

	$rowId = intval( $_POST['row_id'] );
	$chunkBeforeId = isset( $_POST['chunk_before_id'] ) ? intval( $_POST['chunk_before_id'] ) : null;
	$chunkType = sanitize_text_field( $_POST['chunk_type'] );

	// Generate a new chunk ID
	$newChunkId = $chunkBeforeId !== null ? $chunkBeforeId + 1 : 0;

	$columnId = isset( $_POST['column_id'] ) ? intval( $_POST['column_id'] ) : 0;

	$html = generate_builder_chunk( $newChunkId, $chunkType, $rowId, $columnId );

	wp_send_json_success( [ 'html' => $html, 'chunk_id' => $newChunkId ] );
}



add_action( 'wp_ajax_create_new_column', 'handle_create_new_column' );
function handle_create_new_column() {
	$nonce = $_POST['security'] ?? '';
	if ( ! wp_verify_nonce( $nonce, 'template-editor' ) ) {
		wp_send_json_error( [ 'message' => 'Nonce verification failed' ] );
		return;
	}

	$rowId = intval( $_POST['row_id'] ); // Assuming row ID is passed
	$columnIndex = isset( $_POST['column_index'] ) ? intval( $_POST['column_index'] ) : 0;

	// Generate a new column HTML. We'll return a blank column structure.
	$html = generate_builder_column( $rowId, [], $columnIndex );

	wp_send_json_success( [ 'html' => $html ] );
}


function generate_builder_row( $rowId, $rowData = [] ) {

	$uniqueId = uniqid( 'wiz-row-' );

	$columns = isset( $rowData['columns'] ) && is_array( $rowData['columns'] ) ? $rowData['columns'] : [ [ 'chunks' => [] ] ];
	$countColumns = 0;
	foreach ( $columns as $column ) {
		if ( ! isset( $column['activation'] ) || $column['activation'] === 'active' ) {
			$countColumns++;
		}
	}
	$collapseState = $rowData['state'] ?? 'collapsed';

	$magicWrap = $rowData['magic_wrap'] ?? 'off';
	$magicWrapClass = $magicWrap == 'on' ? 'active' : '';

	if ( $magicWrap == 'on' ) {
		$columns = array_reverse( $columns );
	}

	$desktopVisibility = isset( $rowData['desktop_visibility'] ) && $rowData['desktop_visibility'] === 'false' ? 'false' : 'true';
	$mobileVisibility = isset( $rowData['mobile_visibility'] ) && $rowData['mobile_visibility'] === 'false' ? 'false' : 'true';


	// Determine if the icons should have the 'disabled' class based on visibility
	$desktopIconClass = $desktopVisibility === 'false' ? 'disabled' : '';
	$mobileIconClass = $mobileVisibility === 'false' ? 'disabled' : '';

	$rowTitle = $rowData['title'] ?? 'Section';
	$rowNumber = $rowId + 1;
	$columnsStacked = $rowData['stacked'] ?? false;
	$stackedClass = $columnsStacked ? 'fa-rotate-90' : '';
	$html = '<div class="builder-row --' . $collapseState . '" id="' . $uniqueId . '" data-row-id="' . $rowId . '"  data-column-stacked="' . $columnsStacked . '">
				<div class="builder-row-header">
					<div class="builder-row-title"><div class="builder-row-title-number" data-row-id-display="' . $rowNumber . '">' . $rowNumber . '</div>
					<div class="builder-row-title-text">' . $rowTitle . '</div>
					<i class="fa-solid fa-pen-to-square edit-row-title exclude-from-toggle" data-row-id="' . $rowId . '"></i></div>
					<div class="builder-row-actions">
						<div class="builder-row-actions-button exclude-from-toggle show-on-desktop ' . $desktopIconClass . '" data-show-on-desktop="' . $desktopVisibility . '">
						<i class="fas fa-desktop"></i>
						</div>
						<div class="builder-row-actions-button exclude-from-toggle show-on-mobile ' . $mobileIconClass . '" data-show-on-mobile="' . $mobileVisibility . '">
						<i class="fas fa-mobile-alt" ></i>
						</div>
						<div class="builder-row-actions-button exclude-from-toggle ' . $stackedClass . '" title="Stack/Unstack columns">
						<i class="fa-solid fa-bars rotate-columns" ></i>
						</div>
						<div class="builder-row-actions-button row-column-settings exclude-from-toggle" data-columns="' . $countColumns . '" title="Add/Remove columns">
						<i class="fas fa-columns"></i>
						</div>
						<div class="builder-row-actions-button magic-wrap-toggle row-columns-magic-wrap exclude-from-toggle ' . $magicWrapClass . '" data-magic-wrap="' . $magicWrap . '" title="Magic Wrap">
						<i class="fa-solid fa-arrow-right-arrow-left"></i>
						</div>
						<div class="builder-row-actions-button exclude-from-toggle duplicate-row" title="Duplicate row">
						
						<i class="fa-regular fa-copy"></i>
						</div>
						<div class="builder-row-actions-button remove-row exclude-from-toggle" title="Delete row">
						<i class="fas fa-times"></i>
						</div>
					</div>
				</div>
				<div class="builder-row-content">
				<div class="builder-row-columns" data-active-columns="' . $countColumns . '">';

	foreach ( $columns as $columnIndex => $column ) {

		$html .= generate_builder_column( $rowId, $column, $columnIndex );
	}

	$html .= '</div></div></div>'; // Close builder-row-columns, builder-row-content, and builder-row divs
	return $html;
}

function generate_builder_column( $rowId, $columnData, $columnIndex ) {
	$uniqueId = uniqid( 'wiz-column-' );

	$columnNumber = $columnIndex + 1;
	$collapsedState = $columnData['state'] ?? 'expanded';

	$colActiveClass = isset( $columnData['activation'] ) && $columnData['activation'] === 'inactive' ? 'inactive' : 'active';
	$html = '<div class="builder-column ' . $colActiveClass . ' --' . $collapsedState . '" id="' . $uniqueId . '" data-column-id="' . $columnIndex . '">';
	$html .= '<div class="builder-column-header">';

	$colTitle = $columnData['title'] ?? 'Column';

	$html .= '<div class="builder-column-title"><div class="builder-column-title-number" data-column-id-display="' . $columnNumber . '">' . $columnNumber . '</div>';
	$html .= '<div class="builder-column-title-text">' . $colTitle . '</div>';
	$html .= '<i class="fa-solid fa-pen-to-square edit-column-title exclude-from-toggle" data-column-id="' . $columnIndex . '"></i></div>';


	$html .= '<div class="builder-column-actions">';
	$html .= '<div class="builder-column-actions-button">';
	$html .= '<i class="fas fa-arrows-alt move-column"></i>';
	$html .= '</div></div></div>'; // Close header and actions

	$collapsedMsgClass = 'hide';
	if ( $collapsedState === 'collapsed' ) {
		$collapsedMsgClass = 'show';
	}
	$html .= '<div class="collapsed-message ' . $collapsedMsgClass . '">Column is collapsed. Click header toggle to view & edit chunks.</div>';
	$html .= '<div class="builder-column-chunks">';
	$html .= '<div class="builder-column-chunks-body">'; // we need this extra wrapper to avoid slideup/slidedown from messing with our flex layout

	if ( ! empty( $columnData['chunks'] ) ) {
		foreach ( $columnData['chunks'] as $chunkIndex => $chunk ) {
			$chunkType = $chunk['field_type'] ?? 'text';
			$html .= generate_builder_chunk( $chunkIndex, $chunkType, $rowId, $columnIndex, $chunk );
		}
	}
	$html .= '</div>';
	$html .= '<div class="add-chunk-to-end add-chunk-wrapper"><button class="wiz-button centered add-chunk">Add Chunk</button></div>';

	$html .= '</div>';
	$html .= '</div>';
	return $html;
}



function generate_builder_chunk( $chunkId, $chunkType, $rowId, $columnId, $chunkData = [] ) {
	$uniqueId = $chunkData['id'] ?? uniqid( 'wiz-chunk-' );
	$chunkState = $chunkData['state'] ?? 'collapsed';

	$desktopVisibility = ( isset( $chunkData['desktop_visibility'] ) && $chunkData['desktop_visibility'] == 'false' ) ? 'false' : 'true';
	$mobileVisibility = ( isset( $chunkData['mobile_visibility'] ) && $chunkData['mobile_visibility'] == 'false' ) ? 'false' : 'true';


	$desktopIconClass = $desktopVisibility == 'false' ? 'disabled' : '';
	$mobileIconClass = $mobileVisibility == 'false' ? 'disabled' : '';





	$html = '<div class="builder-chunk --' . $chunkState . '" data-chunk-id="' . $chunkId . '" data-chunk-type="' . $chunkType . '" id="' . $uniqueId . '">
				<div class="builder-chunk-header">
					<div class="builder-chunk-title">' . $chunkType . '</div>
					<div class="builder-chunk-actions">
						<div class="builder-chunk-actions-button exclude-from-toggle show-on-desktop ' . $desktopIconClass . '" data-show-on-desktop="' . $desktopVisibility . '" title="Show on desktop">
						<i class="fas fa-desktop" ></i>
						</div>
						<div class="builder-chunk-actions-button exclude-from-toggle show-on-mobile ' . $mobileIconClass . '" data-show-on-mobile="' . $mobileVisibility . '" title="Show on mobile">
						<i class="fas fa-mobile-alt" ></i>
						</div>
						<div class="builder-chunk-actions-button add-chunk-wrapper builder-add-new-chunk-above exclude-from-toggle" title="Add chunk above">
						<span class="add-chunk" data-chunk-id="' . $chunkId . '"><i class="fas fa-plus"></i></span>
						</div>
						<div class="builder-chunk-actions-button exclude-from-toggle duplicate-chunk" title="Duplicate chunk">
						<i class="fas fa-copy"></i>
						</div>
						<div class="builder-chunk-actions-button remove-chunk exclude-from-toggle" title="Remove chunk">
						<i class="fas fa-times"></i>
						</div>
					</div>
				</div>
				<div class="builder-chunk-body">
					' . generate_chunk_form_interface( $chunkType, $rowId, $columnId, $chunkId, $chunkData, $uniqueId ) . '
				</div>
			</div>';
	return $html;
}





function generate_chunk_form_interface( $chunkType, $rowId, $columnId, $chunkId, $chunkData, $uniqueId ) {
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
	foreach ( $tabs as $tab => $label ) {
		$isActive = $tab === $activeTab ? 'active' : '';
		echo "<div class=\"chunk-tab $isActive\" data-target=\"#{$uniqueId}-chunk-{$tab}-container\">{$label}</div>";
	}
	echo '</div>';

	// Content tab content
	echo "<div class='tab-content chunk-content' id='{$uniqueId}-chunk-content-container' " . ( $activeTab !== 'content' ? "style='display:none;'" : "" ) . ">";
	echo "<form id='{$uniqueId}-chunk-fields' class='chunk-fields-form'>";
	// Assuming a function to render fields based on $chunkType
	render_chunk_fields( $chunkType, $chunkData, $uniqueId );
	echo "</form>";
	echo "</div>"; // Close chunk-content container

	// Settings tab content
	echo "<div class='tab-content chunk-settings' id='{$uniqueId}-chunk-settings-container' " . ( $activeTab !== 'settings' ? "style='display:none;'" : "" ) . ">";
	echo "<form id='{$uniqueId}-chunk-settings' class='chunk-settings-form'>";

	echo render_chunk_settings( $chunkType, $chunkData, $uniqueId );
	echo "</form>";
	echo "</div>"; // Close chunk-settings div

	// HTML Code tab content
	echo "<div class='tab-content chunk-code' id='{$uniqueId}-chunk-code-container' " . ( $activeTab !== 'code' ? "style='display:none;'" : "" ) . ">";
	echo "<div class='tab-content-actions'>";
	echo "<button class='wiz-button green copy-chunk-code' title='Copy HTML Code'><i class='fa-regular fa-copy'></i>&nbsp;&nbsp;Copy Code</button>";
	echo "</div>"; // Close chunk-code-actions div
	echo "<form id='{$uniqueId}-chunk-code' class='chunk-code-form'>";
	echo render_chunk_code( $chunkData, $uniqueId );
	echo "</form>";
	echo "</div>"; // Close chunk-settings div

	// Return the captured HTML
	return ob_get_clean();
}

function render_chunk_code( $chunkData, $uniqueId ) {
	$currentTemplate = get_the_ID();
	$wizTemplate = get_wiztemplate( $currentTemplate );
	$chunkTemplate = idwiz_get_chunk_template( $chunkData, $wizTemplate['templateOptions'] );
	echo '<div class="chunk-html-code">';
	//echo '<textarea name="chunk_html" id="'.$uniqueId.'-chunk-html" style="display: none;">'.htmlspecialchars($chunkTemplate).'</textarea>';
	echo '<pre><code>' . htmlspecialchars( $chunkTemplate ) . '</code></pre>';
	echo '</div>';
}

function render_chunk_settings( $chunkType, $chunkData, $uniqueId ) {
	$chunkSettings = $chunkData['settings'] ?? [];
	echo "<div class='chunk-inner-content'>";
	//$typeLabel = ucfirst($chunkType);
	echo "<div class='chunk-settings-section chunk-general-settings'>";
	echo "<h4>Chunk Content Settings</h4>";
	echo "<div class='builder-field-group flex'>";
	$chunkPadding = $chunkSettings['chunk_padding'] ?? '0px';

	echo '<form id="' . $uniqueId . '-chunk-settings-form">';
	switch ( $chunkType ) {

		case 'text':
		case 'button':
			echo "<div class='builder-field-wrapper chunk-padding small-input'><label for='{$uniqueId}-chunk-padding'>Chunk Padding</label>";
			echo "<input type='text' name='chunk_padding' id='{$uniqueId}-chunk-padding' value='{$chunkPadding}'>";
			echo "</div>";
			break;


		case 'image':

			break;

		case 'spacer':

			break;

		case 'snippet':

			break;
		default:
			'No valid chunk type set!';
			break;
	}
	echo "</form>"; // Close chunk-inner-content
	echo "</div>"; // Close chunk-general-settings
	echo "</div>"; // Close builder-field-group.flex 
	echo generateBackgroundSettingsModule( $chunkData['settings'], '' );

	echo "</div>"; // Close chunk-inner-content 

}
function render_chunk_fields( $chunkType, $chunkData, $uniqueId ) {
	// Chunk specific form fields
	echo "<div class='chunk-inner-content'>";
	echo "<form id='{$uniqueId}-chunk-fields-form'>";
	switch ( $chunkType ) {
		case 'text':
			$existingContent = isset( $chunkData['fields']['plain_text_content'] ) ? $chunkData['fields']['plain_text_content'] : 'Enter your content here...';

			echo '<textarea class="wiz-wysiwyg" name="plain_text_content" id="' . $uniqueId . '-wiz-wysiwyg">' . $existingContent . '</textarea>';


			break;
		case 'image':
			$imageUrl = $chunkData['fields']['image_url'] ?? '';
			$imageLink = $chunkData['fields']['image_link'] ?? '';
			$imageAlt = $chunkData['fields']['image_alt'] ?? '';


			echo "<div class='builder-field-group flex'>";
			echo "<div class='builder-field-wrapper'><label for ='{$uniqueId}-image-url'>Image URL</label><input type='text' name='image_url' id='{$uniqueId}-image-url' value='{$imageUrl}' placeholder='https://'></div>";
			echo "<div class='builder-field-wrapper'><label for ='{$uniqueId}-image-link'>Image Link</label><input type='text' name='image_link' id='{$uniqueId}-image-link' value='{$imageLink}' placeholder='https://'></div>";
			echo "<div class='builder-field-wrapper'><label for ='{$uniqueId}-image-alt'>Image Alt</label><input type='text' name='image_alt' id='{$uniqueId}-image-alt' value='{$imageAlt}' placeholder='Describe the image or leave blank'></div>";
			echo "<div class='image-chunk-preview'>";
			echo "<img src='{$imageUrl}' alt=''>";
			echo "</div>";
			echo "</div>"; // close builder-field-group

			break;
		case 'button':
			$buttonBgColor = $chunkData['fields']['button_fill_color'] ?? '#343434';
			$buttonFontSize = $chunkData['fields']['button_font_size'] ?? '1.1em';
			$buttonTextColor = $chunkData['fields']['button_text_color'] ?? '#ffffff';
			$buttonBorderColor = $chunkData['fields']['button_border_color'] ?? '#343434';
			$buttonBorderSize = $chunkData['fields']['button_border_size'] ?? '1px';
			$buttonBorderRadius = $chunkData['fields']['button_border_radius'] ?? '30px';
			$buttonPadding = $chunkData['fields']['button_padding'] ?? '15px 30px';
			$buttonAlign = $chunkData['fields']['button_align'] ?? 'center';

			$buttonLink = $chunkData['fields']['button_link'] ?? 'https://www.idtech.com';
			$buttonCta = $chunkData['fields']['button_text'] ?? 'Click Here';



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
				[ 'id' => $uniqueId . '_btn_align_left', 'value' => 'left', 'label' => '<i class="fa-solid fa-align-left"></i>', 'checked' => 'checked' ],
				[ 'id' => $uniqueId . '_btn_align_center', 'value' => 'center', 'label' => '<i class="fa-solid fa-align-center"></i>' ],
				[ 'id' => $uniqueId . '_btn_align_right', 'value' => 'right', 'label' => '<i class="fa-solid fa-align-right"></i>' ],


			];

			foreach ( $alignOptions as $opt ) {
				$isChecked = isset( $buttonAlign ) && $buttonAlign === $opt['value'] ? 'checked' : '';
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
			echo "<input class='builder-colorpicker' type='color' name='button_border_color' id='{$uniqueId}-button-border-color'  data-color-value='{$buttonBorderColor}'value='{$buttonBorderColor}'>";
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
			echo "<div class='builder-field-wrapper'><label for ='{$uniqueId}-button-text'>CTA Text</label><input type='text' name='button_text' id='{$uniqueId}-button-text' value='{$buttonCta}' placeholder='Click here now!'></div>";
			echo "<div class='builder-field-wrapper'><label for ='{$uniqueId}-button-link'>Button Link</label><input type='text' name='button_link' id='{$uniqueId}-button-link' value='{$buttonLink}' placeholder='https://'></div>";
			echo "</div>";



			break;
		case 'spacer':
			$spacerHeight = $chunkData['fields']['spacer_height'] ?? '60px';

			echo "<div class='builder-field-group'>";
			echo "<div class='builder-field-wrapper'><label for ='{$uniqueId}-spacer-height'>Spacer Height</label><input type='text' name='spacer_height' id='{$uniqueId}-spacer-height' value='{$spacerHeight}' placeholder='px, em, etc'></div>";
			echo "</div>"; // Close builder-field-group

			break;
		case 'snippet':
			$selectedSnippet = $chunkData['fields']['select_snippet'] ?? '';

			echo "<div class='builder-field-group'>";
			echo "<div class='builder-field-wrapper'><label for ='{$uniqueId}-snippet-id'>Snippet ID</label>";
			echo "<select id='{$uniqueId}-snippet-id' name='select_snippet'>";
			$snippetsForSelect = get_snippets_for_select();
			$noSelectionSelected = $selectedSnippet ? '' : 'selected';
			echo "<option value='' {$noSelectionSelected} disabled>Select a Snippet</option>";
			foreach ( $snippetsForSelect as $snippetId => $snippetTitle ) {
				echo "<option value='{$snippetId}' " . ( $selectedSnippet == $snippetId ? 'selected' : '' ) . ">{$snippetTitle}</option>";
			}
			echo "</select>";
			echo "</div>";
			echo "</div>"; // Close chunk-field-group

			break;
		default:
			echo "No valid chunk type set!";
			break;
	}
	echo "</form>";
	echo "</div>"; // Close chunk-inner-content 
}

function generateBackgroundSettingsModule( $backgroundSettings, $uniqueId = '', $typeLabel = true ) {
	// If no unique ID is passed, generate one for use in ID/label attributes for repeated field names (like background settings)
	$uniqueTempId = $uniqueId != '' ? $uniqueId : '_' . uniqid();
	//echo ('Chunk Data for '.$uniqueId.': '. print_r($chunkData, true));
	$chunkBackgroundType = $backgroundSettings[ $uniqueId . 'background-type' ] ?? 'none';
	$chunkBackgroundColor = $backgroundSettings[ $uniqueId . 'background-color' ] ?? '#ffffff';
	$forceBackground = isset( $backgroundSettings[ $uniqueId . 'force-background' ] ) && $backgroundSettings[ $uniqueId . 'force-background' ] == true;

	// Background Type Options
	$backgroundOptions = [ 
		[ 'id' => $uniqueTempId . 'bg-none', 'value' => 'none', 'label' => '<i class="fas fa-ban"></i> None', 'checked' => 'checked' ],
		[ 'id' => $uniqueTempId . 'bg-solid', 'value' => 'solid', 'label' => '<i class="fas fa-fill"></i> Solid' ],
		[ 'id' => $uniqueTempId . 'bg-image', 'value' => 'image', 'label' => '<i class="fas fa-image"></i> Image' ],
		[ 'id' => $uniqueTempId . 'bg-gradient', 'value' => 'gradient', 'label' => '<i class="fas fa-water"></i> Grad' ]
	];

	ob_start();
	?>
	<div class='chunk-settings-section chunk-background-settings'>

		<div class="chunk-background-type-wrapper">
			<div class='button-group-wrapper chunk-background-type'>
				<?php
				if ( $typeLabel ) { ?>
					<label class="button-group-label">Background Type</label>
				<?php } ?>
				<div class="button-group radio">
					<?php foreach ( $backgroundOptions as $opt ) : ?>
						<?php
						// Check if this option is selected
						$isChecked = isset( $chunkBackgroundType ) && $chunkBackgroundType === $opt['value'] ? 'checked' : '';
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
		if ( isset( $chunkBackgroundType ) && $chunkBackgroundType != 'none' ) {
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
							class="checkbox-toggle-replace <?php echo $forceBackground ? 'active' : ''; ?>"><i
								class="<?php echo $forceBackground ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
						<label class="checkbox-toggle-label">Force dark mode BG</label>
					</div>


				</div>
			</div>

		</div>
		<?php
		$showClass = 'hidden';
		if ( isset( $chunkBackgroundType ) && $chunkBackgroundType == 'image' ||
			isset( $chunkBackgroundType ) && $chunkBackgroundType == 'gradient' ) {
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
						value="<?php echo $backgroundSettings[ $uniqueId . 'background-image-url' ] ?? ''; ?>"
						placeholder="https://...">
				</div>
				<div class="builder-field-wrapper chunk-background-image-position">
					<label for="<?php echo $uniqueId . 'background-image-position'; ?>">Position</label>
					<input type="text" name="<?php echo $uniqueId . 'background-image-position'; ?>"
						id="<?php echo $uniqueId . 'background-image-position'; ?>" class="builder-text-input"
						value="<?php echo $backgroundSettings[ $uniqueId . 'background-image-position' ] ?? ''; ?>"
						placeholder="eg center center">
				</div>
				<div class="builder-field-wrapper chunk-background-image-size">
					<label for="<?php echo $uniqueId . 'background-image-size'; ?>">Size</label>
					<input type="text" name="<?php echo $uniqueId . 'background-image-size'; ?>"
						id="<?php echo $uniqueId . 'background-image-size'; ?>" class="builder-text-input"
						value="<?php echo $backgroundSettings[ $uniqueId . 'background-image-size' ] ?? ''; ?>"
						placeholder="eg 100% 100%">
				</div>

				<?php
				$imageRepeatOptions = [ 
					[ 'id' => $uniqueId . 'bg-repeat-horizontal', 'value' => 'repeat-x', 'label' => '<i class="fa-solid fa-left-right"></i>' ],
					[ 'id' => $uniqueId . 'bg-repeat-vertical', 'value' => 'repeat-y', 'label' => '<i class="fa-solid fa-up-down"></i>' ]
				];
				?>

				<div class='button-group-wrapper builder-field-wrapper chunk-background-image-repeat'>
					<label class="button-group-label">Repeat</label>
					<div class="button-group checkbox">
						<?php foreach ( $imageRepeatOptions as $opt ) : ?>
							<?php
							$fieldID = $opt['id'];

							$isChecked = isset( $backgroundSettings[ $fieldID ] ) && $backgroundSettings[ $fieldID ] ? 'checked' : '';

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
		if ( isset( $chunkBackgroundType ) && $chunkBackgroundType == 'gradient' ) {
			$showClass = '';
		}
		?>
		<div class="chunk-settings-section chunk-background-gradient-settings <?php echo $showClass; ?>">
			<label>Gradient Settings</label>
			<div class="chunk-settings-section-fields flex">
				<div class='chunk-gradient-settings'>
					<label>
						<input type="hidden"
							value="<?php echo htmlspecialchars( $backgroundSettings[ $uniqueId . 'gradient-styles' ] ?? '' ); ?>"
							name="<?php echo $uniqueId . 'gradient-styles'; ?>" class="gradientValue"
							id="<?php echo $uniqueId . 'gradient-styles'; ?>" />
						<div class="gradientLabel"
							data-gradientstyles="<?php echo htmlspecialchars( $backgroundSettings[ $uniqueId . 'gradient-styles' ] ?? '' ); ?>">
							Select
							Gradient
						</div>
					</label>
				</div>

			</div>
		</div>


	</div>

	<?php

	return ob_get_clean();
}






add_action( 'wp_ajax_save_wiz_template_data', 'prepare_wiztemplate_for_save' );
function prepare_wiztemplate_for_save() {
	// Check nonce for security
	check_ajax_referer( 'template-editor', 'security' );

	$postId = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;
	$userId = get_current_user_id();
	$saveType = $_POST['save_type'] ?? 'draft';
	$templateData = isset( $_POST['template_data'] ) ? json_decode( stripslashes( $_POST['template_data'] ), true ) : '';

	// Save the template data
	save_template_data( $postId, $userId, $templateData, $saveType === 'draft' );

	wp_send_json_success( [ 'message' => 'Template saved successfully', 'templateData' => $templateData ] );
}



// Save the template data from the ajax call
function save_template_data( $postId, $userId, $templateData, $isDraft = false ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'wiz_templates';

	$dataFieldName = $isDraft ? 'template_data_draft' : 'template_data';

	// Ensure templateData is a JSON string
	if ( is_array( $templateData ) ) {
		$templateData = json_encode( $templateData );
	}

	// Prepare data for database insertion
	$data = [ 
		'last_updated' => date( 'Y-m-d H:i:s' ),
		'post_id' => $postId,
		$dataFieldName => $templateData
	];

	// Check for an existing record
	$existingRecordId = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE post_id = %d", $postId ) );
	if ( $existingRecordId ) {
		// Update existing record
		$wpdb->update( $table_name, $data, [ 'id' => $existingRecordId ] );
	} else {
		// Insert new record
		$wpdb->insert( $table_name, $data );
	}

	// Optional: Check for and log any database errors
	if ( ! empty( $wpdb->last_error ) ) {
		error_log( "WordPress database error: " . $wpdb->last_error );
	}
}


function idwiz_get_chunk_template( $chunk, $templateOptions ) {
	$chunkType = $chunk['field_type'];

	$return = '';

	if ( $chunkType == 'text' ) {
		$return .= idwiz_get_plain_text_chunk( $chunk, $templateOptions );
	} else if ( $chunkType == 'image' ) {
		$return .= idwiz_get_image_chunk( $chunk, $templateOptions );
	} else if ( $chunkType == 'button' ) {
		$return .= idwiz_get_button_chunk( $chunk, $templateOptions );
	} else if ( $chunkType == 'spacer' ) {
		$return .= idwiz_get_spacer_chunk( $chunk, $templateOptions );
	} else if ( $chunkType == 'snippet' ) {
		$return .= idwiz_get_snippet_chunk( $chunk, $templateOptions );
	}



	return $return;

}


function get_snippets_for_select() {
	$snippetArgs = [ 
		'post_type' => 'wysiwyg_snippet',
		'posts_per_page' => -1,
		'orderby' => 'post_title',
		'order' => 'ASC'
	];
	$snippets = get_posts( $snippetArgs );

	$snippetsData = [];
	foreach ( $snippets as $snippet ) {
		$snippetsData[ $snippet->ID ] = $snippet->post_title;
	}

	if ( $snippets ) {
		return $snippetsData;
	} else {
		return 'No snippets found';
	}
}


function get_visibility_class_and_style( $settingsObject ) {
	// Directly use the visibility settings from the settings object
	$desktopVisibility = $settingsObject['desktop_visibility'] ?? 'true';
	$mobileVisibility = $settingsObject['mobile_visibility'] ?? 'true';

	// Initialize class and inline style
	$classes = [];
	$inlineStyle = 'display: block;';

	// Determine classes and inline style based on visibility
	if ( $desktopVisibility === 'true' && $mobileVisibility === 'false' ) {
		// Visible on desktop only
		$classes[] = 'desktop-only';

	} elseif ( $desktopVisibility === 'false' && $mobileVisibility === 'true' ) {
		// Visible on mobile only
		$classes[] = 'mobile-only';
		$inlineStyle = 'display: none;'; // Hide by default, shown on mobile
	} elseif ( $desktopVisibility === 'false' && $mobileVisibility === 'false' ) {
		// Hidden on all devices
		$inlineStyle = 'display: none !important;';
	}

	// Join all classes into a single string
	$class = implode( ' ', $classes );

	// Return the class and style as an associative array
	return [ 
		'class' => $class,
		'inlineStyle' => $inlineStyle
	];
}


add_action( 'wp_ajax_generate_template_for_preview', 'generate_template_for_preview' );
function generate_template_for_preview() {
	$templateId = $_POST['template_id'] ?? '';
	$sessionData = isset( $_POST['session_data'] ) ? $_POST['session_data'] : null;

	// Decide whether to use session data or fetch from database
	if ( $sessionData ) {
		// If session data is provided and valid, use it
		$templateData = $sessionData;
	} else {
		// Otherwise, fetch the template data from the database
		$templateData = get_wizTemplate( $templateId );
	}

	// Generate the template HTML based on the data
	$templateHtml = generate_template_html( $templateData );

	// Generate the preview pane HTML (container + empty iframe)
	$previewPane = generate_template_preview_pane( $templateHtml );

	wp_send_json_success( [ 
		'previewPaneHtml' => $previewPane, // Container + empty iframe
		'emailTemplateHtml' => $templateHtml, // The actual email template HTML to be loaded into the iframe
	] );
}


function generate_template_preview_pane( $templateHtml ) {
	$popupHtml = '<div id="previewPopup">' .
		'<div class="fullScreenButtons">' .
		'<div class="wiz-button green" id="fullModeDesktop"><i class="fa-solid fa-desktop"></i></div>' .
		'<div class="wiz-button" id="fullModeMobile"><i class="fa-solid fa-mobile-screen-button"></i></div>' .
		'<button class="wiz-button" id="hideTemplatePreview"><i class="fa-solid fa-circle-xmark fa-2x"></i></button>' .
		'</div>' .
		'<div id="fullModePreview">' .
		'<div class="previewPopupInnerScroll">' .
		'<div class="previewDisplay">' .
		'<iframe id="emailTemplatePreviewIframe" style="width:100%;height:100%;border:none;"></iframe>' .
		'</div>' .
		'</div>' .
		'</div>' .
		'</div>';

	//error_log( $popupHtml );

	return $popupHtml;
}

add_action( 'wp_ajax_generate_template_html_from_ajax', 'generate_template_html_from_ajax' );
function generate_template_html_from_ajax() {
	$_POST = stripslashes_deep( $_POST );

	$templateId = $_POST['template_id'] ?? '';
	$sessionData = isset( $_POST['session_data'] ) ? $_POST['session_data'] : null;
	if ( $sessionData ) {
		// If session data is provided and valid, use it
		$sessionData = is_string( $sessionData ) ? json_decode( $sessionData, true ) : $sessionData;
		$templateData = $sessionData;
	} else {
		// Otherwise, fetch the template data from the database
		$templateData = get_wizTemplate( $templateId );
	}
	$templateHtml = generate_template_html( $templateData, false );

	if ( $templateHtml ) {
		wp_send_json_success( [ 'templateHtml' => htmlspecialchars( $templateHtml ) ] );
	} else {
		wp_send_json_error( [ 'error' => 'Something went wrong' ] );
	}
}
function generate_template_html( $templateData, $forEditor = false ) {
	$rows = $templateData['rows'] ?? [];
	$templateOptions = $templateData['templateOptions'] ?? [];
	$templateSettings = $templateOptions['templateSettings'] ?? [];
	$templateStyles = $templateOptions['templateStyles'] ?? [];

	$return = '';

	// Email top
	$return .= idwiz_get_email_top( $templateSettings, $templateStyles, $rows );

	// iD Logo Header
	$showIdHeader = $templateSettings['template-settings']['show_id_header'] ?? true;
	if ( $showIdHeader ) {
		$return .= idwiz_get_standard_header( $templateOptions );
	}

	// Generates cols and chunks from row object
	$return .= renderTemplateRows( $templateData, $forEditor );

	// Email Footer
	if ( $templateSettings['template-settings']['show_id_footer'] !== false ) {

		// Show unsub link in footer (or not)
		$showUnsub = true;
		if ( $templateSettings['template-settings']['show_unsub'] != true ) {
			$showUnsub = false;
		}
		$return .= idwiz_get_standard_footer( $templateOptions, $showUnsub );
	}

	// Fine print/disclaimer
	if ( ! empty( $templateSettings['message-settings']['fine_print_disclaimer'] ) ) {
		$return .= idwiz_get_fine_print_disclaimer( $templateOptions );
	}

	// Email Bottom
	$return .= idwiz_get_email_bottom();

	return $return;
}

function renderTemplateRows( $templateData, $isEditor = false ) {
	$templateStyles = $templateData['templateOptions']['templateStyles'];
	$rows = $templateData['rows'];
	$return = '';

	foreach ( $rows as $rowIndex => $row ) {
		if ( $isEditor ) {
			// Row wrapper for the editor with data attributes
			$return .= "<div class='editor-row-wrapper' data-row-index='{$rowIndex}' style='position: relative;'>";
		}

		$columns = $row['columns'] ?? [];
		$numActiveColumns = count( array_filter( $columns, function ($column) {
			return $column['activation'] === 'active';
		} ) );

		$magicRtl = '';
		if ( $row['magic_wrap'] == 'on' ) {
			$magicRtl = 'dir="rtl"';
		}

		$colsClass = $numActiveColumns > 1 ? ( $numActiveColumns == 2 ? 'two-col' : 'three-col' ) : '';
		if ( $colsClass ) {
			$return .= "<div class='$colsClass' $magicRtl style='text-align: center; font-size: 0; background: transparent;'>";
			$return .= "<!--[if mso]><table role='presentation' width='100%' style='white-space:nowrap;text-align:center; background: transparent;'><tr><![endif]-->";
		}



		foreach ( $columns as $columnIndex => $column ) {
			if ( $column['activation'] === 'active' ) {


				$columnChunks = $column['chunks'];
				$templateWidth = isset( $templateStyles['body-and-background']['template_width'] ) && $templateStyles['body-and-background']['template_width'] > 0 ? $templateStyles['body-and-background']['template_width'] : 648;

				$columnWidthPx = $numActiveColumns > 1 ? round( $templateWidth / $numActiveColumns, 0 ) : $templateWidth;
				$columnWidthPct = $numActiveColumns > 1 ? round( 100 / $numActiveColumns ) : 100;
				$columnStyle = "width: 100%; font-size: {$templateStyles['font-styles']['template_font_size']}; max-width: {$columnWidthPx}px; display: inline-block; vertical-align: top;text-align: left;";
				if ( $numActiveColumns > 1 ) {
					$return .= "<!--[if mso]><td style='width:{$columnWidthPct}%;' valign='top'><![endif]-->";
					$return .= "<div class='column' style='$columnStyle' dir='ltr'>";
				}
				if ( $isEditor ) {
					// Column wrapper for the editor with data attributes
					$return .= "<div class='editor-column-wrapper' data-column-index='{$columnIndex}' style='position: relative;'>";
				}

				foreach ( $columnChunks as $chunkIndex => $chunk ) {
					if ( $isEditor ) {
						// Chunk wrapper for the editor with data attributes
						$return .= "<div class='editor-chunk-wrapper' data-chunk-index='{$chunkIndex}' style='position: relative;'>";
					}

					$chunkHtml = idwiz_get_chunk_template( $chunk, $templateData['templateOptions'] );
					$return .= $chunkHtml;

					if ( $isEditor ) {
						$return .= "</div>"; // Close .editor-chunk-wrapper
					}
				}
				if ( $isEditor ) {
					$return .= "</div>"; // Close .editor-column-wrapper
				}
				if ( $numActiveColumns > 1 ) {
					$return .= "</div>"; // Close .column div
					$return .= "<!--[if mso]></td><![endif]-->";
				}

			}
		}

		if ( $colsClass ) {
			$return .= "<!--[if mso]></tr></table><![endif]-->";
			$return .= "</div>"; // Close .two-col or .three-col div
		}

		if ( $isEditor ) {
			$return .= "</div>"; // Close .editor-row-wrapper
		}
	}

	return $return;
}
