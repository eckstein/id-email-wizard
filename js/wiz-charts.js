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
			"wizOpenRate": "Open Rate",
			"wizCto": "CTO",
			"wizCtr": "CTR",
			// Add more mappings as needed
		};

		const chartType = $(canvas).attr("data-charttype");
		const chartId = $(canvas).attr("data-chartid");
		const isStacked = $(canvas).attr("data-stacked") === "true";
		const xAxisDate = $(canvas).attr("data-xAxisDate") === "true"; 

		const additionalData = {
			chartType: chartType,
			chartId: chartId,
			xAxisDate: xAxisDate,
		};

		// Check for other potential attributes and add them if they're present
		if ($(canvas).attr("data-startdate")) {
			additionalData.startDate = $(canvas).attr("data-startdate");
		}
		if ($(canvas).attr("data-enddate")) {
			additionalData.endDate = $(canvas).attr("data-enddate");
		}
		if ($(canvas).attr("data-campaignids")) {
			additionalData.campaignIds = $(canvas).attr("data-campaignids");
		}

		idemailwiz_do_ajax(
			"idwiz_fetch_flexible_chart_data",
			idAjax_wiz_charts.nonce,
			additionalData,
			function (response) {
				if (response.success) {
					console.log("Chart data:", response.data);
					// Modify the label of the dataset
					let datasets = response.data.data.datasets.map(dataset => {
						let userFriendlyLabel = metricLabelMappings[dataset.label] || dataset.label;
						return {
							...dataset,
							label: userFriendlyLabel
						};
					});
					response.data.data.datasets = datasets;
					
					let options = {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							tooltip: {
								callbacks: {
									title: function() {
										return '';  // Removes the title from the tooltip.
									},
									label: function (context) {
										let dataset = context.chart.data.datasets[context.datasetIndex];
										let yAxisType = dataset.yAxisID === "y-axis-1" ? response.data.options.yAxisDataType : response.data.options.dualYAxisDataType;
										let formatFunction = idwiz_getFormatFunction(yAxisType);
										let value = Number(context.raw);
    
										// Fetch the original label using the context's index
										let originalLabel = response.data.data.labels[context.dataIndex];
    
										return originalLabel + ": " + formatFunction(value);
									},


								},
							},
						},
					};

					const xAxisType = response.data.options.xAxisType;

					let xAxesOptions = {};
					

					if (chartType === "pie") {
						options.plugins.legend = {
							position: "right",
						};
					}

					const { yAxisDataType, dualYAxisDataType, dualYAxis } = response.data.options;

					// Determine y-axis min and max based on data type
					let yAxisMin, yAxisMax, yAxisGrace;
					if (yAxisDataType == "percent") {
						yAxisMin = 0;
						//yAxisGrace = '30%';
						//yAxisMax = 100;
					}

					if (chartType !== "pie") {
						options.scales = {
							x: xAxesOptions,
							"y-axis-1": {
								position: "left",
								min: yAxisMin, // Apply the determined min value
								max: yAxisMax,  // Apply the determined max value
								grace: yAxisGrace,
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

						if (isStacked && chartType == "bar") {
							options.scales = {
								x: {
									stacked: true,
								},
								"y-axis-1": {
									position: "left",
									stacked: true,
									ticks: {
										callback: idwiz_getFormatFunction(yAxisDataType),
									},
								},
							};
							options.scales["y-axis-2"] = {
								id: "y-axis-2",
								position: "right",
								ticks: {
									callback: idwiz_getFormatFunction(dualYAxisDataType),
								},
							};
						}	
					}

					if (xAxisType == "date") {
						xAxesOptions = {
							type: "time",
							time: {
								unit: "day",
								displayFormats: {
									day: "MM/DD/YYYY",
								},
							},
						};

						
					} else {
						// Any other default options for non-time x-axes
						xAxesOptions = {
							type: xAxisType,
						};
					}

					// Extract just the dates from the labels
					let dateLabels = response.data.data.labels.map(label => label.split(' - ')[0]);

					// Use dateLabels instead of response.data.data.labels
					idwiz_create_chart(canvas, chartType, dateLabels, response.data.data.datasets, options);


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



	function populate_customer_types_chart(canvas) {
		const chartId = $(canvas).attr("data-chartid");
		const campaignIds = $(canvas).attr("data-campaignids");
		console.log(campaignIds);

		const additionalData = {
			chartId: chartId,
			campaignIds: campaignIds,
		};

		idemailwiz_do_ajax(
			"idwiz_fetch_customer_types_chart_data",
			idAjax_wiz_charts.nonce,
			additionalData,
			function (response) {
				if (response.success) {
					let options = {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							tooltip: {
								callbacks: {
									label: function (context) {
										let dataset = context.chart.data.datasets[context.datasetIndex];
										let totalCount = dataset.data.reduce((acc, val) => acc + val, 0);
										let percentage = ((context.raw / totalCount) * 100).toFixed(2);

										let formatFunction = idwiz_getFormatFunction("number"); // Assuming number type for count
										return formatFunction(context.raw) + ` (${percentage}%)`;
									},
								},
							},
						},
					};

					idwiz_create_chart(canvas, "pie", response.data.data.labels, response.data.data.datasets, options);
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
