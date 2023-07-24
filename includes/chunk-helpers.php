<?php
//Chunk Functions

//set up standard cols
function fillImage($imgUrl,$imgLink,$imgAlt,$mobileImgs,$mobImgUrl,$imageWidth) {
	if (!$imgUrl) {
		return false;
	}
	$dtClass='';
	$htmlComment = $imageWidth.' Image -->';
	if ($mobileImgs == 'alt' || $mobileImgs == 'hide') {
		$dtClass = 'hide-mobile';
		$htmlComment = $imageWidth.' Image Desktop-->';
	}
$colImage = '
<!-- '.$htmlComment.'
<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" style="width:100%;max-width:100%;" class="'.$dtClass.'">
<tr>
    <td align="center" valign="top" class="img-responsive">
    <a href="'.$imgLink.'">
        <img style="display:block;width:100%;max-width:'.$imageWidth.'px;display:block;border:0px;" width="'.$imageWidth.'" src="'.$imgUrl.'" border="0" alt="'.$imgAlt.'" />
    </a>
    </td>
</tr>
</table>
<!-- / End '.$htmlComment;
if ($mobileImgs == 'alt') {
$colImage .= '
                                          
<!-- '.$imageWidth.' Image Mobile Start -->
<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" style="width:100%;max-width:100%;" class="hide-desktop">
    <tr>
    <td align="center" valign="top" class="img-responsive">
        <a href="'.$imgLink.'">
        <img style="display:block;width:100%;max-width:'.$imageWidth.'px;display:block;border:0px;" width="'.$imageWidth.'" src="'.$mobImgUrl.'" border="0" alt="'.$imgAlt.'" />
        </a>
    </td>
    </tr>
</table>
<!-- /End '.$imageWidth.' Image Mobile -->
                                          
';
	}
	return $colImage;
}

function fillText($textContent,$align,$fontColor,$bgColor, $centerOnMobile,$spacing=array('top','bottom'), $padText = false) {
	if (!$textContent) {
		return false;
	}
$centerMobile = '';
if ($centerOnMobile == true) {
	$centerMobile = 'center-on-mobile';
}
$topSpacing = false;
$btmSpacing = false;
if (in_array('top',$spacing)) {
$topSpacing = true;
}
if (in_array('bottom',$spacing)) {
$btmSpacing = true;
}
if ($padText) {
	$textPadding = 'padding: 20px;';
} else {
	$textPadding = '';
}

$colText = '';
if($topSpacing) {
                              $colText .= '
<!-- Optional Top Space -->
<table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:'.$bgColor.'">
<tr>
    <td class="space-control" valign="middle" align="center" height="20"></td>
</tr>
</table>
<!-- / End Optional Top Space -->
';
}
$colText .= '
<!-- Text Start -->
<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" align="center" style="width:100%;max-width:100%;">
<tr>
    <td class="text responsive-text '.$align.'-text '.$centerMobile.'" valign="middle" align="'.$align.'" style="'.$textPadding.' font-family:Poppins, sans-serif;color:'.$fontColor.' !important;text-decoration:none;">
    '.$textContent.'
    </td>
</tr>
</table>
<!-- /End Text -->

';
if($btmSpacing) {
$colText .= '
<!-- Optional Top Space -->
<table role="presentation" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:'.$bgColor.'">
<tr>
    <td class="space-control" valign="middle" align="center" height="20"></td>
</tr>
</table>
<!-- / End Optional Top Space -->
';
}
	return $colText;
}


function inline_button($inlineButton=false) {
	if (!$inlineButton) {
		return;
	}
	
	$buttonText = $inlineButton['button_text'];
	$buttonUrl = $inlineButton['button_url'];
	$buttonSettings = $inlineButton['button_settings'];
		$bgColor = $buttonSettings['button_background_color'] ?? '#94d500';
		$chunkBgColor = $buttonSettings['chunk_background_color'] ?? '#FFFFFF';
		$textColor = $buttonSettings['text_color'] ?? '#FFFFFF';
		$borderColor = $buttonSettings['border_color'] ?? '#94d500';
		$borderSize = $buttonSettings['border_size'] ?? '1px';
		$borderRad = $buttonSettings['border_radius'] ?? '3px';
		$mobileVis = $buttonSettings['mobile_visibility'] ?? true;
		$spacing = $buttonSettings['spacing'] ?? array('top','bottom');
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
		
	ob_start();
	 if($topSpacing) {?>
<!-- Optional Top Space -->
<table class="<?php echo $hideMobile; ?>" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:<?php echo $chunkBgColor; ?>;">
    <tbody>
    <tr>
        <td class="space-control" valign="middle" align="center" height="20">
        </td>
    </tr>
    </tbody>
</table>
<!-- / End Optional Top Space -->
<?php } ?>

<table width="100%" border="0" cellspacing="0" cellpadding="0" class="<?php echo $hideMobile; ?>" >
    <tbody>
    <tr>
        <td>
        <table border="0" cellspacing="0" cellpadding="0" align="center" style="margin: 0 auto;">
            <tbody>
            <tr style="color:<?php echo $textColor; ?>;">
            
            <!--Button Content Start-->
                <td align="center" bgcolor="<?php echo $bgColor; ?>" style="border-radius:<?php echo $borderRad; ?>; color:<?php echo $textColor; ?>;">
                <a target="_blank"
                class="button-link" 
                style="
                    font-size:19px;font-family:Poppins, sans-serif;line-height:24px;font-weight: bold;text-decoration:none;
                    display:inline-block;
                    margin: 0 auto;
                    padding:14px 30px;
                    color:<?php echo $textColor; ?>;
                    border-radius:<?php echo $borderRad; ?>;
                    border:<?php echo $borderSize; ?> solid <?php echo $borderColor; ?>;
                "
                href="<?php echo $buttonUrl; ?>">
                <span style="color:<?php echo $textColor; ?>;"><?php echo $buttonText; ?></span>
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
<table class="<?php echo $hideMobile; ?>" border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;background-color:<?php echo $chunkBgColor; ?>;">
    <tbody>
    <tr>
        <td class="space-control" valign="middle" align="center" height="20">
        </td>
    </tr>
    </tbody>
</table>
<!-- / End Optional Bottom Space -->
<?php }
 
return ob_get_clean();
}
