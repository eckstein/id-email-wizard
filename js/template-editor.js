jQuery(document).ready(function ($) {
	toggleOverlay(false);

	let previewMode = "desktop";

	$("#showMobile").click(function () {
		previewMode = "mobile";
		$("#previewFrame").addClass("mobile-preview");
		$(this).addClass("active");
		$("#showDesktop").removeClass("active");
		idwiz_updatepreview();
	});

	$("#showDesktop").click(function () {
		previewMode = "desktop";
		$("#previewFrame").removeClass("mobile-preview");
		$(this).addClass("active");
		$("#showMobile").removeClass("active");
		idwiz_updatepreview();
	});

	$("#saveTemplate").on("click", function () {
		$(".acf-form-submit input").click();
	});

	//collapse all layouts and accordians
	$(".layout").addClass("-collapsed");
	$(".acf-accordion.-open").removeClass("-open");
	$(".acf-accordion .acf-accordion-content").hide();

	// Capture the scroll position for the active tab
	$("#templateUI .left").on("scroll", function () {
		var activeTab = $("#id-chunks-creator > .acf-fields > .acf-tab-wrap li.active"); // ACF adds .active to the active tab
		var currentScroll = $(this).scrollTop();
		activeTab.attr("data-scroll-position", currentScroll);
	});

	// Apply stored scroll position when a tab is clicked
	$("#id-chunks-creator > .acf-fields > .acf-tab-wrap li").on("click", function () {
		var maxScroll = $("#templateUI .left")[0].scrollHeight - $("#templateUI .left").height(); // Calculate max scroll position for the current tab's content

		var storedScrollPosition = parseInt($(this).attr("data-scroll-position") || 0); // Fallback to 0 if not set
		var finalScrollPosition = Math.min(storedScrollPosition, maxScroll); // Do not exceed max scroll position

		$("#templateUI .left").scrollTop(finalScrollPosition);
	});

	// Scrolls the panes when a chunk or layout is activated
	function scrollPanes(preview, chunk, layout) {
		// Ensure that the layout is visible before calculating positions
		if (layout.is(":visible")) {
			// Recalculate positions to ensure they are up-to-date
			var previewScrollPos = $(chunk).offset().top - 130;
			var builder = $("#templateUI .left");

			preview.find("body, html").animate({ scrollTop: previewScrollPos }, 100);			

			// Make sure the builder element is present and visible before scrolling
			if (builder.length > 0 && builder.is(":visible")) {
				var scrollPosBuilder = layout.offset().top - builder.offset().top + builder.scrollTop() - 200;
				builder.animate({ scrollTop: scrollPosBuilder }, 100);
			} else {
				console.log("Builder element not found or not visible");
			}
		} else {
			console.log("Layout is not visible, cannot scroll");
		}
	}



	// Function to update the TinyMCE background color
	function updateTinyMceBackground(tinyMceEditor, color) {
		tinyMceEditor.getBody().style.backgroundColor = color;
	}

	function setAcfBgColorForTinyMCE(tinyMceEditor, field) {
		// Existing code to set the background color
		var layoutElement = field.$el.closest('.layout').length ? field.$el.closest('.layout') : field.$el.parent();
		var colorPicker = layoutElement.find('.chunkMainBgPicker input');
		if (colorPicker.length) {
			var color = colorPicker.val();
			updateTinyMceBackground(tinyMceEditor, color);
		}


	}


	// ACF action for WYSIWYG TinyMCE initialization
	acf.addAction('wysiwyg_tinymce_init', function(ed, id, mceInit, field) {
		setAcfBgColorForTinyMCE(ed, field);
	});





	// Function to handle color picker changes
	function onColorPickerChange(colorPicker) {
		var color = colorPicker.val();
		var layoutElement = colorPicker.closest('.layout').length ? colorPicker.closest('.layout') : colorPicker.parent();
        
		// Find the TinyMCE iframe within the same layout or parent element
		var tinyMceIframe = layoutElement.find('iframe').filter(function() {
			return $(this).contents().find('.mce-content-body').length > 0;
		});

		if (tinyMceIframe.length) {
			// Get the TinyMCE editor instance and update its background color
			var editorId = tinyMceIframe.attr('id').split('_ifr')[0];
			var editor = tinyMCE.get(editorId);
			if (editor) {
				updateTinyMceBackground(editor, color);
			}
		}
	}

	// Listen for changes on bg color picker inputs
	$(document).on('change', '.chunkMainBgPicker input', function() {
		onColorPickerChange($(this));
	});



	//When a new layout field is added
	acf.addAction("append", function ($el) {
		// Wait for 1 second and then simulate a click on the new layout
		// This will auto-open the newly added field. Turned off for now since it's annoying
		setTimeout(function () {
			//$el.find(".acf-fc-layout-handle").click().click();
		}, 1000);

		// Look within $el for .acf-fc-layout-handle elements and attach event handlers
		$el.find(".acf-fc-layout-handle").on("click", function () {
			idwiz_handle_layout_click($(this));
		});

	});

	var initialLayoutOrder = [];

	// When a layout field drag starts
	acf.addAction('sortstart', function ($el) {
		// Record the initial order of layout fields
			initialLayoutOrder = $el.closest('.layout-fields-container').find('.layout-field').map(function() {
			return $(this).data('layout-id'); // Adjust this selector/data attribute to match your structure
		}).get();
	});

	// When a layout field drag stops
	acf.addAction('sortstop', function ($el) {
		// Get the new order of layout fields
		var newOrder = $el.closest('.layout-fields-container').find('.layout-field').map(function() {
			return $(this).data('layout-id'); // Adjust this selector/data attribute to match your structure
		}).get();

		// Compare the initial order with the new order
		var orderChanged = !initialLayoutOrder.every(function(element, index) {
			return element === newOrder[index];
		});

		// Execute the code only if the order has changed
		if (orderChanged) {
			console.log("A re-ordering has occurred.");
			if (!$el.hasClass("-collapsed")) {
				setTimeout(function () {
					$el.addClass("-collapsed");
					$el.find(".acf-fc-layout-handle").click();
					$el.removeClass("-collapsed");
				}, 1000);
			}
		}
	});


	//When a layout is clicked
	$(".acf-fc-layout-handle").on("click", function () {
		idwiz_handle_layout_click($(this));
	});

	function idwiz_handle_layout_click(clicked) {
		var isChildLayout = clicked.closest(".layout").parents(".layout").length > 0;
		var layout = clicked.closest(".layout");
		var previewPane = $("iframe#previewFrame").contents();

		var layoutDataId;
		var inputName = layout.children("input").attr("name");

		if (isChildLayout) {
			// For child layouts, find the parent layout's layoutDataId
			layoutDataId = layout.parents(".layout").last().attr("data-id");
		} else {
			// For parent layouts, use the clicked layout's layoutDataId
			layoutDataId = layout.attr("data-id");
		}

		layout.siblings(".layout").addClass("-collapsed");
		layout.closest('.layout').find('.layout').addClass('-collapsed');

		// Activate the corresponding chunkWrap
		var correspondingChunkWrap = previewPane.find('.chunkWrap[data-id="' + layoutDataId + '"]');

		if (!isChildLayout) {
			// Remove active class from all chunkwraps
			previewPane.find(".chunkWrap").removeClass("active");
			previewPane.find(".child-chunkWrap").removeClass("active");
			
			
			//layout.closest('.acf-fields').find('.layout').addClass('-collapsed');
		}
		

		// Add active class to the clicked layout
		if (layout.hasClass("-collapsed")) {
			correspondingChunkWrap.addClass("active");
			//initAcfBackgroundColors();
		} 

		if (!isChildLayout && layout.hasClass("-collapsed")) {
			// Scroll to the corresponding chunkWrap
			scrollPanes(previewPane, correspondingChunkWrap, layout);
		}

		// Highlight the specific child element for child layouts
		if (isChildLayout) {
			var childIndex = layout.attr("data-id"); // Get the data-id (row index) of the clicked layout

			// Find the corresponding chunkWrap in the preview pane
			var correspondingChunkWrap = previewPane.find('.chunkWrap[data-id="' + layoutDataId + '"]');

			// Find the specific child element within the corresponding chunkWrap
			var correspondingChild = correspondingChunkWrap.find('.child-chunkWrap[data-content-index="' + childIndex + '"]');

			correspondingChild.siblings().removeClass("active"); // Remove active class from siblings

			if (correspondingChild.length) {
				correspondingChild.toggleClass("active"); // Toggle active class on the corresponding child
				scrollPanes(previewPane, correspondingChild, layout); // Scroll to the corresponding child
			} else {
				console.log("No corresponding child found."); // Debug log
			}
			//scrollPanes(previewPane, correspondingChild, layout);
		}

	}
	
	//Update preview on page load
	$(function () {
		// Check for the chunks creator, indicating we're on a template edit page
		if ($("#id-chunks-creator").length > 0) {
			idwiz_updatepreview();
		}
	});

	//update preview on form update
	$("#id-chunks-creator .acf-field").on("input change", function () {
		idwiz_updatepreview();
	});

	var timeoutId;
	//update preview via ajax
	function idwiz_updatepreview() {
		//check for merge tags toggle
		var mergeToggle = $(".fill-merge-tags.active");
		if (mergeToggle[0]) {
			var mergetags = true;
		} else {
			var mergetags = false;
		}

		//check for separators toggle
		// var sepsToggle = $(".toggle-separators.active");
		// if (sepsToggle[0]) {
		// 	var showseps = true;
		// } else {
		// 	var showseps = false;
		// }
		var templateId = $("#templateUI").data("postid");
		clearTimeout(timeoutId);
		timeoutId = setTimeout(function () {
			var $form = $("#id-chunks-creator");
			var formData = new FormData($form[0]);
			formData.append("action", "idemailwiz_build_template");
			formData.append("security", idAjax_template_editor.nonce);
			formData.append("mergetags", mergetags);
			//formData.append("showseps", showseps);
			formData.append("templateid", templateId);
			formData.append("previewMode", previewMode);

			$.ajax({
				url: idAjax.ajaxurl,
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				success: function (previewHtml) {
					var iframe = $("#previewFrame")[0];
					var iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
					iframeDocument.open();
					iframeDocument.write(previewHtml);
					iframeDocument.close();
					addEventHandlers(iframeDocument);
				},
			});
		}, 500);
	}

	//Fill the merge tags in the template on click
	$(".fill-merge-tags").on("click", function () {
		//Add a class to keep the merge tags consistent until turned off
		$(this).toggleClass("active");
		idwiz_updatepreview();
	});

	// Hide the separators in the preview
	$(".toggle-separators").on("click", function () {
		$(this).toggleClass("active");
		idwiz_updatepreview();
	});


	$(".showFullMode").on("click", function () {
		var mode = $(this).attr('data-preview-mode');

		toggleOverlay(true);
		$('body').css('overflow', 'hidden');
		var templateId = $(this).data("postid");

		var additionalData = {
			template_id: templateId,
			mode: mode
		};

		idemailwiz_do_ajax("idemailwiz_generate_template_html", idAjax_template_editor.nonce, additionalData, getTemplateSuccess, getTemplateError, "html");

		function getTemplateSuccess(data) {
			if ($("#fullScreenMode").length === 0) {
				generateTemplateEditorPopup(mode);
			}

			var container = $(".fullScreenModeInnerScroll .previewDisplay");
        
			if (mode === 'code') {
				container.html('<pre><code>' + data + '</code></pre>');
				hljs.highlightElement(container.find('code')[0]);
			} else {
				container.html(data); // Directly render HTML for preview
			}

			$("#fullScreenMode").show();
			$(".fullScreenModeInnerScroll").scrollTop(0);
			toggleOverlay(true);
			
		}

		function getTemplateError(xhr, status, error) {
			console.log('Error retrieving or generating template HTML!');
		}
	});

	


	//Close code popup
	$(document).on("click", "#hideFullCode", function() {
		$("#fullScreenMode").hide();
		toggleOverlay(false);
		$('body').css('overflow', 'auto');
	});

	// Copy code in the popup
	$(document).on("click", "#copyCode", function() {
		var originalText = $(this).html(); // Store the original button text
		var html = $("#generatedCode code").text();
		var tempInput = document.createElement("textarea");
		tempInput.style = "position: absolute; left: -1000px; top: -1000px";
		tempInput.innerHTML = html;
		document.body.appendChild(tempInput);
		tempInput.select();
		document.execCommand("copy");
		document.body.removeChild(tempInput);

		$(this).html("<i class='fa-solid fa-check'></i>&nbsp;Code copied!");

		// Set a timeout to revert back to the original text after 5 seconds
		setTimeout(() => {
			$(this).html(originalText);
		}, 5000);
	});



	$(document).on("click", "#fullModeDesktop", function() {
		$(this).addClass('green');
		$('#fullModeMobile').removeClass('green');
		$('.fullModeViewWrapper').removeClass('mobile');
		$('#fullModePreview > p').remove();
	});
	$(document).on("click", "#fullModeMobile", function() {
		$(this).addClass('green');
		$('#fullModeDesktop').removeClass('green');
		$('.fullModeViewWrapper').addClass('mobile');
		$('#fullModePreview').prepend('<p style="text-align: center;"><em>Use the mouse wheel or arrow keys to scroll</em></p>');
	});


	// Generate full-code popup
	function generateTemplateEditorPopup(view = 'code') {
		var popupHtml = `
			<div id="fullScreenMode">
				<div class="fullScreenButtons">`;
			if (view == 'code') {
			popupHtml += `
					<div class="wiz-button green" id="copyCode"><i class="fa-regular fa-copy"></i>&nbsp;Copy Code</div>`;
			} else {
			popupHtml += `<div class="wiz-button green" id="fullModeDesktop"><i class="fa-solid fa-desktop"></i></div>
					<div class="wiz-button" id="fullModeMobile"><i class="fa-solid fa-mobile-screen-button"></i></div>`;
			}
			popupHtml += `<button class="wiz-button" id="hideFullCode"><i class="fa-solid fa-circle-xmark fa-2x"></i></button>
				</div>`;
		if (view == 'code') {

		popupHtml += `
		<div id="generatedHTML" class="fullScreenModeInnerScroll">
			<pre id="generatedCode">
				<code class="language-html previewDisplay">
					Code here.
				</code>
			</pre>
		</div>`;
		} else {
		popupHtml += `
		<div id="fullModePreview">
			<div class="fullScreenModeInnerScroll">
				<div class="previewDisplay">
					Preview here.
				</div>
			</div>
		</div>`;
		}
		popupHtml += `</div>`;
		$("body").append(popupHtml);
	}

	// Hide code on click outside box
	$(document).on("click", function(event) {
		if (!$(event.target).closest("#fullScreenMode").length && $("#fullScreenMode").is(":visible")) {
			$("#fullScreenMode").hide();
			$('body').css({ overflow: 'auto' });
			toggleOverlay(false);
		}
	});

	//Save the template title when updated
	$(document).on("change", "#idwiz_templateTitle", function () {
		console.log("changed");
		var templateId = $(this).data("templateid");
		var value = $(this).val();
		$.ajax({
			type: "POST",
			url: idAjax.ajaxurl,
			data: {
				action: "idemailwiz_save_template_title",
				template_id: templateId,
				template_title: value,
				security: idAjax_template_editor.nonce,
			},
			success: function (result) {
				console.log(result);
			},
			error: function (xhr, status, error) {
				console.log(error);
			},
		});
	});

	//iframe context stuff
	//Add event handlers to iframe on load
	$("iframe#previewFrame").on("load", function () {
		addEventHandlers(this.contentDocument || this.contentWindow.document);
	});

	var addEventHandlers;
	addEventHandlers = function (iframeDocument) {
		var preview = $(iframeDocument);

		preview.find(".child-chunkWrap").click(function (e) {

			

			// Click the builder tab in case we're on a settings tab
			$("#id-chunks-creator > .acf-fields > .acf-tab-group li:first-child").click();

			var contentIndex = $(this).attr("data-content-index");
			var parentFieldId = $(this).attr("data-parent-field-id");

			// Find the parent field container
			var parentField = $("body").find("[data-name='" + parentFieldId + "']");

			// Within the parent, find the specific layout with matching data-id
			attachedEditor = parentField.find(".layout").filter(function () {
				return $(this).attr("data-id") === contentIndex;
			});

			var self = this; // Capture the context outside setTimeout

			setTimeout(function () {
				if (!attachedEditor.is(":visible")) {
					
					var tabs = $(parentField).siblings(".acf-tab-wrap").find (".acf-tab-button");
					var found = false; // Variable to track if a matching tab is found

					tabs.each(function (index, tab) {
						$(tab).click();
						if (attachedEditor.is(":visible")) {                
							found = true; // Set to true if the editor becomes visible
							return false; // Exit the loop if the editor is visible
						}
					});

					// Check if the attachedEditor is still not visible after checking all tabs
					if (!found) {
						$(tabs[0]).click(); // Click the first tab as a fallback
					}
				}

				preview.find(".child-chunkWrap").removeClass("active");

				$(self).addClass("active"); // Use 'self' instead of 'this'

				//attachedEditor.siblings(".layout").addClass("-collapsed");
				attachedEditor.closest(".acf-fields").find(".layout").addClass("-collapsed");
				if (attachedEditor.hasClass("-collapsed")) {
					attachedEditor.removeClass("-collapsed").trigger("editorActivated");
				}

				// Scroll to layout
				scrollPanes(preview, self, attachedEditor); // Use 'self' here as well

				return;

			}, 100);


			
		});

		preview.find(".chunkWrap").click(function (e) {
			//console.log("chunkWrap clicked");
			if ($(this).hasClass("child-chunkWrap")) {
				e.stopPropagation();
				return false;
			} else {
				e.preventDefault();
			}

			// If chunk is already active, we do nothing
			if ($(this).hasClass("active")) {
				return;
			}
			var editorID = $(this).attr("data-id");
			var attachedEditor = $("body").find('.layout[data-id="' + editorID + '"]');

			preview.find(".chunkWrap").removeClass("active");

			$(".layout").addClass("-collapsed");
			attachedEditor.removeClass("-collapsed").trigger("editorActivated");

			if (attachedEditor.length === 0) {
				//console.log('No editor found with ID: ' + editorID);
				return;
			}

			$(this).addClass("active");

			var firstTab = $('#id-chunks-creator > .acf-fields > .acf-tab-wrap li:first-child a.acf-tab-button');

			// Click on the first tab
			if (firstTab.length > 0) {
				firstTab.trigger('click');
			}

			// Your existing scrolling function
			scrollPanes(preview, this, attachedEditor);
		});

		preview.find(".showChunkCode").click(function (e) {
			e.stopPropagation();
			e.preventDefault();
			toggleOverlay(true);
			var templateId = $(this).data("templateid");
			var row_id = $(this).data("id");
			console.log(templateId);
			console.log(row_id);
			var additionalData = {
				template_id: templateId,
				row_id: row_id,
				security: idAjax_template_editor.nonce,
			};

			idemailwiz_do_ajax(
				"idemailwiz_generate_chunk_html",
				idAjax_template_editor.nonce,
				additionalData,
				function (html) {
					// Success Callback
					var codeBox = $("body").find("#generatedCode code");
					codeBox.html(html);
					hljs.highlightElement(codeBox[0]);
					$("body").find("#fullScreenMode").show();
					$("body").find(".fullScreenModeInnerScroll").scrollTop(0);
				},
				function (xhr, status, error) {
					// Error Callback
					// Your error handling logic here
				},
				"html" // Data type
			);
		});
	};

	$(document).on("click", ".new-snippet", function () {
		Swal.fire({
			title: "Create New Snippet",
			input: "text",
			inputPlaceholder: "Enter snippet title...",
			showCancelButton: true,
			cancelButtonText: "Cancel",
			confirmButtonText: "Create",
		}).then((result) => {
			if (result.isConfirmed) {
				const title = result.value;

				idemailwiz_do_ajax(
					"idemailwiz_create_new_snippet",
					idAjax_template_editor.nonce,
					{ postTitle: title },
					function (success) {
						//console.log(data);
						var postUrl = idAjax_template_editor.site_url + "/?p=" + success.data.post_id;
						window.open(postUrl, '_blank'); // Opens the link in a new tab
						Swal.fire("Success!", "Snippet was created and opened in a new tab. Or <a href='"+postUrl+"'>click here to go there directly</a>.", "success");
					},
					function (error) {
						console.log(error);
						Swal.fire("Error", "An error occurred. Check the console for details.", "error");
					}
				);
			}
		});
	});
	
});

