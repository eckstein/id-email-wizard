jQuery(document).ready(function ($) {
	

	// On page load, fill canvases with their charts
	$("canvas.wiz-canvas").each(function () {
		idwiz_fill_chart_canvas(this);
	});

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

	fill_engagement_charts();
	function fill_engagement_charts() {
		$("canvas.engagementByHourChart").each(function() {
			let $canvas = $(this);
			let canvasId = $canvas.attr('id');
			let openThreshold = $canvas.attr("data-openthreshold") || false;
			let clickThreshold = $canvas.attr("data-clickthreshold") || false;
			let additionalData = {
				campaignIds: $canvas.attr("data-campaignids"),
				startDate: $canvas.attr("data-startdate"),
				endDate: $canvas.attr("data-enddate"),
				openThreshold: openThreshold,
				clickThreshold: clickThreshold,
				maxHours: $canvas.attr("data-maxhours"),
			};

			idemailwiz_do_ajax( 
				"idwiz_get_engagement_by_hour_chart_data",
				idAjax_wiz_charts.nonce,
				{
					campaignIds: additionalData.campaignIds,
					startDate: additionalData.startDate,
					endDate: additionalData.endDate,
					maxHours: additionalData.maxHours,
					openThreshold: additionalData.openThreshold,
					clickThreshold: additionalData.clickThreshold,
					chartType: canvasId === 'opensByHourChart' ? 'opens' : 'clicks'
				},
				function (response) {
					if (response.success) {
						if (canvasId === 'opensByHourChart') {
							createEngagementChart('opensByHourChart', response.data.opensByHour, 'Campaigns by Hours of Engagement (Opens)');
						} else if (canvasId === 'clicksByHourChart') {
							createEngagementChart('clicksByHourChart', response.data.clicksByHour, 'Campaigns by Hours of Engagement (Clicks)');
						}
					} else {
						console.error('Error fetching chart data:', response.data.message);
					}
				}
			);
		});
	}
	
		
});

function createEngagementChart(canvasId, chartData, title) {
	const ctx = document.getElementById(canvasId).getContext('2d');
    
	new Chart(ctx, {
		type: 'bar',
		data: chartData,
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				title: {
					display: true,
					text: title,
					font: {
						size: 16
					}
				},
				legend: {
					display: false
				},
				tooltip: {
					callbacks: {
						label: function(context) {
							return `${context.parsed.y} campaigns`;
						}
					}
				},
				datalabels: {
					anchor: 'end',
					align: 'top',
					formatter: function(value, context) {
						return value > 0 ? value : ''; // Only show label if value is greater than 0
					},
					font: {
						weight: 'bold'
					}
				}
			},
			scales: {
				x: {
					title: {
						display: true,
						text: 'Hours',
						font: {
							size: 14
						}
					}
				},
				y: {
					beginAtZero: true,
					title: {
						display: true,
						text: 'Number of Campaigns',
						font: {
							size: 14
						}
					},
					ticks: {
						precision: 0
					}
				}
			}
		}
	});
}

