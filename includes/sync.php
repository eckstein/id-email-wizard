<?php
/**
 * Sync orchestration core.
 *
 * Contains the shared DB writer (idemailwiz_update_insert_api_data), the
 * per-table sync orchestrators (idemailwiz_sync_*), metric calculation, and
 * the top-level AJAX/sequence handlers. The fetchers, user sync, queue, cron
 * wiring, and weekly-sends helpers live in includes/sync/*.php.
 */

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

	// If this is a user sync
	if ($table_name == $wpdb->prefix . 'idemailwiz_users') {
		$id_field = 'wizId';
	}

	// Get table columns once before processing items
	$table_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`");
	if (empty($table_columns)) {
		wiz_log("Error: Could not get columns for table {$table_name}");
		return ['success' => [], 'errors' => ["Failed to get table columns for {$table_name}"]];
	}

	// Batch processing - process items in smaller batches to prevent timeouts
	$batch_size = 250;
	$item_batches = array_chunk($items, $batch_size);
	
	foreach ($item_batches as $batch) {
		foreach ($batch as $key => $item) {
			// If this is a campaigns table sync
			if ($table_name == $wpdb->prefix . 'idemailwiz_campaigns' && $operation !== 'delete') {
				//update the campaigns table lastWizSync with the current datetime
				//$item['lastWizSync'] = date('Y-m-d H:i:s');
			}

			// Add 'name' to the metrics array
			if ($table_name == $wpdb->prefix . 'idemailwiz_metrics') {
				$metricCampaign = get_idwiz_campaign($item['id']);
				$metricName = $metricCampaign['name'] ?? '';
			}

			// If this is a purchase sync, we do some database cleanup and field normalization
			if ($table_name == $wpdb->prefix . 'idemailwiz_purchases') {
				// Exclude purchases with campaignIds that are negatives (like -12345)
				if (isset($item['campaignId']) && $item['campaignId'] < 0) {
					continue;
				}
			}

			// Filter out fields that don't exist in the table schema
			$filtered_item = array_intersect_key($item, array_flip($table_columns));
			
			// Log any fields that were filtered out
			$filtered_fields = array_diff_key($item, $filtered_item);

			if (($operation === "update" || $operation === "delete") && !isset($filtered_item[$id_field])) {
				$result['errors'][] = "Failed to perform {$operation}: missing ID field '{$id_field}'";
				continue;
			}

            // --- START: Handle potential array values ---
            $prepared_values = [];
            foreach ($filtered_item as $field_key => $field_value) {
                if (is_array($field_value)) {
                    // Serialize arrays before storing (e.g., for labels or other text fields)
                    // You might need more specific handling based on the column type
                    $prepared_values[] = serialize($field_value);
                } else {
                    $prepared_values[] = $field_value;
                }
            }
            // --- END: Handle potential array values ---

			// Prepare field data for SQL query
			$fields = implode(",", array_map(function ($field) {
				return "`" . esc_sql($field) . "`";
			}, array_keys($filtered_item)));
			
			if (empty($fields)) {
				$result['errors'][] = "Failed to perform {$operation}: no valid fields found";
				continue;
			}

			// Create correct number of placeholders for the values
			$placeholders = implode(",", array_fill(0, count($filtered_item), "%s"));
			// $prepared_values = array_values($filtered_item); // Use the already processed values

			try {
				if ($operation === "insert") {
					if ($table_name == $wpdb->prefix . 'idemailwiz_users') {
						// For users table, use INSERT ... ON DUPLICATE KEY UPDATE
						$updates = implode(", ", array_map(function ($field) {
							return "`$field` = VALUES(`$field`)";
						}, array_keys($filtered_item)));
						$sql = "INSERT INTO `{$table_name}` ({$fields}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updates}";
						try {
							$prepared_sql = $wpdb->prepare($sql, $prepared_values);
						} catch (Exception $e) {
							wiz_log("Error in insert (with update) prepare statement: " . $e->getMessage() . " SQL: " . $sql);
							$result['errors'][] = "Database prepare error: " . $e->getMessage();
							continue;
						}
					} else {
						// For other tables, use regular INSERT
						$sql = "INSERT INTO `{$table_name}` ({$fields}) VALUES ({$placeholders})";
						try {
							$prepared_sql = $wpdb->prepare($sql, $prepared_values);
						} catch (Exception $e) {
							wiz_log("Error in insert prepare statement: " . $e->getMessage() . " SQL: " . $sql);
							$result['errors'][] = "Database prepare error: " . $e->getMessage();
							continue;
						}
					}

					$insert_result = $wpdb->query($prepared_sql);
					if ($insert_result === false) {
						$result['errors'][] = "Failed to insert record: " . $wpdb->last_error;
					} else {
						$result['success'][] = "Inserted record successfully";
						}
				} elseif ($operation === "update") {
					// For update operations
					$updates = implode(", ", array_map(function ($field) {
						return "`$field` = %s";
					}, array_keys($filtered_item)));
                    
                    // Ensure the ID field value is appended correctly for the WHERE clause
                    $update_values = $prepared_values;
                    $update_values[] = $filtered_item[$id_field];

					$sql = "UPDATE `{$table_name}` SET {$updates} WHERE `{$id_field}` = %s";
					// $prepared_values[] = $filtered_item[$id_field]; // Add ID to the end for WHERE clause - Now handled by $update_values
					try {
						$prepared_sql = $wpdb->prepare($sql, $update_values); // Use $update_values
					} catch (Exception $e) {
						wiz_log("Error in update prepare statement: " . $e->getMessage() . " SQL: " . $sql);
						$result['errors'][] = "Database prepare error: " . $e->getMessage();
						continue;
					}

					$update_result = $wpdb->query($prepared_sql);
					if ($update_result === false) {
						$result['errors'][] = "Failed to update record: " . $wpdb->last_error;
					} else {
						$result['success'][] = "Updated record successfully";
					}
				} elseif ($operation === "delete") {
					// For delete operations
					$sql = "DELETE FROM `{$table_name}` WHERE `{$id_field}` = %s";
					try {
						$prepared_sql = $wpdb->prepare($sql, $filtered_item[$id_field]);
					} catch (Exception $e) {
						wiz_log("Error in delete prepare statement: " . $e->getMessage() . " SQL: " . $sql);
						$result['errors'][] = "Database prepare error: " . $e->getMessage();
						continue;
					}

					$delete_result = $wpdb->query($prepared_sql);
					if ($delete_result === false) {
						$result['errors'][] = "Failed to delete record: " . $wpdb->last_error;
					} else {
						$result['success'][] = "Deleted record successfully";
					}
				}
			} catch (Exception $e) {
				wiz_log("Error in database operation: " . $e->getMessage());
				$result['errors'][] = "Database error: " . $e->getMessage();
			}
		}
	}

	return $result;
}


function idemailwiz_sync_campaigns($passedCampaigns = null)
{
	wiz_log("Starting campaign sync process...");

	// Fetch campaigns from the API
	$campaigns = idemailwiz_fetch_campaigns($passedCampaigns);

	if (empty($campaigns) || is_string($campaigns)) {
		wiz_log("Sync Campaigns: No campaigns found to sync or error occurred: " . (is_string($campaigns) ? $campaigns : "Empty result"));
		return "No campaigns found to sync or error occurred.";
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_campaigns';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];
	$records_to_delete = [];

	try {
		// First, filter out campaigns we want to skip
		$filtered_campaigns = [];
		foreach ($campaigns as $campaign) {
			if (!isset($campaign['id'])) {
				continue;
			}
			
			if (isset($campaign['campaignState']) && $campaign['campaignState'] == 'Aborted') {
				// Skip aborted campaigns
				continue;
			}
			
			$filtered_campaigns[] = $campaign;
		}
		
		$campaigns = $filtered_campaigns;
		wiz_log("Sync Campaigns: Processing " . count($campaigns) . " campaigns after filtering.");
		
		// If we have no campaigns after filtering, return early
		if (empty($campaigns)) {
			return "No valid campaigns found to sync after filtering.";
		}

		// Get all campaign IDs in one query for efficiency
		$campaign_ids = array_filter(array_map(function($campaign) {
			return isset($campaign['id']) && is_numeric($campaign['id']) ? (int)$campaign['id'] : null;
		}, $campaigns));
		
		// Get all existing campaign records
		try {
			if (empty($campaign_ids)) {
				$existing_campaigns = [];
				wiz_log("No valid campaign IDs found to check against database.");
			} else {
				$placeholders = implode(',', array_fill(0, count($campaign_ids), '%d'));
				$query_params = array_merge(["SELECT * FROM $table_name WHERE id IN ($placeholders)"], array_values($campaign_ids));
				$existing_campaigns_query = call_user_func_array(
					array($wpdb, 'prepare'),
					$query_params
				);
				$existing_campaigns = $wpdb->get_results($existing_campaigns_query, ARRAY_A);
			}
			
			if ($wpdb->last_error) {
				throw new Exception("Database error fetching existing campaigns: " . $wpdb->last_error);
			}
			
			// Create a lookup array for faster checking
			$existing_campaign_lookup = [];
			foreach ($existing_campaigns as $existing_campaign) {
				$existing_campaign_lookup[$existing_campaign['id']] = $existing_campaign;
			}
		} catch (Exception $e) {
			wiz_log("Error fetching existing campaigns: " . $e->getMessage());
			return "Error fetching existing campaigns: " . $e->getMessage();
		}

		// Process campaigns in batches
		$batch_size = 500;
		$campaign_batches = array_chunk($campaigns, $batch_size);
		
		foreach ($campaign_batches as $batch_index => $campaign_batch) {
			$batch_to_update = [];
			$batch_to_insert = [];
			$batch_to_delete = [];
			
			foreach ($campaign_batch as $campaign) {
				// Check for archived campaigns and mark for deletion if needed
				if (isset($campaign['labels']) && is_array($campaign['labels']) && in_array('x_Archived', $campaign['labels'])) {
					$batch_to_delete[] = $campaign;
					continue;
				}
				
				// Check if campaign already exists using the lookup array
				if (isset($existing_campaign_lookup[$campaign['id']])) {
					// Perform deep comparison to decide if update is needed
					$wizCampaign = $existing_campaign_lookup[$campaign['id']];
					$fieldsDifferent = false;
					
					if ($passedCampaigns) {
						// If campaigns are passed, update them all
						$batch_to_update[] = $campaign;
					} else {
						// Otherwise, check if fields are different
						foreach ($campaign as $key => $value) {
							if (!isset($wizCampaign[$key]) || $wizCampaign[$key] != $value) {
								$fieldsDifferent = true;
								break;
							}
						}
						
						// Update the row if any field is different
						if ($fieldsDifferent) {
							$batch_to_update[] = $campaign;
						}
					}
				} else {
					// Campaign not in DB, add it
					$batch_to_insert[] = $campaign;
				}
			}
			
			// Process this batch and add results to the main arrays
			if (!empty($batch_to_insert)) {
				$records_to_insert = array_merge($records_to_insert, $batch_to_insert);
			}
			
			if (!empty($batch_to_update)) {
				$records_to_update = array_merge($records_to_update, $batch_to_update);
			}
			
			if (!empty($batch_to_delete)) {
				$records_to_delete = array_merge($records_to_delete, $batch_to_delete);
			}
		}
		
		wiz_log("Campaigns to process: Total " . count($campaigns) . " (Insert: " . count($records_to_insert) . ", Update: " . count($records_to_update) . ", Delete: " . count($records_to_delete) . ")");

		// Process the insert/update and log the result
		return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update, $records_to_delete);
	} catch (Exception $e) {
		wiz_log("Error in campaign sync process: " . $e->getMessage());
		return "Error in campaign sync process: " . $e->getMessage();
	}
}




function idemailwiz_sync_templates($passedCampaigns = null)
{
	wiz_log("Starting template sync process...");
	
	// Fetch relevant templates
	// Note: The fetch function filters by updatedAt differences to limit results
	$templates = idemailwiz_fetch_templates($passedCampaigns);

	if (empty($templates)) {
		wiz_log("No templates found to sync");
		return "No templates found to sync.";
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_templates';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];

	// Get all template IDs in one query for efficiency
	$template_ids = array_column($templates, 'templateId');
	
	// Fix for empty array check
	if (empty($template_ids)) {
		wiz_log("No template IDs found in fetched templates");
		return "No template IDs found in fetched templates.";
	}
	
	// Get all existing template IDs
	$placeholders = implode(',', array_fill(0, count($template_ids), '%d'));
	$existing_templates_query = call_user_func_array(
		array($wpdb, 'prepare'),
		array_merge(array("SELECT templateId FROM $table_name WHERE templateId IN ($placeholders)"), $template_ids)
	);
	$existing_template_ids = $wpdb->get_col($existing_templates_query);
	
	// Create a lookup array for faster checking
	$existing_template_lookup = array_flip($existing_template_ids);

	foreach ($templates as $template) {
		if (!isset($template['templateId'])) {
			wiz_log('No templateId found in the fetched template record!');
			continue;
		}
		
		// Check if template already exists using the lookup array
		if (isset($existing_template_lookup[$template['templateId']])) {
			$records_to_update[] = $template;
		} else {
			$records_to_insert[] = $template;
		}
	}
	
	wiz_log("Templates to process: " . count($templates) . " (Insert: " . count($records_to_insert) . ", Update: " . count($records_to_update) . ")");

	// Process and log the sync operation
	return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}





function idemailwiz_sync_purchases($campaignIds = null, $startDate = null, $endDate = null)
{
	wiz_log("Starting purchase sync process...");
	
	try {
		$purchases = idemailwiz_fetch_purchases($campaignIds, $startDate, $endDate);
		
		if (empty($purchases)) {
			wiz_log("No purchases found to sync");
			return "No purchases found to sync.";
		}

		global $wpdb;
		$purchases_table = $wpdb->prefix . 'idemailwiz_purchases';

		$records_to_insert = [];
		$records_to_update = [];
		
		// --- Get purchase IDs from the fetched data (for cleaning AND checking existence) ---
		$fetched_purchase_ids = array_column($purchases, 'id');
		// Clean purchase IDs from any 'purchase-' prefix
		foreach ($fetched_purchase_ids as &$id) {
			if (is_string($id) && strpos($id, 'purchase-') === 0) {
				$id = str_replace('purchase-', '', $id);
			}
		}
		unset($id); // Break the reference to the last element
        // --- End cleaning --- 
        
        // Fetch ONLY existing purchase IDs that are ALSO in the fetched list
        $existing_purchase_ids = [];
        if (!empty($fetched_purchase_ids)) {
            // Ensure IDs are appropriate for SQL (e.g., strings)
            $safe_ids = array_map('strval', $fetched_purchase_ids);
            $placeholders = implode(',', array_fill(0, count($safe_ids), '%s'));
            $existing_purchases_query = $wpdb->prepare(
                "SELECT id FROM $purchases_table WHERE id IN ($placeholders)",
                $safe_ids // Pass the safe array directly
            );
            $existing_purchase_ids = $wpdb->get_col($existing_purchases_query);
            if ($wpdb->last_error) {
                wiz_log("Sync Purchases: Error fetching existing purchase IDs for comparison: " . $wpdb->last_error);
                // Decide how to handle - maybe return error? For now, log and continue.
                $existing_purchase_ids = []; // Prevent further errors
            }
        }
        
        // Create a lookup array from the INTERSECTION of fetched and existing IDs
        $existing_purchase_lookup = array_flip($existing_purchase_ids);
		// --- Prepare campaign start time lookup ---
		$campaign_ids_in_purchases = array_unique(array_filter(array_column($purchases, 'campaignId')));
		$campaign_start_times = [];
		if (!empty($campaign_ids_in_purchases)) {
			$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
			$placeholders = implode(',', array_fill(0, count($campaign_ids_in_purchases), '%d'));
			$campaign_results = $wpdb->get_results(
				call_user_func_array(
					array($wpdb, 'prepare'),
					array_merge(array("SELECT id, startAt FROM $campaigns_table WHERE id IN ($placeholders)"), $campaign_ids_in_purchases)
				),
				ARRAY_A
			);
			// Create the lookup array
			foreach ($campaign_results as $campaign) {
				$campaign_start_times[$campaign['id']] = $campaign['startAt'];
			}
		}
		// --- End campaign start time lookup ---

		foreach ($purchases as &$purchase) {
			if (!isset($purchase['id'])) {
				wiz_log('No ID found in the fetched purchase record!');
				continue;
			}

			// Remove 'purchase-' prefix from ID if it exists (Do this BEFORE the lookup check)
			if (is_string($purchase['id']) && strpos($purchase['id'], 'purchase-') === 0) {
				$purchase['id'] = str_replace('purchase-', '', $purchase['id']);
			}

			// Convert campaignId to NULL if it's missing, empty, or zero
			if (!isset($purchase['campaignId']) || empty($purchase['campaignId']) || $purchase['campaignId'] === 0 || $purchase['campaignId'] === '0') {
				$purchase['campaignId'] = null;
			}

			// Fetch the campaign's startAt using the lookup array if campaignId is set
			if (isset($purchase['campaignId']) && isset($campaign_start_times[$purchase['campaignId']])) {
				$purchase['campaignStartAt'] = $campaign_start_times[$purchase['campaignId']];
			}

			// Check if purchase already exists using the lookup array
            $current_purchase_id = $purchase['id']; // Use the cleaned ID
            $found_in_lookup = isset($existing_purchase_lookup[$current_purchase_id]);

           

			if ($found_in_lookup) {
				$records_to_update[] = $purchase;
			} else {
				$records_to_insert[] = $purchase;
			}
		}
		
		wiz_log("Purchases to process: " . count($purchases) . " (Insert: " . count($records_to_insert) . ", Update: " . count($records_to_update) . ")");
		
		return idemailwiz_process_and_log_sync($purchases_table, $records_to_insert, $records_to_update);
	} catch (Exception $e) {
		wiz_log("Error in purchase sync: " . $e->getMessage());
		return "Error in purchase sync: " . $e->getMessage();
	}
}




function idemailwiz_sync_experiments($passedCampaigns = null)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_experiments';

	// Fetch experiments
	$experiments = idemailwiz_fetch_experiments($passedCampaigns);

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];

	foreach ($experiments as $experiment) {

		if (!isset($experiment['templateId'])) {
			if (!empty($experiment)) {
				wiz_log('No templateId found in the fetched experiment record!');
				continue;
			}
		}

		// Retrieve existing experiments from the database
		$wizExperiments = get_idwiz_experiments(['campaignIds' => [$experiment['campaignId']], 'templateId' => $experiment['templateId']]);

		if ($wizExperiments) {
			// Check if the experiment is already marked for update to avoid duplicates
			if (!in_array($experiment, $records_to_update)) {
				// Mark for update
				$records_to_update[] = $experiment;
			}
		} else {
			// Mark for insert
			$records_to_insert[] = $experiment;
		}
	}

	// Process the insert/update and log them
	return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}

function idemailwiz_sync_journeys($passedJourneys = null)
{
	wiz_log("Starting journeys sync process...");
	
	// Fetch journeys from the API
	$journeys = idemailwiz_fetch_journeys($passedJourneys);
	
	if (empty($journeys) || is_string($journeys)) {
		wiz_log("Sync Journeys: No journeys found to sync or error occurred: " . (is_string($journeys) ? $journeys : "Empty result"));
		return "No journeys found to sync or error occurred.";
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_journeys';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];
	$records_to_delete = [];

	try {
		// Get all journey IDs in one query for efficiency
		$journey_ids = array_filter(array_map(function($journey) {
			return isset($journey['id']) && is_numeric($journey['id']) ? (int)$journey['id'] : null;
		}, $journeys));
		
		// Get all existing journey records
		$existing_journeys = [];
		if (!empty($journey_ids)) {
			$placeholders = implode(',', array_fill(0, count($journey_ids), '%d'));
			$query_params = array_merge(["SELECT * FROM $table_name WHERE id IN ($placeholders)"], array_values($journey_ids));
			$existing_journeys_query = call_user_func_array(
				array($wpdb, 'prepare'),
				$query_params
			);
			$existing_journeys = $wpdb->get_results($existing_journeys_query, ARRAY_A);
		}
		
		if ($wpdb->last_error) {
			throw new Exception("Database error fetching existing journeys: " . $wpdb->last_error);
		}
		
		// Create a lookup array for faster checking
		$existing_journey_lookup = [];
		foreach ($existing_journeys as $existing_journey) {
			$existing_journey_lookup[$existing_journey['id']] = $existing_journey;
		}

		foreach ($journeys as $journey) {
			if (!isset($journey['id'])) {
				wiz_log('No ID found in the fetched journey record!');
				continue;
			}
			
			// Check for archived journeys and mark for deletion if needed
			if (isset($journey['isArchived']) && $journey['isArchived'] === true) {
				$records_to_delete[] = $journey;
				continue;
			}
			
			// Check if journey already exists using the lookup array
			if (isset($existing_journey_lookup[$journey['id']])) {
				// Perform deep comparison to decide if update is needed
				$wizJourney = $existing_journey_lookup[$journey['id']];
				$fieldsDifferent = false;
				
				if ($passedJourneys) {
					// If journeys are passed, update them all
					$records_to_update[] = $journey;
				} else {
					// Otherwise, check if fields are different
					foreach ($journey as $key => $value) {
						if (!isset($wizJourney[$key]) || $wizJourney[$key] != $value) {
							$fieldsDifferent = true;
							break;
						}
					}
					
					// Update the row if any field is different
					if ($fieldsDifferent) {
						$records_to_update[] = $journey;
					}
				}
			} else {
				// Journey not in DB, add it
				$records_to_insert[] = $journey;
			}
		}
		
		wiz_log("Journeys to process: Total " . count($journeys) . " (Insert: " . count($records_to_insert) . ", Update: " . count($records_to_update) . ", Delete: " . count($records_to_delete) . ")");

		// Process the insert/update and log the result
		return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update, $records_to_delete);
	} catch (Exception $e) {
		wiz_log("Error in journeys sync process: " . $e->getMessage());
		return "Error in journeys sync process: " . $e->getMessage();
	}
}




function idemailwiz_sync_metrics($passedCampaigns = null)
{

	$fetch_result = idemailwiz_fetch_metrics($passedCampaigns); // Gets all metrics if none are passed
	$metrics = $fetch_result['metrics'] ?? [];
	$requested_ids = $fetch_result['requested_ids'] ?? [];

	//(print_r($metrics, true));

	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_metrics';

	// Prepare arrays for comparison
	$records_to_update = [];
	$records_to_insert = [];
	$processed_ids = []; // Keep track of IDs returned by API

	if ($passedCampaigns) {
		// If specific campaigns are passed, assume we always want to update them if data exists
		$records_to_update = $metrics;
		$processed_ids = array_column($metrics, 'id');
	} else {
		// If syncing all, check existence before deciding insert/update
		foreach ($metrics as $metric) {
			if (!isset($metric['id'])) {
				wiz_log('No ID found in the fetched metric record!');
				continue;
			}
			$processed_ids[] = $metric['id']; // Track this ID

			// Handle SMS campaign header mapping
			$wizCampaign = get_idwiz_campaign($metric['id']);
			if ($wizCampaign && $wizCampaign['messageMedium'] == 'SMS') {
				$metric['uniqueEmailSends'] = $metric['uniqueSmsSent'] ?? 0;
				$metric['uniqueEmailsDelivered'] = $metric['uniqueSmsDelivered'] ?? 0;
				$metric['uniqueEmailClicks'] = $metric['uniqueSmsClicks'] ?? 0;
				// Potentially unset SMS specific fields if desired, e.g.:
				// unset($metric['uniqueSmsSent'], $metric['uniqueSmsDelivered'], $metric['uniqueSmsClicks']); 
			}

			// Check for existing metric
			$wizMetric = get_idwiz_metric($metric['id']);
			if ($wizMetric) {
				// Gather metric for update and de-dupe (shouldn't be necessary if API returns one row per ID)
				if (!in_array($metric, $records_to_update)) {
					$records_to_update[] = $metric;
				}
			} else {
				// metric not in db, we'll add it
				$records_to_insert[] = $metric;
			}
		}
	}

	// --- Add default records for requested IDs missing from API response --- 
	$missing_ids = array_diff($requested_ids, $processed_ids);
	if (!empty($missing_ids)) {
		wiz_log("Metrics Sync: Found " . count($missing_ids) . " requested campaigns missing from API response. Adding default zeroed records.");
		
		// Define all numeric columns expected in the metrics table (based on schema)
		$numeric_metric_columns = [
			'averageCustomConversionValue', 'averageOrderValue', 'purchasesMEmail', 'revenue', 
			'gaRevenue', 'revenueMEmail', 'sumOfCustomConversions', 'totalComplaints', 
			'totalCustomConversions', 'totalEmailHoldout', 'totalEmailOpens', 'totalEmailOpensFiltered', 
			'totalEmailSendSkips', 'totalEmailSends', 'totalEmailsBounced', 'totalEmailsClicked', 
			'totalEmailsDelivered', 'totalPurchases', 'totalUnsubscribes', 'uniqueCustomConversions', 
			'uniqueEmailClicks', 'uniqueEmailOpens', 'uniqueEmailOpensFiltered', 'uniqueEmailOpensOrClicks', 
			'uniqueEmailSends', 'uniqueEmailsBounced', 'uniqueEmailsDelivered', 'uniquePurchases', 
			'uniqueUnsubscribes', 'purchasesMSms', 'revenueMSms', 'totalInboundSms', 'totalSmsBounced', 
			'totalSmsDelivered', 'totalSmsHoldout', 'totalSmsSendSkips', 'totalSmsSent', 'totalSmsClicks', 
			'uniqueInboundSms', 'uniqueSmsBounced', 'uniqueSmsClicks', 'uniqueSmsDelivered', 
			'uniqueSmsSent', 'totalHostedUnsubscribeClicks', 'uniqueHostedUnsubscribeClicks', 
			'lastWizUpdate', 'wizDeliveryRate', 'wizOpenRate', 'wizCtr', 'wizCto', 'wizUnsubRate', 
			'wizCompRate', 'wizCvr', 'wizAov'
			// Note: opensByHour, clicksByHour are LONGTEXT, handle separately if needed, default to NULL
		];

		$default_metrics = [];
		foreach ($numeric_metric_columns as $col) {
			$default_metrics[$col] = 0;
		}
		$default_metrics['opensByHour'] = null;
		$default_metrics['clicksByHour'] = null;

		foreach ($missing_ids as $missing_id) {
			// Check if this ID already exists in DB (e.g., from a previous run)
			// If it exists, we don't need to insert a zeroed row unless we want to overwrite it.
			// For simplicity now, let's just add to insert - INSERT IGNORE will handle duplicates.
			$zeroed_record = array_merge(['id' => $missing_id], $default_metrics);
			$records_to_insert[] = $zeroed_record;
		}
	}
	// --- End default records addition --- 

	// Does our wiz_logging and returns data about the insert/update
	return idemailwiz_process_and_log_sync($table_name, $records_to_insert, $records_to_update);
}


function idemailwiz_process_and_log_sync($table_name, $records_to_insert = null, $records_to_update = null, $records_to_delete = null)
{
    // Extracting the type (e.g., 'campaign', 'template', etc.) from the table name
    $type = substr($table_name, strrpos($table_name, '_') + 1);

    $insert_results = '';
    $update_results = '';
    $return = array();

    // Only process and log if we have records to handle
    if (!empty($records_to_insert)) {
        $insert_results = idemailwiz_update_insert_api_data($records_to_insert, 'insert', $table_name);
        $return['insert'] = $insert_results;
    }
    
    if (!empty($records_to_update)) {
        $update_results = idemailwiz_update_insert_api_data($records_to_update, 'update', $table_name);
        $return['update'] = $update_results;
    }
    
    if (!empty($records_to_delete)) {
        $delete_results = idemailwiz_update_insert_api_data($records_to_delete, 'delete', $table_name);
        $return['delete'] = $delete_results;
    }

    // Only log if we actually processed some records
    if (!empty($records_to_insert) || !empty($records_to_update) || !empty($records_to_delete)) {
        $logInsertUpdate = return_insert_update_logging($insert_results, $update_results, $table_name);
        if ($logInsertUpdate && !strpos($logInsertUpdate, 'up to date')) {
            wiz_log(ucfirst($type) . " sync results: " . $logInsertUpdate);
        }
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
		return "The $tableNameType database is up to date! No inserts or updates are needed.";
	}

	return trim($logInsert . "\n" . $logUpdate);
}




// Ajax handler for sync button
// Also creates and logs readable sync responses from response arrays



function idemailwiz_ajax_sync()
{
	// Check for valid nonce
	if (
		!(
			check_ajax_referer('data-tables', 'security', false) ||
			check_ajax_referer('initiatives', 'security', false) ||
			check_ajax_referer('wiz-metrics', 'security', false) ||
			check_ajax_referer('id-general', 'security', false) ||
			check_ajax_referer('wizAjaxNonce', 'security', false)
		)
	) {
		wp_die('Invalid action or nonce');
	}

	$campaignIds = isset($_POST['campaignIds']) ? json_decode(stripslashes($_POST['campaignIds']), true) : [];

	$response =	idemailwiz_sync_non_triggered_metrics($campaignIds);

	if ($response === false) {
		wp_send_json_error('There was an error in the sync process!');
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
	if (!isset($metrics[$campaignIdKey])) {
		wiz_log("Can't calculate metrics, no ID found in data!");
		return false;
	}
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
		
		// Map SMS fields to email fields for consistency
		$metrics['uniqueEmailSends'] = $metrics['uniqueSmsSent'] ?? 0;
		$metrics['uniqueEmailsDelivered'] = $metrics['uniqueSmsDelivered'] ?? 0;
		$metrics['uniqueEmailClicks'] = $metrics['uniqueSmsClicks'] ?? 0;
		$metrics['uniqueEmailOpens'] = 0; // SMS doesn't have opens
	}

	// Ensure required fields are set
	foreach ($requiredFields as $field) {
		if (!isset($metrics[$field]) || $metrics[$field] === null) {
			$metrics[$field] = 0;
		}
	}

	// Calculate common metrics
	$sendValue = (float) $metrics['uniqueEmailSends'];
	$deliveredValue = (float) $metrics['uniqueEmailsDelivered'];
	$clicksValue = (float) $metrics['uniqueEmailClicks'];
	$opensValue = $medium == 'Email' ? (float) $metrics['uniqueEmailOpens'] : 0;
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
	if ($medium == 'Email' && $sendValue > 0) {
		$metrics['wizOpenRate'] = ($opensValue / $sendValue) * 100;
		$metrics['wizCto'] = $opensValue > 0 ? ($clicksValue / $opensValue) * 100 : 0;
	} else {
		$metrics['wizOpenRate'] = 0;
		$metrics['wizCto'] = 0;
	}

	// Remove metrics we don't want to sync in
	unset($metrics['uniqueSmsSentByMessage']);

	// For SMS campaigns, store original SMS metrics as well
	if ($medium == 'SMS') {
		$metrics['uniqueSmsSent'] = $metrics['uniqueEmailSends'];
		$metrics['uniqueSmsDelivered'] = $metrics['uniqueEmailsDelivered'];
		$metrics['uniqueSmsClicks'] = $metrics['uniqueEmailClicks'];
	}

	return $metrics;
}

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

function idemailwiz_sync_non_triggered_metrics($campaignIds = [], $sync_dbs = null)
{
	@set_time_limit(600);

	$syncArgs = [];
	$response = [];

	set_transient('idemailwiz_blast_sync_in_progress', true, (10 * MINUTE_IN_SECONDS));
	wiz_log("Starting metrics sync process...");

	$sync_dbs = $sync_dbs ?? ['campaigns', 'templates', 'metrics', 'purchases', 'experiments', 'journeys'];
	
	foreach ($sync_dbs as $db) {
		wiz_log("Syncing " . $db . "...");
		if (!empty($campaignIds)) {
			$syncArgs = $campaignIds;
		}
		
		$function_name = 'idemailwiz_sync_' . $db;
		if (!function_exists($function_name)) {
			wiz_log("Error: Sync function does not exist for " . $db);
			$response[$db] = ['error' => 'Sync function does not exist for ' . $db];
			continue;
		}
		
		try {
			$result = call_user_func($function_name, $syncArgs);
			
			if ($result === false) {
				wiz_log("Error: Sync failed for " . $db);
				$response[$db] = ['error' => 'Sync failed for ' . $db];
			} else {
				$response[$db] = $result;
			}
		} catch (Exception $e) {
			wiz_log("Exception during " . $db . " sync: " . $e->getMessage());
			$response[$db] = ['error' => 'Exception during sync: ' . $e->getMessage()];
		}
	}

	// Do our general database cleanups
	wiz_log('Doing database cleanups...');
	do_database_cleanups($campaignIds);

	delete_transient('idemailwiz_blast_sync_in_progress');
	wiz_log("Completed metrics sync process");

	return $response;
}
