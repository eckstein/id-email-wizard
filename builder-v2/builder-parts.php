<?php

function get_builder_pane_header($postId)
{
    return <<<HTML
    
    <div class="builder-pane-header">
        <div id="main-builder-tabs" class="wizard-tabs" data-scroll-body="builder-pane">
            <div class="wizard-tab builder-tab --active" data-tab="#builder-tab-chunks" id="builder-tab-chunks-tab" title="Content chunks">
                <i class="fa-solid fa-puzzle-piece"></i>&nbsp;&nbsp;Layout
            </div>

            <div class="wizard-tab builder-tab" data-tab="#builder-tab-styles" id="builder-tab-styles-tab" title="Template Styles">
                <i class="fa-solid fa-brush"></i>&nbsp;&nbsp;Styles
            </div>
            <div class="wizard-tab builder-tab" data-tab="#builder-tab-message-settings" id="builder-tab-message-settings-tab"
                title="Message settings">
                <i class="fa-solid fa-envelope"></i>&nbsp;&nbsp;Options
            </div>
            <div class="wizard-tab builder-tab" data-tab="#builder-tab-mocks" id="builder-tab-mocks-tab" title="Mockups">
                <i
                    class="fa-regular fa-file-image"></i>&nbsp;&nbsp;Mocks
            </div>
            <div class="wizard-tab builder-tab" data-tab="#builder-tab-code" id="builder-tab-code-tab">
                <i class="fa-solid fa-code"
                    title="Code & JSON"></i>
            </div>
            <div class="wizard-tab builder-tab" data-tab="#builder-tab-settings" id="builder-tab-settings-tab" title="Template Settings">
                <i
                    class="fa-solid fa-gear"></i>
            </div>


        </div>
        <div class="main-builder-actions">
            <button title="Sync to Iterable" class="wiz-button" id="sendToIterable"
                data-postid="{$postId}"><img style="width: 20px; height: 20px;"
                    src="https://idemailwiz.com/wp-content/uploads/2023/10/Iterable_square_logo-e1677898367554.png" />&nbsp;&nbsp;
                Sync</button>

            <button for="wiz-template-form" class="wiz-button green" id="save-template"><i
                    class="fa-regular fa-floppy-disk"></i>&nbsp;&nbsp;Save</button>



        </div>
    </div>

HTML;
}

function get_template_actions_bar($postId)
{
    return <<<HTML

    <div id="templateActions">

        <div class="innerWrap">
            <div id="templatePreviewIcons">
                <i title="Desktop Preview" class="fas fa-desktop showDesktopPreview active"
                    data-frame="#previewFrame"></i>
                <i title="Mobile Preview" class="fas fa-mobile-alt showMobilePreview"
                    data-frame="#previewFrame"></i>
                <div class="preview_width_dragger" data-frame="#previewFrame"></div>

                <span class="templateActions-divider"></span>

                <i title="White Background" class="fa-solid fa-sun editor-bg-mode light-mode-interface active"
                    data-mode="light" data-frame="#previewFrame"></i>
                <i title="Dark Background" class="fa-solid fa-moon editor-bg-mode dark-mode-interface"
                    data-mode="dark" data-frame="#previewFrame"></i>
                <div title="Transparent Background" class="editor-bg-mode transparent-mode-interface"
                    data-mode="trans" data-frame="#previewFrame">
                </div>
                <span class="templateActions-divider"></span>

                <div title="Fill Merge Tags" class="fill-merge-tags" data-postid="<?php echo $postId; ?>">
                    &nbsp;<span style="font-size:.8em;">{{X}}</span>&nbsp;</div>
                <i title="Template Data" class="fa-solid fa-database manage-template-data"></i>
                <span class="templateActions-divider"></span>
                <i title="Start link checker" class="fa-solid fa-link start-link-checker"></i>
            </div>


            <button title="Refresh Preview" class="wiz-button green" id="showModal"><i
                    class="fa-solid fa-rotate"></i>&nbsp;&nbsp;Modal</button>
            <button title="Refresh Preview" class="wiz-button green" id="refreshPreview"><i
                    class="fa-solid fa-rotate"></i>&nbsp;&nbsp;Refresh</button>
            <button title="Show Preview Pane" class="wiz-button green show-preview" id="showFullPreview"
                data-preview-mode="preview" data-postid="<?php echo $postId; ?>"><i
                    class="fa-solid fa-eye"></i>&nbsp;&nbsp;Full Preview</button>
        </div>
    </div>

HTML;
}



function generate_builder_row($rowId, $rowData = [])
{
    $uniqueId = uniqid('wiz-row-');

    // Attempt to set columnSets from rowData, default to an empty array if not set or not an array
    $columnSets = isset($rowData['columnSets']) && is_array($rowData['columnSets']) ? $rowData['columnSets'] : [];

    $columnSetCount = count($columnSets);

    $rowCollapseState = $rowData['state'] ?? 'collapsed';

    $rowDesktopVisibility = isset($rowData['desktop_visibility']) && $rowData['desktop_visibility'] === 'false' ? 'false' : 'true';
    $rowMobileVisibility = isset($rowData['mobile_visibility']) && $rowData['mobile_visibility'] === 'false' ? 'false' : 'true';

    // Determine if the icons should have the 'disabled' class based on visibility
    $rowDesktopIconClass = $rowDesktopVisibility === 'false' ? 'disabled' : '';
    $rowMobileIconClass = $rowMobileVisibility === 'false' ? 'disabled' : '';

    $rowClasses = $rowData['row_classes'] ?? '';

    $rowBackgroundSettings = $rowData['background_settings'] ?? [];

    $summaryVisClass = $rowCollapseState === 'collapsed' ? 'visible' : '';

    $rowTitle = $rowData['title'] ?? 'Section';
    $rowNumber = $rowId + 1;
    $html = <<<HTML
    <div class="builder-row --{$rowCollapseState}" id="{$uniqueId}" data-row-id="{$rowId}">
        <div class="builder-header builder-row-header">
            <div class="builder-row-title exclude-from-toggle">
                <div class="builder-row-title-number" data-row-id-display="{$rowNumber}">{$rowNumber}</div>
                <div class="builder-row-title-text edit-row-title exclude-from-toggle" data-row-id="{$rowId}">{$rowTitle}</div>
            </div>
            <div class="builder-row-toggle builder-toggle"><div class="builder-element-summary {$summaryVisClass} builder-row-summary">Show {$columnSetCount} column sets</div></div>
            <div class="builder-row-actions">
                <div class="builder-row-actions-button exclude-from-toggle show-on-desktop {$rowDesktopIconClass}" data-show-on-desktop="{$rowDesktopVisibility}" title="Show on desktop">
                    <i class="fas fa-desktop"></i>
                </div>
                <div class="builder-row-actions-button exclude-from-toggle show-on-mobile {$rowMobileIconClass}" data-show-on-mobile="{$rowMobileVisibility}" title="Show on mobile">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                
                <span>&nbsp;|&nbsp;</span>
                <div class="builder-row-actions-button exclude-from-toggle row-bg-settings-toggle" title="Background color">
                    <i class="fa-solid fa-fill-drip"></i>
                </div>
                <div class="builder-row-actions-button exclude-from-toggle json-actions" data-json-element="row" title="Export/Import JSON data">
                    <i class="fa-solid fa-share-nodes"></i>
                </div>
                <div class="builder-row-actions-button exclude-from-toggle duplicate-row" title="Duplicate row">
                    <i class="fa-regular fa-copy"></i>
                </div>
                <div class="builder-row-actions-button remove-element remove-row exclude-from-toggle" title="Delete row">
                    <i class="fas fa-times"></i>
                </div>
            </div>
        </div>
        <div class="builder-settings-section builder-row-settings-row">
            <form class="builder-row-settings">
                <div class="builder-field-group">
                    <div class='builder-field-wrapper row-classes'>
                        <label for='{$uniqueId}-row-classes'>Row Classes</label>
                        <input type='text' name='row_classes' id='{$uniqueId}-row-classes' value='{$rowClasses}'>
                    </div>
                </div>
                <fieldset name="background_settings">
    HTML;

    $html .= generate_background_settings_module($rowBackgroundSettings, '');

    $html .= <<<HTML
                </fieldset>
            </form>
        </div>
        <div class="builder-row-content">
            <div class="builder-columnsets">
    HTML;

    foreach ($columnSets as $colSetIndex => $columnSet) {
        $html .= generate_builder_columnset($colSetIndex, $columnSet, $rowId);
    }

    $html .= <<<HTML
            </div>
            <div class="builder-row-footer">
                <button class="wiz-button outline add-columnset">Add Column Set</button>
            </div>
        </div>
    </div>
    HTML;

    return $html;
}

