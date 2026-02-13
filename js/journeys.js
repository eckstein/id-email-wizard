jQuery(document).ready(function ($) {

    var journeyCampaignsTable = null;
    var showInactiveCampaigns = false;

    // On page load
    if ($(".single-journey-article").length) {
        updateDynamicJourneyRollup();
        initJourneyCampaignsTable();
    }

    function updateDynamicJourneyRollup() {
        var $rollupWrapper = $("#journey-rollup-wrapper");
        if ($rollupWrapper.length === 0) {
            return;   
        }
        var campaignIds = $rollupWrapper.attr('data-campaign-ids');
        var campaignIdsArray = JSON.parse(campaignIds); // Parse JSON string to array
        var startDate = $rollupWrapper.attr('data-start-date');
        var endDate = $rollupWrapper.attr('data-end-date');

        // Fetch rollup summary
        console.log('Fetching rollup data for campaigns:', campaignIdsArray);
        fetchRollUpSummaryData(campaignIdsArray, startDate, endDate, "#journey-timeline-rollup-summary");
    }

    // Initialize DataTables for the journey campaigns table
    function initJourneyCampaignsTable() {
        var $table = $('.journey-campaigns-sortable');
        if ($table.length === 0) {
            return;
        }

        // Add custom filter for inactive campaigns BEFORE initializing DataTable
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            // Only apply to our journey campaigns table
            if (settings.nTable.id.indexOf('journey-campaigns-table') === -1) {
                return true;
            }
            
            // If showing inactive, return all rows
            if (showInactiveCampaigns) {
                return true;
            }
            
            // Otherwise, check if row has inactive class
            var row = settings.aoData[dataIndex].nTr;
            return !$(row).hasClass('inactive-campaign');
        });

        journeyCampaignsTable = $table.DataTable({
            paging: false,
            searching: true, // Enable searching for the filter to work
            info: false,
            order: [[2, 'desc']], // Sort by Last Sent column descending by default
            columnDefs: [
                { targets: 0, orderable: true }, // Campaign name
                { targets: 1, orderable: true }, // Status
                { targets: 2, orderable: true }, // Last Sent
                { targets: '_all', orderable: true }
            ],
            autoWidth: false,
            responsive: false,
            dom: 'lrtip' // Hide the search box but keep search functionality
        });
    }

    // Handle show inactive campaigns checkbox
    $('#show-inactive-campaigns').on('change', function() {
        showInactiveCampaigns = $(this).is(':checked');
        if (journeyCampaignsTable) {
            journeyCampaignsTable.draw();
        }
    });

    // Handle sync journey button
    $('.sync-journey').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const originalText = $button.text();
        const journeyIds = $button.data('journeyids');
        
        $button.prop('disabled', true).text('Syncing...');
        
        const data = {
            action: 'idemailwiz_ajax_sync',
            security: idAjax.wizAjaxNonce,
            campaignIds: journeyIds ? JSON.stringify(journeyIds) : JSON.stringify([])
        };
        
        $.post(idAjax.ajaxurl, data)
            .done(function(response) {
                if (response.success) {
                    $button.text('Synced!').removeClass('green').addClass('blue');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Sync failed: ' + (response.data || 'Unknown error'));
                    $button.prop('disabled', false).text(originalText);
                }
            })
            .fail(function(xhr, status, error) {
                alert('Sync failed: Network error');
                $button.prop('disabled', false).text(originalText);
            });
    });

    // Timeline controls
    var fiscalYearButtons = $('.journey-timeline-control-set button[data-fiscalyear]');
    var monthButtons = $('.journey-timeline-control-set button[data-month]');
    var metricDropdown = $('.journey-timeline-control-set select[name="metric"]');

    function updateTimelineData() {
        var selectedYears = fiscalYearButtons.filter('.active').map(function() {
            return $(this).data('fiscalyear');
        }).get();

        var selectedMonths = monthButtons.filter('.active').map(function() {
            return $(this).data('month');
        }).get();

        var metric = metricDropdown.val();

        var url = new URL(window.location.href);
        url.searchParams.delete('years[]');
        url.searchParams.delete('months[]');

        selectedYears.forEach(function(year) {
            url.searchParams.append('years[]', year);
        });

        selectedMonths.forEach(function(month) {
            url.searchParams.append('months[]', month);
        });

        url.searchParams.set('metric', metric);

        window.location.href = url.toString();
    }

    fiscalYearButtons.on('click', function() {
        $(this).toggleClass('active');
        updateTimelineData();
    });

    monthButtons.on('click', function() {
        $(this).toggleClass('active');
        updateTimelineData();
    });

    metricDropdown.on('change', function() {
        updateTimelineData();
    });

    // Handle collapsible sections
    $('.journey-description-section h3').on('click', function() {
        $(this).parent().find('.journey-details').slideToggle();
    });

});