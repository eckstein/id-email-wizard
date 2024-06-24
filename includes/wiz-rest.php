<?php
add_action('rest_api_init', function () {
    register_rest_route('idemailwiz/v1', '/iterable-link', array(
        'methods' => 'GET',
        'callback' => 'wiz_handle_iterable_data_feed',
        //  'permission_callback' => function () {
        //      return current_user_can( 'edit_others_posts' );
        //  }
    ));
});



function wiz_handle_iterable_data_feed($data) {
    // $wizSettings = get_option('idemailwiz_settings');
    // $api_auth_token = $wizSettings['external_cron_api'];

    // $token = $data->get_header('Authorization');
    // if ($token !== $api_auth_token) {
    //     return new WP_REST_Response('Invalid or missing token', 403);
    // }

    $params = $data->get_params();
    $email = $params['email'];
    //$args = $params['args'];

    $responseData = wiz_encrypt_email($email);

    if ($responseData['wizId']) {
        $message = 'Success! UserId: '.$responseData['wizId'];
    } else {
        $message = $email.': User not found!';
    }
        return new WP_REST_Response(['message' => $message, 'data' => $responseData], 200);
}

function map_division_to_abbreviation($division)
{
    $mapping = array(
        "iD Tech Camps" => "ipc",
        "iD Tech Academies" => "idta",
        "iD Teen Academies" => "ota",
        "iD Teen Academies - 2 weeks" => "ota",
        "Online Private Lessons" => "opl",
        "Virtual Tech Camps" => "vtc"
    );

    return isset($mapping[$division]) ? $mapping[$division] : null;
}

