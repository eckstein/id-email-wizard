<?php
$ctaText = $chunk['cta_text'] ?? 'Click here';
$ctaUrl = $chunk['cta_url'] ?? 'https://www.idtech.com';
?>

<!--[if mso]>
  <table role="presentation" width="100%">
  <tr>
  <td style="width:100px;padding:10px;text-align:center;" valign="middle">
  <![endif]-->
<div class="button">
    <!--[if mso]>
    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo $ctaUrl; ?>" style="height:40px;v-text-anchor:middle;width:200px;" arcsize="50%" strokecolor="#343434" fillcolor="#343434">
    <w:anchorlock/>
    <center style="color:#ffffff;font-family:sans-serif;font-size:18px;font-weight:bold;">
        <?php echo $ctaText; ?>
    </center>
    </v:roundrect>
    <![endif]-->
    <![if !mso]>
    <p style="margin:0;font-family:Poppins,sans-serif;">
        <a href="<?php echo $ctaUrl; ?>"
            style="background: #343434; border: none; text-decoration: none; padding: 15px 40px; color: #ffffff; border-radius: 50px; display:inline-block; mso-padding-alt:0;text-underline-color:#ffffff">
            <span style="font-weight:bold; font-size: 18px; line-height: 18px;">
                <?php echo $ctaText; ?>
            </span>
        </a>
    </p>
    <![endif]>
</div>


<!--[if mso]>
  </td>
  </tr>
  </table>
  <![endif]-->