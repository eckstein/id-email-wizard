<?php

function do_database_cleanups()
{
    update_null_user_ids();
    update_missing_purchase_dates();
    remove_zero_campaign_ids();
    idemailwiz_backfill_campaign_start_dates();
    //idwiz_cleanup_users_database();
}
function update_null_user_ids()
{
    wiz_log('Updating null User IDs...');
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases'; 

    $response = [];

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Update userId for rows with blank (or NULL) userId using a self-join
        $result = $wpdb->query("
            UPDATE {$table_name} p1
            INNER JOIN (
                SELECT MIN(id) as id, accountNumber, userId
                FROM {$table_name}
                WHERE TRIM(IFNULL(userId, '')) <> ''
                GROUP BY accountNumber
            ) p2 ON p1.accountNumber = p2.accountNumber
            SET p1.userId = p2.userId
            WHERE TRIM(IFNULL(p1.userId, '')) = ''
        ");

        // Check for the result to determine if rows were affected
        if ($result === false) {
            // Log the error
            wiz_log('No matching userIds found to update or error occurred.');
            $response = ['success' => false, 'error' => 'No matching userIds found to update or error occurred.'];
            $wpdb->query('ROLLBACK'); // Rollback if no rows were updated or error occurred
            return $response;
        }

        // Commit transaction if rows were updated
        $updatedUserIds = $wpdb->query('COMMIT');
        $response = ['success' => true, 'updated' => $result]; // $result will contain the number of rows affected
        wiz_log("$result null userIds updated.");

    } catch (Exception $e) {
        // Rollback the transaction on error
        $wpdb->query('ROLLBACK');
        // Log the error
        wiz_log('Failed to update null userIds: ' . $e->getMessage());
        $response = ['success' => false, 'error' => 'Failed to update null userIds due to database error: ' . $e->getMessage()];
        return $response;
    }

    return $response;
}



//update_missing_purchase_dates();
function update_missing_purchase_dates() {
    wiz_log('Updating missing purchase dates...');
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    // Begin transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Update purchaseDate based on createdAt for records where purchaseDate is NULL
        $result = $wpdb->query("
            UPDATE {$table_name}
            SET purchaseDate = DATE(createdAt)
            WHERE purchaseDate IS NULL
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
function remove_zero_campaign_ids()
{
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
function remove_apostrophes_from_column($table_suffix, $column_name)
{
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

function idemailwiz_backfill_campaign_start_dates()
{
    global $wpdb;
    $purchases_table = $wpdb->prefix . 'idemailwiz_purchases';
    $campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';

    // Fetch all purchases that have a campaignId and an empty or null campaignStartAt
    $purchases = $wpdb->get_results("SELECT id, campaignId FROM $purchases_table WHERE campaignId IS NOT NULL AND (campaignStartAt IS NULL OR campaignStartAt = '')");
    $countUpdates = 0;
    foreach ($purchases as $purchase) {
        // Fetch the campaign's startAt date
        $campaignStartAt = $wpdb->get_var($wpdb->prepare("SELECT startAt FROM $campaigns_table WHERE id = %d", $purchase->campaignId));

        if ($campaignStartAt) {
            // Update the purchase record with the campaignStartAt date
            $updateRecord = $wpdb->update(
                $purchases_table,
                ['campaignStartAt' => $campaignStartAt],
                ['id' => $purchase->id]
            );
            if ($updateRecord && $updateRecord > 0) {
                $countUpdates++;
            }
        }
    }

    wiz_log("Purchase campaign start date backfill completed for {$countUpdates} records.");
    return "Purchase campaign start date backfill completed for {$countUpdates} records.";
}

function idwiz_cleanup_users_database($batchSize = 10000)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_users';

    wiz_log("Fetching users and cleaning up records...");

    // Fetch all users from the database
    $totalUsers = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    $batches = ceil($totalUsers / $batchSize);

    for ($batch = 0; $batch < $batches; $batch++) {
        $offset = $batch * $batchSize;

        // Fetch a batch of users from the database
        $users = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} LIMIT %d OFFSET %d", $batchSize, $offset), ARRAY_A);



        $countUpdates = 0;
        foreach ($users as $user) {
            $update = false; // Flag to check if we need to update the record

            foreach ($user as $field => $value) {
                // Change blank values to NULL
                if ($value === '') {
                    $user[$field] = NULL;
                    $update = true;
                }

                // Change string "[]" to NULL
                elseif ($value === '[]') {
                    $user[$field] = NULL;
                    $update = true;
                }

                // Check if the value is a string representation of an array
                elseif (is_string($value) && strpos($value, '[') === 0) {
                    // Attempt to decode it as JSON
                    $decoded = json_decode($value, true);

                    // If decoding is successful and the result is an array, serialize it
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $user[$field] = serialize($decoded);
                        $update = true;
                    }
                }
            }

            // Update the user record if changes were made
            if ($update) {
                $wpdb->update(
                    $table_name,
                    $user,
                    ['wizId' => $user['wizId']]
                );
                $countUpdates++;
            }
        }
        wiz_log("Cleaned up {$countUpdates} user records.");
    }
}

function updateTimestampsToMilliseconds()
{
    global $wpdb;

    $tableNames = [
        'idemailwiz_triggered_sends',
        'idemailwiz_triggered_opens',
        'idemailwiz_triggered_clicks',
        'idemailwiz_triggered_bounces',
        'idemailwiz_triggered_unsubscribes',
        'idemailwiz_triggered_complaints',
        'idemailwiz_triggered_sendskips'
    ];

    // SQL query to update startAt column
    // This query assumes that startAt is a BIGINT and multiplies values less than a certain threshold
    $threshold = 10000000000; // Adjust this threshold according to your needs
    foreach ($tableNames as $tableName) {
        $table = $wpdb->prefix . $tableName;
        wiz_log("Updating timestamps in table {$table}...");
        $sql = "UPDATE `$table` SET `startAt` = `startAt` * 1000 WHERE `startAt` < %d";

        try {
            // Execute the query
            $wpdb->query($wpdb->prepare($sql, $threshold));

            // Check for errors
            if (!empty($wpdb->last_error)) {
                wiz_log("Error updating timestamps in table {$table}: ". $wpdb->last_error);
                throw new Exception('Database error: ' . $wpdb->last_error);
            }

            wiz_log ("Timestamps updated successfully in table {$table}.");
            //return "Timestamps updated successfully in table {$table}.";
        } catch (Exception $e) {
            wiz_log("Error updating timestamps in table {$table}: ". $e->getMessage());
            //return "Error: " . $e->getMessage();
        }
        sleep(2);
    }
    wiz_log("Timestamps updated successfully.");
}