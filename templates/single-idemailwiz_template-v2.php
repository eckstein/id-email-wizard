<?php
acf_form_head();
get_header();

$current_user = wp_get_current_user();
$userId = $current_user->ID;
$itTemplateId = get_post_meta( get_the_ID(), 'itTemplateId', true ) ?? '';
$postId = get_the_ID();
?>
<header class="wizHeader">
	<div class="wizHeaderInnerWrap">
		<div class="wizHeader-left">
			<h1 id="single-template-title" class="wizEntry-title" title="<?php echo get_the_title(); ?>"
				itemprop="name">
				<input type="text" name="templateTitle" id="idwiz_templateTitle"
					data-templateid="<?php echo get_the_ID(); ?>"
					value="<?php echo get_the_title( get_the_ID() ); ?>" />
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
					data-postid="<?php echo get_the_ID(); ?>">
					<i class="fa-solid fa-copy"></i>&nbsp;&nbsp;Duplicate
				</div>
				<div title="Move Template" class="wiz-button green moveTemplate"
					data-postid="<?php echo get_the_ID(); ?>">
					<i class="fa-solid fa-folder-tree"></i>&nbsp;&nbsp;Move
				</div>
				<div title="Delete Template" class="wiz-button red delete-template"
					data-postid="<?php echo get_the_ID(); ?>">
					<i class="fa-solid fa-trash"></i>&nbsp;&nbsp;Trash
				</div>
			</div>
		</div>
	</div>
