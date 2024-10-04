<?php get_header();
$post_id = get_the_ID();
// Get the saved data
$saved_data = get_post_meta(get_the_ID(), '_recommendation_engine_data', true);

// Ensure $saved_data is an array
$saved_data = is_array($saved_data) ? $saved_data : array();

// Helper function to get nested array values safely
function get_nested_value($array, $keys, $default = '')
{
	foreach ($keys as $key) {
		if (!isset($array[$key])) {
			return $default;
		}
		$array = $array[$key];
	}
	return $array;
}
?>

<header class="wizHeader">
	<div class="wizHeaderInnerWrap">
		<div class="wizHeader-left">
			<h1 class="wizEntry-title single-wizcampaign-title" title="<?php echo get_the_title(); ?>" itemprop="name">
				<?php echo get_the_title(); ?>
			</h1>
			<div class="wizEntry-meta"><strong>Interactive Element</strong>
			</div>

		</div>
		<div class="wizHeader-right">
			<div class="wizHeader-actions">
				<button class="wiz-button green new-interactive"><i class="fa-regular fa-plus"></i>&nbsp;New
					Interactive</button>

			</div>
		</div>
	</div>
</header>
<?php //print_r(get_post_meta($post_id, '_recommendation_engine_data', true)); 
?>
<article id="post-<?php the_ID(); ?>" data-journey="<?php echo get_the_ID(); ?>">

	<div class="entry-content" itemprop="mainContentOfPage">
		<div class="interactive-builder-container wizcampaign-sections-row flex" data-saved-engine="<?php echo esc_attr(json_encode($saved_data)); ?>" id="rec-engine-builder-ui">
			<div class="builder-pane wizcampaign-section inset noPad">
				<div class="interactive-builder-pane-header">
					<div class="interactive-builder-tabs">
						<ul>
							<li class="active" data-tab="rec-selections">Selections</li>
							<li data-tab="rec-results">Results</li>
							<li data-tab="rec-settings">Settings</li>
							<li data-tab="rec-css" id="show-css-tab">CSS</li>
						</ul>
					</div>
					<div class="interactive-builder-actions">
						<button type="submit" class="wiz-button green save-interactive-btn">Save Module</button>
					</div>
				</div>
				<form id="interactive-builder-form">

					<div class="interactive-builder-tab-content active" data-tab="rec-selections">
						<fieldset name="selections" id="selections-fieldset">
							<div id="selections-container">

								<?php
								if (isset($saved_data['selections']) && is_array($saved_data['selections'])) {
									foreach ($saved_data['selections'] as $index => $selection) {
										echo generate_rec_builder_selection_group($index, $selection);
									}
								}
								?>
							</div>
							<button type="button" class="wiz-button add-selection-btn">Add Selection</button>
						</fieldset>
					</div>
					<div class="interactive-builder-tab-content" data-tab="rec-results">

						<fieldset name="results" class="chunk-settings-section">
							<div id="results-container" class="builder-field-group">
								<?php
								if (isset($saved_data['results']) && is_array($saved_data['results'])) {
									foreach ($saved_data['results'] as $index => $result) {
										echo generate_rec_builder_result_group($index, $result);
									}
								}
								?>
							</div>
							<button type="button" class="wiz-button add-result-btn">Add Result</button>
						</fieldset>
					</div>
					<div class="interactive-builder-tab-content" data-tab="rec-settings">
						<fieldset name="settings">
							<div class="builder-field-group flex distribute">
								<div class="builder-field-wrapper">
									<label for="progress-message">Default result message</label>
									<input type="text" id="progress-message" name="settings[progress_message]" value="<?php echo esc_attr(get_nested_value($saved_data, ['settings', 'progress_message'], 'Make your selections above!')); ?>">
								</div>
							</div>
							<div class="builder-field-group flex">
								<div class="builder-field-group flex">

									<div class="builder-field-wrapper">
										<label for="progress-message">Interactive wrapper classes</label>
										<input type="text" id="wrapper_classes" name="settings[wrapper_classes]" value="<?php echo esc_attr(get_nested_value($saved_data, ['settings', 'wrapper_classes'], 'rec_engine_wrapper')); ?>">
									</div>
								</div>
								<div class="builder-field-group flex distribute">
									<div class="builder-field-wrapper">
										<label for="progress-message">Interactive wrapper ID</label>
										<input type="text" id="wrapper_classes" name="settings[wrapper_id]" value="<?php echo esc_attr(get_nested_value($saved_data, ['settings', 'wrapper_id'], 'rec_engine_wrapper')); ?>">
									</div>
								</div>
							</div>
							<div class="builder-field-group">
								<div class="builder-field-wrapper">
									<label>Progressive Results</label>
									<div class="wiz-checkbox-toggle flex">
										<?php
										$allowIncCombos = $saved_data['settings']['allow_incomplete_combos'] ?? 'off';
										$checked = $allowIncCombos == 'on' ? true : false;
										$checkActiveClass = $checked ? 'fa-solid ' : 'fa-regular ';
										$labelActiveClass = $checked ? 'active' : '';
										?>

										<input type="checkbox" class="wiz-check-toggle" id="allow_incomplete_combos_input" name="settings[allow_incomplete_combos]" hidden="" <?php echo $checked ? 'checked' : ''; ?>>
										<label for="allow_incomplete_combos_input" class="wiz-check-toggle-display <?php echo $labelActiveClass; ?>"><i class="<?php echo $checkActiveClass; ?> fa-2x fa-square-check"></i></label>
										<div class="field-description">Allows results to show before at least one selection is made from each option group.</div>
									</div>

								</div>
							</div>
							
							<h4>Form submit options</h4>
							<div class="field-description">Used for live interactive form version in Gmail/Yahoo!/AOL</div>
							<div class="builder-field-group flex">

								<div class="builder-field-wrapper">
									<label for="form-action">Form action URL</label>
									<input type="text" id="form_action" name="settings[form_action]" value="<?php echo esc_attr(get_nested_value($saved_data, ['settings', 'form_action'], 'https://www.example.com/page')); ?>">
								</div>
								<div class="builder-field-wrapper">
									<label for="submit-button-text">Submit button text</label>
									<input type="text" id="submit_button_text" name="settings[submit_button_text]" value="<?php echo esc_attr(get_nested_value($saved_data, ['settings', 'submit_button_text'], 'Submit')); ?>">
								</div>
							</div>

						</fieldset>

					</div>
					<div class="interactive-builder-tab-content" data-tab="rec-css" id="module-css-tab">
						<fieldset name="module-css">
							<textarea id="module-css" name="module_css" rows="10" cols="50"><?php echo $saved_data['module_css']; ?></textarea>
						</fieldset>
					</div>

				</form>
			</div>
			<div class="preview-pane wizcampaign-section inset">
				<div class="preview-header">
					<h2>Preview</h2>
					<div class="preview-actions">
						<button class="wiz-button green view-module-html"><i class="fa-solid fa-code"></i>&nbsp;&nbsp;View Code</button>
					</div>
				</div>
				<div id="preview-content"></div>
			</div>
		</div>

	</div>
</article>

<?php get_footer(); ?>