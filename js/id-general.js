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
				if (jQuery(".folderList").length > 0) {
					setupCategoriesView();
				}
				//Reinitialize select2 for template search
				initialize_select2_for_template_search();
			});
		};
	})(jQuery);

	//apply highlight to all <code> elements
	hljs.highlightAll();

	// Call setupCategoriesView on page load to show folder list correctly
	if (jQuery(".folderList").length > 0) {
		setupCategoriesView();
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
			do_wiz_notif({message:"Title updated", duration: 3000});
		};

		const errorCallback = function (xhr, status, error) {
			do_wiz_notif({message:"Error: Title was not updated!", duration: 3000});
		};

		idemailwiz_do_ajax("idemailwiz_ajax_save_item_update", nonceValue, additionalData, successCallback, errorCallback);
	});

	

	
	$(document).on("click", ".regenerate-template-preview", function() {
		var templateId = $(this).data("templateid");
		var nonce = idAjax_id_general.nonce;
		var previewContainer = $('[data-templateid="' + templateId + '"]').closest('.template-image-wrapper, .compare-template-preview');
		var spinnerWrapper = previewContainer.find(".wiztemplate-image-spinner");

		spinnerWrapper.show();

		regenerateTemplatePreview(templateId, nonce, function(newImageUrl) {
			updateTemplatePreviewImageElement(previewContainer, newImageUrl, spinnerWrapper);
			do_wiz_notif({message: 'Template preview regenerated!', duration: 3000 });
		});
	});

	



	$(document).on("click", ".wiztemplate-preview", function (e) {
		// Check if the clicked element or any of its parents have the specific class
		if ($(e.target).closest('.regenerate-template-preview').length) {
			return false; // Do nothing if the clicked element is the specified class or a child of it
		}

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

	if ($(".idwiz-campaign-monitor-table").length || $(".idwiz-purchase-monitor-table").length) {
		$.fn.dataTable.moment("x");

		var dtDom = '<"#wiztable_top_wrapper"><"wiztable_toolbar">rtp';

		if ($(".idwiz-campaign-monitor-table").length) {
			$(".idwiz-campaign-monitor-table").DataTable({
				dom: dtDom,
				pageLength: 50,
				order: [
					[1, "desc"],
				],
				columnDefs: [{
					targets: 1, // Targeting the 2nd column
					render: function(data, type, row) {
						if (type === 'display' && data !== null && data !== '') {
							// Assuming the data is in milliseconds
							var date = new Date(parseInt(data * 1000));
							return date.toLocaleString('en-US', {
								month: 'numeric',
								day: 'numeric',
								year: 'numeric',
								hour: '2-digit',
								minute: '2-digit',
							});
						}
						return data; // Return data as is for sorting and filtering
					},
					type: 'num', // Ensures that numeric sorting is applied
				}]
			});
		}


		

		if ($(".idwiz-purchase-monitor-table").length) {
			$(".idwiz-purchase-monitor-table").DataTable({
				dom: dtDom,
				pageLength: 25,
				order: [
					[0, "desc"],
				],
				columnDefs: [{
					targets: 0, // Targeting the 2nd column
					render: function(data, type, row) {
						if (type === 'display' && data !== null && data !== '') {
							// Assuming the data is in milliseconds
							var date = new Date(parseInt(data * 1000));
							return date.toLocaleString('en-US', {
								month: 'numeric',
								day: 'numeric',
								year: 'numeric',
								hour: '2-digit',
								minute: '2-digit',
							});
						}
						return data; // Return data as is for sorting and filtering
					},
					type: 'num', // Ensures that numeric sorting is applied
				}]
			});
		}
	}


	// Standard tab system
	$('.wizcampaign-section-tabs ul li').click(function() {
		var tabId = $(this).data('tab');
		var paneId = $(this).closest('.wizcampaign-section-tabs').data('pane');

		$(this).addClass('active').siblings().removeClass('active');
		$('#' + paneId + ' .wizcampagn-section-tab-content').removeClass('active');
		$('#' + tabId).addClass('active');
	  });
	

});

function updateTemplatePreviewImageElement(previewContainer, newImageUrl, spinnerWrapper) {
	var imageElement = previewContainer.find("img").length ?
					   previewContainer.find("img") :
					   jQuery("<img>").appendTo(previewContainer);

	imageElement.attr("src", newImageUrl)
		.on("load", function() {
			spinnerWrapper.hide();
			previewContainer.find(".template-preview-missing-message, .compare-campaign-missing-preview").hide();
			
		})
		.on("error", function() {
			spinnerWrapper.hide();
			alert("Failed to load image.");
		});
}

//Global scope functions

function loadTemplatePreviewsAsync(wrapperElements) {
	jQuery(wrapperElements).each(function () {
		var wrapper = jQuery(this);
		var imgElements = wrapper.find('img');

		imgElements.each(function () {
			var $img = jQuery(this);
			var imgSrc = $img.data("src");
			var templateId = $img.data("templateid");
			var spinnerWrapper = $img.siblings(".wiztemplate-image-spinner");

			if (imgSrc) {
				checkImageUrl(imgSrc, function(isValid) {
					if (isValid) {
						updateTemplatePreviewImageElement(wrapper, imgSrc, spinnerWrapper);
					} else {
						displayTemplateErrors(wrapper, templateId, "Template image is invalid!");
					}
				});
			} else {
				displayTemplateErrors(wrapper, templateId, "No template image available yet.");
			}
		});
	});
}


