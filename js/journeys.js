jQuery(document).ready(function ($) {

    // On page load
    if ($(".journey-timeline").length) {
        updateDynamicJourneyRollup();
    }


    $(".journey-timeline").sortable({
        items: ".timeline-campaign-row",
        handle: ".timeline-campaign-fixedCol",
        update: function (event, ui) {
            var journeyCampaignIds = [];
        
            // Assuming the post_id is stored on an element with a specific class or ID
            // Modify the selector as per your HTML structure
            var postId = $(ui.item).closest('.journey-timeline').data('post-id'); 

            $(".timeline-campaign-row").each(function() {
                var campaignId = $(this).data('campaign-id');
                if (campaignId) { // Check if campaignId is not empty
                    journeyCampaignIds.push(campaignId);
                }
            });

            // Proceed only if we have a valid postId and there are campaign IDs to update
            if (postId && journeyCampaignIds.length > 0) {
                idemailwiz_do_ajax(
                    "idemailwiz_update_journey_campaigns_order",
                    idAjax_journeys.nonce,
                    { postId: postId, journeyCampaignIds: journeyCampaignIds },
                    function (response) {
                        if (response.success) {
                            console.log("Order updated successfully");
                        } else {
                            console.error("Server-side error: ", response.data);
                        }
                    },
                    function (error) {
                        console.error("AJAX error: ", error);
                    }
                );
            } else {
                console.error("Missing postId or journeyCampaignIds");
            }
        },
    });



    
    function updateDynamicJourneyRollup() {
        var $rollupWrapper = $("#journey-rollup-wrapper");
        var campaignIds = $rollupWrapper.attr('data-campaign-ids');
        var campaignIdsArray = JSON.parse(campaignIds); // Parse JSON string to array
        var startDate = $rollupWrapper.attr('data-start-date');
        var endDate = $rollupWrapper.attr('data-end-date');

        // Fetch rollup summary
        console.log(campaignIdsArray);
        fetchRollUpSummaryData(campaignIdsArray, startDate, endDate, "#journey-timeline-rollup-summary");
    }



    $(document).on("click", ".hide-journey-campaign", function () {
        var $row = $(this).closest('.timeline-campaign-row');
        var campaignId = $row.data('campaign-id');
        var postId = $row.data('post-id');
        var startDate = $(this).closest('.journey-timeline').data('start-date');
        var endDate = $(this).closest('.journey-timeline').data('end-date');
        
        idemailwiz_do_ajax(
            "update_journey_campaign_visibility",
            idAjax_journeys.nonce,
            { postId, campaignId, metaAction: 'hide' },
            function (response) {
                if (response.success) {
                    $row.fadeOut();
                    setTimeout(function () {
                        $('.show-hidden-journey-campaigns').css('background-color', '#90ee90');
                        setTimeout(function () {
                            $('.show-hidden-journey-campaigns').css('background-color', '#fff');
                        }, 1000);

                        $row.remove();
                    }, 500);


                   
                    //do_wiz_notif({ message: response.data.message, duration: 3000 });

                    var campaignIds = response.data.visibleCampaigns;
                    update_journey_visible_campaign_id_data(campaignIds);
                     

                    refreshHiddenCampaignSelect(postId, startDate, endDate);
                    update_journey_meta_counts(response.data.newCounts.total+' campaigns ('+response.data.newCounts.hidden+' hidden)');
                                        
                    
                    setTimeout(function() {
                        updateDynamicJourneyRollup();
                    }, 500);

                    setTimeout(function () {
                        $('.show-hidden-journey-campaigns').css('background-color', '#90ee90');
                        setTimeout(function () {
                            $('.show-hidden-journey-campaigns').css({
                                backgroundColor: '#fff',
                                transition: 'background-color 2s'
                            });
                        }, 1000);
                    }, 1500);

                    

                } else {
                    console.error("Server-side error: ", response.data.message);
                    
                    do_wiz_notif({ message: "Error: " + response.data.message, duration: 3000 });
                }
            },
            function (error) {
                console.error("AJAX error: ", error);
            }
        );
    });

    $(document).on('change', '.show-hidden-journey-campaigns', function() {
        var postId = $(this).data('post-id');
        var campaignId = $(this).val(); 
        var startDate = $(this).data('start-date');
        var endDate = $(this).data('end-date');

        $('.show-hidden-journey-campaigns').attr('disabled', true);
        

        

        idemailwiz_do_ajax(
            'update_journey_campaign_visibility', 
            idAjax_journeys.nonce, 
            { 
                postId: postId, 
                campaignId: campaignId,
                metaAction: 'show'
            },
            function (response) {
                if (response.success) {
                    add_hidden_campaign_to_timeline(postId, campaignId, startDate, endDate);
                    update_journey_meta_counts(response.data.newCounts.total+' campaigns ('+response.data.newCounts.hidden+' hidden)');
                    campaignIds = response.data.visibleCampaigns;
                    update_journey_visible_campaign_id_data(campaignIds);
                    setTimeout(function() {
                        updateDynamicJourneyRollup();
                    }, 500);
                    
                    
                    
                    console.log(response.data.message); // Log detailed message
                    do_wiz_notif({ message: response.data.message, duration: 3000 });

                    
                } else {
                    console.error("Server-side error: ", response.data.message);
                    do_wiz_notif({ message: "Error: " + response.data.message, duration: 3000 });
                }
            },
            function(error) { // error callback
                console.error('AJAX error: ', error);
            }
        );
    
    });
    
    
    function update_journey_visible_campaign_id_data(campaignIds) {
        var $rollupWrapper = $("#journey-rollup-wrapper");

        // Ensure campaignIds is an array
        var campaignIdsArray = Array.isArray(campaignIds) ? campaignIds : Object.values(campaignIds);

        // Convert array to JSON string
        var campaignIdsStr = JSON.stringify(campaignIdsArray); 
        $rollupWrapper.attr('data-campaign-ids', campaignIdsStr);
    }

    if ($('.timeline-campaign-row.loading').length) {
        $('.timeline-campaign-row.loading').each(function(i, row) {
            var $row = $(row); // Convert DOM element to jQuery object
            var postId = $row.data('post-id');
            var startDate = $row.data('start-date');
            var endDate = $row.data('end-date');
            var campaignId = $row.data('campaign-id');

            load_journey_timeline_row(postId, campaignId, startDate, endDate);
        });
    }

    // On initial page load, load the rows
    function load_journey_timeline_row(postId, campaignId, startDate, endDate) {
        var $loadingRow = $('.timeline-campaign-row.loading[data-campaign-id="'+campaignId+'"]');
        idemailwiz_do_ajax(
            'ajax_generate_journey_timeline_row', 
            idAjax_journeys.nonce, 
            { 
                postId: postId, 
                campaignId: campaignId,
                startDate: startDate,
                endDate: endDate
            },
            function (response) {
                if (response.success) {
                    
                    
                    $loadingRow.replaceWith(response.data.html);
                        
                } else {
                    console.error("Server-side error: ", response.data.message);
                    //do_wiz_notif({ message: "Error: " + response.data.message, duration: 3000 });
                }
            },
            function(error) { // error callback
                console.error('AJAX error: ', error);
            }
        );
    }
    
    function add_hidden_campaign_to_timeline(postId, campaignId, startDate, endDate) {
        // Add filler row while it loads
        $('.timeline-campaign-row.date-row').after('<tr data-campaign-id="'+campaignId+'"class="timeline-campaign-row showAsNew placeholder"><td><i class="fa-solid fa-spin fa-spinner"></i>&nbsp;&nbsp;Unhiding campaign...</td></tr>');
        idemailwiz_do_ajax(
            'ajax_generate_journey_timeline_row', 
            idAjax_journeys.nonce, 
            { 
                postId: postId, 
                campaignId: campaignId,
                startDate: startDate,
                endDate: endDate
            },
            function (response) {
                if (response.success) {
                    $('.timeline-campaign-row[data-campaign-id="'+campaignId+'"].placeholder').replaceWith(response.data.html);
                    refreshHiddenCampaignSelect(postId, startDate, endDate);
                    //update_journey_meta_counts(response.data.newCounts.total+' campaigns ('+response.data.newCounts.hidden+' hidden)');

                    setTimeout(function() {
                        $(document).find('.timeline-campaign-row.showAsNew').removeClass('showAsNew');
                    }, 5000);
                    
                    
                    console.log(response.data.message); // Log detailed message

                    // Optionally, you can use response.data to update the UI dynamically
                } else {
                    console.error("Server-side error: ", response.data.message);
                    //do_wiz_notif({ message: "Error: " + response.data.message, duration: 3000 });
                }
            },
            function(error) { // error callback
                console.error('AJAX error: ', error);
            }
        );
    }

    function update_journey_meta_counts(text) {
        $(document).find('.journey-meta-counts').text(text);
    }
    

    function refreshHiddenCampaignSelect(postId, startDate, endDate) {
        idemailwiz_do_ajax(
            'get_unhide_hidden_journey_campaigns_select_ajax',
            idAjax_journeys.nonce,
            {
                postId: postId,
                startDate: startDate,
                endDate: endDate
            },
            function (response) {
                if (response.success) {
                    $('.show-hidden-journey-campaigns').replaceWith(response.data.html);
                    console.log(response.data.message); // Log detailed message
    
                    // Optionally, you can use response.data to update the UI dynamically
                } else {
                    console.error("Server-side error: ", response.data.message);
                }
            },
            function(error) { // error callback
                console.error('AJAX error: ', error);
            }
        )

    }

    function adjustPopupPosition($popup) {
        var $table = $popup.closest('table'); // Get the closest table ancestor
        var $cell = $popup.parent(); // The cell containing the popup
        var tableOffset = $table.offset();
        var cellOffset = $cell.offset();
        var scrollLeft = $table.scrollLeft();
        var tableWidth = $table.width();
        var popupWidth = $popup.outerWidth();
        var popupHeight = $popup.outerHeight();

        // Horizontal position
        var leftPos = cellOffset.left - tableOffset.left + scrollLeft;
        if (leftPos + popupWidth > tableWidth) {
            // If the popup goes beyond the right edge of the table
            $popup.css({
                left: 'auto',
                right: 0
            });
        } else {
            // Normal left position
            $popup.css({
                left: 0,
                right: 'auto'
            });
        }

        // Vertical position
        // Adjust if popup goes beyond the bottom of the table
        var tableHeight = $table.height();
        if (cellOffset.top + popupHeight > tableOffset.top + tableHeight) {
            $popup.css({
                top: 'auto',
                bottom: '100%'
            });
        } else {
            $popup.css({
                top: '100%',
                bottom: 'auto'
            });
        }
    }

    $(document).ready(function() {
        $(document).on('mouseenter', '.journey-timeline .timeline-campaign-row .timeline-cell.active', function() {
            var $popup = $(this).find('.timeline-cell-popup');
            adjustPopupPosition($popup);
            $popup.show();
        }).on('mouseleave', '.journey-timeline .timeline-campaign-row .timeline-cell.active', function() {
            $(this).find('.timeline-cell-popup').hide();
        });
    });



});