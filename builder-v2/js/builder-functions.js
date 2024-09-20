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

function toggleBuilderElementVis($header, action = false) {
    
    let $element, toggledClass;
    if ($header.hasClass('builder-row-header')) {
        $element = $header.closest('.builder-row');
        toggledClass = '.builder-row-content';
    } else if ($header.hasClass('builder-columnset-header')) {
        $element = $header.closest('.builder-columnset');
        toggledClass = '.builder-columnset-content';
    } else if ($header.hasClass('builder-chunk-header')) {
        $element = $header.closest('.builder-chunk');
        toggledClass = '.builder-chunk-body';
    } else {
        return; // Not a valid toggle target
    }

    const isRow = $header.hasClass('builder-row-header');
    const isColumnset = $header.hasClass('builder-columnset-header');
    const isChunk = $header.hasClass('builder-chunk-header');
    const autoCollapseRows = jQuery('#builder-tab-settings input[name="auto_collapse_rows"]').is(':checked');
    const autoCollapseColumnsets = jQuery('#builder-tab-settings input[name="auto_collapse_columnsets"]').is(':checked');
    const autoCollapseChunks = jQuery('#builder-tab-settings input[name="auto_collapse_chunks"]').is(':checked');

    // Auto-collapse logic
    if (isRow && autoCollapseRows) {
        jQuery('.builder-row.--expanded').not($element).each(function() {
            collapseBuilderElementVis(jQuery(this), '.builder-row-content');
        });
    } else if (isColumnset && autoCollapseColumnsets) {
        const $rowColumnsets = $element.closest('.builder-row').find('.builder-columnset.--expanded');
        $rowColumnsets.not($element).each(function() {
            collapseBuilderElementVis(jQuery(this), '.builder-columnset-content');
        });
    } else if (isChunk && autoCollapseChunks) {
        const $columnChunks = $element.closest('.builder-column').find('.builder-chunk.--expanded');
        $columnChunks.not($element).each(function() {
            collapseBuilderElementVis(jQuery(this), '.builder-chunk-body');
        });
    }

    // Toggle visibility
    let shouldExpand = action ? action === 'expand' : $element.hasClass('--collapsed');

    if (shouldExpand) {
        expandBuilderElementVis($element, toggledClass);
        // Sync the preview element
        syncPreviewElement($element, shouldExpand);
    } else {
        collapseBuilderElementVis($element, toggledClass);
    }

    // Handle CodeMirror refresh for HTML chunks
    if (isChunk && shouldExpand) {
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
    }

    return shouldExpand;
}

function syncPreviewElement($builderElement, isExpanding) {
    let $previewElement = find_matching_preview_element($builderElement);
    
    if (!$previewElement.length) {
        console.warn('No matching preview element found');
        return;
    }

    let $firstChunk = $previewElement.hasClass('chunk') ? $previewElement : $previewElement.find('.chunk').first();
    
    if (!$firstChunk.length) {
        // If no chunk is found, keep the original $previewElement
        // This will be either an empty row or columnset
        $firstChunk = $previewElement;
    } else {
        // If a chunk is found, set $previewElement to $firstChunk
        $previewElement = $firstChunk;
    }

    // Function to check if element is actually visible
    function isActuallyVisible($el) {
        return $el.is(':visible') && $el.width() > 0;
    }

    // Check current preview mode and visibility
    let currentMode = jQuery('#previewFrame').width() <= 320 ? 'mobile' : 'desktop';
    let isVisible = isActuallyVisible($previewElement);

    if (!isVisible) {
        let newMode = currentMode === 'mobile' ? 'desktop' : 'mobile';
        update_template_device_preview(newMode);
        
        setTimeout(() => {
            isVisible = isActuallyVisible($previewElement);
            if (!isVisible) {
                do_wiz_notif({ message: 'This element is hidden in both mobile and desktop views!', duration: 5000 });
                return;
            }
        }, 300);
    }

    // check that the data-chunk-type data attributes match on the synced elements
        $builderFirstChunk = $builderElement.hasClass('builder-chunk') ? $builderElement : $builderElement.find('.builder-chunk').first();
        if ($firstChunk.data('chunk-type') !== $builderFirstChunk.data('chunk-type')) {
            console.warn('Mismatched data-chunk-type data attributes');
            update_template_preview();
            return;
        }

    setTimeout(function() {
        if ($previewElement.length && isExpanding) {
            $previewElement.closest('body').find('.chunk').removeClass('active');
            if ($firstChunk.hasClass('chunk')) {
                $firstChunk.addClass('active');
            }
        }

        if (isExpanding) {
            scrollPreviewElementIntoView($previewElement);
            scrollBuilderElementIntoView($builderElement);
        }

        // Add 'last-clicked' class to the builder element
        jQuery('.last-clicked').removeClass('last-clicked');
        $builderElement.addClass('last-clicked');
    }, 400);
}

function scrollPreviewElementIntoView($element) {
    if (!$element.length) return;

    const previewFrame = document.getElementById('previewFrame').contentWindow;
    if (!previewFrame || !previewFrame.document.body) {
        console.warn('Preview frame not ready');
        return;
    }

    const elementTop = $element.offset().top;
    const windowHeight = jQuery(previewFrame).height();
    const buffer = 20; // Add a small buffer at the top

    // Scroll to the top of the element with a small buffer
    const scrollTo = Math.max(0, elementTop - buffer);

    wizSmoothScroll(previewFrame.document.body, scrollTo, 500);
}

function scrollBuilderElementIntoView($element) {
    if (!$element.length) return;

    const builder = document.getElementById('builder-pane');
    const elementTop = $element.offset().top;
    const builderScrollTop = builder.scrollTop;
    const builderOffsetTop = builder.getBoundingClientRect().top;
    const elementRelativeTop = elementTop - builderOffsetTop + builderScrollTop;
    const buffer = 20; // Add a small buffer at the top

    // Scroll to the top of the element with a small buffer
    const scrollTo = Math.max(0, elementRelativeTop - buffer);

    wizSmoothScroll(builder, scrollTo, 500);
}


