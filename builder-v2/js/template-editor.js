jQuery(document).ready(function($) {

	// Check if the #builder element exists on the page
	if ($("#builder").length > 0) {
	/*
	****************
	* On Page Load
	****************
	*/	

		// Start a new session to track unsaved changes
		sessionStorage.setItem('unsavedChanges', 'false');

		// Initialize template
		initialize_template();

		// Warn before reload with unsaved changes
		window.onbeforeunload = function(e) {
			if (sessionStorage.getItem('unsavedChanges') === 'true') {
				// For modern browsers, a standard message will be shown, not this custom message.
				e.returnValue = 'You have unsaved changes!';
				return e.returnValue;
			}
		};

	/*
	****************
	* Builder UI Interactions
	****************
	*/

		// Save Template Button
		$("#builder").on('click', '#save-template', function(e) {
			e.preventDefault();
			save_template_to_session();
			save_template_data().then(function(saveTemplate) {
				do_wiz_notif({ message: saveTemplate.message, duration: 3000 });
				update_template_preview();
			}).catch(function(error) {
				console.error(error);
			});
		});

		// Auto refresh template preview on changes
		$("#builder").on('change', 'input, select, textarea, .button-group *', function () {
			update_chunk_data_attr_data();
			save_template_to_session(); // includes updating template and chunk html
			update_template_preview();
			updateChunkPreviews($(this).closest('.builder-chunk'));
			sessionStorage.setItem('unsavedChanges', 'true');
			
		});

		

		//Save the template title when updated
		$("#builder").on("change", "#idwiz_templateTitle", function () {
			//save_wiz_template_title($(this).data("templateid"), $(this).val());
		});

	/*
	****************
	* Builder UI Interactions
	*** Template Settings/Options/Styles
	****************
	*/

	// Template settings tabs
	$('.template-settings-tab').on('click', function () {
	var tab = $(this).attr('data-tab');
		$('.template-settings-tab').removeClass('active');
		$(this).addClass('active');
		$('.template-settings-tab-content').removeClass('active');
		var tabContent = $("#builder").find(`#${tab}`);
		tabContent.addClass('active');
	});

	// template header logo select
	 $('#template_header_logo').on('change', function() {
		// Check if the selected value is 'manual'
		if ($(this).val() === 'manual') {
			// Show the manual input field
			$('.template-header-logo-manual').show();
		} else {
			// Hide the manual input field
			$('.template-header-logo-manual').hide();
		}
	});

	// Merge tags inserting for Subject line and Preview Text
	$('.builder-field').on('click keyup', function() {
		const cursorPosition = $(this).prop('selectionStart');
		const selectedText = $(this).val().substring($(this).prop('selectionStart'), $(this).prop('selectionEnd'));
		$(this).data('cursorPosition', cursorPosition);
		$(this).data('selectedText', selectedText);
	  });

	  $('.insert-merge-tag').on('click', function() {
		const insertText = $(this).data('insert');
		const targetField = $(this).closest('.message-settings-merge-tags').data('field');
		$(targetField).insertMergeTag(insertText);
	  });

	/*
	****************
	* Builder UI Interactions
	*** Mock-ups
	****************
	*/
		// Mockup Device Tabs functionality
		$('.mockup-tabs li').on('click', function () {
		var tabId = $(this).data('tab');
		$('.mockup-tabs li').removeClass('active');
		$(this).addClass('active');
		$('.mockup-tab-content').addClass('hidden');
		$('#' + tabId).removeClass('hidden');
		});

		// File upload functionality
		$('.mockup-upload-field').on('change', function () {
			upload_wiz_mock(this);
		});



		// Upload new mock button click
		$('.upload-new-mock').on('click', function () {
		var type = $(this).data('type');
		var mockupUploader = $('#' + type + '-mockup .mockup-uploader');

		mockupUploader.toggleClass('hidden');
		});

		// Remove mock button click
		$('.remove-mock').on('click', function () {
		var type = $(this).data('type');
		var mockupUploader = $('#' + type + '-mockup .mockup-uploader');
		var mockupDisplay = $('#' + type + '-mockup .mockup-display');
		var urlInput = $('#' + type + '-mockup-url');
		mockupUploader.removeClass('hidden');
		mockupDisplay.addClass('hidden');
		mockupDisplay.find('img').attr('src', '');
		urlInput.val('');
		});
	
	/*
	****************
	* Builder UI Interactions
	*** Preview Pane
	****************
	*/

		// Manually refresh template preview
		$('#refreshPreview').on('click', function(e) {
			e.preventDefault();
			update_template_preview();
		});

		// Setup click handlers for preview mode buttons
		$("#templateUI").on('click', '.showDesktopPreview, .showMobilePreview', function() {
			var mode = $(this).hasClass('showDesktopPreview') ? 'desktop' : 'mobile';
			update_template_device_preview($(this), mode);
		});

		// Toggle editor background modes
		$('.editor-bg-mode').on('click', function () {
			update_preview_pane_background($(this).data('frame'), $(this).data('mode'));
		});

	
	/*
	****************
	* Builder UI Interactions
	*** Preview Popup
	****************
	*/
		$(".show-preview").on("click", function () {
			show_template_preview($(this));
		});

		
		//Close preview popup
		$(document).on("click", "#hideTemplatePreview", function() {
			close_preview_popup();
		});

	/*
	****************
	* Builder UI Interactions
	*** Chunk Header Previews
	****************
	*/

		// Show a larger version of a chunk's preview image on hover
		$("#builder").on('mouseenter', '.image-chunk-preview-wrapper img', function(e) {
			generate_chunk_image_preview_flyover($(this).attr('src'));
			update_chunk_image_preview_flyover_position(e);
		}).on('mousemove', '.image-chunk-preview-wrapper img', function(e) {
			update_chunk_image_preview_flyover_position(e);
		}).on('mouseleave', '.image-chunk-preview-wrapper img', function() {
			$('#chunk-image-preview').hide();
		});

		// Update chunk preview headers when content fields are updated
		
		$("#builder").on('change','.builder-chunk[data-chunk-type="image"] input[name="image_url"]', function() {
			updateBuilderChunkPreview('image', this);
		});

		$("#builder").on('change', '.builder-chunk[data-chunk-type="button"] input[name="button_text"]', function() {
			updateBuilderChunkPreview('button', this);
		});

		$("#builder").on('change', '.builder-chunk[data-chunk-type="spacer"] input[name="spacer_height"]', function() {
			updateBuilderChunkPreview('spacer', this);
		});

		$("#builder").on('change', '.builder-chunk[data-chunk-type="snippet"] select[name="select_snippet"]', function() {
			updateBuilderChunkPreview('snippet', this);
		});

	/*
	****************
	* Builder UI Interactions
	*** Code and JSON
	****************
	*/

		// View JSON in popup
		$("#viewJson").on("click", function () {
			var templateId = $(this).data("post-id");
			get_wiztemplate_json(templateId, display_wiztemplate_json);
		});

		// Function to export JSON data
		$("#exportJson").on("click", function () {
			download_template_json($(this));
		});

		
		// Import json button
		$('#importJson').on('click', function () {
			import_wiztemplate_json();
		});

	/*
	****************
	* Builder Element Interactions
	****************
	*/

		// Click collapse/expand for row, column, and chunk headers
		$("#builder").on("click", ".builder-toggle, .builder-chunk-title", function(e){
			var $header = $(this).closest('.builder-row-header, .builder-columnset-header, .builder-chunk-header');
			toggleBuilderElementVis($header);
		});

		// Remove element from builder
		$("#builder").on('click', '.remove-element', function() {
			let $toRemove = $(this).closest('.builder-row, .builder-columnset, .builder-chunk');

			if ($toRemove.hasClass('builder-row')) {
				// If this is the last row, show the empty template message
				if ($toRemove.siblings('.builder-row').length < 1) {
				$('.blank-template-message').show();
				}
			}
			remove_builder_element($toRemove);
		});

		// Handles any instances of .wizard-tab elements
		$("#builder").on("click", ".wizard-tab", function () {
			switch_wizard_tab(this);
		});

		// Gradient picker show interface on click
		$("#builder").on('click', '.gradientLabel', function() {
			initGradientPicker(this);
		});

		// Toggle device visibility
		$("#builder").on('click', '.show-on-desktop, .show-on-mobile', function() {
			toggle_device_visibility($(this));
		});

		// Row and Colset background toggle
		$("#builder").on('click', '.colset-bg-settings-toggle, .row-bg-settings-toggle', function () {
			if ($(this).hasClass('colset-bg-settings-toggle')) {
				$(this).closest('.builder-columnset').find('.builder-columnset-settings-row').slideToggle();
			} else if ($(this).hasClass('row-bg-settings-toggle')) {
				$(this).closest('.builder-row').find('.builder-row-settings-row').slideToggle();
			}
		});
	
	/*
	****************
	* Builder Element Interactions
	*** Row Specific
	****************
	*/

		// Add new blank row
		$("#builder").on('click', '.add-row', function() {
			create_or_dupe_builder_row($(this));		
		});

		// Duplicate existing row 
		$("#builder").on('click', '.duplicate-row', function() {
			create_or_dupe_builder_row($(this));		
		});
			
	


	/*
	****************
	* Builder Element Interactions
	*** Columnset Specific
	****************
	*/
		// Add new blank columnset
		$("#builder").on('click', '.add-columnset', function() {
			create_or_dupe_builder_columnset($(this));		
		});

		// Duplicate existing columnset 
		$("#builder").on('click', '.duplicate-columnset', function() {
			create_or_dupe_builder_columnset($(this));		
		});

		// Magic wrap toggle
		$("#builder").on('click', '.magic-wrap-toggle', function() {
			toggle_magic_wrap($(this));
		});

		// Rotate columns switcher
		$('#builder').on('click', '.rotate-columns', function() {
			$(this).toggleClass('fa-rotate-90');
			var $row = $(this).closest('.builder-row');

			// Rotate the columns
			$row.find('.builder-columnset-columns').css('flex-direction', $(this).hasClass('fa-rotate-90')? 'column' : 'row');

			// Update column data attribute
			$row.attr('data-column-stacked', $(this).hasClass('fa-rotate-90')? 'stacked' : 'false');

			expandBuilderElementVis($row, '.builder-row-content');
		});

		// Handle click event on the column count adjustment settings icon
		$("#builder").on('click', '.columnset-column-settings', function() {
			var $this = $(this);
			var $colSet = $this.closest('.builder-columnset');

			// If row is collapsed, show it when columns are being adjusted
			if (!$colSet.find('.builder-columnset-content').is(':visible')) {
				expandBuilderElementVis($colSet, '.builder-columnset-content');
			}

			// Only append columns popup if it does not already exist
			if ($this.find('.column-selection-popup').length === 0) {
				var currentLayout = $colSet.attr('data-layout');
				var popupHtml = generate_column_layout_choices(currentLayout);
				$this.append(popupHtml);
			} else {
				// Close the popup
				$('.column-selection-popup').remove();
			}
		});

		// Column select
		$("#builder").on('mouseenter mouseleave', '.column-select-option', function(event) {
			var hoverColumnCount = $(this).data('columns');
			$('.column-select-option').each(function() {
				var columnNum = $(this).data('columns');
				$(this).toggleClass('hovered', event.type === 'mouseenter' && columnNum <= hoverColumnCount);
			});
		});

		// Handle column selection from the pop-up
		$("#builder").on('click', '.column-select-option', function () {
			var selectedLayout = $(this).data('layout');
			handle_column_selection($(this), selectedLayout);
		});

		// Hide column selection on outside click
		$("#builder").on('click', function(event) {
			if (!$(event.target).closest('.columnset-column-settings').length) {
				$('.column-selection-popup').remove();
			}
		});


	/*
	****************
	* Builder Element Interactions
	*** Column Specific
	****************
	*/

		// Column settings section toggle
		$("#builder").on('click', '.builder-column-header > *:not(.exclude-from-toggle)', function (e) {
			toggle_column_settings($(this));
		});


	/*
	****************
	* Builder Element Interactions
	*** Chunk Specific
	****************
	*/

		// Duplicate chunk
		$("#builder").on('click', '.duplicate-chunk', function() {
			add_chunk_by_type($(this).closest('.builder-chunk').attr('data-chunk-type'), $(this), true);
		});

		// Show add chunk menu
		$("#builder").on('click', '.add-chunk', function() {
			toggle_chunk_type_choices($(this));
		});

		// Handle add chunk menu seleciton
		$("#builder").on('click', '.wiz-tiny-dropdown-options', function(event) {
			//event.preventDefault();
			var $this = $(this);

			// When a layout option is chosen
			var chunkType = $this.data('layout');
			var addChunkTrigger = $this.closest('.add-chunk-wrapper').find('.add-chunk');
			add_chunk_by_type(chunkType, addChunkTrigger);
			$('.wiz-tiny-dropdown').hide(); // Hide menu after selection

		});

		// Hide the layout choices when clicking outside
		$("#builder").on('click', function(event) {
			if (!$(event.target).closest('.add-chunk, .wiz-tiny-dropdown').length) {
				$('.wiz-tiny-dropdown').hide();
			}
		});

		// Chunk wrap setting for raw html chunks
		$("#builder").on('change', '[name=chunk_wrap]', function() {
			toggle_chunkwrap_settings($(this));
		});

		// When toggling the chunk wrapper for a raw html chunk, make it visible on all devices when wrapper is turn off.
		$("#builder").on('change', 'input[name=chunk_wrap]', function() {
			//If value is changed to unchecked
			if (!$(this).is(':checked')) {
				var dtState = $(this).closest('.builder-chunk').find('.show-on-desktop').attr('data-show-on-desktop');
				var mobState = $(this).closest('.builder-chunk').find('.show-on-mobile').attr('data-show-on-mobile');
				if (dtState === 'false' || mobState === 'false') {
					toggle_device_visibility($(this), 'show-on-desktop');
					toggle_device_visibility($(this), 'show-on-mobile');
				}
			}
		});

		$("#builder").on('click', '.refresh-chunk-code', function () {
			refresh_chunk_html($(this));
		});


	/*
	****************
	* Utility Functions
	****************
	*/

		// Copies the code, specified by the data-code-in attribute, to the clipboard
		$(document).on("click", "[data-code-in]", function () {
			copy_code_to_clipboard($(this));
		});

		// Custom checkbox/radio button group toggle functionality
		$("#builder").on('click', '.wiz-check-toggle-display', function(e) {
			// Prevent the default label behavior to ensure our custom logic runs smoothly
			e.preventDefault();

			toggle_wizard_button_group($(this));

			save_template_to_session();
			update_template_preview();

		});

	}

});





