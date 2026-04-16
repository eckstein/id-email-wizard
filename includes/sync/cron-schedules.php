<?php
/**
 * Cron interval definitions + scheduling/processor glue for the sync subsystem.
 *
 * Declares custom cron intervals, schedules the blast metrics sync, and wires
 * the engagement-data/queue-fill events to their processors in sync-queue.php.
 */

if (!defined('ABSPATH')) {
	exit;
}

// Add our Sync Sequence custom action for blast metrics
add_action("idemailwiz_process_blast_sync", 'idemailwiz_process_blast_sync', 10);

// Schedule the blast metrics sync to run hourly
function idemailwiz_schedule_blast_sync()
{
	if (!wp_next_scheduled('idemailwiz_process_blast_sync')) {
		wp_schedule_event(time(), 'hourly', 'idemailwiz_process_blast_sync');
	}
}
add_action('wp', 'idemailwiz_schedule_blast_sync');

// Runs the Blast metric sync sequence
function idemailwiz_process_blast_sync()
{
	// Clear expired transients manually before checking anything
	delete_expired_transients();

	$wizSettings = get_option('idemailwiz_settings');

	$blastSyncOn = $wizSettings['iterable_sync_toggle'] ?? 'off';

	$blastSyncInProgress = get_transient('idemailwiz_blast_sync_in_progress');

	// Check for GA sync
	$gaSyncWaiting = get_transient('ga_sync_waiting');
	if (!$gaSyncWaiting) {
		sync_ga_campaign_revenue_data();
		// Sync every 2 hours only
		set_transient('ga_sync_waiting', true, (120 * MINUTE_IN_SECONDS));
	}

	$blastSyncWaiting = get_transient('blast_sync_waiting');
	if ($blastSyncOn && !$blastSyncWaiting && !$blastSyncInProgress) {
		idemailwiz_sync_non_triggered_metrics();
	}
	return true; // Indicate successful completion of the sync sequence
}

// Define the custom cron interval
add_filter('cron_schedules', 'idemailwiz_add_cron_intervals');
function idemailwiz_add_cron_intervals($schedules)
{
	$schedules['every_minute'] = array(
		'interval' => 60,
		'display' => __('Every Minute')
	);
	$schedules['every_three_minutes'] = array(
		'interval' => 180,
		'display' => __('Every 3 Minutes')
	);
	$schedules['every_two_minutes'] = array(
		'interval' => 120,
		'display' => __('Every 2 Minutes')
	);
	$schedules['every_thirty_minutes'] = array(
		'interval' => 30 * 60,
		'display' => __('Every 30 Minutes')
	);
	$schedules['every_two_hours'] = array(
		'interval' => 60 * 60 * 2,
		'display' => __('Every 2 Hours')
	);
	$schedules['every_six_hours'] = array(
		'interval' => 60 * 60 * 6,
		'display' => __('Every 6 Hours')
	);
	$schedules['weekly_monday_morning'] = array(
		'interval' => 604800,
		'display' => __('Every Monday Morning')
	);
	return $schedules;
}




// Schedule the sync process. It will continue until cron is deleted
add_action('wp_loaded', 'idemailwiz_schedule_sync_process');
function idemailwiz_schedule_sync_process()
{
	$wizSettings = get_option('idemailwiz_settings');
	$engSync = $wizSettings['iterable_engagement_data_sync_toggle'] ?? 'off';

	if ($engSync == 'on') {
		if (!wp_next_scheduled('idemailwiz_sync_engagement_data')) {
			if (get_transient('data_sync_in_progress')) {
				wiz_log("Auto-sync triggered, but data sync is already in progress.");
				wp_schedule_single_event(time() + 2 * HOUR_IN_SECONDS, 'idemailwiz_sync_engagement_data');
				sleep(1);
				return;
			};
			wp_schedule_event(time(), 'every_two_hours', 'idemailwiz_sync_engagement_data');

			//wp_schedule_single_event(time(), 'idemailwiz_sync_engagement_data');
		}
	}
}

