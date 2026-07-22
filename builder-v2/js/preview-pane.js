
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
    var additionalData = {
        action: "idemailwiz_save_template_session_to_transient",
        security: idAjax_template_editor.nonce,
        templateid: templateId
    };
    
    if (sessionData && !fromDatabase) {
        additionalData.template_data = JSON.stringify(sessionData);
    }

    jQuery.ajax({
        url: idAjax.ajaxurl,
        //url: idAjax.wizAjaxUrl, // Our custom ajax endpont doesn't work here for some reason
        type: "POST",
        data: additionalData,
      success: function (response) {
        if (response.success) {
          // Session data saved successfully

        } else {
          // Session data saving failed, log an error message
          console.error("Failed to save template session data to the transient. Data loss may occur on next refresh.");
          do_wiz_notif({ message: 'Failed to save template session data to the transient.  Data loss may occur on next refresh.', duration: 5000 });          
          
        }

        var iframe = jQuery("#previewFrame")[0];
        var preview = jQuery(iframe.contentWindow.document || iframe.contentDocument);

        let params = {
            isEditor: true,
            partType: 'fullTemplate'
        };
        get_template_part_do_callback(params, function(error, data) {
            if (error) {
                console.error('Error:', error.message);
                return;
            }
            const decodedHTML = decodeHTMLEntities(data.html);

            // Re-capture scroll just before swapping content: the user may have
            // scrolled during the ~1s debounce + AJAX, so the position grabbed at
            // the top of update_template_preview() can be stale.
            currentScrollPosition = {
                x: iframe.contentWindow.pageXOffset,
                y: iframe.contentWindow.pageYOffset
            };

            // Update the preview with the decoded HTML
            preview.find('body').html(decodedHTML);
            jQuery('#templatePreview-status').fadeOut();

            reinitIframeConstants(iframe);

        });
        

        // For initial page load and refreshes
        iframe.onload = function () {
            reinitIframeConstants(iframe);
        };
      },
      error: function () {
        // AJAX request failed, log an error message
        console.error("AJAX request failed for idemailwiz_save_template_session_to_transient");
      }
    });
  }, 1000); // Update debounce

    function reinitIframeConstants(iframe) {
        // Restore scroll to where it was before the refresh. The freshly-injected
        // content loads its images/fonts asynchronously, so on the first pass the
        // document isn't at its final height yet and scrollTo() gets clamped short
        // (the classic "scroll is off until I refresh" symptom). Re-apply after the
        // next paint and again once any still-loading images settle.
        var win = iframe.contentWindow;
        var target = currentScrollPosition;
        var applyScroll = function () { win.scrollTo(target.x, target.y); };

        applyScroll();
        win.requestAnimationFrame(applyScroll);

        var images = win.document.images;
        var pending = 0;
        var onImageSettled = function () {
            this.removeEventListener('load', onImageSettled);
            this.removeEventListener('error', onImageSettled);
            pending--;
            if (pending <= 0) {
                applyScroll();
            }
        };
        for (var i = 0; i < images.length; i++) {
            if (!images[i].complete) {
                pending++;
                images[i].addEventListener('load', onImageSettled);
                images[i].addEventListener('error', onImageSettled);
            }
        }


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

        // Re-setup event handlers
        setupPreviewFrameEventHandlers(iframe)
    }

}





