// Default to no unsaved changes
let unsavedWysiwygChanges = false;

jQuery(document).ready(function ($) {

	//Save the template title when updated
	$(document).on("change", "#idwiz_templateTitle", function () {
		var templateId = $(this).data("templateid");
		var value = $(this).val();
		$.ajax({
			type: "POST",
			url: idAjax.ajaxurl,
			data: {
				action: "idemailwiz_save_template_title",
				template_id: templateId,
				template_title: value,
				security: idAjax_template_editor.nonce,
			},
			success: function (result) {
				do_wiz_notif({message: result.data.message, duration: 3000});
			},
			error: function (xhr, status, error) {
				do_wiz_notif({message: error, duration: 3000});
			},
		});
	});

	// Only attach the onbeforeunload handler if #builder exists
	if ($('#builder').length) {
		window.onbeforeunload = function(e) {
			if (unsavedWysiwygChanges) {
				// For modern browsers, a standard message will be shown, not this custom message.
				e.returnValue = 'You have unsaved changes!';
				return e.returnValue;
			}
		};


		// Initialize TinyMCE on all visible editors on page load
		builder_init_tinymce();

		// Initialize sortable elements
		initializeRowSortables();
		initializeColumnSortables();
		initializeChunkSortables();

		// Initialize editable elements (row and columns names)
		initializeEditable('.builder-row-title-text', '.edit-row-title', 'row-id');
		initializeEditable('.builder-column-title-text', '.edit-column-title', 'column-id');

		// Initialize chunk tabs (content and settings)
		initializeChunkTabs();

		// Initialize color pickers
		initColorPickers();

		// Initialize background type selection buttons
		initializeBackgroundTypeSelection();

		// Save a session on page load so we have one to work with
		saveTemplateToSession();

		let previewMode = "desktop";

		$("#showMobile").on('click', function () {
			previewMode = "mobile";
			$("#previewFrame").addClass("mobile-preview");
			$(this).addClass("active");
			$("#showDesktop").removeClass("active");
			idwiz_updatepreview();
		});

		$("#showDesktop").on('click', function () {
			previewMode = "desktop";
			$("#previewFrame").removeClass("mobile-preview");
			$(this).addClass("active");
			$("#showMobile").removeClass("active");
			idwiz_updatepreview();
		});

		$(document).find("#fullModeMobile").on('click', function () {
			previewMode = "mobile";
			$("#emailTemplatePreviewIframe").addClass("mobile-preview");
			$(this).addClass("active");
			$("#fullModeDesktop").removeClass("active");
			idwiz_updatepreview();
		});

		$(document).find("#fullModeDesktop").on('click', function () {
			previewMode = "desktop";
			$("#emailTemplatePreviewIframe").removeClass("mobile-preview");
			$(this).addClass("active");
			$("#fullModeMobile").removeClass("active");
			idwiz_updatepreview();
		});


		if ($('#preview_width_dragger').length) {
			var initialX;
			var initialWidth;

			function updateWidthDisplay() {
				$('#preview_width_dragger').text($('#previewFrame').width() + 'px');
			}

			updateWidthDisplay(); // Initial display of width

			// Listen for window resize and update the width display
			$(window).resize(function() {
				updateWidthDisplay();
			});

			$('#preview_width_dragger').on('mousedown', function(e) {
				e.preventDefault(); // Prevent default drag behavior
				initialX = e.pageX;
				initialWidth = $('#previewFrame').width();

				$(document).on('mousemove', function(e) {
					var newWidth = initialWidth + (e.pageX - initialX);
					$('#previewFrame').width(newWidth);
					updateWidthDisplay();
				});

				$(document).on('mouseup', function() {
					$(document).off('mousemove');
					$(document).off('mouseup');
				});
			});
		}


		$('#refreshPreview').on('click', function(e) {
			e.preventDefault();
			idwiz_updatepreview();
		});

		$('.light-mode-interface').on('click', function (e) {
			e.preventDefault();
			$(this).addClass('active');
			$('.dark-mode-interface').removeClass('active');
			$('.interface-transparency-toggle').removeClass('active');
			$('#previewFrame').addClass('light-mode');
			$('#previewFrame').removeClass('dark-mode');
			$('#previewFrame').removeClass('transparent-mode');
		});
		$('.dark-mode-interface').on('click', function (e) {
			e.preventDefault();
			$(this).addClass('active');
			$('.light-mode-interface').removeClass('active');
			$('.interface-transparency-toggle').removeClass('active');
			$('#previewFrame').addClass('dark-mode');
			$('#previewFrame').removeClass('light-mode');
			$('#previewFrame').removeClass('transparent-mode');
		});
		$('.interface-transparency-toggle').on('click', function (e) {
			e.preventDefault();
			$(this).toggleClass('active');
			$('.light-mode-interface').removeClass('active');
			$('.dark-mode-interface').removeClass('active');
			$('#previewFrame').addClass('transparent-mode');
			$('#previewFrame').removeClass('light-mode');
			$('#previewFrame').removeClass('dark-mode');
		});


		
		
	}

	


	// Click events
	
	// Main UI tabs 
	$(document).on('click', '.builder-tab', function() {
		var clickedTab = $(this);
		var tabId = clickedTab.data('tab');

		// Remove active class from all tabs
		$('#main-builder-tabs .builder-tab').removeClass('--active');

		// Add active class to clicked tab
		clickedTab.addClass('--active');

		// Hide all content areas
		$('.builder-tab-content').hide();

		// Show the content area corresponding to the clicked tab
		$('#' + tabId).show();
	});

	// Save Template Button
	$('#save-template').on('click', function(e) {
		e.preventDefault();
		saveTemplateToSession();
		saveTemplateData('publish');
		idwiz_updatepreview();
	});

	// Save draft button
	$('#save-draft').on('click', function(e) {
		e.preventDefault();
		saveTemplateToSession();
		saveTemplateData('draft');
		idwiz_updatepreview();
	});

	// Click collapse/expand for row, column, and chunk headers
	$(document).on('click', '.builder-row-header, .builder-column-header, .builder-chunk-header, .collapsed-message.show', function(e) {
		toggleBuilderElementVis($(this), e);
	});

	// Gradient pickers
	$(document).on('click', '.gradientLabel', function() {
		initGradientPicker(this);
	});

	// Duplicate row 
	// $(document).on('click', '.duplicate-row', function() {
	// 	var $row = $(this).closest('.builder-row');
	// 	duplicateElement($row, 'row-id');
	// 	saveTemplateToSession();
	// 	idwiz_updatepreview();
	// 	unsavedWysiwygChanges = true;
	// });

	// Duplicate chunk 
	// $(document).on('click', '.duplicate-chunk', function() {
	// 	var $chunk = $(this).closest('.builder-chunk');
	// 	duplicateElement($chunk, 'chunk-id');
	// 	saveTemplateToSession();
	// 	idwiz_updatepreview();
	// 	unsavedWysiwygChanges = true;
	// });

	// Remove row
	$(document).on('click', '.remove-row', function() {
		if ($(this).closest('.builder-row').siblings('.builder-row').length < 1) {
			$('.blank-template-message').show();
		}
		$(this).closest('.builder-row').remove();
		saveTemplateToSession();
		idwiz_updatepreview();
		unsavedWysiwygChanges = true;
	});

	// Remove chunk
	$(document).on('click', '.remove-chunk', function() {
		$(this).closest('.builder-chunk').remove();
		saveTemplateToSession();
		idwiz_updatepreview();
		unsavedWysiwygChanges = true;
	});

	$(document).on('click', '.show-on-desktop, .show-on-mobile', function() {
		var $this = $(this);
		var isDesktopToggle = $this.hasClass('show-on-desktop');
		var toggleType = isDesktopToggle ? 'show-on-desktop' : 'show-on-mobile';
		var currentState = $this.attr('data-' + toggleType) !== 'false';
		var newState = !currentState;

		// Update the clicked toggle's state and visual indication
		$this.attr('data-' + toggleType, newState.toString()).toggleClass('disabled', !newState);

		var $builderChunk = $this.closest('.builder-chunk');
		var $builderRow = $this.closest('.builder-row');

		// Click is on a row setting
		if ($builderRow.length && !$builderChunk.length) {
			// Update all child chunks to match both row settings
			// Ensure we're getting the most current state directly from the row toggles
			var rowDesktopState = $builderRow.find('.show-on-desktop').attr('data-show-on-desktop') === 'true';
			var rowMobileState = $builderRow.find('.show-on-mobile').attr('data-show-on-mobile') === 'true';

			$builderRow.find('.builder-chunk .show-on-desktop')
				.attr('data-show-on-desktop', rowDesktopState.toString())
				.toggleClass('disabled', !rowDesktopState);
			$builderRow.find('.builder-chunk .show-on-mobile')
				.attr('data-show-on-mobile', rowMobileState.toString())
				.toggleClass('disabled', !rowMobileState);
		}
		// Click is within a chunk
		else if ($builderChunk.length) {
			// Reset row's visibility toggles to default (true)
			$builderRow.find('.builder-row-actions .show-on-desktop, .builder-row-actions .show-on-mobile')
				.attr('data-show-on-desktop', 'true').removeClass('disabled')
				.attr('data-show-on-mobile', 'true').removeClass('disabled');
		}

		saveTemplateToSession();
		idwiz_updatepreview();
		unsavedWysiwygChanges = true;
	});



	// Magic wrap toggle
	$(document).on('click', '.magic-wrap-toggle', function() {
		$(this).toggleClass('active');
		if ($(this).hasClass('active')) {
			$(this).attr('data-magic-wrap', 'on');
		} else {
			$(this).attr('data-magic-wrap', 'off');
		}
		unsavedWysiwygChanges = true;
		saveTemplateToSession();
		idwiz_updatepreview();
	});
	
	// Rotate columns switcher
	$(document).on('click', '.rotate-columns', function() {
		$(this).toggleClass('fa-rotate-90');
		var $row = $(this).closest('.builder-row');
		var $column = $(this).closest('.builder-column');

		// Rotate the columns
		$row.find('.builder-row-columns').css('flex-direction', $(this).hasClass('fa-rotate-90')? 'column' : 'row');

		// Update column data attribute
		$row.attr('data-column-stacked', $(this).hasClass('fa-rotate-90')? 'stacked' : 'false');

		expandBuilderElementVis($row, '.builder-row-content');
	});






	

	
	// Initiate gradX gradient pickers with Swal2
	function initGradientPicker(clickedElement) {
		var $gradientLabel = $(clickedElement);
		// Generate a unique ID for the gradient picker to avoid conflicts
		var gradientPickerId = 'gradientPicker-' + Math.random().toString(36).substr(2, 9);
		var $gradientInput = $gradientLabel.prev('input.gradientValue'); // Make sure this matches your actual DOM structure

		var initialGradientValue = $gradientInput.val();

		Swal.fire({
			title: 'Build Gradient',
			html: '<div id="' + gradientPickerId + '" style="height: 200px; margin-bottom: 200px;"></div>',
			showCancelButton: true,
			didOpen: () => {
				var existingGradientValue = $gradientInput.val();
				console.log(existingGradientValue);
				var gradientConfig = existingGradientValue ? JSON.parse(existingGradientValue) : {sliders: [], style: '', type: 'linear', direction: 'left', direction2: 'center'};

				// Initialize the gradient picker using the dynamically generated ID
				gradX('#' + gradientPickerId, {
					sliders: gradientConfig.sliders.map(slider => ({
						color: slider.color, 
						position: slider.position
					})),
					change: function(sliders, styles) {
						var style = styles[0];

						var saveFormat = {
							sliders: sliders.map(slider => {
								return { color: slider[0], position: slider[1] }; 
							}),
							style: style,
							type: $('#gradx_gradient_type').val(), 
							direction: $('#gradx_gradient_subtype').val(),
							direction2: $('#gradx_gradient_subtype2').val(),
						};

						$gradientInput.val(JSON.stringify(saveFormat));
						$gradientLabel.css('background-image', style);
					}
				});

				$('#gradx_gradient_type').val(gradientConfig.type).trigger('change');
				$('#gradx_gradient_subtype').val(gradientConfig.direction).trigger('change');
				if (gradientConfig.type !== 'linear') {
					$('#gradx_gradient_subtype2').val(gradientConfig.direction2).trigger('change');
				}
			},
			preConfirm: () => $gradientInput.val()
		}).then((result) => {
			if (result.dismiss === Swal.DismissReason.cancel) {
				$gradientInput.val(initialGradientValue);
				updateGradientPreview($gradientLabel, $gradientInput);
			}
			saveTemplateToSession();
			idwiz_updatepreview();
		});
	}
	
	// Utility function to update the gradient preview
	function updateGradientPreview($label, $input) {
		var gradientValue = $input.val();
	
		if (gradientValue) {
			var gradientConfig = JSON.parse(gradientValue);
			$label.css('background-image', gradientConfig.style);
		}
	}
	
	
	//Apply saved gradient to any gradient picker labels that have one
	$('.chunk-gradient-settings .gradientLabel').each(function() {
		var gradientData = $(this).attr('data-gradientstyles');
        
		if (gradientData) {
			try {
				var gradientObj = JSON.parse(gradientData);
				// Apply the gradient style directly
				$(this).css('background', gradientObj.style);
			} catch (e) {
				console.error("Error parsing gradient data: ", e);
			}
		}
	});


	

	//Init TinyMCE on each .wiz-wysiwyg element
	function builder_init_tinymce($optionalElement) {
		// If an optional jQuery element is provided, find TinyMCE instances within it; otherwise, use the global selector
		var selector = $optionalElement ? '#' + $optionalElement.attr('id') + ' .wiz-wysiwyg' : '.wiz-wysiwyg';

		tinymce.PluginManager.add('merge_tags_button', function(editor, url) {
			const menuItems = 	idwizMergeMenuItemList();
  
			function generateMenuItems(items) {
			  return items.map(function(item) {
				return {
				  type: 'menuitem',
				  text: item.text,
				  onAction: function() {
					editor.insertContent(item.value);
				  }
				};
			  });
			}
  
			editor.ui.registry.addMenuButton('merge_tags_button', {
			  text: 'Merge Tags',
			  tooltip: 'Insert personalization',
			  fetch: function(callback) {
				const items = menuItems.map(item => ({
				  type: 'nestedmenuitem',
				  text: item.text,
				  getSubmenuItems: () => generateMenuItems(item.items)
				}));
				callback(items);
			  }
			});
		});

		tinymce.PluginManager.add('theme_switcher', function(editor) {
			editor.ui.registry.addToggleButton('theme_switcher', {
				icon: 'contrast', 
				onpostrender: function() {
					// Add class to button
					editor.ui.registry.get('theme_switcher').element.classList.add('theme-switcher');
				},
				onAction: function(api) {
					const isActive = api.isActive();
					api.setActive(!isActive); // Toggle the button's active state
            
					// Target the body of the editor's iframe document to change its background
					const bodyStyle = editor.getBody().style; // This targets the content body directly
					if (!isActive) { // If the button was not active, activate dark mode
						bodyStyle.backgroundColor = '#222222';
						// Store the state for later use, if needed
					} else { // If the button was active, revert to light mode
						bodyStyle.backgroundColor = '#FFFFFF';
					}
					saveTemplateToSession();
				},
				onSetup: function(api) {
					// Retrieve the original textarea element that TinyMCE is based on
					const originalTextarea = editor.getElement();
					// Read the data-editor-mode attribute to determine the preferred mode
					const editorMode = originalTextarea.getAttribute('data-editor-mode');
    
					// Determine if dark mode should be active based on the attribute's value
					const isDarkMode = editorMode === 'dark';

					// Set the toggle button's active state based on isDarkMode
					api.setActive(isDarkMode);

					// Apply the corresponding styles to the editor's body based on the mode
					const bodyStyle = editor.getBody().style;
					if (isDarkMode) {
						// Apply Dark Mode styles
						bodyStyle.backgroundColor = '#222222';
					} else {
						// Apply Light Mode styles (or simply don't change anything if these are the defaults)
						bodyStyle.backgroundColor = '#FFFFFF';
					}

					
				}

			});
		});


		tinymce.init({
			selector: selector,
			//icons: 'small',
			height: 250,
			toolbar: [
				{ name: 'code', items: [ 'code'] },
				{ name: 'merge_tags_button', items: [ 'merge_tags_button'] },
				{ name: 'theme_switcher', items: [ 'theme_switcher'] },
				{ name: 'styles', items: [ 'styles' ] }, 
				{ name: 'formatting', items: [ 'fontsize', 'lineheight', 'forecolor', 'bold', 'italic', 'uppercase', 'removeformat'] },
				{ name: 'alignment', items: [ 'alignleft', 'aligncenter', 'alignright' ] },
				{ name: 'lists', items: [ 'bullist', 'numlist' ] },
				{ name: 'link', items: [ 'link'] },
				
			],
			toolbar_mode: 'scrolling',
			block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5; Heading 6=h6;',
			font_size_formats: '.8em 1em 1.1em 1.2em 1.3em 1.4em 1.5em 1.6em 1.7em 1.8em 1.9em 2em 2.5em',
			line_height_formats: '.8em 1em 1.1em 1.2em 1.3em 1.4em 1.5em 1.6em 1.7em 1.8em 1.9em 2em 2.5em',
			elementpath: false,
			menubar: false,
			plugins: 'link code lists merge_tags_button theme_switcher',
			setup: function(editor) {
				editor.ui.registry.addButton('uppercase', {
					text: 'aA',
					tooltip: 'Uppercase Style',
					onAction: function() {
						var content = editor.selection.getContent({ 'format': 'html' });
						editor.selection.setContent('<span style="text-transform: uppercase;">' + content + '</span>');
					}
				});
				
				editor.on('activate', function() {
					var editorContainer = $(editor.getContainer());
					var $baseColorInput = editorContainer.closest('.builder-chunk').find('input[name="text_base_color"]');
					
					var baseColor = $baseColorInput.attr('data-color-value');

					// Set all elements inside the editor body to the base color
					editor.getBody().style.color = baseColor;
				});

				editor.on('init', function() {
					$(document).on('change.spectrum', 'input[name="text_base_color"]', function(e, tinycolor) {

						var baseColor = tinycolor.toHexString();

						// Check if the input is within the same .builder-chunk as this editor
						var editorContainer = $(editor.getContainer());
						var $parent = $(this).closest('.builder-chunk');
						if ($parent.has(editorContainer).length) {
							// Apply the base color to the editor's content
							editor.getBody().style.color = baseColor;	
						}

						
					});
					// Handle changes to link styles using a similar pattern
					$(document).on('change.spectrum', '#template_style_link_color, #template_link_style_hover_color', function() {
						// Apply the link and hover colors along with other styles
						updateLinkStyles();
					});

					// Listen for changes to checkbox inputs for underline, italic, and bold styles
					$(document).on('change', '#template_styles_underline_links, #template_styles_italic_links, #template_styles_bold_links', function() {
						// Apply the link styles again as these inputs change
						updateLinkStyles();
					});

					// Function to update link styles in the editor
					function updateLinkStyles() {
						var linkColor = $('#template_style_link_color').spectrum('get').toHexString();
						var linkHoverColor = $('#template_link_style_hover_color').spectrum('get').toHexString();
						var linkUnderline = $('#template_styles_underline_links').is(':checked') ? 'underline' : 'none';
						var linkItalic = $('#template_styles_italic_links').is(':checked') ? 'italic' : 'normal';
						var linkBold = $('#template_styles_bold_links').is(':checked') ? 'bold' : 'normal';

						// Construct CSS rules
						var style = `.mce-content-body a { color: ${linkColor}; text-decoration: ${linkUnderline}; font-style: ${linkItalic}; font-weight: ${linkBold}; }`;
						style += `.mce-content-body a:hover { color: ${linkHoverColor}; }`;

						// Apply styles by injecting a <style> tag into the editor's content
						var doc = editor.getDoc();
						var head = doc.head || doc.getElementsByTagName('head')[0];
						var customStyleTag = doc.getElementById('customLinkStyles');

						// If the style tag doesn't exist, create it and append it to <head>
						if (!customStyleTag) {
							customStyleTag = doc.createElement('style');
							customStyleTag.id = 'customLinkStyles';
							head.appendChild(customStyleTag);
						}

						// Set the style tag's contents
						customStyleTag.innerHTML = style;
					}

					// Initial call to apply styles upon editor initialization
					updateLinkStyles();
				});

				editor.on('input', function() {
					idwiz_updatepreview();
					updateBuilderChunkTitle_debounced(editor);
					unsavedWysiwygChanges = true;
				});

				// Before an action is added to the undo stack
				editor.on('AddUndo', function(e) {
					//console.log('AddUndo event fired.', e);
					idwiz_updatepreview();
					updateBuilderChunkTitle_debounced(editor);
					unsavedWysiwygChanges = true;
				});

				// When an undo action is performed
				editor.on('Undo', function(e) {
					//console.log('Undo event fired.', e);
					idwiz_updatepreview();
					updateBuilderChunkTitle_debounced(editor);
				});

				// If you also need to hook into the redo action
				editor.on('Redo', function(e) {
					//console.log('Redo event fired.', e);
					idwiz_updatepreview();
					updateBuilderChunkTitle_debounced(editor);
				});
			},
			style_formats: [ // Define custom styles
				{ title: 'P', format: 'p' },
				{ title: 'H1', format: 'h1' },
				{ title: 'H2', format: 'h2' },
				{ title: 'H3', format: 'h3' },
				{ title: 'H4', format: 'h4' },
				{ title: 'H5', format: 'h5' },
				{ title: 'H6', format: 'h6' },
				
			],
			fontsize_formats: "8pt 10pt 12pt 14pt 18pt 24pt 36pt", // Correct option for font size selection
		});

	}

	function updateBuilderChunkTitle(editor) {
		// Get the content from the editor, stripping HTML tags
		let textContent = editor.getContent({ format: 'text' }).trim();

		// Trim the text content to the first 32 characters
		textContent = textContent.substring(0, 32);

		// Find the closest .builder-chunk-title element and update its text
		const editorElement = editor.getElement();
		const builderChunkTitle = $(editorElement).closest('.builder-chunk').find('.builder-chunk-title');
		if (builderChunkTitle.length) {
			builderChunkTitle.text(textContent+'...');
		}
	}

	const updateBuilderChunkTitle_debounced = wizDebounce(function(editor) {
		updateBuilderChunkTitle(editor);
	}, 1000);

	function wizDebounce(func, wait) {
		let timeout;
		return function() {
			const context = this, args = arguments;
			clearTimeout(timeout);
			timeout = setTimeout(function() {
				func.apply(context, args);
			}, wait);
		};
	}


	 // Attach hover event handlers
	 $(document).on('mouseenter','.image-chunk-preview-wrapper img', function(e) {
		// Create the preview element if it doesn't exist
		if ($('#chunk-image-preview').length === 0) {
			$('body').append('<div id="chunk-image-preview" style="position: absolute; display: none;"><img src="" style="max-width: 200px; max-height: 200px;"></div>');
		}

		// Set the source of the preview image to the hovered image's src
		$('#chunk-image-preview img').attr('src', $(this).attr('src'));

		// Position and show the preview
		$('#chunk-image-preview').css({
			'display': 'block',
			'left': e.pageX + 10, // Offset from cursor
			'top': e.pageY + 10
		});
	}).on('mousemove','.image-chunk-preview-wrapper img', function(e) {
		// Make the preview follow the mouse cursor
		$('#chunk-image-preview').css({
			'left': e.pageX + 10, // Offset from cursor
			'top': e.pageY + 10
		});
	}).on('mouseleave','.image-chunk-preview-wrapper img', function() {
		// Hide the preview when mouse leaves
		$('#chunk-image-preview').hide();
	});

	
	// Destroy and re-initialize TinyMCE on each .wiz-wysiwyg element with option element selection
	function reinitTinyMCE($optionalElement = null) {
		console.log('reinitTinyMCE on ' + ($optionalElement ? $optionalElement.attr('class') : 'global'));

		// Determine the correct selector for the operation
		var selector = $optionalElement ? $optionalElement.find('.wiz-wysiwyg') : '.wiz-wysiwyg';

		$(selector).each(function() {
			var editorId = $(this).attr('id');
			var editor = tinymce.get(editorId);
			if (editor) {
				// Save the content from the TinyMCE editor back to the textarea
				editor.save();

				// Properly remove the TinyMCE instance to avoid any residual states
				editor.remove();
			}

			// It's crucial to clear any TinyMCE-related data attributes that might interfere with reinitialization
			$(this).removeAttr('data-mce-id').removeAttr('data-id');
		});

		// After ensuring all editors within the context are properly reset, reinitialize TinyMCE
		builder_init_tinymce($optionalElement);


	}

	function saveAllTinyMces($optionalElement = null) {
		var selector = $optionalElement ? $optionalElement.find('.wiz-wysiwyg') : '.wiz-wysiwyg';
		$(selector).each(function() {
			var editorId = $(this).attr('id');
			var editor = tinymce.get(editorId);
			if (editor) {
				// Save the content from the TinyMCE editor back to the textarea
				editor.save();
			}
		});
	}
	

	// Initialize sortable for rows, columns, and chunks
	function initializeRowSortables(containerId = null) {
		var containerSelector = containerId ? '#' + containerId : '.builder-rows-wrapper';
		initializeSortable(containerSelector, '.builder-row', '.builder-row-header', 'row-placeholder', {});
	}

	function initializeColumnSortables(containerId = null) {
		var containerSelector = containerId ? '#' + containerId + ' .builder-row-columns' : '.builder-row-columns';
		initializeSortable(containerSelector, '.builder-column', '.builder-column-header', 'column-placeholder', { 
			tolerance: 'pointer',
		});
	}

	function initializeChunkSortables(containerId = null) {
		var containerSelector = containerId ? '#' + containerId + ' .builder-column-chunks-body' : '.builder-column-chunks-body';
		initializeSortable(containerSelector, '.builder-chunk', '.builder-chunk-header', 'chunk-placeholder', {
			connectWith: '.builder-column-chunks-body',
			tolerance: 'pointer',
			dropOnEmpty: true,
			receive: function(event, ui) {
				reindexDataAttributes('chunk-id');
				
				

				// Reinitialize TinyMCE for editors within the moved chunk
				var $movedChunk = ui.item; // The jQuery object of the moved chunk
				reinitTinyMCE($movedChunk);
				

			},
			over: function(event, ui) {
				if ($(this).children('.builder-chunk').length === 0) {
					// When dragging to an empty column
					$(this).addClass('highlight-empty');
				}
			},
			out: function(event, ui) {
				// When leaving the drag over an empty column
				$(this).removeClass('highlight-empty');
			},
		});
	}

	function initializeSortableInternal($container, itemsSelector, handleSelector, placeholderClass, additionalOptions) {
		$container.sortable($.extend({
			items: itemsSelector,
			handle: handleSelector,
			placeholder: placeholderClass,
			start: function(event, ui) {

				var headerHeight = ui.item.find(handleSelector).outerHeight();
				$('.' + placeholderClass, $container).css({
					'min-height': headerHeight,
					'width': ui.item.outerWidth() + 'px'
				});

				// Update and destroy TinyMCE instances within the dragged item
				ui.item.find('.wiz-wysiwyg').each(function() {
					var editor = tinymce.get(this.id);
					if (editor) {
						$(this).val(editor.getContent());
						editor.remove();
					}
				});
			},
			stop: function(event, ui) {
				// Delay reinitialization to ensure the DOM has updated
				setTimeout(function() {
					// Reinitialize TinyMCE 
					reinitTinyMCE(ui.item);
				}, 100);
				
			},
			update: function(event, ui) {
				setTimeout(function() {
				// Determine which attribute to reindex
				var attributeToReindex = itemsSelector.includes('row') ? 'row-id' :
										 itemsSelector.includes('column') ? 'column-id' :
										 'chunk-id';
				reindexDataAttributes(attributeToReindex, $container);
				
				idwiz_updatepreview();
				unsavedWysiwygChanges = true;
				console.log('unsaved changes: ' + unsavedWysiwygChanges);
				},500);
			}
		}, additionalOptions));
	}

	function initializeSortable(containerSelector, itemsSelector, handleSelector, placeholderClass, additionalOptions) {
		var $containers = $(containerSelector);

		$containers.each(function() {
			var $container = $(this);

			// Directly initialize sortable for each container found
			initializeSortableInternal($container, itemsSelector, handleSelector, placeholderClass, additionalOptions);
		});
	}

	// Destroy and re-init sortables on columns
	function updateColumnSortables(row_id) {
		// Target the specific row by row_id
		var $rowColumns = $(document).find('.builder-row[data-row-id="' + row_id + '"]').find('.builder-row-columns');

		// Check if Sortable is initialized on this specific instance
		if ($rowColumns.hasClass('ui-sortable')) {
			// If initialized, destroy existing Sortable
			$rowColumns.sortable('destroy');
		}

		// Re-initialize Sortable on the specific row's columns
		initializeColumnSortables();
	}


	
	// Toggle visibility function for rows, cols, and chunks
	function toggleBuilderElementVis($header, e) {
		if ($header.hasClass('collapsed-message')) {
			$header = $header.closest('.builder-column').find('.builder-column-header');
		}
		// Prevent toggle if clicked within an excluded area
		if ($(e.target).closest('.exclude-from-toggle').length || $(e.target).closest('input').length) {
			return;
		}

		// Determine the context and corresponding classes
		let $element, toggleClass;
		if ($header.hasClass('builder-row-header')) {
			$element = $header.closest('.builder-row');
			toggleClass = '.builder-row-content';
		} else if ($header.hasClass('builder-column-header')) {
			$element = $header.closest('.builder-column');
			toggleClass = '.builder-column-chunks';
		} else if ($header.hasClass('builder-chunk-header')) {
			$element = $header.closest('.builder-chunk');
			toggleClass = '.builder-chunk-body';
		} else {
			return; // Not a valid toggle target
		}

		// Toggle visibility
		if ($element.hasClass('--collapsed')) {
			expandBuilderElementVis($element, toggleClass);
		} else {
			collapseBuilderElementVis($element, toggleClass);
		}
	}

	



	// Utility function to expand an element
	function expandBuilderElementVis($element, toggledClass) {
		$element.children(toggledClass).slideDown(function() {
			// Reinitialize TinyMCE for each chunk within the expanded element
			$element.find('.builder-chunk').each(function() {
				reinitTinyMCE($(this));
			});
		});

		$element.children('.collapsed-message').hide().addClass('hide').removeClass('show');
		$element.addClass('--expanded').removeClass('--collapsed');

		
	}


	// Utility function to collapse an element
	function collapseBuilderElementVis($element, toggledClass) {
		$element.children(toggledClass).slideUp(); // Target only the direct child
		setTimeout(function() {
			$element.children('.collapsed-message').fadeIn().addClass('show').removeClass('hide');
		},250);


		// Delay the class change a bit to avoid the first application killing the slideup
		setTimeout(function() {
			$element.addClass('--collapsed').removeClass('--expanded');
		}, 500);
	}

	// Utility function to initialize editable elements
	
	function initializeEditable(editableClass, triggerClass, dataAttributeName, $context) {
		// Default to the whole document if no context is provided
		$context = $context || $(document);

		// Find editable elements within the specified context and initialize them
		$context.find(editableClass).each(function() {
			var $editable = $(this);
			var id = $editable.closest('[data-' + dataAttributeName + ']').data(dataAttributeName);
			var $trigger = $context.find(triggerClass + '[data-' + dataAttributeName + '="' + id + '"]');

			// Initialize editable functionality here
			$editable.editable({
				trigger: $trigger,
				action: 'click',
				onSubmit: function(e) {
					console.log('Saved text for ' + dataAttributeName + ' ' + id + ':', e.value);
				}
			});
		});

		// Since we're initializing within a context, we attach the click handler to the context rather than document
		// This ensures the handler is attached to dynamically added elements within this context
		$context.on('click', triggerClass, function() {
			var id = $(this).data(dataAttributeName);
			// Find the corresponding editable element within the context
			var $editableText = $context.find(editableClass + '[data-' + dataAttributeName + '="' + id + '"]');

			// Trigger the editable manually
			$editableText.trigger('edit');
		});
	}

	// Attach click event to the label that visually represents the checkbox
	$(document).on('click', '.checkbox-toggle-replace', function(e) {
		// Prevent the default label behavior to ensure our custom logic runs smoothly
		e.preventDefault();

		var $checkbox = $(this).prev('.wiz-check-toggle');
		// Toggle the checkbox's checked property directly
		$checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change'); // Trigger change to ensure any other handlers are notified

		// Now, update the visual state based on the checkbox's state
		
		var $icon = $(this).find('i');
		if ($checkbox.prop('checked')) {
			$icon.removeClass('fa-regular').addClass('fa-solid');
			$(this).addClass('active');
		} else {
			$icon.removeClass('fa-solid').addClass('fa-regular');
			$(this).removeClass('active');
		}
	});


	// Generate a new unique string for the cloned element
	function generateUniqueId(prefix = 'wizid', moreEntropy = false) {
		const timestamp = new Date().getTime() / 1000;
		const mainPart = parseInt(timestamp, 10).toString(16); // Convert timestamp to hexadecimal
		const randomPart = moreEntropy 
			? '-' + (Math.random() * 100000000 + 10000000).toString(16) 
			: Math.random().toString(16).slice(2, 10);

		return prefix + mainPart + randomPart;
	}


	// Update all instances of an old unique ID string within an element to a new unique ID
	function updateClonedWrapperIds($element, oldIdWithPrefix, newUniqueString, isRow = false) {
		const prefix = isRow ? 'wiz-row-' : 'wiz-chunk-'; // Ensure prefix is non-numeric
		const newIdWithPrefix = prefix + newUniqueString;

		$element.add($element.find('*')).each(function() {
			var $this = $(this);

			// Update the ID attribute if present
			if (this.id && this.id.includes(oldIdWithPrefix)) {
				var newId = this.id.replace(oldIdWithPrefix, newIdWithPrefix);
				$this.attr('id', newId);
			}

			// Update other attributes that may contain IDs or references to IDs
			$.each(this.attributes, function() {
				if (this.value.includes(oldIdWithPrefix)) {
					var newValue = this.value.replace(oldIdWithPrefix, newIdWithPrefix);
					$this.attr(this.name, newValue);
				}
			});
			
		});
	}




	// Reinitialize sortables for an element that has been cloned
	function reinitializeSortablesForCloned($originalElement, $clonedElement) {
		// Destroy sortable on the original element's container if applicable
		let $originalSortableContainer = $originalElement.closest('.ui-sortable');
		if ($originalSortableContainer.length && $.contains(document, $originalSortableContainer[0])) {
			$originalSortableContainer.sortable('destroy');
		}

		// Remove any inherited sortable data from the cloned element
		$clonedElement.removeData("sortable").removeData("uiSortable").off(".sortable");
		// Also clear for child elements that might have been part of a nested sortable
		$clonedElement.find('.ui-sortable').removeData("sortable").removeData("uiSortable").off(".sortable");

		// After cleaning, reinitialize sortable for the containers of both original and cloned elements
		// by using the classes to find the containers as the IDs are for the elements themselves.

		// For rows
		if ($clonedElement.hasClass('builder-row')) {
			let $rowContainers = $('.builder-rows-wrapper'); // Assuming this is a common class for row containers
			$rowContainers.each(function() {
				
				initializeRowSortables(); // Initialize for all row containers without needing an ID
				initializeColumnSortables();
				initializeChunkSortables();
			});
		}

		// For columns
		if ($clonedElement.hasClass('builder-column') || $originalElement.hasClass('builder-column')) {
			// Find the row container of the cloned column to reinitialize column sortables within it
			let $clonedRowContainer = $clonedElement.closest('.builder-rows-wrapper');
			if ($clonedRowContainer.length) {
				
				initializeColumnSortables(); 
				initializeChunkSortables();
			}
		}

		// For chunks
		if ($clonedElement.hasClass('builder-chunk') || $originalElement.hasClass('builder-chunk')) {
			// Find the column container of the cloned chunk to reinitialize chunk sortables within it
			let $clonedColumnContainer = $clonedElement.closest('.builder-row-columns');
			if ($clonedColumnContainer.length) {
				
				initializeChunkSortables(); // Reinitialize all chunks within the column container
			}
		}
	}



	// Reindex data attributes for a given attribute name when elements are moved around added or deleted
	function reindexDataAttributes(attributeName) {
		// Find all elements with the specified data attribute
		$('[data-' + attributeName + ']').each(function() {
			var $parent = $(this).parent();
			var $siblings = $parent.children('[data-' + attributeName + ']');

			// Reindex each sibling (including the current element)
			$siblings.each(function(index) {
				var displayIndex = index + 1; // For display-friendly numbering
				$(this).attr('data-' + attributeName, index);
            
				// Update display-friendly elements if they exist
				var $displayElement = $(this).find('[data-' + attributeName + '-display]');
				if ($displayElement.length) {
					$displayElement.attr('data-' + attributeName + '-display', displayIndex);
					$displayElement.text(displayIndex); // Update inner text
				}
			});
		});
	}



	
	





	



	// Initialize background type selection in the background settings modual
	function initializeBackgroundTypeSelection($context) {
		$context = $context || $(document);

		// Delegate change event for current and future 'input[name="background_type"]' within '.chunk-background-settings'
		$context.off('change', '.chunk-background-settings input.background-type-select').on('change', '.chunk-background-settings input.background-type-select', function() {
			var container = $(this).closest('.chunk-background-settings');
			var selectedType = $(this).val();

			// Hide all sections initially within the container
			container.find('.chunk-settings-section').hide();
			container.find('.chunk-background-type').show(); // Always show the background type selection within the container

			// Based on the selected type, show the appropriate settings
			if (selectedType !== 'none') {
				container.find('.chunk-background-color-settings').show();
			}
			if (selectedType === 'image') {
				container.find('.chunk-background-image-settings').show();
				container.find('.chunk-background-color-settings > label').text('Fallback Background Color');
			} else if (selectedType === 'gradient') {
				container.find('.chunk-background-image-settings, .chunk-background-gradient-settings').show();
				container.find('.chunk-background-color-settings > label').text('Fallback Background Color');
				container.find('.chunk-background-image-settings > label').text('Fallback Background Image');
			} else {
				// Reset to default texts or hide elements as needed
				container.find('.chunk-background-image-settings > label').text('Background Image URL');
				container.find('.chunk-background-color-settings > label').text('Background Color');
			}
		});

		// Initialize or reinitialize settings based on the currently selected type
		$context.find('.chunk-background-settings input.background-type-select:checked').each(function() {
			$(this).change(); // Trigger the change event to ensure the UI is in the correct state
		});
	}

	

	//Initialize chunk tabs 
	function initializeChunkTabs($context) {
		$context = $context || $(document);

		$context.on('click', '.chunk-tab', function() {
			var $thisTab = $(this);
			var targetSelector = $thisTab.data('target');
			var $targetContent = $(targetSelector);

			// Hide all tab contents in the current chunk
			$thisTab.closest('.builder-chunk').find('.tab-content').hide();

			// Show the target content
			$targetContent.show();

			// Update active state for tabs
			$thisTab.siblings().removeClass('active');
			$thisTab.addClass('active');
		});
	}



	
	// Initiate color pickers based on optional selected element
	function initColorPickers($optionalElement = null) {
		var $element = $optionalElement ? $optionalElement : $('#builder');
		let colorPickers = $element.find('.builder-colorpicker');

		colorPickers.each(function () {
			// Check if Spectrum is already initialized on this element
			if ($(this).siblings('.sp-replacer').length) { // Check for a Spectrum-specific property or class
				$(this).spectrum("destroy"); // If initialized, destroy it first
			}

			// Then initialize Spectrum
			$(this).spectrum({
				allowEmpty:true,
				showInitial: true,
				showInput: true,
				showPalette: true,
				palette: [
					['#000000', '#343434', '#94c52a', '#f4f4f4', '#ffffff', 'transparent']
				],
				showAlpha: true,
				preferredFormat: 'hex'
			}).change(function (color) {
				saveTemplateToSession();
				idwiz_updatepreview();
			});

			// Retrieve the existing value from the input's value attribute
			var existingColor = $(this).attr('data-color-value');

			// Set the Spectrum color picker to the existing color
			if (existingColor) {
				$(this).spectrum('set', existingColor);
			}
		});

	}


	

	// Add new blank row
	$(document).on('click','.builder-new-row', function() {
		const nonceValue = idAjax_template_editor.nonce;
		const userId = idAjax_template_editor.current_user.ID;
		const postId = idAjax_template_editor.currentPost.ID; 
		const lastRow = $('.builder-rows-wrapper .builder-row').last();

		// Prepare additional data for the row
		const additionalData = {
			post_id: postId,
			user_id: userId,
			row_above: lastRow.length ? lastRow.data('row-id') : false,
		};

		// Use the utility function to create a new row
		createNewBuilderRow(nonceValue, additionalData);
	});

	$(document).on('click','.duplicate-row', function() {
		const nonceValue = idAjax_template_editor.nonce;
		const userId = idAjax_template_editor.current_user.ID;
		const postId = idAjax_template_editor.currentPost.ID; 
		const rowToDupe = $(this).closest('.builder-row');

		// Prepare additional data for the row
		const additionalData = {
			post_id: postId,
			user_id: userId,
			row_to_dupe: rowToDupe.length ? rowToDupe.data('row-id') : false,
			session_data: JSON.stringify(getTemplateFromSession()) // Ensure this is a string
		};

		// Use the utility function to create a new row
		createNewBuilderRow(nonceValue, additionalData, $(this));
	});

	// Add new row
	function createNewBuilderRow(nonceValue, additionalData, $clicked) {
		idemailwiz_do_ajax('create_new_row', nonceValue, additionalData, 
			function(response) { // Success callback
				console.log('New row added:', response);
				do_wiz_notif({'message': response.data.message, 'duration': 3000});

				// Append the new row HTML to the builder
				if(response.data.html) {
					let $newRow = $(response.data.html).appendTo('.builder-rows-wrapper');
					$('.blank-template-message').hide();
					initializeEditable('.builder-row-title-text', '.edit-row-title', 'row-id');
					initializeEditable('.builder-column-title-text', '.edit-column-title', 'column-id');
					initColorPickers($newRow);

					let $originalRow = $clicked.closest('.builder-row');

					// Reinitialize sortables for the new row
					reinitializeSortablesForCloned($originalRow, $newRow);

					$newRow.addClass('newly-added');
					setTimeout(function() {
						$newRow.removeClass('newly-added');
					}, 3000);


					saveTemplateToSession();
					idwiz_updatepreview();
					unsavedWysiwygChanges = true;
				}
			}, 
			function(xhr, status, error) { // Error callback
				console.error('Error adding new row:', error);
			}
		);
	}



	

	// Handle click event on the column settings icon
	$(document).on('click', '.row-column-settings', function() {
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
		}
	});

	// Consolidate mouseover and mouseout events for column-select-option elements
	$(document).on('mouseenter mouseleave', '.column-select-option', function(event) {
		var hoverColumnCount = $(this).data('columns');
		$('.column-select-option').each(function() {
			var columnNum = $(this).data('columns');
			$(this).toggleClass('hovered', event.type === 'mouseenter' && columnNum <= hoverColumnCount);
		});
	});

	// Handle column selection from the pop-up
	$(document).on('click', '.column-select-option', function() {
		var selectedColumns = $(this).data('columns');
		handleColumnSelection($(this), selectedColumns);

		// Close the popup
		$('.column-selection-popup').remove();
	});

	// Hide column selection on outside click
	$(document).on('click', function(event) {
		if (!$(event.target).closest('.column-selection-popup, .row-column-settings').length) {
			$('.column-selection-popup').remove();
		}
	});

	// Prevent the click event on the column selection popup from bubbling up
	$(document).on('click', '.column-selection-popup', function(event) {
		event.stopPropagation();
	});


	function generateColumnSelectionPopup(currentColumnCount) {
		var popupHtml = '<div class="column-selection-popup">';
		for (var i = 1; i <= 3; i++) {
			var activeClass = i <= currentColumnCount ? ' active' : '';
			popupHtml += `<i class="fas fa-square column-select-option${activeClass}" data-columns="${i}"></i>`;
		}
		popupHtml += '</div>';
		return popupHtml;
	}

	function handleColumnSelection($element, selectedColumns) {
		var $row = $element.closest('.builder-row');
		var $columns = $row.find('.builder-column');
		var currentColumnCount = $columns.length;

		// Logic to update or create columns based on selection
		updateOrCreateColumns($row, selectedColumns, currentColumnCount);

		// Hide extra columns if any
		if (currentColumnCount > selectedColumns) {
			$columns.slice(selectedColumns).removeClass('active').addClass('inactive');
		}

		// Update the data-columns attribute
		$row.find('.row-column-settings').attr('data-columns', selectedColumns);
		$row.find('.builder-row-columns').attr('data-active-columns', selectedColumns);

		saveTemplateToSession();
		idwiz_updatepreview();
		unsavedWysiwygChanges = true;
	}

	function updateOrCreateColumns($row, selectedColumns, currentColumnCount) {
		for (var i = 0; i < selectedColumns; i++) {
			if (i >= currentColumnCount) {
				createNewColumn($row, i); 
			} else {
				// Column exists, ensure it is active
				$row.find('.builder-column').eq(i).removeClass('inactive').addClass('active');
			}
		}

	}

	function createNewColumn($row, columnIndex) {
		idemailwiz_do_ajax('create_new_column', idAjax_template_editor.nonce, 
			{
				row_id: $row.data('row-id'),
				column_index: columnIndex // Pass the column index
			},
			function(response) {
				if (response.data.html) {
					// Append the new column HTML to the row
					$row.find('.builder-row-columns').append(response.data.html);

					// Update sortable for new columns
					updateColumnSortables($row.data('row-id'));

					// Reinitialize TinyMCE for any new editors in the column
					reinitTinyMCE($row);

					initializeEditable('.builder-column-title-text', '.edit-column-title', 'column-id');

					initColorPickers($row);

				}
			},
			function(xhr, status, error) {
				console.error('Error creating new column:', error);
			}
		);
	}


	 // Click event for adding a chunk and handling layout option selection
	 $(document).on('click', '.add-chunk, .wiz-tiny-dropdown-options', function(event) {
		event.preventDefault();
		var $this = $(this);

		if ($this.hasClass('add-chunk')) {
			event.stopPropagation(); // Stop propagation only for add-chunk to prevent document click from immediately hiding the menu
			toggleLayoutChoices($this);
		} else if ($this.hasClass('wiz-tiny-dropdown-options')) {
			// When a layout option is chosen
			var chunkType = $this.data('layout');
			var addChunkTrigger = $this.closest('.add-chunk-wrapper').find('.add-chunk');
			add_chunk_by_type(chunkType, addChunkTrigger);
			$('.wiz-tiny-dropdown').hide(); // Hide menu after selection

			
			 
		}
	});

	// Hide the layout choices when clicking outside
	$(document).on('click', function(event) {
		if (!$(event.target).closest('.add-chunk, .wiz-tiny-dropdown').length) {
			$('.wiz-tiny-dropdown').hide();
		}
	});

	// Toggle Add Chunk layout choice menu
	function toggleLayoutChoices(clicked) {
		var addChunkWrapper = clicked.closest('.add-chunk-wrapper');
		var chunkLayoutChoices = addChunkWrapper.find('.wiz-tiny-dropdown');

		if (chunkLayoutChoices.length === 0) {
			// Create and append layout choices if they don't exist
			var layoutChoicesHtml = '<div class="wiz-tiny-dropdown" style="display:none;">' +
				'<div class="wiz-tiny-dropdown-options" data-layout="text"><i class="fas fa-align-left"></i> Text</div>' +
				'<div class="wiz-tiny-dropdown-options" data-layout="image"><i class="fas fa-image"></i> Image</div>' +
				'<div class="wiz-tiny-dropdown-options" data-layout="button"><i class="fas fa-square"></i> Button</div>' +
				'<div class="wiz-tiny-dropdown-options" data-layout="spacer"><i class="fas fa-arrows-alt-v"></i> Spacer</div>' +
				'<div class="wiz-tiny-dropdown-options" data-layout="snippet"><i class="fa-solid fa-code"></i> Snippet</div>' +
				'</div>';
			addChunkWrapper.append(layoutChoicesHtml);
			chunkLayoutChoices = addChunkWrapper.find('.wiz-tiny-dropdown'); // Ensure the element is selected after creation
		}

		// Toggle display of the layout choices
		chunkLayoutChoices.toggle().position({
			my: "center top",
			at: "center bottom",
			of: addChunkWrapper,
			collision: "fit flip"
		});
	}
	$(document).on('click', '.duplicate-chunk', function() {
		add_chunk_by_type($(this).closest('.builder-chunk').attr('data-chunk-type'), $(this), true);
	});
	// Add a chunk based on the passed type
	function add_chunk_by_type(chunkType, addChunkTrigger, duplicate = false) {
		
		var row = addChunkTrigger.closest('.builder-row');
		var column = addChunkTrigger.closest('.builder-column');
		var rowId = row.data('row-id');
		var columnId = column.data('column-id');
		var thisChunk = addChunkTrigger.closest('.builder-chunk');
		var chunkId = thisChunk.data('chunk-id');

		idemailwiz_do_ajax('add_new_chunk', idAjax_template_editor.nonce, 
			{
				post_id: idAjax_template_editor.currentPost.ID,
				row_id: rowId,
				column_id: columnId,
				chunk_before_id: chunkId,
				chunk_type: chunkType,
				duplicate: duplicate,
				session_data: JSON.stringify(getTemplateFromSession())
			},
			function(response) {
				if (response.data.html) {
        
					var newChunk;
					if (chunkId !== undefined && chunkId !== '') {
						// Insert after the specified chunk
						//var targetChunk = $('.builder-row[data-row-id="'+rowId+'"] .builder-column[data-column-id="'+columnId+'"] .builder-chunk[data-chunk-id="' + thisChunk + '"]');
						newChunk = $(response.data.html).insertAfter(thisChunk);
					} else {
						// No specific chunk to add after, insert after the last chunk in the column
						var lastChunk = column.find('.builder-chunk').last();
						if (lastChunk.length > 0) {
							newChunk = $(response.data.html).insertAfter(lastChunk);
						} else {
							// No specific chunk to add after, and no existing chunks in the column
							var chunksWrapperBody = column.find('.builder-column-chunks-body');
							var appendedContent = $(response.data.html); // Create a jQuery object from the HTML string
							chunksWrapperBody.append(appendedContent); // Append the new content to the DOM
							newChunk = appendedContent; // Now newChunk correctly references the newly added chunk
						}
						
						
					}


					// Add 'newly-added' class to the new chunk
					newChunk.addClass('newly-added');

					// Reinitialize TinyMCE for any new editors in the column
					reinitTinyMCE(newChunk);
    
					initializeChunkSortables();					

					// Reinitialize tab functionality for the new chunk
					initializeChunkTabs(newChunk);

					// Initialize color picker
					initColorPickers(newChunk);

					// Remove the 'newly-added' class after 3 seconds
					setTimeout(function() {
						newChunk.removeClass('newly-added');
					}, 3000);

					setTimeout(function() {
						reindexDataAttributes('chunk-id');
						
					}, 500);


					saveTemplateToSession();
					idwiz_updatepreview();
					unsavedWysiwygChanges = true;
				}
			},


			function(xhr, status, error) {
				console.error('Error adding new chunk:', error);
			}
		);
	};

	 



	$(document).on('click', '.colAlignToggle', function (e) {
		e.stopPropagation();
		var currentText = $(this).text();
		if (currentText === 'Top') {
			$(this).text('Middle');
		} else if (currentText === 'Middle') {
			$(this).text('Bottom');
		} else {
			$(this).text('Top');
		}
		saveTemplateToSession();
		idwiz_updatepreview();
		unsavedWysiwygChanges = true;
	});

	$(document).on('click', '.show-column-settings', function (e) {
		e.stopPropagation();
		var $column = $(this).closest('.builder-column');
		var $columnSettings = $column.find('.builder-column-settings-row');
		$columnSettings.slideToggle().toggleClass('open');
	});

	


	

	function getFieldValue($field) {
		if ($field.hasClass('builder-colorpicker')) {
			var color = $field.spectrum("get");

			// Check if the color is transparent by evaluating the alpha value
			if (color && color._a === 0) {
				// If the color is transparent, return the string 'transparent'
				return 'transparent';
			} else {
				// Otherwise, return the hex string of the color
				return color && typeof color.toHexString === 'function' ? color.toHexString() : '';
			}

		} else if ($field.hasClass('wiz-wysiwyg')) {
			var editor = tinymce.get($field.attr('id'));
			if (editor) {
				editor.save();
				return $field.val();
			}
		} else {
			return $field.val();
		}
	}

	function collectFieldValues($container, target) {
		// Iterate over all inputs within the current container
		$container.find('input, select, textarea, .builder-colorpicker, .wiz-wysiwyg').each(function() {
			var $field = $(this);
			var value = getFieldValue($field);
			var key = $field.attr('name');

			// Build the hierarchy of fieldsets for this field
			var fieldsetHierarchy = [];
			$field.parents('fieldset').each(function() {
				var fieldsetName = $(this).attr('name');
				if (fieldsetName) {
					// Prepend to maintain the correct order (closest first)
					fieldsetHierarchy.unshift(fieldsetName);
				}
			});

			// Find or create the nested structure in the target based on the hierarchy
			var nestedTarget = target;
			fieldsetHierarchy.forEach(function(name) {
				nestedTarget[name] = nestedTarget[name] || {};
				nestedTarget = nestedTarget[name];
			});

			// Assign the value within the nested structure
			if ($field.is(':checkbox')) {
				// Directly assign true or false to the checkbox key based on its checked state
				nestedTarget[key] = $field.is(':checked');
			} else if ($field.is(':radio')) {
				// For radio buttons, store the single selected value directly
				if ($field.is(':checked')) {
					nestedTarget[key] = value;
				}
			} else {
				// Directly assign the value for other input types
				nestedTarget[key] = value;
				
			}

		});
	}


	function processChunk($chunk, colCount) {
		var chunk = {
			//id: $chunk.attr('id'),
			state: $chunk.hasClass('--expanded') ? 'expanded' : 'collapsed',
			field_type: $chunk.data('chunk-type'),
			editor_mode: 'light',
			fields: {},
			settings: {},
		};
		var $chunkFields = $chunk.find('.chunk-content');
		var $chunkSettings = $chunk.find('.chunk-settings');

		// Check for dark mode on chunk
		if (chunk.field_type === 'text') {
		var darkModeToggle = $('.tox-toolbar__group[title="theme_switcher"]');
		if (darkModeToggle.length) {
			if (darkModeToggle.find('.tox-tbtn--enabled').length) {
				chunk.editor_mode = 'dark';
			}
		}
		}

		// Determine desktop/mobile visibility
		var chunkDesktopVisibility = $chunk.find('.builder-chunk-header .show-on-desktop').attr('data-show-on-desktop');
		var chunkMobileVisibility = $chunk.find('.builder-chunk-header .show-on-mobile').attr('data-show-on-mobile');

		chunk.settings.desktop_visibility = chunkDesktopVisibility === 'false' ? 'false' : 'true';
		chunk.settings.mobile_visibility = chunkMobileVisibility === 'false' ? 'false' : 'true';

		// Set image context for exact pixel width on Outlook images
		if ($chunk.data('chunk-type') === 'image') {
			chunk.settings.image_context = '';
			if (colCount === 2) {
			chunk.settings.image_context = 'two-col';
			} else if (colCount === 3) {
				chunk.settings.image_context = 'three-col';
			}
		}

		// For snippets, save both the value (ID) and the snippet name
		if ($chunk.data('chunk-type') === 'snippet') {
			var snippetName = $chunk.find('select[name="select_snippet"] option:selected').text();
			chunk.fields.snippet_name = snippetName;
		}

    
		// Directly modify chunk.fields and chunk.settings without reassignment
		collectFieldValues($chunkFields, chunk.fields);
		collectFieldValues($chunkSettings, chunk.settings);

		return chunk;
	}


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



	function updateBuilderChunkPreview(chunkType, element) {
		var $element = $(element);
		var $closestChunk = $element.closest('.builder-chunk');
  
		switch (chunkType) {
		  case 'image':
			var imageUrl = $element.val();
			$closestChunk.find('.image-chunk-preview-wrapper > img').attr('src', imageUrl);
			break;
		  case 'button':
			var buttonText = $element.val();
			$closestChunk.find('.button-chunk-preview-wrapper .wiz-button').text(buttonText);
			break;
		  case 'spacer':
			var spacerHeight = $element.val();
			$closestChunk.find('.spacer-chunk-preview-wrapper .spacer-height-display').text(spacerHeight);
			break;
		  case 'snippet':
			var snippetText = $element.find('option:selected').text();
			$closestChunk.find('.snippet-chunk-preview-wrapper .snippet-name-display').text(snippetText);
			break;
		  default:
			console.warn('Unsupported chunk type:', chunkType);
		}
	  }
	

	// Goes through the DOM and gathers all the data needed to save to the JSON object
	function gatherTemplateData() {
		var templateData = {
			templateOptions: {
				templateSettings: {},
				templateStyles: {
					'custom-styles': {} 
				}
			},
			rows: []
		};

		// Look for any force white settings set to true and set a global setting if so
		templateData.templateOptions.templateStyles['custom-styles']['force_white_text'] = false;

		// Check if at least one of the checkboxes is checked
		var force_white_text_desktop = $('.chunk-settings input[name="force_white_text_on_desktop"]').is(':checked');
		var force_white_text_mobile = $('.chunk-settings input[name="force_white_text_on_mobile"]').is(':checked');

		if (force_white_text_desktop) {
			templateData.templateOptions.templateStyles['custom-styles'].force_white_text_desktop = true;
		}
		if (force_white_text_mobile) {
			templateData.templateOptions.templateStyles['custom-styles'].force_white_text_mobile = true;
		}

		

		// Collect settings and styles
		collectFieldValues($('#builder-tab-settings'), templateData.templateOptions.templateSettings);
		collectFieldValues($('#builder-tab-styles'), templateData.templateOptions.templateStyles);

		// Collect row data
		$('#builder .builder-row').each(function() {
			

			var row = { 
				columns: [],
				state: $(this).hasClass('--expanded') ? 'expanded' : 'collapsed',
				title: $(this).find('.builder-row-title-text').text(),
				stacked: $(this).attr('data-column-stacked') === 'stacked' ? 'stacked' : false,
				desktop_visibility: 'true',
				mobile_visibility: 'true',
				magic_wrap: 'off',
			};

			// Determine desktop/mobile visibility
			var rowDesktopVisibility = $(this).find('.builder-row-header .show-on-desktop').attr('data-show-on-desktop');
			var rowMobileVisibility = $(this).find('.builder-row-header .show-on-mobile').attr('data-show-on-mobile');

			row.desktop_visibility = rowDesktopVisibility === 'true' ? 'true' : 'false';
			row.mobile_visibility = rowMobileVisibility === 'true' ? 'true' : 'false';
			

			// Determine magic wrap status
			var rowColsMagicWrap = $(this).find('.builder-row-header .row-columns-magic-wrap').attr('data-magic-wrap');
			row.magic_wrap = rowColsMagicWrap;

			let builderColumns = $(this).find('.builder-column');

			var columnCount = builderColumns.length;

			// If magic wrap is on, we reverse the array of dom elements and save them criss-cross to the builder's columns
			// By doing this, the columns in the builder and on the desktop preview will match order, but the mobile version will still magic wrap properly
			if (rowColsMagicWrap === 'on') {
				// Convert the jQuery object to an array and reverse it
				builderColumns = $.makeArray(builderColumns).reverse();
			}

			

			// Use $.each() to iterate over the array since builderColumns is no longer a jQuery object
			$.each(builderColumns, function(index, column) {
				var $column = $(column);
				var columnActivation = $column.hasClass('active') ? 'active' : 'inactive';
				var columnState = $column.hasClass('--expanded') ? 'expanded' : 'collapsed';

				//var columnValign = $column.find('.colAlignToggle').text();
				
				var buildColumn = { 
					state: columnState,
					title: $column.find('.builder-column-title-text').text(),
					activation: columnActivation,
					settings: {
						valign: 'top',
					},
					chunks: []
				};

				var $columnSettings = $column.find('.builder-column-settings');
    
				// Directly modify column.settings
				collectFieldValues($columnSettings, buildColumn.settings);

				$column.find('.builder-chunk').each(function() {
					var chunk = processChunk($(this), columnCount);

					buildColumn.chunks.push(chunk);
				});




				row.columns.push(buildColumn);
			});

			templateData.rows.push(row);
		});


		//console.log('Template data:', templateData);
		return templateData;
	}







	// Saves the template data, a JSON object, (publish or draft) to the database
	function saveTemplateData(saveType, templateData = false) {
		if (!templateData) {
			var templateData = gatherTemplateData();
		}
		//console.log('To save: Template data:', templateData);
		var formData = {
			action: 'save_wiz_template_data',
			security: idAjax_template_editor.nonce,
			post_id: idAjax_template_editor.currentPost.ID,
			save_type: saveType,
			template_data: JSON.stringify(templateData) 
		};

		$.ajax({
			url: idAjax_template_editor.ajaxurl,
			type: 'POST',
			data: formData, 
			success: function(response) {
				//console.log(response);
				//console.log('Template saved:', response.data.message);
				//console.log('Data saved:', response.data.templateData);
				do_wiz_notif({message: response.data.message, duration: 5000});
				idwiz_updatepreview();
				unsavedWysiwygChanges = false;
			},
			error: function(xhr, status, error) {
				console.error('Error saving template:', error);
			}
		});
	}


	//Update preview on page load
	$(function () {
		// Check for the chunks creator, indicating we're on a template edit page
		if ($("#builder").length > 0) {
			idwiz_updatepreview(true);
		}
	});
	


	$("#builder").on("input change paste keydown", "input, select, textarea", function(e) {
		// Assuming iframe is fully loaded before this code runs. Otherwise, ensure it's loaded.
		var iframeDocument = $('#previewFrame').get(0).contentDocument || $('#previewFrame').get(0).contentWindow.document;
    
		// Make sure to access the scrolling element correctly based on standards
		var scrollElement = iframeDocument.documentElement;

		// Capture the current scroll position
		var currentScrollPosition = $(scrollElement).scrollTop();
		console.log("Current Scroll Position:", currentScrollPosition);


		// Check for undo and redo key combinations
		if (e.type === "keydown" && (e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'y' || e.key === 'Z' || e.key === 'Y')) {
			// Debounce or delay to catch the result of undo/redo
			setTimeout(function() {
				saveTemplateToSession();
				idwiz_updatepreview(false);
				unsavedChanges = true;
			}, 100);
		} else if (e.type !== "keydown") {
			// For input, change, and paste events, directly update and restore position without delay
			saveTemplateToSession();
			idwiz_updatepreview(false);
			unsavedChanges = true;
		}
	});




	var previewRefreshTimeoutId;
	var scrollRestoreTimeoutId; // For debouncing scroll restoration

	// Update preview via AJAX, with optional use of session storage data
	function idwiz_updatepreview(fromDatabase = false) {
		var iframe = $("#previewFrame")[0];
		var iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
		var scrollElement = iframeDocument.documentElement;

		// Capture the current scroll position
		var currentScrollPosition = $(scrollElement).scrollTop();
		//console.log("Current Scroll Position:", currentScrollPosition);

		var templateId = $("#templateUI").data("postid");
		saveTemplateToSession();
    
		clearTimeout(previewRefreshTimeoutId); // Clear existing timeout to debounce updates
		$('#templatePreview-status').fadeIn().text('Updating preview...');
		previewRefreshTimeoutId = setTimeout(function () {
			var sessionData = getTemplateFromSession();

			refresh_template_html();
        
			var formData = new FormData();
			formData.append("action", "idemailwiz_build_template");
			formData.append("security", idAjax_template_editor.nonce);
			formData.append("templateid", templateId);

			if (sessionData && !fromDatabase) {
				formData.append("template_data", JSON.stringify(sessionData));
			}

			$.ajax({
				url: idAjax.ajaxurl,
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				success: function (previewHtml) {
					iframe.srcdoc = previewHtml;
					$('#templatePreview-status').fadeOut();

					// Debounce the scroll restoration to align with update frequency
					clearTimeout(scrollRestoreTimeoutId);
					scrollRestoreTimeoutId = setTimeout(function() {
						//console.log('Restoring scroll to:', currentScrollPosition);
						var iframeWindow = iframe.contentWindow;
						if (iframeWindow && typeof iframeWindow.scrollTo === 'function') {
							iframeWindow.scrollTo(0, currentScrollPosition);
						}
					}, 100); // Scroll debounce
				}
			});
		}, 1000); // Update debounce
	}




	//iframe context stuff
	var addIframeEventHandlers;
	addIframeEventHandlers = function (iframeDocument) {
		var preview = $(iframeDocument);

		
	};

	

	var saveSessionTimeoutId; // Global or higher scope variable for tracking the debounce timeout

	// Saves current DOM to session storage
	function saveTemplateToSession() {
		// Clear any existing timeout to debounce function calls
		clearTimeout(saveSessionTimeoutId);

		// Set a new timeout to delay the execution of session storage saving
		saveSessionTimeoutId = setTimeout(function() {
			// Save tinyMCE content
			saveAllTinyMces();
			var templateData = gatherTemplateData();
			var dataWithTimestamp = {
				timestamp: new Date().toISOString(),
				data: templateData
			};
			sessionStorage.setItem('templateData', JSON.stringify(dataWithTimestamp));
			do_wiz_notif({message: 'Template data saved to local session', duration: 5000});
			//console.log('Template data saved to session:', dataWithTimestamp);
		}, 1000); // Delay the execution by 500ms
	}


	// Retreives sessiondata
	function getTemplateFromSession() {
		var storedData = sessionStorage.getItem('templateData');
		if (storedData) {
			var parsedData = JSON.parse(storedData);
			var templateData = parsedData.data; // This is your actual template data
			//console.log('Retrieved template data from session:', templateData);
			return templateData;
		} else {
			//console.log('No template data found in session.');
			return null; // Or handle this case as needed
		}
	}



	$(".show-preview").on("click", function () {
		show_template_preview();
	});

	function show_template_preview() {
		toggleOverlay(true);
		$('body').css('overflow', 'hidden');
		var templateId = $(this).data("postid");

		var additionalData = {
			template_id: templateId,
			user_id: idAjax_template_editor.current_user.ID,
			session_data: getTemplateFromSession()
		};

		idemailwiz_do_ajax("generate_template_for_preview", idAjax_template_editor.nonce, additionalData, getTemplateSuccess, getTemplateError, "html");

		function getTemplateSuccess(data) {
			console.log(data);
			// First, parse the JSON if not automatically done by jQuery
			var responseData = typeof data === 'string' ? JSON.parse(data) : data;
    
			// Append the preview pane HTML (which includes the empty iframe) to the body
			$('body').append(responseData.data.previewPaneHtml);
    
			// Populate the iframe with the email template HTML
			populateIframeWithTemplate(responseData.data.emailTemplateHtml);

			// Show the preview pane
			$("#previewPopup").fadeIn();
			$(".previewPopupInnerScroll").scrollTop(0);
			toggleOverlay(true);
		}


		function getTemplateError(xhr, status, error) {
			console.log('Error retrieving or generating template HTML!');
		}

		function populateIframeWithTemplate(templateHtml) {
			var iframe = document.getElementById('emailTemplatePreviewIframe');
			if (iframe) {
				var doc = iframe.contentDocument || iframe.contentWindow.document;
				doc.open();
				doc.write(templateHtml);
				doc.close();
			}
		}
	};




	// // Hide code on click outside box
	// $(document).on("click", function(event) {
	// 	if (!$(event.target).closest("#previewPopup").length && $("#previewPopup").is(":visible")) {
	// 		$("#previewPopup").hide();
	// 		$('body').css({ overflow: 'auto' });
	// 		toggleOverlay(false);
	// 	}
	// });

	//Close preview popup
	$(document).on("click", "#hideTemplatePreview", function() {
		$("#previewPopup").fadeOut(function () {
			setTimeout(function () {
				$("#previewPopup").remove();
			}, 1000);
		});

		toggleOverlay(false);
		$('body').css('overflow', 'auto');
	});


	// Copy code in the popup
	$(document).on("click", "#copyCode", function() {
		var originalText = $(this).html(); // Store the original button text
		var html = $("#templateCode code").text();
		var tempInput = document.createElement("textarea");
		tempInput.style = "position: absolute; left: -1000px; top: -1000px";
		tempInput.innerHTML = html;
		document.body.appendChild(tempInput);
		tempInput.select();
		document.execCommand("copy");
		document.body.removeChild(tempInput);

		$(this).html("<i class='fa-solid fa-check'></i>&nbsp;Code copied!");

		// Set a timeout to revert back to the original text after 5 seconds
		setTimeout(() => {
			$(this).html(originalText);
		}, 5000);
	});

	// Function to display JSON data in a Swal box
	function displayJsonData(templateData) {
		var jsonData = JSON.stringify(templateData, null, 2);
		Swal.fire({
			title: 'JSON Data',
			html: `<pre><code class="json">${wizEscapeHtml(jsonData)}</code></pre>`,
			customClass: {
				popup: 'template-json-modal',
				htmlContainer: 'template-json-pre-wrap'
			},
			width: '800px',
			didOpen: () => {
				document.querySelectorAll('pre code').forEach((block) => {
					hljs.highlightElement(block);
				});
			}
		});
	}

	// Function to fetch JSON data and display or export it
	function getWizTemplateJson(templateId, callback) {
		var sessionData = getTemplateFromSession();
		if (sessionData) {
			callback(sessionData);
		} else {
			var additionalData = { template_id: templateId };
			idemailwiz_do_ajax("get_wiztemplate_with_ajax", idAjax_template_editor.nonce, additionalData, 
				function(data) { // Success callback
					callback(data.data);
				}, 
				function(xhr, status, error) { // Error callback
					console.error('Error retrieving or generating JSON for template');
					Swal.fire({
						icon: 'error',
						title: 'Oops...',
						text: 'Error retrieving or generating JSON for template!',
					});
				}, 
				"json");
		}
	}

	// View JSON in popup
	$("#viewJson").on("click", function () {
		var templateId = $(this).data("post-id");
		getWizTemplateJson(templateId, displayJsonData);
	});

	// Function to export JSON data
	$("#exportJson").on("click", function () {
		var templateId = $(this).data("post-id");
		getWizTemplateJson(templateId, function(jsonData) {
			var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(jsonData, null, 2));
			var downloadAnchorNode = document.createElement('a');
			downloadAnchorNode.setAttribute("href", dataStr);
			downloadAnchorNode.setAttribute("download", "template_data.json");
			document.body.appendChild(downloadAnchorNode); // required for firefox
			downloadAnchorNode.click();
			downloadAnchorNode.remove();
		});
	});

	$('#importJson').on('click', function () {
		importWizTemplateJson();
	});
	function importWizTemplateJson() {
		Swal.fire({
			title: 'Import JSON Data',
			showCancelButton: true,
			confirmButtonText: 'Import',
			html: `
				<div class="swalTabs">
					<ul>
						<li><a href="#pasteTab" class="active" data-tab="pasteTab">Paste JSON</a></li>
						<li><a href="#uploadTab" data-tab="uploadTab">Upload File</a></li>
					</ul>
					<div id="pasteTab" style="display: block; height: 300px;">
						<textarea id="jsonInput" rows="10" style="width: 100%; margin-top: 15px;"></textarea>
					</div>
					<div id="uploadTab" style="display: none; height: 300px;">
						<div class="swal-file-upload">
							<input type="file" id="jsonFileInput" name="jsonFile">
							<label for="jsonFileInput" class="file-upload-label">Drag and drop a file here or click to select a file</label>
						</div>
					</div>

				</div>
			`,
			focusConfirm: false,
			preConfirm: () => {
				const isPastedData = $('.swalTabs ul li a.active').attr('data-tab') === 'pasteTab';
				return process_wiz_template_json_upload(isPastedData)
				.then(sessionKey => {
					// Optionally clear the session storage if it's no longer needed
					sessionStorage.removeItem(sessionKey);

					// Show a success message with Swal
					Swal.fire({
						title: 'Success!',
						text: 'Your JSON data has been processed successfully.',
						icon: 'success',
						confirmButtonText: 'OK'
					}).then((result) => {
						if (result.value) {
							// Refresh the page to reflect the changes
							window.location.reload();
						}
					});
				})
				.catch(error => {
					Swal.showValidationMessage(`Process failed: ${error.message}`);
					// Returning a rejected promise prevents Swal from closing
					return Promise.reject(error);
					
				});
			},
			didOpen: () => {
				// Initialize the tab interface
				document.querySelectorAll('.swalTabs ul li a').forEach((tab) => {
					tab.addEventListener('click', (e) => {
						e.preventDefault();
						const tabId = tab.getAttribute('href').substring(1); // Get the ID without the '#'
                    
						// Deactivate all tabs and hide all tab content
						document.querySelectorAll('.swalTabs ul li a').forEach(t => t.classList.remove('active'));
						document.querySelectorAll('.swalTabs > div').forEach(content => content.style.display = 'none');
                    
						// Activate clicked tab and show its content
						tab.classList.add('active');
						document.getElementById(tabId).style.display = 'block';
					});
				});

				// Trigger click on the first tab to show it by default
				document.querySelector('.swalTabs ul li a').click();

				$('#jsonFileInput').on('change', function() {
					// Check if any files were selected
					if (this.files && this.files.length > 0) {
						var file = this.files[0];
						var fileType = file.type;
						var match = ['application/json', 'text/json'];

						// Validate file type
						if (match.indexOf(fileType) !== -1) {
							// File is a JSON, update label text to show file name
							$('.file-upload-label').text(file.name + " is ready to upload.")
								.css('color', '#28a745'); // Optional: change label color
            
							$('.swal-file-upload').css({
								'border-color': '#28a745', // Example: Change border color
								'background-color': '#e2e6ea' // Lighten background
							});
						} else {
							// File is not a JSON, show error and reset input
							$('.file-upload-label').text("Invalid file type. Please select a .json file.")
								.css('color', '#dc3545'); // Optional: change label color for error
            
							$('.swal-file-upload').css({
								'border-color': '#dc3545', // Example: Change border color for error
								'background-color': '#f8d7da' // Light background for error
							});

							// Reset the file input for another selection
							$(this).val('');
						}
					} else {
						// No file selected, reset to default state
						resetUploadField();
					}
				});

				// Function to reset the upload field to its default state
				function resetUploadField() {
					$('.file-upload-label').text("Drag and drop a file here or click to select a file")
						.css('color', '#007bff');
    
					$('.swal-file-upload').css({
						'border-color': '#007bff',
						'background-color': '#f8f9fa'
					});
				}

			}
		});
	}


	async function process_wiz_template_json_upload(isPastedData) {
		async function processData(data) {
			try {
				const parsedData = JSON.parse(data);
				// Await the validation result; this will throw an error if validation fails
				await validateWizTemplateSchema(parsedData);

				// If validation succeeds, proceed with saving the data
				const timestamp = new Date().getTime();
				const sessionKey = `uploadedJsonData_${timestamp}`;
				sessionStorage.setItem(sessionKey, JSON.stringify(parsedData));

				// Update template from JSON
				saveTemplateData('publish', parsedData);
        
				// Return the session key or any other result as needed
				return sessionKey;
			} catch (error) {
				// Handle or rethrow the error as appropriate
				console.error(error);
				throw error;
			}
		}

		if (isPastedData) {
			const pastedData = document.getElementById('jsonInput').value;
			return processData(pastedData); // Return the promise
		} else {
			const file = document.getElementById('jsonFileInput').files[0];
			if (file) {
				return new Promise((resolve, reject) => {
					const reader = new FileReader();
					reader.onload = async (e) => {
						try {
							const fileData = e.target.result;
							await processData(fileData);
							resolve(); // Resolve the outer promise
						} catch (error) {
							reject(error); // Reject the outer promise
						}
					};
					reader.onerror = (e) => {
						reject(`Error reading file: ${e.target.error}`);
					};
					reader.readAsText(file);
				});
			} else {
				return Promise.reject(new Error('No file selected.'));
			}
		}
	}


	
	function validateWizTemplateSchema(parsedData) {
		// Check if the main key 'templateOptions' exists
		if (parsedData.hasOwnProperty('templateOptions')) {
			// Check for 'templateSettings' and 'rows' keys within 'templateOptions'
			if (parsedData.templateOptions.hasOwnProperty('templateSettings') &&
				parsedData.templateOptions.hasOwnProperty('rows')) {
				console.log("JSON structure is valid.");
				return true;
			} else {
				console.error("JSON structure does not have the required 'templateSettings' and 'rows' keys.");
				return false;
			}
		} else {
			console.error("JSON structure does not have the 'templateOptions' key.");
			return false;
		}
	}

	




	
	// Utility function to safely escape HTML
	function wizEscapeHtml(text) {
		return text.replace(/&/g, "&amp;")
				   .replace(/</g, "&lt;")
				   .replace(/>/g, "&gt;")
				   .replace(/"/g, "&quot;")
				   .replace(/'/g, "&#039;");
	}


	
	function refresh_template_html() {
		additionalData = {
			template_id: idAjax_template_editor.currentPost.ID,
			session_data: getTemplateFromSession()
		}
		idemailwiz_do_ajax("generate_template_html_from_ajax", idAjax_template_editor.nonce, additionalData, getHtmlSuccess, getHtmlError, "json");
	}

	function getHtmlSuccess(data) {
		//console.log(data);

		// Target the <code> element within #templateCode for the update
		var codeElement = $('#templateCode').find('code');
		if(codeElement.length === 0) {
			// If <code> doesn't exist, create it
			$('#templateCode').html('<code></code>');
			codeElement = $('#templateCode').find('code');
		}

		// Update the content of the <code> element
		codeElement.html(data.data.templateHtml);

		// Reapply syntax highlighting to the new content
		hljs.highlightElement(codeElement.get(0));

		do_wiz_notif({message: 'HTML code updated', duration: 3000});
	}


	function getHtmlError(xhr, status, error) {
		console.log('Error retrieving or generating HTML for template');
		console.log(xhr);
	}
	

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



	
	


});





