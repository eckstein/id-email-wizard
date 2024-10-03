<?php



function wiz_get_modal_content()
{
    // Verify the nonce for security
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    if (!wp_verify_nonce($nonce, 'template-editor')) {
        wp_send_json_error(['message' => 'Nonce verification failed']);
        return;
    }

    // Get the data from the AJAX request
    $data = isset($_POST['data']) ? $_POST['data'] : [];

    // Check if modal_type is set
    if (!isset($data['modal_type'])) {
        wp_send_json_error(['message' => 'Modal type not specified']);
        return;
    }

    $modal_type = sanitize_text_field($data['modal_type']);
    $title = isset($data['title']) ? sanitize_text_field($data['title']) : 'Untitled';

    // Generate the modal content
    $modal_html = generate_wiz_modal($modal_type, $title, $data);

    wp_send_json_success(['html' => $modal_html]);
}

add_action('wp_ajax_wiz_get_modal_content', 'wiz_get_modal_content');
add_action('wp_ajax_nopriv_wiz_get_modal_content', 'wiz_get_modal_content');

function generate_wiz_modal($modal_type, $title, $data)
{
    ob_start();
?>
    <div class="wiz-modal-container" data-modal-type="<?php echo esc_attr($modal_type); ?>">
        <div class="wiz-modal">
            <div class="wiz-modal-content">
                <div class="wiz-modal-header">
                    <h4><?php echo esc_html($title); ?></h4>
                    <button class="wiz-modal-close wiz-button red">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                <div class="wiz-modal-body">
                    <?php echo generate_modal_content($modal_type, $data); ?>
                </div>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}

function generate_modal_content($modal_type, $data)
{
    switch ($modal_type) {
        case 'edit_interactive':
            return interactive_content_editor($data);
            break;
        default:
            return "Invalid modal type.";
    }
}


// Specifical Modal rendering

function interactive_content_editor($data)
{
    $postId = isset($data['post_id']) ? intval($data['post_id']) : 0;
    if (!$postId) {
        return "Invalid post ID.";
    }
    $interactiveType = isset($data['interactive_type']) ? sanitize_text_field($data['interactive_type']) : '';
    if (!$interactiveType) {
        return "Invalid interactive type.";
    }
    ob_start();
?>
    Edit interactive content here
<?php
    return ob_get_clean();
}
