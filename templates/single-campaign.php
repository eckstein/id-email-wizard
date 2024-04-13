<?php get_header(); ?>
<?php

global $wpdb;

date_default_timezone_set( 'America/Los_Angeles' );

$campaign_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
$campaign = get_idwiz_campaign( $campaign_id );

// Figure out start and end date

// Define default values
$startDate = date( 'Y-m-01' ); // Default start date is the first day of the current month
$endDate = date( 'Y-m-d' );    // Default end date is today

// Check if the startDate and endDate parameters are present in the $_GET array
if ( isset( $_GET['startDate'] ) && $_GET['startDate'] !== '' ) {
	$startDate = $_GET['startDate'];
}
if ( isset( $_GET['endDate'] ) && $_GET['endDate'] !== '' ) {
	$endDate = $_GET['endDate'];
} else {
	if ( isset( $campaign['type'] ) && $campaign['type'] === 'Blast' ) {
		$campaignStartStamp = (int) ( $campaign['startAt'] / 1000 );
		$startDate = $campaignStartStamp > 0 ? date( 'Y-m-d', $campaignStartStamp ) : '2023-11-01';
		$endDate = date( 'Y-m-d' ); // End date is today
	}

}






$metrics = get_idwiz_metric( $campaign['id'] );
$template = get_idwiz_template( $campaign['templateId'] );

// Set the default timezone
date_default_timezone_set( 'America/Los_Angeles' );

$campaignStartStamp = (int) ( $campaign['startAt'] / 1000 );
$campaignEndStamp = (int) ( $campaign['endedAt'] / 1000 );

$campaignStartAt = date( 'm/d/Y g:ia', $campaignStartStamp );
$campaignEndedAt = date( 'g:ia', $campaignEndStamp );
$campaignEndDateTime = date( 'm/d/Y \a\t g:ia', $campaignEndStamp );


// Create formatted end date and time
$campaignEndDateTime = date( 'm/d/Y \a\t g:ia', $campaignEndStamp );

// Get timezone abbreviation from the start time
$sendsTimezone = date( 'T', $campaignStartStamp );

$campaignState = $campaign['campaignState'];

$connectedCampaignIds = !empty(maybe_unserialize( $campaign['connectedCampaigns'] )) ? maybe_unserialize( $campaign['connectedCampaigns'] ) : [];

//Create an array with the current campaign id and all connected campaigns ids
$allCampaignIds = array_merge( [ $campaign_id ], $connectedCampaignIds );

if ( ! empty( $connectedCampaignIds ) ) {
	$connectedCampaigns = get_idwiz_campaigns( [ 
		'campaignIds' => $connectedCampaignIds,
		'fields' => [ 'id', 'name' ]
	] );

}




$purchases = get_idwiz_purchases( [ 'campaignIds' => $allCampaignIds, 'shoppingCartItems_utmMedium' => 'email' ] );

//$experimentIds = maybe_unserialize($campaign['experimentIds']) ?? array();
// This returns one row per experiment TEMPLATE
$experiments = get_idwiz_experiments( array( 'campaignIds' => [ $campaign['id'] ] ) );
// Returns multiple templates with the same experiment ID, so we de-dupe
$experimentIds = array_unique( array_column( $experiments, 'experimentId' ) );
$linkedExperimentIds = array_map( function ($id) {
	return '<a href="https://app.iterable.com/experiments/monitor?experimentId=' . urlencode( $id ) . '">' . htmlspecialchars( $id ) . '</a>';
}, $experimentIds );

?>

