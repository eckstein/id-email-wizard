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

// Wait until the iframe has loaded before setting up event handlers
$("iframe#previewFrame").on('load', function() {
    var preview = $(this).contents();

    preview.find('.chunkWrap').on('click', function (e) {
        e.preventDefault();
        chunkWrapActivate(preview, this);
    });
});

function chunkWrapActivate(preview, chunk) {
    // If the layout is already activated, ignore the click
    if ($(chunk).hasClass('active')) {
        return;
    }

    var editorID = $(chunk).attr('data-id');
    var attachedEditor = $('.layout[data-id="' + editorID + '"]');

    if (attachedEditor.length === 0) {
        console.log('No editor found with ID: ' + editorID);
        return;
    }

    // Remove the active class from all chunkWraps in the iframe
    preview.find('.chunkWrap').removeClass('active');

    // Add the active class to the clicked chunkWrap
    $(chunk).addClass('active');

    // Collapse all layouts and expand the attachedEditor
    $('.layout').addClass('-collapsed');
    attachedEditor.removeClass('-collapsed').trigger('editorActivated');

    // Switch to the main editor tab if we're on the settings tab
    $('a[data-key="field_63e3d761ed5b4"]').click();

    // Scroll the #builder div to the expanded .layout element
    // with a 40px offset from the top
    var builder = $('#builder');
    var scrollPos = attachedEditor.offset().top - builder.offset().top - 40;
    smoothScroll(builder[0], scrollPos, 500); // animate over 500ms
}


// When ACF layout field is clicked
$('.acf-fc-layout-handle').on('click', function () {
    var chunkID = $(this).parent('.layout').attr('data-id');
    var layout = $(this).parent('.layout');
    layout.siblings('.layout').addClass('-collapsed');

    var previewPane = $("iframe#previewFrame").contents();

    // Deactivate all chunkWraps before activating the corresponding one
    previewPane.find('.chunkWrap').removeClass('active');

    var correspondingChunkWrap = previewPane.find('.chunkWrap[data-id="' + chunkID + '"]');
    correspondingChunkWrap.addClass('active');

    // Calculate the scroll position relative to the iframe's body
    var scrollPos = correspondingChunkWrap.offset().top - 40;

    // Animate the scroll position of the iframe's body
    previewPane.find('body, html').animate({ scrollTop: scrollPos }, 500);

    if (layout.hasClass('-collapsed')) {
        // Delay the scroll operation to give the layout time to expand
        setTimeout(function() {
            // Scroll the #builder div to the bottom of the expanded .layout element
            // with a 40px offset from the top
            var builder = $('#builder');
            var scrollPos = layout.offset().top - builder.offset().top - 40;
            smoothScroll(builder[0], scrollPos, 250); // animate over 250ms
        }, 200); // 200 ms delay, adjust as needed
    } else {
        correspondingChunkWrap.removeClass('active');
    }
});


//collapse all acf groups on page load
$('.layout').addClass('-collapsed');
//collapse all accordion on page load
$('.acf-accordion.-open').removeClass('-open');
$('.acf-accordion .acf-accordion-content').hide();

	

$('#templateTable').on('click', '.folderMenu i', function() {
	$('.archive.category .aboveTemplateTable .folderMenu ul').slideToggle();
});

});



function smoothScroll(element, target, duration) {
    target = Math.round(target);
    duration = Math.round(duration);
    var start_time = Date.now();
    var end_time = start_time + duration;

    var start_top = element.scrollTop;
    var distance = target - start_top;

    // based on http://en.wikipedia.org/wiki/Smoothstep
    var smooth_step = function(start, end, point) {
        if(point <= start) { return 0; }
        if(point >= end) { return 1; }
        var x = (point - start) / (end - start); // interpolation
        return x*x*(3 - 2*x);
    }

    return new Promise(function(resolve, reject) {
        var previous_top = element.scrollTop;

        var scroll_frame = function() {
            if(element.scrollTop != previous_top) {
                reject("Interrupted");
                return;
            }

            var now = Date.now();
            var point = smooth_step(start_time, end_time, now);
            var frameTop = Math.round(start_top + (distance * point));
            element.scrollTop = frameTop;

            if(now >= end_time) {
                resolve();
                return;
            }

            if(element.scrollTop === previous_top && element.scrollTop !== frameTop) {
                resolve();
                return;
            }
            previous_top = element.scrollTop;

            setTimeout(scroll_frame, 0);
        }
        setTimeout(scroll_frame, 0);
    });
}