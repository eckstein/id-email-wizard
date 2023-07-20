<?php

//remove curly brackets from wysiwyg
function my_acf_remove_curly_quotes() {
    remove_filter ('acf_the_content', 'wptexturize');
}
add_action('acf/init', 'my_acf_remove_curly_quotes');

// Modify the default font family for all ACF WYSIWYG fields
add_filter('tiny_mce_before_init', function($init) {
  $init['content_style'] = "body { font-family: 'Poppins', sans-serif; }";
  return $init;
});

//change <b> to <strong> in tinymce
function custom_acf_wysiwyg_toolbars( $toolbars ) {
  // Check if the 'items' index exists in the expected location
  if ( isset( $toolbars['Basic'][1]['items'] ) ) {
    // Modify the B button to wrap text with <strong> tags
    $toolbars['Basic'][1]['items'] .= ',strong';
  }
  return $toolbars;
}
add_filter( 'acf/fields/wysiwyg/toolbars', 'custom_acf_wysiwyg_toolbars' );

//custom editor styles
add_editor_style('custom-editor.css');


// Custom wpautop for ACF WYSIWYG fields
function my_acf_wysiwyg_custom_wpautop($content) {
    $content = wpautop($content, false);

    // Replace </p> with two <br/> tags
    $content = str_replace("</p>", "<br/>", $content);

    // Remove <p> tags
    $content = str_replace("<p>", "", $content);

    return $content;
}

// Override wpautop filter for ACF WYSIWYG fields
function my_acf_override_wpautop() {
    remove_filter('acf_the_content', 'wpautop');
    add_filter('acf_the_content', 'my_acf_wysiwyg_custom_wpautop');
}
add_action('init', 'my_acf_override_wpautop');



//Setup up custom tinyMCE buttons and menu
add_action( 'init', 'merge_tags_button' );

function merge_tags_button() {
    add_filter( 'mce_external_plugins', 'add_merge_tags_button' );
    add_filter( 'mce_buttons', 'register_merge_tags_button' );
}

function add_merge_tags_button( $plugin_array ) {
    $plugin_array['merge_tags_button'] = plugins_url('js/mergeTags.js', IDEMAILWIZ_ROOT);
    return $plugin_array;
}

function register_merge_tags_button( $buttons ) {
    array_push( $buttons, 'merge_tags_button' );
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
add_filter( 'mce_buttons', 'id_remove_tiny_mce_buttons_from_editor');
function id_remove_tiny_mce_buttons_from_editor( $buttons ) {

    $remove_buttons = array(
        'formatselect', // format dropdown menu for <p>, headings, etc
		'blockquote',
        'wp_more', // read more link
        'spellchecker',
        'fontselect',
        'fullscreen',
		'alignleft',
		'aligncenter',
		'alignright',
        'dfw', // distraction free writing mode
        'wp_adv', // kitchen sink toggle (if removed, kitchen sink will always display)
    );
    foreach ( $buttons as $button_key => $button_value ) {
        if ( in_array( $button_value, $remove_buttons ) ) {
            unset( $buttons[ $button_key ] );
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
add_filter( 'mce_buttons_2', 'id_remove_tiny_mce_buttons_from_kitchen_sink');
function id_remove_tiny_mce_buttons_from_kitchen_sink( $buttons ) {

    $remove_buttons = array(
        
        'alignjustify',
        'charmap', // special characters
        'outdent',
        'indent',
		'hr',
        'wp_help', // keyboard shortcuts
    );
    foreach ( $buttons as $button_key => $button_value ) {
        if ( in_array( $button_value, $remove_buttons ) ) {
            unset( $buttons[ $button_key ] );
        }
    }
    return $buttons;
}