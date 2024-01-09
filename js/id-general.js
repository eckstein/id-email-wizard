jQuery(document).ready(function ($) {
	// Call toggleOverlay(false) once everything (including images, iframes, scripts, etc.) has finished loading
	$(window).on("load", function () {
		toggleOverlay(false);
	});

	//Function to reload an element in the template folder interface
	(function ($) {
		$.refreshUIelement = function (selector) {
			// Load the content into a container div
			var container = $("<div>").load(location.href + " " + selector + " > *", function () {
				// Replace the contents of the specified element with the new content
				$(selector).html(container.contents());
				//If folder list is visible, reset it to the proper view
				setupCategoriesView();
				//Reinitialize select2 for template search
				initialize_select2_for_template_search();
			});
		};
	})(jQuery);

	//apply highlight to all <code> elements
	hljs.highlightAll();

	// Call setupCategoriesView on page load to show folder list correctly
	setupCategoriesView();

	// Show the template selection modal when needed
	$("body").on("click", ".show-new-template-ui", function () {
		showTemplateSelectionModal();
	});

	// Main function to load the base templates and show the Swal box
	function showTemplateSelectionModal() {
		idemailwiz_do_ajax("idwiz_fetch_base_templates", idAjax_id_general.nonce, {}, handleNewTemplateHtmlSuccess, (response) => {
			Swal.fire("Error", "Could not load templates", "error");
		});
	}

	// Function to handle the success response
	function handleNewTemplateHtmlSuccess(response) {
		if (response.success) {
			const mockupHtml = response.data.html; // HTML is now directly from the server
			showTemplateModal(mockupHtml);

			// Add click event listener to the mockup elements
			$(".startTemplate").click(function () {
				$(".startTemplate").removeClass("selected");
				$(this).addClass("selected");
			});

			// Initialize the tab interface
			$(".templateTabs ul li a").click(function (e) {
				e.preventDefault();
				const tabId = $(this).attr("href").substring(1); // Get the ID without the '#'
				$(".templateTabs ul li a").removeClass("active");
				$(this).addClass("active");
				$(".templateTabs > div").hide(); // Hide all tabs
				$("#" + tabId).css("display", "grid"); // Show the clicked tab
			});

			// Show the first tab by default
			$(".templateTabs ul li a:first").trigger("click");
		} else {
			Swal.fire("Error", "Could not load templates", "error");
		}
	}

	// Function to show the template modal
	function showTemplateModal(mockupHtml) {
		Swal.fire({
			title: "Select a Base Template",
			html: mockupHtml,
			showCancelButton: true,
			confirmButtonText: "Next",
			width: "80%",
			customClass: {
				container: "new-template-popup",
			},
			preConfirm: () => {
				const selectedTemplateId = $(".startTemplate.selected").attr("data-postid");
				if (!selectedTemplateId) {
					Swal.showValidationMessage("Please select a template");
					return;
				}
				return selectedTemplateId;
			},
		}).then(handleTemplateSelection);
	}

	// Function to handle template selection
	function handleTemplateSelection(result) {
		if (result.isConfirmed) {
			const selectedTemplateId = result.value;
			// Show the next Swal for entering the template title
			// This could be another function for cleanliness
			showTemplateTitleInput(selectedTemplateId);
		}
	}

	// Function to show the template title input
	function showTemplateTitleInput(selectedTemplateId) {
		Swal.fire({
			title: "New Template",
			icon: "info",

			confirmButtonText: "Create Template",
			showCancelButton: true,
			cancelButtonText: "Cancel",
			input: "text",
			inputLabel: "Enter a template title",
			inputPlaceholder: "ie: 0876 | Global | VTC Rocks!",
		}).then((inputValue) => {
			if (inputValue.isConfirmed) {
				const template_title = inputValue.value.trim();
				if (template_title.length === 0) {
					Swal.showValidationMessage("Please enter a title for the new template.");
					return;
				}
				const createFromTemplateData = {
					template_action: "create_from_template",
					post_id: selectedTemplateId,
					template_title: template_title,
				};

				idemailwiz_do_ajax(
					"id_ajax_template_actions",
					idAjax_id_general.nonce,
					createFromTemplateData,
					(response) => {
						window.location.href = response.actionResponse.newURL;
					},
					(response) => {
						Swal.fire("Uh oh, something went wrong! Refresh and try again maybe?", { icon: "error" });
					}
				);
			}
		});
	}

	// Stop click events within the popover from propagating
	$("#dt-popover-container").on("click", function (e) {
		e.stopPropagation();
	});

	// Stop click events within the Select2 dropdown from propagating
	$(document).on("click", ".select2-dropdown", function (e) {
		e.stopPropagation();
	});

	// jQuery click handler for the settings button
	$(".module-settings").on("click", function (event) {
		// Prevent the click on the button from propagating to the document
		event.stopPropagation();

		// Toggle the display of the dropdown menu
		$("#module-settings-dropdown")
			.toggle()
			.css({
				position: "absolute",
				right: 0,
				top: $(this).outerHeight() + 5,
			});
	});

	// Close the dropdown menu if the user clicks outside of it
	$(document).on("click", function () {
		$("#module-settings-dropdown").hide();
	});

	// Stop propagation for clicks within the dropdown to prevent it from closing
	$("#module-settings-dropdown").on("click", function (event) {
		event.stopPropagation();
	});

	//Attribution form change
	$("#attribution-settings-form").on("change", "input, select, textarea", function () {
		var field = $(this).attr("name");
		var value = $(this).val();

		idemailwiz_do_ajax(
			"idemailwiz_update_user_attribution_setting",
			idAjax_id_general.nonce,
			{ field, value },
			function (data) {
				location.reload();
			},
			function (error) {
				console.error("Failed to update attribution settings", error);
				failure();
			}
		);
	});

	enableDragScrolling(".idwiz-dragScroll");

	function enableDragScrolling(selector) {
		var $element = $(selector);

		// Check if the element exists
		if ($element.length) {
			var isDown = false;
			var startX;
			var scrollLeft;

			$element.on("mousedown", function (e) {
				isDown = true;
				$element.addClass("active");
				startX = e.pageX - $element.offset().left;
				scrollLeft = $element.scrollLeft();
			});

			$(document).on("mouseup", function () {
				isDown = false;
				$element.removeClass("active");
			});

			$(document).on("mouseleave", function () {
				if (isDown) {
					isDown = false;
					$element.removeClass("active");
				}
			});

			$element.on("mousemove", function (e) {
				if (!isDown) return;
				e.preventDefault();
				var x = e.pageX - $element.offset().left;
				var walk = (x - startX) * 1; // Adjust the multiplier for sensitivity
				$element.scrollLeft(scrollLeft - walk);
			});
		}
	}

	$(".month-year-select")
		.select2({
			allowClear: true,
			placeholder: "Go to Month",
		})
		.on("select2:select", function (e) {
			// Get the selected value which is in 'Y-m' format
			var selectedMonthYear = e.params.data.id;

			// Parse year and month from the selected value
			var [year, month] = selectedMonthYear.split("-").map(Number);

			// Calculate the startDate (first day of the month)
			var startDate = new Date(year, month - 1, 1).toISOString().split("T")[0];

			// Calculate the endDate (last day of the month)
			var endDate = new Date(year, month, 0).toISOString().split("T")[0];

			// Construct the query parameters
			var queryParams = new URLSearchParams(window.location.search);
			queryParams.set("startDate", startDate);
			queryParams.set("endDate", endDate);

			// Construct the new URL with the updated query parameters
			var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?" + queryParams.toString();

			// Update the browser's location to the new URL and reload the page
			window.location.href = newUrl;
		});

	// Handle both title and content updates for initiatives
	$(document).on("change", ".editableTitle", function () {
		const itemId = $(this).attr("data-itemid");
		const value = $(this).val();
		const updateType = $(this).attr("data-updatetype");
		const nonceValue = idAjax_id_general.nonce;

		const additionalData = {
			itemId: itemId,
			updateContent: value,
			updateType: updateType,
		};

		const successCallback = function (result) {
			console.log(result);
		};

		const errorCallback = function (xhr, status, error) {
			console.log(error);
		};

		idemailwiz_do_ajax("idemailwiz_ajax_save_item_update", nonceValue, additionalData, successCallback, errorCallback);
	});

	function regenerateTemplatePreview(templateId, nonce, onSuccess) {
		idemailwiz_do_ajax(
			"regenerate_template_preview",
			nonce,
			{ templateIds: [templateId] },
			function(response) {
				if (response.success && response.data && response.data[templateId]) {
					var newImageUrl = response.data[templateId] + "?t=" + new Date().getTime();
					if (typeof onSuccess === 'function') {
						onSuccess(newImageUrl);
					}
				}
			},
			function(xhr, status, error) {
				console.error("Error regenerating preview:", error);
			}
		);
	}

	
	$(document).on("click", ".regenerate-preview", function() {
		var templateId = $(this).data("templateid");
		var nonce = idAjax_id_general.nonce; 
		var previewContainer = $('.template-image-wrapper[data-templateid="' + templateId + '"]');
		var spinnerWrapper = previewContainer.find(".wiztemplate-image-spinner");

		spinnerWrapper.show();

		regenerateTemplatePreview(templateId, nonce, function(newImageUrl) {
			var imageElement = previewContainer.find("img").length ?
							   previewContainer.find("img") :
							   $("<img>").appendTo(previewContainer);

			imageElement.attr("src", newImageUrl)
				.on("load", function() {
					spinnerWrapper.hide();
					$(previewContainer).find(".template-preview-missing-message").hide();
					do_wiz_notif({message: 'Template preview re-generated!', duration: 3000 });
				})
				.on("error", function() {
					spinnerWrapper.hide();
					alert("Failed to load image.");
				});
		});
	});


	$(document).on("click", ".wiztemplate-preview", function () {
		var image = $(this).find("img").clone();

		// Create lightbox structure with close button
		var lightbox = $('<div class="campaign-template-lightbox">' + '<div class="lightbox-wrapper">' + '<div class="campaign-template-lightbox-content"></div>' + '<span class="lightbox-close"><i class="fa-solid fa-xmark"></i></span>' + "</div>" + "</div>");

		// Append image and add click event for closing
		lightbox.find(".campaign-template-lightbox-content").append(image);
		lightbox.appendTo("body").fadeIn();
		$("body").addClass("no-scroll"); // Disable scrolling on the main page

		// Function to close lightbox and re-enable scrolling
		function closeLightbox() {
			lightbox.fadeOut(function () {
				lightbox.remove();
				$("body").removeClass("no-scroll"); // Re-enable scrolling on the main page
			});
		}

		// Close functionality
		lightbox.find(".lightbox-close").click(closeLightbox);

		// Close lightbox when clicking outside the image
		lightbox.click(function (event) {
			if (!$(event.target).closest(".campaign-template-lightbox-content").length) {
				closeLightbox();
			}
		});
	});

});



