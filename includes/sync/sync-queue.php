<?php
/**
 * Triggered/engagement data sync queue.
 *
 * Manages the idemailwiz_sync_jobs table: building jobs from Iterable exports,
 * processing them, re-queueing on failure, and cleaning up finished jobs.
 * Also exposes the AJAX handlers for manual triggered syncs.
 */

if (!defined('ABSPATH')) {
	exit;
}

// Ajax handler for manual sync form on single campaign page
add_action('wp_ajax_handle_single_triggered_sync', 'handle_single_triggered_sync');
function handle_single_triggered_sync()
{
	check_ajax_referer('id-general', 'security');

	$campaignId = $_POST['campaignId'];
	$startAt = $_POST['startDate'] ?? null;
	$endAt = $_POST['endDate'] ?? null;
	$metricTypes = ['send', 'open', 'click', 'unSubscribe', 'complaint', 'bounce', 'sendSkip'];

	// Call the maybe_add_to_sync_queue function
	maybe_add_to_sync_queue([$campaignId], $metricTypes, $startAt, $endAt, 100);

	wp_send_json_success('Sync queued successfully.');
}



function maybe_add_to_sync_queue($campaignIds, $metricTypes, $startAt = null, $endAt = null, $priority = 1)
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	foreach ($campaignIds as $campaignId) {
		$campaign = get_idwiz_campaign($campaignId);
		$syncTypes = array_map(function ($metricType) use ($campaign) {
			return strtolower($campaign['type']) . '_' . $metricType . 's';
		}, $metricTypes);

		// Create placeholders for the IN clause
		$placeholders = implode(',', array_fill(0, count($syncTypes), '%s'));
		$existingJobs = $wpdb->get_results(
			call_user_func_array(
				array($wpdb, 'prepare'),
				array_merge(
					array("SELECT syncType FROM $sync_jobs_table_name WHERE campaignId = %d AND syncType IN ($placeholders) AND syncStatus = 'pending'"),
					array($campaignId),
					$syncTypes
				)
			)
		);

		$existingSyncTypes = array_column($existingJobs, 'syncType');
		$newSyncTypes = array_diff($syncTypes, $existingSyncTypes);

		if (!empty($newSyncTypes)) {
			// Add new sync jobs for the missing syncTypes
			idwiz_export_and_store_jobs_to_sync_queue([$campaignId], null, ['Email', 'SMS'], array_map(function ($syncType) {
				return explode('_', rtrim($syncType, 's'))[1];
			}, $newSyncTypes), $startAt, $endAt, $priority);
		}

		if (!empty($existingSyncTypes)) {
			// Update the priority of existing pending jobs
			$wpdb->update(
				$sync_jobs_table_name,
				['syncPriority' => $priority],
				['campaignId' => $campaignId, 'syncType' => $existingSyncTypes, 'syncStatus' => 'pending']
			);
			if ($wpdb->rows_affected) {
				return true;
			}
		}
	}
	return false;
}






function idwiz_export_and_store_jobs_to_sync_queue($campaignIds = null, $campaignTypes = ['Blast', 'Triggered', 'FromWorkflow'], $messageMediums = ['Email', 'SMS'], $metricTypes = ['send', 'open', 'click', 'unSubscribe', 'sendSkip', 'bounce', 'complaint'], $exportStart = null, $exportEnd = null, $priority = 1, $batchSize = 10)
{
	// Clean up the sync queue to get rid of old jobs
	idemailwiz_cleanup_sync_queue();

	$localTimezone = new DateTimeZone('America/Los_Angeles');
	$utcTimezone = new DateTimeZone('UTC');

	if (!$exportStart) {
		$exportStart = new DateTime('2021-11-01', $localTimezone);
	} elseif (is_string($exportStart)) {
		$exportStart = DateTime::createFromFormat('Y-m-d', $exportStart, $localTimezone);
	}

	if (!$exportEnd) {
		$exportEnd = new DateTime('tomorrow', $localTimezone);
	} elseif (is_string($exportEnd)) {
		$exportEnd = DateTime::createFromFormat('Y-m-d', $exportEnd, $localTimezone);
		$exportEnd->setTime(23, 59, 59); // Set to end of day
	}

	// Convert to UTC and format
	$exportStart->setTimezone($utcTimezone);
	$exportEnd->setTimezone($utcTimezone);

	$exportStartFormatted = $exportStart->format('Y-m-d H:i:s');
	$exportEndFormatted = $exportEnd->format('Y-m-d H:i:s');

	// If campaignIds are provided, override the campaignTypes
	if (!empty($campaignIds)) {
		$campaignIds = array_unique($campaignIds);
		$campaignTypes = [];
	}

	// Get the appropriate campaigns based on the provided parameters or default criteria
	$campaigns = get_campaigns_to_sync($campaignIds, $campaignTypes, $messageMediums);

	if (empty($campaigns)) {
		wiz_log('No campaigns found to export jobs for.');
		// Cancel next scheduled event and reschedule for 30 minutes later
		wp_clear_scheduled_hook('idemailwiz_fill_sync_queue');
		wp_schedule_single_event(time() + 30 * MINUTE_IN_SECONDS, 'idemailwiz_fill_sync_queue');
		return;
	}

	// Process campaigns in batches
	$campaignBatches = array_chunk($campaigns, $batchSize);
	$totalBatches = count($campaignBatches);

	// Schedule the first batch processing event
	wp_schedule_single_event(time(), 'idemailwiz_process_campaign_export_batch', [$campaignBatches, 0, $totalBatches, $metricTypes, $exportStartFormatted, $exportEndFormatted, $priority]);
}

