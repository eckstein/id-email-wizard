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


// Schedule the hourly job exports for triggered data
if (!wp_next_scheduled('idemailwiz_start_export_jobs')) {
    wp_schedule_event(time(), 'hourly', 'idemailwiz_start_export_jobs');
}
add_action('idemailwiz_start_export_jobs', 'idemailwiz_start_export_jobs');

function idemailwiz_start_export_jobs()
{
    $metricTypes = ['send', 'open', 'click', 'bounce', 'sendSkip', 'unSubscribe', 'complaint'];
    wiz_log('Iterable export jobs started via external cron...');




    foreach ($metricTypes as $metricType) {
        $startTime = microtime(true);

        $jobTransient = get_transient("idemailwiz_sync_{$metricType}_jobId") ?? false;
        $transientLastUpdated = $jobTransient['lastUpdated'] ?? false;

        if (!$jobTransient || ($transientLastUpdated && (time() - $transientLastUpdated) > (60 * 60))) {
            if (get_transient('idemailwiz_export_' . $metricType . '_jobs_running')) {
                wiz_log("Export jobs for $metricType are already running, skipping...");
                return;
            }
            set_transient('idemailwiz_export_' . $metricType . '_jobs_running', true, 60 * 20);
            wiz_log("Starting triggered {$metricType}s export jobs...");
            idemailwiz_start_triggered_data_job($metricType);
        }

        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;

        // If the request took less than 1 second, sleep the remaining time
        if ($elapsedTime < 1) {
            usleep((1 - $elapsedTime) * 1000000);
        }
        delete_transient('idemailwiz_export_' . $metricType . '_jobs_running');
    }

}





$wizSettings = get_option('idemailwiz_settings');
$cronSyncActive = $wizSettings['sync_method'] ?? 'wp_cron';

// Check if wp_cron sync method is active
if ($cronSyncActive === 'wp_cron') {
    // Schedule the initial sync event if not already scheduled
    if (!wp_next_scheduled('idemailwiz_sync_sequence')) {
        //Schedule the initial sync event, 1 hour from when the wp_cron sync is turned on (to avoid immediate execution when turned on)
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'idemailwiz_sync_sequence');
    }
    // The action that manages the sync sequence
    add_action('idemailwiz_sync_sequence', 'idemailwiz_process_sync_sequence');
} else {
    // If wp_cron is not the sync method, clear any scheduled events
    $timestamp = wp_next_scheduled('idemailwiz_sync_sequence');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'idemailwiz_sync_sequence');
    }
}

add_action('wp_ajax_idemailwiz_handle_manual_sync', 'idemailwiz_handle_manual_sync');

function idemailwiz_handle_manual_sync()
{
    check_ajax_referer('id-general', 'security');

    // Extract the form fields from the POST data
    parse_str($_POST['formFields'], $formFields);

    // Check for sync types
    if (empty($formFields['syncTypes'])) {
        wp_send_json_error('No sync types were received.');
        return;
    }

    // Extract campaign IDs, if provided
    $campaignIds = !empty($formFields['campaignIds']) ? explode(',', $formFields['campaignIds']) : false;

    // Initiate the sync sequence
    $syncResult = idemailwiz_process_sync_sequence($formFields['syncTypes'], $campaignIds, true);

    if ($syncResult === false) {
        wp_send_json_error('Sync sequence aborted: Another sync is still in progress or there was an error.');
    } else {
        wp_send_json_success('Sync sequence successfully initiated.');
    }
}


