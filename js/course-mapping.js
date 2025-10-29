jQuery(document).on("click", ".add-course", function () {
    var courseId = jQuery(this).closest(".course-recs").data("course-id");
    var recType = jQuery(this).closest(".course-recs").data("rec-type");
    var division = jQuery(this).closest(".course-recs").data("division");
    // Note: Target fiscal years removed - mapping is now course-to-course without FY restriction

    // Capture the current scroll position
    var scrollPosition = jQuery(window).scrollTop();

    Swal.fire({
        title: "Add Course",
        html: '<select id="selectCourse" style="width: 100%;" multiple="multiple"><option value="">Select courses</option></select>',
        showCancelButton: true,
        confirmButtonText: "Add Courses",
        allowOutsideClick: false,
        didOpen: function () {
            // Initialize Select2
            var select2Instance = jQuery("#selectCourse").select2({
                dropdownParent: jQuery(".swal2-container"),
                width: "100%",
                placeholder: "Select courses",
                multiple: true,
                ajax: {
                    transport: function (params, success, failure) {
                        idemailwiz_do_ajax(
                            "id_get_courses_options",
                            idAjax_id_general.nonce,
                            {
                                term: params.data.term, // Pass the search term for filtering courses
                                division: division
                                // Note: target_fiscals parameter removed since mapping is now course-to-course
                            },
                            function (response) {
                                success(response);
                            },
                            function (error) {
                                failure(error);
                            }
                        );
                    },
                    processResults: function (data) {
                        // Check if data is in expected format, if not, handle appropriately
                        if (!Array.isArray(data) && data.success && Array.isArray(data.data)) {
                            // If data is wrapped in an object with 'data' key
                            return { results: data.data };
                        } else if (Array.isArray(data)) {
                            // If data is a plain array
                            return { results: data };
                        } else {
                            console.error("Unexpected data format received for Select2: ", data);
                            return { results: [] };
                        }
                    },
                },
            });
            
            // Auto-focus the search field
            setTimeout(function() {
                jQuery('.select2-search__field').focus();
                
                // Reset the scroll position AFTER focus, with a slightly longer delay
                setTimeout(function() {
                    jQuery(window).scrollTop(scrollPosition);
                }, 50);
            }, 100);
        },
        preConfirm: function () {
            return new Promise(function (resolve) {
                resolve({
                    course_id: courseId,
                    rec_type: recType,
                    status: "active",
                    selected_courses: jQuery("#selectCourse").val(),
                });
            });
        },
    }).then(function (result) {
        if (result.isConfirmed && result.value.selected_courses && result.value.selected_courses.length > 0) {
            idemailwiz_do_ajax(
                "id_add_course_to_rec",
                idAjax_id_general.nonce,
                {
                    course_id: result.value.course_id,
                    rec_type: result.value.rec_type,
                    selected_courses: result.value.selected_courses,
                },
                function (response) {
                    location.reload();
                },
                function (error) {
                    console.log("Error: ", error);
                }
            );
        }
    });
});

jQuery(document).on("click", ".remove-course", function () {
    var recdCourseId = jQuery(this).closest(".course-blob").data("recd-course-id");
    var courseId = jQuery(this).closest(".course-recs").data("course-id");
    var recType = jQuery(this).closest(".course-recs").data("rec-type");
    
    Swal.fire({
        title: "Are you sure?",
        text: "Do you really want to remove this course?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, remove it!",
        cancelButtonText: "No, cancel!",
    }).then((result) => {
        if (result.isConfirmed) {
            // AJAX call to remove the course
            idemailwiz_do_ajax(
                "id_remove_course_from_rec",
                idAjax_id_general.nonce,
                {
                    course_id: courseId,
                    rec_type: recType,
                    recd_course_id: recdCourseId,
                },
                function (response) {
                    location.reload();
                },
                function (error) {
                    console.log("Error: ", error);
                }
            );
        }
    });
});

jQuery(document).ready(function() {
    // Initialize select2 for dropdown menus
    jQuery('#division-select').select2({
        width: '100%',
        dropdownAutoWidth: true,
        closeOnSelect: false,
        placeholder: "Select divisions",
        allowClear: true
    });
});