//Global scope functions

function loadCompareImagesAsync(imgElement) {
	jQuery(imgElement).each(function () {
		var $img = jQuery(this);
		var imgSrc = $img.data("src");
		var templateId = $img.data("templateid");

		if (imgSrc) {

			jQuery("<img>")
			.on("load", function () {
				$img.attr("src", imgSrc).show();
				$img.siblings(".wiztemplate-image-spinner").hide();
			})
			.attr("src", imgSrc);

		} else {
			$img.siblings(".wiztemplate-image-spinner").hide();
			$img.parent().html("<div class='compare-campaign-missing-preview' style='padding: 20px; font-size: 12px; color: #343434;'>No template image available yet.<br/><button class='wiz-button green regenerate-compare-campaign-preview' data-templateid='" + templateId + "'><i class='fa-regular fa-file-image'></i>&nbsp;Generate Preview</button></div>");
		}
	});
}

function initialize_select2_for_template_search() {
	jQuery("#live-template-search")
		.select2({
			minimumInputLength: 3,
			placeholder: "Search templates...",
			allowClear: true,
			ajax: {
				delay: 250,
				transport: function (params, success, failure) {
					idemailwiz_do_ajax(
						"idemailwiz_get_templates_for_select",
						idAjax_id_general.nonce,
						{
							q: params.data.term,
						},
						function (data) {
							success({ results: data });
						},
						function (error) {
							console.error("Failed to fetch templates", error);
							failure();
						}
					);
				},
			},
		})
		.on("select2:select", function (e) {
			var data = e.params.data;
			if (data.id) {
				var postUrl = idAjax_id_general.site_url + "/?p=" + data.id;
				window.location.href = postUrl;
			}
		});
}

