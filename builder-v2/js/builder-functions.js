function expandBuilderElementVis($element, toggledClass) {
    $element.children(toggledClass).slideDown(250, function() {
        $element.addClass('--expanded').removeClass('--collapsed');
    });
    
}

function collapseBuilderElementVis($element, toggledClass) {
    $element.children(toggledClass).slideUp(250, function() {
        $element.addClass('--collapsed').removeClass('--expanded');
    });
    $element.find('.builder-settings-section').slideUp();
}

function toggleBuilderElementVis($header, manualToggle = false) {
    //e.stopPropagation();

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
                }, 100); 
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
    if (manualToggle) {
        if (manualToggle === 'collapse' && $element.hasClass('--expanded')) {
            collapseBuilderElementVis($element, toggleClass);
        } else if (manualToggle === 'expand' && $element.hasClass('--collapsed')) {
            expandBuilderElementVis($element, toggleClass);
        }
    } else {
        if ($element.hasClass('--collapsed')) {
            expandBuilderElementVis($element, toggleClass);
        } else {
            collapseBuilderElementVis($element, toggleClass);
        }
    }
}




// Remove an element from the editor UI
function remove_builder_element(element) {
        let reindexKey = '';
    if (element.hasClass('builder-row')) {
        reindexKey = 'row-id';
    } else if (element.hasClass('builder-columnset')) {
        reindexKey = 'columnset-id';
    } else if (element.hasClass('builder-chunk')) {
        reindexKey = 'chunk-id';
    } 
    element.remove();
    reindexDataAttributes(reindexKey);
    save_template_to_session();
    update_template_preview();
    
    sessionStorage.setItem('unsavedChanges', 'true');
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
    save_template_to_session();
    update_template_preview();
    sessionStorage.setItem('unsavedChanges', 'true');
}



