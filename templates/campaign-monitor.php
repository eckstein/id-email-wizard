<?php
get_header();

global $wpdb;


?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="wizHeader">
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<h1 class="wizEntry-title" itemprop="name">Campaign Monitor</h1>
			</div>
			<div class="wizHeader-right">
				<!-- Additional header actions if needed -->
			</div>
		</div>
	</header>

	<div class="entry-content" itemprop="mainContentOfPage">

		<div class="wizcampaign-sections-row">
			<div class="wizcampaign-section inset" id="campaign-monitor">
				<h2>Incoming Data Monitor</h2>
				<?php
				$triggeredDataArgs['fields'] = 'campaignId, startAt';
				$triggeredDataArgs['startAt_start'] = date( 'Y-m-d', strtotime( '- 1 month' ) );

				$triggeredCampaigns = get_idwiz_campaigns( array( 'type' => 'triggered', 'campaignState' => 'Running', 'sortBy' => 'startAt' ) );

				?>
				<div id="campaign-monitor-table-container">
					<table class="idwiz-campaign-monitor-table idemailwiz_table display dataTable no-footer">
						<thead>
							<tr>
								<th>Campaign Name</th>
								<th>Last Triggered</th>

							</tr>
						</thead>
						<?php
						date_default_timezone_set( 'UTC' );
						foreach ( $triggeredCampaigns as $campaign ) {
							$campaignId = $campaign['id'];

							$campaignData = get_idemailwiz_triggered_data( 'idemailwiz_triggered_sends', [ 
								'campaignIds' => [ $campaignId ],
								'fields' => 'startAt',
								'limit' => 10
							] );

							$maxStartAt = 0; // Variable to hold the maximum startAt timestamp
						
							foreach ( $campaignData as $data ) {
								if ( $data['startAt'] > $maxStartAt ) {
									$maxStartAt = $data['startAt'];
								}
							}

							// Convert to America/Los_Angeles time
							$lastSendDate = '1970-01-01 00:00:00';

							if ( isset( $campaign['wizSentAt'] ) && $campaign['wizSentAt'] ) {
								// Convert wizSentAt to a timestamp in milliseconds
								$wizSentAtMs = strtotime( $campaign['wizSentAt'] ) * 1000;
								//echo $wizSentAtMs.'-'.$maxStartAt;
								if ( intval($wizSentAtMs) > intval($maxStartAt) ) {
									$maxStartAt = $wizSentAtMs;
								}
							}
							$lastSendDate = new DateTime();
							$lastSendDate->setTimestamp( intval( $maxStartAt / 1000 ) ); // Convert milliseconds to seconds
							$lastSendDate->setTimezone( new DateTimeZone( 'UTC' ) );
							$sendDateDisplay = $lastSendDate->format( 'm/d/Y, g:ia' );
						

							?>
							<tr>
								<td>
									<a
										href="<?php echo get_bloginfo( 'url' ) . '/metrics/campaign/?id=' . $campaign['id']; ?>">
										<?php echo htmlspecialchars( $campaign['name'] ); ?>
									</a>
								</td>
								<td>
									<?php echo strtotime( $sendDateDisplay ); ?>
								</td>


							</tr>
						<?php } ?>

					</table>
				</div>
			</div>
			<div class="wizcampaign-section inset">
				<h2>Purchase Monitor</h2>
				<?php $last50Purchases = get_idwiz_purchases( array( 'limit' => 50, 'sortBy' => 'purchaseDate' ) ); ?>
				<table class="idwiz-purchase-monitor-table idemailwiz_table display dataTable no-footer">
					<thead>
						<tr>
							<th>Date</th>
							<th>Channel</th>
							<th>Product</th>
							<th>Amount</th>
							<th>Campaign</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $last50Purchases as $purchase ) { ?>
							<tr>
								<td>
									<?php echo strtotime( $purchase['createdAt'] ); ?>
								</td>
								<td>
									<?php echo $purchase['shoppingCartItems_divisionName']; ?>
								</td>
								<td>
									<?php echo $purchase['shoppingCartItems_name']; ?>
								</td>

								<td>
									$
									<?php echo number_format( $purchase['total'], 2 ); ?>
								</td>
								<td>
									<?php
									$wizCampaign = get_idwiz_campaign( $purchase['campaignId'] );
									if ( $wizCampaign ) { ?>
										<a href="<?php echo 'https://localhost/metrics/campaign/?id=' . $purchase['campaignId']; ?>"
											title="<?php echo $wizCampaign['name']; ?>" target="_blank">
											<?php echo $purchase['campaignId']; ?>
										</a>
									<?php } else { ?>
										<?php echo $purchase['campaignId']; ?>
									<?php } ?>
								</td>
							</tr>
						<?php } ?>
					</tbody>
				</table>

			</div>
		</div>




	</div>
</article>

<?php get_footer(); ?>