function setupCategoriesView() {
	if (jQuery(".folderList").is(":visible")) {
		// Close all sub-categories initially
		jQuery(".sub-categories").hide();

		// Set the arrow icons for all categories to point down
		jQuery(".showHideSubs").removeClass("fa-angle-up").addClass("fa-angle-down");

		// Open the first top-level root folder by default
		jQuery(".cat-item").first().addClass("open").children(".sub-categories").show();
		jQuery(".cat-item.open").find("> .showHideSubs").removeClass("fa-angle-down").addClass("fa-angle-up");

		// Set current-cat and its direct parent categories to be expanded
		jQuery(".current-cat").parents(".cat-item").addClass("open").children(".sub-categories").show();
		jQuery(".current-cat, .current-cat").parents(".cat-item").find("> .showHideSubs").removeClass("fa-angle-down").addClass("fa-angle-up");

		// Show sub-categories of the current-cat if they exist
		jQuery(".current-cat").children(".sub-categories").show();
		jQuery(".current-cat").find("> .showHideSubs").removeClass("fa-angle-down").addClass("fa-angle-up");
	}
}

// A generalized Ajax call.
// Params: action_name, nonce_value, array of passed data, success callback, error callback
// The callback functions can either be directly built in the function or can also take names of
// callback functions which will get the data and error objects passed into them for use
// This function can handle  calls that rely on await, or not
function idemailwiz_do_ajax(actionFunctionName, nonceValue, additionalData, successCallback, errorCallback, dataType = "json") {
	return new Promise((resolve, reject) => {
		let defaultData = {
			action: actionFunctionName,
			security: nonceValue,
		};

		let mergedData = Object.assign({}, defaultData, additionalData);

		jQuery
			.ajax({
				url: idAjax.ajaxurl,
				context: this,
				type: "POST",
				data: mergedData,
				dataType: dataType,
			})
			.done((response) => {
				if (successCallback) {
					successCallback(response);
				}
				resolve(response);
			})
			.fail((xhr, status, error) => {
				if (errorCallback) {
					errorCallback(xhr, status, error);
				}
				reject({ xhr, status, error });
			})
			.always(function () {
				// Always executed regardless of response
			});
	});
}

