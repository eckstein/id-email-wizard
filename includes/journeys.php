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

function update_journey_send_times() {
	global $wpdb;
	$workflows_table = $wpdb->prefix . 'idemailwiz_workflows';
	$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
	$sends_table = $wpdb->prefix . 'idemailwiz_triggered_sends';

	$workflows = $wpdb->get_results( "SELECT DISTINCT workflowId FROM $campaigns_table", ARRAY_A );

	foreach ( $workflows as $workflow ) {
		$workflowId = $workflow['workflowId'];

		// Optimized query to get the earliest and latest sendAt times for a given workflowId
		$times = $wpdb->get_row( $wpdb->prepare( "
			SELECT MIN(startAt) as firstSend, MAX(startAt) as lastSend
			FROM $sends_table
			WHERE campaignId IN (
				SELECT id FROM $campaigns_table WHERE workflowId = %d
			)",
			$workflowId
		), ARRAY_A );

		if ( $times ) {
			$wpdb->update(
				$workflows_table,
				[ 'firstSendAt' => $times['firstSend'], 
				'lastSendAt' => $times['lastSend'] ],
				[ 'workflowId' => $workflowId ],
				[ '%s', '%s', '%d' ]
			);
		}
	}
}

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'update_journey_send_times_hook' ) ) {
		wp_schedule_event( time(), 'hourly', 'update_journey_send_times_hook' );
	}
} );

add_action( 'update_journey_send_times_hook', 'update_journey_send_times' );



function upsert_workflow( $workflowId, $workflowName = '' ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'idemailwiz_workflows';

	if ( $workflowId > 0 ) {
		if ( empty( $workflowName ) ) {
			$workflowName = 'Journey ' . $workflowId;
		}

		$wpdb->query( $wpdb->prepare( "
		INSERT INTO $table_name (workflowId, workflowName)
		VALUES (%d, %s)
		ON DUPLICATE KEY UPDATE workflowName = %s",
			$workflowId, $workflowName, $workflowName
		) );
	}
}

function get_workflow( $workflowId ) {
	global $wpdb;
	$workflows_table = $wpdb->prefix . 'idemailwiz_workflows';
	$workflow = $wpdb->get_row( $wpdb->prepare( "
		SELECT * FROM $workflows_table WHERE workflowId = %d",
		(int)$workflowId
	), ARRAY_A );

	return $workflow;
}

function get_workflow_by_campaign_id( $campaignId ) {
	global $wpdb;
	$workflows_table = $wpdb->prefix . 'idemailwiz_workflows';

	$campaign = get_idwiz_campaigns( [ 'id' => $campaignId, 'fields' => [ 'workflowId' ], 'limit' => 1 ] );

	if ( empty( $campaign ) ) {
		return null;
	}

	$workflowId = $campaign[0]['workflowId'];
	$workflow = $wpdb->get_row( $wpdb->prepare( "
		SELECT * FROM $workflows_table WHERE workflowId = %d",
		$workflowId
	), ARRAY_A );

	return $workflow;
}

function get_workflow_campaigns($workflowId) {
	global $wpdb;
	$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
	return $wpdb->get_results( $wpdb->prepare ( "
	SELECT * FROM $campaigns_table
	WHERE workflowId = %d", $workflowId ), ARRAY_A );

}

function add_new_workflows_daily() {
	global $wpdb;
	$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
	$workflows_table = $wpdb->prefix . 'idemailwiz_workflows';

	$new_workflow_ids = $wpdb->get_col( "
		SELECT DISTINCT workflowId FROM $campaigns_table
		WHERE workflowId NOT IN (
			SELECT workflowId FROM $workflows_table
		)
	" );

	foreach ( $new_workflow_ids as $workflowId ) {
		upsert_workflow( $workflowId ); // Use the upsert function created earlier
	}
	remove_orphaned_workflows();
}
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'add_new_workflows_daily_hook' ) ) {
		wp_schedule_event( time(), 'daily', 'add_new_workflows_daily_hook' );
	}
} );
add_action( 'add_new_workflows_daily_hook', 'add_new_workflows_daily' );

function remove_orphaned_workflows() {
	global $wpdb;
	$campaigns_table = $wpdb->prefix . 'idemailwiz_campaigns';
	$workflows_table = $wpdb->prefix . 'idemailwiz_workflows';

	$wpdb->query( "
		DELETE FROM $workflows_table
		WHERE workflowId NOT IN (
			SELECT DISTINCT workflowId FROM $campaigns_table
		)
	" );
}

add_action( 'wp_ajax_upsert_workflow', function () {

	$workflowId = isset ( $_POST['workflowId'] ) ? intval( $_POST['workflowId'] ) : null;
	$workflowName = isset ( $_POST['workflowName'] ) ? sanitize_text_field( $_POST['workflowName'] ) : '';

	if ( ! $workflowId ) {
		wp_send_json_error( 'Missing workflow ID', 400 );
	}

	upsert_workflow( $workflowId, $workflowName );
	wp_send_json_success();
} );

function display_workflow_campaigns_table( $workflowId, $campaigns, $startDate = null, $endDate = null ) {
	$workflow = get_workflow( $workflowId );
	?>
		<div class="workflow-campaigns">
			<table class="wizcampaign-tiny-table">
				<thead>
					<tr>
						<th>Campaign</th>
	
						<th>Last Sent</th>
						<th>Started</th>
						<th>Total Sent</th>
						<th>Open %</th>
						<th>CTR</th>
						<th>CTO</th>
						<th>Rev</th>
						<?php
						if (!$startDate && !$endDate) {
							?>
						<th>GA Rev</th>
						<?php } ?>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $campaigns as $campaign ) {
					if ( $campaign['campaignState'] == 'Running' ) {
						if (!$startDate && !$endDate) {
							$campaignMetrics = get_idwiz_metric( $campaign['id'] );
						} else {
							$campaignMetrics = get_triggered_campaign_metrics( [$campaign['id']], $startDate, $endDate );
						}
						$totalSent = $campaignMetrics['uniqueEmailSends'];
						$openRate = $campaignMetrics['wizOpenRate'];
						$ctr = $campaignMetrics['wizCtr'];
						$cto = $campaignMetrics['wizCto'];
						$revenue = $campaignMetrics['revenue'];
						$gaRevenue = $campaignMetrics['gaRevenue'];
						?>
						<tr>
							<td>
								<a href="<?php echo get_bloginfo( 'url' ); ?>/metrics/campaign/?id=<?php echo $campaign['id']; ?>">
									<?php echo $campaign['name']; ?>
								</a>
							</td>
	
	
							<td>
								<?php echo date( 'm/d/Y', $campaign['startAt'] / 1000 ); ?>
							</td>
							<td>
								<?php echo date( 'm/d/Y', $workflow['firstSendAt'] / 1000 ); ?>
							</td>
	
							<td>
								<?php echo number_format( (int)$totalSent ); ?>
							</td>
							<td>
								<?php echo number_format( (int)$openRate, 2 ); ?>%
							</td>
							<td>
								<?php echo number_format( (int)$ctr, 2 ); ?>%
							</td>
							<td>
								<?php echo number_format( (int)$cto, 2 ); ?>%
							</td>
							<td>
								<?php echo '$' . number_format( (int)$revenue ); ?>
							</td>
							<?php
							if (!$startDate && !$endDate) {
								?>
							<td>
								<?php echo '$' . number_format( (int)$gaRevenue ); ?>
							</td>
							<?php } ?>
						</tr>
						<?php
					}
				}
				?>
			</tbody>
		</table>
	</div>
	<?php
}