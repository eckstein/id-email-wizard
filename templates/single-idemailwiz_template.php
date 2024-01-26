<?php
acf_form_head();
get_header();

$tempSettings = get_field( 'template_settings' );
$templateStyles = get_field( 'template_styles' );
$emailSettings = get_field( 'email_settings' );
$dtSize = $templateStyles['desktop_font_size'] ?? '18px';
$dtHeight = $templateStyles['desktop_line_height'] ?? '26px';
$mobSize = $templateStyles['mobile_font_size'] ?? '16px';
$mobHeight = $templateStyles['mobile_line_height'] ?? '24px';
$current_user = wp_get_current_user();
$itTemplateId = get_post_meta( get_the_ID(), 'itTemplateId', true ) ?? '';
?>
<header class="wizHeader">
	<div class="wizHeaderInnerWrap">
		<div class="wizHeader-left">
			<h1 id="single-template-title" class="wizEntry-title" title="<?php echo get_the_title(); ?>"
				itemprop="name">
				<input type="text" name="templateTitle" id="idwiz_templateTitle"
					data-templateid="<?php echo get_the_ID(); ?>" value="<?php echo get_the_title( get_the_ID() ); ?>" />
			</h1>
			<div class="wizEntry-meta">
				<strong>WizTemplate</strong>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
				<span class="iDbreadcrumb">Located in:
					<?php echo display_template_folder_hierarchy( get_the_ID() ); ?>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;

					<?php
					$campaignSent = '';
					if ( $itTemplateId ) {
						$lastIterableSync = get_post_meta( get_the_ID(), 'lastIterableSync', true ) ?? '<em>an unknown date and time.</em>';
						echo 'Last synced to Iterable template <a target="_blank" href="https://app.iterable.com/templates/editor?templateId=' . $itTemplateId . '">' . $itTemplateId . '</a> on ' . $lastIterableSync;
						// check for wiz template to see if the campaign has been sent
						$wizTemplate = get_idwiz_template( (int) $itTemplateId );
						//print_r($wizTemplate);
					
						if ( $wizTemplate ) {
							$wizCampaign = get_idwiz_campaign( $wizTemplate['campaignId'] );
							if ( $wizCampaign && $wizCampaign['campaignState'] == 'Finished' ) {
								$campaignSent = true;
								echo '<br/><br/><strong><em>The <a href="' . get_bloginfo( 'url' ) . '/metrics/campaign/?id=' . $wizCampaign['id'] . '">campaign</a> for this template was sent on ' . date( 'm/d/Y', $wizCampaign['startAt'] / 1000 ) . '.</em></strong>';
								echo '<br/><em>Templates for sent campaigns can no longer be synced. You can either duplicate this template or sync it to another, unsent template in Iterable.</em>';
							}
						}
					} else {
						echo '<em>Not synced.</em>';
					} ?>
				</span>

			</div>
		</div>
		<div class="wizHeader-right">
			<div class="wizHeader-actions">
				<div id="search-templates">
					<input type="text" id="live-template-search" placeholder="Search templates..." />
				</div>
				<div class="wiz-button green show-new-template-ui"><i class="fa fa-plus"></i>&nbsp;&nbsp;New Template
				</div>
			</div>
		</div>
	</div>
</header>

<div id="templateUI" class="entry-content two-col-wrap" data-postid="<?php echo get_the_ID(); ?>"
	data-iterableid="<?php echo $itTemplateId; ?>" data-campaignsent="<?php echo $campaignSent; ?>"
	itemprop="mainContentOfPage">
	<div class="left" id="builder">


		<div id="builder-chunks">
			<?php
			$options = get_option( 'idemailwiz_settings' );
			$wizBuilderFieldGroupId = $options['wizbuilder_field_group'];
			$acfForm = array(
				'id' => 'id-chunks-creator',
				'field_groups' => array( $wizBuilderFieldGroupId ),
				'updated_message' => false,
				'html_after_fields' => '<div class="scrollSpace"></div>'
			);
			acf_form( $acfForm );
			?>

		</div>
	</div>

	<div class="right" id="preview" type="text/html">


		<div id="templateActions">

			<div class="innerWrap">
				<?php if ( is_user_favorite( get_the_ID(), 'Template' ) ) {
					$fileStarClass = 'fa-solid';
				} else {
					$fileStarClass = 'fa-regular';
				}
				?>
				<i title="Add/Remove Favorite" class="addRemoveFavorite <?php echo $fileStarClass; ?> fa-star"
					data-objecttype="Template" data-objectid="<?php echo get_the_ID(); ?>"></i>
				<a title="Save Template" class="wiz-button green" id="saveTemplate"><i
						class="fa-solid fa-floppy-disk"></i>&nbsp;&nbsp;Save</a>
				<a title="Get Template Code" class="wiz-button showFullMode" id="showFullCode" data-preview-mode="edit"
					data-postid="<?php echo get_the_id(); ?>"><i class="fa-solid fa-code"></i>&nbsp;&nbsp;Code</a>
				<a title="Show Full Preview" class="wiz-button showFullMode" id="showFullPreview"
					data-preview-mode="preview" data-postid="<?php echo get_the_id(); ?>"><i
						class="fa-solid fa-eye"></i>&nbsp;&nbsp;Preview</a>
				<a title="Sync to Iterable" class="wiz-button" id="sendToIterable"
					data-postid="<?php echo get_the_id(); ?>"><img style="width: 20px; height: 20px;"
						src="https://idemailwiz.com/wp-content/uploads/2023/10/Iterable_square_logo-e1677898367554.png" />&nbsp;&nbsp
					Sync</a>
				<span class="templateActions-divider"></span>
				<a title="Duplicate Template" class="wiz-button duplicate-template"
					data-postid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-copy"></i></a>
				<a title="Move Template" class="wiz-button green moveTemplate"
					data-postid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-folder-tree"></i></i></a>
				<a title="Delete Template" class="wiz-button delete-template"
					data-postid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-trash"></i></a>



				<div id="deviceSwitcher">
					<i title="Desktop Preview" class="fas fa-desktop active" id="showDesktop"></i>
					<i title="Mobile Preview" class="fas fa-mobile-alt" id="showMobile"></i>
					<div title="Toggle Separators" class="toggle-separators active"><i
							class="fa-solid fa-xmarks-lines"></i></div>
					<div title="Fill Merge Tags" class="fill-merge-tags" data-postid="<?php echo get_the_ID(); ?>">
						&nbsp;{<i class="fa-solid fa-power-off"></i>}&nbsp;</div>
				</div>

			</div>
		</div>
		<div id="templatePreview">
			<iframe id="previewFrame" src="<?php echo home_url( 'build-template/' . get_the_ID() ); ?>"></iframe>
		</div>

	</div>
</div>

<?php
get_footer();
?>