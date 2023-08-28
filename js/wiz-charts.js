// Initialize the charts when the document is ready
jQuery(document).ready(function($) {
    
    // Chart switcher icons
   $(document).on('click', '.chart-type-switcher', function() {
        $(this).closest('.wizcampaign-section').find('.chart-type-switcher.active').removeClass('active');
        $(this).addClass('active');
        var switchTo = $(this).data('chart-type');
        var canvas = $(this).closest('.wizcampaign-section').find('canvas')[0];

        // Destroy existing chart if it exists
        if (canvas.chartInstance) {
            canvas.chartInstance.destroy();
        }

        // Update the data attribute
        $(canvas).attr('data-charttype', switchTo);

        // Recreate the chart
        fill_idwiz_chart_canvas(canvas);
    });

// On page load, fill canvases with their charts
$('canvas').each(function() {
    fill_idwiz_chart_canvas(this);
});

function fill_idwiz_chart_canvas(canvasElement) {
    const element = canvasElement;
    const chartType = $(element).attr('data-charttype');
    const xAxis = $(element).data('chart-x-axis');
    const yAxis = $(element).data('chart-y-axis');
    const dualYAxis = $(element).data('chart-dual-y-axis');
    const campaignIds = $(element).data('campaignids');

    const actionFunctionName = 'idwiz_fetch_flexible_chart_data';
    
    const additionalData = {
        chartType: chartType,
        xAxis: xAxis,
        yAxis: yAxis,
        dualYAxis: dualYAxis,
        campaignIds: campaignIds
    };

    idemailwiz_do_ajax(
        actionFunctionName,
        idAjax_wiz_charts.nonce,
        additionalData,
        function(response) {
            if (response.success) {
                let options = {
                    layout: {
                        padding: {
                            bottom: 50
                        }
                    }
                }; // Default empty options object for common settings

                if (chartType !== 'pie') {
                    const yAxisDataType = response.data.yAxisDataType;
                    const dualYAxisDataType = response.data.dualYAxisDataType;

                    // Define your default formatting functions
                    const numberFormatFn = value => value.toLocaleString();
                    const percentFormatFn = value => `${value.toFixed(2)}%`;
                    const moneyFormatFn = value => value.toLocaleString('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0, maximumFractionDigits: 0 });

                    // Initialize options specific to 'bar' and 'line' charts
                    options.scales = {
                        x: {
                            ticks: {
                                maxRotation: 90
                            }
                        },
                        'y-axis-1': {
                            position: 'left',
                            ticks: {
                                callback: yAxisDataType === 'number' ? numberFormatFn :
                                        yAxisDataType === 'percent' ? percentFormatFn : moneyFormatFn
                            }
                        }
                    };
                    
                    // If dualYAxis is set, add dual Y-axis settings
                    if (dualYAxis) {
                        options.scales['y-axis-2'] = {
                            id: 'y-axis-2',
                            type: 'linear',
                            position: 'right',
                            ticks: {
                                callback: dualYAxisDataType === 'number' ? numberFormatFn :
                                        dualYAxisDataType === 'percent' ? percentFormatFn : moneyFormatFn
                            }
                        };
                    }

                    // Set manual height to prevent too much squishing
                    const minChartHeight = 150;  // The minimum height you want for the chart area
                    const labelPadding = 50;    // Additional height to accommodate the labels
                    // Set the canvas height
                    element.height = minChartHeight + labelPadding;
                }

                create_idwiz_chart(element, chartType, response.data.labels, response.data.datasets, options);
            } else {
                console.error("AJAX request successful but response indicated failure:", response);
            }
        },
        function(error) {
            console.error("An error occurred during the AJAX request:", error);
        }
    );
}



function create_idwiz_chart(element, chartType, labels, datasets, options) {
    const ctx = element.getContext('2d');
    const chartInstance = new Chart(ctx, {
        type: chartType,
        data: {
            labels: labels,
            datasets: datasets
        },
        options: options
    });

    // Store the Chart.js instance on the canvas element
    // This way, we can easily access and destroy it later
    element.chartInstance = chartInstance;
}

});