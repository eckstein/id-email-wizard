jQuery(document).ready(function ($) {
    console.log('Endpoints.js loaded');
    
    // Check if our elements exist
    console.log('Test user select exists:', $('.test-user-select').length);
    console.log('Test user select HTML:', $('.test-user-select').parent().html());
    
    // Add cache object at the top
    const userDataCache = {
        data: {},
        timeout: 5 * 60 * 1000, // 5 minutes cache timeout
        timestamps: {}
    };

    // Debounce function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

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
                    url: idAjax_wiz_endpoints.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'idwiz_create_endpoint',
                        endpoint: result.value.route,
                        name: result.value.name,
                        description: result.value.description,
                        config: JSON.stringify(result.value.config),
                        security: idAjax_wiz_endpoints.nonce
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
                    url: idAjax_wiz_endpoints.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'idwiz_remove_endpoint',
                        endpoint: endpoint,
                        security: idAjax_wiz_endpoints.nonce
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

    // Function to generate preset options HTML
    function generatePresetOptionsHtml(presets) {
        let html = '<option value="">Select Preset</option>';
        let currentGroup = null;
        
        // Sort presets by group then name
        const sortedPresets = Object.entries(presets).sort((a, b) => {
            const groupCompare = (a[1].group || '').localeCompare(b[1].group || '');
            if (groupCompare !== 0) return groupCompare;
            return a[1].name.localeCompare(b[1].name);
        });
        
        for (const [value, preset] of sortedPresets) {
            if (preset.group !== currentGroup) {
                if (currentGroup !== null) {
                    html += '</optgroup>';
                }
                if (preset.group) {
                    html += `<optgroup label="${preset.group}">`;
                }
                currentGroup = preset.group;
            }
            html += `<option value="${value}">${preset.name}</option>`;
        }
        
        if (currentGroup !== null) {
            html += '</optgroup>';
        }
        
        return html;
    }

    // Cache for presets
    let presetsCache = null;

    // Function to load presets
    function loadPresets() {
        return new Promise((resolve, reject) => {
            if (presetsCache) {
                resolve(presetsCache);
                return;
            }

            $.ajax({
                url: idAjax_wiz_endpoints.ajaxurl,
                type: 'POST',
                data: {
                    action: 'idwiz_get_available_presets',
                    security: idAjax_wiz_endpoints.nonce
                },
                success: function(response) {
                    if (response.success) {
                        presetsCache = response.data;
                        resolve(presetsCache);
                    } else {
                        reject('Failed to load presets');
                    }
                },
                error: function() {
                    reject('Error loading presets');
                }
            });
        });
    }

    $(document).on('change', '.mapping-type', function() {
        const valueContainer = $(this).next();
        const type = $(this).val();
        if (type === 'static') {
            valueContainer.replaceWith('<input type="text" class="mapping-value wiz-input" placeholder="Value">');
        } else {
            const loadingSelect = $('<select class="mapping-preset wiz-select"><option>Loading presets...</option></select>');
            valueContainer.replaceWith(loadingSelect);
            
            loadPresets()
                .then(presets => {
                    loadingSelect.html(generatePresetOptionsHtml(presets));
                })
                .catch(error => {
                    console.error('Error loading presets:', error);
                    loadingSelect.html('<option value="">Error loading presets</option>');
                });
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

    // Handle refresh preview button
    $(document).on('click', '.refresh-preview', function(e) {
        e.preventDefault();
        console.log('Refresh preview clicked');
        
        // Get the current account number
        const accountNumber = $('.test-user-select').val() || $('.manual-user-id').val();
        
        if (!accountNumber) {
            alert('Please select a test user first');
            return;
        }
        
        // Clear cache for this account to force a fresh load
        const cacheKey = accountNumber;
        if (userDataCache.data[cacheKey]) {
            delete userDataCache.data[cacheKey];
            delete userDataCache.timestamps[cacheKey];
        }
        
        // Reload the data
        loadUserDataAndUpdatePreview(accountNumber);
    });

    function loadUserDataAndUpdatePreview(accountNumber) {
        console.log('Loading user data for account:', accountNumber);
        const $activeContainer = $('.endpoint-content.active');
        const $preview = $activeContainer.find('.payload-preview');
        const endpoint = $activeContainer.attr('id').replace('endpoint-', '');
        
        // Check cache first
        const cacheKey = accountNumber;
        const now = Date.now();
        if (userDataCache.data[cacheKey] && 
            (now - userDataCache.timestamps[cacheKey]) < userDataCache.timeout) {
            console.log('Using cached data');
            generatePreview(userDataCache.data[cacheKey], endpoint, $activeContainer);
            return;
        }

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
                console.log('AJAX response received');
                if (response.success) {
                    // Validate that the response contains the expected data structure
                    if (!response.data) {
                        console.error('Response missing data property:', response);
                        if (editor) {
                            editor.setValue('Error: Invalid response format');
                        } else {
                            $preview.html('Error: Invalid response format');
                        }
                        return;
                    }
                    
                    // Ensure _presets exists
                    if (!response.data._presets) {
                        console.warn('Response missing _presets property, creating empty object');
                        response.data._presets = {};
                    }
                    
                    // Log presets for debugging
                    console.log('Presets in response:', response.data._presets);
                    
                    // Cache the response
                    userDataCache.data[cacheKey] = response.data;
                    userDataCache.timestamps[cacheKey] = now;
                    
                    // Generate the preview
                    generatePreview(response.data, endpoint, $activeContainer);
                } else {
                    const errorMsg = response.data || 'Unknown error';
                    console.error('Error in AJAX response:', errorMsg);
                    if (editor) {
                        editor.setValue('Error loading user data: ' + errorMsg);
                    } else {
                        $preview.html('Error loading user data: ' + errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                if (editor) {
                    editor.setValue('Error loading user data: ' + error);
                } else {
                    $preview.html('Error loading user data: ' + error);
                }
            }
        });
    }

    // Debounce the preview updates
    const debouncedLoadUserData = debounce(loadUserDataAndUpdatePreview, 300);

    // Update handlers to use debounced function
    $(document).on('change', '.test-user-select', function() {
        console.log('User selected from dropdown');
        const accountNumber = $(this).val();
        console.log('Account number:', accountNumber);
        if (accountNumber) {
            $('.manual-user-id').val(accountNumber);
            debouncedLoadUserData(accountNumber);
        }
    });

    $(document).on('click', '.load-user-data', function() {
        console.log('Manual load clicked');
        const accountNumber = $('.manual-user-id').val();
        console.log('Manual account number:', accountNumber);
        if (accountNumber) {
            $('.test-user-select').val(accountNumber);
            debouncedLoadUserData(accountNumber);
        }
    });

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
            // Make a deep copy without the _presets property
            if (cleanUserData._presets) {
                delete cleanUserData._presets;
            }
            Object.assign(payload.data, cleanUserData);
        }

        // Then apply any custom mappings, which will override base data if there are conflicts
        mappings.forEach(mapping => {
            if (!mapping.key) return; // Skip if no key defined
            
            if (mapping.type === 'static') {
                payload.data[mapping.key] = mapping.value;
            } else if (mapping.type === 'preset' && userData._presets) {
                // Get the actual value from the preset
                const presetValue = userData._presets[mapping.value];
                
                // Only include the preset if it exists and is not null/undefined
                if (presetValue !== undefined && presetValue !== null) {
                    payload.data[mapping.key] = presetValue;
                } else {
                    // Return null instead of an error message when preset not found
                    payload.data[mapping.key] = null;
                }
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

    // Function to update preset definitions
    function updatePresetDefinitions($container) {
        const $definitionsContent = $container.find('.preset-definitions-content');
        const usedPresets = new Set();
        
        // Collect all used presets
        $container.find('.data-mapping-item').each(function() {
            const $item = $(this);
            if ($item.find('.mapping-type').val() === 'preset') {
                const presetValue = $item.find('.mapping-preset').val();
                if (presetValue) {
                    usedPresets.add(presetValue);
                }
            }
        });

        // If we have the presets cached, use them immediately
        if (presetsCache) {
            renderPresetDefinitions($definitionsContent, usedPresets);
        } else {
            // Otherwise load them first
            loadPresets().then(() => {
                renderPresetDefinitions($definitionsContent, usedPresets);
            });
        }
    }

    // Function to render preset definitions
    function renderPresetDefinitions($container, usedPresets) {
        if (usedPresets.size === 0) {
            $container.html('<p class="no-presets-message">No presets currently in use. Add a preset mapping above to see its definition here.</p>');
            return;
        }

        let html = '<dl class="preset-list">';
        for (const presetKey of usedPresets) {
            const preset = presetsCache[presetKey];
            if (preset) {
                html += `<dt>${preset.name}</dt>`;
                html += `<dd>${preset.description}</dd>`;
            }
        }
        html += '</dl>';
        $container.html(html);
    }

    // Update handlers to trigger preset definitions update
    $(document).on('change', '.mapping-type, .mapping-preset', function() {
        const $container = $(this).closest('.endpoint-content');
        updatePresetDefinitions($container);
    });

    $(document).on('click', '.remove-mapping', function() {
        const $container = $(this).closest('.endpoint-content');
        // Use setTimeout to ensure the DOM is updated before we check for presets
        setTimeout(() => updatePresetDefinitions($container), 0);
    });

    // Update preset definitions when adding new mapping
    $(document).on('click', '.add-mapping', function() {
        const $container = $(this).closest('.endpoint-content');
        // Use setTimeout to ensure the DOM is updated before we check for presets
        setTimeout(() => updatePresetDefinitions($container), 0);
    });
});