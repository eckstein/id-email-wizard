<?php
$templateBgColor = $templateStyles['template_bg_color'] ?? '#F4F4F4'; 

//Chunk Settings
$chunkSettings = $chunk['chunk_settings'];
	$bgColor = $chunkSettings['background_color'] ?? '#FFFFFF';
	$mobileImgSetting = $chunkSettings['mobile_images'] ?? 'alt';
	$layout = $chunkSettings['layout'] ?? 'ltr';
	$magicWrap = $chunkSettings['magic_wrap'] ?? false;
	

$mobileVis = $chunkSettings['mobile_visibility'];
	$hideMobile = '';
	if ($mobileVis == false) {
		$hideMobile = 'hide-mobile';
	}
$desktopVis = $chunkSettings['desktop_visibility'];
	$hideDesktop = '';
	if ($desktopVis == false) {
		$hideDesktop = 'hide-desktop';
	}





	$colLeft = '';
	$colRight = '';
switch ($layout) {
	case 'ltr':
		$image = $chunk['left_image'] ?? '';
		$text = $chunk['right_text'] ?? array('text_content'=>'Your content here!','align'=>'center','text_color'=>'#000000','center_on_mobile'=>true);
		if (isset($text['add_button']) && $text['add_button'] != false) {
			$inlineButton = $text['inline_button'];
		} else {
			$inlineButton = false;
		}
		$centerOnMobile = $text['center_on_mobile'] ?? false;
		$spacing = $text['spacing'] ?? array('top', 'bottom');
		$textColor = $text['text_color'] ?? '#000000';
		$textAlign = $text['align'] ?? 'left';
		$textContent = $text['text_content'] ?? 'Your content here!';
		if ($magicWrap) {
			$layoutDirLeft = 'right';
			$colRight = fillImage($image['left_image_url'],$image['left_image_link'],$image['left_image_alt'],$mobileImgSetting,$image['left_image_url_mobile'] ?? '','400');
			$colLeft = fillText($textContent,$textAlign,$textColor,$bgColor,$centerOnMobile, $spacing, true);
			$colLeft .= inline_button($inlineButton);
		} else {
			$layoutDirLeft = 'left';
			$colLeft = fillImage($image['left_image_url'],$image['left_image_link'],$image['left_image_alt'],$mobileImgSetting,$image['left_image_url_mobile'] ?? '','400');
			$colRight = fillText($textContent,$textAlign,$textColor,$bgColor,$centerOnMobile, $spacing, true);
			$colRight .= inline_button($inlineButton);
		}
		
	break;
	case 'rtl':
		$image = $chunk['right_image'] ? $chunk['right_image'] : array('right_image_url'=>'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/square-image.jpg','left_image_link'=>'https://www.idtech.com','left_image_alt'=>'','left_image_url_mobile'=>'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/square-image.jpg');
			$text = $chunk['left_text'] ?? array('text_content'=>'Your content here!','align'=>'center','text_color'=>'#000000','center_on_mobile'=>true);
			if (isset($text['add_button']) && $text['add_button'] != false) {
				$inlineButton = $text['inline_button'];
			} else {
				$inlineButton = false;
			}
			$centerOnMobile = $text['center_on_mobile'] ?? false;
			$spacing = $text['spacing'] ?? array('top', 'bottom');
			$textColor = $text['text_color'] ?? '#000000';
			$textAlign = $text['align'] ?? 'left';
			$textContent = $text['text_content'] ?? 'Your content here!';
		if ($magicWrap) {
			$layoutDirLeft = 'right';
			//if magic wrap is on, we render the content from left-to-right but add an align=right on the left column, switching the order. On mobile, it will wrap to one column in the correct order.
			$colLeft = fillImage($image['right_image_url'],$image['right_image_link'],$image['right_image_alt'],$mobileImgSetting,$image['right_image_url_mobile'] ?? '','400');
			$colRight = fillText($textContent,$textAlign,$textColor,$bgColor,$centerOnMobile,$spacing, true);
			$colRight .= inline_button($inlineButton);
		} else {
			$layoutDirLeft = 'left';
			$colRight = fillImage($image['right_image_url'],$image['right_image_link'],$image['right_image_alt'],$mobileImgSetting,$image['right_image_url_mobile'] ?? '','400');
			$colLeft = fillText($textContent,$textAlign,$textColor,$bgColor,$centerOnMobile,$spacing, true);
			$colLeft .= inline_button($inlineButton);
		}
		
	break;
	case 'img':
		
		$leftImage = $chunk['left_image'] ? $chunk['left_image'] : array('left_image_url'=>'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/square-image.jpg','left_image_link'=>'https://www.idtech.com','left_image_alt'=>'','left_image_url_mobile'=>'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/square-image.jpg');
		$rightImage = $chunk['right_image'] ? $chunk['right_image'] : array('right_image_url'=>'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/square-image.jpg','left_image_link'=>'https://www.idtech.com','left_image_alt'=>'','left_image_url_mobile'=>'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/square-image.jpg');
		if ($magicWrap) {
			$layoutDirLeft = 'right';
			$colRight = fillImage($leftImage['left_image_url'],$leftImage['left_image_link'],$leftImage['left_image_alt'],$mobileImgSetting,$leftImage['left_image_url_mobile'] ?? '','400');
			$colLeft = fillImage($rightImage['right_image_url'],$rightImage['right_image_link'],$rightImage['right_image_alt'],$mobileImgSetting, $rightImage['right_image_url_mobile'] ?? '','400');
		} else {
			$layoutDirLeft = 'left';
			$colLeft = fillImage($leftImage['left_image_url'],$leftImage['left_image_link'],$leftImage['left_image_alt'],$mobileImgSetting,$leftImage['left_image_url_mobile'] ?? '','400');
			$colRight = fillImage($rightImage['right_image_url'],$rightImage['right_image_link'],$rightImage['right_image_alt'],$mobileImgSetting, $rightImage['right_image_url_mobile'] ?? '','400');
		}
	break;
	case 'txt':
		$text = $chunk['left_text'] ? $chunk['left_text'] : array('text_content'=>'Your content here!','align'=>'center','text_color'=>'#000000','center_on_mobile'=>true);
		$text2 = $chunk['right_text'] ? $chunk['right_text'] : array('text_content'=>'Your content here!','align'=>'center','text_color'=>'#000000','center_on_mobile'=>true);
		if ($text['add_button'] != false) {
			$inlineButton = $text['inline_button'];
		} else {
			$inlineButton = false;
		}
		if ($text2['add_button'] != false) {
			$inlineButton2 = $text2['inline_button'];
		} else {
			$inlineButton2 = false;
		}
		$centerOnMobileLeft = $text['center_on_mobile'];
		$centerOnMobileRight = $text2['center_on_mobile'];
		$spacing = $text['spacing'];
		$spacing2 = $text2['spacing'];
		if ($magicWrap) {
			$layoutDirLeft = 'right';
			$colRight = fillText($text['text_content'],$text['align'],$text['text_color'],$bgColor,$centerOnMobileLeft,$spacing,true);
			$colRight .= inline_button($inlineButton);
			$colLeft = fillText($text2['text_content'],$text2['align'],$text2['text_color'],$bgColor,$centerOnMobileRight,$spacing2, true);
			$colLeft .= inline_button($inlineButton2);
		} else {
			$layoutDirLeft = 'left';
			$colLeft = fillText($text['text_content'],$text['align'],$text['text_color'],$bgColor,$centerOnMobileLeft,$spacing, true);
			$colLeft .= inline_button($inlineButton);
			$colRight = fillText($text2['text_content'],$text2['align'],$text2['text_color'],$bgColor,$centerOnMobileRight,$spacing2, true);
			$colRight .= inline_button($inlineButton2);
		}
	break;
}


