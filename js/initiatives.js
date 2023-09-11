jQuery(document).ready(function ($) {

    // Handle both title and content updates for initiatives
    $(document).on('change', '[data-initUpdateType]', function() {
        const initID = $('.single-idwiz_initiative article').attr('data-initiativeid');
        const value = $(this).val();
        const updateType = $(this).attr('data-initUpdateType');
        const nonceValue = idAjax_initiatives.nonce;

        const additionalData = {
            initID: initID,
            updateContent: value,
            updateType: updateType
        };

        const successCallback = function(result) {
            console.log(result);
        };

        const errorCallback = function(xhr, status, error) {
            console.log(error);
        };

        idemailwiz_do_ajax('idemailwiz_save_initiative_update', nonceValue, additionalData, successCallback, errorCallback);
    });


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

    // Select2 for init search/select
    $('.initCampaignSelect').select2({
    
    multiple: true,
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

    // When Add Campaigns or remove campaign is clicked, do the ajax
    $(document).on('click', '.add-init-campaign, .remove-init-campaign', function() {
    var initiativeID = $(this).data('initiativeid');
    var action = $(this).data('initcampaignaction');
    var campaignIDs;

    

    if (action === 'remove') {
        campaignIDs = [$(this).data('campaignid')];
        var isConfirmed = window.confirm("Are you sure you want to remove this campaign?");
        if (!isConfirmed) {
            return;  // User canceled the action
        }
    } else {
        campaignIDs = $('.initCampaignSelect').val();
    }

    // Send an AJAX request to update the database
    idemailwiz_do_ajax(
        'idemailwiz_add_remove_campaign_from_initiative',
        idAjax_initiatives.nonce,
        {
            campaign_ids: campaignIDs,
            initiative_id: initiativeID,
            campaignAction: action,
        },
        function(response) {
            // Detailed response for debugging
            console.log(response);

            // User-friendly alert message based on overall success
            if (response.success) {
                alert("Campaigns succesfully added!");
                setTimeout(function() { window.location.reload(); }, 500);
            } else {
                alert("Some campaigns could not be processed. Check the console for details.");
                console.error("Detailed messages:", response.data.messages);
            }
        },
        function(error) {
            // Error handling
            alert("An error occurred. Check the console for details.");
            console.error("Failed to make call to update function", error);
        }
    );


});

    
    if ($('#idemailwiz_initiatives_table').length) {
        var allInitsTable = $('#idemailwiz_initiatives_table').DataTable({
            dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>tp',
            "order": [ 1, 'desc' ],
            language: {
                search: '',
                searchPlaceholder: 'Quick search',
            },
            scrollX: true,
            scrollY: true,
            paging: true,
			pageLength: 25,
            select: true,
			fixedHeader: {
                header: true,
                footer: false
            },
            buttons: [
                {
                    text: '<i class="fa-solid fa-plus"></i>',
                    className: 'wiz-dt-button new-initiative',
                    attr: {
                        'title': 'Create new initiative',
                    }
                },
                {
                    extend: 'selected',
                    text: '<i class="fa-solid fa-trash"></i>',
                    className: 'wiz-dt-button remove-initiative',
                    attr: {
                        'title': 'Delete initiative',
                    }
                },
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
            ],
            drawCallback: initiative_archive_table_callback
        });
        
        function initiative_archive_table_callback() {
            $('.new-initiative').on('click', function() {
                Swal.fire({
                    title: 'Enter Initiative Title',
                    input: 'text',
                    inputPlaceholder: 'Enter the title here'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const title = result.value;
            
                        // Ajax call to create new initiative
                        idemailwiz_do_ajax(
                            'idemailwiz_create_new_initiative',
                            idAjax_initiatives.nonce,  
                            { newInitTitle: title },
                            function(data) {
                                console.log(data);
                                var postUrl = idAjax_initiatives.site_url + '/?p=' + data.data.post_id;
                                window.location.href = postUrl;
                            },
                            function(error) {
                                console.log(error);
                            }
                        );
                    }
                });
            });

            $('.remove-initiative').on('click', function() {
                const selectedRows = allInitsTable.rows({ selected: true }).nodes().to$();
                const selectedIds = [];

                selectedRows.each(function() {
                    const initId = $(this).attr('data-initid');
                    if (initId) {
                        selectedIds.push(initId);
                    }
                });

                // Modify the Swal2 text based on the number of selected initiatives
                const swalTitle = selectedIds.length > 1 ? "Delete These Initiatives?" : "Delete This Initiative?";
                const swalButton = selectedIds.length > 1 ? "Yes, delete them" : "Yes, delete it";

                // Show Swal2 confirmation dialog
                Swal.fire({
                    title: swalTitle,
                    text: '(Campaigns will be preserved)',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: swalButton,
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Proceed with the Ajax call to delete the initiatives
                        idemailwiz_do_ajax(
                            'idemailwiz_delete_initiative',
                            idAjax_initiatives.nonce,
                            { selectedIds: selectedIds },
                            function(data) {
                                if (data.success) {
                                    // Turn the row red and then fade out
                                    selectedRows.addClass('removed').delay(2000).fadeOut(400, function(){
                                        // Remove from DOM
                                        $(this).remove();
                                    });
                                }
                            },
                            function(error) {
                                console.log(error);
                            }
                        );
                    }
                });
            });


        }

        

    }

    
    if ($('#idemailwiz_initiative_campaign_table').length) {
        
        // Custom sorting for date format 'm/d/Y'
        $.fn.dataTable.ext.type.order['date-mdy-pre'] = function (dateString) {
            var dateParts = dateString.split('/');
            return new Date(dateParts[2], dateParts[0] - 1, dateParts[1]).getTime(); // Month is 0-indexed
        };

		var idemailwiz_initiative_campaign_table = $('#idemailwiz_initiative_campaign_table').DataTable({
			dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>tp',
            columns: [ 
                { type: 'date-mdy' }, 
                {
                    width: "300px",
                },
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
            ],
			order: [[ 1, 'desc' ]],
			scrollX: true,
            scrollY: true,
            paging: true,
			pageLength: 25,
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
                    text: '<i class="fa-solid fa-rotate"></i>',
                    className: 'sync-initiative wiz-dt-button',
                    attr:  {
                        "data-sync-db": 'initiative',
                    },
                    autoClose: true,
                },
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
                    extend: 'spacer',
                    style: 'bar'
                },
                {
                    extend: 'selected',
                    text: '<i class="fa-solid fa-trash-can"></i>',
                    name: 'remove-from-init',
                    className: 'wiz-dt-button',
                    attr: {
                        'title': 'Remove from initiative',
                    },
                    action: function(e, dt, node, config) {
                        // Confirm dialog
                        let isConfirmed = confirm("Remove these campaigns from the initiative?");
                        if (!isConfirmed) {
                            return; // Exit the function if user clicked "Cancel"
                        }

                        // Get initiative ID
                        let selectedInitiative = $('#content article').attr('data-initiativeid');

                        // Retrieve selected row indices
                        let selectedRowIndices = dt.rows({ selected: true }).indexes().toArray();

                        // Extract campaign IDs from selected rows (using tr data attribute)
                        let selectedCampaignIds = selectedRowIndices.map(index => {
                            let rowNode = dt.row(index).node();
                            return $(rowNode).attr('data-campaignid') || dt.cell(index, 'campaign_id:name').data();
                        });


                        idemailwiz_do_ajax(
                            'idemailwiz_add_remove_campaign_from_initiative',
                            idAjax_initiatives.nonce,
                            {
                                initiative_id: selectedInitiative,
                                campaign_ids: selectedCampaignIds,
                                campaignAction: 'remove',
                            },
                            function(successData) {
                                console.log(successData);
                                setTimeout(function() { window.location.reload(); }, 500);
                            },
                            function(errorData) {
                                // Handle error
                                console.error("Failed to remove campaigns from initiative", errorData);
                                alert('Error removing campaign(s). Try refreshing the page and trying again.');
                            }
                        );
                    }

                },
			],
			 language: {
				search: '',
    	        searchPlaceholder: 'Quick search',
			 },
             drawCallback: idwiz_initiative_table_callback,
		});

        function idwiz_initiative_table_callback() {
            var api = this.api();
            
            // Readjust the column widths on each draw
             api.columns.adjust();
            
            
            // Sync initiative campaigns
            $('.sync-initiative').on('click', function() {
                var campaignIds = JSON.parse($('#idemailwiz_initiative_campaign_table').attr('data-campaignids'));
                handle_idwiz_sync_buttons("idemailwiz_ajax_sync", idAjax_initiatives.nonce, { campaignIds: JSON.stringify(campaignIds) });
            });
           


        }
	}

});