jQuery(document).on("click", "#clear-non-current-mappings", function () {
    Swal.fire({
        title: "Clear Non-Current FY Mappings?",
        text: "This will remove all course recommendations that are not offered in the current fiscal year. This action cannot be undone.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, clear them!",
        cancelButtonText: "Cancel",
        confirmButtonColor: "#d33",
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: "Processing...",
                text: "Clearing non-current fiscal year mappings...",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // AJAX call to clear non-current FY mappings
            idemailwiz_do_ajax(
                "id_clear_non_current_fy_mappings",
                idAjax_id_general.nonce,
                {},
                function (response) {
                    Swal.fire({
                        title: "Success!",
                        text: response.message || "Non-current FY mappings cleared successfully",
                        icon: "success",
                        confirmButtonText: "OK"
                    }).then(() => {
                        location.reload();
                    });
                },
                function (error) {
                    Swal.fire({
                        title: "Error",
                        text: "Failed to clear mappings: " + (error.message || "Unknown error"),
                        icon: "error",
                        confirmButtonText: "OK"
                    });
                    console.log("Error: ", error);
                }
            );
        }
    });
});

// CSV Upload Modal Handling
jQuery(document).on("click", "#upload-csv-mappings", function () {
    jQuery('#csv-upload-modal').fadeIn(200);
    jQuery('#upload-results').hide();
    jQuery('#csv-upload-form').show();
    jQuery('#upload-progress').hide();
    jQuery('#csv-file').val('');
    jQuery('#clear-existing').prop('checked', false);
});

jQuery(document).on("click", ".wiz-modal-close, .cancel-upload", function () {
    jQuery('#csv-upload-modal').fadeOut(200);
});

