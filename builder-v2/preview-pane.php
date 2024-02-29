<?php
// Initialize flags and settings
//print_r($templateData);
$mergeTags = $_POST['mergetags'] ?? false === 'true';
$previewMode = $_POST['previewMode'] ?? 'desktop';

$rows = $templateData;
ob_start();

// Preview pane styles
include dirname( plugin_dir_path( __FILE__ ) ) . '/builder-v2/preview-pane-styles.html';

echo generate_template_html($templateData, true);


// Add spacer for proper scrolling in preview pane
echo '<div style="height: 100vh; color: #cdcdcd; padding: 20px; font-family: Poppins, sans-serif; text-align: center; border-top: 2px dashed #fff;" class="scrollSpace"><em>The extra space below allows proper scrolling in the builder and will not appear in the template</em></div>';

// Output and terminate
if ( wp_doing_ajax() ) {
	echo ob_get_clean();
	wp_die();
} else {
	return ob_get_clean();
}



