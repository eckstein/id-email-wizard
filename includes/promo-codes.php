<?php
function get_all_promo_codes()
{
    $args = array(
        'post_type' => 'wiz_promo_code',
        'posts_per_page' => -1,
    );

    return get_posts($args);
}

// Get Promo Codes data
function get_promo_code_data()
{
    if (!check_ajax_referer('promo-codes', 'security', false)) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    $args = array(
        'post_type' => 'wiz_promo_code',
        'posts_per_page' => -1,
    );

    $promo_codes = get_posts($args);
    $data = array();

    foreach ($promo_codes as $promo) {
        $campaignsInPromo = get_campaigns_in_promo($promo->ID);
        $campaignIdsInPromo = array_column($campaignsInPromo, 'id');

        $promoCode = get_post_meta($promo->ID, 'code', true);
        $promoStartDate = get_post_meta($promo->ID, 'start_date', true) ?? date('Y-m-d');
        $promoEndDate = get_post_meta($promo->ID, 'end_date', true);

        $idtcDiscount = get_post_meta($promo->ID, 'idtc_discount', true);

        $campaignPurchases = !empty($campaignIdsInPromo) ? get_idwiz_purchases(['shoppingCartItems_discountCode' => $promoCode, 'startAt_start' => $promoStartDate, 'campaignIds' => $campaignIdsInPromo]) : false;
        $purchases = get_idwiz_purchases(['shoppingCartItems_discountCode' => $promoCode, 'startAt_start' => $promoStartDate]);

        $totalRevenue = 0;
        $lastUsed = 0;

        if ($purchases) {
            foreach ($purchases as $purchase) {
                $totalRevenue += $purchase['total'];
                $purchaseDate = $purchase['purchaseDate'];
                // Find latest purchase date
                if (!$lastUsed || strtotime($purchaseDate) > strtotime($lastUsed)) {
                    $lastUsed = $purchaseDate;
                }
            }
        }

        $campaignRevenue = 0;
        if ($campaignPurchases) {
            foreach ($campaignPurchases as $purchase) {
                $campaignRevenue += $purchase['total'];
            }
        }

        $data[] = array(
            'id' => $promo->ID,
            'code' =>  $promoCode,
            'name' => esc_html($promo->post_title),
            'permalink' => get_permalink($promo->ID), 
            'idtc_discount' => $idtcDiscount,
            'start_date' => $promoStartDate ? $promoStartDate : '',
            'end_date' => $promoEndDate ? $promoEndDate : '',
            'last_used' => $lastUsed ? $lastUsed : '',
            'cohort' => get_post_meta($promo->ID, 'cohort', true) ?: '',
            'campaigns' => count($campaignsInPromo),
            'campaign_purchases' => $campaignPurchases ? count($campaignPurchases) : 0,
            'all_purchases' => $purchases ? count($purchases) : 0,
            'campaign_revenue' => $campaignRevenue,
            'all_revenue' => $totalRevenue,
        );
    }

    wp_send_json_success(array('data' => $data));
}
add_action('wp_ajax_get_promo_code_data', 'get_promo_code_data');


