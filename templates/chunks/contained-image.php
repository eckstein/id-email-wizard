<?php
$chunkSettings = $chunk['chunk_settings'];

$useMobileAlt = $chunk['mobile_image'] ?? false;

$desktopImage = !empty($chunkSettings['desktop_image_url']) ? $chunkSettings['desktop_image_url'] : 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/contained-width-image.jpg';
$mobileImage = !empty($chunkSettings['mobile_image_url']) ? $chunkSettings['mobile_image_url'] : 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/full-width-mobile-image.jpg';

$imageLink = !empty($chunkSettings['image_link']) ? $chunkSettings['image_link'] : 'https://www.idtech.com';
$imageAltTag = $chunkSettings['alt_tag'] ?? '';

//conditionally apply hide-mobile class to desktop image if mobile image is enabled
if (isset($chunkSettings['mobile_image'])) {
	$dtClass = 'hide-mobile';
} else {
	$dtClass = '';
}

$showOnDesktop = $chunkSettings['desktop_visibility'] ?? true;
if (!$showOnDesktop){
  $hideDesktop = 'hide-desktop';
} else {
  $hideDesktop = '';
}
$showOnMobile = $chunkSettings['mobile_visibility'] ?? true;
if (!$showOnMobile){
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
          <td class="body-bg-color" align="center" valign="top" bgcolor="#F4F4F4">
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
