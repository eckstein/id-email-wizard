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
jQuery(document).on("click", "#upload-csv-mappings", function (e) {
    e.preventDefault();
    console.log('Upload button clicked'); // Debug
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
    
    var fileInput = jQuery('#csv-file')[0];
    var clearExisting = jQuery('#clear-existing').is(':checked');
    
    if (!fileInput.files || !fileInput.files[0]) {
        Swal.fire({
            icon: 'error',
            title: 'No File Selected',
            text: 'Please select a CSV file to upload.'
        });
        return;
    }
    
    var file = fileInput.files[0];
    
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
    var reader = new FileReader();
    
    reader.onload = function(e) {
        var csvContent = e.target.result;
        
        // Update progress
        jQuery('#upload-progress-bar').css('width', '30%').text('30%');
        jQuery('#upload-status').text('Parsing CSV data...');
        
        // Parse CSV - use fixed column positions (skip row 1, start from row 2)
        var lines = csvContent.split('\n');
        var mappings = [];
        
        // Column positions (0-indexed):
        // 0: Last Course Shortcode
        // 2, 4, 6: iDTC Rec 1/2/3 Shortcode
        // 8, 10, 12: iDTC Age Up 1/2/3 Shortcode
        // 14: IDTA Rec Age Up 1 Shortcode
        // 16, 18, 20: VTC Rec 1/2/3 Shortcode
        // 22, 24, 26: VTC Age Up 1/2/3 Shortcode
        
        // Start from line 1 (index 1, skipping header row 0)
        for (var i = 1; i < lines.length; i++) {
            var line = lines[i].trim();
            if (!line) continue;
            
            // Split by comma and clean values
            var values = line.split(',').map(function(v) { 
                return v.trim().replace(/^["']|["']$/g, ''); 
            });
            
            var courseAbbr = values[0];
            if (!courseAbbr) continue;
            
            var mapping = {
                course_abbreviation: courseAbbr,
                idtc: [],
                idtc_ageup: [],
                idta: [],
                vtc: [],
                vtc_ageup: []
            };
            
            // iDTC recs (columns 2, 4, 6)
            if (values[2]) mapping.idtc.push(values[2]);
            if (values[4]) mapping.idtc.push(values[4]);
            if (values[6]) mapping.idtc.push(values[6]);
            
            // iDTC age-up recs (columns 8, 10, 12)
            if (values[8]) mapping.idtc_ageup.push(values[8]);
            if (values[10]) mapping.idtc_ageup.push(values[10]);
            if (values[12]) mapping.idtc_ageup.push(values[12]);
            
            // iDTA rec (column 14)
            if (values[14]) mapping.idta.push(values[14]);
            
            // VTC recs (columns 16, 18, 20)
            if (values[16]) mapping.vtc.push(values[16]);
            if (values[18]) mapping.vtc.push(values[18]);
            if (values[20]) mapping.vtc.push(values[20]);
            
            // VTC age-up recs (columns 22, 24, 26)
            if (values[22]) mapping.vtc_ageup.push(values[22]);
            if (values[24]) mapping.vtc_ageup.push(values[24]);
            if (values[26]) mapping.vtc_ageup.push(values[26]);
            
            mappings.push(mapping);
            
            // Log progress every 10 rows
            if (i % 10 === 0) {
                console.log('Parsed ' + i + ' rows...');
            }
        }
        
        console.log('Total mappings parsed: ' + mappings.length);
        console.log('Sample mapping:', mappings[0]);
        
        // Update progress
        jQuery('#upload-progress-bar').css('width', '50%').text('50%');
        jQuery('#upload-status').text('Uploading to server...');
        
        // Send to server
        console.log('Sending ' + mappings.length + ' mappings to server...');
        idemailwiz_do_ajax(
            "id_import_csv_mappings",
            idAjax_id_general.nonce,
            {
                mappings: JSON.stringify(mappings),
                clear_existing: clearExisting ? '1' : '0'
            },
            function (response) {
                console.log('Server response:', response);
                
                // Handle both direct response and nested data structure
                var data = response.data || response;
                
                // Update progress to complete
                jQuery('#upload-progress-bar').css('width', '100%').text('100%');
                jQuery('#upload-status').text('Import complete!');
                
                // Hide form, show results
                setTimeout(function() {
                    jQuery('#csv-upload-form').hide();
                    jQuery('#upload-results').show();
                    
                    // Display results
                    var summary = '<strong>Import Summary:</strong><br>' +
                        'Courses processed: ' + (data.total_processed || 0) + '<br>' +
                        'Mappings created: ' + (data.mappings_created || 0) + '<br>' +
                        'Errors: ' + (data.errors || 0);
                    jQuery('#results-summary').html(summary);
                    
                    if (data.details && data.details.length > 0) {
                        var detailsHtml = '<ul style="list-style: none; padding: 0;">';
                        data.details.forEach(function(detail) {
                            var icon = detail.success ? '✓' : '✗';
                            var color = detail.success ? '#46b450' : '#dc3232';
                            detailsHtml += '<li style="color: ' + color + '; margin: 5px 0;">' + icon + ' ' + detail.message + '</li>';
                        });
                        detailsHtml += '</ul>';
                        jQuery('#results-details').html(detailsHtml);
                    }
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Import Complete!',
                        text: 'Processed ' + (data.total_processed || 0) + ' courses with ' + (data.mappings_created || 0) + ' mappings created.',
                        confirmButtonText: 'Reload Page'
                    }).then(function() {
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

