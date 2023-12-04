<div class="two-col" style="padding-left: 10px; padding-right: 10px; text-align:center;font-size:0;">
    <?php
    $parentChunk = $chunk;
    if (count($parentChunk) === 3) {
        foreach ($parentChunk as $key => $contentField) {
            if (strpos($key, 'field_') === 0) {
                // Extract the field ID from the key
                $parentFieldID = str_replace('acf_fc_layout', '', $key);
                ?>
                <!--[if mso]>
                <table role="presentation" width="100%">
                <tr>
                <td style="width:50%;" valign="middle">
                <![endif]-->
                <div class="column" style="width:100%;max-width:400px;display:inline-block;vertical-align:top;">
                    <?php
                    foreach ($contentField as $rowId => $chunk) {
                        // Include both parent field ID and row index in data attributes
                        ?>
                        <div class="child-chunkWrap" data-parent-field-id="<?php echo $parentFieldID; ?>"
                            data-content-index="<?php echo $rowId; ?>">
                            <div style="font-size:14px;line-height:18px;text-align:left;">
                                <?php
                                // Determine the layout type for each row
                                $layoutType = $chunk['acf_fc_layout'];
                                $chunkFileName = str_replace('_', '-', $layoutType);
                                $file = __DIR__ . '/' . $chunkFileName . '.php'; // Use __DIR__ for the current directory
                                // Check if the file exists and include it
                                if (file_exists($file)) {
                                    ob_start();
                                    include $file;
                                    $html = ob_get_clean();
                                    echo $html; // Output the content of the included file
                                }
                                ?>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <!--[if mso]>
                </td>
                </tr>
                </table>
                <![endif]-->
                <?php
            }
        }
    } else {
        echo "Error: The layout requires exactly two sets of flexible content fields.";
    }
    ?>
</div>