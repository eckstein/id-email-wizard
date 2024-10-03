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
    var newTabId = $clickedTab.data('tab');
    var scrollBodyId = $clickedTab.closest('.wizard-tabs').data('scroll-body');
    var $scrollBody = jQuery('#' + scrollBodyId);
    var currentScrollPosition = $scrollBody.scrollTop();
    
    
    // Save scroll position for the currently active tab
    var $currentActiveTab = $clickedTab.closest('.wizard-tabs').find('.wizard-tab.--active');
    if ($currentActiveTab.length && $scrollBody.length) {
        var currentTabId = $currentActiveTab.data('tab');
        sessionStorage.setItem('scrollPosition_' + currentTabId, currentScrollPosition);
    }

    // Remove --active class from all tabs in the same container
    $clickedTab.closest('.wizard-tabs').find('.wizard-tab').removeClass('--active');

    // Add --active class to the clicked tab
    $clickedTab.addClass('--active');

    // Hide all sibling content areas
    jQuery(newTabId).siblings('.wizard-tab-content').removeClass('--active');

    // Show the content area corresponding to the clicked tab
    jQuery(newTabId).addClass('--active');

    if ($scrollBody.length) {
        // Restore scroll position from session storage based on clicked tab, or default to 0
        var restoredScrollPosition = sessionStorage.getItem('scrollPosition_' + newTabId) || 0;
        $scrollBody.scrollTop(parseInt(restoredScrollPosition, 10));
    }
}

// Reindex data attributes in the builder for a given attribute name when elements are moved around added or deleted
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



function toggle_wiz_check_toggle($clicked) {

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



function generate_chunk_image_preview_flyover(src) {
    if (jQuery('.chunk-image-preview').length === 0) {
        jQuery('body').append('<div class="chunk-image-preview" style="position: absolute; display: none; background-color:#fff; padding:3px; border-radius:3px; overflow:hidden;"><img src="" style="max-width: 200px; max-height: 200px;"></div>');
    }
    jQuery('.chunk-image-preview img').attr('src', src);
}

function update_chunk_image_preview_flyover_position(e) {
    jQuery('.chunk-image-preview').css({
        'display': 'block',
        'left': e.pageX + 10, // Offset from cursor
        'top': e.pageY + 10
    });
}

function beautify_html(html) {
    // Beautify the received HTML using HTML-Crush
    const beautifiedHtml = htmlCrush.crush(html, {
        removeHTMLComments: false, // set to 1 to remove all comment except Outlook,
        removeCSSComments: false,
        removeIndentations: false,
        removeLineBreaks: true,
        breakToTheLeftOf: [
            "<p",
            "</p",
            "<div",
            "</div",
            //"<tr",
            "</tr",
            "<td",
            "</td",
            "<html",
            "</html",
            "<head",
            "</head",
            "<meta",
            "<link",
            //"<table",
            //"</table",
            "<script",
            "</script",
            "<!DOCTYPE",
            "<style",
            "</style",
            "<title",
            "<body",
            "@media",
            "<!",
            "/*",
            
            
          ],
    }).result;

    return beautifiedHtml;
}


function wizSmoothScroll(element, to, duration) {
    const start = element.scrollTop;
    const change = to - start;
    let currentTime = 0;
    const increment = 20;

    function animateScroll() {
        currentTime += increment;
        const val = Math.easeInOutQuad(currentTime, start, change, duration);
        element.scrollTop = val;
        if (currentTime < duration) {
            requestAnimationFrame(animateScroll);
        } 
    }

    Math.easeInOutQuad = function(t, b, c, d) {
        t /= d / 2;
        if (t < 1) return c / 2 * t * t + b;
        t--;
        return -c / 2 * (t * (t - 2) - 1) + b;
    };

    requestAnimationFrame(animateScroll);
}

function sanitizeTextArea(input) {
  // Remove HTML tags
  let sanitized = input.replace(/<[^>]*>/g, '');
  
  // Remove script tags and their contents
  sanitized = sanitized.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
  
  // Remove style tags and their contents
  sanitized = sanitized.replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, '');
  
  // Remove potentially dangerous attributes
  sanitized = sanitized.replace(/ on\w+="[^"]*"/g, '');
  
  // Encode special characters
  sanitized = sanitized
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;')
    .replace(/\//g, '&#x2F;');
  
  // Remove any remaining HTML entity references
  sanitized = sanitized.replace(/&[^\s;]+;/g, '');
  
  // Remove non-printable ASCII characters
  sanitized = sanitized.replace(/[^\x20-\x7E]/g, '');
  
  return sanitized.trim();
}