<article id="campaign-<?php echo $campaign['id']; ?>" class="wizcampaign-single has-wiz-chart"
	data-campaignid="<?php echo $campaign['id']; ?>">
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
						<?php echo $campaign['type']; ?> <?php echo $campaign['messageMedium']; ?> Campaign <a
							href="https://app.iterable.com/campaigns/<?php echo $campaign['id']; ?>?view=summary">
							<?php echo $campaign['id']; ?></a> within Workflow <a
							href="https://app.iterable.com/workflows/<?php echo $campaign['workflowId']; ?>/edit"><?php echo $campaign['workflowId']; ?></a>

						<?php if ( $experimentIds ) {
							echo '&nbsp;with Experiment ' . implode( ', ', $linkedExperimentIds ) . '</a>';
						} ?>
					</strong>
					&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
					<?php
					if ( ! empty( $connectedCampaignIds ) ) {
						$campaignNames = [];
						foreach ( $connectedCampaigns as $connectedCampaign ) {
							$campaignNames[ $connectedCampaign['id'] ] = $connectedCampaign['name'];
						}

						$campaignIdList = array_map( function ($id) use ($campaignNames) {
							$name = isset ( $campaignNames[ $id ] ) ? htmlspecialchars( $campaignNames[ $id ] ) : '';
							return '<span class="connected-campaign-bubble"><a href="' . get_bloginfo( 'url' ) . '/metrics/campaign?id=' . urlencode( $id ) . '" title="' . $name . '">' . htmlspecialchars( $id ) . '</a><i class="fa-solid fa-xmark disconnect-campaign" data-remove-from="' . intval( $_GET['id'] ) . '" data-campaignid="' . $id . '"></i></span>';
						}, $connectedCampaignIds );


						?>
						<span class="connected-campaigns">Connected to:
							<?php echo implode( ' ', $campaignIdList ); ?></span>
					<?php } ?>
					<a href="#" class="connect-campaigns" data-campaignid="<?php echo $campaign['id']; ?>">Connect
						campaigns</a>

					<br />
					<?php if ( $campaign['type'] == 'Triggered' ) {
						echo 'Last ';
					} ?>Sent on
					<?php echo $campaignStartAt; ?>
					<?php
					if ( $campaignStartAt != $campaignEndDateTime && $campaign['type'] == 'Blast' ) {
						echo "— $campaignEndedAt";
					}
					echo '&nbsp;' . $sendsTimezone;
					?>




					<?php
					if ( isset( $template['clientTemplateId'] ) ) { ?>
						&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
						<?php echo 'Wiz Template: <a href="' . get_bloginfo( 'url' ) . '?p=' . $template['clientTemplateId'] . '">' . $template['clientTemplateId'] . '</a>'; ?>
					<?php } ?>
					&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
					<?php echo 'Campaign state: ' . $campaignState; ?>
				</div>

				<?php generate_initiative_flags( $campaign['id'] ); ?>
			</div>
			<div class="wizHeader-right">
				<div class="wizHeader-actions">

					<button class="wiz-button green doWizSync"
						data-campaignIds="<?php echo esc_attr( json_encode( array( $campaign['id'] ) ) ); ?>"
						data-metricTypes="<?php echo esc_attr( json_encode( array( 'blast' ) ) ); ?>"><i
							class="fa-solid fa-arrows-rotate"></i>&nbsp;&nbsp;Sync Campaign</button>

					<?php if ( $campaign['type'] == 'Triggered' ) { ?>

						<button class="wiz-button green sync-single-triggered"
							data-campaignId="<?php echo $campaign['id']; ?>" data-start-date="<?php echo $startDate; ?>"
							data-end-date="<?php echo $endDate; ?>"><i
								class="fa-solid fa-arrows-rotate"></i>&nbsp;&nbsp;Sync Triggered Data</button>
					<?php } ?>

					<?php include plugin_dir_path( __FILE__ ) . 'parts/module-user-settings-form.php'; ?>
				</div>
				<div class="wizHeader-actions-meta">
					<?php
					$lastSyncedOn = $campaign['last_wiz_update'] ?? 'never';
					if ( $lastSyncedOn != 'never' ) {
						$lastSyncedOn = date( 'm/d/Y, g:ia', strtotime( $lastSyncedOn ) );
					}
					?>
					Last synced:
					<?php echo $lastSyncedOn; ?>
				</div>
			</div>
		</div>
	</header>

	<div class="entry-content" itemprop="mainContentOfPage">

		<?php if ( $campaign['type'] == 'Triggered' ) {
			//include plugin_dir_path(__FILE__) . 'parts/dashboard-date-buttons.php'; 
			include plugin_dir_path( __FILE__ ) . 'parts/dashboard-date-pickers.php';

		}
		?>

		<?php
		$metricRates = get_idwiz_metric_rates( $allCampaignIds, $startDate, $endDate, [ $campaign['type'] ] );
		echo get_idwiz_rollup_row( $metricRates );

		//if ( $journeyPostId ) {
		//echo generate_journey_timeline_html( $journeyPostId, $startDate, $endDate, [$campaign['id']] );
		//}
		?>

		<?php
		if ( $campaign['type'] == 'Triggered' ) {
			?>
			<div>
				<?php
				$triggeredSends = get_triggered_data_by_campaign_id( $allCampaignIds, 'send' );
				if ( ! empty( $triggeredSends ) ) {
					?>


					<div class="wizcampaign-sections-row">
						<div class="wizcampaign-section inset" id="sendsByDateSection">
							<div class="wizcampaign-section-title-area">
								<h4>Sends by Date</h4>
							</div>
							<div class="wizChartWrapper">


								<canvas class="sendsByDate wiz-canvas" data-chartid="sendsByDate"
									data-campaignids='<?php echo json_encode( $allCampaignIds ); ?>' data-charttype="bar"
									data-startdate="<?php echo $startDate; ?>" data-year-over-year="true"
									data-enddate="<?php echo $endDate; ?>"></canvas>
							</div>
						</div>

						<div class="wizcampaign-section inset" id="openedByDateSection">
							<div class="wizcampaign-section-title-area">
								<h4>Opens by Date</h4>
							</div>
							<div class="wizChartWrapper">


								<canvas class="opensByDate wiz-canvas" data-chartid="opensByDate"
									data-campaignids='<?php echo json_encode( $allCampaignIds ); ?>' data-charttype="bar"
									data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>"></canvas>
							</div>
						</div>

						<div class="wizcampaign-section inset" id="clicksByDateSection">
							<div class="wizcampaign-section-title-area">
								<h4>Clicks by Date</h4>
							</div>
							<div class="wizChartWrapper">


								<canvas class="clicksByDate wiz-canvas" data-chartid="clicksByDate"
									data-campaignids='<?php echo json_encode( $allCampaignIds ); ?>' data-charttype="bar"
									data-startdate="<?php echo $startDate; ?>" data-enddate="<?php echo $endDate; ?>"></canvas>
							</div>
						</div>
					</div>

					<?php
				}
				?>
			</div>
			<?php
		}

		// Setup standard chart variables
		$standardChartCampaignIds = $allCampaignIds;
		$standardChartPurchases = $purchases;
		include plugin_dir_path( __FILE__ ) . 'parts/standard-charts.php';

		if ( $experiments ) {
			?>
			<div class="wizcampaign-section inset wizcampaign-experiments">
				<div class="wizcampaign-experiments-header">
					<h2>Experiment Results</h2>

				</div>
				<div class="wizcampaign-experiment-results">
					<div class="wizcampaign-experiment-metrics">
						<?php
						$metrics = [ 
							'uniqueEmailSends' => [ 'label' => 'Sent', 'type' => 'number' ],
							'wizOpenRate' => [ 'label' => 'Open Rate', 'type' => 'percent' ],
							'wizCtr' => [ 'label' => 'CTR', 'type' => 'percent' ],
							'wizCto' => [ 'label' => 'CTO', 'type' => 'percent' ],
							'totalPurchases' => [ 'label' => 'Purchases', 'type' => 'number' ],
							'revenue' => [ 'label' => 'Revenue', 'type' => 'currency' ],
							'wizCvr' => [ 'label' => 'CVR', 'type' => 'percent' ],
							'wizAov' => [ 'label' => 'AOV', 'type' => 'currency' ],
							'wizUnsubRate' => [ 'label' => 'Unsub. Rate', 'type' => 'percent' ],
							'confidence' => [ 'label' => 'Confidence', 'type' => 'percent' ],
							'improvement' => [ 'label' => 'Improvement', 'type' => 'percent' ]
						];

						// Calculate max values for each metric
						$maxValues = [];
						$topTwoUniqueValues = [];
						foreach ( $metrics as $key => $metric ) {
							$values = array_column( $experiments, $key );
							arsort( $values ); // Sort in descending order
							$uniqueValues = array_unique( $values );
							$topTwoUnique = array_slice( $uniqueValues, 0, 2 );
							$topTwoUniqueValues[ $key ] = $topTwoUnique;

							if ( ! in_array( $key, array( 'uniqueEmailSends', 'wizUnsubRate' ) ) ) {
								$maxValues[ $key ] = max( array_column( $experiments, $key ) );
							} else if ( $key == 'wizUnsubRate' ) {
								// For unsubs, we flip the max so we highlight the lowest
								$maxValues[ $key ] = min( array_column( $experiments, $key ) );
							}
						}

						foreach ( $experiments as $experiment ) {
							$winnerClass = $experiment['wizWinner'] ? 'winner' : '';
							?>
							<div class="wizcampaign-experiment">

								<div class="experiment_var_wrapper <?php echo $winnerClass; ?>">
									<h4>
										<?php echo $experiment['name']; ?>
									</h4>
									<div class="rollup_summary_wrapper">
										<?php
										foreach ( $metrics as $key => $metric ) {
											$value = $experiment[ $key ];
											$formattedValue = "";

											switch ( $metric['type'] ) {
												case 'number':
													$formattedValue = number_format( $value );
													break;
												case 'percent':
													$formattedValue = number_format( $value, 2 ) . "%";
													break;
												case 'currency':
													$formattedValue = "$" . number_format( $value, 0 );
													break;
											}

											$epsilon = 0.01; // smallest number above what we'll display as zero
											$highlightClass = '';
											if ( isset( $maxValues[ $key ] ) && count( $topTwoUniqueValues[ $key ] ) > 1 ) {
												// If both top two unique values are effectively zero, then don't highlight
												if ( $topTwoUniqueValues[ $key ][0] < $epsilon && $topTwoUniqueValues[ $key ][1] < $epsilon ) {
													$highlightClass = '';
												} else {
													$highlightClass = ( $value == $maxValues[ $key ] ) ? 'highlight' : '';
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
										<button class="wiz-button"
											data-actiontype="<?php echo $winnerClass ? 'remove-winner' : 'add-winner'; ?>"
											data-experimentid="<?php echo $experiment['experimentId']; ?>"
											data-templateid="<?php echo $experiment['templateId']; ?>">
											<?php echo $winnerClass ? 'Winner!' : 'Mark as winner'; ?>
										</button>
									</div>
								</div>
							</div>
							<?php
						} // End of foreach loop
						?>


					</div>
					<div class="wizcampaign-experiment-notes"
						data-experimentid="<?php echo $experiments[0]['experimentId']; ?>">
						<h3>Experiment Notes</h3>
						<?php
						// Ensure $experimentNotes is always set, even if it's an empty string
						$experimentNotes = stripslashes( $experiments[0]['experimentNotes'] ?? '' );
						?>
						<textarea id="experimentNotes"
							placeholder="Enter some notes about this experiment..."><?php echo $experimentNotes; ?></textarea>
					</div>

				</div>
			</div>
			<?php
		}
		?>

		<div class="wizcampaign-template-area">

			<?php
			$displayTemplates = [];
			$templateName = '';
			if ( $experiments ) {
				$experimentTemplateIds = array_column( $experiments, 'templateId' );
				foreach ( $experimentTemplateIds as $templateId ) {
					$displayTemplates[] = get_idwiz_template( $templateId );
				}
				$templateName = 'Variation';
			} else {
				$templateName = '';
				$displayTemplates = array( $template );
			}

			if ( ! empty( $displayTemplates ) ) {

				foreach ( $displayTemplates as $currentTemplate ) {
					if ( ! $currentTemplate ) {
						continue;
					}


					foreach ( $experiments as $experiment ) {
						if ( $experiment['templateId'] == $currentTemplate['templateId'] ) {
							$templateName = $experiment['name'];
							break;
						}
					}

					?>

					<?php include plugin_dir_path( __FILE__ ) . 'parts/template-preview-html.php'; ?>
					<?php

				}
			}
			?>
		</div>

	</div>

	</div>
</article>
<?php get_footer(); ?>