<?php
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
    $html = generate_builder_column($rowId, $columnIndex);

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

    $chunkBeforeId = isset($_POST['chunk_before_id']) ? intval($_POST['chunk_before_id']) : null;
    $chunkType = sanitize_text_field($_POST['chunk_type']);

    // Generate a new chunk ID
    $newChunkId = $chunkBeforeId !== null ? $chunkBeforeId + 1 : 0;

    if (isset($_POST['duplicate']) && $_POST['chunk_data'] !== null) {
        $chunkData = $_POST['chunk_data'];
        error_log(print_r($chunkData, true));
    } else {
        $chunkData = [];
    }

    $html = generate_builder_chunk($newChunkId, $chunkType, $chunkData);

    error_log($html);

    wp_send_json_success(['html' => $html, 'chunk_id' => $newChunkId]);
}





function get_chunk_preview($chunkData = [], $chunkType = null)
{

    if (!$chunkType) {
        return;
    }
    $chunkPreview = ucwords(str_replace('-', ' ', $chunkType));

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

    if ($chunkType == 'icon-list' && isset($chunkData['fields'])) {
        $image = $chunkData['fields']['image_url'] ?? 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/full-width-image.jpg';
        if ($image) {
            $chunkPreview = '<div class="image-chunk-preview-wrapper"><img src="' . $image . '" /></div>';
        }
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






