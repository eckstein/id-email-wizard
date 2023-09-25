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

	// Fill our summary table on page load
	// Initialize variables for totals
	var totalSends = 0;
	var totalOpens = 0;
	var totalClicks = 0;
	var totalPurchases = 0;
	var totalRevenue = 0;
	var totalUnsubs = 0;

	// Iterate through the rows of the second table to calculate the totals
	$("#idemailwiz_initiative_campaign_table tbody tr").each(function () {
		totalSends += parseFloat($(this).find(".uniqueSends").text().replace(/,/g, ""));
		totalOpens += parseFloat($(this).find(".uniqueOpens").text().replace(/,/g, ""));
		totalClicks += parseFloat($(this).find(".uniqueClicks").text().replace(/,/g, ""));
		totalPurchases += parseFloat($(this).find(".uniquePurchases").text().replace(/,/g, ""));
		totalRevenue += parseFloat($(this).find(".campaignRevenue").text().replace(/,/g, "").replace("$", ""));
		totalUnsubs += parseFloat($(this).find(".uniqueUnsubs").text().replace(/,/g, ""));
	});

	// Perform the required calculations
	var openRate = (totalOpens / totalSends) * 100;
	var CTR = (totalClicks / totalSends) * 100;
	var CTO = (totalClicks / totalOpens) * 100;
	var CVR = (totalPurchases / totalSends) * 100;
	var AOV = totalRevenue / totalPurchases;
	var unsubRate = (totalUnsubs / totalSends) * 100;

	// Initialize a number formatter for currency
	var currencyFormatter = new Intl.NumberFormat("en-US", {
		style: "currency",
		currency: "USD",
	});

	// Update the first table with the calculated values
	$(".initiativeSends .metric_view_value").text(totalSends.toLocaleString());
	$(".initiativeOpenRate .metric_view_value").text(openRate.toFixed(2) + "%");
	$(".initiativeCtr .metric_view_value").text(CTR.toFixed(2).toLocaleString() + "%");
	$(".initiativeCto .metric_view_value").text(CTO.toFixed(2).toLocaleString() + "%");
	$(".initiativePurchases .metric_view_value").text(totalPurchases.toLocaleString());
	$(".initiativeRevenue .metric_view_value").text(currencyFormatter.format(totalRevenue.toFixed(2)));
	$(".initiativeCvr .metric_view_value").text(CVR.toFixed(2).toLocaleString() + "%");
	$(".initiativeAov .metric_view_value").text(currencyFormatter.format(AOV.toFixed(2)));
	$(".initiativeUnsubRate .metric_view_value").text(unsubRate.toFixed(2).toLocaleString() + "%");

	async function addCampaigns(initiativeID) {
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

	// Base template module
	$(".attachBaseTemplate").on("click", function () {
		$("#showAttachBaseTemplate").slideToggle();
	});

	// Function to handle removing campaigns
	function removeCampaigns(initiativeID, campaignID) {
		campaignIDs = [campaignID];

		// Swal2 Confirm dialog
		Swal.fire({
			title: "Are you sure?",
			text: "Do you want to remove this campaign?",
			icon: "warning",
			showCancelButton: true,
			confirmButtonColor: "#3085d6",
			cancelButtonColor: "#d33",
			confirmButtonText: "Yes, remove it!",
		})
			.then((result) => {
				if (result.isConfirmed) {
					// Send an AJAX request to update the database
					idemailwiz_do_ajax(
						"idemailwiz_add_remove_campaign_from_initiative",
						idAjax_initiatives.nonce,
						{
							campaign_ids: campaignIDs,
							initiative_id: initiativeID,
							campaignAction: "remove",
						},
						function (response) {
							// Detailed response for debugging
							console.log(response);

							// User-friendly alert message based on overall success
							if (response.success) {
								Swal.fire({
									icon: "success",
									title: "Success",
									text: "Campaigns successfully removed!",
								}).then(() => {
									setTimeout(function () {
										window.location.reload();
									}, 500);
								});
							} else {
								Swal.fire({
									icon: "error",
									title: "Error",
									text: "Some campaigns could not be processed. Check the console for details.",
								});
								console.error("Detailed messages:", response.data.messages);
							}
						},
						function (error) {
							// Error handling
							Swal.fire({
								icon: "error",
								title: "Error",
								text: "An error occurred. Check the console for details.",
							});
							console.error("Failed to make call to update function", error);
						}
					);
				}
			})
			.catch((error) => {
				// Handle any Swal2 errors here
				console.error("Swal2 error:", error);
			});
	}

	// Main click handler to decide campaign action
	$(document).on("click", ".add-init-campaign, .remove-init-campaign", function () {
		const initiativeID = $(this).data("initiativeid");
		const action = $(this).data("initcampaignaction");
		const campaignID = $(this).data("campaignid");

		if (action == "remove") {
			removeCampaigns(initiativeID, campaignID);
		} else {
			addCampaigns(initiativeID);
		}
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
                                    }).done(function(response) {
                                        console.log('AJAX Response:', response);
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
                                setTimeout(function() {
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
                                initiativeIds: initiativeIds
                            }
                        }).done(function(response) {
                            console.log('AJAX Response:', response);
                        });

                        ajaxCalls.push(ajaxCall);

                        // Wait for the AJAX call to complete
                        $.when.apply($, ajaxCalls)
                        .then(function () {
                            Swal.fire({
                                icon: "success",
                                title: "Initiatives are being updated...",
                                text: "The page will refresh once finished!",
                                showConfirmButton: false,
                                timer: 1500,
                            });
                            setTimeout(function() {
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
                    }
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

	if ($("#idemailwiz_initiative_campaign_table").length) {
		// Custom sorting for date format 'm/d/Y'
		$.fn.dataTable.ext.type.order["date-mdy-pre"] = function (dateString) {
			var dateParts = dateString.split("/");
			return new Date(dateParts[2], dateParts[0] - 1, dateParts[1]).getTime(); // Month is 0-indexed
		};

		var idemailwiz_initiative_campaign_table = $("#idemailwiz_initiative_campaign_table").DataTable({
			dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>rtp',
			columns: [
				{ type: "date-mdy" },
				null,
				null,
				{
					width: "300px",
				},
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
				null,
			],

			order: [[1, "desc"]],
			scrollX: true,
			scrollY: true,
			paging: true,
			pageLength: 10,
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

	// Delete an initiative from the single initiative page
	$(".remove-single-initiative").on("click", function () {
		const initiativeId = $(this).data("initiativeid"); // Assuming you have a data attribute for the ID
		idwiz_deleteInitiatives([initiativeId], function () {
			// Redirect to /initiatives
			window.location.href = "/initiatives";
		});
	});
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
