jQuery(document).ready(function ($) {
	// Chart switcher icons
	$(document).on("click", ".chart-type-switcher", function () {
		$(this).closest(".wizcampaign-section").find(".chart-type-switcher.active").removeClass("active");
		$(this).addClass("active");
		var switchTo = $(this).data("chart-type");
		var canvas = $(this).closest(".wizcampaign-section").find("canvas")[0];

		if (canvas.chartInstance) {
			canvas.chartInstance.destroy();
		}

		$(canvas).attr("data-charttype", switchTo);
		idwiz_fill_chart_canvas(canvas);
	});

	$(document).on("click", ".chart-timescale-switcher", function () {
		$(this).closest(".wizcampaign-section").find(".chart-timescale-switcher.active").removeClass("active");
		$(this).addClass("active");
		var switchTo = $(this).data("timescale");
		var canvases = $(this).closest(".wizcampaign-section").find("canvas[data-timescale]");

		canvases.each(function () {
			var canvas = this;
			if (canvas.chartInstance) {
				canvas.chartInstance.destroy();
			}

			$(canvas).attr("data-timescale", switchTo);
			idwiz_fill_chart_canvas(canvas);
		});
	});

	// On page load, fill canvases with their charts
	$("canvas.wiz-canvas").each(function () {
		if ($(this).attr("data-lazy-load") == "true") {
			$(this).before('<button class="wizChart-loadChart wiz-button green">Load chart</button>');
		} else {
			do_canvas_chart(this);
		}
	});

	$(document).on("click", ".wizChart-loadChart", function () {
		$(this).closest(".wizChartWrapper").append('<span class="wizChartLoader"><i class="fa-solid fa-spinner fa-spin"></i>Fetching chart data...</span>');
		do_canvas_chart($(this).siblings("canvas")[0]);
		$(this).remove();
	});

	function do_canvas_chart(canvas) {
		if ($(canvas).attr("data-chartid") === "customerTypesChart") {
			populate_customer_types_chart(canvas);
		} else {
			idwiz_fill_chart_canvas(canvas);
		}
	}

	

	// $(document).on("change", "#limit30Days", function () {
	// 	var startDate = $('#purchasesByDate').data('startdate');
	// 	var newEndDate = new Date(startDate);
	// 	if ($(this).is(':checked')) {
	// 		newEndDate.setDate(newEndDate.getDate() + 30);
	// 	} else {
	// 		newEndDate = new Date(); // Default back to today's date
	// 	}
	// 	var formattedEndDate = newEndDate.getFullYear() + '-' +
	// 						   ('0' + (newEndDate.getMonth() + 1)).slice(-2) + '-' +
	// 						   ('0' + newEndDate.getDate()).slice(-2);

	// 	$('#purchasesByDate').data('enddate', formattedEndDate).attr('data-enddate', formattedEndDate);

	// 	// Assuming the canvas is within the same section as the #limit30Days checkbox
	// 	var canvas = $(this).closest(".wizcampaign-section").find("canvas")[0];
	// 	if (canvas && typeof idwiz_fill_chart_canvas === "function") {
	// 		if (canvas.chartInstance) {
	// 			canvas.chartInstance.destroy();
	// 		}
	// 		idwiz_fill_chart_canvas(canvas);
	// 	}
	// });


	function idwiz_fill_chart_canvas(canvas) {
		const chartType = $(canvas).attr("data-charttype");
		const chartId = $(canvas).attr("data-chartid");

		const additionalData = {
			chartType: chartType,
			chartId: chartId,
		};

		// Check for other potential attributes and add them if they're present
		if ($(canvas).attr("data-startdate")) {
			additionalData.startDate = $(canvas).attr("data-startdate");
		}
		if ($(canvas).attr("data-enddate")) {
			additionalData.endDate = $(canvas).attr("data-enddate");
		}
		if ($(canvas).attr("data-minsends")) {
			additionalData.minSends = $(canvas).attr("data-minsends");
		}
		if ($(canvas).attr("data-maxsends")) {
			additionalData.maxSends = $(canvas).attr("data-maxsends");
		}
		if ($(canvas).attr("data-minmetric")) {
			additionalData.minMetric = $(canvas).attr("data-minmetric");
		}
		if ($(canvas).attr("data-maxmetric")) {
			additionalData.maxMetric = $(canvas).attr("data-maxmetric");
		}
		if ($(canvas).attr("data-campaignids")) {
			additionalData.campaignIds = $(canvas).attr("data-campaignids");
		}
		if ($(canvas).attr("data-year-over-year")) {
			additionalData.yearOverYear = $(canvas).attr("data-year-over-year");
		}
		if ($(canvas).attr("data-timescale")) {
			additionalData.timeScale = $(canvas).attr("data-timescale");
		}

		if ($(canvas).attr("data-campaigntypes")) {
			additionalData.campaignTypes = $(canvas).attr("data-campaigntypes");
		}

		if ($(canvas).attr("data-campaigntype")) {
			additionalData.campaignType = $(canvas).attr("data-campaigntype");
		}

		idemailwiz_do_ajax(
			"idwiz_catch_chart_request",
			idAjax_wiz_charts.nonce,
			additionalData,
			function (response) {
				
				if (response.success) {
					$('.wizsection-error-message').remove();
					let options = response.data.options;

					// Determine the format function for each yAxisID
					let formatFunctions = {};

					// Loop through all y-axes in the scale configuration
					for (let yAxisID in response.data.options.scales) {
						if (response.data.options.scales[yAxisID].dataType) {
							// Use the dataType from the y-axis configuration to get the format function
							formatFunctions[yAxisID] = idwiz_getFormatFunction(response.data.options.scales[yAxisID].dataType);
						}
					}

					// Loop through scales and apply formatting
					for (let scale in options.scales) {
						if (options.scales.hasOwnProperty(scale) && formatFunctions[scale]) {
							options.scales[scale].ticks = options.scales[scale].ticks || {};
							options.scales[scale].ticks.callback = formatFunctions[scale];
						}
					}

					// Tooltips formatting
					if (options && options.plugins && options.plugins.tooltip) {
						if (!options.plugins.tooltip.callbacks) {
							options.plugins.tooltip.callbacks = {};
						}
						if (response.data.options.hideTooltipTitle) {
							options.plugins.tooltip.callbacks.title = function () {
								return ""; // hides the default label (x-axis label) from the tooltip
							};
						} else {
							options.plugins.tooltip.callbacks.title = function (tooltipItems) {
								return tooltipItems[0].label;
							};
						}

						options.plugins.tooltip.callbacks.label = function (context) {
							let labelsArray = [];

							// Check if the current chart is a pie or doughnut chart
							if (context.chart.config.type === "pie" || context.chart.config.type === "doughnut") {
								let total = context.dataset.data.reduce((a, b) => a + b, 0);
								let percentage = ((context.raw / total) * 100).toFixed(2);

								// Access the revenue from metaData
								let revenue = context.dataset.metaData[context.dataIndex];
								let formattedRevenue = idwiz_getFormatFunction("money")(revenue);

								labelsArray.push(`${context.label}: ${context.raw} (${percentage}%)`);
								labelsArray.push(`Revenue: ${formattedRevenue}`);
							} else {
								// Get the dataType for this dataset from the chart options
								let dataType = context.chart.options.scales[context.dataset.yAxisID].dataType;

								// Get the format function based on the dataType
								formatFunc = idwiz_getFormatFunction(dataType);

								// Apply the format function
								let formattedValue = formatFunc(context.parsed.y);
								labelsArray.push(`${context.dataset.label}: ${formattedValue}`);
							}

							// Append additional labels if present
							if (context.chart.data.tooltipLabels && context.chart.data.tooltipLabels[context.dataIndex]) {
								let tooltipLabels = context.chart.data.tooltipLabels[context.dataIndex];
								labelsArray = labelsArray.concat(tooltipLabels);
							}

							return labelsArray;
						};
					}

					idwiz_create_chart(canvas, response.data.type, response.data.data.labels, response.data.data.datasets, options);
					$(canvas).siblings(".wizChartLoader").remove();
				} else {
					setTimeout(function () {
						$(canvas).closest('.wizChartWrapper').append('<div class="wizsection-error-message">' + response.data + '</div>');
						$(canvas).siblings(".wizChartLoader").remove();
						$(canvas).hide();
					}, 1000);

					
				}
			},
			function (error) {
				console.error("An error occurred during the AJAX request:", error);
				if (error.responseJSON && error.responseJSON.message) {
					//console.error("Server says:", error.responseJSON.message);
					$(canvas).before('<div class="wizsection-error-message">' + error.responseJSON.message + "</div>");
				} else {
					$(canvas).before('<div class="wizsection-error-message">No data available</div>');
				}
				$(canvas).siblings(".wizChartLoader").remove();
			}
		);
	}

	function idwiz_create_chart(element, chartType, labels, datasets, options) {
		const ctx = element.getContext("2d");

		const idwizChart = new Chart(ctx, {
			type: chartType,
			data: {
				labels: labels,
				datasets: datasets,
			},
			options: options,
		});

		element.chartInstance = idwizChart;
	}

	function idwiz_getFormatFunction(dataType) {
		return (valueOrTooltipItem, chartData) => {
			let value = valueOrTooltipItem.value ? valueOrTooltipItem.value : valueOrTooltipItem; // Handle both direct values and tooltip items

			if (dataType === "number") {
				return value.toLocaleString();
			} else if (dataType === "percent") {
				return `${value.toFixed(2)}%`;
			} else if (dataType === "money") {
				return value.toLocaleString("en-US", { style: "currency", currency: "USD", minimumFractionDigits: 0, maximumFractionDigits: 0 });
			}
		};
	}

	function populate_customer_types_chart(canvas) {
		const chartId = $(canvas).attr("data-chartid");
		const campaignIds = $(canvas).attr("data-campaignids");
		console.log(campaignIds);

		const additionalData = {
			chartId: chartId,
			campaignIds: campaignIds,
			startDate: $(canvas).attr("data-startdate"),
			endDate: $(canvas).attr("data-enddate"),
		};

		idemailwiz_do_ajax(
			"idwiz_fetch_customer_types_chart_data",
			idAjax_wiz_charts.nonce,
			additionalData,
			function (response) {
				if (response.success) {
					// Update the tooltip callback function from the placeholder
					response.data.options.plugins.tooltip.callbacks.label = function (context) {
						let dataset = context.chart.data.datasets[context.datasetIndex];
						let totalCount = dataset.data.reduce((acc, val) => acc + val, 0);
						let percentage = ((context.raw / totalCount) * 100).toFixed(2);

						let formatFunction = idwiz_getFormatFunction("number"); // Assuming number type for count
						return formatFunction(context.raw) + ` (${percentage}%)`;
					};

					idwiz_create_chart(canvas, response.data.type, response.data.data.labels, response.data.data.datasets, response.data.options);
				} else {
					console.error("AJAX request successful but response indicated failure:", response);
				}
				$(canvas).siblings(".wizChartLoader").remove();
			},
			function (error) {
				console.error("An error occurred during the AJAX request:", error);
				if (error.responseJSON && error.responseJSON.message) {
					console.error("Server says:", error.responseJSON.message);
					$(canvas).before('<div class="wizsection-error-message">' + error.responseJSON.message + "</div>");
				} else {
					$(canvas).before('<div class="wizsection-error-message">No data available</div>');
				}
				$(canvas).siblings(".wizChartLoader").remove();
			}
		);
	}
});
