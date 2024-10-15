<?php
function get_image_aspect_ratio($imageSrc, $defaultRatio = 3)
{
    $cached_data = get_cached_image_data($imageSrc);

    if ($cached_data && isset($cached_data['aspect_ratio'])) {
        return [
            'status' => 'success',
            'data' => $cached_data['aspect_ratio']
        ];
    }

    return [
        'status' => 'error',
        'data' => $defaultRatio
    ];
}




// Gets image data either from the cache or from the remote server
function get_cached_image_data($url, $cache_time = 1 * WEEK_IN_SECONDS, $max_size = 5242880)
{
    $cache_key = 'wiz_image_cache_' . md5($url);
    $cached_data = get_transient($cache_key);

    if (false === $cached_data) {
        $response = wp_remote_get($url,
            array('timeout' => 15)
        );

        if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
            $image_data = wp_remote_retrieve_body($response);
            $size = strlen($image_data);

            if ($size <= $max_size) {
                $headers = wp_remote_retrieve_headers($response);
                $image_size = getimagesize($url);
                if (!$image_size) {
                    return false;
                }
                $aspect_ratio = $image_size[0] / $image_size[1];

                // Base64 encode the image data
                $base64_image = base64_encode($image_data);
                $data_uri = 'data:' . $headers['content-type'] . ';base64,' . $base64_image;

                $cached_data = array(
                    'data_uri' => $data_uri,
                    'type' => $headers['content-type'],
                    'size' => $size,
                    'time' => time(),
                    'aspect_ratio' => $aspect_ratio
                );
                set_transient($cache_key, $cached_data, $cache_time);
            } else {
                $max_size_mb = $max_size / (1024 * 1024);
                error_log("Image caching error: Image exceeds maximum size of {$max_size_mb}mb: $url");
                return false;
            }
        } else {
            return false; // Failed to fetch image
        }
    }

    return $cached_data;
}

function cleanup_image_cache($max_size = 100 * 1024 * 1024, $max_age = 604800)
{
    global $wpdb;

    // Get all our image cache transients
    $transients = $wpdb->get_results(
        "SELECT option_name, option_value 
        FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_wiz_image_cache_%'
        ORDER BY option_id DESC"
    );

    $total_size = 0;
    $deleted = 0;

    foreach ($transients as $transient) {
        $$image_data = maybe_unserialize($transient->option_value);
        if (!is_array($image_data) || !isset($image_data['size'])) continue;

        $total_size += $image_data['size'];
        $age = time() - (int)$image_data['time'];

        // Delete if too old or if we're over the size limit
        if ($age > $max_age || $total_size > $max_size) {
            $transient_name = str_replace('_transient_', '', $transient->option_name);
            delete_transient($transient_name);
            $deleted++;
            $total_size -= $image_data['size'];
        }
    }

    return $deleted;
}

add_action('wp_scheduled_delete', 'cleanup_wiz_image_cache');
if (!wp_next_scheduled('cleanup_wiz_image_cache')) {
    wp_schedule_event(time(), 'daily', 'cleanup_wiz_image_cache');
}

function get_wizbuilder_image_src($image_url, $isEditor)
{
    if ($isEditor !== false && $isEditor !== 'false') {
        $cached_data = get_cached_image_data($image_url);
        if ($cached_data && isset($cached_data['data_uri'])) {
            return $cached_data['data_uri'];
        }
    }
    return $image_url;
}
