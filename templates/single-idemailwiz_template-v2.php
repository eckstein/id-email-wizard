<?php
get_header();
$postId = get_the_ID();
$wizTemplate = get_wiztemplate( $postId);
$wizTemplateObject = get_wiztemplate_object($postId);

$current_user = wp_get_current_user();
$userId = $current_user->ID;
// $itTemplateId = get_post_meta( $postId, 'itTemplateId', true ) ?? '';
$itTemplateId = $wizTemplate['template-settings']['iterable-sync']['iterable_template_id'] ?? '';

?>
<header class="wizHeader">
	<div class="wizHeaderInnerWrap">
		<div class="wizHeader-left">
			<h1 id="single-template-title" class="wizEntry-title" title="<?php echo get_the_title(); ?>"
				itemprop="name">
				<input type="text" name="templateTitle" id="idwiz_templateTitle"
					data-templateid="<?php echo $postId; ?>"
					value="<?php echo get_the_title( $postId ); ?>" />
			</h1>
			<div class="wizEntry-meta">
				<strong>WizTemplate</strong>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;
				<span class="iDbreadcrumb">Located in:
					<?php echo display_template_folder_hierarchy( $postId ); ?>&nbsp;&nbsp;&#x2022;&nbsp;&nbsp;

					<?php
					$campaignSent = '';
					if ( $itTemplateId ) {
						$lastIterableSync = get_post_meta( $postId, 'lastIterableSync', true ) ?? '<em>an unknown date and time.</em>';
						echo 'Last synced to Iterable template <a target="_blank" href="https://app.iterable.com/templates/editor?templateId=' . $itTemplateId . '">' . $itTemplateId . '</a> on ' . $lastIterableSync;
						// check for wiz template to see if the campaign has been sent
						$wizDbTemplate = get_idwiz_template( (int) $itTemplateId );

						if ( $wizDbTemplate ) {
							$wizCampaign = get_idwiz_campaign( $wizDbTemplate['campaignId'] );
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
				<div class="wiz-button green show-new-template-ui">
					<i class="fa fa-plus"></i>&nbsp;&nbsp;New Template
				</div>
				<div title="Duplicate Template" class="wiz-button green duplicate-template"
					data-postid="<?php echo $postId; ?>">
					<i class="fa-solid fa-copy"></i>&nbsp;&nbsp;Duplicate
				</div>
				<div title="Move Template" class="wiz-button green moveTemplate"
					data-postid="<?php echo $postId; ?>">
					<i class="fa-solid fa-folder-tree"></i>&nbsp;&nbsp;Move
				</div>
				<div title="Delete Template" class="wiz-button red delete-template"
					data-postid="<?php echo $postId; ?>">
					<i class="fa-solid fa-trash"></i>&nbsp;&nbsp;Trash
				</div>
			</div>
		</div>
	</div>
</header>
<?php

//  echo '<pre style="color: white; max-height: 200px; overflow-y:auto;">';
//  print_r( $wizTemplate );
//  echo '</pre>';
?>
<div id="templateUI" class="entry-content two-col-wrap" data-postid="<?php echo $postId; ?>"
	data-iterableid="<?php echo $itTemplateId; ?>" data-campaignsent="<?php echo $campaignSent; ?>"
	itemprop="mainContentOfPage">

	<div class="left panel-left">
		<div id="builder">
			<div class="main-builder-header">
				<div id="main-builder-tabs" class="builder-tabs">
					<div class="builder-tab --active" data-tab="builder-tab-chunks" title="Content chunks"><i
							class="fa-solid fa-puzzle-piece"></i>&nbsp;&nbsp;Chunks</div>
					<div class="builder-tab" data-tab="builder-tab-styles" title="Template Styles"><i
							class="fa-solid fa-brush"></i>&nbsp;&nbsp;Styles</div>
					<div class="builder-tab" data-tab="builder-tab-message-settings" title="Message settings"><i
							class="fa-solid fa-envelope"></i>&nbsp;&nbsp;Options</div>
					<div class="builder-tab" data-tab="builder-tab-code"><i class="fa-solid fa-code"
							title="Code & JSON"></i></div>
					<div class="builder-tab" data-tab="builder-tab-settings" title="Template Settings"><i
							class="fa-solid fa-gear"></i></div>

				</div>
				<div class="main-builder-actions">
					<button title="Sync to Iterable" class="wiz-button" id="sendToIterable"
						data-postid="<?php echo $postId; ?>"><img style="width: 20px; height: 20px;"
							src="https://idemailwiz.com/wp-content/uploads/2023/10/Iterable_square_logo-e1677898367554.png" />&nbsp;&nbsp;
						Sync</button>

					<button for="wiz-template-form" class="wiz-button green" id="save-template"><i
							class="fa-regular fa-floppy-disk"></i>&nbsp;&nbsp;Save</button>



				</div>
			</div>
			<div class="builder-tab-content --active" id="builder-tab-chunks">
				<div class="builder-form-wrapper">
					<div class="builder-rows-wrapper">
						<?php


						if ( ! empty( $wizTemplate['rows'] ) ) {
							echo '<div class="blank-template-message hide"><em>Click <strong>Add Section</strong> below to start building your template.</em></div>';
							foreach ( $wizTemplate['rows'] as $rowIndex => $row ) {
								echo generate_builder_row( $rowIndex, $row );
							}
						} else {
							echo '<div class="blank-template-message show"><em>Click <strong>Add Section</strong> below to start building your template.</em></div>';
						}
						?>
					</div>
					<div class="builder-new-row">
						<i class="fas fa-plus-circle"></i>&nbsp;&nbsp;Add Section
					</div>

				</div>
			</div>

			<?php
			$templateOptions = $wizTemplate['template_options'] ?? [];
			$templateSettings = $templateOptions['message_settings'] ?? [];
			//print_r( $templateOptions );
			$templateStyles = $templateOptions['template_styles'] ?? [];
			?>

			<div class="builder-tab-content" id="builder-tab-styles">
				<div class="builder-vertical-tabs">
					<div class="template-settings-tabs">
						<div class="template-settings-tab active" data-tab="template-styles-tab-header-and-footer">
							Header & Footer
						</div>
						<div class="template-settings-tab" data-tab="template-styles-tab-body-and-background">
							Body & Background
						</div>
						<div class="template-settings-tab" data-tab="template-styles-tab-text-and-links">
							Text & Links
						</div>
						<div class="template-settings-tab" data-tab="template-styles-tab-custom-styles">
							Custom Styles
						</div>
					</div>
					<div class="template-settings-tabs-content">
						<?php //print_r( $templateStyles );                                              ?>
						<form id="template-styles-form">
							<?php
							$templateHeaderFooterStyles = $templateStyles['header-and-footer'] ?? [];
							?>
							<fieldset name="header-and-footer" class="template-settings-tab-content active"
								id="template-styles-tab-header-and-footer">
								<h5>Template Header</h5>
								<div class="builder-field-group flex">
									<?php
									$showHeader = $templateSettings['template_styles']['header-and-footer']['show_id_header'] ?? true;
									?>
									<div class="builder-field-wrapper">
										<label class="checkbox-toggle-label">Show Header</label>
										<div class="wiz-checkbox-toggle">
											<input type="checkbox" class="wiz-check-toggle"
												id="template_settings_show_id_header" name="show_id_header" hidden <?php echo $showHeader ? 'checked' : ''; ?>>
											<label for="template_settings_show_id_header"
												class="checkbox-toggle-replace <?php echo $showHeader ? 'active' : ''; ?>"><i
													class="<?php echo $showHeader ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
										</div>

									</div>
									<div class="builder-field-wrapper template-header-logo">
										<?php
										$templateHeaderLogo = $templateHeaderFooterStyles['template_header_logo'] ?? '';
										?>
										<label for="template_header_logo">Header Logo Image</label>
										<select name="template_header_logo" id="template_header_logo">
											<option
												value="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/cd7e1e87-607c-4e56-ad9c-0b0d34671949.png"
												<?php if ( $templateHeaderLogo == "https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/cd7e1e87-607c-4e56-ad9c-0b0d34671949.png" ) {
													echo 'selected="selected"';
												} ?>>
												Black transparent
											</option>
											<option
												value="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/1-Logo.png"
												<?php if ( $templateHeaderLogo == "https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/1-Logo.png" ) {
													echo 'selected="selected"';
												} ?>>
												White transparent
											</option>
											<option
												value="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/d09a7ff1-d8de-498b-9c4f-0a45374c7ab4.jpg"
												<?php if ( $templateHeaderLogo == "https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/d09a7ff1-d8de-498b-9c4f-0a45374c7ab4.jpg" ) {
													echo 'selected="selected"';
												} ?>>
												Black on white
											</option>
											<option value="manual" <?php if ( $templateHeaderLogo == "manual" ) {
												echo 'selected="selected"';
											} ?>>
												Manual link
											</option>
										</select>
									</div>

									<?php $showManualHeaderUrlField = $templateHeaderLogo != 'manual' ? 'hide' : ''; ?>
									<div
										class="builder-field-wrapper template-header-logo-manual <?php echo $showManualHeaderUrlField; ?>">
										<?php
										$templateHeaderLogo = $templateHeaderFooterStyles['template_header_logo_manual'] ?? '';
										?>
										<label for=" template_header_logo_manual">Header Logo Image</label>
										<input type="text" name="template_header_logo_manual"
											id="template_header_logo_manual" value="<?php echo $templateHeaderLogo; ?>">
									</div>

									<?php $headerPadding = $templateHeaderFooterStyles['header_padding'] ?? ''; ?>
									<div class='builder-field-wrapper header-padding small-input'><label
											for='header-chunk-padding'>Header Padding</label>
										<input type='text' name='header_padding' id='header-padding'
											value='<?php echo $headerPadding; ?>'>
									</div>


								</div>
								<h5>Template Footer</h5>

								<div class="builder-field-group flex">
									<div class="builder-field-wrapper centered">
										<?php $showFooter = $templateSettings['template_styles']['header-and-footer']['show_id_footer'] ?? true; ?>
										<label class="checkbox-toggle-label">Show Footer</label>
										<div class="wiz-checkbox-toggle">
											<input type="checkbox" class="wiz-check-toggle"
												id="template_settings_show_id_footer" name="show_id_footer" hidden <?php echo $showFooter ? 'checked' : ''; ?>>
											<label for="template_settings_show_id_footer"
												class="checkbox-toggle-replace <?php echo $showFooter ? 'active' : ''; ?>"><i
													class="<?php echo $showFooter ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
										</div>

									</div>
									<div class="builder-field-wrapper centered">
										<?php $showUnsub = $templateSettings['template_styles']['header-and-footer']['show_unsub'] ?? true; ?>
										<label class="checkbox-toggle-label">Show Unsub</label>
										<div class="wiz-checkbox-toggle">
											<input type="checkbox" class="wiz-check-toggle"
												id="template_settings_show_unsub" name="show_unsub" hidden <?php echo $showUnsub ? 'checked' : ''; ?>>
											<label for="template_settings_show_unsub"
												class="checkbox-toggle-replace <?php echo $showUnsub ? 'active' : ''; ?>"><i
													class="<?php echo $showUnsub ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
										</div>

									</div>
									<div class="builder-field-wrapper template-footer-text-color centered">
										<?php
										$templateFooterTextColor = $templateHeaderFooterStyles['template_footer_text_color'] ?? '#000';
										?>
										<label for="template_styles_footer_text_color">Text</label>
										<input class="builder-colorpicker" type="color"
											name="template_footer_text_color" id="template_styles_footer_text_color"
											data-color-value="<?php echo $templateFooterTextColor; ?>">
									</div>
									<div class="builder-field-wrapper template-footer-link-color centered">
										<?php
										$templateFooterLinkColor = $templateHeaderFooterStyles['template_footer_link_color'] ?? '#000';
										?>
										<label for="template_styles_footer_link_color">Links</label>
										<input class="builder-colorpicker" type="color"
											name="template_footer_link_color" id="template_styles_footer_link_color"
											data-color-value="<?php echo $templateFooterLinkColor; ?>">
									</div>
									<?php
									$forceWhiteTextDevices = [ 
										[ 'id' => "footer_" . "force-white-text-desktop",
											'name' => 'footer_force_white_text_on_desktop', 'display' => 'desktop', 'value' => true, 'label'
											=> '<i class="fa-solid fa-desktop"></i>' ],
										[ 'id' => "footer_" . "force-white-text-mobile",
											'name' => 'footer_force_white_text_on_mobile', 'display' => 'mobile', 'value' => true, 'label'
											=> '<i class="fa-solid fa-mobile-screen-button"></i>' ]
									];

									$forceWhiteTextDesktop = $templateHeaderFooterStyles['footer_force_white_text_on_desktop'];
									$forceWhiteTextMobile = $templateHeaderFooterStyles['footer_force_white_text_on_mobile'];

									?>

									<div
										class='button-group-wrapper builder-field-wrapper chunk-force-white-text-devices'>
										<label class='button-group-label'>Force Gmail white text on:</label>
										<div class='button-group conditional checkbox'>
											<?php foreach ( $forceWhiteTextDevices as $opt ) { ?>

												<?php
												$fieldID = $opt['id'];
												$isChecked = isset( $templateHeaderFooterStyles[ $opt['name'] ] ) && $templateHeaderFooterStyles[ $opt['name'] ] ? 'checked' : '';
												?>

												<input type='checkbox' id='<?php echo $fieldID; ?>'
													name='<?php echo $opt['name']; ?>' value='<?php echo $opt['value']; ?>'
													<?php echo $isChecked; ?>>
												<label for='<?php echo $fieldID; ?>' class='button-label'
													title='<?php echo $opt['display']; ?>'>
													<?php echo $opt['label']; ?>
												</label>

											<?php } ?>
										</div>
									</div>



								</div>
								<div class="builder-field-group flex">
									<div class="builder-field-wrapper template-footer-color">
										<?php
										$templateFooterColor = $templateHeaderFooterStyles['template_footer_color'] ?? 'transparent';
										?>
										<label for="template_styles_footer_color">Footer BG</label>
										<fieldset name="footer-background" id="template-footer-background">
											<?php echo generateBackgroundSettingsModule( $templateHeaderFooterStyles['footer-background'] ?? [], '', false ); ?>
										</fieldset>
									</div>
								</div>
							</fieldset>
							<fieldset name="body-and-background" class="template-settings-tab-content"
								id="template-styles-tab-body-and-background">
								<?php
								$bodyAndBackgroundStyles = $templateStyles['body-and-background'] ?? [];
								$templateWidth = $bodyAndBackgroundStyles['template_width'] ?? '648';
								$bodyBackground = $bodyAndBackgroundStyles['body-background'] ?? [];
								$pageBackground = $bodyAndBackgroundStyles['page-background'] ?? [];
								?>
								<div class="builder-field-group">
									<div class="builder-field-wrapper template_width">

										<h5>Template Width</h5>
										<span class="input-post-text-wrap"><input type="number"
												id="template_styles_template_width" name="template_width"
												class="builder-field" value="<?php echo $templateWidth; ?>"><span
												class="input-post-text">px</span></span>
									</div>
								</div>
								<fieldset name="body-background">
									<div class="builder-field-group">
										<div class="builder-field-wrapper body-background">
											<h5>Body Background</h5>
											<?php
											echo generateBackgroundSettingsModule( $bodyBackground, 'body_background_', false );
											?>
										</div>
									</div>
								</fieldset>
								<fieldset name="page-background">
									<div class="builder-field-group">
										<div class="builder-field-wrapper body-background">
											<h5>Page Background</h5>
											<?php
											echo generateBackgroundSettingsModule( $pageBackground, 'page_background_', false );
											?>
										</div>
									</div>

								</fieldset>
							</fieldset>
							<div class="template-settings-tab-content" id="template-styles-tab-text-and-links">
								<?php
								$templateFontStyles = $templateStyles['font-styles'] ?? [];
								?>
								<fieldset name="font-styles">
									<div class="builder-field-group inline">
										<div class='builder-field-wrapper flex'>
											<div class="builder-field-wrapper">
												<?php
												$templateFontSize = $templateFontStyles['template_font_size'] ?? '16px';
												?>
												<label for="template_styles_font_size">Font Size</label>
												<input type="text" id="template_styles_font_size"
													name="template_font_size" class="builder-field"
													value="<?php echo $templateFontSize; ?>">
											</div>
											<div class="builder-field-wrapper">
												<?php
												$templateLineHeight = $templateFontStyles['template_line_height'] ?? '1.5';
												?>
												<label for="template_styles_line_height">Line Height</label>
												<input type="text" id="template_styles_line_height"
													name="template_line_height" class="builder-field"
													value="<?php echo $templateLineHeight; ?>">
											</div>
										</div>
									</div>
								</fieldset>

								<fieldset name="link-styles">
									<?php

									$linkStyles = $templateStyles['link-styles'] ?? [];

									$linkStyleOptions = [ 
										[ 'id' => 'template_styles_underline_links', 'value' => 'underline', 'label' => '<strong><u style="font-family: serif;">U</u></strong>' ],
										[ 'id' => 'template_styles_italic_links', 'value' => 'italic', 'label' => '<strong><em style="font-family: serif;">I</em></strong>' ],
										[ 'id' => 'template_styles_bold_links', 'value' => 'bold', 'label' => '<strong style="font-family: serif;">B</strong>' ]
									];
									?>
									<div class="builder-field-group inline flex">
										<div class='button-group-wrapper builder-field-wrapper link-style-options'>
											<label>Link Styles</label>
											<div class="button-group checkbox">
												<?php foreach ( $linkStyleOptions as $opt ) : ?>
													<?php
													// Directly check the presence and truthiness of the value for each style option
													$isChecked = ! empty( $linkStyles[ $opt['value'] ] ) && $linkStyles[ $opt['value'] ] === true ? 'checked' : '';
													?>
													<input type='checkbox' id='<?php echo $opt['id']; ?>'
														name='<?php echo $opt['value']; ?>' value='true' <?php echo $isChecked; ?>>
													<label for='<?php echo $opt['id']; ?>' class='button-label'>
														<?php echo $opt['label']; ?>
													</label>
												<?php endforeach; ?>
											</div>
										</div>

										<div class="builder-field-wrapper template-link-color centered">
											<?php
											$templateLinkColor = $linkStyles['template_link_style_color'] ?? '#0073dd';
											?>
											<label for="template_style_link_color">Links</label>
											<input class="builder-colorpicker" type="color"
												name="template_link_style_color" id="template_style_link_color"
												data-color-value="<?php echo $templateLinkColor; ?>">
										</div>
										<div class="builder-field-wrapper template-visited-link-color centered">
											<?php
											$templateLinkHoverColor = $linkStyles['template_link_style_hover_color'] ?? $templateLinkColor;
											?>
											<label for="template_link_style_hover_color">Hover</label>
											<input class="builder-colorpicker" type="color"
												name="template_link_style_hover_color"
												id="template_link_style_hover_color"
												data-color-value="<?php echo $templateLinkHoverColor; ?>">
										</div>

									</div>
								</fieldset>
								<fieldset name="external-utms">

								</fieldset>
							</div>

							<fieldset name="custom-styles" class="template-settings-tab-content"
								id="template-styles-tab-custom-styles">
								<?php
								$includeDarkModeSupport = $customStyles['dark-mode-support'] ?? false;
								?>
								<div class="builder-field-wrapper">
									<label class="checkbox-toggle-label">Include Dark Mode Support Meta Tag</label>
									<div class="wiz-checkbox-toggle">
										<input type="checkbox" class="wiz-check-toggle"
											id="template_styles_dark_mode_support" name="dark-mode-support" hidden <?php echo $includeDarkModeSupport ? 'checked' : ''; ?>>
										<label for="template_styles_dark_mode_support"
											class="checkbox-toggle-replace <?php echo $includeDarkModeSupport ? 'active' : ''; ?>"><i
												class="<?php echo $includeDarkModeSupport ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
									</div>

								</div>

								<?php
								$customStyles = $templateStyles['custom-styles'] ?? [];
								?>
								<div class="builder-field-wrapper block">
									<label for="template_styles_additional_css">Additional CSS</label>
									<textarea id="template_styles_additional_css" name="additional_template_css"
										class="builder-field"><?php echo $customStyles['additional_template_css'] ?? ''; ?></textarea>
								</div>

							</fieldset>

						</form>
					</div>
				</div>
			</div>
			<div class="builder-tab-content" id="builder-tab-message-settings">
				<!-- Template Settings Section -->
				<div class="template-settings">
					<form id="template-settings-form">
						<?php
						$email_type = $templateSettings['email_type'] ?? 'promotional';
						?>
						<div class="builder-field-group">
							<div class="builder-field-wrapper">
								<label>Email Channel</label>
								<div class="button-group radio">
									<div class="builder-field-wrapper">
										<input type="radio" id="template_settings_email_type_promotional"
											name="email_type" value="promotional" hidden <?php echo $email_type == 'promotional' ? 'checked' : ''; ?>>
										<label for="template_settings_email_type_promotional" class="button-label"><i
												class="fa-solid fa-bullhorn"></i>
											Promotional</label>
									</div>
									<div class="builder-field-wrapper">
										<input type="radio" id="template_settings_email_type_transactional"
											name="email_type" value="transactional" hidden <?php echo $email_type == 'transactional' ? 'checked' : ''; ?>>
										<label for="template_settings_email_type_transactional" class="button-label"><i
												class="fa-solid fa-file-invoice-dollar"></i>
											Transactional</label>
									</div>
								</div>
							</div>
						</div>

						<div class="builder-field-wrapper block">
							<label for="template_settings_subject_line">Subject Line</label>
							<input type="text" id="template_settings_subject_line" name="subject_line"
								class="builder-field" value="<?php echo $templateSettings['subject_line'] ?? ''; ?>">
						</div>

						<div class="builder-field-wrapper block">
							<label for="template_settings_preview_text">Preview Text</label>
							<input type="text" id="template_settings_preview_text" name="preview_text"
								class="builder-field" value="<?php echo $templateSettings['preview_text'] ?? ''; ?>">
						</div>

						<div class="builder-field-wrapper block">
							<label for="template_settings_from_name">From Name</label>
							<input type="text" id="template_settings_from_name" name="from_name" class="builder-field"
								value="<?php echo $templateSettings['from_name'] ?? 'iD Tech Camps'; ?>">
						</div>

						<div class="builder-field-wrapper block">
							<label for="template_settings_reply_to">Reply To</label>
							<input type="email" id="template_settings_reply_to" name="reply_to" class="builder-field"
								value="<?php echo $templateSettings['reply_to'] ?? 'hello@idtech.com'; ?>">
						</div>

						<div class="builder-field-wrapper block">
							<label for="template_settings_fine_print_disclaimer">Fine Print/Disclaimer</label>
							<textarea id="template_settings_fine_print_disclaimer" name="fine_print_disclaimer"
								class="builder-field"><?php echo $templateSettings['fine_print_disclaimer'] ?? ''; ?></textarea>
						</div>
						<div class="builder-field-wrapper">
							<?php $extUtms = $templateSettings['template_styles']['header-and-footer']['ext_utms'] ?? true; ?>
							<label class="checkbox-toggle-label">Ext. UTMs</label>
							<div class="wiz-checkbox-toggle">
								<input type="checkbox" class="wiz-check-toggle" id="template_settings_ext_utms"
									name="ext_utms" hidden <?php echo $extUtms ? 'checked' : ''; ?>>
								<label for="template_settings_ext_utms"
									class="checkbox-toggle-replace <?php echo $extUtms ? 'active' : ''; ?>"><i
										class="<?php echo $extUtms ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
							</div>

						</div>

						<div class="builder-field-wrapper">
							<label for="template_settings_ext_utm_string">External UTM String</label>
							<input type="text" id="template_settings_ext_utm_string" name="ext_utm_string"
								class="builder-field"
								value="<?php echo $templateSettings['template_styles']['header-and-footer']['ext_utm_string'] ?? ''; ?>">
						</div>



						<!-- Email Settings Section -->

					</form>
				</div>
			</div>
			<div class="builder-tab-content" id="builder-tab-code">
				<div class="builder-code-wrapper">
					<div class="builder-code-actions">
						<button id="copyCode" class="wiz-button green"><i class="fa-solid fa-copy"></i>&nbsp;&nbsp;Copy
							HTML</button>
						<button id="viewJson" class="wiz-button green" data-post-id="<?php echo $postId ?>"><i
								class="fa-solid fa-code"></i>&nbsp;&nbsp;View JSON</button>
						<button id="exportJson" class="wiz-button green" data-post-id="<?php echo $postId ?>">
							<i class="fa-solid fa-file-export"></i>&nbsp;&nbsp;Export JSON</button>
						<button id="importJson" class="wiz-button green" data-post-id="<?php echo $postId ?>">
							<i class="fa-solid fa-file-import"></i>&nbsp;&nbsp;Import JSON</button>
					</div>
					<pre
						id="templateCode"><code><?php echo htmlspecialchars( generate_template_html( $wizTemplate, false ) ); ?></code></pre>
				</div>
			</div>
			<div class="builder-tab-content" id="builder-tab-settings">
				<div class="builder-field-group">
					<h4>Sync Settings</h4>
					<fieldset name="iterable-sync">
						<h5>Iterable Sync</h5>
						<div class="builder-field-wrapper block">
							<label for="iterable_template_id">Iterable Template Id</label>
							<input type="text" id="iterable_template_id" name="iterable_template_id"
								class="builder-field"
								value="<?php echo $templateOptions['template_settings']['iterable-sync']['iterable_template_id'] ?? ''; ?>">
						</div>

					</fieldset>
				</div>
				<div class="builder-field-group">
					<h4>Interface Settings</h4>
					<fieldset name="interface-settings">
						<div class="builder-field-wrapper centered">
							<label class="checkbox-toggle-label">Save Collapse States</label>
							<div class="wiz-checkbox-toggle">
								<?php $saveCollapseStates = $templateOptions['template_settings']['interface-settings']['save_collapse_states'] ?? true; ?>
								<input type="checkbox" class="wiz-check-toggle"
									id="builder_settings_save_collapse_states" name="save_collapse_states" hidden <?php echo $saveCollapseStates ? 'checked' : ''; ?>>
								<label for="builder_settings_save_collapse_states"
									class="checkbox-toggle-replace <?php echo $saveCollapseStates ? 'active' : ''; ?>"><i
										class="<?php echo $saveCollapseStates ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
							</div>

						</div>
						<div class="builder-field-wrapper centered">
							<label class="checkbox-toggle-label">Auto-Collapse Rows</label>
							<div class="wiz-checkbox-toggle">
								<?php $autoCollapseRows = $templateOptions['template_settings']['interface-settings']['auto_collapse_rows'] ?? false; ?>
								<input type="checkbox" class="wiz-check-toggle" id="builder_settings_auto_collapse_rows"
									name="auto_collapse_rows" hidden <?php echo $autoCollapseRows ? 'checked' : ''; ?>>
								<label for="builder_settings_auto_collapse_rows"
									class="checkbox-toggle-replace <?php echo $autoCollapseRows ? 'active' : ''; ?>"><i
										class="<?php echo $autoCollapseRows ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
							</div>

						</div>
						<div class="builder-field-wrapper centered">
							<label class="checkbox-toggle-label">Auto-Collapse chunks (within rows)</label>
							<div class="wiz-checkbox-toggle">
								<?php $autoCollapsechunks = $templateOptions['template_settings']['interface-settings']['auto_collapse_chunks_within_rows'] ?? false; ?>
								<input type="checkbox" class="wiz-check-toggle"
									id="builder_settings_auto_collapse_chunks_within_rows"
									name="auto_collapse_chunks_within_rows" hidden <?php echo $autoCollapsechunks ? 'checked' : ''; ?>>
								<label for="builder_settings_auto_collapse_chunks_within_rows"
									class="checkbox-toggle-replace <?php echo $autoCollapsechunks ? 'active' : ''; ?>"><i
										class="<?php echo $autoCollapsechunks ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
							</div>

						</div>
						<div class="builder-field-wrapper centered">
							<label class="checkbox-toggle-label">Auto-Collapse chunks</label>
							<div class="wiz-checkbox-toggle">
								<?php $autoCollapsechunks = $templateOptions['template_settings']['interface-settings']['auto_collapse_chunks'] ?? false; ?>
								<input type="checkbox" class="wiz-check-toggle"
									id="builder_settings_auto_collapse_chunks" name="auto_collapse_chunks" hidden <?php echo $autoCollapsechunks ? 'checked' : ''; ?>>
								<label for="builder_settings_auto_collapse_chunks"
									class="checkbox-toggle-replace <?php echo $autoCollapsechunks ? 'active' : ''; ?>"><i
										class="<?php echo $autoCollapsechunks ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
							</div>

						</div>
					</fieldset>
				</div>
			</div>
			

		</div>

	</div>
	<div id="templateUIresizer" class="handle"></div>
	<div class="right panel-right" id="preview" type="text/html">


		<div id="templateActions">

			<div class="innerWrap">
				<?php
				// if ( is_user_favorite( $postId, 'Template' ) ) {
				// 	$fileStarClass = 'fa-solid';
				// } else {
				// 	$fileStarClass = 'fa-regular';
				// }
				?>
				<!-- <i title="Add/Remove Favorite" class="addRemoveFavorite <?php //echo $fileStarClass;                                                                        ?> fa-star"
					data-objecttype="Template" data-objectid="<?php //echo $postId;                                                                        ?>"></i> -->



				<div id="templatePreviewIcons">
					<i title="Desktop Preview" class="fas fa-desktop active" id="showDesktop"></i>
					<i title="Mobile Preview" class="fas fa-mobile-alt" id="showMobile"></i>
					<div id="preview_width_dragger"></div>
					<span class="templateActions-divider"></span>
					<i title="White Background" class="fa-solid fa-sun light-mode-interface active"></i>
					<i title="Dark Background" class="fa-solid fa-moon dark-mode-interface"></i>
					<div title="Transparent Background"
						class="interface-transparency-toggle transparent-mode-background">
					</div>
					<span class="templateActions-divider"></span>
					<div title="Fill Merge Tags" class="fill-merge-tags" data-postid="<?php echo $postId; ?>">
						&nbsp;<span style="font-size:.8em;">{{X}}</span>&nbsp;</div>
				</div>


				<button title="Refresh Preview" class="wiz-button green" id="refreshPreview"><i
						class="fa-solid fa-rotate"></i>&nbsp;&nbsp;Refresh</button>
				<button title="Show Preview Pane" class="wiz-button green show-preview" id="showFullPreview"
					data-preview-mode="preview" data-postid="<?php echo $postId; ?>"><i
						class="fa-solid fa-eye"></i>&nbsp;&nbsp;Full Preview</button>
			</div>
		</div>
		<div id="templatePreview">
			<div id="templatePreview-status">

			</div>
			<iframe id="previewFrame" src="<?php echo home_url( 'build-template-v2/' . $postId ); ?>"></iframe>
		</div>

	</div>
</div>

<?php
get_footer();
?>