// Even more global function to reload and element, when passed one
function wizReloadThing(selector) {
	var element = jQuery(selector);

	if (element.length) {
		// Fetch the current page's content
		jQuery.ajax({
			url: window.location.href,
			type: "GET",
			success: function (data) {
				// Replace the element's content with the content from the fetched page
				var updatedContent = jQuery(selector, data).html();
				element.html(updatedContent);
			},
			error: function () {
				console.error("Failed to reload element:", selector);
			},
		});
	} else {
		console.warn("Element not found:", selector);
	}
}

jQuery(document).on("click", ".sync-single-triggered", function () {
	var $thisButton = jQuery(this);

	var campaignId = $thisButton.attr("data-campaignid");
	var startDate = $thisButton.attr("data-start-date");
	var endDate = $thisButton.attr("data-end-date");

	$thisButton.addClass("disabled");
	$thisButton.data("original-text", $thisButton.html());
	$thisButton.html('<i class="fa-solid fa-arrows-rotate fa-spin"></i>&nbsp;&nbsp;Syncing...');
	var syncStationUrl = idAjax_id_general.site_url + "/sync-station";
	do_wiz_notif({
		message: "Syncing data from " + moment(startDate, 'YYYY-MM-DD').format('M/D/YYYY') + " to " + moment(endDate, 'YYYY-MM-DD').format('M/D/YYYY') + "... <a href='" + syncStationUrl + "'>View sync log</a>",
		duration: 10000
	});


	idemailwiz_do_ajax(
		"idemailwiz_manual_export_request_ajax",
		idAjax_id_general.nonce,
		{
			campaignId: campaignId,
			startDate: startDate,
			endDate: endDate,
		},
		function (result) {
			// success
			console.log("Success: ", result.data);
    
			$thisButton.html($thisButton.data("original-text")).removeClass("disabled");
    
			

			Swal.fire({
				title: "Sync Complete",
				html: 'Check the <a target="_blank" href="'+idAjax_id_general.site_url+'/sync-station">sync log</a> for details.',
				icon: "success",
			});

		},
		function (error) {
			console.log(error);
			// Reset the button's HTML content to its original state and remove the disabled class
			$thisButton.html($thisButton.data("original-text")).removeClass("disabled");
		}
	);
});

