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
	} else if ($doCleanup == 'update-course-fiscal-years') {
		updateCourseFiscalYears();
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
				<!-- Header left content if needed -->
			</div>
			<div class="wizHeader-right">
				<!-- Header right content if needed -->
			</div>
		</div>
	</header>

	<div class="entry-content" itemprop="mainContentOfPage">
		<div class="wizcampaign-sections-row">
			
			<!-- Main Control Panel -->
			<div class="wizcampaign-section inset" style="flex: 1;">
				<!-- Manual Sync Controls -->
				<div class="wizcampaign-section">
					<h2><i class="fa-solid fa-sync"></i> Manual Sync Controls</h2>
					
					<!-- Targeted Sync Section -->
					<div class="wizcampaign-section card">
						<h3>Targeted Sync Operations</h3>
						<form id="syncStationForm" method="post">
							<div class="sync-form-grid">
								<div class="sync-form-column">
									<fieldset class="syncTypes blast">
										<legend>Sync Blast Metrics:</legend>
										<?php echo "<label><input type='checkbox' name='syncTypes[]' value='blastMetrics'>Blast Metrics</label>"; ?>
									</fieldset>
									
									<fieldset class="syncTypes triggered">
										<legend>Sync Engagement Data:</legend>
										<div class="checkbox-grid">
											<?php
											$syncTypes = ['Sends' => 'send', 'Opens' => 'open', 'Clicks' => 'click', 'Unsubscribes' => 'unSubscribe', 'Bounces' => 'bounce', 'Complaints' => 'complaint', 'SendSkips' => 'sendSkip'];
											foreach ($syncTypes as $label => $type) {
												echo "<label><input type='checkbox' name='syncTypes[]' value='$type'> $label</label>";
											}
											?>
										</div>
									</fieldset>
								</div>
								
								<div class="sync-form-column">
									<fieldset id="syncStation-syncCampaigns">
										<legend>Sync specific campaigns</legend>
										<label for="campaignIds">Campaign IDs (comma-separated):</label>
										<textarea id="campaignIds" name="campaignIds"></textarea>
									</fieldset>
									
									<fieldset id="syncStation-syncByDate">
										<legend>Sync by date (for engagement metrics only)</legend>
										<div class="date-range-inputs">
											<div class="date-input-group">
												<label for="startAt">Start Date:</label>
												<input type="date" id="startAt" name="startAt" value="" />
											</div>
											<div class="date-input-group">
												<label for="endAt">End Date:</label>
												<input type="date" id="endAt" name="endAt" value="" />
											</div>
										</div>
									</fieldset>
								</div>
							</div>
							
							<div class="sync-form-actions">
								<input type="submit" class="wiz-button primary-button" value="Initiate Sync">
							</div>

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
					
					<!-- Quick Sync Operations -->
					<div class="wizcampaign-section card">
						<h3>Quick Sync Operations</h3>
						<div class="quick-sync-grid">
							<!-- Users Sync -->
							<div class="quick-sync-item">
								<h4>Sync Users</h4>
								<form id="syncUsersByDateForm" method="get" class="date-range-form">
									<input type="hidden" name="sync" value="users" />
									<div class="date-inputs">
										<div class="date-input-group">
											<label for="syncUsersStart">Start:</label>
											<input type="date" name="syncUsersStart" id="syncUsersStart" value="<?php echo $syncUsersStart ?? date('Y-m-d', strtotime('-3 days')); ?>" />
										</div>
										<div class="date-input-group">
											<label for="syncUsersEnd">End:</label>
											<input type="date" name="syncUsersEnd" id="syncUsersEnd" value="<?php echo $syncUsersEnd ?? date('Y-m-d'); ?>" />
										</div>
									</div>
									<input type="submit" class="wiz-button green" value="Sync Users" />
								</form>
							</div>
							
							<!-- Purchases Sync -->
							<div class="quick-sync-item">
								<h4>Sync Purchases</h4>
								<form id="syncPurchasesByDate" method="get" class="date-range-form">
									<input type="hidden" name="sync" value="purchases" />
									<div class="date-inputs">
										<div class="date-input-group">
											<label for="syncPurchasesStart">Start:</label>
											<input type="date" name="syncPurchasesStart" id="syncPurchasesStart" value="<?php echo $syncPurchasesStart ?? date('Y-m-d', strtotime('-3 days')); ?>" />
										</div>
										<div class="date-input-group">
											<label for="syncPurchasesEnd">End:</label>
											<input type="date" name="syncPurchasesEnd" id="syncPurchasesEnd" value="<?php echo $syncPurchasesEnd ?? date('Y-m-d'); ?>" />
										</div>
									</div>
									<input type="submit" class="wiz-button green" value="Sync Purchases" />
								</form>
							</div>
							
							<!-- Metrics Sync -->
							<div class="quick-sync-item">
								<h4>Sync Campaign Metrics</h4>
								<form id="syncMetricsByDate" method="get" class="date-range-form">
									<input type="hidden" name="sync" value="metrics" />
									<div class="date-inputs">
										<div class="date-input-group">
											<label for="syncMetricsStart">Start:</label>
											<input type="date" name="syncMetricsStart" id="syncMetricsStart" value="<?php echo $syncMetricsStart ?? date('Y-m-d', strtotime('-3 days')); ?>" />
										</div>
										<div class="date-input-group">
											<label for="syncMetricsEnd">End:</label>
											<input type="date" name="syncMetricsEnd" id="syncMetricsEnd" value="<?php echo $syncMetricsEnd ?? date('Y-m-d'); ?>" />
										</div>
									</div>
									<input type="submit" class="wiz-button green" value="Sync Metrics" />
								</form>
							</div>
							
							<!-- Hourly Opens & Clicks -->
							<div class="quick-sync-item">
								<h4>Sync Hourly Opens & Clicks</h4>
								<form id="syncHourlyClicksOpens" method="get" class="date-range-form">
									<input type="hidden" name="sync" value="hourlyOpensClicks" />
									<div class="date-inputs">
										<div class="date-input-group">
											<label for="syncHourlyStart">Start:</label>
											<input type="date" name="syncHourlyStart" id="syncHourlyStart" value="<?php echo $syncHourlyStart ?? date('Y-m-d', strtotime('-3 days')); ?>" />
										</div>
										<div class="date-input-group">
											<label for="syncHourlyEnd">End:</label>
											<input type="date" name="syncHourlyEnd" id="syncHourlyEnd" value="<?php echo $syncHourlyEnd ?? date('Y-m-d'); ?>" />
										</div>
									</div>
									<input type="submit" class="wiz-button green" value="Sync Hourly Data" />
								</form>
							</div>
						</div>
					</div>
					
					<!-- Database Cleanup Operations -->
					<div class="wizcampaign-section card">
						<h3><i class="fa-solid fa-database"></i> Database Cleanup Operations</h3>
						<div class="db-cleanup-grid">
							<!-- Queues and Scheduling -->
							<div class="db-cleanup-category">
								<h4>Queue Management</h4>
								<div class="db-cleanup-item">
									<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'requeue-retries'); ?>" id="requeueRetries">
										<i class="fa-solid fa-redo"></i> Re-queue Retries
									</a>
									<p>Sets all pending sync jobs to be retried immediately</p>
								</div>
								<div class="db-cleanup-item">
									<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'backfill-blast-data'); ?>" id="backfillBlastData">
										<i class="fa-solid fa-chart-line"></i> Backfill Blast Data
									</a>
									<p>Backfill engagement data for all blast campaigns</p>
								</div>
							</div>
							
							<!-- Purchase Data Fixes -->
							<div class="db-cleanup-category">
								<h4>Purchase Data Fixes</h4>
								<div class="db-cleanup-item">
									<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'update-null-user-ids'); ?>" id="updateNullUserIds">
										<i class="fa-solid fa-user-check"></i> Match Purchase User IDs
									</a>
									<p>Fills null userIds in purchases by matching accountNumber to other purchases</p>
								</div>
								<div class="db-cleanup-item">
									<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'update-missing-purchase-dates'); ?>" id="updateMissingPurchaseDates">
										<i class="fa-solid fa-calendar-check"></i> Update Missing Purchase Dates
									</a>
									<p>Fills in missing purchaseDate using the createdAt date</p>
								</div>
								<div class="db-cleanup-item">
									<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'clean-campaign-ids'); ?>" id="removeZeroCampaignIds">
										<i class="fa-solid fa-broom"></i> Clean Campaign IDs
									</a>
									<p>Remove campaignIds with value "0" (sets to null)</p>
								</div>
								<div class="db-cleanup-item">
									<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'backfill-purchase-campaign-dates'); ?>" id="backfillPurchaseCampaignDates">
										<i class="fa-solid fa-clock"></i> Backfill Campaign Send Dates
									</a>
									<p>Adds the startAt date/time to each purchase, if missing</p>
								</div>
							</div>
							
							<!-- Data Structure Fixes -->
							<div class="db-cleanup-category">
								<h4>Data Structure Fixes</h4>
								<div class="db-cleanup-item">
									<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'cleanup-users-database'); ?>" id="cleanupUsersDatabase">
										<i class="fa-solid fa-user-edit"></i> Cleanup Users Database
									</a>
									<p>Removes empty arrays and blank values; converts arrays to properly serialized data</p>
								</div>
								<div class="db-cleanup-item">
									<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'fix-triggered-timestamps'); ?>" id="fixTriggeredTimestamps">
										<i class="fa-solid fa-stopwatch"></i> Fix Triggered Timestamps
									</a>
									<p>Converts timestamps from seconds to milliseconds where needed</p>
								</div>
								<div class="db-cleanup-item">
									<a class="wiz-button green" href="<?php echo add_query_arg('db-cleanup', 'update-course-fiscal-years'); ?>" id="updateCourseFiscalYears">
										<i class="fa-solid fa-graduation-cap"></i> Update Course Fiscal Years
									</a>
									<p>Updates the fiscal_years field for all courses based on purchase data</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			
			<!-- Sync Log Section -->
			<div class="wizcampaign-section inset" id="sync-log-panel" style="flex: 1;">
				<h2><i class="fa-solid fa-list-alt"></i> Sync Log</h2>
				<div class="log-container">
					<pre id="syncLogContent"><code><?php echo get_wiz_log(250); ?></code></pre>
				</div>
			</div>
		</div>
	</div>