?>

<!-- 2x Column -->
<table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;" class="<?php echo $hideMobile.' '.$hideDesktop; ?>">
  <tr>
    <td align="center" valign="top">
      <table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" class="row" style="width:100%;max-width:100%;">
        <tr>
          <td class="body-bg-color" align="center" valign="top" bgcolor="<?php echo $templateBgColor; ?>">
            <table role="presentation" border="0" width="800" align="center" cellpadding="0" cellspacing="0" class="row" style="width:800px;max-width:800px;">
              <tr>
                <td class="bg-color" align="center" valign="top" bgcolor="<?php echo $bgColor; ?>">
                  <table role="presentation" width="800" border="0" cellpadding="0" cellspacing="0" align="center" class="row" style="width:800px;max-width:800px;">
                    <tr>
                      <td align="center" valign="top">
                        <table role="presentation" width="800" border="0" cellpadding="0" cellspacing="0" align="center" class="row" style="width:800px;max-width:800px;">
                          <tr>
                            <td align="center" valign="top">
                              <!-- If Outlook (mso), add an extra inner wrapper -->
                              <!--[if (gte mso 9)|(IE)]>
                              <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                  <td valign="top">
                                    <![endif]-->

                                    <!-- Column 1 Start -->
                                    <table role="presentation" width="400" border="0" cellpadding="0" cellspacing="0" align="<?php echo $layoutDirLeft; ?>" class="row" style="width:400px;max-width:400px;">
                                      <tr>
                                        <td align="center" valign="top">
<?php echo $colLeft; ?>
                                        </td>
                                      </tr>
                                    </table>
                                    <!-- /End Column 1 -->

                                 

                                   <!-- Column 2 Start -->
                                    <table role="presentation" width="400" border="0" cellpadding="0" cellspacing="0" align="left" class="row" style="width:400px;max-width:400px;">
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