
var previewRefreshTimeoutId;

function update_template_preview(fromDatabase = false) {
  var iframe = jQuery("#previewFrame")[0];
  var templateId = jQuery("#templateUI").data("postid");
  clearTimeout(previewRefreshTimeoutId);

  // Get the current scroll position of the iframe
  var currentScrollPosition = {
    x: iframe.contentWindow.pageXOffset,
    y: iframe.contentWindow.pageYOffset
  };

  jQuery('#templatePreview-status').fadeIn().text('Updating preview...');

  previewRefreshTimeoutId = setTimeout(function () {
    var sessionData = get_template_from_session();
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
      success: function (response) {
        if (response.success) {
          // Session data saved successfully
        var url = idAjax.site_url + '/build-template-v2/' +templateId;
          url += "?cache_bust=" + new Date().getTime();
          iframe.src = url;

        } else {
          // Session data saving failed, log an error message
          console.error("Failed to save template data to the session.");
          do_wiz_notif({ message: 'Failed to save template data to the session.', duration: 5000 });
          
          // Update the iframe with the current template data
          var url = idAjax.site_url + '/build-template-v2/' +templateId;
          url += "?cache_bust=" + new Date().getTime();
          iframe.src = url;
          
        }

        // Set the scroll position of the iframe after the new content has loaded
            iframe.onload = function () {
            // Sets scroll position back to where it was before refreshing
            iframe.contentWindow.scrollTo(currentScrollPosition.x, currentScrollPosition.y);
            jQuery('#templatePreview-status').fadeOut();

            // Re-apply active class
            var activeBuilderElement = jQuery('#builder .last-clicked');

            // Builder element indexes        
            var rowIndex = activeBuilderElement.closest('.builder-row').attr('data-row-id');
            var columnsetIndex = activeBuilderElement.closest('.builder-columnset').attr('data-columnset-id');
            var columnIndex = activeBuilderElement.closest('.builder-column').attr('data-column-id');
            var chunkIndex = activeBuilderElement.attr('data-chunk-id');

            // Preview pane indexes
            var preview = jQuery(iframe.contentWindow.document);
            var thisPreviewRow = preview.find('.row[data-row-index="'+rowIndex+'"]');
            var thisPreviewColset = thisPreviewRow.find('.columnset[data-columnset-index="'+columnsetIndex+'"]');
            var thisPreviewColumn = thisPreviewColset.find('.column[data-column-index="'+columnIndex+'"]');
            var thisPreviewChunk = thisPreviewColumn.find('.chunk[data-chunk-index="' + chunkIndex + '"]');

            // Add active class to the preview pane
            thisPreviewChunk.addClass('active');

          };
      },
      error: function () {
        // AJAX request failed, log an error message
        console.error("AJAX request failed.");
      }
    });
  }, 1000); // Update debounce
}

