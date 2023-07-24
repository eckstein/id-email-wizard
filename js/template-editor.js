jQuery(document).ready(function ($) {
	toggleOverlay(false);

	//Show the full template code 
	$('#showFullCode').on('click', function () {
		$('#fullScreenCode').show();
		toggleOverlay(true);
	});
	$('#hideFullCode').on('click', function () {
		$('#fullScreenCode').hide();
		toggleOverlay(false);
	});

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

	//Copy code snippet
	$('#copyCodeSnippet').click(function () {
		var html = $('.codeChunk:visible code').text();
		var tempInput = document.createElement("textarea");
		tempInput.style = "position: absolute; left: -1000px; top: -1000px";
		tempInput.innerHTML = html;
		document.body.appendChild(tempInput);
		tempInput.select();
		document.execCommand("copy");
		document.body.removeChild(tempInput);
		$('.copySnippetConfirm').fadeIn(1000, function () {
			setTimeout(function () {
				$('.copySnippetConfirm').fadeOut(1000);
			}, 5000);
		});
	});


	// Show block code in pop-up
	$('.showChunkCode').on('click', function (e) {

		//var blockKey = $(this).attr('data-id');
		// var codeBlock = $('#'+blockKey);
		var codeBlock = $(this).siblings('.hiddenCodeChunk:first').html();
		console.log('code block: ' + codeBlock);

		Swal.fire({
			title: 'Chunk Code: ' + $(this).closest('.chunkWrap').attr('data-chunk-layout'),
			html: codeBlock,
			customClass: {
				container: 'code-popup',
			},
			heightAuto: false, //height set with css
			width: '800px',
			showCancelButton: true,
			cancelButtonText: 'Close',
			confirmButtonText: 'Copy Code',
		}).then((copyCode) => {
			if (copyCode.isConfirmed) {
				var html = $('.swal2-html-container code').text();
				var tempInput = document.createElement("textarea");
				tempInput.style = "position: absolute; left: -1000px; top: -1000px";
				tempInput.innerHTML = html;
				document.body.appendChild(tempInput);
				tempInput.select();
				document.execCommand("copy");
				document.body.removeChild(tempInput);
				Swal.fire({
					title: "Code copied!",
					icon: "success",
				}).then(() => {
					toggleOverlay(false);
				});
			} else {
				//nada
			}
		});


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

$("iframe#previewFrame").on('load', function() {
    var preview = $(this).contents();

    preview.find('.chunkWrap').on('click', function (e) {
        e.preventDefault();

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
});

$('.acf-fc-layout-handle').on('click', function () {
    var chunkID = $(this).parent('.layout').attr('data-id');
    var layout = $(this).parent('.layout');
    layout.siblings('.layout').addClass('-collapsed');

    var previewPane = $("iframe#previewFrame").contents();

    // Deactivate all chunkWraps before activating the corresponding one
    previewPane.find('.chunkWrap').removeClass('active');

    var correspondingChunkWrap = previewPane.find('.chunkWrap[data-id="' + chunkID + '"]');
    correspondingChunkWrap.addClass('active');

    scrollPanes(previewPane, correspondingChunkWrap, layout);
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




});