function generate_builder_columnset($colSetIndex, $columnSet, $rowId)
{
    $uniqueId = uniqid('wiz-columnset-');

    $colsetDesktopVisibility = isset($columnSet['desktop_visibility']) && $columnSet['desktop_visibility'] === 'false' ? 'false' : 'true';
    $colsetMobileVisibility = isset($columnSet['mobile_visibility']) && $columnSet['mobile_visibility'] === 'false' ? 'false' : 'true';

    // Determine if the icons should have the 'disabled' class based on visibility
    $colsetDesktopIconClass = $colsetDesktopVisibility === 'false' ? 'disabled' : '';
    $colsetMobileIconClass = $colsetMobileVisibility === 'false' ? 'disabled' : '';

    $columns = $columnSet['columns'] ?? [];

    // Ensure there are always three columns available
    while (count($columns) < 3) {
        $columns[] = [
            'title' => 'Column',
            'activation' => 'inactive',
            'chunks' => []
        ];
    }

    $countColumns = 0;
    foreach ($columns as $column) {
        if (! isset($column['activation']) || $column['activation'] === 'active') {
            $countColumns++;
        }
    }

    if ($countColumns > 1) {
        $magicWrap = $columnSet['magic_wrap'] ?? 'off';
        $mobileWrap = $columnSet['mobile_wrap'] ?? 'on';

        $magicWrapToggleClass = $magicWrap == 'on' ? 'active' : '';
        $mobileWrapToggleClass = $mobileWrap == 'on' ? 'active' : '';

        if ($magicWrap == 'on') {
            //$columns = array_reverse($columns);
        }
    } else {
        $magicWrap = 'off';
        $mobileWrap = 'off';

        $magicWrapToggleClass = 'disabled';
        $mobileWrapToggleClass = 'disabled';
    }

    $columnsStacked = $columnSet['stacked'] ?? false;

    $stackedClass = $columnsStacked ? 'fa-rotate-90' : '';

    $stackedActiveClass = $columnsStacked ? 'active' : '';

    $columnsetTitle = $columnSet['title'] ?? 'Column Set';

    $colsLayout = $columnSet['layout'] ?? 'one-column';

    $columnSetClasses = $columnSet['columnset_classes'] ?? '';

    $colsetBgSettings = $columnSet['background_settings'] ?? [];

    $colSetState = $columnSet['state'] ?? 'collapsed';

    $columnSetDisplayCnt = $colSetIndex + 1;

    $html = <<<HTML
    <div class="builder-columnset --{$colSetState}" id="{$uniqueId}" data-columnset-id="{$colSetIndex}" data-layout="{$colsLayout}" data-magic-wrap="{$magicWrap}" data-mobile-wrap="{$mobileWrap}" data-show-on-desktop="{$colsetDesktopVisibility}" data-show-on-mobile="{$colsetMobileVisibility}">
        <div class="builder-header builder-columnset-header">
            <div class="builder-columnset-title exclude-from-toggle">
                <div class="builder-columnset-title-number" data-columnset-id-display="{$columnSetDisplayCnt}">{$columnSetDisplayCnt}</div>
                <div class="builder-columnset-title-text edit-columnset-title exclude-from-toggle" data-columnset-id="{$colSetIndex}">{$columnsetTitle}</div>
            </div>
            <div class="builder-toggle builder-columnset-toggle">&nbsp;</div>
            <div class="builder-columnset-actions">
                <div class="builder-columnset-actions-button exclude-from-toggle show-on-desktop {$colsetDesktopIconClass}" data-show-on-desktop="{$colsetDesktopVisibility}" title="Show on desktop">
                    <i class="fas fa-desktop"></i>
                </div>
                <div class="builder-columnset-actions-button exclude-from-toggle show-on-mobile {$colsetMobileIconClass}" data-show-on-mobile="{$colsetMobileVisibility}" title="Show on mobile">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <span>&nbsp;|&nbsp;</span>
                <div class="builder-columnset-actions-button exclude-from-toggle colset-bg-settings-toggle" title="Background color">
                    <i class="fa-solid fa-fill-drip"></i>
                </div>
                <div class="builder-columnset-actions-button columnset-column-settings exclude-from-toggle" data-columns="{$countColumns}" title="Change columns layout">
                    <i class="fas fa-columns"></i>
                </div>
                <div class="builder-columnset-actions-button mobile-wrap-toggle columnset-columns-mobile-wrap exclude-from-toggle {$mobileWrapToggleClass}" title="Toggle mobile column wrap">
                    <i class="fa-solid fa-mobile-alt"></i> <i class="fa-solid fa-arrows-turn-right fa-rotate-180"></i>
                </div>
                <div class="builder-columnset-actions-button magic-wrap-toggle columnset-columns-magic-wrap exclude-from-toggle {$magicWrapToggleClass}" title="Magic Wrap">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> <i class="fa-solid fa-arrow-right-arrow-left"></i>
                </div>
                <span>&nbsp;|&nbsp;</span>
                <div class="builder-columnset-actions-button exclude-from-toggle rotate-columns {$stackedActiveClass}" title="Stack/Unstack columns">
                    <i class="fa-solid fa-bars {$stackedClass}"></i>
                </div>
                <div class="builder-columnset-actions-button exclude-from-toggle json-actions" data-json-element="columnset" title="Export/Import JSON data">
                    <i class="fa-solid fa-share-nodes"></i>
                </div>
                <div class="builder-columnset-actions-button exclude-from-toggle duplicate-columnset" title="Duplicate columnset">
                    <i class="fa-regular fa-copy"></i>
                </div>
                <div class="builder-columnset-actions-button remove-element remove-columnset exclude-from-toggle" title="Delete columnset">
                    <i class="fas fa-times"></i>
                </div>
            </div>
        </div>
        <div class="builder-settings-section builder-columnset-settings-row">
            <form class="builder-columnset-settings">
                <div class="builder-field-group">
                    <div class='builder-field-wrapper columnset-classes'>
                        <label for='{$uniqueId}-columnset-classes'>ColumnSet Classes</label>
                        <input type='text' name='columnset_classes' id='{$uniqueId}-columnset-classes' value='{$columnSetClasses}'>
                    </div>
                </div>
                <fieldset name='background_settings'>
    HTML;

    $html .= generate_background_settings_module($colsetBgSettings, '');

    $html .= <<<HTML
                </fieldset>
            </form>
        </div>
        <div class="builder-columnset-content">
    HTML;

    if ($magicWrap == 'on') {
        $html .= '<div class="magic-wrap-indicator"><i class="fa-solid fa-wand-magic-sparkles"></i>&nbsp;&nbsp;Magic wrap is on! Columns will be reversed when wrapped for mobile.</div>';
    }


    $html .= <<<HTML
        <div class="builder-columnset-columns" data-active-columns="{$countColumns}" data-column-stacked="{$columnsStacked}">
    HTML;

    $colSetIndex = 0;
    foreach ($columns as $columnIndex => $column) {
        $colSetIndex++;
        $html .= generate_builder_column($rowId, $columnIndex, $column);
    }

    $html .= <<<HTML
            </div>
        </div>
    </div>
    HTML;

    return $html;
}

