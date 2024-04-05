jQuery(document).ready(function ($) {

	// Return if no builder is in the dom
	if (!$("#builder").length) {
		return;
	}

	// Start a new session to track unsaved changes
	sessionStorage.setItem('unsavedChanges', 'false');

	window.onbeforeunload = function(e) {
		if (sessionStorage.getItem('unsavedChanges') === 'true') {
			// For modern browsers, a standard message will be shown, not this custom message.
			e.returnValue = 'You have unsaved changes!';
			return e.returnValue;
		}
	};

	// Save a session on page load so we have one to work with
	saveTemplateToSession();

	idwiz_updatepreview(true);

	// Initialize TinyMCE on all visible editors on page load
	builder_init_tinymce();

	// Initialize sortable elements
	initializeAllSortables();

	// Initialize editable elements (row and columns names)
	initializeEditable('.builder-row-title-text', 'row-id');
	initializeEditable('.builder-column-title-text', 'column-id');
	initializeEditable('.builder-columnset-title-text', 'columnset-id');

	// Initialize chunk tabs (content and settings)
	initializeChunkTabs();

	// Initialize color pickers
	initColorPickers();

	// Gradient picker show interface on click
	$(document).on('click', '.gradientLabel', function() {
		initGradientPicker(this);
	});

	// Initialize background type selection buttons
	initializeBackgroundTypeSelection();

	// Initialize sliders for all preview frames
	initialize_device_width_slider();


	


	// Handles any instances of .wizard-tab elements
	$(document).on("click", ".wizard-tab", function () {
		switch_wizard_tab(this);
	});

	// Setup click handlers for preview mode buttons
	$(document).on('click', '.showDesktopPreview, .showMobilePreview', function() {
		var mode = $(this).hasClass('showDesktopPreview') ? 'desktop' : 'mobile';
		update_template_device_preview($(this), mode);
	});

	//Save the template title when updated
	$(document).on("change", "#idwiz_templateTitle", function () {
		save_wiz_template_title($(this).data("templateid"), $(this).val());
	});

	
	// Manually refresh template preview
	$('#refreshPreview').on('click', function(e) {
		e.preventDefault();
		idwiz_updatepreview();
	});

	// Toggle editor background modes
	$('.editor-bg-mode').on('click', function () {
		updateBackgroundMode($(this).data('frame'), $(this).data('mode'));
	});


	

	// Save Template Button
	$(document).on('click', '#save-template', function(e) {
		e.preventDefault();
		saveTemplateToSession();
		saveTemplateData().then(function(saveTemplate) {
			do_wiz_notif({ message: saveTemplate.message, duration: 3000 });
			idwiz_updatepreview();
		}).catch(function(error) {
			console.error(error);
		});
	});

	// Click collapse/expand for row, column, and chunk headers
	$(document).on("click", ".builder-toggle, .builder-chunk-title", function(e){
		var $header = $(this).closest('.builder-row-header, .builder-columnset-header, .builder-chunk-header');
		toggleBuilderElementVis($header, e);
	});

	
	// Remove row
	$(document).on('click', '.remove-row', function() {
		if ($(this).closest('.builder-row').siblings('.builder-row').length < 1) {
			$('.blank-template-message').show();
		}
		remove_builder_element($(this).closest('.builder-row'));
	});

	// Remove chunk
	$(document).on('click', '.remove-columnset', function() {
		remove_builder_element($(this).closest('.builder-columnset'));
	});

	// Remove chunk
	$(document).on('click', '.remove-chunk', function() {
		remove_builder_element($(this).closest('.builder-chunk'));
	});


	 $(document).on('click', '.show-on-desktop, .show-on-mobile', function() {
		toggle_device_visibility($(this));
	});

	// When toggling the chunk wrapper for a raw html chunk, make it visible on all devices when wrapper is turn off.
	$(document).on('change', 'input[name=chunk_wrap]', function() {
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

	// Magic wrap toggle
	$(document).on('click', '.magic-wrap-toggle', function() {
		toggle_magic_wrap($(this));
	});


	// Codemirror for raw HTML chunk content
	  $('.wiz-html-block').each(function () {
		init_codemirror_chunk(this);
	  });

	
	// Codemirror for custom styles area
	init_codemirror_for_custom_styles();

	// Rotate columns switcher
	$(document).on('click', '.rotate-columns', function() {
		$(this).toggleClass('fa-rotate-90');
		var $row = $(this).closest('.builder-row');
		var $column = $(this).closest('.builder-column');

		// Rotate the columns
		$row.find('.builder-columnset-columns').css('flex-direction', $(this).hasClass('fa-rotate-90')? 'column' : 'row');

		// Update column data attribute
		$row.attr('data-column-stacked', $(this).hasClass('fa-rotate-90')? 'stacked' : 'false');

		expandBuilderElementVis($row, '.builder-row-content');
	});


	// Colset background toggle
	$(document).on('click', '.colset-bg-settings-toggle', function () {
		$(this).closest('.builder-columnset').find('.builder-columnset-settings-row').slideToggle();
	});
	$(document).on('click', '.row-bg-settings-toggle', function () {
		$(this).closest('.builder-row').find('.builder-row-settings-row').slideToggle();
	});


	
	//Apply saved gradient to any gradient picker labels that have one
	$('.chunk-gradient-settings .gradientLabel').each(function() {
		
		apply_gradient_data_to_color_picker_label($(this));
	});

	
	
	// Show a larger version of a chunk's preview image on hover
	$(document).on('mouseenter', '.image-chunk-preview-wrapper img', function(e) {
		generate_chunk_image_preview_flyover($(this).attr('src'));
		update_chunk_image_preview_flyover_position(e);
	}).on('mousemove', '.image-chunk-preview-wrapper img', function(e) {
		update_chunk_image_preview_flyover_position(e);
	}).on('mouseleave', '.image-chunk-preview-wrapper img', function() {
		$('#chunk-image-preview').hide();
	});



	// Attach click event to the label that visually represents the checkbox
	$(document).on('click', '.wiz-check-toggle-display', function(e) {
		// Prevent the default label behavior to ensure our custom logic runs smoothly
		e.preventDefault();

		toggle_wizard_button_group($(this));

		saveTemplateToSession();
		idwiz_updatepreview();

	});
	

	$(document).on('change', '#builder input, #builder select, #builder textarea, #builder .button-group *', function () {
		saveTemplateToSession();
		idwiz_updatepreview();
		refresh_chunks_html();
		sessionStorage.setItem('unsavedChanges', 'true');
	});


	

	// Add new blank row
	$(document).on('click', '.builder-new-row', function() {
		create_or_dupe_builder_row($(this));		
	});

	// Duplicate existing row 
	$(document).on('click', '.duplicate-row', function() {
		create_or_dupe_builder_row($(this));		
	});
	
	// Add new blank row
	$(document).on('click', '.add-columnset', function() {
		create_or_dupe_builder_columnset($(this));		
	});

	// Duplicate existing row 
	$(document).on('click', '.duplicate-columnset', function() {
		create_or_dupe_builder_columnset($(this));		
	});


	$(document).on('click', '.duplicate-chunk', function() {
		add_chunk_by_type($(this).closest('.builder-chunk').attr('data-chunk-type'), $(this), true);
	});


	// Handle click event on the column count adjustment settings icon
	$(document).on('click', '.columnset-column-settings', function() {
		
		var $this = $(this);
		var $row = $this.closest('.builder-row');

		// If row is collapsed, show it when columns are being adjusted
		if (!$row.find('.builder-row-content').is(':visible')) {
			expandBuilderElementVis($row, '.builder-row-content');
		}

		// Only append columns popup if it does not already exist
		if ($this.find('.column-selection-popup').length === 0) {
			//var currentColumnCount = $this.data('columns');
			var currentColumnCount = $this.closest('.builder-row').find('.builder-column.active').length;
			var popupHtml = generateColumnSelectionPopup(currentColumnCount);
			$this.append(popupHtml);
		} else {
			// Close the popup
			$('.column-selection-popup').remove();
		}
	});

	// Column select
	$(document).on('mouseenter mouseleave', '.column-select-option', function(event) {
		var hoverColumnCount = $(this).data('columns');
		$('.column-select-option').each(function() {
			var columnNum = $(this).data('columns');
			$(this).toggleClass('hovered', event.type === 'mouseenter' && columnNum <= hoverColumnCount);
		});
	});

	// Handle column selection from the pop-up
	$(document).on('click', '.column-select-option', function() {
		var selectedLayout = $(this).data('layout');
		handleColumnSelection($(this), selectedLayout);

		// Close the popup
		$('.column-selection-popup').remove();
	});

	// Hide column selection on outside click
	$(document).on('click', function(event) {
		if (!$(event.target).closest('.column-selection-popup, .columnset-column-settings').length) {
			$('.column-selection-popup').remove();
		}
	});


	

	// Destroy and re-init sortables on columns
	// function updateColumnSortables(row_id) {
	// 	// Target the specific row by row_id
	// 	var $rowColumns = $(document).find('.builder-row[data-row-id="' + row_id + '"]').find('.builder-columnset-columns');

	// 	// Check if Sortable is initialized on this specific instance
	// 	if ($rowColumns.hasClass('ui-sortable')) {
	// 		// If initialized, destroy existing Sortable
	// 		$rowColumns.sortable('destroy');
	// 	}

	// 	// Re-initialize Sortable on the specific row's columns
	// 	initializeColumnSortables();
	// }


	 // Show add chunk menu
	 $(document).on('click', '.add-chunk', function() {
		toggleLayoutChoices($(this));
	 });

	 // Handle add chunk menu seleciton
	 $(document).on('click', '.wiz-tiny-dropdown-options', function(event) {
		//event.preventDefault();
		var $this = $(this);

		// When a layout option is chosen
		var chunkType = $this.data('layout');
		var addChunkTrigger = $this.closest('.add-chunk-wrapper').find('.add-chunk');
		add_chunk_by_type(chunkType, addChunkTrigger);
		$('.wiz-tiny-dropdown').hide(); // Hide menu after selection

	});

	// Hide the layout choices when clicking outside
	$(document).on('click', function(event) {
		if (!$(event.target).closest('.add-chunk, .wiz-tiny-dropdown').length) {
			$('.wiz-tiny-dropdown').hide();
		}
	});
	

	
	
	// Column settings toggle
	$(document).on('click', '.builder-column-header > *:not(.exclude-from-toggle)', function (e) {
		toggle_column_settings($(this));
	});


	// Update chunk preview headers when content fields are updated
	
	$(document).on('change','.builder-chunk[data-chunk-type="image"] input[name="image_url"]', function() {
		updateBuilderChunkPreview('image', this);
	});

	$(document).on('change', '.builder-chunk[data-chunk-type="button"] input[name="button_text"]', function() {
		updateBuilderChunkPreview('button', this);
	});

	$(document).on('change', '.builder-chunk[data-chunk-type="spacer"] input[name="spacer_height"]', function() {
		updateBuilderChunkPreview('spacer', this);
	});

	$(document).on('change', '.builder-chunk[data-chunk-type="snippet"] select[name="select_snippet"]', function() {
		updateBuilderChunkPreview('snippet', this);
	});


	$(".show-preview").on("click", function () {
		show_template_preview($(this));
	});

	
	//Close preview popup
	$(document).on("click", "#hideTemplatePreview", function() {
		close_preview_popup();
	});

	
	// Copies the code, specified by the data-code-in attribute, to the clipboard
	$(document).on("click", "[data-code-in]", function () {
		copy_code_to_clipboard($(this));
	});


	// View JSON in popup
	$("#viewJson").on("click", function () {
		var templateId = $(this).data("post-id");
		getWizTemplateJson(templateId, displayJsonData);
	});

	// Function to export JSON data
	$("#exportJson").on("click", function () {
		download_template_json($(this));
	});

	

	$('#importJson').on('click', function () {
		importWizTemplateJson();
	});


	$('.template-settings-tab').on('click', function () {
	var tab = $(this).attr('data-tab');
		$('.template-settings-tab').removeClass('active');
		$(this).addClass('active');
		$('.template-settings-tab-content').removeClass('active');
		var tabContent = $(document).find(`#${tab}`);
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

	// Chunk wrap setting for raw html chunks
	$(document).on('change', '[name=chunk_wrap]', function() {
		toggleChunkWrapSettings($(this));
	});

	 // Mockup Tab functionality
	$('.mockup-tabs li').on('click', function () {
	  var tabId = $(this).data('tab');
	  $('.mockup-tabs li').removeClass('active');
	  $(this).addClass('active');
	  $('.mockup-tab-content').addClass('hidden');
	  $('#' + tabId).removeClass('hidden');
	});

	// File upload functionality
	$('.mockup-upload-field').on('change', function () {
	  var uploadInput = $(this);
	  var mockupDisplay = $(uploadInput.data('preview'));
	  var urlInput = $(uploadInput.data('url'));
	  var file = this.files[0];
	  var formData = new FormData();
	  formData.append('file', file);
	  formData.append('action', 'upload_mockup');

	  $.ajax({
		url: idAjax_template_editor.ajaxurl,
		type: 'POST',
		data: formData,
		processData: false,
		contentType: false,
		success: function (response) {
		  if (response.success) {
			mockupDisplay.find('img').attr('src', response.data.url);
			urlInput.val(response.data.url);
			uploadInput.parent('.mockup-uploader').addClass('hidden');
			mockupDisplay.removeClass('hidden');
		  } else {
			console.error('Error uploading mockup:', response.data);
		  }
		},
		error: function (xhr, status, error) {
		  console.error('AJAX error:', error);
		}
	  });
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



	

	

	setTimeout(function  () {
	 //updateInterfaceColors();
	},500);

	$('.interface_colors_picker').on('change', function() {
		setTimeout(function  () {
		 updateInterfaceColors();
		},2000);
	});

	$('.reset-interface-colors').on('click', function() {
		// Reset the color picker inputs
		$('.interface_colors_picker').each(function() {
			var color;
			if ($(this).attr('name') == 'row_color') {
				color = '#EEEEEE';
			} else if ($(this).attr('name') == 'colset_color') {
				color = '#FFFFFF';
			} else if ($(this).attr('name') == 'column_color') {
				color = '#EEEEEE';
			} else if ($(this).attr('name') == 'chunk_color') {
				color = '#FFFFFF';
			}

			$(this).attr('data-color-value', color);
			$(this).spectrum("set", color);
			$(this).trigger('change');
		});

		setTimeout(function () {
			updateInterfaceColors();
		}, 200);
	});
		


	


});





