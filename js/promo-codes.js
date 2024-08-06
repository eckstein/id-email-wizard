jQuery(document).ready(function($) {

    // Promo code archive table

    let table = $('#idemailwiz_promo_codes_table').DataTable({
        ajax: {
            url: idAjax.ajaxurl,
            type: 'POST',
            data: function(d) {
                d.action = 'get_promo_code_data';
                d.security = idAjax_promo_codes.nonce;
            },
            dataSrc: function(json) {
                return json.data.data;
            }
        },
        dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>rtp',
        language: {
            search: "",
            searchPlaceholder: "Quick search",
        },
        columns: [
            { data: 'code' },
            {
                data: 'name',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return '<a href="' + row.permalink + '">' + data + '</a>';
                    }
                    return data;
                }
            },
            { 
                data: 'idtc_discount',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return '$' + parseFloat(data).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                    }
                    return data;
                },
                type: 'num'
            },
            {
             data: 'start_date',
                render: function(data, type, row) {
                    if (type === 'display' && data) {
                        var date = new Date(data);
                        return (date.getUTCMonth() + 1).toString().padStart(2, '0') + '/' + 
                               date.getUTCDate().toString().padStart(2, '0') + '/' + 
                               date.getUTCFullYear();
                    }
                    return data;
                }
            },
            { 
                data: 'end_date',
                render: function(data, type, row) {
                    if (type === 'display' && data) {
                        var date = new Date(data);
                        return (date.getUTCMonth() + 1).toString().padStart(2, '0') + '/' + 
                               date.getUTCDate().toString().padStart(2, '0') + '/' + 
                               date.getUTCFullYear();
                    }
                    return data;
                }
            },
            { 
                data: 'last_used',
                render: function(data, type, row) {
                    if (type === 'display' && data) {
                        var date = new Date(data);
                        return (date.getUTCMonth() + 1).toString().padStart(2, '0') + '/' + 
                               date.getUTCDate().toString().padStart(2, '0') + '/' + 
                               date.getUTCFullYear();
                    }
                    return data;
                }
            },
            { data: 'cohort' },
            { 
                data: 'campaigns',
                type: 'num'
            },
            { 
                data: 'campaign_purchases',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return data.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                    }
                    return data;
                },
                type: 'num'
            },
            { 
                data: 'all_purchases',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return data.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                    }
                    return data;
                },
                type: 'num'
            },
            { 
                data: 'campaign_revenue',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return '$' + parseFloat(data).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                    }
                    return data;
                },
                type: 'num'
            },
            { 
                data: 'all_revenue',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return '$' + parseFloat(data).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                    }
                    return data;
                },
                type: 'num'
            },
            {
                data: null,
                render: function(data, type, row) {
                    let promoId = row.id;
                    return '<i class="fa-solid fa-trash delete-promo" data-promo-codeid="'+promoId+'"></i>';
                },
                orderable: false
            }
        ],
        rowId: 'id',
    });

    function savePromoCodeUpdate(promoId, updates) {
        idemailwiz_do_ajax(
            "idemailwiz_save_promo_code_update",
            idAjax_promo_codes.nonce,
            {
                promoId: promoId,
                updates: updates
            },
            function(response) {
                if (response.success) {
                if ($('#idemailwiz_promo_codes_table').length > 0) {
                    table.ajax.reload();
                } else {
                    location.reload();
                }
            } else {
                Swal.fire("Error", response.data.message, "error");
            }
            },
            function(error) {
                console.error(error);
                Swal.fire("Error", "Failed to update promo code.", "error");
            }
        );
    }

    // Function to edit promo code
    function editPromoCode(promoId) {
        // Fetch promo code data via AJAX
        idemailwiz_do_ajax(
            "get_single_promo_code_data_ajax",
            idAjax_promo_codes.nonce,
            { promo_id: promoId },
            function(response) {
                if (response.success) {
                    show_edit_promo_modal(response.data);
                } else {
                    Swal.fire("Error", "Failed to fetch promo code data.", "error");
                }
            },
            function(error) {
                console.error(error);
                Swal.fire("Error", "Failed to fetch promo code data.", "error");
            }
        );
    }

    // Function to show edit modal
    function show_edit_promo_modal(promoData) {
        Swal.fire({
            title: 'Edit Promo Code',
            showCancelButton: true,
            confirmButtonText: 'Save',
            html: 
            '<div class="form-group">' +
            '<label for="swal-input-name">Name:</label>' +
            '<input id="swal-input-name" class="swal2-input" value="' + promoData.name + '">' +
            '</div>' +
            '<div class="form-group">' +
            '<label for="swal-input-code">Code:</label>' +
            '<input id="swal-input-code" class="swal2-input" value="' + promoData.code + '">' +
            '</div>' +
            '<div class="form-group">' +
            '<label for="swal-input-idtc_discount">iDTC Discount:</label>' +
            '<input type="number" id="swal-input-idtc_discount" class="swal2-input" value="' + promoData.idtc_discount + '">' +
            '</div>' +
            '<div class="form-group">' +
            '<label for="swal-input-start_date">Start Date:</label>' +
            '<input id="swal-input-start_date" class="swal2-input datepicker" value="' + promoData.start_date + '">' +
            '</div>' +
            '<div class="form-group">' +
            '<label for="swal-input-end_date">End Date:</label>' +
            '<input id="swal-input-end_date" class="swal2-input datepicker" value="' + promoData.end_date + '">' +
            '</div>' +
            '<div class="form-group">' +
            '<label for="swal-input-cohort">Cohort:</label>' +
            '<input id="swal-input-cohort" class="swal2-input" value="' + promoData.cohort + '">' +
            '</div>',
            focusConfirm: false,
            didOpen: () => {
                // Initialize date pickers
                $('.datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
            },
            preConfirm: () => {
                return {
                    name: document.getElementById('swal-input-name').value,
                    code: document.getElementById('swal-input-code').value,
                    idtc_discount: document.getElementById('swal-input-idtc_discount').value,
                    start_date: document.getElementById('swal-input-start_date').value,
                    end_date: document.getElementById('swal-input-end_date').value,
                    cohort: document.getElementById('swal-input-cohort').value
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                savePromoCodeUpdate(promoData.id, result.value);
            }
        });
    }

    // Double-click event for editing
    $('#idemailwiz_promo_codes_table tbody').on('dblclick', 'tr', function() {
        let rowData = table.row(this).data();
        let promoId = rowData.id;
        editPromoCode(promoId);
    });

    $('.edit-single-promo-code').on('click', function() {
        let promoId = $(this).attr('data-promo-codeid');
        editPromoCode(promoId);
    });

    // New Promo Code button
    $('.new-promo-code').on('click', function() {
        Swal.fire({
            title: "Create New Promo Code",
            input: "text",
            inputPlaceholder: "Enter promo code...",
            showCancelButton: true,
            cancelButtonText: "Cancel",
            confirmButtonText: "Create",
        }).then((result) => {
            if (result.isConfirmed) {
                const code = result.value;

                $.ajax({
                    url: idAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'idemailwiz_create_new_promo_code',
                        security: idAjax_promo_codes.nonce,
                        newPromoCode: code
                    },
                    success: function(response) {
                        if (response.success) {
                            table.ajax.reload();
                            Swal.fire("Success", "Promo code created!", "success");
                        } else {
                            Swal.fire("Error", response.data.message, "error");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                        Swal.fire("Error", "An error occurred. Check the console for details.", "error");
                    }
                });
            }
        });
    });

    // Delete Promo Code
    $('#idemailwiz_promo_codes_table').on('click', '.delete-promo', function() {
        delete_promo_code(this);
    });

    $('.remove-single-promo-code').on('click', function() {
        delete_promo_code(this);
    });

    function delete_promo_code(clicked) {
        let promoId = $(clicked).data('promo-codeid');

        Swal.fire({
            title: "Delete This Promo Code?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, delete it",
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: idAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_promo_code',
                        security: idAjax_promo_codes.nonce,
                        promoId: promoId
                    },
                    success: function(response) {
                        if (response.success) {
                            
                            if (table.length) {
                                table.ajax.reload();
                            } else {
                                 let siteUrl = idAjax.site_url;
                                //redirect to /promo-codes archive
                                window.location.href = siteUrl + '/promo-codes'
                            }
                           

                        } else {
                            Swal.fire("Error", response.data.message, "error");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(error);
                        Swal.fire("Error", "An error occurred. Check the console for details.", "error");
                    }
                });
            }
        });
    }


    // Handle Remove Promo Code
    $(document).on('click', '.remove-promo-from-campaign', function(e) {
        e.preventDefault();
        var promoId = $(this).data('promoid');
        var campaignId = $(this).data('campaignid');

        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, remove it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    method: 'POST',
                    url: idAjax.ajaxurl,
                    data: {
                        action: 'remove_promo_code_from_campaign_ajax',
                        promo_id: promoId,
                        campaign_id: campaignId
                    },
                    success: function(response) {
                        location.reload();
                    }
                });
            } else {
                do_wiz_notif({message:'Error removing promo code', duration: 3000});
            }
        });
    });


    // Handle Add Promo Code
    $(document).on('click', '.add-promo-to-campaign', function(e) {
        e.preventDefault();
        var campaignId = $(this).data('campaignid');

        $.ajax({
            method: 'POST',
            url: idAjax.ajaxurl,
            data: {
                action: 'get_all_promo_codes'
            },
            success: function(response) {
                if (response.success) {
                    let promoCodes = response.data;
                    let promoOptions = promoCodes.map(promo => ({
                        id: promo.ID,
                        text: promo.post_title
                    }));

                    Swal.fire({
                        title: 'Select a promo code to add',
                        confirmButtonText: 'Add',
                        html: '<select id="swal-input-promo" class="swal2-input" style="width: 100%"></select>',
                        showCancelButton: true,
                        preConfirm: () => {
                            return $('#swal-input-promo').val();
                        },
                        didOpen: () => {
                            $('#swal-input-promo').select2({
                                data: promoOptions,
                                placeholder: 'Select a promo code',
                                width: '100%'
                            });
                        }
                    }).then((result) => {
                        if (result.value) {
                            $.ajax({
                                method: 'POST',
                                url: idAjax.ajaxurl,
                                data: {
                                    action: 'add_promo_code_to_campaign_ajax',
                                    promo_id: result.value,
                                    campaign_id: campaignId
                                },
                                success: function(response) {
                                    location.reload();
                                }
                            });
                        }
                    });
                }
            }
        });
    });


    // Add or remove promo_codes from one or more campaigns
    window.manageCampaignsInPromoCode = function (action, campaignIds, onSuccess = null, skipPromoCodeSelection = false, promo_codeId = null) {
        const performAction = (promo_codeId) => {
            // Perform AJAX call to manage campaigns in the selected promo_code
            idemailwiz_do_ajax(
                "idemailwiz_add_remove_campaign_from_promo_code",
                idAjax_promo_codes.nonce,
                {
                    promo_code_id: promo_codeId,
                    campaign_ids: campaignIds,
                    campaignAction: action,
                },
                function (response) {
                    var confirmMessage = "Promo code succesfully added to campaigns";
                    if (response.data.action == "remove") {
                        var confirmMessage = "Promo codes succesfully removed from campaigns!";
                    }

                    window.Swal.fire({
                        icon: "success",
                        title: "Success",
                        text: confirmMessage,
                    }).then(() => {
                        if (onSuccess) {
                            onSuccess();
                        }
                    });
                },
                function (errorData) {
                    // Handle error
                    console.error(`Failed to ${action} promo code from campaigns`, errorData);
                }
            );
        };

        // If skipping promo_code selection, perform the action immediately
        if (skipPromoCodeSelection) {
            performAction(promo_codeId);
            return;
        }

        // Determine the action and title based on the action parameter
        let titleText = action === "add" ? "Add to Promo Code" : "Remove from Promo Code";
        let confirmText = action === "add" ? "Add" : "Remove";

        const swalConfig = {
            title: titleText,
            html: '<select id="promo_code-select"></select><br/>- or -<br/><button id="create-new-promo_code" class="wiz-button green" type="button">Create New PromoCode</button>',
            showCancelButton: true,
            cancelButtonText: "Cancel",
            confirmButtonText: confirmText,
            preConfirm: () => {
                let selectedPromoCode = jQuery("#promo_code-select").val();
                performAction(selectedPromoCode);
            },
        };

        // Conditionally add the 'didOpen' callback if we need to select an promo_code
        if (!skipPromoCodeSelection) {
            swalConfig.didOpen = () => {
                jQuery("#promo_code-select").select2({
                    minimumInputLength: 0,
                    placeholder: "Search promo_codes...",
                    allowClear: true,
                    ajax: {
                        delay: 250,
                        transport: function (params, success, failure) {
                            idemailwiz_do_ajax(
                                "idemailwiz_get_promo_codes_for_select",
                                idAjax_promo_codes.nonce,
                                {
                                    q: params.data.term,
                                },
                                function (data) {
                                    success({ results: data });
                                },
                                function (error) {
                                    console.error("Failed to fetch promo_codes", error);
                                    failure();
                                }
                            );
                        },
                    },
                });

                jQuery("#create-new-promo_code").on("click", function () {
                    Swal.fire({
                        title: "Create New Promo Code",
                        input: "text",
                        inputPlaceholder: "Enter Promo Code...",
                        showCancelButton: true,
                        cancelButtonText: "Cancel",
                        confirmButtonText: "Create",
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const title = result.value;
                            idemailwiz_do_ajax(
                                "idemailwiz_create_new_promo_code",
                                idAjax_promo_codes.nonce,
                                { newPromoCode: title },
                                function (data) {
                                    console.log(data);
                                    // After creating the new promo_code, perform the initial action (add/remove campaigns)
                                    performAction(data.data.post_id);
                                },
                                function (error) {
                                    console.log(error);
                                    Swal.fire("Error", "An error occurred. Check the console for details.", "error");
                                }
                            );
                        }
                    });
                });
            };
        }

        window.Swal.fire(swalConfig);
    };



});