function generate_builder_column($rowId, $columnIndex, $columnData = [])
{
    $uniqueId = uniqid('wiz-column-');

    $columnNumberDisplay = $columnIndex + 1;

    $colTitle = $columnData['title'] ?? 'Column';

    $columnClasses = $columnData['settings']['column_classes'] ?? '';

    $colValign = $columnData['settings']['valign'] ?? 'top';
    $valignTopChecked = $colValign === 'top' ? 'checked' : '';
    $valignMiddleChecked = $colValign === 'middle' ? 'checked' : '';
    $valignBottomChecked = $colValign === 'bottom' ? 'checked' : '';

    $colBgSettings = $columnData['settings'] ?? [];

    $colActiveClass = isset($columnData['activation']) && $columnData['activation'] === 'inactive' ? 'inactive' : 'active';

    $html = <<<HTML
    <div class="builder-column {$colActiveClass}" id="{$uniqueId}" data-column-id="{$columnIndex}">
        <div class="builder-header builder-column-header">
            <div class="builder-column-title exclude-from-toggle">
                <div class="builder-column-title-number">{$columnNumberDisplay}</div>
                <div class="builder-column-title-text edit-column-title exclude-from-toggle" data-column-id="{$columnIndex}">{$colTitle}</div>
            </div>
            <div class="builder-column-toggle">&nbsp;</div>
            <div class="builder-column-actions">
                <div class="builder-column-actions-button show-column-settings">
                    <i class="fa-solid fa-fill-drip" title="Column Styles"></i>
                </div>
            </div>
        </div>
        <div class="builder-column-settings-row">
            <form class="builder-column-settings">
                <div class="builder-field-group">
                    <div class='builder-field-wrapper column-classes'>
                        <label for='{$uniqueId}-column-classes'>Column Classes</label>
                        <input type='text' name='column_classes' id='{$uniqueId}-column-classes' value='{$columnClasses}'>
                    </div>
                </div>
                <div class="builder-field-group">
                    <div class="button-group-wrapper">
                        <label class="button-group-label">Vertical Align</label>
                        <div class="button-group radio">
                            <input type="radio" id="{$uniqueId}_valign_top" name="valign" value="top" class="valign-type-select" {$valignTopChecked}>
                            <label class="button-label" for="{$uniqueId}_valign_top">Top</label>
                            <input type="radio" id="{$uniqueId}_valign_middle" name="valign" value="middle" class="valign-type-select" {$valignMiddleChecked}>
                            <label class="button-label" for="{$uniqueId}_valign_middle">Middle</label>
                            <input type="radio" id="{$uniqueId}_valign_bottom" name="valign" value="bottom" class="valign-type-select" {$valignBottomChecked}>
                            <label class="button-label" for="{$uniqueId}_valign_bottom">Bottom</label>
                        </div>
                    </div>
                </div>
    HTML;

    $html .= generate_background_settings_module($colBgSettings, '');

    $html .= <<<HTML
            </form>
        </div>
        <div class="builder-column-chunks">
            <div class="builder-column-chunks-body">
    HTML;

    if (!empty($columnData['chunks'])) {
        foreach ($columnData['chunks'] as $chunkIndex => $chunk) {
            $chunkType = $chunk['field_type'] ?? 'text';
            $html .= generate_builder_chunk($chunkIndex, $chunkType, $chunk);
        }
    }

    $html .= <<<HTML
            </div>
            <div class="builder-column-footer add-chunk-wrapper">
                <button class="wiz-button centered add-chunk">Add Chunk</button>
            </div>
        </div>
    </div>
    HTML;

    return $html;
}


