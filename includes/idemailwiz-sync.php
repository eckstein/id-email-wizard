<?php
// Include WordPress' database functions
global $wpdb;




function idemailwiz_update_insert_api_data($items, $operation, $table_name)
{
    global $wpdb;
    $result = ['success' => [], 'errors' => []];


    $id_field = 'id'; // Default ID field
    $name_field = 'name'; // Default name field

    // Determine the ID and name fields based on the table name
    if ($table_name == $wpdb->prefix . 'idemailwiz_templates' || $table_name == $wpdb->prefix . 'idemailwiz_experiments') {
        $id_field = 'templateId';
    }

    // If this is a purchase sync, we do some database cleanup
    if ($table_name == $wpdb->prefix . 'idemailwiz_purchases') {
        $items = idwiz_cleanup_purchase_records($items);
    }

    foreach ($items as $key => $item) {
        // Add 'name' to the metrics array
        if ($table_name == $wpdb->prefix . 'idemailwiz_metrics') {
            $metricCampaign = get_idwiz_campaign($item['id']);
            $metricName = $metricCampaign['name'];
        }



        // If this is a template record, we check if there's a wiz builder template with this template ID set as the sync-to
        if ($table_name == $wpdb->prefix . 'idemailwiz_templates') {
            $incomingTemplateId = $item['templateId'];
            $wizDbTemplate = get_idwiz_template($incomingTemplateId);
            $args = array(
                'post_type' => 'idemailwiz_template',
                'meta_key' => 'itTemplateId',
                'meta_value' => $incomingTemplateId,
                'posts_per_page' => 1
            );

            $builderTemplates = get_posts($args);

            if ($wizDbTemplate && !empty($builderTemplates)) {

                $sql = $wpdb->prepare(
                    "UPDATE `{$wpdb->prefix}idemailwiz_templates` SET clientTemplateId = %s WHERE templateId = %d",
                    $builderTemplates[0]->ID,
                    $wizDbTemplate['templateId']

                );
                $wpdb->query($sql);
            } else {
                $sql = $wpdb->prepare(
                    "UPDATE `{$wpdb->prefix}idemailwiz_templates` SET clientTemplateId = NULL WHERE templateId = %d",
                    $incomingTemplateId
                );
                $wpdb->query($sql);
            }

        }



        //If this is an experiment record, we check if the corrosponding campaign in the campaign table has the experiment ID present. If not, add it.
        if ($table_name == $wpdb->prefix . 'idemailwiz_experiments') {
            $experimentId = $item['experimentId']; // Retrieve the current experiment ID

            $experimentCampaign = get_idwiz_campaign($item['campaignId']);

            // Get the existing experimentIds, if any
            $existingExperimentIds = $experimentCampaign['experimentIds'];
            $experimentIdsArray = $existingExperimentIds ? unserialize($existingExperimentIds) : array();

            // Add the new experimentId if it doesn't already exist in the array
            if (!in_array($experimentId, $experimentIdsArray)) {
                $experimentIdsArray[] = $experimentId;
            }

            // Serialize the updated array
            $serializedExperimentIds = serialize($experimentIdsArray);

            // Using prepare to safely insert values into the query
            $sql = $wpdb->prepare(
                "UPDATE `{$wpdb->prefix}idemailwiz_campaigns` SET experimentIds = %s WHERE id = %d",

                $serializedExperimentIds,
                $item['campaignId']
            );
            $wpdb->query($sql);
        }

        // Serialize values that are arrays
        foreach ($item as $childKey => $childValue) {
            if (is_array($childValue)) {
                if (empty($childValue)) {
                    $item[$childKey] = null; // Set to NULL if the array is empty
                } else {
                    $serialized = serialize($childValue);
                    $item[$childKey] = $serialized;
                }
            }
        }

        // Convert key/header to camel case for db compatibility
        $key = str_replace('.', '_', $key); // Replace periods with underscores
        $words = explode(' ', $key); // Split the string into words
        $words = array_map('ucwords', $words); // Capitalize the first letter of each word
        $camelCaseString = implode('', $words); // Join the words back together
        $key = lcfirst($camelCaseString); // Make the first letter lowercase and return

        $fields = implode(",", array_map(function ($field) {
            return "`" . esc_sql($field) . "`";
        }, array_keys($item)));
        $placeholders = implode(",", array_fill(0, count($item), "%s"));
        $prepared_values = array_values($item);

        if ($operation === "insert") {
            $sql = "INSERT INTO `{$table_name}` ({$fields}) VALUES ({$placeholders})";
            $prepared_sql = $wpdb->prepare($sql, $prepared_values);
        } else {
            $updates = implode(", ", array_map(function ($field) {
                return "`$field` = %s";
            }, array_keys($item)));
            $sql = "UPDATE `{$table_name}` SET {$updates} WHERE `{$id_field}` = %s";
            $prepared_sql = $wpdb->prepare($sql, array_merge($prepared_values, [$item[$id_field]]));
        }

        // Do the insert/update
        $query_result = $wpdb->query($prepared_sql);




        // Extracting relevant details for logging
        $item_name = isset($item[$name_field]) ? $item[$name_field] : ''; // Item name
        if ($table_name == $wpdb->prefix . 'idemailwiz_metrics') {
            $item_name = $metricName;
        }
        $item_id = $item[$id_field]; // Item ID

        if ($query_result !== false) {
            if ($query_result > 0) {
                // Success details
                $result['success'][] = "Successfully performed {$operation} on '{$item_name}' (ID: {$item_id}).";
            } else {
                // Success, but no rows were affected
                //$result['success'][] = "Skipped '{$item_name}' (ID: {$item_id}); no updates needed.";
            }
        } else {
            // Error details
            $result['errors'][] = "Failed to perform {$operation} on '{$item_name}' (ID: {$item_id}). Database Error: {$wpdb->last_error}.";
        }

    }

    return $result;
}


