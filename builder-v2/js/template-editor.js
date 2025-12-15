jQuery(document).ready(function($) {

	// Check if the #builder element exists on the page
	if ($("#builder").length > 0) {
	/*
	****************
	* On Page Load
	****************
	*/	

		// Flag to ignore change events during initialization
		window.wizBuilderInitializing = true;

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
	

	

		// Handle global actions on any builder update
		$("#builder").on('change', 'input, select, textarea', function (e) {
			// Ignore changes during initialization
			if (window.wizBuilderInitializing) return;
			
			// Delay these operations slightly to allow UI updates to complete
			setTimeout(() => {
				save_template_to_session();
				sessionStorage.setItem('unsavedChanges', 'true');
			}, 200);
		})


		// Handle field changes in the builder tabs
		$("#builder-tab-chunks, #builder-tab-styles, #builder-tab-message-settings").on('change', 'input, select, textarea', function() {
			handle_style_field_changes($(this));
		});


		

	/*
	****************
	* Builder UI Interactions
	*** Template Settings/Options/Styles
	****************
	*/
	
	// HTML code tab
	$('#builder-tab-code-tab').on('click', function() {
		refresh_template_html();
	});
	// Template settings tabs
	$('.template-settings-tab').on('click', function () {
	var tab = $(this).attr('data-tab');
		$('.template-settings-tab').removeClass('active');
		$(this).addClass('active');
		$('.template-settings-tab-content').removeClass('active');
		var tabContent = $("#builder").find(`#${tab}`);
		tabContent.addClass('active');
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

	// Add UTM parameter
	$('#add_utm_parameter').on('click', function (e) {
		e.preventDefault();
		var fieldsetArea = $('fieldset[name="utm_parameters"]');
		var utmSets = fieldsetArea.find('.utm_fields_wrapper');
		var utmSetsCount = utmSets.length;
		add_utm_fieldset_to_dom(utmSetsCount !== undefined ? utmSetsCount : 0);
	});

	// Remove UTM parameter
	$(document).on('click', '.remove_utm_parameter', function(e) {
		e.preventDefault();
		$(this).closest('.utm_fields_wrapper').remove();
		// If no UTM parameters left, show the "No UTM parameters set" message
		var fieldsetArea = $('fieldset[name="utm_parameters"]');
		if (fieldsetArea.find('.utm_fields_wrapper').length === 0) {
			fieldsetArea.html('<div class="no-utm-message field-description">No UTM parameters set.</div>');
		}
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
		
		// Clear cached scroll position and validate scroll after DOM changes
		sessionStorage.removeItem('scrollPosition_#builder-tab-mocks');
		validateBuilderPaneScroll();
		});

		// File upload functionality
		$('.mockup-upload-field').on('change', function () {
			upload_wiz_mock(this);
			console.log('Uploading mock...');
		});



		// Upload new mock button click
		$('.upload-new-mock').on('click', function () {
		var type = $(this).data('type');
		var mockupUploader = $('#' + type + '-mockup .mockup-uploader');

		mockupUploader.toggleClass('hidden');
		
		// Clear cached scroll position and validate scroll after DOM changes
		sessionStorage.removeItem('scrollPosition_#builder-tab-mocks');
		validateBuilderPaneScroll();
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
		
		// Clear cached scroll position and validate scroll after DOM changes
		sessionStorage.removeItem('scrollPosition_#builder-tab-mocks');
		validateBuilderPaneScroll();
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
			update_template_device_preview(mode);
		});

		// Toggle editor background modes
		$('.editor-bg-mode').on('click', function () {
			update_preview_pane_background($(this).data('frame'), $(this).data('mode'));
		});

		$(document).on('click', '.re-start-link-checker', function () {
			$('#link-analysis-modal').remove();
			setTimeout(function () {
				analyzeTemplateLinks();
			}, 50);
		});

		$('.start-link-checker').on('click', function () {
			if ($(document).find('#link-analysis-modal').length > 0) {
				toggleOverlay(true);
				$('#link-analysis-modal').show();
			} else {
				analyzeTemplateLinks();
			}
		});

		$(document).on('click', '.close-link-analysis', function () {
			closeAnalysisModal();
		});



	/*
	****************
	* Builder UI Interactions
	*** Preview Popup
	****************
	*/
		$("#showFullPreview").on("click", function () {
			const $right = $('#templateUI .right');
			const $previewFrame = $('#previewFrame');
    
			if ($right.hasClass('popout')) {
				$("#iDoverlay").hide();	
				$right.removeClass('popout');
				$(this).html('<i class="fas fa-expand"></i>&nbsp;&nbsp;Show Full Preview');
				$previewFrame.contents().find('.chunk').removeClass('off');
				// If the chunk has the .active-temp-off class, change it to .active
				if ($previewFrame.contents().find('.chunk.active-temp-off').length) {
					$previewFrame.contents().find('.chunk.active-temp-off').removeClass('active-temp-off').addClass('active');
				}
				// Remove styling on .outer
				$($previewFrame.contents()).find('.outer').removeClass('fullPreview')
			} else {
				$("#iDoverlay").show();	
				$right.addClass('popout');
				$(this).html('<i class="fas fa-compress-alt"></i>&nbsp;&nbsp;Hide Preview');
				$previewFrame.contents().find('.chunk').addClass('off');
				// If the chunk has the .active class, change it to .active-temp-off
				if ($previewFrame.contents().find('.chunk.active').length) {
					$previewFrame.contents().find('.chunk.active').removeClass('active').addClass('active-temp-off');
				}
				// Remove styling on .outer
				$($previewFrame.contents()).find('.outer').addClass('fullPreview')
			}
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
			$('.chunk-image-preview').hide();
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
			setTimeout(function() {
				update_template_preview_part($("#builder"), 'email_head');
			}, 100);
			
			
		});

	/*
	****************
	* Builder UI Interactions
	*** Code and JSON
	****************
	*/

		// View JSON in popup
		$("#viewJson").on("click", function () {
			var $button = $(this);
			var $icon = $button.find('i');
    
			// Show spinner
			$icon.removeClass('fa-code').addClass('fa-spinner fa-spin');
    
			var templateId = $button.data("post-id");
    
			get_wiztemplate_json(templateId, 
				function(data) {
					// Success callback
					display_wiztemplate_json(data);
					// Delay hiding spinner to ensure it's visible
					setTimeout(function() {
						$icon.removeClass('fa-spinner fa-spin').addClass('fa-code');
					}, 500); // 500ms delay
				},
				function() {
					// Error callback
					// Delay hiding spinner to ensure it's visible
					setTimeout(function() {
						$icon.removeClass('fa-spinner fa-spin').addClass('fa-code');
					}, 500); // 500ms delay
				}
			);
		});
		// Function to export JSON data
		$("#exportJson").on("click", function () {
			download_template_json($(this));
		});

		
		// Import json button
		$('#importJson').on('click', function () {
			import_wiztemplate_json();
		});

	// View MSO code in popup
	$("#viewMsoCode").on("click", function () {
		var $button = $(this);
		var $icon = $button.find('i');
    
		// Show spinner
		$icon.removeClass('fa-code').addClass('fa-spinner fa-spin');
    
		var templateId = $button.data("post-id");
    
		jQuery.ajax({
			url: idAjax.wizAjaxUrl,
			type: 'POST',
			data: {
				action: 'get_mso_html',
				postId: templateId,
				security: idAjax_template_editor.nonce
			},
			success: function(response) {
				// Hide spinner
				$icon.removeClass('fa-spinner fa-spin').addClass('fa-code');
            
				if (response.success && response.data && response.data.msoHtml) {
					Swal.fire({
						title: 'MSO HTML Code',
						html: response.data.msoHtml,
						customClass: {
							popup: 'mso-html-modal',
							htmlContainer: 'mso-html-pre-wrap'
						},
						width: '800px',
						didOpen: () => {
							document.querySelectorAll('pre code').forEach((block) => {
								hljs.highlightElement(block);
							});
						}
					});
				} else {
					console.error('Error: ', response.data ? response.data.message : 'Unknown error');
					do_wiz_notif({ message: response.data ? response.data.message : 'Failed to load MSO HTML', duration: 5000 });
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				// Hide spinner
				$icon.removeClass('fa-spinner fa-spin').addClass('fa-code');
            
				console.error('AJAX error: ' + textStatus + ' - ' + errorThrown);
				do_wiz_notif({ message: 'Failed to load MSO HTML. Please try again.', duration: 5000 });
			}
		});
	});
	/*
	****************
	* Builder Element Interactions
	****************
	*/

		// Click collapse/expand for row, column, and chunk headers
		$('#builder').on('click', '.builder-row-header, .builder-columnset-header, .builder-chunk-header', function(event) {
			event.preventDefault();
			// Don't toggle on .exclude-from-toggle elements
			if (!$(event.target).closest('.exclude-from-toggle').length) {
				toggleBuilderElementVis(jQuery(this));
			}
		});

		// Remove element from builder
		$("#builder").on('click', '.remove-element', function() {
			let $toRemove = $(this).closest('.builder-row, .builder-columnset, .builder-chunk');

			if ($toRemove.hasClass('builder-row')) {
				// If this is the last row, show the empty template message
				if ($toRemove.siblings('.builder-row').length < 1) {
					// prevent removing and show a warning
					do_wiz_notif({ message: 'You must have at least one row in your template!', duration: 5000 });
					return;
				}
			}
			remove_builder_element($toRemove);
		});

		// Handles any instances of .wizard-tab elements
		$("#builder").on("click", ".wizard-tab", function () {
			switch_wizard_tab(this);
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
		$("#builder").on('click', '.add-row, .duplicate-row', function() {
			create_or_dupe_builder_row($(this));		
		});
	
	


	/*
	****************
	* Builder Element Interactions
	*** Columnset Specific
	****************
	*/
		// Add new blank columnset
		$("#builder").on('click', '.add-columnset, .duplicate-columnset', function() {
			create_or_dupe_builder_columnset($(this));		
		});

		// Magic wrap toggle
		$("#builder").on('click', '.magic-wrap-toggle', function() {
			toggle_magic_wrap($(this));
		});

		// Mobile wrap toggle
		$("#builder").on('click', '.mobile-wrap-toggle', function() {
			toggle_mobile_wrap($(this));
		});

		

		// Rotate columns switcher
		$('#builder').on('click', '.rotate-columns', function() {
			var activeState = $(this).hasClass('active');
			$(this).toggleClass('active');
			$(this).find('i').toggleClass('fa-rotate-90');
			const $columnSet = $(this).closest('.builder-columnset');
			const $colWrapper = $columnSet.find('.builder-columnset-columns');

			// Expand the columnset content if it's collapsed
			if (!$columnSet.find('.builder-columnset-content').is(':visible')) {
				expandBuilderElementVis($columnSet, '.builder-columnset-content');
			}			

			// Update column data attribute
			$columnSet.attr('data-column-stacked', !activeState ? 'stacked' : 'false');

			setTimeout(function () {
				// Rotate the columns
				$colWrapper.attr('data-column-stacked', !activeState ? 'stacked' : 'false');
			}, 200);
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

		

	// Handle click event on share/JSON icon
	$("#builder").on('click', '.json-actions', function() {
			// Only append columns popup if it does not already exist
			if ($(this).find('.json-actions-popup').length === 0) {
				var popupHtml = generate_json_action_choices();
				$(this).append(popupHtml);
			} else {
				// Close the popup
				$('.json-actions-popup').remove();
			}
		
	});

	// Handle JSON actions select
	$("#builder").on('click', '.json-action-option', function () {
		var action = $(this).data('action');
		handle_wiz_json_action($(this), action);
	});



	// Hide clicking off popup menus
	$("#builder").on('click', function(event) {
		if (!$(event.target).closest('.columnset-column-settings').length) {
			$('.column-selection-popup').remove();
		}
		if (!$(event.target).closest('.json-actions').length) {
			$('.json-actions-popup').remove();
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

		// Handle add chunk menu selection
		$("#builder").on('click', '.wiz-tiny-dropdown-options', function(event) {
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
		$("#builder").on('change', '.toggle-chunk-wrap-input', function() {
			toggle_chunkwrap_settings($(this));
		});

		// When toggling the chunk wrapper for a raw html chunk, make it visible on all devices when wrapper is turn off.
		$("#builder").on('change', '.toggle-chunk-wrap-input', function() {
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

		$("#builder").on('click', '.chunk-tab', function() {
			var $thisTab = $(this);
			var targetSelector = $thisTab.data('target');
			var $targetContent = $(targetSelector);

			 // Update active state for tabs
			$thisTab.siblings().removeClass('active');
			$thisTab.addClass('active');

			// Show the target content
			$targetContent.siblings().removeClass('active');
			$targetContent.addClass('active');

       
		});

		// Refresh the chunk HTML when the HTML tab is clicked
		$("#builder").on('click', '.refresh-chunk-code', function () {
			refresh_chunk_html_tab($(this));
		});

		$("#builder").on('change', '.builder-chunk[data-chunk-type="snippet"] select[name="select_snippet"]', function() {
			var $snippetPostId = $(this).val();
			var site_url = idAjax.site_url;

			var snippetEditLink = $(this).closest('.builder-chunk').find('.snippet-edit-link a');
			var newLink = '<a href="' + site_url + '/?p=' + $snippetPostId + '" target="_blank">Edit Snippet</a>';
			$(snippetEditLink).replaceWith(newLink);
		});

	/*
	****************
	* Builder UI Interactions
	*** Wiz Modal
	****************
	*/
	$(document).on('click', '.wiz-modal-close', function () {
		wizCloseModal();
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

		
	$("#builder").on('click', '.wiz-check-toggle-display', function(e) {
		save_template_to_session();
	});

	// Handle email type (promotional/transactional) radio button changes
	$('input[name="email_type"]').on('change', function() {
		var selectedType = $(this).val();
		var $promotionalTypes = $('.promotional-types');
		var $transactionalTypes = $('.transactional-types');
		var $hiddenInput = $('#template_settings_message_type_id');
		
		if (selectedType === 'promotional') {
			// Show promotional, hide transactional
			$promotionalTypes.addClass('active');
			$transactionalTypes.removeClass('active');
			
			// Enable promotional select, disable transactional
			$promotionalTypes.find('.message-type-select').prop('disabled', false);
			$transactionalTypes.find('.message-type-select').prop('disabled', true);
			
			// Update hidden input with selected promotional message type
			var promotionalValue = $promotionalTypes.find('.message-type-select').val();
			$hiddenInput.val(promotionalValue);
		} else if (selectedType === 'transactional') {
			// Show transactional, hide promotional
			$transactionalTypes.addClass('active');
			$promotionalTypes.removeClass('active');
			
			// Enable transactional select, disable promotional
			$transactionalTypes.find('.message-type-select').prop('disabled', false);
			$promotionalTypes.find('.message-type-select').prop('disabled', true);
			
			// Update hidden input with transactional message type (always 52620)
			var transactionalValue = $transactionalTypes.find('.message-type-select').val();
			$hiddenInput.val(transactionalValue);
		}
		
		// Trigger a change event to save
		$hiddenInput.trigger('change');
	});
	
	// Handle message type select dropdown changes
	$('.message-type-select').on('change', function() {
		// Only update if this select is in the active message type
		if (!$(this).prop('disabled')) {
			var selectedValue = $(this).val();
			$('#template_settings_message_type_id').val(selectedValue).trigger('change');
		}
	});


	} // end check for builder

});

// Custom checkbox/radio button group toggle functionality
jQuery(document).on('click', '.wiz-check-toggle-display', function(e) {
	// Prevent the default label behavior to ensure our custom logic runs smoothly
	e.preventDefault();

	var $changedElement = jQuery(this);
	toggle_wiz_check_toggle($changedElement);
			
});






