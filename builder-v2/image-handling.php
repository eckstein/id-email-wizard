<?php
function get_image_aspect_ratio($imageSrc, $defaultRatio = 3)
{
    $cache_key = 'wiz_image_ratio_' . md5($imageSrc);
    $cached_ratio = get_transient($cache_key);

    if (false !== $cached_ratio) {
        return [
            'status' => 'success',
            'data' => $cached_ratio
        ];
    }

    $aspect_ratio = fetch_image_aspect_ratio($imageSrc);
    if ($aspect_ratio) {
        set_transient($cache_key, $aspect_ratio, 1 * WEEK_IN_SECONDS);
        return [
            'status' => 'success',
            'data' => $aspect_ratio
        ];
    }

    return [
        'status' => 'error',
        'data' => $defaultRatio
    ];
}

function fetch_image_aspect_ratio($url)
{
    $response = wp_remote_get($url, array('timeout' => 15));

    if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
        return false;
    }

    $image_size = getimagesize($url);
    if (!$image_size) {
        return false;
    }

    return $image_size[0] / $image_size[1];
}

// Cleanup function
function cleanup_aspect_ratio_cache()
{
    global $wpdb;

    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_wiz_image_ratio_%' 
        AND option_value < " . (time() - WEEK_IN_SECONDS)
    );
}

// Schedule cleanup
function schedule_cleanup_aspect_ratio_cache()
{
    if (!wp_next_scheduled('cleanup_aspect_ratio_cache')) {
        wp_schedule_event(time(), 'weekly', 'cleanup_aspect_ratio_cache');
    }
}
add_action('wp', 'schedule_cleanup_aspect_ratio_cache');

add_action('cleanup_aspect_ratio_cache', 'cleanup_aspect_ratio_cache');
