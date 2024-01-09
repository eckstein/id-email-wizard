<?php

function manual_import_single_triggered_campaign()
{
    $campaignId = $_POST['campaignId'];
    check_ajax_referer('id-general', 'security');

    idemailwiz_import_triggered_metrics_from_api([$campaignId], 'send');
    idemailwiz_import_triggered_metrics_from_api([$campaignId], 'open');
    idemailwiz_import_triggered_metrics_from_api([$campaignId], 'click');

    wp_send_json_success('Triggered sync functions called....');
}
add_action('wp_ajax_manual_import_single_triggered_campaign', 'manual_import_single_triggered_campaign');


if (isset($syncFromCsvCampaigns)) {
    foreach ($syncFromCsvCampaigns as $campaign) {
        idemailwiz_import_triggered_metrics_from_api([$campaign['id']], 'send');
        idemailwiz_import_triggered_metrics_from_api([$campaign['id']], 'open');
        idemailwiz_import_triggered_metrics_from_api([$campaign['id']], 'click');
        sleep(15);

    }
}
function idemailwiz_import_triggered_metrics_from_api($campaignIds, $metricType)
{
    foreach ($campaignIds as $campaignId) {
        $apiEndpoint = 'https://api.iterable.com/api/export/data.csv?dataTypeName=email' . ucfirst($metricType) . '&range=All&delimiter=%2C&onlyFields=campaignId&onlyFields=createdAt&onlyFields=messageId&onlyFields=templateId&campaignId=' . $campaignId;
        // Use cURL call function to fetch the CSV data from the API
        $apiResponse = idemailwiz_iterable_curl_call($apiEndpoint);
        $apiCsv = $apiResponse['response'];

        if ($apiCsv && !in_array($apiResponse, [400, 401, 429])) {
            // Save the CSV data to a temporary file
            $tempCsvFilePath = tempnam(sys_get_temp_dir(), 'iterable_csv_');
            file_put_contents($tempCsvFilePath, $apiCsv);

            // Import the CSV data into the database 
            idemailwiz_import_triggered_metrics_from_csv($tempCsvFilePath, $metricType);

            // Delete the temporary CSV file
            unlink($tempCsvFilePath);
        }
    }
}




function idemailwiz_import_triggered_metrics_from_csv($localCsvFilePath, $metricType)
{
    global $wpdb;

    // Open the local CSV file
    $tempFile = fopen($localCsvFilePath, 'r');

    if (!$tempFile) {
        error_log("Failed to open local CSV file.");
        return false;
    }

    $header = fgetcsv($tempFile);

    // Mapping of field to index
    $indexMapping = array_flip($header);

    // Create an array to store the records
    $uniqueRecords = [];

    while ($row = fgetcsv($tempFile)) {
        if (
            is_array($row) &&
            isset($indexMapping['campaignId'], $indexMapping['createdAt'], $indexMapping['messageId'], $indexMapping['templateId']) &&
            isset($row[$indexMapping['campaignId']], $row[$indexMapping['createdAt']], $row[$indexMapping['messageId']], $row[$indexMapping['templateId']])
        ) {
            $messageId = $row[$indexMapping['messageId']];
            $createdAt = strtotime($row[$indexMapping['createdAt']]) * 1000;

            // Check if the messageId already exists in $uniqueRecords
            if (isset($uniqueRecords[$messageId])) {
                // If the current row has an earlier createdAt date, update the record
                if ($createdAt < $uniqueRecords[$messageId]['startAt']) {
                    $uniqueRecords[$messageId] = [
                        'messageId' => $messageId,
                        'campaignId' => $row[$indexMapping['campaignId']],
                        'templateId' => $row[$indexMapping['templateId']],
                        'startAt' => $createdAt,
                    ];
                }
            } else {
                // If messageId doesn't exist in $uniqueRecords, add it as a new record
                $uniqueRecords[$messageId] = [
                    'messageId' => $messageId,
                    'campaignId' => $row[$indexMapping['campaignId']],
                    'templateId' => $row[$indexMapping['templateId']],
                    'startAt' => $createdAt,
                ];
            }
        }
    }

    fclose($tempFile);

    // Insert unique records into the database
    foreach ($uniqueRecords as $record) {
        $tableName = $wpdb->prefix . 'idemailwiz_triggered_' . lcfirst($metricType) . 's';
        idemailwiz_insert_exported_job_record($record, $tableName);
    }

    error_log("Finished inserting new $metricType records for triggered campaigns from CSV.");
    return true;
}

function updateDatabaseFromCSV($csvUrl, $tableName) {
    global $wpdb;
    $fullTableName = "{$wpdb->prefix}$tableName";

    // Fetch existing columns and their data types from the table
    $columnsData = $wpdb->get_results("DESCRIBE $fullTableName", OBJECT_K);
    $tableColumns = array_keys((array)$columnsData);

    // Open CSV file
    if (($handle = fopen($csvUrl, "r")) !== FALSE) {
        $header = fgetcsv($handle); // Assuming first row is header

        while (($data = fgetcsv($handle)) !== FALSE) {
            // Ensure $header and $data have the same number of elements
            if (count($header) !== count($data)) {
                continue; // Skip this row if they don't match
            }

            $dataAssoc = array_combine($header, $data);
            if ($dataAssoc === false) {
                continue; // Skip if array_combine fails
            }

            $dataFiltered = array_intersect_key($dataAssoc, array_flip($tableColumns));

            // Check for empty dataFiltered to skip rows with no matching columns
            if (empty($dataFiltered)) {
                continue;
            }

            // Determine format based on column data type
            $format = array_map(function ($column) use ($columnsData) {
                $type = $columnsData[$column]->Type;
                if (strpos($type, 'int') !== false) {
                    return '%d';
                } elseif (strpos($type, 'float') !== false || strpos($type, 'double') !== false || strpos($type, 'decimal') !== false) {
                    return '%f';
                } else {
                    return '%s';
                }
            }, array_keys($dataFiltered));

            // Insert or update data
            if ($wpdb->replace($fullTableName, $dataFiltered, $format) === FALSE) {
                echo "Error: " . $wpdb->last_error;
            }
        }

        fclose($handle);
    }
}




