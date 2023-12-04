<?php 
$finePrintDisclaimer = $templateSettings['fine_print_disclaimer'] ?? ''; 
if ($finePrintDisclaimer) { 
    echo '<center style="font-size:12px !important;color:#444444;line-height:16px;">'.$finePrintDisclaimer. '</center>';
}
?>
