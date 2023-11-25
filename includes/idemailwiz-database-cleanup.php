<?php

function do_database_cleanups() {
    update_null_user_ids();
    update_missing_purchase_dates();
    remove_zero_campaign_ids();
}
function update_null_user_ids() {
    wiz_log('Updating null User IDs...');
    global $wpdb;
    $table_name = $wpdb->prefix.'idemailwiz_purchases'; // Use your actual table name

    $response = [];

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Update userId for rows with NULL userId using a self-join
        $result = $wpdb->query("
            UPDATE {$table_name} p1
            INNER JOIN (
                SELECT MIN(id) as id, accountNumber, userId
                FROM {$table_name}
                WHERE userId IS NOT NULL
                GROUP BY accountNumber
            ) p2 ON p1.accountNumber = p2.accountNumber
            SET p1.userId = p2.userId
            WHERE p1.userId IS NULL
        ");

        // Check for the result to determine if rows were affected
        if (!$result) {
            // Log the error
            wiz_log("No matching userIds found to update.");
            $response = ['success' => false, 'error'=> 'No matching userIds found to update.'];
            
        }

        // Commit transaction
        $updatedUserIds = $wpdb->query('COMMIT');
        $response = ['success' => true, 'updated'=> $updatedUserIds];
        wiz_log("$updatedUserIds null userIds updated.");

    } catch (Exception $e) {
        // Rollback the transaction on error
        $wpdb->query('ROLLBACK');
        // Log the error
        wiz_log("Failed to update null userIds: " . $e->getMessage());
        $response = ['success' => false, 'error'=> "Failed to update null userIds due to database error: " . $e->getMessage()];
        return $response;
    }

    return $response;
}


//update_missing_purchase_dates();
function update_missing_purchase_dates() {
    wiz_log('Updating missing purchase dates...');
    global $wpdb;
    $table_name = $wpdb->prefix.'idemailwiz_purchases'; 

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Update purchaseDate based on createdAt for all records with a missing purchaseDate
        $result = $wpdb->query("
            UPDATE {$table_name}
            SET purchaseDate = DATE(createdAt)
            WHERE purchaseDate IS NULL OR purchaseDate = ''
        ");

        // Check for the result to determine if rows were affected
        if ($result === false) {
            // Log the error
            wiz_log("No purchases were found to update.");
        } else {
            // Log how many rows were affected
            wiz_log("Purchase dates updated for {$result} records with missing purchaseDate.");
        }

        // Commit transaction
        $wpdb->query('COMMIT');
    } catch (Exception $e) {
        // Rollback the transaction on error
        $wpdb->query('ROLLBACK');
        // Log the error
        wiz_log("Failed to update purchase dates: " . $e->getMessage());
        return false;
    }

    return true;
}
//remove_zero_campaign_ids();
function remove_zero_campaign_ids() {
    wiz_log('Cleaning campaign IDs...');
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    // Attempt to update records with campaignId = 0
    $result = $wpdb->query($wpdb->prepare("
        UPDATE {$table_name}
        SET campaignId = NULL
        WHERE TRIM(campaignId) = %d
    ", 0));

    if ($result === false) {
        // Log the error
        wiz_log("Failed to update campaignIds: an error occurred.");
        return false;
    } else {
        // Log how many rows were affected
        wiz_log("CampaignIds set to NULL for {$result} records with campaignId = 0.");
        return true;
    }
}




//remove_apostrophes_from_column('idemailwiz_purchases', 'accountNumber');
function remove_apostrophes_from_column($table_suffix, $column_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . $table_suffix; // Construct table name with WP prefix

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Update the specified column by removing any single apostrophes
        $result = $wpdb->query($wpdb->prepare("
            UPDATE `{$table_name}`
            SET `{$column_name}` = REPLACE(`{$column_name}`, '''', '')
            WHERE `{$column_name}` LIKE '%%\'%%'
        "));

        // Check for the result to determine if rows were affected
        if ($result === false) {
            // Log the error
            error_log("Failed to remove apostrophes from {$column_name} in {$table_name}: " . $wpdb->last_error);
            $wpdb->query('ROLLBACK'); // Rollback the transaction on error
            return false;
        } else {
            // Log how many rows were affected
            error_log("Apostrophes removed from {$column_name} in {$result} rows of {$table_name}.");
            $wpdb->query('COMMIT'); // Commit the transaction
            return true;
        }
    } catch (Exception $e) {
        // Rollback the transaction on error
        $wpdb->query('ROLLBACK');
        // Log the error
        error_log("Exception occurred: " . $e->getMessage());
        return false;
    }
}