function idwiz_cleanup_purchase_records($items)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    // Extract accountNumbers for items with a NULL userId and a non-empty accountNumber
    $accountNumbers = array_column(array_filter($items, function ($item) {
        return empty($item['userId']) && !empty($item['accountNumber']);
    }), 'accountNumber');

    // Unique accountNumbers to minimize the database calls
    $uniqueAccountNumbers = array_unique($accountNumbers);

    // Retrieve userIds for these accountNumbers in one call if there are any account numbers to process
    $existingUserIds = [];
    if (!empty($uniqueAccountNumbers)) {
        $placeholders = implode(',', array_fill(0, count($uniqueAccountNumbers), '%s'));
        $query = $wpdb->prepare("
            SELECT accountNumber, userId 
            FROM {$table_name} 
            WHERE accountNumber IN ($placeholders)
            AND userId IS NOT NULL
        ", $uniqueAccountNumbers);
        $existingUserIds = $wpdb->get_results($query, OBJECT_K);
    } else {
        // Skip, no userId cleanup needed
        //error_log('No unique accountNumbers with NULL userId found.');
    }

    // Now iterate over the items and update them as necessary
    foreach ($items as $key => $item) {
        // Exclude campaignIds with negatives (like -12345)
        if (isset($item['campaignId']) && $item['campaignId'] < 0) {
            unset($items[$key]);
            continue;
        }

        // set campaign Ids that equal 0 to null instead
        if (isset($item['campaignId']) && $item['campaignId'] === '0') {
            $item['campaignId'] = null;
        }

        // Update purchaseDate if needed
        if (empty($item['purchaseDate']) && !empty($item['createdAt'])) {
            $created_at_date = new DateTime($item['createdAt']);
            $item['purchaseDate'] = $created_at_date->format('Y-m-d');
        }

        // Update userId if needed
        if (empty($item['userId']) && !empty($item['accountNumber']) && isset($existingUserIds[$item['accountNumber']])) {
            $item['userId'] = $existingUserIds[$item['accountNumber']]->userId;
        }


        $items[$key] = $item;
    }

    return $items;
}



function idemailwiz_fetch_campaigns($campaignIds = null)
{
    // $campaignIds is a dummy variable to prevent errors
    // Iterable doesn't allow API calls per campaign as of September 2023
    $url = 'https://api.iterable.com/api/campaigns';
    try {
        $response = idemailwiz_iterable_curl_call($url);
    } catch (Exception $e) {
        if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
            // Stop execution if more than 5 consecutive 400 errors
            wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
            return;
        }
    }

    // Check if campaigns exist in the API response
    if (!isset($response['response']['campaigns'])) {
        return "Error: No campaigns found in the API response.";
    }

    // Return the campaigns array
    return $response['response']['campaigns'];
}

function idemailwiz_fetch_templates($campaignIds = null)
{
    $allTemplates = [];

    // Initialize URLs for fetching templates of different types and mediums
    $templateAPIurls = [
        'blastEmails' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=Email',
        'triggeredEmails' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=Email',
        'blastSMS' => 'https://api.iterable.com/api/templates?templateType=Blast&messageMedium=SMS',
        'triggeredSMS' => 'https://api.iterable.com/api/templates?templateType=Triggered&messageMedium=SMS',
    ];

    // Fetch templates from all four endpoints
    foreach ($templateAPIurls as $typeKey => $url) {
        try {
            $response = idemailwiz_iterable_curl_call($url);
            if (!empty($response['response']['templates'])) {
                $templates = $response['response']['templates'];

                // Add templates to the allTemplates array
                foreach ($templates as $template) {
                    $allTemplates[] = $template;
                }
            }

            usleep(10000);
        } catch (Exception $e) {
            wiz_log("Error during initial API call: " . $e->getMessage());
        }
    }

    // Fetch the detailed templates for all fetched templates
    $urlsToFetch = [];
    foreach ($allTemplates as $template) {
        $templateId = $template['templateId'];

        // Try email endpoint first, and if it fails, fall back to SMS
        $emailEndpoint = "https://api.iterable.com/api/templates/email/get?templateId=$templateId";
        $smsEndpoint = "https://api.iterable.com/api/templates/sms/get?templateId=$templateId";

        $urlsToFetch[] = $emailEndpoint;
        $urlsToFetch[] = $smsEndpoint;
    }

    try {
        $multiResponses = idemailwiz_iterable_curl_multi_call($urlsToFetch);
        $fetchedTemplates = [];

        foreach ($multiResponses as $response) {
            if ($response['httpCode'] == 200) {
                $fetchedTemplate = idemailwiz_simplify_templates_array($response['response']);
                $fetchedTemplates[] = $fetchedTemplate;
            }
        }

        // Replace the original templates with the fetched ones
        $allTemplates = $fetchedTemplates;

        usleep(10000);
    } catch (Exception $e) {
        wiz_log("Error during multi cURL request: " . $e->getMessage());
    }

    return $allTemplates;
}





