<?php //print_r($currentTemplate); ?>
<?


?>
<div class="wizcampaign-template-html <?php echo strtolower( $currentTemplate['messageMedium'] ); ?>">
	<?php

	?>
	<div class="wizcampaign-template-top">

		<button title="Regenerate Preview" class="wiz-button green regenerate-preview"
			data-templateid="<?php echo $currentTemplate['templateId']; ?>"><i
				class="fa-solid fa-arrows-rotate"></i>&nbsp;Regenerate</button>
		<?php if ( isset( $currentTemplate['clientTemplateId'] ) ) { ?>
			<button title="Duplicate Template" class="wiz-button duplicate-template"
				data-postid="<?php echo $currentTemplate['clientTemplateId']; ?>"><i
					class="fa-solid fa-copy"></i>&nbsp;Duplicate</button>
		<?php }
		if ( $currentTemplate['messageMedium'] == 'Email' ) { ?>
			<a target="_blank"
				href="https://app.iterable.com/analytics/campaignPerformance/heatmap?campaignId=<?php echo $campaign_id; ?>"
				class="wiz-button red" title="View heatmap on Iterable" target="_blank"><i
					class="fa-solid fa-arrow-up-right-from-square"></i>&nbsp;View heatmap</a>
		<?php } ?>
	</div>
	<?php


	// Fallback in case template didn't get a message medium for some reason
	if ( ! isset( $currentTemplate['messageMedium'] ) ) {
		$messageMedium = $campaign['messageMedium'];
	}
	?>
	<div class="wizcampaign-template-details">
		<h3>
			<?php echo $currentTemplate['name']; ?>
		</h3>
		<?php if ( $currentTemplate['messageMedium'] == 'Email' ) { ?>
			<ul>
				<li>
					<strong>
						<?php echo $currentTemplate['subject']; ?>
					</strong>
				</li>
				<li>
					<?php echo $currentTemplate['preheaderText']; ?>
				</li>
				<li>
					<?php echo $currentTemplate['fromName'] . ' &lt' . $currentTemplate['fromEmail'] . '&gt'; ?>
				</li>
			</ul>
		<?php } ?>
	</div>
	<div class="wizcampaign-template-preview-container">
		<?php echo get_template_preview( $currentTemplate ); ?>
	</div>
</div>