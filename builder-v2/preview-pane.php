<?php

ob_start();

// Preview pane styles
include dirname(plugin_dir_path(__FILE__)) . '/builder-v2/preview-pane-styles.html';

// Email top
include dirname(plugin_dir_path(__FILE__)) . '/builder-v2/chunks/email-top.php';

// Build chunks
if (!empty($chunks)) {
    foreach ($chunks as $chunkId => $chunk) {
        $chunkFileName = str_replace('_', '-', $chunk['acf_fc_layout']);
        $file = dirname(plugin_dir_path(__FILE__)) . '/builder-v2/chunks/' . $chunkFileName . '.php';

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

            echo "<div class='chunkWrap $chunkSepsClass' data-id='$chunkId' data-chunk-layout='{$chunk['acf_fc_layout']}' data-desktop-visibility='$desktopVisibility' data-mobile-visibility='$mobileVisibility'>";
            echo $html;
            echo "<div class='chunkOverlay'><span class='chunk-label'>Chunk Type: {$chunk['acf_fc_layout']}</span><button class='showChunkCode' data-id='$chunkId' data-templateid='$template_id'>Get Code</button></div>";
            echo '</div>';
        }
    }
}

// Fine print/disclaimer
if (!empty($templateSettings['fine_print_disclaimer'])) {
    include dirname(plugin_dir_path(__FILE__)) . '/builder-v2/chunks/fine-print-disclaimer.php';
}
// Email bottom
if ($templateSettings['id_tech_footer'] == true) {
    include dirname(plugin_dir_path(__FILE__)) . '/builder-v2/chunks/email-bottom.php';
}



// Output and terminate
if (wp_doing_ajax()) {
    echo ob_get_clean();
    // Add spacer for proper scrolling in preview pane
    echo '<div style="height: 100vh; color: #cdcdcd; padding: 20px; font-family: Poppins, sans-serif; text-align: center; border-top: 2px dashed #fff;" class="scrollSpace"><em>The extra space below allows proper scrolling in the builder and will not appear in the template</em></div>';
    wp_die();
} else {
    return ob_get_clean();
}