// For the returned Template object from Iterable.
// Flattens a multi-dimensional array into a single-level array by concatenating keys into a prefix string.
// Skips values we don't want in the database. Limits 'linkParams' keys to 2 (typically utm_term and utm_content)
function idemailwiz_simplify_templates_array($template)
{

    if (isset($template['metadata'])) {
        // Extract the desired keys from the 'metadata' array
        if (isset($template['metadata']['campaignId'])) {
            $result['campaignId'] = $template['metadata']['campaignId'];
        }
        if (isset($template['metadata']['createdAt'])) {
            $result['createdAt'] = $template['metadata']['createdAt'];
        }
        if (isset($template['metadata']['updatedAt'])) {
            $result['updatedAt'] = $template['metadata']['updatedAt'];
        }
        //if (isset($template['metadata']['clientTemplateId'])) {
        // $result['clientTemplateId'] = $template['metadata']['clientTemplateId'];
        // }
    }

    if (isset($template['linkParams'])) {
        // Extract the desired keys from the 'linkParams' array
        if (isset($template['linkParams'])) {
            foreach ($template['linkParams'] as $linkParam) {
                if ($linkParam['key'] == 'utm_term') {
                    $result['utmTerm'] = $linkParam['value'];
                }
                if ($linkParam['key'] == 'utm_content') {
                    $result['utmContent'] = $linkParam['value'];
                }
            }
        }
    }


    // Add the rest of the keys to the result
    foreach ($template as $key => $value) {
        if (!isset($template['message'])) { //if 'message' is set, it's an SMS, so this is for email
            $result['messageMedium'] = 'Email';
            // Skip the excluded keys and the keys we've already added
            $excludeKeys = array('clientTemplateId', 'plainText', 'cacheDataFeed', 'mergeDataFeedContext', 'utm_term', 'utm_content', 'createdAt', 'updatedAt', 'ccEmails', 'bccEmails', 'dataFeedIds');
        } else {
            $result['messageMedium'] = 'SMS';
            $excludeKeys = array('messageTypeId', 'trackingDomain', 'googleAnalyticsCampaignName');
        }
        if ($key !== 'metadata' && $key !== 'linkParams' && !in_array($key, $excludeKeys)) {
            $result[$key] = $value;
        }
    }




    return $result;
}




function idemailwiz_fetch_experiments($campaignIds = null)
{
    $today = new DateTime();
    $startFetchDate = $today->modify('-4 weeks')->format('Y-m-d');

    $fetchCampArgs = array(
        'messageMedium' => 'Email',
        'type' => 'Blast',
        'startAt_start' => $startFetchDate
    );

    if ($campaignIds) {
        $fetchCampArgs['campaignIds'] = $campaignIds;
    }

    $allCampaigns = get_idwiz_campaigns($fetchCampArgs);

    $data = [];
    $allExpMetrics = [];
    foreach ($allCampaigns as $campaign) {
        $url = 'https://api.iterable.com/api/experiments/metrics?campaignId=' . $campaign['id'];
        try {
            $response = idemailwiz_iterable_curl_call($url);
        } catch (Exception $e) {
            if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
                // Stop execution if more than 5 consecutive 400 errors
                wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
                return;
            }
        }
        if ($response['response']) {

            // Split the CSV data into lines
            $lines = explode("\n", $response['response']);

            // Parse the header line into headers
            $headers = str_getcsv($lines[0]);

            // Replace spaces and slashes with underscores, remove parentheses, remove leading/trailing whitespace, and convert to CamelCase
            $headers = array_map(function ($header) {
                $header = str_replace([' ', '/'], '_', $header);
                $header = str_replace('(', '', $header);
                $header = str_replace(')', '', $header);
                $header = trim($header);
                $header = ucwords(strtolower($header), "_");
                $header = str_replace('_', '', $header);
                $header = lcfirst($header);
                return $header;
            }, $headers);

            // Iterate over the non-header lines
            for ($i = 1; $i < count($lines); $i++) {
                // Parse the line into values
                $values = str_getcsv($lines[$i]);

                // Check if the number of headers and values matches
                if (count($headers) != count($values)) {
                    continue;
                }

                // Combine headers and values into an associative array
                $lineArray = array_combine($headers, $values);

                // Calculate the additional percentage metrics
                $metrics = idemailwiz_calculate_metrics($lineArray);

                // Merge the metrics with the existing data
                $data[] = $metrics;
            }
            $allExpMetrics = $data + $data;
        }
    }

    // Return the array of metrics
    return $allExpMetrics;
}
function idemailwiz_fetch_metrics($campaignIds = null)
{

    $today = new DateTime();
    $startFetchDate = $today->modify('-4 weeks')->format('Y-m-d');

    $metricCampaignArgs = array(
        'fields' => array('id'),
        'type' => array('Blast', 'Triggered'),
    );

    if ($campaignIds) {
        $metricCampaignArgs['campaignIds'] = $campaignIds;
    } else {
        // If not campaigns are passed, limit the call to a timeframe
        $metricCampaignArgs['startAt_start'] = $startFetchDate;
    }

    $campaigns = get_idwiz_campaigns($metricCampaignArgs);

    $batchCount = 0;
    $batches = array();
    $currentBatch = array();
    foreach ($campaigns as $campaign) {
        $currentBatch[] = $campaign['id'];
        if (++$batchCount % 100 == 0) {
            $batches[] = $currentBatch;
            $currentBatch = array();
        }
    }
    // Add any remaining campaigns that didn't fill a full batch
    if (!empty($currentBatch)) {
        $batches[] = $currentBatch;
    }

    $allMetrics = []; // Initialize $data as an array

    foreach ($batches as $batch) {
        $getString = '?campaignId=' . implode('&campaignId=', $batch);

        $url = "https://api.iterable.com/api/campaigns/metrics" . $getString;
        try {
            $response = idemailwiz_iterable_curl_call($url);
        } catch (Exception $e) {
            if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
                // Stop execution if more than 5 consecutive 400 errors
                wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
                return;
            }
        }

        // Split the CSV data into lines
        $lines = explode("\n", $response['response']);

        // Parse the header line into headers
        $headers = str_getcsv($lines[0]);

        // Replace spaces and slashes with underscores, remove parentheses, remove leading/trailing whitespace, and convert to CamelCase
        $headers = array_map(function ($header) {
            $header = str_replace([' ', '/'], '_', $header);
            $header = str_replace('(', '', $header);
            $header = str_replace(')', '', $header);
            $header = trim($header);
            $header = ucwords(strtolower($header), "_");
            $header = str_replace('_', '', $header);
            $header = lcfirst($header);
            return $header;
        }, $headers);

        // Iterate over the non-header lines
        for ($i = 1; $i < count($lines); $i++) {
            // Parse the line into values
            $values = str_getcsv($lines[$i]);

            // Check if the number of headers and values matches
            if (count($headers) != count($values)) {
                continue;
            }

            // Combine headers and values into an associative array
            $lineArray = array_combine($headers, $values);

            // Calculate the additional percentage metrics
            $metrics = idemailwiz_calculate_metrics($lineArray);

            // Merge the metrics with the existing data
            $allMetrics[] = $metrics;
        }
        sleep(7); // Respect Iterable's rate limit of 10 requests per minute
    }

    // Return the data array
    return $allMetrics;
}




