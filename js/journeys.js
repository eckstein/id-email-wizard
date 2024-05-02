jQuery(document).ready(function ($) {

    // On page load
    if ($(".single-journey-article").length) {
        updateDynamicJourneyRollup();
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
        console.log(campaignIdsArray);
        fetchRollUpSummaryData(campaignIdsArray, startDate, endDate, "#journey-timeline-rollup-summary");
    }
    

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



});