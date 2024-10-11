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

		if ($templateData && $templateData['template_options'] && !empty($templateData['template_options'])) {
			// Preview pane styles
			include dirname(plugin_dir_path(__FILE__)) . '/builder-v2/preview-pane-styles.html';

			echo '<div class="template-preview-loader">Loading template...</div>';

			// Add spacer for proper scrolling in preview pane
			echo '<div style="height: 100vh; color: #cdcdcd; padding: 20px; font-family: Poppins, sans-serif; text-align: center; border-top: 2px dashed #fff;" class="scrollSpace"><em>The extra space below allows proper scrolling in the builder and will not appear in the template</em></div>';
		} else {
			echo '<div style="height: 100vh; color: #cdcdcd; padding: 20px; font-family: Poppins, sans-serif; text-align: center; border-top: 2px dashed #fff;">Start adding sections and your preview will show here.</div>';
		}
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