add_action('idemailwiz_process_campaign_export_batch', 'idemailwiz_process_campaign_export_batch', 10, 7);
function idemailwiz_process_campaign_export_batch($campaignBatches, $currentBatch, $totalBatches, $metricTypes, $exportStart, $exportEnd, $priority)
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	$campaignBatch = $campaignBatches[$currentBatch];
	wiz_log("Processing batch " . ($currentBatch + 1) . " of $totalBatches...");

	foreach ($campaignBatch as $campaign) {
		$campaignId = $campaign['id'];
		$messageMedium = strtolower($campaign['messageMedium']);
		$campaignType = strtolower($campaign['type']);

		// Check for existing send records for this campaign
		$campaignSends = get_engagement_data_by_campaign_id($campaignId, $campaignType, 'send');

		foreach ($metricTypes as $metricType) {			

			// If sends already exist, we skip them (plus bounced and sendSkips)
			if ($campaignSends) {

				// Skip blast campaign send, sendSkip, and bounce records if the campaign's end time is more than 1 day ago
				// Prevents exporting unneccesary and/or huge jobs from Iterable
				$campaignEnd = $campaign['endedAt'];
				$campaignEndTimestamp = floor($campaignEnd / 1000);  // convert milliseconds to seconds

				if (
					($campaignType == 'blast')
					&& (in_array($metricType, ['send', 'bounce', 'sendSkip']))
					&& ($messageMedium == 'email')
					&& ($campaignEndTimestamp < strtotime('-1 day'))
					&& ($priority == 1) // higher priorities indicate manual syncs
				) {
					continue;  // skip processing
				}
			}

			// For SMS we only have sends and clicks
			if (($messageMedium == 'sms') && !in_array($metricType, ['send', 'click'])) {
				continue;
			}

			// Build syncType name to match database name
			$syncType = $campaignType . '_' . strtolower($metricType) . 's'; // matches database name after $wpdb->prefix . 'idemailwiz_'

			// Check if a queued job already exists for this campaign and metric type
			$existingJob = $wpdb->get_row("SELECT * FROM $sync_jobs_table_name WHERE campaignId = $campaignId AND syncType = '$syncType'");
			if ($existingJob) {
				continue;
			}

			// Schedule a single cron job to export data and add a row to the queue
			wp_schedule_single_event(time() + 1, 'idemailwiz_export_and_queue_single_job_event', [
				$campaignId, $messageMedium, $metricType, $syncType, $exportStart, $exportEnd, $priority
			]);
		}
	}

	// If there are more batches to process, schedule the next batch processing event
	if ($currentBatch + 1 < $totalBatches) {
		wp_schedule_single_event(time(), 'idemailwiz_process_campaign_export_batch', [$campaignBatches, $currentBatch + 1, $totalBatches, $metricTypes, $exportStart, $exportEnd, $priority]);
	} else {
		// If all batches have been processed, schedule the sync process
		wiz_log("Batches processing complete, sync starting in 30 seconds...");

		// Check for upcoming sync event in next 30 seconds, and if later or not scheduled, schedule it
		$nextSyncCheck = wp_next_scheduled('idemailwiz_sync_engagement_data');
		if ($nextSyncCheck && $nextSyncCheck > time() + 30) {
			wp_unschedule_event($nextSyncCheck, 'idemailwiz_sync_engagement_data');
			wp_schedule_single_event(time() + 30, 'idemailwiz_sync_engagement_data');
		}
	}
}


