<?php
get_header();

global $wpdb;

//print_r(idemailwiz_fetch_experiments([6027838]));



if ( isset( $_GET['db-cleanup'] ) ) {
	$doCleanup = $_GET['db-cleanup'];
	if ( $doCleanup == 'update-null-user-ids' ) {
		update_null_user_ids();
	} else if ( $doCleanup == 'update-missing-purchase-dates' ) {
		update_missing_purchase_dates();
	} else if ( $doCleanup == 'clean-campaign-ids' ) {
		remove_zero_campaign_ids();
	} else if ( $doCleanup == 'backfill-purchase-campaign-dates' ) {
		idemailwiz_backfill_campaign_start_dates();
	} else if ( $doCleanup == 'cleanup-users-database' ) {
		idwiz_cleanup_users_database();
	} else if ( $doCleanup == 'fix-triggered-timestamps' ) {
		updateTimestampsToMilliseconds();
	}
}

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="wizHeader">
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<h1 class="wizEntry-title" itemprop="name">Sync Station</h1>
			</div>
			<div class="wizHeader-right">
				<!-- Additional header actions if needed -->
			</div>
		</div>
	</header>

	<div class="entry-content" itemprop="mainContentOfPage">
		<?php //print_r(idemailwiz_fetch_purchases(['8328396']));     ?>
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
							$syncTypes = [ 'Sends' => 'send', 'Opens' => 'open', 'Clicks' => 'click', 'Unsubscribes' => 'unSubscribe', 'Bounces' => 'bounce', 'Complaints' => 'complaint', 'SendSkips' => 'sendSkip' ];
							foreach ( $syncTypes as $label => $type ) {
								echo "<label><input type='checkbox' name='syncTypes[]' value='$type'> $label</label>";
							}
							?>

						</fieldset>

						<fieldset id="syncStation-syncCampaigns">
							<legend>Sync specific campaigns <br /></legend>
							<label for="campaignIds">Campaign IDs (comma-separated):</label><br />
							<textarea id="campaignIds" name="campaignIds"></textarea>
						</fieldset>

						<input type="submit" class="wiz-button green" value="Initiate Sync">
						<?php
						// Check if a sync is already in progress
						$overlayClass = '';
						if ( get_transient( 'idemailwiz_sync_in_progress' ) ) {
							$overlayClass = 'active';
						}
						?>
						<div class="syncForm-overlay <?php echo $overlayClass; ?>">
							<div class="syncForm-overlayContent">Sync in progress...</div>
						</div>
					</form>
				</div>
				<div class="wizcampaign-section">
					<h2>Database Cleanup Operations</h2>
					<div class="wizcampaign-sections-row">

						<div class="wizcampaign-section">
							<a class="wiz-button green"
								href="<?php echo add_query_arg( 'db-cleanup', 'update-null-user-ids' ); ?>"
								id="updateNullUserIds">Match Purchase User IDs</a>
							<h5>Fills null userIds in the purchases database by matching accountNumber to other
								purchases</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green"
								href="<?php echo add_query_arg( 'db-cleanup', 'update-missing-purchase-dates' ); ?>"
								id="updateMissingPurchaseDates">Update Missing Purchase
								Dates</a>
							<h5>Fill in PurchaseDate where missing using the createdAt date.</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green"
								href="<?php echo add_query_arg( 'db-cleanup', 'clean-campaign-ids' ); ?>"
								id="removeZeroCampaignIds">Clean Campaign IDs</a>
							<h5>Remove campaignIds with a value of "0" (updates value to null).</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green"
								href="<?php echo add_query_arg( 'db-cleanup', 'backfill-purchase-campaign-dates' ); ?>"
								id="backfillPurchaseCampaignDates">Backfill Purchase Campaign Send Dates</a>
							<h5>At the startAt date/time to each purchase, if missing.</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green"
								href="<?php echo add_query_arg( 'db-cleanup', 'cleanup-users-database' ); ?>"
								id="cleanupUsersDatabase">Cleanup Users Database</a>
							<h5>Removes empty arrays and blank values. Converts array to properly serialized data</h5>
						</div>
						<div class="wizcampaign-section">
							<a class="wiz-button green"
								href="<?php echo add_query_arg( 'db-cleanup', 'fix-triggered-timestamps' ); ?>"
								id="fixTriggeredTimestamps">Fix Triggered Timestamps</a>
							<h5>Converts timestamps in seconds to milliseconds, where needed.</h5>
						</div>
					</div>
				</div>
			</div>
			<!-- Sync Log Section -->
			<div class="wizcampaign-section inset" id="sync-log-panel">
				<h2>Sync Log</h2>
				<pre id="syncLogContent"><code><?php echo get_wiz_log( 250 ); ?></code></pre>
			</div>

		</div>

	</div>
</article>

<?php get_footer(); ?>