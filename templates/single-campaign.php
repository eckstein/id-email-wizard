<?php get_header(); ?>
<?php

global $wpdb;

date_default_timezone_set('America/Los_Angeles');

$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$campaign = get_idwiz_campaign($campaign_id);

// Figure out start and end date

// Define default values
$startDate = date('Y-m-01'); // Default start date is the first day of the current month
$endDate = date('Y-m-d');    // Default end date is today

// Check if the startDate and endDate parameters are present in the $_GET array
if (isset($_GET['startDate']) && $_GET['startDate'] !== '') {
	$startDate = $_GET['startDate'];
}
if (isset($_GET['endDate']) && $_GET['endDate'] !== '') {
	$endDate = $_GET['endDate'];
} else {
	if (isset($campaign['type']) && $campaign['type'] === 'Blast') {
		$campaignStartStamp = (int) ($campaign['startAt'] / 1000);
		$startDate = $campaignStartStamp > 0 ? date('Y-m-d', $campaignStartStamp) : '2023-11-01';
		$endDate = date('Y-m-d'); // End date is today
	}
}



$metrics = get_idwiz_metric($campaign['id']);
$template = get_idwiz_template($campaign['templateId']);

// Set the default timezone
date_default_timezone_set('America/Los_Angeles');

$campaignStartStamp = (int) ($campaign['startAt'] / 1000);
$campaignEndStamp = (int) ($campaign['endedAt'] / 1000);

$campaignStartAt = date('m/d/Y g:ia', $campaignStartStamp);
$campaignEndedAt = date('g:ia', $campaignEndStamp);
$campaignEndDateTime = date('m/d/Y \a\t g:ia', $campaignEndStamp);


// Create formatted end date and time
$campaignEndDateTime = date('m/d/Y \a\t g:ia', $campaignEndStamp);

// Get timezone abbreviation from the start time
$sendsTimezone = date('T', $campaignStartStamp);

$campaignState = $campaign['campaignState'];

$connectedCampaignIds = !empty(maybe_unserialize($campaign['connectedCampaigns'])) ? maybe_unserialize($campaign['connectedCampaigns']) : [];

//Create an array with the current campaign id and all connected campaigns ids
$allCampaignIds = array_merge([$campaign_id], $connectedCampaignIds);

if (!empty($connectedCampaignIds)) {
	$connectedCampaigns = get_idwiz_campaigns([
		'campaignIds' => $connectedCampaignIds,
		'fields' => ['id', 'name']
	]);
}




$purchases = get_idwiz_purchases(['campaignIds' => $allCampaignIds]);

//$experimentIds = maybe_unserialize($campaign['experimentIds']) ?? array();
// This returns one row per experiment TEMPLATE
$experiments = get_idwiz_experiments(array('campaignIds' => [$campaign['id']]));
// Returns multiple templates with the same experiment ID, so we de-dupe
$experimentIds = array_unique(array_column($experiments, 'experimentId'));
$linkedExperimentIds = array_map(function ($id) {
	return '<a href="https://app.iterable.com/experiments/monitor?experimentId=' . urlencode($id) . '">' . htmlspecialchars($id) . '</a>';
}, $experimentIds);

?>

