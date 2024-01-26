<?php


// Override curly quotes for ACF WYSIWYG fields
function idemailwiz_acf_wysiwyg_custom()
{
    // Remove curly quotes
    remove_filter('acf_the_content', 'wptexturize');

}
add_action('init', 'idemailwiz_acf_wysiwyg_custom');



// Modify the default settings for ACF Tiny MCE fields
function custom_acf_wysiwyg_toolbars($toolbars)
{

    // Check if the 'items' index exists in the expected location
    if (isset($toolbars['Basic'][1]['items'])) {
        // Add the 'fontsize' selector to the toolbar
        $toolbars['Basic'][1]['items'] .= ',fontsize';
        $toolbars['Basic'][1]['items'] .= ',textcolor';
    }
    return $toolbars;
}
add_filter('acf/fields/wysiwyg/toolbars', 'custom_acf_wysiwyg_toolbars');






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
        //'formatselect',
        // format dropdown menu for <p>, headings, etc
        'blockquote',
        'wp_more',
        // read more link
        'spellchecker',
        'fontselect',
        //'fullscreen',
        'alignleft',
        'aligncenter',
        'alignright',
        'dfw',
        // distraction free writing mode
        //'wp_adv',
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

// Set up custom tinyMCE plugins
add_action('init', 'custom_tinymce_plugins');

function custom_tinymce_fontsize_formats($initArray) {
    // Define the font size formats you want to include
    $initArray['fontsize_formats'] = '0.75em 1em 1.25em 1.5em 1.75em 2em 2.5em 3em';
    return $initArray;
}

// Modify your custom_tinymce_plugins function to include this new filter
function custom_tinymce_plugins() {
    add_filter('mce_external_plugins', 'add_custom_tinymce_plugins');
    add_filter('mce_buttons', 'register_custom_tinymce_buttons');
    add_filter('tiny_mce_before_init', 'custom_tinymce_fontsize_formats'); 
}
function add_custom_tinymce_plugins($plugin_array)
{
    // URL to the JavaScript file containing the TinyMCE plugin definitions
    $custom_plugins_url = plugins_url('js/mergeTags.js', IDEMAILWIZ_ROOT);

    // Add each plugin from the script
    $plugin_array['merge_tags_button'] = $custom_plugins_url; // Plugin for merge tags
    $plugin_array['capitalize_button'] = $custom_plugins_url; // Plugin for capitalize functionality
    // Add more plugins here if necessary

    return $plugin_array;
}

function register_custom_tinymce_buttons($buttons)
{
    // Find the index of the "italic" button
    $italicIndex = array_search('italic', $buttons);

    // Check if the "italic" button exists in the array
    if ($italicIndex !== false) {
        // Insert the 'capitalize_button' right after the 'italic' button
        array_splice($buttons, $italicIndex + 1, 0, 'capitalize_button');
    } else {
        // If the 'italic' button isn't found, add your button at the end
        array_push($buttons, 'capitalize_button');
    }

    // Include other custom buttons as necessary
    array_push($buttons, 'merge_tags_button');
    
    return $buttons;
}