add_action('idemailwiz_export_and_queue_single_job_event', 'idemailwiz_export_and_queue_single_job', 10, 7);
// Callback function for the single cron job
function idemailwiz_export_and_queue_single_job($campaignId, $messageMedium, $metricType, $syncType, $exportStart, $exportEnd, $priority = 1)
{
	$apiData['url'] = "https://api.iterable.com/api/export/start";

	// Build dataTypeName in the format {messageMedium}{MetricType}
	$dataTypeName = $messageMedium . ucfirst($metricType);

	$apiData['args'] = [
		"outputFormat" => "application/x-json-stream",
		"dataTypeName" => $dataTypeName,
		"delimiter" => ",",
		"onlyFields" => "createdAt,userId,campaignId,templateId,messageId,email",
		"campaignId" => (int) $campaignId,
		"startDateTime" => $exportStart,
		"endDateTime" => $exportEnd,
	];

	try {
		$scheduledJob = idemailwiz_iterable_curl_call($apiData['url'], $apiData['args'], 'POST');
		//$scheduledJob = idemailwiz_iterable_curl_call($apiData['url'], $apiData['args']);
		if (!isset($scheduledJob['response']['jobId'])) {
			throw new Exception("No job ID received from API.");
		}
		idemailwiz_add_sync_queue_row(
			$scheduledJob,
			$campaignId,
			$syncType,
			$priority
		);
	} catch (Exception $e) {
		wiz_log("Error starting export job for campaign $campaignId: " . $e->getMessage());
		return;
	}
}



function idemailwiz_add_sync_queue_row($scheduledJob, $campaignId, $syncType, $priority = 1, $retries = 3)
{
	global $wpdb;

	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	if (isset($scheduledJob['response']['jobId'])) {
		$iterableJobId = $scheduledJob['response']['jobId'];

		// Create a DateTime object representing the current time
		$currentTime = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));

		$deleteAfter = $currentTime->modify('+ 12 hours');
		$retryAfter = $currentTime->modify('+1 hour');

		$deleteAfter = $deleteAfter->format('Y-m-d H:i:s');

		if ($priority > 1) {
			$retryAfter = $currentTime->format('Y-m-d H:i:s');
		} else {
			$retryAfter = $retryAfter;
		}

		$retry = 0;
		while ($retry < $retries) {
			try {
				// Start a transaction
				$wpdb->query('START TRANSACTION');

				// Insert a new row into the sync queue
				$result = $wpdb->insert($sync_jobs_table_name, [
					'jobId' => $iterableJobId,
					'campaignId' => $campaignId,
					'jobState' => '',
					'retryAfter' => $retryAfter,
					'deleteAfter' => $deleteAfter,
					'syncType' => $syncType,
					'syncStatus' => 'pending',
					'syncPriority' => $priority,
				]);

				if ($result === false) {
					// If the insertion fails, roll back the transaction
					$wpdb->query('ROLLBACK');
					throw new Exception("Error inserting sync queue row for jobId: $iterableJobId. Error: " . $wpdb->last_error);
				}

				// If the insertion is successful, commit the transaction
				$wpdb->query('COMMIT');
				return $result;
			} catch (Exception $e) {
				// Log the error
				wiz_log("Error inserting sync queue row (retry $retry): " . $e->getMessage());
				// Roll back the transaction if an exception occurs
				$wpdb->query('ROLLBACK');

				$retry++;
				if ($retry < $retries) {
					// Delay before retrying (adjust the delay as needed)
					usleep(100000); // Sleep for 100 milliseconds
				}
			}
		}

		// If all retries have been exhausted, log an error
		wiz_log("Failed to insert sync queue row after $retries retries for jobId: $iterableJobId");
	}

	return false;
}




