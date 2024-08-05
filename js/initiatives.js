jQuery(document).ready(function ($) {
	// Handle both title and content updates for initiatives
	$(document).on("change", "[data-initUpdateType]", function () {
		const initID = $(".single-idwiz_initiative article").attr("data-initiativeid");
		const value = $(this).val();
		const updateType = $(this).attr("data-initUpdateType");
		const nonceValue = idAjax_initiatives.nonce;

		const additionalData = {
			initID: initID,
			updateContent: value,
			updateType: updateType,
		};

		const successCallback = function (result) {
			console.log(result);
		};

		const errorCallback = function (xhr, status, error) {
			console.log(error);
		};

		idemailwiz_do_ajax("idemailwiz_save_initiative_update", nonceValue, additionalData, successCallback, errorCallback);
	});

	// async load images
	loadTemplatePreviewsAsync('.init-template-preview');

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
		let confirmText = action === "add" ? "Add" : "Remove";

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

	// From the single campaign page, when we want to add this campaign to an initiative
	$(".add-initiative-to-campaign").on("click", function () {
		const action = "add";
		const campaignId = $(this).data("campaignid");
		window.manageCampaignsInInitiative(action, [campaignId], function () {
			location.reload();
		});
	});


	// From the single campaign page, when we want to remove this campaign from an initiative
	$(".remove-initiative-from-campaign").on("click", function () {
		const action = "remove";
		const initiativeId = $(this).data("initid");
		const campaignId = $(this).data("campaignid");
		window.Swal.fire({
			title: "Confirm Removal",
			text: "Are you sure you want to remove this initiative?",
			showCancelButton: true,
			confirmButtonText: "Yes, remove it!",
		}).then((result) => {
			if (result.isConfirmed) {
				window.manageCampaignsInInitiative(
					action,
					[campaignId],
					function () {
						location.reload();
					},
					true,
					initiativeId
				);
			}
		});
	});

	// On the single Initiative page, shows a SWAL2 popup to select campaigns to add to the current Initiative
	async function show_add_campaigns_to_init_ui(initiativeID) {
		const { value: formValues, isConfirmed } = await Swal.fire({
			title: "Select Campaign",
			html: '<select class="swal2-input swalSelect2" id="initCampaignSelect" multiple="multiple"></select>',
			focusConfirm: false,
			showCancelButton: true,
			cancelButtonText: "Cancel",
			preConfirm: () => {
				return $("#initCampaignSelect").val();
			},
			didOpen: () => {
				$("#initCampaignSelect").select2({
					multiple: true,
					ajax: {
						delay: 250,
						transport: function (params, success, failure) {
							idemailwiz_do_ajax(
								"idemailwiz_get_campaigns_for_select",
								idAjax_initiatives.nonce,
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
				"idemailwiz_add_remove_campaign_from_initiative",
				idAjax_initiatives.nonce,
				{
					campaign_ids: formValues,
					initiative_id: initiativeID,
					campaignAction: "add",
				},
				function (response) {
					console.log(response);
					Swal.fire("Success", "Campaign(s) successfully added!", "success").then(() => {
						setTimeout(function () {
							window.location.reload();
						}, 500);
					});
				},
				function (error) {
					console.error("Failed to make call to update function", error);
					Swal.fire("Error", "An error occurred. Check the console for details.", "error");
				}
			);
		}
	}

	
	


	// On the single Initiative, page, handles adding campaigns to the current Initiative
	$(document).on("click", ".add-init-campaign", function () {
		const initiativeID = $(this).data("initiativeid");
		show_add_campaigns_to_init_ui(initiativeID);
	});




	// Delete an initiative from the single initiative page
	$(".remove-single-initiative").on("click", function () {
		const initiativeId = $(this).data("initiativeid"); 
		idwiz_deleteInitiatives([initiativeId], function () {
			// Redirect to /initiatives
			window.location.href = "/initiatives";
		});
	});


	
	// Base template module
	$(".attachBaseTemplate").on("click", function () {
		$("#showAttachBaseTemplate").slideToggle();
	});

	//Date sort plugin
	$.fn.dataTable.moment("x");

	// Initiatives Datatable
	if ($("#idemailwiz_initiatives_table").length) {
		var allInitsTable = $("#idemailwiz_initiatives_table").DataTable({
			dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>rtp',
			columnDefs: [
				{
					targets: [0],
					visible: false,
				},
				{
					targets: [2, 3], // 2nd and 3rd columns
					render: function (data, type, row) {
						if (type === "display") {
							return new Date(parseInt(data) * 1000).toLocaleDateString("en-US", {
								month: "numeric",
								day: "numeric",
								year: "numeric",
							});
						}
						return data; // for sorting, filter, etc.
					},
					type: "num", // treat it as a number for sorting
				},
				
				{
				targets: "dtNumVal",
				type: "num",
				},
			],
			order: [
				[0, "desc"], // sort by hidden fav column in ascending order
				[2, "desc"], // then sort latest send in descending order
			],
			scrollX: true,
			scrollY: true,
			paging: true,
			pageLength: 25,
			select: true,
			fixedHeader: {
				header: true,
				footer: false,
			},
			colReorder: {
				realtime: true,
			},
			language: {
				search: "",
				searchPlaceholder: "Search Initiatives",
			},
			buttons: [
				{
					extend: "selected",
					text: '<i class="fa-solid fa-thumbtack"></i>',
					className: "wiz-dt-button pin-initiative",
					action: function (e, dt, node, config) {
						// Get selected rows
						var selectedRows = dt.rows(".selected").nodes().to$();

						// Array to store Deferred objects
						var ajaxCalls = [];

						var delay = 0; // Initialize delay
						var delayIncrement = 500; // 200 ms delay between each AJAX call

						// Loop through the selected rows and trigger the AJAX function to update the favorite status
						selectedRows.each(function () {
							var postId = $(this).data("initid");
							if (postId) {
								setTimeout(function () {
									var ajaxCall = $.ajax({
										url: idAjax.ajaxurl,
										type: "POST",
										data: {
											action: "add_remove_user_favorite",
											security: idAjax_initiatives.nonce,
											object_id: postId,
											object_type: "Initiative",
										},
									}).done(function (response) {
										console.log("AJAX Response:", response);
									});

									ajaxCalls.push(ajaxCall);
								}, delay);

								delay += delayIncrement; // Increase delay for the next iteration
							}
						});

						// Wait for all AJAX calls to complete
						console.log("Number of AJAX calls:", ajaxCalls.length);
						$.when
							.apply($, ajaxCalls)
							.then(function () {
								Swal.fire({
									icon: "success",
									title: "Favorites are being toggled...",
									text: "The page will refresh once finished!",
									showConfirmButton: false,
									timer: delay,
								});
								setTimeout(function () {
									location.reload();
								}, delay);
							})
							.fail(function () {
								Swal.fire({
									icon: "error",
									title: "Oops...",
									text: "Something went wrong!",
								});
							});
					},
				},
				{
					extend: "collection",
					text: '<i class="fa-solid fa-file-arrow-down"></i>',
					className: "wiz-dt-button",
					attr: {
						title: "Export",
					},
					align: "button-right",
					autoClose: true,
					buttons: ["copy", "csv", "excel"],
					background: false,
				},
				{
					extend: "collection",
					text: '<i class="fa-solid fa-table-columns"></i>',
					className: "wiz-dt-button",
					attr: {
						title: "Show/hide columns",
					},
					align: "button-right",
					buttons: [
						"colvis",
						{
							extend: "colvisRestore",
							text: "Restore Defaults",
							className: "wizcols_restore",
							align: "button-right",
						},
					],
					background: false,
				},

				{
					extend: "pageLength",
					className: "wiz-dt-button",
					background: false,
				},
				{
					extend: "spacer",
					style: "bar",
				},
				{
					extend: "selected",
					text: '<i class="fa-solid fa-box-archive"></i>',
					className: "wiz-dt-button archive-initiatives",
					attr: {
						title: "Archive initiatives",
					},
					action: function (e, dt, node, config) {
						// Get selected rows
						var selectedRows = dt.rows(".selected").nodes().to$();

						// Array to store Deferred objects
						var ajaxCalls = [];

						// Prepare an array to store the initiative IDs
						var initiativeIds = [];

						// Loop through the selected rows and gather the initiative IDs
						selectedRows.each(function () {
							var postId = $(this).data("initid");
							if (postId) {
								initiativeIds.push(postId);
							}
						});

						// Make the AJAX call to toggle the archive status
						var ajaxCall = $.ajax({
							url: idAjax.ajaxurl,
							type: "POST",
							data: {
								action: "idemailwiz_archive_initiative",
								security: idAjax_initiatives.nonce, // Replace this with the actual nonce for the action
								initiativeIds: initiativeIds,
							},
						}).done(function (response) {
							console.log("AJAX Response:", response);
						});

						ajaxCalls.push(ajaxCall);

						// Wait for the AJAX call to complete
						$.when
							.apply($, ajaxCalls)
							.then(function () {
								Swal.fire({
									icon: "success",
									title: "Initiatives are being updated...",
									text: "The page will refresh once finished!",
									showConfirmButton: false,
									timer: 1500,
								});
								setTimeout(function () {
									location.reload();
								}, 1500);
							})
							.fail(function () {
								Swal.fire({
									icon: "error",
									title: "Oops...",
									text: "Something went wrong!",
								});
							});
					},
				},
				{
					extend: "selected",
					text: '<i class="fa-solid fa-trash"></i>',
					className: "wiz-dt-button remove-initiatives",
					attr: {
						title: "Delete initiatives",
					},
				},
			],
			drawCallback: initiative_archive_table_callback,
		});

		function initiative_archive_table_callback() {
			$(".remove-initiatives").on("click", function () {
				const selectedRows = allInitsTable.rows({ selected: true }).nodes().to$();
				const selectedIds = [];

				selectedRows.each(function () {
					const initId = $(this).attr("data-initid");
					if (initId) {
						selectedIds.push(initId);
					}
				});

				idwiz_deleteInitiatives(selectedIds, function () {
					// Turn the row red and then fade out
					selectedRows
						.addClass("removed")
						.delay(2000)
						.fadeOut(400, function () {
							// Remove from DOM
							$(this).remove();
						});
				});
			});
		}
	}

	


	// Initiative campaign table
	if ($(".idwiz-initiative-table").length) {


		var idemailwiz_initiative_campaign_table = $(".idwiz-initiative-table").DataTable({
			dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>rtp',
			columnDefs: [
			
			{ targets: "campaignId", 
				visible: false 
			},
			],
			order: [[1, "desc"]],
			autoWidth: false,
			scrollX: true,
			scrollY: true,
			paging: true,
			pageLength: 25,
			select: true,
			fixedHeader: {
				header: true,
				footer: false,
			},
			colReorder: {
				realtime: true,
			},
			buttons: [
				{
					extend: "collection",
					text: '<i class="fa-solid fa-file-arrow-down"></i>',
					className: "wiz-dt-button",
					attr: {
						title: "Export",
					},
					align: "button-right",
					autoClose: true,
					buttons: ["copy", "csv", "excel"],
					background: false,
				},
				{
					extend: "collection",
					text: '<i class="fa-solid fa-table-columns"></i>',
					className: "wiz-dt-button",
					attr: {
						title: "Show/hide columns",
					},
					align: "button-right",
					buttons: [
						"colvis",
						{
							extend: "colvisRestore",
							text: "Restore Defaults",
							className: "wizcols_restore",
							align: "button-right",
						},
					],
					background: false,
				},

				{
					extend: "pageLength",
					className: "wiz-dt-button",
					background: false,
				},
				{
					extend: "spacer",
					style: "bar",
				},
				{
					extend: "selected",
					text: '<i class="fa-solid fa-trash-can"></i>',
					name: "remove-from-init",
					className: "wiz-dt-button",
					attr: {
						title: "Remove from initiative",
					},
					action: function (e, dt, node, config) {
						// Swal2 Confirm dialog
						Swal.fire({
							title: "Are you sure?",
							text: "Remove these campaigns from the initiative?",
							icon: "warning",
							showCancelButton: true,
							confirmButtonColor: "#3085d6",
							cancelButtonColor: "#d33",
							confirmButtonText: "Yes, remove them!",
						})
							.then((result) => {
								if (result.isConfirmed) {
									// The code for when the user confirms
									let selectedInitiative = $("#content article").attr("data-initiativeid");
									let selectedRowIndices = dt.rows({ selected: true }).indexes().toArray();

									let selectedCampaignIds = selectedRowIndices.map((index) => {
										let rowNode = dt.row(index).node();
										return $(rowNode).attr("data-campaignid") || dt.cell(index, "campaign_id:name").data();
									});

									idemailwiz_do_ajax(
										"idemailwiz_add_remove_campaign_from_initiative",
										idAjax_initiatives.nonce,
										{
											initiative_id: selectedInitiative,
											campaign_ids: selectedCampaignIds,
											campaignAction: "remove",
										},
										function (successData) {
											console.log(successData);
											setTimeout(function () {
												window.location.reload();
											}, 500);
										},
										function (errorData) {
											// Handle error
											console.error("Failed to remove campaigns from initiative", errorData);
											Swal.fire({
												icon: "error",
												title: "Error",
												text: "Error removing campaign(s). Try refreshing the page and trying again.",
											});
										}
									);
								}
							})
							.catch((error) => {
								// Handle any Swal2 errors here
								console.error("Swal2 error:", error);
							});
					},
				},
			],
			language: {
				search: "",
				searchPlaceholder: "Quick search",
			},
			drawCallback: idwiz_initiative_table_callback,
		});

		function idwiz_initiative_table_callback() {
			var api = this.api();

			// Readjust the column widths on each draw
			api.columns.adjust();

			// Sync initiative campaigns
			$(".sync-initiative").on("click", function () {
				//var campaignIds = JSON.parse($('#idemailwiz_initiative_campaign_table').attr('data-campaignids'));
				let campaignIds = $(this).data("initids");
				handle_idwiz_sync_buttons("idemailwiz_ajax_sync", idAjax_initiatives.nonce, { campaignIds: JSON.stringify(campaignIds) });
			});
		}
	}

	



	$(".init_asset_wrap img").on("click", function () {
		var imageUrl = $(this).attr("src");
		var index = $(".init_asset_wrap img").index($(this));
		showImageModal(imageUrl, index);
	});


	function showImageModal(imageUrl, index) {
		var images = $(".init_asset_wrap img")
			.map(function () {
				return $(this).attr("src");
			})
			.get();

		Swal.fire({
			imageUrl: imageUrl,
			width: '1000px',
			imageAlt: "Selected Image",
			showCloseButton: true,
			showDenyButton: index > 0, // Show "Previous" only if not the first image
			showConfirmButton: index < images.length - 1, // Show "Next" only if not the last image
			confirmButtonText: '<i class="fa fa-arrow-right"></i>',
			denyButtonText: '<i class="fa fa-arrow-left"></i>',
			reverseButtons: true,
			showClass: {
				backdrop: "", // disable backdrop animation
				popup: "no-animation-popup", // disable popup animation
				icon: "", // disable icon animation
			},
			hideClass: {
				popup: "", // disable popup fade-out animation
			},

		}).then((result) => {
			if (result.isConfirmed) {
				// Go to next image
				showImageModal(images[index + 1], index + 1);
			} else if (result.isDenied) {
				// Go to previous image
				showImageModal(images[index - 1], index - 1);
			} 
		});
	}

	



});

//Global scope
// Define the function in the global scope

jQuery(".new-initiative").on("click", function () {
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
					console.log(data);
					var postUrl = idAjax_initiatives.site_url + "/?p=" + data.data.post_id;
					window.location.href = postUrl;
				},
				function (error) {
					console.log(error);
					Swal.fire("Error", "An error occurred. Check the console for details.", "error");
				}
			);
		}
	});
});

function idwiz_deleteInitiatives(initiativeIds, onSuccess) {
	// Modify the Swal2 text based on the number of selected initiatives
	const swalTitle = initiativeIds.length > 1 ? "Delete These Initiatives?" : "Delete This Initiative?";
	const swalButton = initiativeIds.length > 1 ? "Yes, delete them" : "Yes, delete it";

	// Show Swal2 confirmation dialog
	Swal.fire({
		title: swalTitle,
		text: "(Campaigns will be preserved)",
		icon: "warning",
		showCancelButton: true,
		confirmButtonText: swalButton,
	}).then((result) => {
		if (result.isConfirmed) {
			// Proceed with the Ajax call to delete the initiatives
			idemailwiz_do_ajax(
				"idemailwiz_delete_initiative",
				idAjax_initiatives.nonce,
				{ selectedIds: initiativeIds },
				function (data) {
					if (data.success) {
						// Call the onSuccess callback if provided
						if (typeof onSuccess === "function") {
							onSuccess();
						}
					}
				},
				function (error) {
					console.log(error);
				}
			);
		}
	});
}
