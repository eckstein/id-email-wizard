jQuery(document).ready(function ($) {
    $('.add-endpoint').on('click', function() {
        Swal.fire({
            title: 'Add New Endpoint',
            html:
                '<p>Endpoint URL: idemailwiz/v1/<input id="endpoint-input" class="swal2-input" placeholder="Enter endpoint"></p>',
            focusConfirm: false,
            preConfirm: () => {
                const endpoint = document.getElementById('endpoint-input').value;
                if (!endpoint) {
                    Swal.showValidationMessage('Please enter an endpoint');
                    return false;
                }
                if (!/^[a-zA-Z0-9_\-\/]+$/.test(endpoint)) {
                    Swal.showValidationMessage('Endpoint can only contain letters, numbers, underscores, hyphens, and forward slashes');
                    return false;
                }
                return endpoint;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // AJAX call to check if endpoint exists and create it if it doesn't
                $.ajax({
                    url: idAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'idwiz_create_endpoint',
                        endpoint: result.value,
                        security: idAjax_id_general.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success!', 'Endpoint created successfully.', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.data, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'An error occurred while creating the endpoint.', 'error');
                    }
                });
            }
        });
    });

    $('.remove-endpoint').on('click', function() {
        // Get endpoint from data
        const endpoint = $(this).data('route');
        Swal.fire({
            title: 'Remove Endpoint',
            text: 'Are you sure you want to remove this endpoint?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, remove',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // AJAX call to remove the endpoint
                $.ajax({
                    url: idAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'idwiz_remove_endpoint',
                        endpoint: endpoint,
                        security: idAjax_id_general.nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            Swal.fire('Success!', 'Endpoint removed successfully.', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.data, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'An error occurred while removing the endpoint.', 'error');
                    }
                });
            }
        });

    });
});