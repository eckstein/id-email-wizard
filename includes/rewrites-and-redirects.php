<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

//Custom redirects
function redirect_template_archive_url()
{

    //If someone lands on /templates, redirect them to /templates/all
    if (isset($_SERVER['REQUEST_URI']) && trim($_SERVER['REQUEST_URI'], '/') == 'templates') {
        wp_redirect(site_url('/templates/all'), 301);
        exit;
    }

    //Redirect any weird/wrong template URLs to the proper/current URL (handles cases where the slug changed but an old link was used)
    // if (is_singular('idemailwiz_template')) {
    //     $post_slug = $post->post_name;
    //     if ( isset( $_SERVER['REQUEST_URI'] ) && $_SERVER['REQUEST_URI'] != "/template/{$post->ID}/{$post_slug}/" ) {
    // 		wp_redirect( home_url( "/template/{$post->ID}/{$post_slug}/" ) . $_SERVER['QUERY_STRING'], 301 );
    // 		exit;
    // 	}

    // }
}
add_action('template_redirect', 'redirect_template_archive_url', 11);



// Custom rewrite rules and endpoints
function idemailwiz_custom_rewrite_rule()
{
    // Template editor rewrite
    add_rewrite_rule('^template/([0-9]+)/([^/]+)/?', 'index.php?post_type=idemailwiz_template&p=$matches[1]', 'top');

    // Add custom endpoints
    add_rewrite_endpoint('metrics/campaign', EP_ROOT);
    add_rewrite_endpoint('metrics/journey', EP_ROOT);
    //add_rewrite_endpoint('build-template', EP_ROOT);
    add_rewrite_endpoint('build-template-v2', EP_ROOT);
    add_rewrite_endpoint('user-profile', EP_ROOT);
    add_rewrite_endpoint('settings', EP_ROOT);
    add_rewrite_endpoint('sync-station', EP_ROOT);
    add_rewrite_endpoint('campaign-monitor', EP_ROOT);
    add_rewrite_endpoint('course-mapping', EP_ROOT);

    add_rewrite_endpoint('endpoints/iterable-triggeredSend', EP_ROOT);

    // Custom ajax endpoint
    add_rewrite_rule('^idwiz-ajax/?$', 'index.php?idwiz_ajax_endpoint=1', 'top');
    add_rewrite_tag('%idwiz_ajax_endpoint%', '([0-9]+)');
}

add_action('init', 'idemailwiz_custom_rewrite_rule', 10);


// Custom permalinks for wiz templates
add_filter('post_type_link', 'custom_template_permalink', 10, 2);
function custom_template_permalink($post_link, $post)
{
    if ($post->post_type === 'idemailwiz_template') {
        return home_url("/template/{$post->ID}/" . $post->post_name . '/');
    }
    return $post_link;
}