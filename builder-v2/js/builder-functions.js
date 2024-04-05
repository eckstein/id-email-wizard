// Saves the template title when edited
function save_wiz_template_title(templateId, value) {
    jQuery.ajax({
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
};

// Handles clicks on the device preview mode buttons
function update_template_device_preview($clicked, mode) {
    var targetFrameSelector = $clicked.data('frame');
    var $targetFrame = jQuery(targetFrameSelector);

    if (mode === "mobile") {
        $targetFrame.width('320px'); // Set width for mobile
    } else {
        $targetFrame.width('100%'); // Set width for desktop
    }

    update_template_width_display(targetFrameSelector); // Update width display based on current frame
    jQuery(".showDesktopPreview, .showMobilePreview").removeClass("active");
    $clicked.addClass("active");
    //idwiz_updatepreview(); 
}

// Initialize slider functionality for each preview frame
function initialize_device_width_slider() {
    jQuery('.preview_width_dragger').each(function() {
        var targetFrameSelector = jQuery(this).data('frame');
        var $targetFrame = jQuery(targetFrameSelector);
        var initialX;
        var initialWidth;

        // Initial display of width for each frame
        update_template_width_display(targetFrameSelector);

        jQuery(window).resize(function() {
            update_template_width_display(targetFrameSelector);
        });

        jQuery(this).on('mousedown', function (e) {
            e.preventDefault(); // Prevent default drag behavior
            initialX = e.pageX;
            initialWidth = $targetFrame.width();

            jQuery(document).on('mousemove', function (e) {
                var newWidth = initialWidth + (e.pageX - initialX);
                $targetFrame.width(newWidth);
                update_template_width_display(targetFrameSelector);
            });

            jQuery(document).on('mouseup', function () {
                jQuery(document).off('mousemove');
                jQuery(document).off('mouseup');
            });
        });
    });
}

// Function to update the width display text
function update_template_width_display(targetFrameSelector) {
    var $draggers = jQuery(".preview_width_dragger[data-frame='" + targetFrameSelector + "']");
    var $targetFrame = jQuery(targetFrameSelector);
    $draggers.text($targetFrame.width() + 'px');
}

// Change background mode of editor
function updateBackgroundMode(targetFrameSelector, mode) {
    var $targetFrame = jQuery(targetFrameSelector);
    // Clear all mode classes
    $targetFrame.removeClass('light-mode dark-mode trans-mode');

    // Apply the new mode class based on the data-mode attribute
    $targetFrame.addClass(mode + '-mode');

    // Update button states based on the active mode
    jQuery('.editor-bg-mode[data-frame="' + targetFrameSelector + '"]').each(function() {
        var $this = jQuery(this);
        if ($this.data('mode') === mode) {
            $this.addClass('active');
        } else {
            $this.removeClass('active');
        }
    });
}

// Wizard Tab controller
function switch_wizard_tab(clickedTabSelector) {
    var $clickedTab = jQuery(clickedTabSelector);
    var tabId = $clickedTab.data('tab');

    // Remove --active class from all tabs in the same container
    $clickedTab.closest('.wizard-tabs').find('.wizard-tab').removeClass('--active');

    // Add --active class to the clicked tab
    $clickedTab.addClass('--active');

    // Hide all sibling content areas
    jQuery(tabId).siblings('.wizard-tab-content').removeClass('--active');

    // Show the content area corresponding to the clicked tab
    jQuery(tabId).addClass('--active');
}


// Initiate color pickers based on optional selected element
function initColorPickers($optionalElement = null) {
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
            saveTemplateToSession();
            idwiz_updatepreview();
        });

        // Retrieve the existing value from the input's value attribute
        var existingColor = jQuery(this).attr('data-color-value');

        // Set the Spectrum color picker to the existing color
        if (existingColor) {
            jQuery(this).spectrum('set', existingColor);
        }
    });

}


function expandBuilderElementVis($element, toggledClass) {
    $element.children('.collapsed-message').hide().addClass('hide').removeClass('show');
    $element.addClass('--expanded').removeClass('--collapsed');
}

function collapseBuilderElementVis($element, toggledClass) {
    $element.children(toggledClass).slideUp();
    setTimeout(function() {
        $element.children('.collapsed-message').fadeIn().addClass('show').removeClass('hide');
    }, 250);

    setTimeout(function() {
        $element.addClass('--collapsed').removeClass('--expanded');
    }, 500);
}

function toggleBuilderElementVis($header, e) {
    e.stopPropagation();

    let $element, toggleClass;
    if ($header.hasClass('builder-row-header')) {
        $element = $header.closest('.builder-row');
        toggleClass = '.builder-row-content';
    } else if ($header.hasClass('builder-columnset-header')) {
        $element = $header.closest('.builder-columnset');
        toggleClass = '.builder-columnset-content';
    } else if ($header.hasClass('builder-chunk-header')) {
        $element = $header.closest('.builder-chunk');
        toggleClass = '.builder-chunk-body';

        // Make CodeMirror show its content when its chunk is toggled
        var chunkType = $element.attr('data-chunk-type');
        if (chunkType === 'html') {
            var $htmlBlock = $element.find('.wiz-html-block');
            var editor = $htmlBlock.data('CodeMirrorInstance');

            if (editor) {
                setTimeout(function() {
                    editor.refresh();
                }, 100); // Adjust the delay as needed
            }
        }
    } else {
        return; // Not a valid toggle target
    }

    const isRow = $header.hasClass('builder-row-header');
    const isColumnset = $header.hasClass('builder-columnset-header');
    const isChunk = $header.hasClass('builder-chunk-header');
    const autoCollapseRows = jQuery('#builder-tab-settings input[name="auto_collapse_rows"]').is(':checked');
    const autoCollapseColumnsets = jQuery('#builder-tab-settings input[name="auto_collapse_columnsets"]').is(':checked');
    const autoCollapseChunks = jQuery('#builder-tab-settings input[name="auto_collapse_chunks"]').is(':checked');

    // Auto-collapse logic for rows
    if (isRow && autoCollapseRows) {
        jQuery('.builder-row.--expanded').not($element).each(function() {
            collapseBuilderElementVis(jQuery(this), '.builder-row-content');
        });
    }

    // Auto-collapse logic for columnsets within their respective row
    if (isColumnset && autoCollapseColumnsets) {
        const $rowColumnsets = $element.closest('.builder-row').find('.builder-columnset.--expanded');
        $rowColumnsets.not($element).each(function() {
            collapseBuilderElementVis(jQuery(this), '.builder-columnset-content');
        });
    }

    // Auto-collapse logic for chunks within their respective column
    if (isChunk && autoCollapseChunks) {
        const $columnChunks = $element.closest('.builder-column').find('.builder-chunk.--expanded');
        $columnChunks.not($element).each(function() {
            collapseBuilderElementVis(jQuery(this), '.builder-chunk-body');
        });
    }

    // Directly toggling the visibility
    if ($element.hasClass('--collapsed')) {
        expandBuilderElementVis($element, toggleClass);
    } else {
        collapseBuilderElementVis($element, toggleClass);
    }
}

//Toggle column settings
function toggle_column_settings($clicked) {
    var $column = $clicked.closest('.builder-column');
    var $columnSettings = $column.find('.builder-column-settings-row');
    $columnSettings.slideToggle().toggleClass('open');
		
};


// Remove an element from the editor UI
function remove_builder_element(element) {
    element.remove();
    saveTemplateToSession();
    idwiz_updatepreview();
    sessionStorage.setItem('unsavedChanges', 'true');
}


var saveSessionTimeoutId; // Global or higher scope variable for tracking the debounce timeout