function idemailwiz_process_job_from_sync_queue($jobId = null)
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	if (!$jobId) {
		$job = $wpdb->get_row("SELECT * FROM $sync_jobs_table_name WHERE syncStatus = 'pending' ORDER BY syncPriority ASC, retryAfter ASC LIMIT 1", ARRAY_A);
	} else {
		$job = $wpdb->get_row("SELECT * FROM $sync_jobs_table_name WHERE jobId = $jobId LIMIT 1", ARRAY_A);
	}

	if (empty($job)) {
		wiz_log("Could not find job in queue with id $jobId");
		// Add return here if job is not found
		return;
	}

	$retryAfter = new DateTimeImmutable($job['retryAfter'], new DateTimeZone('America/Los_Angeles'));

	$updateSyncing = $wpdb->update(
		$sync_jobs_table_name,
		['syncStatus' => 'syncing', 'retryAfter' => $retryAfter->format('Y-m-d H:i:s')],
		['jobId' => $jobId]
	);

	if ($updateSyncing === false) {
		wiz_log("Error updating sync status to syncing for job $jobId");
		return;
	}

	$jobId = $job['jobId'];
	$startAfter = $job['startAfter'] ? '?startAfter=' . $job['startAfter'] : '';
	$table_name = $wpdb->prefix . 'idemailwiz_' . $job['syncType'];

	// Wrap the API call in a try-catch block
	try {
		$jobApiResponse = idemailwiz_iterable_curl_call("https://api.iterable.com/api/export/" . $jobId . "/files{$startAfter}");
		if (!isset($jobApiResponse['response'])) {
			wiz_log("Job $jobId: 'response' key not set in API response.");
		}
	} catch (Exception $e) {
		wiz_log("Job $jobId: Exception during API call: " . $e->getMessage() . ". Requeueing job.");
		// Set job status back to pending and schedule retry
		$retryTimestamp = date('Y-m-d H:i:s', strtotime('+15 minutes')); // Retry in 15 minutes
		$wpdb->update(
			$sync_jobs_table_name,
			['syncStatus' => 'pending', 'retryAfter' => $retryTimestamp],
			['jobId' => $jobId]
		);
		return; // Exit processing for this job
	}

	// --- Add more robust checks after the API call --- 
	// Ensure the response variable is set and is an array
	if (!isset($jobApiResponse) || !is_array($jobApiResponse)) {
		wiz_log("Job $jobId: API Response variable is not set or not an array after call. Requeueing.");
		$wpdb->update($sync_jobs_table_name, ['syncStatus' => 'pending', 'retryAfter' => date('Y-m-d H:i:s', strtotime('+10 minutes'))], ['jobId' => $jobId]);
		return;
	}

	// Ensure the HTTP status code is set and is 200
	if (!isset($jobApiResponse['http_code']) || $jobApiResponse['http_code'] !== 200) {
		$loggedCode = isset($jobApiResponse['http_code']) ? $jobApiResponse['http_code'] : 'Not Set';
		wiz_log("Job $jobId: API call did not return HTTP 200 (Actual: $loggedCode). Requeueing.");
		$wpdb->update($sync_jobs_table_name, ['syncStatus' => 'pending', 'retryAfter' => date('Y-m-d H:i:s', strtotime('+10 minutes'))], ['jobId' => $jobId]);
		return; 
	}

	if (!isset($jobApiResponse['response']['jobState'])) {
		wiz_log("Job $jobId: API response structure missing 'jobState'. Requeueing.");
		$wpdb->update($sync_jobs_table_name, ['syncStatus' => 'pending', 'retryAfter' => date('Y-m-d H:i:s', strtotime('+10 minutes'))], ['jobId' => $jobId]);
		return; 
	}
	
	// --- Checks passed, proceed with job state logic --- 

	$batchInsertData = [];
	$lastProcessedFile = '';
	$insertBatchSize = 1000; // Define batch size for inserts
	$recordsInCurrentBatch = 0;
	$totalInsertedCount = 0;

	// Check jobState before attempting to process files
	if ($jobApiResponse['response']['jobState'] !== 'completed') {
		wiz_log("Job $jobId: Iterable job state is not 'completed' (State: " . $jobApiResponse['response']['jobState'] . "). Requeueing.");
		// Create a DateTime object representing the current time
		$currentTime = new DateTime('now', new DateTimeZone('UTC'));

		// Add 5 minutes to the current time
		$currentTime->modify('+5 minutes');

		// Set the timezone to Pacific Time (PT)
		$currentTime->setTimezone(new DateTimeZone('America/Los_Angeles'));

		// Format the time in a suitable format
		$retryAfter = $currentTime->format('Y-m-d H:i:s');

		// Update the database with the retryAfter timestamp
		$updatRetryAfter = $wpdb->update(
			$sync_jobs_table_name,
			[
				'retryAfter' => $retryAfter,
			],
			[
				'jobId' => $jobId,
			]
		);
		if ($updatRetryAfter === false) {
			wiz_log("Error updating retryAfter timestamp for job $jobId. Error: " . $wpdb->last_error);
		}

		// Change status back to pending
		$updatePending = $wpdb->update(
			$sync_jobs_table_name,
			['syncStatus' => 'pending'],
			['jobId' => $jobId]
		);
		if ($updatePending === false) {
			wiz_log("Error updating sync status back to pending for requeued job $jobId");
		}

		return; // Exit processing since the job is not completed
	}


	// --- Job is completed, proceed with file processing ---
	foreach ($jobApiResponse['response']['files'] as $file) {
		$jsonResponse = @file_get_contents($file['url']);
		if ($jsonResponse === false) {
			wiz_log("Failed to retrieve file content for job $jobId. File URL: " . $file['url']);
			continue;
		}

		$lines = explode("\n", $jsonResponse);
		if (!empty($lines) && (count(array_filter($lines)) > 0)) {
			foreach ($lines as $line) {
				if (trim($line) === '') {
					continue;
				}
				$record = json_decode($line, true);
				if (json_last_error() !== JSON_ERROR_NONE) {
					wiz_log("Failed to decode JSON for job $jobId. Line: $line. Error: " . json_last_error_msg());
					continue;
				}

				if (!is_array($record) || empty($record) || !isset($record['messageId'])) {
					continue;
				}

				$createdAt = new DateTime($record['createdAt'], new DateTimeZone('UTC'));

				$msTimestamp = (int) ($createdAt->format('U.u') * 1000);

				// Prepare data for insertion
				$query_args = array_merge(
					["(%s, %s, %d, %d, %d)"],
					[
						$record['messageId'],
						$record['userId'] ?? null,
						$record['campaignId'] ?? null,
						$record['templateId'] ?? null,
						$msTimestamp,
					]
				);
				$batchInsertData[] = call_user_func_array([$wpdb, 'prepare'], $query_args);
				$recordsInCurrentBatch++;

				// Insert batch if size limit reached
				if ($recordsInCurrentBatch >= $insertBatchSize) {
					$columns = ['messageId', 'userId', 'campaignId', 'templateId', 'startAt'];
					$placeholders = implode(", ", $batchInsertData);
					$insertQuery = "INSERT IGNORE INTO $table_name (" . implode(", ", $columns) . ") VALUES " . $placeholders;
					$insertResult = $wpdb->query($insertQuery);
					
					if ($insertResult === false) {
						wiz_log("Job $jobId: Error inserting batch records. Error: " . $wpdb->last_error);
					} else {
						$totalInsertedCount += $insertResult; 
					}
					// Reset batch
					$batchInsertData = [];
					$recordsInCurrentBatch = 0;
				}
			}
		} else {
			wiz_log("Downloaded file is empty for job $jobId.");
			continue;
		}

		$lastProcessedFile = $file['file'];

		//Update lastWizSync in campaigns database
		$campaignId = $job['campaignId'];
		$campaign = get_idwiz_campaign($campaignId);
		if ($campaign) {
			$updateResult = $wpdb->update(
				$wpdb->prefix . 'idemailwiz_campaigns',
				[
					'lastWizSync' => date('Y-m-d H:i:s'),
				],
				[
					'id' => $campaignId,
				]
			);
			if ($updateResult === false) {
				wiz_log("Error updating lastWizSync for campaign $campaignId in job $jobId. Error: " . $wpdb->last_error);
			}
		}
	}

	// Insert any remaining records in the last batch
	if (!empty($batchInsertData)) {
		$columns = ['messageId', 'userId', 'campaignId', 'templateId', 'startAt'];
		$placeholders = implode(", ", $batchInsertData);
		$insertQuery = "INSERT IGNORE INTO $table_name (" . implode(", ", $columns) . ") VALUES " . $placeholders;
		$insertResult = $wpdb->query($insertQuery);

		if ($insertResult === false) {
			wiz_log("Job $jobId: Error inserting final batch records. Error: " . $wpdb->last_error);
		} else {
			$totalInsertedCount += $insertResult;
		}
	}

	// Log total inserted count for the job
	wiz_log("Job $jobId: Finished processing. Total records inserted/updated: $totalInsertedCount");

	if ($jobApiResponse['response']['exportTruncated'] === true && (!empty($lines) && (count(array_filter($lines)) > 0))) {
		// Update the row to reflect the requeued job
		$updateRequeued = $wpdb->update(
			$sync_jobs_table_name,
			['startAfter' => $lastProcessedFile, 'syncStatus'  => 'requeued', 'retryAfter' => current_time('mysql')],
			['jobId' => $jobId]
		);
		if ($updateRequeued === false) {
			wiz_log("Error updating the requeue job status for job $jobId. Error: " . $wpdb->last_error);
		} else {
			wiz_log("Export truncated for job $jobId. Requeueing..");
		}
	} else {

		// Mark job as finished and schedule deleteAfter for 1 hour later
		$deleteAfter = date('Y-m-d H:i:s', strtotime('1 hour', current_time('timestamp')));
		$updateFinished = $wpdb->update(
			$sync_jobs_table_name,
			['startAfter' => null, 'syncStatus' => 'finished', 'deleteAfter' => $deleteAfter],
			['jobId' => $jobId]
		);
		if ($updateFinished === false) {
			wiz_log("Error marking job as finished for job $jobId. Error: " . $wpdb->last_error);
		}
	}

	// --- Update campaign startAt based on latest send --- 
	$latestSendTimestamp = null; // Changed from earliest
	$isSendJob = (strpos($job['syncType'], '_sends') !== false); // Check if it's a send job

	if ($isSendJob && !empty($batchInsertData)) { // Only proceed if it was a send job with data processed
		// Re-process lines to find max timestamp
		foreach ($jobApiResponse['response']['files'] as $file) {
			$jsonResponse = @file_get_contents($file['url']);
			if ($jsonResponse === false) continue;
			$lines = explode("\n", $jsonResponse);
			foreach ($lines as $line) {
				if (trim($line) === '') continue;
				$record = json_decode($line, true);
				if (json_last_error() !== JSON_ERROR_NONE || !is_array($record) || !isset($record['createdAt'])) continue;

				$createdAt = new DateTime($record['createdAt'], new DateTimeZone('UTC'));
				$msTimestamp = (int) ($createdAt->format('U.u') * 1000);

				if ($latestSendTimestamp === null || $msTimestamp > $latestSendTimestamp) { // Changed comparison to >
					$latestSendTimestamp = $msTimestamp; // Changed variable name
				}
			}
		}
	}

	if ($latestSendTimestamp !== null && $jobApiResponse['response']['exportTruncated'] !== true) {
		$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
		$campaignId = $job['campaignId'];
		$existingCampaign = $wpdb->get_row($wpdb->prepare("SELECT startAt FROM $campaigns_table WHERE id = %d", $campaignId), ARRAY_A);

		if ($existingCampaign && ($existingCampaign['startAt'] === null || $latestSendTimestamp > $existingCampaign['startAt'])) { // Changed comparison to >
			wiz_log("Job $jobId: Updating startAt for campaign $campaignId to latest send time: $latestSendTimestamp"); // Changed log message
			$updateResult = $wpdb->update(
				$campaigns_table,
				['startAt' => $latestSendTimestamp], // Changed variable name
				['id' => $campaignId]
			);
			if ($updateResult === false) {
				 wiz_log("Job $jobId: Error updating startAt for campaign $campaignId: " . $wpdb->last_error);
			}
		} elseif (!$existingCampaign) {
			 wiz_log("Job $jobId: Could not find campaign $campaignId in campaigns table to update startAt.");
		}
	}
	// --- End campaign startAt update --- 
}