function idemailwiz_fetch_purchases($campaignIds = null)
{
    date_default_timezone_set('UTC'); // Set timezone to UTC to match Iterable


    // Define the base URL
    $baseUrl = 'https://api.iterable.com/api/export/data.csv';

    // Define the fields to be omitted
    $omitFields = [
        'shoppingCartItems.orderDetailId',
        'shoppingCartItems.parentOrderDetailId',
        'shoppingCartItems.predecessorOrderDetailId',
        'shoppingCartItems.financeUnitId',
        'currencyTypeId',
        'eventName',
        'shoppingCartItems.id',
        'shoppingCartItems.imageUrl',
        'shoppingCartItems.subsidiaryId',
        'shoppingCartItems.packageType',
        'shoppingCartItems.StudentFirstName',
        'shoppingCartItems.StudentLastName',
        'email',
    ];

    // Create the array of query parameters
    $queryParams = [
        'dataTypeName' => 'purchase',
        'delimiter' => ',',
        'omitFields' => implode(',', $omitFields)
    ];

    // Handle the campaign IDs if provided
    if ($campaignIds) {
        if (count($campaignIds) === 1) {
            // If there's only one campaign ID, add it directly
            $queryParams['campaignId'] = $campaignIds[0];
        } else {
            // If multiple campaign IDs, find the earliest date
            $campaigns = get_idwiz_campaigns(['campaignIds' => $campaignIds]);
            $earliestDate = min(array_column($campaigns, 'startAt'));
            $startDateTime = date('Y-m-d', $earliestDate / 1000);
        }
    }

    // Define the start and end date time for the API call
    $startDateTime = date('Y-m-d', strtotime('-3 days'));
    $endDateTime = date('Y-m-d', strtotime('+1 day')); // End date is always today

    // Add the start and end datetime to the query parameters
    $queryParams['startDateTime'] = $startDateTime;
    $queryParams['endDateTime'] = $endDateTime;

    // Build the query string
    $queryString = http_build_query($queryParams);

    // Combine the base URL with the query string
    $url = $baseUrl . '?' . $queryString;

    try {
        $response = idemailwiz_iterable_curl_call($url);
    } catch (Exception $e) {
        if ($e->getMessage() === "CONSECUTIVE_400_ERRORS") {
            // Stop execution if more than 5 consecutive 400 errors
            wiz_log("More than 5 consecutive HTTP 400 errors. Stopping execution.");
            return;
        }
    }


    // Prepare the omit fields to match the processed headers format
    $omitFields[] = 'shoppingCartItems'; //Add the main shoppingCartItems column to be omitted.
    $processedOmitFields = array_map(function ($field) {
        $fieldWithoutPeriods = str_replace('.', '_', $field);
        // Special handling for '_id' field to be 'id'
        return strtolower($fieldWithoutPeriods === '_id' ? 'id' : $fieldWithoutPeriods);
    }, $omitFields);

    $allPurchases = []; // Initialize $allPurchases as an array

    // Open a memory-based stream for reading and writing
    if (($handle = fopen("php://temp", "r+")) !== FALSE) {
        // Write the CSV content to the stream and rewind the pointer
        fwrite($handle, $response['response']);
        rewind($handle);

        // Parse the header line into headers
        $headers = fgetcsv($handle);

        // Prepare the headers
        $processedHeaders = array_map(function ($header) {
            $headerWithoutUnderscores = str_replace('_', '', $header);
            return strtolower(str_replace('.', '_', $headerWithoutUnderscores));
        }, $headers);

        // Iterate over each line of the file
        while (($values = fgetcsv($handle)) !== FALSE) {
            $purchaseData = []; // Initialize as empty array

            // Only process lines with the correct number of columns
            if (count($values) === count($processedHeaders)) {
                // Iterate over the values and headers simultaneously
                foreach ($values as $index => $value) {
                    $header = $processedHeaders[$index];
                    // Skip the fields that are in the omit list
                    if (in_array($header, $processedOmitFields)) {
                        continue;
                    }
                    // Clean the value and add to the purchase data
                    $cleanValue = str_replace(['[', ']', '"'], '', $value);
                    $purchaseData[$header] = $cleanValue;
                }
            }

            // If there's data to add, append it to all purchases
            if (!empty($purchaseData)) {
                $allPurchases[] = $purchaseData;
            }
        }

        // Close the file handle
        fclose($handle);
    }

    // Return the data array
    return $allPurchases;

}


