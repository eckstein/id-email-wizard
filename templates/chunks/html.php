<?php
$templateBgColor = $templateStyles['template_bg_color'] ?? '#F4F4F4';

$chunkSettings = $chunk['chunk_settings'];

$useWrapper = $chunkSettings['use_wrapper'] ?? false;
if ($useWrapper == true) {
  $bgColor = $chunkSettings['background_color'];
}


$showOnDesktop = $chunkSettings['desktop_visibility'] ?? true;
if (!$showOnDesktop) {
  $hideDesktop = 'hide-desktop';
} else {
  $hideDesktop = '';
}
$showOnMobile = $chunkSettings['mobile_visibility'] ?? true;
if (!$showOnMobile) {
  $hideMobile = 'hide-mobile';
} else {
  $hideMobile = '';
}

//Custom HTML content
$rawHTML = $chunk['raw_html'];

if ($useWrapper == true) { ?>
  <!-- Optional Wrapper -->
  <table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0"
    style="width: 100%; max-width: 100%;" class="<?php echo $hideMobile . ' ' . $hideDesktop; ?>">
    <tr>
      <td align="center" valign="top">
        <table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" class="row"
          style="width: 100%; max-width: 100%;">
          <tr>
            <td class="body-bg-color" align="center" valign="top" bgcolor="<?php echo $templateBgColor; ?>">
              <table role="presentation" border="0" width="800" align="center" cellpadding="0" cellspacing="0" class="row"
                style="width: 800px; max-width: 800px;">
                <tr>
                  <td class="bg-color" align="center" valign="top" bgcolor="<?php echo $bgColor; ?>">
                    <table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" align="center"
                      class="row" style="width: 600px; max-width: 600px;">
                      <tr>
                        <td align="center" valign="top" class="container-padding">
                          <table role="presentation" border="0" width="100%" cellpadding="0" cellspacing="0"
                            align="center" style="width: 100%; max-width: 100%;">
                            <tr>
                              <td class="bg-color responsive-text" align="center" valign="top"
                                bgcolor="<?php echo $bgColor; ?>">
                              <?php } ?>
                              <!-- Custom HTML -->
                              <?php echo $rawHTML; ?>
                              <!-- /Custom HTML -->
                              <?php if ($useWrapper == true) { ?>
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
  <!-- End Optional Wrapper -->
<?php } ?>