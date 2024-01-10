<?php get_header(); ?>


<?php if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		$postId = get_the_ID();
		$campaignSets = get_post_meta( $postId, 'compare_campaign_sets', true );

		$subTitleString = generateComparisonSubtitle( $campaignSets );

		?>
		<article id="post-<?php the_ID(); ?>" data-comparisonid="<?php echo get_the_ID(); ?>" <?php post_class( 'has-wiz-chart' ); ?>>
			<header class="wizHeader">
				<div class="wizHeaderInnerWrap">
					<div class="wizHeader-left">
						<h1 class="wizEntry-title single-wizcampaign-title" title="<?php echo get_the_title(); ?>"
							itemprop="name">
							<input type="text" class="editableTitle" id="comparison-title-editable" data-updatetype="title"
								data-itemid="<?php echo get_the_ID(); ?>" value="<?php echo get_the_title(); ?>" />
						</h1>
						<div class="wizEntry-meta comparison-subtitle"><strong>
								<?php echo $subTitleString; ?>
							</strong>
						</div>

					</div>
					<div class="wizHeader-right">
						<div class="wizHeader-actions">
							<button class="wiz-button green new-comparison"><i class="fa-regular fa-plus"></i>&nbsp;New
								Comparison</button>
							<button class="wiz-button red delete-comparison" data-post-id="<?php echo $postId; ?>"><i class="fa-solid fa-trash"></i>&nbsp;Delete
								Comparison</button>
							<?php //include plugin_dir_path(__FILE__) . 'parts/module-user-settings-form.php'; ?>

						</div>
					</div>
				</div>
			</header>


			<div class="entry-content" itemprop="mainContentOfPage">
				<?php


				// if (!$campaignSets || empty($campaignSets['sets'])) {
				// $columnCount = 2; 
				// } else {
				//     usort($campaignSets['sets'], function ($a, $b) {
				//         return $a['setId'] - $b['setId'];
				//     });
		
				//     $columnCount = count($campaignSets['sets']);
				//     if ($columnCount == 1) {
				//         $columnCount = 2; 
				//     }
				// }
				$columnCount = 2;

				//echo idemailwiz_generate_image_from_template(8600851);
				//generate_all_template_images();
				?>
				<div id="comparison-columns">
					<?php //print_r($campaignSets); 
							//delete_post_meta($postId, 'compare_campaign_sets');
							?>

					<?php
					$firstColumnHasValidSet = false;
					for ( $i = 1; $i <= $columnCount; $i++ ) {
						$campaignSet = $campaignSets['sets'][ $i - 1 ] ?? [];

						if ( $campaignSet && isset( $campaignSet['campaigns'] ) && ! empty( $campaignSet['campaigns'] ) ) {
							$validCampaignSet = true;
						} else {
							$validCampaignSet = false;
						}

						$setId = $campaignSet['setId'] ?? $i;
						$setName = $campaignSet['setName'] ?? 'Campaign Set #' . $i;

						// Set which metrics to include in the rollup
						$includeMetrics = [ 
							'uniqueEmailSends',
							'wizDeliveryRate',
							'wizOpenRate',
							'wizCtr',
							'wizCto',
							'uniquePurchases',
							'revenue',
							'wizAov'
						];
						?>
						<div class="comparison-column" id="campaign-set-<?php echo $i; ?>-column"
							data-set-id="<?php echo $setId; ?>" data-post-id="<?php echo $postId; ?>"
							data-campaign-ids='<?php echo json_encode( $campaignSet['campaigns'] ?? [] ); ?>'
							data-include-metrics='<?php echo json_encode( $includeMetrics ); ?>'
							data-valid-campaign-set='<?php echo $validCampaignSet; ?>'>
							<div class="wizcampaign-sections-row comparison-column-settings">
								<div class="wizcampaign-section inset">
									<div class="wizcampaign-section-title-area">
										<h4 title="Click to edit title"><input class="editable-set-title" type="text"
												value="<?php echo $setName; ?>" name="set-title" data-set-id="<?php echo $setId; ?>"
												data-post-id="<?php echo $postId; ?>"></h4>
										<div class="wizcampaign-section-title-area-right wizcampaign-section-icons">
											<?php
											// Check if it's the first column and has a valid set
											if ( $i == 1 && $validCampaignSet ) {
												$firstColumnHasValidSet = true;
											}

											// Condition for the first column
											if ( ! $validCampaignSet && $i == 1 ) { ?>
												<button class="wiz-button green centered add-compare-campaigns"
													data-set-id="<?php echo $i; ?>" data-post-id="<?php echo $postId; ?>">Add
													Campaigns</button>
											<?php }

											// Condition for the second column
											if ( $i == 2 && $firstColumnHasValidSet && ! $validCampaignSet ) { ?>
												<button class="wiz-button green centered add-compare-campaigns"
													data-set-id="<?php echo $i; ?>" data-post-id="<?php echo $postId; ?>">Add
													Campaigns</button>
											<?php }

											?>
											<?php if ( $validCampaignSet ) { ?>
												<?php echo idwiz_get_comparison_column_buttons( $postId, $setId ); ?>
											<?php } ?>
										</div>
									</div>
								</div>
							</div>

							<div class="rollup_summary_wrapper" id="rollup-summary-<?php echo $setId; ?>">
								<?php
								if ( $validCampaignSet ) { ?>
									<div class="rollup_summary_loader"><i class="fa-solid fa-spinner fa-spin"></i>&nbsp;&nbsp;Loading
										rollup summary...</div>
								<?php } ?>
							</div>
							<?php if ( ! $validCampaignSet ) {
								
								if ( $i == 1 ) {
									echo '<div class="new-comparison-instructions"><em>To begin, add campaigns (by date range or individually) to the first column. </em></div>';
												$showCol2 = true;
								} else {
									if ( $firstColumnHasValidSet ) {
										echo '<div class="new-comparison-instructions"><em>Add campaigns to this 2nd column to compare them to the first. You can add, remove, and re-arrange campaigns as needed and the rollup data will update.</em></div>';
									}
								}
							} ?>

							<?php
							if ( $validCampaignSet ) {
								foreach ( $campaignSet['campaigns'] as $campaignId ) { ?>
									<?php
									echo generate_compare_campaign_card_html( $setId, $campaignId, $postId, null, null, true );
									?>
								<?php }
							} ?>

						</div>

					<?php } ?>

				</div>
			</div>




		</article>
	<?php endwhile; endif; ?>
<?php get_footer(); ?>