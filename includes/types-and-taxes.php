<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register custom post types
add_action('init', 'idwiz_register_custom_post_types', 0);
function idwiz_register_custom_post_types()
{


    $promoCodeLabels = array(
        'name' => 'Promo Codes',
        'singular_name' => 'Promo Code',
        'menu_name' => __('Promo Codes', 'idemailwiz'),
        'name_admin_bar' => __('Promo Code', 'idemailwiz'),
        'archives' => __('Promo Code Archives', 'idemailwiz'),
        'attributes' => __('Promo Code Attributes', 'idemailwiz'),
        'parent_item_colon' => __('Parent Promo Code:', 'idemailwiz'),
        'all_items' => __('All Promo Codes', 'idemailwiz'),
        'add_new_item' => __('Add New Promo Code', 'idemailwiz'),
        'add_new' => __('Add New', 'idemailwiz'),
        'new_item' => __('New Promo Code', 'idemailwiz'),
        'edit_item' => __('Edit Promo Code', 'idemailwiz'),
        'update_item' => __('Update Promo Code', 'idemailwiz'),
        'view_item' => __('View Promo Code', 'idemailwiz'),
        'view_items' => __('View Promo Codes', 'idemailwiz'),
        'search_items' => __('Search Promo Code', 'idemailwiz'),
        'insert_into_item' => __('Insert into promo code', 'idemailwiz'),
        'uploaded_to_this_item' => __('Uploaded to this promo code', 'idemailwiz'),
        'items_list' => __('Promo codes list', 'idemailwiz'),
        'items_list_navigation' => __('Promo codes list navigation', 'idemailwiz'),
        'filter_items_list' => __('Filter promo codes list', 'idemailwiz'),
    );




    $promoCodeArgs = array(
        'labels' => $promoCodeLabels,
        'public' => true,
        'has_archive' => 'promo-codes',
        'supports' => array('title', 'custom-fields'),
        'rewrite' => array(
            'slug' => 'promo-code',
            'with_front' => false
        ),
    );
    register_post_type('wiz_promo_code', $promoCodeArgs);

    function custom_promo_code_rewrite_rules()
    {
        add_rewrite_rule(
            'promo-code/([0-9]+)/?$',
            'index.php?post_type=wiz_promo_code&p=$matches[1]',
            'top'
        );

        // Preserve the archive page rule
        add_rewrite_rule(
            'promo-codes/?$',
            'index.php?post_type=wiz_promo_code',
            'top'
        );
    }
    add_action('init', 'custom_promo_code_rewrite_rules', 10, 0);

    function custom_promo_code_post_link($post_link, $post)
    {
        if ($post->post_type === 'wiz_promo_code') {
            return home_url("promo-code/{$post->ID}/");
        }
        return $post_link;
    }
    add_filter('post_type_link', 'custom_promo_code_post_link', 10, 2);

    function custom_promo_code_request($query_vars)
    {
        if (
            isset($query_vars['post_type']) && $query_vars['post_type'] === 'wiz_promo_code'
            && isset($query_vars['name'])
        ) {
            $query_vars['p'] = $query_vars['name'];
            unset($query_vars['name']);
        }
        return $query_vars;
    }
    add_filter('request', 'custom_promo_code_request');

    $templateLabels = array(
        'name' => 'Templates',
        'singular_name' => 'Template',
        // Add other labels as needed
    );

    $templateArgs = array(
        'labels' => $templateLabels,
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
        'show_in_rest' => true, // This is required if you want to use this post type with Gutenberg
        'rewrite' => array(
            'slug' => 'template', // This is the base slug for your templates
            'with_front' => false, // This ensures that the slug is exactly what you specify, not prepended with a front base
        ),
    );




    register_post_type('idemailwiz_template', $templateArgs);

    $initiativeLabels = array(
        'name' => _x('Initiatives', 'Post Type General Name', 'idemailwiz'),
        'singular_name' => _x('Initiative', 'Post Type Singular Name', 'idemailwiz'),
        'menu_name' => __('Initiatives', 'idemailwiz'),
        'name_admin_bar' => __('Initiative', 'idemailwiz'),
        'archives' => __('Initiative Archives', 'idemailwiz'),
        'attributes' => __('Initiative Attributes', 'idemailwiz'),
        'parent_item_colon' => __('Parent Initiative:', 'idemailwiz'),
        'all_items' => __('All Initiatives', 'idemailwiz'),
        'add_new_item' => __('Add New Initiative', 'idemailwiz'),
        'add_new' => __('Add New', 'idemailwiz'),
        'new_item' => __('New Initiative', 'idemailwiz'),
        'edit_item' => __('Edit Initiative', 'idemailwiz'),
        'update_item' => __('Update Initiative', 'idemailwiz'),
        'view_item' => __('View Initiative', 'idemailwiz'),
        'view_items' => __('View Initiatives', 'idemailwiz'),
        'search_items' => __('Search Initiative', 'idemailwiz'),
        'not_found' => __('Not found', 'idemailwiz'),
        'not_found_in_trash' => __('Not found in Trash', 'idemailwiz'),
        'featured_image' => __('Featured Image', 'idemailwiz'),
        'set_featured_image' => __('Set featured image', 'idemailwiz'),
        'remove_featured_image' => __('Remove featured image', 'idemailwiz'),
        'use_featured_image' => __('Use as featured image', 'idemailwiz'),
        'insert_into_item' => __('Insert into initiative', 'idemailwiz'),
        'uploaded_to_this_item' => __('Uploaded to this initiative', 'idemailwiz'),
        'items_list' => __('Initiatives list', 'idemailwiz'),
        'items_list_navigation' => __('Initiatives list navigation', 'idemailwiz'),
        'filter_items_list' => __('Filter initiatives list', 'idemailwiz'),
    );

    $initiativeArgs = array(
        'label' => __('Initiative', 'idemailwiz'),
        'description' => __('Initiative Description', 'idemailwiz'),
        'labels' => $initiativeLabels,
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields', 'revisions', 'page-attributes'),
        'taxonomies' => array('category', 'post_tag'),
        // Optional
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 5,
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => true,
        'can_export' => true,
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'rewrite' => ['slug' => 'initiative'],
        'has_archive' => 'initiatives',
        'capability_type' => 'post',
        'show_in_rest' => true,
        // Enable Gutenberg editor
    );

    register_post_type('idwiz_initiative', $initiativeArgs);

    $comparisonLabels = array(
        'name' => _x('Comparisons', 'Post Type General Name', 'idemailwiz'),
        'singular_name' => _x('Comparison', 'Post Type Singular Name', 'idemailwiz'),
        'menu_name' => __('Comparisons', 'idemailwiz'),
        'name_admin_bar' => __('Comparison', 'idemailwiz'),
        'archives' => __('Comparison Archives', 'idemailwiz'),
        'attributes' => __('Comparison Attributes', 'idemailwiz'),
        'parent_item_colon' => __('Parent Comparison:', 'idemailwiz'),
        'all_items' => __('All Comparisons', 'idemailwiz'),
        'add_new_item' => __('Add New Comparison', 'idemailwiz'),
        'add_new' => __('Add New', 'idemailwiz'),
        'new_item' => __('New Comparison', 'idemailwiz'),
        'edit_item' => __('Edit Comparison', 'idemailwiz'),
        'update_item' => __('Update Comparison', 'idemailwiz'),
        'view_item' => __('View Comparison', 'idemailwiz'),
        'view_items' => __('View Comparisons', 'idemailwiz'),
        'search_items' => __('Search Comparison', 'idemailwiz'),
        'not_found' => __('Not found', 'idemailwiz'),
        'not_found_in_trash' => __('Not found in Trash', 'idemailwiz'),
        'featured_image' => __('Featured Image', 'idemailwiz'),
        'set_featured_image' => __('Set featured image', 'idemailwiz'),
        'remove_featured_image' => __('Remove featured image', 'idemailwiz'),
        'use_featured_image' => __('Use as featured image', 'idemailwiz'),
        'insert_into_item' => __('Insert into comparison', 'idemailwiz'),
        'uploaded_to_this_item' => __('Uploaded to this comparison', 'idemailwiz'),
        'items_list' => __('Comparisons list', 'idemailwiz'),
        'items_list_navigation' => __('Comparisons list navigation', 'idemailwiz'),
        'filter_items_list' => __('Filter comparisons list', 'idemailwiz'),
    );
    $comparisonArgs = array(
        'label' => __('Comparison', 'idemailwiz'),
        'description' => __('Comparison Description', 'idemailwiz'),
        'labels' => $comparisonLabels,
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields', 'revisions', 'page-attributes'),
        'taxonomies' => array('category', 'post_tag'),
        // Optional
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 5,
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => true,
        'can_export' => true,
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'rewrite' => ['slug' => 'comparison'],
        'has_archive' => 'comparisons',
        'capability_type' => 'post',
        'show_in_rest' => true,
        // Enable Gutenberg editor
    );

    register_post_type('idwiz_comparison', $comparisonArgs);

    register_post_type('wysiwyg_snippet', [
        'labels' => ['name' => __('Snippets'), 'singular_name' => __('Snippet')],
        'public' => true,
        'has_archive' => 'snippets',
        'rewrite' => ['slug' => 'snippet'],
        'supports' => ['title', 'editor', 'custom-fields'],
        'delete_with_user' => false,
        'capability_type' => 'post',
        'show_in_rest' => true,
        'show_in_menu' => 'edit.php?post_type=idemailwiz_template',
    ]);

    register_post_type('wysiwyg_interactive', [
        'labels' => ['name' => __('Interactives'), 'singular_name' => __('Interactive')],
        'public' => true,
        'has_archive' => 'interactives',
        'rewrite' => ['slug' => 'interactive'],
        'supports' => ['title', 'editor', 'custom-fields'],
        'delete_with_user' => false,
        'capability_type' => 'post',
        'show_in_rest' => true,
        'show_in_menu' => 'edit.php?post_type=idemailwiz_template',
        'has_archive' => 'interactives',
    ]);
}

add_post_type_support('idwiz_initiative', 'thumbnail');



//Register folder taxonomy
add_action('init', 'idemailwiz_create_taxonomies', 10);
function idemailwiz_create_taxonomies()
{
    $folderLabels = array(
        'name' => 'Folders',
        'singular_name' => 'Folder',
        'public' => true,
        'show_admin_column' => true,
        // Add other labels as needed
    );

    $folderargs = array(
        'labels' => $folderLabels,
        'public' => true,
        'hierarchical' => true,
        'show_in_rest' => true,
        // This is required if you want to use this taxonomy with Gutenberg

        'default_term' => array(
            'name' => 'All Templates',
            'slug' => 'all',
        ),
        'has_archive' => true,
        'rewrite' => array(
            'slug' => 'templates',
            'hierarchical' => true,
            'with_front' => false,
        ),
        'query_var' => true,
    );

    register_taxonomy('idemailwiz_folder', 'idemailwiz_template', $folderargs);
}