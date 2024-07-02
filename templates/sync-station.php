<?php
get_header();

global $wpdb;

if (isset($_GET['db-cleanup'])) {
	$doCleanup = $_GET['db-cleanup'];
	if ($doCleanup == 'update-null-user-ids') {
		update_null_user_ids();
	} else if ($doCleanup == 'update-missing-purchase-dates') {
		update_missing_purchase_dates();
	} else if ($doCleanup == 'clean-campaign-ids') {
		remove_zero_campaign_ids();
	} else if ($doCleanup == 'backfill-purchase-campaign-dates') {
		idemailwiz_backfill_campaign_start_dates();
	} else if ($doCleanup == 'cleanup-users-database') {
		idwiz_cleanup_users_database();
	} else if ($doCleanup == 'fix-triggered-timestamps') {
		updateTimestampsToMilliseconds();
	} else if ($doCleanup == 'requeue-retries') {
		requeue_retry_afters();
	} else if ($doCleanup == 'backfill-blast-data') {
		backfill_blast_engagment_data();
	}
}

if (isset($_GET['sync'])) {
	if ($_GET['sync'] == 'users') {
		$syncUsersStart = $_GET['syncUsersStart'] ?? null;
		$syncUsersEnd = $_GET['syncUsersEnd'] ?? null;
		if ($syncUsersStart && $syncUsersEnd) {
			idemailwiz_sync_users($syncUsersStart, $syncUsersEnd);
		} else {
			wiz_log('No start or end date provided for syncing users');
		}
	} else if ($_GET['sync'] == 'purchases') {
		$syncPurchasesStart = $_GET['syncPurchasesStart'] ?? null;
		$syncPurchasesEnd = $_GET['syncPurchasesEnd'] ?? null;
		idemailwiz_sync_purchases(null, $syncPurchasesStart, $syncPurchasesEnd);
	} else if ($_GET['sync'] == 'metrics') {
		$syncMetricsStart = $_GET['syncMetricsStart'] ?? null;
		$syncMetricsEnd = $_GET['syncMetricsEnd'] ?? null;
		$campaigns = get_idwiz_campaigns(['startAt_start' => $syncMetricsStart, 'startAt_end' => $syncMetricsEnd, 'type' => 'Blast']);
		idemailwiz_sync_metrics(array_column($campaigns, 'id'));
	} else if ($_GET['sync'] == 'hourlyOpensClicks') {
		$syncHourlyStart = $_GET['syncHourlyStart'] ?? null;
		$syncHourlyEnd = $_GET['syncHourlyEnd'] ?? null;
		$campaigns = get_idwiz_campaigns(['startAt_start' => $syncHourlyStart, 'startAt_end' => $syncHourlyEnd, 'type' => 'Blast']);
		update_opens_and_clicks_by_hour($campaigns);
	}
}

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="wizHeader">
		<h1 class="wizEntry-title" itemprop="name">Sync Station</h1>
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">

			</div>
			<div class="wizHeader-right">
				<!-- Additional header actions if needed -->
			</div>
		</div>
	</header>

	<div class="entry-content" itemprop="mainContentOfPage">
		<?php //print_r(idemailwiz_fetch_purchases(['8328396']));     
		?>
		<div class="wizcampaign-sections-row">
			<!-- Sync Form -->
			<div class="wizcampaign-section inset">
				<div class="wizcampaign-section">
					<h2>Manual Sync Controls</h2>
					<form id="syncStationForm" method="post">
						<fieldset class="syncTypes blast">
							<legend>Sync Blast Metrics:</legend>
							<?php echo "<label><input type='checkbox' name='syncTypes[]' value='blastMetrics'>Blast Metrics</label>"; ?>
						</fieldset>
						<fieldset class="syncTypes triggered">
							<legend>Sync Engagement Data:</legend>
							<?php
							$syncTypes = ['Sends' => 'send', 'Opens' => 'open', 'Clicks' => 'click', 'Unsubscribes' => 'unSubscribe', 'Bounces' => 'bounce', 'Complaints' => 'complaint', 'SendSkips' => 'sendSkip'];
							foreach ($syncTypes as $label => $type) {
								echo "<label><input type='checkbox' name='syncTypes[]' value='$type'> $label</label>";
							}
							?>

						</fieldset>

						<fieldset id="syncStation-syncCampaigns">
							<legend>Sync specific campaigns <br /></legend>
							<label for="campaignIds">Campaign IDs (comma-separated):</label><br />
							<textarea id="campaignIds" name="campaignIds"></textarea>
						</fieldset>

						<fieldset id="syncStation-syncByDate">
							<legend>Sync by date (for engagement metrics only)<br /></legend>
							<input type="date" name="startAt" value="" /> thru <input type="date" name="endAt" value="" />
						</fieldset>

						<input type="submit" class="wiz-button green" value="Initiate Sync">

						<?php
						// Check if a sync is already in progress
						$overlayClass = '';
						if (get_transient('idemailwiz_sync_in_progress')) {
							$overlayClass = 'active';
						}
						?>
						<div class="syncForm-overlay <?php echo $overlayClass; ?>">
							<div class="syncForm-overlayContent">Sync in progress...</div>
						</div>
					</form>
				</div>
				<div class="wizcampaign-section">
					<form id="syncUsersByDateForm" method="get">
						<input type="hidden" name="sync" value="users" />
						<input type="date" name="syncUsersStart" id="syncUsersStart" value="<?php echo $syncUsersStart ?? date('Y-m-d', strtotime('-3 days')); ?>" />
						<input type="date" name="syncUsersEnd" id="syncUsersStart" value="<?php echo $syncUsersEnd ?? date('Y-m-d'); ?>" />
						<!-- submit $_GET with start and end date for users-->
						<input type="submit" class="wiz-button green" value="Sync Users" />
					</form>
				</div>
				<div class="wizcampaign-section">
					<form id="syncPurchasesByDate" method="get">
						<input type="hidden" name="sync" value="purchases" />
						<input type="date" name="syncPurchasesStart" id="syncPurchasesStart" value="<?php echo $syncPurchasesStart ?? date('Y-m-d', strtotime('-3 days')); ?>" />
						<input type="date" name="syncPurchasesEnd" id="syncPurchasesEnd" value="<?php echo $syncPurchasesEnd ?? date('Y-m-d'); ?>" />
						<!-- submit $_GET with start and end date for users-->
						<input type="submit" class="wiz-button green" value="Sync Purchases" />
					</form>
				</div>
				<div class="wizcampaign-section">
					<form id="syncMetricsByDate" method="get">
						<input type="hidden" name="sync" value="metrics" />
						<input type="date" name="syncMetricsStart" id="syncMetricsStart" value="<?php echo $syncMetricsStart ?? date('Y-m-d', strtotime('-3 days')); ?>" />
						<input type="date" name="syncMetricsEnd" id="syncMetricsEnd" value="<?php echo $syncMetricsEnd ?? date('Y-m-d'); ?>" />
						<!-- submit $_GET with start and end date for users-->
						<input type="submit" class="wiz-button green" value="Sync Metrics" />
					</form>
				</div>
				<div class="wizcampaign-section">
					<form id="syncHourlyClicksOpens" method="get">
						<input type="hidden" name="sync" value="hourlyOpensClicks" />
						<input type="date" name="syncHourlyStart" id="syncHourlyStart" value="<?php echo $syncHourlyStart ?? date('Y-m-d', strtotime('-3 days')); ?>" />
						<input type="date" name="syncHourlyEnd" id="syncHourlyEnd" value="<?php echo $syncHourlyEnd ?? date('Y-m-d'); ?>" />
						<!-- submit $_GET with start and end date for users-->
						<input type="submit" class="wiz-button green" value="Sync Hourly Opens & Clicks" />
					</form>
				</div>
				<div class="wizcampaign-section">
					<h2>Database Cleanup Operations</h2>
					<div class="wizcampaign-sections-row">

						<div class="wizcampaign-section">
							<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'requeue-retries'); ?>" id="requeueRetries">Re-queue retries</a>
							<h5>Sets all pending sync jobs to be retried after right now.</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'update-null-user-ids'); ?>" id="updateNullUserIds">Match Purchase User IDs</a>
							<h5>Fills null userIds in the purchases database by matching accountNumber to other
								purchases</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'update-missing-purchase-dates'); ?>" id="updateMissingPurchaseDates">Update Missing Purchase
								Dates</a>
							<h5>Fill in PurchaseDate where missing using the createdAt date.</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'clean-campaign-ids'); ?>" id="removeZeroCampaignIds">Clean Campaign IDs</a>
							<h5>Remove campaignIds with a value of "0" (updates value to null).</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'backfill-purchase-campaign-dates'); ?>" id="backfillPurchaseCampaignDates">Backfill Purchase Campaign Send Dates</a>
							<h5>At the startAt date/time to each purchase, if missing.</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'cleanup-users-database'); ?>" id="cleanupUsersDatabase">Cleanup Users Database</a>
							<h5>Removes empty arrays and blank values. Converts array to properly serialized data</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'fix-triggered-timestamps'); ?>" id="fixTriggeredTimestamps">Fix Triggered Timestamps</a>
							<h5>Converts timestamps in seconds to milliseconds, where needed.</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'backfill-blast-data'); ?>" id="backfullBlastData">Backfill Blast Data</a>
							<h5>Backfill engagement data for all blast campaigns</h5>
						</div>
					</div>
				</div>
			</div>
			<!-- Sync Log Section -->
			<div class="wizcampaign-section inset" id="sync-log-panel">
				<h2>Sync Log</h2>
				<pre id="syncLogContent"><code><?php echo get_wiz_log(250); ?></code></pre>
			</div>

		</div>

	</div>
</article>

<?php get_footer(); ?>