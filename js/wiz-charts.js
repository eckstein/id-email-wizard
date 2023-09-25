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
	$("canvas").each(function () {
		idwiz_fill_chart_canvas(this);
	});

	// Add styling and visual options here per chart type
	function idwiz_getBaseLayoutOptions(chartType) {
		let layoutOptions = {
			padding: {
				bottom: 50,
			},
		};

		if (chartType === "pie") {
			layoutOptions.padding = { top: 10, bottom: 20 };
		} else if (chartType === "line") {
			layoutOptions.padding = { left: 20, right: 20, bottom: 40 };
		} else if (chartType === "bar") {
			layoutOptions.padding = { left: 15, right: 15, bottom: 30 };
		}

		return layoutOptions;
	}

	function idwiz_fill_chart_canvas(canvas) {
		const chartType = $(canvas).attr("data-charttype");
		const chartId = $(canvas).attr("data-chartid");
		const campaignIds = $(canvas).attr("data-campaignids");

		const additionalData = {
			chartType: chartType,
			chartId: chartId,
			campaignIds: campaignIds,
		};

		idemailwiz_do_ajax(
			"idwiz_fetch_flexible_chart_data",
			idAjax_wiz_charts.nonce,
			additionalData,
			function (response) {
				if (response.success) {
					const baseLayout = idwiz_getBaseLayoutOptions(chartType);
					let options = {
						layout: baseLayout,
						responsive: true,
						plugins: {
							tooltip: {
								callbacks: {
									label: function (context) {
										let dataset = context.chart.data.datasets[context.datasetIndex];
										let yAxisType = dataset.yAxisID === "y-axis-1" ? response.data.options.yAxisDataType : response.data.options.dualYAxisDataType;
										let formatFunction = idwiz_getFormatFunction(yAxisType);
										return dataset.label + ": " + formatFunction(context.raw);
									},
								},
							},
						},
					};

					if (chartType === "pie") {
						options.plugins.legend = {
							position: "right",
						};
					}

					const { yAxisDataType, dualYAxisDataType, dualYAxis } = response.data.options;

					if (chartType !== "pie") {
						options.scales = {
							x: {},
							"y-axis-1": {
								position: "left",
								ticks: {
									callback: idwiz_getFormatFunction(yAxisDataType),
								},
							},
						};

						if (dualYAxis) {
							options.scales["y-axis-2"] = {
								id: "y-axis-2",
								position: "right",
								ticks: {
									callback: idwiz_getFormatFunction(dualYAxisDataType),
								},
							};
						}
					}

					idwiz_create_chart(canvas, chartType, response.data.data.labels, response.data.data.datasets, options);
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

	function idwiz_getFormatFunction(dataType) {
		if (dataType === "number") {
			return (value) => value.toLocaleString();
		} else if (dataType === "percent") {
			return (value) => `${value.toFixed(2)}%`;
		} else if (dataType === "money") {
			return (value) => value.toLocaleString("en-US", { style: "currency", currency: "USD", minimumFractionDigits: 0, maximumFractionDigits: 0 });
		}
	}

	function idwiz_create_chart(element, chartType, labels, datasets, baseOptions) {
		const ctx = element.getContext("2d");

		// Retrieve layout options
		const layoutOptions = idwiz_getBaseLayoutOptions(chartType);

		// Set height from base layout options
		element.height = layoutOptions.height;

		const idwizChart = new Chart(ctx, {
			type: chartType,
			data: {
				labels: labels,
				datasets: datasets,
			},
			options: baseOptions,
		});

		element.chartInstance = idwizChart;
	}
});