function idemailwiz_sync_campaigns($passedCampaigns = null)
{

    // If no campaigns are passed, we fetch them from Iterable
    $campaigns = idemailwiz_fetch_campaigns($passedCampaigns);

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_campaigns';

    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];

    // For passed campaigns, we just update them all
    if ($passedCampaigns) {
        $records_to_update = $campaigns;
        // Otherwise, we do our logic for update vs insert from the Iterable API
    } else {
        foreach ($campaigns as $campaign) {
            if ($campaign['campaignState'] == 'Finished' || $campaign['campaignState'] == 'Running') {
                // Check for an existing campaign in the database
                $wizCampaign = get_idwiz_campaign($campaign['id']);

                if ($wizCampaign) {
                    $fieldsDifferent = false;


                    foreach ($campaign as $key => $value) {
                        // Perform deep comparison
                        if (!isset($wizCampaign[$key]) || $wizCampaign[$key] != $value) {
                            $fieldsDifferent = true;
                            break;
                        }

                    }

                    // Update the row if any field is different
                    if ($fieldsDifferent) {
                        // If this is a triggered campaign, get the latest startAt value from our DB
                        if ($campaign['type'] == 'Triggered') {
                            $latestStartAt = get_latest_triggered_startAt($campaign['id']);
                            if ($latestStartAt !== null) {
                                $campaign['startAt'] = $latestStartAt;


                            } else {
                                // Skip this campaign if not found in the wp_idemailwiz_triggered_sends database
                                continue;
                            }


                        }

                        $records_to_update[] = $campaign;
                    }
                } else {
                    // Campaign not in DB, we'll add it
                    $records_to_insert[] = $campaign;
                }
            }
        }

    }

    // Does our wiz_logging and returns the returns data about the insert/update
    return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);

}

global $wpdb; // Make sure to declare this as global if your function is inside a function scope

function get_latest_triggered_startAt($campaignId)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'idemailwiz_triggered_sends';

    // Prepare the SQL query to prevent SQL injection
    $sql = $wpdb->prepare(
        "SELECT startAt FROM $table_name WHERE campaignId = %s ORDER BY startAt DESC LIMIT 1",
        $campaignId
    );

    // Execute the SQL query
    $result = $wpdb->get_var($sql);

    if ($result !== null) {
        return (int) $result; // Convert to integer if your startAt is stored as string
    } else {
        return null; // Return null if no record is found
    }
}


function idemailwiz_sync_templates($passedCampaigns = null)
{
    // Fetch relevant templates
    // Note: The fetch function filters by updatedAt differences to limit results
    $templates = idemailwiz_fetch_templates($passedCampaigns);


    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_templates';

    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];

    foreach ($templates as $template) {
        // See if the template exists in our database yet
        $wizTemplate = get_idwiz_template($template['templateId']);

        if ($wizTemplate) {
            // Template exists, we'll update it
            $records_to_update[] = $template;
        } else {
            // Template not in the database, we'll add it
            $records_to_insert[] = $template;
        }
    }

    // Process and log the sync operation
    return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}





function idemailwiz_sync_purchases($campaignIds = null)
{
    $purchases = idemailwiz_fetch_purchases($campaignIds);

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_purchases';

    $records_to_insert = [];
    $records_to_update = [];

    foreach ($purchases as $purchase) {

        //$wizPurchase = get_idwiz_purchase($purchase['id']);
        $wizPurchase = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE id = %s", $purchase['id']));

        if (!$wizPurchase) {
            $records_to_insert[] = $purchase;
        } else {
            $records_to_update[] = $purchase;
        }
    }

    return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}



function idemailwiz_sync_experiments($passedCampaigns = null)
{

    $experiments = idemailwiz_fetch_experiments($passedCampaigns);

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_experiments';

    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];

    if ($passedCampaigns) {
        $records_to_update = $experiments;
    } else {
        foreach ($experiments as $experiment) {
            $wizExperiment = get_idwiz_experiment($experiment['templateId']);

            // Check for experiment in database
            if ($wizExperiment) {
                // Mark existing rows for update
                if (!in_array($experiment, $records_to_update)) {
                    $records_to_update[] = $experiment;
                }

            } else {
                // experiment not in db, we'll add it
                $records_to_insert[] = $experiment;
            }
        }
    }

    // Does our wiz_logging and returns the returns data about the insert/update
    return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}



function idemailwiz_sync_metrics($passedCampaigns = null)
{

    $metrics = idemailwiz_fetch_metrics($passedCampaigns); // Gets all metrics if none are passed

    //(print_r($metrics, true));

    global $wpdb;
    $table_name = $wpdb->prefix . 'idemailwiz_metrics';

    // Prepare arrays for comparison
    $records_to_update = [];
    $records_to_insert = [];

    if ($passedCampaigns) {
        $records_to_update = $metrics;
    } else {
        foreach ($metrics as $metric) {

            // Handle SMS campaign header mapping
            $wizCampaign = get_idwiz_campaign($metric['id']);
            if ($wizCampaign && $wizCampaign['messageMedium'] == 'SMS') {
                $metric['uniqueEmailSends'] = $metric['uniqueSmsSent'];
                $metric['uniqueEmailsDelivered'] = $metric['uniqueSmsDelivered'];
                $metric['uniqueEmailClicks'] = $metric['uniqueSmsClicks'];
                $records_to_update[] = $metric;
            }

            // Check for existing metric
            $wizMetric = get_idwiz_metric($metric['id']);
            if ($wizMetric) {
                // Gather metric for update and de-dupe
                if (!in_array($metric, $records_to_update)) {
                    $records_to_update[] = $metric;
                }

            } else {
                // metric not in db, we'll add it
                $records_to_insert[] = $metric;
            }
        }
    }
    //error_log('Metrics Records to update: '. count($records_to_update));
    //error_log('Metrics Records to insert: '. count($records_to_insert));

    // Does our wiz_logging and returns data about the insert/update
    return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}


