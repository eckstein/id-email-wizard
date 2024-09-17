

// Saves the template data, as a JSON object, to the database
function save_template_data(templateData = false) {
    if (!templateData) {
        var templateData = gather_template_data();
    }
    var formData = {
        action: 'save_wiz_template_data',
        security: idAjax_template_editor.nonce,
        post_id: idAjax_template_editor.currentPost.ID,
        template_data: JSON.stringify(templateData) 
    };

    return new Promise(function(resolve, reject) {
        jQuery.ajax({
            //url: idAjax_template_editor.ajaxurl,
            url: idAjax.wizAjaxUrl,
            type: 'POST',
            data: formData, 
            success: function(response) {
                save_template_to_session();
                sessionStorage.setItem('unsavedChanges', 'false');
                resolve(response.data);
            },
            error: function(xhr, status, error) {
                console.error('Error saving template:', error);
                reject('Error saving template: ' + error);
            }
        });
    });
}

function gather_mockups(templateData) {
    var desktopMockup = jQuery('#desktop-mockup-url').val();
    var mobileMockup = jQuery('#mobile-mockup-url').val();

    // Check for file value
    if (desktopMockup) {
        templateData.mockups.desktop = desktopMockup;
    }
    if (mobileMockup) {
        templateData.mockups.mobile = mobileMockup;
    }
    return templateData;
}

function gather_force_white_settings(templateData) {
    // Look for any force white settings set to true and set a global setting if so
    templateData.template_options.template_styles['custom-styles']['force_white_text'] = false;

    // Check if at least one of the checkboxes is checked
    var force_white_text_desktop = jQuery('.chunk-settings input[name="force_white_text_on_desktop"]').is(':checked') || jQuery('input[name="footer_force_white_text_on_desktop"]').is(':checked');
    var force_white_text_mobile = jQuery('.chunk-settings input[name="force_white_text_on_mobile"]').is(':checked') || jQuery('input[name="footer_force_white_text_on_mobile"]').is(':checked');

    if (force_white_text_desktop) {
        templateData.template_options.template_styles['custom-styles'].force_white_text_desktop = true;
    }
    if (force_white_text_mobile) {
        templateData.template_options.template_styles['custom-styles'].force_white_text_mobile = true;
    }
    return templateData;
}

function get_default_template_data() {
    var templateData = {
        template_name: jQuery('#idwiz_templateTitle').val(),
        template_id: idAjax_template_editor.currentPost.ID,
        last_updated: new Date().toISOString(),
        template_options: {
            message_settings: {},
            template_styles: {
                'custom-styles': {} 
            },
            template_settings: {}
        },
        rows: [],
        mockups: {
            desktop: null,
            mobile: null
        }
    };
    return templateData;
}

function gather_template_settings_data(templateData) {
    // Handle Mock-ups
    templateData = gather_mockups(templateData);
    
    // Handle force white settings
    templateData = gather_force_white_settings(templateData);
    

    // Collect message settings and styles
    collect_element_field_values(jQuery('#builder-tab-message-settings'), templateData.template_options.message_settings);
    collect_element_field_values(jQuery('#builder-tab-styles'), templateData.template_options.template_styles);

    // Collect settings
    collect_element_field_values(jQuery('#builder-tab-settings'), templateData.template_options.template_settings);

    // Collect plain text content textarea
    collect_element_field_values(jQuery('#plain-text-content'), templateData.template_options.message_settings);

    return templateData;
}

function get_element_state(element) {
    // If state saving is off, we collapse everything on next page load
    var state = 'collapsed';

    var saveCollapseStates = jQuery('#builder-tab-settings input[name="save_collapse_states"]').is(':checked');
    
    if (saveCollapseStates) {
        state = jQuery(element).hasClass('--expanded') ? 'expanded' : 'collapsed';
    }
        return state;
}

// Goes through the DOM and gathers all the data needed to save to the JSON object
function gather_template_data() {
    
    // Get template data
    var templateData = get_default_template_data();

    templateData = gather_template_settings_data(templateData);
    templateData.rows = gather_rows_data();    

    return templateData;
}

function gather_rows_data(index = null) {
    var rows = [];
    jQuery('#builder .builder-row').each(function() {
        if (index !== null && jQuery(this).attr('data-row-id') !== index) {
            return;
        }
        var $row = jQuery(this);
        var row = { 
            state: get_element_state($row),
            title: $row.find('.builder-row-title-text').text(),
            background_settings: {},
            columnSets: [],
        };

        // Collect row settings
        $rowSettings = $row.find('.builder-row-settings');
        collect_element_field_values($rowSettings, row.background_settings);


        // Determine desktop/mobile visibility
        var rowDesktopVisibility = $row.find('.builder-row-header .show-on-desktop').attr('data-show-on-desktop');
        var rowMobileVisibility = $row.find('.builder-row-header .show-on-mobile').attr('data-show-on-mobile');

        row.desktop_visibility = rowDesktopVisibility === 'true' ? 'true' : 'false';
        row.mobile_visibility = rowMobileVisibility === 'true' ? 'true' : 'false';

        var framesMode = $row.find('.toggle-frames-mode').attr('data-frames-mode');
        row.frames_mode = framesMode === 'true' ? 'true' : 'false';
			

        // Collect columnset data
        row.columnSets = gather_columnsets_data($row);

        rows.push(row);
    });
    return rows;
}

