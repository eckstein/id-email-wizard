<?php
// Initialize flags and settings
$mergeTags = $_POST['mergetags'] === 'true';
$template_id = $_POST['templateid'];
$previewMode = $_POST['previewMode'] ?? 'desktop';

// Prepare form data
$formData = array_map( 'stripslashes_deep', $_POST );
$formData = convert_keys_to_names( $formData );
$fields = $formData['acf'];
$chunks = $fields['add_chunk'];
$templateSettings = $fields['template_settings'];
$templateStyles = $fields['template_styles'];
$emailSettings = $fields['email_settings'];
$externalUTMs = $fields['email_settings']['external_utms'] ?? false;
$externalUTMstring = $fields['email_settings']['external_utm_string'] ?? '';


// Show unsub link in footer (or not)
$showUnsub = true;
if ( $templateSettings['show_unsubscribe_link'] != true ) {
    $showUnsub = false;
}

ob_start();

// Preview pane styles
include dirname( plugin_dir_path( __FILE__ ) ) . '/builder-v2/preview-pane-styles.html';

// Email top
echo idwiz_get_email_top($chunks, $templateSettings, $templateStyles, $emailSettings);

// iD Logo Header
if ( $templateSettings['id_tech_header'] == true ) {
    echo idwiz_get_standard_header();
}

// Build chunks
if ( ! empty( $chunks ) ) {
	foreach ( $chunks as $chunkId => $chunk ) {
		//print_r($chunk);
		$deviceVisibility = $chunk['chunk_settings']['visibility'] ?? [];
		//print_r($deviceVisibility);
		$desktopVisibility = $deviceVisibility['desktop_visibility'] ?? true;
		$mobileVisibility = $deviceVisibility['mobile_visibility'] ?? true;
		
		$chunkType = $chunk['acf_fc_layout'];

		// Get base chunk template
		// Set the final arg to TRUE indicating we're generating a preview, which will include the child chunk-wraps on column children elements
		$html = idwiz_get_chunk_template( $chunk, null, null, null, true );		

		// Add merge tags, if active
		if ( $mergeTags ) {
			$html = idwiz_apply_mergemap( $html );
		}

		// Add external UTMs, if active
		if ( $externalUTMs ) {
			$html = idwiz_add_utms( $html, $externalUTMstring );
		}

		// Echo out the final chunk HTML with the chunkWrap class for the builder
		echo "<div class='chunkWrap' data-id='$chunkId' data-chunk-layout='{$chunk['acf_fc_layout']}' data-desktop-visibility='$desktopVisibility' data-mobile-visibility='$mobileVisibility'>";
		echo $html;
		echo "<div class='chunkOverlay'><span class='chunk-label'>Chunk Type: {$chunk['acf_fc_layout']}</span><button class='showChunkCode' data-id='$chunkId' data-templateid='$template_id'>Get Code</button></div>";
		echo '</div>';

	}
}


// Email Footer
if ( $templateSettings['id_tech_footer'] == true ) {
        
    
	//echo idwiz_standard_row_wrap(idwiz_get_standard_footer($showUnsub), true);
	echo idwiz_get_standard_footer($showUnsub);
}

// Fine print/disclaimer
if ( ! empty( $templateSettings['fine_print_disclaimer'] ) ) {
	echo idwiz_get_fine_print_disclaimer( $templateSettings['fine_print_disclaimer'] );
}

// Email Bottom
echo idwiz_get_email_bottom();


// Add spacer for proper scrolling in preview pane
echo '<div style="height: 100vh; color: #cdcdcd; padding: 20px; font-family: Poppins, sans-serif; text-align: center; border-top: 2px dashed #fff;" class="scrollSpace"><em>The extra space below allows proper scrolling in the builder and will not appear in the template</em></div>';

// Output and terminate
if ( wp_doing_ajax() ) {
	echo ob_get_clean();
	wp_die();
} else {
	return ob_get_clean();
}