function get_single_promo_code_data($promo_id) {
    if (!$promo_id) {
        return array('message' => 'Invalid promo code ID');
    }

    $promo = get_post($promo_id);
    if (!$promo || $promo->post_type !== 'wiz_promo_code') {
        return array('message' => 'Promo code not found');
        
    }

    $campaignsInPromo = get_campaigns_in_promo($promo->ID);
    $campaignIdsInPromo = array_column($campaignsInPromo, 'id');

    $promoCode = get_post_meta($promo->ID, 'code', true);
    $promoStartDate = get_post_meta($promo->ID, 'start_date', true);
    $promoEndDate = get_post_meta($promo->ID, 'end_date', true);

    $campaignPurchases = !empty($campaignIdsInPromo) ? get_idwiz_purchases(['shoppingCartItems_discountCode' => $promoCode, 'startAt_start' => $promoStartDate, 'campaignIds' => $campaignIdsInPromo]) : false;
    $purchases = get_idwiz_purchases(['shoppingCartItems_discountCode' => $promoCode, 'startAt_start' => $promoStartDate]);

    $totalRevenue = 0;
    $lastUsed = 0;

    if ($purchases) {
        foreach ($purchases as $purchase) {
            $totalRevenue += $purchase['total'];
            $purchaseDate = $purchase['purchaseDate'];
            // Find latest purchase date
            if (!$lastUsed || strtotime($purchaseDate) > strtotime($lastUsed)) {
                $lastUsed = $purchaseDate;
            }
        }
    }

    $campaignRevenue = 0;
    if ($campaignPurchases) {
        foreach ($campaignPurchases as $purchase) {
            $campaignRevenue += $purchase['total'];
        }
    }
    
    $data = array(
        'id' => $promo->ID,
        'code' => get_post_meta($promo->ID, 'code', true),
        'name' => esc_html($promo->post_title),
        'idtc_discount' => get_post_meta($promo->ID, 'idtc_discount', true),
        'start_date' => get_post_meta($promo->ID, 'start_date', true),
        'last_used' => $lastUsed ? $lastUsed : '',
        'end_date' => get_post_meta($promo->ID, 'end_date', true) > 0 ? get_post_meta($promo->ID, 'end_date', true) : '',
        'cohort' => get_post_meta($promo->ID, 'cohort', true) ?: '',
        'campaigns' => count($campaignsInPromo),
        'campaign_purchases' => $campaignPurchases ? count($campaignPurchases) : 0,
        'all_purchases' => $purchases ? count($purchases) : 0,
        'campaign_revenue' => $campaignRevenue,
        'all_revenue' => $totalRevenue,
    );

    return $data;
}

function get_single_promo_code_data_ajax()
{
    if (!check_ajax_referer('promo-codes', 'security', false)) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    $promo_id = isset($_POST['promo_id']) ? intval($_POST['promo_id']) : 0;
    
    $data = get_single_promo_code_data($promo_id);

    wp_send_json_success($data);
}
add_action('wp_ajax_get_single_promo_code_data_ajax', 'get_single_promo_code_data_ajax');

// Create Promo Code
function idemailwiz_create_new_promo_code()
{
    if (!check_ajax_referer('promo-codes', 'security', false)) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        return;
    }

    $promo_code = sanitize_text_field($_POST['newPromoCode']);

    $post_id = wp_insert_post(array(
        'post_title' => $promo_code,
        'post_type' => 'wiz_promo_code',
        'post_status' => 'publish'
    ));


    if ($post_id > 0) {
        update_post_meta($post_id, 'code', $promo_code);
        update_post_meta($post_id, 'start_date', date('Y-m-d'));
        update_post_meta($post_id, 'idtc_discount', 0);
        wp_send_json_success(array('message' => 'Promo code created successfully', 'post_id' => $post_id));
    } else {
        wp_send_json_error(array('message' => 'Failed to create the promo code'));
    }
    
}
add_action('wp_ajax_idemailwiz_create_new_promo_code', 'idemailwiz_create_new_promo_code');

// Delete Promo Code
function delete_promo_code()
{
    if (!check_ajax_referer('promo-codes', 'security', false)) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    if (!current_user_can('delete_posts')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        return;
    }

    $promo_id = intval($_POST['promoId']);

    if (wp_trash_post($promo_id)) {
        wp_send_json_success();
    } else {
        wp_send_json_error(array('message' => 'Failed to delete promo code.'));
    }
}
add_action('wp_ajax_delete_promo_code', 'delete_promo_code');

