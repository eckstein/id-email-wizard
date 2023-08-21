jQuery(document).ready(function ($) {


if ($('#purchByLOB').length) { //only run if a chart is on the page
    var ctx = document.getElementById('purchByLOB').getContext('2d');
    var purchByLOB = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [], // Empty labels initially
            datasets: [
                {
                    label: 'Number of purchases',
                    data: [], // Empty data initially for purchases
                },
                {
                    label: 'Revenue',
                    data: [], // Empty data initially for revenue
                    yAxisID: 'y-axis-revenue', // Associate with the right y-axis
                }
            ]
        },
        options: {
            scales: {
                x: {
                    ticks: {
                        
                        autoSkip: false, // Auto-skip labels to avoid overlap
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1, // Force step size to be 1
                        precision: 0 // No decimal places
                    }
                },
                'y-axis-revenue': {
                    type: 'linear',
                    position: 'right',
                    ticks: {
                        // Define a callback function to format the tick labels
                        callback: function(value, index, values) {
                            return '$' + value.toLocaleString(); // Add a dollar sign and format with commas
                        },

                        

                    }
                }
            },
            plugins: [{
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            var value = context.parsed.y;
                            if (context.datasetIndex === 1) { // Check if it's the revenue dataset
                                label += '$' + value.toLocaleString('en-US'); // Add a dollar sign and format with commas
                            } else {
                                label += value;
                            }
                            return label;
                        }
                    }
                },
                
            }]
        }
    });

        var campaignId = $('.wizcampaign-single').data('campaignid');
        $.ajax({
            url: idAjax.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action:'chart_wizpurchases_by_division',
                security: idAjax_wiz_charts.nonce,
                campaignIds: [campaignId], //send as an array, even though just one
            },
            success:function(response) {
                console.log(response);
                if (response.success) {
                    // Update the chart with the retrieved data
                    purchByLOB.data.labels = response.data.labels;
                    purchByLOB.data.datasets = response.data.datasets;
                    purchByLOB.update();
                }
            },
            error: function(errorThrown){
                console.log(errorThrown);
            }


        });
} // End check for #purchByLOB

if ($('#purchByDate').length) { //only run if a chart is on the page
    var ctx = document.getElementById('purchByDate').getContext('2d');
    var purchByDate = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [], // Empty labels initially
            datasets: [
                {
                    label: 'Number of purchases',
                    data: [], // Empty data initially for purchases
                }
            ]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1, // Force step size to be 1
                        precision: 0 // No decimal places
                    }
                }
            }
        }
    });

    var campaignId = $('.wizcampaign-single').data('campaignid');
    $.ajax({
        url: idAjax.ajaxurl,
        method: 'POST',
        dataType: 'json',
        data: {
            action:'chart_wizpurchases_by_date',
            security: idAjax_wiz_charts.nonce,
            campaignIds: [campaignId], //send as an array, even though just one
        },
        success:function(response) {
            console.log(response);
            if (response.success) {
                // Update the chart with the retrieved data
                purchByDate.data.labels = response.data.labels;
                purchByDate.data.datasets = response.data.datasets;
                purchByDate.update();
            }
        },
        error: function(errorThrown){
            console.log(errorThrown);
        }
    });
} // End check for #purchByDate






});



