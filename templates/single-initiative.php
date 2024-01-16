<?php acf_form_head(); ?>
<?php get_header(); ?>


<?php if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		// Get the list of campaign IDs associated with the current initiative
		$associated_campaign_ids = idemailwiz_get_campaign_ids_for_initiative( get_the_ID() ) ?? array();
		if ( ! empty( $associated_campaign_ids ) ) {
			$purchases = get_idwiz_purchases( [ 'campaignIds' => $associated_campaign_ids ] );
		}

		// If IDs exist, fetch campaigns
		if ( ! empty( $associated_campaign_ids ) ) {
			$initCampaigns = get_idwiz_campaigns(
				array(
					'campaignIds' => $associated_campaign_ids,
					'sortBy' => 'startAt',
					'sort' => 'DESC'
				)
			);
		}

		?>
		<article id="post-<?php the_ID(); ?>" data-initiativeid="<?php echo get_the_ID(); ?>" <?php post_class( 'has-wiz-chart' ); ?>>
			<header class="wizHeader">
				<div class="wizHeaderInnerWrap">
					<div class="wizHeader-left">
						<h1 class="wizEntry-title single-wizcampaign-title" title="<?php echo get_the_title(); ?>"
							itemprop="name">
							<input type="text" class="editableTitle" id="initiative-title-editable" data-updatetype="title"
								data-itemid="<?php echo get_the_ID(); ?>" value="<?php echo get_the_title(); ?>" />

						</h1>
						<div class="wizEntry-meta"><strong>Initiative</strong>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;Send dates:
							<?php echo display_init_date_range( $associated_campaign_ids ); ?>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;Includes
							<?php echo count( $associated_campaign_ids ); ?> campaigns
						</div>

					</div>
					<div class="wizHeader-right">
						<div class="wizHeader-actions">
							<button class="wiz-button green doWizSync"
								data-campaignIds="<?php echo esc_attr( json_encode( array( $associated_campaign_ids ) ) ); ?>"
								data-metricTypes="<?php echo esc_attr( json_encode( array( 'blast' ) ) ); ?>"><i
									class="fa-solid fa-arrows-rotate"></i>&nbsp;&nbsp;Sync Metrics</button>

							<button class="wiz-button green add-init-campaign"
								data-initiativeid="<?php echo get_the_ID(); ?>"><i class="fa-regular fa-plus"></i>&nbsp;Add
								Campaigns</button>
							<button class="wiz-button red remove-single-initiative" title="Delete Initiative"
								data-initiativeid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-trash"></i></button>
							&nbsp;&nbsp;|&nbsp;&nbsp;
							<button class="wiz-button green new-initiative"><i class="fa-regular fa-plus"></i>&nbsp;New
								Initiative</button>
							<?php include plugin_dir_path( __FILE__ ) . 'parts/module-user-settings-form.php'; ?>

						</div>
					</div>
				</div>
			</header>


			<div class="entry-content" itemprop="mainContentOfPage">



				<?php
				if ( ! empty( $associated_campaign_ids ) ) { 
				$metricRates = get_idwiz_metric_rates( $associated_campaign_ids );

				echo get_idwiz_rollup_row( $metricRates );
				} else {
					echo '<p>This initiative is not associated with any campaigns yet.</p>';
				}


				?>

				<div class="wizcampaign-sections-row">
					<div class="wizcampaign-section inset template-timeline-wrapper">
						<div class="template-timeline">
							<?php
							if (!empty($initCampaigns)) {
							$campaignsAsc = array_reverse( $initCampaigns );
							foreach ( $campaignsAsc as $campaign ) {
								$template = get_idwiz_template( $campaign['templateId'] );
								?>
								<div class="template-timeline-card">
									<div class="template-timeline-card-title">
										<?php 
                                        $startStamp = $campaign['startAt'] / 1000;
                                        echo date('m/d/Y', $startStamp).'<br/>';
                                        echo $campaign['name']; 
                                        ?>
									</div>
                                    <div class="template-timeline-card-image">
									<?php
                                    echo "<div title='Click to enlarge' class='init-template-preview wiztemplate-preview template-image-wrapper'>";
                                    echo "<div class='wiztemplate-image-spinner'><div class='fa-solid fa-spin fa-spinner fa-3x' data-templateid='" . $template['templateId'] . "'></div></div>";
                                    echo "<img data-templateid='" . $template['templateId'] . "' data-src='" . $template['templateImage'] . "' />";
                                    echo "</div>"; // Close template preview
                                    
                                       ?>
                                    </div>
								</div>
							<?php } 
							}?>
						</div>
					</div>


				</div>
				<div class="wizcampaign-sections-row">
					<div id="initiative-campaigns-table" class="wizcampaign-section inset span4">

						<table class="idemailwiz_table display idemailwiz-simple-table"
							id="idemailwiz_initiative_campaign_table" style="width: 100%; vertical-align: middle;"
							valign="middle" width="100%"
							data-campaignids='<?php echo json_encode( $associated_campaign_ids ); ?>'>
							<thead>
								<tr>
									<th>Date</th>
									<th>Type</th>
									<th>Medium</th>
									<th>Campaign</th>
									<th>Sent</th>
									<th>Opened</th>
									<th>Open Rate</th>
									<th>Clicked</th>
									<th>CTR</th>
									<th>CTO</th>
									<th>Purchases</th>
									<th>Rev</th>
									<th>CVR</th>
									<th>Unsubs</th>
									<th>Unsub. Rate</th>
									<th>ID</th>
								</tr>
							</thead>
							<tbody>
								<?php



								if ( ! empty( $associated_campaign_ids ) ) {
									foreach ( $initCampaigns as $campaign ) {
										$wizCampaign = get_idwiz_campaign( $campaign['id'] );
										$campaignMetrics = get_idwiz_metric( $campaign['id'] );
										$campaignStartStamp = (int) ( $campaign['startAt'] / 1000 );
										$readableStartAt = date( 'm/d/Y', $campaignStartStamp );
										?>
										<tr data-campaignid="<?php echo $campaign['id']; ?>">
											<td class="campaignDate ">
												<?php echo $readableStartAt; ?>
											</td>
											<td class="campaignType">
												<?php echo $wizCampaign['type']; ?>
											</td>
											<td class="messageMedium">
												<?php echo $wizCampaign['messageMedium']; ?>
											</td>
											<td class="campaignName"><a
													href="<?php echo get_bloginfo( 'wpurl' ); ?>/metrics/campaign/?id=<?php echo $campaign['id']; ?>">
													<?php echo $campaign['name']; ?>
												</a></td>
											<td class="uniqueSends dtNumVal">
												<?php echo number_format( $campaignMetrics['uniqueEmailSends'] ); ?>
											</td>
											<td class="uniqueOpens dtNumVal">
												<?php echo number_format( $campaignMetrics['uniqueEmailOpens'] ); ?>
											</td>

											<td class="openRate">
												<?php echo number_format( $campaignMetrics['wizOpenRate'] * 1, '2' ); ?>%
											</td>
											<td class="uniqueClicks dtNumVal">
												<?php echo number_format( $campaignMetrics['uniqueEmailClicks'] ); ?>
											</td>
											<td class="ctr">
												<?php echo number_format( $campaignMetrics['wizCtr'] * 1, 2 ); ?>%
											</td>
											<td class="cto">
												<?php echo number_format( $campaignMetrics['wizCto'] * 1, 2 ); ?>%
											</td>
											<td class="uniquePurchases dtNumVal">
												<?php echo number_format( $campaignMetrics['uniquePurchases'] ); ?>
											</td>
											<td class="campaignRevenue dtNumVal">
												<?php echo '$' . number_format( $campaignMetrics['revenue'] * 1, 2 ); ?>
											</td>
											<td class="cvr">
												<?php echo number_format( $campaignMetrics['wizCvr'] * 1, 2 ); ?>%
											</td>
											<td class="uniqueUnsubs dtNumVal">
												<?php echo number_format( $campaignMetrics['uniqueUnsubscribes'] ); ?>
											</td>
											<td class="unsubRate">
												<?php echo number_format( $campaignMetrics['wizUnsubRate'] * 1, 2 ); ?>%
											</td>
											<td class="campaignId">
												<?php echo $campaign['id'] ?>
											</td>
										</tr>
									<?php }
								}
								?>
							</tbody>
						</table>

					</div>
				</div>

				<div class="wizcampaign-sections-row">

					<div class="wizcampaign-section inset">
						<div class="initiative-about">
							<h4>About
								<?php the_title(); ?>
							</h4>
						</div>
						<textarea id="initiative-content-editable"
							data-initUpdateType="content"><?php echo strip_tags( get_the_content() ); ?></textarea>
					</div>
					<div id="initiativeTemplate" class="wizcampaign-section inset">
						<?php $initBaseTemplate = get_field( 'base_template' );
						?>
						<div class="wizcampaign-section-title-area">
							<h4>Initiative Base Template</h4>
							<div class="wizcampaign-section-icons">
								<?php if ( $initBaseTemplate ) { ?>
									<button data-postid="<?php echo $initBaseTemplate->ID; ?>" data-frombase="true"
										class="duplicate-template wiz-button green"><i class="fa-solid fa-plus"></i> Start New
										Template</button>
								<?php } ?>
							</div>
						</div>

						<div id="initTemplateTools">
							<?php if ( ! $initBaseTemplate ) { ?>
								<br />
								<em>No base template set</em><br /><button class="wiz-button green attachBaseTemplate"><i
										class="fa-solid fa-plus"></i> Attach Base Template</button>
							<?php } ?>
							<div id="showAttachBaseTemplate">
								<?php
								acf_form( array( 'fields' => array( 'field_65032b84c2f62' ), 'updated_message' => false, 'html_submit_button' => '<input type="submit" class="acf-button wiz-button green" value="%s" />' ) );
								?>
							</div>
							<?php if ( $initBaseTemplate ) { ?>

								<?php
								$mockups = get_field( 'template_mock-ups', $initBaseTemplate->ID );
								if ( $mockups ) {
									echo '<div class="init-template-mock-ups">';
									$dtMockup = $mockups['mock-up-image-desktop'];
									$mobMockup = $mockups['mock-up-image-mobile'];
									if ( $dtMockup ) {
										echo '<div class="init-template-mock-up"><strong>Desktop</strong><br/><img src="' . $dtMockup . '"/></div>';
									}
									if ( $mobMockup ) {
										echo '<div class="init-template-mock-up"><strong>Mobile</strong><br/><img src="' . $mobMockup . '"/></div>';
									}
									echo '</div>';
								} else {
									echo 'Base Template:<br/><strong>' . $initBaseTemplate->post_title . '</strong><br/><em>No mock-ups were found for this base template.</em>';
								}
								?>
								<button class="wiz-button outline attachBaseTemplate" id="replaceBaseTemplate"><i
										class="fa-solid fa-shuffle"></i> Replace Base Template</button>
							<?php } ?>

						</div>

					</div>
					<div id="initiativeAssets" class="wizcampaign-section short inset span2">
						<div class="wizcampaign-section-title-area">
							<h4>Initiative Assets</h4>

						</div>
						<div id="initAssetsUI">
							<div class="initAssetsLibrary">
								<?php
								$initAssets = get_post_meta( $post->ID, 'wizinitiative_assets', true );
								if ( is_array( $initAssets ) ) {
									foreach ( $initAssets as $asset ) {
										echo '<div class="init_asset_wrap"><img src="' . $asset['src'] . '" alt="' . $asset['alt'] . '" /></div>';
									}
								}
								?>
							</div>

						</div>
					</div>
				</div>

				<?php
				if ( ! empty( $associated_campaign_ids ) ) {
				// Setup standard chart variables
				$standardChartCampaignIds = $associated_campaign_ids;
				$standardChartPurchases = $purchases;

				$startAts = array_column( $initCampaigns, 'startAt' );
				$earliestDate = min( $startAts );
				$startDate = date( 'Y-m-d', $earliestDate / 1000 );

				$endDate = date( 'Y-m-d' );
				include plugin_dir_path( __FILE__ ) . 'parts/standard-charts.php';
				}
				?>





			</div>
		</article>
	<?php endwhile; endif; ?>
<?php get_footer(); ?>