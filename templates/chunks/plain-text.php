<?php 
$templateBgColor = $templateStyles['template_bg_color'] ?? '#F4F4F4'; 

$chunkSettings = $chunk['chunk_settings'];
$textContent = $chunk['plain_text_content'] ?? 'Your content goes here!';

$contentAlign = $chunkSettings['align_content'] ?? 'center';
$textColor = $chunkSettings['text_color'] ?? '#000000';
$bgColor = $chunkSettings['background_color'] ?? '#FFFFFF';
$spacing = $chunkSettings['spacing'] ?? $defaultSettings['spacing'];
$topSpacing = false;
$btmSpacing = false;
if (in_array('top',$spacing)) {
$topSpacing = true;
}
if (in_array('bottom',$spacing)) {
$btmSpacing = true;
}
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

<!-- Plain Text -->
<table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width: 100%; max-width: 100%;" class="<?php echo $hideMobile.' '.$hideDesktop; ?>">
  <tr>
    <td align="center" valign="top">
      <table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" class="row" style="width: 100%; max-width: 100%;">
        <tr>
          <td class="body-bg-color" align="center" valign="top" bgcolor="<?php echo $templateBgColor; ?>">
            <table role="presentation" border="0" width="800" align="center" cellpadding="0" cellspacing="0" class="row" style="width: 800px; max-width: 800px;">
              <tr>
                <td class="bg-color" align="center" valign="top" bgcolor="<?php echo $bgColor; ?>">
                  <table role="presentation" width="800" border="0" cellpadding="0" cellspacing="0" align="center" class="row" style="width: 800px; max-width: 800px;">
                    <tr>
                      <td align="center" valign="top" class="container-padding">
                        <table role="presentation" border="0" width="100%" cellpadding="0" cellspacing="0" align="center" style="width: 100%; max-width: 100%;">
                          <tr>
                            <td class="text responsive-text <?php echo $contentAlign; ?>-text" valign="middle" align="<?php echo $contentAlign; ?>" style="font-family:Poppins, sans-serif;color:<?php echo $textColor; ?>!important;text-decoration:none;">
<?php if($topSpacing) {?>
                              <!-- Optional Top Space -->
                              <table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:<?php echo $bgColor; ?>;">
                                <tr>
                                  <td class="space-control" valign="middle" align="center" height="20"></td>
                                </tr>
                              </table>
							  <!-- / End Optional Top Space -->
<?php } ?>
                              
                              <!-- Chunk Content Start -->
                              <?php echo idwiz_pReplace(wpautop($textContent)); ?>
                              <!-- / End Chunk Content -->
                              
<?php if($btmSpacing) {?>
                              <!-- Optional Bottom Space -->
                              <table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:<?php echo $bgColor; ?>;">
                                <tr>
                                  <td class="space-control" valign="middle" align="center" height="20"></td>
                                </tr>
                              </table>
                              <!-- / End Optional Bottom Space -->
<?php } ?>
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
<!-- /Plain Text -->
