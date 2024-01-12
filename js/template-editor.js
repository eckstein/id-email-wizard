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
			preview.find("body, html").animate({ scrollTop: previewScrollPos }, 200);

			var builder = $("#templateUI .left");

			// Make sure the builder element is present and visible before scrolling
			if (builder.length > 0 && builder.is(":visible")) {
				var scrollPosBuilder = layout.offset().top - builder.offset().top + builder.scrollTop() - 200;
				builder.animate({ scrollTop: scrollPosBuilder }, 200);
			} else {
				console.log("Builder element not found or not visible");
			}
		} else {
			console.log("Layout is not visible, cannot scroll");
		}
	}


	//When a new layout field is added
	acf.addAction("append", function ($el) {
		// Wait for 1 second and then simulate a click on the new layout
		// This will auto-open the newly added field. Turned off for now since it's annoying
		setTimeout(function () {
			$el.find(".acf-fc-layout-handle").click().click();
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
		} 

		if (!isChildLayout && layout.hasClass("-collapsed")) {
			// Scroll to the corresponding chunkWrap
			scrollPanes(previewPane, correspondingChunkWrap, layout);
		}

		// Highlight the specific child element for child layouts
		if (isChildLayout) {
			var childIndex = layout.attr("data-id");
			var inputName = layout.children("input").attr("name");
			var nameParts = inputName ? inputName.split("][") : [];
			var parentFieldId = null;

			// Check if the nameParts array has the expected number of parts
			if (nameParts.length > 2) {
				var thirdBracketContent = nameParts[2]; // Content of the third set of brackets
				parentFieldId = thirdBracketContent;
			}

			var correspondingChild = correspondingChunkWrap.find('[data-content-index="' + childIndex + '"][data-parent-field-id="' + parentFieldId + '"]');
			correspondingChild.siblings().removeClass("active");
			if (correspondingChild.length) {
				if (correspondingChild.hasClass("active")) {
					correspondingChild.removeClass("active");
				} else {
					correspondingChild.addClass("active");
				}
			} else {
				console.log("No corresponding child found."); // Debug log
			}
			scrollPanes(previewPane, correspondingChild, layout);
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
		var sepsToggle = $(".toggle-separators.active");
		if (sepsToggle[0]) {
			var showseps = true;
		} else {
			var showseps = false;
		}
		var templateId = $("#templateUI").data("postid");
		clearTimeout(timeoutId);
		timeoutId = setTimeout(function () {
			var $form = $("#id-chunks-creator");
			var formData = new FormData($form[0]);
			formData.append("action", "idemailwiz_build_template");
			formData.append("security", idAjax_template_editor.nonce);
			formData.append("mergetags", mergetags);
			formData.append("showseps", showseps);
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

	//Retrieve, generate, and show the full template HTML
	$("#showFullCode").on("click", function () {
		toggleOverlay(true);
		var templateId = $(this).data("postid");

		var additionalData = {
			template_id: templateId,
		};

		function successCallback(data) {
			var codeBox = $("#generatedCode code");
			codeBox.html(data);
			hljs.highlightElement(codeBox[0]);
			$("#fullScreenCode").show();
			$("#generatedHTML").scrollTop(0);
		}

		function errorCallback(xhr, status, error) {
			// Handle the error here
		}

		idemailwiz_do_ajax("idemailwiz_generate_template_html", idAjax_template_editor.nonce, additionalData, successCallback, errorCallback, "html");
	});

	//Close code popup
	$("#hideFullCode").on("click", function () {
		$("#fullScreenCode").hide();
		toggleOverlay(false);
	});
	//Copy code in the popup
	$("#copyCode").on("click", function () {
		var html = $("#generatedCode code").text();
		var tempInput = document.createElement("textarea");
		tempInput.style = "position: absolute; left: -1000px; top: -1000px";
		tempInput.innerHTML = html;
		document.body.appendChild(tempInput);
		tempInput.select();
		document.execCommand("copy");
		document.body.removeChild(tempInput);
		$(this).text("Code copied!", function () {
			setTimeout(function () {
				$(this).text("Copy code");
			}, 5000);
		});
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

			

			// Click the builder tab in case we're on a settings tabs
			$("#id-chunks-creator > .acf-fields > .acf-tab-group li:first-child").click();

			var parentFieldId = $(this).closest(".child-chunkWrap").attr("data-parent-field-id");
			var contentIndex = $(this).closest(".child-chunkWrap").attr("data-content-index");

			attachedEditor = $("body")
				.find(".layout")
				.filter(function () {
					var inputName = $(this).find("input").attr("name");
					return inputName && inputName.includes(parentFieldId) && $(this).attr("data-id") === contentIndex;
				});

			var self = this; // Capture the context outside setTimeout

			setTimeout(function () {
				if (!attachedEditor.is(":visible")) {
					var tabs = attachedEditor.closest(".acf-field").siblings(".acf-tab-wrap").find(".acf-tab-group li a");
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
			if ($(this).hasClass("interactive")) {
				return;
			}
			if ($(this).hasClass("showChunkCode")) {
				$(this).closest(".chunkCode").show();
			} 
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

			// Click the builder tab in case we're on a settings tabs
			$(".acf-tab-group li:first-child").click();

			// Scroll to layout
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
					$("body").find("#fullScreenCode").show();
					$("body").find("#generatedHTML").scrollTop(0);
				},
				function (xhr, status, error) {
					// Error Callback
					// Your error handling logic here
				},
				"html" // Data type
			);
		});
	};
});

