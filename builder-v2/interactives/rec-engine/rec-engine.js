    jQuery(document).ready(function($) {

    // Update class options based on existing selections
    updateClassOptions();

    // Initialize existing select2 elements
    $('.result-classes').each(function() {
        initializeSelect2($(this));
    });

     // Initialize Select2 for dynamic selects
    function initializeSelect2($element) {
        $element.select2({
            tags: true,
            tokenSeparators: [',', ' '],
            placeholder: 'Select or type classes',
            allowClear: true
        });
    }
    

    // Initialize existing CodeMirror elements on .results-html
    $(document).find('textarea.results-html').each(function() {
        initCodeMirrorForResults($(this));
    });

    // Initialize CodeMirror for result content
    function initCodeMirrorForResults($element) {
        let resultsCodemirror = CodeMirror.fromTextArea($element[0], {
            mode: 'htmlmixed',
            lineNumbers: true,
            autoRefresh: true,
            theme: 'mbo',
            indentUnit: 4,
            smartIndent: true,
            indentWithTabs: true,
             lineWrapping: true,
            gutters: ["CodeMirror-linenumbers", "CodeMirror-lint-markers"],
        });
        resultsCodemirror.setSize('100%', 200);

         resultsCodemirror.on('change', function() {
            resultsCodemirror.save();
            updatePreview();
        });

        return resultsCodemirror;
    }

    // Refresh codemirror for the results containers
    $('.result-container-header').on('click', function () {
        const $containerContent = $(this).next('.result-container-content');
        if (!$containerContent.hasClass($containerContent, 'codemirror-refreshed')) {
            const codeMirror = $containerContent.find('.CodeMirror')[0].CodeMirror;
             setTimeout(function () {
                codeMirror.refresh();
            }, 100);
            $containerContent.addClass('codemirror-refreshed');
        }
        
    });
    

    // Add event listeners for add/remove buttons
    $('#selections-container, #results-container').on('click', '.add-option-btn, .remove-option-btn, .add-result-btn, .remove-result-btn', function() {
        setTimeout(function() {
            updatePreview();
        }, 100);

    });

    // Add a new selection
    $('#selections-fieldset').on('click', '.add-selection-btn', function() {
        let $selectionsContainer = $('#selections-container');
        const selectionIndex = $selectionsContainer.children('.selection-group').length;

        $.ajax({
            url: idAjax.wizAjaxUrl,
            type: 'POST',
            data: {
                action: 'generate_rec_builder_selection_group',
                security: idAjax_template_editor.nonce,
                index: selectionIndex
            },
            success: function(response) {
                if (response.success) {
                    $selectionsContainer.append(response.data.html);
                } else {
                    console.error('Error generating new selection: ' + response.data);
                }
            },
            error: function() {
                console.error('An error occurred while generating the new selection.');
            }
        });

        initialize_rec_selections_sortables();

        
    });

    

    // Remove a selection
    $('#selections-container').on('click', '.remove-selection-btn', function() {
        $(this).closest('.selection-group').remove();
        updateSelectionIndexes();
    });

    // Add a new option to a selection
    $('#selections-container').on('click', '.add-option-btn', function() {
        const $selectionGroup = $(this).closest('.selection-group');
        const $optionsContainer = $selectionGroup.find('.options-container');
        const selectionIndex = $selectionGroup.index();
        const optionIndex = $optionsContainer.children('.option-group').length;

        $.ajax({
            url: idAjax.wizAjaxUrl,
            type: 'POST',
            data: {
                action: 'generate_rec_builder_selection_option',
                security: idAjax_template_editor.nonce,
                selectionIndex: selectionIndex,
                optionIndex: optionIndex
            },
            success: function(response) {
                if (response.success) {
                    $optionsContainer.append(response.data.html);
                    updateOptionIndexes($selectionGroup);
                } else {
                    console.error('Error generating new selection option: ' + response.data);
                }
            },
            error: function() {
                console.error('An error occurred while generating the new selection option.');
            }
        });
    });

    // Remove an option
    $('#selections-container').on('click', '.remove-option-btn', function() {
        $(this).closest('.option-group').remove();
        updateOptionIndexes($(this));
    });

    // Update selection indexes when a selection is removed
    function updateSelectionIndexes() {
        $('#selections-container').children('.selection-group').each(function(index) {
            $(this).find('.selection-group-number').text(index + 1);
            $(this).find('input, select').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/selections\[\d+\]/, `selections[${index}]`));
                }
            });
            //update labels
            $(this).find('label').each(function () {
                const name = $(this).attr('for');
                if (name) {
                    $(this).attr('for', name.replace(/selections\[\d+\]/, `selections[${index}]`));
                }
            });
        });
    }

    // Add result click
    $('.add-result-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        addNewResult();
    });

    // Add result
    function addNewResult() {
        let $resultsContainer = $('#results-container');
        const resultIndex = $resultsContainer.children('.result-container').length;
        $.ajax({
            url: idAjax.wizAjaxUrl,
            type: 'POST',
            data: {
                action: 'generate_rec_builder_result_group',
                security: idAjax_template_editor.nonce,
                index: resultIndex
            },
            success: function(response) {
                if (response.success) {
                    var $newResult = $(response.data.html).appendTo($resultsContainer);
                    initializeSelect2($newResult.find('.result-classes'));
                    setTimeout(function() {
                        initCodeMirrorForResults($newResult.find('textarea[name$="[content]"]'));
                    }, 0);
                    updateClassOptions();
                    updatePreview();
                } else {
                    console.error('Error generating new result group: ' + response.data);
                }
            },
            error: function() {
                console.error('An error occurred while generating the new result group.');
            }
        });
       
    }

    // Remove a result
    $('#results-container').on('click', '.remove-result-btn', function() {
        $(this).closest('.result-container').remove();
        updateResultIndexes();
        updateClassOptions();
    });


    function updateOptionIndexes($selectionGroup) {
        const selectionIndex = $selectionGroup.index();
        $selectionGroup.find('.options-container .option-group').each(function (optionIndex) {
            // Reindex inputs
            $(this).find('input').each(function () {
                const name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/selections\[\d+\]\[options\]\[\d+\]/, `selections[${selectionIndex}][options][${optionIndex}]`));
                }
            });

            // Reindex labels
            $(this).find('label').each(function () {
                const forAttr = $(this).attr('for');
                if (forAttr) {
                    $(this).attr('for', forAttr.replace(/selections\[\d+\]\[options\]\[\d+\]/, `selections[${selectionIndex}][options][${optionIndex}]`));
                }
                // Replace the number in .option-index-display
                $(this).find('.option-index-display').text(optionIndex + 1);
            });
        });
    }

    // Update result indexes when a result is removed
    function updateResultIndexes() {
        $('#results-container').children('.result-container').each(function(index) {
            $(this).find('.result-group-number').text(index + 1);
            $(this).find('select, input, textarea').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/results\[\d+\]/, `results[${index}]`));
                }
            });
            //update labels
            $(this).find('label').each(function () {
                const name = $(this).attr('for');
                if (name) {
                    $(this).attr('for', name.replace(/results\[\d+\]/, `results[${index}]`));
                }
            });
        });
    }

    

    // Generate class options based on selections
    function generateClassOptions() {
        const options = [];
        $('.selection-group').each(function() {
            const selectionKey = $(this).find('input[name$="[key]"]').val();
            $(this).find('.option-group').each(function() {
                const optionValue = $(this).find('input[name$="[value]"]').val();
                if (selectionKey && optionValue) {
                    options.push(`${selectionKey}-${optionValue}`);
                }
            });
        });
        return options;
    }

    // Update class options when selections change
    $('#selections-container').on('input', 'input[name$="[key]"], input[name$="[value]"]', function() {
        updateClassOptions();
    });

    // Update class options based on selections
    function updateClassOptions() {
        const classOptions = generateClassOptions();
        
        $('.result-classes').each(function() {
            const selectedValues = $(this).val() || [];
            $(this).empty();
            classOptions.forEach(option => {
                $(this).append(new Option(option, option, false, selectedValues.includes(option)));
            });
            $(this).trigger('change');
        });
    }


    // Setup expanding elements
    $('.selection-group-content, .result-container-content').hide();
    
    $(document).on('click', '.selection-group-header .toggle-hotspot', function () {
        $(this).closest('.selection-group-header').next('.selection-group-content').slideToggle();
    });
    $(document).on('click', '.result-container-header .toggle-hotspot', function () {
        $(this).closest('.result-container-header').next('.result-container-content').slideToggle();
    });

    initialize_rec_selections_sortables();
    function initialize_rec_selections_sortables(containerId = null) {
        const containerSelector = containerId ? '#' + containerId : '#selections-container';
        const sortableConfig = {
            itemsSelector: '.selection-group',
            handle: '.selection-group-header',
            placeholderClass: 'interactive-builder-placeholder',
            start: function (event, ui) {
                ui.placeholder.height(ui.item.height());
                ui.placeholder.css('background', '#ffffd1');
            },
            stop: function(event, ui) {
                updateSelectionIndexes();
                updatePreview();
            }
            
        };
        
        //init sortable
        $(containerSelector).sortable(sortableConfig);
    }

    initialize_selection_options_sortables();
    function initialize_selection_options_sortables(containerId = null) {
        const containerSelector = containerId ? '#' + containerId : '.options-container';
        const sortableConfig = {
            itemsSelector: '.option-group',
            handleSelector: '.option-group',
            placeholderClass: 'interactive-builder-placeholder',
            tolerance: 'pointer',
            start: function (event, ui) {
                ui.placeholder.height(ui.item.height());
                ui.placeholder.width(ui.item.width());
                ui.placeholder.css('background', '#ffffd1');
            },
            stop: function(event, ui) {
                var $selection = $(ui.item).closest('.selection-group');
                updateOptionIndexes($selection);
                updatePreview();
            }
            
        };
        
        //init sortable
        $(containerSelector).sortable(sortableConfig);
    }

    initialize_rec_results_sortables();
    function initialize_rec_results_sortables(containerId = null) {
        const containerSelector = containerId ? '#' + containerId : '#results-container';
        const sortableConfig = {
            itemsSelector: '.result-container',
            handle: '.result-container-header',
            placeholderClass: 'interactive-builder-placeholder',
            start: function (event, ui) {
                ui.placeholder.height(ui.item.height());
                ui.placeholder.css('background', '#ffffd1');
            },
            stop: function(event, ui) {
                updateResultIndexes();
                updatePreview();
            }
        };
        
        //init sortable
        $(containerSelector).sortable(sortableConfig);
    }



    
});