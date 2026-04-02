<?php
global $wpdb;

function idemailwiz_iterable_curl_call($apiURL, $postData = null, $verifySSL = false, $retryAttempts = 2, $maxConsecutive400Errors = 2, $timeout = 60)
{
    $attempts = 0;
    $consecutive400Errors = 0;
    $consecutiveTimeouts = 0;

    do {
        $ch = curl_init($apiURL);

        $headers = ["Content-Type: application/json"];
        if (strpos($apiURL, 'iterable')) {
            $settings = get_option('idemailwiz_settings', array());
            $api_key = isset($settings['iterable_api_key']) ? $settings['iterable_api_key'] : '';
            
            if (empty($api_key)) {
                $error_msg = "Iterable API key not found in settings";
                error_log($error_msg);
                throw new Exception($error_msg);
            }
            
            $headers[] = "Api-Key: $api_key";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);

        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        unset($ch);

        if ($curlErrno) {
            wiz_log("cURL Error for $apiURL (Attempt " . ($attempts + 1) . "): [$curlErrno] $curlError");
            if ($curlErrno == CURLE_OPERATION_TIMEDOUT) {
                $consecutiveTimeouts++;
                
                if ($consecutiveTimeouts > 2) {
                    wiz_log("Too many consecutive timeouts. Aborting API call to $apiURL.");
                    throw new Exception("CONSECUTIVE_TIMEOUTS");
                }
                
                sleep(2);
                continue;
            }
            
            throw new Exception("cURL Error: " . $curlError);
        }

        if ($httpCode >= 400) {
            wiz_log("HTTP $httpCode for $apiURL (Attempt " . ($attempts + 1) . "): " . substr($response ?? '', 0, 500));
            if ($httpCode >= 400 && $httpCode < 500) {
                $consecutive400Errors++;
                if ($consecutive400Errors > $maxConsecutive400Errors) {
                    wiz_log("Consecutive 4xx errors exceeded limit for $apiURL. Last Code: $httpCode");
                    throw new Exception("CONSECUTIVE_400_ERRORS");
                }
                sleep(3);
            } else {
                throw new Exception("HTTP_ERROR");
            }
        } else {
            $consecutive400Errors = 0; 
            $consecutiveTimeouts = 0;
            break; 
        }

        $attempts++;

        if ($attempts >= $retryAttempts) {
             wiz_log("API call to $apiURL failed after $retryAttempts attempts. Last HTTP Code: $httpCode. Response: " . substr($response ?? '', 0, 500));
             throw new Exception("MAX_RETRY_ATTEMPTS_REACHED (HTTP $httpCode)");
        }

    } while ($httpCode >= 400);

    $decodedResponse = json_decode($response, true);
    if (is_array($decodedResponse)) {
        $response = $decodedResponse;
    } else {
         if (!empty($response)) {
             wiz_log("json_decode failed for $apiURL. JSON Error: " . json_last_error_msg() . ". Response start: " . substr($response, 0, 100));
         }
    }

    return ['response' => $response, 'http_code' => $httpCode];
}


function idemailwiz_iterable_curl_multi_call($apiURLs, $verifySSL = false)
{
    $settings = get_option('idemailwiz_settings', array());
    $api_key = isset($settings['iterable_api_key']) ? $settings['iterable_api_key'] : '';
    
    if (empty($api_key)) {
        $error_msg = "Iterable API key not found in settings";
        error_log($error_msg);
        wiz_log("Error in multi_call: Iterable API key not found in settings"); 
        throw new Exception($error_msg);
    }

    $mh = curl_multi_init();
    $handles = [];

    foreach ($apiURLs as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Api-Key: $api_key",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_multi_add_handle($mh, $ch);
        $handles[$url] = $ch;
    }

    $running = null;
    do {
        $execReturnValue = curl_multi_exec($mh, $running);
    } while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);

    while ($running && $execReturnValue == CURLM_OK) {
        if (curl_multi_select($mh) == -1) {
            usleep(100);
        }
        do {
            $execReturnValue = curl_multi_exec($mh, $running);
        } while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);
    }

    $results = [];
    foreach ($handles as $url => $handle) {
        $responseContent = curl_multi_getcontent($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($handle);
        
        if ($curlError) {
            wiz_log("Multi cURL Error for $url: $curlError");
        }

        $decodedResponse = json_decode($responseContent, true);
        if (json_last_error() !== JSON_ERROR_NONE && !empty($responseContent)) {
             wiz_log("Multi cURL json_decode failed for $url. JSON Error: " . json_last_error_msg());
        }

        $results[] = [
            'response' => $decodedResponse ?? $responseContent,
            'httpCode' => $httpCode,
            'curlError' => $curlError
        ];
        curl_multi_remove_handle($mh, $handle);
    }

    unset($mh);
    return $results;
}
