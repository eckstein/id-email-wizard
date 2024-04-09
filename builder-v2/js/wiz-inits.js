function initialize_template() {
    // Start a new session to track unsaved changes
    save_template_to_session();
		

    // Initialize template-specific functionality

    //update_template_preview(true); // We don't update the template preview on page load because it's initiated by the server from the saved data

    builder_init_tinymce();
    initialize_all_sortables();
    initialize_editable('.builder-row-title-text', 'row-id');
    initialize_editable('.builder-column-title-text', 'column-id');
    initialize_editable('.builder-columnset-title-text', 'columnset-id');
    initialize_chunk_tabs();
    init_spectrum_pickers();
    initialize_bg_type_selection();
    initialize_device_width_slider();
    init_codemirror_for_custom_styles();

    // Codemirror for raw HTML chunk content
    jQuery('.wiz-html-block').each(function () {
        init_codemirror_chunk(this);
    });

    // Apply saved gradient to any gradient picker labels that have one
    jQuery('.chunk-gradient-settings .gradientLabel').each(function() {
      apply_gradient_to_picker_label(jQuery(this));
    });

    // Set unsavedChanges to false to prevent warning without any template changes on initial load
    sessionStorage.setItem('unsavedChanges', 'false');
  }

// Initiate color pickers based on optional selected element
function init_spectrum_pickers($optionalElement = null) {
    var $element = $optionalElement ? $optionalElement : jQuery('#builder');
    let colorPickers = $element.find('.builder-colorpicker');

    colorPickers.each(function () {
        // Check if Spectrum is already initialized on this element
        if (jQuery(this).siblings('.sp-replacer').length) { // Check for a Spectrum-specific property or class
            jQuery(this).spectrum("destroy"); // If initialized, destroy it first
        }

        // Then initialize Spectrum
        jQuery(this).spectrum({
            allowEmpty:true,
            showInitial: true,
            showInput: true,
            showPalette: true,
            palette: [
                ['#000000', '#343434', '#94c52a', '#f4f4f4', '#ffffff', '#1b75d0', 'transparent']
            ],
            //showAlpha: true,
            preferredFormat: 'hex'
        }).change(function (color) {
            save_template_to_session();
            update_template_preview();
        });

        // Retrieve the existing value from the input's value attribute
        var existingColor = jQuery(this).attr('data-color-value');

        // Set the Spectrum color picker to the existing color
        if (existingColor) {
            jQuery(this).spectrum('set', existingColor);
        }
    });

}

// Initiate gradX gradient pickers with Swal2
function initGradientPicker(clickedElement) {
    var $gradientLabel = jQuery(clickedElement);
    // Generate a unique ID for the gradient picker to avoid conflicts
    var gradientPickerId = 'gradientPicker-' + Math.random().toString(36).substr(2, 9);
    var $gradientInput = $gradientLabel.prev('input.gradientValue'); 

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
                        type: jQuery('#gradx_gradient_type').val(), 
                        direction: jQuery('#gradx_gradient_subtype').val(),
                        direction2: jQuery('#gradx_gradient_subtype2').val(),
                    };

                    $gradientInput.val(JSON.stringify(saveFormat));
                    $gradientLabel.css('background-image', style);
                }
            });

            jQuery('#gradx_gradient_type').val(gradientConfig.type).trigger('change');
            jQuery('#gradx_gradient_subtype').val(gradientConfig.direction).trigger('change');
            if (gradientConfig.type !== 'linear') {
                jQuery('#gradx_gradient_subtype2').val(gradientConfig.direction2).trigger('change');
            }
        },
        preConfirm: () => $gradientInput.val()
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            $gradientInput.val(initialGradientValue);
            update_gradient_preview($gradientLabel, $gradientInput);
        }
        save_template_to_session();
        update_template_preview();
    });
}

function initialize_all_sortables() {
    // Call each specific sortable initializer as needed
    initialize_row_sortables();
    initialize_columnset_sortables();
    initialize_column_sortables();
    initialize_chunk_sortables();
}

	

