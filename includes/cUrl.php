<?php
// Include WordPress' database functions
global $wpdb;

function idemailwiz_iterable_curl_call($apiURL, $postData = null, $verifySSL = false, $retryAttempts = 2, $maxConsecutive400Errors = 2, $timeout = 60)
{
    $attempts = 0;
    $consecutive400Errors = 0;
    $consecutiveTimeouts = 0;

    do {
        // Initialize cURL
        $ch = curl_init($apiURL);

        // Set the appropriate headers based on the URL
        $headers = ["Content-Type: application/json"];
        if (strpos($apiURL, 'iterable')) {
            $settings = get_option('idemailwiz_settings', array());
            $api_key = isset($settings['iterable_api_key']) ? $settings['iterable_api_key'] : '';
            
            if (empty($api_key)) {
                $error_msg = "Iterable API key not found in settings";
                error_log($error_msg);
                wiz_log($error_msg);
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
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            
            // Check if it's a timeout error
            if (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
                $consecutiveTimeouts++;
                
                if ($consecutiveTimeouts > 2) {
                    wiz_log("Too many consecutive timeouts. Aborting API call.");
                    throw new Exception("CONSECUTIVE_TIMEOUTS");
                }
                
                sleep(2); // Wait before retrying
                continue;
            }
            
            wiz_log("cURL Error: " . $error);
            throw new Exception("cURL Error: " . $error);
        }

        // Get the HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close the cURL session
        curl_close($ch);

        // If a 400 error occurs, log the response and attempt details
        if ($httpCode === 400 || $httpCode >= 400) {
            $consecutive400Errors++;
            if ($consecutive400Errors > $maxConsecutive400Errors) {
                wiz_log("Consecutive HTTP Errors exceeded limit. HTTP Error: $httpCode");
                throw new Exception("CONSECUTIVE_400_ERRORS");
            }
            sleep(3); // Wait for 3 seconds before retrying
        } else {
            $consecutive400Errors = 0; // Reset consecutive 400 errors count if other status code received
        }

        $attempts++;

        // If maximum attempts reached, throw an exception
        if ($attempts > $retryAttempts) {
            wiz_log("HTTP Error after $retryAttempts attempts. Stopping execution.");
            throw new Exception("MAX_RETRY_ATTEMPTS_REACHED");
        }

    } while ($httpCode === 400 || $httpCode === 429 || $consecutiveTimeouts > 0);

    $decodedResponse = json_decode($response, true);
    if (is_array($decodedResponse)) {
        // If decoding was successful and it's an array
        $response = $decodedResponse;
    }

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
        wiz_log($error_msg);
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
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch; // Store handles for later use
    }

    // Execute the handles
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    // Collect results
    $results = [];
    foreach ($handles as $handle) {
        $results[] = [
            'response' => json_decode(curl_multi_getcontent($handle), true),
            'httpCode' => curl_getinfo($handle, CURLINFO_HTTP_CODE)
        ];
        curl_multi_remove_handle($mh, $handle);
    }

    curl_multi_close($mh);
    return $results;
}