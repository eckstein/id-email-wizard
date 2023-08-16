<?php
function idemailwiz_code_repository_shortcode() {
	ob_start();
	include (dirname(plugin_dir_path( __FILE__ )) . '/templates/idemailwiz-code-repo.php');
	return ob_get_clean();
}
add_shortcode('idwiz_code_repo', 'idemailwiz_code_repository_shortcode');


