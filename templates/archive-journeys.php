<?php get_header(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="wizHeader">
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<h1 class="wizEntry-title" itemprop="name">
					Journeys & Automations
				</h1>



			</div>
			<div class="wizHeader-right">
				<div class="wizHeader-actions">

				</div>
			</div>
		</div>
	</header>

	<div class="entry-content" itemprop="mainContentOfPage">
		<?php
		$wizJourneys = get_posts( [ 'post_type' => 'journey', 'posts_per_page' => -1 ] );
		$triggeredCampaigns = get_idwiz_campaigns( [ 'type' => 'Triggered', 'campaignState' => [ 'Finished', 'Running' ] ] );

		$workflowCampaigns = [];
		foreach ( $triggeredCampaigns as $campaign ) {
			if ( isset( $campaign['workflowId'] ) ) {
				$workflowCampaigns[] = $campaign;
			}
		}

		// Group campaigns by workflowId
		$workflowCampaigns = array_reduce( $triggeredCampaigns, function ($carry, $item) {
			$carry[ $item['workflowId'] ][] = $item;
			return $carry;
		}, [] );

       

		foreach ( $workflowCampaigns as $workflowId => $campaigns ) {
			$workflow = get_workflow( $workflowId );
			
			if ( ! $workflowId ) {
				continue;
			}

			$workflowActive = false;

			foreach ( $campaigns as $campaign ) {
				if ( $campaign['campaignState'] === 'Running' ) {
					$workflowActive = true;
				} 
			}

            if (!$workflowActive) {
                continue;
            }
			?>
			<div class="workflow-wrapper">
				<div class="workflow-title">
					<?php
					echo '<h3>' . $workflow['workflowName'] . '</h3>'
						?>
				</div>
				<div class="workflow-details">
					Sending since:
					<?php echo date( 'm/d/Y', $workflow['firstSendAt'] / 1000 ); ?>
				</div>
				<div class="workflow-campaigns">
					<table class="wizcampaign-tiny-table">
						<thead>
							<tr>
								<th>Campaign</th>
								<th>Last Sent</th>
								<th>Open %</th>
								<th>Cth</th>
								<th>CTO</th>
								<th>Rev</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $campaigns as $campaign ) {
								if ( $campaign['campaignState'] == 'Running' ) {
									?>
									<tr>
										<td>
											<a
												href="<?php echo get_bloginfo( 'url' ); ?>/metrics/campaign/?id=<?php echo $campaign['id']; ?>">
												<?php echo $campaign['name']; ?>
											</a>
										</td>

										<td>
											<?php echo date( 'm/d/Y', $campaign['startAt'] / 1000 ); ?>
										</td>

										<td>OPENS</td>
										<td>CTR</td>
										<td>CTO</td>
										<td>Rev</td>
									</tr>
									<?php
								}
							}
							?>
						</tbody>
					</table>
				</div>
			</div>
			<?php
		}

		?>
	</div>




</article>
<?php get_footer(); ?>