function setupPreviewFrameEventHandlers() {

  var iframe = jQuery("#previewFrame")[0];
  var preview = jQuery(iframe.contentWindow.document || iframe.contentDocument);
  var previewScroll = jQuery('#templatePreview');

  var builder = jQuery('#builder');
  var builderScroll = jQuery('#builder-pane');

  // Handle clicks on builder chunks
  jQuery(document).on('click', '#builder .builder-chunk', function(event) {
    
    var clickedElement = jQuery(this);

    // Remove 'active' class from all chunks in the preview pane
    preview.find('.active').removeClass('active');

    // Remove 'last-clicked' class from any builder elements
    jQuery('#builder .last-clicked').removeClass('last-clicked');

    // If the clicked element is not a header with the expanded class (indicating we're collapsing it with this click)
    // or if the clicked element is a header with the collapsed class (indicating we're expanding it with this click)
    if ((!clickedElement.hasClass('builder-chunk-header') 
      && !clickedElement.closest('.builder-chunk').hasClass('--expanded')) 
      || clickedElement.closest('.builder-chunk').hasClass('--collapsed')) {

      // Builder element indexes        
      var rowIndex = clickedElement.closest('.builder-row').attr('data-row-id');
      var columnsetIndex = clickedElement.closest('.builder-columnset').attr('data-columnset-id');
      var columnIndex = clickedElement.closest('.builder-column').attr('data-column-id');
      var chunkIndex = clickedElement.closest('.builder-chunk').attr('data-chunk-id');

      // Preview pane indexes
      var thisPreviewRow = preview.find('.row[data-row-index="'+rowIndex+'"]');
      var thisPreviewColset = thisPreviewRow.find('.columnset[data-columnset-index="'+columnsetIndex+'"]');
      var thisPreviewColumn = thisPreviewColset.find('.column[data-column-index="'+columnIndex+'"]');
      var thisPreviewChunk = thisPreviewColumn.find('.chunk[data-chunk-index="' + chunkIndex + '"]');

      // Add 'last-clicked' class to the clicked chunk
      clickedElement.closest('.builder-chunk').addClass('last-clicked');

      // Add 'active' class to the clicked chunk in the preview pane
      thisPreviewChunk.addClass('active');
    }
  });
  
  preview.on('click', function(e) {
    e.preventDefault();
  });
  preview.on('click', '.chunk', function(event) {
    var clickedElement = jQuery(this);

    // Move to the chunks tab if not on it
    switch_wizard_tab(jQuery('[data-tab=#builder-tab-chunks]'));
    
    preview.find('.active').removeClass('active');
    clickedElement.addClass('active');

    // Get the indexes of the clicked element and it's related elements
    var rowIndex = clickedElement.closest('.row').attr('data-row-index');
    var builderRow = jQuery('#builder .builder-row[data-row-id="' + rowIndex + '"]');

    var columnsetIndex = clickedElement.closest('.columnset').attr('data-columnset-index');
    var builderColumnset = builderRow.find('.builder-columnset[data-columnset-id="' + columnsetIndex + '"]');

    var columnIndex = clickedElement.closest('.column').attr('data-column-index');

    var builderColumn = builderColumnset.find('.builder-column[data-column-id="' + columnIndex + '"]');   

    var chunkIndex = clickedElement.attr('data-chunk-index');
    var builderChunk = builderColumn.find('.builder-chunk[data-chunk-id="' + chunkIndex + '"]');

    var $builderChunkHeader = builderChunk.find('.builder-chunk-header');
    var $builderColumnSetHeader = builderColumnset.find('.builder-columnset-header');
    var $builderRowHeader = builderRow.find('.builder-row-header');

    // Add or remove the 'last-clicked' class 
    
      jQuery('.last-clicked').removeClass('last-clicked');
      builderChunk.addClass('last-clicked');

      toggleBuilderElementVis($builderChunkHeader, 'expand');
      toggleBuilderElementVis($builderColumnSetHeader, 'expand');
      toggleBuilderElementVis($builderRowHeader, 'expand');
   
  });

}

// Call setupPreviewFrameEventHandlers when the iframe loads
jQuery("#previewFrame").on("load", function() {
  setupPreviewFrameEventHandlers();
});

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
}

// Initialize slider functionality for each preview frame
function initialize_device_width_slider() {
    const $previewWidthDraggers = jQuery('.preview_width_dragger');

    $previewWidthDraggers.each(function() {
        const $dragger = jQuery(this);
        const targetFrameSelector = $dragger.data('frame');
        const $targetFrame = jQuery(targetFrameSelector);

        // Initial display of width for each frame
        update_template_width_display(targetFrameSelector);

        const handleResize = function() {
            update_template_width_display(targetFrameSelector);
        };

        const handleMouseMove = function(e) {
            const newWidth = initialWidth + (e.pageX - initialX);
            $targetFrame.width(newWidth);
            update_template_width_display(targetFrameSelector);
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
    });
}
// Function to update the width display text
function update_template_width_display(targetFrameSelector) {
    var $draggers = jQuery(".preview_width_dragger[data-frame='" + targetFrameSelector + "']");
    var $targetFrame = jQuery(targetFrameSelector);
    $draggers.text($targetFrame.width() + 'px');
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


function show_template_preview($clicked) {
    toggleOverlay(true);
    jQuery('body').css('overflow', 'hidden');
    var templateId = $clicked.data("postid");

    var additionalData = {
        template_id: templateId,
        user_id: idAjax_template_editor.current_user.ID,
        session_data: get_template_from_session()
    };

    idemailwiz_do_ajax("generate_template_for_popup", idAjax_template_editor.nonce, additionalData, getTemplateSuccess, getTemplateError, "html");

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