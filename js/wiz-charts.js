// Initialize the charts when the document is ready
jQuery(document).ready(function($) {

    $('canvas').each(function() {
    const element = this;
    const chartType = $(element).data('charttype');
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
                const yAxisDataType = response.data.yAxisDataType;
                const dualYAxisDataType = response.data.dualYAxisDataType;

                let options = {}; // Default empty options object

                // Define your default formatting functions
                const numberFormatFn = value => value.toLocaleString();
                const percentFormatFn = value => `${value.toFixed(2)}%`;
                const moneyFormatFn = value => value.toLocaleString('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0, maximumFractionDigits: 0 });

                let yTickCallback; // Initialize callback

                // Assign the appropriate formatting function
                switch(yAxisDataType) {
                    case 'number': yTickCallback = numberFormatFn; break;
                    case 'percent': yTickCallback = percentFormatFn; break;
                    case 'money': yTickCallback = moneyFormatFn; break;
                }

                // Initialize options for charts with primary Y-axis settings
                options = {
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 90,
                               
                                //autoSkip: false
                            }
                        },
                        'y-axis-1': {
                            position: 'left',
                            ticks: {
                                callback: yAxisDataType === 'number' ? numberFormatFn :
                                        yAxisDataType === 'percent' ? percentFormatFn : moneyFormatFn
                            }
                        }
                    },
                    layout: {
                        padding: {
                            bottom: 50
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

                // Set manual height to preven too much squishing
                const minChartHeight = 150;  // The minimum height you want for the chart area
                const labelPadding = 50;    // Additional height to accommodate the labels
                // Set the canvas height
                element.height = minChartHeight + labelPadding;

                create_idwiz_chart(element, chartType, response.data.labels, response.data.datasets, options);
            } else {
                console.error("AJAX request successful but response indicated failure:", response);
            }
        },
        function(error) {
            console.error("An error occurred during the AJAX request:", error);
        }
    );
});

function create_idwiz_chart(element, chartType, labels, datasets, options) {
    const ctx = element.getContext('2d');
    new Chart(ctx, {
        type: chartType,
        data: {
            labels: labels,
            datasets: datasets
        },
        options: options
    });
}

});