function idemailwiz_process_and_log_sync($table_name, $records_to_insert = null, $records_to_update = null)
{

    // Extracting the type (e.g., 'campaign', 'template', etc.) from the table name
    $type = substr($table_name, strrpos($table_name, '_') + 1);

    $insert_results = '';
    $update_results = '';
    $logChunk = "";
    $return = array();

    $logChunk .= ucfirst($type) . " sync results: \n";

    if ($records_to_insert) {
        //$logChunk .= count($records_to_insert) . " $type" . " to insert.\n";
        $insert_results = idemailwiz_update_insert_api_data($records_to_insert, 'insert', $table_name);
    }
    if ($records_to_update) {
        //$logChunk .= count($records_to_update) . " $type" . " to update.\n";
        $update_results = idemailwiz_update_insert_api_data($records_to_update, 'update', $table_name);
    }

    $logInsertUpdate = return_insert_update_logging($insert_results, $update_results, $table_name);

    $logChunk .= $logInsertUpdate;

    wiz_log($logChunk);

    if ($records_to_insert) {
        $return['insert'] = $insert_results;
    }
    if ($records_to_update) {
        $return['update'] = $update_results;
    }

    return $return;
}



function return_insert_update_logging($insert_results, $update_results, $table_name)
{
    $logResults = function ($results, $type) {
        if (empty($results['success']) && empty($results['errors'])) {
            return ""; // No operations performed
        }
        if (!isset($results['success'], $results['errors'])) {
            return "Invalid {$type} results structure.";
        }
        $log = '';
        $cntSuccess = count($results['success']);
        $cntErrors = count($results['errors']);

        if ($cntSuccess) {
            $log .= "Successful {$type} of $cntSuccess records.\n";
        }
        foreach ($results['errors'] as $message) {
            $log .= "Error ({$type}): $message\n";
        }
        return rtrim($log); // Remove trailing newline
    };

    $logInsert = isset($insert_results) ? $logResults($insert_results, 'insert') : '';
    $logUpdate = isset($update_results) ? $logResults($update_results, 'update') : '';
    $logSync = $logInsert . $logUpdate;

    if (!$logSync) {
        $tableNameParts = explode('_', $table_name);
        $tableNameType = end($tableNameParts);
        return "The $tableNameType sync is up to date! No inserts or updates are needed.";
    }

    return trim($logInsert . "\n" . $logUpdate);
}




// Ajax handler for sync button
// Also creates and logs readable sync responses from response arrays
function idemailwiz_sync_non_triggered_metrics($campaignIds = null)
{
    // Set transient to indicate the sync is running
    set_transient('idemailwiz_sync_non_triggered_running', true, 10 * MINUTE_IN_SECONDS);

    $syncArgs = [];
    $response = [];

    if ($campaignIds) {
        $wizCampaignIds = get_idwiz_campaigns(array('campaignIds' => $campaignIds, 'fields' => 'id'));
    }

    $sync_dbs = ['campaigns', 'templates', 'metrics', 'purchases', 'experiments'];
    foreach ($sync_dbs as $db) {
        if ($campaignIds) {
            $syncArgs = array_column($wizCampaignIds, 'id');
        }
        $function_name = 'idemailwiz_sync_' . $db;
        if (!function_exists($function_name)) {
            return ['error' => 'Sync function does not exist for ' . $db];
        }
        $result = call_user_func($function_name, $syncArgs);

        if ($result === false) {
            return ['error' => 'Sync failed for ' . $db];
        }
        $response[$db] = $result;
    }

    // Delete transient to indicate the sync is done
    delete_transient('idemailwiz_sync_non_triggered_running');

    return $response;
}

function idemailwiz_ajax_sync()
{
    // Check for valid nonce
    if (
        !(
            check_ajax_referer('data-tables', 'security', false) ||
            check_ajax_referer('initiatives', 'security', false) ||
            check_ajax_referer('wiz-metrics', 'security', false) ||
            check_ajax_referer('id-general', 'security', false)
        )
    ) {
        wp_die('Invalid action or nonce');
    }

    $campaignIds = null;
    if (isset($_POST['campaignIds'])) {
        $campaignIds = json_decode(stripslashes($_POST['campaignIds']), true) ?? false;
        if (!is_array($campaignIds)) {
            $campaignIds = array($campaignIds);
        }
    }

    $response = idemailwiz_sync_non_triggered_metrics($campaignIds);

    if (isset($response['error'])) {
        wp_send_json_error($response['error']);
    } else {
        wp_send_json_success($response);
    }
}
add_action('wp_ajax_idemailwiz_ajax_sync', 'idemailwiz_ajax_sync');