// Callback function for the sync process event
add_action('idemailwiz_sync_engagement_data', 'idemailwiz_sync_engagement_data_callback');
function idemailwiz_sync_engagement_data_callback()
{
	global $wpdb;
	$sync_jobs_table_name = $wpdb->prefix . 'idemailwiz_sync_jobs';

	// Get the Wiz Settings to check if the sync is turned on
	$wizSettings = get_option('idemailwiz_settings');
	$engSync = $wizSettings['iterable_engagement_data_sync_toggle'] ?? 'off';

	if ($engSync !== 'on') {
		// If the sync is turned off, log a message and clear the scheduled hook
		wiz_log("Engagement data sync is disabled in Wiz Settings. Unscheduling sync events.");
		// Clear both recurring and potential single events
		wp_clear_scheduled_hook('idemailwiz_sync_engagement_data');
		// Maybe reschedule the main hook checker if needed, or rely on settings save/activation
		delete_transient('data_sync_in_progress'); // Ensure transient is cleared
		return;
	}

	// Prevent overlapping runs using a transient
	if (get_transient('idemailwiz_single_job_processor_running')) {
		wiz_log("Single job processor is already running. Skipping this callback.");
		return;
	}
	set_transient('idemailwiz_single_job_processor_running', true, MINUTE_IN_SECONDS * 5); // Lock for 5 minutes max

	// Get the next single ready job (pending or requeued, retryAfter passed)
	$now_pt = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));
	$now_formatted = $now_pt->format('Y-m-d H:i:s');

	$job = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $sync_jobs_table_name 
			 WHERE syncStatus IN ('pending', 'requeued') 
			 AND retryAfter <= %s 
			 ORDER BY syncPriority DESC, retryAfter ASC 
			 LIMIT 1",
			$now_formatted
		),
		ARRAY_A
	);

	if (!empty($job)) {
		// Job found - process it
		wiz_log("Found ready job {$job['jobId']}. Processing...");
		
		// Clear any existing single schedule before processing
		// (Prevents double scheduling if processing is very fast)
		$timestamp = wp_next_scheduled('idemailwiz_sync_engagement_data');
		if ($timestamp && !wp_get_schedule('idemailwiz_sync_engagement_data')) { // Check if it's a single event
			wp_unschedule_event($timestamp, 'idemailwiz_sync_engagement_data');
		}

		try {
			idemailwiz_process_job_from_sync_queue($job['jobId']);
		} catch (Exception $e) {
			 wiz_log("Error processing job {$job['jobId']}: " . $e->getMessage());
			 // Optionally mark the job as failed here if needed
		}

		wp_schedule_single_event(time() + 5, 'idemailwiz_sync_engagement_data');

	} else {

		// Clear any lingering single schedule
		$timestamp = wp_next_scheduled('idemailwiz_sync_engagement_data');
		if ($timestamp && !wp_get_schedule('idemailwiz_sync_engagement_data')) { // Check if it's a single event
			wp_unschedule_event($timestamp, 'idemailwiz_sync_engagement_data');
		}

		// Ensure the main recurring schedule is active
		if (!wp_next_scheduled('idemailwiz_sync_engagement_data')) {
			wiz_log("Recurring schedule not found. Re-adding 'every_two_hours' schedule.");
			wp_schedule_event(time() + (2 * HOUR_IN_SECONDS), 'every_two_hours', 'idemailwiz_sync_engagement_data');
		}
	}

	// Release the lock
	delete_transient('idemailwiz_single_job_processor_running');
}

// Schedule the sync queue cleanup
add_action('idemailwiz_sync_queue_cleanup_event', 'idemailwiz_cleanup_sync_queue');
if (!wp_next_scheduled('idemailwiz_sync_queue_cleanup_event')) {
	wp_schedule_event(time(), 'every_thirty_minutes', 'idemailwiz_sync_queue_cleanup_event');
}

// Schedule the queue fill process, if turned on
add_action('wp_loaded', 'idemailwiz_schedule_queue_fill_process');

function idemailwiz_schedule_queue_fill_process()
{
	$wizSettings = get_option('idemailwiz_settings');
	$engSync = $wizSettings['iterable_engagement_data_sync_toggle'] ?? 'off';

	if ($engSync == 'on') {
		if (!wp_next_scheduled('idemailwiz_fill_sync_queue')) {
			wp_schedule_event(time(), 'twicedaily', 'idemailwiz_fill_sync_queue');
		}
	} else {
		$timestamp = wp_next_scheduled('idemailwiz_fill_sync_queue');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'idemailwiz_fill_sync_queue');
			wiz_log("Engagement data sync fill was deactivated and unscheduled.");
		}
	}
}

add_action('idemailwiz_fill_sync_queue', 'idwiz_export_and_store_jobs_to_sync_queue');
