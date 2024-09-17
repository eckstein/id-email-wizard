<?php
// Runs on plugin activation

//Get the term ID of the default folder and save it to options (runs on first page load after activation)
function idemailwiz_set_root_folder()
{
    // Retrieve the current settings
    $options = get_option('idemailwiz_settings');

    // Check if the 'folder_base' setting is already set
    if (empty($options['folder_base'])) {
        // Create a new term for the root folder
        $trashTerm = wp_insert_term('Trash', 'idemailwiz_folder', array('slug' => 'trash'));

        // Check if the term was created successfully
        if (!is_wp_error($trashTerm)) {
            // Update the 'folder_base' setting with the newly created term ID
            $options['folder_base'] = $trashTerm['term_id'];

            // Save the updated options back to the database
            update_option('idemailwiz_settings', $options);
        }
    }
}

//Create a term for the trash folder (runs on first page load after activation)
function idemailwiz_set_trash_term()
{
    // Retrieve the current settings
    $options = get_option('idemailwiz_settings');

    // Check if the 'folder_trash' setting is already set
    if (empty($options['folder_trash'])) {
        // Create a new term for the trash folder
        $trashTerm = wp_insert_term('Trash', 'idemailwiz_folder', array('slug' => 'trash'));

        // Check if the term was created successfully
        if (!is_wp_error($trashTerm)) {
            // Update the 'folder_trash' setting with the newly created term ID
            $options['folder_trash'] = $trashTerm['term_id'];

            // Save the updated options back to the database
            update_option('idemailwiz_settings', $options);
        }
    }
}