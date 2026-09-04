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

        var tableOptions = {
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
        };

        // "Export current view": the same copy/CSV/Excel collection the campaigns
        // table uses (js/id-general.js). Because it exports with the search
        // modifier applied, the file matches what is on screen - the inactive
        // campaign filter and the date range included.
        if (typeof window.idwizExportCollection === 'function') {
            tableOptions.dom = 'B' + tableOptions.dom;
            tableOptions.buttons = [window.idwizExportCollection()];
        }

        journeyCampaignsTable = $table.DataTable(tableOptions);
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
        const originalText = $button.html();
        const journeyIds = $button.data('journeyids');
        
        $button.prop('disabled', true).text('Syncing...');

        // Honor the date range currently being viewed so we don't sync the journey's
        // entire purchase history (which can exhaust server memory). Fall back to the
        // rollup wrapper's defaults if the date pickers aren't present on this view.
        const $rollupWrapper = $('#journey-rollup-wrapper');
        const startDate = $('#wizStartDate').val() || $rollupWrapper.attr('data-start-date') || '';
        const endDate = $('#wizEndDate').val() || $rollupWrapper.attr('data-end-date') || '';

        const campaignIds = journeyIds ? JSON.stringify(journeyIds) : JSON.stringify([]);

        // Campaigns, templates, metrics, purchases, experiments, journeys. This
        // does not touch the triggered tables the Sent/Delivered/Opens columns
        // are read from.
        const metricsSync = $.post(idAjax.ajaxurl, {
            action: 'idemailwiz_ajax_sync',
            security: idAjax.wizAjaxNonce,
            campaignIds: campaignIds,
            startDate: startDate,
            endDate: endDate
        });

        // Queues the Iterable engagement exports that do fill those columns.
        const engagementSync = $.post(idAjax.ajaxurl, {
            action: 'handle_journey_triggered_sync',
            security: idAjax.wizAjaxNonce,
            campaignIds: campaignIds,
            startDate: startDate,
            endDate: endDate
        });

        $.when(metricsSync, engagementSync)
            .done(function(metricsResult, engagementResult) {
                const metricsResponse = metricsResult[0];
                const engagementResponse = engagementResult[0];

                if (!metricsResponse.success) {
                    alert('Sync failed: ' + (metricsResponse.data || 'Unknown error'));
                    $button.prop('disabled', false).html(originalText);
                    return;
                }

                // The engagement queue only drains while Engagement Data Sync is
                // on, so pass that back rather than reporting a finished sync.
                if (engagementResponse.success && engagementResponse.data && !engagementResponse.data.engagementSyncEnabled) {
                    alert(engagementResponse.data.message);
                } else if (!engagementResponse.success) {
                    alert('Engagement sync failed: ' + (engagementResponse.data || 'Unknown error'));
                }

                $button.text('Synced!').removeClass('green').addClass('blue');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            })
            .fail(function(xhr, status, error) {
                alert('Sync failed: Network error');
                $button.prop('disabled', false).html(originalText);
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