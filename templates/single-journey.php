<?php get_header(); ?>
<?php
// Retrieve the journey ID from the URL
$journeyId = $_GET['id'] ?? false;

// Check if the startDate and endDate parameters are present in the $_GET array, if not, default
$startDate = $_GET['startDate'] ?? date('Y-m-01');
$endDate = $_GET['endDate'] ?? date('Y-m-d');

if ($journeyId && get_idwiz_journey($journeyId)) {
	$journey = get_idwiz_journey($journeyId);

	// Get campaigns associated with this journey
	$journeyCampaigns = get_idwiz_campaigns([
		'workflowId' => $journeyId,
		'campaignState' => ['Running', 'Finished']
	]);

	// If no campaigns found, fallback to all campaigns for this journey
	if (empty($journeyCampaigns)) {
		$journeyCampaigns = get_idwiz_campaigns([
			'workflowId' => $journeyId
		]);
	}

	$journeyCampaignIds = array_column($journeyCampaigns, 'id');
	$journeyName = $journey['name'];

	// Calculate journey send dates from campaigns
	$journeyFirst = null;
	$journeyLast = null;
	
	foreach ($journeyCampaigns as $campaign) {
		if (isset($campaign['startAt'])) {
			$journeyFirst = $journeyFirst === null ? $campaign['startAt'] : min($journeyFirst, $campaign['startAt']);
			$journeyLast = $journeyLast === null ? $campaign['startAt'] : max($journeyLast, $campaign['startAt']);
		}
	}

	$journeyStartDate = $journeyFirst ? date('m/d/Y', (int)($journeyFirst / 1000)) : 'N/A';
	$journeyEndDate = $journeyLast ? date('m/d/Y', (int)($journeyLast / 1000)) : 'N/A';

	$campaigns = $journeyCampaigns;
?>
	<?php $activeTab = $_GET['view'] ?? 'Campaigns'; ?>
	<header class="wizHeader">
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<h1 class="wizEntry-title single-wizcampaign-title" title="<?php echo esc_attr(is_array($journeyName) ? implode(', ', $journeyName) : $journeyName); ?>" itemprop="name">
					<?php echo esc_html(is_array($journeyName) ? implode(', ', $journeyName) : $journeyName); ?>
				</h1>
				<div class="wizEntry-meta">
					<strong>Journey</strong>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
					<?php if (!empty($journey['journeyType'])): ?>
						<span class="journey-type"><?php echo esc_html(is_array($journey['journeyType']) ? implode(', ', $journey['journeyType']) : $journey['journeyType']); ?></span>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
					<?php endif; ?>
					Send dates: <?php echo $journeyStartDate; ?> - <?php echo $journeyEndDate; ?>
				</div>
				<div id="header-tabs">
					<a href="<?php echo add_query_arg(['view' => 'Campaigns']); ?>" class="campaign-tab <?php if ($activeTab == 'Campaigns') {
																											echo 'active';
																										} ?>">
						Campaigns Table
					</a>
					<a href="<?php echo add_query_arg(['view' => 'Timeline']); ?>" class="campaign-tab <?php if ($activeTab == 'Timeline') {
																											echo 'active';
																										} ?>">
						Timeline
					</a>
				</div>
			</div>
			<div class="wizHeader-right">
				<div class="wizHeader-actions">
					<button class="wiz-button green sync-journey" data-journeyids="<?php echo htmlspecialchars(json_encode($journeyCampaignIds)); ?>">Sync
						Journey</button>
					<?php include plugin_dir_path(__FILE__) . 'parts/module-user-settings-form.php'; ?>
				</div>
			</div>
		</div>
	</header>

	<article id="journey-<?php echo $journeyId; ?>" data-journey="<?php echo $journeyId; ?>" class="single-journey-article">
		<div class="entry-content" itemprop="mainContentOfPage">
			<?php if (!empty($journey['description'])): ?>
				<div class="wizcampaign-section inset journey-description-section">
					<h3>Journey Description</h3>
					<p><?php echo esc_html(is_array($journey['description']) ? implode(', ', $journey['description']) : $journey['description']); ?></p>
					
					<div class="journey-details">
						<?php if (isset($journey['createdAt'])): ?>
							<span class="journey-created">Created: <?php echo date('M j, Y', (int)($journey['createdAt'] / 1000)); ?></span>
						<?php endif; ?>
						<?php if (isset($journey['lifetimeLimit']) && $journey['lifetimeLimit'] > 0): ?>
							• <span class="journey-limit">Limit: <?php echo number_format($journey['lifetimeLimit']); ?> per user</span>
						<?php endif; ?>
						<?php if (isset($journey['triggerEventNames'])): 
							$triggers = is_string($journey['triggerEventNames']) ? unserialize($journey['triggerEventNames']) : $journey['triggerEventNames'];
							if (is_array($triggers) && !empty($triggers)): ?>
							• <span class="journey-triggers">Triggers: <?php echo implode(', ', $triggers); ?></span>
						<?php endif; endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['view']) && $_GET['view'] == 'Campaigns' || !isset($_GET['view'])) { ?>
				<?php include plugin_dir_path(__FILE__) . 'parts/dashboard-date-pickers.php'; ?>

				<div id="journey-rollup-wrapper" data-campaign-ids='<?php echo json_encode($journeyCampaignIds); ?>' data-start-date="<?php echo $startDate; ?>" data-end-date="<?php echo $endDate; ?>">
					<div class="rollup_summary_wrapper" id="journey-timeline-rollup-summary">
						<div class="rollup_summary_loader"><i class="fa-solid fa-spinner fa-spin"></i>&nbsp;&nbsp;Loading
							rollup summary...</div>
					</div>
				</div>

				<div class="journey-campaigns-wrapper">
					<?php
					if (!empty($campaigns)) {
						echo display_workflow_campaigns_table($journeyId, $campaigns, $startDate, $endDate);
					} else {
						echo '<div class="wizcampaign-section inset"><p>No campaigns found for this journey.</p></div>';
					}
					?>
				</div>
			<?php } ?>
			
			<?php if (isset($_GET['view']) && $_GET['view'] == 'Timeline') { ?>
				<?php
				$months = ['November', 'December', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October'];
				
				// Generate fiscal years dynamically based on current year
				$currentYear = (int)date('Y');
				$fiscalYears = [];
				for ($year = 2021; $year <= $currentYear + 1; $year++) {
					$fiscalYears[] = (string)$year;
				}
				
				$metrics = ['Open Rate', 'CTR', 'CTO', 'CVR', 'Revenue'];

				$selectedMetric = $_GET['metric'] ?? 'Open Rate';
				$selectedYears = $_GET['years'] ?? [date('Y')];
				$selectedMonths = $_GET['months'] ?? $months;

				// Ensure $selectedYears and $selectedMonths are arrays
				$selectedYears = is_array($selectedYears) ? $selectedYears : [$selectedYears];
				$selectedMonths = is_array($selectedMonths) ? $selectedMonths : [$selectedMonths];
				?>

				<div class="wizcampaign-section inset" id="journey-timeline-controls">
					<div class="journey-timeline-control-set">
						<select name="metric">
							<?php
							foreach ($metrics as $metric) {
								$selected = ($metric === $selectedMetric) ? 'selected' : '';
							?>
								<option value="<?php echo $metric; ?>" <?php echo $selected; ?>><?php echo $metric; ?></option>
							<?php
							}
							?>
						</select>
					</div>
					<div class="journey-timeline-control-set">
						<?php
						foreach ($fiscalYears as $fiscalYear) {
							$active = in_array($fiscalYear, $selectedYears) ? 'active green' : '';
						?>
							<button class="wiz-button <?php echo $active; ?>" data-fiscalyear="<?php echo $fiscalYear; ?>"><?php echo $fiscalYear; ?></button>
						<?php
						}
						?>
					</div>
					<div class="journey-timeline-control-set">
						<?php
						foreach ($months as $month) {
							$active = in_array($month, $selectedMonths) ? 'active green' : '';
						?>
							<button class="wiz-button <?php echo $active; ?>" data-month="<?php echo $month; ?>"><?php echo $month; ?></button>
						<?php
						}
						?>
					</div>
				</div>
				<div class="journey-timeline-wrapper">
					<h2><?php echo $selectedMetric; ?> Timeline</h2>
					<?php if (!empty($campaigns)): ?>
						<table class="idemailwiz_table journey_campaigns_table">
							<thead>
								<tr>
									<th>Fisc. Year</th>
									<th>Month</th>
									<?php foreach ($campaigns as $campaign) { ?>
										<th><?php echo esc_html(is_array($campaign['name']) ? implode(', ', $campaign['name']) : $campaign['name']); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<?php
								$selectedMetric = $_GET['metric'] ?? 'Open Rate';
								$selectedYears = $_GET['years'] ?? [date('Y')];
								// de-dupe years
								$selectedYears = array_unique($selectedYears);
								$selectedMonths = $_GET['months'] ?? $months;
								//de-dupe months
								$selectedMonths = array_unique($selectedMonths);

								$data = [];

								foreach ($selectedYears as $year) {
									foreach ($selectedMonths as $month) {
										$rowData = [];
										$rowData['year'] = $year;
										$rowData['month'] = $month;

										foreach ($campaigns as $campaign) {
											$campaignId = $campaign['id'];
											$monthIndex = array_search($month, $months);

											if ($monthIndex < 2) {
												$fiscalYear = $year - 1;
												$monthNumber = $monthIndex + 11;
											} else {
												$fiscalYear = $year;
												$monthNumber = $monthIndex - 1;
											}

											$monthNumber = str_pad($monthNumber, 2, '0', STR_PAD_LEFT);
											$startDate = $fiscalYear . '-' . $monthNumber . '-01';
											$endDate = date('Y-m-t', strtotime($startDate));

											$campaignMetrics = get_triggered_campaign_metrics([$campaignId], $startDate, $endDate);
											switch ($selectedMetric) {
												case 'Open Rate':
													$metricValue = $campaignMetrics['wizOpenRate'];
													$rowData[$campaignId] = number_format($metricValue, 2) . '%';
													break;
												case 'CTR':
													$metricValue = $campaignMetrics['wizCtr'];
													$rowData[$campaignId] = number_format($metricValue, 2) . '%';
													break;
												case 'CTO':
													$metricValue = $campaignMetrics['wizCto'];
													$rowData[$campaignId] = number_format($metricValue, 2) . '%';
													break;
												case 'CVR':
													$metricValue = $campaignMetrics['wizCvr'];
													$rowData[$campaignId] = number_format($metricValue, 2) . '%';
													break;
												case 'Revenue':
													$metricValue = $campaignMetrics['revenue'];
													$rowData[$campaignId] = '$' . number_format($metricValue, 2);
													break;
											}
										}

										$data[] = $rowData;
									}
								}

								// Sort by month first, then by year within each month
								usort($data, function ($a, $b) use ($months) {
									$monthComparison = array_search($a['month'], $months) - array_search($b['month'], $months);
									if ($monthComparison != 0) {
										return $monthComparison;
									}
									return $a['year'] - $b['year'];
								});

								// Define year colors for easy identification
								$baseColors = [
									'#e3f2fd', // Light blue
									'#f3e5f5', // Light purple  
									'#e8f5e8', // Light green
									'#fff3e0', // Light orange
									'#fce4ec', // Light pink
									'#f1f8e9', // Light lime
									'#e0f2f1', // Light teal
									'#fdf7e3', // Light yellow
									'#f0f4ff', // Light lavender
									'#fff0f5', // Light rose
								];
								
								$yearColors = [];
								$colorIndex = 0;
								for ($year = 2021; $year <= $currentYear + 5; $year++) {
									$yearColors[(string)$year] = $baseColors[$colorIndex % count($baseColors)];
									$colorIndex++;
								}

								foreach ($data as $rowData) {
									$yearColor = $yearColors[$rowData['year']] ?? '#f8f9fa';
									echo '<tr style="background-color: ' . $yearColor . ';">';
									echo '<td><strong>' . $rowData['year'] . '</strong></td>';
									echo '<td><strong>' . $rowData['month'] . '</strong></td>';

									foreach ($campaigns as $campaign) {
										$campaignId = $campaign['id'];
										echo '<td>' . $rowData[$campaignId] . '</td>';
									}

									echo '</tr>';
								}
								?>
							</tbody>
						</table>
					<?php else: ?>
						<div class="wizcampaign-section inset">
							<p>No campaigns found for timeline analysis.</p>
						</div>
					<?php endif; ?>
				</div>
			<?php } ?>
		</div>
	</article>

<?php
} else { // if valid journey id
	echo '<div class="wizcampaign-section inset"><p>Invalid journey ID or journey not found! <a href="' . get_bloginfo('url') . '/metrics/journeys">View all journeys</a></p></div>';
}

// Enqueue journeys styles and scripts
wp_enqueue_style('journeys-archive', plugin_dir_url(__FILE__) . '../styles/journeys-archive.css', [], '1.0.0');
wp_enqueue_script('journeys', plugin_dir_url(__FILE__) . '../js/journeys.js', ['jquery'], '1.0.0', true);

get_footer(); ?>