function gather_columnsets_data($row, $columnSetIndex = null) {
    var columnSets = []; 

    $row.find('.builder-columnset').each(function(index, columnSet) {
        if ($columnSetIndex !== null && jQuery(columnSet).attr('data-columnset-id') !== $columnSetIndex) {
            
            return;
        }
        var columnSet = jQuery(columnSet);
        var colsetDesktopVisibility = columnSet.find('.builder-columnset-header .show-on-desktop').attr('data-show-on-desktop');
        var colsetMobileVisibility = columnSet.find('.builder-columnset-header .show-on-mobile').attr('data-show-on-mobile');
        var columnSetData = {
            title: columnSet.find('.builder-columnset-title-text').text(),
            state: get_element_state(columnSet),
            layout: columnSet.attr('data-layout') ? columnSet.attr('data-layout') : 'one-col',
            stacked: columnSet.attr('data-column-stacked') === 'stacked' ? 'stacked' : false,
            desktop_visibility: colsetDesktopVisibility === 'true' ? 'true' : 'false',
            mobile_visibility: colsetMobileVisibility === 'true' ? 'true' : 'false',
            magic_wrap: columnSet.attr('data-magic-wrap') == 'on' ? 'on' : 'off',
            mobile_wrap: columnSet.attr('data-mobile-wrap') == 'on' ? 'on' : 'off',
            background_settings: {},
            columns: [],
        };

        // Collect colset settings
        $colsetSettings = columnSet.find('.builder-columnset-settings');
        collect_element_field_values($colsetSettings, columnSetData.background_settings);


        let builderColumns = columnSet.find('.builder-column');

        var columnLayout = columnSet.attr('data-layout') ? columnSet.attr('data-layout') : ''

        // Collect column data
        columnSetData.columns = gather_columns_data(builderColumns, columnLayout);
            
        
        columnSets.push(columnSetData);
    });
    return columnSets;
}


function gather_columns_data(builderColumns, columnLayout) {
    // Use $.each() to iterate over the array since builderColumns is no longer a jQuery object
    var columns = [];
    jQuery.each(builderColumns, function(columnIndex, column) {

        var $column = jQuery(column);
        var columnActivation = $column.hasClass('active') ? 'active' : 'inactive';
                    
        var columnData = { 
            title: $column.find('.builder-column-title-text').text(),
            activation: columnActivation,
            settings: {
                valign: 'top',
            },
            chunks: []
        };

        var $columnSettings = $column.find('.builder-column-settings');
        
        // Directly modify column.settings
        collect_element_field_values($columnSettings, columnData.settings);

        $column.find('.builder-chunk').each(function() {
            var chunk = gather_chunk_data(jQuery(this), columnLayout, columnIndex);

            columnData.chunks.push(chunk);
        });

        columns.push(columnData);
    });

    return columns;
}



// Gather data from a single chunk for duplicating
function gather_chunk_data($chunk) {
    var chunk = {
        state: get_element_state($chunk),
        field_type: $chunk.attr('data-chunk-type'),
        editor_mode: 'light',
        settings: {},
        fields: {},
    };

    var $chunkFields = $chunk.find('.chunk-content');
    var $chunkSettings = $chunk.find('.chunk-settings');

    // Check for dark mode on plain text chunks
    if (chunk.field_type === 'text') {
        var editorMode = $chunk.find('.wiz-wysiwyg').attr('data-editor-mode');
        chunk.editor_mode = editorMode ? editorMode : 'light';
    }

    // Determine desktop/mobile visibility
    var chunkDesktopVisibility = $chunk.find('.builder-chunk-header .show-on-desktop').attr('data-show-on-desktop');
    var chunkMobileVisibility = $chunk.find('.builder-chunk-header .show-on-mobile').attr('data-show-on-mobile');

    chunk.settings.desktop_visibility = chunkDesktopVisibility === 'false' ? 'false' : 'true';
    chunk.settings.mobile_visibility = chunkMobileVisibility === 'false' ? 'false' : 'true';

    
    if (chunk.field_type === 'image') {

        // Set image context for exact pixel width on Outlook images
        chunk.settings.image_context = '';

        var columnLayout = $chunk.closest('.builder-columnset').attr('data-layout');
        var columnIndex = $chunk.closest('.builder-column').index();

        if (columnLayout === 'two-col') {
            chunk.settings.image_context = 'two-col';
        } else if (columnLayout === 'three-col') {
            chunk.settings.image_context = 'three-col';
        } else if (columnLayout === 'sidebar-left') {
            if (columnIndex === 0) {
                chunk.settings.image_context = 'sidebar-side';
            } else {
                chunk.settings.image_context = 'sidebar-main';
            }
        } else if (columnLayout === 'sidebar-right') {
            if (columnIndex === 0) {
                chunk.settings.image_context = 'sidebar-main';
            } else {
                chunk.settings.image_context = 'sidebar-side';
            }
        }

        

    }

    // For snippets, save both the value (ID) and the snippet name
    if (chunk.field_type === 'snippet') {
        var snippetName = $chunk.find('select[name="select_snippet"] option:selected').text();
        chunk.fields.snippet_name = snippetName;
    }

    // Directly modify chunk.fields and chunk.settings without reassignment
    collect_element_field_values($chunkFields, chunk.fields);
    collect_element_field_values($chunkSettings, chunk.settings);

    return chunk;
}

