<?php


// Override wpautop filter and curly quotes for ACF WYSIWYG fields
function idemailwiz_acf_wywiwyg_custom()
{
    // Remove curly quotes
    remove_filter('acf_the_content', 'wptexturize');

    // Disable wpautop
    //remove_filter('acf_the_content', 'wpautop');

}
add_action('init', 'idemailwiz_acf_wywiwyg_custom');



// Modify the default settings for ACF Tiny MCE fields
function custom_acf_wysiwyg_toolbars($toolbars)
{
    // Check if the 'items' index exists in the expected location
    if (isset($toolbars['Basic'][1]['items'])) {
        // Modify the B button to wrap text with <strong> tags
        $toolbars['Basic'][1]['items'] .= ',strong';
    }
    return $toolbars;
}
add_filter('acf/fields/wysiwyg/toolbars', 'custom_acf_wysiwyg_toolbars');



// Modify validation for acf URL fields to allow for handlebar inputs
add_filter('acf/validate_value/type=url', 'allow_handlebars_in_acf_url', 99, 4);

function allow_handlebars_in_acf_url($valid, $value, $field, $input_name)
{
    // If the value starts with "{{", consider it valid
    if (strpos($value, '{{') === 0) {
        $valid = true;
    }

    // Return the validity status
    return $valid;
}

// Setup up custom tinyMCE buttons and menu for merge tags
add_action('init', 'merge_tags_button');

function merge_tags_button()
{
    add_filter('mce_external_plugins', 'add_merge_tags_button');
    add_filter('mce_buttons', 'register_merge_tags_button');
}

function add_merge_tags_button($plugin_array)
{
    $plugin_array['merge_tags_button'] = plugins_url('js/mergeTags.js', IDEMAILWIZ_ROOT);
    return $plugin_array;
}

function register_merge_tags_button($buttons)
{
    array_push($buttons, 'merge_tags_button');
    return $buttons;
}





/**
 * Removes buttons from the first row of the tiny mce editor
 *
 * @link     http://thestizmedia.com/remove-buttons-items-wordpress-tinymce-editor/
 *
 * @param    array    $buttons    The default array of buttons
 * @return   array                The updated array of buttons that exludes some items
 */
add_filter('mce_buttons', 'id_remove_tiny_mce_buttons_from_editor');
function id_remove_tiny_mce_buttons_from_editor($buttons)
{

    $remove_buttons = array(
        'formatselect',
        // format dropdown menu for <p>, headings, etc
        'blockquote',
        'wp_more',
        // read more link
        'spellchecker',
        'fontselect',
        'fullscreen',
        'alignleft',
        'aligncenter',
        'alignright',
        'dfw',
        // distraction free writing mode
        'wp_adv',
        // kitchen sink toggle (if removed, kitchen sink will always display)
    );
    foreach ($buttons as $button_key => $button_value) {
        if (in_array($button_value, $remove_buttons)) {
            unset($buttons[$button_key]);
        }
    }
    return $buttons;
}

/**
 * Removes buttons from the second row (kitchen sink) of the tiny mce editor
 *
 * @link     http://thestizmedia.com/remove-buttons-items-wordpress-tinymce-editor/
 *
 * @param    array    $buttons    The default array of buttons in the kitchen sink
 * @return   array                The updated array of buttons that exludes some items
 */
add_filter('mce_buttons_2', 'id_remove_tiny_mce_buttons_from_kitchen_sink');
function id_remove_tiny_mce_buttons_from_kitchen_sink($buttons)
{

    $remove_buttons = array(

        'alignjustify',
        'charmap',
        // special characters
        'outdent',
        'indent',
        'hr',
        'wp_help',
        // keyboard shortcuts
    );
    foreach ($buttons as $button_key => $button_value) {
        if (in_array($button_value, $remove_buttons)) {
            unset($buttons[$button_key]);
        }
    }
    return $buttons;
}