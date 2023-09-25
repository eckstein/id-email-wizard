<?php
$client = new Google_Client();
$client->setAuthConfig(plugin_dir_path(__FILE__) . 'path/to/credentials.json');
$client->addScope(Google_Service_Analytics::ANALYTICS_READONLY);
