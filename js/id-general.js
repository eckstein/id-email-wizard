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
	$(".purchase-attribution").on("change", function () {
		var value = $(this).val();
		idemailwiz_do_ajax(
			"idemailwiz_update_user_attribution_setting",
			idAjax_id_general.nonce,
			{
				value: value,
			},
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
});

//Global scope functions

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
	var campaignId = jQuery(this).attr("data-campaignid");
	idemailwiz_do_ajax(
		"sync_single_triggered_campaign",
		idAjax_id_general.nonce,
		{
			campaignId: campaignId,
		},
		function (result) {
			//success
			console.log("Success: " + result);
			alert("Triggered sync success!");
		},
		function (error) {
			console.log(error);
		}
	);
});

jQuery(document).on("click", ".sync-everything", function () {
	handle_idwiz_sync_buttons("idemailwiz_ajax_sync", idAjax_id_general.nonce, null);
});

function handle_idwiz_sync_buttons(action, passedNonce, data = {}) {
	// Show status updates
	jQuery("#wiztable_status_updates").addClass("active").slideDown();
	jQuery("#wiztable_status_updates .wiztable_update").text("Syncing databases...");

	// Write initialization to log
	idemailwiz_do_ajax(
		"ajax_to_wiz_log",
		idAjax_id_general.nonce,
		{
			log_data: "Initializing database sync. Please wait a few moments...",
			timestamp: true,
		},
		function (result) {
			jQuery("#wiztable_status_sync_details").load(idAjax.plugin_url + "/wiz-log.log");
		},
		function (error) {
			console.log(error);
		}
	);

	// Start refreshing the log
	let refreshInterval = setInterval(() => {
		jQuery("#wiztable_status_sync_details").load(idAjax.plugin_url + "/wiz-log.log");
	}, 3000);

	// Perform the AJAX call
	idemailwiz_do_ajax(
		action,
		passedNonce,
		data,
		function (result) {
			// success callback
			clearInterval(refreshInterval);
			jQuery("#wiztable_status_updates .wiztable_update").text("Sync completed! Refresh the table for new data");
			jQuery("#wiztable_status_sync_details").load(idAjax.plugin_url + "/wiz-log.log");
		},
		function (error) {
			// error callback
			clearInterval(refreshInterval);
			jQuery("#wiztable_status_updates .wiztable_update").text("ERROR: Sync process failed with message: " + JSON.stringify(error));
			jQuery("#wiztable_status_sync_details").load(idAjax.plugin_url + "/wiz-log.log");
		}
	);
}

// Sync log toggle
jQuery(document).on("click", ".wiztable_view_sync_details", function () {
	jQuery("#wiztable_status_sync_details").slideToggle();
	jQuery(this).find("i").toggleClass("fa-chevron-down fa-chevron-up");
});

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

// if (jQuery("#syncLogContent code").length) {
// 	// Auto-refresh a log element
// 	let refreshWizLog = setInterval(() => {
// 		jQuery("#syncLogContent code").load(idAjax.plugin_url + "/wiz-log.log", function () {
// 			hljs.highlightElement(document.querySelector("#syncLogContent code"));
// 		});
// 	}, 3000);
// }

jQuery("#syncStationForm").on("submit", function (e) {
	e.preventDefault();
	var formFields = jQuery(this).serialize();
	jQuery(".syncForm-overlay").addClass("active");
	idemailwiz_do_ajax(
		"idemailwiz_handle_manual_sync",
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
			// wiz log error
			idemailwiz_do_ajax(
				"ajax_to_wiz_log",
				idAjax_id_general.nonce,
				{
					log_data: "There was an error attempting to sync: " + JSON.stringify(error),
				},
				function (result) {
					jQuery("#syncLogContent code").load(idAjax.plugin_url + "/wiz-log.log");
				},
				function (error) {
					console.log(error);
				}
			);
		}
	);
});