function setupPreviewFrameEventHandlers(iframe) {

//   var iframe = jQuery("#previewFrame")[0];
  var preview = jQuery(iframe.contentWindow.document || iframe.contentDocument);

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
      var thisPreviewColset = thisPreviewRow.find('.columnset[data-columnet-index="'+columnsetIndex+'"]');
      var thisPreviewColumn = thisPreviewColset.find('.column[data-column-index="'+columnIndex+'"]');
      var thisPreviewChunk = thisPreviewColumn.find('.chunk[data-chunk-index="' + chunkIndex + '"]');

      // Add 'last-clicked' class to the clicked chunk
      clickedElement.closest('.builder-chunk').addClass('last-clicked');

      // Add 'active' class to the clicked chunk in the preview pane
      thisPreviewChunk.addClass('active');
    }
  });
  
    preview.on('click', '.chunk', function(event) {
        var clickedElement = jQuery(this);

        if (clickedElement.hasClass('off')) {
            return; // Exit early if in full preview mode
        }

        event.preventDefault();

        // Update active state in preview
        preview.find('.active').removeClass('active');
        clickedElement.addClass('active');

        if (clickedElement.hasClass('id-footer') || clickedElement.hasClass('id-header')) {
            switch_wizard_tab(jQuery('[data-tab="#builder-tab-styles"]'));
        } else if (clickedElement.hasClass('id-fine-print')) {
            switch_wizard_tab(jQuery('[data-tab="#builder-tab-message-settings"]'));
        } else {
            switch_wizard_tab(jQuery('[data-tab="#builder-tab-chunks"]'));     

            // Find corresponding builder elements
            var rowIndex = clickedElement.closest('.row').attr('data-row-index');
            var columnsetIndex = clickedElement.closest('.columnset').attr('data-columnset-index');
            var columnIndex = clickedElement.closest('.column').attr('data-column-index');
            var chunkIndex = clickedElement.attr('data-chunk-index');

            var builderRow = jQuery('#builder .builder-row[data-row-id="' + rowIndex + '"]');
            var builderColumnset = builderRow.find('.builder-columnset[data-columnset-id="' + columnsetIndex + '"]');
            var builderColumn = builderColumnset.find('.builder-column[data-column-id="' + columnIndex + '"]');
            var builderChunk = builderColumn.find('.builder-chunk[data-chunk-id="' + chunkIndex + '"]');

            // Expand the corresponding elements
            toggleBuilderElementVis(builderRow.find('> .builder-row-header'), 'expand', true);
            toggleBuilderElementVis(builderColumnset.find('> .builder-columnset-header'), 'expand', true);
            toggleBuilderElementVis(builderChunk.find('> .builder-chunk-header'), 'expand', true);

            // Sync the builder element with the preview
            syncPreviewElement(builderChunk, true);
        }
    });

}

// Call setupPreviewFrameEventHandlers when the iframe loads
jQuery("#previewFrame").on("load", function() {
    var iframe = jQuery("#previewFrame")[0];
    setupPreviewFrameEventHandlers(iframe);
});


// Export the full template preview as a PNG, entirely client-side (html2canvas).
jQuery(document).on('click', '.export-preview-png', function () {
    var button = jQuery(this);
    if (button.hasClass('disabled')) {
        return;
    }
    export_preview_to_png(button);
});

function export_preview_to_png(button) {
    if (typeof html2canvas === 'undefined') {
        do_wiz_notif({ message: 'Screenshot library failed to load. Please refresh and try again.', duration: 5000 });
        return;
    }

    var iframe = jQuery('#previewFrame')[0];
    var doc = iframe.contentDocument || iframe.contentWindow.document;
    if (!doc || !doc.body) {
        do_wiz_notif({ message: 'Preview is not ready yet. Please wait for it to load.', duration: 5000 });
        return;
    }

    // Full scrollable dimensions of the template.
    var rootEl = doc.documentElement;
    var captureWidth = Math.max(rootEl.scrollWidth, doc.body.scrollWidth);
    var captureHeight = Math.max(rootEl.scrollHeight, doc.body.scrollHeight);

    // Preserve the template's own background; fall back to white where transparent.
    var bodyBg = iframe.contentWindow.getComputedStyle(doc.body).backgroundColor;
    var backgroundColor = (!bodyBg || bodyBg === 'transparent' || bodyBg === 'rgba(0, 0, 0, 0)') ? '#ffffff' : bodyBg;

    button.addClass('disabled fa-spin');
    jQuery('#templatePreview-status').fadeIn().text('Capturing preview...');

    html2canvas(doc.body, {
        backgroundColor: backgroundColor,
        useCORS: true,
        allowTaint: false,
        scale: 1,
        logging: false,
        width: captureWidth,
        height: captureHeight,
        windowWidth: captureWidth,
        windowHeight: captureHeight,
        scrollX: 0,
        scrollY: 0,
        onclone: function (clonedDoc) {
            // Strip editor-only highlights so the shot matches the delivered email.
            clonedDoc.querySelectorAll('.active').forEach(function (el) {
                el.classList.remove('active');
            });
        }
    }).then(function (canvas) {
        canvas.toBlob(function (blob) {
            if (!blob) {
                do_wiz_notif({ message: 'Could not generate the PNG. An external image may have blocked the export.', duration: 6000 });
                cleanup();
                return;
            }
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = get_preview_export_filename();
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            cleanup();
        }, 'image/png');
    }).catch(function (error) {
        console.error('html2canvas export failed:', error);
        do_wiz_notif({ message: 'Screenshot failed. If the template uses external images, they may need to be uploaded to the media library first.', duration: 7000 });
        cleanup();
    });

    function cleanup() {
        button.removeClass('disabled fa-spin');
        jQuery('#templatePreview-status').fadeOut();
    }
}

function get_preview_export_filename() {
    var title = (jQuery('#single-template-title').text() || 'template').trim();
    var postId = jQuery('#templateUI').data('postid') || '';
    // Slugify the title for a safe filename.
    var slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'template';
    return 'preview-' + slug + (postId ? '-' + postId : '') + '.png';
}