(function($){

	// Function to update links
	function updatePostObjectLinks(selectField) {
		// Clear any existing link
		selectField.closest('.acf-field-post-object').find('.post-object-link').remove();

		// Get the selected option(s)
		var selectedOptions = selectField.find('option:selected');

		// If there's a selected option
		if(selectedOptions.length){
			var linkHtml = '<div class="snippet-object-links"><a href="#" class="new-snippet"><i class="fa-solid fa-circle-plus"></i>&nbsp;&nbsp;Create New Snippet</a><br/><br/>Edit: ';
			// For single select fields
			if(!selectField.attr('multiple')){
				var postId = selectedOptions.val();
				var postTitle = selectedOptions.text();
				linkHtml += '<div class="snippet-object-link"><a href="?p='+postId+'" target="_blank">'+postTitle+'</a></div>';

				// Append the link after the select2 container
				selectField.closest('.acf-input').append(linkHtml);
			}

			// For multi-select fields
			else{
				var linksHTML = '<div class="post-object-links">';
				selectedOptions.each(function(){
					var postId = $(this).val();
					var postTitle = $(this).text();
					linksHTML += '<div class="snippet-object-link"><a href="?p='+postId+'" target="_blank">'+postTitle+'</a></div>';
				});
				linksHTML += '</div>';

				// Append the links after the select2 container
				selectField.closest('.acf-input').append(linksHTML);
			}
			linkHtml += '</div>';
		}
		
	}

	// When the document is ready
	$(document).ready(function(){

		// Update links on page load for each post object field within the 'html' layout of a flexible content field
		$('.layout[data-layout="html"] .acf-field-post-object select').each(function(){
			updatePostObjectLinks($(this));
		});

		// When the ACF field is changed
		$('.layout[data-layout="html"] .acf-field-post-object select').on('change', function(){
			updatePostObjectLinks($(this));
		});

	});

})(jQuery);




