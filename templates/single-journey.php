<?php get_header(); ?>
<?php
// Retrieve the stored campaign IDs and send dates from post meta
$post_id = get_the_ID();

// Check if the startDate and endDate parameters are present in the $_GET array, if not, default
$startDate = $_GET['startDate'] ?? date( 'Y-m-01' );
$endDate = $_GET['endDate'] ?? date( 'Y-m-d' );

?>
<header class="wizHeader">
	<div class="wizHeaderInnerWrap">
		<div class="wizHeader-left">
			<h1 class="wizEntry-title single-wizcampaign-title" title="<?php echo get_the_title(); ?>" itemprop="name">
				<?php echo get_the_title(); ?>
			</h1>
			<div class="wizEntry-meta"><strong>Journey</strong>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;Send dates:
				<?php
				$defFirstJourneySend = get_post_meta( $post_id, 'earliest_send', true );
				$defLastJourneySend = get_post_meta( $post_id, 'latest_send', true );
				echo date( 'm/d/Y', $defFirstJourneySend / 1000 ) . ' - ' . date( 'm/d/Y', $defLastJourneySend / 1000 ); ?>
				&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;Includes
				<?php
				$journeyCampaignIds = get_post_meta( $post_id, 'journey_campaign_ids', true ) ?? [];
				$hiddenJourneyCampaignIds = is_array(get_post_meta( $post_id, 'journey_hidden_campaign_ids', true )) ? get_post_meta( $post_id, 'journey_hidden_campaign_ids', true ) : [];

				$visibleCampaignIds = array_values( array_diff( $journeyCampaignIds, $hiddenJourneyCampaignIds ) );


				// error_log( print_r( $journeyCampaignIds, true ) );
				// error_log( print_r( $hiddenJourneyCampaignIds, true ) );
				// error_log( print_r( $visibleCampaignIds, true ) );

				echo '<span class="journey-meta-counts"><span class="journey-meta-count-all">' . count( $journeyCampaignIds ); ?></span> campaigns
				<?php if ( count( $hiddenJourneyCampaignIds ) > 0 ) {
					echo '(<span class="journey-meta-count-hidden">' . count( $hiddenJourneyCampaignIds ) . '</span> hidden)';
				}
				echo '</span>' ?>
			</div>

		</div>
		<div class="wizHeader-right">
			<div class="wizHeader-actions">
				<button class="wiz-button green sync-journey"
					data-journeyids="<?php echo htmlspecialchars( json_encode( $journeyCampaignIds ) ); ?>">Sync
					Journey</button>
				<?php include plugin_dir_path( __FILE__ ) . 'parts/module-user-settings-form.php'; ?>

			</div>
		</div>
	</div>
</header>

<article id="post-<?php the_ID(); ?>" data-journey="<?php echo get_the_ID(); ?>">

	<div class="entry-content" itemprop="mainContentOfPage">
		<?php include plugin_dir_path( __FILE__ ) . 'parts/dashboard-date-pickers.php'; ?>

		<div id="journey-rollup-wrapper" data-campaign-ids='<?php echo json_encode( $visibleCampaignIds ); ?>'
			data-start-date="<?php echo $startDate; ?>" data-end-date="<?php echo $endDate; ?>">
			<div class="rollup_summary_wrapper" id="journey-timeline-rollup-summary">
				<div class="rollup_summary_loader"><i class="fa-solid fa-spinner fa-spin"></i>&nbsp;&nbsp;Loading
					rollup summary...</div>
			</div>
		</div>

		<div class="dragScroll-indicator">Drag timeline to scroll <i class="fa-solid fa-right-long"></i></div>

		<?php echo generate_journey_timeline_html( $post_id, $startDate, $endDate ); ?>

	</div>
</article>


<?php get_footer(); ?>