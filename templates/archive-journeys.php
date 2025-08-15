<?php get_header(); ?>
<article id="journey-archive">
	<header class="wizHeader">
		<h1 class="wizEntry-title" itemprop="name">
			Journeys & Automations
		</h1>
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<div class="wizEntry-meta">
					<span id="journey-count">Loading...</span> journeys
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
		<!-- Filter tabs and search -->
		<div class="journey-filters wizcampaign-section inset">
			<div class="filter-tabs">
				<?php $activeFilter = $_GET['filter'] ?? 'running'; ?>
				<a href="<?php echo add_query_arg(['filter' => 'running']); ?>" 
				   class="filter-tab <?php echo $activeFilter === 'running' ? 'active' : ''; ?>">
					Running
				</a>
				<a href="<?php echo add_query_arg(['filter' => 'archived']); ?>" 
				   class="filter-tab <?php echo $activeFilter === 'archived' ? 'active' : ''; ?>">
					Archived
				</a>
				<a href="<?php echo add_query_arg(['filter' => 'deactivated']); ?>" 
				   class="filter-tab <?php echo $activeFilter === 'deactivated' ? 'active' : ''; ?>">
					Deactivated
				</a>
			</div>
			<div class="search-box">
				<input type="text" id="journey-search" placeholder="Search journeys..." value="<?php echo esc_attr($_GET['search'] ?? ''); ?>">
				<i class="fa-solid fa-search search-icon"></i>
			</div>
		</div>

		<div id="journeys-container">
			<?php
			// Determine filter parameters based on active filter
			$journeyParams = ['sortBy' => 'name', 'sort' => 'ASC'];
			
			switch ($activeFilter) {
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

			// Get journeys based on filter
			$journeys = get_idwiz_journeys($journeyParams);
			
			// Apply search filter manually if provided (since the database function only does exact matches)
			$searchTerm = $_GET['search'] ?? '';
			if (!empty($searchTerm)) {
				$journeys = array_filter($journeys, function($journey) use ($searchTerm) {
					$name = is_array($journey['name']) ? implode(' ', $journey['name']) : $journey['name'];
					return stripos($name, $searchTerm) !== false;
				});
			}

			if (empty($journeys)) {
				?>
				<div class="wizcampaign-section inset">
					<p>No <?php echo $activeFilter; ?> journeys found. 
					<?php if (empty($searchTerm)): ?>
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
					if ($activeFilter === 'running' && empty($journeyCampaigns)) {
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

				$journeyCount = count($processedJourneys);
				
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
			?>
		</div>
	</div>
</article>

<?php
// Enqueue journeys archive styles and scripts
wp_enqueue_style('journeys-archive', plugin_dir_url(__FILE__) . '../styles/journeys-archive.css', [], '1.0.0');
wp_enqueue_script('journeys-archive', plugin_dir_url(__FILE__) . '../js/journeys-archive.js', ['jquery'], '1.0.0', true);
?>

<?php get_footer(); ?>