function saveTemplateToSession() {
    // Clear any existing timeout to debounce function calls
    clearTimeout(saveSessionTimeoutId);

    // Set a new timeout to delay the execution of session storage saving
    saveSessionTimeoutId = setTimeout(function() {

        saveAllTinyMces();

        var templateData = gatherTemplateData();
        var dataWithTimestamp = {
            timestamp: new Date().toISOString(),
            data: templateData
        };

        // Saving the data to sessionStorage
        sessionStorage.setItem('templateData', JSON.stringify(dataWithTimestamp));

        refresh_template_html();
        refresh_chunks_html();

        // Notification for the save operation
        //do_wiz_notif({message: 'Template data saved to local session', duration: 5000});

        // Uncomment below for debugging purposes
        //console.log('Template data saved to session:', dataWithTimestamp);
    }, 1000); // Delay the execution by 1000ms to debounce rapid calls
}


function getTemplateFromSession() {
    var storedData = sessionStorage.getItem('templateData');
    if (storedData) {
        var parsedData = JSON.parse(storedData);
        var templateData = parsedData.data; // The actual template data

        // Uncomment below for debugging purposes
        //console.log('Retrieved template data from session:', templateData);

        return templateData;
    } else {
        // Log or handle the case where no data was found
        //console.log('No template data found in session.');
        return null; // Returning null to indicate no data was found
    }
}


function saveAllTinyMces($optionalElement = null) {
    var selector = $optionalElement ? $optionalElement.find('.wiz-wysiwyg') : '.wiz-wysiwyg';
    jQuery(selector).each(function() {
        var editorId = jQuery(this).attr('id');
        var editor = tinymce.get(editorId);
        if (editor) {
            // Save the content from the TinyMCE editor back to the textarea
            editor.save();
        }
    });
}


// Saves the template data, as a JSON object, to the database
function saveTemplateData(templateData = false) {
    if (!templateData) {
        var templateData = gatherTemplateData();
    }
    var formData = {
        action: 'save_wiz_template_data',
        security: idAjax_template_editor.nonce,
        post_id: idAjax_template_editor.currentPost.ID,
        template_data: JSON.stringify(templateData) 
    };

    return new Promise(function(resolve, reject) {
        jQuery.ajax({
            url: idAjax_template_editor.ajaxurl,
            type: 'POST',
            data: formData, 
            success: function(response) {
                saveTemplateToSession();
                sessionStorage.setItem('unsavedChanges', 'false');
                idwiz_updatepreview();
                resolve(response.data);
            },
            error: function(xhr, status, error) {
                console.error('Error saving template:', error);
                reject('Error saving template: ' + error);
            }
        });
    });
}

// Goes through the DOM and gathers all the data needed to save to the JSON object
function gatherTemplateData() {
    var templateData = {
        template_name: jQuery('#idwiz_templateTitle').val(),
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

    // Handle Mock-ups

    var desktopMockup = jQuery('#desktop-mockup-url').val();
    var mobileMockup = jQuery('#mobile-mockup-url').val();

    // Check for file value
    if (desktopMockup) {
        templateData.mockups.desktop = desktopMockup;
    }
    if (mobileMockup) {
        templateData.mockups.mobile = mobileMockup;
    }


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

		

    // Collect message settings and styles
    collectFieldValues(jQuery('#builder-tab-message-settings'), templateData.template_options.message_settings);
    collectFieldValues(jQuery('#builder-tab-styles'), templateData.template_options.template_styles);

    // Collect settings
    collectFieldValues(jQuery('#builder-tab-settings'), templateData.template_options.template_settings);
	


    // Collect row data
    jQuery('#builder .builder-row').each(function() {
		
        var row = { 
            columnSets: [],
            state: jQuery(this).hasClass('--expanded') ? 'expanded' : 'collapsed',
            title: jQuery(this).find('.builder-row-title-text').text(),
            background_settings: {}
        };

        // Collect colset settings
            $rowSettings = jQuery(this).find('.builder-row-settings');
        collectFieldValues($rowSettings, row.background_settings);

        // Determine row state first by user settings and then UI
        // If state saving is off, we collapse everything on next page load
        var saveCollapseStates = jQuery('#builder-tab-settings input[name="save_collapse_states"]').is(':checked');
        if (saveCollapseStates) {
            row.state = jQuery(this).hasClass('--expanded') ? 'expanded' : 'collapsed';
        } else {
            row.state = 'collapsed';
        }

        // Determine desktop/mobile visibility
        var rowDesktopVisibility = jQuery(this).find('.builder-row-header .show-on-desktop').attr('data-show-on-desktop');
        var rowMobileVisibility = jQuery(this).find('.builder-row-header .show-on-mobile').attr('data-show-on-mobile');

        row.desktop_visibility = rowDesktopVisibility === 'true' ? 'true' : 'false';
        row.mobile_visibility = rowMobileVisibility === 'true' ? 'true' : 'false';
			

        

        //let columnSets = jQuery(this).find('.builder-columnset');

        jQuery(this).find('.builder-columnset').each(function(index, columnSet) { 
        var columnSetData = {
            columns: [],
            title: jQuery(this).find('.builder-columnset-title-text').text(),
            state: 'expanded',
            layout: jQuery(columnSet).attr('data-layout') ? jQuery(columnSet).attr('data-layout') : 'one-col',
            stacked: jQuery(columnSet).attr('data-column-stacked') === 'stacked' ? 'stacked' : false,
            desktop_visibility: jQuery(columnSet).attr('data-show-on-desktop') === 'true' ? 'true' : 'false',
            mobile_visibility: jQuery(columnSet).attr('data-show-on-mobile') === 'true' ? 'true' : 'false',
            magic_wrap: jQuery(columnSet).attr('data-magic-wrap') == 'on' ? 'on' : 'off',
            background_settings: {}
        };

        // Collect colset settings
            $colsetSettings = jQuery(columnSet).find('.builder-columnset-settings');
        collectFieldValues($colsetSettings, columnSetData.background_settings);

        // Determine colset state first by user settings and then UI
        // If state saving is off, we collapse everything on next page load
        var saveCollapseStates = jQuery('#builder-tab-settings input[name="save_collapse_states"]').is(':checked');
        if (saveCollapseStates) {
            columnSetData.state = jQuery(this).hasClass('--expanded') ? 'expanded' : 'collapsed';
        } else {
            columnSetData.state = 'collapsed';
        }

        let builderColumns = jQuery(this).find('.builder-column');

        var columnLayout = jQuery(columnSet).attr('data-layout') ? jQuery(columnSet).attr('data-layout') : ''

        // If magic wrap is on, we reverse the array of dom elements and save them criss-cross to the builder's columns
        // By doing this, the columns in the builder and on the desktop preview will match order, but the mobile version will still magic wrap properly
        if (columnSetData.magic_wrap == 'on') {
            // Convert the jQuery object to an array and reverse it
            builderColumns = jQuery.makeArray(builderColumns).reverse();
        }

			

        // Use $.each() to iterate over the array since builderColumns is no longer a jQuery object
            jQuery.each(builderColumns, function(columnIndex, column) {
            var $column = jQuery(column);
            var columnActivation = $column.hasClass('active') ? 'active' : 'inactive';
            //var columnState = $column.hasClass('--expanded') ? 'expanded' : 'collapsed';

            //var columnValign = $column.find('.colAlignToggle').text();
				
            var buildColumn = { 
                //state: columnState,
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
                var chunk = processChunk(jQuery(this), columnLayout, columnIndex);

                buildColumn.chunks.push(chunk);
            });




            columnSetData.columns.push(buildColumn);
        });
        
        row.columnSets.push(columnSetData);
    });

        templateData.rows.push(row);
    });

    return templateData;
}


