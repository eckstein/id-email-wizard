jQuery(document).ready(function ($) {
    console.log('Endpoints.js loaded');
    
    // Check if our elements exist
    console.log('Test user select exists:', $('.test-user-select').length);
    console.log('Test user select HTML:', $('.test-user-select').parent().html());
    
    // Add endpoint modal
    $('.add-endpoint').on('click', function() {
        Swal.fire({
            title: 'Add New Endpoint',
            customClass: {
                popup: 'endpoint-form-popup'
            },
            html: `
                <div class="endpoint-form">
                    <div class="endpoint-field">
                        <label>Route:</label>
                        <div class="endpoint-route-input">
                            <span>idemailwiz/v1/</span>
                            <input id="endpoint-route" type="text" placeholder="Enter endpoint route">
                        </div>
                        <div class="route-validation-message"></div>
                    </div>
                    <div class="endpoint-field">
                        <label>Name:</label>
                        <input id="endpoint-name" type="text" placeholder="Enter endpoint name">
                    </div>
                    <div class="endpoint-field">
                        <label>Description:</label>
                        <textarea id="endpoint-description" placeholder="Enter endpoint description"></textarea>
                    </div>
                    <div class="endpoint-field">
                        <label>Configuration (JSON):</label>
                        <textarea id="endpoint-config" placeholder="Enter endpoint configuration"></textarea>
                    </div>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            preConfirm: () => {
                const route = document.getElementById('endpoint-route').value;
                const name = document.getElementById('endpoint-name').value;
                const description = document.getElementById('endpoint-description').value;
                let config = document.getElementById('endpoint-config').value;

                // Route validation
                if (!route) {
                    Swal.showValidationMessage('Please enter an endpoint route');
                    return false;
                }

                // Validate route format
                const routeRegex = /^[a-zA-Z0-9_\-\/]+$/;
                if (!routeRegex.test(route)) {
                    Swal.showValidationMessage('Route can only contain letters, numbers, underscores, hyphens, and forward slashes');
                    return false;
                }

                // Additional route validation rules
                if (route.startsWith('/') || route.endsWith('/')) {
                    Swal.showValidationMessage('Route should not start or end with a forward slash');
                    return false;
                }

                if (route.includes('//')) {
                    Swal.showValidationMessage('Route should not contain consecutive forward slashes');
                    return false;
                }

                // JSON validation
                try {
                    config = config ? JSON.parse(config) : {};
                } catch (e) {
                    Swal.showValidationMessage('Invalid JSON in configuration');
                    return false;
                }

                return { route, name, description, config };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // AJAX call to create endpoint
                $.ajax({
                    url: idAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'idwiz_create_endpoint',
                        endpoint: result.value.route,
                        name: result.value.name,
                        description: result.value.description,
                        config: JSON.stringify(result.value.config),
                        security: idAjax.wizAjaxNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Endpoint created successfully.',
                                icon: 'success',
                                customClass: {
                                    popup: 'endpoint-form-popup'
                                }
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: response.data,
                                icon: 'error',
                                customClass: {
                                    popup: 'endpoint-form-popup'
                                }
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Error',
                            text: 'An error occurred while creating the endpoint.',
                            icon: 'error',
                            customClass: {
                                popup: 'endpoint-form-popup'
                            }
                        });
                    }
                });
            }
        });
    });

    // Remove endpoint
    $('.remove-endpoint').on('click', function() {
        const endpoint = $(this).data('endpoint');
        Swal.fire({
            title: 'Remove Endpoint',
            text: 'Are you sure you want to remove this endpoint?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, remove',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: idAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'idwiz_remove_endpoint',
                        endpoint: endpoint,
                        security: idAjax.wizAjaxNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success!', 'Endpoint removed successfully.', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.data, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'An error occurred while removing the endpoint.', 'error');
                    }
                });
            }
        });
    });

    // Save endpoint changes
    $('.save-endpoint').on('click', function() {
        const endpoint = $(this).data('endpoint');
        const container = $(`#endpoint-${endpoint}`);
        
        // Collect data mapping
        const dataMapping = {};
        container.find('.data-mapping-item').each(function() {
            const key = $(this).find('.mapping-key').val();
            const type = $(this).find('.mapping-type').val();
            const value = type === 'static' 
                ? $(this).find('.mapping-value').val()
                : $(this).find('.mapping-preset').val();
            
            if (key && value) {
                dataMapping[key] = {
                    type: type,
                    value: value
                };
            }
        });

        const data = {
            action: 'idwiz_update_endpoint',
            security: idAjax_wiz_endpoints.nonce,
            endpoint: endpoint,
            data: JSON.stringify({
                name: container.find('.endpoint-name').val(),
                description: container.find('.endpoint-description').val(),
                config: JSON.parse(container.find('.endpoint-config').val()),
                base_data_source: container.find('.endpoint-base-data-source').val(),
                data_mapping: dataMapping
            })
        };

        // Validate configuration JSON
        try {
            JSON.parse(container.find('.endpoint-config').val());
        } catch (e) {
            alert('Invalid JSON in configuration field');
            return;
        }

        $.ajax({
            url: idAjax_wiz_endpoints.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    alert('Endpoint updated successfully');
                } else {
                    alert('Error updating endpoint: ' + response.data);
                }
            },
            error: function() {
                alert('Error communicating with server');
            }
        });
    });

    // Handle settings toggle
    $(document).on('click', '.toggle-settings', function() {
        $(this).closest('.endpoint-details').find('.basic-settings').slideToggle();
        $(this).toggleClass('active');
    });

    // Handle tab switching
    $('.endpoint-tab').on('click', function() {
        const endpoint = $(this).data('endpoint');
        
        // Update URL without reloading
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('endpoint', endpoint);
        window.history.pushState({}, '', newUrl);

        // Update UI
        $('.endpoint-tab').removeClass('active');
        $('.endpoint-content').hide().removeClass('active');
        $(this).addClass('active');
        $(`#endpoint-${endpoint}`).show().addClass('active');
    });

    // Show initial endpoint content
    function showInitialEndpoint() {
        // Try to get endpoint from URL
        const urlParams = new URLSearchParams(window.location.search);
        const urlEndpoint = urlParams.get('endpoint');
        
        let activeTab;
        if (urlEndpoint) {
            activeTab = $(`.endpoint-tab[data-endpoint="${urlEndpoint}"]`);
        }
        
        // If no endpoint in URL or endpoint not found, use first tab
        if (!activeTab || !activeTab.length) {
            activeTab = $('.endpoint-tab').first();
        }
        
        if (activeTab.length) {
            activeTab.click();
        }
    }

    // Call on page load
    showInitialEndpoint();

    // Data Mapping UI Handlers
    $(document).on('click', '.add-mapping', function(e) {
        e.preventDefault();
        const mappingItem = $(`
            <div class="data-mapping-item">
                <input type="text" class="mapping-key wiz-input" placeholder="Key">
                <select class="mapping-type wiz-select">
                    <option value="static">Static Value</option>
                    <option value="preset">Preset Function</option>
                </select>
                <input type="text" class="mapping-value wiz-input" placeholder="Value">
                <button class="remove-mapping" title="Remove Mapping">×</button>
            </div>
        `);
        $(this).before(mappingItem);
    });

    $(document).on('click', '.remove-mapping', function() {
        $(this).closest('.data-mapping-item').remove();
    });

    $(document).on('change', '.mapping-type', function() {
        const valueContainer = $(this).next();
        if (this.value === 'static') {
            valueContainer.replaceWith('<input type="text" class="mapping-value wiz-input" placeholder="Value">');
        } else {
            valueContainer.replaceWith(`
                <select class="mapping-preset wiz-select">
                    <option value="">Select Preset</option>
                    <option value="most_recent_purchase">Most Recent Purchase Date</option>
                </select>
            `);
        }
    });

    // Handle user selection and preview
    console.log('Setting up endpoint preview handlers');
    
    // Toggle manual user ID input
    $(document).on('click', '.toggle-user-input', function(e) {
        e.preventDefault();
        $('.user-id-input').slideToggle();
        $(this).text($(this).text() === 'Enter Student Account Number manually' ? 'Select from dropdown' : 'Enter Student Account Number manually');
    });

    // Handle user selection change
    $(document).on('change', '.test-user-select', function() {
        console.log('User selected from dropdown');
        const accountNumber = $(this).val();
        console.log('Account number:', accountNumber);
        if (accountNumber) {
            // Also update the manual input field to keep them in sync
            $('.manual-user-id').val(accountNumber);
            loadUserDataAndUpdatePreview(accountNumber);
        }
    });

    // Handle manual user ID load
    $(document).on('click', '.load-user-data', function() {
        console.log('Manual load clicked');
        const accountNumber = $('.manual-user-id').val();
        console.log('Manual account number:', accountNumber);
        if (accountNumber) {
            // Also update the dropdown to keep them in sync
            $('.test-user-select').val(accountNumber);
            loadUserDataAndUpdatePreview(accountNumber);
        }
    });

    // Handle refresh preview button
    $(document).on('click', '.refresh-preview', function() {
        console.log('Refresh clicked');
        const accountNumber = $('.test-user-select').val() || $('.manual-user-id').val();
        console.log('Refresh account number:', accountNumber);
        if (accountNumber) {
            loadUserDataAndUpdatePreview(accountNumber);
        }
    });

    function loadUserDataAndUpdatePreview(accountNumber) {
        console.log('Loading user data for account:', accountNumber);
        const $activeContainer = $('.endpoint-content.active');
        const $preview = $activeContainer.find('.payload-preview');
        const endpoint = $activeContainer.attr('id').replace('endpoint-', '');
        
        console.log('Endpoint:', endpoint);
        
        // If there's a CodeMirror instance, update its content
        const editor = $preview.data('codemirror');
        if (editor) {
            editor.setValue('Loading...');
        } else {
            $preview.html('Loading...');
        }
        
        // Get user data
        $.ajax({
            url: idAjax_wiz_endpoints.ajaxurl,
            type: 'POST',
            data: {
                action: 'idwiz_get_user_data',
                account_number: accountNumber,
                security: idAjax_wiz_endpoints.nonce
            },
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    generatePreview(response.data, endpoint, $activeContainer);
                } else {
                    if (editor) {
                        editor.setValue('Error loading user data: ' + response.data);
                    } else {
                        $preview.html('Error loading user data: ' + response.data);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                if (editor) {
                    editor.setValue('Error loading user data');
                } else {
                    $preview.html('Error loading user data');
                }
            }
        });
    }

    function generatePreview(userData, endpoint, $container) {
        const $preview = $container.find('.payload-preview');
        const baseDataSource = $container.find('.endpoint-base-data-source').val();
        const mappings = [];
        
        // Collect all mappings
        $container.find('.data-mapping-item').each(function() {
            const $item = $(this);
            const key = $item.find('.mapping-key').val();
            const type = $item.find('.mapping-type').val();
            let value;
            
            if (type === 'static') {
                value = $item.find('.mapping-value').val();
            } else {
                value = $item.find('.mapping-preset').val();
            }
            
            if (key) { // Only require key to be present
                mappings.push({
                    key: key,
                    type: type,
                    value: value || '' // Include empty values
                });
            }
        });

        // Generate preview payload
        const payload = {
            endpoint: endpoint,
            data: {}
        };

        // First, include the base data if using user_feed
        if (baseDataSource === 'user_feed') {
            const cleanUserData = { ...userData };
            delete cleanUserData._presets;
            Object.assign(payload.data, cleanUserData);
        }

        // Then apply any custom mappings, which will override base data if there are conflicts
        mappings.forEach(mapping => {
            if (!mapping.key) return; // Skip if no key defined
            
            if (mapping.type === 'static') {
                payload.data[mapping.key] = mapping.value;
            } else if (mapping.type === 'preset' && userData._presets) {
                // Get the actual value from the preset
                payload.data[mapping.key] = userData._presets[mapping.value] || `[Preset not found: ${mapping.value}]`;
            }
        });

        // Format JSON with proper indentation
        const formattedJson = JSON.stringify(payload, null, 2);

        // Get existing CodeMirror instance or create new one
        let editor = $preview.data('codemirror');
        
        if (!editor) {
            // First time initialization
            editor = CodeMirror(function(elt) {
                $preview.empty().append(elt);
            }, {
                value: formattedJson,
                mode: 'application/json',
                theme: 'mbo',
                lineNumbers: true,
                readOnly: true,
                matchBrackets: true,
                autoCloseBrackets: true,
                foldGutter: true,
                gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
                lint: true,
                viewportMargin: Infinity
            });
            $preview.data('codemirror', editor);
        } else {
            // Update existing CodeMirror instance
            editor.setValue(formattedJson);
            editor.refresh();
        }
    }

    // Handle endpoint testing
    $(document).on('click', '.test-endpoint', function(e) {
        e.preventDefault();
        const accountNumber = $('.test-user-select').val() || $('.manual-user-id').val();
        
        if (!accountNumber) {
            alert('Please select a test user first');
            return;
        }

        const baseUrl = $(this).data('url');
        const token = $(this).data('token');
        const testUrl = `${baseUrl}?account_number=${accountNumber}`;

        // First test the endpoint via AJAX
        $.ajax({
            url: testUrl,
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            success: function(response) {
                // Create a new window/tab
                const newWindow = window.open('', '_blank');
                
                // Create a pretty HTML display of the JSON response
                const html = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Endpoint Test Result</title>
                        <style>
                            body { font-family: monospace; padding: 20px; background: #f5f5f5; }
                            pre { background: white; padding: 15px; border-radius: 5px; overflow-x: auto; }
                            .endpoint-info { margin-bottom: 20px; }
                            .endpoint-url { color: #0066cc; }
                            .auth-token { color: #666; }
                        </style>
                    </head>
                    <body>
                        <div class="endpoint-info">
                            <div>Endpoint URL: <span class="endpoint-url">${testUrl}</span></div>
                            <div>Authorization: <span class="auth-token">Bearer ${token}</span></div>
                        </div>
                        <pre>${JSON.stringify(response, null, 2)}</pre>
                    </body>
                    </html>
                `;
                
                newWindow.document.write(html);
                newWindow.document.close();
            },
            error: function(xhr, status, error) {
                // Show more detailed error information
                let errorMessage = error;
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.error) {
                        errorMessage = errorResponse.error;
                    } else if (errorResponse.message) {
                        errorMessage = errorResponse.message;
                    }
                } catch (e) {
                    // If we can't parse the error response, use the original error
                }
                alert('Error testing endpoint: ' + errorMessage);
            }
        });
    });
});