jQuery(document).on("click", ".doWizSync:not(.disabled)", function () {
	var $thisButton = jQuery(this);
	var metricTypes = $thisButton.attr("data-metricTypes");
	var campaignIds = $thisButton.attr("data-campaignIds");

	// Convert the data attributes to arrays if they are JSON strings
	metricTypes = metricTypes ? JSON.parse(metricTypes) : ["blast"];
	campaignIds = campaignIds ? JSON.parse(campaignIds) : [];

	// Disable the button, change its text, and add the spinner class to the icon
	$thisButton.addClass("disabled");
	$thisButton.data("original-text", $thisButton.html());
	$thisButton.html('<i class="fa-solid fa-arrows-rotate fa-spin"></i>&nbsp;&nbsp;Syncing...');

	// Call the function to handle the sync process
	handle_idwiz_sync_buttons(metricTypes, campaignIds, $thisButton);
});

function handle_idwiz_sync_buttons(metricTypes, campaignIds, $button) {
	// Perform the AJAX call
	var syncStationUrl = idAjax_id_general.site_url + "/sync-station";
	do_wiz_notif({ message: "Sync initiated! <a href='" + syncStationUrl + "'>View sync log</a>", duration: 10000 });
	idemailwiz_do_ajax(
		"idemailwiz_ajax_sync",
		idAjax_id_general.nonce,
		{
			metricTypes: JSON.stringify(metricTypes), // Pass arrays as JSON strings
			campaignIds: JSON.stringify(campaignIds),
		},
		function (response) {
			// success callback
			// Reset the button's HTML content to its original state and remove the disabled class
			$button.html($button.data("original-text")).removeClass("disabled");

			if (response.success) {
				Swal.fire({
					title: "Success!",
					text: "The sync sequence has finished successfully.",
					icon: "success",
				});
			} else {
				Swal.fire({
					title: "Error!",
					text: response.data.error || "Unknown error.",
					icon: "error",
				});
			}
		},
		function (xhr, status, error) {
			// error callback
			// Reset the button's HTML content to its original state and remove the disabled class
			$button.html($button.data("original-text")).removeClass("disabled");

			Swal.fire({
				title: "Error!",
				text: error || "There was an error completing the sync sequence.",
				icon: "error",
			});
		}
	);
}

// Add or remove initiatives from one or more campaigns
window.manageCampaignsInInitiative = function (action, campaignIds, onSuccess = null, skipInitiativeSelection = false, initiativeId = null) {
	const performAction = (initiativeId) => {
		// Perform AJAX call to manage campaigns in the selected initiative
		idemailwiz_do_ajax(
			"idemailwiz_add_remove_campaign_from_initiative",
			idAjax_data_tables.nonce,
			{
				initiative_id: initiativeId,
				campaign_ids: campaignIds,
				campaignAction: action,
			},
			function (response) {
				var confirmMessage = "Campaign(s) successfully added to initiative!";
				if (response.data.action == "remove") {
					var confirmMessage = "Campaign(s) successfully removed from initiative!";
				}

				window.Swal.fire({
					icon: "success",
					title: "Success",
					text: confirmMessage,
				}).then(() => {
					if (onSuccess) {
						onSuccess();
					}
				});
			},
			function (errorData) {
				// Handle error
				console.error(`Failed to ${action} campaigns to initiative`, errorData);
			}
		);
	};

	// If skipping initiative selection, perform the action immediately
	if (skipInitiativeSelection) {
		performAction(initiativeId);
		return;
	}

	// Determine the action and title based on the action parameter
	let titleText = action === "add" ? "Add to Initiative" : "Remove from Initiative";
	let confirmText = action === "add" ? "Add campaigns" : "Remove campaigns";

	const swalConfig = {
		title: titleText,
		html: '<select id="initiative-select"></select><br/>- or -<br/><button id="create-new-initiative" class="wiz-button green" type="button">Create New Initiative</button>',
		showCancelButton: true,
		cancelButtonText: "Cancel",
		confirmButtonText: confirmText,
		preConfirm: () => {
			let selectedInitiative = jQuery("#initiative-select").val();
			performAction(selectedInitiative);
		},
	};

	// Conditionally add the 'didOpen' callback if we need to select an initiative
	if (!skipInitiativeSelection) {
		swalConfig.didOpen = () => {
			jQuery("#initiative-select").select2({
				minimumInputLength: 0,
				placeholder: "Search initiatives...",
				allowClear: true,
				ajax: {
					delay: 250,
					transport: function (params, success, failure) {
						idemailwiz_do_ajax(
							"idemailwiz_get_initiatives_for_select",
							idAjax_data_tables.nonce,
							{
								q: params.data.term,
							},
							function (data) {
								success({ results: data });
							},
							function (error) {
								console.error("Failed to fetch initiatives", error);
								failure();
							}
						);
					},
				},
			});

			jQuery("#create-new-initiative").on("click", function () {
				Swal.fire({
					title: "Create New Initiative",
					input: "text",
					inputPlaceholder: "Enter initiative title...",
					showCancelButton: true,
					cancelButtonText: "Cancel",
					confirmButtonText: "Create",
				}).then((result) => {
					if (result.isConfirmed) {
						const title = result.value;
						idemailwiz_do_ajax(
							"idemailwiz_create_new_initiative",
							idAjax_initiatives.nonce,
							{ newInitTitle: title },
							function (data) {
								// After creating the new initiative, perform the initial action (add/remove campaigns)
								performAction(data.data.post_id);
							},
							function (error) {
								console.log(error);
								Swal.fire("Error", "An error occurred. Check the console for details.", "error");
							}
						);
					}
				});
			});
		};
	}

	window.Swal.fire(swalConfig);
};

