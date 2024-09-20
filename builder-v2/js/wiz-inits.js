function initialize_template() {
    // Start a new session to track unsaved changes
    save_template_to_session();
		
    // Initialize template-specific functionality

    init_template_title_tinymce();
    builder_init_tinymce();
    
    initialize_all_sortables();

    init_element_title_tinymce();

    initialize_chunk_tabs();
    init_spectrum_pickers();
    initialize_bg_type_selection();
    initialize_device_width_slider();

    setupPlainTextEditor();

    // Codemirror for raw HTML chunk content
    if (jQuery('.wiz-html-block').length) {
        jQuery('.wiz-html-block').each(function () {
            init_codemirror_chunk(this);
        });
    }
    
    setTimeout(function () {
        // Initialize the template preview
        update_template_preview();
        sessionStorage.setItem('unsavedChanges', 'false');
    }, 500);
    

  }

jQuery('#template-styles-custom-styles-tab').on('click', function () {
    init_codemirror_for_custom_styles();
});

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
            handle_colorpicker_changes(jQuery(this));
        });

        // Retrieve the existing value from the input's value attribute
        var existingColor = jQuery(this).attr('data-color-value');

        // Set the Spectrum color picker to the existing color
        if (existingColor) {
            jQuery(this).spectrum('set', existingColor);
        }
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
        dropOnEmpty: true,
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
            
            receive: function(event, ui) {
                reindexDataAttributes('columnset-id');
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
            receive: function(event, ui) {
                reindexDataAttributes('column-id');
            },
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
            stop: function(event, ui) {
                reindexDataAttributes('chunk-id');
                //save_template_to_session();

                var $movedTo = jQuery(ui.item).closest('.builder-column');
                var $movedFrom = jQuery(ui.sender).closest('.builder-column');

                // Update both source and destination columns
                update_template_preview_part($movedTo);
                 if ($movedFrom.length && !$movedFrom.is($movedTo)) {
                    update_template_preview_part($movedFrom);
                }
                
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
        tolerance: 'pointer',
        distance: 10,
        dropOnEmpty: true,
        //helper: 'clone',
        start: function(event, ui) {
            var headerHeight = ui.item.find('.builder-header').outerHeight();
            jQuery('.' + placeholderClass, $container).css({
                'min-height': headerHeight,
                'max-width': '100%'
            });
        },
        stop: function(event, ui) {
            handleSortableUpdate(ui.item);
        },
        receive: function (event, ui) {
            handleSortableUpdate(ui.item);
        },
        update: function(event, ui) {
            // Destroy TinyMCE instances within the dragged item
            ui.item.find('.wiz-wysiwyg').each(function() {
                var editor = tinymce.get(this.id);
                if (editor) {
                    jQuery(this).val(editor.getContent());
                    editor.remove();
                }
            });

            handleSortableUpdate(ui.item, ui.sender);
        }
    }, additionalOptions));

    function handleSortableUpdate(item, sender) {
        setTimeout(function() {
            reinitTinyMCE(item);
            save_template_to_session();

            var attributeToReindex = attributeMap[itemsSelector];
            if (attributeToReindex) {
                reindexDataAttributes(attributeToReindex);
            }
    
            if (item.hasClass('builder-row')) {
                update_template_preview();
            } else {
                // Update the new wrapper
                var $newWrapper = item.parents('.builder-row, .builder-columnset, .builder-column').first();
                update_template_preview_part($newWrapper, find_matching_preview_element($newWrapper));
        
                // Update the previous wrapper if it exists
                if (sender) {
                    var $previousWrapper = sender.parents('.builder-row, .builder-columnset, .builder-column').first();
                    update_template_preview_part($previousWrapper, find_matching_preview_element($previousWrapper));
                }
        
                reindexPreviewElements();
            }

            sessionStorage.setItem('unsavedChanges', 'true');
        }, 200);
    }
}


// Initialize editable elements
	
function initialize_editable(editableClass, dataAttributeName, $context) {
    //return;
    // Default to the whole builder div if no context is provided
    $context = $context || jQuery("#builder");

    // Find editable elements within the specified context and initialize them
    $context.find(editableClass).each(function() {
        var $editable = jQuery(this);
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

function init_codemirror_chunk(textarea, mode='htmlmixed') {
    var editor = CodeMirror.fromTextArea(jQuery(textarea).get(0), {
        mode: mode,
        autoRefresh: true,
        lineNumbers: true,
        theme: 'mbo',
        smartIndent: true,
        indentWithTabs: false,
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

function init_codemirror_custom_background(textarea, mode='text/css') {
    var editor = CodeMirror.fromTextArea(jQuery(textarea).get(0), {
        mode: mode,
        autoRefresh: true,
        lineNumbers: true,
        theme: 'mbo',
        smartIndent: true,
        indentWithTabs: false,
        gutters: ["CodeMirror-linenumbers", "CodeMirror-lint-markers"],
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
    // Destroy existing cm instance
    if (jQuery(custom_styles_textarea).siblings('.CodeMirror').length) {
        return; // do no re-init
    }
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
        handle_codemirror_changes(jQuery(textarea));
        //update_template_preview_part(jQuery(textarea));

    }, 500); 
        
    editor.on('change', debouncedUpdate);

    
}

// Initialize background type selection in the background settings modual
function initialize_bg_type_selection($context) {
    $context = $context || jQuery("#builder");

    // Delegate change event for current and future 'input[name="background_type"]' within '.chunk-background-settings'
    $context.off('change', '.chunk-background-settings input.background-type-select').on('change', '.chunk-background-settings input.background-type-select', function() {
        let container = jQuery(this).closest('.chunk-background-settings');
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
        } else if (selectedType === 'custom') {
            container.find('.chunk-background-image-settings, .chunk-background-custom-settings').show();
            container.find('.chunk-background-color-settings').hide();
            container.find('.chunk-background-image-settings').hide();
            let $textarea = container.find('.custom-background-css-input');
            if ($textarea.siblings('.CodeMirror').length) {
                return; // do no re-init
            }
            init_codemirror_custom_background($textarea, 'text/css');
            
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