function getFieldValue($field) {
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

function collectFieldValues($container, target) {
    // Iterate over all inputs within the current container, excluding .CodeMirror and .wiz-html-block
    $container.find('input, select, textarea, .builder-colorpicker, .wiz-wysiwyg').not('.CodeMirror *, .wiz-html-block').each(function() {
        var $field = jQuery(this);
        var value = $field.is('input[type="text"]') ? $field.val().replace(/"/g, '&quot;') : getFieldValue($field);
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
    });

    // Handle .wiz-html-block separately
    $container.find('.wiz-html-block').each(function() {
        var $htmlBlock = jQuery(this);
        var key = $htmlBlock.attr('name');
        var value = getFieldValue($htmlBlock);

        if (key) {
            target[key] = value;
        }
    });
}


function processChunk($chunk, columnLayout, columnIndex) {
    var chunk = {
        //id: $chunk.attr('id'),
        state: $chunk.hasClass('--expanded') ? 'expanded' : 'collapsed',
        field_type: $chunk.attr('data-chunk-type'),
        editor_mode: 'light',
        fields: {},
        settings: {},
    };

    var saveCollapseStates = jQuery('#builder-tab-settings input[name="save_collapse_states"]').is(':checked');
    if (saveCollapseStates) {
        chunk.state = jQuery(this).hasClass('--expanded') ? 'expanded' : 'collapsed';
    } else {
        chunk.state = 'collapsed';
    }

    var $chunkFields = $chunk.find('.chunk-content');
    var $chunkSettings = $chunk.find('.chunk-settings');

    // Check for dark mode on chunk
    if (chunk.field_type === 'text') {
        var editorMode = $chunk.find('.wiz-wysiwyg').attr('data-editor-mode');
        chunk.editor_mode = editorMode ? editorMode : 'light';
    }

    // Determine desktop/mobile visibility
    var chunkDesktopVisibility = $chunk.find('.builder-chunk-header .show-on-desktop').attr('data-show-on-desktop');
    var chunkMobileVisibility = $chunk.find('.builder-chunk-header .show-on-mobile').attr('data-show-on-mobile');

    chunk.settings.desktop_visibility = chunkDesktopVisibility === 'false' ? 'false' : 'true';
    chunk.settings.mobile_visibility = chunkMobileVisibility === 'false' ? 'false' : 'true';

    // Set image context for exact pixel width on Outlook images
    if (chunk.field_type === 'image') {
        chunk.settings.image_context = '';

        //console.log('layout: ' + columnLayout + ', index: ' + columnIndex);
    
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
    collectFieldValues($chunkFields, chunk.fields);
    collectFieldValues($chunkSettings, chunk.settings);
    

    return chunk;
}


var previewRefreshTimeoutId;

// Update preview via AJAX, with optional use of session storage data
function idwiz_updatepreview(fromDatabase = false) {
  var iframe = jQuery("#previewFrame")[0];
  var templateId = jQuery("#templateUI").data("postid");
  //saveTemplateToSession();
  clearTimeout(previewRefreshTimeoutId);

  // Get the current scroll position of the iframe
  var currentScrollPosition = {
    x: iframe.contentWindow.pageXOffset,
    y: iframe.contentWindow.pageYOffset
  };

  jQuery('#templatePreview-status').fadeIn().text('Updating preview...');
  previewRefreshTimeoutId = setTimeout(function () {
    var sessionData = getTemplateFromSession();
    var formData = new FormData();
    formData.append("action", "idemailwiz_build_template");
    formData.append("security", idAjax_template_editor.nonce);
    formData.append("templateid", templateId);
    if (sessionData && !fromDatabase) {
      formData.append("template_data", JSON.stringify(sessionData));
    }

    jQuery.ajax({
      url: idAjax.ajaxurl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (previewHtml) {
        // Create a new Blob object with the preview HTML
        var blob = new Blob([previewHtml], { type: 'text/html' });

        // Create a URL for the Blob object
        var url = URL.createObjectURL(blob);

        // Set the iframe's src attribute to the URL
        iframe.src = url;

        // Set the scroll position of the iframe after the new content has loaded
        iframe.onload = function () {
          iframe.contentWindow.scrollTo(currentScrollPosition.x, currentScrollPosition.y);
          jQuery('#templatePreview-status').fadeOut();

          // Revoke the URL to free up memory
          URL.revokeObjectURL(url);
        };
      }
    });
  }, 1000); // Update debounce
}



function findChunkByIndices(rows, rowIndex, colSetIndex, columnIndex, chunkIndex) {
    if (rows && rows.length > rowIndex) {
        var colSets = rows[rowIndex].columnSets;
        if (colSets && colSets.length > colSetIndex) {
            var columns = colSets[colSetIndex].columns;
            if (columns && columns.length > columnIndex) {
                var chunks = columns[columnIndex].chunks;
                if (chunks && chunks.length > chunkIndex) {
                    return chunks[chunkIndex]; // Return the found chunk
                }
            }
        }
    }
    return null; // Return null if no chunk is found
}
function refresh_chunks_html($passedElement = null) {
  var $chunkElements;
  
  if ($passedElement && $passedElement.length) {
    // If an element is passed, find the closest .builder-chunk and its .chunk-html-code
    $chunkElements = $passedElement.closest('.builder-chunk').find('.chunk-html-code');
  } else {
    // If no element is passed, select all .chunk-html-code elements
    $chunkElements = jQuery('.chunk-html-code');
  }
  
  $chunkElements.each(function () {
    var $this = jQuery(this);

    // Retrieve indices from the element's closest parents.
    var rowIndex = parseInt($this.closest('.builder-row').attr('data-row-id'), 10);
    var colSetIndex = parseInt($this.closest('.builder-columnset').attr('data-columnset-id'), 10);
    var columnIndex = parseInt($this.closest('.builder-column').attr('data-column-id'), 10);
    var chunkIndex = parseInt($this.closest('.builder-chunk').attr('data-chunk-id'), 10);

    // Retrieve session data.
    var session_data = getTemplateFromSession();
    var rows = session_data.rows;

    // Use the utility function to find the chunk.
    var chunkData = findChunkByIndices(rows, rowIndex, colSetIndex, columnIndex, chunkIndex);

    // Prepare the data to be sent to the server.
    var additionalData = {
        action: "get_chunk_code",
        chunkData: JSON.stringify(chunkData), 
        security: idAjax_template_editor.nonce
    };
    jQuery.ajax({
        type: "POST",
        url: idAjax.ajaxurl,
        data: additionalData,
        contentType: "application/x-www-form-urlencoded; charset=UTF-8", 
        success: function(data) {
            // Success handler
            var $codeElement = $this.find('code');
            if ($codeElement.length === 0) {
                // If <code> doesn't exist, create it
                $codeElement.html('<code></code>');
                $codeElement = $codeElement.find('code');
            }
            var beautifiedHtml = beautify_html(data);

            // Update the content of the <code> element with the beautified HTML
            $codeElement.html(beautifiedHtml);

            // Reapply syntax highlighting to the new content
            hljs.highlightElement($codeElement.get(0));

            
        },
        error: function(xhr, status, error) {
            // Error handler
            console.error('Error updating HTML code:', xhr, status, error);
        }
    });
  });
    //do_wiz_notif({ message: 'Chunk HTML blocks updated', duration: 3000 });
}




function refresh_template_html() {
    var additionalData = {
        action: "generate_template_html_from_ajax",
        template_id: idAjax_template_editor.currentPost.ID,
        session_data: getTemplateFromSession(),
        security: idAjax_template_editor.nonce
    };

    jQuery.ajax({
        type: "POST",
        url: idAjax.ajaxurl,
        data: additionalData,
        success: function(data) {
            // Handling success
            var $codeElement = jQuery('#templateCode').find('code');
            if ($codeElement.length === 0) {
                // If <code> doesn't exist, create it
                jQuery('#templateCode').html('<code></code>');
                $codeElement = jQuery('#templateCode').find('code');
            }

           var beautifiedHtml = beautify_html(data);

            // Update the content of the <code> element with the beautified HTML
            $codeElement.html(beautifiedHtml);

            // Reapply syntax highlighting to the new content
            hljs.highlightElement($codeElement.get(0));

            //do_wiz_notif({ message: 'HTML code updated', duration: 3000 });
        },
        error: function(xhr, status, error) {
            // Handling error
            console.log('Error retrieving or generating HTML for template');
            console.log(xhr);
            console.log(status);
            console.log(error);
            do_wiz_notif({ message: 'Error updating HTML code', duration: 3000 });
        }
    });
}

function beautify_html(html) {
    // Beautify the received HTML using HTML-Crush
    const beautifiedHtml = htmlCrush.crush(html, {
        removeHTMLComments: 1, // set to 1 to remove all comment except Outlook,
        removeCSSComments: true,
        removeIndentations: true,
        removeLineBreaks: false,
        breakToTheLeftOf: [
            "<div",
            "</div",
            "</td",
            "<html",
            "</html",
            "<head",
            "</head",
            "<meta",
            "<link",
            "<table",
            "<script",
            "</script",
            "<!DOCTYPE",
            "<style",
            "</style",
            "<title",
            "<body",
            "@media",
            "</body",
            "<!--[if",
            "<!--<![endif",
            "<![endif]"
          ],
    }).result;

    return beautifiedHtml;
}

function updateBuilderChunkPreview(chunkType, element) {
    var $element = jQuery(element);
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


// Destroy and re-initialize TinyMCE on each .wiz-wysiwyg element with option element selection
function reinitTinyMCE($optionalElement = null) {
    //console.log('reinitTinyMCE on ' + ($optionalElement ? $optionalElement.attr('class') : 'global'));

    // Determine the correct selector for the operation
    var selector = $optionalElement ? $optionalElement.find('.wiz-wysiwyg') : '.wiz-wysiwyg';

    jQuery(selector).each(function() {
        var editorId = jQuery(this).attr('id');
        var editor = tinymce.get(editorId);
        if (editor) {
            // Save the content from the TinyMCE editor back to the textarea
            editor.save();

            // Properly remove the TinyMCE instance to avoid any residual states
            editor.remove();
        }

        // Clear any TinyMCE-related data attributes that might interfere with reinitialization
        jQuery(this).removeAttr('data-mce-id').removeAttr('data-id');
    });

    // After ensuring all editors within the context are properly reset, reinitialize TinyMCE
    builder_init_tinymce($optionalElement);


}

    function builder_init_tinymce($optionalElement) {
    var selector = $optionalElement ? '#' + $optionalElement.attr('id') + ' .wiz-wysiwyg' : '.wiz-wysiwyg';

    function applyLinkStyles(editor) {
        // var linkColor = jQuery('#template_style_link_color').attr('data-color-value');
        // var linkUnderline = jQuery('#template_styles_underline_links').is(':checked') ? 'underline' : 'none';
        // var linkItalic = jQuery('#template_styles_italic_links').is(':checked') ? 'italic' : 'normal';
        // var linkBold = jQuery('#template_styles_bold_links').is(':checked') ? 'bold' : 'normal';

        // jQuery(editor.getDoc()).find('a.id-textlink').each(function() {
        //     var $link = jQuery(this);
        //     $link.attr('style', 'color: ' + linkColor + '; text-decoration: ' + linkUnderline + '; font-style: ' + linkItalic + '; font-weight: ' + linkBold + ';');
        // });
    }

    function updateEditorContent(editor) {
        saveTemplateToSession();
        idwiz_updatepreview();
        updateBuilderChunkTitle_debounced(editor);
        sessionStorage.setItem('unsavedChanges', 'true');
    }

    

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

                // Get the attached textarea element
                const textarea = editor.getElement();

                if (!isActive) { // If the button was not active, activate dark mode
                    bodyStyle.backgroundColor = '#222222';
                    // Set the data attribute on the textarea element
                    textarea.setAttribute('data-editor-mode', 'dark');
                } else { // If the button was active, revert to light mode
                    bodyStyle.backgroundColor = '#FFFFFF';
                    // Set the data attribute on the textarea element
                    textarea.setAttribute('data-editor-mode', 'light');
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

    tinymce.PluginManager.add('custom_link_handler', function(editor) {
        editor.on('PreProcess', function(e) {
            jQuery(e.node).find('a').each(function() {
                var $link = jQuery(this);
                if (!$link.find('img').length) {
                    $link.addClass('id-textlink');
                    $link.removeAttr('data-mce-style'); // Remove the data-mce-style attribute
                }
            });
            applyLinkStyles(editor);
        });
    });

    tinymce.init({
        selector: selector,
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
        font_size_formats: '.8em .9em 1em 1.1em 1.2em 1.3em 1.4em 1.5em 1.6em 1.7em 1.8em 1.9em 2em 2.5em',
        line_height_formats: '.8em .9em 1em 1.1em 1.2em 1.3em 1.4em 1.5em 1.6em 1.7em 1.8em 1.9em 2em 2.5em',
        elementpath: false,
        menubar: false,
        force_hex_color: 'always',
        extended_valid_elements: 'span[*]',
        valid_children: '+body[style],+span[span]',
        plugins: 'link code lists merge_tags_button theme_switcher custom_link_handler',
        setup: function(editor) {
            
            editor.ui.registry.addButton('uppercase', {
                text: 'aA',
                tooltip: 'Uppercase Style',
                onAction: function() {
                    var content = editor.selection.getContent({ 'format': 'html' });
                    editor.selection.setContent('<span style="text-transform: uppercase;">' + content + '</span>');
                }
            });

            editor.on('init', function() {
                var editorContainer = jQuery(editor.getContainer());
                var $baseColorInput = editorContainer.closest('.builder-chunk').find('input[name="text_base_color"]');
                var baseColor = $baseColorInput.attr('data-color-value');
                editor.getBody().style.color = baseColor;

                jQuery(document).on('change', 'input[name="text_base_color"]', function(e, tinycolor) {
                    var baseColor = tinycolor.toHexString();
                    var editorContainer = jQuery(editor.getContainer());
                    var $parent = jQuery(this).closest('.builder-chunk');
                    if ($parent.has(editorContainer).length) {
                        editor.getBody().style.color = baseColor;
                    }
                });

                jQuery(document).on('change', '#template_style_link_color, #template_link_style_hover_color, #template_styles_underline_links, #template_styles_italic_links, #template_styles_bold_links', function() {
                    applyLinkStyles(editor);
                });

                applyLinkStyles(editor);
            });

            editor.on('input', function() {
                updateEditorContent(editor);
            });

            editor.on('AddUndo', function(e) {
                updateEditorContent(editor);
            });

            editor.on('Undo', function(e) {
                updateEditorContent(editor);
            });

            editor.on('Redo', function(e) {
                updateEditorContent(editor);
            });

            editor.on('SetContent', function(e) {
                jQuery(editor.getBody()).find('a').each(function() {
                    var $link = jQuery(this);
                    if (!$link.find('img').length) {
                        $link.addClass('id-textlink');
                    }
                });
                applyLinkStyles(editor);
            });

            
        },
        formats: {
            bold: [
              { inline: 'span', styles: { fontWeight: 'bold' } },
              { inline: 'strong', remove: 'all' },
              { inline: 'b', remove: 'all' }
            ],
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
        fontsize_formats: "8pt 10pt 12pt 14pt 18pt 24pt 36pt",
    });
}


function updateBuilderChunkTitle(editor) {
    // Get the content from the editor, stripping HTML tags
    let textContent = editor.getContent({ format: 'text' }).trim();

    // Trim the text content to the first 32 characters
    textContent = textContent.substring(0, 32);

    // Find the closest .builder-chunk-title element and update its text
    const editorElement = editor.getElement();
    const builderChunkTitle = jQuery(editorElement).closest('.builder-chunk').find('.builder-chunk-title');
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

function generate_chunk_image_preview_flyover(src) {
    if (jQuery('#chunk-image-preview').length === 0) {
        jQuery('body').append('<div id="chunk-image-preview" style="position: absolute; display: none;"><img src="" style="max-width: 200px; max-height: 200px;"></div>');
    }
    jQuery('#chunk-image-preview img').attr('src', src);
}

function update_chunk_image_preview_flyover_position(e) {
    jQuery('#chunk-image-preview').css({
        'display': 'block',
        'left': e.pageX + 10, // Offset from cursor
        'top': e.pageY + 10
    });
}

function initializeAllSortables() {
    // Call each specific sortable initializer as needed
    initializeRowSortables();
    initializeColumnsetSortables();
    initializeColumnSortables();
    initializeChunkSortables();
}

	

// Initialize sortable for rows, columns, and chunks
function initializeRowSortables(containerId = null) {
    const containerSelector = containerId ? '#' + containerId : '.builder-rows-wrapper';
    const sortableConfig = {
        itemsSelector: '.builder-row',
        handleSelector: '.builder-row-header',
        placeholderClass: 'row-placeholder',
        additionalOptions: {}
    };
    initializeSortables(containerSelector, sortableConfig.itemsSelector, sortableConfig.handleSelector, sortableConfig.placeholderClass, sortableConfig.additionalOptions);
}

// Initialize sortable for rows, columns, and chunks
function initializeColumnsetSortables(containerId = null) {
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
    initializeSortables(containerSelector, sortableConfig.itemsSelector, sortableConfig.handleSelector, sortableConfig.placeholderClass, sortableConfig.additionalOptions);
}

function initializeColumnSortables(containerId = null) {
    const containerSelector = containerId ? '#' + containerId + ' .builder-columnset-columns' : '.builder-columnset-columns';
    const sortableConfig = {
        itemsSelector: '.builder-column',
        handleSelector: '.builder-column-header',
        placeholderClass: 'column-placeholder',
        additionalOptions: { 
            tolerance: 'pointer'
        }
    };
    initializeSortables(containerSelector, sortableConfig.itemsSelector, sortableConfig.handleSelector, sortableConfig.placeholderClass, sortableConfig.additionalOptions);
}

function initializeChunkSortables(containerId = null) {
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
    initializeSortables(containerSelector, sortableConfig.itemsSelector, sortableConfig.handleSelector, sortableConfig.placeholderClass, sortableConfig.additionalOptions);
}


function initializeSortables(containerSelector, itemsSelector, handleSelector, placeholderClass, additionalOptions) {
    var $containers = jQuery(containerSelector);

    $containers.each(function() {
        var $container = jQuery(this);

        // Initialize sortable for each container found
        initializeSortable($container, itemsSelector, handleSelector, placeholderClass, additionalOptions);
    });
}


function initializeSortable($container, itemsSelector, handleSelector, placeholderClass, additionalOptions) {
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
            saveTemplateToSession();
            setTimeout(function() {
                var attributeToReindex = attributeMap[itemsSelector];
                if (attributeToReindex) {
                    reindexDataAttributes(attributeToReindex);
                }
                
                idwiz_updatepreview();
                
                sessionStorage.setItem('unsavedChanges', 'true');
            }, 500);
        }
    }, additionalOptions));
}


// Initialize editable elements
	
function initializeEditable(editableClass, dataAttributeName, $context) {
    // Default to the whole document if no context is provided
    $context = $context || jQuery(document);

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

function toggle_wizard_button_group($clicked) {
    var $checkbox = $clicked.siblings('.wiz-check-toggle').first();
    // Toggle the checkbox's checked property directly
    $checkbox.prop('checked', !$checkbox.prop('checked')).change();

    // Update the visual state based on the checkbox's state
		
    var $icon = $clicked.find('i');
    if ($checkbox.prop('checked')) {
        $icon.removeClass('fa-regular').addClass('fa-solid');
        $clicked.addClass('active');
    } else {
        $icon.removeClass('fa-solid').addClass('fa-regular');
        $clicked.removeClass('active');
    }		
};

// Reinitialize sortables for an element that has been cloned
function reinitializeSortablesForCloned($originalElement, $clonedElement) {
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
				
            initializeAllSortables(); 
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
        let $clonedColumnContainer = $clonedElement.closest('.builder-columnset-columns');
        if ($clonedColumnContainer.length) {
				
            initializeChunkSortables(); // Reinitialize all chunks within the column container
        }
    }
}



// Reindex data attributes for a given attribute name when elements are moved around added or deleted
function reindexDataAttributes(attributeName) {
    // Find all elements with the specified data attribute
        jQuery('[data-' + attributeName + ']').each(function() {
        var $parent = jQuery(this).parent();
        var $siblings = $parent.children('[data-' + attributeName + ']');

        // Reindex each sibling (including the current element)
        $siblings.each(function(index) {
            var displayIndex = index + 1; // For display-friendly numbering
            jQuery(this).attr('data-' + attributeName, index);
            
            // Update display-friendly elements if they exist
            var $displayElement = jQuery(this).find('[data-' + attributeName + '-display]');
            if ($displayElement.length) {
                $displayElement.attr('data-' + attributeName + '-display', displayIndex);
                $displayElement.text(displayIndex); // Update inner text
            }
        });
    });
}

function toggle_magic_wrap($clicked) {
    $clicked.toggleClass('active');
    $colSet = $clicked.closest('.builder-columnset');

    if ($clicked.hasClass('active')) {
        $colSet.attr('data-magic-wrap', 'on');
    } else {
        $colSet.attr('data-magic-wrap', 'off');
    }
    
    
    sessionStorage.setItem('unsavedChanges', 'true');
    saveTemplateToSession();
    idwiz_updatepreview();
};

function toggle_device_visibility($clicked, manualDeviceType = null) {

    var isDesktopToggle = $clicked.hasClass('show-on-desktop');
    var isChunkWrapToggle = $clicked.hasClass('toggle_chunk_wrap');

    if (isChunkWrapToggle) {

        var toggleType = manualDeviceType ? manualDeviceType : 'show-on-desktop';
        var $element = $element.closest('.builder-chunk').find('.'+toggleType);
       
        newState = true;       
        
    } else {
        var $element = $clicked;
        var toggleType = isDesktopToggle ? 'show-on-desktop' : 'show-on-mobile';
        var currentState = $element.attr('data-' + toggleType) !== 'false';
        var newState = !currentState;
        
    }

    // Update the clicked toggle's state and visual indication
    $element.attr('data-' + toggleType, newState.toString()).toggleClass('disabled', !newState);

    var $builderChunk = $element.closest('.builder-chunk');
    var $builderColumnSet = $element.closest('.builder-columnset');
    var $builderRow = $element.closest('.builder-row');

    // Click is on a row setting
    if ($builderRow.length && !$builderColumnSet.length && !$builderChunk.length) {
        // Update all child columnsets and chunks to match both row settings
        var rowDesktopState = $builderRow.find('.show-on-desktop').attr('data-show-on-desktop') === 'true';
        var rowMobileState = $builderRow.find('.show-on-mobile').attr('data-show-on-mobile') === 'true';

        $builderRow.find('.builder-columnset .show-on-desktop')
            .attr('data-show-on-desktop', rowDesktopState.toString())
            .toggleClass('disabled', !rowDesktopState);
        $builderRow.find('.builder-columnset .show-on-mobile')
            .attr('data-show-on-mobile', rowMobileState.toString())
            .toggleClass('disabled', !rowMobileState);

        $builderRow.find('.builder-chunk .show-on-desktop')
            .attr('data-show-on-desktop', rowDesktopState.toString())
            .toggleClass('disabled', !rowDesktopState);
        $builderRow.find('.builder-chunk .show-on-mobile')
            .attr('data-show-on-mobile', rowMobileState.toString())
            .toggleClass('disabled', !rowMobileState);
    }
    // Click is on a columnset setting
    else if ($builderColumnSet.length && !$builderChunk.length) {
        // Update all child chunks to match both columnset settings
        var columnSetDesktopState = $builderColumnSet.find('.show-on-desktop').attr('data-show-on-desktop') === 'true';
        var columnSetMobileState = $builderColumnSet.find('.show-on-mobile').attr('data-show-on-mobile') === 'true';

        $builderColumnSet.find('.builder-chunk .show-on-desktop')
            .attr('data-show-on-desktop', columnSetDesktopState.toString())
            .toggleClass('disabled', !columnSetDesktopState);
        $builderColumnSet.find('.builder-chunk .show-on-mobile')
            .attr('data-show-on-mobile', columnSetMobileState.toString())
            .toggleClass('disabled', !columnSetMobileState);
    }
    // Click is within a chunk
    else if ($builderChunk.length) {
        var $chunkWrapToggle = $builderChunk.find('input[name=chunk_wrap]');
        var $chunkWrap = false;
        if ($chunkWrapToggle.length > 0) {
            $chunkWrap = $chunkWrapToggle.is(':checked');
        }
        if (!$chunkWrap && $builderChunk.attr('chunk-type') === 'html') {
            // Reset the visibility to visible
            $builderChunk.find('.show-on-desktop').attr('data-show-on-desktop', 'true').removeClass('disabled');
            $builderChunk.find('.show-on-mobile').attr('data-show-on-mobile', 'true').removeClass('disabled');

            // Display a SweetAlert2 message
            Swal.fire({
                icon: 'warning',
                title: 'Chunk Visibility',
                text: 'Raw HTML cannot be hidden without a chunk wrapper.',
                confirmButtonText: 'OK'
            });
        }
        
        // Reset row's and columnset's visibility toggles to default (true)
        $builderRow.find('.builder-row-actions .show-on-desktop, .builder-row-actions .show-on-mobile')
            .attr('data-show-on-desktop', 'true').removeClass('disabled')
            .attr('data-show-on-mobile', 'true').removeClass('disabled');
        $builderColumnSet.find('.builder-columnset-actions .show-on-desktop, .builder-columnset-actions .show-on-mobile')
            .attr('data-show-on-desktop', 'true').removeClass('disabled')
            .attr('data-show-on-mobile', 'true').removeClass('disabled');
    }

    // Persist changes and update preview
    saveTemplateToSession();
    idwiz_updatepreview();
    sessionStorage.setItem('unsavedChanges', 'true');
}


function apply_gradient_data_to_color_picker_label($clicked) {
    var gradientData = $clicked.attr('data-gradientstyles');
    if (gradientData) {
			
        try {
            var gradientObj = JSON.parse(gradientData);
            // Apply the gradient style directly
            $clicked.css('background', gradientObj.style);
            saveTemplateToSession();
            idwiz_updatepreview();
        } catch (e) {
            console.error("Error parsing gradient data: ", e);
        }
    }
};

// Initialize background type selection in the background settings modual
function initializeBackgroundTypeSelection($context) {
    $context = $context || jQuery(document);

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

//Initialize chunk tabs 
function initializeChunkTabs($context) {
    $context = $context || jQuery(document);

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

// Toggle Add Chunk layout choice menu
function toggleLayoutChoices($clicked) {
    var addChunkWrapper = $clicked.closest('.add-chunk-wrapper');
    var chunkLayoutChoices = addChunkWrapper.find('.wiz-tiny-dropdown');

    if (chunkLayoutChoices.length === 0) {
        // Create and append layout choices if they don't exist
        var layoutChoicesHtml = '<div class="wiz-tiny-dropdown" style="display:none;">' +
            '<div class="wiz-tiny-dropdown-options" data-layout="text"><i class="fas fa-align-left"></i> Text</div>' +
            '<div class="wiz-tiny-dropdown-options" data-layout="image"><i class="fas fa-image"></i> Image</div>' +
            '<div class="wiz-tiny-dropdown-options" data-layout="button"><i class="fas fa-square"></i> Button</div>' +
            '<div class="wiz-tiny-dropdown-options" data-layout="spacer"><i class="fas fa-arrows-alt-v"></i> Spacer</div>' +
            '<div class="wiz-tiny-dropdown-options" data-layout="html"><i class="fa-solid fa-code"></i> Raw HTML</div>' +
            '<div class="wiz-tiny-dropdown-options" data-layout="snippet"><i class="fa-solid fa-file-code"></i> Snippet</div>' +
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

// Function to handle common initialization tasks
function init_ui_componants($element) {
    initializeEditable('.builder-row-title-text', 'row-id');
    initializeEditable('.builder-column-title-text', 'column-id');
    initializeEditable('.builder-columnset-title-text', 'columnset-id');

    initializeAllSortables();

    initColorPickers($element);
    reinitTinyMCE($element);
    initializeChunkTabs($element);

}


// Function to handle common finalization tasks
function finalize_new_item($element) {
    $element.addClass('newly-added');
    setTimeout(() => $element.removeClass('newly-added'), 3000);
    saveTemplateToSession();
    idwiz_updatepreview();
    sessionStorage.setItem('unsavedChanges', 'true');
}



// Updated create_new_builder_row function
function create_new_builder_row(rowData, $clicked) {
    idemailwiz_do_ajax('create_new_row', idAjax_template_editor.nonce, rowData, 
        function(response) { // Success callback
            if(response.data.html) {
                let $newRow = jQuery(response.data.html).appendTo('.builder-rows-wrapper');
                jQuery('.blank-template-message').hide();
                let $originalRow = $clicked.closest('.builder-row');
                reinitializeSortablesForCloned($originalRow, $newRow);

                init_ui_componants($newRow);
                finalize_new_item($newRow);
            }
        }, 
        function(xhr, status, error) { // Error callback
            console.error('Error adding new row:', error);
        }
    );
}

function create_or_dupe_builder_row($clicked) {
    const userId = idAjax_template_editor.current_user.ID;
    const postId = idAjax_template_editor.currentPost.ID;
    const rowData = {
        post_id: postId,
        user_id: userId
    };

    if ($clicked.hasClass('builder-new-row')) {
        const lastRow = jQuery('.builder-rows-wrapper .builder-row').last();
        rowData.row_above = lastRow.length ? lastRow.data('row-id') : false;
    } else if ($clicked.hasClass('duplicate-row')) {
        const rowToDupe = $clicked.closest('.builder-row');
        rowData.row_to_dupe = rowToDupe.length ? rowToDupe.data('row-id') : false;
        rowData.session_data = JSON.stringify(getTemplateFromSession()); // Ensure this is a string
    }

    // Create a new row
    create_new_builder_row(rowData, $clicked);
}

// Create or dupe columnset
function create_or_dupe_builder_columnset($clicked) {
    const postId = idAjax_template_editor.currentPost.ID;
    const row = $clicked.closest('.builder-row');
    const $colSet = $clicked.closest('.builder-columnset');
    const rowId = row.data('row-id');

    let colSetIndex;
    let colSetData = {
        post_id: postId,
        row_id: rowId
    };

    if ($clicked.hasClass('add-columnset')) {
        const lastColumnSet = row.find('.builder-columnset').last();
        colSetIndex = lastColumnSet.length ? lastColumnSet.data('columnset-id') + 1 : 0;
        colSetData.colset_index = colSetIndex;
    } else if ($clicked.hasClass('duplicate-columnset')) {
        const colSetToDupe = $clicked.closest('.builder-columnset');
        colSetIndex = colSetToDupe.data('columnset-id') + 1;
        colSetData.colset_index = colSetIndex;
        colSetData.colset_to_dupe = colSetToDupe.data('columnset-id');
    }

    colSetData.session_data = JSON.stringify(getTemplateFromSession());

    idemailwiz_do_ajax('create_new_columnset', idAjax_template_editor.nonce, colSetData,
        function(response) {
            if (response.data.html) {
                let $newColumnSet;
                if ($clicked.hasClass('add-columnset')) {
                    $newColumnSet = jQuery(response.data.html).appendTo(row.find('.builder-columnsets'));
                } else if ($clicked.hasClass('duplicate-columnset')) {
                    $newColumnSet = jQuery(response.data.html).insertAfter($colSet);
                }
                init_ui_componants($newColumnSet);
                finalize_new_item($newColumnSet);

            }
        },
        function(xhr, status, error) {
            console.error('Error creating or duplicating columnSet:', error);
        }
    );
}

function generateColumnSelectionPopup(currentLayout) {
    var popupHtml = '<div class="column-selection-popup">';
    var layouts = [
        { name: '1 Column', value: 'one-col' },
        { name: '2 Column', value: 'two-col' },
        { name: '3 Column', value: 'three-col' },
        { name: 'Sidebar Left', value: 'sidebar-left' },
        { name: 'Sidebar Right', value: 'sidebar-right' }
    ];
    layouts.forEach(function(layout) {
        var activeClass = layout.value === currentLayout ? ' active' : '';
        popupHtml += `<div class="column-select-option ${layout.value}${activeClass}" data-layout="${layout.value}">
                          <div class="layout-icon">
                              ${getLayoutIconHtml(layout.value)}
                          </div>
                          ${layout.name}
                      </div>`;
    });
    popupHtml += '</div>';
    return popupHtml;
}

function getLayoutIconHtml(layoutValue) {
    switch (layoutValue) {
        case 'one-col':
            return '<div class="col-layout-visual-wrapper"><div></div></div>';
        case 'two-col':
            return '<div class="col-layout-visual-wrapper"><div></div><div></div></div>';
        case 'three-col':
            return '<div class="col-layout-visual-wrapper"><div></div><div></div><div></div></div>';
        case 'sidebar-left':
            return '<div class="col-layout-visual-wrapper"><div></div><div></div></div>';
        case 'sidebar-right':
            return '<div class="col-layout-visual-wrapper"><div></div><div></div></div>';
        default:
            return '';
    }
}

function handleColumnSelection($element, selectedLayout) {
    var $columnSet = $element.closest('.builder-columnset');
    var $columns = $columnSet.find('.builder-column');

    // Reset all columns to inactive
    $columns.removeClass('active').addClass('inactive');

    // Activate columns based on the selected layout
    switch (selectedLayout) {
        case 'one-col':
            $columns.eq(0).removeClass('inactive').addClass('active');
            break;
        case 'two-col':
            $columns.eq(0).removeClass('inactive').addClass('active');
            $columns.eq(1).removeClass('inactive').addClass('active');
            break;
        case 'three-col':
            $columns.eq(0).removeClass('inactive').addClass('active');
            $columns.eq(1).removeClass('inactive').addClass('active');
            $columns.eq(2).removeClass('inactive').addClass('active');
            break;
        case 'sidebar-left':
            $columns.eq(0).removeClass('inactive').addClass('active');
            $columns.eq(1).removeClass('inactive').addClass('active');
            break;
        case 'sidebar-right':
            $columns.eq(0).removeClass('inactive').addClass('active');
            $columns.eq(1).removeClass('inactive').addClass('active');
            break;
    }

    // Update the data-layout attribute on the columnset
    $columnSet.attr('data-layout', selectedLayout);

    saveTemplateToSession();
    idwiz_updatepreview();
    sessionStorage.setItem('unsavedChanges', 'true');
}

// Add a new chunk by type
function add_chunk_by_type(chunkType, addChunkTrigger, duplicate = false) {
    var row = addChunkTrigger.closest('.builder-row');
    var column = addChunkTrigger.closest('.builder-column');
    var colSet = addChunkTrigger.closest('.builder-columnset'); 
    var thisChunk = addChunkTrigger.closest('.builder-chunk');
    var chunkId = thisChunk.data('chunk-id');


    var data = {
        post_id: idAjax_template_editor.currentPost.ID,
        row_id: row.data('row-id'),
        colset_index: colSet.data('columnset-id'), 
        column_id: column.data('column-id'),
        chunk_before_id: addChunkTrigger.closest('.builder-chunk').data('chunk-id'),
        chunk_type: chunkType,
        duplicate: duplicate,
        session_data: JSON.stringify(getTemplateFromSession())
    };

    idemailwiz_do_ajax('add_new_chunk', idAjax_template_editor.nonce, data,
        function(response) {
                if (response.data.html) {
                var newChunk;
                if (chunkId !== undefined && chunkId !== '') {
                    // Insert after the specified chunk
                    newChunk = jQuery(response.data.html).insertAfter(thisChunk);
                } else {
                    // No specific chunk to add after, insert at the end of the column
                    newChunk = jQuery(response.data.html).appendTo(column.find('.builder-column-chunks-body'));
                }

                
                if (newChunk.attr('data-chunk-type') === 'html') {
                    html_editor = newChunk.find('.wiz-html-block');
                    init_codemirror_chunk(html_editor)
                }

                init_ui_componants(newChunk);
                initializeChunkSortables();
                finalize_new_item(newChunk);
            }
        },
        function(xhr, status, error) {
            console.error('Error adding new chunk:', error);
        }
    );
}


function show_template_preview($clicked) {
    toggleOverlay(true);
    jQuery('body').css('overflow', 'hidden');
    var templateId = $clicked.data("postid");

    var additionalData = {
        template_id: templateId,
        user_id: idAjax_template_editor.current_user.ID,
        session_data: getTemplateFromSession()
    };

    idemailwiz_do_ajax("generate_template_for_preview", idAjax_template_editor.nonce, additionalData, getTemplateSuccess, getTemplateError, "html");

    function getTemplateSuccess(data) {
        //console.log(data);
        // First, parse the JSON if not automatically done by jQuery
        var responseData = typeof data === 'string' ? JSON.parse(data) : data;
    
        // Append the preview pane HTML (which includes the empty iframe) to the body
        jQuery('body').append(responseData.data.previewPaneHtml);
    
        // Populate the iframe with the email template HTML
        populateIframeWithTemplate(responseData.data.emailTemplateHtml);

        // Show the preview pane
        jQuery("#previewPopup").fadeIn();
        jQuery(".previewPopupInnerScroll").scrollTop(0);
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

function close_preview_popup() {
    jQuery("#previewPopup").fadeOut(function () {
        setTimeout(function () {
            jQuery("#previewPopup").remove();
        }, 1000);
    });

    toggleOverlay(false);
    jQuery('body').css('overflow', 'auto');
};

function copy_code_to_clipboard($element) {
    var codeSelector = $element.data('code-in');
    var $codeFrame = jQuery(codeSelector).find('code');
    var textToCopy = $codeFrame.text();

    // Using the Clipboard API to write text
    navigator.clipboard.writeText(textToCopy).then(function() {
        // Success: Update the button to show a confirmation
        var originalText = $element.html();
        $element.html("<i class='fa-solid fa-check'></i>&nbsp;Code copied!");

        setTimeout(() => {
            $element.html(originalText);
        }, 5000);
    }, function(err) {
        // Error: Log or handle the error
        console.error('Could not copy text: ', err);
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

// Utility function to safely escape HTML
function wizEscapeHtml(text) {
    return text.replace(/&/g, "&amp;")
               .replace(/</g, "&lt;")
               .replace(/>/g, "&gt;")
               .replace(/"/g, "&quot;")
               .replace(/'/g, "&#039;");
}

function download_template_json($clicked) {
    var templateId = $clicked.data("post-id");
    getWizTemplateJson(templateId, function(jsonData) {
        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(jsonData, null, 2));
        var downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", "template_data.json");
        document.body.appendChild(downloadAnchorNode); // required for firefox
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    });
};

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
            const isPastedData = jQuery('.swalTabs ul li a.active').attr('data-tab') === 'pasteTab';
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

            jQuery('#jsonFileInput').on('change', function() {
                // Check if any files were selected
                if (this.files && this.files.length > 0) {
                    var file = this.files[0];
                    var fileType = file.type;
                    var match = ['application/json', 'text/json'];

                    // Validate file type
                    if (match.indexOf(fileType) !== -1) {
                        // File is a JSON, update label text to show file name
                        jQuery('.file-upload-label').text(file.name + " is ready to upload.")
                            .css('color', '#28a745'); // Optional: change label color
            
                        jQuery('.swal-file-upload').css({
                            'border-color': '#28a745', // Example: Change border color
                            'background-color': '#e2e6ea' // Lighten background
                        });
                    } else {
                        // File is not a JSON, show error and reset input
                        jQuery('.file-upload-label').text("Invalid file type. Please select a .json file.")
                            .css('color', '#dc3545'); // Optional: change label color for error
            
                        jQuery('.swal-file-upload').css({
                            'border-color': '#dc3545', // Example: Change border color for error
                            'background-color': '#f8d7da' // Light background for error
                        });

                        // Reset the file input for another selection
                        jQuery(this).val('');
                    }
                } else {
                    // No file selected, reset to default state
                    resetUploadField();
                }
            });

            // Function to reset the upload field to its default state
            function resetUploadField() {
                jQuery('.file-upload-label').text("Drag and drop a file here or click to select a file")
                    .css('color', '#007bff');
    
                jQuery('.swal-file-upload').css({
                    'border-color': '#007bff',
                    'background-color': '#f8f9fa'
                });
            }

        }
    });
}


async function process_wiz_template_json_upload(isPastedData) {
    async function processData(data) {
        //try {
            const parsedData = JSON.parse(data);
            // Await the validation result; this will throw an error if validation fails
            //await validateWizTemplateSchema(parsedData);

            // If validation succeeds, proceed with saving the data
            const timestamp = new Date().getTime();
            const sessionKey = `uploadedJsonData_${timestamp}`;
            sessionStorage.setItem(sessionKey, JSON.stringify(parsedData));

            // Update template from JSON
            saveTemplateData(parsedData);
        
            // Return the session key or any other result as needed
            return sessionKey;
        // } catch (error) {
        //     // Handle or rethrow the error as appropriate
        //     console.error(error);
        //     throw error;
        // }
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
    // Check if the main key 'template_options' exists
    if (parsedData.hasOwnProperty('template_options')) {
        // Check for 'message_settings' and 'rows' keys within 'template_options'
        if (parsedData.template_options.hasOwnProperty('message_settings') &&
            parsedData.template_options.hasOwnProperty('rows')) {
            console.log("JSON structure is valid.");
            return true;
        } else {
            console.error("JSON structure does not have the required 'message_settings' and 'rows' keys.");
            return false;
        }
    } else {
        console.error("JSON structure does not have the 'template_options' key.");
        return false;
    }
}


jQuery.fn.insertMergeTag = function(insertText) {
  const inputElement = jQuery(this);
  const currentValue = inputElement.val();

  if (inputElement.data('selectedText')) {
    // Replace the selected text with the merge tag
    const cursorPosition = inputElement.data('cursorPosition');
    const selectedText = inputElement.data('selectedText');
    inputElement.val(currentValue.substring(0, cursorPosition) + insertText + currentValue.substring(cursorPosition + selectedText.length));
  } else {
    // Insert the merge tag at the cursor position
    const cursorPosition = inputElement.data('cursorPosition');
    inputElement.val(currentValue.substring(0, cursorPosition) + insertText + currentValue.substring(cursorPosition));
  }

  // Set focus back to the input element
  inputElement.focus();
  inputElement.prop('selectionStart', inputElement.data('cursorPosition') + insertText.length);
  inputElement.prop('selectionEnd', inputElement.data('cursorPosition') + insertText.length);
};



// Function to update interface colors
function updateInterfaceColors() {
  var templateData = getTemplateFromSession();

  if (templateData && templateData.template_options && templateData.template_options.template_settings && templateData.template_options.template_settings['interface-colors']) {
    var interfaceColors = templateData.template_options.template_settings['interface-colors'];

    // Update row colors
    var rowColor = interfaceColors.row_color;
    if (rowColor) {
      jQuery('.builder-row-content').css('background-color', rowColor);
      var darkerRowColor = darkenColor(rowColor, 50);
      jQuery('.builder-row-header').css('background-color', darkerRowColor);
      var headerTextColor = isColorLight(darkerRowColor) ? '#343434' : '#FFFFFF';
      jQuery('.builder-row-header').css('color', headerTextColor);
      jQuery('.builder-row-title-number').css('border-color', headerTextColor);
    }

    // Update colset colors
    var colsetColor = interfaceColors.colset_color;
    if (colsetColor) {
      jQuery('.builder-columnset').css('background-color', colsetColor);
      var darkerColsetColor = darkenColor(colsetColor, 30);
      jQuery('.builder-columnset-header').css('background-color', darkerColsetColor);
    jQuery('.builder-columnset').css('border-color', darkerColsetColor);
      var headerTextColor = isColorLight(darkerColsetColor) ? '#343434' : '#FFFFFF';
      jQuery('.builder-columnset-header').css('color', headerTextColor);
      jQuery('.builder-columnset-title-number').css('border-color', headerTextColor);
    }

    // Update column colors
    var columnColor = interfaceColors.column_color;
    if (columnColor) {
      jQuery('.builder-column').css('background-color', columnColor);
      //var darkerColumnColor = darkenColor(columnColor, 30);
      //jQuery('.builder-column-header.builder-header').css('background-color', darkerColumnColor);
      var headerTextColor = isColorLight(columnColor) ? '#343434' : '#FFFFFF';
      jQuery('.builder-column-header.builder-header').css('color', headerTextColor);
      jQuery('.builder-column-title-number').css('border-color', headerTextColor);
    }

    // Update chunk colors
    var chunkColor = interfaceColors.chunk_color;
    if (chunkColor) {
      jQuery('.builder-chunk-body').css('background-color', chunkColor);
      var darkerChunkColor = darkenColor(chunkColor, 50);
      jQuery('.builder-chunk-header').css('background-color', darkerChunkColor);
      var headerTextColor = isColorLight(darkerChunkColor) ? '#343434' : '#FFFFFF';
      jQuery('.builder-chunk-header').css('color', headerTextColor);
    }
  }
}

// Function to darken a color
function darkenColor(color, amount) {
  const hex = color.replace('#', '');
  const r = parseInt(hex.substr(0, 2), 16);
  const g = parseInt(hex.substr(2, 2), 16);
  const b = parseInt(hex.substr(4, 2), 16);
  const newR = Math.max(0, r - amount);
  const newG = Math.max(0, g - amount);
  const newB = Math.max(0, b - amount);
  const newColor = '#' + ((1 << 24) + (newR << 16) + (newG << 8) + newB).toString(16).slice(1);
  return newColor;
}

// Function to determine if a color is light or dark
function isColorLight(color) {
  const hex = color.replace('#', '');
  const r = parseInt(hex.substr(0, 2), 16);
  const g = parseInt(hex.substr(2, 2), 16);
  const b = parseInt(hex.substr(4, 2), 16);
  const brightness = ((r * 299) + (g * 587) + (b * 114)) / 1000;
  return brightness > 150;
}

function toggleChunkWrapSettings($checkbox) {
    var shouldHide = !$checkbox.is(':checked');
    var $settingsWrapper = $checkbox.closest('.builder-chunk').find('.chunk-wrap-hide-settings');

    if (shouldHide) {
        $settingsWrapper.css('display', 'none'); // Hide the settings
        $settingsWrapper.attr('data-chunk-wrap-hide', 'true');
    } else {
        $settingsWrapper.css('display', 'flex'); // Show the settings
        $settingsWrapper.attr('data-chunk-wrap-hide', 'false');
    }
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
    jQuery('#save-template').on('click', validateStyleCode);

    function validateStyleCode() {
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

function init_codemirror_in_app(textarea, editor) {
    // Store the CodeMirror instance as data on the textarea element
    jQuery(textarea).data('CodeMirrorInstance', editor);

    // Force the editor to update its content
    editor.setValue(editor.getValue());

    // Introduce a short delay before refreshing the editor
    setTimeout(function() {
        // Refresh the editor to ensure proper rendering
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
        saveTemplateToSession();
        idwiz_updatepreview();
    }, 500); // Adjust the debounce delay (in milliseconds) as needed
        
    editor.on('change', debouncedUpdate);
}