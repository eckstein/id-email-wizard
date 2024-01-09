<?php
function update_journey_meta_on_acf_save( $post_id ) {
	// Ensure it's the "journey" post type
	if ( get_post_type( $post_id ) != 'journey' ) {
		return;
	}

	// Get the field object using the field name
	$field = get_field_object( 'workflow_ids', $post_id );
	if ( ! $field ) {
		return; // Exit if the field does not exist
	}
	$field_key = $field['key'];

	// Check if 'workflow_ids' field is being updated
	if ( isset( $_POST['acf'][ $field_key ] ) ) {
		$new_workflow_ids_raw = $_POST['acf'][ $field_key ];
		if ( ! is_array( $new_workflow_ids_raw ) ) {
			return;
		}

		// Extract the values directly from each row's array
		$new_workflow_ids = array_map( function ($item) {
			return array_values( $item )[0]; // Extract the first value from each row's array
		}, $new_workflow_ids_raw );

		$campaignIds = [];
		$earliestSend = PHP_INT_MAX;
		$latestSend = PHP_INT_MIN;

		// Process the new workflow IDs
		foreach ( $new_workflow_ids as $workflowId ) {
			$campaigns = get_idwiz_campaigns( [ 'workflowId' => $workflowId, 'fields' => [ 'id' ], 'sortBy' => 'startAt', 'sort' => 'ASC' ] );
			foreach ( $campaigns as $campaign ) {
				$campaignId = $campaign['id'];
				$campaignIds[] = $campaignId;

				$sends = get_idemailwiz_triggered_data( 'idemailwiz_triggered_sends', [ 'campaignIds' => [ $campaignId ] ] );
				foreach ( $sends as $send ) {
					$sendAt = $send['startAt'];
					$earliestSend = min( $earliestSend, $sendAt );
					$latestSend = max( $latestSend, $sendAt );
				}
			}
		}



		// Check if valid sends were found before updating post meta
		if ( $earliestSend != PHP_INT_MAX ) {
			update_post_meta( $post_id, 'earliest_send', $earliestSend );
		}
		if ( $latestSend != PHP_INT_MIN ) {
			update_post_meta( $post_id, 'latest_send', $latestSend );
		}
		update_post_meta( $post_id, 'journey_campaign_ids', $campaignIds );
		
		
	}
}

add_action( 'acf/save_post', 'update_journey_meta_on_acf_save', 5 );

function update_journey_meta_cron() {
	$allJourneys = get_posts( [ 'post_type' => 'journey', 'posts_per_page' => -1 ] );
	foreach ( $allJourneys as $journey ) {
		update_journey_meta_on_acf_save( $journey->ID );
	}
}

if ( ! wp_next_scheduled( 'update_journey_meta_cron' ) ) {

	wp_schedule_event( time(), 'hourly', 'update_journey_meta_cron' );

}
add_action( 'update_journey_meta_cron', 'update_journey_meta_cron' );


function idemailwiz_update_journey_campaigns_order() {
	check_ajax_referer( 'journeys', 'security' );

	if ( ! isset( $_POST['postId'], $_POST['journeyCampaignIds'] ) ) {
		wp_send_json_error( 'Missing required parameters' );
		return;
	}

	$post_id = intval( $_POST['postId'] );
	$journeyCampaignIds = is_array( $_POST['journeyCampaignIds'] ) ? array_map( 'intval', $_POST['journeyCampaignIds'] ) : [];

	if ( ! $post_id || empty( $journeyCampaignIds ) ) {
		wp_send_json_error( 'Invalid data' );
		return;
	}

	$update_result = update_post_meta( $post_id, 'journey_campaign_ids', $journeyCampaignIds );

	if ( $update_result ) {
		wp_send_json_success( 'Campaign order updated' );
	} else {
		wp_send_json_error( 'Failed to update campaign order' );
	}
}

add_action( 'wp_ajax_idemailwiz_update_journey_campaigns_order', 'idemailwiz_update_journey_campaigns_order' );