// Calculate percentage metrics
// Takes a row of metrics data from the api call
function idemailwiz_calculate_metrics($metrics)
{

    $campaignIdKey = 'id';

    if (isset($metrics['confidence'])) { // Only experiments have the 'confidence' key (since Iterable gives us no other way to check)
        // If this is an experiment, we look in the campaignId column instead of the id column
        $campaignIdKey = 'campaignId';
    }

    // Get the campaign using the campaignId from the passed metrics
    $wiz_campaign = get_idwiz_campaign($metrics[$campaignIdKey]);

    // Campaign must already be in database for metrics to be added/updated
    if (!$wiz_campaign) {
        return false;
    }

    // Check the campaign medium
    $medium = $wiz_campaign['messageMedium'];

    // Required fields for Email
    $requiredFields = ['uniqueEmailSends', 'uniqueEmailsDelivered', 'uniqueEmailOpens', 'uniqueEmailClicks', 'uniqueUnsubscribes', 'totalComplaints', 'uniquePurchases', 'revenue'];

    // Update required fields if it's an SMS campaign
    if ($medium == 'SMS') {
        $requiredFields = ['uniqueSmsSent', 'uniqueSmsDelivered', 'uniqueSmsClicks', 'uniqueUnsubscribes', 'totalComplaints', 'uniquePurchases', 'revenue'];
    }

    // Ensure required fields are set
    foreach ($requiredFields as $field) {
        if (!isset($metrics[$field]) || $metrics[$field] === null) {
            $metrics[$field] = 0;
        }
    }

    // Calculate common metrics
    $sendField = $medium == 'SMS' ? 'uniqueSmsSent' : 'uniqueEmailSends';
    $deliveredField = $medium == 'SMS' ? 'uniqueSmsDelivered' : 'uniqueEmailsDelivered';
    $clicksField = $medium == 'SMS' ? 'uniqueSmsClicks' : 'uniqueEmailClicks';

    $sendValue = (float) $metrics[$sendField];
    $deliveredValue = (float) $metrics[$deliveredField];
    $clicksValue = (float) $metrics[$clicksField];
    $unsubscribesValue = (float) $metrics['uniqueUnsubscribes'];
    $complaintsValue = (float) $metrics['totalComplaints'];
    $purchasesValue = (float) $metrics['uniquePurchases'];
    $revenueValue = (float) $metrics['revenue'];

    if ($sendValue > 0) {
        $metrics['wizDeliveryRate'] = ($deliveredValue / $sendValue) * 100;
        $metrics['wizCtr'] = ($clicksValue / $sendValue) * 100;
        $metrics['wizUnsubRate'] = ($unsubscribesValue / $sendValue) * 100;
        $metrics['wizCompRate'] = ($complaintsValue / $sendValue) * 100;
        $metrics['wizCvr'] = ($purchasesValue / $sendValue) * 100;
    } else {
        $metrics['wizCtr'] = 0;
        $metrics['wizUnsubRate'] = 0;
        $metrics['wizCompRate'] = 0;
        $metrics['wizCvr'] = 0;
    }

    if ($purchasesValue > 0) {
        $metrics['wizAov'] = ($revenueValue / $purchasesValue);
    } else {
        $metrics['wizAov'] = 0;
    }

    // Open metrics (sms or no opens gets zero values)
    $opensValue = $medium == 'Email' ? (float) $metrics['uniqueEmailOpens'] : 0;

    if ($opensValue && $sendValue > 0) {
        $metrics['wizOpenRate'] = $medium == 'Email' ? ($opensValue / $sendValue) * 100 : 0;
    } else {
        $metrics['wizOpenRate'] = 0;
    }

    if ($opensValue && $opensValue > 0) {
        $metrics['wizCto'] = $medium == 'Email' ? ($clicksValue / $opensValue) * 100 : 0;
    } else {
        $metrics['wizCto'] = 0;
    }

    // Remove metrics we don't want to sync in
    unset($metrics['uniqueSmsSentByMessage']);

    return $metrics;
}

// Schedule the twice daily sync of GA data
if (!wp_next_scheduled('idemailwiz_twice_daily_sync')) {
    wp_schedule_event(time(), 'twicedaily', 'idemailwiz_twice_daily_sync');
}
add_action('idemailwiz_twice_daily_sync', 'sync_ga_campaign_revenue_data');


// Define the queue of sync tasks
$sync_queue = ['send', 'open', 'click', 'unsubscribe', 'bounce', 'sendSkip', 'complaint'];

$wizSettings = get_option('idemailwiz_settings');
$cronSyncActive = $wizSettings['sync_method'] ?? 'wp_cron';

// Check if wp_cron sync method is active
if ($cronSyncActive === 'wp_cron') {
    // Schedule the initial sync event if not already scheduled
    if (!wp_next_scheduled('idemailwiz_sync_sequence')) {
        //Schedule the initial sync event, 1 hour from when the wp_cron sync is turned on (to avoid immediate execution when turned on)
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'idemailwiz_sync_sequence');
    }
} else {
    // If wp_cron is not the sync method, clear any scheduled events
    $timestamp = wp_next_scheduled('idemailwiz_sync_sequence');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'idemailwiz_sync_sequence');
    }
}

// The action that manages the sync sequence
add_action('idemailwiz_sync_sequence', 'idemailwiz_process_sync_sequence');

function idemailwiz_process_sync_sequence() {
    global $sync_queue;
    $wizSettings = get_option('idemailwiz_settings');

    if (isset($wizSettings['iterable_sync_toggle']) && $wizSettings['iterable_sync_toggle'] === 'on') {
        // Perform the non-triggered sync
        idemailwiz_sync_non_triggered_metrics();
    } else {
        error_log('Blast sync cron was initiated but sync toggle is disabled');
    }

    if (isset($wizSettings['iterable_triggered_sync_toggle']) && $wizSettings['iterable_triggered_sync_toggle'] === 'on') {
        foreach ($sync_queue as $sync_action) {
            idemailwiz_sync_triggered_metrics($sync_action);

            // Assume each sync function sets a transient when it starts and deletes it when done
            while (get_transient("idemailwiz_sync_{$sync_action}_running")) {
                sleep(10); // Wait before checking again
            }
        }
    } else {
        error_log('Triggered sync cron was initiated but sync toggle is disabled');
    }
}



