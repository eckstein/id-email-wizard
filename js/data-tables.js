jQuery(document).ready(function ($) {

	//Tiny Tables
	if ($(".wizcampaign-tiny-table").length) {

	// Initialize DataTables with the parsed data
	table = $(".wizcampaign-tiny-table").DataTable({
		dom: 'ltp',
		scrollY: '340px',
		scrollCollapse: true,
		pageLength: 50,
		fnDrawCallback: function(oSettings) {
			if (oSettings._iDisplayLength > oSettings.fnRecordsDisplay()) {
				$(oSettings.nTableWrapper).find('.dataTables_paginate').hide();
				$(oSettings.nTableWrapper).find('.dataTables_length').hide();
			} else {
				 $(oSettings.nTableWrapper).find('.dataTables_paginate').show();
				 $(oSettings.nTableWrapper).find('.dataTables_length').show();
			}
		}
	});

	}


	if ($("#idemailwiz_campaign_table").length) {
		//Date sort plugin
		$.fn.dataTable.moment("x");

		// Fetch campaign_type from URL parameters
		const urlParams = new URLSearchParams(window.location.search);
		const campaignTypeFromUrl = urlParams.get("view") || "Blast"; // Default to 'Blast'

		// Initialize DataTables with the parsed data
		table = $("#idemailwiz_campaign_table").DataTable({
			ajax: {
				url: idAjax.ajaxurl,
				type: "POST",
				data: function (d) {
					d.action = "idwiz_get_campaign_table_view";
					d.security = idAjax_data_tables.nonce;
					d.campaign_type = campaignTypeFromUrl;
				},
				dataType: "json",
			},
			//serverSide: true,
			order: [
				[2, "desc"],
				[1, "desc"],
			],
			autoWidth: false,
			fixedColumns: { left: 3 },
			columns: get_wiz_campaign_columns(),
			buttons: get_wiz_campaign_buttons(),
			language: get_wiz_campaign_languages(),
			dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>rptp',
			fixedHeader: { header: true, footer: false },
			colReorder: { realtime: false },
			//scroller: true,
			scrollX: true,
			//scrollY: "650px",
			paging: true,
			lengthMenu: [
				[25, 50, 100, 200, 350, 500, 1000 - 1],
				[25, 50, 100, 200, 350, 500, 1000, "All"],
			],
			pageLength: 100,
			//scrollResize: true,
			scrollCollapse: true,
			processing: true,
			select: {
				selector: "td:not(:first-child), td:not(a)",
				style: "os",
				items: "row",
			},
			stateSave: true,
			stateDuration: 1,
			drawCallback: idwiz_dt_draw_callback,
			initComplete: idwiz_dt_init_callback,
		});

		// Handle state save and restore
		setupStateHandling(table);

		// Handle state save and restore
		function setupStateHandling(table) {
			table.on("stateSaveParams", function (e, settings, data) {
				// Save the current values of the date pickers
				data.startDate = $("#wizStartDate").val();
				data.endDate = $("#wizEndDate").val();
			});

			table.on("stateLoadParams", function (e, settings, data) {
				// Restore the date picker values
				$("#wizStartDate").val(data.startDate).trigger('change');
				$("#wizEndDate").val(data.endDate).trigger('change');
			});
		}

		// Main DT Initiation callback function
		function idwiz_dt_init_callback(settings, json) {
			addDateFilter();
			addDateChangeListener();
		}

		// Main draw callback function
		function idwiz_dt_draw_callback(settings, json) {
			var api = this.api();

			// Move some buttons
			moveButtons();

			// Create the counter column
			updateCounterColumn(api);

			// Readjust the column widths
			api.columns.adjust();

			// Handle the dynamic rollup
			
			var rollupSelector = '#campaigns-table-rollup';
			$(rollupSelector).html('<div class="rollup_summary_loader"><i class="fa-solid fa-spinner fa-spin"></i>&nbsp;&nbsp;Loading rollup summary...</div>');

			var campaignIds = [];
			
			api.rows({ search: "applied" }).every(function (rowIdx, tableLoop, rowLoop) {
				var data = this.data();
				campaignIds.push(data.campaign_id);
			});
			
			var startDate = $('.idemailwiz_table_wrapper').find("#wizStartDate").val();
			var endDate = $('.idemailwiz_table_wrapper').find("#wizEndDate").val();
			
			// Fetch rollup summary data
			setTimeout(() => fetchRollUpSummaryData(campaignIds, startDate, endDate, rollupSelector), 500);
		}

		let addButton = table.button("Add:name");
		let removeButton = table.button("Remove:name");

		// Initially, disable the buttons
		addButton.disable();
		removeButton.disable();

		// Flatpickr Inits
		var startFlatpickr = $("#wizStartDate").flatpickr({
			altInput: true,
			altFormat: "m/d/Y",
			dateFormat: "Y-m-d",
		});

		var endFlatpickr = $("#wizEndDate").flatpickr({
			altInput: true,
			altFormat: "m/d/Y",
			dateFormat: "Y-m-d",
		});

		// Listen for date button clicks
		$(".dashboard-date-buttons .wiz-button").on("click", function (e) {
			e.preventDefault(); // Prevent the link from navigating

			var startDate = $(this).data("start");
			var endDate = $(this).data("end");

			// Update the Flatpickr displayed dates
			startFlatpickr.setDate(startDate);
			endFlatpickr.setDate(endDate);

			// Trigger the change event to refresh the table
			$("#wizStartDate, #wizEndDate").trigger("change");
			
		});

		// Listen for select and deselect events
		table.on("select.dt deselect.dt", function (e, dt, type, indexes) {
			const selectedRows = dt.rows({ selected: true }).count();
			const selectedColumns = dt.columns({ selected: true }).count();
			const selectedCells = dt.cells({ selected: true }).count();

			if (selectedRows > 0 && selectedColumns === 0 && selectedCells === 0) {
				// Enable buttons when only rows are selected
				addButton.enable();
				removeButton.enable();
			} else {
				// Disable buttons if anything other than rows are selected
				addButton.disable();
				removeButton.disable();
			}
		});

		// Add date filter search logic
		function addDateFilter() {
			$.fn.dataTable.ext.search.push(function (settings, data, dataIndex, rowData) {
				var startDateInput = $("#wizStartDate").val();
				var endDateInput = $("#wizEndDate").val();

				// Check if the date filter is being used
				if (startDateInput || endDateInput) {
					// Exclude records with "N/A" date
					if (!rowData.campaign_start) return false;

					var campaignDate = new Date(parseInt(rowData.campaign_start)).toISOString().split("T")[0]; // UTC date string

					if (startDateInput && campaignDate < startDateInput) return false;
					if (endDateInput && campaignDate > endDateInput) return false;
				}
				return true;
			});
		}

		// Refresh the table when the dates are changed
		function addDateChangeListener() {
			$("#wizStartDate, #wizEndDate").change(function () {
				var startDate = $("#wizStartDate").val();
				var endDate = $("#wizEndDate").val();

				var newUrl = updateUrlParameter(window.location.href, "startDate", startDate);
				newUrl = updateUrlParameter(newUrl, "endDate", endDate);
				history.pushState(null, null, newUrl);

				table.draw();
			});

			// Check if the date pickers have values on page load and trigger the change event
			if ($("#wizStartDate").val() || $("#wizEndDate").val()) {
				$("#wizStartDate, #wizEndDate").trigger("change");
			}
		}

		// Utility function to update the URL when the dates change
		function updateUrlParameter(url, param, paramVal) {
			var newAdditionalURL = "";
			var tempArray = url.split("?");
			var baseURL = tempArray[0];
			var additionalURL = tempArray[1];
			var temp = "";
			if (additionalURL) {
				tempArray = additionalURL.split("&");
				for (i = 0; i < tempArray.length; i++) {
					if (tempArray[i].split("=")[0] != param) {
						newAdditionalURL += temp + tempArray[i];
						temp = "&";
					}
				}
			}

			var rowsText = temp + "" + param + "=" + paramVal;
			return baseURL + "?" + newAdditionalURL + rowsText;
		}

		

		// Function to move buttons to their respective locations
		function moveButtons() {
			var advSearch = $(".btn-advanced-search").closest(".dt-button");
			advSearch.insertAfter("#wiztable_top_search");
		}

		// Function to update the counter column
		function updateCounterColumn(api) {
			var info = api.page.info();
			var start = info.start;
			api.column(0, { page: "current" })
				.nodes()
				.each(function (cell, i) {
					cell.innerHTML = i + 1 + start;
				});
		}

		function get_wiz_campaign_columns() {
			return [
				{
					className: "row-counter",
					title: "#",
					name: "row-counter",
					orderable: false,
					data: null,
				},

				{
					data: "campaign_start",
					name: "campaign_start",
					title: "Sent At",
					//className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "date",
					render: function (data) {
						return new Date(parseInt(data)).toLocaleString("en-US", {
							month: "numeric",
							day: "numeric",
							year: "numeric",
							hour: "numeric",
							minute: "numeric",
							hour12: true,
						});
					},
					type: "date",
					
				},
				
				
				{
					data: "campaign_name",
					name: "campaign_name",
					title: "Campaign Name<div style='margin-right: 350px;'></div>",
					render: function (data, type, row, meta) {
						var campaignId = row.campaign_id;
						var url = idAjax.site_url + "/metrics/campaign/?id=" + campaignId;
						return '<a href="' + url + '">' + data + "</i></a>";
					},
					className: "idwiz_searchBuilder_enabled campaignName",
					searchBuilderType: "string",
					searchBuilder: {
						defaultCondition: 'contains'
					},
					width: "300px",
				},
				{
					data: "campaign_type",
					name: "campaign_type",
					title: "Type",
					className: "idwiz_searchBuilder_enabled",
					searchBuilder: {
						defaultCondition: '='
					},
					searchBuilderType: "string",
					visible: false
				},
				
				{
					data: "experiment_ids",
					name: "experiment_ids",
					title: '<i class="fa fa-flask"></i>',
					searchBuilderTitle: "Has Experiment",
					searchBuilderType: "string",
					"searchBuilder.defaultConditions": "==",
					className: "idwiz_searchBuilder_enabled customColName",
					type: "bool",
					render: function (data, type) {
						if (type === "display") {
							return data ? '<i class="fa fa-flask"></i>' : "";
						}
						if (type === "filter") {
							return !data ? "False" : "True";
						}
						return data;
					},
					searchBuilder: {
						orthogonal: {
							search: "filter",
							display: "filter",
						},
						defaultCondition: '='
					},
					colvisName: "Has Experiment",
				},
				{
					data: "unique_email_sends",
					name: "unique_email_sends",
					title: "Sends",
					type: "num",
					render: $.fn.dataTable.render.number(",", ""),
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
				},
				{
					data: "unique_delivered",
					name: "unique_delivered",
					title: "Delivered",
					type: "num",
					render: $.fn.dataTable.render.number(",", ""),
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
					visible: false,
				},
				{
					data: "wiz_delivery_rate",
					name: "wiz_delivery_rate",
					title: "Delivery",
					render: function (data) {
						return parseFloat(data).toFixed(2) + "%";
					},
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
				},
				{
					data: "unique_email_opens",
					name: "unique_email_opens",
					title: "Opens",
					type: "num",
					render: $.fn.dataTable.render.number(",", ""),
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
					visible: false,
				},
				{
					data: "wiz_open_rate",
					name: "wiz_open_rate",
					title: "Opened",
					render: function (data) {
						return parseFloat(data).toFixed(2) + "%";
					},
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
				},
				{
					data: "unique_email_clicks",
					name: "unique_email_clicks",
					title: "Clicks",
					type: "num",
					render: $.fn.dataTable.render.number(",", ""),
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
					visible: false,
				},
				{
					data: "wiz_ctr",
					name: "wiz_ctr",
					title: "CTR",
					render: function (data) {
						return parseFloat(data).toFixed(2) + "%";
					},
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
				},
				{
					data: "wiz_cto",
					name: "wiz_cto",
					title: "CTO",
					render: function (data) {
						return parseFloat(data).toFixed(2) + "%";
					},
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
				},
				{
					data: "unique_unsubscribes",
					name: "unique_unsubscribes",
					title: "Unsubs",
					type: "num",
					render: function (data, type, row) {
						return $.fn.dataTable.render.number(",", "").display(data);
					},
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
					visible: false,
				},
				{
					data: "wiz_unsub_rate",
					name: "wiz_unsub_rate",
					title: "Unsubed",
					render: function (data) {
						return parseFloat(data).toFixed(2) + "%";
					},
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
				},
				{
					data: "unique_purchases",
					name: "unique_purchases",
					type: "num",
					title: "Purchases",
					render: function (data, type, row) {
						return $.fn.dataTable.render.number(",", "").display(data);
					},
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
				},
				{
					data: "wiz_cvr",
					name: "wiz_cvr",
					title: "CVR",
					render: function (data) {
						return parseFloat(data).toFixed(2) + "%";
					},
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
				},
				{
					data: "revenue",
					name: "revenue",
					title: "Revenue",
					render: function (data) {
						return "$" + parseFloat(data).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
					},
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
				},
				{
					data: "ga_revenue",
					name: "ga_revenue",
					title: "GA Revenue",
					render: function (data, type) {
						if (type === 'display') {
							return "$" + parseFloat(data).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
						}
						return data; // Return unformatted data for sorting/filtering
					},
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num-fmt",
					type: 'num', // Explicitly specify the type as numeric
				},
				{
					data: "template_subject",
					name: "template_subject",
					title: "Subject Line",
					render: $.fn.dataTable.render.ellipsis(40, true),
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "string",
					searchBuilder: {
						defaultCondition: 'contains'
					},
				},
				{
					data: "template_preheader",
					name: "template_preheader",
					title: "Pre Header",
					render: $.fn.dataTable.render.ellipsis(40, true),
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "string",
					searchBuilder: {
						defaultCondition: 'contains'
					},
				},
				{
					data: "initiative_links",
					name: "initiative_links",
					title: "Initiatives<div style='margin-right: 150px;'></div>",
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "string",
					render: function (data, type, row, meta) {
						if (type === "display") {
							return data || "";
						}
						if (type === "filter") {
							// Extract the inner text (initiative names) for filtering in the SearchBuilder
							if (data) {
								var parser = new DOMParser();
								var doc = parser.parseFromString(data, "text/html");
								var anchorTags = doc.querySelectorAll("a");
								var uniqueNames = Array.from(new Set(Array.from(anchorTags).map((a) => a.innerText)));

								// Exclude blank or whitespace-only names
								var filteredNames = uniqueNames.filter((name) => name.trim() !== "");

								// If filteredNames is empty, return an empty string
								return filteredNames.length > 0 ? filteredNames.join("~") : "";
							}
							return "";
						}
						return data;
					},
				},
				{
					data: "message_medium",
					name: "message_medium",
					title: "Medium",
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "string",
					searchBuilder: {
						defaultCondition: '='
					},
				},
				{
					data: "campaign_id",
					name: "campaign_id",
					title: "ID",
					className: "idwiz_searchBuilder_enabled",
					searchBuilderType: "num",
					searchBuilder: {
						defaultCondition: '='
					},
				},
				
			];
		}

		function get_wiz_campaign_buttons() {
			return [
				{
					extend: "searchBuilder",
					background: false,
					text: '<i class="fa-solid fa-sliders"></i>',
					className: "btn-advanced-search wiz-dt-button",
					attr: {
						title: "Advanced search and filter",
					},
					config: {
						columns: ".idwiz_searchBuilder_enabled",
					},
					// Add a class to the popover for SearchBuilder so we can resize it with CSS
					action: function (e, dt, node, config) {
						this.popover(config._searchBuilder.getNode(), {
							collectionLayout: "wiz_sbpopover",
						});
						// Need to redraw the contents to calculate the correct positions for the elements
						if (config._searchBuilder.s.topGroup !== undefined) {
							config._searchBuilder.s.topGroup.dom.container.trigger("dtsb-redrawContents");
						}
						if (config._searchBuilder.s.topGroup.s.criteria.length === 0) {
							$("." + $.fn.dataTable.Group.classes.add).click();
						}
					},
				},
				{
					extend: "selected",
					text: '<i class="fa-solid fa-rotate"></i>  Sync Selected',
					titleAttr: "Sync selected campaigns",
					className: "wiz-dt-button sync-selected",
					action: function (e, dt, node, config) {
						// Use the DataTables button element (node) as the $button argument
						let $button = $(node);

						let selectedRowIndices = dt.rows({ selected: true }).indexes().toArray();
						let selectedCampaignIdsString = selectedRowIndices.map((index) => dt.cell(index, "campaign_id:name").data()).join(',');
						let selectedCampaignIds = selectedCampaignIdsString.split(',');

						// Disable the button, change its text, and add the spinner class to the icon
						$button.addClass('disabled');
						$button.data('original-text', $button.html());
						$button.html('<i class="fa-solid fa-arrows-rotate fa-spin"></i>&nbsp;&nbsp;Syncing...');

						// Disable the button here if handle_idwiz_sync_buttons expects a button jQuery object
						$button.addClass('disabled').prop('disabled', true);

						// Call the sync function with the campaign IDs and the button
						handle_idwiz_sync_buttons(['blast'], selectedCampaignIds, $button);
					}
				},
				{
					extend: "selected",
					text: '<i class="fa-regular fa-plus"></i>',
					name: "Add",
					className: "wiz-dt-button",
					attr: { title: "Add to Initiative" },
					action: function (e, dt, node, config) {
						let selectedRowIndices = dt.rows({ selected: true }).indexes().toArray();
						let selectedCampaignIds = selectedRowIndices.map((index) => dt.cell(index, "campaign_id:name").data());
						window.manageCampaignsInInitiative("add", selectedCampaignIds, dt.ajax.reload);
					},
				},
				{
					extend: "selected",
					text: '<i class="fa-solid fa-minus"></i>',
					name: "Remove",
					className: "wiz-dt-button",
					attr: { title: "Remove from Initiative" },
					action: function (e, dt, node, config) {
						let selectedRowIndices = dt.rows({ selected: true }).indexes().toArray();
						let selectedCampaignIds = selectedRowIndices.map((index) => dt.cell(index, "campaign_id:name").data());
						window.manageCampaignsInInitiative("remove", selectedCampaignIds, dt.ajax.reload);
					},
				},

				{
					extend: "spacer",
					style: "bar",
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
						{
							extend: "colvis",
							columnText: function (dt, idx, title) {
								if (idx == dt.colReorder.transpose(1)) {
									return "Info";
								}
								if (idx == dt.colReorder.transpose(5)) {
									return "Has Experiment";
								} else {
									return title;
								}
							},
						},
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
					extend: "collection",
					text: '<i class="fa-regular fa-hand-pointer"></i>',
					className: "wiz-dt-button",
					attr: {
						title: "Selection mode",
					},
					align: "button-right",
					autoClose: true,
					buttons: ["selectNone", "selectRows", "selectColumns", "selectCells"],
					background: false,
				},
				{
					extend: "spacer",
					style: "bar",
				},

				{
					extend: "collection",
					text: '<i class="fa-solid fa-file-arrow-down"></i>',
					className: "wiz-dt-button",
					attr: {
						title: "Export current view",
					},
					align: "button-right",
					autoClose: true,
					buttons: ["copy", "csv", "excel"],
					background: false,
				},
				{
					extend: "spacer",
					style: "bar",
				},
				{
					extend: "pageLength",
					className: "wiz-dt-button",
					background: false,
				},
			];
		}

		function get_wiz_campaign_languages() {
			return {
				searchBuilder: {
					data: "Select column...",
					title: "Advanced Campaign Filter",
					button: {
						0: '<i class="fa-solid fa-sliders"></i> Filters',
						_: '<i class="fa-solid fa-sliders"></i> Filters (%d)',
					},
				},
				search: "",
				searchPlaceholder: "Quick search",
			};
		}
	}
});