function update_journey_campaign_visibility() {

	if ( ! check_ajax_referer( 'journeys', 'security', false ) ) {
		wp_send_json_error( 'Nonce check failed' );
		return;
	}

	$postId = intval( $_POST['postId'] );
	$campaignId = intval( $_POST['campaignId'] );
	$action = $_POST['metaAction'];

	if ( ! $postId ) {
		wp_send_json_error( 'Post ID is missing.' );
		return;
	}
	if ( ! $campaignId ) {
		wp_send_json_error( 'Campaign ID is missing.' );
		return;
	}
	if ( ! in_array( $action, [ 'hide', 'show' ] ) ) {
		wp_send_json_error( 'Invalid action specified.' );
		return;
	}

	$allJourneyCampaigns = get_post_meta( $postId, 'journey_campaign_ids', true ) ?? [];
	if ( ! is_array( $allJourneyCampaigns ) ) {
		wp_send_json_error( 'Invalid campaign data.' );
		return;
	}

	$journeyHiddenCampaignIds = is_array( get_post_meta( $postId, 'journey_hidden_campaign_ids', true ) ) ? get_post_meta( $postId, 'journey_hidden_campaign_ids', true ) : [];


	if ( $action == 'hide' ) {
		// Check if campaign is already hidden
		if ( in_array( $campaignId, $journeyHiddenCampaignIds ) ) {
			wp_send_json_error( 'Campaign is already hidden' );
			return;
		}

		// Add campaign to hidden list
		$journeyHiddenCampaignIds[] = $campaignId;

	} else {
		// Remove campaign from hidden list
		$key = array_search( $campaignId, $journeyHiddenCampaignIds );
		if ( $key !== false ) {
			unset( $journeyHiddenCampaignIds[ $key ] );
			$journeyHiddenCampaignIds = array_values( $journeyHiddenCampaignIds ); // Reindex the array

			// Move campaign to first position in allJourneyCampaigns
			$key = array_search( $campaignId, $allJourneyCampaigns );
			unset( $allJourneyCampaigns[ $key ] );
			array_unshift( $allJourneyCampaigns, $campaignId );



		} else {
			wp_send_json_error( 'Campaign is already not hidden' );
			return;
		}
	}

	$result = update_post_meta( $postId, 'journey_hidden_campaign_ids', $journeyHiddenCampaignIds );
	$updatedHiddenCampaignIds = get_post_meta( $postId, 'journey_hidden_campaign_ids', true ) ?? [];

	$visibleCampaignIds = array_diff( $allJourneyCampaigns, $updatedHiddenCampaignIds );

	// Check if visibleCampaignIds is empty or null
	if ( empty( $visibleCampaignIds ) ) {
		$visibleCampaignIds = []; // Ensure it's an empty array instead of null
	}

	if ( $result ) {
		$message = $action === 'hide' ? 'Campaign hidden successfully.' : 'Campaign shown successfully.';
		wp_send_json_success( [ 
			'newCounts' => [ 
				'hidden' => count( $updatedHiddenCampaignIds ),
				'total' => count( $allJourneyCampaigns ),
				'visible' => count( $visibleCampaignIds )
			],
			'visibleCampaigns' => $visibleCampaignIds
		] );
	} else {
		$message = $action === 'hide' ? 'No changes made. Campaign was already hidden.' : 'No changes made. Campaign was already shown.';
		wp_send_json_error( [ 'message' => $message, 'data' => $result ] );
	}
}

add_action( 'wp_ajax_update_journey_campaign_visibility', 'update_journey_campaign_visibility' );


function get_journey_campaign_single_metric_data( $campaignId, $dataType ) {
	$triggeredData = get_idemailwiz_triggered_data( 'idemailwiz_triggered_' . $dataType, [ 'campaignIds' => [ $campaignId ] ] );
	return $triggeredData;
}

