<?php
$templateBgColor = $templateStyles['template_bg_color'] ?? '#F4F4F4'; 

$height = $chunk['spacer_height'] ?? '20';
$bgColor = $chunk['background_color'] ?? '#FFFFFF';

$chunkSettings = $chunk['chunk_settings'];

$mobileVis = $chunkSettings['mobile_visibility'] ?? true;
$desktopVis = $chunkSettings['desktop_visibility'] ?? true;
$hideMobile = '';
$hideDesktop = '';
if ($mobileVis == false) {
$hideMobile = 'hide-mobile';
}
if ($desktopVis == false) {
$hideDesktop = 'hide-desktop';
}
?>

<!-- Spacer -->
<table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;" class="<?php echo $hideMobile.' '.$hideDesktop; ?>">
  <tr>
    <td class="text-2" align="center" valign="top">
      <table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" class="row" style="width:100%;max-width:100%;">
        <tr>
          <td class="body-bg-color" align="center" valign="top" bgcolor="<?php echo $templateBgColor; ?>">
            <table role="presentation" border="0" width="800" align="center" cellpadding="0" cellspacing="0" class="row" style="width:800px;max-width:800px;">
              <tr>
                <td class="bg-color" align="center" valign="top" bgcolor="<?php echo $bgColor; ?>">
                  <table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" align="center" class="row" style="width:600px;max-width:600px;">
                    <tr>
                      <td align="center" valign="top" class="container-padding">
                        <table role="presentation" border="0" width="100%" cellpadding="0" cellspacing="0" align="center" style="width:100%;max-width:100%;">
                          <tr>
                            <td>
                              <!-- Start Spacer Fill -->
                              <table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:<?php echo $bgColor; ?>;">
                                <tr>
                                  <td class="space-control" valign="middle" align="center" style="height:<?php echo $height; ?>;" height="<?php echo $height; ?>"></td>
                                </tr>
                              </table>
                              <!-- End Spacer Fill -->
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<!-- /Spacer -->