// Update Promo Code
function save_promo_code_update()
{
    if (!check_ajax_referer('promo-codes', 'security', false)) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        return;
    }

    $promo_id = intval($_POST['promoId']);
    $updates = $_POST['updates'];

    $success = true;

    if (!isset($updates['name']) || $updates['name'] != '') {
        if (isset($updates['code'])) {
            $updates['name'] == $updates['code'];
        } else {
            wp_send_json_error(array('message' => 'You must enter a code for this promo.'));
            return;
        }
    }
    // Update post title if name is provided
    if (isset($updates['name'])) {
        $post_update = array(
            'ID' => $promo_id,
            'post_title' => sanitize_text_field($updates['name'])
        );
        if (!wp_update_post($post_update)) {
            $success = false;
        }
        unset($updates['name']); // Remove name from updates array
    }

    // Update other meta fields
    foreach ($updates as $field => $content) {
        $field = sanitize_text_field($field);
        $content = sanitize_text_field($content);

        // Make sure start_date and end_date are in Y-m-d format
        if ($field == 'start_date' || $field == 'end_date') {
            if ($content && $content > 0) {
                $content = date('Y-m-d', strtotime($content));
            } else {
                $content = '';
            }
        }

        // Strip formatting from amount
        if ($field == 'idtc_discount') {
            intval($content);
        }

        // Validate
        $required = ['start_date', 'idtc_discount', 'code'];
        if (in_array($field, $required)) {
            if ($content == '') {
                wp_send_json_error(array('message' => 'You must enter a '.$field.' for this promo.'));
                return;
            }
        }

        if (!update_post_meta($promo_id, $field, $content)) {
            if (get_post_meta($promo_id, $field, true) != $content) {
                $success = false;
            }
            
        }
    }

    if ($success) {
        wp_send_json_success();
    } else {
        wp_send_json_error(array('message' => 'Failed to update one or more fields.'));
    }
}
add_action('wp_ajax_idemailwiz_save_promo_code_update', 'save_promo_code_update');


add_action('wp_ajax_remove_promo_code_from_campaign_ajax', 'remove_promo_code_from_campaign_ajax');
add_action('wp_ajax_add_promo_code_to_campaign_ajax', 'add_promo_code_to_campaign_ajax');
add_action('wp_ajax_get_all_promo_codes', 'get_all_promo_codes_ajax');