function generate_journey_campaign_send_cell_data( $campaignId, $sendData, $dateString ) {
	// Initialize metrics for sends
	$totalDateSends = 0;
	$dateMessageOpens = 0;
	$dateMessageClicks = 0;
	$messageIdsForDate = [];

	// Count sends for this date and collect message IDs
	foreach ( $sendData['sends'] as $send ) {
		$sendDate = new DateTimeImmutable( date( 'Y-m-d', $send['startAt'] / 1000 ) );
		if ( $sendDate->format( 'Y-m-d' ) === $dateString ) {
			$totalDateSends++;
			$messageIdsForDate[] = $send['messageId'];
		}
	}

	$thisCampaignDateOpens = get_idemailwiz_triggered_data( 'idemailwiz_triggered_opens', [ 'campaignIds' => [ $campaignId ] ] );
	$thisCampaignDateClicks = get_idemailwiz_triggered_data( 'idemailwiz_triggered_clicks', [ 'campaignIds' => [ $campaignId ] ] );

	// Count opens and clicks for message IDs sent on this date
	foreach ( $thisCampaignDateOpens as $open ) {
		if ( in_array( $open['messageId'], $messageIdsForDate ) ) {
			$dateMessageOpens++;
		}
	}

	foreach ( $thisCampaignDateClicks as $click ) {
		if ( in_array( $click['messageId'], $messageIdsForDate ) ) {
			$dateMessageClicks++;
		}
	}
	return [ 'dateCampaignSends' => $totalDateSends, 'messageOpens' => $dateMessageOpens, 'messageClicks' => $dateMessageClicks ];
}
function get_journey_campaign_sends_data($post_id, $campaignId, $startDate, $endDate) {
	//$journeyCampaignIds = get_filtered_journey_campaigns($post_id);
	$result = ['sends' => [], 'opens' => [], 'clicks' => []];

	//foreach ($journeyCampaignIds as $campaignId) {
		$offset = 0;
		$batchSize = 10000;
		$allTriggeredSends = [];

		do {
			$triggeredSends = get_idemailwiz_triggered_data('idemailwiz_triggered_sends', [ 
				'campaignIds' => [$campaignId],
				'startAt_start' => $startDate,
				'startAt_end' => $endDate,
				'fields' => ['messageId', 'startAt', 'campaignId'],
				'batchSize' => $batchSize,
				'offset' => $offset
			]);

			if (is_array($triggeredSends) && !empty($triggeredSends)) {
				// Append each element of $triggeredSends to $allTriggeredSends
				foreach ($triggeredSends as $send) {
					$allTriggeredSends[] = $send;
				}

				// Free up memory
				unset($triggeredSends);
			}

			$offset += $batchSize;
		} while (!empty($triggeredSends));

		// Accumulate the results
		$result['sends'] = $allTriggeredSends;
	//}

	return $result;
}





function generate_journey_campaigns_data_array( $post_id, $campaignId, $startDate, $endDate, $campaignSends = [] ) {

	$campaignSendData = get_journey_campaign_sends_data( $post_id, $campaignId, $startDate, $endDate );

	//foreach ( $journeyCampaignIds as $campaignId ) {
		// Initialize the array for this campaignId
		//if ( ! isset( $campaignSends[ $campaignId ] ) ) {
			$campaignSends = [ 
				'sendDates' => [],
				'messageIds' => [],
				'sends' => [],
				'sendOpens' => [],
				'sendClicks' => []
			];
		//}

		// Convert startAt and endAt dates to DateTime objects for comparison
		$startDateObj = new DateTime( $startDate );
		$endDateObj = new DateTime( $endDate );

		// Process sends within date range for this campaign ID
		foreach ( $campaignSendData['sends'] as $send ) {
			$sendDate = new DateTime( date( 'Y-m-d', $send['startAt'] / 1000 ) );
			// Check if the send date is within the desired date range
			if ( $sendDate >= $startDateObj && $sendDate <= $endDateObj ) {
				$sendDateString = $sendDate->format( 'Y-m-d' );
				$messageId = $send['messageId'];

				if ( ! in_array( $sendDateString, $campaignSends['sendDates'] ) ) {
					$campaignSends['sendDates'][] = $sendDateString;
				}
				if ( ! in_array( $messageId, $campaignSends['messageIds'] ) ) {
					$campaignSends['messageIds'][] = $messageId;
					$campaignSends['sends'][] = $send;
				}
			}
		}

		// Collect all opens and clicks that correspond to the messageIds for this campaign
		if ( isset( $campaignSendData['opens'] ) ) {
			foreach ( $campaignSendData['opens'] as $open ) {
				if ( in_array( $open['messageId'], $campaignSends['messageIds'] ) ) {
					$campaignSends['sendOpens'][] = $open;
				}
			}
		}
		if ( isset( $campaignSendData['clicks'] ) ) {
			foreach ( $campaignSendData['clicks'] as $click ) {
				if ( in_array( $click['messageId'], $campaignSends['messageIds'] ) ) {
					$campaignSends['sendClicks'][] = $click;
				}
			}
		}
	//}

	return $campaignSends;
}


