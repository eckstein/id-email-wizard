<?php get_header(); ?>
<?php
// Retrieve the stored campaign IDs and send dates from post meta
$journeyId = $_GET['id'] ?? false;



// Check if the startDate and endDate parameters are present in the $_GET array, if not, default
$startDate = $_GET['startDate'] ?? date('Y-m-01');
$endDate = $_GET['endDate'] ?? date('Y-m-d');
if ($journeyId && get_workflow($journeyId)) {

	$journey = get_workflow($journeyId);

	$journeyFirst = $journey['firstSendAt'];
	$journeyLast = $journey['lastSendAt'];

	$journeyStartDate = date('m/d/Y', $journeyFirst / 1000);
	$journeyEndDate = date('m/d/Y', $journeyLast / 1000);

	$journeyCampaigns = get_workflow_campaigns($journeyId);
	$journeyCampaignIds = array_column($journeyCampaigns, 'id');

	$journeyName = $journey['workflowName'];

	$campaigns = get_workflow_campaigns($journeyId);
?>
	<?php $activeTab = $_GET['view'] ?? 'Campaigns'; ?>
	<header class="wizHeader">
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<h1 class="wizEntry-title single-wizcampaign-title" title="<?php echo $journeyName; ?>" itemprop="name">
					<?php echo $journeyName; ?>
				</h1>
				<div class="wizEntry-meta"><strong>Journey</strong>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;Send dates:
					<?php echo $journeyStartDate; ?> -
					<?php echo $journeyEndDate; ?>
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

	<article id="journey-<?php $journeyId; ?>" data-journey="<?php echo $journeyId; ?>" class="single-journey-article">

		<div class="entry-content" itemprop="mainContentOfPage">

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

					echo display_workflow_campaigns_table($journeyId, $campaigns, $startDate, $endDate); ?>
				</div>
			<?php } ?>
			<?php if (isset($_GET['view']) && $_GET['view'] == 'Timeline') { ?>
				<?php
				$months = ['November', 'December', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October'];
				$fiscalYears = ['2021', '2022', '2023', '2024'];
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
					<table class="idemailwiz_table journey_campaigns_table">
						<thead>
							<tr>
								<th>Year</th>
								<th>Month</th>
								<?php foreach ($campaigns as $campaign) { ?>
									<th><?php echo $campaign['name']; ?></th>
								<?php } ?>
							</tr>
						</thead>
						<tbody>
							<?php
							$selectedMetric = $_GET['metric'] ?? 'Open Rate';
							$selectedYears = $_GET['years'] ?? [date('Y')];
							$selectedMonths = $_GET['months'] ?? $months;

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

							usort($data, function ($a, $b) use ($months) {
								if ($a['year'] != $b['year']) {
									return $a['year'] - $b['year'];
								}
								return array_search($a['month'], $months) - array_search($b['month'], $months);
							});


							foreach ($data as $rowData) {
								echo '<tr>';
								echo '<td>' . $rowData['year'] . '</td>';
								echo '<td>' . $rowData['month'] . '</td>';

								foreach ($campaigns as $campaign) {
									$campaignId = $campaign['id'];
									echo '<td>' . $rowData[$campaignId] . '</td>';
								}

								echo '</tr>';
							}
							?>
						</tbody>
					</table>
				</div>
			<?php } ?>

		</div>
	</article>


<?php
} else { // if valid workflow id
	echo 'Invalid workflowId or workflow has been deleted!';
}
get_footer(); ?>