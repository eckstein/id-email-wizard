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

// Function to handle common finalization tasks
function finalize_new_item($element, response) {
    $element.attr('data-chunk-data', JSON.stringify(response.data.chunk_data));
    $element.addClass('newly-added');
    setTimeout(() => $element.removeClass('newly-added'), 3000);
    save_template_to_session();
    update_template_preview();
    sessionStorage.setItem('unsavedChanges', 'true');
}

function apply_gradient_to_picker_label($clicked) {
    var gradientData = $clicked.attr('data-gradientstyles');
    if (gradientData) {
			
        try {
            var gradientObj = JSON.parse(gradientData);
            // Apply the gradient style directly
            $clicked.css('background', gradientObj.style);
            save_template_to_session();
            update_template_preview();
        } catch (e) {
            console.error("Error parsing gradient data: ", e);
        }
    }
};

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

// Utility function to update the gradient preview
function update_gradient_preview($label, $input) {
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