function get_journey_total_send_days_to_show( $post_id, $startDate, $endDate ) {
	$firstJourneySend = get_post_meta( $post_id, 'earliest_send', true );
	$lastJourneySend = get_post_meta( $post_id, 'latest_send', true );

	// Start and end dates
	$firstSendObj = new DateTime( date( 'Y-m-d', $firstJourneySend / 1000 ) );
	$lastSendObj = new DateTime( date( 'Y-m-d', $lastJourneySend / 1000 ) );

	// Determine number of columns (days) we need to show
	// Determine if the current startDate is after the firstSend date
	$startDateDT = new DateTimeImmutable( $startDate );
	$endDateDT = new DateTimeImmutable( $endDate );
	if ( $startDateDT > $firstSendObj ) {
		$showStartDate = $startDateDT;
	} else {
		$showStartDate = $firstSendObj;
	}
	if ( $endDateDT < $lastSendObj ) {
		$showEndDate = $endDateDT;
	} else {
		$showEndDate = $lastSendObj;
	}

	// Calculate the interval and the number of days
	$sendInterval = $showStartDate->diff( $showEndDate );
	$totalSendDays = $sendInterval->days + 1;

	return $totalSendDays;
}

function get_filtered_journey_campaigns( $post_id ) {
	$allJourneyCampaignIds = get_post_meta( $post_id, 'journey_campaign_ids', true );
	$journeyHiddenCampaignIds = is_array( get_post_meta( $post_id, 'journey_hidden_campaign_ids', true ) ) ? get_post_meta( $post_id, 'journey_hidden_campaign_ids', true ) : [];

	return array_diff( $allJourneyCampaignIds, $journeyHiddenCampaignIds );
}

function generate_journey_timeline_html( $post_id, $startDate, $endDate ) {
	ob_start(); // Start output buffering

	//Get journey campaigns, excluding hidding ones
	$journeyCampaignIds = get_filtered_journey_campaigns( $post_id );
	$totalSendDays = get_journey_total_send_days_to_show( $post_id, $startDate, $endDate )
		?>
	<div class="journey-timeline-scrollWrap idwiz-dragScroll">
		<table class="journey-timeline " data-post-id="<?php echo $post_id; ?>" data-start-date="<?php echo $startDate; ?>"
			data-end-date="<?php echo $endDate; ?>" cellspacing="0">

			<?php
			echo get_journey_timeline_header_row( $post_id, $startDate, $endDate );
			foreach ( $journeyCampaignIds as $campaignId ) {
				//echo get_journey_timeline_campaign_rows( $post_id, [ $campaignId ], $startDate, $endDate );
				?>
				<tr class="timeline-campaign-row loading" data-campaign-id='<?php echo $campaignId; ?>'
					data-post-id='<?php echo $post_id; ?>' data-start-date="<?php echo $startDate; ?>"
					data-end-date="<?php echo $endDate; ?>">

					<td class="timeline-campaign-fixedCol">
						<div class="timeline-campaign-fixedCol-flexWrap">
							<div class="timeline-campaign-row-actions">
								<i title="Hide this campaign from the timeline view"
									class="fa-solid fa-eye-slash hide-journey-campaign"></i><i class="fa-solid fa-up-down"></i>
							</div>
							<h4>
								<a href="<?php echo get_bloginfo( 'url' ) . '/metrics/campaign?id=' . $campaignId; ?>">
									<?php echo get_idwiz_campaign( $campaignId )['name']; ?>
								</a>
							</h4>
						</div>
					</td>
					<td colspan="<?php echo $totalSendDays; ?>"><i class="fa-solid fa-spin fa-spinner"></i></td>
				</tr>
				<?php
			}
			?>
		</table>
	</div>
	<?php

	return ob_get_clean(); // Return the buffered content
}