</article>

<style>
/* Sync Station Styles */
.card {
	border: 1px solid #e0e0e0;
	border-radius: 8px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.05);
	padding: 20px;
	margin-bottom: 20px;
	background: #fff;
}

.sync-form-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
}

@media (max-width: 768px) {
	.sync-form-grid {
		grid-template-columns: 1fr;
	}
}

.checkbox-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
}

.date-range-form {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.date-inputs {
	display: flex;
	gap: 10px;
	margin-bottom: 10px;
}

.date-input-group {
	display: flex;
	flex-direction: column;
	flex: 1;
}

.date-input-group label {
	margin-bottom: 5px;
	font-weight: 500;
}

.date-input-group input {
	padding: 8px;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.sync-form-actions {
	margin-top: 20px;
	text-align: right;
}

.primary-button {
	background-color: #2271b1;
	color: white;
	padding: 10px 20px;
	border: none;
	border-radius: 4px;
	font-weight: 500;
	cursor: pointer;
	transition: background-color 0.2s;
}

.primary-button:hover {
	background-color: #135e96;
}

fieldset {
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 15px;
	margin-bottom: 15px;
}

legend {
	font-weight: 600;
	padding: 0 10px;
}

textarea {
	width: 100%;
	min-height: 80px;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 8px;
}

/* Quick Sync Grid */
.quick-sync-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 20px;
}

.quick-sync-item {
	border: 1px solid #ddd;
	border-radius: 6px;
	padding: 15px;
	background: #f8f9fa;
}

.quick-sync-item h4 {
	margin-top: 0;
	margin-bottom: 15px;
	border-bottom: 1px solid #eee;
	padding-bottom: 8px;
}

/* Database Cleanup Grid */
.db-cleanup-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
}

.db-cleanup-category {
	border: 1px solid #ddd;
	border-radius: 6px;
	padding: 15px;
	background: #f8f9fa;
}

.db-cleanup-category h4 {
	margin-top: 0;
	margin-bottom: 15px;
	border-bottom: 1px solid #eee;
	padding-bottom: 8px;
}

.db-cleanup-item {
	margin-bottom: 15px;
	padding-bottom: 15px;
	border-bottom: 1px solid #eee;
}

.db-cleanup-item:last-child {
	margin-bottom: 0;
	padding-bottom: 0;
	border-bottom: none;
}

.db-cleanup-item p {
	margin: 8px 0 0 0;
	font-size: 0.9em;
	color: #666;
}

.db-cleanup-item .wiz-button {
	display: flex;
	align-items: center;
	gap: 8px;
}

/* Log Container */
.log-container {
	background: #f6f8fa;
	border: 1px solid #ddd;
	border-radius: 6px;
	height: 600px;
	overflow: auto;
}

#syncLogContent {
	margin: 0;
	padding: 15px;
	white-space: pre-wrap;
	font-family: monospace;
	font-size: 12px;
	line-height: 1.4;
}
</style>

<?php get_footer(); ?>