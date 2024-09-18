<?php
add_action('wp_ajax_generate_template_html_from_ajax', 'generate_template_html_from_ajax');
function generate_template_html_from_ajax()
{
    $_POST = stripslashes_deep($_POST);

    $templateId = $_POST['template_id'] ?? '';
    $sessionData = isset($_POST['session_data']) ? $_POST['session_data'] : null;
    if ($sessionData) {
        // If session data is provided and valid, use it
        $sessionData = is_string($sessionData) ? json_decode($sessionData, true) : $sessionData;
        $templateData = $sessionData;
    } else {
        // Otherwise, fetch the template data from the database
        $templateData = get_wiztemplate($templateId);
    }
    $templateHtml = generate_template_html($templateData, false);

    if ($templateHtml) {
        // Encode HTML characters and return as plain text
        echo htmlspecialchars($templateHtml);
        die(); // We don't use wp_die here because we're using our custom ajax endpoint
    } else {
        die('Something went wrong');
    }
}

function generate_template_structure($templateData, $isEditor = false)
{
    $rows = $templateData['rows'] ?? [];
    $templateOptions = $templateData['template_options'] ?? [];
    $templateSettings = $templateOptions['message_settings'] ?? [];
    $templateStyles = $templateOptions['template_styles'] ?? [];

    $showIdHeader = filter_var($templateStyles['header-and-footer']['show_id_header'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $showIdFooter = filter_var($templateStyles['header-and-footer']['show_id_footer'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $structure = ['top' => [], 'rows' => [], 'bottom' => []];

    $structure['top']['head'] = idwiz_get_email_top($templateSettings, $templateStyles, $rows);
    $structure['top']['body_start'] = idwiz_get_email_body_top($templateStyles);
    if ($showIdHeader) {
        $structure['top']['header'] = idwiz_get_standard_header($templateOptions);
    }

    $structure['rows'] = [];

    foreach ($templateData['rows'] as $rowIndex => $row) {
        $structure['rows'][$rowIndex] = [
            'start' => generate_row_start($rowIndex, $templateData, $isEditor),
            'columnSets' => [],
            'end' => generate_row_end($row)
        ];

        foreach ($row['columnSets'] as $colSetIndex => $columnSet) {
            $structure['rows'][$rowIndex]['columnSets'][$colSetIndex] = [
                'start' => generate_columnset_start($rowIndex, $colSetIndex, $templateData, $isEditor),
                'columns' => [],
                'end' => generate_columnset_end($columnSet)
            ];

            $columns = $columnSet['columns'] ?? [];

            foreach ($columns as $columnIndex => $column) {
                if ($column['activation'] !== 'active') {
                    unset($columns[$columnIndex]);
                }
            }

            // If Magic Wrap is on, reverse the order of columns but keep the keys intact
            $magicWrap = $columnSet['magic_wrap'] ?? 'off';
            if ($magicWrap == 'on') {
                $columns = array_reverse($columnSet['columns'], true);
            }

            foreach ($columns as $colIndex => $column) {
                $structure['rows'][$rowIndex]['columnSets'][$colSetIndex]['columns'][$colIndex] = [
                    'start' => generate_column_start($rowIndex, $colSetIndex, $colIndex, $templateData, $isEditor),
                    'chunks' => [],
                    'end' => generate_column_end()
                ];

                


                foreach ($column['chunks'] as $chunkIndex => $chunk) {
                    $structure['rows'][$rowIndex]['columnSets'][$colSetIndex]['columns'][$colIndex]['chunks'][$chunkIndex] = [
                        'chunk' => idwiz_get_chunk_template(false, $rowIndex, $colSetIndex, $colIndex, $chunkIndex, $templateData, $isEditor)
                    ];
                }
            }
        }
    }

    if ($showIdFooter) {
        $structure['bottom']['footer'] = idwiz_get_standard_footer($templateStyles);
    }
    if (! empty($message_settings['fine_print_disclaimer'])) {
        $structure['bottom']['fine_print'] = idwiz_get_fine_print_disclaimer($templateOptions);
    }
    $structure['bottom']['body_end'] = idwiz_get_email_body_bottom();
    $structure['bottom']['closetags'] = idwiz_get_email_bottom();

    return $structure;
}

function render_template_from_structure($structure)
{
    $html = '';

    // Render top section
    foreach ($structure['top'] as $topElement) {
        $html .= $topElement;
    }

    // Render rows
    foreach ($structure['rows'] as $row) {
        $html .= $row['start'];
        foreach ($row['columnSets'] as $columnSet) {
            $html .= $columnSet['start'];
            foreach ($columnSet['columns'] as $column) {
                $html .= $column['start'];
                foreach ($column['chunks'] as $chunk) {
                    $html .= $chunk['chunk'];
                }
                $html .= $column['end'];
            }
            $html .= $columnSet['end'];
        }
        $html .= $row['end'];
    }

    // Render bottom section
    foreach ($structure['bottom'] as $bottomElement) {
        $html .= $bottomElement;
    }

    return $html;
}

function generate_template_html($templateData, $isEditor = false)
{
    //convert string true/false in booleans through the template data
    convertStringBooleans($templateData);

    $templateStructure = generate_template_structure($templateData, $isEditor);
    return render_template_from_structure($templateStructure);
}




function get_allRows_html($templateId = null, $templateData = null, $rowIndexes = null, $isEditor = false)
{

    if (!$templateData) {
        $templateData = get_wiztemplate($templateId);
    }
    $rows = $templateData['rows'] ?? [];
    $return = '';

    if ($rowIndexes === null) {
        $rowIndexes = array_keys($rows);
    } elseif (!is_array($rowIndexes)) {
        $rowIndexes = [$rowIndexes];
    }

    foreach ($rowIndexes as $rowIndex) {
        if (isset($rows[$rowIndex])) {
            $return .= get_row_html($templateId, $rowIndex, $templateData, $isEditor);
        }
    }

    return $return;
}

function get_row_html($templateId, $rowIndex, $templateData = null, $isEditor = false)
{
    if (!$templateData) {
        $templateData = get_wiztemplate($templateId);
    }
    $row = $templateData['rows'][$rowIndex] ?? null;
    if (!$row) return '';
    $return = '';

    $rowBackgroundCss = generate_background_css($row['background_settings']);
    $rowBackgroundCssMso = generate_background_css($row['background_settings'], '', true);

    // If this is showing in the editor, add a data attribute to the row
    $rowDataAttr = $isEditor ? 'data-row-index=' . $rowIndex : '';

    $return .= "<div class='row' $rowDataAttr style='font-size:0; width: 100%; margin: 0; padding: 0; " . $rowBackgroundCss . "'>";
    $return .= "<!--[if mso]><table role='presentation' class='row' role='presentation' width='100%' style='$rowBackgroundCssMso white-space:nowrap;width: 100%; border: 0; border-spacing: 0;margin: 0 auto;text-align:center; '><tr><td><![endif]-->";

    $columnSets = $row['columnSets'] ?? [];

    $columnSetsArray = [];
    foreach ($columnSets as $columnSetIndex => $columnSet) {
        $columnSetsArray[] = get_columnset_html($templateId, $rowIndex, $columnSetIndex, $templateData, $isEditor);
    }
    $return .= implode('', $columnSetsArray);
    $return .= "<!--[if mso]></td></tr></table><![endif]-->";
    $return .= "</div>"; // Close the row layout div

    return $return;
}

function generate_row_start($rowIndex, $templateData = null, $isEditor = false)
{
    if (!$templateData) {
        return;
    }
    $row = $templateData['rows'][$rowIndex] ?? null;
    if (!$row) return '';
    $return = '';

    $rowBackgroundCss = generate_background_css($row['background_settings']);
    $rowBackgroundCssMso = generate_background_css($row['background_settings'], '', true);

    // If this is showing in the editor, add a data attribute to the row
    $rowDataAttr = $isEditor ? 'data-row-index=' . $rowIndex : '';

    $return .= "<div class='row' $rowDataAttr style='font-size:0; width: 100%; margin: 0; padding: 0; " . $rowBackgroundCss . "'>";
    $return .= "<!--[if mso]><table role='presentation' class='row' role='presentation' width='100%' style='$rowBackgroundCssMso white-space:nowrap;width: 100%; border: 0; border-spacing: 0;margin: 0 auto;text-align:center; '><tr><td><![endif]-->";
    return $return;
}
function generate_row_end()
{
    $return = "<!--[if mso]></td></tr></table><![endif]-->";
    $return .= "</div>"; // Close the row layout div
    return $return;
}

function get_columnset_html($templateId, $rowIndex, $columnSetIndex, $templateData = null, $isEditor = false)
{
    if (!$templateData) {
        $templateData = get_idwiz_template($templateId);
    }

    $columnSet = $templateData['rows'][$rowIndex]['columnSets'][$columnSetIndex] ?? null;
    if (!$columnSet) return '';

    $return = '';

    $allColumns = $columnSet['columns'] ?? []; // includes inactive columns

    $magicRtl = '';
    $magicWrap = $columnSet['magic_wrap'] ?? 'off';
    if ($magicWrap == 'on') {
        $magicRtl = 'dir="rtl"';
    }

    $mobileWrap = $columnSet['mobile_wrap'] ?? 'on';
    $mobileWrapClass = '';
    if ($mobileWrap !== 'on') {
        $mobileWrapClass = 'noWrap';
    }

    $columns = [];
    foreach ($allColumns as $columnIndex => $allColumn) {
        if ($allColumn['activation'] !== 'active') {
            unset($allColumns[$columnIndex]);
        }
    }
    $columns = $allColumns;

    $numActiveColumns = count($allColumns);

    $colSetBackgroundCss = generate_background_css($columnSet['background_settings'], '', false);
    $colSetBackgroundCssMso = generate_background_css($columnSet['background_settings'], '', true);

    $layoutClass = $columnSet['layout'] ?? '';

    $colSetDataAttr = $isEditor ? 'data-columnset-index=' . $columnSetIndex : '';

    $displayTable = $numActiveColumns > 1 ? 'display: table;' : '';

    $return .= "<div class='columnSet $layoutClass  $mobileWrapClass' $colSetDataAttr $magicRtl style='$colSetBackgroundCss text-align: center; font-size: 0; width: 100%; " . $displayTable . "'>";
    $return .= "<!--[if mso]><table role='presentation' class='columnSet' role='presentation' width='100%' style='width: 100%; border: 0; border-spacing: 0;margin: 0 auto;text-align: center;'><tr><td style='$colSetBackgroundCssMso'><![endif]-->";
    $return .= "<table role='presentation' width='100%' style='width: 100%; border: 0; border-spacing: 0;margin: 0 auto;'><tr>";


    // If Magic Wrap is on, reverse the order of columns but keep the keys intact
    if ($magicWrap == 'on') {
        $columns = array_reverse($allColumns, true);
    }
    $colsArray = [];
    foreach ($columns as $columnIndex => $column) {
        $colsArray[] = get_column_html($templateId, $rowIndex, $columnSetIndex, $columnIndex, $templateData, $isEditor);
    }

    $return .= implode('', $colsArray);

    $return .= "</tr></table>";
    $return .= "<!--[if mso]></td></tr></table><![endif]-->";
    $return .= "</div>"; // Close the colset layout div

    return $return;
}

function
generate_columnset_start($rowIndex, $columnSetIndex, $templateData = null, $isEditor = false)
{
    if (!$templateData) {
        error_log('No template data provided for columnSet ' . $columnSetIndex);
        return;
    }
    $columnSet = $templateData['rows'][$rowIndex]['columnSets'][$columnSetIndex] ?? null;
    if (!$columnSet) {
        error_log('No columnSet found');
        return '';
    }

    $return = '';

    $allColumns = $columnSet['columns'] ?? []; // includes inactive columns

    $magicRtl = '';
    $magicWrap = $columnSet['magic_wrap'] ?? 'off';
    if ($magicWrap == 'on') {
        $magicRtl = 'dir="rtl"';
    }

    $mobileWrap = $columnSet['mobile_wrap'] ?? 'on';
    $mobileWrapClass = '';
    if ($mobileWrap !== 'on') {
        $mobileWrapClass = 'noWrap';
    }

    foreach ($allColumns as $columnIndex => $allColumn) {
        if ($allColumn['activation'] !== 'active') {
            unset($allColumns[$columnIndex]);
        }
    }

    $numActiveColumns = count($allColumns);
    $colSetBGSettings = $columnSet['background_settings'] ?? [];
    $colSetBackgroundCss = generate_background_css($colSetBGSettings, '', false);
    $colSetBackgroundCssMso = generate_background_css($colSetBGSettings, '', true);

    $layoutClass = $columnSet['layout'] ?? '';

    $colSetDataAttr = $isEditor ? 'data-columnset-index=' . $columnSetIndex : '';

    $displayTable = $numActiveColumns > 1 ? 'display: table;' : '';

    $return .= "<div class='columnSet $layoutClass  $mobileWrapClass' $colSetDataAttr $magicRtl style='$colSetBackgroundCss text-align: center; font-size: 0; width: 100%; " . $displayTable . "'>";
    $return .= "<!--[if mso]><table role='presentation' class='columnSet' role='presentation' width='100%' style='width: 100%; border: 0; border-spacing: 0;margin: 0 auto;text-align: center;'><tr><td style='$colSetBackgroundCssMso'><![endif]-->";
    $return .= "<table role='presentation' width='100%' style='width: 100%; border: 0; border-spacing: 0;margin: 0 auto;'><tr>";
    return $return;
}

function generate_columnset_end()
{
    $return = "</tr></table>";
    $return .= "<!--[if mso]></td></tr></table><![endif]-->";
    $return .= "</div>"; // Close the colset layout div

    return $return;
}

function get_column_html($templateId, $rowIndex, $columnSetIndex, $columnIndex, $templateData = null, $isEditor = false)
{
    if (!$templateData) {
        $templateData = get_idwiz_template($templateId);
    }

    $columnSet = $templateData['rows'][$rowIndex]['columnSets'][$columnSetIndex] ?? null;
    if (!$columnSet) return '';
    $layoutClass = $columnSet['layout'] ?? '';
    $mobileWrap = $columnSet['mobile_wrap'] ?? 'on';
    $mobileWrapClass = '';

    if ($mobileWrap == 'on') {
        $mobileWrapClass = 'wrap';
    }
    $columns = $columnSet['columns'];
    $column = $columns[$columnIndex] ?? null;

    if (!$column || $column['activation'] !== 'active') {
        return '';
    }

    $templateStyles = $templateData['template_options']['template_styles'];
    $colValign = $column['settings']['valign'] ? strtolower($column['settings']['valign']) : 'top';

    $colBackgroundCSS = generate_background_css($column['settings'], '');
    $msoColBackgroundCSS = generate_background_css($column['settings'], '', true);

    $columnChunks = $column['chunks'] ?? [];
    $templateWidth = $templateStyles['body-and-background']['template_width'];
    $templateWidth = (int) $templateWidth > 0 ? (int) $templateWidth : 648;

    // Determine column width based on layout and style index
    if ($layoutClass === 'two-col') {
        $columnWidthPx = round($templateWidth / 2, 0);
        $columnWidthPct = 50;
    } elseif ($layoutClass === 'three-col') {
        $columnWidthPx = round($templateWidth / 3, 0);
        $columnWidthPct = 33.33;
    } elseif ($layoutClass === 'sidebar-left') {
        if ($columnIndex === 0) {
            $columnWidthPx = round($templateWidth * 0.33, 0);
            $columnWidthPct = 33.33;
        } else {
            $columnWidthPx = round($templateWidth * 0.67, 0);
            $columnWidthPct = 66.67;
        }
    } elseif ($layoutClass === 'sidebar-right') {
        if ($columnIndex === 0) {
            $columnWidthPx = round($templateWidth * 0.67, 0);
            $columnWidthPct = 66.67;
        } else {
            $columnWidthPx = round($templateWidth * 0.33, 0);
            $columnWidthPct = 33.33;
        }
    } else {
        // Default layout (single column)
        $columnWidthPx = $templateWidth;
        $columnWidthPct = 100;
    }

    $columnStyle = "width: {$columnWidthPct}%; max-width: {$columnWidthPx}px; font-size: {$templateStyles['font-styles']['template_font_size']}; vertical-align: {$colValign}; text-align: left; display: inline-block;";

    $columnDataAttr = $isEditor ? 'data-column-index=' . $columnIndex : '';
    $return = "<div class='column $mobileWrapClass' $columnDataAttr style='$columnStyle $colBackgroundCSS' dir='ltr'>";
    $return .= "<!--[if mso]><td style='width:{$columnWidthPx}px; $msoColBackgroundCSS' width='{$columnWidthPx}' valign='{$colValign}'><![endif]-->";


    foreach ($columnChunks as $chunkIndex => $chunk) {
        $return .= idwiz_get_chunk_template($templateId, $rowIndex, $columnSetIndex, $columnIndex, $chunkIndex, $templateData, $isEditor);
    }

    $return .= "<!--[if mso]></td><![endif]-->";
    $return .= "</div>"; // Close .column div



    return $return;
}

function generate_column_start($rowIndex, $columnSetIndex, $columnIndex, $templateData = null, $isEditor = false)
{
    if (!$templateData) {
        error_log('No template data provided for column ' . $columnIndex);
        return;
    }

    $columnSet = $templateData['rows'][$rowIndex]['columnSets'][$columnSetIndex] ?? null;
    if (!$columnSet) return '';
    $layoutClass = $columnSet['layout'] ?? '';
    $mobileWrap = $columnSet['mobile_wrap'] ?? 'on';
    $mobileWrapClass = '';

    if ($mobileWrap == 'on') {
        $mobileWrapClass = 'wrap';
    }
    $columns = $columnSet['columns'];
    $column = $columns[$columnIndex] ?? null;

    if (!$column || $column['activation'] !== 'active') {
    error_log('Not active or found: Row ' . $rowIndex . ' ColSet ' . $columnSetIndex . ' Column ' . $columnIndex);
        return '';
    }

    

    $templateStyles = $templateData['template_options']['template_styles'];
    $colValign = $column['settings']['valign'] ? strtolower($column['settings']['valign']) : 'top';

    $colBackgroundCSS = generate_background_css($column['settings'], '');
    $msoColBackgroundCSS = generate_background_css($column['settings'], '', true);

    $templateWidth = $templateStyles['body-and-background']['template_width'];
    $templateWidth = (int) $templateWidth > 0 ? (int) $templateWidth : 648;

    // Determine column width based on layout and style index
    if ($layoutClass === 'two-col') {
        $columnWidthPx = round($templateWidth / 2, 0);
        $columnWidthPct = 50;
    } elseif ($layoutClass === 'three-col') {
        $columnWidthPx = round($templateWidth / 3, 0);
        $columnWidthPct = 33.33;
    } elseif ($layoutClass === 'sidebar-left') {
        if ($columnIndex === 0) {
            $columnWidthPx = round($templateWidth * 0.33, 0);
            $columnWidthPct = 33.33;
        } else {
            $columnWidthPx = round($templateWidth * 0.67, 0);
            $columnWidthPct = 66.67;
        }
    } elseif ($layoutClass === 'sidebar-right') {
        if ($columnIndex === 0) {
            $columnWidthPx = round($templateWidth * 0.67, 0);
            $columnWidthPct = 66.67;
        } else {
            $columnWidthPx = round($templateWidth * 0.33, 0);
            $columnWidthPct = 33.33;
        }
    } else {
        // Default layout (single column)
        $columnWidthPx = $templateWidth;
        $columnWidthPct = 100;
    }

    $columnStyle = "width: {$columnWidthPct}%; max-width: {$columnWidthPx}px; font-size: {$templateStyles['font-styles']['template_font_size']}; vertical-align: {$colValign}; text-align: left; display: inline-block;";

    $columnDataAttr = $isEditor ? 'data-column-index=' . $columnIndex : '';
    $return = "<div class='column $mobileWrapClass' $columnDataAttr style='$columnStyle $colBackgroundCSS' dir='ltr'>";
    $return .= "<!--[if mso]><td style='width:{$columnWidthPx}px; $msoColBackgroundCSS' width='{$columnWidthPx}' valign='{$colValign}'><![endif]-->";

    return  $return;
}

function generate_column_end()
{
    $return = "<!--[if mso]></td><![endif]-->";
    $return .= "</div>"; // Close .column div

    return $return;
}

function idwiz_get_chunk_template($templateId, $rowIndex, $columnSetIndex, $columnIndex, $chunkIndex, $templateData = null, $isEditor = false)
{
    if (!$templateData) {
        $templateData = get_idwiz_template($templateId);
    }

    $chunk = $templateData['rows'][$rowIndex]['columnSets'][$columnSetIndex]['columns'][$columnIndex]['chunks'][$chunkIndex] ?? null;
    if (!$chunk) return '';

    $chunkType = $chunk['field_type'];
    $templateOptions = $templateData['template_options'];
    $return = '';
    switch ($chunkType) {
        case 'text':
            $return = idwiz_get_plain_text_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'image':
            $return = idwiz_get_image_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'button':
            $return = idwiz_get_button_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'icon-list':
            $return = idwiz_get_icon_list_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'spacer':
            $return = idwiz_get_spacer_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'snippet':
            $return = idwiz_get_snippet_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        case 'html':
            $return = idwiz_get_raw_html_chunk($chunk, $templateOptions, $chunkIndex, $isEditor);
            break;
        default:
            return 'Unknown chunk type passed for generation';
            break;
    }

    return $return;
}



add_action('wp_ajax_get_wiztemplate_part_html', 'get_wiztemplate_part_html');

function get_wiztemplate_part_html()
{
    // Verify the nonce for security.
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    if (!wp_verify_nonce($nonce, 'template-editor')) {
        wp_send_json_error(['message' => 'Nonce verification failed']);
        return;
    }

    // Get the template data
    $templateData = json_decode(stripslashes($_POST['templateData']), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'Invalid template data']);
        return;
    }


    $templateOptions = $templateData['template_options'];
    $templateStyles = $templateOptions['template_styles'];
    $templateSettings = $templateOptions['message_settings'];
    $rows = $templateData['rows'];

    // Get and sanitize the input parameters
    $templateId = isset($_POST['templateId']) ? intval($_POST['templateId']) : 0;
    $isEditor = isset($_POST['isEditor']) ? ($_POST['isEditor'] === 'false' ? false : true) : false;
    $partType = isset($_POST['partType']) ? sanitize_text_field($_POST['partType']) : null;


    $rowIndex = isset($_POST['rowIndex']) ? intval($_POST['rowIndex']) : null;
    $columnSetIndex = isset($_POST['columnSetIndex']) ? intval($_POST['columnSetIndex']) : null;
    $columnIndex = isset($_POST['columnIndex']) ? intval($_POST['columnIndex']) : null;
    $chunkIndex = isset($_POST['chunkIndex']) ? intval($_POST['chunkIndex']) : null;



    // Generate the requested HTML based on the part type
    $html = '';
    switch ($partType) {
        case 'fullTemplate':
            $html = generate_template_html($templateData, $isEditor);
            break;
        case 'emailTop':
            $html = idwiz_get_email_top($templateSettings, $templateStyles, $rows);
            break;
        case 'emailBottom':
            $html = idwiz_get_email_bottom();
            break;
        case 'bodyWrap':
            $html = idwiz_get_email_body_top($templateStyles) . idwiz_get_email_body_bottom();
            break;
        case 'bodyTop':
            $html = idwiz_get_email_body_top($templateStyles);
            break;
        case 'bodyBottom':
            $html = idwiz_get_email_body_bottom();
            break;
        case 'footer':
            $html = idwiz_get_standard_footer($templateStyles);
            break;
        case 'finePrintDisclaimer':
            $html = idwiz_get_fine_print_disclaimer($templateOptions);
            break;
        case 'allRows':
            $html = get_allRows_html($templateId, $templateData, null, $isEditor);
            break;
        case 'row':
            $html = get_row_html($templateId, $rowIndex, $templateData, $isEditor);
            break;
        case 'columnset':
            $html = get_columnset_html($templateId, $rowIndex, $columnSetIndex, $templateData, $isEditor);
            break;
        case 'column':
            $html = get_column_html($templateId, $rowIndex, $columnSetIndex, $columnIndex, $templateData, $isEditor);
            break;
        case 'chunk':
            $html = idwiz_get_chunk_template($templateId, $rowIndex, $columnSetIndex, $columnIndex, $chunkIndex, $templateData, $isEditor);
            break;
        default:
            wp_send_json_error(['message' => 'Failed to generate HTML. Invalid part type passed: ' . $partType]);
    }

    // Send the response
    if ($html) {
        wp_send_json_success(['html' => $html]);
    } else {
        wp_send_json_error(['message' => 'Failed to generate HTML']);
    }
}
