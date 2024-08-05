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

		

		if ($("#dashboard-campaigns").length) {
			
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
					//{ targets: "dtNumVal", type: "num" },
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

	// Utility function to format as money
	function formatMoney(amount) {
		return (
			"$" +
			Number(amount)
				.toFixed(2)
				.replace(/\d(?=(\d{3})+\.)/g, "$&,")
		);
	}

	
})(jQuery);