</header>
<?php
$wizTemplate = get_wizTemplate( $postId );
//  echo '<pre style="color: white; max-height: 200px; overflow-y:auto;">';
//  print_r( $wizTemplate );
//  echo '</pre>';
?>
<div id="templateUI" class="entry-content two-col-wrap" data-postid="<?php echo get_the_ID(); ?>"
	data-iterableid="<?php echo $itTemplateId; ?>" data-campaignsent="<?php echo $campaignSent; ?>"
	itemprop="mainContentOfPage">

	<div class="left panel-left">
		<div id="builder">
			<div class="main-builder-header">
				<div id="main-builder-tabs" class="builder-tabs">
					<div class="builder-tab --active" data-tab="builder-tab-chunks"><i
							class="fa-solid fa-puzzle-piece"></i>&nbsp;&nbsp;Chunks</div>
					<div class="builder-tab" data-tab="builder-tab-styles"><i
							class="fa-solid fa-brush"></i>&nbsp;&nbsp;Styles</div>
					<div class="builder-tab" data-tab="builder-tab-settings"><i
							class="fa-solid fa-sliders"></i>&nbsp;&nbsp;Settings</div>
					<div class="builder-tab" data-tab="builder-tab-code"><i
							class="fa-solid fa-code"></i>&nbsp;&nbsp;Code</div>

				</div>
				<div class="main-builder-actions">
					<button title="Sync to Iterable" class="wiz-button" id="sendToIterable"
						data-postid="<?php echo get_the_id(); ?>"><img style="width: 20px; height: 20px;"
							src="https://idemailwiz.com/wp-content/uploads/2023/10/Iterable_square_logo-e1677898367554.png" />&nbsp;&nbsp;
						Sync</button>

					<button for="wiz-template-form" class="wiz-button blue" id="save-draft"><i
							class="fa-regular fa-floppy-disk"></i>&nbsp;&nbsp;Save Draft</button>
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
			$templateOptions = $wizTemplate['templateOptions'] ?? [];
			$templateSettings = $templateOptions['templateSettings'] ?? [];
			//print_r( $templateOptions );
			$templateStyles = $templateOptions['templateStyles'] ?? [];
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
						<?php //print_r( $templateStyles );                           ?>
						<form id="template-styles-form">
							<?php
							$templateHeaderFooterStyles = $templateStyles['header-and-footer'] ?? [];
							?>
							<fieldset name="header-and-footer" class="template-settings-tab-content active"
								id="template-styles-tab-header-and-footer">
								<h5>Template Header</h5>
								<div class="builder-field-group flex">
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


								</div>
								<h5>Template Footer</h5>
								<div class="builder-field-group flex">
									<div class="builder-field-wrapper template-footer-color">
										<?php
										$templateFooterColor = $templateHeaderFooterStyles['template_footer_color'] ?? 'transparent';
										?>
										<label for="template_styles_footer_color">Footer BG</label>
										<input class="builder-colorpicker" type="color" name="template_footer_color"
											id="template_styles_footer_color"
											data-color-value="<?php echo $templateFooterColor; ?>">
									</div>
									<div class="builder-field-wrapper template-footer-text-color">
										<?php
										$templateFooterTextColor = $templateHeaderFooterStyles['template_footer_text_color'] ?? '#000';
										?>
										<label for="template_styles_footer_text_color">Footer Text</label>
										<input class="builder-colorpicker" type="color"
											name="template_footer_text_color" id="template_styles_footer_text_color"
											data-color-value="<?php echo $templateFooterTextColor; ?>">
									</div>
									<div class="builder-field-wrapper template-footer-link-color">
										<?php
										$templateFooterLinkColor = $templateHeaderFooterStyles['template_footer_link_color'] ?? '#000';
										?>
										<label for="template_styles_footer_link_color">Footer Links</label>
										<input class="builder-colorpicker" type="color"
											name="template_footer_link_color" id="template_styles_footer_link_color"
											data-color-value="<?php echo $templateFooterLinkColor; ?>">
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

										<div class="builder-field-wrapper template-link-color">
											<?php
											$templateLinkColor = $linkStyles['template_link_style_color'] ?? '#0073dd';
											?>
											<label for="template_link_color">Link</label>
											<input class="builder-colorpicker" type="color"
												name="template_link_style_color" id="template_link_color"
												data-color-value="<?php echo $templateLinkColor; ?>">
										</div>
										<div class="builder-field-wrapper template-visited-link-color">
											<?php
											$templateVisitedLinkColor = $linkStyles['template_link_style_visited_color'] ?? '#0073dd';
											?>
											<label for="template_link_visited_color">Visited</label>
											<input class="builder-colorpicker" type="color"
												name="template_link_style_visited_color"
												id="template_link_visited_color"
												data-color-value="<?php echo $templateVisitedLinkColor; ?>">
										</div>

									</div>
								</fieldset>
							</div>

							<fieldset name="custom-styles" class="template-settings-tab-content"
								id="template-styles-tab-custom-styles">
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
			<div class="builder-tab-content" id="builder-tab-settings">
				<!-- Template Settings Section -->
				<div class="template-settings">
					<form id="template-settings-form">
						<fieldset name="template-settings">
							<?php
							$email_type = $templateSettings['template-settings']['email_type'] ?? 'promotional';
							?>
							<div class="builder-field-group">
								<div class="builder-field-wrapper">
									<label>Email Channel</label>
									<div class="button-group radio">
										<div class="builder-field-wrapper">
											<input type="radio" id="template_settings_email_type_promotional"
												name="email_type" value="promotional" hidden <?php echo $email_type == 'promotional' ? 'checked' : ''; ?>>
											<label for="template_settings_email_type_promotional"
												class="button-label"><i class="fa-solid fa-bullhorn"></i>
												Promotional</label>
										</div>
										<div class="builder-field-wrapper">
											<input type="radio" id="template_settings_email_type_transactional"
												name="email_type" value="transactional" hidden <?php echo $email_type == 'transactional' ? 'checked' : ''; ?>>
											<label for="template_settings_email_type_transactional"
												class="button-label"><i class="fa-solid fa-file-invoice-dollar"></i>
												Transactional</label>
										</div>
									</div>
								</div>
							</div>
							<div class="builder-field-group flex">
								<?php
								$showHeader = $templateSettings['template-settings']['show_id_header'] ?? true;
								?>
								<div class="builder-field-wrapper">
									<label class="checkbox-toggle-label">Show iD Header</label>
									<div class="wiz-checkbox-toggle">
										<input type="checkbox" class="wiz-check-toggle"
											id="template_settings_show_id_header" name="show_id_header" hidden <?php echo $showHeader ? 'checked' : ''; ?>>
										<label for="template_settings_show_id_header"
											class="checkbox-toggle-replace <?php echo $showHeader ? 'active' : ''; ?>"><i
												class="<?php echo $showHeader ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
									</div>

								</div>

								<div class="builder-field-wrapper">
									<?php $showFooter = $templateSettings['template-settings']['show_id_footer'] ?? true; ?>
									<label class="checkbox-toggle-label">Show iD Footer</label>
									<div class="wiz-checkbox-toggle">
										<input type="checkbox" class="wiz-check-toggle"
											id="template_settings_show_id_footer" name="show_id_footer" hidden <?php echo $showFooter ? 'checked' : ''; ?>>
										<label for="template_settings_show_id_footer"
											class="checkbox-toggle-replace <?php echo $showFooter ? 'active' : ''; ?>"><i
												class="<?php echo $showFooter ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
									</div>

								</div>

								<div class="builder-field-wrapper">
									<?php $showUnsub = $templateSettings['template-settings']['show_unsub'] ?? true; ?>
									<label class="checkbox-toggle-label">Show Unsub Link</label>
									<div class="wiz-checkbox-toggle">
										<input type="checkbox" class="wiz-check-toggle"
											id="template_settings_show_unsub" name="show_unsub" hidden <?php echo $showUnsub ? 'checked' : ''; ?>>
										<label for="template_settings_show_unsub"
											class="checkbox-toggle-replace <?php echo $showUnsub ? 'active' : ''; ?>"><i
												class="<?php echo $showUnsub ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
									</div>

								</div>


								<div class="builder-field-wrapper">
									<?php $extUtms = $templateSettings['template-settings']['ext_utms'] ?? true; ?>
									<label class="checkbox-toggle-label">External UTMs</label>
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
										value="<?php echo $templateSettings['template-settings']['ext_utm_string'] ?? ''; ?>">
								</div>
							</div>
						</fieldset>
						<fieldset name="message-settings">

							<div class="builder-field-wrapper block">
								<label for="template_settings_subject_line">Subject Line</label>
								<input type="text" id="template_settings_subject_line" name="subject_line"
									class="builder-field"
									value="<?php echo $templateSettings['message-settings']['subject_line'] ?? ''; ?>">
							</div>

							<div class="builder-field-wrapper block">
								<label for="template_settings_preview_text">Preview Text</label>
								<input type="text" id="template_settings_preview_text" name="preview_text"
									class="builder-field"
									value="<?php echo $templateSettings['message-settings']['preview_text'] ?? ''; ?>">
							</div>

							<div class="builder-field-wrapper block">
								<label for="template_settings_from_name">From Name</label>
								<input type="text" id="template_settings_from_name" name="from_name"
									class="builder-field"
									value="<?php echo $templateSettings['message-settings']['from_name'] ?? 'iD Tech Camps'; ?>">
							</div>

							<div class="builder-field-wrapper block">
								<label for="template_settings_reply_to">Reply To</label>
								<input type="email" id="template_settings_reply_to" name="reply_to"
									class="builder-field"
									value="<?php echo $templateSettings['message-settings']['reply_to'] ?? 'hello@idtech.com'; ?>">
							</div>

							<div class="builder-field-wrapper block">
								<label for="template_settings_fine_print_disclaimer">Fine Print/Disclaimer</label>
								<textarea id="template_settings_fine_print_disclaimer" name="fine_print_disclaimer"
									class="builder-field"><?php echo $templateSettings['message-settings']['fine_print_disclaimer'] ?? ''; ?></textarea>
							</div>

						</fieldset>


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
					</div>
					<pre
						id="templateCode"><code><?php echo htmlspecialchars( generate_template_html( $wizTemplate, false ) ); ?></code></pre>
				</div>
			</div>
		</div>

	</div>
	<div id="templateUIresizer" class="handle"></div>
	<div class="right panel-right" id="preview" type="text/html">


		<div id="templateActions">

			<div class="innerWrap">
				<?php
				// if ( is_user_favorite( get_the_ID(), 'Template' ) ) {
				// 	$fileStarClass = 'fa-solid';
				// } else {
				// 	$fileStarClass = 'fa-regular';
				// }
				?>
				<!-- <i title="Add/Remove Favorite" class="addRemoveFavorite <?php //echo $fileStarClass;                                                     ?> fa-star"
					data-objecttype="Template" data-objectid="<?php //echo get_the_ID();                                                     ?>"></i> -->

				<span class="templateActions-divider"></span>

				<div id="templatePreviewIcons">
					<i title="Desktop Preview" class="fas fa-desktop active" id="showDesktop"></i>
					<i title="Mobile Preview" class="fas fa-mobile-alt" id="showMobile"></i>
					<div id="preview_width_dragger"></div>
					<i title="Reset to Light Mode" class="fa-solid fa-sun light-mode-reset active"></i>
					<div class="toggleDarkMode-dropdown" title="View Dark Modes">
						<i title="Toggle Dark Mode Options" class="fa-solid fa-moon"></i>
						<div class="wiz-tiny-dropdown">
							<div class="wiz-tiny-dropdown-options full-invert" title="Full Inversion">
								<i class="fa-solid fa-circle-half-stroke"></i>&nbsp;&nbsp;Full Inversion
							</div>
							<div class="wiz-tiny-dropdown-options partial-invert" title="Partial Inversion">
								<i class="fa-solid fa-circle-half-stroke"></i>&nbsp;&nbsp;Partial Inversion
							</div>
						</div>
					</div>

					<div title="Fill Merge Tags" class="fill-merge-tags" data-postid="<?php echo get_the_ID(); ?>">
						&nbsp;<span style="font-size:.8em;">{{X}}</span>&nbsp;</div>
				</div>

				<button title="Refresh Preview" class="wiz-button green" id="refreshPreview"><i
						class="fa-solid fa-rotate"></i>&nbsp;&nbsp;Refresh</button>
				<button title="Show Preview Pane" class="wiz-button green show-preview" id="showFullPreview"
					data-preview-mode="preview" data-postid="<?php echo get_the_id(); ?>"><i
						class="fa-solid fa-eye"></i>&nbsp;&nbsp;Full Preview</button>
			</div>
		</div>
		<div id="templatePreview">
			<div id="templatePreview-status">

			</div>
			<iframe id="previewFrame" src="<?php echo home_url( 'build-template-v2/' . get_the_ID() ); ?>"></iframe>
		</div>

	</div>
</div>

<?php
get_footer();
?>