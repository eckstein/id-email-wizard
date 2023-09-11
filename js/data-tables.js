jQuery(document).ready(function ($) {    

    //Date sort plugin
    $.fn.dataTable.moment('x');

    //Only run when the table is on the page
    if ($('#idemailwiz_campaign_table').length) {
        

        idemailwiz_do_ajax('idwiz_get_campaign_table_view', idAjax_data_tables.nonce, {}, campaign_table_success_response, campaign_table_error_response);
    } // end if table exists

    function campaign_table_success_response(data) {

        // Initialize DataTables with the parsed data
        var table = $('#idemailwiz_campaign_table').DataTable({
            data: data,
            //serverSide: true,
            "order": [[ 3, 'asc' ], [ 2, 'desc' ]],
            "autoWidth" : false,
            fixedColumns: {
                left: 2
            },
            columns: [
                {
                    "className": 'row-counter',
                    "title": '#',
                    "name": 'row-counter',
                    "orderable": false,
                    "data": null,
                    "width": "20px",
                },
                {
                    "className": 'details-control customColName',
                    "title": '<i class="fa-solid fa-arrow-up-right-from-square"></i>',
                    "name": 'details-control',
                    "orderable": false,
                    "data": null,
                    "defaultContent": '<i class="fa-solid fa-circle-info"></i>',
                    "colvisName": 'Details'
                },
                {
                    "data": "campaign_start",
                    "name": "campaign_start",
                    "title": "Sent At",
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "date",
                    "render": function(data) {
                        return new Date(parseInt(data))
                            .toLocaleString('en-US', {
                            month: 'numeric',
                            day: 'numeric', 
                            year: 'numeric',
                            hour: 'numeric',
                            minute: 'numeric', 
                            hour12: true  
                            });
                        },
                    "type": "date",
                },
                { 
                    "data": "campaign_type",
                    "name": "campaign_type",
                    "title": "Type",
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "string",
                },
                { 
                    "data": "message_medium",
                    "name": "message_medium",
                    "title": "Medium",
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "string",
                },
                { 
                    "data": "campaign_name",
                    "name": "campaign_name",
                    "title": "Campaign Name",
                    "render": $.fn.dataTable.render.ellipsis(50, true),
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "string",
                },
                { 
                    "data": "campaign_labels",
                    "name": "campaign_labels",
                    "title": "Labels",
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "string",
                },
                {
                    "data": "experiment_ids",
                    "name": "experiment_ids",
                    "title": '<i class="fa fa-flask"></i>',
                    "searchBuilderTitle": 'Has Experiment',
                    "searchBuilderType": "string",
                    "searchBuilder.defaultConditions": "==",
                    "className": "idwiz_searchBuilder_enabled customColName",
                    "type": "bool",
                    "render": function(data, type) {
                        if (type === 'display') {
                            return data ? '<i class="fa fa-flask"></i>' : '';
                        }
                        if (type === 'filter') {
                            return !data ? 'True' : 'False';
                        }
                        return data;
                    },
                    "searchBuilder": {
                        "orthogonal": {
                            "search": "filter",
                            "display": "filter",
                        }
                    },
                    "colvisName": 'Has Experiment'
                },
                { 
                    "data": "unique_email_sends",
                    "name": "unique_email_sends",
                    "title": "Sends",
                    "type": "num",
                    "render": $.fn.dataTable.render.number(',', ''),
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "unique_delivered",
                    "name": "unique_delivered",
                    "title": "Delivered",
                    "type": "num",
                    "render": $.fn.dataTable.render.number(',', ''),
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "wiz_delivery_rate",
                    "name": "wiz_delivery_rate",
                    "title": "Deliv. Rate",
                    "render": function (data) { return parseFloat(data).toFixed(2) + '%'; },
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "unique_email_opens",
                    "name": "unique_email_opens",
                    "title": "Opens",
                    "render": $.fn.dataTable.render.number(',', ''),
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "wiz_open_rate",
                    "name": "wiz_open_rate",
                    "title": "Open Rate",
                    "render": function (data) { return parseFloat(data).toFixed(2) + '%'; },
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "unique_email_clicks",
                    "name": "unique_email_clicks",
                    "title": "Clicks",
                    "render": $.fn.dataTable.render.number(',', ''),
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "wiz_ctr",
                    "name": "wiz_ctr",
                    "title": "CTR",
                    "render": function (data) { return parseFloat(data).toFixed(2) + '%'; },
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "wiz_cto",
                    "name": "wiz_cto",
                    "title": "CTO",
                    "render": function (data) { return parseFloat(data).toFixed(2) + '%'; },
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "unique_unsubscribes",
                    "name": "unique_unsubscribes",
                    "title": "Unsubs.",
                    "render": function(data, type, row) {
                        return $.fn.dataTable.render.number(',', '').display(data);
                    },
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "wiz_unsub_rate",
                    "name": "wiz_unsub_rate",
                    "title": "Unsub. Rate",
                    "render": function (data) { return parseFloat(data).toFixed(2) + '%'; },
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "unique_purchases",
                    "name": "unique_purchases",
                    "title": "Purchases",
                    "render": function(data, type, row) {
                        return $.fn.dataTable.render.number(',', '').display(data);
                    },
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "wiz_cvr",
                    "name": "wiz_cvr",
                    "title": "CVR",
                    "render": function (data) { return parseFloat(data).toFixed(2) + '%'; },
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "revenue",
                    "name": "revenue",
                    "title": "Revenue",
                    "render": function (data) { return '$' + parseFloat(data).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num-fmt",
                },
                { 
                    "data": "template_subject",
                    "name": "template_subject",
                    "title": "Subject Line",
                    "render": $.fn.dataTable.render.ellipsis(40, true),
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "string",
                },
                { 
                    "data": "template_preheader",
                    "name": "template_preheader",
                    "title": "Pre Header",
                    "render": $.fn.dataTable.render.ellipsis(40, true),
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "string",
                },
                
                { 
                    "data": "campaign_id",
                    "name": "campaign_id",
                    "title": "ID",
                    "className": "idwiz_searchBuilder_enabled",
                    "searchBuilderType": "num",
                    "searchBuilder.defaultConditions": "==",
                },
                
            ],
            
            dom: '<"#wiztable_top_wrapper"><"wiztable_toolbar" <"#wiztable_top_search" f><"#wiztable_top_dates">  B>t',
            fixedHeader: {
                header: true,
                footer: false
            },
            colReorder: {
                realtime: false
            },
            scroller: true,
            scrollX: true,
            scrollY: '700px',
            paging: true,
            scrollResize: true,
            scrollCollapse: true,
            processing: true,
            select: {
                selector: "td:not(:first-child)",
                
            },
            buttons: [
                {
                    extend: 'searchBuilder',
                    background: false,
                    text: '<i class="fa-solid fa-sliders"></i>',
                    className: 'btn-advanced-search wiz-dt-button',
                    attr: {
                        'title': 'Advanced search and filter',
                    },
                    config: {
                        columns: '.idwiz_searchBuilder_enabled',
                    },
                    // Add a class to the popover for SearchBuilder so we can resize it with CSS
                    action: function (e, dt, node, config) {
                        this.popover(config._searchBuilder.getNode(), {
                            collectionLayout: 'wiz_sbpopover'
                        });
                        // Need to redraw the contents to calculate the correct positions for the elements
                        if (config._searchBuilder.s.topGroup !== undefined) {
                            config._searchBuilder.s.topGroup.dom.container.trigger('dtsb-redrawContents');
                        }
                        if (config._searchBuilder.s.topGroup.s.criteria.length === 0) {
                            $('.' + $.fn.dataTable.Group.classes.add).click();
                        }
                    },
                    

                },
                {
                    text: '<i class="fa-solid fa-angle-left"></i>',
                    className: 'wiz-dt-button skinny prev-month',
                    action: function ( e, dt, node, config ) {
                        var table = $('.idemailwiz_table').DataTable();
                        table.button('.next-month').enable();  // Enable the "Next" button

                        var startDateStr = $('#wiztable_startDate').val();
                        var startDate = startDateStr ? new Date(startDateStr) : new Date();
                        startDate.setUTCHours(0, 0, 0, 0);

                        var today = new Date();
                        today.setUTCHours(0, 0, 0, 0);

                        if (isNaN(startDate.getTime()) || !startDateStr) {
                            startDate = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 1));
                        }

                        startDate.setUTCMonth(startDate.getUTCMonth() - 1);
                        var endDate = new Date(Date.UTC(startDate.getUTCFullYear(), startDate.getUTCMonth() + 1, 0));

                        $('#wiztable_startDate').val(startDate.toISOString().split('T')[0]);
                        $('#wiztable_endDate').val(endDate.toISOString().split('T')[0]);
                        $('#saved_state_title').text(`Custom View: ${startDate.toLocaleString('default', { month: 'long', timeZone: 'UTC' })}, ${startDate.getUTCFullYear()}`);
                        table.draw();
                    }
                },
                {
                    text: function() {
                        var today = new Date();
                        var monthAbbr = today.toLocaleString('default', { month: 'short' });  // Get the three-letter abbreviation
                        var yearTwoDigit = today.getFullYear() % 100;  // Get the last two digits of the year
                        return `<i class="fa-regular fa-calendar"></i>&nbsp;&nbsp;${monthAbbr} '${yearTwoDigit}`;
                    },
                    action: function ( e, dt, node, config ) {
                        var today = new Date();
                        var firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                        var lastDayOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

                        $('#wiztable_startDate').val(firstDayOfMonth.toISOString().split('T')[0]);
                        $('#wiztable_endDate').val(lastDayOfMonth.toISOString().split('T')[0]);
                        $('#saved_state_title').text(`Custom View: ${today.toLocaleString('default', { month: 'long' })}, ${today.getFullYear()}`);
                        $('.idemailwiz_table').DataTable().draw();
                    },
                    className: 'wiz-dt-button current-month',
                },
                {
                    text: '<i class="fa-solid fa-angle-right"></i>',
                    className: 'wiz-dt-button skinny next-month',
                    action: function ( e, dt, node, config ) {
                        var table = $('.idemailwiz_table').DataTable();

                        var startDateStr = $('#wiztable_startDate').val();
                        var startDate = startDateStr ? new Date(startDateStr) : new Date();
                        startDate.setUTCHours(0, 0, 0, 0);

                        var today = new Date();
                        today.setUTCHours(0, 0, 0, 0);

                        if (isNaN(startDate.getTime()) || !startDateStr) {
                            startDate = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 1));
                            table.button('.next-month').disable();  // Disable the "Next" button when fields are empty or unorthodox
                            return;
                        }

                        if (startDate.getUTCFullYear() < today.getUTCFullYear() || (startDate.getUTCFullYear() === today.getUTCFullYear() && startDate.getUTCMonth() < today.getUTCMonth())) {
                            startDate.setUTCMonth(startDate.getUTCMonth() + 1);
                            var endDate = new Date(Date.UTC(startDate.getUTCFullYear(), startDate.getUTCMonth() + 1, 0));

                            $('#wiztable_startDate').val(startDate.toISOString().split('T')[0]);
                            $('#wiztable_endDate').val(endDate.toISOString().split('T')[0]);
                            $('#saved_state_title').text(`Custom View: ${startDate.toLocaleString('default', { month: 'long', timeZone: 'UTC' })}, ${startDate.getUTCFullYear()}`);
                            table.draw();
                        }

                        if (startDate.getUTCFullYear() === today.getUTCFullYear() && startDate.getUTCMonth() >= today.getUTCMonth()) {
                            table.button('.next-month').disable();  // Disable the "Next" button
                        } else {
                            table.button('.next-month').enable();  // Enable the "Next" button
                        }
                    }
                },
                
                {
                    text: function() {
                        var today = new Date();
                        var year = today.getFullYear();
        
                        // Fiscal year starts on November 1st of the previous year
                        if (today.getMonth() >= 10) {  // January is 0, November is 10
                            year += 1;
                        }
        
                        var yearTwoDigit = year % 100;
                        return `<i class="fa-regular fa-calendar"></i>&nbsp;&nbsp;FY '${yearTwoDigit}`;
                    },
                    action: function ( e, dt, node, config ) {
                        var today = new Date();
                        var fiscalYearStart = new Date(today.getFullYear(), 10, 1);  // Nov 1
                        var fiscalYearEnd = new Date(today.getFullYear() + 1, 9, 31);  // Oct 31
                        if (today < fiscalYearStart) {
                            fiscalYearStart.setFullYear(fiscalYearStart.getFullYear() - 1);
                            fiscalYearEnd.setFullYear(fiscalYearEnd.getFullYear() - 1);
                        }
                        $('#wiztable_startDate').val(fiscalYearStart.toISOString().split('T')[0]);
                        $('#wiztable_endDate').val(fiscalYearEnd.toISOString().split('T')[0]);
                        $('#saved_state_title').text(`Custom View: ${fiscalYearStart.getFullYear()} Fiscal Year`);
                        $('.idemailwiz_table').DataTable().draw();
                    },
                    className: 'wiz-dt-button fiscal-year',
                },

                {
                    extend: 'selected',
                    text: '<i class="fa-regular fa-plus"></i>',
                    name: 'Actions',
                    className: 'wiz-dt-button',
                    attr: {
                        'title': 'Actions',
                    },
                    action: function(e, dt, node, config) {
                    // Retrieve selected row indices
                    let selectedRowIndices = dt.rows({ selected: true }).indexes().toArray();

                    // Extract campaign IDs from selected rows
                    let selectedCampaignIds = selectedRowIndices.map(index => {
                        return dt.cell(index, 'campaign_id:name').data();
                    });
                    
                    Swal.fire({
                        title: 'Add to Initiative',
                        html: '<select id="initiative-select"></select>',
                        showCancelButton: true,
                        cancelButtonText: 'Cancel',
                        confirmButtonText: 'Add campaigns',  
                        preConfirm: () => {
                        let selectedInitiative = $('#initiative-select').val();
                            // Perform AJAX call to add campaigns to the selected initiative
                            idemailwiz_do_ajax(
                                'idemailwiz_add_remove_campaign_from_initiative',
                                idAjax_data_tables.nonce,
                                {
                                initiative_id: selectedInitiative,
                                campaign_ids: selectedCampaignIds,
                                campaignAction: 'add',
                                },
                                function(successData) {
                                console.log(successData);
                                alert('Campaigns have been added to initiative!');
                                },
                                function(errorData) {
                                // Handle error
                                console.error("Failed to add campaigns to initiative", errorData);
                                }
                            );
                        },
                        didOpen: () => {
                        $('#initiative-select').select2({
                            minimumInputLength: 0,
                            placeholder: "Search initiatives...",
                            allowClear: true,
                            ajax: {
                            delay: 250,
                            transport: function(params, success, failure) {
                                idemailwiz_do_ajax(
                                'idemailwiz_get_initiatives_for_select',
                                idAjax_data_tables.nonce, 
                                {
                                    q: params.data.term,
                                },
                                function(data) {
                                    success({results: data});
                                },
                                function(error) {
                                    console.error("Failed to fetch initiatives", error);
                                    failure();
                                }
                                );
                                 }
                             }
                             });
                            }
                        });
                    }
                },
                {
                    text: '<i class="fa-solid fa-rotate"></i>',
                    className: 'wiz-dt-button sync-db sync-everything',
                    attr:  {
                        "data-sync-db": "everything",
                        "title": "Sync Databases"
                    },
                    autoClose: true,
                    background: false,
                },
                {
                    extend: 'spacer',
                    style: 'bar'
                },                     
                {
                    extend: 'collection',
                    text: '<i class="fa-solid fa-table-columns"></i>',
                    className: 'wiz-dt-button',
                    attr: {
                        'title': 'Show/hide columns',
                    },
                    align: 'button-right',
                    buttons: [ 
                        {
                            extend: 'colvis',
                            columnText: function ( dt, idx, title ) {
                                if ( idx == dt.colReorder.transpose( 1 ) ) {
                                    return 'Info';
                                }
                                if ( idx == dt.colReorder.transpose( 7 ) ) {
                                    return 'Has Experiment';
                                } else {
                                    return title;
                                }
                            }
                        },
                        {
                            extend: 'colvisRestore',
                            text: 'Restore Defaults',
                            className: 'wizcols_restore',
                            align: 'button-right',
                        }
                    ],
                    background: false,
                },
                {
                    extend: 'collection',
                    text: '<i class="fa-regular fa-hand-pointer"></i>',
                    className: 'wiz-dt-button',
                    attr: {
                        'title': 'Selection mode',
                    },
                    align: 'button-right',
                    autoClose: true,
                    buttons: [ 
                        'selectNone',
                        'selectRows',
                        'selectColumns',
                        'selectCells',
                    ],
                    background: false,
                },
                {
                    extend: 'spacer',
                    style: 'bar'
                },
                
                {
                    extend: 'collection',
                    text: '<i class="fa-solid fa-floppy-disk"></i>',
                    className: 'wiz-dt-button saved-views',
                    attr: {
                    'title': 'View options',
                    },
                    align: 'button-right',
                    background: false,
                    buttons: [
                    {
                        extend: 'createState',
                        text: 'Create View',
                        attr: {
                        'title': 'Create View',
                        },
                        config: {    
                        creationModal: true,
                        }
                    },
                    {
                        extend: 'savedStates',
                        text: 'Saved Views',
                        className: 'saved-views',
                        background: false,
                        attr: {
                        'title': 'Saved Views',
                        },
                    }
                    ]
                },
                
                {
                    extend: 'collection',
                    text: '<i class="fa-solid fa-file-arrow-down"></i>',
                    className: 'wiz-dt-button',
                    attr: {
                        'title': 'Export current view',
                    },
                    align: 'button-right',
                    autoClose: true,
                    buttons: [ 
                        'copy',
                        'csv', 
                        'excel' ],
                    background: false,
                },
                

            ],
            

            stateRestore: {
                create: true, // Enable the creation of new states
                remove: true, // Enable the removal of states
                rename: true, // Enable the renaming of states
                save: true, // Enable the saving of states
            }, 
            "stateLoaded": function (settings, data) {
                if (currentStateName) {
                    $('#saved_state_title').text('Saved view: ' + currentStateName);
                }
            },
            stateSaveParams: function(settings, data) {
                // Save the current values of the date pickers
                data.startDate = $('#wiztable_startDate').val();
                data.endDate = $('#wiztable_endDate').val();
                
            },
            stateLoadParams: function(settings, data) {

                // Delay the restoration of the date picker values
                setTimeout(function() {
                    $('#wiztable_startDate').val(data.startDate);
                    $('#wiztable_endDate').val(data.endDate);
                }, 500);
            },
            language: {
                buttons: {
                    createState: 'Create View',
                    removeState: 'Delete View',
                    renameState: 'Rename View',
                    savedStates: 'Saved Views',
                },
                stateRestore: {
                    creationModal:{
                        title: 'Create new View',
                        button: 'Create View'
                    },
                    emptyError: 'Please enter a valid name!',
                    removeError: 'Error removing View.',
                    removeTitle: 'Delete View'
                },
                searchBuilder: {
                    data: 'Select column...',
                    title: 'Advanced Campaign Filter',
                    button: {
                        0: '<i class="fa-solid fa-sliders"></i> Filters',
                        _: '<i class="fa-solid fa-sliders"></i> Filters (%d)'
                    },
                },
                search: '',
                searchPlaceholder: 'Quick search',
                
            },
            // Draw and init callback functions
            drawCallback: idwiz_dt_draw_callback,                                                                                                                                    
            initComplete: idwiz_dt_init_callback,
            

        });

        // Inside datatables context
        // table = datatables instance
         // Enable "Actions" button when rows are selected
        table.on('select', function (e, dt, type, indexes) {
        if (type === 'row') {
            table.button('Actions:name').enable();
        }
        });

        // Disable "Actions" button when no rows are selected
        table.on('deselect', function (e, dt, type, indexes) {
            if (type === 'row') {
                if (!table.rows({ selected: true }).any()) {
                table.button('Actions:name').disable();
                }
            }
        });
        
    } // End of table success callback context
    
    function campaign_table_error_response(errorThrown){
        console.log(errorThrown);
    }
    
    let currentStateName = ""; // To store the state name

    $(document).on('click', 'div.dt-button-split > button.dt-button', function() {
        // Check if this button is related to 'saved-views'
        if ($(this).closest('.dt-button-collection').siblings('.saved-views').length) {
            currentStateName = $(this).text().trim();
            $('#saved_state_title').text('Saved view: "' + currentStateName + '"');
        }
    });

    // Init callback
    function idwiz_dt_init_callback(settings, json) {
        //Append date range into DataTables dom
        var dateSelector = '<div id="idemailwiz_date_range"><label><input type="date" id="wiztable_startDate"></label>&nbsp;thru&nbsp;<label><input type="date" id="wiztable_endDate"></label></div>';
        $('#wiztable_top_dates').append(dateSelector);
        
        //Apply and sync start/end date inputs
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex, rowData) {
                var startDateInput = $('#wiztable_startDate').val();
                var endDateInput = $('#wiztable_endDate').val();
        
                // Check if the date filter is being used
                if (startDateInput || endDateInput) {
                    // Exclude records with "N/A" date
                    if (!rowData.campaign_start) return false;
        
                    var campaignDate = new Date(parseInt(rowData.campaign_start)).toISOString().split('T')[0]; // UTC date string
        
                    if (startDateInput && campaignDate < startDateInput) return false;
                    if (endDateInput && campaignDate > endDateInput) return false;
                }
                return true;
            }
        );
        

        // Refresh the table when the dates are changed
        $('#wiztable_startDate, #wiztable_endDate').change(function() {
            $('.idemailwiz_table').DataTable().draw();
        });

        
        // Add click to single campaign page on info icon
        $('.idemailwiz_table').on('click', 'td.details-control', function() {
            var table = $('.idemailwiz_table').DataTable();
            var tr = $(this).closest('tr');
            var row = table.row(tr);

            // Access the campaign_id from the row's data
            var campaignId = row.data().campaign_id;

            // Construct the URL
            var url = "https://localhost/metrics/campaign/?id=" + campaignId;

            // Open the URL in a new tab
            window.open(url, '_blank');
          });

        

    
    } // End DT Init callback   
    

    // Draw callback
    function idwiz_dt_draw_callback(settings, json) {
        var api = this.api();

        // Hide the loader
        $('#idemailwiz_tableLoader').hide();

        // Hide saved view title, if there
        $('#saved_state_title').text('');

        

        // Move some buttons
        var advSearch = $('.btn-advanced-search').closest('.dt-button');
        advSearch.insertAfter('#wiztable_top_search');
        
        var FYbutton = $('.dt-button.fiscal-year')
        var prevMonthButton = $('.dt-button.prev-month');
        var thisMonthButton = $('.dt-button.current-month');
        var nextMonthButton = $('.dt-button.next-month');
        thisMonthButton.insertAfter('#wiztable_top_dates');
        prevMonthButton.insertBefore(thisMonthButton);
        nextMonthButton.insertAfter(thisMonthButton);
        
        FYbutton.insertAfter(nextMonthButton);
        
        
        //Change width of popup for advanced search
        $('btn-advanced-search').on('click', function() {
            $('.dtb-collection-closeable').css('width: 800px');
        });

        

        // Create the counter column
        var info = api.page.info();
        var start = info.start;
        api.column(0, { page: 'current' }).nodes().each(function (cell, i) {
            cell.innerHTML = i + 1 + start;
        });

        // Readjust the column widths on each draw
        api.columns.adjust();

        // Get current record count
        var recordCount = api.rows({search:'applied'}).count();

        // Define a function to calculate the sum of a column
        var sumColumn = function(columnName) {
            var data = api.column(columnName + ':name', {search:'applied'}).data();
            if (data.toArray) {
                data = data.toArray();
            }
            return data.reduce(function(a, b) {
                return a + Number(b) || 0;
            }, 0);
        };

        // Define a function to calculate the ratio
        var calculateRatio = function(numerator, denominator) {
            return denominator ? (numerator / denominator) * 100 : 0;
        };

        // Calculate the totals
        var totalSends = sumColumn('unique_email_sends');
        var totalDelivered = sumColumn('unique_delivered');
        var totalOpens = sumColumn('unique_email_opens');
        var totalClicks = sumColumn('unique_email_clicks');
        var totalUnsubs = sumColumn('unique_unsubscribes');
        var totalPurchases = sumColumn('unique_purchases'); 
        var totalRevenue = sumColumn('revenue'); 

        // Calculate the rates
        var openRate = calculateRatio(totalOpens, totalSends);
        var delivRate = calculateRatio(totalDelivered, totalSends);
        var clickRate = calculateRatio(totalClicks, totalSends);
        var unsubRate = calculateRatio(totalUnsubs, totalSends);
        var cto = calculateRatio(totalClicks, totalOpens);
        var cvr = calculateRatio(totalPurchases, totalOpens);

        // Update the HTML element with the calculated total and rates
        $('#wiztable_view_metrics').html(
            '<table class="wiztable_view_metrics_table"><tr>' +
            '<td><span class="metric_view_label">Campaigns</span><span class="metric_view_value">' + recordCount.toLocaleString() + '</span></td>' +
            '<td><span class="metric_view_label">Sends</span><span class="metric_view_value">' + totalSends.toLocaleString() + '</span></td>' +
            '<td><span class="metric_view_label">Delivered</span><span class="metric_view_value">' + totalDelivered.toLocaleString() + '</span></td>' +
            '<td><span class="metric_view_label">Deliv. Rate</span><span class="metric_view_value">' + delivRate.toFixed(2) + '%' + '</span></td>' +
            '<td><span class="metric_view_label">Opens</span><span class="metric_view_value"> ' + totalOpens.toLocaleString() +  '</span></td>' +
            '<td><span class="metric_view_label">Open Rate</span><span class="metric_view_value"> ' + openRate.toFixed(2) + '%' +  '</span></td>' +
            '<td><span class="metric_view_label">Clicks</span><span class="metric_view_value"> ' + totalClicks.toLocaleString() +  '</span></td>' +
            '<td><span class="metric_view_label">CTR</span><span class="metric_view_value"> ' + clickRate.toFixed(2) + '%' +  '</span></td>' +
            '<td><span class="metric_view_label">CTO</span><span class="metric_view_value"> ' + cto.toFixed(2) + '%' +  '</span></td>' +
            '<td><span class="metric_view_label">Unsubs</span><span class="metric_view_value"> ' + totalUnsubs.toLocaleString() +  '</span></td>' +
            '<td><span class="metric_view_label">Unsub. Rate</span><span class="metric_view_value"> ' + unsubRate.toFixed(2) + '%' +  '</span></td>' +
            '<td><span class="metric_view_label">Purchases</span><span class="metric_view_value"> ' + totalPurchases.toLocaleString() +
            '<td><span class="metric_view_label">Revenue</span><span class="metric_view_value"> $' + totalRevenue.toLocaleString() +
            '<td><span class="metric_view_label">CVR</span><span class="metric_view_value"> ' + cvr.toFixed(2) + '%' + '</span></td>' +
            '</tr></table>'
        );

        

    } // End DT draw callback
    
    // Sync Button
    $(document).on('click', '.sync-db', function() {
        handle_idwiz_sync_buttons("idemailwiz_ajax_sync", idAjax_data_tables.nonce);
    });

    

});