// Handles clicks on the device preview mode buttons
function update_template_device_preview(mode) {
    const $targetFrame = jQuery('#previewFrame');

    if (mode === "mobile") {
        $targetFrame.width('320px'); // Set width for mobile
    } else {
        $targetFrame.width('100%'); // Set width for desktop
    }

    update_template_width_display(); // Update width display
    jQuery(".showDesktopPreview, .showMobilePreview").removeClass("active");
    jQuery(`.show${mode.charAt(0).toUpperCase() + mode.slice(1)}Preview`).addClass("active");
}

// Initialize slider functionality for the preview frame
function initialize_device_width_slider() {
    const $dragger = jQuery('.preview_width_dragger');
    const $targetFrame = jQuery('#previewFrame');
    const $parent = $targetFrame.parent();

    // Initial display of width
    update_template_width_display();

    const handleResize = function() {
        update_template_width_display();
        // Ensure frame doesn't exceed parent width on window resize
        if ($targetFrame.width() > $parent.width()) {
            $targetFrame.width($parent.width());
        }
    };

    const handleMouseMove = function(e) {
        const maxWidth = $parent.width();
        let newWidth = initialWidth + (e.pageX - initialX);
        
        // Limit the new width to the parent's width
        newWidth = Math.min(newWidth, maxWidth);
        
        $targetFrame.width(newWidth);
        update_template_width_display();
    };

    const handleMouseUp = function() {
        jQuery(document).off('mousemove', handleMouseMove);
        jQuery(document).off('mouseup', handleMouseUp);
    };

    const handleMouseDown = function(e) {
        e.preventDefault(); // Prevent default drag behavior
        initialX = e.pageX;
        initialWidth = $targetFrame.width();

        jQuery(document).on('mousemove', handleMouseMove);
        jQuery(document).on('mouseup', handleMouseUp);
    };

    // Attach event listeners
    jQuery(window).on('resize', handleResize);
    $dragger.on('mousedown', handleMouseDown);
}

// Function to update the width display text
function update_template_width_display() {
    const $dragger = jQuery('.preview_width_dragger');
    const $targetFrame = jQuery('#previewFrame');
    $dragger.text($targetFrame.width() + 'px');
}

// Change background mode of editor
function update_preview_pane_background(targetFrameSelector, mode) {
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

    const $changedElement = element;
    let previewElement = find_matching_preview_element($changedElement);
				
    if (previewElement && previewElement.length) {
        previewElement.remove();
        reindexPreviewElements();
    } else {
        update_template_preview();
    }

    element.remove();
    reindexDataAttributes(reindexKey);
    save_template_to_session();

    
    
    sessionStorage.setItem('unsavedChanges', 'true');
}