function get_unhide_hidden_journey_campaigns_select_ajax() {
	$post_id = $_POST['postId'];
	$startDate = $_POST['startDate'];
	$endDate = $_POST['endDate'];
	if ( isset( $_POST['campaignIds'] ) || isset( $_POST['campaignId'] ) || isset( $_POST['campaign'] ) ) {
		wp_send_json_error( 'Data is missing from requets.' );
	}

	$html = get_unhide_hidden_journey_campaigns_select( $post_id, $startDate, $endDate );
	if ( $html ) {
		wp_send_json_success( [ 'message' => 'HTML retrieved for dropdown', 'html' => $html ] );
	} else {
		wp_send_json_error( 'No hidden campaigns found.' );
	}
}
add_action( 'wp_ajax_get_unhide_hidden_journey_campaigns_select_ajax', 'get_unhide_hidden_journey_campaigns_select_ajax' );

function get_unhide_hidden_journey_campaigns_select( $post_id, $startDate, $endDate ) {
	ob_start();
	?>
	<select class="show-hidden-journey-campaigns" data-post-id="<?php echo $post_id; ?>"
		data-start-date="<?php echo $startDate; ?>" data-end-date="<?php echo $endDate; ?>">
		<option selected disabled>Unhide hidden campaigns</option>
		<?php
		if ( get_post_meta( $post_id, 'journey_hidden_campaign_ids', true ) ) {
			$allHiddenJourneyCampaignIds = get_post_meta( $post_id, 'journey_hidden_campaign_ids', true );

			//echo "<option value='showAll'>Unhide All</option>";
			foreach ( $allHiddenJourneyCampaignIds as $campaignId ) {
				$campaignName = get_idwiz_campaign( $campaignId )['name'];
				echo "<option value='$campaignId'>$campaignName</option>";
			}
		} else {
			echo "<option disabled>No campaigns are hidden</option>";
		}
		?>
	</select>
	<?php
	return ob_get_clean();
}

function get_journey_timeline_header_row( $post_id, $startDate, $endDate ) {
	$totalSendDays = get_journey_total_send_days_to_show( $post_id, $startDate, $endDate );
	ob_start();
	?>
	<tr class="timeline-campaign-row date-row">
		<td class="timeline-campaign-fixedCol spacer">
			<div class="timeline-campaign-fixedCol-flexWrap">
				<?php echo get_unhide_hidden_journey_campaigns_select( $post_id, $startDate, $endDate ); ?>
			</div>
		</td>
		<?php
		$startDateObj = new DateTimeImmutable( $startDate );
		for ( $day = 0; $day < $totalSendDays; $day++ ) {
			$cellDate = ( clone $startDateObj )->modify( "+$day day" );
			echo "<td class='timeline-cell send-date'>" . $cellDate->format( 'D\<\/\b\r\>n/j<\/\b\r\>Y' ) . "</td>";
		}
		?>
	</tr>
	<?php
	return ob_get_clean();
}
function ajax_generate_journey_timeline_row() {
	$post_id = $_POST['postId'];
	$campaignId = $_POST['campaignId'];
	$startDate = $_POST['startDate'];
	$endDate = $_POST['endDate'];
	if ( ! $campaignId || ! $startDate || ! $endDate || ! $post_id ) {
		wp_send_json_error( 'Missing required data' );
	}


	$html = get_journey_timeline_campaign_rows( $post_id, [ $campaignId ], $startDate, $endDate, false );

	if ( $html ) {
		wp_send_json_success( [ 'message' => 'Row HTML retrieved', 'html' => $html ] );
	} else {
		wp_send_json_error( [ 'message' => 'No row HTML retrieved', 'html' => '' ] );
	}

}
add_action( 'wp_ajax_ajax_generate_journey_timeline_row', 'ajax_generate_journey_timeline_row' );


