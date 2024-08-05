<?php acf_form_head(); ?>
<?php get_header(); ?>


<?php if (have_posts()) :
	while (have_posts()) :
		the_post();
		$promoCodeId = get_the_ID();

		$promoCode = get_post_meta(get_the_ID(), 'code', true);
		$idtcDiscount = get_post_meta(get_the_ID(), 'idtc_discount', true);
		// Get the list of campaign IDs associated with the current promo-code
		$associated_campaigns = get_campaigns_in_promo($promoCodeId);
		$associated_campaign_ids = array_column($associated_campaigns, 'id');

		$promoStart = get_post_meta(get_the_ID(), 'start_date', true);
		$promoEnd = get_post_meta(get_the_ID(), 'end_date', true);

		if (!empty($associated_campaign_ids)) {
			$purchases = get_idwiz_purchases(['campaignIds' => $associated_campaign_ids]);
		}

		// If IDs exist, fetch campaigns
		if (!empty($associated_campaign_ids)) {
			$promoCodeCampaigns = get_idwiz_campaigns(
				array(
					'campaignIds' => $associated_campaign_ids,
					'sortBy' => 'startAt',
					'sort' => 'DESC'
				)
			);
		}

?>
		<article id="post-<?php the_ID(); ?>" data-promo-codeid="<?php echo get_the_ID(); ?>" <?php post_class('has-wiz-chart'); ?>>
			<header class="wizHeader">
				<h1 class="wizEntry-title single-wizcampaign-title" title="<?php echo get_the_title(); ?>" itemprop="name">
					<input type="text" class="editableTitle" id="promo-code-title-editable" data-updatetype="title" data-itemid="<?php echo get_the_ID(); ?>" value="<?php echo get_the_title(); ?>" />

				</h1>

				<div class="wizHeaderInnerWrap">
					<div class="wizHeader-left">

						<div class="wizEntry-meta"><strong>Promo Code</strong>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;Run dates:
							<?php echo get_post_meta(get_the_ID(), 'start_date', true) ?? 'Not set'; ?> - <?php echo get_post_meta(get_the_ID(), 'end_date', true) ?? 'Not set'; ?>
							&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;Includes
							<?php echo !empty($associated_campaign_ids) ? count($associated_campaign_ids) : 0; ?> campaigns
							<br />
							<strong>Code:</strong> <?php echo $promoCode; ?> | <?php echo '<strong>iDTC Discount:</strong> $' . $idtcDiscount; ?>
						</div>

					</div>
					<div class="wizHeader-right">
						<div class="wizHeader-actions">


							<!--<button class="wiz-button green add-promo-code-campaign" data-promo-codeid="<?php echo get_the_ID(); ?>"><i class="fa-regular fa-plus"></i>&nbsp;Add
								Campaigns</button>-->
							<button class="wiz-button green edit-single-promo-code" title="Edit promo code details" data-promo-codeid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-pencil"></i></button>
							<button class="wiz-button red remove-single-promo-code" title="Delete promo code" data-promo-codeid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-trash"></i></button>
							&nbsp;&nbsp;|&nbsp;&nbsp;
							<?php include plugin_dir_path(__FILE__) . 'parts/module-user-settings-form.php'; ?>

						</div>
					</div>
				</div>
			</header>


			<div class="entry-content" itemprop="mainContentOfPage">



				<?php $singlePromoData = get_single_promo_code_data(get_the_ID()); ?>

				<div class="rollup_summary_wrapper" id="single-promo-code-rollup">
					<div class="metric-item"><span class="metric-label">Campaigns</span><span class="metric-value"><?php echo $singlePromoData['campaigns']; ?></span></div>
					<div class="metric-item"><span class="metric-label">Campaign Purchases</span><span class="metric-value"><?php echo $singlePromoData['campaign_purchases']; ?></span></div>
					<div class="metric-item"><span class="metric-label">All Purchases</span><span class="metric-value"><?php echo $singlePromoData['all_purchases']; ?></span></div>
					<div class="metric-item"><span class="metric-label">Campaign Revenue</span><span class="metric-value"><?php echo '$'.number_format($singlePromoData['campaign_revenue']); ?></span></div>
					<div class="metric-item"><span class="metric-label">All Revenue</span><span class="metric-value"><?php echo '$' . number_format($singlePromoData['all_revenue']); ?></span></div>
					<div class="metric-item"><span class="metric-label">Last Used</span><span class="metric-value"><?php echo date('m-d-Y', strtotime($singlePromoData['last_used'])); ?></span></div>
				</div>


				<div class="wizcampaign-sections-row">
					<div class="wizcampaign-section inset template-timeline-wrapper">
						<div class="template-timeline">
							<?php
							if (!empty($promoCodeCampaigns)) {
								$campaignsAsc = array_reverse($promoCodeCampaigns);
								foreach ($campaignsAsc as $campaign) {
									$template = get_idwiz_template($campaign['templateId']);
							?>
									<div class="template-timeline-card">
										<div class="template-timeline-card-title">
											<?php
											$startStamp = intval($campaign['startAt'] / 1000);
											echo date('m/d/Y', $startStamp) . '<br/>';
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
							} ?>
						</div>
					</div>
					<div class="wizcampaign-section inset span2" id="email-info">
						<?php
						//For purchases by date, we want to get purchases (not campaigns) by date.
						?>
						<div class="wizcampaign-section-title-area">
							<h4>Purchases by Date</h4>
							<div class="wizcampaign-section-icons">

							</div>
						</diV>
						<div class="wizChartWrapper">
							<?php
							// Set up the data attributes
							$purchByPromoAtts = [];


							$purchByPromoAtts[] = 'data-chartid="promoPurchasesByDate"';

							$purchByPromoAtts[] = 'data-promocode="' . $promoCode . '"';

							$purchByPromoAtts[] = "data-startdate='{$promoStart}'";
							$purchByPromoAtts[] = "data-enddate='" . date('Y-m-d') . "'";

							$purchByPromoAtts[] = 'data-charttype="bar"';


							// Convert the array to a string for echoing
							$purchByPromoAttsString = implode(' ', $purchByPromoAtts);
							?>

							<canvas class="promoPurchByDate wiz-canvas" id="promoPurchasesByDate" <?php echo $purchByPromoAttsString; ?>></canvas>

						</div>

					</div>

					<div class="wizcampaign-sections-row">
						<div id="promo-code-campaigns-table" class="wizcampaign-section inset span4">

							<table class="idemailwiz_table display idwiz-initiative-table" id="idemailwiz_promo-code_campaign_table" style="width: 100%; vertical-align: middle;" valign="middle" width="100%" data-campaignids='<?php echo json_encode($associated_campaign_ids); ?>'>
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



									if (!empty($associated_campaign_ids)) {
										foreach ($promoCodeCampaigns as $campaign) {
											$wizCampaign = get_idwiz_campaign($campaign['id']);
											$campaignMetrics = get_idwiz_metric($campaign['id']);
											$campaignStartStamp = (int) ($campaign['startAt'] / 1000);
											$readableStartAt = date('m/d/Y', $campaignStartStamp);
									?>
											<tr data-campaignid="<?php echo $campaign['id']; ?>">
												<td class="campaignDate" data-sort="<?php echo $campaignStartStamp; ?>">
													<?php echo $readableStartAt; ?>
												</td>
												<td class="campaignType">
													<?php echo $wizCampaign['type']; ?>
												</td>
												<td class="messageMedium">
													<?php echo $wizCampaign['messageMedium']; ?>
												</td>
												<td class="campaignName"><a href="<?php echo get_bloginfo('wpurl'); ?>/metrics/campaign/?id=<?php echo $campaign['id']; ?>">
														<?php echo $campaign['name']; ?>
													</a></td>
												<td class="uniqueSends dtNumVal">
													<?php echo number_format($campaignMetrics['uniqueEmailSends']); ?>
												</td>
												<td class="uniqueOpens dtNumVal">
													<?php echo number_format($campaignMetrics['uniqueEmailOpens']); ?>
												</td>

												<td class="openRate">
													<?php echo number_format($campaignMetrics['wizOpenRate'] * 1, '2'); ?>%
												</td>
												<td class="uniqueClicks dtNumVal">
													<?php echo number_format($campaignMetrics['uniqueEmailClicks']); ?>
												</td>
												<td class="ctr">
													<?php echo number_format($campaignMetrics['wizCtr'] * 1, 2); ?>%
												</td>
												<td class="cto">
													<?php echo number_format($campaignMetrics['wizCto'] * 1, 2); ?>%
												</td>
												<td class="uniquePurchases dtNumVal">
													<?php echo number_format($campaignMetrics['uniquePurchases']); ?>
												</td>
												<td class="campaignRevenue dtNumVal">
													<?php echo '$' . number_format($campaignMetrics['revenue'] * 1, 2); ?>
												</td>
												<td class="cvr">
													<?php echo number_format($campaignMetrics['wizCvr'] * 1, 2); ?>%
												</td>
												<td class="uniqueUnsubs dtNumVal">
													<?php echo number_format($campaignMetrics['uniqueUnsubscribes']); ?>
												</td>
												<td class="unsubRate">
													<?php echo number_format($campaignMetrics['wizUnsubRate'] * 1, 2); ?>%
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










				</div>
		</article>
<?php endwhile;
endif; ?>
<?php get_footer(); ?>