// Auto-refresh sync log
if (jQuery("#syncLogContent code").length) {
	setInterval(function () {
		idemailwiz_do_ajax(
			"refresh_wiz_log",
			idAjax_id_general.nonce,
			{},
			function (response) {
				if (response.success) {
					jQuery("#syncLogContent code").text(response.data);
				}
			},
			function (error) {
				// Error
				console.log("Error refreshing log:", error);
			}
		);
	}, 3000);
}

// Manual sync form submission
jQuery("#syncStationForm").on("submit", function (e) {
	e.preventDefault();
	var formFields = jQuery(this).serialize();
	jQuery(".syncForm-overlay").addClass("active");
	idemailwiz_do_ajax(
		"handle_sync_station_sync",
		idAjax_id_general.nonce,
		{ formFields },
		function (data) {
			// After sync, we do stuff
			jQuery(".syncForm-overlay").removeClass("active");
			Swal.fire("Sync Successful", "The manual sync has completed. See sync log for details.", "success");
		},
		function (error) {
			console.log(error);
			Swal.fire("Error", "An error occurred. Check the sync log or browser console for details.", "error");

			// Prepare a detailed error message
			var errorMessage = "Ajax error: " + error.status + " " + error.statusText;
			if (error.responseJSON && error.responseJSON.data) {
				errorMessage += " - " + error.responseJSON.data;
			}

			// Log the detailed error message
			idemailwiz_do_ajax(
				"ajax_to_wiz_log",
				idAjax_id_general.nonce,
				{ log_data: errorMessage },
				function (result) {
					jQuery("#syncLogContent code").load(idAjax.plugin_url + "/wiz-log.log");
				},
				function (error) {
					console.log("Error logging to wiz_log: ", error);
				}
			);
		}
	);
});

// Generates and displays a notification
// Example: do_wiz_notif({ message: 'You clicked a thing.', duration: 3000 });
function do_wiz_notif(notifData) {
	// Create the notification elements
	var notif = jQuery("<div>", { class: "wizNotif" });
	var icon = jQuery("<div>", { class: "wizNotifIcon" }).append(jQuery("<i>", { class: "fa fa-bell" }));
	var content = jQuery("<div>", { class: "wizNotifContent" }).html(notifData.message);
	var close = jQuery("<i>", { class: "fa fa-times wizNotifClose" });

	// Assemble the notification
	notif.append(icon, content, close);

	// Append to the container
	jQuery(".wizNotifs").prepend(notif);

	// Set a timeout to automatically remove the notification after a certain period
	setTimeout(function () {
		notif.fadeOut(function () {
			jQuery(this).remove();
		});
	}, notifData.duration || 5000); // Default duration to 5 seconds if not specified

	// Handle the click event on the close button
	close.on("click", function () {
		notif.fadeOut(function () {
			jQuery(this).remove();
		});
	});
}