function get_journey_timeline_campaign_rows( $post_id, $campaignIds, $startDate, $endDate, $asNew = false ) {

	$totalSendDays = get_journey_total_send_days_to_show( $post_id, $startDate, $endDate );

	ob_start();

	foreach ( $campaignIds as $campaignId ) {
		$sendData = generate_journey_campaigns_data_array( $post_id, $campaignId, $startDate, $endDate );

		$wizCampaign = get_idwiz_campaign( $campaignId );
		?>
		<tr class="timeline-campaign-row <?php echo $asNew ? 'showAsNew' : ''; ?>" data-campaign-id='<?php echo $campaignId; ?>'
			data-post-id='<?php echo $post_id; ?>' data-start-date="<?php echo $startDate; ?>"
			data-end-date="<?php echo $endDate; ?>">
			<td class="timeline-campaign-fixedCol">
				<div class="timeline-campaign-fixedCol-flexWrap">
					<div class="timeline-campaign-row-actions">
						<i title="Hide this campaign from the timeline view"
							class="fa-solid fa-eye-slash hide-journey-campaign"></i><i class="fa-solid fa-up-down"></i>
					</div>
					<h4>
						<a href="<?php echo get_bloginfo( 'url' ) . '/metrics/campaign?id=' . $wizCampaign['id']; ?>">
							<?php echo $wizCampaign['name']; ?>
						</a>
					</h4>
				</div>
			</td>
			<?php
			$startDateObject = new DateTimeImmutable( $startDate );
			for ( $day = 0; $day < $totalSendDays; $day++ ) {
				$cellDate = ( clone $startDateObject )->modify( "+$day day" );
				$sendDateString = $cellDate->format( 'Y-m-d' );


				$cellData = generate_journey_campaign_send_cell_data( $campaignId, $sendData, $sendDateString );

				$activeCell = in_array( $sendDateString, $sendData['sendDates'] ) ? true : false;

				echo generate_journey_campaign_date_cell( $campaignId, $cellData, $sendDateString, $endDate, $activeCell );

				?>

				<?php
			}
			?>
		</tr>
		<?php
	}
	return ob_get_clean();
}

function generate_journey_campaign_date_cell( $campaignId, $cellData, $sendDateString, $endDate, $activeCell = false ) {

	ob_start();
	?>
	<td class='timeline-cell <?php echo $activeCell ? 'active' : ''; ?>'>
		<?php
		if ( $activeCell ) {
			echo '<a target="_blank" href="' . get_bloginfo( 'url' ) . '/metrics/campaign?id=' . $campaignId . '&startDate=' . $sendDateString . '&endDate=' . $endDate . '" class="timeline-cell-link"></a><i class="fa-regular fa-envelope"></i>';
			?>
			<div class="timeline-cell-popup">
				<div class="timeline-cell-popup-title">
					<?php echo $sendDateString; ?>
				</div>
				<div class="timeline-cell-popup-content">
					Sends:
					<?php echo $cellData['dateCampaignSends']; ?><br />
					Opens:
					<?php echo $cellData['messageOpens']; ?> (
					<?php echo ( $cellData['dateCampaignSends'] > 0 ? number_format( $cellData['messageOpens'] / $cellData['dateCampaignSends'] * 100, 2 ) : '0' ) . '%'; ?>)<br />
					Clicks:
					<?php echo $cellData['messageClicks']; ?> (
					<?php echo ( $cellData['messageClicks'] > 0 ? number_format( $cellData['messageClicks'] / $cellData['dateCampaignSends'] * 100, 2 ) : '0' ) . '%'; ?>)
				</div>
			</div>
		<?php } ?>
	</td>
	<?php
	return ob_get_clean();
}