jQuery(document).on("submit", "#csv-upload-form", function (e) {
    e.preventDefault();
    
    const fileInput = jQuery('#csv-file')[0];
    const clearExisting = jQuery('#clear-existing').is(':checked');
    
    if (!fileInput.files || !fileInput.files[0]) {
        Swal.fire({
            icon: 'error',
            title: 'No File Selected',
            text: 'Please select a CSV file to upload.'
        });
        return;
    }
    
    const file = fileInput.files[0];
    
    // Check file type
    if (!file.name.toLowerCase().endsWith('.csv')) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid File Type',
            text: 'Please upload a CSV file (.csv extension).'
        });
        return;
    }
    
    // Show progress
    jQuery('#upload-progress').show();
    jQuery('#upload-progress-bar').css('width', '0%').text('0%');
    jQuery('#upload-status').text('Reading CSV file...');
    jQuery('button[type="submit"]', this).prop('disabled', true);
    
    // Read and parse CSV file
    const reader = new FileReader();
    
    reader.onload = function(e) {
        const csvContent = e.target.result;
        
        // Update progress
        jQuery('#upload-progress-bar').css('width', '30%').text('30%');
        jQuery('#upload-status').text('Parsing CSV data...');
        
        // Parse CSV
        const lines = csvContent.split('\n');
        const headers = lines[0].split(',').map(h => h.trim().replace(/"/g, ''));
        
        // Find column indices
        const columnMap = {
            'Last Course Shortcode': headers.indexOf('Last Course Shortcode'),
            'Rec 1 Shortcode': headers.indexOf('Rec 1 Shortcode'),
            'Rec 2 Shortcode': headers.indexOf('Rec 2 Shortcode'),
            'Rec 3 Shortcode': headers.indexOf('Rec 3 Shortcode'),
            'Rec Age Up 1 Shortcode': headers.indexOf('Rec Age Up 1 Shortcode'),
            'Rec Age Up 2 Shortcode': headers.indexOf('Rec Age Up 2 Shortcode'),
            'Rec Age Up 3 Shortcode': headers.indexOf('Rec Age Up 3 Shortcode'),
            'IDTA Rec Age Up 1 Shortcode': headers.indexOf('IDTA Rec Age Up 1 Shortcode'),
            'VTC Rec 1 Shortcode': headers.indexOf('VTC Rec 1 Shortcode'),
            'VTC Rec 2 Shortcode': headers.indexOf('VTC Rec 2 Shortcode'),
            'VTC Rec 3 Shortcode': headers.indexOf('VTC Rec 3 Shortcode'),
            'VTC Rec Age Up 1 Shortcode': headers.indexOf('VTC Rec Age Up 1 Shortcode'),
            'VTC Rec Age Up 2 Shortcode': headers.indexOf('VTC Rec Age Up 2 Shortcode'),
            'VTC Rec Age Up 3 Shortcode': headers.indexOf('VTC Rec Age Up 3 Shortcode')
        };
        
        // Parse data rows
        const mappings = [];
        for (let i = 1; i < lines.length; i++) {
            if (!lines[i].trim()) continue;
            
            const values = lines[i].split(',').map(v => v.trim().replace(/"/g, ''));
            const courseAbbr = values[columnMap['Last Course Shortcode']];
            
            if (!courseAbbr) continue;
            
            const mapping = {
                course_abbreviation: courseAbbr,
                idtc: [],
                idtc_ageup: [],
                idta: [],
                vtc: [],
                vtc_ageup: []
            };
            
            // iDTC recs
            ['Rec 1 Shortcode', 'Rec 2 Shortcode', 'Rec 3 Shortcode'].forEach(col => {
                const val = values[columnMap[col]];
                if (val) mapping.idtc.push(val);
            });
            
            // iDTC age-up recs
            ['Rec Age Up 1 Shortcode', 'Rec Age Up 2 Shortcode', 'Rec Age Up 3 Shortcode'].forEach(col => {
                const val = values[columnMap[col]];
                if (val) mapping.idtc_ageup.push(val);
            });
            
            // iDTA rec
            const idtaVal = values[columnMap['IDTA Rec Age Up 1 Shortcode']];
            if (idtaVal) mapping.idta.push(idtaVal);
            
            // VTC recs
            ['VTC Rec 1 Shortcode', 'VTC Rec 2 Shortcode', 'VTC Rec 3 Shortcode'].forEach(col => {
                const val = values[columnMap[col]];
                if (val) mapping.vtc.push(val);
            });
            
            // VTC age-up recs
            ['VTC Rec Age Up 1 Shortcode', 'VTC Rec Age Up 2 Shortcode', 'VTC Rec Age Up 3 Shortcode'].forEach(col => {
                const val = values[columnMap[col]];
                if (val) mapping.vtc_ageup.push(val);
            });
            
            mappings.push(mapping);
        }
        
        // Update progress
        jQuery('#upload-progress-bar').css('width', '50%').text('50%');
        jQuery('#upload-status').text('Uploading to server...');
        
        // Send to server
        idemailwiz_do_ajax(
            "id_import_csv_mappings",
            idAjax_id_general.nonce,
            {
                mappings: JSON.stringify(mappings),
                clear_existing: clearExisting ? '1' : '0'
            },
            function (response) {
                // Update progress to complete
                jQuery('#upload-progress-bar').css('width', '100%').text('100%');
                jQuery('#upload-status').text('Import complete!');
                
                // Hide form, show results
                setTimeout(function() {
                    jQuery('#csv-upload-form').hide();
                    jQuery('#upload-results').show();
                    
                    // Display results
                    const summary = `
                        <strong>Import Summary:</strong><br>
                        Courses processed: ${response.total_processed || 0}<br>
                        Mappings created: ${response.mappings_created || 0}<br>
                        Errors: ${response.errors || 0}
                    `;
                    jQuery('#results-summary').html(summary);
                    
                    if (response.details && response.details.length > 0) {
                        let detailsHtml = '<ul style="list-style: none; padding: 0;">';
                        response.details.forEach(detail => {
                            const icon = detail.success ? '✓' : '✗';
                            const color = detail.success ? '#46b450' : '#dc3232';
                            detailsHtml += `<li style="color: ${color}; margin: 5px 0;">${icon} ${detail.message}</li>`;
                        });
                        detailsHtml += '</ul>';
                        jQuery('#results-details').html(detailsHtml);
                    }
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Import Complete!',
                        text: `Processed ${response.total_processed || 0} courses with ${response.mappings_created || 0} mappings created.`,
                        confirmButtonText: 'Reload Page'
                    }).then(() => {
                        location.reload();
                    });
                }, 500);
            },
            function (error) {
                jQuery('#upload-progress').hide();
                jQuery('button[type="submit"]').prop('disabled', false);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Import Failed',
                    text: error.message || 'An error occurred during import.'
                });
            }
        );
    };
    
    reader.onerror = function() {
        jQuery('#upload-progress').hide();
        jQuery('button[type="submit"]').prop('disabled', false);
        
        Swal.fire({
            icon: 'error',
            title: 'File Read Error',
            text: 'Failed to read the CSV file. Please try again.'
        });
    };
    
    reader.readAsText(file);
});