function collect_element_field_values($container, target) {
    // Iterate over all inputs within the current container, excluding .CodeMirror and .wiz-html-block
    $container.find('input, select, textarea, .builder-colorpicker, .wiz-wysiwyg').not('.CodeMirror *, .wiz-html-block').each(function() {
        var $field = jQuery(this);
        var value = $field.is('input[type="text"]') ? $field.val().replace(/"/g, '&quot;') : get_wizfield_value($field);
        var key = $field.attr('name');

        // Build the hierarchy of fieldsets for this field
        var fieldsetHierarchy = [];
        $field.parents('fieldset').each(function() {
            var fieldsetName = jQuery(this).attr('name');
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

        // Handle plain text input
        if ($container.is('#plain-text-content')) {
            let sanitizedText = sanitizeTextArea($field.val());
            nestedTarget[key] = sanitizedText;
        }
    });

    // Handle fieldsets with key-value pairs
    $container.find('fieldset[data-save-as="key_value_pairs"]').each(function() {
        var $fieldset = jQuery(this);
        var fieldsetName = $fieldset.attr('name');
        var keyValuePairs = {};

        $fieldset.find('.builder-field-wrapper.flex').each(function() {
            var $wrapper = jQuery(this);
            var $keyInput = $wrapper.find('input[name^="key_"]');
            var $valueInput = $wrapper.find('input[name^="value_"]');
            
            var key = $keyInput.val();
            var value = $valueInput.val();
            
            if (key && value) {
                keyValuePairs[key] = value;
            }
        });

        // Find or create the nested structure in the target based on the fieldset name
        var currentTarget = target;
        var nameParts = fieldsetName.split('.');
        for (var i = 0; i < nameParts.length - 1; i++) {
            currentTarget[nameParts[i]] = currentTarget[nameParts[i]] || {};
            currentTarget = currentTarget[nameParts[i]];
        }
        currentTarget[nameParts[nameParts.length - 1]] = keyValuePairs;
    });

    // Handle .wiz-html-block separately
    $container.find('.wiz-html-block').each(function() {
        var $htmlBlock = jQuery(this);
        var key = $htmlBlock.attr('name');
        var value = get_wizfield_value($htmlBlock);

        if (key) {
            target[key] = value;
        }
    });
}

function get_wizfield_value($field) {
    if ($field.hasClass('builder-colorpicker')) {
        var color = $field.spectrum("get");
        if (color && color._a === 0) {
            return 'transparent';
        } else {
            return color && typeof color.toHexString === 'function' ? color.toHexString() : '';
        }
    } else if ($field.hasClass('wiz-wysiwyg')) {
        var editor = tinymce.get($field.attr('id'));
        if (editor) {
            editor.save();
            return $field.val();
        }
    } else if ($field.is('.wiz-html-block')) {
        var editor = $field.data('CodeMirrorInstance');
        if (editor) {
            return editor.getValue(); // Get the current content of the editor
        }
    } else {
        return $field.val();
    }
}

function update_chunk_data_attr_data($chunk = null) {
    var chunk = $chunk || jQuery('.builder-chunk');
    // Update data-chunk-data attribute for each chunk
    jQuery(chunk).each(function () {
        let chunkData = gather_chunk_data(jQuery(this));
        if (chunkData) {
            jQuery(this).attr('data-chunk-data', JSON.stringify(chunkData));
        }
    });
}


var saveSessionTimeoutId; // Global or higher scope variable for tracking the debounce timeout
function save_template_to_session($templateData = null) {
    // Clear any existing timeout
    clearTimeout(saveSessionTimeoutId);

    // Set a new timeout
    saveSessionTimeoutId = setTimeout(function() {

        save_all_tiny_mces();

        if ($templateData) {
            var templateData = $templateData;
        } else {
            var templateData = gather_template_data();
        }
        var dataWithTimestamp = {
            timestamp: new Date().toISOString(),
            data: templateData
        };

        // Saving the data to sessionStorage
        sessionStorage.setItem('templateData', JSON.stringify(dataWithTimestamp));


    }, 500); // Delay the execution to debounce rapid calls
}


function get_template_from_session() {
    var storedData = sessionStorage.getItem('templateData');
    if (storedData) {
        var parsedData = JSON.parse(storedData);
        var templateData = parsedData.data; 

        return templateData;
    } else {
        // Log or handle the case where no data was found
        //console.log('No template data found in session.');
        return null; // Returning null to indicate no data was found
    }
}