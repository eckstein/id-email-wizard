<?php get_header(); ?>
<?php
// Retrieve the stored campaign IDs and send dates from post meta
$journeyId = $_GET['id'] ?? false;



// Check if the startDate and endDate parameters are present in the $_GET array, if not, default
$startDate = $_GET['startDate'] ?? date( 'Y-m-01' );
$endDate = $_GET['endDate'] ?? date( 'Y-m-d' );
if ( $journeyId && get_workflow( $journeyId ) ) {

	$journey = get_workflow( $journeyId );

	$journeyFirst = $journey['firstSendAt'];
	$journeyLast = $journey['lastSendAt'];

	$journeyStartDate = date( 'm/d/Y', $journeyFirst / 1000 );
	$journeyEndDate = date( 'm/d/Y', $journeyLast / 1000 );

	$journeyCampaigns = get_workflow_campaigns( $journeyId );
	$journeyCampaignIds = array_column( $journeyCampaigns, 'id' );

	$journeyName = $journey['workflowName'];
	?>
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

	<article id="journey-<?php $journeyId; ?>" data-journey="<?php echo $journeyId; ?>" class="single-journey-article">

		<div class="entry-content" itemprop="mainContentOfPage">
			<?php include plugin_dir_path( __FILE__ ) . 'parts/dashboard-date-pickers.php'; ?>

			<div id="journey-rollup-wrapper" data-campaign-ids='<?php echo json_encode( $journeyCampaignIds ); ?>'
				data-start-date="<?php echo $startDate; ?>" data-end-date="<?php echo $endDate; ?>">
				<div class="rollup_summary_wrapper" id="journey-timeline-rollup-summary">
					<div class="rollup_summary_loader"><i class="fa-solid fa-spinner fa-spin"></i>&nbsp;&nbsp;Loading
						rollup summary...</div>
				</div>
			</div>

			<div class="journey-campaigns-wrapper">
				<?php 
				$campaigns = get_workflow_campaigns($journeyId);
				echo display_workflow_campaigns_table( $journeyId, $campaigns, $startDate, $endDate ); ?>
			</div>

		</div>
	</article>


	<?php
} else { // if valid workflow id
	echo 'Invalid workflowId or workflow has been deleted!';
}
get_footer(); ?>