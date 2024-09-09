
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

        // Update the iframe which will pull the new transient data
        //   var url = idAjax.site_url + '/build-template-v2/' +templateId;
        //   url += "?cache_bust=" + new Date().getTime();
        //   iframe.src = url;

        
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
        // Sets scroll position back to where it was before refreshing
        iframe.contentWindow.scrollTo(currentScrollPosition.x, currentScrollPosition.y);
                

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
        setupPreviewFrameEventHandlers()
    }

}





function setupPreviewFrameEventHandlers() {

  var iframe = jQuery("#previewFrame")[0];
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

        if (clickedElement.hasClass('id-footer')) {
            switch_wizard_tab(jQuery('[data-tab=#builder-tab-styles]'));
        } else if (clickedElement.hasClass('id-fine-print')) {
            switch_wizard_tab(jQuery('[data-tab=#builder-tab-message-settings]'));
        } else {
            switch_wizard_tab(jQuery('[data-tab=#builder-tab-chunks]'));     

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
            toggleBuilderElementVis(builderRow.find('> .builder-row-header'), 'expand');
            toggleBuilderElementVis(builderColumnset.find('> .builder-columnset-header'), 'expand');
            toggleBuilderElementVis(builderChunk.find('> .builder-chunk-header'), 'expand');

            // Sync the builder element with the preview
            syncPreviewElement(builderChunk, true);
        }
    });

}

// Call setupPreviewFrameEventHandlers when the iframe loads
jQuery("#previewFrame").on("load", function() {
    
    setupPreviewFrameEventHandlers();
});



function replacePlaceholders(iframeSelector, replacements) {
    let $iframe = jQuery(iframeSelector);
    let iframeDocument = $iframe[0].contentDocument || $iframe[0].contentWindow.document;
    let $body = jQuery(iframeDocument.body);

    function replacePlaceholdersInString(str) {
        jQuery.each(replacements, function(placeholder, replacement) {
            let escapedPlaceholder = placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            let regex = new RegExp(escapedPlaceholder, 'g');
            str = str.replace(regex, replacement);
        });
        return str;
    }

    // Store original content for later reversion
    $body.data('originalContent', $body.html());

    // Replace placeholders in the entire body HTML
    let newHtml = replacePlaceholdersInString($body.html());
    $body.html(newHtml);
}

function revertPlaceholders(iframeSelector) {
    let $iframe = jQuery(iframeSelector);
    let iframeDocument = $iframe[0].contentDocument || $iframe[0].contentWindow.document;
    let $body = jQuery(iframeDocument.body);

    // Revert to original content
    let originalContent = $body.data('originalContent');
    if (originalContent) {
        $body.html(originalContent);
    }
}

function generateReplacements(data) {
    let replacements = {};
    
    // Handle flat key-value pairs
    for (let key in data) {
        if (typeof data[key] !== 'object') {
            replacements[`{{${key}}}`] = data[key];
        }
    }

    // Handle student array for FirstName and pronouns
    if (data.StudentArray && data.StudentArray.length > 0) {
        let student = data.StudentArray[0];
        replacements['{{{snippet "FirstName" "your child"}}}'] = student.StudentFirstName || 'your child';

        let pronouns = getPronounsByGender(student.StudentGender);
        for (let pronoun in pronouns) {
            replacements[`{{{snippet "pronoun" "${pronoun}"}}}`] = pronouns[pronoun];
        }
    }

    // Handle dot notation for arrays
    replaceDotNotation(replacements, data, '');

    return replacements;
}

function getPronounsByGender(gender) {
    switch(gender) {
        case '99000':
            return { 'SP': 'his', 'O': 'him', 'S': 'he', 'OP': 'his' };
        case '99001':
            return { 'SP': 'her', 'O': 'her', 'S': 'she', 'OP': 'hers' };
        case '99002':
        default:
            return { 'SP': 'their', 'O': 'them', 'S': 'they', 'OP': 'theirs' };
    }
}

function replaceDotNotation(replacements, obj, prefix) {
    for (let key in obj) {
        if (typeof obj[key] === 'object' && obj[key] !== null) {
            replaceDotNotation(replacements, obj[key], prefix + key + '.');
        } else {
            replacements[`{{${prefix}${key}}}`] = obj[key];
        }
    }
}

function getDefaultReplacements() {
    return {
        '{{{snippet "FirstName" "your child"}}}': 'Samantha',
        '{{{snippet "pronoun" "SP"}}}': 'her',
        '{{{snippet "pronoun" "O"}}}': 'her',
        '{{{snippet "pronoun" "S"}}}': 'she',
        '{{{snippet "pronoun" "OP"}}}': 'hers',
    };
}