// Toggle Add Chunk layout choice menu
function toggle_chunk_type_choices($clicked) {
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



// Create new builder row
function create_new_builder_row(rowData, $clicked) {
    idemailwiz_do_ajax('create_new_row', idAjax_template_editor.nonce, rowData, 
        function(response) { // Success callback
            if(response.data.html) {
                let $newRow = jQuery(response.data.html).appendTo('.builder-rows-wrapper');
                jQuery('.blank-template-message').hide();
                let $originalRow = $clicked.closest('.builder-row');
                reinitialize_wiz_sortables_for_cloned($originalRow, $newRow);

                init_ui_for_new_chunk($newRow);
                finalize_new_item($newRow, response);
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

    if ($clicked.hasClass('add-row')) {
        const lastRow = jQuery('.builder-rows-wrapper .builder-row').last();
        rowData.row_above = lastRow.length ? lastRow.data('row-id') : false;
    } else if ($clicked.hasClass('duplicate-row')) {
        const rowToDupe = $clicked.closest('.builder-row');
        rowData.row_to_dupe = rowToDupe.length ? rowToDupe.data('row-id') : false;
        rowData.session_data = JSON.stringify(get_template_from_session()); // Ensure this is a string
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

    colSetData.session_data = JSON.stringify(get_template_from_session());

    idemailwiz_do_ajax('create_new_columnset', idAjax_template_editor.nonce, colSetData,
        function(response) {
            if (response.data.html) {
                let $newColumnSet;
                if ($clicked.hasClass('add-columnset')) {
                    $newColumnSet = jQuery(response.data.html).appendTo(row.find('.builder-columnsets'));
                } else if ($clicked.hasClass('duplicate-columnset')) {
                    $newColumnSet = jQuery(response.data.html).insertAfter($colSet);
                }
                init_ui_for_new_chunk($newColumnSet);
                reinitialize_wiz_sortables_for_cloned($colSet, $newColumnSet);
                finalize_new_item($newColumnSet, response);

            }
        },
        function(xhr, status, error) {
            console.error('Error creating or duplicating columnSet:', error);
        }
    );
}

//Toggle column settings
function toggle_column_settings($clicked) {
    var $column = $clicked.closest('.builder-column');
    var $columnSettings = $column.find('.builder-column-settings-row');
    $columnSettings.slideToggle().toggleClass('open');
		
};
function generate_column_layout_choices(currentLayout) {
    var popupHtml = '<div class="column-selection-popup">';
    var layouts = [
        { name: '1 Column', value: 'one-col' },
        { name: '2 Column', value: 'two-col' },
        { name: '3 Column', value: 'three-col' },
        { name: 'Sidebar Left', value: 'sidebar-left' },
        { name: 'Sidebar Right', value: 'sidebar-right' }
    ];
    layouts.forEach(function(layout) {
        var currentLayoutClass = layout.value === currentLayout ? ' current-layout' : '';
        popupHtml += `<div class="column-select-option ${layout.value}${currentLayoutClass}" data-layout="${layout.value}">
                          <div class="layout-icon">
                              ${get_layout_icon_html(layout.value)}
                          </div>
                          ${layout.name}
                      </div>`;
    });
    popupHtml += '</div>';
    return popupHtml;
}

function get_layout_icon_html(layoutValue) {
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

function handle_column_selection($element, selectedLayout) {
    var $columnSet = $element.closest('.builder-columnset');
    var $columns = $columnSet.find('.builder-column');
    

    // Remove the "current-layout" class from all options
    $columnSet.find('.column-select-option').removeClass('current-layout');

    // Add the "current-layout" class to the selected option
    $element.addClass('current-layout');

    // Reset all columns to inactive
    $columns.removeClass('active').addClass('inactive');

    // Reindex columns based on magic wrap
    var magicWrap = $columnSet.attr('data-magic-wrap');
    
    // Reindex columns
    $columns.each(function (index) {
        jQuery(this).attr('data-column-id', index);
        
    });
    

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

    // Check for magic wrap and re-index to accomodate
    // var $activeColumns = $columns.filter('.active');
    // if (magicWrap == 'on') {
    //     var totalColumns = $activeColumns.length;
    //     $activeColumns.each(function (index) {
    //         jQuery(this).attr('data-column-id', totalColumns - index - 1);
    //     });
    // }

    save_template_to_session();
    update_template_preview();
    sessionStorage.setItem('unsavedChanges', 'true');
}

function toggle_magic_wrap($clicked) {
    $clicked.toggleClass('active');
    $colSet = $clicked.closest('.builder-columnset');

    if ($clicked.hasClass('active')) {
        $colSet.attr('data-magic-wrap', 'on');
        $colSet.find('.magic-wrap-indicator').show();
    } else {
        $colSet.attr('data-magic-wrap', 'off');
        $colSet.find('.magic-wrap-indicator').hide();
    }

    //reverse the data-columnset-index attribute
    var $columns = $colSet.find('.builder-column.active');
    var colCount = $columns.length;

    if (colCount == 2) {
        if ($colSet.attr('data-column-id') == 0) {
            $colSet.attr('data-column-id', 1);
        } else {
            $colSet.attr('data-column-id', 0);
        }
    }
    if (colCount == 3) {
        if ($colSet.attr('data-column-id') == 0) {
            $colSet.attr('data-column-id', 2);
        }
        if ($colSet.attr('data-column-id') == 2) {
            $colSet.attr('data-column-id', 0);
        }
    }

    
    
    sessionStorage.setItem('unsavedChanges', 'true');
    save_template_to_session();
    update_template_preview();
};

// Add a new chunk by type
function add_chunk_by_type(chunkType, addChunkTrigger, duplicate = false) {
    var row = addChunkTrigger.closest('.builder-row');
    var column = addChunkTrigger.closest('.builder-column');
    var colSet = addChunkTrigger.closest('.builder-columnset'); 
    var thisChunk = addChunkTrigger.closest('.builder-chunk');
    var chunkId = thisChunk.attr('data-chunk-id');


    var data = {
        post_id: idAjax_template_editor.currentPost.ID,
        row_id: row.attr('data-row-id'),
        colset_index: colSet.attr('data-columnset-id'), 
        column_id: column.attr('data-column-id'),
        chunk_before_id: addChunkTrigger.closest('.builder-chunk').attr('data-chunk-id'),
        chunk_type: chunkType,
        chunk_data: duplicate ? gather_chunk_data(thisChunk) : null,
        duplicate: duplicate,
        session_data: JSON.stringify(get_template_from_session())
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

                init_ui_for_new_chunk(newChunk);
                reinitialize_wiz_sortables_for_cloned(thisChunk, newChunk);
                finalize_new_item(newChunk, response);
            }
        },
        function(xhr, status, error) {
            console.error('Error adding new chunk:', error);
        }
    );
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





function toggle_chunkwrap_settings($checkbox) {
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


function upload_wiz_mock(uploadInput) {
    
    var $uploadInput = jQuery(uploadInput);
    var mockupDisplay = $uploadInput.data('preview');
    var urlInput = $uploadInput.data('url');
    var file = uploadInput.files[0];
    var formData = new FormData();
    formData.append('file', file);
    formData.append('action', 'upload_mockup');

    jQuery.ajax({
        url: idAjax_template_editor.ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            if (response.success) {
                jQuery(mockupDisplay).find('img').attr('src', response.data.url);
                jQuery(urlInput).val(response.data.url);
                $uploadInput.parent('.mockup-uploader').addClass('hidden');
                jQuery(mockupDisplay).removeClass('hidden');
            } else {
                console.error('Error uploading mockup:', response.data);
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX error:', error);
        }
    });
};


function refresh_chunk_html($chunkChild) {
  
    var $chunk = $chunkChild.closest('.builder-chunk');
    
    var $chunkCode = $chunk.find('.chunk-html-code');

    // Gather the chunks data from the dom
    var chunkData = gather_chunk_data($chunk);

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
            var $codeElement = $chunkCode.find('code');
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
            console.error('Error updating chunks HTML code:', xhr, status, error);
        }
    });
 
    //do_wiz_notif({ message: 'Chunk HTML blocks updated', duration: 3000 });
}


function refresh_template_html() {
    var additionalData = {
        action: "generate_template_html_from_ajax",
        template_id: idAjax_template_editor.currentPost.ID,
        session_data: get_template_from_session(),
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
            do_wiz_notif({ message: 'Error updating template HTML code', duration: 3000 });
        }
    });
}