function idemailwiz_process_sync_sequence($syncTypes = [], $campaignIds = null, $manualSync = false)
{
    // Default sync queue
    $default_sync_queue = ['blast', 'send', 'open', 'click', 'unSubscribe', 'bounce', 'sendSkip', 'complaint'];

    // If specific sync types are set, use that list; otherwise, use the default
    $sync_queue = !empty($syncTypes) ? $syncTypes : $default_sync_queue;

    $wizSettings = get_option('idemailwiz_settings');

    // Check if a sync is already in progress
    if (get_transient('idemailwiz_sync_in_progress')) {
        wiz_log('Sync sequence aborted: Another sync is still in progress.');
        return false; // Abort the sync sequence
    }

    wiz_log('Sync sequence for ' . implode(', ', $sync_queue) . ' initiated, please wait...');
    $blastSync = $wizSettings['iterable_sync_toggle'] ?? 'off';
    $triggeredSync = $wizSettings['iterable_triggered_sync_toggle'] ?? 'off';
    if (($blastSync != 'on' && $triggeredSync != 'on') || $manualSync) {
        wiz_log('Blast and Triggered sync are turned off in the sync settings.');
        return false;
    }

    // Mark the start of a sync sequence
    set_transient('idemailwiz_sync_in_progress', true, 60 * 61); // 61 minute expiration

    if (in_array('blast', $sync_queue)) {

        if ($blastSync == 'on' || $manualSync) {

            // Perform the non-triggered (blast) sync
            idemailwiz_sync_non_triggered_metrics($campaignIds);
        } else {
            wiz_log('Blast sync cron initiated but sync toggle is disabled.');
            //return false;
        }
    }


    // If there's more than 1 item in the sync queue, we know there's at least one triggered type
    if (count($sync_queue) > 1) {

        if ($triggeredSync == 'on' || $manualSync) {
            foreach ($sync_queue as $sync_action) {
                // Handle the triggered sync types
                idemailwiz_sync_triggered_metrics($sync_action);
            }
        } else {
            wiz_log('Triggered sync cron initiated but sync toggle is disabled.');
            //return false;
        }
    }



    // Mark the end of the sync sequence
    delete_transient('idemailwiz_sync_in_progress');

    return true; // Indicate successful completion of the sync sequence
}



function idemailwiz_start_triggered_data_job($metricType)
{
    $triggeredCampaigns = get_idwiz_campaigns(['type' => 'Triggered', 'campaignState' => 'Running', 'fields' => 'id']);
    // Prepare the API call to fetch data
    $exportFetchStart = new DateTimeImmutable('-36 hours');
    $transientData['jobIds'] = [];
    $countRetrieved = 0;
    foreach ($triggeredCampaigns as $campaign) {

        if (!isset($campaign['id'])) {
            continue;
        }
        $exportStartData = [
            "outputFormat" => "application/x-json-stream",
            "dataTypeName" => 'email' . ucfirst($metricType),
            "delimiter" => ",",
            "onlyFields" => "createdAt,userId,campaignId,templateId,messageId",
            "startDateTime" => $exportFetchStart->format('Y-m-d'),
            "campaignId" => (int) $campaign['id']
        ];
        try {
            $startTime = microtime(true);
            // Start the export job for all data
            $response = idemailwiz_iterable_curl_call('https://api.iterable.com/api/export/start', $exportStartData);
            if (isset($response['response']['jobId'])) {
                $jobId = $response['response']['jobId'];
                $transientData['lastUpdated'] = time();
                if (!in_array($jobId, $transientData['jobIds'])) {
                    $transientData['jobIds'][] = $jobId;
                }
                set_transient("idemailwiz_sync_{$metricType}_jobId", $transientData, (60 * 60 * 24) * 7); // 7 days expiration (Iterable holds exports for 7 days)

                $countRetrieved++;
                //wiz_log("Triggered {$metricType}s export started for campaign {$campaign['id']} with Job ID: $jobId");
            } else {
                throw new Exception('Failed to start export job');
            }
        } catch (Exception $e) {
            wiz_log("Error starting export job: " . $e->getMessage());
            delete_transient("idemailwiz_sync_{$metricType}_running");
            return false;
        }
        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;

        // If the request took less than 1 second, sleep the remaining time
        if ($elapsedTime < 1) {
            usleep((1 - $elapsedTime) * 1000000);
        }
    }
    wiz_log("Triggered {$metricType}s exports started and Job IDs stored for $countRetrieved campaigns");
    return true;
}


