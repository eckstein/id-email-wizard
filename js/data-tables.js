jQuery(document).ready(function ($) {

    //Date sort plugin
    $.fn.dataTable.moment('x');

    //Only run when there's a table on the page
    if ($('.idemailwiz_table_wrapper').length) {
        $.ajax({
            url: idAjax.ajaxurl,
            serverSide: true,
            data: {
                action:'idwiz_get_campaign_table_view',
                security: idAjax_data_tables.nonce
            },
            success:function(data) {
                // Parse the data to a JavaScript object
                var parsedData = JSON.parse(data);
        
                // Initialize DataTables with the parsed data
                var table = $('.idemailwiz_table').DataTable({
                    data: parsedData,
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
                            "width": "20px"
                        },
                        {
                            "className": 'details-control',
                            "title": '<i class="fa-solid fa-arrow-up-right-from-square"></i>',
                            "name": 'details-control',
                            "orderable": false,
                            "data": null,
                            "defaultContent": '<i class="fa-solid fa-circle-info"></i>'
                        },
                        {
                            "data": "campaign_start",
                            "name": "campaign_start",
                            "title": "Sent At",
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
                            extend: 'collection',
                            text: '<i class="fa-solid fa-rotate"></i>',
                            className: 'sync-buttons wiz-dt-button',
                            attr: {
                                'title': 'Sync data from Iterable',
                            },
                            background: false,
                            autoClose: true,
                            buttons: [
                                {
                                    text: 'Sync All (1 min)',
                                    className: 'sync-db sync-everything',
                                    attr:  {
                                        "data-sync-db": 'everything',
                                    },
                                    autoClose: true,
                                },
                                {
                                    text: 'Sync Campaigns (5 sec)',
                                    className: 'sync-db sync-campaigns',
                                    attr:  {
                                        "data-sync-db": 'campaigns',
                                    },
                                    autoClose: true,
                                },
                                {
                                    text: 'Sync Purchases (5 sec)',
                                    className: 'sync-db sync-purchases',
                                    attr:  {
                                        "data-sync-db": 'purchases',
                                    },
                                    autoClose: true,
                                },
                                {
                                    text: 'Sync Templates (25 sec)',
                                    className: 'sync-db sync-templates',
                                    attr:  {
                                        "data-sync-db": 'templates',
                                    },
                                    autoClose: true,
                                },
                                {
                                    text: 'Sync Metrics (25 sec)',
                                    className: 'sync-db sync-metrics',
                                    attr:  {
                                        "data-sync-db": 'metrics',
                                    },
                                    autoClose: true,
                                },
                                {
                                    text: 'Sync Experiments (20 sec)',
                                    className: 'sync-db sync-experiments',
                                    attr:  {
                                        "data-sync-db": 'experiments',
                                    },
                                    autoClose: true,
                                },
                                {
                                    text: 'View sync log',
                                    className: 'wiztable_view_sync_details',
                                    autoClose: true,
                                },
                                
                            ]
                        },
                                              
                        {
                            extend: 'searchBuilder',
                            background: false,
                            text: '<i class="fa-solid fa-sliders"></i> Filters',
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
                            extend: 'collection',
                            text: '<i class="fa-solid fa-table-columns"></i>',
                            className: 'wiz-dt-button',
                            attr: {
                                'title': 'Show/hide columns',
                            },
                            align: 'button-right',
                            buttons: [ 
                                'colvis',
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
                            extend: 'collection',
                            text: '<i class="fa-solid fa-eye"></i>',
                            className: 'wiz-dt-button',
                            attr: {
                                'title': 'Saves views',
                            },
                            align: 'button-right',
                            
                            buttons: [ 
                                'createState', 
                                { 
                                    extend: 'savedStates',
                                    config: {
                                        creationModal: true,
                                    },
                                    collectionLayout: 'fixed',
                                } 
                            ],
                            background: false,
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
                    //stateSave: true,
 
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
                            createState: 'Create view',
                            removeState: 'Delete view',
                            renameState: 'Rename view',
                            savedStates: 'Saved views',
                        },
                        stateRestore: {
                            creationModal:{
                                title: 'Create new view',
                                button: 'Create view'
                            },
                            emptyError: 'Please enter a valid name!',
                            removeError: 'Error removing view.',
                            removeTitle: 'Delete view'
                        },
                        searchBuilder: {
                            data: 'Select column...',
                            title: 'Advanced Campaign Filter',
                            button: {
                                0: '<i class="fa-solid fa-sliders"></i> Filters',
                                _: '<i class="fa-solid fa-sliders"></i> Filters (%d)'
                            }
                        },
                        search: '',
                        searchPlaceholder: 'Quick search',
                        
                    },
                    // Draw and init callback functions
                    drawCallback: idwiz_dt_draw_callback,                                                                                                                                    
                    initComplete: idwiz_dt_init_callback,
                    

                });
                
            },
            
            error: function(errorThrown){
                console.log(errorThrown);
            }
        });

        
        
    
    } // end if table exists

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
    }

    
    

    function idwiz_dt_draw_callback(settings, json) {
        var api = this.api();

        // Hide the loader
        $('#idemailwiz_tableLoader').hide();

        // Move some buttons
        var advSearch = $('.btn-advanced-search').closest('.dt-button');
        advSearch.insertAfter('#wiztable_top_dates');
        
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
        var totalPurchases = sumColumn('unique_purchases'); 
        var totalRevenue = sumColumn('revenue'); 

        // Calculate the rates
        var openRate = calculateRatio(totalOpens, totalSends);
        var delivRate = calculateRatio(totalDelivered, totalSends);
        var clickRate = calculateRatio(totalClicks, totalSends);
        var cto = calculateRatio(totalClicks, totalOpens);
        var cvr = calculateRatio(totalPurchases, totalOpens);

        // Update the HTML element with the calculated total and rates
        $('#wiztable_view_metrics').html(
            '<table id="wiztable_view_metrics_table"><tr>' +
            '<td><span class="metric_view_label">Campaigns</span><span class="metric_view_value">' + recordCount.toLocaleString() + '</span></td>' +
            '<td><span class="metric_view_label">Sends</span><span class="metric_view_value">' + totalSends.toLocaleString() + '</span></td>' +
            '<td><span class="metric_view_label">Delivered</span><span class="metric_view_value">' + totalDelivered.toLocaleString() + '</span></td>' +
            '<td><span class="metric_view_label">Deliv. Rate</span><span class="metric_view_value">' + delivRate.toFixed(2) + '%' + '</span></td>' +
            '<td><span class="metric_view_label">Opens</span><span class="metric_view_value"> ' + totalOpens.toLocaleString() +  '</span></td>' +
            '<td><span class="metric_view_label">Open Rate</span><span class="metric_view_value"> ' + openRate.toFixed(2) + '%' +  '</span></td>' +
            '<td><span class="metric_view_label">Clicks</span><span class="metric_view_value"> ' + totalClicks.toLocaleString() +  '</span></td>' +
            '<td><span class="metric_view_label">CTR</span><span class="metric_view_value"> ' + clickRate.toFixed(2) + '%' +  '</span></td>' +
            '<td><span class="metric_view_label">CTO</span><span class="metric_view_value"> ' + cto.toFixed(2) + '%' +  '</span></td>' +
            '<td><span class="metric_view_label">Purchases</span><span class="metric_view_value"> ' + totalPurchases.toLocaleString() +
            '<td><span class="metric_view_label">Revenue</span><span class="metric_view_value"> $' + totalRevenue.toLocaleString() +
            '<td><span class="metric_view_label">CVR</span><span class="metric_view_value"> ' + cvr.toFixed(2) + '%' + '</span></td>' +
            '</tr></table>'
        );
    }
    


    $(document).on('click', '.sync-db', function() {

        

        // For security, define only possible dbs
        var dbs = ['campaigns', 'templates', 'metrics', 'purchases', 'experiments'];

        // Get the type of db we're updating
        var syncDb = $(this).attr('data-sync-db');

        // Notice and logging
        $('#wiztable_status_updates').addClass('active').slideDown();
        $('#wiztable_status_updates .wiztable_update').text('Preparing ' + syncDb + ' sync...');

        

       

        if (syncDb === 'everything') {
            // Sync all dbs if 'everything' is clicked
            $('#wiztable_status_updates .wiztable_update').text('Syncing blasts, templates, metrics, experiments, and purchases...');
        } else if (dbs.includes(syncDb)) {
            // We're only syncing one table if this is set
            dbs = [syncDb];
            $('#wiztable_status_updates .wiztable_update').text('Syncing '+syncDb+'...');
        } else {
            console.log('Invalid sync option.');
            $('#wiztable_status_updates .wiztable_update').text('ERROR: Invalid sync option passed!');
            return;
        }
        
        // Write initialization to log
        $.ajax({
            type: "POST",
            url: idAjax.ajaxurl,
            data: {
                action: "ajax_to_wiz_log",
                log_data: "Initializing " + syncDb + " sync. Please wait a few moments...",
                timestamp: true,
                security: idAjax_data_tables.nonce
            },
            success: function(result) {
                $('#wiztable_status_sync_details').load(idAjax.plugin_url + '/sync-log.txt');
            }
        });


        $.ajax({
            type: "POST",
            url: idAjax.ajaxurl,
            data: {
                action: "idemailwiz_ajax_sync",
                dbs: JSON.stringify(dbs),
                security: idAjax_data_tables.nonce
            },
            success: function(result) {

                $('#wiztable_status_updates .wiztable_update').text('Sync completed! Refresh the table for new data');
                $('#wiztable_status_sync_details').load(idAjax.plugin_url + '/sync-log.txt');
                

            },
            error: function(xhr, status, error) {
                console.log(error);
                $('#wiztable_status_updates .wiztable_update').text('ERROR: Sync process failed with message: ' + error);
                $('#wiztable_status_sync_details').load(idAjax.plugin_url + '/sync-log.txt');
            }
            
        });
    });


    $(document).on('click', '.wiztable_view_sync_details', function() {
        $('#wiztable_status_sync_details').slideToggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });


    

    
    

});
