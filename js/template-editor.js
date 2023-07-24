jQuery(document).ready(function ($) {
	toggleOverlay(false);

	

	//Show mobile preview
	$('#showMobile').click(function () {
		$('#previewFrame').addClass('mobile-preview');		
	});
	//Show desktop preview
	$('#showDesktop').click(function () {
		$('#previewFrame').removeClass('mobile-preview');  
	});

	//Save ACF form on click outside of form
	$('#saveTemplate').on('click', function () {
		$('.acf-form-submit input').click();
	});
	
	

	


	

//collapse all acf layout groups on page load
$('.layout').addClass('-collapsed');
//collapse all accordion on page load
$('.acf-accordion.-open').removeClass('-open');
$('.acf-accordion .acf-accordion-content').hide();

	
function scrollPanes(preview, chunk, layout) {
    // Calculate the scroll position for the preview pane
    var previewScrollPos = $(chunk).offset().top - 80;

    // Use animate to scroll the preview pane
    preview.find('body, html').animate({scrollTop: previewScrollPos}, 200);

    // Calculate the scroll position for the builder pane
    var builder = $('#builder-chunks');
    var scrollPosBuilder = layout.offset().top - builder.offset().top + builder.scrollTop() - 112;

    // Animate the scroll position of the builder
    builder.animate({scrollTop: scrollPosBuilder}, 200);
}

//On acf layout click
$('.acf-fc-layout-handle').on('click', function () {
    var chunkID = $(this).parent('.layout').attr('data-id');
    var layout = $(this).parent('.layout');
    layout.siblings('.layout').addClass('-collapsed');

    var previewPane = $("iframe#previewFrame").contents();

    // Deactivate all chunkWraps before activating the corresponding one
    previewPane.find('.chunkWrap').removeClass('active');

	//Only activate the chunk and scroll if the layout accordian is opening (not collapsing)
	if ($(this).closest('.layout').hasClass('-collapsed')) {
		var correspondingChunkWrap = previewPane.find('.chunkWrap[data-id="' + chunkID + '"]');
		correspondingChunkWrap.addClass('active');
		scrollPanes(previewPane, correspondingChunkWrap, layout);
	}

    
});



var timeoutId;

$('#id-chunks-creator .acf-field').on('input change', function() {
    var $field = $(this);  // Preserve context

    clearTimeout(timeoutId);  // Clear the previous timer

    timeoutId = setTimeout(function() {
        console.log('form changed!');
        var $form = $field.closest('.acf-form').clone();
        var formData = new FormData($form[0]);
        formData.append('action', 'idemailwiz_build_template');
        
        $.ajax({
            url: idAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,  // tell jQuery not to process the data
            contentType: false,  // tell jQuery not to set contentType
            success: function(previewHtml) {
                var iframe = $('#previewFrame')[0];
                var iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
                iframeDocument.open();
                iframeDocument.write(previewHtml);
                iframeDocument.close();
            }
        });

    }, 500);  // Wait for 500ms of inactivity before calling the function
});


//Functions that need to run from the iframe context
$("iframe#previewFrame").on('load', function() {
    var preview = $(this).contents();

    preview.find('.chunkWrap').on('click', function (e) {
        // Check if the clicked element has the class 'showChunkCode'
        if ($(this).hasClass('showChunkCode')) {
            // If the element has 'showChunkCode' class, show the .chunkCode element
            $(this).closest('.chunkCode').show();
        } else {
            // Otherwise, prevent the default behavior for elements without 'showChunkCode'
            e.preventDefault();
        }


		
        // If the chunk is already activated, ignore the click
        if ($(this).hasClass('active')) {
            return;
        }

        var editorID = $(this).attr('data-id');
        var attachedEditor = $('.layout[data-id="' + editorID + '"]');

        if (attachedEditor.length === 0) {
            console.log('No editor found with ID: ' + editorID);
            return;
        }

        // Remove the active class from all chunkWraps in the iframe
        preview.find('.chunkWrap').removeClass('active');

        // Add the active class to the clicked chunkWrap
        $(this).addClass('active');

        // Collapse all layouts and expand the attachedEditor
        $('.layout').addClass('-collapsed');
        attachedEditor.removeClass('-collapsed').trigger('editorActivated');

        // Switch to the main editor tab if we're on the settings tab
        $('a[data-key="field_63e3d761ed5b4"]').click();

        scrollPanes(preview, this, attachedEditor);
    });




	//Show the full template code 
	preview.on('click', '.showChunkCode', function (e) {
		e.preventDefault(); // Prevent the default link behavior
		toggleOverlay(true);
		var templateId = $(this).data('templateid');
		var row_id = $(this).data('id');//like "row-0", "row-1", etc
		$.ajax({
			type: "POST",
			url: idAjax.ajaxurl,
			data: {
				action: 'idemailwiz_generate_chunk_html',
				template_id: templateId,
				row_id: row_id,
				security: idAjax_template_editor.nonce,
				
			},
			success: function (html) {
				//console.log(html);
				var codeBox = $('#generatedCode code');
				codeBox.html(html);
				hljs.highlightElement(codeBox[0]);
				$('#fullScreenCode').show();
				$('#generatedHTML').scrollTop(0);
			},
			error: function (xhr, status, error) {
				reject(false);
			}
		});	
	});


});

//Show the full template code 
$('#showFullCode').on('click', function () {
	toggleOverlay(true);
	var templateId = $(this).data('postid');
	$.ajax({
		type: "POST",
		url: idAjax.ajaxurl,
		data: {
			action: "idemailwiz_generate_template_html",
			template_id: templateId,
			security: idAjax_template_editor.nonce
		},
		success: function (html) {
			//console.log(html);
			var codeBox = $('#generatedCode code');
			codeBox.html(html);
			hljs.highlightElement(codeBox[0]);
			$('#fullScreenCode').show();
			$('#generatedHTML').scrollTop(0);
		},
		error: function (xhr, status, error) {
			reject(false);
		}
	});	
});

$('#hideFullCode').on('click', function () {
	$('#fullScreenCode').hide();
	toggleOverlay(false);
});

$('#copyCode').on('click', function () {
	var html = $('#generatedCode code').text();
	var tempInput = document.createElement("textarea");
	tempInput.style = "position: absolute; left: -1000px; top: -1000px";
	tempInput.innerHTML = html;
	document.body.appendChild(tempInput);
	tempInput.select();
	document.execCommand("copy");
	document.body.removeChild(tempInput);
	$(this).text('Code copied!', function () {
		setTimeout(function () {
			$(this).text('Copy code');
		}, 5000);
	});
});






});