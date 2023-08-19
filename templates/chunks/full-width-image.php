<?php
$chunkSettings = $chunk['chunk_settings'];

$useMobileAlt = $chunk['mobile_image'] ?? false;

$desktopImage = $chunk['desktop_image_url'] ?? '';
$mobileImage = $chunk['mobile_image_url'] ?? '';

$imageLink = $chunk['image_link'] ?? '';
$imageAltTag = $chunk['alt_tag'] ?? '';

//conditionally apply hide-mobile class to desktop image if mobile image is enabled
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

<!-- Full Width Image with Maybe Mobile Alt -->
<table border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;" class="<?php echo $hideMobile . ' ' . $hideDesktop; ?>">
  <tr>
    <td align="center" valign="top">
      <table border="0" width="100%" align="center" cellpadding="0" cellspacing="0" class="row" style=
        "width:100%;max-width:100%;">
        <tr>
          <td class="body-bg-color" align="center" valign="top" bgcolor="#F4F4F4">
            <table border="0" width="800" align="center" cellpadding="0" cellspacing="0" class="row" style=
              "width:800px;max-width:800px;">
              <tr>
                <td class="bg-color" align="center" valign="top" bgcolor="#FFFFFF">

                  <!--Desktop Image-->
                 <!-- The .hide-mobile class is conditionally present, 
                  and only appears when a mobile asset is included -->
                  <table class="<?php echo $dtClass; ?>" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" style=
                    "width:100%;max-width:100%;">
                    <tr>
                      <td align="center" valign="top" class="img-responsive img800">
                        <a href="<?php echo $imageLink; ?>"><img style=
                          "display:block;width:100%;max-width:800px;display:block;border:0px;" src=
                          "<?php echo $desktopImage; ?>"
                          width="800" border="0" alt=
                          "<?php echo $imageAltTag; ?>" /></a>
                      </td>
                    </tr>
                  </table>
                  <!-- /End Desktop Image-->

<?php if ($useMobileAlt) { ?>
                  <!--Mobile Image-->
                  <!-- the .hide-desktop class is always present since we'll never want to show mobile images on desktop -->
                  <!-- If Outlook (mso), always exclude the mobile image since it lacks @media support-->
                  <!--[if !mso]><!-->
                  <table class="hide-desktop" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" style=
                    "width:100%;max-width:100%; display: none; height:0;overflow:auto;">
                    <tr>
                      <td align="center" valign="top" class="img-responsive img800">
                        <a href="<?php echo $imageLink; ?>"><img style=
                          "display:block;width:100%;max-width:800px;display:block;border:0px;" src=
                          "<?php echo $mobileImage; ?>"
                          width="800" border="0" alt=
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
        </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<!-- /Full Width Image with Maybe Mobile Alt -->
