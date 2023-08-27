jQuery(document).ready(function ($) {


    // Fill our summary table on page load
    // Initialize variables for totals
    var totalSends = 0;
    var totalOpens = 0;
    var totalClicks = 0;
    var totalPurchases = 0;
    var totalRevenue = 0;
    var totalUnsubs = 0;

    // Iterate through the rows of the second table to calculate the totals
    $('#idemailwiz_initiative_campaign_table tbody tr').each(function() {
        totalSends += parseFloat($(this).find('.uniqueSends').text().replace(/,/g, ''));
        totalOpens += parseFloat($(this).find('.uniqueOpens').text().replace(/,/g, ''));
        totalClicks += parseFloat($(this).find('.uniqueClicks').text().replace(/,/g, ''));
        totalPurchases += parseFloat($(this).find('.uniquePurchases').text().replace(/,/g, ''));
        totalRevenue += parseFloat($(this).find('.campaignRevenue').text().replace(/,/g, '').replace('$', ''));
        totalUnsubs += parseFloat($(this).find('.uniqueUnsubs').text().replace(/,/g, ''));
    });

    // Perform the required calculations
    var openRate = (totalOpens / totalSends) * 100;
    var CTR = (totalClicks / totalSends) * 100;
    var CTO = (totalClicks / totalOpens) * 100;
    var CVR = (totalPurchases / totalSends) * 100;
    var AOV = totalRevenue / totalPurchases;
    var unsubRate = (totalUnsubs / totalSends) * 100;

    // Initialize a number formatter for currency
    var currencyFormatter = new Intl.NumberFormat('en-US', {
     style: 'currency',
     currency: 'USD',
    });

   // Update the first table with the calculated values
    $('.initiativeSends .metric_view_value').text(totalSends.toLocaleString());
    $('.initiativeOpenRate .metric_view_value').text(openRate.toFixed(2) + '%');
    $('.initiativeCtr .metric_view_value').text(CTR.toFixed(2).toLocaleString() + '%');
    $('.initiativeCto .metric_view_value').text(CTO.toFixed(2).toLocaleString() + '%');
    $('.initiativePurchases .metric_view_value').text(totalPurchases.toLocaleString());
    $('.initiativeRevenue .metric_view_value').text(currencyFormatter.format(totalRevenue.toFixed(2)));
    $('.initiativeCvr .metric_view_value').text(CVR.toFixed(2).toLocaleString() + '%');
    $('.initiativeAov .metric_view_value').text(currencyFormatter.format(AOV.toFixed(2)));
    $('.initiativeUnsubRate .metric_view_value').text(unsubRate.toFixed(2).toLocaleString() + '%');

    // Gather the campaign IDs to exclude
    var campaignIdsToExclude = [];
    $('#idemailwiz_initiative_campaign_table tbody tr').each(function() {
    var campaignID = $(this).find('.remove-init-campaign').data('campaignid');
    if (campaignID) {
        campaignIdsToExclude.push(campaignID.toString());
    }
    });

    // Select2 for campaigns search/select
    $('.initCampaignSelect').select2({
    minimumInputLength: 3,
    ajax: {
        delay: 250,
        transport: function(params, success, failure) {
        idemailwiz_do_ajax(
            'idemailwiz_get_campaigns_for_select',
            idAjax_initiatives.nonce, 
            {
            q: params.data.term,
            exclude: campaignIdsToExclude // Include the IDs to exclude as a parameter
            },
            function(data) {
            success({results: data});
            },
            function(error) {
            console.error("Failed to fetch campaigns", error);
            failure();
            }
        );
        }
    }
    });



    // Toggle the add campaign form/button
    $('.show-add-to-campaign').on('click', function(e) {
        e.preventDefault();
            $('.initiative-add-campaign-form').slideToggle();
        });

    // When Add Campaign is clicked, do the ajax
    $(document).on('click', '.add-to-table, .remove-init-campaign', function() {
    var initiativeID = $(this).data('initiativeid');
    var action = $(this).data('initcampaignaction');
    var campaignID;

    if (action === 'remove') {
        campaignID = $(this).data('campaignid');
        var isConfirmed = window.confirm("Are you sure you want to remove this campaign?");
        if (!isConfirmed) {
            return;  // User canceled the action
        }
    } else {
        campaignID = $('.initCampaignSelect').val();
    }

    addRemoveInitCampaign();

    function addRemoveInitCampaign() {
        // Send an AJAX request to update the database
        idemailwiz_do_ajax(
            'idemailwiz_add_remove_campaign_from_initiative',
            idAjax_initiatives.nonce,
            {
                campaign_id: campaignID,
                initiative_id: initiativeID,
                campaignAction: action,
            },
            function(response) {
                if (response.success) {
                    alert(response.data.message || "Campaign has been added!");
                    location.reload();
                } else {
                    alert(response.data.message || "Failed to update campaign.");
                }
            },
            function(error) {
                console.error("Failed to make call to update function", error);
            }
        );
    }
});

    
    if ($('.idemailwiz-simple-table').length) {
        
        // Custom sorting for date format 'm/d/Y'
        $.fn.dataTable.ext.type.order['date-mdy-pre'] = function (dateString) {
            var dateParts = dateString.split('/');
            return new Date(dateParts[2], dateParts[0] - 1, dateParts[1]).getTime(); // Month is 0-indexed
        };

		var simpleTable = $('.idemailwiz-simple-table').DataTable({
			dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>t',
            columns: [
                null, // Assuming the first column doesn't require custom sorting
                { type: 'date-mdy' }, // Specify custom sorting for the 2nd column
                null, 
                null, 
                null, 
                null, 
                null, 
                null, 
                null, 
                null, 
                null, 
                null, 
                null, 
                { orderable: false, targets: 0 }, 
            ],
			order: [[ 1, 'desc' ]],
			scrollX: true,
            scroller: true,
            scrollY: true,
			pageLength: 100,
            select: true,
			fixedHeader: {
                header: true,
                footer: false
            },
            colReorder: {
                realtime: true,
            },
			buttons: [
				{
					extend: 'collection',
					text: '<i class="fa-solid fa-file-arrow-down"></i>',
					className: 'wiz-dt-button',
					attr: {
						'title': 'Export',
					},
					align: 'button-right',
					autoClose: true,
					buttons: [ 
						'copy',
						'csv', 
						'excel' ],
					background: false,
				},
                {
                    text: '<i class="fa-solid fa-rotate"></i>',
                    className: 'sync-initiative wiz-dt-button',
                    attr:  {
                        "data-sync-db": 'initiative',
                    },
                    autoClose: true,
                },
			],
			 language: {
				search: '',
    	        searchPlaceholder: 'Quick search',
			 },
             drawCallback: idwiz_initiative_table_callback,
		});

        function idwiz_initiative_table_callback() {

            
            $('.sync-initiative').on('click', function() {
                idwiz_sync_initiative();
            })
            
            function idwiz_sync_initiative() {
                var campaignIds = JSON.parse($('#idemailwiz_initiative_campaign_table').attr('data-campaignids'));
                idemailwiz_do_ajax(
                    "idemailwiz_ajax_sync",
                    idAjax_initiatives.nonce,
                    {
                        campaignIds: JSON.stringify(campaignIds)
                    },
                    function(result) {
                        $('#wiztable_status_updates .wiztable_update').text('Sync completed! Refresh the table for new data');
                        $('#wiztable_status_sync_details').load(idAjax.plugin_url + '/sync-log.txt');
                    },
                    function(error) {
                        console.log(error);
                        $('#wiztable_status_updates .wiztable_update').text('ERROR: Sync process failed with message: ' + error);
                        $('#wiztable_status_sync_details').load(idAjax.plugin_url + '/sync-log.txt');
                    }
                );
            }

        }
	}

});