<article id="campaign-<?php echo $campaign['id']; ?>" class="wizcampaign-single has-wiz-chart" data-campaignid="<?php echo $campaign['id']; ?>">
	<?php
	//fetch_ga_data();
	?>

	<header class="wizHeader">
		<div class="wizHeaderInnerWrap">

			<div class="wizHeader-left">
				<h1 class="wizEntry-title single-wizcampaign-title" itemprop="name">
					<?php echo $campaign['name']; ?>
				</h1>
				<div class="wizEntry-meta">

					<strong>
						<?php echo $campaign['type']; ?> <?php echo $campaign['messageMedium']; ?> Campaign <a href="https://app.iterable.com/campaigns/<?php echo $campaign['id']; ?>?view=summary">
							<?php echo $campaign['id']; ?></a> <?php if ($campaign['workflowId']) { ?>within Workflow <a href="https://app.iterable.com/workflows/<?php echo $campaign['workflowId']; ?>/edit"><?php echo $campaign['workflowId']; ?></a><?php } ?>

					<?php if ($experimentIds) {
						echo '&nbsp;with Experiment ' . implode(', ', $linkedExperimentIds) . '</a>';
					} ?>
					</strong>
					&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
					<?php
					if (!empty($connectedCampaignIds)) {
						$campaignNames = [];
						foreach ($connectedCampaigns as $connectedCampaign) {
							$campaignNames[$connectedCampaign['id']] = $connectedCampaign['name'];
						}

						$campaignIdList = array_map(function ($id) use ($campaignNames) {
							$name = isset($campaignNames[$id]) ? htmlspecialchars($campaignNames[$id]) : '';
							return '<span class="connected-campaign-bubble"><a href="' . get_bloginfo('url') . '/metrics/campaign?id=' . urlencode($id) . '" title="' . $name . '">' . htmlspecialchars($id) . '</a><i class="fa-solid fa-xmark disconnect-campaign" data-remove-from="' . intval($_GET['id']) . '" data-campaignid="' . $id . '"></i></span>';
						}, $connectedCampaignIds);


					?>
						<span class="connected-campaigns">Connected to:
							<?php echo implode(' ', $campaignIdList); ?></span>
					<?php } ?>
					<a href="#" class="connect-campaigns" data-campaignid="<?php echo $campaign['id']; ?>">Connect
						campaigns</a>

					<br />
					<?php if ($campaign['type'] == 'Triggered') {
						echo 'Last ';
					} ?>Sent on
					<?php echo $campaignStartAt; ?>
					<?php
					if ($campaignStartAt != $campaignEndDateTime && $campaign['type'] == 'Blast') {
						echo "— $campaignEndedAt";
					}
					echo '&nbsp;' . $sendsTimezone;
					?>




					<?php
					if (isset($template['clientTemplateId'])) { ?>
						&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
						<?php echo 'Wiz Template: <a href="' . get_bloginfo('url') . '?p=' . $template['clientTemplateId'] . '">' . $template['clientTemplateId'] . '</a>'; ?>
					<?php } ?>
					&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
					<?php echo 'Campaign state: ' . $campaignState; ?>
				</div>

				<?php generate_initiative_flags($campaign['id']); ?>
			</div>
			<div class="wizHeader-right">
				<div class="wizHeader-actions">

					<button class="wiz-button green doWizSync" data-campaignIds="<?php echo esc_attr(json_encode(array($campaign['id']))); ?>" data-metricTypes="<?php echo esc_attr(json_encode(array('blast'))); ?>"><i class="fa-solid fa-arrows-rotate"></i>&nbsp;&nbsp;Sync Metrics</button>



					<button class="wiz-button green sync-single-triggered" data-campaignId="<?php echo $campaign['id']; ?>" data-start-date="<?php echo $startDate; ?>" data-end-date="<?php echo $endDate; ?>"><i class="fa-solid fa-arrows-rotate"></i>&nbsp;&nbsp;Sync Engagement Data</button>


					<?php include plugin_dir_path(__FILE__) . 'parts/module-user-settings-form.php'; ?>
				</div>
				<div class="wizHeader-actions-meta">
					<?php
					$lastSyncedOn = $campaign['lastWizSync'] ?? 'never';
					// Display a default message if lastWizSync is not set
					if ($lastSyncedOn === 'never') {
						$displayDate = 'never';
					} else {
						// Create a DateTime object from the UTC date/time
						$utcTime = new DateTime($lastSyncedOn, new DateTimeZone('UTC'));

						// Convert UTC time to Pacific Time
						//$utcTime->setTimeZone(new DateTimeZone('America/Los_Angeles'));

						// Format the date/time in the desired format
						$displayDate = $utcTime->format('m/d/Y, g:ia');
					}
					?>
					Last synced: <?php echo $displayDate; ?>

				</div>
			</div>
		</div>
	</header>

	<div class="entry-content" itemprop="mainContentOfPage">

		<?php if ($campaign['type'] == 'Triggered') {
			//include plugin_dir_path(__FILE__) . 'parts/dashboard-date-buttons.php'; 
			include plugin_dir_path(__FILE__) . 'parts/dashboard-date-pickers.php';
		}
		?>

		<?php
		if ($campaign['type'] == 'Blast') {
			$metricRates = get_idwiz_metric_rates($allCampaignIds, $startDate, $endDate, [$campaign['type']], 'campaignsInDate');
		} else {
			$metricRates = get_idwiz_metric_rates($allCampaignIds, $startDate, $endDate, [$campaign['type']], 'allPurchasesInDate');
		}
		echo get_idwiz_rollup_row($metricRates);
		?>
		<div>
			<?php
			$campaignType = $campaign['type'] == 'Blast' ? 'blast' : 'triggered';
			//$triggeredSends = get_engagement_data_by_campaign_id($allCampaignIds, $campaignType, 'send');



			?>


			<div class="wizcampaign-sections-row">
				<div class="wizcampaign-section">
					<h3>Template(s)</h3>
					<div class="wizcampaign-section-tabs-wrapper">
						<?php
						$displayTemplates = [];
						//$templateName = '';
						if ($experiments) {
							$experimentTemplateIds = array_column($experiments, 'templateId');
							foreach ($experimentTemplateIds as $templateId) {
								$displayTemplates[] = get_idwiz_template($templateId);
							}
							//$templateName = 'Variation';
						} else {
							//$templateName = '';
							$displayTemplates = array($template);
						}

						if (!empty($displayTemplates)) {
							if (count($displayTemplates) > 1) {
						?>
								<div class="wizcampaign-section-tabs" data-pane="campaign-template-tabs">
									<ul>
										<?php foreach ($displayTemplates as $index => $currentTemplate) { ?>
											<li<?php if ($index === 0) echo ' class="active"'; ?> data-tab="campaignTemplate<?php echo $index + 1; ?>">
												<?php
												foreach ($experiments as $experiment) {
													if ($experiment['templateId'] == $currentTemplate['templateId']) {
														//echo $experiment['name'];
														echo $currentTemplate['name'];
														break;
													}
												}
												?>
												</li>
											<?php } ?>
									</ul>
								</div>
								<div class="wizcampaign-section-tabs-pane" id="campaign-template-tabs">
									<?php foreach ($displayTemplates as $index => $currentTemplate) { ?>
										<div class="wizcampaign-section inset wizcampagn-section-tab-content<?php if ($index === 0) echo ' active'; ?>" id="campaignTemplate<?php echo $index + 1; ?>">
											<?php include plugin_dir_path(__FILE__) . 'parts/template-preview-html.php'; ?>
										</div>
									<?php } ?>
								</div>
							<?php
							} else {
								$currentTemplate = $displayTemplates[0];
							?>
								<div class="wizcampaign-section wizcampagn-section-tab-content active">
									<?php include plugin_dir_path(__FILE__) . 'parts/template-preview-html.php'; ?>
								</div>
						<?php
							}
						}
						?>
					</div>
				</div>
				<div class="wizcampaign-section">
					<div class="wizcampaign-section-title-area">
						<h4>Metrics by Date</h4>
						<div class="wizcampaign-section-icons">
							<i class="fa-regular fa-calendar-days chart-timescale-switcher active" data-timescale="daily" title="By Day"></i><i class="fa-solid fa-clock chart-timescale-switcher" data-timescale="hourly" title="By Hour"></i>
						</div>
					</div>
					<div class="wizcampaign-section-tabs-wrapper">
						<div class="wizcampaign-section-tabs" data-pane="campaign-byDate-tabs">
							<ul>

								<li class="active" data-tab="sendsByDateSection">Sends</li>
								<li data-tab="openedByDateSection">Opens</li>
								<li data-tab="clicksByDateSection">Clicks</li>

							</ul>
						</div>
						<div class="wizcampaign-section-tabs-pane" id="campaign-byDate-tabs">

							<div class="wizcampaign-section inset wizcampagn-section-tab-content active" id="sendsByDateSection">
								<div class="wizChartWrapper">
									<canvas class="sendsByDate wiz-canvas" data-timescale="daily" data-chartid="sendsByDate" data-campaignids='<?php echo json_encode($allCampaignIds); ?>' data-charttype="bar" data-campaignType="<?php echo strtolower($campaign['type']); ?>" data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>"></canvas>
								</div>
							</div>

							<div class="wizcampaign-section inset wizcampagn-section-tab-content" id="openedByDateSection">
								<div class="wizChartWrapper">
									<canvas class="opensByDate wiz-canvas" data-timescale="daily" data-chartid="opensByDate" data-campaignids='<?php echo json_encode($allCampaignIds); ?>' data-charttype="bar" data-campaignType="<?php echo strtolower($campaign['type']); ?>" data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>"></canvas>
								</div>
							</div>

							<div class="wizcampaign-section inset wizcampagn-section-tab-content" id="clicksByDateSection">
								<div class="wizChartWrapper">
									<canvas class="clicksByDate wiz-canvas" data-timescale="daily" data-chartid="clicksByDate" data-campaignids='<?php echo json_encode($allCampaignIds); ?>' data-charttype="bar" data-campaignType="<?php echo strtolower($campaign['type']); ?>" data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>"></canvas>
								</div>
							</div>

						</div>
					</div>
					<div class="wizcampaign-section inset" id="purchasesByDateSection">
						<div class="wizcampaign-section-title-area">
							<h4>Purchases</h4>
							<div class="wizcampaign-section-icons">
								<!-- timeScale won't work for purchases since we don't have a time. Maybe in the future we will....-->
								<!--<i class="fa-regular fa-calendar-days chart-timescale-switcher active" data-timescale="daily" title="By Day"></i><i class="fa-solid fa-clock chart-timescale-switcher" data-timescale="hourly" title="By Hour"></i>-->
							</div>
						</div>
						<div class="wizChartWrapper">
							<?php
							// Set up the data attributes
							$purchByDateAtts = [];


							$purchByDateAtts[] = 'data-chartid="purchasesByDate"';

							if ($allCampaignIds) {
								$purchByDateAtts[] = 'data-campaignids=\'' . json_encode($allCampaignIds) . '\'';
							} else {
								$purchByDateAtts[] = 'data-campaignids=\'' . json_encode('') . '\'';
							}

							//$purchByDateAtts[] = 'data-campaignids=\'' . json_encode([]) . '\'';

							if (isset($campaignTypes)) {
								$purchByDateAtts[] = 'data-campaigntypes=\'' . json_encode($campaignTypes) . '\'';
							}

							$purchByDateAtts[] = "data-startdate='{$startDate}'";
							$purchByDateAtts[] = "data-enddate='{$endDate}'";

							$purchByDateAtts[] = 'data-charttype="bar"';

							//$purchByDateAtts[] = 'data-timescale="daily"';

							if (isset($campaignTypes)) {
								$purchByDateAtts[] = 'data-campaigntypes=\'' . json_encode($campaignTypes) . '\'';
							} else {
								$purchByDateAtts[] = 'data-campaigntypes=\'' . json_encode(['Blast', 'Triggered']) . '\'';
							}

							// Convert the array to a string for echoing
							$purchByDateAttsString = implode(' ', $purchByDateAtts);
							?>

							<canvas class="purchByDate wiz-canvas" id="purchasesByDate" <?php echo $purchByDateAttsString; ?>></canvas>

						</div>
					</div>
				</div>

				<div class="wizcampaign-section">
					<h3>Purchases</h3>
					<div class="wizcampaign-section-tabs-wrapper">
						<div class="wizcampaign-section-tabs" data-pane="campaign-purchases-tabs">
							<ul>

								<li class="active" data-tab="purchasesByPurchase" data-colAdjust="true">All</li>
								<li data-tab="purchasesByProduct" data-colAdjust="true">By Product</li>
								<li data-tab="purchasesByLocation" data-colAdjust="true">By Location</li>
								<li data-tab="purchasesByDivision">By Division</li>

							</ul>
						</div>
						<div class="wizcampaign-section-tabs-pane" id="campaign-purchases-tabs">

							<div class="wizcampaign-section inset wizcampagn-section-tab-content active" id="purchasesByPurchase">
								<div class="tinyTableWrapper">
									<?php
									$byProductHeaders = [
										'Product' => '40%',
										'Division' => '15%',
										'Location' => '30%',
										'Revenue' => '10%',
									];

									$purchasesByProduct = generate_purchases_table_data($purchases);

									generate_mini_table($byProductHeaders, $purchasesByProduct);

									?>
								</div>
							</div>

							<div class="wizcampaign-section inset wizcampagn-section-tab-content" id="purchasesByProduct">
								<div class="tinyTableWrapper">
									<?php
									$byProductHeaders = [
										'Product' => '45%',
										'Topics' => '25%',
										'Purchases' => '10%',
										'Revenue' => '20%',
									];

									$purchasesByProduct = transfigure_purchases_by_product($purchases);

									generate_mini_table($byProductHeaders, $purchasesByProduct);

									?>
								</div>
							</div>

							<div class="wizcampaign-section inset wizcampagn-section-tab-content" id="purchasesByLocation">
								<div class="tinyTableWrapper">
									<?php
									// Group purchases by location
									$locationData = idwiz_group_purchases_by_location($purchases);

									// Convert the grouped data into a format suitable for the table generator
									$tableData = [];
									foreach ($locationData as $location => $data) {
										$tableData[] = [
											'Location' => $location,
											'Purchases' => $data['Purchases'],
											'Revenue' => '$' . number_format($data['Revenue'], 2)
										];
									}

									// Define headers for the table
									$headers = [
										'Location' => 'auto',
										'Purchases' => 'auto',
										'Revenue' => 'auto'
									];

									// Generate the table
									generate_mini_table($headers, $tableData);
									?>
								</div>
							</div>

							<div class="wizcampaign-section inset wizcampagn-section-tab-content" id="purchasesByDivision">
								<div class="wizChartWrapper">
									<?php
									// Set up the data attributes
									$purchByDivisionAtts = [];

									$purchByDivisionAtts[] = 'data-campaignids=\'' . json_encode($allCampaignIds) . '\'';

									$purchByDivisionAtts[] = "data-startdate='{$startDate}'";
									$purchByDivisionAtts[] = "data-enddate='{$endDate}'";

									$purchByDivisionAtts[] = 'data-charttype="bar"';

									if (isset($campaignTypes)) {
										$purchByDivisionAtts[] = 'data-campaigntypes=\'' . json_encode($campaignTypes) . '\'';
									} else {
										$purchByDivisionAtts[] = 'data-campaigntypes=\'' . json_encode(['Blast', 'Triggered']) . '\'';
									}

									// Convert the array to a string for echoing
									$purchByDivisionAttsString = implode(' ', $purchByDivisionAtts);
									?>
									<canvas class="purchByDivision wiz-canvas" data-chartid="purchasesByDivision" data-campaignids='<?php echo json_encode($allCampaignIds); ?>' <?php echo $purchByDivisionAttsString; ?>></canvas>
								</div>
							</div>

						</div>
					</div>
					<div class="wizcampaign-section inset">
						<div class="wizcampaign-section-title-area">
							<h4>Promo Code Use</h4>
							<div>
								<?php

								$promoCodeData = prepare_promo_code_summary_data($purchases); ?>
								<?php echo $promoCodeData['ordersWithPromoCount'] ?>/
								<?php echo $promoCodeData['totalOrderCount'] ?> (
								<?php echo $promoCodeData['percentageWithPromo'] ?>%)
							</div>
						</div>
						<div class="tinyTableWrapper">
							<?php generate_mini_table($promoCodeData['promoHeaders'], $promoCodeData['promoData']); ?>
						</div>
					</div>
				</div>

			</div>

		</div>
		<?php


		// Setup standard chart variables
		// $standardChartCampaignIds = $allCampaignIds;
		// $standardChartPurchases = $purchases;
		// include plugin_dir_path(__FILE__) . 'parts/standard-charts.php';

		if ($experiments) {
		?>
			<div class="wizcampaign-section inset wizcampaign-experiments">
				<div class="wizcampaign-experiments-header">
					<h2>Experiment Results</h2>

				</div>
				<div class="wizcampaign-experiment-results">
					<div class="wizcampaign-experiment-metrics">
						<?php
						$metrics = [
							'uniqueEmailSends' => ['label' => 'Sent', 'type' => 'number'],
							'wizDeliveryRate' => ['label' => 'Delivery', 'type' => 'percent'],
							'wizOpenRate' => ['label' => 'Open Rate', 'type' => 'percent'],
							'wizCtr' => ['label' => 'CTR', 'type' => 'percent'],
							'wizCto' => ['label' => 'CTO', 'type' => 'percent'],
							'totalPurchases' => ['label' => 'Purchases', 'type' => 'number'],
							'revenue' => ['label' => 'Revenue', 'type' => 'currency'],
							'wizCvr' => ['label' => 'CVR', 'type' => 'percent'],
							'wizAov' => ['label' => 'AOV', 'type' => 'currency'],
							'wizUnsubRate' => ['label' => 'Unsub. Rate', 'type' => 'percent'],
							'confidence' => ['label' => 'Confidence', 'type' => 'percent'],
							'improvement' => ['label' => 'Improvement', 'type' => 'percent']
						];

						// Calculate max values for each metric
						$maxValues = [];
						$topTwoUniqueValues = [];
						foreach ($metrics as $key => $metric) {
							$values = array_column($experiments, $key);
							arsort($values); // Sort in descending order
							$uniqueValues = array_unique($values);
							$topTwoUnique = array_slice($uniqueValues, 0, 2);
							$topTwoUniqueValues[$key] = $topTwoUnique;

							if (!in_array($key, array('uniqueEmailSends', 'wizUnsubRate'))) {
								$maxValues[$key] = max(array_column($experiments, $key));
							} else if ($key == 'wizUnsubRate') {
								// For unsubs, we flip the max so we highlight the lowest
								$maxValues[$key] = min(array_column($experiments, $key));
							}
						}

						foreach ($experiments as $experiment) {
							$winnerClass = $experiment['wizWinner'] ? 'winner' : '';
						?>
							<div class="wizcampaign-experiment">

								<div class="experiment_var_wrapper <?php echo $winnerClass; ?>">
									<h4>
										<?php echo $experiment['name']; ?>
									</h4>
									<div class="rollup_summary_wrapper">
										<?php
										foreach ($metrics as $key => $metric) {
											$value = $experiment[$key];
											$formattedValue = "";

											switch ($metric['type']) {
												case 'number':
													$formattedValue = number_format($value);
													break;
												case 'percent':
													$formattedValue = number_format($value, 2) . "%";
													break;
												case 'currency':
													$formattedValue = "$" . number_format($value, 0);
													break;
											}

											$epsilon = 0.01; // smallest number above what we'll display as zero
											$highlightClass = '';
											if (isset($maxValues[$key]) && count($topTwoUniqueValues[$key]) > 1) {
												// If both top two unique values are effectively zero, then don't highlight
												if ($topTwoUniqueValues[$key][0] < $epsilon && $topTwoUniqueValues[$key][1] < $epsilon) {
													$highlightClass = '';
												} else {
													$highlightClass = ($value == $maxValues[$key]) ? 'highlight' : '';
												}
											}
										?>
											<div class="metric-item <?php echo $highlightClass; ?>">
												<span class="metric-label">
													<?php echo $metric['label']; ?>
												</span>
												<span class="metric-value">
													<?php echo $formattedValue; ?>
												</span>
											</div>
										<?php } ?>
									</div>

									<div class="mark_as_winner">
										<button class="wiz-button" data-actiontype="<?php echo $winnerClass ? 'remove-winner' : 'add-winner'; ?>" data-experimentid="<?php echo $experiment['experimentId']; ?>" data-templateid="<?php echo $experiment['templateId']; ?>">
											<?php echo $winnerClass ? 'Winner!' : 'Mark as winner'; ?>
										</button>
									</div>
								</div>
							</div>
						<?php
						} // End of foreach loop
						?>


					</div>
					<div class="wizcampaign-experiment-notes" data-experimentid="<?php echo $experiments[0]['experimentId']; ?>">
						<h3>Experiment Notes</h3>
						<?php
						// Ensure $experimentNotes is always set, even if it's an empty string
						$experimentNotes = stripslashes($experiments[0]['experimentNotes'] ?? '');
						?>
						<textarea id="experimentNotes" placeholder="Enter some notes about this experiment..."><?php echo $experimentNotes; ?></textarea>
					</div>

				</div>
			</div>
		<?php
		}
		?>



	</div>

	</div>
</article>
<?php get_footer(); ?>