function remove_promo_code_from_campaign($campaignId, $promo_id) {
    global $wpdb;
    $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_idemailwiz_campaigns WHERE id = %d", $campaignId), ARRAY_A);
    $promo_codes = $campaign['promoCodes'] ? unserialize($campaign['promoCodes']) : [];
    if (($key = array_search($promo_id, $promo_codes)) !== false) {
        unset($promo_codes[$key]);
    }
    $new_promo_codes = serialize(array_values($promo_codes));

    return $wpdb->update(
        $wpdb->prefix . 'idemailwiz_campaigns',
        array('promoCodes' => $new_promo_codes),
        array('id' => $campaignId),
        array('%s'),
        array('%d')
    );
}
function remove_promo_code_from_campaign_ajax()
{
    
    $campaign_id = intval($_POST['campaign_id']);
    $promo_id = intval($_POST['promo_id']);

    $removePromo = remove_promo_code_from_campaign($campaign_id, $promo_id);

    if ($removePromo) {
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}
function add_promo_code_to_campaign($campaignId, $promo_id) {
    global $wpdb;
    $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_idemailwiz_campaigns WHERE id = %d", $campaignId), ARRAY_A);
    $promo_codes = $campaign['promoCodes'] ? unserialize($campaign['promoCodes']) : [];
    if (!in_array($promo_id, $promo_codes)) {
        $promo_codes[] = $promo_id;
    }
    $new_promo_codes = serialize($promo_codes);

    return $wpdb->update(
        'wp_idemailwiz_campaigns',
        array('promoCodes' => $new_promo_codes),
        array('id' => $campaignId),
        array('%s'),
        array('%d')
    );
}
function add_promo_code_to_campaign_ajax()
{
   
    $campaign_id = intval($_POST['campaign_id']);
    $promo_id = intval($_POST['promo_id']);

    $addPromo = add_promo_code_to_campaign($campaign_id, $promo_id);
    if ($addPromo) {
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}

function idemailwiz_get_promo_codes_for_select()
{
    // Check for nonce and security
    if (
        !check_ajax_referer('promo-codes', 'security', false)
        && !check_ajax_referer('id-general', 'security', false)
        && !check_ajax_referer('data-tables', 'security', false)
    ) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    $searchTerm = isset($_POST['q']) ? $_POST['q'] : '';

    // Fetch promo_codes
    $allPromoCodes = get_posts(array(
        'post_type' => 'wiz_promo_code', 
        'posts_per_page' => -1,
        's' => $searchTerm
    ));

    // Prepare data
    $data = array_map(function ($promo_code) {
        return array(
            'id' => $promo_code->ID,
            'text' => $promo_code->post_title
        );
    }, $allPromoCodes);

    // Return JSON-encoded data
    echo json_encode(array_values($data));
    wp_die();
}
add_action('wp_ajax_idemailwiz_get_promo_codes_for_select', 'idemailwiz_get_promo_codes_for_select');


function get_all_promo_codes_ajax()
{
    $promo_codes = get_all_promo_codes();
    wp_send_json_success($promo_codes);
}

function get_promo_codes_for_campaign($campaignId)
{
    $campaign = get_idwiz_campaign($campaignId);
    return $campaign['promoCodes'] ? unserialize($campaign['promoCodes']) : [];
}

function generate_promo_code_flags($campaignId)
{
    $promoCodeIds = get_promo_codes_for_campaign($campaignId);
    ob_start(); // Start output buffering
?>
    <div class="campaign-meta-flags">
        <?php if ($promoCodeIds) { ?>
            <?php foreach ($promoCodeIds as $promoCodeId) : ?>
                <span class="campaign-meta-flag">
                    <a href="<?php echo get_the_permalink($promoCodeId); ?>" title="Go to Initiative">
                        <?php echo get_the_title($promoCodeId); ?>
                    </a>
                    <span class="remove-promo-from-campaign remove-meta-icon fa fa-times" data-promoid="<?php echo esc_attr($promoCodeId); ?>" data-campaignid="<?php echo esc_attr($campaignId); ?>" title="Remove campaign from Initiative">
                    </span>
                </span>
            <?php endforeach; ?>
        <?php } else {
            echo '<span class="no-meta-message">No connected promo codes</span>';
        } ?>
        <span class="add-promo-to-campaign add-meta-icon fa fa-plus" data-action="add" data-initids='<?php echo json_encode($promoCodeIds); ?>' data-campaignid="<?php echo esc_attr($campaignId); ?>" title="Add promo code to campaign">
        </span>
    </div>
<?php
    return ob_get_clean(); // End output buffering and return the captured HTML
}

function get_campaigns_in_promo($promoCodeId)
{
    $allCampaigns = get_idwiz_campaigns();
    $associated_campaigns = [];
    foreach ($allCampaigns as $campaign) {
        $campaignPromoCodes = $campaign['promoCodes'] ? unserialize($campaign['promoCodes']) : [];
        if (!empty($campaignPromoCodes)) {
            if (in_array($promoCodeId, $campaignPromoCodes)) {
                $associated_campaigns[] = $campaign;
            }
        }
    }
    return $associated_campaigns;
}

add_action('wp_ajax_idemailwiz_add_remove_campaign_from_promo_code', 'idemailwiz_add_remove_campaign_from_promo_code');
function idemailwiz_add_remove_campaign_from_promo_code()
{
    $response = [
        'success' => true,
        'message' => '',
        'data' => []
    ];

    $isValidNonce = check_ajax_referer('data-tables', 'security', false)
        || check_ajax_referer('promo-codes', 'security', false)
        || check_ajax_referer('metrics', 'security', false);
    if (!$isValidNonce) {
        wp_send_json_error(['message' => 'Invalid nonce.']);
        return;
    }

    $campaignIDs = $_POST['campaign_ids'];
    if (!is_array($campaignIDs)) {
        $campaignIDs = [$campaignIDs];
    }
    $promo_codeID = intval($_POST['promo_code_id']);
    $action = $_POST['campaignAction'];

    if ($action != 'add' && $action != 'remove') {
        wp_send_json_error(['message' => 'Invalid action.']);
        return;
    }

    $messages = [];
    $successCount = 0;

    foreach ($campaignIDs as $campaignID) {
        if ($action == 'add') {
            $result = add_promo_code_to_campaign($campaignID, $promo_codeID);
        } else {
            $result = remove_promo_code_from_campaign($campaignID, $promo_codeID);
        }
        
        if ($result['success']) {
            $successCount++;
        }
        $messages[] = $result['message'];
    }

    if ($successCount == count($campaignIDs)) {
        $response['message'] = "All campaigns successfully processed for action: {$action}.";
    } else {
        $response['success'] = false;
        $response['message'] = "Some campaigns could not be processed for action: {$action}.";
    }

    $response['data'] = [
        'successCount' => $successCount,
        'totalCount' => count($campaignIDs),
        'messages' => $messages,
        'action' => $action
    ];

    wp_send_json($response);
}