function idwiz_fill_chart_canvas(canvas) {
	const chartType = jQuery(canvas).attr("data-charttype");
	const chartId = jQuery(canvas).attr("data-chartid");

	const additionalData = {
		chartType: chartType,
		chartId: chartId,
	};

	// Check for other potential attributes and add them if they're present
	if (jQuery(canvas).attr("data-startdate")) {
		additionalData.startDate = jQuery(canvas).attr("data-startdate");
	}
	if (jQuery(canvas).attr("data-enddate")) {
		additionalData.endDate = jQuery(canvas).attr("data-enddate");
	}
	if (jQuery(canvas).attr("data-campaignids")) {
		additionalData.campaignIds = jQuery(canvas).attr("data-campaignids");
	}
	if (jQuery(canvas).attr("data-minsends")) {
		additionalData.minSends = jQuery(canvas).attr("data-minsends");
	}
	if (jQuery(canvas).attr("data-maxsends")) {
		additionalData.maxSends = jQuery(canvas).attr("data-maxsends");
	}
	if (jQuery(canvas).attr("data-minmetric")) {
		additionalData.minMetric = jQuery(canvas).attr("data-minmetric");
	}
	if (jQuery(canvas).attr("data-maxmetric")) {
		additionalData.maxMetric = jQuery(canvas).attr("data-maxmetric");
	}
	
	if (jQuery(canvas).attr("data-year-over-year")) {
		additionalData.yearOverYear = jQuery(canvas).attr("data-year-over-year");
	}
	if (jQuery(canvas).attr("data-timescale")) {
		additionalData.timeScale = jQuery(canvas).attr("data-timescale");
	}

	if (jQuery(canvas).attr("data-campaigntypes")) {
		additionalData.campaignTypes = jQuery(canvas).attr("data-campaigntypes");
	}

	if (jQuery(canvas).attr("data-campaigntype")) {
		additionalData.campaignType = jQuery(canvas).attr("data-campaigntype");
	}

	if (jQuery(canvas).attr("data-promocode")) {
		additionalData.promoCode = jQuery(canvas).attr("data-promocode");
	}

	if (jQuery(canvas).attr("data-sendsByWeekData")) {
		additionalData.sendsByWeekData = jQuery(canvas).attr("data-sendsByWeekData");
	}
	if (jQuery(canvas).attr("data-cohorts")) {
		additionalData.cohorts = jQuery(canvas).attr("data-cohorts");
	}
	if (jQuery(canvas).attr("data-cohorts-exclude")) {
		additionalData.cohortsExclude = jQuery(canvas).attr("data-cohorts-exclude");
	}
	if (jQuery(canvas).attr("data-max-y")) {
		additionalData.maxY = jQuery(canvas).attr("data-max-y");
	}

	idemailwiz_do_ajax(
		"idwiz_catch_chart_request",
		idAjax_wiz_charts.nonce,
		additionalData,
		function (response) {
			if (response.success) {
				jQuery('.wizsection-error-message').remove();
				let options = response.data.options;
				let datasets = response.data.data.datasets;

				// Define the custom formatting function for the customer types chart
				function formatLabelForCustomerTypes(context) {
					let total = context.dataset.data.reduce((a, b) => a + b, 0);
					let value = context.raw;
					let percentage = ((value / total) * 100).toFixed(2);
					return `${context.label}: ${value} (${percentage}%)`;
				}

				// Check if the response contains custom tooltip data for the specific charts
				if (response.data.customTooltip) {
					// Tooltips formatting for specific chart
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
								let datasetIndex = tooltipItems[0].datasetIndex;
								let date = tooltipItems[0].label;
								let tooltipData = datasets[datasetIndex].tooltipData && datasets[datasetIndex].tooltipData[date];

								if (tooltipData) {
									let displayDate = datasetIndex === 1 ? tooltipData.originalDate : date; // Use originalDate for previous year
									return `${displayDate} | ${tooltipData.name}`;
								}
								return date;
							};
						}

						options.plugins.tooltip.callbacks.label = function (context) {
							let index = context.dataIndex;
							let datasetIndex = context.datasetIndex;
							let date = context.label;
							let tooltipData = datasets[datasetIndex].tooltipData[date];

							let label = "";
							if (!response.data.options.hideTooltipLabel) {
								label = context.dataset.label || "";
								if (label) {
									label += ": ";
								}
							}

							if (context.parsed.y !== null && tooltipData) {
								label += new Intl.NumberFormat("en-US", {
									style: "percent",
									minimumFractionDigits: 2
								}).format(tooltipData.value / 100);
							} else {
								label += "No data";
							}

							return label;
						};
					}
				} else {
					// Other charts
					if (options && options.plugins && options.plugins.tooltip) {
						options.plugins.tooltip.mode = 'nearest';
						options.plugins.tooltip.intersect = true;
						options.plugins.tooltip.callbacks = {
							title: function(tooltipItems) {
								let item = tooltipItems[0];
								let datasetIndex = item.datasetIndex;
								let date = item.label;
								let tooltipData = datasets[datasetIndex].tooltipData && datasets[datasetIndex].tooltipData[date];

								if (tooltipData) {
									let displayDate = datasetIndex === 1 ? tooltipData.originalDate : date; // Use originalDate for previous year
									return `${displayDate} | ${tooltipData.name}`;
								}
								return date;
							},
							label: function(context) {
								let datasetIndex = context.datasetIndex;
								let date = context.label;
								let tooltipData = datasets[datasetIndex].tooltipData && datasets[datasetIndex].tooltipData[date];

								let label = context.dataset.label || '';
								if (label) {
									label += ': ';
								}

								if (context.parsed.y !== null && tooltipData) {
									if (!tooltipData.dataType || tooltipData.dataType === 'percent') {
									label += new Intl.NumberFormat("en-US", {
										style: "percent",
										minimumFractionDigits: 2
									}).format(tooltipData.value / 100);
									} else if (tooltipData.dataType === 'money') {
										label += new Intl.NumberFormat("en-US", {
											style: "currency",
											currency: "USD",
										}).format(tooltipData.value);
									} else {
										label += tooltipData.value;
										
									}
								} else {
									label += "No data";
								}

								return label;
							}
						};
					}
				}

				// Determine the format function for each yAxisID
				let formatFunctions = {};
				for (let yAxisID in options.scales) {
					if (options.scales[yAxisID].dataType) {
						formatFunctions[yAxisID] = idwiz_getFormatFunction(options.scales[yAxisID].dataType);
					}
				}

				// Loop through scales and apply formatting
				for (let scale in options.scales) {
					if (options.scales.hasOwnProperty(scale) && formatFunctions[scale]) {
						options.scales[scale].ticks = options.scales[scale].ticks || {};
						options.scales[scale].ticks.callback = formatFunctions[scale];
					}
				}

				idwiz_create_chart(canvas, response.data.type, response.data.data.labels, datasets, options);
				jQuery(canvas).siblings(".wizChartLoader").remove();
			} else {
				setTimeout(function () {
					jQuery(canvas).closest('.wizChartWrapper').append('<div class="wizsection-error-message">' + response.data + '</div>');
					jQuery(canvas).siblings(".wizChartLoader").remove();
					jQuery(canvas).hide();
				}, 1000);
			}
		},
		function (error) {
			console.error("An error occurred during the AJAX request:", error);
			if (error.responseJSON && error.responseJSON.message) {
				jQuery(canvas).before('<div class="wizsection-error-message">' + error.responseJSON.message + "</div>");
			} else {
				jQuery(canvas).before('<div class="wizsection-error-message">No data available</div>');
			}
			jQuery(canvas).siblings(".wizChartLoader").remove();
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