jQuery(document).on("click", ".add-course", function () {
	var courseId = jQuery(this).data("course-id");
	var recType = jQuery(this).data("rec-type");
	var division = jQuery(this).closest(".course-recs").data("division");

	Swal.fire({
		title: "Add Course",
		html: '<select id="selectCourse" style="width: 100%;"><option value="">Select a course</option></select>',
		showCancelButton: true,
		confirmButtonText: "Add Course",
		didOpen: function () {
			// Initialize Select2
			jQuery("#selectCourse").select2({
				dropdownParent: jQuery(".swal2-container"),
				width: "100%",
				placeholder: "Select a course",
				ajax: {
					transport: function (params, success, failure) {
						idemailwiz_do_ajax(
							"id_get_courses_options",
							idAjax_id_general.nonce,
							{
								term: params.data.term, // Pass the search term for filtering courses
								division: division,
							},
							function (response) {
								success(response);
							},
							function (error) {
								failure(error);
							}
						);
					},
					processResults: function (data) {
						// Check if data is in expected format, if not, handle appropriately
						if (!Array.isArray(data) && data.success && Array.isArray(data.data)) {
							// If data is wrapped in an object with 'data' key
							return { results: data.data };
						} else if (Array.isArray(data)) {
							// If data is a plain array
							return { results: data };
						} else {
							console.error("Unexpected data format received for Select2: ", data);
							return { results: [] };
						}
					},
				},
			});
		},
		preConfirm: function () {
			return new Promise(function (resolve) {
				resolve({
					course_id: courseId,
					rec_type: recType,
					selected_course: jQuery("#selectCourse").val(),
				});
			});
		},
	}).then(function (result) {
		if (result.isConfirmed && result.value.selected_course) {
			idemailwiz_do_ajax(
				"id_add_course_to_rec",
				idAjax_id_general.nonce,
				{
					course_id: result.value.course_id,
					rec_type: result.value.rec_type,
					selected_course: result.value.selected_course,
				},
				function (response) {
					Swal.fire({
						title: "Course Added!",
						icon: "success",
					}).then(function () {
						location.reload();
					});
				},
				function (error) {
					console.log("Error: ", error);
				}
			);
		}
	});
});

jQuery(document).on("click", ".remove-course", function () {
	var recdCourseId = jQuery(this).closest(".course-blob").data("recd-course");
	var courseId = jQuery(this).closest("td").data("course-id");
	var recType = jQuery(this).closest("td").data("rec-type");

	Swal.fire({
		title: "Are you sure?",
		text: "Do you really want to remove this course?",
		icon: "warning",
		showCancelButton: true,
		confirmButtonText: "Yes, remove it!",
		cancelButtonText: "No, cancel!",
	}).then((result) => {
		if (result.isConfirmed) {
			// AJAX call to remove the course
			idemailwiz_do_ajax(
				"id_remove_course_from_rec",
				idAjax_id_general.nonce,
				{
					course_id: courseId,
					rec_type: recType,
					recd_course_id: recdCourseId,
				},
				function (response) {
					Swal.fire({
						title: "Removed!",
						text: "The course has been removed.",
						icon: "success",
					}).then(function () {
						location.reload();
					});
				},
				function (error) {
					console.log("Error: ", error);
				}
			);
		}
	});
});

// Fetch and fill the rollup summary
function fetchRollUpSummaryData(campaignIds, startDate, endDate, rollupSelector, includeMetrics=[], excludeMetrics=[]) {
	console.log('Fetching rollup data');
	var $rollupElement = jQuery(document).find(rollupSelector);

	var rollupElementId;
	if ($rollupElement[0]) {
		rollupElementId = $rollupElement[0].id;
	} else {
		rollupElementId = $rollupElement.attr("id");
	}

	const rollupData = {
		campaignIds,
		rollupElementId,
		startDate,
		endDate,
		includeMetrics,
		excludeMetrics
	};
	
	idemailwiz_do_ajax("idwiz_generate_dynamic_rollup", idAjax_id_general.nonce, rollupData, getRollupSuccess, getRollupError, "html");

	function getRollupSuccess(response) {
		console.log("Rollup metrics success");
		if (response != 0) {
			$rollupElement.replaceWith(response);
		}
	}

	function getRollupError(response) {
		console.log("Rollup metrics error: " + response);
	}
}
