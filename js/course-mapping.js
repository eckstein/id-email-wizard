jQuery(document).on("click", ".add-course", function () {
    var courseId = jQuery(this).closest(".course-recs").data("course-id");
    var recType = jQuery(this).closest(".course-recs").data("rec-type");
    var division = jQuery(this).closest(".course-recs").data("division");

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
                                division: division,
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

jQuery('#division-select').select2();
jQuery('#fy-select').select2();