function idemailwiz_sync_triggered_metrics($metricType)
{
    $jobTransient = get_transient("idemailwiz_sync_{$metricType}_jobId") ?? false;
    $jobIds = $jobTransient['jobIds'] ?? false;

    // Check if a sync is already running, and if so, exit the function
    if (get_transient("idemailwiz_sync_{$metricType}_running")) {
        wiz_log("Sync for {$metricType} is already running. Exiting...");
        return;
    }

    // Set transient to indicate the sync is running
    set_transient("idemailwiz_sync_{$metricType}_running", true, 20 * MINUTE_IN_SECONDS);

    wiz_log("Starting triggered {$metricType}s sync...");
    global $wpdb;



    if (!$jobIds || empty($jobIds)) {
        wiz_log("No Export Job IDs found for Triggered {$metricType}s. Will check again in an hour.");

        // Start a new export job so it fills our missing transient
        //idemailwiz_start_triggered_data_job($metricType);
        delete_transient("idemailwiz_sync_{$metricType}_running");
        return false;
    }
    wiz_log('Retrieving jobs from Iterable');
    $cntRecords = 0;
    foreach ($jobIds as $jobId) {
        $apiResponse = idemailwiz_iterable_curl_call('https://api.iterable.com/api/export/' . $jobId . '/files');
        if (!$apiResponse or empty($apiResponse)) {
            return false;
        }
        $jobState = $apiResponse['response']['jobState'];

        if ($jobState === 'failed') {
            wiz_log("Export job failed for Job ID: $jobId");

            continue;
        } else if ($jobState == 'completed') {
            wiz_log('Fetched completed job with Job ID: ' . $jobId);
            $startAfter = '';
            do {
                // Fetch file list with pagination
                $fileApiResponse = idemailwiz_iterable_curl_call('https://api.iterable.com/api/export/' . $jobId . '/files?startAfter=' . $startAfter);
                foreach ($fileApiResponse['response']['files'] as $file) {
                    // Process each file
                    $processResult = idemailwiz_process_completed_sync_job($file['url'], $metricType);
                    if ($processResult == false) {
                        wiz_log('job could not be processed, skipping...');
                        continue;
                    }

                    $cntRecords += $processResult;

                    // Update startAfter for pagination
                    $startAfter = basename($file['url']);
                }

                // Check if more files are available
                $moreFilesAvailable = count($fileApiResponse['response']['files']) > 0;
            } while ($moreFilesAvailable);

        } else {
            wiz_log("Job ID $jobId for Triggered {$metricType}s is not yet ready for export, skipping...");
            continue;
        }
        sleep(1); // max a per second max
    }
    wiz_log('Finished triggered sync for ' . $metricType);
    delete_transient("idemailwiz_sync_{$metricType}_running");
}

function idemailwiz_process_completed_sync_job($fileUrl, $metricType)
{
    
    set_time_limit(360);
    global $wpdb;
    $cntRecords = 0;

    $jsonResponse = file_get_contents($fileUrl);
    $lines = explode("\n", $jsonResponse);

    wiz_log("Processing $metricType with file: $fileUrl");
    wiz_log('Looping through lines of file...');

    foreach ($lines as $line) {
        if (trim($line) === '')
            continue;

        $decodedData = json_decode($line, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wiz_log('json_decode error: ' . json_last_error_msg());
            continue;
            wiz_log('json error, skipping');
        }

        if (idemailwiz_insert_triggered_metric_record($decodedData, $metricType)) {
            $cntRecords++;
        }

        // Manually invoke garbage collection
        //unset($decodedData);
        gc_collect_cycles();

    }

    wiz_log("Finished updating $cntRecords triggered $metricType records.");
    return $cntRecords;
}






function idemailwiz_insert_triggered_metric_record($record, $metricType)
{
    global $wpdb;
    $dbMetric = strtolower($metricType);
    $tableName = $wpdb->prefix . 'idemailwiz_triggered_' . $dbMetric . 's';

    if (!is_array($record) || empty($record) || !isset($record['messageId'])) {
        return false;
    }

    // Prepare data for insertion or update
    $data = [
        'messageId' => $record['messageId'],
        'userId' => $record['userId'] ?? null,
        'campaignId' => $record['campaignId'] ?? null,
        'templateId' => $record['templateId'] ?? null,
        'startAt' => strtotime($record['createdAt']) ?? null,
    ];

    // Check if the record exists
    $exists = $wpdb->get_var($wpdb->prepare("SELECT messageId FROM $tableName WHERE messageId = %s", $record['messageId']));


    if ($exists) {
        // Update existing record
        $where = ['messageId' => $record['messageId']];
        $result = $wpdb->update($tableName, $data, $where);

        if ($result === false && $wpdb->rows_affected == 0) {
            // The update was successful, but no rows were affected (no changes)
            $result = true; // Treat this as a successful operation
        }
    } else {
        // Insert new record
        $result = $wpdb->insert($tableName, $data);
    }

    if (!$result) {
        //wiz_log("Failed to upsert $metricType record for messageId: " . $record['messageId'] . ". Error: " . $wpdb->last_error);
    }

    return (bool) $result;
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