function generate_builder_chunk($chunkId, $chunkType, $chunkData = [])
{
    $uniqueId = $chunkData['id'] ?? uniqid('wiz-chunk-');
    $uniqueId = uniqid('wiz-chunk-');
    $chunkState = $chunkData['state'] ?? 'collapsed';

    $desktopVisibility = (isset($chunkData['settings']['desktop_visibility']) && $chunkData['settings']['desktop_visibility'] == 'false') ? 'false' : 'true';
    $mobileVisibility = (isset($chunkData['settings']['mobile_visibility']) && $chunkData['settings']['mobile_visibility'] == 'false') ? 'false' : 'true';


    $desktopIconClass = $desktopVisibility == 'false' ? 'disabled' : '';
    $mobileIconClass = $mobileVisibility == 'false' ? 'disabled' : '';


    $chunkPreview = get_chunk_preview($chunkData, $chunkType);

    $encodedChunkData = htmlspecialchars(json_encode($chunkData), ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
    <div class="builder-chunk --{$chunkState}" data-chunk-id="{$chunkId}" data-chunk-type="{$chunkType}" id="{$uniqueId}" data-chunk-data="{$encodedChunkData}">
        <div class="builder-header builder-chunk-header">
            <div class="builder-chunk-title">{$chunkPreview}</div>
            <div class="builder-toggle builder-chunk-toggle">&nbsp;</div>
            <div class="builder-chunk-actions">
                <div class="builder-chunk-actions-button exclude-from-toggle show-on-desktop {$desktopIconClass}" data-show-on-desktop="{$desktopVisibility}" title="Show on desktop">
                    <i class="fas fa-desktop"></i>
                </div>
                <div class="builder-chunk-actions-button exclude-from-toggle show-on-mobile {$mobileIconClass}" data-show-on-mobile="{$mobileVisibility}" title="Show on mobile">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <span>&nbsp;|&nbsp;</span>
                <div class="builder-chunk-actions-button add-chunk-wrapper builder-add-new-chunk-above exclude-from-toggle" title="Add chunk below">
                    <span class="add-chunk" data-chunk-id="{$chunkId}"><i class="fas fa-plus"></i></span>
                </div>
                <div class="builder-chunk-actions-button exclude-from-toggle duplicate-chunk" title="Duplicate chunk">
                    <i class="fa-regular fa-copy"></i>
                </div>
                <div class="builder-chunk-actions-button remove-element remove-chunk exclude-from-toggle" title="Remove chunk">
                    <i class="fas fa-times"></i>
                </div>
            </div>
        </div>
        <div class="builder-chunk-body">
    HTML;

    $html .= generate_chunk_form_interface($chunkType, $chunkData, $uniqueId);

    $html .= <<<HTML
        </div>
    </div>
    HTML;

    return $html;
}


function generate_chunk_form_interface($chunkType, $chunkData, $uniqueId)
{
    ob_start();

    $activeTab = $chunkData['activeTab'] ?? 'content';

    // Define tabs, their labels, and content generators
    $tabs = array(
        'content' => array(
            'label' => 'Content',
            'contentGenerator' => function ($chunkType, $chunkData, $uniqueId) {
                echo "<form id='{$uniqueId}-chunk-fields' class='chunk-fields-form'>";
                render_chunk_fields($chunkType, $chunkData, $uniqueId);
                echo "</form>";
            }
        ),
        'settings' => array(
            'label' => 'Settings',
            'contentGenerator' => function ($chunkType, $chunkData, $uniqueId) {
                echo "<form id='{$uniqueId}-chunk-settings' class='chunk-settings-form'>";
                echo render_chunk_settings($chunkType, $chunkData, $uniqueId);
                echo "</form>";
            }
        ),
        'code' => array(
            'label' => 'HTML Code',
            'addtLabelClasses' => 'refresh-chunk-code',
            'contentGenerator' => function ($uniqueId) {
                echo "<div class='chunk-tab-content-actions'>";
                echo "<button class='wiz-button green copy-chunk-code' title='Copy HTML Code' data-code-in='#{$uniqueId}-chunk-code'><i class='fa-regular fa-copy'></i>&nbsp;&nbsp;Copy Code</button>";
                echo "</div>";
                echo "<form id='{$uniqueId}-chunk-code' class='chunk-code-form'>";
                echo "<div class='chunk-html-code'>";
                echo "<pre><code>Loading HTML code...</code></pre>";
                echo "</div>";
                echo "</form>";
            }
        )
    );

    // Generate tabs
    echo '<div class="chunk-tabs">';
    foreach ($tabs as $tab => $tabInfo) {
        $isActive = $tab === $activeTab ? 'active' : '';
        $additionalClasses = $tabInfo['addtLabelClasses'] ?? '';
        echo "<div class='chunk-tab $isActive $additionalClasses' data-target='#{$uniqueId}-chunk-{$tab}-container'>{$tabInfo['label']}</div>";
    }
    echo '</div>';

    // Generate tab content
    foreach ($tabs as $tab => $tabInfo) {
        $isActive = $tab === $activeTab ? ' active' : '';
        echo "<div class='chunk-tab-content chunk-{$tab}{$isActive}' id='{$uniqueId}-chunk-{$tab}-container'>";
        $tabInfo['contentGenerator']($chunkType, $chunkData, $uniqueId);
        echo "</div>";
    }

    return ob_get_clean();
}
function render_chunk_settings($chunkType, $chunkData, $uniqueId)
{
    $settings = array(
        'text' => ['chunk_classes', 'chunk_padding', 'p_padding', 'div', 'force_white_text_devices', 'div', 'background_settings'],
        'html' => ['chunk_wrap', 'chunk_classes', 'chunk_padding', 'div', 'force_white_text_devices', 'div', 'background_settings', 'chunk_wrap_hide_end'],
        'icon-list' => ['chunk_classes', 'chunk_padding', 'p_padding', 'div', 'force_white_text_devices', 'div', 'list_width', 'icon_width', 'div', 'background_settings'],
        'image' => ['chunk_classes', 'chunk_padding', 'div', 'background_settings'],
        'button' => ['chunk_classes', 'chunk_padding', 'div', 'background_settings'],
        'spacer' => ['chunk_classes', 'div', 'background_settings'],
        'snippet' => ['chunk_classes', 'div', 'background_settings'],
        'interactive' => ['chunk_classes', 'div', 'background_settings']
    );

    $chunkSettings = $settings[$chunkType] ?? [];

    echo "<div class='chunk-inner-content'>";
    show_specific_chunk_settings($chunkData, $uniqueId, $chunkSettings, $chunkType);
    echo "</div>";
}
function show_specific_chunk_settings($chunkData, $uniqueId, $settings, $chunkType)
{
    $showChunkWrap = in_array('chunk_wrap', $settings);

    $settingsActive = true; // default to showing the chunk settings

    $chunkSettings = $chunkData['settings'] ?? [];

    if ($showChunkWrap) {
        echo "<div class='builder-field-group flex'>"; // Start the chunk wrap setting

        $chunkWrap = $chunkSettings['chunk_wrap'] ?? false;

        $settingsActive = $chunkWrap === true ? 'active' : '';
        echo "<div class='builder-field-wrapper flex'>";
        $uniqueIdchunkWrap = $uniqueId . 'chunk_wrap';
        $chunkWrapChecked = $chunkWrap ? 'checked' : '';
        $chunkWrapActive = $chunkWrap ? 'active' : '';
        $chunkWrapClass = $chunkWrap ? 'fa-solid' : 'fa-regular';


        echo "<div class='wiz-checkbox-toggle'>";

        echo "<input type='checkbox' class='wiz-check-toggle toggle-chunk-wrap-input' id='$uniqueIdchunkWrap' name='chunk_wrap' hidden $chunkWrapChecked>";
        echo "<label for='$uniqueIdchunkWrap' class='wiz-check-toggle-display toggle_chunk_wrap $chunkWrapActive'><i class='$chunkWrapClass fa-2x fa-square-check'></i></label>";
        echo "<label class='checkbox-toggle-label'>Standard Chunk Wrap</label>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }

    if ($showChunkWrap) {
        echo "<div class='chunk-wrap-hide-settings $settingsActive'>";
    }

    echo "<div class='chunk-settings-section chunk-general-settings'>";
    echo "<div class='builder-field-group flex'>"; // Start the main wrapper

    foreach ($settings as $setting) {

        if ($setting !== 'div') {
            switch ($setting) {
                case 'chunk_classes':
                    $chunkClasses = $chunkSettings['chunk_classes'] ?? '';
                    echo "<div class='builder-field-wrapper chunk-classes'><label for='{$uniqueId}-chunk-classes'>Chunk Classes</label>";
                    echo "<input type='text' name='chunk_classes' id='{$uniqueId}-chunk-classes' value='{$chunkClasses}'>";
                    echo "</div>";
                    break;
                case 'chunk_padding':
                    $defaultPadding = $chunkType === 'text' ? '20px' : '0';
                    $chunkPadding = $chunkSettings['chunk_padding'] ?? $defaultPadding;
                    echo "<div class='builder-field-wrapper chunk-padding small-input'><label for='{$uniqueId}-chunk-padding'>Chunk Padding</label>";
                    echo "<input type='text' name='chunk_padding' id='{$uniqueId}-chunk-padding' value='{$chunkPadding}'>";
                    echo "</div>";
                    break;
                case 'p_padding':
                    $pPadding = $chunkSettings['p_padding'] ?? false;
                    $uniqueIdPpadding = $uniqueId . 'p_padding';
                    $pPaddingChecked = $pPadding ? 'checked' : '';
                    $pPaddingActive = $pPadding ? 'active' : '';
                    $npPaddingClass = $pPadding ? 'fa-solid' : 'fa-regular';

                    echo "<div class='builder-field-wrapper'>";
                    echo "<div class='wiz-checkbox-toggle'>";

                    echo "<input type='checkbox' class='wiz-check-toggle' id='$uniqueIdPpadding' name='p_padding' hidden $pPaddingChecked>";
                    echo "<label for='$uniqueIdPpadding' class='wiz-check-toggle-display $pPaddingActive'><i class='$npPaddingClass fa-2x fa-square-check'></i></label>";
                    echo "<label class='checkbox-toggle-label'>Pad " . htmlentities('<p>') . "'s</label>";
                    echo "</div>";
                    echo "</div>";
                    break;
                


                case 'force_white_text_devices': 
                    $forceWhiteTextDevices = [
                        ['id' => $uniqueId . '_force-white-text-desktop', 'name' => 'force_white_text_on_desktop', 'display' => 'desktop', 'label' => '<i class="fa-solid fa-desktop"></i>'],
                        ['id' => $uniqueId . '_force-white-text-mobile', 'name' => 'force_white_text_on_mobile', 'display' => 'mobile', 'label' => '<i class="fa-solid fa-mobile-screen-button"></i>']
                    ];

                    echo "<div class='button-group-wrapper builder-field-wrapper chunk-force-white-text-devices'>";
                    echo "<label class='button-group-label'>Force Gmail white text on:</label>";
                    echo "<div class='button-group checkbox'>";
                    foreach ($forceWhiteTextDevices as $opt) {
                        $fieldID = $opt['id'];
                        $isChecked = $chunkSettings[$opt['name']];
                        $checkVal = $isChecked ? 'true' : 'false';
                        $checkedAtt = $isChecked ? 'checked' : '';

                        echo "<input type='checkbox' id='{$fieldID}' name='{$opt['name']}'
                            value='$checkVal' $checkedAtt>";
                        echo "<label for='{$fieldID}' class='button-label' title='{$opt['display']}'>";
                        echo $opt['label'];
                        echo "</label>";
                    }
                    echo "</div>";
                    echo "</div>";
                    break;
                case 'list_width':
                    $listWidth = $chunkSettings['list_width'] ?? '100%';
                    echo "<div class='builder-field-wrapper list-width'><label for='{$uniqueId}-list-width'>List Width</label>";
                    echo "<input type='text' name='list_width' id='{$uniqueId}-list-width' value='{$listWidth}'>";
                    echo "</div>";
                    break;
                case 'icon_width':
                    $iconWidth = $chunkSettings['icon_width'] ?? '100px';
                    echo "<div class='builder-field-wrapper icon-width'><label for='{$uniqueId}-icon-width'>Icon Width</label>";
                    echo "<input type='text' name='icon_width' id='{$uniqueId}-icon-width' value='{$iconWidth}'>";
                    echo "</div>";
                    break;
                case 'background_settings':
                    echo generate_background_settings_module($chunkSettings, '');
                    break;
            }
        } else {
            echo "</div>
            <div class='builder-field-group flex'>";
        }
    }

    if ($showChunkWrap) {
        echo "</div>"; // Close the chunk wrap hide settings div
    }

    echo "
        </div>"; // Close the main wrapper div
    echo "</div>"; // Close the general settings div
}



function render_chunk_fields($chunkType, $chunkData, $uniqueId)
{
    // Chunk specific form fields
    echo "<div class='chunk-inner-content'>";
    echo "<form id='{$uniqueId}-chunk-fields-form'>";
    switch ($chunkType) {
        case 'text':
            $existingContent = isset($chunkData['fields']['plain_text_content']) ? stripslashes($chunkData['fields']['plain_text_content']) : 'Enter your content here...';
            $editorMode = $chunkData['editor_mode'] ?? 'light';

            echo '<textarea class="wiz-wysiwyg" name="plain_text_content" id="' . $uniqueId . '-wiz-wysiwyg" data-editor-mode="' . $editorMode . '">' . $existingContent . '</textarea>';

            break;
        case 'html':
            $existingContent = isset($chunkData['fields']['raw_html_content']) ? $chunkData['fields']['raw_html_content'] : '<p>Enter your HTML here...</p>';
            echo '<textarea class="wiz-html-block" name="raw_html_content" id="' . $uniqueId . '-raw-html">' . $existingContent . '</textarea>';

            break;
        case 'image':
            $imageUrl = $chunkData['fields']['image_url'] ?? 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/full-width-image.jpg';
            $darkModeImageUrl = $chunkData['fields']['dark_mode_image_url'] ?? '';
            $imageLink = $chunkData['fields']['image_link'] ?? 'https://www.idtech.com';
            $imageAlt = $chunkData['fields']['image_alt'] ?? '';

            echo "<div class='builder-field-group flex'>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-image-url'>Image URL</label><input type='text' name='image_url' id='{$uniqueId}-image-url' value='{$imageUrl}' placeholder='https://'></div>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-dark-mode-image-url'>Dark Mode Image URL</label><input type='text' name='dark_mode_image_url' id='{$uniqueId}-dark-mode-image-url' value='{$darkModeImageUrl}' placeholder='https://'></div>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-image-link'>Image Link</label><input type='text' name='image_link' id='{$uniqueId}-image-link' value='{$imageLink}' placeholder='https://'></div>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-image-alt'>Image Alt</label><input type='text' name='image_alt' id='{$uniqueId}-image-alt' value='{$imageAlt}' placeholder='Describe the image or leave blank'></div>";
            echo "</div>"; // close builder-field-group

            break;
        case 'icon-list':
            $imageUrl = $chunkData['fields']['image_url'] ?? 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/full-width-image.jpg';
            $darkModeImageUrl = $chunkData['fields']['dark_mode_image_url'] ?? '';
            $imageLink = $chunkData['fields']['image_link'] ?? 'https://www.idtech.com';
            $imageAlt = $chunkData['fields']['image_alt'] ?? '';
            echo "<div class='builder-field-group flex noWrap'>";
            echo "<div class='builder-field-group icon-list-image-fields'>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-image-url'>Image URL</label><input type='text' name='image_url' id='{$uniqueId}-image-url' value='{$imageUrl}' placeholder='https://'></div>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-dark-mode-image-url'>Dark Mode Image URL</label><input type='text' name='dark_mode_image_url' id='{$uniqueId}-dark-mode-image-url' value='{$darkModeImageUrl}' placeholder='https://'></div>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-image-link'>Image Link</label><input type='text' name='image_link' id='{$uniqueId}-image-link' value='{$imageLink}' placeholder='https://'></div>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-image-alt'>Image Alt</label><input type='text' name='image_alt' id='{$uniqueId}-image-alt' value='{$imageAlt}' placeholder='Describe the image or leave blank'></div>";
            echo "</div>";
            echo "<div class='builder-field-group'>";
            $existingContent = isset($chunkData['fields']['plain_text_content']) ? $chunkData['fields']['plain_text_content'] : 'Enter your content here...';
            $editorMode = $chunkData['editor_mode'] ?? 'light';
            echo '<textarea class="wiz-wysiwyg" name="plain_text_content" id="' . $uniqueId . '-wiz-wysiwyg" data-editor-mode="' . $editorMode . '">' . $existingContent . '</textarea>';
            echo "</div>";
            echo "</div>";
            break;
        case 'button':
            $buttonBgColor = $chunkData['fields']['button_fill_color'] ?? '#343434';
            $buttonFontSize = $chunkData['fields']['button_font_size'] ?? '1.1em';
            $buttonTextColor = $chunkData['fields']['button_text_color'] ?? '#ffffff';
            $buttonBorderColor = $chunkData['fields']['button_border_color'] ?? '#343434';
            $buttonBorderSize = $chunkData['fields']['button_border_size'] ?? '1px';
            $buttonBorderRadius = $chunkData['fields']['button_border_radius'] ?? '30px';
            $buttonPadding = $chunkData['fields']['button_padding'] ?? '10px 80px';
            $buttonAlign = $chunkData['fields']['button_align'] ?? 'center';

            $buttonLink = $chunkData['fields']['button_link'] ?? 'https://www.idtech.com';
            $buttonCta = htmlspecialchars($chunkData['fields']['button_text'] ?? 'Click Here', ENT_QUOTES);

            // Button Background Color
            echo "<div class='builder-field-group button-options-group flex'>";
            echo "<div class='builder-field-wrapper background-color'><label for='{$uniqueId}-button-background-color'>Fill</label>";
            echo "<input class='builder-colorpicker' type='color' name='button_fill_color' id='{$uniqueId}-button-background-color' data-color-value='{$buttonBgColor}'>";
            echo "</div>";

            // Button Text Color
            echo "<div class='builder-field-wrapper button-text-color'><label for='{$uniqueId}-button-text-color'>Text</label>";
            echo "<input class='builder-colorpicker' type='color' name='button_text_color' id='{$uniqueId}-button-text-color' data-color-value='{$buttonTextColor}' value='{$buttonTextColor}'>";
            echo "</div>";

            // Button Padding
            echo "<div class='builder-field-wrapper button-padding small-input'><label for='{$uniqueId}-button-padding'>Btn Padding</label>";
            echo "<input type='text' name='button_padding' id='{$uniqueId}-button-padding' value='{$buttonPadding}'>";
            echo "</div>";

            // Button Font Size
            echo "<div class='builder-field-wrapper button-font-size tiny-input'><label for='{$uniqueId}-button-font-size'>Font Size</label>";
            echo "<input type='text' name='button_font_size' id='{$uniqueId}-button-font-size' value='{$buttonFontSize}'>";
            echo "</div>";

            // Alignment Options
            echo "<div class='button-group-wrapper builder-field-wrapper button-align'><label class='button-group-label'>Align</label>";
            echo "<div class='button-group radio'>";

            $alignOptions = [
                ['id' => $uniqueId . '_btn_align_left', 'value' => 'left', 'label' => '<i class="fa-solid fa-align-left"></i>', 'checked' => 'checked'],
                ['id' => $uniqueId . '_btn_align_center', 'value' => 'center', 'label' => '<i class="fa-solid fa-align-center"></i>'],
                ['id' => $uniqueId . '_btn_align_right', 'value' => 'right', 'label' => '<i class="fa-solid fa-align-right"></i>'],


            ];

            foreach ($alignOptions as $opt) {
                $isChecked = isset($buttonAlign) && $buttonAlign === $opt['value'] ? 'checked' : '';
                $fieldID = $opt['id'];
                $label = $opt['label'];
                $value = $opt['value'];

                echo "<input type='radio' id='{$fieldID}' name='button_align' value='{$value}' hidden {$isChecked}>";
                echo "<label for='{$fieldID}' class='button-label'>{$label}</label>";
            }

            echo "</div>";

            echo "</div>";

            // Button Border Color
            echo "<div class='builder-field-wrapper button-border-color'><label for='{$uniqueId}-button-border-color'>Stroke</label>";
            echo "<input class='builder-colorpicker' type='color' name='button_border_color' id='{$uniqueId}-button-border-color' data-color-value='{$buttonBorderColor}' value='{$buttonBorderColor}'>";
            echo "</div>";

            // Button Border Size
            echo "<div class='builder-field-wrapper button-border-size tiny-input'><label for='{$uniqueId}-button-border-size'>Border</label>";
            echo "<input type='text' name='button_border_size' id='{$uniqueId}-button-border-size' value='{$buttonBorderSize}'>";
            echo "</div>";

            // Button Border Radius
            echo "<div class='builder-field-wrapper button-border-radius tiny-input'><label for='{$uniqueId}-button-border-radius'>Radius</label>";
            echo "<input type='text' name='button_border_radius' id='{$uniqueId}-button-border-radius' value='{$buttonBorderRadius}'>";
            echo "</div>";



            echo "</div>";
            echo "<div class='builder-field-group flex'>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-button-text'>CTA Text</label><input type='text' name='button_text' id='{$uniqueId}-button-text' value='{$buttonCta}' placeholder='Click here now!'></div>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-button-link'>Button Link</label><input type='text' name='button_link' id='{$uniqueId}-button-link' value='{$buttonLink}' placeholder='https://'></div>";
            echo "</div>";

            break;
        case 'spacer':
            $spacerHeight = $chunkData['fields']['spacer_height'] ?? '60px';

            echo "<div class='builder-field-group'>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-spacer-height'>Spacer Height</label><input type='text' name='spacer_height' id='{$uniqueId}-spacer-height' value='{$spacerHeight}' placeholder='px, em, etc'></div>";
            echo "</div>"; // Close builder-field-group

            break;
        case 'snippet':
            $selectedSnippet = $chunkData['fields']['select_snippet'] ?? '';
            $snippetEditLink = get_permalink($selectedSnippet) ?? get_post_type_archive_link('wysiwyg_snippet');

            echo "<div class='builder-field-group flex'>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-snippet-id'>Select Snippet</label>";
            echo "<select id='{$uniqueId}-snippet-id' name='select_snippet' data-also-update-head='true'>";
            $snippetsForSelect = get_snippets_for_select();
            $noSelectionSelected = $selectedSnippet ? '' : 'selected';
            echo "<option value='' {$noSelectionSelected} disabled>Select a Snippet</option>";
            foreach ($snippetsForSelect as $snippetId => $snippetTitle) {
                echo "<option value='{$snippetId}' " . ($selectedSnippet == $snippetId ? 'selected' : '') . ">{$snippetTitle}</option>";
            }
            echo "</select>";
            echo "</div>";
            $showEditLinkClass = 'hidden';
            if ($selectedSnippet) {
                $showEditLinkClass = 'visible';
            }
            echo "<div class='snippet-edit-link $showEditLinkClass'><a href='" . $snippetEditLink . "' target='_blank'>Edit Snippet</a></div>";
            echo "</div>"; // Close chunk-field-group

            break;
        case 'interactive':
            $selectedInt = $chunkData['fields']['select_interactive'] ?? '';
            $IntEditLink = get_permalink($selectedInt) ?? get_post_type_archive_link('wysiwyg_interactive');

            echo "<div class='builder-field-group flex'>";
            echo "<div class='builder-field-wrapper'><label for='{$uniqueId}-interactive-id'>Select Interactive</label>";
            echo "<select id='{$uniqueId}-interactive-id' name='select_interactive' data-also-update-head='true'>";
            $intsForSelect = get_interactives_for_select();
            $noSelectionSelected = $selectedInt ? '' : 'selected';
            echo "<option value='' {$noSelectionSelected} disabled>Select an Interactive</option>";
            foreach ($intsForSelect as $intId => $intTitle) {
                echo "<option value='{$intId}' " . ($selectedInt == $intId ? 'selected' : '') . ">{$intTitle}</option>";
            }
            echo "</select>";
            echo "</div>";
            $showEditLinkClass = 'hidden';
            if ($selectedInt) {
                $showEditLinkClass = 'visible';
            }
            echo "<div class='snippet-edit-link $showEditLinkClass'><a href='" . $IntEditLink . "' target='_blank'>Edit Interactive</a></div>";
            echo "</div>"; // Close chunk-field-group

            break;
        default:
            echo "No valid chunk type set!";
            break;
    }
    echo "</form>";
    echo "</div>"; // Close chunk-inner-content
}


function generate_background_settings_module($backgroundSettings, $uniqueId = '', $typeLabel = true, $previewPart = false)
{
    // If no unique ID is passed, generate one for use in ID/label attributes for repeated field names (like background settings)
    $uniqueTempId = $uniqueId != '' ? $uniqueId : '_' . uniqid();
    //echo ('Chunk Data for '.$uniqueId.': '. print_r($chunkData, true));
    $chunkBackgroundType = $backgroundSettings[$uniqueId . 'background-type'] ?? 'none';
    $chunkBackgroundColor = $backgroundSettings[$uniqueId . 'background-color'] ?? '#ffffff';
    $forceBackground = isset($backgroundSettings[$uniqueId . 'force-background']) && $backgroundSettings[$uniqueId . 'force-background'] == true;

    // Background Type Options
    $backgroundOptions = [
        ['id' => $uniqueTempId . 'bg-none', 'value' => 'none', 'label' => '<i class="fas fa-ban"></i> None', 'checked' => 'checked'],
        ['id' => $uniqueTempId . 'bg-solid', 'value' => 'solid', 'label' => '<i class="fas fa-fill"></i> Solid'],
        ['id' => $uniqueTempId . 'bg-image', 'value' => 'image', 'label' => '<i class="fas fa-image"></i> Image'],
        ['id' => $uniqueTempId . 'bg-custom', 'value' => 'custom', 'label' => '<i class="fa-solid fa-code"></i> Custom'],
    ];

    ob_start();
?>
    <div class='chunk-settings-section chunk-background-settings'>

        <div class="chunk-background-type-wrapper">
            <div class='button-group-wrapper chunk-background-type'>
                <?php
                if ($typeLabel) { ?>
                    <label class="button-group-label">Background Type</label>
                <?php } ?>
                <div class="button-group radio">
                    <?php foreach ($backgroundOptions as $opt) : ?>
                        <?php
                        // Check if this option is selected
                        $isChecked = isset($chunkBackgroundType) && $chunkBackgroundType === $opt['value'] ? 'checked' : '';
                        $fieldID = $opt['id'];
                        $previewPartDataAttr = $previewPart ? 'data-preview-part="' . $previewPart . '"' : '';

                        ?>
                        <input type='radio' id='<?php echo $fieldID; ?>' name='<?php echo $uniqueId . 'background-type'; ?>' <?php echo $previewPartDataAttr; ?>
                            value='<?php echo $opt['value']; ?>' hidden <?php echo $isChecked; ?>
                            class="background-type-select">
                        <label class="button-label" for='<?php echo $fieldID; ?>'>
                            <?php echo $opt['label']; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php
        $showClass = 'hidden';
        if (isset($chunkBackgroundType) && $chunkBackgroundType != 'none') {
            $showClass = '';
        }
        ?>
        <div class='chunk-settings-section chunk-background-color-settings <?php echo $showClass; ?>'>
            <label>Background Color</label>
            <div class='background-color'>
                <div class="builder-field-wrapper background-color"><label
                        for="<?php echo $uniqueId . 'background-color'; ?>"></label>
                    <input class="builder-colorpicker" type="color" name="<?php echo $uniqueId . 'background-color'; ?>" <?php echo $previewPartDataAttr; ?>
                        id="<?php echo $uniqueId . 'background-color'; ?>"
                        data-color-value="<?php echo $chunkBackgroundColor; ?>">
                </div>
                <div class="builder-field-wrapper">

                    <div class="wiz-checkbox-toggle">
                        <input type="checkbox" class="wiz-check-toggle" id="<?php echo $uniqueId . 'force-background'; ?>" <?php echo $previewPartDataAttr; ?>
                            name="<?php echo $uniqueId . 'force-background'; ?>" hidden <?php echo $forceBackground ? 'checked' : ''; ?>>
                        <label for="<?php echo $uniqueId . 'force-background'; ?>"
                            class="wiz-check-toggle-display <?php echo $forceBackground ? 'active' : ''; ?>"><i
                                class="<?php echo $forceBackground ? 'fa-solid' : 'fa-regular'; ?> fa-2x fa-square-check"></i></label>
                        <label class="checkbox-toggle-label">Force BG in all modes</label>
                    </div>
                </div>
            </div>

        </div>
        <?php
        $showClass = 'hidden';
        if (
            isset($chunkBackgroundType) && $chunkBackgroundType == 'image'
        ) {
            $showClass = '';
        }
        ?>

        <div class='chunk-settings-section chunk-background-image-settings <?php echo $showClass; ?>'>

            <label>Background Image</label>
            <div class="chunk-settings-section-fields flex">

                <div class="builder-field-wrapper chunk-background-image-url">
                    <label for="<?php echo $uniqueId . 'background-image-url'; ?>">Image URL</label>
                    <input type="text" name="<?php echo $uniqueId . 'background-image-url'; ?>" <?php echo $previewPartDataAttr; ?>
                        id="<?php echo $uniqueId . 'background-image-url'; ?>" class="builder-text-input"
                        value="<?php echo $backgroundSettings[$uniqueId . 'background-image-url'] ?? ''; ?>"
                        placeholder="https://...">
                </div>
                <div class="builder-field-wrapper chunk-background-image-position">
                    <label for="<?php echo $uniqueId . 'background-image-position'; ?>">Position</label>
                    <input type="text" name="<?php echo $uniqueId . 'background-image-position'; ?>" <?php echo $previewPartDataAttr; ?>
                        id="<?php echo $uniqueId . 'background-image-position'; ?>" class="builder-text-input"
                        value="<?php echo $backgroundSettings[$uniqueId . 'background-image-position'] ?? ''; ?>"
                        placeholder="eg center center">
                </div>
                <div class="builder-field-wrapper chunk-background-image-size">
                    <label for="<?php echo $uniqueId . 'background-image-size'; ?>">Size</label>
                    <input type="text" name="<?php echo $uniqueId . 'background-image-size'; ?>" <?php echo $previewPartDataAttr; ?>
                        id="<?php echo $uniqueId . 'background-image-size'; ?>" class="builder-text-input"
                        value="<?php echo $backgroundSettings[$uniqueId . 'background-image-size'] ?? ''; ?>"
                        placeholder="eg 100% 100%">
                </div>

                <?php
                $imageRepeatOptions = [
                    ['id' => $uniqueId . 'bg-repeat-horizontal', 'value' => 'repeat-x', 'label' => '<i class="fa-solid fa-left-right"></i>'],
                    ['id' => $uniqueId . 'bg-repeat-vertical', 'value' => 'repeat-y', 'label' => '<i class="fa-solid fa-up-down"></i>']
                ];
                ?>

                <div class='button-group-wrapper builder-field-wrapper chunk-background-image-repeat'>
                    <label class="button-group-label">Repeat</label>
                    <div class="button-group checkbox">
                        <?php foreach ($imageRepeatOptions as $opt) : ?>
                            <?php
                            $fieldID = $opt['id'];

                            $isChecked = isset($backgroundSettings[$fieldID]) && $backgroundSettings[$fieldID] ? 'checked' : '';

                            ?>
                            <input type='checkbox' id='<?php echo $uniqueTempId . $fieldID; ?>' name='<?php echo $fieldID; ?>' <?php echo $previewPartDataAttr; ?>
                                value='<?php echo $opt['value']; ?>' <?php echo $isChecked; ?>>
                            <label for='<?php echo $uniqueTempId . $fieldID; ?>' class='button-label'>
                                <?php echo $opt['label']; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>


            </div>
        </div>

        <?php
        $showClass = 'hidden';
        if (isset($chunkBackgroundType) && $chunkBackgroundType == 'custom') {
            $showClass = '';
        }
        ?>
        <div class="chunk-settings-section chunk-background-custom-settings <?php echo $showClass;
                                                                            ?>">
            <label for="<?php echo $uniqueId . 'custom-background-css'; ?>">Custom Background CSS</label>
            <div class="field-description"><strong style="font-style:normal;">Key:value;</strong> pairs, e.g., background-color:red;</div>
            <div class="chunk-settings-section-fields">
                <textarea name="<?php echo $uniqueId . 'custom-background-css'; ?>" class="custom-background-css-input" id="<?php echo $uniqueId . 'custom-background-css'; ?>" <?php echo $previewPartDataAttr; ?>><?php echo isset($backgroundSettings[$uniqueId . 'custom-background-css']) ? trim($backgroundSettings[$uniqueId . 'custom-background-css']) : ''; ?></textarea>
            </div>
        </div>
    </div>

<?php

    return ob_get_clean();
}





function get_template_data_modal()
{
    ob_start();
?>
    <div id="template-data-modal">
        <div class="inner-flex">
            <div id="template-data-modal-header">
                <h4>Template Data</h4><i class="fa-solid fa-xmark close-modal"></i>
            </div>

            <form id="templateDataForm">
                <div class="template-data-form-wrap">
                    <div class="template-data-form-fieldset presetSelect">
                        <label for="dataPresetSelect">Select preset profile</label>
                        <select id="dataPresetSelect" name="dataPreset">
                            <option value="" disabled selected>Select a profile</option>
                            <?php
                            $presetProfiles = get_template_data_profiles();
                            foreach ($presetProfiles as $profile) {
                                echo '<option value="' . $profile['WizProfileId'] . '">' . $profile['WizProfileName'] . '</option>';
                            }
                            ?>

                        </select>
                    </div>
                    <div class="template-data-form-fieldset jsonData">
                        <label for="templateData">JSON data</label>
                        <textarea id="templateData" name="template_data" class="templateData" placeholder="{}" rows="5"></textarea>
                    </div>
                </div>
            </form>

        </div>
    </div>
<?php
    return ob_get_clean();
}