function reindexPreviewElements() {
    const iframe = jQuery('#previewFrame')[0].contentWindow.document;
    // Setup selectors in the iframe for .row, .columnSet, .column, and .chunk
    const $rows = jQuery(iframe).find('.row');
    // Loop through each row and re-index them by updating data-row-index
    $rows.each(function (index) {
        jQuery(this).attr('data-row-index', index);
        // Loop through .columnSet and then .column, and then .chunk recursively to re-index them
        jQuery(this).find('.columnSet').each(function (index) {
            jQuery(this).attr('data-columnset-index', index);
            jQuery(this).find('.column').each(function (index) {
                jQuery(this).attr('data-column-index', index);
                jQuery(this).find('.chunk').each(function (index) {
                    jQuery(this).attr('data-chunk-index', index);
                });
            });
        });
    });
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


function setupPlainTextEditor() {
    const $editLink = jQuery('.edit-plain-text-link');
    const $editContent = jQuery('#edit-plain-text-content');
    const $plainTextArea = jQuery('#plain-text-content');

    $editLink.on('click', function(e) {
        e.preventDefault();

        $editContent.slideToggle(300, function() {
            const isVisible = jQuery(this).is(':visible');
            
            if (isVisible) {
                $editLink.html('<i class="fa-solid fa-xmark"></i>&nbsp;&nbsp;Hide plain text editor');
                $plainTextArea.focus();
            } else {
                $editLink.html('<i class="fa-solid fa-i-cursor"></i>&nbsp;&nbsp;Edit plain text version');
                $plainTextArea.val('').blur(); // Reset content and remove focus
            }
        });
    });
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

        elementIndex = $builderRow.data('row-id');
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

    update_template_preview_part($clicked);

    sessionStorage.setItem('unsavedChanges', 'true');
}

function find_matching_preview_element($changedElement) {
    const iframe = jQuery('#previewFrame')[0].contentWindow.document;
    let previewElement;

    const selectors = [
        { builderClass: '.builder-row', previewClass: '.row', dataAttr: 'row-id', previewAttr: 'row-index' },
        { builderClass: '.builder-columnset', previewClass: '.columnSet', dataAttr: 'columnset-id', previewAttr: 'columnset-index' },
        { builderClass: '.builder-column', previewClass: '.column', dataAttr: 'column-id', previewAttr: 'column-index' },
        { builderClass: '.builder-chunk', previewClass: '.chunk', dataAttr: 'chunk-id', previewAttr: 'chunk-index' }
    ];

    if(!$changedElement || $changedElement.length === 0) {
        return;
    }
    let selector = '';
    for (let i = 0; i < selectors.length; i++) {
        const $parent = jQuery($changedElement).closest(selectors[i].builderClass);
        if ($parent.length) {
            const dataValue = $parent.data(selectors[i].dataAttr);
            selector += `${i > 0 ? ' ' : ''}${selectors[i].previewClass}[data-${selectors[i].previewAttr}="${dataValue}"]`;
        } else {
            break;
        }
    }

    if (selector) {
        previewElement = jQuery(iframe).find(selector);   
    } else {
        console.log('No matching elements in builder.');
    }

    return previewElement;
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
            '<div class="wiz-tiny-dropdown-options" data-layout="icon-list"><i class="fa-solid fa-list"></i> Icon List</div>' +
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
function create_new_builder_row(rowData, $clicked, duplicate = false) {
    idemailwiz_do_ajax('create_new_row', idAjax_template_editor.nonce, rowData, 
        function(response) { // Success callback
            if(response.data.html) {
                let $newRow;
                if (duplicate) {
                    $newRow = jQuery(response.data.html).insertAfter($clicked.closest('.builder-row'));
                } else {
                    $newRow = jQuery(response.data.html).appendTo('.builder-rows-wrapper');
                }
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
    var duplicate = false;
    if ($clicked.hasClass('add-row')) {
        const lastRow = jQuery('.builder-rows-wrapper .builder-row').last();
        rowData.row_above = lastRow.length ? lastRow.attr('data-row-id') : false;
    } else if ($clicked.hasClass('duplicate-row')) {
        duplicate = true;
        const rowToDupe = $clicked.closest('.builder-row');
        rowData.row_to_dupe = rowToDupe.length ? rowToDupe.attr('data-row-id') : false;
        rowData.session_data = JSON.stringify(get_template_from_session()); // Ensure this is a string
    }

    // Create a new row
    create_new_builder_row(rowData, $clicked, duplicate);
}

// Create or dupe columnset
function create_or_dupe_builder_columnset($clicked) {
    const postId = idAjax_template_editor.currentPost.ID;
    const row = $clicked.closest('.builder-row');
    const $colSet = $clicked.closest('.builder-columnset');
    const rowId = row.attr('data-row-id');

    let colSetIndex;
    let colSetData = {
        post_id: postId,
        row_id: rowId
    };
      if ($clicked.hasClass('add-columnset')) {
          const lastColumnSet = row.find('.builder-columnset').last();
          colSetIndex = lastColumnSet.length ? parseInt(lastColumnSet.attr('data-columnset-id')) + 1 : 0;
          colSetData.colset_index = colSetIndex;
    } else if ($clicked.hasClass('duplicate-columnset')) {
        const colSetToDupe = $clicked.closest('.builder-columnset');
        colSetIndex = parseInt(colSetToDupe.attr('data-columnset-id')) + 1;
        colSetData.colset_index = colSetIndex;
        colSetData.colset_to_dupe = colSetToDupe.attr('data-columnset-id');
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
    var popupHtml = '<div class="builder-actions-popup column-selection-popup exclude-from-toggle">';
    var layouts = [
        { name: '1 Column', value: 'one-col' },
        { name: '2 Column', value: 'two-col' },
        { name: '3 Column', value: 'three-col' },
        { name: 'Sidebar Left', value: 'sidebar-left' },
        { name: 'Sidebar Right', value: 'sidebar-right' }
    ];
    layouts.forEach(function(layout) {
        var currentLayoutClass = layout.value === currentLayout ? ' current-layout' : '';
        popupHtml += `<div class="builder-actions-popup-option column-select-option ${layout.value}${currentLayoutClass}" data-layout="${layout.value}">
                          <div class="layout-icon">
                              ${get_layout_icon_html(layout.value)}
                          </div>
                          ${layout.name}
                      </div>`;
    });
    popupHtml += '</div>';
    return popupHtml;
}

function generate_json_action_choices() {
    var popupHtml = '<div class="builder-actions-popup json-actions-popup">';
    var actions = [
        { name: 'Copy JSON', value: 'copy_json', title: 'Copy the current template JSON to clipboard' },
        { name: 'Export JSON', value: 'export_json', title: 'Export the current template JSON to a file' },
        { name: 'Import JSON', value: 'import_json', title: 'Import JSON data' },
    ];
    actions.forEach(function (action) {
        popupHtml += `<div class="builder-actions-popup-option json-action-option" data-action="${action.value}" title="${action.title}">
                          ${action.name}
                      </div>`;
    });
    popupHtml += '</div>';
    return popupHtml;
}

function handle_wiz_json_action(clicked, action) {
    const $clicked = jQuery(clicked);
    let jsonData;
    var templateId = idAjax.currentPostId;
    var rowIndex = $clicked.closest('.builder-row').attr('data-row-id');
    var columnSetIndex = null;
    var $row = $clicked.closest('.builder-row');
    if ($clicked.closest('.json-actions').attr('data-json-element') === 'row') {
        jsonData = gather_rows_data(rowIndex);
    } else if ($clicked.closest('.json-actions').attr('data-json-element') === 'columnset') {
        columnSetIndex = $clicked.closest('.builder-columnset').attr('data-columnset-id');
        jsonData = gather_columnsets_data(jQuery($row), columnSetIndex);
    } else {
        return;
    }
    
    // validate JSON data
    if (!jsonData || typeof jsonData !== 'object') {
        do_wiz_notif({ message: 'Invalid JSON data gathered', duration: 5000 });
        return;
    }

    try {
        JSON.parse(JSON.stringify(jsonData));
    } catch (error) {
        do_wiz_notif({ message: 'Invalid JSON data gathered: ' + error.message, duration: 5000 });
        return;
    }
    
    switch (action) {
        case 'copy_json':
            copy_json_to_clipboard(jsonData);
            break;
        case 'export_json':
            export_json_to_file(jsonData);
            break;
        case 'import_json':
            show_json_import_modal(templateId, rowIndex, columnSetIndex)
            break;
        default:
            break;
    }
}

function copy_json_to_clipboard(jsonData) {
    	
    returnData = JSON.stringify(jsonData);
    // Copy to clipboard
    navigator.clipboard.writeText(returnData);
    do_wiz_notif({ message: 'JSON copied to clipboard', duration: 5000 });
}

function export_json_to_file(jsonData) {
    var jsonString = JSON.stringify(jsonData);
    var blob = new Blob([jsonString], { type: 'application/json' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = 'template_data.json';
    link.click();
    URL.revokeObjectURL(url);
    do_wiz_notif({ message: 'JSON exported to file', duration: 5000 });
}

function show_json_import_modal(templateId, rowIndex, columnSetIndex) {
    // Show a swal box with an input for the JSON
    Swal.fire({
        title: 'Import JSON',
        html: '<textarea id="json-input" class="swal2-input" placeholder="Enter JSON here"></textarea>',
        showCancelButton: true,
        confirmButtonText: 'Import',
        cancelButtonText: 'Cancel',
        preConfirm: function () {
            var jsonInput = document.getElementById('json-input').value;
            try {
                var jsonData = JSON.parse(jsonInput);
                return jsonData;
            } catch (error) {
                swal({
                    title: 'Error',
                    text: 'Invalid JSON',
                    type: 'error'
                });
                return null;
            }
        }
    }).then(function (result) {
        if (result.value) {
            // Handle the imported JSON data
            handle_json_pattern_import(result.value, templateId, rowIndex, columnSetIndex);
        }
    });
}

function handle_json_pattern_import(jsonData, templateId, rowIndex, columnSetIndex) {
    var $row = jQuery('.builder-row[data-row-id="' + rowIndex + '"]');
    get_wiztemplate_json(templateId, 
        function(json) {
            console.log(json);
            var templateData = json;
            
            // Parse the incoming jsonData
            var importedData = JSON.parse(jsonData);
            
            // Find the correct row in templateData
            if (templateData.rows && templateData.rows[rowIndex]) {
                if (columnSetIndex !== undefined) {
                    // Replace only the specified columnset
                    if (templateData.rows[rowIndex].columnsets && templateData.rows[rowIndex].columnsets[columnSetIndex]) {
                        templateData.rows[rowIndex].columnsets[columnSetIndex] = importedData;
                    } else {
                        console.error('Specified columnset not found');
                        return;
                    }
                } else {
                    // Replace the entire row
                    templateData.rows[rowIndex] = importedData;
                }
                
                // Update the session storage with the modified template data
                sessionStorage.setItem('unsavedChanges', 'true');
                save_template_to_session(JSON.stringify(templateData));
                update_template_preview_part($row);
                
                do_wiz_notif({ message: 'Template updated successfully', duration: 5000 });
                console.log('Template updated successfully');
            } else {
                do_wiz_notif({ message: 'Import Error: Specified row not found', duration: 5000 });
                console.error('Specified row not found');
            }
        },
        function() {
            console.error('Error fetching template JSON');
        }
    );
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
    
    // Reindex columns
    $columns.each(function (index) {
        jQuery(this).attr('data-column-id', index);
        
    });
    

    // Activate columns based on the selected layout
    var mobileWrapToggle = $columnSet.find('.mobile-wrap-toggle');
    var magicWrapToggle = $columnSet.find('.magic-wrap-toggle');
    switch (selectedLayout) {
        case 'one-col':
            $columns.eq(0).removeClass('inactive').addClass('active');
            
            mobileWrapToggle.removeClass('active').addClass('disabled');
            mobile_on_off(mobileWrapToggle, 'off');
            magicWrapToggle.removeClass('active').addClass('disabled');
            break;
        case 'two-col':
            $columns.eq(0).removeClass('inactive').addClass('active');
            $columns.eq(1).removeClass('inactive').addClass('active');
            mobileWrapToggle.removeClass('disabled').addClass('active');
            mobile_on_off(mobileWrapToggle, 'on');
            magicWrapToggle.removeClass('disabled');
            break;
        case 'three-col':
            $columns.eq(0).removeClass('inactive').addClass('active');
            $columns.eq(1).removeClass('inactive').addClass('active');
            $columns.eq(2).removeClass('inactive').addClass('active');
            mobileWrapToggle.removeClass('disabled').addClass('active');
            mobile_on_off(mobileWrapToggle, 'on');
            magicWrapToggle.removeClass('disabled');
            break;
        case 'sidebar-left':
            $columns.eq(0).removeClass('inactive').addClass('active');
            $columns.eq(1).removeClass('inactive').addClass('active');
            mobileWrapToggle.removeClass('disabled').addClass('active');
            mobile_on_off(mobileWrapToggle, 'on');
            magicWrapToggle.removeClass('disabled');
            break;
        case 'sidebar-right':
            $columns.eq(0).removeClass('inactive').addClass('active');
            $columns.eq(1).removeClass('inactive').addClass('active');
            mobileWrapToggle.removeClass('disabled').addClass('active');
            mobile_on_off(mobileWrapToggle, 'on');
            magicWrapToggle.removeClass('disabled');
            break;
    }
    
    function mobile_on_off(mobileWrapToggle, state) {
        mobileWrapToggle.closest('.builder-columnset').attr('data-mobile-wrap', state);
    }

    // Update the data-layout attribute on the columnset
    $columnSet.attr('data-layout', selectedLayout);

    save_template_to_session();    

    colSetIndex = $columnSet.attr('data-columnset-id');

    $row = $columnSet.closest('.builder-row');

    update_template_preview_part($row);


    sessionStorage.setItem('unsavedChanges', 'true');
}

function toggle_magic_wrap($clicked) {
    if ($clicked.hasClass('disabled')) {
        return;
    }

    $colSet = $clicked.closest('.builder-columnset');

    $clicked.toggleClass('active');
    
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

    update_template_preview_part($clicked);
};

function toggle_mobile_wrap($clicked) {
    if ($clicked.hasClass('disabled')) {
        return;
    }

    $colSet = $clicked.closest('.builder-columnset');

     if ($clicked.hasClass('active')) {
        $colSet.attr('data-mobile-wrap', 'off');
        $colSet.find('.magic-wrap-toggle').removeClass('active').addClass('disabled');
    } else {
        $colSet.attr('data-mobile-wrap', 'on');
        $colSet.find('.magic-wrap-toggle').removeClass('disabled');
    }

    $clicked.toggleClass('active');

    if ($clicked.hasClass('active')) {
        $colSet.attr('data-mobile-wrap', 'on');
    } else {
        $colSet.attr('data-mobile-wrap', 'off');
    }    
    
    sessionStorage.setItem('unsavedChanges', 'true');
    save_template_to_session();

    update_template_preview_part($clicked);
};

// Toggle the Frames mode setting
function toggle_frames_mode($clicked) {

    $row = $clicked.closest('.builder-row');

     if ($clicked.hasClass('active')) {
        $row.attr('data-frames-mode', 'false');
        $row.find('.toggle-frames-mode').removeClass('active');
        $row.find('.toggle-frames-mode').attr('data-frames-mode', 'false');
    } else {
        $row.attr('data-frames-mode', 'true');
        $row.find('.toggle-frames-mode').addClass('active');
        $row.find('.toggle-frames-mode').attr('data-frames-mode', 'true');
    }   
    
    sessionStorage.setItem('unsavedChanges', 'true');
    update_template_preview_part($clicked);
}

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

    //console.log('Data: '.data);

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
                    // Set the data-chunk-id attribute to the proper index based on # of existing chunks
                    newChunk.attr('data-chunk-id', column.find('.builder-chunk').length - 1);
                }

                
                if (newChunk.attr('data-chunk-type') === 'html') {
                    html_editor = newChunk.find('.wiz-html-block');
                    init_codemirror_chunk(html_editor)
                }

                init_ui_for_new_chunk(newChunk);
                reinitialize_wiz_sortables_for_cloned(thisChunk, newChunk);
                finalize_new_item(newChunk, response);

                var columnSet = newChunk.closest('.builder-columnset');

                update_template_preview_part(columnSet);
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


// Function to handle common finalization tasks
function finalize_new_item($element, response) {
    $element.attr('data-chunk-data', JSON.stringify(response.data.chunk_data));
    $element.addClass('newly-added');
    setTimeout(() => $element.removeClass('newly-added'), 3000);

    if ($element.hasClass('builder-row')) {
        reindexDataAttributes('row-id');
        update_template_preview_part(jQuery('#builder').find($element), 'newRow');
    } else {
        if ($element.hasClass('builder-columnset')) {
            reindexDataAttributes('columnset-id');
            update_template_preview_part($element.closest('.builder-row'));
        } else if ($element.hasClass('builder-column')) {
            reindexDataAttributes('column-id');
            update_template_preview_part($element.closest('.builder-columnset'));
        } else if ($element.hasClass('builder-chunk')) {
            reindexDataAttributes('chunk-id');
            update_template_preview_part($element.closest('.builder-column'));
        }
        
    }

    save_template_to_session();

    sessionStorage.setItem('unsavedChanges', 'true');
}




function toggle_chunkwrap_settings($checkbox) {
    var shouldHide = !$checkbox.is(':checked');
    var $settingsWrapper = $checkbox.closest('.builder-chunk').find('.chunk-wrap-hide-settings');

    if (shouldHide) {
        $settingsWrapper.removeClass('active'); 
    } else {
        $settingsWrapper.addClass('active');
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
        //url: idAjax_template_editor.ajaxurl,
        url: idAjax.wizAjaxUrl,
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



function add_utm_fieldset_to_dom(index) {
    index = parseFloat(index);
    jQuery.ajax({
        url: idAjax.wizAjaxUrl,
        type: 'POST',
        data: {
            action: 'get_utm_term_fieldset_ajax',
            security: idAjax_template_editor.nonce,
            index: index,
            key: '',
            value: ''
        },
        success: function(response) {
            if (response.success) {
                var fieldsetArea = jQuery('fieldset[name="utm_parameters"]');
                // Remove the "No UTM parameters set" message if it exists
                fieldsetArea.find('.no-utm-message').remove();
                fieldsetArea.append(response.data.fieldsetHtml);
            } else {
                console.error('Error adding UTM parameter:', response.data.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
        }
    });
}



function get_template_part_do_callback(params, callback) {
    var templateData = gather_template_data();

    var defaultParams = {
        action: "get_wiztemplate_part_html",
        templateData: JSON.stringify(templateData),
        isEditor: false,
        templateId: idAjax_template_editor.currentPostId,
        security: idAjax_template_editor.nonce,
        partType: null,
        rowIndex: null,
        columnSetIndex: null,
        columnIndex: null,
        chunkIndex: null
    };

    var additionalData = Object.assign({}, defaultParams, params);

    //fetch(idAjax.ajaxurl, {
    fetch(idAjax.wizAjaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(additionalData).toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            callback(null, data.data);
        } else {
            callback(new Error(data.data.message), null);
            
        }
    })
    .catch(error => {
        callback(new Error('AJAX error: ' + error.message), null);
        
    });
}




function refresh_chunk_html_tab($chunkChild) {
    var $chunk = $chunkChild.closest('.builder-chunk');
    var chunkIndex = $chunk.attr('data-chunk-id');
    var columnIndex = $chunk.closest('.builder-column').attr('data-column-id');
    var columnSetIndex = $chunk.closest('.builder-columnset').attr('data-columnset-id');
    var rowIndex = $chunk.closest('.builder-row').attr('data-row-id');
    
    var params = {
        partType: 'chunk',
        rowIndex: rowIndex,
        columnSetIndex: columnSetIndex,
        columnIndex: columnIndex,
        chunkIndex: chunkIndex,
        isEditor: false,
        templateId: idAjax_builder_functions.currentPostId,
        security: idAjax_template_editor.nonce,
    };

    get_template_part_do_callback(params, function(error, data) {
        if (error) {
            console.error('Error:', error.message);
            return;
        }

        var $chunkCode = $chunk.find('.chunk-html-code');
        var $codeElement = $chunkCode.find('code');
        if ($codeElement.length === 0) {
            $chunkCode.html('<code></code>');
            $codeElement = $chunkCode.find('code');
        }
        
        // Decode the HTML entities
        var decodedHtml = jQuery('<textarea/>').html(data.html).text();
        
        // Beautify the decoded HTML
        var beautifiedHtml = beautify_html(decodedHtml);

        // Set the beautified HTML as text content
        $codeElement.text(beautifiedHtml);
        
        // Apply syntax highlighting
        hljs.highlightElement($codeElement.get(0));
    });
}



function refresh_template_html() {
    jQuery('#copyCode').addClass('disabled');
    jQuery('.builder-code-actions').append('<div id="code-refresh-message"><i class="fas fa-spinner fa-spin"></i> Refreshing HTML, please wait...</div>');

    var params = {
        partType: 'fullTemplate',
        isEditor: false,
        templateId: idAjax_builder_functions.currentPostId,
        security: idAjax_template_editor.nonce,
    };

    get_template_part_do_callback(params, function(error, data) {
        if (error) {
            console.error('Error:', error.message);
            return;
        }

        var $templateCode = jQuery(document).find('#templateCode');
        var $codeElement = $templateCode.find('code');
        if ($codeElement.length === 0) {
            $templateCode.html('<pre><code></code></pre>');
            $codeElement = $templateCode.find('code');
        }
        
        // Directly use the HTML from the response
        var htmlString = data.html;
        
        // Escape HTML entities to display as raw text
        function escapehtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Beautify the HTML (optional, remove if causing issues)
        var beautifiedHtml = beautify_html(htmlString);
        
        // Escape and set the HTML content
        $codeElement.html(escapehtml(beautifiedHtml));
        
        // Apply syntax highlighting
        hljs.highlightElement($codeElement[0]);

        jQuery('#code-refresh-message').remove();
        jQuery('#copyCode').removeClass('disabled');
    });
}

function updateChunkPreviews(baseElement = '#builder') {
    // Define the base element to search within
    var baseElement = jQuery(baseElement);

    // Find all .builder-chunk elements within the base element
    baseElement.find('.builder-chunk').addBack('.builder-chunk').each(function() {
        var chunkElement = jQuery(this);
        var chunkData = chunkElement.data('chunk-data');
        var chunkType = chunkElement.data('chunk-type');

        jQuery.ajax({
            //url: idAjax.ajaxurl,
            url: idAjax.wizAjaxUrl,
            type: 'POST',
            data: {
                action: 'get_chunk_preview',
                chunkData: chunkData,
                chunkType: chunkType,
                security: idAjax_template_editor.nonce
            },
            success: function(response) {
                if (response.success) {
                    chunkElement.find('.builder-chunk-title').html(response.data.html);
                } else {
                    console.error('Error: ', response.data.message);
                }
            }
        });
    });
}

function decodeHTMLEntities(text) {
    var textArea = document.createElement('textarea');
    textArea.innerHTML = text;
    return textArea.value;
}


let templateUpdateQueue = [];
let templateUpdateProcessing = false;


function update_template_preview_part($changedElement, previewElement=null) {
    templateUpdateQueue.push({$changedElement, previewElement});
    processPreviewUpdateQueue();
}

function processPreviewUpdateQueue() {
    if (templateUpdateProcessing || templateUpdateQueue.length === 0) return;

    templateUpdateProcessing = true;
    let {$changedElement, previewElement} = templateUpdateQueue.shift();

    save_template_to_session();

    previewElement = previewElement ? previewElement : find_matching_preview_element($changedElement);


    if (previewElement.length === 0) {
        update_template_preview();
        templateUpdateProcessing = false;
        processPreviewUpdateQueue();
        return;
    }

    let params = {
        isEditor: true,
    };

    // Catch changes within the builder layout elements
    if ($changedElement.closest('.builder-rows-wrapper').length) {
        if ($changedElement.closest('.builder-chunk').length) {
            const $chunk = $changedElement.closest('.builder-chunk');
            params.partType = 'chunk';
            params.rowIndex = $chunk.closest('.builder-row').data('row-id');
            params.columnSetIndex = $chunk.closest('.builder-columnset').data('columnset-id');
            params.columnIndex = $chunk.closest('.builder-column').data('column-id');
            params.chunkIndex = $chunk.data('chunk-id');
        } else if ($changedElement.closest('.builder-column').length) {
            const $column = $changedElement.closest('.builder-column');
            params.partType = 'column';
            params.rowIndex = $column.closest('.builder-row').data('row-id');
            params.columnSetIndex = $column.closest('.builder-columnset').data('columnset-id');
            params.columnIndex = $column.data('column-id');
        } else if ($changedElement.closest('.builder-columnset').length) {
            const $columnset = $changedElement.closest('.builder-columnset');
            params.partType = 'columnset';
            params.rowIndex = $columnset.closest('.builder-row').data('row-id');
            params.columnSetIndex = $columnset.data('columnset-id');
        } else if ($changedElement.closest('.builder-row').length) {
            const $row = $changedElement.closest('.builder-row');
            params.partType = 'row';
            params.rowIndex = $row.data('row-id');
        } else {
            update_template_preview();
            templateUpdateProcessing = false;
            processPreviewUpdateQueue();
            return;
        }
    } else {
        // For fields outside the layout/chunks tab
        params.partType = previewElement;
    }

    get_template_part_do_callback(params, function(error, data) {
        if (error) {
            console.error('Error:', error.message);
            templateUpdateProcessing = false;
            processPreviewUpdateQueue();
            return;
        }
        const decodedHTML = decodeHTMLEntities(data.html);
        const iframe = jQuery('#previewFrame')[0].contentWindow.document;
        // Special handling for a new row
        if (previewElement == 'newRow') {            
            
            const previousRow = iframe.querySelector('.row[data-row-index="' + (parseInt(params.rowIndex) - 1) + '"]');
            if (previousRow) {
                previousRow.insertAdjacentHTML('afterend', decodedHTML);
            } else {
                iframe.querySelector('.builder-rows-wrapper').insertAdjacentHTML('beforeend', decodedHTML);
            }
            // if the changed element has the data-preview-part attribute, we replace the content between the placeholders
        } else if ($changedElement.is("[data-preview-part]")) {
            var previewPart = $changedElement.data('preview-part');
            // Replace everything between the placeholders
            const rangeStart = iframe.querySelector('wizPlaceholder[data-preview-part="'+previewPart+'_start"]');
            const rangeEnd = iframe.querySelector('wizPlaceholder[data-preview-part="'+previewPart+'_end"]');
            if (rangeStart && rangeEnd) {
                
            const range = new Range();
            range.setStartAfter(rangeStart);
            range.setEndBefore(rangeEnd);
            range.deleteContents();
            range.insertNode(document.createRange().createContextualFragment(decodedHTML));

            }
            // Regular updates of chunks, columns, rows, etc.
        } else {
            previewElement.replaceWith(decodedHTML);
        }
        reindexPreviewElements();

        templateUpdateProcessing = false;
        processPreviewUpdateQueue();
    });
}

function load_json_into_template_data(profileId) {
    if (!profileId) {
        console.error('No profile ID provided.');
        do_wiz_notif({ message: 'No profile ID provided!', duration: 5000 });
        return;
    }
    jQuery.ajax({
        url: idAjax.wizAjaxUrl,
        type: 'POST',
        data: {
            action: 'get_template_data_profile_ajax',
            profileId: profileId,
            security: idAjax_template_editor.nonce
        },
        success: function(response) {
            if (response.success && response.data && response.data.templateData) {
                // Remove the WizProfileId and WizProfileName fields
                delete response.data.templateData.WizProfileId;
                delete response.data.templateData.WizProfileName;
                const jsonString = JSON.stringify(response.data.templateData, null, 2);
                jQuery('textarea#templateData').val(jsonString);
            } else {
                console.error('Error: ', response.data ? response.data.message : 'Unknown error');
                do_wiz_notif({ message: response.data ? response.data.message : 'Unknown error occurred', duration: 5000 });
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error: ' + textStatus + ' - ' + errorThrown);
            do_wiz_notif({ message: 'Failed to load template data. Please try again.', duration: 5000 });
        }
    });
}

function analyzeTemplateLinks() {
    toggleOverlay(true);

    var modal = '<div id="link-analysis-modal">' +
                    '<h2 class="link-analysis-progress-title">Analyzing Template Links</h2>' +
                    '<div id="link-analysis-progress"></div>' +
                    '<div id="link-analysis-results" style="display:none;"></div>' +
                '</div>';
    jQuery('body').append(modal);

    var params = {
        partType: 'fullTemplate',
        isEditor: false,
        templateId: idAjax_builder_functions.currentPostId,
        security: idAjax_template_editor.nonce,
    };

    get_template_part_do_callback(params, function(error, data) {
        if (error) {
            console.log('Error retrieving template HTML:', error.message);
            do_wiz_notif({ message: 'Error retrieving template HTML', duration: 3000 });
            closeAnalysisModal();
            return;
        }

        var templateHtml = data.html;
        analyzeLinks(templateHtml);
    });
}

function analyzeLinks(encodedHtml) {
    const html = decodeHTMLEntities(encodedHtml);
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const allLinks = doc.getElementsByTagName('a');
    
    // Create a Set to store unique URLs and a Map for occurrences
    const uniqueUrls = new Set();
    const untestableUrls = [];
    const urlOccurrences = new Map();
    
    // Filter and collect unique URLs and count occurrences
    Array.from(allLinks).forEach(link => {
        const href = link.getAttribute('href');
        if (href && !href.startsWith('javascript:') && !href.startsWith('#') && !href.match(/\{\{.*\}\}/)) {
            uniqueUrls.add(href);
            urlOccurrences.set(href, (urlOccurrences.get(href) || 0) + 1);
        } else {
            // collect untestable url strings
            untestableUrls.push(href);
        }
    });

    const totalLinks = uniqueUrls.size;
    let processedLinks = 0;
    const results = [];

    function checkNextLink() {
        if (processedLinks < totalLinks) {
            const href = Array.from(uniqueUrls)[processedLinks];
            checkLink(href, urlOccurrences.get(href)).then(result => {
                results.push(result);
                processedLinks++;
                updateProgressDisplay(processedLinks, totalLinks);
                checkNextLink();
            });
        } else {
            displayResults(results, allLinks, untestableUrls);
        }
    }

    checkNextLink();
}

function checkLink(url, occurrences) {
    return new Promise((resolve) => {
        jQuery.ajax({
            type: "POST",
            url: idAjax.wizAjaxUrl,
            data: {
                action: "check_link_ajax",
                url: url,
                security: idAjax_template_editor.nonce,
            },
            success: function(response) {
                if (response.success) {
                    resolve({...response.data, occurrences: occurrences});
                } else {
                    resolve({
                        original_url: url,
                        error: response.data.message,
                        http_code: 'Error',
                        load_time: 'N/A',
                        redirected: false,
                        occurrences: occurrences
                    });
                }
            },
            error: function(xhr, status, error) {
                resolve({
                    original_url: url,
                    error: error,
                    http_code: 'Error',
                    load_time: 'N/A',
                    redirected: false,
                    occurrences: occurrences
                });
            }
        });
    });
}

function updateProgressDisplay(current, total) {
    const percentage = Math.round((current / total) * 100);
    jQuery('#link-analysis-progress').html('<div class="link-analysis-progress-message">Processed ' + current + ' of ' + total + ' links</div>' +
        '<div class="link-analysis-progress-bar"><div class="link-analysis-progress" style="width:' + percentage + '%;"></div></div>');
}

function displayResults(results, allLinks, untestableUrls) {
    const uniqueResults = results.reduce((acc, curr) => {
        acc[curr.original_url] = curr;
        return acc;
    }, {});


    let resultsHtml = '<div class="link-analysis-header"><h3>Link Analysis Summary</h3>' +
                      '<div class="link-analysis-counts">Total Links: ' + allLinks.length + '&nbsp;&nbsp;|&nbsp;&nbsp;' +
                      'Unique Links: ' + Object.keys(uniqueResults).length  + '&nbsp;&nbsp;|&nbsp;&nbsp;' +
                      'Untestable: ' + untestableUrls.length  +
                      '</div>' +
        '<div class="link-analysis-last-updated">Last Checked: ' + new Date().toLocaleString() + '</div>' + 
                      '<button class="wiz-button green re-start-link-checker">Re-Run Check</button>' +
                      '<i class="fa fa-solid fa-xmark close-link-analysis"></i>' + 
                      '</div>' +
                      '<div id="link-analysis-tablewrap"><table>' +
                      '<tr>' +
                        '<th>URL</th>' +
                        '<th>Tested URL (simulated UTM parameter)</th>' +
                        '<th class="center">Response</th>' +
                        '<th class="center">Retry<br/>(No UTM)</th>' +
                        '<th class="center">Load Time</th>' +
                        '<th class="center">Redirect</th>' +
                        '<th class="center">UTMs<br/>Retained</th>' +
                        '<th class="center">Used</th>' +
                      '</tr>';

    for (const link of Object.values(uniqueResults)) {
        var responseIcon = '';
        if (link.http_code === 200) {
            responseIcon = '<i class="fa fa-solid fa-circle-check"></i>';
        } else if (link.http_code === 301 || link.http_code === 302) {
            responseIcon = '<i class="fa fa-solid fa-arrow-right"></i>';
        } else if (link.http_code === 404) {
            responseIcon = '<i class="fa fa-solid fa-circle-xmark"></i>';
        } else if (link.http_code === 500) {
            responseIcon = '<i class="fa fa-solid fa-circle-exclamation"></i>';
        } else {
            responseIcon = '<i class="fa fa-solid fa-circle-question"></i>';
        }

        var retryResponseIcon = '';
        if (link.retry_http_code) {
            if (link.retry_http_code === 200) {
                retryResponseIcon = '<i class="fa fa-solid fa-circle-check"></i>';
            } else if (link.retry_http_code === 301 || link.retry_http_code === 302) {
                retryResponseIcon = '<i class="fa fa-solid fa-arrow-right"></i>';
            } else if (link.retry_http_code === 404) {
                retryResponseIcon = '<i class="fa fa-solid fa-circle-xmark"></i>';
            } else if (link.retry_http_code === 500) {
                retryResponseIcon = '<i class="fa fa-solid fa-circle-exclamation"></i>';
            } else {
                responseIcon = '<i class="fa fa-solid fa-circle-question"></i>';
            }
        } else {
            retryResponseIcon = '';
        }
        
        resultsHtml += '<tr>' +
            '<td><div><a href="' + (link.original_url || '') + '" target="_blank">' + (link.original_url || 'N/A') + '</a></div></td>' +
            '<td><div><a href="' + (link.final_url || '') + '" target="_blank">' + (link.final_url || 'N/A') + '</a></div></td>' +
            '<td class="http' + (link.http_code || '000') + '"><div class="center">' + responseIcon + '&nbsp;' + (link.http_code || 'N/A') + '</div></td>' +
            '<td class="http' + (link.retry_http_code || '000') + '"><div class="center">' + retryResponseIcon + '&nbsp;' + (link.retry_http_code || 'N/A') + '</div></td>' +
            '<td><div class="center">' + (link.load_time || 'N/A') + 's</div></td>' +
            '<td><div class="center">' + (link.redirected ? 'Yes' : 'No') + '</div></td>' +
            '<td><div class="center">' + (link.utm_accepted ? 'Yes' : 'No') + '</div></td>' +
            '<td><div class="center">x ' + (link.occurrences || 1) + '</div></td>' +
            '</tr>';
    }

    // Loop through the array of untestable URLs and add to bottom of table
    for (const url of untestableUrls) {
        resultsHtml += '<tr>' +
            '<td><div><a href="' + url + '" target="_blank">' + url + '</a></div></td>' +
            '<td colspan="7"><div class="center">Unable to test</div></td>' +
            '</tr>'
    }    

    resultsHtml += '</table></div>';
    jQuery('#link-analysis-progress').hide();
    jQuery('.link-analysis-progress-title').hide();
    jQuery('#link-analysis-results').html(resultsHtml).show();
}

function closeAnalysisModal() {
    jQuery('#link-analysis-modal').hide();
    toggleOverlay(false);
}



function handle_colorpicker_changes($inputField) {
    var previewPart = $inputField.attr('data-preview-part');
    if (previewPart) {
        update_template_preview_part($inputField, previewPart);
    }
}

function handle_codemirror_changes($textArea) {
    var previewPart = $textArea.attr('data-preview-part');
    if (previewPart) {
        update_template_preview_part($textArea, previewPart);
    } else {
        update_template_preview_part($textArea);
    }
}

function handle_style_field_changes($inputField) {
        var previewPart = $inputField.attr('data-preview-part');
        if (previewPart) {
            update_template_preview_part($inputField, previewPart);
        } else {
            update_template_preview_part($inputField);
        }
    }

function handle_layout_field_changes($clicked) {
    update_chunk_data_attr_data();
    requestAnimationFrame(() => {
        updateChunkPreviews($clicked.closest('.builder-chunk'));
        update_template_preview_part($clicked);
    });
		
}
