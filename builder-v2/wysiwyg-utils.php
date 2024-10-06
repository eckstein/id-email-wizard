<?php
function convertStringBooleans(&$data)
{
    if (is_array($data) || is_object($data)) {
        foreach ($data as &$value) {
            if (is_array($value) || is_object($value)) {
                convertStringBooleans($value);
            } elseif ($value === 'true') {
                $value = true;
            } elseif ($value === 'false') {
                $value = false;
            }
        }
        unset($value); // Unset the reference to avoid potential issues
    } elseif ($data === 'true') {
        $data = true;
    } elseif ($data === 'false') {
        $data = false;
    }
}

function check_link_ajax()
{
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    $url = $_POST['url'] ?? '';

    if (empty($url)) {
        wp_send_json_error(['message' => 'No URL provided']);
        return;
    }

    $utmParam = 'test_' . substr(md5(rand()), 0, 7);
    $urlWithUtm = add_query_arg('utm_campaign', $utmParam, $url);

    $startTime = microtime(true);
    $response = wp_remote_get($urlWithUtm, ['redirection' => 5]);
    $loadTime = microtime(true) - $startTime;

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
        return;
    }

    $httpCode = wp_remote_retrieve_response_code($response);
    $finalUrl = wp_remote_retrieve_header($response, 'url') ?: $urlWithUtm;
    $redirectionCount = $response['redirection_count'] ?? 0;

    $isRedirected = ($redirectionCount > 0) || ($finalUrl !== $urlWithUtm);
    $utmAccepted = strpos($finalUrl, $utmParam) !== false;

    // If the response was not successful, try it without any $_GET requests
    $retryHttpCode = false;
    if ($httpCode >= 400) {
        $urlWithoutUtm = preg_replace('/utm_[^&]+/', '', $url);
        $retryResponse = wp_remote_get($urlWithoutUtm, ['redirection' => 5]);

        if (is_wp_error($retryResponse)) {
            wp_send_json_error(['message' => $retryResponse->get_error_message()]);
            return;
        }

        $retryHttpCode = wp_remote_retrieve_response_code($retryResponse);
        $retryFinalUrl = wp_remote_retrieve_header($retryResponse, 'url') ?: $urlWithoutUtm;
        $retryRedirectionCount = $retryResponse['redirection_count'] ?? 0;

        $isRedirected = ($retryRedirectionCount > 0) || ($retryFinalUrl !== $urlWithoutUtm);
        $utmAccepted = false;
    }

    $successObject = [
        'original_url' => $url,
        'final_url' => $finalUrl,
        'http_code' => $httpCode,
        'load_time' => round($loadTime, 2),
        'redirected' => $isRedirected,
        'redirection_count' => $redirectionCount,
        'utm_accepted' => $utmAccepted
    ];

    if ($retryHttpCode) {
        $successObject['retry_http_code'] = $retryHttpCode;
    }

    wp_send_json_success($successObject);
}

add_action('wp_ajax_check_link_ajax', 'check_link_ajax');

function get_preview_text_hack()
{
    $return = '';
    for ($i = 0; $i <= 40; $i++) {
        $return .= '&#847; &zwnj; &nbsp; &#8199; ';
    }
    return $return;
}

add_action('wp_ajax_upload_mockup', 'handle_mockup_upload');
function handle_mockup_upload()
{
    // Define ABSPATH if not already defined
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__FILE__) . '/../../../');
    }

    // Include necessary WordPress files
    require_once(ABSPATH . 'wp-load.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    if (!isset($_FILES['file'])) {
        wp_send_json_error('No file uploaded');
        wp_die();
    }

    $file = $_FILES['file'];
    $upload = wp_handle_upload($file, array('test_form' => false));

    if (isset($upload['error'])) {
        wp_send_json_error($upload['error']);
        wp_die();
    }

    $attachment_id = wp_insert_attachment(array(
        'post_title' => sanitize_file_name($file['name']),
        'post_content' => '',
        'post_type' => 'attachment',
        'post_mime_type' => $file['type'],
        'guid' => $upload['url'],
    ), $upload['file']);

    if (is_wp_error($attachment_id)) {
        wp_send_json_error($attachment_id->get_error_message());
        wp_die();
    }

    wp_send_json_success(array('url' => $upload['url']));
    wp_die();
}



function hex2rgba($color, $opacity = 1)
{
    // Check if already rgba and return it
    if (strpos($color, 'rgba') === 0) {
        return $color;
    }

    // Remove any leading '#' if present
    $color = ltrim($color, '#');

    // Ensure the color is a valid hex color
    if (!ctype_xdigit($color) || (strlen($color) != 6 && strlen($color) != 3)) {
        return "rgba(0, 0, 0, $opacity)"; // Return black if invalid color
    }

    // If it's a 3 digit hex, convert to 6 digit
    if (strlen($color) == 3) {
        $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
    }

    // Convert hex to RGB
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));

    // Return the rgba string
    return "rgba($r, $g, $b, $opacity)";
}


function add_aria_label_to_links($html)
{
    return preg_replace_callback(
        '/<a\s+(.*?)>(.*?)<\/a>/is',
        function ($matches) {
            $attributes = $matches[1];
            $content = $matches[2];

            // Extract link text or use alt attribute if content is empty
            if (trim(strip_tags($content)) === '') {
                // If content is empty, try to get alt attribute
                if (preg_match('/\balt=["\'](.+?)["\']/i', $attributes, $alt_match)) {
                    $linkText = $alt_match[1];
                } else {
                    $linkText = '';
                }
            } else {
                $linkText = strip_tags($content);
            }

            $linkText = preg_replace('/\s+/', ' ', $linkText); // Replace multiple whitespaces with a single space
            $linkText = trim($linkText); // Trim whitespace from the beginning and end

            // Check if aria-label or title already exists
            $hasAriaLabel = preg_match('/\baria-label=["\'].*?["\']/i', $attributes);
            $hasTitle = preg_match('/\btitle=["\'].*?["\']/i', $attributes);

            // Add aria-label and title if they don't exist and linkText is not empty
            if (!empty($linkText)) {
                if (!$hasAriaLabel) {
                    $attributes .= ' aria-label="' . esc_attr($linkText) . '"';
                }
                if (!$hasTitle) {
                    $attributes .= ' title="' . esc_attr($linkText) . '"';
                }
            }

            // Open all links in new tab
            if (!preg_match('/\btarget=["\'].*?["\']/i', $attributes)) {
                $attributes .= ' target="_blank"';
            }
            if (!preg_match('/\brel=["\'].*?["\']/i', $attributes)) {
                $attributes .= ' rel="noopener noreferrer"';
            }

            // Create the new <a> tag
            return '<a ' . $attributes . '>' . $content . '</a>';
        },
        $html
    );
}