function get_campaigns_to_sync(
	$campaignIds = null,
	$campaignTypes = ['Blast', 'Triggered', 'FromWorkflow'],
	$messageMediums = ['Email', 'SMS']
) {

	$campaigns = [];
	$triggeredCampaigns = [];
	$blastCampaigns = [];

	if (!empty($campaignIds)) {
		$args = [
			'campaignIds' => $campaignIds,
		];

		$campaigns = get_idwiz_campaigns($args);
	} else {
		$args = [
			'messageMedium' => $messageMediums,
		];
		if (in_array('Triggered', $campaignTypes) || in_array('FromWorkflow', $campaignTypes)) {
			$args = [
				'type' => ['Triggered', 'FromWorkflow'],
				'campaignState' => 'Running',
			];

			$triggeredCampaigns = get_idwiz_campaigns($args);
		}

		if (in_array('Blast', $campaignTypes)) {

			// Sync blast campaign data for max 7 days
			$blastStartDate = date('Y-m-d', strtotime('-7 days'));

			$args = [
				'type' => 'Blast',
				'campaignState' => 'Finished',
				'startAt_start' => $blastStartDate
			];

			$blastCampaigns = get_idwiz_campaigns($args);
		}
		$campaigns = array_merge($blastCampaigns, $triggeredCampaigns);
	}

	return $campaigns;
}

