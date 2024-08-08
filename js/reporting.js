
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
            .attr('data-enddate', formDataObject.endDate ? formDataObject.endDate : defaultEndDate);

        if ($(canvas).attr('data-chartid') == 'opensReport') {
            $(canvas)
                .attr('data-minmetric', formDataObject.minOpenRate || 0)
                .attr('data-maxmetric', formDataObject.maxOpenRate  || 100);
        } else if ($(canvas).attr('data-chartid') == 'ctrReport') {
            $(canvas)
                .attr('data-minmetric', formDataObject.minClickRate ? formDataObject.minClickRate * 100 : 0)
                .attr('data-maxmetric', formDataObject.maxClickRate ? formDataObject.maxClickRate * 100 : 100);
        } else if ($(canvas).attr('data-chartid') == 'ctoReport') {
            $(canvas)
                .attr('data-minmetric', formDataObject.minCtoRate ? formDataObject.minCtoRate * 100 : 0)
                .attr('data-maxmetric', formDataObject.maxCtoRate ? formDataObject.maxCtoRate * 100 : 100);
        }

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
