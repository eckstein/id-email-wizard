<?php get_header(); ?>
<article id="journey-archive">
	<header class="wizHeader">
		<h1 class="wizEntry-title" itemprop="name">
			Journeys & Automations
		</h1>
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">




			</div>
			<div class="wizHeader-right">
				<div class="wizHeader-actions">

				</div>
			</div>
		</div>
	</header>

	<div class="entry-content" itemprop="mainContentOfPage">
		<?php
		$wizJourneys = get_posts(['post_type' => 'journey', 'posts_per_page' => -1]);
		$triggeredCampaigns = get_idwiz_campaigns(['type' => 'Triggered', 'campaignState' => ['Finished', 'Running']]);

		$workflowCampaigns = [];
		foreach ($triggeredCampaigns as $campaign) {
			if (isset($campaign['workflowId'])) {
				$workflowCampaigns[] = $campaign;
			}
		}

		// Group campaigns by workflowId
		$workflowCampaigns = array_reduce($triggeredCampaigns, function ($carry, $item) {
			$carry[$item['workflowId']][] = $item;
			return $carry;
		}, []);



		foreach ($workflowCampaigns as $workflowId => $campaigns) {
			$workflow = get_workflow($workflowId);

			if (!$workflowId) {
				continue;
			}

			$workflowActive = false;

			foreach ($campaigns as $campaign) {
				if ($campaign['campaignState'] === 'Running') {
					$workflowActive = true;
				}
			}

			if (!$workflowActive) {
				continue;
			}
		?>
			<div class="workflow-wrapper wizcampaign-section inset">
				<div class="workflow-title">
					<?php
					echo '<a href="' . get_bloginfo('url') . '/metrics/journey?id=' . $workflow['workflowId'] . '">' . $workflow['workflowName'] . '</a>'
					?>
				</div>
				<div class="workflow-details">


				</div>

				<?php echo display_workflow_campaigns_table($workflowId, $campaigns); ?>

			</div>
		<?php
		}

		?>
	</div>




</article>
<?php get_footer(); ?>