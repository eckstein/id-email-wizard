<?php
$onRepo = false;
if (is_page('366')) {
	$onRepo = true;
}
$dtImage = ( get_sub_field('desktop_image_url') != '' ) ? get_sub_field('desktop_image_url') : 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/contained-width-image.jpg';
if (get_sub_field('mobile_image') == true || $onRepo == true) {
	$mobImage = ( get_sub_field('mobile_image_url') != '' ) ? get_sub_field('mobile_image_url') : 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/full-width-mobile-image.jpg';
}
$altTag = ( get_sub_field('alt_tag') != '' ) ? get_sub_field('alt_tag') : '';
$imageLink = ( get_sub_field('image_link') != '' ) ? get_sub_field('image_link') : 'https://www.idtech.com';
$mobileVis = get_sub_field('mobile_visibility');

$dtClass = '';
if (get_sub_field('mobile_image') == true || $onRepo == true) {
	$dtClass = 'hide-mobile';
} else {
	$dtClass = '';
}
//if we're hiding the whole block on mobile
$hideMobile = '';
if ($mobileVis == false) {
	$hideMobile = 'hide-mobile';
}
?>

<!-- Contained-Width Image with Maybe Mobile Alt -->
<table border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;" class="<?php echo $hideMobile; ?>">
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
                          "<?php echo $dtImage; ?>"
                          width="600" border="0" alt=
                          "<?php echo $altTag; ?>" /></a>
                      </td>
                    </tr>
                  </table>
                  <!-- /End Desktop Image-->
                  
<?php if (get_sub_field('mobile_image') == true || $onRepo == true) { ?>
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
                          "<?php echo $mobImage; ?>"
                          width="600" border="0" alt=
                          "<?php echo $altTag; ?>" /></a>
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
