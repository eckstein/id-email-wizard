jQuery(document).ready(function ($) {
	// Sync a single campaign
	$(".sync-campaign").on("click", function () {
		const campaignId = $(this).attr("data-campaignid");
		handle_idwiz_sync_buttons("idemailwiz_ajax_sync", idAjax_wiz_metrics.nonce, { campaignIds: JSON.stringify(campaignId) });
	});

	$(".add-initiative-icon").on("click", function () {
		const action = "add";
		const campaignId = $(this).data("campaignid");
		window.manageCampaignsInInitiative(action, [campaignId], function () {
			location.reload();
		});
	});

	$(".remove-initiative-icon").on("click", function () {
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

	// Template preview heatmap for single campaigns page
	if ($(".templatePreviewIframe").length) {
		$(".templatePreviewIframe").each(function () {
			var iframe = $(this);
			var heatmapContainer = iframe.closest(".wizcampaign-template-preview-iframe-container").find(".heatmap-container");

			// Set the overlay height for this iframe
			function setOverlayHeight() {
				var iframeContentHeight = iframe.contents().find("body").height();
				heatmapContainer.height(iframeContentHeight);
			}

			iframe.on("load", function () {
				setTimeout(function () {
					setOverlayHeight();
					syncScrolling(iframe[0], heatmapContainer);
				}, 500);
			});
		});
	}

	$(".heatmap-point").hover(
		function () {
			var unique_clicks = $(this).data("unique-clicks");
			var unique_click_rate = $(this).data("unique-click-rate");
			var url = $(this).data("url");
			var tooltip_content = "Unique Clicks: " + unique_clicks + "<br>Unique Click Rate: " + unique_click_rate + '<br>URL: <a href="' + url + '">' + url + "</a>";
			var tooltip = $('<div class="heatmap-tooltip"></div>').html(tooltip_content);

			var position = $(this).position(); // Get the position of the heatmap point
			tooltip.css({
				left: position.left,
				top: position.top - 30, // Position above the point, adjust as needed
			});

			$(".heatmap-tooltips").append(tooltip);
			tooltip.show();
		},
		function () {
			$(".heatmap-tooltips").find(".heatmap-tooltip").remove();
		}
	);

	// Function to set the overlay height to match the iframe content height
	function setOverlayHeight() {
		var iframeContentHeight = $(".templatePreviewIframe").contents().find("body").height();
		$(".heatmap-overlay").height(iframeContentHeight);
	}

	// Function to synchronize scrolling
	function syncScrolling(iframe, heatmapContainer) {
		var iframeWindow = iframe.contentWindow;
		var iframeDocument = iframe.contentDocument || iframeWindow.document;

		$(iframeDocument).on("scroll", function () {
			var scrollTop = $(this).scrollTop();
			heatmapContainer.css("top", -scrollTop + "px");
		});
	}

	$(".mark_as_winner button").on("click", function () {
		console.log("toggling winner....");
		// Get the experiment and template IDs from the button's data attributes
		const experimentId = $(this).data("experimentid");
		const templateId = $(this).data("templateid");
		const actionType = $(this).data("actiontype");

		// Create the data object to pass to the Ajax function
		let additionalData = {
			experimentId: experimentId,
			templateId: templateId,
			actionType: actionType,
		};

		// Make the Ajax call
		idemailwiz_do_ajax("handle_experiment_winner_toggle", idAjax_wiz_metrics.nonce, additionalData, toggleWinnerSuccess.bind(this), toggleWinnerError.bind(this));

		// Define success and error callbacks
		function toggleWinnerSuccess(data) {
			if (data.success) {
				// Handle successful response
				console.log("Winner updated successfully");
				$(".experiment_var_wrapper.winner .mark_as_winner button").text("Mark as winner");
				$(".experiment_var_wrapper.winner").removeClass("winner");
				$(this).closest(".experiment_var_wrapper").toggleClass("winner");
				$(this).closest(".experiment_var_wrapper .mark_as_winner button").text("Winner!");
			}
		}

		function toggleWinnerError(jqXHR, textStatus, errorThrown) {
			// Handle error
			console.log("Error updating winner: " + textStatus);
		}
	});

	$("#experimentNotes").on("blur", function () {
		// Get textarea value
		let notes = $(this).val();

		// Data validation and cleanup
		notes = notes.trim();
		console.log(notes);
		// Get experiment ID from parent element
		let experimentId = $(this).closest(".wizcampaign-experiment-notes").data("experimentid");

		// Prepare data to send
		let additionalData = {
			experimentNotes: notes,
			experimentId: experimentId,
		};
		console.log(additionalData);
		// Make AJAX call
		idemailwiz_do_ajax(
			"save_experiment_notes",
			idAjax_wiz_metrics.nonce,
			additionalData,
			function (response) {
				console.log("Data saved successfully:", response);
			},
			function (error) {
				console.error("Error saving data:", error);
			}
		);
	});
});