function displayTemplateErrors(wrapperElement, templateId, message) {
	var errorMessageHtml = "<div class='compare-campaign-missing-preview' style='padding: 20px; font-size: 12px; color: #343434;'>" + 
						   message +
						   "<div class='wiztemplate-image-spinner hide'><i class='fa-solid fa-spin fa-spinner fa-3x'></i></div><br/><button class='wiz-button green regenerate-template-preview' data-templateid='" + templateId + "'>" +
						   "<i class='fa-regular fa-file-image'></i>&nbsp;Generate Preview</button></div>";

	jQuery(wrapperElement).html(errorMessageHtml);
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

// A generalized Ajax call.
// Params: action_name, nonce_value, array of passed data, success callback, error callback
// The callback functions can either be directly built in the function or can also take names of
// callback functions which will get the data and error objects passed into them for use
// This function can handle  calls that rely on await, or not
function idemailwiz_do_ajax(actionFunctionName, nonceValue, additionalData, successCallback, errorCallback, dataType = "json", contentType = "application/x-www-form-urlencoded; charset=UTF-8") {
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
				contentType: contentType,
				
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
		"handle_single_triggered_sync",
		idAjax_id_general.nonce,
		{
			campaignId: campaignId,
			startDate: startDate,
			endDate: endDate,
		},
		function (result) {
			// success
			//console.log("Success: ", result.data);
    
			$thisButton.html($thisButton.data("original-text")).removeClass("disabled");
			do_wiz_notif({
				message: 'Sync queued! Check the <a target="_blank" href="'+idAjax_id_general.site_url+'/sync-station">sync log</a> for details.',
				duration: 10000
			});
			// Swal.fire({
			// 	title: "Sync Queued!",
			// 	html: 'Check the <a target="_blank" href="'+idAjax_id_general.site_url+'/sync-station">sync log</a> for details.',
			// 	icon: "success",
			// });

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
					confirmButtonText: 'OK'
				}).then((result) => {
					if (result.isConfirmed) {
						location.reload();
					}
				})

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
			Swal.fire("Sync Successful", "The manual sync has been initiated. See sync log for progress.", "success");
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

function checkImageUrl(url, callback) {
	jQuery.get(url)
		.done(function() {
			callback(true); // Image is valid
		})
		.fail(function() {
			callback(false); // Image is not valid
		});
}

// Main click handler to decide campaign action
jQuery(document).on("click", ".connect-campaigns", function () {
	var thisCampaignId = jQuery(this).attr('data-campaignid');
	connectCampaigns(thisCampaignId);
});

// Click handler for disconnecting a campaign
jQuery(document).on("click", ".disconnect-campaign", function (event) {
	event.preventDefault();
	var campaignId = jQuery(this).attr("data-remove-from");
	var campaignToDisconnectId = jQuery(this).attr("data-campaignid");
	disconnectCampaign(campaignId, campaignToDisconnectId);
});

async function connectCampaigns(campaignId) {
	const { value: formValues, isConfirmed } = await Swal.fire({
		title: "Select Campaigns",
		html: '<select class="swal2-input swalSelect2" id="campaignSelect" multiple="multiple"></select>',
		focusConfirm: false,
		showCancelButton: true,
		cancelButtonText: "Cancel",
		preConfirm: () => {
			return jQuery("#campaignSelect").val();
		},
		didOpen: () => {
			jQuery("#campaignSelect").select2({
				multiple: true,
				ajax: {
					delay: 250,
					transport: function (params, success, failure) {
						idemailwiz_do_ajax(
							"idemailwiz_get_campaigns_for_select",
							idAjax_id_general.nonce,
							{
								q: params.data.term,
							},
							function (data) {
								success({ results: data });
							},
							failure
						);
					},
				},
			});
		},
	});

	if (isConfirmed && formValues) {
		idemailwiz_do_ajax(
			"idemailwiz_connect_campaigns",
			idAjax_id_general.nonce,
			{
				campaign_id: campaignId,
				campaign_to_connect_ids: formValues,
				connect_action: "add",
			},
			function (response) {
				console.log(response);
				Swal.fire("Success", "Campaign(s) successfully connected!", "success").then(() => {
					setTimeout(function () {
						window.location.reload();
					}, 500);
				});
			},
			function (error) {
				console.error("Failed to make call to connect campaigns", error);
				Swal.fire("Error", "An error occurred. Check the console for details.", "error");
			}
		);
	}
}

async function disconnectCampaign(campaignId, campaignToDisconnectId) {
	const result = await Swal.fire({
		title: "Confirm Disconnection",
		text: "Are you sure you want to disconnect this campaign?",
		icon: "warning",
		showCancelButton: true,
		confirmButtonText: "Yes, disconnect",
		cancelButtonText: "Cancel"
	});

	if (result.isConfirmed) {
		idemailwiz_do_ajax(
			"idemailwiz_connect_campaigns",
			idAjax_id_general.nonce,
			{
				campaign_id: campaignId,
				campaign_to_connect_ids: [campaignToDisconnectId],
				connect_action: "remove",
			},
			function (response) {
				console.log(response);
				Swal.fire("Success", "Campaign successfully disconnected!", "success").then(() => {
					setTimeout(function () {
						window.location.reload();
					}, 500);
				});
			},
			function (error) {
				console.error("Failed to make call to disconnect campaign", error);
				Swal.fire("Error", "An error occurred. Check the console for details.", "error");
			}
		);
	}
}
