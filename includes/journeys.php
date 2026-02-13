<?php

// function get_journey_first_and_last_sends( $workflowId ) {
// 	$earliestSend = PHP_INT_MAX;
// 	$latestSend = PHP_INT_MIN;

// 	$return = [];

// 	// Process the new workflow IDs
// 	$campaigns = get_idwiz_campaigns( [ 'workflowId' => $workflowId, 'fields' => [ 'id' ], 'sortBy' => 'startAt', 'sort' => 'ASC' ] );
// 	foreach ( $campaigns as $campaign ) {
// 		$sends = get_idemailwiz_triggered_data( 'idemailwiz_triggered_sends', [ 'campaignIds' => [ $campaign['id'] ] ] );
// 		foreach ( $sends as $send ) {
// 			$sendAt = $send['startAt'];
// 			$earliestSend = min( $earliestSend, $sendAt );
// 			$latestSend = max( $latestSend, $sendAt );
// 		}
// 	}

// 	if ( $earliestSend != PHP_INT_MAX ) {
// 		$return['first'] = $earliestSend;
// 	}
// 	if ( $latestSend != PHP_INT_MIN ) {
// 		$return['last'] = $latestSend;
// 	}

// 	if ( count( $return ) > 0 ) {
// 		return $return;
// 	} else {
// 		return [ 'first' => null, 'last' => null ];
// 	}
// }

function update_journey_send_times()
{
	global $wpdb;
	$workflows_table = $wpdb->prefix . 'idemailwiz_workflows';
	$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
	$sends_table = $wpdb->prefix . 'idemailwiz_triggered_sends';

	$workflows = $wpdb->get_results("SELECT DISTINCT workflowId FROM $campaigns_table", ARRAY_A);

	$collectUpdates = [];
	foreach ($workflows as $workflow) {
		$workflowId = $workflow['workflowId'];

		// Retrieve the IDs of all campaigns associated with the current workflow
		$campaignsWithWorkflows = get_idwiz_campaigns(['workflowId' => [$workflowId], 'fields' => ['id']]);
		$campaign_ids = array_column($campaignsWithWorkflows, 'id');


		if (!empty($campaign_ids)) {
			// Create a comma-separated string of campaign IDs
			$campaign_ids_string = implode(',', $campaign_ids);

			// Prepare the SQL query using the campaign IDs string
			$query = "SELECT MIN(startAt) as firstSend, MAX(startAt) as lastSend FROM $sends_table WHERE campaignId IN ($campaign_ids_string)";

			$times = $wpdb->get_row($query, ARRAY_A);

			if ($times) {

				$firstSend = isset($times['firstSend']) && !empty($times['firstSend']) ? $times['firstSend'] : null;
				$lastSend = isset($times['lastSend']) && !empty($times['lastSend']) ? $times['lastSend'] : null;

				if ($firstSend !== null || $lastSend !== null) {
					// Update the workflows table with the retrieved times
					$wpdb->update(
						$workflows_table,
						['firstSendAt' => $firstSend, 'lastSendAt' => $lastSend],
						['workflowId' => $workflowId],
						['%d', '%d'],
						['%d']
					);
					$collectUpdates[] = $workflowId;
					
				}
			}
		}
	}
	wiz_log('Updated journey send times for workflows: ' . implode(', ', $collectUpdates));
}

add_action('init', function () {
	if (!wp_next_scheduled('update_journey_send_times_hook')) {
		wp_schedule_event(time(), 'hourly', 'update_journey_send_times_hook');
	}
});

add_action('update_journey_send_times_hook', 'update_journey_send_times');



function upsert_workflow($workflowId, $workflowName = '')
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_workflows';

	if ($workflowId > 0) {
		if (empty($workflowName)) {
			$workflowName = 'Journey ' . $workflowId;
		}

		$wpdb->query($wpdb->prepare(
			"
		INSERT INTO $table_name (workflowId, workflowName)
		VALUES (%d, %s)
		ON DUPLICATE KEY UPDATE workflowName = %s",
			$workflowId,
			$workflowName,
			$workflowName
		));
	}
}

