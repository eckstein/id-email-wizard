jQuery(document).ready(function ($) {
	toggleOverlay(false);

	$('#showMobile').click(function () {
		$('#previewFrame').addClass('mobile-preview');
		$(this).addClass('active');
		$('#showDesktop').removeClass('active');		
	});

	$('#showDesktop').click(function () {
		$('#previewFrame').removeClass('mobile-preview'); 
		$(this).addClass('active');
		$('#showMobile').removeClass('active'); 
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
		var previewScrollPos = $(chunk).offset().top - 130;
		preview.find('body, html').animate({scrollTop: previewScrollPos}, 200);
		var builder = $('#builder-chunks');
		var scrollPosBuilder = layout.offset().top - builder.offset().top + builder.scrollTop() - 112;
		builder.animate({scrollTop: scrollPosBuilder}, 200);
	}

	
	//When a new layout field is added
	acf.addAction('append', function($el){
		// Wait for 1 second and then simulate a click on the new layout
		//setTimeout(function() {
			//$el.find('.acf-fc-layout-handle').click();
		//}, 1000);
	
		// Look within $el for .acf-fc-layout-handle elements and attach event handlers
		$el.find('.acf-fc-layout-handle').on('click', function () {
			idwiz_handle_layout_click($(this));
		});
	});

	//When a layout field is drag/drog reordered
	acf.addAction('sortstop', function($el){
		console.log('sortstop');
		console.log($el);
		if (!$el.hasClass('-collapsed')) {
			
			setTimeout(function() {
				$el.addClass('-collapsed');
				//$el.find('.acf-fc-layout-handle').click();
				//idwiz_handle_layout_click($el.find('.acf-fc-layout-handle'));
				$el.find('.acf-fc-layout-handle').click();
				$el.removeClass('-collapsed');
			}, 1000);
		}
		

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
	//Update preview on page load
	$(function(){
		// Check for the chunks creator, indicating we're on a template edit page
		if ($('#id-chunks-creator').length > 0) {
			idwiz_updatepreview();
		}
	});

	//update preview on form update
	$('#id-chunks-creator .acf-field').on('input change', function() {
		idwiz_updatepreview();
	});
	//update preview via ajax
	function idwiz_updatepreview() {
		//check for merge tags toggle
		var mergeToggle = $('.fill-merge-tags.active');
		if (mergeToggle[0]) {
			var mergetags = true;
		} else {
			var mergetags = false;
		}

		//check for separators toggle
		var sepsToggle = $('.toggle-separators.active');
		if (sepsToggle[0]) {
			var showseps = true;
		} else {
			var showseps = false;
		}

		clearTimeout(timeoutId);
		timeoutId = setTimeout(function() {
			var $form = $('#id-chunks-creator');
			var formData = new FormData($form[0]);
			formData.append('action', 'idemailwiz_build_template');
			formData.append('mergetags', mergetags);
			formData.append('showseps', showseps);
			
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



//Fill the merge tags in the template on click
$('.fill-merge-tags').on('click', function() {
	//Add a class to keep the merge tags consistent until turned off
	$(this).toggleClass('active');
	idwiz_updatepreview();
});

// Hide the separators in the preview
$('.toggle-separators').on('click', function() {
	$(this).toggleClass('active');
	idwiz_updatepreview();
});


//Retrieve, generate, and show the full template HTML
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
//Close code popup
$('#hideFullCode').on('click', function () {
	$('#fullScreenCode').hide();
	toggleOverlay(false);
});
//Copy code in the popup
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


//Save the template title when updated
$(document).on('change', '#idwiz_templateTitle', function() {
	console.log('changed');
	var templateId = $(this).data('templateid');
	var value= $(this).val();
	$.ajax({
		type: "POST",
		url: idAjax.ajaxurl,
		data: {
			action: 'idemailwiz_save_template_title',
			template_id: templateId,
			template_title: value,
			security: idAjax_template_editor.nonce,
		},
		success: function (result) {
			console.log(result);
		},
		error: function (xhr, status, error) {
			console.log(error);
		}
	});	
});

//iframe context stuff
	//Add event handlers to iframe on load
	$("iframe#previewFrame").on('load', function() {
		//console.log("Iframe load event triggered");
		addEventHandlers(this.contentDocument || this.contentWindow.document);
	});

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

	
	

});
