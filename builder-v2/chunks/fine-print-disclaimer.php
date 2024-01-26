<!--[if mso]> 
<table role="presentation" align="center" style="width:780px;"> 
<tr> 
<td style="background-color: #efefef; padding-top: 20px; padding-bottom: 20px; font-size: 12px;"> 
<![endif]-->
<div
    style="background-color: #efefef; width: 100%; max-width: 780px; padding-top: 10px; padding-bottom: 20px; font-size: 12px;">
    <?php 
$finePrintDisclaimer = $templateSettings['fine_print_disclaimer'] ?? ''; 
if ($finePrintDisclaimer) { 
    echo '<center style="font-size:12px !important;color:#444444;line-height:16px;">'.$finePrintDisclaimer. '</center>';
}
?>
</div>
<!--[if mso]> 
</td>
</tr>
</table>
<![endif]-->