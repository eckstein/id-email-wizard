<?php
// Hook into template redirect and detect if we're on the template builder page
add_action('template_redirect', 'idemailwiz_handle_template_request', 20);
function idemailwiz_handle_template_request()
{
    global $wp_query, $wp;

    // Handle build-template
    if (isset($wp_query->query_vars['build-template'])) {

        $current_url = home_url(add_query_arg(array(), $wp->request));
        if (strpos($current_url, '/build-template/') !== false && !isset($_SERVER['HTTP_REFERER'])) {
            $dieMessage = 'Direct access to the template builder endpoint is not allowed!';
            wp_die($dieMessage);
            exit;
        }

        echo '<div style="padding: 30px; text-align: center; font-weight: bold; font-family: Poppins, sans-serif;"><i style="font-family: Font Awesome 5;" class="fas fa-spinner fa-spin"></i>  Loading template...<br/>';
        exit;
    }
}

add_action('template_redirect', 'idemailwiz_handle_builder_v2_request', 20);
function idemailwiz_handle_builder_v2_request()
{
    global $wp_query, $wp;

    // Handle build-template
    if (isset($wp_query->query_vars['build-template-v2'])) {

        $current_url = home_url(add_query_arg(array(), $wp->request));
        if (strpos($current_url, '/build-template-v2/') !== false && !isset($_SERVER['HTTP_REFERER'])) {
            $dieMessage = 'Direct access to the template builder endpoint is not allowed!';
            wp_die($dieMessage);
            exit;
        }

        echo '<div style="padding: 30px; text-align: center; font-weight: bold; font-family: Poppins, sans-serif;"><i style="font-family: Font Awesome 5;" class="fas fa-spinner fa-spin"></i>  Loading template...<br/>';
        exit;
    }
}

add_action('wp_ajax_idemailwiz_build_template', 'idemailwiz_build_template');
function idemailwiz_build_template()
{
    // Validate AJAX and nonce
    if (!wp_doing_ajax() || !check_ajax_referer('template-editor', 'security', false)) {
        wp_die();
    }

    // Validate POST action
    if ($_POST['action'] !== 'idemailwiz_build_template') {
        wp_die();
    }



    // Initialize flags and settings
    $mergeTags = $_POST['mergetags'] === 'true';
    $chunkSeps = $_POST['showseps'] === 'true';
    $template_id = $_POST['templateid'];
    $chunkSepsClass = $chunkSeps ? '' : 'hide-seps';
    $previewMode = $_POST['previewMode'] ?? 'desktop';

    // Prepare form data
    $formData = array_map('stripslashes_deep', $_POST);
    $formData = convert_keys_to_names($formData);
    $fields = $formData['acf'];
    $chunks = $fields['add_chunk'];
    $templateSettings = $fields['template_settings'];
    $templateStyles = $fields['template_styles'];
    $externalUTMs = $fields['email_settings']['external_utms'] ?? false;
    $externalUTMstring = $fields['email_settings']['external_utm_string'] ?? '';

    $wizSettings = get_option('idemailwiz_settings');
    $builderVersion = $wizSettings['wysiwyg_builder_version'];

    if ($builderVersion == 'v2') {
        include dirname(plugin_dir_path(__FILE__)) . '/builder-v2/preview-pane.php';
    } else {


        // Start output buffering
        ob_start();

        // Include styles for preview pane
        include dirname(plugin_dir_path(__FILE__)) . '/styles/preview-pane-styles.html';

        // Include template chunk sections
        include dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/email-top.php';
        include dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/css.php';
        include dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/end-email-top.php';

        // Standard email header
        if ($templateSettings['id_tech_header'] == true) {
            include dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/preview-header.php';
        }

        // Build chunks
        if (!empty($chunks)) {
            foreach ($chunks as $chunkId => $chunk) {
                $chunkFileName = str_replace('_', '-', $chunk['acf_fc_layout']);
                $file = dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/' . $chunkFileName . '.php';

                if (file_exists($file)) {
                    ob_start();
                    include $file;
                    $html = ob_get_clean();

                    if ($mergeTags) {
                        $html = idwiz_apply_mergemap($html);
                    }

                    if ($externalUTMs) {
                        $html = idwiz_add_utms($html, $externalUTMstring);
                    }

                    // Determine if the chunk should be hidden based on the preview mode
                    $hiddenClass = '';
                    if ($previewMode == 'desktop' && isset($chunk['chunk_settings']['desktop_visibility']) && !$chunk['chunk_settings']['desktop_visibility']) {
                        $hiddenClass = 'hiddenChunk';
                    } elseif ($previewMode == 'mobile' && isset($chunk['chunk_settings']['mobile_visibility']) && !$chunk['chunk_settings']['mobile_visibility']) {
                        $hiddenClass = 'hiddenChunk';
                    }

                    $desktopVisibility = isset($chunk['chunk_settings']['desktop_visibility']) ? $chunk['chunk_settings']['desktop_visibility'] : true;
                    $mobileVisibility = isset($chunk['chunk_settings']['mobile_visibility']) ? $chunk['chunk_settings']['mobile_visibility'] : true;

                    echo "<div class='chunkWrap $chunkSepsClass $hiddenClass' data-id='$chunkId' data-chunk-layout='{$chunk['acf_fc_layout']}' data-desktop-visibility='$desktopVisibility' data-mobile-visibility='$mobileVisibility'>";
                    echo $html;
                    echo "<div class='chunkOverlay'><span class='chunk-label'>Chunk Type: {$chunk['acf_fc_layout']}</span><button class='showChunkCode' data-id='$chunkId' data-templateid='$template_id'>Get Code</button></div>";
                    echo '</div>';

                }
            }
        } else {
            echo '<div style="color: #343434; padding: 20px; margin-top: 20px; font-family: Arial, sans-serif; text-align: center;"><span style="font-size: 20px;"><strong>Choose a chunk to start building your layout here.</strong></span><br/><br/><em>Hint: You can turn off the default header and footer sections from the settings tab.</em></div>';
        }


        // Email footer
        if ($templateSettings['id_tech_footer'] == true) {
            include dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/preview-footer.php';
        }

        // Fine print/disclaimer
        if (!empty($templateSettings['fine_print_disclaimer'])) {
            include dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/email-before-disclaimer.php';
            include dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/fine-print-disclaimer.php';
            include dirname(plugin_dir_path(__FILE__)) . '/templates/chunks/email-after-disclaimer.php';
        }

        // Output and terminate
        if (wp_doing_ajax()) {
            echo ob_get_clean();
            echo '<div style="height: 100vh; color: #cdcdcd; padding: 20px; font-family: Arial, sans-serif; text-align: center; border-top: 2px dashed #fff;" class="scrollSpace"><em>The extra space below allows proper scrolling in the builder and will not appear in the template</em></div>';
            wp_die();
        } else {
            return ob_get_clean();
        }
    }

}


