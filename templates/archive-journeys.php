<?php get_header(); ?>
<article id="journey-archive">
	<header class="wizHeader">
		<h1 class="wizEntry-title" itemprop="name">
			Active Journeys & Automations
		</h1>
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<div class="wizEntry-meta">
					<span id="journey-count">Loading...</span> active journeys
				</div>
			</div>
			<div class="wizHeader-right">
				<div class="wizHeader-actions">
					<button class="wiz-button green sync-journeys">Sync All Journeys</button>
				</div>
			</div>
		</div>
	</header>

	<div class="entry-content" itemprop="mainContentOfPage">
		<?php
		// Get all active journeys (enabled = true, not archived)
		$activeJourneys = get_idwiz_journeys([
			'enabled' => 1,
			'isArchived' => 0,
			'sortBy' => 'name',
			'sort' => 'ASC'
		]);

		if (empty($activeJourneys)) {
			?>
			<div class="wizcampaign-section inset">
				<p>No active journeys found. <a href="#" class="sync-journeys">Sync journeys</a> to load them from Iterable.</p>
			</div>
			<?php
		} else {
			$journeyCount = 0;
			
			foreach ($activeJourneys as $journey) {
				// Get campaigns associated with this journey using the journey ID as workflowId
				$journeyCampaigns = get_idwiz_campaigns([
					'workflowId' => $journey['id'],
					'campaignState' => ['Running', 'Finished']
				]);

				// Skip journeys with no campaigns
				if (empty($journeyCampaigns)) {
					continue;
				}

				// Check if any campaigns are currently running
				$hasRunningCampaigns = false;
				foreach ($journeyCampaigns as $campaign) {
					if ($campaign['campaignState'] === 'Running') {
						$hasRunningCampaigns = true;
						break;
					}
				}

				$journeyCount++;
				?>
				<div class="journey-wrapper wizcampaign-section inset" data-journey-id="<?php echo $journey['id']; ?>">
					<div class="journey-header">
						<div class="journey-title-section">
							<h2 class="journey-title">
								<a href="<?php echo get_bloginfo('url'); ?>/metrics/journey?id=<?php echo $journey['id']; ?>">
									<?php echo esc_html(is_array($journey['name']) ? implode(', ', $journey['name']) : $journey['name']); ?>
								</a>
								<?php if ($hasRunningCampaigns): ?>
									<span class="journey-status running">Running</span>
								<?php else: ?>
									<span class="journey-status finished">Finished</span>
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
				</div>
				<?php
			}
			
			// Update journey count via JavaScript - handled by external JS file
		}
		?>
	</div>
</article>

<?php
// Enqueue journeys archive styles and scripts
wp_enqueue_style('journeys-archive', plugin_dir_url(__FILE__) . '../styles/journeys-archive.css', [], '1.0.0');
wp_enqueue_script('journeys-archive', plugin_dir_url(__FILE__) . '../js/journeys-archive.js', ['jquery'], '1.0.0', true);
?>

<?php get_footer(); ?>