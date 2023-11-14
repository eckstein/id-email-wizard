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



	// On page load, fill canvases with their charts
	$("canvas.wiz-canvas").each(function () {
		if ($(this).attr("data-chartid") === "customerTypesChart") {
			populate_customer_types_chart(this);
		} else {
			idwiz_fill_chart_canvas(this);
		}
	});

	function idwiz_fill_chart_canvas(canvas) {
		const metricLabelMappings = {
			wizOpenRate: "Open Rate",
			wizCto: "CTO",
			wizCtr: "CTR",
			// Add more mappings as needed
		};

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

		if ($(canvas).attr("data-campaigntypes")) {
			additionalData.campaignTypes = $(canvas).attr("data-campaigntypes");
		}

		idemailwiz_do_ajax(
			"idwiz_catch_chart_request",
			idAjax_wiz_charts.nonce,
			additionalData,
			function (response) {
				if (response.data.error) {
					$(canvas).before('<div class="wizsection-error-message">No data available</div>');
				}
				if (response.success) {
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
							options.plugins.tooltip.callbacks.title = function() {
								return ''; // hides the default label (x-axis label) from the tooltip
							};
						} else {
							options.plugins.tooltip.callbacks.title = function(tooltipItems) {
								return tooltipItems[0].label;
							};
						}

						options.plugins.tooltip.callbacks.label = function (context) {
							let labelsArray = [];
    
							if (response.data.type === "pie" || response.data.type === "doughnut") {
								let total = context.dataset.data.reduce((a, b) => a + b, 0);
								let percentage = ((context.raw / total) * 100).toFixed(2);

								// Access the revenue using context.dataIndex and the correct path
								let revenue = response.data.data.revenues && response.data.data.revenues[context.dataIndex] ? response.data.data.revenues[context.dataIndex] : "N/A";
								let formattedRevenue = formatFunctions['y-axis-rev'](revenue);

								labelsArray.push(`${context.raw} (${percentage}%)`);
								labelsArray.push(`Revenue: ${formattedRevenue}`);
							} else {
								let formatFunc = formatFunctions[context.dataset.yAxisID];
								if (formatFunc) {
									labelsArray.push(formatFunc(context.parsed.y));
								} else {
									labelsArray.push(context.parsed.y);
								}
							}

							// Check if tooltipLabels is present and is not empty
							if (response.data.data.tooltipLabels && response.data.data.tooltipLabels[context.dataIndex]) {
								let tooltipLabels = response.data.data.tooltipLabels[context.dataIndex];
								labelsArray = labelsArray.concat(tooltipLabels);
							}

							return labelsArray;
						};



					}

					idwiz_create_chart(canvas, response.data.type, response.data.data.labels, response.data.data.datasets, options);
				} else {
					console.error("AJAX request successful but response indicated failure:", response);
					
				}

			},
			function (error) {
				console.error("An error occurred during the AJAX request:", error);
				if (error.responseJSON && error.responseJSON.message) {
					console.error("Server says:", error.responseJSON.message);
					$(canvas).before('<div class="wizsection-error-message">' + error.responseJSON.message + "</div>");
				} else {
					$(canvas).before('<div class="wizsection-error-message">No data available</div>');
				}
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
			},
			function (error) {
				console.error("An error occurred during the AJAX request:", error);
				if (error.responseJSON && error.responseJSON.message) {
					console.error("Server says:", error.responseJSON.message);
					$(canvas).before('<div class="wizsection-error-message">' + error.responseJSON.message + "</div>");
				} else {
					$(canvas).before('<div class="wizsection-error-message">No data available</div>');
				}
			}
		);
	}
});
