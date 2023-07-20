<?php
//Chunk Settings
$chunkSettings = get_sub_field('chunk_settings');
	$bgColor = $chunkSettings['background_color'] ?? '#FFFFFF';
	$mobileImgSetting = $chunkSettings['mobile_images'] ?? 'alt';
	$layout = $chunkSettings['layout'] ?? 'ltr';
	$magicWrap = $chunkSettings['magic_wrap'] ?? true;
	$layoutDirLeft = 'left';

$mobileVis = get_sub_field('mobile_visibility');
	$hideMobile = '';
	if ($mobileVis == false) {
		$hideMobile = 'hide-mobile';
	}





	$colLeft = '';
	$colRight = '';
switch ($layout) {
	case 'ltr':
		$image = get_sub_field('left_image') ? get_sub_field('left_image') : array('left_image_url'=>'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/square-image.jpg','left_image_link'=>'https://www.idtech.com','left_image_alt'=>'','left_image_url_mobile'=>'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/square-image.jpg');
		$text = get_sub_field('right_text') ? get_sub_field('right_text') : array('text_content'=>'Your content here!','align'=>'center','text_color'=>'#000000','center_on_mobile'=>true);
		$centerOnMobile = $text['center_on_mobile'];
		$colLeft = fillImage($image['left_image_url'],$image['left_image_link'],$image['left_image_alt'],$mobileImgSetting,$image['left_image_url_mobile']);
		$colRight = fillText($text['text_content'],$text['align'],$text['text_color'],$centerOnMobile);
	break;
	case 'rtl':
			$image = get_sub_field('right_image');
			$text = get_sub_field('left_text') ? get_sub_field('left_text') : array('text_content'=>'Your content here!','align'=>'center','text_color'=>'#000000','center_on_mobile'=>true);
			$centerOnMobile = $text['center_on_mobile'];
		if ($magicWrap) {
			$layoutDirLeft = 'right';
			//if magic wrap is on, we render the content from left-to-right but add an align=right on the left column, switching the order. On mobile, it will wrap to one column in the correct order.
			$colLeft = fillImage($image['right_image_url'],$image['right_image_link'],$image['right_image_alt'],$mobileImgSetting,$image['right_image_url_mobile']);
			$colRight = fillText($text['text_content'],$text['align'],$text['text_color'],$centerOnMobile);
		} else {
			$colRight = fillImage($image['right_image_url'],$image['right_image_link'],$image['right_image_alt'],$mobileImgSetting,$image['right_image_url_mobile']);
			$colLeft = fillText($text['text_content'],$text['align'],$text['text_color'],$centerOnMobile);
		}
		
	break;
	case 'img':
		$image = get_sub_field('left_image');
		$image2 = get_sub_field('right_image');
		$colLeft = fillImage($image['left_image_url'],$image['left_image_link'],$image['left_image_alt'],$mobileImgSetting,$image['left_image_url_mobile']);
		$colRight = fillImage($image2['right_image_url'],$image2['right_image_link'],$image2['right_image_alt'],$mobileImgSetting, $image['right_image_url_mobile']);
	break;
	case 'txt':
		$text = get_sub_field('left_text') ? get_sub_field('left_text') : array('text_content'=>'Your content here!','align'=>'center','text_color'=>'#000000','center_on_mobile'=>true);
		$text2 = get_sub_field('right_text') ? get_sub_field('right_text') : array('text_content'=>'Your content here!','align'=>'center','text_color'=>'#000000','center_on_mobile'=>true);
		$centerOnMobileLeft = $text['center_on_mobile'];
		$centerOnMobileRight = $text2['center_on_mobile'];
		$colLeft = fillText($text['text_content'],$text['align'],$text['text_color'],$centerOnMobileLeft);
		$colRight = fillText($text2['text_content'],$text2['align'],$text2['text_color'],$centerOnMobileRight);
	break;
}


?>

<!-- 2x Column -->
<table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;" class="<?php echo $hideMobile; ?>">
  <tr>
    <td class="blog-8" align="center" valign="top">
      <table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" class="row" style="width:100%;max-width:100%;">
        <tr>
          <td class="body-bg-color" align="center" valign="top" bgcolor="#F4F4F4">
            <table role="presentation" border="0" width="800" align="center" cellpadding="0" cellspacing="0" class="row" style="width:800px;max-width:800px;">
              <tr>
                <td class="bg-color" align="center" valign="top" bgcolor="<?php echo $bgColor; ?>">
                  <table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" align="center" class="row" style="width:600px;max-width:600px;">
                    <tr>
                      <td align="center" valign="top" class="container-padding">
                        <table role="presentation" width="600" border="0" cellpadding="0" cellspacing="0" align="center" class="row" style="width:600px;max-width:600px;">
                          <tr>
                            <td align="center" valign="top">
                              <!-- If Outlook (mso), add an extra inner wrapper -->
                              <!--[if (gte mso 9)|(IE)]>
                              <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                  <td valign="top">
                                    <![endif]-->

                                    <!-- Column 1 Start -->
                                    <table role="presentation" width="290" border="0" cellpadding="0" cellspacing="0" align="<?php echo $layoutDirLeft; ?>" class="row" style="width:290px;max-width:290px;">
                                      <tr>
                                        <td align="center" valign="top">
<?php echo $colLeft; ?>
                                        </td>
                                      </tr>
                                    </table>
                                    <!-- /End Column 1 -->

                                    <!-- If Outlook (mso), add an extra <td> for a column gap -->
                                    <!--[if (gte mso 9)|(IE)]>
                                  </td>
                                  <td valign="top">
                                    <![endif]-->
                                    <!-- gap -->
                                    <table role="presentation" width="20" border="0" cellpadding="0" cellspacing="0" align="left" class="row colGap" style="width:20px;max-width:20px;">
                                      <tr>
                                        <td valign="middle" align="center" height="40"></td>
                                      </tr>
                                    </table>
                                    <!-- gap -->
                                    <!--[if (gte mso 9)|(IE)]>
                                  </td>
                                  <td valign="top">
                                    <![endif]-->

                                   <!-- Column 2 Start -->
                                    <table role="presentation" width="290" border="0" cellpadding="0" cellspacing="0" align="left" class="row" style="width:290px;max-width:290px;">
                                      <tr>
                                        <td align="center" valign="top">
<?php echo $colRight; ?>
                                        </td>
                                      </tr>
                                    </table>
                                    <!-- End Column 2 -->
                                </td>
                              </tr>
                              </table>

                            <!-- If Outlook (mso), close the extra inner wrapper -->
                            <!--[if (gte mso 9)|(IE)]>
                          </td>
                        </tr>
                      </table>
                      <![endif]-->
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
<!-- /2x Column -->