<?php
// Include WordPress' database functions
global $wpdb;

function idemailwiz_iterable_curl_call($apiURL, $postData = null, $verifySSL = false, $retryAttempts = 2, $maxConsecutive400Errors = 2, $timeout = 60)
{
    // wiz_log("Entering idemailwiz_iterable_curl_call for URL: $apiURL"); // REMOVED
    $attempts = 0;
    $consecutive400Errors = 0;
    $consecutiveTimeouts = 0;

    do {
        // wiz_log("cURL Attempt #" . ($attempts + 1) . " for $apiURL"); // REMOVED
        // Initialize cURL
        $ch = curl_init($apiURL);

        // Set the appropriate headers based on the URL
        $headers = ["Content-Type: application/json"];
        if (strpos($apiURL, 'iterable')) {
            $settings = get_option('idemailwiz_settings', array());
            $api_key = isset($settings['iterable_api_key']) ? $settings['iterable_api_key'] : '';
            
            if (empty($api_key)) {
                $error_msg = "Iterable API key not found in settings";
                error_log($error_msg); // Keep error_log for system/PHP errors
                throw new Exception($error_msg);
            }
            
            $headers[] = "Api-Key: $api_key";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);

        // If POST data is provided, set up a POST request
        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }

        // Set timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        // Return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Execute the request
        // wiz_log("Executing curl_exec for $apiURL"); // REMOVED
        $response = curl_exec($ch);
        // wiz_log("curl_exec finished for $apiURL. Raw response size: " . strlen($response ?? '') . " bytes."); // REMOVED

        // Get HTTP status code and cURL error
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        // Close cURL handle
        curl_close($ch);

        // Check for cURL errors
        if ($curlErrno) {
            wiz_log("cURL Error detected for $apiURL (Attempt " . ($attempts + 1) . "): [$curlErrno] $curlError"); // KEEP: Log cURL Error
            if ($curlErrno == CURLE_OPERATION_TIMEDOUT) {
                $consecutiveTimeouts++;
                
                if ($consecutiveTimeouts > 2) {
                    wiz_log("Too many consecutive timeouts. Aborting API call to $apiURL."); // KEEP: Log consecutive timeouts
                    throw new Exception("CONSECUTIVE_TIMEOUTS");
                }
                
                sleep(2); // Wait before retrying
                continue;
            }
            
            // wiz_log("cURL Error: " . $curlError); // Redundant with above log
            throw new Exception("cURL Error: " . $curlError);
        }

        // Check for HTTP errors (4xx, 5xx)
        if ($httpCode >= 400) {
            wiz_log("HTTP Error detected: $httpCode for $apiURL (Attempt " . ($attempts + 1) . "): " . substr($response ?? '', 0, 500)); // KEEP: Log HTTP Error
            if ($httpCode >= 400 && $httpCode < 500) { // 4xx Client Errors
                $consecutive400Errors++;
                if ($consecutive400Errors > $maxConsecutive400Errors) {
                    wiz_log("Consecutive 4xx HTTP Errors exceeded limit for $apiURL. Last Code: $httpCode"); // KEEP: Log consecutive 4xx errors
                    throw new Exception("CONSECUTIVE_400_ERRORS");
                }
                sleep(3); // Wait for 3 seconds before retrying
            } else { // 5xx Server Errors or other >= 400
                // wiz_log("HTTP Error: $httpCode for $apiURL (Attempt " . ($attempts + 1) . "): " . substr($response ?? '', 0, 500)); // Redundant with above log
                throw new Exception("HTTP_ERROR"); // Throw generic HTTP error for retry or handling by caller
            }
        } else {
            // Success! Reset counters and break the loop
            $consecutive400Errors = 0; 
            $consecutiveTimeouts = 0;
            // wiz_log("cURL call successful for $apiURL on attempt " . ($attempts + 1)); // REMOVED Success log
            break; 
        }

        $attempts++;

        // If maximum attempts reached after errors, throw an exception
        if ($attempts >= $retryAttempts) { // Use >= to ensure it triggers after the last allowed attempt
             wiz_log("API call to $apiURL failed after $retryAttempts attempts. Last HTTP Code: $httpCode"); // KEEP: Log max retries reached
             throw new Exception("MAX_RETRY_ATTEMPTS_REACHED");
        }

    } while ($httpCode >= 400); // Continue loop only if there was an error code

    // Decode the JSON response if possible
    // wiz_log("Attempting json_decode for response from $apiURL"); // REMOVED
    $decodedResponse = json_decode($response, true);
    if (is_array($decodedResponse)) {
        // If decoding was successful and it's an array
        // wiz_log("json_decode successful for $apiURL"); // REMOVED
        $response = $decodedResponse;
    } else {
         // Only log if the response wasn't empty but failed to decode
         if (!empty($response)) {
             wiz_log("json_decode failed or did not return an array for $apiURL response. JSON Error: " . json_last_error_msg() . ". Response start: " . substr($response, 0, 100)); // KEEP: Log decode failure
         }
    }

    // wiz_log("Exiting idemailwiz_iterable_curl_call for $apiURL with HTTP code: $httpCode"); // REMOVED
    // Return both the decoded response and the HTTP status code
    return ['response' => $response, 'http_code' => $httpCode];
}


function idemailwiz_iterable_curl_multi_call($apiURLs, $verifySSL = false)
{
    // Fetch the API key
    $settings = get_option('idemailwiz_settings', array());
    $api_key = isset($settings['iterable_api_key']) ? $settings['iterable_api_key'] : '';
    
    if (empty($api_key)) {
        $error_msg = "Iterable API key not found in settings";
        error_log($error_msg);
        // Keep wiz_log for multi-call failure as it might not be caught elsewhere easily
        wiz_log("Error in multi_call: Iterable API key not found in settings"); 
        throw new Exception($error_msg);
    }

    // Initialize cURL Multi handle
    $mh = curl_multi_init();
    $handles = [];

    // Initialize each cURL handle and add it to the Multi handle
    foreach ($apiURLs as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Api-Key: $api_key",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // Add a reasonable timeout for multi calls too
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_multi_add_handle($mh, $ch);
        $handles[$url] = $ch; // Store handles keyed by URL for easier result mapping
    }

    // Execute the handles
    $running = null;
    do {
        $execReturnValue = curl_multi_exec($mh, $running);
    } while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);

    while ($running && $execReturnValue == CURLM_OK) {
        if (curl_multi_select($mh) == -1) {
            usleep(100); // Prevent busy-waiting
        }
        do {
            $execReturnValue = curl_multi_exec($mh, $running);
        } while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);
    }

    // Collect results
    $results = [];
    foreach ($handles as $url => $handle) {
        $responseContent = curl_multi_getcontent($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($handle);
        
        if ($curlError) {
            wiz_log("Multi cURL Error for $url: $curlError"); // KEEP: Log multi-curl errors
        }

        $decodedResponse = json_decode($responseContent, true);
        if (json_last_error() !== JSON_ERROR_NONE && !empty($responseContent)) {
             wiz_log("Multi cURL json_decode failed for $url. JSON Error: " . json_last_error_msg()); // KEEP: Log multi-curl decode failure
        }

        $results[] = [
            'response' => $decodedResponse ?? $responseContent, // Return raw content if decode fails
            'httpCode' => $httpCode,
            'curlError' => $curlError // Include curl error in result
        ];
        curl_multi_remove_handle($mh, $handle);
    }

    curl_multi_close($mh);
    return $results;
}