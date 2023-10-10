<?php
$templateBgColor = $templateStyles['template_bg_color'] ?? '#F4F4F4';

$chunkSettings = $chunk['chunk_settings'];

$useMobileAlt = $chunk['mobile_image'] ?? false;

$desktopImage = $chunk['desktop_image_url'] ?? '';
$mobileImage = $chunk['mobile_image_url'] ?? '';

$imageLink = !empty($chunkSettings['image_link']) ? $chunkSettings['image_link'] : 'https://www.idtech.com';
$imageAltTag = $chunkSettings['alt_tag'] ?? '';

if (isset($chunk['mobile_image']) && $chunk['mobile_image'] == true) {
  $dtClass = 'hide-mobile';
} else {
  $dtClass = '';
}

$showOnDesktop = $chunkSettings['desktop_visibility'] ?? true;
if ($showOnDesktop == false){
  $hideDesktop = 'hide-desktop';
} else {
  $hideDesktop = '';
}
$showOnMobile = $chunkSettings['mobile_visibility'] ?? true;
if ($showOnMobile == false){
  $hideMobile = 'hide-mobile';
} else {
  $hideMobile = '';
}
?>

<!-- Contained-Width Image with Maybe Mobile Alt -->
<table border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;" class="<?php echo $hideMobile.' '.$hideDesktop; ?>">
  <tr>
    <td align="center" valign="top">
      <table border="0" width="100%" align="center" cellpadding="0" cellspacing="0" class="row" style=
        "width:100%;max-width:100%;">
        <tr>
          <td class="body-bg-color" align="center" valign="top" bgcolor="<?php echo $templateBgColor; ?>">
            <table border="0" width="800" align="center" cellpadding="0" cellspacing="0" class="row" style=
              "width:800px;max-width:800px;">
              <tr>
                <td class="bg-color" align="center" valign="top" bgcolor="#FFFFFF">

                 <!--Desktop Image-->
                 <!-- The .hide-mobile class is conditionally present and only appears when a mobile asset is included -->
                  <table class="<?php echo $dtClass; ?>" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" style=
                    "width:100%;max-width:100%;">
                    <tr>
                      <td align="center" valign="top" class="img-responsive img600">
                        <a href="<?php echo $imageLink; ?>"><img style=
                          "display:block;width:100%;max-width:600px;display:block;border:0px;" src=
                          "<?php echo $desktopImage; ?>"
                          width="600" border="0" alt=
                          "<?php echo $imageAltTag; ?>" /></a>
                      </td>
                    </tr>
                  </table>
                  <!-- /End Desktop Image-->
                  
<?php if ($useMobileAlt) { ?>
                  <!--Mobile Image-->
                  <!-- the .hide-desktop class is always present on the mobile image since we'll never want to show mobile images on desktop -->
                  <!-- If Outlook (mso), always exclude the mobile image since it lacks @media support-->
                  <!--[if !mso]><!-->
                  <table class="hide-desktop" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" style=
                    "width:100%;max-width:100%; display: none; height:0;overflow:auto;">
                    <tr>
                      <td align="center" valign="top" class="img-responsive img600">
                        <a href="<?php echo $imageLink; ?>"><img style=
                          "display:block;width:100%;max-width:600px;display:block;border:0px;" src=
                          "<?php echo $mobileImage; ?>"
                          width="600" border="0" alt=
                          "<?php echo $imageAltTag; ?>" /></a>
                      </td>
                    </tr>
                  </table>
                   <!--<![endif]-->
                  <!-- /End Mobile Image-->
                  
<?php } ?>
                </td>
              </tr>
            </table>
        </tr>
        </td>
      </table>
    </td>
  </tr>
</table>
<!-- /Contained-Width Image with Maybe Mobile Alt -->
