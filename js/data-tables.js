jQuery(document).ready(function ($) {
	let lastCallTime = 0;
	const debounceTime = 500; // 500 milliseconds

	//Date sort plugin
	$.fn.dataTable.moment("x");

    // Fetch campaign_type from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const campaignTypeFromUrl = urlParams.get('view') || 'Blast'; // Default to 'Blast'

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
			[2, "asc"],
			[1, "desc"],
		],
		autoWidth: false,
		fixedColumns: { left: 2 },
		columns: get_wiz_campaign_columns(),
		buttons: get_wiz_campaign_buttons(),
		language: get_wiz_campaign_languages(),
		dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>rt',
		fixedHeader: { header: true, footer: false },
		colReorder: { realtime: false },
		scroller: true,
		scrollX: true,
		scrollY: "700px",
		paging: true,
		scrollResize: true,
		scrollCollapse: true,
		processing: true,
		select: {
			selector: "td:not(:first-child)",
			style: "os",
			items: "row",
		},
		stateSave: true,
		drawCallback: idwiz_dt_draw_callback,
		initComplete: idwiz_dt_init_callback,
	});

	// Handle state save and restore
	setupStateHandling(table);

	// Handle state save and restore
	function setupStateHandling(table) {
		table.on("stateSaveParams", function (e, settings, data) {
			// Save the current values of the date pickers
			data.startDate = $("#wiztable_startDate").val();
			data.endDate = $("#wiztable_endDate").val();
		});

		table.on("stateLoadParams", function (e, settings, data) {
			// Delay the restoration of the date picker values
			setTimeout(function () {
				$("#wiztable_startDate").val(data.startDate);
				$("#wiztable_endDate").val(data.endDate);
			}, 500);
		});
	}

	// Update rollup summary on each table draw
	table.on("draw.dt", function () {
		fetchRollUpSummaryData(table);
	});

	// Main DT Initiation callback function
	function idwiz_dt_init_callback(settings, json) {
		appendDateSelector();
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
	}

	let addButton = table.button("Add:name");
	let removeButton = table.button("Remove:name");

	// Initially, disable the buttons
	addButton.disable();
	removeButton.disable();

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

	// Custom function to handle Ajax for state saving and loading
	function idwiz_handle_states(data, callback) {
		const currentTime = Date.now();

		if (currentTime - lastCallTime < debounceTime) {
			console.log("Function call debounced");
			return;
		}

		lastCallTime = currentTime;
		console.log("idwiz_handle_states called with data:", data);

		// Include the action type in the data to be sent to the server
		var ajaxData = {
			dataTableAction: data.action, // This is the DataTables action like "load" or "save"
			stateRestore: data.stateRestore,
		};

		
	}

	// Append date range into DataTables DOM
	function appendDateSelector() {
		var dateSelector = '<div id="idemailwiz_date_range"><label><input type="date" id="wiztable_startDate"></label>&nbsp;thru&nbsp;<label><input type="date" id="wiztable_endDate"></label></div>';
		$("#wiztable_top_dates").append(dateSelector);
	}

	// Add date filter search logic
	function addDateFilter() {
		$.fn.dataTable.ext.search.push(function (settings, data, dataIndex, rowData) {
			var startDateInput = $("#wiztable_startDate").val();
			var endDateInput = $("#wiztable_endDate").val();

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
		$("#wiztable_startDate, #wiztable_endDate").change(function () {
			$(".idemailwiz_table").DataTable().draw();
		});
	}

	// Fetch and fill the rollup summary
	function fetchRollUpSummaryData(api) {
		var campaignIds = [];
		api.rows({ search: "applied" }).every(function (rowIdx, tableLoop, rowLoop) {
			var data = this.data();
			campaignIds.push(data.campaign_id);
		});
		var fields = {
			campaignCount: {
				label: "Campaigns",
				format: "num",
			},
			uniqueEmailSends: {
				label: "Sends",
				format: "num",
			},
			uniqueEmailOpens: {
				label: "Opens",
				format: "num",
			},
			wizOpenRate: {
				label: "Open Rate",
				format: "perc",
			},
			uniqueEmailClicks: {
				label: "Clicks",
				format: "num",
			},
			wizCtr: {
				label: "CTR",
				format: "perc",
			},
			wizCto: {
				label: "CTO",
				format: "perc",
			},
			uniquePurchases: {
				label: "Purchases",
				format: "num",
			},
			revenue: {
				label: "Revenue",
				format: "money",
			},
		};

		const additionalData = {
			campaignIds: campaignIds,
			fields: fields,
		};

		idemailwiz_do_ajax("idwiz_generate_dynamic_rollup", idAjax_data_tables.nonce, additionalData, getRollupSuccess, getRollupError, "html");

		function getRollupSuccess(response) {
			$("#campaigns-table-rollup").replaceWith(response);
		}

		function getRollupError(response) {
			console.log("Rollup metrics error: " + response);
		}
	}

	// Function to move buttons to their respective locations
	function moveButtons() {
		var advSearch = $(".btn-advanced-search").closest(".dt-button");
		advSearch.insertAfter("#wiztable_top_search");

		var FYbutton = $(".dt-button.fiscal-year");
		var prevMonthButton = $(".dt-button.prev-month");
		var thisMonthButton = $(".dt-button.current-month");
		var nextMonthButton = $(".dt-button.next-month");
		thisMonthButton.insertAfter("#wiztable_top_dates");
		prevMonthButton.insertBefore(thisMonthButton);
		nextMonthButton.insertAfter(thisMonthButton);

		FYbutton.insertAfter(nextMonthButton);
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
				width: "20px",
			},

			{
				data: "campaign_start",
				name: "campaign_start",
				title: "Sent At",
				className: "idwiz_searchBuilder_enabled",
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
				data: "campaign_type",
				name: "campaign_type",
				title: "Type",
				className: "idwiz_searchBuilder_enabled",
				searchBuilderType: "string",
			},
			{
				data: "message_medium",
				name: "message_medium",
				title: "Medium",
				className: "idwiz_searchBuilder_enabled",
				searchBuilderType: "string",
			},
			{
				data: "campaign_name",
				name: "campaign_name",
				title: "Campaign Name",
				render: function (data, type, row, meta) {
					var campaignId = row.campaign_id;
					var url = "https://localhost/metrics/campaign/?id=" + campaignId;
					return '<a href="' + url + '">' + data + "</i></a>";
				},
				className: "idwiz_searchBuilder_enabled",
				searchBuilderType: "string",
			},
			{
				data: "initiative_links",
				name: "initiative_links",
				title: "Initiatives",
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
				data: "campaign_labels",
				name: "campaign_labels",
				title: "Labels",
				className: "idwiz_searchBuilder_enabled",
				searchBuilderType: "string",
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
						return !data ? "True" : "False";
					}
					return data;
				},
				searchBuilder: {
					orthogonal: {
						search: "filter",
						display: "filter",
					},
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
			},
			{
				data: "wiz_delivery_rate",
				name: "wiz_delivery_rate",
				title: "Deliv. Rate",
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
				render: $.fn.dataTable.render.number(",", ""),
				className: "idwiz_searchBuilder_enabled",
				searchBuilderType: "num-fmt",
			},
			{
				data: "wiz_open_rate",
				name: "wiz_open_rate",
				title: "Open Rate",
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
				render: $.fn.dataTable.render.number(",", ""),
				className: "idwiz_searchBuilder_enabled",
				searchBuilderType: "num-fmt",
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
				title: "Unsubs.",
				render: function (data, type, row) {
					return $.fn.dataTable.render.number(",", "").display(data);
				},
				className: "idwiz_searchBuilder_enabled",
				searchBuilderType: "num-fmt",
			},
			{
				data: "wiz_unsub_rate",
				name: "wiz_unsub_rate",
				title: "Unsub. Rate",
				render: function (data) {
					return parseFloat(data).toFixed(2) + "%";
				},
				className: "idwiz_searchBuilder_enabled",
				searchBuilderType: "num-fmt",
			},
			{
				data: "unique_purchases",
				name: "unique_purchases",
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
				data: "template_subject",
				name: "template_subject",
				title: "Subject Line",
				render: $.fn.dataTable.render.ellipsis(40, true),
				className: "idwiz_searchBuilder_enabled",
				searchBuilderType: "string",
			},
			{
				data: "template_preheader",
				name: "template_preheader",
				title: "Pre Header",
				render: $.fn.dataTable.render.ellipsis(40, true),
				className: "idwiz_searchBuilder_enabled",
				searchBuilderType: "string",
			},

			{
				data: "campaign_id",
				name: "campaign_id",
				title: "ID",
				className: "idwiz_searchBuilder_enabled",
				searchBuilderType: "num",
				"searchBuilder.defaultConditions": "==",
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
				text: '<i class="fa-solid fa-angle-left"></i>',
				className: "wiz-dt-button skinny prev-month",
				action: function (e, dt, node, config) {
					var table = $(".idemailwiz_table").DataTable();
					table.button(".next-month").enable(); // Enable the "Next" button

					var startDateStr = $("#wiztable_startDate").val();
					var startDate = startDateStr ? new Date(startDateStr) : new Date();
					startDate.setUTCHours(0, 0, 0, 0);

					var today = new Date();
					today.setUTCHours(0, 0, 0, 0);

					if (isNaN(startDate.getTime()) || !startDateStr) {
						startDate = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 1));
					}

					startDate.setUTCMonth(startDate.getUTCMonth() - 1);
					var endDate = new Date(Date.UTC(startDate.getUTCFullYear(), startDate.getUTCMonth() + 1, 0));

					$("#wiztable_startDate").val(startDate.toISOString().split("T")[0]);
					$("#wiztable_endDate").val(endDate.toISOString().split("T")[0]);
					table.draw();
				},
			},
			{
				text: function () {
					var today = new Date();
					var monthAbbr = today.toLocaleString("default", { month: "short" }); // Get the three-letter abbreviation
					var yearTwoDigit = today.getFullYear() % 100; // Get the last two digits of the year
					return `<i class="fa-regular fa-calendar"></i>&nbsp;&nbsp;${monthAbbr} '${yearTwoDigit}`;
				},
				action: function (e, dt, node, config) {
					var today = new Date();
					var firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
					var lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

					$("#wiztable_startDate").val(firstDayOfMonth.toISOString().split("T")[0]);
					$("#wiztable_endDate").val(lastDayOfMonth.toISOString().split("T")[0]);
					
					table.draw();
				},
				className: "wiz-dt-button current-month",
			},
			{
				text: '<i class="fa-solid fa-angle-right"></i>',
				className: "wiz-dt-button skinny next-month",
				action: function (e, dt, node, config) {
					var table = $(".idemailwiz_table").DataTable();

					var startDateStr = $("#wiztable_startDate").val();
					var startDate = startDateStr ? new Date(startDateStr) : new Date();
					startDate.setUTCHours(0, 0, 0, 0);

					var today = new Date();
					today.setUTCHours(0, 0, 0, 0);

					if (isNaN(startDate.getTime()) || !startDateStr) {
						startDate = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 1));
						table.button(".next-month").disable(); // Disable the "Next" button when fields are empty or unorthodox
						return;
					}

					if (startDate.getUTCFullYear() < today.getUTCFullYear() || (startDate.getUTCFullYear() === today.getUTCFullYear() && startDate.getUTCMonth() < today.getUTCMonth())) {
						startDate.setUTCMonth(startDate.getUTCMonth() + 1);
						var endDate = new Date(Date.UTC(startDate.getUTCFullYear(), startDate.getUTCMonth() + 1, 0));

						$("#wiztable_startDate").val(startDate.toISOString().split("T")[0]);
						$("#wiztable_endDate").val(endDate.toISOString().split("T")[0]);
						
						table.draw();
					}

					if (startDate.getUTCFullYear() === today.getUTCFullYear() && startDate.getUTCMonth() >= today.getUTCMonth()) {
						table.button(".next-month").disable(); // Disable the "Next" button
					} else {
						table.button(".next-month").enable(); // Enable the "Next" button
					}
				},
			},

			{
				text: function () {
					var today = new Date();
					var year = today.getFullYear();

					// Fiscal year starts on November 1st of the previous year
					if (today.getMonth() >= 10) {
						// January is 0, November is 10
						year += 1;
					}

					var yearTwoDigit = year % 100;
					return `<i class="fa-regular fa-calendar"></i>&nbsp;&nbsp;FY '${yearTwoDigit}`;
				},
				action: function (e, dt, node, config) {
					var today = new Date();
					var fiscalYearStart = new Date(today.getFullYear(), 10, 1); // Nov 1
					var fiscalYearEnd = new Date(today.getFullYear() + 1, 9, 31); // Oct 31
					if (today < fiscalYearStart) {
						fiscalYearStart.setFullYear(fiscalYearStart.getFullYear() - 1);
						fiscalYearEnd.setFullYear(fiscalYearEnd.getFullYear() - 1);
					}
					$("#wiztable_startDate").val(fiscalYearStart.toISOString().split("T")[0]);
					$("#wiztable_endDate").val(fiscalYearEnd.toISOString().split("T")[0]);
					$(".idemailwiz_table").DataTable().draw();
				},
				className: "wiz-dt-button fiscal-year",
			},

			{
				extend: "selected",
				text: '<i class="fa-solid fa-rotate"></i>',
				name: "Sync",
				className: "wiz-dt-button sync-selected",
				attr: { title: "Sync selected campaigns" },
				action: function (e, dt, node, config) {
					let selectedRowIndices = dt.rows({ selected: true }).indexes().toArray();
					let selectedCampaignIds = selectedRowIndices.map((index) => dt.cell(index, "campaign_id:name").data());
					handle_idwiz_sync_buttons("idemailwiz_ajax_sync", idAjax_data_tables.nonce, { campaignIds: JSON.stringify(selectedCampaignIds) });
				},
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
							if (idx == dt.colReorder.transpose(7)) {
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
});
