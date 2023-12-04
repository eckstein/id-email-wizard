<?php
$spacerHeight = $chunk['spacer_height'] ?? '20px';
$spacerBgColor = $chunk['background_color'] ?? '#FFFFFF';

?>
<!--[if mso]>
  <table role="presentation" width="100%">
  <tr>
  <td style="width:100px;padding:10px;text-align:center;" valign="middle">
  <![endif]-->
<div class="spacer" style="padding-left: 10px; padding-right: 10px;">
    <div style="line-height:<?php echo $spacerHeight; ?>;height:<?php echo $spacerHeight; ?>;mso-line-height-rule:exactly;
        background-color:<?php echo $spacerBgColor; ?>"></div>
</div>
 <!--[if mso]>
  </td>
  </tr>
  </table>
  <![endif]-->