// Initialize sortable for rows, columns, and chunks
function initialize_row_sortables(containerId = null) {
    const containerSelector = containerId ? '#' + containerId : '.builder-rows-wrapper';
    const sortableConfig = {
        itemsSelector: '.builder-row',
        handleSelector: '.builder-row-header',
        placeholderClass: 'row-placeholder',
        additionalOptions: {}
    };
    initialize_wiz_sortables(containerSelector, sortableConfig.itemsSelector, sortableConfig.handleSelector, sortableConfig.placeholderClass, sortableConfig.additionalOptions);
}

// Initialize sortable for rows, columns, and chunks
function initialize_columnset_sortables(containerId = null) {
    const containerSelector = containerId ? '#' + containerId : '.builder-columnsets';
    const sortableConfig = {
        itemsSelector: '.builder-columnset',
        handleSelector: '.builder-columnset-header',
        placeholderClass: 'columnset-placeholder',
        additionalOptions: {
            connectWith: '.builder-columnsets',
            tolerance: 'pointer', 
            dropOnEmpty: true,
            receive: function(event, ui) {
                reindexDataAttributes('columnset-id');
                reinitTinyMCE(ui.item);
            },
        }
    };
    initialize_wiz_sortables(containerSelector, sortableConfig.itemsSelector, sortableConfig.handleSelector, sortableConfig.placeholderClass, sortableConfig.additionalOptions);
}

function initialize_column_sortables(containerId = null) {
    const containerSelector = containerId ? '#' + containerId + ' .builder-columnset-columns' : '.builder-columnset-columns';
    const sortableConfig = {
        itemsSelector: '.builder-column',
        handleSelector: '.builder-column-header',
        placeholderClass: 'column-placeholder',
        additionalOptions: { 
            tolerance: 'pointer'
        }
    };
    initialize_wiz_sortables(containerSelector, sortableConfig.itemsSelector, sortableConfig.handleSelector, sortableConfig.placeholderClass, sortableConfig.additionalOptions);
}

function initialize_chunk_sortables(containerId = null) {
    const containerSelector = containerId ? '#' + containerId + ' .builder-column-chunks-body' : '.builder-column-chunks-body';
    const sortableConfig = {
        itemsSelector: '.builder-chunk',
        handleSelector: '.builder-chunk-header',
        placeholderClass: 'chunk-placeholder',
        additionalOptions: {
            connectWith: '.builder-column-chunks-body',
            tolerance: 'pointer',
            dropOnEmpty: true,
            receive: function(event, ui) {
                reindexDataAttributes('chunk-id');
                reinitTinyMCE(ui.item);
            },
            over: function(event, ui) {
                if (jQuery(this).children('.builder-chunk').length === 0) {
                    jQuery(this).addClass('highlight-empty');
                }
            },
            out: function(event, ui) {
                jQuery(this).removeClass('highlight-empty');
            }
        }
    };
    initialize_wiz_sortables(containerSelector, sortableConfig.itemsSelector, sortableConfig.handleSelector, sortableConfig.placeholderClass, sortableConfig.additionalOptions);
}


function initialize_wiz_sortables(containerSelector, itemsSelector, handleSelector, placeholderClass, additionalOptions) {
    var $containers = jQuery(containerSelector);

    $containers.each(function() {
        var $container = jQuery(this);

        // Initialize sortable for each container found
        initialize_wiz_sortable($container, itemsSelector, handleSelector, placeholderClass, additionalOptions);
    });
}