function idemailwiz_sync_triggered_metrics($metricType)
{
    // Check if a sync is already running, and if so, exit the function
    if (get_transient("idemailwiz_sync_{$metricType}_running")) {
        //wiz_log("Sync for {$metricType} is already running. Exiting...");
        return;
    }

    // Set transient to indicate the sync is running
    set_transient("idemailwiz_sync_{$metricType}_running", true, 20 * MINUTE_IN_SECONDS);

    wiz_log("Starting triggered {$metricType}s sync...");
    global $wpdb;

    // Get all triggered campaign IDs with 'Running' state
    $triggeredCampaigns = get_idwiz_campaigns(['type' => 'Triggered', 'campaignState' => 'Running']);
    $triggeredCampaignIds = array_column($triggeredCampaigns, 'id');

    // Prepare the API call to fetch data
    $exportFetchStart = new DateTimeImmutable('-36 hours');
    $exportStartData = [
        "outputFormat" => "application/x-json-stream",
        "dataTypeName" => 'email' . ucfirst($metricType), // Assuming 'email' is prefixed for email metrics
        "delimiter" => ",",
        "onlyFields" => "createdAt,userId,campaignId,templateId,messageId",
        "startDateTime" => $exportFetchStart->format('Y-m-d')
    ];

    try {
        // Start the export job for all data
        $response = idemailwiz_iterable_curl_call('https://api.iterable.com/api/export/start', $exportStartData);
        if (isset($response['response']['jobId'])) {
            $jobId = $response['response']['jobId'];
            wiz_log("Export job started with Job ID: $jobId");
        } else {
            throw new Exception('Failed to start export job');
        }
    } catch (Exception $e) {
        wiz_log("Error starting export job: " . $e->getMessage());
        delete_transient("idemailwiz_sync_{$metricType}_running");
        return;
    }

    // Wait and fetch the data from the Iterable export job
    $cntRecords = 0;
    $tableName = $wpdb->prefix . 'idemailwiz_triggered_' . $metricType . 's';
    $jobState = 'starting';
    while ($jobState !== 'completed') {
        sleep(1);
        $apiResponse = idemailwiz_iterable_curl_call('https://api.iterable.com/api/export/' . $jobId . '/files');
        $jobState = $apiResponse['response']['jobState'];
        if ($jobState === 'failed') {
            wiz_log("Export job failed for Job ID: $jobId");
            delete_transient("idemailwiz_sync_{$metricType}_running");
            return;
        }
    }
    // Process the completed job
    foreach ($apiResponse['response']['files'] as $file) {
        $jsonResponse = file_get_contents($file['url']);
        $lines = explode("\n", $jsonResponse);

        foreach ($lines as $line) {
            if (trim($line) === '')
                continue;

            $decodedData = json_decode($line, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                wiz_log('json_decode error: ' . json_last_error_msg());
                continue;
            }
            // Filter out records that don't match our triggered campaigns
            
            if (isset($decodedData['campaignId']) && in_array($decodedData['campaignId'], $triggeredCampaignIds)) {
                $messageId = $decodedData['messageId'] ?? false;
                if (!$messageId) {
                    continue;
                }
                $exists = $wpdb->get_var($wpdb->prepare("SELECT messageId FROM $tableName WHERE messageId = %s", $messageId));

                // If the record doesn't exist, insert it
                if (!$exists) {
                    $record = [
                        'messageId' => $messageId,
                        'userId' => $decodedData['userId'] ?? null,
                        'campaignId' => $decodedData['campaignId'],
                        'templateId' => $decodedData['templateId'],
                        'startAt' => strtotime($decodedData['createdAt']) * 1000,
                    ];
                    idemailwiz_insert_triggered_metric_record($record, $metricType);
                    $cntRecords++;
                }
            }
        }
    }

    wiz_log("Finished updating $cntRecords triggered $metricType records!");

    // Delete transient to indicate the sync is done
    delete_transient("idemailwiz_sync_{$metricType}_running");

    return true;
}


function idemailwiz_insert_triggered_metric_record($record, $metricType)
{
    global $wpdb;
    $tableName = $wpdb->prefix . 'idemailwiz_triggered_' . $metricType . 's';

    if (!is_array($record) || empty($record) || !isset($record['messageId'])) {
        //wiz_log("No new $metricType records found for campaign " . $record['campaignId'] . ".");
        return false;
    }

    // Check if a record with the same messageId already exists in the database
    $existingRecord = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $tableName WHERE messageId = %s",
            $record['messageId']
        ),
        ARRAY_A
    );

    // If an existing record is found and the current record has an earlier startAt date, update the record
    if ($existingRecord && $record['startAt'] < $existingRecord['startAt']) {
        $data = [
            'startAt' => $record['startAt'],
        ];
        $where = ['messageId' => $record['messageId']];
        $result = $wpdb->update($tableName, $data, $where);
    } elseif (!$existingRecord) {
        // If no existing record is found, insert the new record
        $data = [
            'messageId' => $record['messageId'],
            'campaignId' => $record['campaignId'],
            'templateId' => $record['templateId'],
            'startAt' => $record['startAt'],
        ];
        $result = $wpdb->insert($tableName, $data);
    } else {
        // The current record is older or the same as the existing record, so no action is needed
        $result = true;
    }

    // Log the outcome of the insert/update
    if ($result) {
        // Log success if a new record was inserted or an existing record was updated
        //wiz_log("Successfully inserted/updated record for messageId: " . $record['messageId']);
    } else {
        wiz_log("Failed to insert/update $metricType record for messageId: " . $record['messageId'] . ' on campaign ' . $record['campaignId']);
    }

    return $result;
}




// The below functions are mostly used during the import process, no the regular sync


function sync_single_triggered_campaign()
{
    $campaignId = $_POST['campaignId'];
    check_ajax_referer('id-general', 'security');

    idemailwiz_import_triggered_metrics_from_api([$campaignId], 'send');
    idemailwiz_import_triggered_metrics_from_api([$campaignId], 'open');
    idemailwiz_import_triggered_metrics_from_api([$campaignId], 'click');
}
add_action('wp_ajax_sync_single_triggered_campaign', 'sync_single_triggered_campaign');


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
        idemailwiz_insert_triggered_metric_record($record, $metricType);
    }

    error_log("Finished inserting new $metricType records for triggered campaigns from CSV.");
    return true;
}