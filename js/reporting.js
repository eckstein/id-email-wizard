
jQuery(document).ready(function ($) {
    
    // Initialize Select2 for cohort selects
$(".cohort-select, .cohort-exclude").select2({
    multiple: true
});

// Handle form submission
$("#reports-filter-form").on("submit", function(e) {
    e.preventDefault(); // Prevent default form submission

    // Get current URL parameters
    var queryParams = new URLSearchParams(window.location.search);

    // Collect form data
    var formData = $(this).serializeArray();
    var formDataObject = {};

    // Update query parameters with form data
    formData.forEach(function(item) {
        if (item.name === "cohorts" || item.name === "exclude_cohorts") {
            // Handle multiple select values
            var values = $("#wiz-report-" + item.name).select2("val"); // Get values from Select2
            if (values && values.length > 0) {
                queryParams.set(item.name, values.join(','));
                formDataObject[item.name] = values; // Store as an array
            } else {
                queryParams.delete(item.name);
                formDataObject[item.name] = [];
            }
        } else {
            formDataObject[item.name] = item.value;
            if (item.value) {
                queryParams.set(item.name, item.value);
            } else {
                queryParams.delete(item.name);
            }
        }
    });

    // Construct new URL
    var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?" + queryParams.toString();

    // Update URL without reloading the page
    window.history.pushState({path: newUrl}, '', newUrl);

        // If the form has the refresh-on-submit class, reload the page with the new URL
        if ($(this).hasClass('refresh-on-submit')) {
            window.location.href = newUrl
            return
        }

    // Update charts dynamically after updating URL
    
    $("canvas").each(function() {
        const canvas = this;

        if (canvas.chartInstance) {
            canvas.chartInstance.destroy();
        }

        // Get default dates
        const today = new Date();
        const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        const defaultStartDate = firstDayOfMonth.toISOString().split('T')[0];
        const defaultEndDate = today.toISOString().split('T')[0];

        // Update data attributes
        $(canvas)
            .attr('data-cohorts', JSON.stringify(formDataObject.cohorts || ['all']))
            .attr('data-cohorts-exclude', JSON.stringify(formDataObject.exclude_cohorts || []))
            .attr('data-minsends', formDataObject.minSendSize || 1)
            .attr('data-maxsends', formDataObject.maxSendSize || 500000)
            .attr('data-startdate', formDataObject.startDate ? formDataObject.startDate : defaultStartDate)
            .attr('data-enddate', formDataObject.endDate ? formDataObject.endDate : defaultEndDate)
            .attr('data-campaigntype', formDataObject.campaignType ? formDataObject.campaignType.toLowerCase() : 'all')
            .attr('data-messagemedium', formDataObject.messageMedium || 'all');

        // Handle both legacy and new parameter structure
        var chartId = $(canvas).attr('data-chartid');
        
        // Legacy parameter support
        if (chartId == 'opensReport') {
            var minFilter = formDataObject.minOpenRate || formDataObject.opensReport_minFilter || 0;
            var maxFilter = formDataObject.maxOpenRate || formDataObject.opensReport_maxFilter || 100;
            $(canvas)
                .attr('data-minmetric', minFilter)
                .attr('data-maxmetric', maxFilter)
                .attr('data-minfilter', minFilter)
                .attr('data-maxfilter', maxFilter);
        } else if (chartId == 'ctrReport') {
            var minFilter = formDataObject.minClickRate || formDataObject.ctrReport_minFilter || 0;
            var maxFilter = formDataObject.maxClickRate || formDataObject.ctrReport_maxFilter || 30;
            $(canvas)
                .attr('data-minmetric', minFilter)
                .attr('data-maxmetric', maxFilter)
                .attr('data-minfilter', minFilter)
                .attr('data-maxfilter', maxFilter);
        } else if (chartId == 'ctoReport') {
            var minFilter = formDataObject.minCtoRate || formDataObject.ctoReport_minFilter || 0;
            var maxFilter = formDataObject.maxCtoRate || formDataObject.ctoReport_maxFilter || 100;
            $(canvas)
                .attr('data-minmetric', minFilter)
                .attr('data-maxmetric', maxFilter)
                .attr('data-minfilter', minFilter)
                .attr('data-maxfilter', maxFilter);
        }
        
        // Handle new module-specific parameters
        ['opensReport', 'ctrReport', 'ctoReport', 'unsubReport', 'revReport'].forEach(function(moduleId) {
            if (chartId == moduleId) {
                if (formDataObject[moduleId + '_minFilter'] !== undefined) {
                    $(canvas).attr('data-minfilter', formDataObject[moduleId + '_minFilter']);
                    $(canvas).attr('data-minmetric', formDataObject[moduleId + '_minFilter']);
                }
                if (formDataObject[moduleId + '_maxFilter'] !== undefined) {
                    $(canvas).attr('data-maxfilter', formDataObject[moduleId + '_maxFilter']);
                    $(canvas).attr('data-maxmetric', formDataObject[moduleId + '_maxFilter']);
                }
                if (formDataObject[moduleId + '_minScale'] !== undefined) {
                    $(canvas).attr('data-minscale', formDataObject[moduleId + '_minScale']);
                }
                if (formDataObject[moduleId + '_maxScale'] !== undefined) {
                    $(canvas).attr('data-maxscale', formDataObject[moduleId + '_maxScale']);
                }
            }
        });

        idwiz_fill_chart_canvas(canvas);
    });
    fill_engagement_charts();
});

// Handle "All" selection in cohort selects
$(".cohort-select, .cohort-exclude").on("change", function() {
    var $select = $(this);
    var selectedValues = $select.val() || [];

    if (selectedValues.includes("all")) {
        $select.val(["all"]).trigger('change.select2');
    } else {
        selectedValues = selectedValues.filter(value => value !== "all");
        $select.val(selectedValues).trigger('change.select2');
    }
});

});