function initialize_wiz_sortable($container, itemsSelector, handleSelector, placeholderClass, additionalOptions) {
    var attributeMap = {
        '.builder-row': 'row-id',
        '.builder-columnset': 'columnset-id',
        '.builder-column': 'column-id',
        '.builder-chunk': 'chunk-id'
    };
    $container.sortable(jQuery.extend({
        items: itemsSelector,
        handle: handleSelector,
        placeholder: placeholderClass,
        start: function(event, ui) {

            var headerHeight = ui.item.find(handleSelector).outerHeight();
            jQuery('.' + placeholderClass, $container).css({
                'min-height': headerHeight,
                'width': ui.item.outerWidth() + 'px'
            });

            // Update and destroy TinyMCE instances within the dragged item
            ui.item.find('.wiz-wysiwyg').each(function() {
                var editor = tinymce.get(this.id);
                if (editor) {
                    jQuery(this).val(editor.getContent());
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
            save_template_to_session();
            setTimeout(function() {
                var attributeToReindex = attributeMap[itemsSelector];
                if (attributeToReindex) {
                    reindexDataAttributes(attributeToReindex);
                }
                
                update_template_preview();
                
                sessionStorage.setItem('unsavedChanges', 'true');
            }, 500);
        }
    }, additionalOptions));
}


// Initialize editable elements
	
function initialize_editable(editableClass, dataAttributeName, $context) {
    // Default to the whole builder div if no context is provided
    $context = $context || jQuery("#builder");

    // Find editable elements within the specified context and initialize them
    $context.find(editableClass).each(function() {
        var $editable = jQuery(this);
        // Assuming the data attribute is directly on the $editable element
        var id = $editable.data(dataAttributeName);

        // Initialize editable functionality here without the need for a separate trigger
        $editable.editable({
            action: 'click', // Assuming 'click' activates editing
            onSubmit: function(e) {
                console.log('Saved text for ' + dataAttributeName + ' ' + id + ':', e.value);
            }
        });

        // Bind click event directly to the editable element to activate edit mode
        $editable.on('click', function() {
            $editable.editable('show');
        });
    });
}

// Reinitialize sortables for an element that has been cloned
function reinitialize_wiz_sortables_for_cloned($originalElement, $clonedElement) {
    // Destroy sortable on the original element's container if applicable
    let $originalSortableContainer = $originalElement.closest('.ui-sortable');
    if ($originalSortableContainer.length && jQuery.contains(document, $originalSortableContainer[0])) {
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
        let $rowContainers = jQuery('.builder-rows-wrapper'); 
        $rowContainers.each(function() {
				
            initialize_all_sortables(); 
        });
    }

    // For columns
    if ($clonedElement.hasClass('builder-column') || $originalElement.hasClass('builder-column')) {
        // Find the columns container of the cloned column to reinitialize column sortables within it
        let $clonedRowContainer = $clonedElement.closest('.builder-rows-wrapper');
        if ($clonedRowContainer.length) {
				
            initialize_column_sortables(); 
            initialize_chunk_sortables();
        }
    }

    // For columnsets
    if ($clonedElement.hasClass('builder-columnset') || $originalElement.hasClass('builder-columnset')) {
        // Find the columnsets container of the cloned columnset to reinitialize columnset sortables within it
        let $clonedColSetContainer = $clonedElement.closest('.builder-columnsets');
        if ($clonedColSetContainer.length) {
				
            initialize_columnset_sortables(); 
            initialize_column_sortables(); 
            initialize_chunk_sortables();
        }
    }

    // For chunks
    if ($clonedElement.hasClass('builder-chunk') || $originalElement.hasClass('builder-chunk')) {
        // Find the chunks container of the cloned chunk to reinitialize chunk sortables within it
        let $clonedColumnContainer = $clonedElement.closest('.builder-columnset-columns');
        if ($clonedColumnContainer.length) {
				
            initialize_chunk_sortables(); // Reinitialize all chunks within the column container
        }
    }
}


//Initialize chunk tabs 
function initialize_chunk_tabs($context) {
    $context = $context || jQuery("#builder");

    $context.on('click', '.chunk-tab', function() {
        var $thisTab = jQuery(this);
        var targetSelector = $thisTab.data('target');
        var $targetContent = jQuery(targetSelector);

        // Hide all tab contents in the current chunk
        $thisTab.closest('.builder-chunk').find('.tab-content').hide();

        // Show the target content
        $targetContent.show();

        // Update active state for tabs
        $thisTab.siblings().removeClass('active');
        $thisTab.addClass('active');
    });
}

// Function to handle common initialization tasks
function init_ui_for_new_chunk($element) {
    initialize_editable('.builder-row-title-text', 'row-id');
    initialize_editable('.builder-column-title-text', 'column-id');
    initialize_editable('.builder-columnset-title-text', 'columnset-id');

    init_spectrum_pickers($element);
    reinitTinyMCE($element);
    initialize_chunk_tabs($element);

}

function init_codemirror_chunk(textarea) {
    var editor = CodeMirror.fromTextArea(jQuery(textarea).get(0), {
        mode: 'htmlmixed',
        autoRefresh: true,
        lineNumbers: true,
        theme: 'mbo',
        smartIndent: true,
        indentWithTabs: true,
        gutters: ["CodeMirror-linenumbers", "CodeMirror-lint-markers"],
        lint: true,
        extraKeys: {
            'Ctrl-Space': 'autocomplete'
        },
        hintOptions: {
            completeSingle: false
        },
    });
        
    init_codemirror_in_app(textarea, editor);
}

function init_codemirror_for_custom_styles() {
    var custom_styles_textarea = jQuery('#template_styles_additional_css').get(0);
    var customStylesEditor = CodeMirror.fromTextArea(custom_styles_textarea, {
        mode: 'css',
        lineNumbers: true,
        autoRefresh: true,
        theme: 'mbo',
        indentUnit: 4,
        smartIndent: true,
        indentWithTabs: true,
        gutters: ["CodeMirror-linenumbers", "CodeMirror-lint-markers"],
        lint: {
            getAnnotations: function(text, options, codeMirror) {
                var preprocessedText = text.replace(/<style\b[^>]*>|<\/style>/ig, '');
                var foundAnnotations = CodeMirror.lint.css(preprocessedText, options, codeMirror);
                return foundAnnotations;
            }
           
        },
        extraKeys: {
            'Ctrl-Space': 'autocomplete'
        },
        hintOptions: {
            completeSingle: false
        },
    });

    init_codemirror_in_app(custom_styles_textarea, customStylesEditor);
    

    // Function to check if the content has properly opened and closed <style> tags
    function validateStyleTags(content) {
        if (content.trim().length !== 0) {
            var openingTag = /<style\b[^>]*>/i;
            var closingTag = /<\/style>/i;
            var openingTagMatch = content.match(openingTag);
            var closingTagMatch = content.match(closingTag);
        return openingTagMatch && closingTagMatch;
        } else {
            return true;
        }
    }

    
    // When attempting to save, give a warning for invalid CSS if needed
    jQuery('#save-template').on('click', validate_custom_styles);

    function validate_custom_styles() {
        var content = customStylesEditor.getValue();
        var lintErrors = customStylesEditor.state.lint.marked;
        if (!validateStyleTags(content) || lintErrors.length > 0) {
             // Show a swal2 warning that there is invalid CSS content
            Swal.fire({
                icon: 'warning',
                title: 'Invalid CSS',
                text: 'Please fix the CSS syntax errors in the Custom Styles tab before saving.'
            });
        }
        
    }

    

}



function init_codemirror_in_app(textarea, editor) {
    // Store the CodeMirror instance as data on the textarea element
    jQuery(textarea).data('CodeMirrorInstance', editor);

    // Force the editor to update its content
    editor.setValue(editor.getValue());
    editor.save();

    // Refresh the editor with a delay to allow the textarea to be updated
    setTimeout(function() {
        editor.refresh();
    }, 100); 

    // Debounce function to limit the execution rate
    var debounce = function(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };
        
    // Hook into the edit action of CodeMirror with debounce
    var debouncedUpdate = debounce(function() {
        // Update the <textarea> value with the current content of the editor
        textarea.value = editor.getValue();
        editor.save();

        save_template_to_session();
        update_template_preview();
    }, 500); 
        
    editor.on('change', debouncedUpdate);

    
}

// Initialize background type selection in the background settings modual
function initialize_bg_type_selection($context) {
    $context = $context || jQuery("#builder");

    // Delegate change event for current and future 'input[name="background_type"]' within '.chunk-background-settings'
    $context.off('change', '.chunk-background-settings input.background-type-select').on('change', '.chunk-background-settings input.background-type-select', function() {
        var container = jQuery(this).closest('.chunk-background-settings');
        var selectedType = jQuery(this).val();

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
        jQuery(this).change(); // Trigger the change event to ensure the UI is in the correct state
    });
}
