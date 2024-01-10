jQuery(document).ready(function ($) {
	// On comparisons archive page, add DataTable
	if ($("#idemailwiz_comparisons_table").length) {
		var allInitsTable = $("#idemailwiz_comparisons_table").DataTable();
	}

	$(document).on("click", ".new-comparison", function () {
		Swal.fire({
			title: "Create New Comparison",
			input: "text",
			//inputLabel: 'Comparison Name',
			inputPlaceholder: "Enter a name for this comparison",
			showCancelButton: true,
			inputValidator: (value) => {
				if (!value) {
					return "You need to write something!";
				}
			},
		}).then((result) => {
			if (result.isConfirmed) {
				idemailwiz_do_ajax(
					"create_new_comparison_post", // Action function name
					idAjax_comparisons.nonce, // Nonce value
					{ postTitle: result.value }, // Additional data
					function (response) {
						// Success callback
						if (response.success && response.data.url) {
							window.location.href = response.data.url; // Redirect to the new post
						} else {
							Swal.fire("Error", response.data.message, "error");
						}
					},
					function (xhr, status, error) {
						// Error callback
						console.error("Error:", error);
						Swal.fire("Error", "An error occurred while creating the post.", "error");
					}
				);
			}
		});
	});

	// Single comparison posts

	//On load, initialize in between elements, the dynamic rollup, and the campaign images
	if ($('.single-idwiz_comparison').length) {
		reinitializeInBetweenElements();
		updateDynamicCompareRollup();
		loadTemplatePreviewsAsync(".compare-template-preview");
	}

	function refreshComparisonSubtitle() {
		var postId = $("article").data("comparisonid");
		idemailwiz_do_ajax(
			"idemailwiz_refresh_comparison_subtitle",
			idAjax_comparisons.nonce,
			{
				postId: postId,
			},
			function (response) {
				if (response.success) {
					$(".comparison-subtitle strong").html(response.data);
					console.log("Subtitle refreshed successfully");
					//do_wiz_notif({ message: "Set title updated successfully", duration: 10000 });
				} else {
					console.error("Error updating set title:", response.data);
				}
			},
			function (error) {
				console.error("AJAX error:", error);
			}
		);
	}



	$(document).on("change", ".editable-set-title", function () {
		var $this = $(this);
		var newTitle = $this.val().trim();
		var setId = $this.data("set-id");
		var postId = $this.data("post-id");

		// Delay the AJAX call by 1 second to prevent rapid successive updates
		clearTimeout($this.data("timeout"));
		var timeout = setTimeout(function () {
			idemailwiz_do_ajax(
				"idemailwiz_update_set_title",
				idAjax_comparisons.nonce,
				{
					setTitle: newTitle,
					setId: setId,
					postId: postId,
				},
				function (response) {
					if (response.success) {
						refreshComparisonSubtitle();
						console.log("Set title updated successfully");
						do_wiz_notif({ message: "Set title updated successfully", duration: 10000 });
					} else {
						console.error("Error updating set title:", response.data);
					}
				},
				function (error) {
					console.error("AJAX error:", error);
				}
			);
		}, 1000); // 1000 milliseconds delay

		$this.data("timeout", timeout);
	});

	$(document).on("click", ".toggle-all-compare-campaigns", function () {
		var state = $(this).data("collapse-state"); // this is the state that will happen when clicked

		$(".compare-campaign-wrapper").each(function () {
			toggleCampaign($(this), state);
		});
	});

	$(document).on("click", ".collapse-compare-row", function () {
		var $clickedCampaign = $(this).closest(".compare-campaign-wrapper");
		var campaignIndex = $clickedCampaign.prevAll(".compare-campaign-wrapper").length; // Custom index

		// Toggle the clicked campaign
		toggleCampaign($clickedCampaign);

		// Find and toggle the corresponding campaign in all columns
		$(".comparison-column").each(function () {
			var $partnerCampaign = $(this).find(".compare-campaign-wrapper").eq(campaignIndex);
			if ($partnerCampaign.length && !$partnerCampaign.is($clickedCampaign)) {
				toggleCampaign($partnerCampaign);
			}
		});

		reinitializeInBetweenElements();
	});

	function toggleCampaign($campaign, forceState) {
		var $details = $campaign.find(".compare-campaign-details");
		var $icon = $campaign.find(".collapse-compare-row i");

		function collapseCampaign() {
			$details.stop().animate({ height: "0" }, "fast", function () {
				$(this).hide(); // Hide after animation completes
			});
			$campaign.animate({ height: "100px" }, "fast").addClass("collapsed");
			$campaign.find(".collapse-compare-row").removeClass("fa-chevron-up").addClass("fa-chevron-down");

			var $campaignComments = $details.find(".compare-campaign-comments");
			if ($campaignComments.is(":visible")) {
				toggleCompareCommentsVis($campaignComments);
			}
		}

		function expandCampaign() {
			// Expand the campaign card back to its full height
			$campaign.stop().animate({ height: "420px" }, "fast", function () {
				$campaign.removeClass("collapsed");
				$campaign.find(".collapse-compare-row").removeClass("fa-chevron-down").addClass("fa-chevron-up");
			});

			// Show and expand the details box
			if ($details.length) {
				$details.css("height", "auto");
				var fullHeight = $details.height();
				$details.hide().height(0);
				$details
					.stop()
					.animate({ height: fullHeight }, "fast", function () {
						$(this).css("height", "");
					})
					.show();
			}

			$icon.removeClass("fa-square-plus").addClass("fa-square-minus");
		}

		if (forceState === "open") {
			expandCampaign();
		} else if (forceState === "close") {
			collapseCampaign();
		} else {
			// Toggle based on the presence of "collapsed" class
			if ($campaign.hasClass("collapsed")) {
				expandCampaign();
			} else {
				collapseCampaign();
			}
		}
	}

	$(document).on("click", ".re-sort-compare-campaigns", function () {
		var setId = $(this).data("set-id");
		var postId = $(this).data("post-id");

		Swal.fire({
			title: "Re-sort Campaigns",
			html: `
                <p>This will re-sort the campaigns by date and remove any spacers.</p>
                <label><input type="radio" name="sortOrder" value="DESC" checked> Newest First</label><br>
                <label><input type="radio" name="sortOrder" value="ASC"> Oldest First</label>
            `,
			icon: "warning",
			showCancelButton: true,
			confirmButtonColor: "#3085d6",
			cancelButtonColor: "#d33",
			confirmButtonText: "Yes, re-sort!",
			preConfirm: () => {
				return document.querySelector('input[name="sortOrder"]:checked').value;
			},
		}).then((result) => {
			if (result.isConfirmed) {
				var sortOrder = result.value;
				idemailwiz_do_ajax(
					"idemailwiz_re_sort_compare_campaigns",
					idAjax_comparisons.nonce,
					{
						setId: setId,
						postId: postId,
						sort: sortOrder,
					},
					function (response) {
						if (response.success) {
							console.log("Campaigns re-sorted successfully");
							do_wiz_notif({ message: "Campaigns re-sorted successfully", duration: 10000 });
							location.reload();
						} else {
							console.error("Error re-sorting campaigns:", response.data);
						}
					},
					function (error) {
						console.error("AJAX error:", error);
					}
				);
			}
		});
	});

	$(".clear-compare-campaigns").on("click", function () {
		var postId = $(this).data("post-id");
		var setId = $(this).data("set-id");

		Swal.fire({
			title: "Are you sure?",
			text: "Remove all campaigns from this column?",
			icon: "warning",
			showCancelButton: true,
			confirmButtonColor: "#3085d6",
			cancelButtonColor: "#d33",
			confirmButtonText: "Yes, remove them!",
		}).then((result) => {
			if (result.isConfirmed) {
				idemailwiz_do_ajax(
					"idemailwiz_clear_comparision_campaign",
					idAjax_comparisons.nonce,
					{ postId: postId, setId: setId },
					function (response) {
						console.log("Campaigns cleared", response);
						location.reload();
					},
					function (error) {
						console.error("Error clearing campaigns", error);
					}
				);
			}
		});
	});

	

    

	

	$(document).on("click", ".add-spacer", function () {
		var $clickedButton = $(this);

		var setId = $(this).data("set-id");
		var postId = $(this).data("post-id");
		var addBefore = $(this).data("addbefore");

		idemailwiz_do_ajax(
			"idemailwiz_add_compare_set_spacer",
			idAjax_comparisons.nonce,
			{
				setId,
				postId,
				addBefore,
			},
			function (response) {
				console.log("Spacer added", response);
				if (response.success) {
					var spacerHtml = response.data.spacerHtml;
					$clickedButton.closest(".compare-campaign-between").after(spacerHtml);
					reinitializeInBetweenElements();
					syncCampaignStatesAcrossColumns();
				} else {
					console.error("Error adding spacer", response);
				}
			},
			function (error) {
				console.error("Error adding spacer", error);
			}
		);
	});

	$(document).on("click", ".add-compare-campaigns", function () {
		var setId = $(this).data("set-id");
		var postId = $(this).data("post-id");
		var addBefore = $(this).data("addbefore") || false;
		var replaceWith = $(this).data("replacewith") || false;

		Swal.fire({
			title: "Add Campaigns",
			html:
				'<form id="add-compare-campaigns-form">' +
				'<div class="form-group">' +
				'<label><input type="radio" name="campaign_mode" value="byDate" checked> Date Range</label>&nbsp;&nbsp;' +
				'<label><input type="radio" name="campaign_mode" value="byCampaign"> Specific Campaigns</label><br/><br/>' +
				'<label><input type="radio" name="campaign_mode" value="byInitiative"> Initiatives</label><br/><br/>' +
				"</div>" +
				'<div class="form-group byDate-group">' +
				"<label>Start Date:</label>" +
				'<input type="date" class="swal2-input" id="start-date"><br/>' +
				"<label>End Date:</label>" +
				'<input type="date" class="swal2-input" id="end-date">' +
				"</div>" +
				'<div class="form-group byCampaign-group" style="display: none;">' +
				'<select class="swal2-input swalSelect2" id="campaign-select" multiple="multiple"></select>' +
				"</div>" +
				'<div class="form-group byInitiative-group" style="display: none;">' +
				'<select class="swal2-input swalSelect2" id="initiative-select" multiple="multiple"></select>' +
				"</div>" +
				"</form>",
			showCancelButton: true,
			confirmButtonText: "Add Campaigns",
			cancelButtonText: "Cancel",
			preConfirm: () => {
				var mode = $("input[name='campaign_mode']:checked").val();
				if (mode === "byDate") {
					return {
						mode: mode,
						startDate: $("#start-date").val(),
						endDate: $("#end-date").val(),
					};
				} else if (mode === "byInitiative") {
					return {
						mode: mode,
						initiatives: $("#initiative-select").val(),
					}
				} else if (mode === "byCampaign") {
					return {
						mode: mode,
						campaigns: $("#campaign-select").val(),
					};
				}
			},
			didOpen: () => {
				// Initialize Select2 for 'byCampaign' mode
				$("#campaign-select").select2({
					multiple: true,
					ajax: {
						delay: 250,
						transport: function (params, success, failure) {
							idemailwiz_do_ajax(
								"idemailwiz_get_campaigns_for_select",
								idAjax_initiatives.nonce,
								{
									q: params.data.term,
									type: "Blast",
								},
								function (data) {
									success({ results: data });
								},
								failure
							);
						},
					},
				});

				// Initialize Select2 for 'byInitiative' mode
				$("#initiative-select").select2({
					multiple: true,
					ajax: {
						delay: 250,
						transport: function (params, success, failure) {
							idemailwiz_do_ajax(
								"idemailwiz_get_initiatives_for_select",
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

				// Toggle form visibility based on mode selection
				$("input[name='campaign_mode']").change(function () {
					if (this.value === "byDate") {
						$(".byDate-group").show();
						$(".byCampaign-group").hide();
						$(".byInitiative-group").hide();
					} else if (this.value === "byCampaign") {
						$(".byDate-group").hide();
						$(".byInitiative-group").hide();
						$(".byCampaign-group").show();
					} else if (this.value === "byInitiative") {
						$(".byDate-group").hide();
						$(".byCampaign-group").hide();
						$(".byInitiative-group").show();
					}
				});
			},
		}).then((result) => {
			if (result.isConfirmed && result.value) {
				// Process the form data
				console.log("Form data:", result.value);

				// Define the action name and nonce value for the AJAX call
				var actionName = "idemailwiz_handle_ajax_add_compare_campaign";
				var nonceValue = idAjax_comparisons.nonce;

				// Prepare the data to be sent
				var ajaxData = {
					mode: result.value.mode,
					postId: postId,
					setId: setId,
					startDate: result.value.startDate,
					endDate: result.value.endDate,
					campaigns: result.value.campaigns,
					initiatives: result.value.initiatives,
				};

				if (addBefore !== false) {
					ajaxData.addBefore = addBefore;
				}
				if (replaceWith !== false) {
					ajaxData.replaceWith = replaceWith;
				}

				// Perform the AJAX call
				idemailwiz_do_ajax(
					actionName,
					nonceValue,
					ajaxData,
					function (response) {
						if (response.success) {
							if (response.data.firstAddition == true) {
								location.reload();
							}
							// Message about successful addition
							do_wiz_notif({ message: response.data.message, duration: 10000 });

							//Hide main add campaigns button, if visible
							$(".comparison-column-settings .add-compare-campaigns").remove();

							// HTML content to insert
							var newHtml = response.data.html;

							if (response.data.replaceWith) {
								// Replace the identified campaign instead of inserting
								var $replaceElement = $('.compare-campaign-wrapper[data-campaignid="' + response.data.replaceWith + '"]');

								if ($replaceElement.length) {
									$replaceElement.replaceWith(newHtml);
								} else {
									// If replaceWith element not found, just append
									$('.comparison-column[data-set-id="' + setId + '"]').append(newHtml);
								}
							} else if (response.data.addBefore) {
								// Original addBefore logic
								var $addBeforeElement = $('.compare-campaign-wrapper[data-campaignid="' + response.data.addBefore + '"]');

								if ($addBeforeElement.length) {
									$addBeforeElement.before(newHtml);
								} else {
									$('.comparison-column[data-set-id="' + setId + '"]').append(newHtml);
								}
							} else {
								// Default append
								$('.comparison-column[data-set-id="' + setId + '"]').append(newHtml);
							}

							// Reinitialize any necessary events or styles
							reinitializeInBetweenElements();
							syncCampaignStatesAcrossColumns();
							refreshComparisonSubtitle();
							loadTemplatePreviewsAsync(".compare-template-preview");
							updateDynamicCompareRollup();

							setTimeout(function () {
								$(".compare-campaign-wrapper.showAsNew").css("background-color", "#fff");
							}, 3000);
						} else {
							console.error("Error:", response.data);
							Swal.fire("Error", "An error occurred while adding the campaigns: " + response.data, "error");
						}
					},
					function (error) {
						console.error("Error:", error);
						Swal.fire("Error", "An error occurred while adding the campaigns: " + error, "error");
					}
				);
			}
		});
	});

	// Manual campaign card refresh
	$(document).on("click", ".refresh-compare-campaign", function () {

		var setId = $(this).data("set-id");
		var postId = $(this).data("post-id");
		var replaceWith = $(this).data("replacewith");

		var clickedElement = $(this);

		refresh_compare_campaign(setId, postId, replaceWith, clickedElement);
	});

	function refresh_compare_campaign(setId, postId, replaceWith, clickedElement) {
		clickedElement.addClass("fa-spin");

		// Define the action name and nonce value for the AJAX call
		var actionName = "idemailwiz_handle_ajax_add_compare_campaign";
		var nonceValue = idAjax_comparisons.nonce;

		// Prepare the data to be sent
		var ajaxData = {
			postId: postId,
			setId: setId,
			replaceWith: replaceWith,
			refreshOnly: true,
		};

		// Perform the AJAX call
		idemailwiz_do_ajax(
			actionName,
			nonceValue,
			ajaxData,
			function (response) {
				if (response.success) {
					// Replace the identified campaign with the new HTML
					var newHtml = response.data.html;
					var $replaceElement = $('.compare-campaign-wrapper[data-campaignid="' + response.data.replaceWith + '"]');

					if ($replaceElement.length) {
						$replaceElement.replaceWith(newHtml);
					}

					clickedElement.removeClass("fa-spin");

					// Reinitialize any necessary events or styles
					reinitializeInBetweenElements();
					syncCampaignStatesAcrossColumns();
					loadTemplatePreviewsAsync(".compare-template-preview");

					setTimeout(function () {
						$(".compare-campaign-wrapper.showAsNew").css("background-color", "#fff");
					}, 3000);

					do_wiz_notif({ message: "Campaign updated!", duration: 3000 });
				} else {
					console.error("Error:", response.data);
					do_wiz_notif({ message: "An error occurred while refreshing the campaign: " + response.data, type: "error" });
					//alert("An error occurred while refreshing the campaign: " + response.data);
				}
			},
			function (error) {
				console.error("Error:", error);
				do_wiz_notif({ message: "An error occurred while refreshing the campaign: " + error, type: "error" });
				//alert("An error occurred while refreshing the campaign: " + error);
			}
		);
	}
	

	function reinitializeInBetweenElements() {
		$(".comparison-column").each(function () {
			var $column = $(this);
			var setId = $column.data("set-id");
			var postId = $column.data("post-id");

			// Remove existing in-between elements
			$column.find(".compare-campaign-between").remove();

			// Iterate over campaign cards to add in-between elements
			$column.find(".compare-campaign-wrapper").each(function () {
				var $campaign = $(this);
				var campaignId = $campaign.data("campaignid");

				// Create and insert the in-between element before each campaign card
				$campaign.before(createInBetweenElement(setId, postId, campaignId));
			});

			// Insert in-between element after the last campaign card
			var lastCampaign = $column.find(".compare-campaign-wrapper").last();
			if (lastCampaign.length) {
				lastCampaign.after(createInBetweenElement(setId, postId));
			}
		});
	}

	// Function to create an in-between element
	function createInBetweenElement(setId, postId, addBeforeCampaignId) {
		return $("<div/>", {
			class: "compare-campaign-between",
			html: `
                <div class="between-line"></div>
                <div class="add-between">
                    <i class="fa-regular fa-square-plus"></i> 
                    <span class="between-hover-actions">
                        <span class="between-add-new add-compare-campaigns" data-set-id="${setId}" data-post-id="${postId}" data-addbefore="${addBeforeCampaignId}">
                            <i class="fa-solid fa-circle-plus"></i> Campaign
                        </span>
                        <span class="between-add-new add-spacer" data-post-id="${postId}" data-set-id="${setId}" data-addbefore="${addBeforeCampaignId}">
                            <i class="fa-solid fa-circle-plus"></i> Spacer
                        </span>
                    </span>
                </div>`,
		});
	}

	$(".comparison-column").sortable({
		items: ".compare-campaign-wrapper",
		handle: ".sortable-handle",
		update: function (event, ui) {
			var postId = ui.item.data("postid");
			var droppedCampaignId = ui.item.data("campaignid");
			var setId = ui.item.data("setid");
			var nextCampaignId = ui.item.next().data("campaignid");

			// AJAX call to update order
			idemailwiz_do_ajax(
				"idemailwiz_update_comparison_campaigns_order",
				idAjax_comparisons.nonce,
				{ postId: postId, setId: setId, droppedCampaignId: droppedCampaignId, nextCampaignId: nextCampaignId },
				function (response) {
					reinitializeInBetweenElements();
					syncCampaignStatesAcrossColumns();
				},
				function (error) {
					console.error("Error updating order", error);
				}
			);
		},
	});

	function syncCampaignStatesAcrossColumns() {
		var $firstColumnCampaigns = $(".comparison-column").first().find(".compare-campaign-wrapper");

		$firstColumnCampaigns.each(function () {
			var $campaign = $(this);
			var index = $campaign.index(".compare-campaign-wrapper");
			console.log("Campaign index:", index); // Debugging: Log the index

			var isCollapsed = $campaign.hasClass("collapsed");

			// Sync state with corresponding campaigns in other columns
			$(".comparison-column").each(function (columnIndex) {
				var $partnerCampaign = $(this).find(".compare-campaign-wrapper").eq(index);
				console.log("Partner campaign index in column " + columnIndex + ":", $partnerCampaign.index()); // Debugging

				if ($partnerCampaign.length) {
					if (isCollapsed !== $partnerCampaign.hasClass("collapsed")) {
						toggleCampaign($partnerCampaign, isCollapsed ? "close" : "open");
					}
				}
			});
		});
	}

	// Remove campaign from set
	$(document).on("click", ".compare-campaign-actions .remove-comparison-campaign", function () {
		var postId = $(this).closest(".compare-campaign-wrapper").data("postid");
		var campaignId = $(this).closest(".compare-campaign-wrapper").data("campaignid");
		var setId = $(this).closest(".compare-campaign-wrapper").data("setid");
		var campaignElement = $(this).closest(".compare-campaign-wrapper");
		console.log("Campaign id", campaignId);
		console.log("Set id", setId);
		console.log("Post id", postId);
		idemailwiz_do_ajax(
			"idemailwiz_remove_comparision_campaign",
			idAjax_comparisons.nonce,
			{ postId, setId, campaignId },
			function (response) {
				if (response.success) {
					console.log(response.data.message);
					if (response.data.refreshForEmpty == true) {
						location.reload();
					}
					// Visual effect for removing campaign
					campaignElement.fadeOut(600, function () {
						$(this).remove();
						reinitializeInBetweenElements();
						syncCampaignStatesAcrossColumns();
						refreshComparisonSubtitle();
						updateDynamicCompareRollup();
					});
				} else {
					swal.fire("Error", response.data.message, "error");
					console.error(response.data.message);
				}
			},
			function (error) {
				swal.fire("Error", error, "error");
				console.error("Error removing campaign", error);
			}
		);
	});
	$(document).on("click", ".compare-experiment-tabs li", function () {
		var campaignWrapper = $(this).closest(".compare-campaign-wrapper");
		var setId = campaignWrapper.data("setid");
		var postId = campaignWrapper.data("postid");
		var campaignId = campaignWrapper.data("campaignid");
		var templateId = $(this).data("templateid");
		var isBaseMetric = $(this).data("is-base-metric");

		idemailwiz_do_ajax(
			"idemailwiz_generate_campaign_card_ajax",
			idAjax_comparisons.nonce,
			{ setId, postId, campaignId, asNew: false, templateId, isBaseMetric },
			function (response) {
				if (response.success) {
					console.log("Campaign card HTML updated");
					// Replace the campaign with the new HTML
					campaignWrapper.replaceWith(response.data.html);
					loadTemplatePreviewsAsync(".compare-template-preview");
				} else {
					do_wiz_notif({ message: response.data.message, duration: 10000 });
					console.error(response.data.message);
				}
			},
			function (error) {
				do_wiz_notif({ message: "An error occurred: " + error, duration: 10000 });
				console.error("Error updating campaign card", error);
			}
		);
	});

	


	$(document).on("click", ".show-hide-compare-comments", function () {
		var $campaignCard = $(this).closest(".compare-campaign-wrapper");
		var $parentColumn = $campaignCard.closest(".comparison-column");
		var index = $parentColumn.find(".compare-campaign-wrapper").index($campaignCard);
		var $commentsSection = $campaignCard.find(".compare-campaign-comments");

		// Check if the campaign is not already expanded
		if ($campaignCard.hasClass("collapsed")) {
			// Expand the clicked campaign
			toggleCampaign($campaignCard, "open");

			// Find and expand the corresponding campaign in other columns
			$(".comparison-column")
				.not($parentColumn)
				.each(function () {
					var $partnerCampaign = $(this).find(".compare-campaign-wrapper").eq(index);
					if ($partnerCampaign.length && !$partnerCampaign.hasClass("expanded")) {
						toggleCampaign($partnerCampaign, "open");
					}
				});
		}

		toggleCompareCommentsVis($commentsSection);
	});

	function toggleCompareCommentsVis($commentsSection) {
		// Toggle the visibility with sliding effect
		if ($commentsSection.is(":visible")) {
			// Slide up (hide)
			$commentsSection.animate(
				{
					bottom: "-100%",
				},
				"slow",
				function () {
					$(this).hide();
				}
			);
		} else {
			// Slide down (show)
			$commentsSection.show().animate(
				{
					bottom: "0",
				},
				"slow"
			);
		}
	}

    function updateComparisonColumns(callback) {
        $('.comparison-column').each(function() {
            var campaignIds = [];
            $(this).find('.compare-campaign-wrapper').each(function() {
                var campaignId = $(this).data('campaignid');
                if (campaignId && !String(campaignId).startsWith('spacer_')) {
                    campaignIds.push(campaignId);
                }
            });
            $(this).attr('data-campaign-ids', JSON.stringify(campaignIds));
        });

        if (typeof callback === 'function') {
            callback();
        }
    }

    function updateDynamicCompareRollup() {
        // Update the campaignIds and then fetch rollup summary
        updateComparisonColumns(function() {
            $(".rollup_summary_wrapper").each(function() {
                var campaignIds = $(this).closest(".comparison-column").attr("data-campaign-ids");
                var rollupElementId = $(this).attr("id");
                var includeMetrics = $(this).closest(".comparison-column").data("include-metrics");
                fetchRollUpSummaryData(JSON.parse(campaignIds), null, null, "#" + rollupElementId, includeMetrics);
            });
        });
    }

   

	// Add new comment

	$(document).on("click", ".add-new-compare-comment", function () {
		var $campaignComments = $(this).closest(".compare-campaign-comments");
		var $scrollWrap = $campaignComments.find(".compare-campaigns-comments-scrollwrap");
		var postId = $(this).data("post-id");
		var campaignId = $(this).data("campaign-id");
		var setId = $(this).data("set-id");

		// Check if an input box already exists
		if ($scrollWrap.find(".new-comment-input").length === 0) {
			// Create and append input box for new comment
			var $inputBox = $("<div class='new-comment-input'><textarea></textarea><button class='wiz-button green submit-comment'>Add Comment</button>&nbsp;&nbsp;<button class='wiz-button gray cancel-comment'>Cancel</button></div>");
			$scrollWrap.append($inputBox);
		}
		// Scroll to the bottom of the scrollwrap
		var bottomPosition = $scrollWrap.prop("scrollHeight");

		$scrollWrap.animate(
			{
				scrollTop: bottomPosition,
			},
			"slow"
		);

		// Cancel button
		$inputBox.find(".cancel-comment").on("click", function () {
			$inputBox.remove();
		});

		// Handle submit of new comment
		$inputBox.find(".submit-comment").on("click", function () {
			var $commentArea = $(this).closest(".compare-campaign-comments");
			var commentContent = $inputBox.find("textarea").val();
			var $inputForm = $commentArea.find(".new-comment-input");
			idemailwiz_do_ajax(
				"add_new_compare_comment",
				idAjax_comparisons.nonce,
				{ postId: postId, campaignId: campaignId, setId: setId, comment: commentContent },
				function (response) {
					// Success callback
					if (response.success) {
						// Insert the new comment HTML just before the 'add-new-compare-comment-wrap' div
						$commentArea.find(".add-new-compare-comment-wrap").before(response.data.html);
						$commentArea.find('.no-comments-message').hide();

						$inputForm.remove();
					} else {
						alert("Error: " + response.message);
					}
				},
				function (error) {
					// Error callback
					console.error("Error adding comment:", error);
				}
			);
		});
	});

	//Edit existing comment
	$(document).on("click", ".edit-compare-comment", function () {
		var $commentDiv = $(this).closest(".compare-campaign-comment");
		var $commentContent = $commentDiv.find(".compare-campaign-comment-content");
		var originalCommentHtml = $commentContent.html();

		// Convert <br> tags to newline characters for editing in textarea
		// Use a placeholder for existing line breaks to avoid double conversion
		var placeholder = "PLACEHOLDER_LINE_BREAK";
		var originalCommentText = originalCommentHtml
			.replace(/<br\s*[\/]?>/gi, placeholder)
			.replace(/(\r\n|\n|\r)/gm, "")
			.replace(new RegExp(placeholder, "g"), "\n");

		var editHtml = "<textarea class='edit-comment-textarea'>" + originalCommentText + "</textarea>";
		editHtml += "<div class='edit-comment-actions'>";
		editHtml += "<button class='wiz-button green save-edited-comment'>Save</button>";
		editHtml += "<button class='wiz-button gray cancel-edited-comment'>Cancel</button>";
		editHtml += "</div>";

		$commentContent.html(editHtml);

		// Handle Cancel
		$commentDiv.find(".cancel-edited-comment").on("click", function () {
			$commentContent.html(originalCommentText); // Reset to original text
		});
	});

	$(document).on("click", ".save-edited-comment", function () {
		var $commentDiv = $(this).closest(".compare-campaign-comment");
		var $lastUpdatedSpan = $commentDiv.find(".compare-campaign-last-updated");
		var editedText = $commentDiv.find(".edit-comment-textarea").val();
		var postId = $commentDiv.find(".compare-campaign-comment-actions").data("post-id");
		var campaignId = $commentDiv.find(".compare-campaign-comment-actions").data("campaign-id");
		var setId = $commentDiv.find(".compare-campaign-comment-actions").data("set-id");
		var commentTimestamp = $commentDiv.find(".compare-campaign-comment-actions").data("timestamp");

		idemailwiz_do_ajax(
			"save_edited_compare_comment", // PHP function to handle the request
			idAjax_comparisons.nonce,
			{ postId: postId, campaignId: campaignId, setId: setId, comment: editedText, commentTimestamp: commentTimestamp },
			function (response) {
				if (response.success) {
					// Update the comment content in the DOM with the HTML returned from the server
					$commentDiv.find(".compare-campaign-comment-content").html(response.data.html);
					$lastUpdatedSpan.text("Edited " + response.data.lastUpdated);
				} else {
					alert("Error: " + response.message);
				}
			},
			function (error) {
				console.error("Error saving edited comment:", error);
			}
		);
	});

	// Delete existing comment
	$(document).on("click", ".delete-compare-comment", function () {
		var $commentDiv = $(this).closest(".compare-campaign-comment");
		var postId = $(this).parent().data("post-id");
		var campaignId = $(this).parent().data("campaign-id");
		var setId = $(this).parent().data("set-id");
		var setId = $(this).parent().data("set-id");
		var commentTimestamp = $commentDiv.find(".compare-campaign-comment-actions").data("timestamp");

		idemailwiz_do_ajax(
			"delete_compare_comment", // PHP function to handle the request
			idAjax_comparisons.nonce,
			{ postId: postId, campaignId: campaignId, setId: setId, commentTimestamp: commentTimestamp },
			function (response) {
				if (response.success) {
					$commentDiv.remove(); // Remove the comment from the DOM
				} else {
					alert("Error: " + response.message);
				}
			},
			function (error) {
				console.error("Error deleting comment:", error);
			}
		);
	});
});