// Function to clean up old sync jobs from the queue
function idemailwiz_cleanup_sync_queue()
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	$currentTime = current_time('mysql');

	$wpdb->query("DELETE FROM $sync_jobs_table_name WHERE deleteAfter <= '$currentTime'");
}

function get_idwiz_sync_jobs($syncStatus = 'pending', $campaignId = null)
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';
	if ($campaignId) {
		$campaignId = (int) $campaignId;
		$jobs = $wpdb->get_results("SELECT * FROM $sync_jobs_table_name WHERE syncStatus = '$syncStatus' AND campaignId = $campaignId", ARRAY_A);
	} else {
		$jobs = $wpdb->get_results("SELECT * FROM $sync_jobs_table_name WHERE syncStatus = '$syncStatus'", ARRAY_A);
	}

	return $jobs;
}

// Ajax handler for manual sync form on sync station page
add_action('wp_ajax_handle_sync_station_sync', 'handle_sync_station_sync');
function handle_sync_station_sync()
{
	check_ajax_referer('id-general', 'security');

	// Extract the form fields from the POST data
	parse_str($_POST['formFields'], $formFields);

	

	// Check for sync types
	if (empty($formFields['syncTypes'])) {
		wp_send_json_error('No sync types were received.');
		return;
	}

	// Check for dates
	$syncStartAt = $formFields['startAt'] ?? null;
	$syncEndAt = $formFields['endAt'] ?? null;

	// If campaigns are not sent, decide which ones to sync based on dates sent
	if (empty($formFields['campaignIds'])) {

		$campaigns = get_idwiz_campaigns(['type' => 'Blast', 'fields' => 'id', 'startAt_start' => $syncStartAt, 'startAt_end' => $syncEndAt ?? date('Y-m-d')]);

		$campaignIds = array_column($campaigns, 'id');
	} else {
		// Extract campaign IDs, if provided
		$campaignIds = explode(',', $formFields['campaignIds']);
	}

	// wp_send_json_success($campaignIds);
	// return; 

	idemailwiz_cleanup_sync_queue();

	// Initiate the sync sequence
	if (in_array('blastMetrics', $formFields['syncTypes'])) {
		$blastSyncInProgress = get_transient('idemailwiz_blast_sync_in_progress');
		if ($blastSyncInProgress) {
			$syncResult = ['error' => 'Another sync is already in progress!'];
		} else {
			$syncResult = idemailwiz_sync_non_triggered_metrics($campaignIds);
		}
		unset($formFields['syncTypes'][array_search('blastMetrics', $formFields['syncTypes'])]);
	} else {
		$syncResult = maybe_add_to_sync_queue($campaignIds, $formFields['syncTypes'], $syncStartAt, $syncEndAt, 100);
	}


	if ($syncResult === false || isset($syncResult['error'])) {
		wp_send_json_error('Sync sequence aborted: Another sync is still in progress or there was an error.');
	} else {
		wp_send_json_success('Sync sequence successfully initiated.');
	}
}

//requeue_retry_afters();
function requeue_retry_afters()
{
	global $wpdb;

	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';
	// Set any 'syncing' jobs back to 'pending'
	$wpdb->update($sync_jobs_table_name, [
		'syncStatus' => 'pending',
	], [
		'syncStatus' => 'syncing',
	]);

	// Delete the idemailwiz_blast_sync_in_progress transiet
	delete_transient('idemailwiz_blast_sync_in_progress');
	
	// Set retryAfter to right now for all pending jobs
	$currentTime = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));
	$result = $wpdb->update($sync_jobs_table_name, [
		'retryAfter' => $currentTime->format('Y-m-d H:i:s'),
	], [
		'syncStatus' => 'pending',
	]);

	wp_schedule_single_event(time(), 'idemailwiz_sync_engagement_data');

	return $result;
}
