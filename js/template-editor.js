jQuery(document).ready(function ($) {
	toggleOverlay(false);

	$('#showMobile').click(function () {
		$('#previewFrame').addClass('mobile-preview');		
	});

	$('#showDesktop').click(function () {
		$('#previewFrame').removeClass('mobile-preview');  
	});

	$('#saveTemplate').on('click', function () {
		$('.acf-form-submit input').click();
	});

	//collapse all layouts and accordians
	$('.layout').addClass('-collapsed');
	$('.acf-accordion.-open').removeClass('-open');
	$('.acf-accordion .acf-accordion-content').hide();

	//Scrolls the panes when a chunk or layout is activated
	function scrollPanes(preview, chunk, layout) {
		//console.log(preview+chunk+layout);
		var previewScrollPos = $(chunk).offset().top - 80;
		preview.find('body, html').animate({scrollTop: previewScrollPos}, 200);
		var builder = $('#builder-chunks');
		var scrollPosBuilder = layout.offset().top - builder.offset().top + builder.scrollTop() - 112;
		builder.animate({scrollTop: scrollPosBuilder}, 200);
	}

	
	acf.addAction('append', function($el){
		// Wait for 1 second and then simulate a click on the new layout
		setTimeout(function() {
			$el.find('.acf-fc-layout-handle').click();
		}, 1000);
	
		// Look within $el for .acf-fc-layout-handle elements and attach event handlers
		$el.find('.acf-fc-layout-handle').on('click', function () {
			idwiz_handle_layout_click($(this));
		});
	});
	

	//When a chunk layout is clicked
	$('.acf-fc-layout-handle').on('click', function () {
		idwiz_handle_layout_click($(this));
	});

	function idwiz_handle_layout_click(clicked) {
		var chunkID = clicked.parent('.layout').attr('data-id');
		//console.log('chunkID: ', chunkID); // log the chunkID
		var layout = clicked.parent('.layout');
		layout.siblings('.layout').addClass('-collapsed');
		var previewPane = $("iframe#previewFrame").contents();
		previewPane.find('.chunkWrap').removeClass('active');
	
		if (clicked.closest('.layout').hasClass('-collapsed')) {
			var correspondingChunkWrap = previewPane.find('.chunkWrap[data-id="' + chunkID + '"]');
			//console.log('correspondingChunkWrap: ', correspondingChunkWrap); // log the correspondingChunkWrap
			correspondingChunkWrap.addClass('active');
			scrollPanes(previewPane, correspondingChunkWrap, layout);
		}
	}


	var timeoutId;
	var addEventHandlers;

	addEventHandlers = function(iframeDocument) {
		var preview = $(iframeDocument);

		preview.find('.chunkWrap').click(function (e) {
			
			//console.log("chunkWrap clicked");
			if ($(this).hasClass('showChunkCode')) {
				$(this).closest('.chunkCode').show();
			} else {
				e.preventDefault();
			}

			if ($(this).hasClass('active')) {
				return;
			}

			var editorID = $(this).attr('data-id');
			var attachedEditor = $('body').find('.layout[data-id="' + editorID + '"]');

			if (attachedEditor.length === 0) {
				//console.log('No editor found with ID: ' + editorID);
				return;
			}

			preview.find('.chunkWrap').removeClass('active');
			$(this).addClass('active');
			$('.layout').addClass('-collapsed');
			attachedEditor.removeClass('-collapsed').trigger('editorActivated');
			$('a[data-key="field_63e3d761ed5b4"]').click();
			scrollPanes(preview, this, attachedEditor);
		});

		preview.find('.showChunkCode').click(function (e) {
			//console.log("showChunkCode clicked");
			e.stopPropagation();
			e.preventDefault();
			toggleOverlay(true);
			var templateId = $(this).data('templateid');
			var row_id = $(this).data('id');
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
					var codeBox = $('body').find('#generatedCode code');
					codeBox.html(html);
					hljs.highlightElement(codeBox[0]);
					$('body').find('#fullScreenCode').show();
					$('body').find('#generatedHTML').scrollTop(0);
				},
				error: function (xhr, status, error) {
					reject(false);
				}
			});	
		});
	}

	//Update preview on page load
	$(function(){idwiz_updatepreview();});
	//update preview on form update
	$('#id-chunks-creator .acf-field').on('input change', function() {
		idwiz_updatepreview();
	});
	//update preview via ajax
	function idwiz_updatepreview() {
		clearTimeout(timeoutId);
		timeoutId = setTimeout(function() {
			var $form = $('#id-chunks-creator');
			var formData = new FormData($form[0]);
			formData.append('action', 'idemailwiz_build_template');
			
			$.ajax({
				url: idAjax.ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(previewHtml) {
					var iframe = $('#previewFrame')[0];
					var iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
					iframeDocument.open();
					iframeDocument.write(previewHtml);
					iframeDocument.close();
					addEventHandlers(iframeDocument);
				}
			});
		}, 500);
	}

	$("iframe#previewFrame").on('load', function() {
		//console.log("Iframe load event triggered");
		addEventHandlers(this.contentDocument || this.contentWindow.document);
	});

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


	var $scrollingDiv = $('#builder-chunks');
    var $postTitle = $('#builder-chunks .acf-field--post-title');
    var $tabs = $('#builder-chunks .acf-tab-wrap');

    if ($tabs.length > 0 && $postTitle.length > 0) {
        $scrollingDiv.on("scroll", function() { // scroll event
            var scrollingDivTop = $scrollingDiv.scrollTop(); // returns number

            var postTitleHeight = $postTitle.outerHeight();

            if (scrollingDivTop > postTitleHeight) {
                $tabs.css({ position: 'sticky', top: postTitleHeight });
            } else {
                $tabs.css('position', 'relative');
            }

            if (scrollingDivTop > 0) {
                $postTitle.css({ position: 'sticky', top: 0 });
            } else {
                $postTitle.css('position', 'relative');
            }
        });
    }
	
	
	

});
