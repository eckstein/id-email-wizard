<?php

$ctaText = ( get_sub_field('cta_text') != '' ) ? get_sub_field('cta_text') : 'Click Here';
$ctaUrl = ( get_sub_field('cta_url') != '' ) ? get_sub_field('cta_url') : 'https://www.idtech.com';
$chunkSettings = get_sub_field('chunk_settings');
	$bgColor = $chunkSettings['button_background_color'] ?? '#94d500';
	$chunkBgColor = $chunkSettings['chunk_background_color'] ?? '#FFFFFF';
	$textColor = $chunkSettings['text_color'] ?? '#FFFFFF';
	$borderColor = $chunkSettings['border_color'] ?? '#94d500';
	$borderSize = $chunkSettings['border_size'] ?? '1px';
	$borderRad = $chunkSettings['border_radius'] ?? '3px';
	$mobileVis = $chunkSettings['mobile_visibility'] ?? true;
	$spacing = $chunkSettings['spacing'] ?? array('top','bottom');
	$hideMobile = '';
	if ($mobileVis == false) {
		$hideMobile = 'hide-mobile';
	}
	$topSpacing = false;
	$btmSpacing = false;
	if (in_array('top',$spacing)) {
		$topSpacing = true;
	}
	if (in_array('bottom',$spacing)) {
	$btmSpacing = true;
}
?>

<!-- Button -->
<table border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;" class="iDbutton <?php echo $hideMobile; ?>">
  <tbody>
    <tr>
      <td class="text-2" align="center" valign="top">
        <table border="0" width="100%" align="center" cellpadding="0" cellspacing="0" class="row" style="width:100%;max-width:100%;">
          <tbody>
            <tr>
              <td class="body-bg-color" align="center" valign="top" bgcolor="#F4F4F4">
                <table border="0" width="800" align="center" cellpadding="0" cellspacing="0" class="row" style="width:800px;max-width:800px;">
                  <tbody>
                    <tr>
                      <td class="bg-color" align="center" valign="top" bgcolor="<?php echo $chunkBgColor; ?>">
                        <table width="600" border="0" cellpadding="0" cellspacing="0" align="center" class="row" style="width:600px;max-width:600px;">
                          <tbody>
                            <tr>
                              <td align="center" valign="top" class="container-padding">
                                <!-- Button Wrap Start-->
                                <table border="0" cellpadding="0" cellspacing="0" align="center" class="center-float" style="display:inline-block;vertical-align:middle;">
                                  <tbody>
                                    <tr>
                                      <td align="center" valign="top">
                                        <table border="0" width="100%" cellpadding="0" cellspacing="0" align="center" style="width:100%;max-width:100%;">
                                          <tbody>
                                            <tr>
                                              <td valign="middle" align="center" style="font-family:Roboto, Arial, Helvetica, sans-serif;font-size:14px;line-height:19px;font-weight:bolder;font-style:normal;color:<?php echo $textColor; ?>;text-decoration:none;letter-spacing:0px;">
<?php if($topSpacing) {?>
                                               <!-- Optional Top Space -->
                                               <table border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:<?php echo $chunkBgColor; ?>;">
                                                  <tbody>
                                                    <tr>
                                                      <td class="space-control" valign="middle" align="center" height="20">
                                                      </td>
                                                    </tr>
                                                  </tbody>
                                                </table>
                                               <!-- / End Optional Top Space -->
<?php } ?>
                                               
                                                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                                  <tbody>
                                                    <tr>
                                                      <td>
                                                        <table border="0" cellspacing="0" cellpadding="0" align="center">
                                                          <tbody>
                                                            <tr style="color:<?php echo $textColor; ?>;">
                                                            
                                                            <!--Button Content Start-->
                                                              <td align="center" bgcolor="<?php echo $bgColor; ?>" style="border-radius:<?php echo $borderRad; ?>; color:<?php echo $textColor; ?>;">
                                                              <a target="_blank"
                                                                class="button-link" 
                                                                style="
                                                                  font-size:19px;font-family:Poppins, sans-serif;line-height:24px;font-weight: bold;text-decoration:none;
                                                                  display:inline-block;padding:14px 30px;
                                                                  color:<?php echo $textColor; ?>;
                                                                  border-radius:<?php echo $borderRad; ?>;
                                                                  border:<?php echo $borderSize; ?> solid <?php echo $borderColor; ?>;
                                                                "
                                                                href="<?php echo $ctaUrl; ?>">
                                                                <span style="color:<?php echo $textColor; ?>;"><?php echo $ctaText; ?></span>
                                                              </a>
                                                              </td>
                                                            <!--/End Button Content-->
                                                            
                                                            </tr>
                                                          </tbody>
                                                        </table>
                                                      </td>
                                                    </tr>
                                                  </tbody>
                                                </table>
<?php if($btmSpacing) {?>
                                               <!-- Optional Bottom Space -->
                                               <table border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:<?php echo $chunkBgColor; ?>;">
                                                  <tbody>
                                                    <tr>
                                                      <td class="space-control" valign="middle" align="center" height="20">
                                                      </td>
                                                    </tr>
                                                  </tbody>
                                                </table>
                                               <!-- / End Optional Bottom Space -->
<?php } ?>
                                              </td>
                                            </tr>
                                          </tbody>
                                        </table>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                                <!-- /End Button Wrap-->
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
  </tbody>
</table>
<!-- /Button -->