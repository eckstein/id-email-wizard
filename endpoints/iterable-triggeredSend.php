<?php
handle_iterable_webhook();
function update_wiz_sent_at($campaignId, $sentAt) {
    // Access the global wpdb object
    global $wpdb;
    // Prepare the datetime value by stripping off the timezone data
    // assuming the format is "Y-m-d H:i:s +00:00"
    $datetime = explode(' ', $sentAt)[0] . ' ' . explode(' ', $sentAt)[1];
    // Use the wpdb->update method to safely update the row
    $table_name = $wpdb->prefix . 'idemailwiz_campaigns';
    $data = array('wizSentAt' => $datetime);
    $where = array('id' => $campaignId);
    
    // Update the row in the database, checking for success
    $updated = $wpdb->update($table_name, $data, $where);

    if (false === $updated) {
        // There was an error updating the record
        error_log('Error updating the wizSentAt field in the database.');
        return false;
    } else {
        // Success, $updated contains the number of rows updated
        return true;
    }
}

// This is the entry point for the webhook
function handle_iterable_webhook() {
    // Only proceed if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json_str = file_get_contents('php://input');
        $json_obj = json_decode($json_str, true);
        //error_log('Received webhook: '. $json_str);
        // Check if the necessary fields are present
        if (isset($json_obj['dataFields']['campaignId'], $json_obj['dataFields']['createdAt'])) {
            $campaignId = $json_obj['dataFields']['campaignId'];
            $createdAt = $json_obj['dataFields']['createdAt'];

            // Update the database record
            $result = update_wiz_sent_at($campaignId, $createdAt);

            // Respond to the webhook
            if ($result) {
                http_response_code(200);
                //echo json_encode(array('message' => 'wizSentAt updated successfully'));
            } else {
                // Respond with an error message
                //http_response_code(500);
                //echo json_encode(array('message' => 'Failed to update wizSentAt'));
            }
        } else {
            // Missing data
            //http_response_code(400);
            //echo json_encode(array('message' => 'Missing campaignId or createdAt data'));
        }
    } else {
        // If not a POST request, send an error message
        //http_response_code(405);
        //echo json_encode(array('message' => 'Method Not Allowed'));
    }
}
?>
