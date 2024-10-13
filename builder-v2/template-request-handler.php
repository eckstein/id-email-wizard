<?php
add_action('template_redirect', 'idwiz_handle_preview_frame_redirect', 20);
// Loads our default iframe content (styles and initial loader)
function idwiz_handle_preview_frame_redirect()
{
	global $wp_query;

	// Handle build-template
	if (isset($wp_query->query_vars['template-frame'])) {

		// Preview pane styles
		include dirname(plugin_dir_path(__FILE__)) . '/builder-v2/preview-pane-styles.html';

		echo '<div class="template-preview-loader">Loading template...</div>';
		exit;
	}
}

function idemailwiz_custom_archive_templates($tpl)
{
	if (is_post_type_archive('idwiz_initiative')) {
		$tpl = dirname(plugin_dir_path(__FILE__)) . '/templates/archive-initiative.php';
	}

	if (is_post_type_archive('idwiz_comparison')) {
		$tpl = dirname(plugin_dir_path(__FILE__)) . '/templates/archive-comparison.php';
	}

	if (is_post_type_archive('wiz_promo_code')) {
		$tpl = dirname(plugin_dir_path(__FILE__)) . '/templates/archive-promo-code.php';
	}

	return $tpl;
}

add_filter('archive_template', 'idemailwiz_custom_archive_templates');


