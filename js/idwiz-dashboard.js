(function ($) {
	// Extract startDate and endDate from query params or set default values
	var currentMonth = (new Date().getMonth() + 1).toString().padStart(2, "0");
	var currentYear = new Date().getFullYear();

	var startDateParam = new URLSearchParams(window.location.search).get("startDate") || `${currentYear}-${currentMonth}-01`;
	var endDateParam = new URLSearchParams(window.location.search).get("endDate") || new Date().toISOString().slice(0, 10);

	$(document).ready(function() {
		if ($('.wiz_dashboard').length) {
			initializeDashboard();
		}
	});


	

	function initializeDashboard() {
		// Select2 Inits
		$("#divisionsSelect").select2();
		$("#wizMonthDropdown, #wizYearDropdown").select2();

		// Flatpickr Inits
		$("#wizStartDate, #wizEndDate").flatpickr({
			altInput: true,
			altFormat: "m/d/Y",
			dateFormat: "Y-m-d",
		});

		// Triggered campaign checkbox switch
		// Retrieve the 'showTriggered' parameter from the URL
		let params = new URLSearchParams(window.location.search);
		let showTriggeredValue = params.get('showTriggered');

		// Set the checkbox's state based on the parameter's value
		if (showTriggeredValue === 'true') {
			$('#toggleTriggeredDash').prop('checked', true);
		} else {
			$('#toggleTriggeredDash').prop('checked', false);
		}

		// Update URL parameter on toggle
		$('#toggleTriggeredDash').change(function() {
			let isChecked = $(this).is(':checked');
			params.set('showTriggered', isChecked ? 'true' : 'false');
			window.location.search = params.toString();
		});

		if ($("article.wiz_dashboard").length) {
			// Fetch the data via AJAX
			idemailwiz_do_ajax(
				"idwiz_handle_monthly_goal_chart_request",
				idAjax_dashboard.nonce,
				{ startDate: startDateParam, endDate: endDateParam },
				function (response) {
					if (response.success) {
						const data = response.data;
						const canvas = $("#monthlyGoalTracker")[0];
						const ctx = canvas.getContext("2d");

						// Initialize the Chart.js chart
						const monthlyGoalChart = new Chart(ctx, {
							type: "bar",
							data: {
								labels: [""],
								datasets: [
									{
										yAxisID: "revenue",
										data: [data.totalRevenue],
										backgroundColor: ["rgba(54, 162, 235, 0.2)"],
									},
								],
							},
							options: {
								maintainAspectRatio: false,
								responsive: true,
								plugins: {
									legend: {
										display: false,
									},
									tooltip: {
										enabled: true,
										callbacks: {
											title: function () {
												return "Monthly Progress";
											},
											label: function (context) {
												return "Total Revenue: " + formatMoney(data.totalRevenue);
											},
											footer: function () {
												return "Percent to Goal: " + data.percentToGoal.toFixed(2) + "%";
											},
										},
									},
								},
								scales: {
									revenue: {
										display: false, // Hide this axis
										drawBorder: false, // Hide vertical axis line
										beginAtZero: true,
										max: data.monthlyProjection,
										ticks: {
											display: false, // Hide tick marks
										},
										grid: {
											display: false, // Hide grid lines
										},
									},
									percent: {
										display: false, // Hide this axis
										drawBorder: false,
										position: "right",
										beginAtZero: true,
										max: 100,
										ticks: {
											display: false, // Hide tick marks
										},
										grid: {
											display: false, // Hide grid lines
										},
									},
								},
								layout: {
									padding: {
										left: 0,
										right: 0,
										top: 0,
										bottom: 0,
									},
								},
								animation: {
									onProgress: function () {
										const chartInstance = monthlyGoalChart,
											ctx = chartInstance.ctx;

										ctx.textAlign = "center";
										ctx.textBaseline = "bottom";

										const canvasHeight = chartInstance.canvas.height;

										chartInstance.data.datasets.forEach(function (dataset, i) {
											const meta = chartInstance.getDatasetMeta(i);
											meta.data.forEach(function (bar, index) {
												const data = dataset.data[index];
												const percent = ((data / chartInstance.options.scales["revenue"].max) * 100).toFixed(2);

												// Fixed y-position to align with the bottom axis
												const yPos = canvasHeight - 200; // pixels above the bottom

												// Draw the percentage (Bold and Large)
												ctx.font = "bold 22px 'Poppins', sans-serif";

												ctx.fillText(percent + "%", bar.x, yPos); // Stick to the fixed position

												// Draw the revenue amount (Normal and Smaller)
												ctx.font = "normal 16px 'Poppins', sans-serif";
												ctx.fillText(formatMoney(data), bar.x, yPos + 25); // pixels above the fixed position
											});
										});
									},
								},
							},
						});
					} else {
						console.error("Error:", response.data.message);
					}
				},
				function (xhr, status, error) {
					console.error("AJAX Error:", error);
				}
			);
		}

		if ($("#dashboard-campaigns").length) {
			if ($.fn.dataTable.isDataTable('#dashboard-campaigns')) {
				$('#dashboard-campaigns').DataTable().destroy();
			}
			// Custom sorting for date format 'm/d/Y'
			$.fn.dataTable.ext.type.order["date-mdy-pre"] = function (dateString) {
				var dateParts = dateString.split("/");
				return new Date(dateParts[2], dateParts[0] - 1, dateParts[1]).getTime(); // Month is 0-indexed
			};

			var idwiz_dashboard_campaign_table = $("#dashboard-campaigns").DataTable({
				dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>rtp',
				columnDefs: [
					{ targets: "campaignDate", type: "date-mdy" },
					{ targets: "campaignId", visible: false },
				],
				order: [[0, "desc"]],
				autoWidth: false,
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
					}
				],
				language: {
					search: "",
					searchPlaceholder: "Quick search",
				},
				drawCallback: idwiz_dash_camp_table_callback,
			});

			function idwiz_dash_camp_table_callback() {
				var api = this.api();

				// Readjust the column widths on each draw
				api.columns.adjust();
			}
		}

		if ($("#cohortChart").length) {
			const canvas = $("#cohortChart");
			const purchaseMonth = canvas.data("purchase-month"); // Note the kebab-case here
			const purchaseMonthDay = canvas.data("purchase-month-day");
			const divisions = JSON.parse(canvas.attr("data-divisions")); // Parse the JSON string to get the array

			const purchaseWindow = canvas.data("purchaseWindowDays");

			// Add loader
			$(".wizChartWrapper").append('<span class="wizChartLoader"><i class="fa-solid fa-spinner fa-spin"></i>Fetching chart data...</span>');

			// Prepare data to send in the Ajax call
			const ajaxData = {
				purchaseMonth: purchaseMonth,
				purchaseMonthDay: purchaseMonthDay,
				divisions: divisions,
				purchaseWindow: purchaseWindow,
			};

			// Make the Ajax call to fetch the chart data
			idemailwiz_do_ajax("idwiz_generate_cohort_chart", idAjax_dashboard.nonce, ajaxData, populateCohortChart, (xhr, status, error) => {
				console.error("Error:", error);
			});

			function populateCohortChart(responseData) {
				$(".wizChartLoader").hide();
				console.log(responseData);
				const ctx = document.getElementById("cohortChart").getContext("2d");

				// The actual data is in responseData.data
				const data = responseData.data;

				// Group data by division
				const groupedByDivision = data.reduce((acc, { day_of_year, division, count }) => {
					if (!acc[division]) {
						acc[division] = {};
					}
					acc[division][day_of_year] = count;
					return acc;
				}, {});

				// Extract labels (day of year)
				const labels = [...new Set(data.map((item) => item.day_of_year))].sort((a, b) => a - b);

				// Create datasets
				const datasets = Object.keys(groupedByDivision).map((division) => ({
					label: division,
					data: labels.map((day) => groupedByDivision[division][day] || 0),
					stack: "Stack 0",
				}));

				const chart = new Chart(ctx, {
					type: "bar",
					data: {
						labels: labels,
						datasets: datasets,
					},
					options: {
						scales: {
							y: {
								stacked: true,
							},
						},
						maintainAspectRatio: false,
						responsive: true,
					},
				});
			}
		}

		if ($(".idemailwiz_table.report-table").length) {
			// Custom sorting for date format 'm/d/Y'
			$.fn.dataTable.ext.type.order["date-mdy-pre"] = function (dateString) {
				var dateParts = dateString.split("/");
				return new Date(dateParts[2], dateParts[0] - 1, dateParts[1]).getTime(); // Month is 0-indexed
			};

			var idwiz_dashboard_campaign_table = $(".idemailwiz_table.report-table").DataTable({
				dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>rtp',
				columnDefs: [{ targets: "campaignDate", type: "date-mdy" }],
				order: [[0, "desc"]],
				autoWidth: false,
				scrollX: true,
				scrollY: true,
				paging: true,
				pageLength: 40,
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
				],
				language: {
					search: "",
					searchPlaceholder: "Quick search",
				},
				drawCallback: idwiz_dash_camp_table_callback,
			});

			function idwiz_dash_camp_table_callback() {
				var api = this.api();

				// Readjust the column widths on each draw
				api.columns.adjust();
			}
		}

		// Handler for month/year dropdown changes

		$("#wizMonthDropdown, #wizYearDropdown").change(function () {
			var selectedMonth = $("#wizMonthDropdown").val();
			var selectedYear = $("#wizYearDropdown").val();

			// Construct the start date (first day of the month)
			var startDate = new Date(selectedYear, selectedMonth - 1, 1);
			var formattedStartDate = startDate.toISOString().split('T')[0]; // Format as 'yyyy-mm-dd'

			// Construct the end date (last day of the month)
			var endDate = new Date(selectedYear, selectedMonth, 0); // Using day 0 gets the last day of the previous month
			var formattedEndDate = endDate.toISOString().split('T')[0]; // Format as 'yyyy-mm-dd'

			// Construct the URL with the startDate and endDate
			var newUrl = "?startDate=" + formattedStartDate + "&endDate=" + formattedEndDate;
			window.location.href = newUrl;
		});


		// Handler for left navigation arrow
		$(".wizDateNav-left a").click(function (e) {
			e.preventDefault();

			// Get the previous month and year from the href attribute of the link
			var href = $(this).attr("href");
			window.location.href = href;
		});

		// Handler for right navigation arrow
		$(".wizDateNav-right a").click(function (e) {
			e.preventDefault();

			// Check if the arrow is not disabled
			if (!$(this).children("i").hasClass("disabled")) {
				// Get the next month and year from the href attribute of the link
				var href = $(this).attr("href");
				window.location.href = href;
			}
		});
	}

	// Utility function to get the current URL parameters for month/year
	function getQueryParam(param) {
		var urlParams = new URLSearchParams(window.location.search);
		return urlParams.get(param);
	}

	// Utility function to format as money
	function formatMoney(amount) {
		return (
			"$" +
			Number(amount)
				.toFixed(2)
				.replace(/\d(?=(\d{3})+\.)/g, "$&,")
		);
	}

	// Utility function to get the appropriate format function based on the data type
	function getFormatFunction(dataType) {
		if (dataType === "money") {
			return formatMoney;
		} else if (dataType === "percent") {
			return function (value) {
				return value.toFixed(2) + "%";
			};
		} else if (dataType === "number") {
			return function (value) {
				return value.toLocaleString();
			};
		}
		return function (value) {
			return value;
		};
	}
})(jQuery);
