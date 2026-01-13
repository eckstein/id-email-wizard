jQuery(document).ready(function ($) {
    // Only run on endpoint pages
    if (!$('.endpoint-content').length) {
        return;
    }
    
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
        const $activeContent = $(`#endpoint-${endpoint}`);
        $activeContent.show().addClass('active');
        
        // Check if we need to update the test user dropdown
        const baseDataSource = $activeContent.find('.endpoint-base-data-source').val();
        const $testUserSelect = $activeContent.find('.test-user-select');
        const currentType = $testUserSelect.data('type');
        
        // If dropdown type doesn't match the data source, reload options
        if (baseDataSource === 'parent' && currentType !== 'parent') {
            loadParentAccountOptions($testUserSelect);
        } else if (baseDataSource === 'student' && currentType !== 'student') {
            loadStudentAccountOptions($testUserSelect);
        }
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

    // Cache for presets (keyed by data source)
    let presetsCache = {};

    // Function to load presets for a specific data source
    // PHP is the single source of truth for which presets are compatible with which data sources
    function loadPresets(dataSource = 'student') {
        return new Promise((resolve, reject) => {
            // Check cache for this data source
            if (presetsCache[dataSource]) {
                resolve(presetsCache[dataSource]);
                return;
            }

            $.ajax({
                url: idAjax_wiz_endpoints.ajaxurl,
                type: 'POST',
                data: {
                    action: 'idwiz_get_available_presets',
                    security: idAjax_wiz_endpoints.nonce,
                    data_source: dataSource
                },
                success: function(response) {
                    if (response.success) {
                        presetsCache[dataSource] = response.data;
                        resolve(presetsCache[dataSource]);
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
            
            // Get base data source - PHP handles filtering
            const baseDataSource = $(this).closest('.endpoint-content').find('.endpoint-base-data-source').val();
            
            loadPresets(baseDataSource)
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
    
    // Toggle manual user ID input
    $(document).on('click', '.toggle-user-input', function(e) {
        e.preventDefault();
        const $container = $(this).closest('.endpoint-content');
        const $userIdInput = $container.find('.user-id-input');
        const baseDataSource = $container.find('.endpoint-base-data-source').val();
        const accountType = baseDataSource === 'parent' ? 'Parent Account Number' : 'Student Account Number';
        
        // Check visibility BEFORE toggling (since slideToggle is async)
        const wasHidden = !$userIdInput.is(':visible');
        
        $userIdInput.slideToggle();
        
        // If it was hidden, it will now be visible (showing manual input)
        // If it was visible, it will now be hidden (showing dropdown)
        $(this).text(wasHidden ? 'Select from dropdown' : 'Enter ' + accountType + ' manually');
    });

    // Handle refresh preview button
    $(document).on('click', '.refresh-preview', function(e) {
        e.preventDefault();
        
        const $container = $(this).closest('.endpoint-content');
        const baseDataSource = $container.find('.endpoint-base-data-source').val();
        
        if (baseDataSource === 'location') {
            // Handle location data source
            const locationId = $container.find('.manual-location-id').val();
            
            if (!locationId) {
                alert('Please enter a Location ID first');
                return;
            }
            
            // Clear cache for this location
            for (const key in userDataCache.data) {
                if (key.includes('location_' + locationId)) {
                    delete userDataCache.data[key];
                    delete userDataCache.timestamps[key];
                }
            }
            
            loadLocationDataAndUpdatePreview(locationId);
        } else if (baseDataSource === 'quiz_url') {
            // Handle quiz URL data source
            const quizUrl = $container.find('.manual-quiz-url').val();
            
            if (!quizUrl) {
                alert('Please paste a Quiz URL first');
                return;
            }
            
            // Clear cache for this quiz URL
            for (const key in userDataCache.data) {
                if (key.includes('quiz_url_')) {
                    delete userDataCache.data[key];
                    delete userDataCache.timestamps[key];
                }
            }
            
            loadQuizUrlDataAndUpdatePreview(quizUrl);
        } else {
            // Handle account-based data sources
            const dropdownVal = $container.find('.test-user-select').val();
            const manualVal = $container.find('.manual-user-id').val();
            const accountNumber = dropdownVal || manualVal;
            
            if (!accountNumber) {
                alert('Please select or enter a test user first');
                return;
            }
            
            // Clear all caches that might contain this account
            for (const key in userDataCache.data) {
                if (key.includes(accountNumber)) {
                    delete userDataCache.data[key];
                    delete userDataCache.timestamps[key];
                }
            }
            
            loadUserDataAndUpdatePreview(accountNumber);
        }
    });

    function loadUserDataAndUpdatePreview(accountNumber) {
        const $activeContainer = $('.endpoint-content.active');
        const $preview = $activeContainer.find('.payload-preview');
        const endpoint = $activeContainer.attr('id').replace('endpoint-', '');
        const baseDataSource = $activeContainer.find('.endpoint-base-data-source').val();
        
        // Collect all mappings for the server as an object
        const dataMappings = {}; // Initialize as an object
        $activeContainer.find('.data-mapping-item').each(function() {
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
                dataMappings[key] = { // Assign as object property
                    type: type,
                    value: value || '' // Include empty values
                };
            }
        });
        
        // Check cache first
        const cacheKey = baseDataSource + '_' + accountNumber + '_' + JSON.stringify(dataMappings); // Include mappings in cache key
        const now = Date.now();
        if (userDataCache.data[cacheKey] && 
            (now - userDataCache.timestamps[cacheKey]) < userDataCache.timeout) {
            handlePayloadData(userDataCache.data[cacheKey], $activeContainer);
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
                base_data_source: baseDataSource,
                endpoint: endpoint,
                data_mapping: JSON.stringify(dataMappings),
                security: idAjax_wiz_endpoints.nonce
            },
            success: function(response) {
                // Check if response is HTML (error page) instead of JSON
                if (typeof response === 'string' && response.trim().startsWith('<')) {
                    console.error('Received HTML response instead of JSON. Likely PHP error:', response);
                    if (editor) {
                        editor.setValue('PHP Error: Server returned HTML instead of JSON. Check console for details.');
                    } else {
                        $preview.html('PHP Error: Server returned HTML instead of JSON. Check console for details.');
                    }
                    return;
                }
                
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
                    
                    // Cache the response
                    userDataCache.data[cacheKey] = response.data;
                    userDataCache.timestamps[cacheKey] = now;
                    
                    // Display the payload
                    handlePayloadData(response.data, $activeContainer);
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
                console.error('Response Text:', xhr.responseText);
                
                let errorMessage = error;
                // Try to extract meaningful error from response if possible
                if (xhr.responseText) {
                    if (xhr.responseText.includes('Fatal error') || xhr.responseText.includes('Parse error')) {
                        const errorMatch = xhr.responseText.match(/(Fatal error|Parse error).*?<\/b>:(.*?)(<br|<\/p>)/s);
                        if (errorMatch && errorMatch[2]) {
                            errorMessage = errorMatch[0];
                        }
                    }
                }
                
                if (editor) {
                    editor.setValue('Error loading user data: ' + errorMessage);
                } else {
                    $preview.html('Error loading user data: ' + errorMessage);
                }
            },
            dataType: 'json',
            timeout: 30000 // 30 second timeout
        });
    }
    
    // Function to handle payload data display
    function handlePayloadData(payloadData, $container) {
        const $preview = $container.find('.payload-preview');
        
        // Format JSON with proper indentation
        const formattedJson = JSON.stringify(payloadData, null, 2);
        
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

    // Function to load location data and update preview
    function loadLocationDataAndUpdatePreview(locationId) {
        const $activeContainer = $('.endpoint-content.active');
        const $preview = $activeContainer.find('.payload-preview');
        const endpoint = $activeContainer.attr('id').replace('endpoint-', '');
        const baseDataSource = $activeContainer.find('.endpoint-base-data-source').val();
        
        // Collect all mappings for the server as an object
        const dataMappings = {};
        $activeContainer.find('.data-mapping-item').each(function() {
            const $item = $(this);
            const key = $item.find('.mapping-key').val();
            const type = $item.find('.mapping-type').val();
            let value;
            
            if (type === 'static') {
                value = $item.find('.mapping-value').val();
            } else {
                value = $item.find('.mapping-preset').val();
            }
            
            if (key) {
                dataMappings[key] = {
                    type: type,
                    value: value || ''
                };
            }
        });
        
        // Check cache first
        const cacheKey = 'location_' + locationId + '_' + JSON.stringify(dataMappings);
        const now = Date.now();
        if (userDataCache.data[cacheKey] && 
            (now - userDataCache.timestamps[cacheKey]) < userDataCache.timeout) {
            handlePayloadData(userDataCache.data[cacheKey], $activeContainer);
            return;
        }

        // If there's a CodeMirror instance, update its content
        const editor = $preview.data('codemirror');
        if (editor) {
            editor.setValue('Loading...');
        } else {
            $preview.html('Loading...');
        }
        
        // Get location data
        $.ajax({
            url: idAjax_wiz_endpoints.ajaxurl,
            type: 'POST',
            data: {
                action: 'idwiz_get_location_data',
                location_id: locationId,
                base_data_source: baseDataSource,
                endpoint: endpoint,
                data_mapping: JSON.stringify(dataMappings),
                security: idAjax_wiz_endpoints.nonce
            },
            success: function(response) {
                if (typeof response === 'string' && response.trim().startsWith('<')) {
                    console.error('Received HTML response instead of JSON:', response);
                    if (editor) {
                        editor.setValue('PHP Error: Server returned HTML instead of JSON.');
                    } else {
                        $preview.html('PHP Error: Server returned HTML instead of JSON.');
                    }
                    return;
                }
                
                if (response.success) {
                    if (!response.data) {
                        console.error('Response missing data property:', response);
                        if (editor) {
                            editor.setValue('Error: Invalid response format');
                        } else {
                            $preview.html('Error: Invalid response format');
                        }
                        return;
                    }
                    
                    // Cache the response
                    userDataCache.data[cacheKey] = response.data;
                    userDataCache.timestamps[cacheKey] = now;
                    
                    // Display the payload
                    handlePayloadData(response.data, $activeContainer);
                } else {
                    const errorMsg = response.data || 'Unknown error';
                    if (editor) {
                        editor.setValue('Error loading location data: ' + errorMsg);
                    } else {
                        $preview.html('Error loading location data: ' + errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                if (editor) {
                    editor.setValue('Error loading location data: ' + error);
                } else {
                    $preview.html('Error loading location data: ' + error);
                }
            },
            dataType: 'json',
            timeout: 30000
        });
    }

    // Function to load quiz URL data and update preview
    function loadQuizUrlDataAndUpdatePreview(quizUrl) {
        const $activeContainer = $('.endpoint-content.active');
        const $preview = $activeContainer.find('.payload-preview');
        const endpoint = $activeContainer.attr('id').replace('endpoint-', '');
        const baseDataSource = $activeContainer.find('.endpoint-base-data-source').val();
        
        // Collect all mappings for the server as an object
        const dataMappings = {};
        $activeContainer.find('.data-mapping-item').each(function() {
            const $item = $(this);
            const key = $item.find('.mapping-key').val();
            const type = $item.find('.mapping-type').val();
            let value;
            
            if (type === 'static') {
                value = $item.find('.mapping-value').val();
            } else {
                value = $item.find('.mapping-preset').val();
            }
            
            if (key) {
                dataMappings[key] = {
                    type: type,
                    value: value || ''
                };
            }
        });
        
        // Check cache first
        const cacheKey = 'quiz_url_' + quizUrl + '_' + JSON.stringify(dataMappings);
        const now = Date.now();
        if (userDataCache.data[cacheKey] && 
            (now - userDataCache.timestamps[cacheKey]) < userDataCache.timeout) {
            handlePayloadData(userDataCache.data[cacheKey], $activeContainer);
            return;
        }

        // If there's a CodeMirror instance, update its content
        const editor = $preview.data('codemirror');
        if (editor) {
            editor.setValue('Loading...');
        } else {
            $preview.html('Loading...');
        }
        
        // Get quiz URL data
        $.ajax({
            url: idAjax_wiz_endpoints.ajaxurl,
            type: 'POST',
            data: {
                action: 'idwiz_get_quiz_url_data',
                quiz_url: quizUrl,
                base_data_source: baseDataSource,
                endpoint: endpoint,
                data_mapping: JSON.stringify(dataMappings),
                security: idAjax_wiz_endpoints.nonce
            },
            success: function(response) {
                if (typeof response === 'string' && response.trim().startsWith('<')) {
                    console.error('Received HTML response instead of JSON:', response);
                    if (editor) {
                        editor.setValue('PHP Error: Server returned HTML instead of JSON.');
                    } else {
                        $preview.html('PHP Error: Server returned HTML instead of JSON.');
                    }
                    return;
                }
                
                if (response.success) {
                    if (!response.data) {
                        console.error('Response missing data property:', response);
                        if (editor) {
                            editor.setValue('Error: Invalid response format');
                        } else {
                            $preview.html('Error: Invalid response format');
                        }
                        return;
                    }
                    
                    // Cache the response
                    userDataCache.data[cacheKey] = response.data;
                    userDataCache.timestamps[cacheKey] = now;
                    
                    // Display the payload
                    handlePayloadData(response.data, $activeContainer);
                } else {
                    const errorMsg = response.data || 'Unknown error';
                    if (editor) {
                        editor.setValue('Error loading quiz URL data: ' + errorMsg);
                    } else {
                        $preview.html('Error loading quiz URL data: ' + errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                if (editor) {
                    editor.setValue('Error loading quiz URL data: ' + error);
                } else {
                    $preview.html('Error loading quiz URL data: ' + error);
                }
            },
            dataType: 'json',
            timeout: 30000
        });
    }

    // Debounce the preview updates
    const debouncedLoadUserData = debounce(loadUserDataAndUpdatePreview, 300);
    const debouncedLoadLocationData = debounce(loadLocationDataAndUpdatePreview, 300);
    const debouncedLoadQuizUrlData = debounce(loadQuizUrlDataAndUpdatePreview, 300);

    // Update handlers to use debounced function
    $(document).on('change', '.test-user-select', function() {
        const accountNumber = $(this).val();
        if (accountNumber) {
            // Scope to the current endpoint container
            const $container = $(this).closest('.endpoint-content');
            $container.find('.manual-user-id').val(accountNumber);
            debouncedLoadUserData(accountNumber);
        }
    });

    $(document).on('click', '.load-user-data', function() {
        // Find the active container first
        const $activeContainer = $('.endpoint-content.active');
        // Get the manual input value from within the active container
        const accountNumber = $activeContainer.find('.manual-user-id').val();
        if (accountNumber) {
            // Also update the dropdown to reflect the manually entered value, if it exists as an option
            const $select = $activeContainer.find('.test-user-select');
            if ($select.find(`option[value="${accountNumber}"]`).length > 0) {
                $select.val(accountNumber);
            } else {
                // Optionally, clear the select or add a temporary option
                // $select.val('');
            }
            debouncedLoadUserData(accountNumber);
        } else {
            alert('Please enter an account number.');
        }
    });

    // Handle location data load button
    $(document).on('click', '.load-location-data', function() {
        const $activeContainer = $('.endpoint-content.active');
        const locationId = $activeContainer.find('.manual-location-id').val();
        if (locationId) {
            loadLocationDataAndUpdatePreview(locationId);
        } else {
            alert('Please enter a Location ID.');
        }
    });

    // Handle quiz URL data load button
    $(document).on('click', '.load-quiz-url-data', function() {
        const $activeContainer = $('.endpoint-content.active');
        const quizUrl = $activeContainer.find('.manual-quiz-url').val();
        if (quizUrl) {
            loadQuizUrlDataAndUpdatePreview(quizUrl);
        } else {
            alert('Please paste a Quiz URL.');
        }
    });

    // Handle base data source change
    $(document).on('change', '.endpoint-base-data-source', function() {
        const baseDataSource = $(this).val();
        const $container = $(this).closest('.endpoint-content');
        const $testUserContainer = $container.find('.test-user-selector');
        
        // Clear existing test input UI and rebuild based on data source
        if (baseDataSource === 'location') {
            // Show location ID input
            $testUserContainer.html(`
                <label class="test-user-label">Test Location:</label>
                <div class="location-id-input">
                    <input type="text" class="manual-location-id wiz-input" placeholder="Enter Location ID">
                    <button class="load-location-data wiz-button">Load Location</button>
                </div>
            `);
        } else if (baseDataSource === 'quiz_url') {
            // Show quiz URL input
            $testUserContainer.html(`
                <label class="test-user-label">Test Quiz URL:</label>
                <div class="quiz-url-input">
                    <input type="text" class="manual-quiz-url wiz-input" placeholder="Paste quiz result URL (e.g., https://www.idtech.com/courses?interests=295003,295001&age=10&format=405000)">
                    <button class="load-quiz-url-data wiz-button">Load Quiz URL</button>
                </div>
            `);
        } else {
            // Show account number inputs (student or parent)
            const accountType = baseDataSource === 'parent' ? 'Parent Account Number' : 'Student Account Number';
            const labelText = baseDataSource === 'parent' ? 'Test Parent Account:' : 'Test User:';
            const selectType = baseDataSource === 'parent' ? 'parent' : 'student';
            
            $testUserContainer.html(`
                <label class="test-user-label">${labelText}</label>
                <select class="test-user-select wiz-select" data-type="${selectType}">
                    <option value="">Loading...</option>
                </select>
                <div class="user-id-input" style="display: none;">
                    <label>Or enter ${accountType}:</label>
                    <input type="text" class="manual-user-id wiz-input" placeholder="Enter ${accountType}">
                    <button class="load-user-data wiz-button">Load User</button>
                </div>
                <a href="#" class="toggle-user-input">Enter ${accountType} manually</a>
            `);
            
            // Load appropriate options
            const $testUserSelect = $testUserContainer.find('.test-user-select');
            if (baseDataSource === 'parent') {
                loadParentAccountOptions($testUserSelect);
            } else {
                loadStudentAccountOptions($testUserSelect);
            }
        }
        
        // Update preset dropdowns based on new data source
        updatePresetDropdowns($container, baseDataSource);
        
        // Clear any existing preview data
        const $preview = $container.find('.payload-preview');
        const editor = $preview.data('codemirror');
        let promptText = 'Select a test user to preview the payload';
        if (baseDataSource === 'location') {
            promptText = 'Enter a Location ID to preview the payload';
        } else if (baseDataSource === 'quiz_url') {
            promptText = 'Paste a Quiz URL to preview the payload';
        }
        if (editor) {
            editor.setValue(promptText);
        } else {
            $preview.html(promptText);
        }
        
        // Clear the manual input field
        $container.find('.manual-user-id').val('');
    });
    
    // Function to update preset dropdowns based on data source
    function updatePresetDropdowns($container, baseDataSource) {
        // Find all preset dropdowns in the container
        $container.find('.mapping-type').each(function() {
            const $mappingType = $(this);
            if ($mappingType.val() === 'preset') {
                const $presetDropdown = $mappingType.next('.mapping-preset');
                if ($presetDropdown.length) {
                    // Save the currently selected value if possible
                    const currentValue = $presetDropdown.val();
                    
                    // Replace with loading indicator
                    $presetDropdown.html('<option>Loading presets...</option>');
                    
                    // Load presets filtered by data source (PHP handles filtering)
                    loadPresets(baseDataSource)
                        .then(presets => {
                            $presetDropdown.html(generatePresetOptionsHtml(presets));
                            
                            // Try to restore previously selected value if it's compatible
                            if (currentValue && presets[currentValue]) {
                                $presetDropdown.val(currentValue);
                            }
                        })
                        .catch(error => {
                            console.error('Error updating presets for data source change:', error);
                            $presetDropdown.html('<option value="">Error loading presets</option>');
                        });
                }
            }
        });
    }
    
    // Function to load parent account options via AJAX
    function loadParentAccountOptions($select) {
        $select.html('<option value="">Loading parent accounts...</option>');
        
        $.ajax({
            url: idAjax_wiz_endpoints.ajaxurl,
            type: 'POST',
            data: {
                action: 'idwiz_get_test_accounts',
                type: 'parent',
                security: idAjax_wiz_endpoints.nonce
            },
            success: function(response) {
                if (response.success) {
                    $select.html(response.data);
                    $select.data('type', 'parent');
                } else {
                    $select.html('<option value="">Error loading accounts</option>');
                }
            },
            error: function() {
                $select.html('<option value="">Error loading accounts</option>');
            }
        });
    }
    
    // Function to load student account options via AJAX
    function loadStudentAccountOptions($select) {
        $select.html('<option value="">Loading student accounts...</option>');
        
        $.ajax({
            url: idAjax_wiz_endpoints.ajaxurl,
            type: 'POST',
            data: {
                action: 'idwiz_get_test_accounts',
                type: 'student',
                security: idAjax_wiz_endpoints.nonce
            },
            success: function(response) {
                if (response.success) {
                    $select.html(response.data);
                    $select.data('type', 'student');
                } else {
                    $select.html('<option value="">Error loading accounts</option>');
                }
            },
            error: function() {
                $select.html('<option value="">Error loading accounts</option>');
            }
        });
    }

    // Handle endpoint testing
    $(document).on('click', '.test-endpoint', function(e) {
        e.preventDefault();
        const $container = $(this).closest('.endpoint-content');
        const baseDataSource = $container.find('.endpoint-base-data-source').val();
        
        let testUrl;
        const baseUrl = $(this).data('url');
        const token = $(this).data('token');
        
        if (baseDataSource === 'location') {
            const locationId = $container.find('.manual-location-id').val();
            if (!locationId) {
                alert('Please enter a Location ID first');
                return;
            }
            testUrl = `${baseUrl}?location_id=${locationId}`;
        } else {
            const dropdownVal = $container.find('.test-user-select').val();
            const manualVal = $container.find('.manual-user-id').val();
            const accountNumber = dropdownVal || manualVal;
            
            if (!accountNumber) {
                alert('Please select or enter a test user first');
                return;
            }
            testUrl = `${baseUrl}?account_number=${accountNumber}`;
        }

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
        const baseDataSource = $container.find('.endpoint-base-data-source').val() || 'student';
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

        // If we have the presets cached for this data source, use them immediately
        if (presetsCache[baseDataSource]) {
            renderPresetDefinitions($definitionsContent, usedPresets, baseDataSource);
        } else {
            // Otherwise load them first
            loadPresets(baseDataSource).then(() => {
                renderPresetDefinitions($definitionsContent, usedPresets, baseDataSource);
            });
        }
    }

    // Function to render preset definitions
    function renderPresetDefinitions($container, usedPresets, dataSource) {
        if (usedPresets.size === 0) {
            $container.html('<p class="no-presets-message">No presets currently in use. Add a preset mapping above to see its definition here.</p>');
            return;
        }

        const presets = presetsCache[dataSource] || {};
        let html = '<dl class="preset-list">';
        for (const presetKey of usedPresets) {
            const preset = presets[presetKey];
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