function get_workflow($workflowId)
{
	global $wpdb;
	$workflows_table = $wpdb->prefix . 'idemailwiz_workflows';
	$workflow = $wpdb->get_row($wpdb->prepare(
		"
		SELECT * FROM $workflows_table WHERE workflowId = %d",
		(int)$workflowId
	), ARRAY_A);

	return $workflow;
}

function get_workflow_by_campaign_id($campaignId)
{
	global $wpdb;
	$workflows_table = $wpdb->prefix . 'idemailwiz_workflows';

	$campaign = get_idwiz_campaigns(['id' => $campaignId, 'fields' => ['workflowId'], 'limit' => 1]);

	if (empty($campaign)) {
		return null;
	}

	$workflowId = $campaign[0]['workflowId'];
	$workflow = $wpdb->get_row($wpdb->prepare(
		"
		SELECT * FROM $workflows_table WHERE workflowId = %d",
		$workflowId
	), ARRAY_A);

	return $workflow;
}

function get_workflow_campaigns($workflowId)
{
	global $wpdb;
	$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
	return $wpdb->get_results($wpdb->prepare("
	SELECT * FROM $campaigns_table
	WHERE workflowId = %d", $workflowId), ARRAY_A);
}

function add_new_workflows_daily()
{
	global $wpdb;
	$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
	$workflows_table = $wpdb->prefix . 'idemailwiz_workflows';

	$new_workflow_ids = $wpdb->get_col("
		SELECT DISTINCT workflowId FROM $campaigns_table
		WHERE workflowId NOT IN (
			SELECT workflowId FROM $workflows_table
		)
	");

	foreach ($new_workflow_ids as $workflowId) {
		upsert_workflow($workflowId); // Use the upsert function created earlier
	}
	remove_orphaned_workflows();
}
add_action('init', function () {
	if (!wp_next_scheduled('add_new_workflows_daily_hook')) {
		wp_schedule_event(time(), 'daily', 'add_new_workflows_daily_hook');
	}
});
add_action('add_new_workflows_daily_hook', 'add_new_workflows_daily');

function remove_orphaned_workflows()
{
	global $wpdb;
	$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
	$workflows_table = $wpdb->prefix . 'idemailwiz_workflows';

	$wpdb->query("
		DELETE FROM $workflows_table
		WHERE workflowId NOT IN (
			SELECT DISTINCT workflowId FROM $campaigns_table
		)
	");
}

add_action('wp_ajax_upsert_workflow', function () {

	$workflowId = isset($_POST['workflowId']) ? intval($_POST['workflowId']) : null;
	$workflowName = isset($_POST['workflowName']) ? sanitize_text_field($_POST['workflowName']) : '';

	if (!$workflowId) {
		wp_send_json_error('Missing workflow ID', 400);
	}

	upsert_workflow($workflowId, $workflowName);
	wp_send_json_success();
});

// AJAX handler for filtering and searching journeys
add_action('wp_ajax_filter_journeys', function() {
	// Verify nonce - using the same pattern as existing handlers
	if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'wizAjaxNonce')) {
		wp_send_json_error('Security check failed');
	}
	
	$filter = sanitize_text_field($_POST['filter'] ?? 'running');
	$search = sanitize_text_field($_POST['search'] ?? '');
	
	// Determine filter parameters based on active filter
	$journeyParams = ['sortBy' => 'name', 'sort' => 'ASC'];
	
	switch ($filter) {
		case 'running':
			$journeyParams['enabled'] = 1;
			$journeyParams['isArchived'] = 0;
			break;
		case 'archived':
			$journeyParams['isArchived'] = 1;
			break;
		case 'deactivated':
			$journeyParams['enabled'] = 0;
			$journeyParams['isArchived'] = 0;
			break;
	}
	
	// Get journeys based on filter (without search first)
	$journeys = get_idwiz_journeys($journeyParams);
	
	// Apply search filter manually if provided (since the database function only does exact matches)
	if (!empty($search)) {
		$journeys = array_filter($journeys, function($journey) use ($search) {
			$name = is_array($journey['name']) ? implode(' ', $journey['name']) : $journey['name'];
			return stripos($name, $search) !== false;
		});
	}
	
	// Generate HTML content
	ob_start();
	
	if (empty($journeys)) {
		?>
		<div class="wizcampaign-section inset">
			<p>No <?php echo $filter; ?> journeys found. 
			<?php if (empty($search)): ?>
				<a href="#" class="sync-journeys">Sync journeys</a> to load them from Iterable.
			<?php else: ?>
				Try adjusting your search criteria.
			<?php endif; ?>
			</p>
		</div>
		<?php
	} else {
		// Process journeys to add last sent date and filter out those without campaigns
		$processedJourneys = [];
		
		foreach ($journeys as $journey) {
			// Get campaigns associated with this journey using the journey ID as workflowId
			$journeyCampaigns = get_idwiz_campaigns([
				'workflowId' => $journey['id'],
				'campaignState' => ['Running', 'Finished']
			]);

			// For running filter, skip journeys with no campaigns
			if ($filter === 'running' && empty($journeyCampaigns)) {
				continue;
			}

			// Calculate last sent date from campaigns
			$lastSentTimestamp = null;
			$hasRunningCampaigns = false;

			foreach ($journeyCampaigns as $campaign) {
				if ($campaign['campaignState'] === 'Running') {
					$hasRunningCampaigns = true;
				}
				
				if (isset($campaign['startAt'])) {
					$campaignTimestamp = (int)($campaign['startAt'] / 1000);
					if ($lastSentTimestamp === null || $campaignTimestamp > $lastSentTimestamp) {
						$lastSentTimestamp = $campaignTimestamp;
					}
				}
			}

			// Add computed fields to journey
			$journey['lastSentTimestamp'] = $lastSentTimestamp;
			$journey['lastSentFormatted'] = $lastSentTimestamp ? date('M j, Y', $lastSentTimestamp) : 'Never';
			$journey['hasRunningCampaigns'] = $hasRunningCampaigns;
			$journey['campaigns'] = $journeyCampaigns;

			$processedJourneys[] = $journey;
		}

		// Sort by last sent date (most recent first)
		usort($processedJourneys, function($a, $b) {
			// Prioritize journeys with sent dates over those without
			if ($a['lastSentTimestamp'] === null && $b['lastSentTimestamp'] === null) {
				return 0;
			}
			if ($a['lastSentTimestamp'] === null) {
				return 1; // Move nulls to end
			}
			if ($b['lastSentTimestamp'] === null) {
				return -1; // Move nulls to end
			}
			return $b['lastSentTimestamp'] - $a['lastSentTimestamp']; // Newest first
		});

		foreach ($processedJourneys as $journey) {
			$journeyCampaigns = $journey['campaigns'];
			?>
			<div class="journey-wrapper wizcampaign-section inset" data-journey-id="<?php echo $journey['id']; ?>">
				<div class="journey-header">
					<div class="journey-title-section">
						<h2 class="journey-title">
							<a href="<?php echo get_bloginfo('url'); ?>/metrics/journey?id=<?php echo $journey['id']; ?>">
								<?php echo esc_html(is_array($journey['name']) ? implode(', ', $journey['name']) : $journey['name']); ?>
							</a>
							<?php if ($journey['hasRunningCampaigns']): ?>
								<span class="journey-status running">Running</span>
							<?php elseif (!empty($journeyCampaigns)): ?>
								<span class="journey-status finished">Finished</span>
							<?php else: ?>
								<span class="journey-status inactive">No Campaigns</span>
							<?php endif; ?>
						</h2>
						
						<?php if (!empty($journey['description'])): ?>
							<p class="journey-description"><?php echo esc_html(is_array($journey['description']) ? implode(', ', $journey['description']) : $journey['description']); ?></p>
						<?php endif; ?>
						
						<div class="journey-meta">
							<span class="journey-type"><?php 
								$journeyType = $journey['journeyType'] ?? 'Journey';
								echo esc_html(is_array($journeyType) ? implode(', ', $journeyType) : $journeyType);
							?></span>
							<?php if (isset($journey['createdAt'])): ?>
								• <span class="journey-created">Created <?php echo date('M j, Y', (int)($journey['createdAt'] / 1000)); ?></span>
							<?php endif; ?>
							<span class="journey-last-sent">• Last sent: <?php echo $journey['lastSentFormatted']; ?></span>
							<?php if (isset($journey['lifetimeLimit']) && $journey['lifetimeLimit'] > 0): ?>
								• <span class="journey-limit">Limit: <?php echo number_format($journey['lifetimeLimit']); ?> per user</span>
							<?php endif; ?>
							<?php if (isset($journey['triggerEventNames'])): 
								$triggers = is_string($journey['triggerEventNames']) ? unserialize($journey['triggerEventNames']) : $journey['triggerEventNames'];
								if (is_array($triggers) && !empty($triggers)): ?>
								• <span class="journey-triggers">Triggers: <?php echo implode(', ', array_slice($triggers, 0, 3)); ?><?php echo count($triggers) > 3 ? '...' : ''; ?></span>
							<?php endif; endif; ?>
						</div>
					</div>
					
					<div class="journey-actions">
						<button class="wiz-button sync-journey" data-journey-id="<?php echo $journey['id']; ?>">
							Sync Journey
						</button>
					</div>
				</div>

				<?php if (!empty($journeyCampaigns)): ?>
				<div class="journey-campaigns">
					<h3>Campaign Performance (<?php echo count($journeyCampaigns); ?> campaigns)</h3>
					<?php 
					// Calculate rollup metrics for this journey
					$totalSent = 0;
					$totalDelivered = 0;
					$totalOpens = 0;
					$totalClicks = 0;
					$totalPurchases = 0;
					$totalRevenue = 0;
					$totalUnsubscribes = 0;

					foreach ($journeyCampaigns as $campaign) {
						if ($campaign['campaignState'] == 'Running') {
							$metrics = get_idwiz_metric($campaign['id']);
							if ($metrics) {
								$totalSent += $metrics['uniqueEmailSends'] ?? 0;
								$totalDelivered += $metrics['uniqueEmailsDelivered'] ?? 0;
								$totalOpens += $metrics['uniqueEmailOpens'] ?? 0;
								$totalClicks += $metrics['uniqueEmailClicks'] ?? 0;
								$totalPurchases += $metrics['uniquePurchases'] ?? 0;
								$totalRevenue += $metrics['revenue'] ?? 0;
								$totalUnsubscribes += $metrics['uniqueUnsubscribes'] ?? 0;
							}
						}
					}

					// Calculate rates
					$openRate = $totalSent > 0 ? ($totalOpens / $totalSent) * 100 : 0;
					$ctr = $totalSent > 0 ? ($totalClicks / $totalSent) * 100 : 0;
					$cto = $totalOpens > 0 ? ($totalClicks / $totalOpens) * 100 : 0;
					$cvr = $totalSent > 0 ? ($totalPurchases / $totalSent) * 100 : 0;
					$unsubRate = $totalSent > 0 ? ($totalUnsubscribes / $totalSent) * 100 : 0;
					?>
					
					<div class="rollup_summary_wrapper">
						<div class="metric-item">
							<span class="metric-label">Total Sent</span>
							<span class="metric-value"><?php echo number_format($totalSent); ?></span>
						</div>
						<div class="metric-item">
							<span class="metric-label">Open Rate</span>
							<span class="metric-value"><?php echo number_format($openRate, 1); ?>%</span>
						</div>
						<div class="metric-item">
							<span class="metric-label">Click Rate</span>
							<span class="metric-value"><?php echo number_format($ctr, 1); ?>%</span>
						</div>
						<div class="metric-item">
							<span class="metric-label">Click-to-Open</span>
							<span class="metric-value"><?php echo number_format($cto, 1); ?>%</span>
						</div>
						<div class="metric-item">
							<span class="metric-label">Purchases</span>
							<span class="metric-value"><?php echo number_format($totalPurchases); ?></span>
						</div>
						<div class="metric-item">
							<span class="metric-label">Revenue</span>
							<span class="metric-value">$<?php echo number_format($totalRevenue); ?></span>
						</div>
					</div>

					<div class="journey-campaigns-table-wrapper">
						<details class="campaigns-details">
							<summary class="campaigns-summary">
								<span class="toggle-text">Show Individual Campaigns</span>
								<span class="campaign-count">(<?php echo count($journeyCampaigns); ?> campaigns)</span>
							</summary>
							<div class="campaigns-table-container">
								<?php echo display_workflow_campaigns_table($journey['id'], $journeyCampaigns); ?>
							</div>
						</details>
					</div>
				</div>
				<?php endif; ?>
			</div>
			<?php
		}
	}
	
	$html = ob_get_clean();
	
	wp_send_json_success([
		'html' => $html,
		'count' => count($processedJourneys ?? [])
	]);
});

function display_workflow_campaigns_table($workflowId, $campaigns, $startDate = null, $endDate = null, $showAllWithFilter = false)
{
	//$workflow = get_workflow($workflowId);
?>
	<div class="workflow-campaigns">
		<table class="idemailwiz_table journey_campaigns_table<?php echo $showAllWithFilter ? ' journey-campaigns-sortable' : ''; ?>" id="journey-campaigns-table-<?php echo $workflowId; ?>">
			<thead>
				<tr>
					<th>Campaign</th>
					<th>Status</th>
					<th>Last Sent</th>
					<th>Sent</th>
					<th>Delivered</th>
					<th>Opens</th>
					<th>Open Rate</th>
					<th>Clicks</th>
					<th>CTR</th>
					<th>CTO</th>
					<th>Purch.</th>
					<th>CVR</th>
					<th>Rev</th>
					<th>Unsubs.</th>
					<?php
					if (!$startDate && !$endDate) {
					?>
						<th>GA Rev</th>
					<?php } ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($campaigns as $campaign) {
					$campaignState = $campaign['campaignState'] ?? 'Unknown';
					$campaignType = $campaign['type'] ?? 'Unknown';
					
					// For triggered campaigns, "Finished" means deactivated (inactive)
					// For blast campaigns, "Finished" means completed (still considered active for display)
					if ($campaignType === 'Triggered' || $campaignType === 'FromWorkflow') {
						$isActive = ($campaignState === 'Running');
					} else {
						$isActive = ($campaignState === 'Running' || $campaignState === 'Finished');
					}
					
					// If not showing all with filter, only show active campaigns
					if (!$showAllWithFilter && !$isActive) {
						continue;
					}
					
					// Get metrics for active campaigns, show zeros for inactive
					if ($isActive) {
						if (!$startDate && !$endDate) {
							$campaignMetrics = get_idwiz_metric($campaign['id']);
						} else {
							$campaignMetrics = get_triggered_campaign_metrics([$campaign['id']], $startDate, $endDate);
						}
						$totalSent = $campaignMetrics['uniqueEmailSends'] ?? 0;
						$uniqueDelivers = $campaignMetrics['uniqueEmailsDelivered'] ?? 0;
						$uniqueOpens = $campaignMetrics['uniqueEmailOpens'] ?? 0;
						$openRate = $campaignMetrics['wizOpenRate'] ?? 0;
						$uniqueClicks = $campaignMetrics['uniqueEmailClicks'] ?? 0;
						$ctr = $campaignMetrics['wizCtr'] ?? 0;
						$cto = $campaignMetrics['wizCto'] ?? 0;
						$uniquePurchases = $campaignMetrics['uniquePurchases'] ?? 0;
						$wizCvr = $campaignMetrics['wizCvr'] ?? 0;
						$revenue = $campaignMetrics['revenue'] ?? 0;
						$gaRevenue = $campaignMetrics['gaRevenue'] ?? 0;
						$wizUnsubRate = $campaignMetrics['wizUnsubRate'] ?? 0;
					} else {
						// Inactive campaigns show zeros
						$totalSent = $uniqueDelivers = $uniqueOpens = $openRate = $uniqueClicks = 0;
						$ctr = $cto = $uniquePurchases = $wizCvr = $revenue = $gaRevenue = $wizUnsubRate = 0;
					}
					
					// Determine status class and display text for styling
					$statusClass = '';
					$statusDisplay = $campaignState;
					
					// For triggered campaigns, "Finished" means deactivated
					if (($campaignType === 'Triggered' || $campaignType === 'FromWorkflow') && $campaignState === 'Finished') {
						$statusClass = 'status-deactivated';
						$statusDisplay = 'Deactivated';
					} else {
						switch ($campaignState) {
							case 'Running':
								$statusClass = 'status-running';
								break;
							case 'Finished':
								$statusClass = 'status-finished';
								break;
							case 'Draft':
								$statusClass = 'status-draft';
								break;
							default:
								$statusClass = 'status-inactive';
						}
					}
					
					// Calculate sortable timestamp
					$campaignStartStamp = isset($campaign['startAt']) ? (int) ($campaign['startAt'] / 1000) : 0;
				?>
						<tr class="campaign-row <?php echo !$isActive ? 'inactive-campaign' : ''; ?>" data-status="<?php echo esc_attr($campaignState); ?>">
							<td>
								<a href="<?php echo get_bloginfo('url'); ?>/metrics/campaign/?id=<?php echo $campaign['id']; ?>">
									<?php echo esc_html($campaign['name']); ?>
								</a>
							</td>
							<td>
								<span class="campaign-status-badge <?php echo $statusClass; ?>">
									<?php echo esc_html($statusDisplay); ?>
								</span>
							</td>
							<td data-order="<?php echo $campaignStartStamp; ?>">
								<?php 
								// Validate that we have a proper timestamp and it's not zero
								if ($campaignStartStamp > 0 && $campaignStartStamp > 946684800) {
									echo date('M j, Y', $campaignStartStamp);
								} else {
									echo 'N/A';
								}
								?>
							</td>
							<td data-order="<?php echo $totalSent; ?>">
								<?php echo number_format($totalSent); ?>
							</td>
							<td data-order="<?php echo $uniqueDelivers; ?>">
								<?php echo number_format($uniqueDelivers); ?>
							</td>
							<td data-order="<?php echo $uniqueOpens; ?>">
								<?php echo number_format($uniqueOpens); ?>
							</td>
							<td data-order="<?php echo $openRate; ?>">
								<?php echo number_format($openRate, 2); ?>%
							</td>
							<td data-order="<?php echo $uniqueClicks; ?>">
								<?php echo number_format($uniqueClicks); ?>
							</td>
							<td data-order="<?php echo $ctr; ?>">
								<?php echo number_format($ctr, 2); ?>%
							</td>
							<td data-order="<?php echo $cto; ?>">
								<?php echo number_format($cto, 2); ?>%
							</td>
							<td data-order="<?php echo $uniquePurchases; ?>">
								<?php echo number_format($uniquePurchases); ?>
							</td>
							<td data-order="<?php echo $wizCvr; ?>">
								<?php echo '$' . number_format($wizCvr); ?>
							</td>
							<td data-order="<?php echo $revenue; ?>">
								<?php echo '$' . number_format($revenue); ?>
							</td>
							<td data-order="<?php echo $wizUnsubRate; ?>">
								<?php echo number_format($wizUnsubRate, 2); ?>%
							</td>
							<?php
							if (!$startDate && !$endDate) {
							?>
								<td data-order="<?php echo (int)$gaRevenue; ?>">
									<?php echo '$' . number_format((int)$gaRevenue); ?>
								</td>
							<?php } ?>
						</tr>
				<?php
				}
				?>
			</tbody>
		</table>
	</div>
<?php
}
