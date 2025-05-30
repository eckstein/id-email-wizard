jQuery(document).ready(function ($) {

	// Tiny Tables
	if ($(".wizcampaign-tiny-table").length) {
		// Define the DataTables options based on classes
		var tableOptions = {
			dom: 'ltp',
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
		};

		// Initialize DataTables for each table
		$(".wizcampaign-tiny-table").each(function() {
			var $table = $(this);
			var options = $.extend({}, tableOptions);

			// Modify options based on classes
			if ($table.hasClass("static")) {
				options.sort = false;
			}
			if ($table.hasClass("tall")) {
				options.scrollY = '400px'; // Set the desired height for tall tables
			} else {
				options.scrollY = '250px'; // Default height for other tables
			}

			// Initialize DataTable with the modified options
			var table = $table.DataTable(options);

			$(document).on('click', '[data-coladjust="true"]', function () {
				table.columns.adjust();
			});
		});
	}


	if ($("#idemailwiz_campaign_table").length) {
		//Date sort plugin
		$.fn.dataTable.moment("x");

		// Fetch campaign_type from URL parameters
		const urlParams = new URLSearchParams(window.location.search);
		const campaignTypeFromUrl = urlParams.get("view") || "Blast"; // Default to 'Blast'

		// Variable to store fetched filter options
		var wizFilterOptions = { mediums: [], labels: [] };

		// Initialize DataTables with the parsed data
		var table = $("#idemailwiz_campaign_table").DataTable({
			ajax: {
				url: idAjax.ajaxurl,
				type: "POST",
				data: function (d) {
					d.action = "idwiz_get_campaign_table_view";
					d.security = idAjax_data_tables.nonce;
					d.campaign_type = campaignTypeFromUrl;
					// Send current date filters for server-side filtering
					d.startDate = $('#wizStartDate').val(); 
					d.endDate = $('#wizEndDate').val();
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
			dom: '<"#wiztable_top_filters" > <"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>rptp',
			fixedHeader: { header: false, footer: false },
			colReorder: { realtime: false },
			//scroller: true,
			scrollX: true,
			//scrollY: "650px",
			paging: true,
			lengthMenu: [
				[25, 50, 100, 200, 350, 500, 1000, -1],
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
			stateSaveParams: function (settings, data) {
				// Save the current values of the date pickers
				data.startDate = $("#wizStartDate").val();
				data.endDate = $("#wizEndDate").val();

				// Save active filter buttons
				data.activeMediums = [];
				$('#wiztable-medium-filters .wiz-filter-button.active').each(function() {
					data.activeMediums.push($(this).data('filter-value'));
				});
				data.activeLabels = [];
				$('#wiztable-label-filters .wiz-filter-button.active').each(function() {
					data.activeLabels.push($(this).data('filter-value'));
				});
			},
			stateLoadParams: function (settings, data) {
				// Restore the date picker values
				$("#wizStartDate").val(data.startDate).trigger('change');
				$("#wizEndDate").val(data.endDate).trigger('change');
			},
			drawCallback: idwiz_dt_draw_callback,
			initComplete: function(settings, json) {
				idwiz_dt_init_callback(settings, json);
				// Restore filter button state after table and buttons are initialized
				var state = table.state.loaded();
				if (state) {
					if (state.activeMediums && state.activeMediums.length > 0) {
						state.activeMediums.forEach(function(medium) {
							$('#wiztable-medium-filters .wiz-filter-button[data-filter-value="' + medium + '"]').addClass('active');
						});
					}
					if (state.activeLabels && state.activeLabels.length > 0) {
						state.activeLabels.forEach(function(label) {
							$('#wiztable-label-filters .wiz-filter-button[data-filter-value="' + label + '"]').addClass('active');
						});
					}
					// Trigger filter application based on restored state
					applyDynamicFilters(); 
				}
			}
		});

		// Handle state save and restore - Moved state save/load directly into DT init options
		// setupStateHandling(table);

		// Function to apply filters based on active buttons
		function applyDynamicFilters() {
			let activeMediums = [];
			$('#wiztable-medium-filters .wiz-filter-button.active').each(function() {
				activeMediums.push($(this).data('filter-value'));
			});

			let activeLabels = [];
			$('#wiztable-label-filters .wiz-filter-button.active').each(function() {
				// Escape special regex characters in labels
				let label = $(this).data('filter-value').replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
				activeLabels.push(label);
			});

			let mediumRegex = activeMediums.length ? '^(' + activeMediums.join('|') + ')$' : '';
			// Search for labels within comma-separated values or as the sole value
			// Ensures we match whole labels, e.g., "Prospects" not "Prospects, Global"
			let labelRegex = activeLabels.length ? '\\b(' + activeLabels.join('|') + ')\\b' : ''; 


			table.column('message_medium:name').search(mediumRegex, true, false);
			table.column('campaign_labels:name').search(labelRegex, true, false);

			// Only draw if filters changed
			table.draw(); // Always redraw when filters might have changed
		}

		// Function to extract unique mediums and labels from the table data
		function extractUniqueFiltersFromData(data) {
			let uniqueMediums = new Set();
			let uniqueLabels = new Set();

			if (data && data.length) {
				data.forEach(row => {
					if (row.message_medium) {
						uniqueMediums.add(row.message_medium);
					}
					if (row.campaign_labels) {
						// Split comma-separated labels and add individually
						row.campaign_labels.split(',').forEach(label => {
							let trimmedLabel = label.trim();
							if (trimmedLabel) {
								uniqueLabels.add(trimmedLabel);
							}
						});
					}
				});
			}

			return {
				mediums: Array.from(uniqueMediums).sort(),
				labels: Array.from(uniqueLabels).sort()
			};
		}

		// Function to create filter buttons
		function createFilterButtons(filterOptions) {
			var mediumFiltersHtml = '<div class="wiztable-filter-group" id="wiztable-medium-filters"><strong>Medium:</strong> ';
			filterOptions.mediums.forEach(function(medium) {
				mediumFiltersHtml += '<button class="wiz-filter-button" data-filter-type="medium" data-filter-value="' + medium + '">' + medium + '</button>';
			});
			mediumFiltersHtml += '</div>';

			var labelFiltersHtml = '<div class="wiztable-filter-group" id="wiztable-label-filters"><strong>Cohorts:</strong> ';
			filterOptions.labels.forEach(function(label) {
				labelFiltersHtml += '<button class="wiz-filter-button" data-filter-type="label" data-filter-value="' + label + '">' + label + '</button>';
			});
			labelFiltersHtml += '</div>';

			$('#wiztable_top_filters').html(mediumFiltersHtml + labelFiltersHtml);

			// Add click handlers
			$('.wiz-filter-button').on('click', function() {
				$(this).toggleClass('active');
				applyDynamicFilters();
			});
		}

		// Main DT Initiation callback function
		function idwiz_dt_init_callback(settings, json) {
			addDateChangeListener();

			// Extract filters from the initial data load
			if (json && json.data) {
				wizFilterOptions = extractUniqueFiltersFromData(json.data);
				createFilterButtons(wizFilterOptions);

				// Restore state for buttons *after* they are created
				var state = table.state.loaded();
				if (state) {
					if (state.activeMediums) {
						state.activeMediums.forEach(medium => $('#wiztable-medium-filters .wiz-filter-button[data-filter-value="' + medium + '"]').addClass('active'));
					}
					if (state.activeLabels) {
						state.activeLabels.forEach(label => $('#wiztable-label-filters .wiz-filter-button[data-filter-value="' + label + '"]').addClass('active'));
					}
				}
			}
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
			
			// Get IDs from the *filtered and paged* view for the rollup
			api.rows({ search: "applied" }).data().each(function(data) {
				campaignIds.push(data.campaign_id);
			});
			
			var startDate = $('.idemailwiz_table_wrapper').find("#wizStartDate").val();
			var endDate = $('.idemailwiz_table_wrapper').find("#wizEndDate").val();
			
			// Fetch rollup summary data
			// Debounce or delay slightly to avoid rapid firing during redraws
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

				// Get the selected data and calculate summary
				const selectedData = dt.rows({ selected: true }).data().toArray();
				const summaryString = calculateSummary(selectedData);

				// Update the DOM element with the summary
				$('#campaign-table-selected-rollup').html(summaryString).show();
			} else {
				// Disable buttons if anything other than rows are selected
				addButton.disable();
				removeButton.disable();

				// Hide the summary when no rows are selected
				$('#campaign-table-selected-rollup').hide();
			}
		});


		function calculateSummary(selectedData) {
			let summary = {
				sends: 0,
				delivered: 0,
				opens: 0,
				clicks: 0,
				unsubscribes: 0,
				purchases: 0,
				revenue: 0,
				gaRevenue: 0
			};

			selectedData.forEach(row => {
				summary.sends += parseInt(row.unique_email_sends) || 0;
				summary.delivered += parseInt(row.unique_delivered) || 0;
				summary.opens += parseInt(row.unique_email_opens) || 0;
				summary.clicks += parseInt(row.unique_email_clicks) || 0;
				summary.unsubscribes += parseInt(row.unique_unsubscribes) || 0;
				summary.purchases += parseInt(row.unique_purchases) || 0;
				summary.revenue += parseFloat(row.revenue) || 0;
				summary.gaRevenue += parseFloat(row.ga_revenue) || 0;
			});

			const deliveryRate = (summary.delivered / summary.sends * 100).toFixed(2);
			const openRate = (summary.opens / summary.delivered * 100).toFixed(2);
			const clickRate = (summary.clicks / summary.delivered * 100).toFixed(2);
			const unsubRate = (summary.unsubscribes / summary.delivered * 100).toFixed(2);
			const conversionRate = (summary.purchases / summary.delivered * 100).toFixed(2);

			return `Sends: ${summary.sends.toLocaleString()} | ` +
				   `Delivery: ${deliveryRate}% | ` +
				   `Opens: ${openRate}% | ` +
				   `Clicks: ${clickRate}% | ` +
				   `Unsubscribes: ${unsubRate}% | ` +
				   `Purchases: ${summary.purchases.toLocaleString()} | ` +
				   `CVR: ${conversionRate}% | ` +
				   `Revenue: $${summary.revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} | ` +
				   `GA Revenue: $${summary.gaRevenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
		}



		// Refresh the table when the dates are changed
		function addDateChangeListener() {
			$("#wizStartDate, #wizEndDate").change(function () {
				var startDate = $("#wizStartDate").val();
				var endDate = $("#wizEndDate").val();

				var newUrl = updateUrlParameter(window.location.href, "startDate", startDate);
				newUrl = updateUrlParameter(newUrl, "endDate", endDate);
				history.pushState(null, null, newUrl);

				table.ajax.reload(); // Reload data from server with new dates
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
					data: "campaign_labels",
					name: "campaign_labels",
					title: "Labels",
					className: "idwiz_searchBuilder_enabled ellipsis",
					render: $.fn.dataTable.render.ellipsis(30, true),
					searchBuilderType: "string",
					searchBuilder: {
						defaultCondition: 'contains',
						preDefined: {
							getOptions: function(callback) {
								// Use the globally stored wizFilterOptions extracted from initial data
								callback(wizFilterOptions.labels || []); 
							}
						}
					},
					// visible: false, // Initially hide? Or show truncated?
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
					data: "promo_links",
					name: "promo_links",
					title: "Promo Codes<div style='margin-right: 150px;'></div>",
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
					extend: "collection",
					text: 'Connections',
					className: 'wiz-dt-button',
					background: false,
					buttons: [
						{
							extend: "selected",
							text: 'Add to Initiative',
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
							text: 'Remove from Initiative',
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
							extend: "selected",
							text: 'Add Promo Code',
							name: "Add",
							className: "wiz-dt-button",
							attr: { title: "Add to Promo" },
							action: function (e, dt, node, config) {
								let selectedRowIndices = dt.rows({ selected: true }).indexes().toArray();
								let selectedCampaignIds = selectedRowIndices.map((index) => dt.cell(index, "campaign_id:name").data());
								window.manageCampaignsInPromoCode("add", selectedCampaignIds, dt.ajax.reload);
							},
						},
						{
							extend: "selected",
							text: 'Remove Promo Code',
							name: "Remove",
							className: "wiz-dt-button",
							attr: { title: "Remove from Promo" },
							action: function (e, dt, node, config) {
								let selectedRowIndices = dt.rows({ selected: true }).indexes().toArray();
								let selectedCampaignIds = selectedRowIndices.map((index) => dt.cell(index, "campaign_id:name").data());
								window.manageCampaignsInPromoCode("remove", selectedCampaignIds, dt.ajax.reload);
							},
						}
					]
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
					title: "Advanced Campaign Filter (scroll to preview results)",
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
