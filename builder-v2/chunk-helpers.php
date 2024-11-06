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
	$textPadding = 'add-padding';
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
    <td class="text responsive-text '.$textPadding.' '.$align.'-text '.$centerMobile.'" valign="middle" align="'.$align.'" style="'.$textPadding.' font-family:Poppins, sans-serif;color:'.$fontColor.' !important;text-decoration:none;">
    '.wpautop($textContent).'
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
                class="id-button"
                style="
                    font-size:19px;font-family:Poppins, sans-serif;line-height:24px;font-weight: bold;text-decoration:none;
                    display:inline-block;
                    margin: 0 auto;
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



function get_visibility_class_and_style($settingsObject, $darkMode = false)
{

    $desktopVisibility = filter_var($settingsObject['desktop_visibility'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $mobileVisibility = filter_var($settingsObject['mobile_visibility'] ?? true, FILTER_VALIDATE_BOOLEAN);

    // Initialize class and inline style
    $classes = [];
    $inlineStyle = 'display: block;';

    // Determine classes and inline style based on visibility
    if ($desktopVisibility === true && $mobileVisibility === false) {
        // Visible on desktop only
        $classes[] = 'desktop-only';
    } elseif ($desktopVisibility === false && $mobileVisibility === true) {
        // Visible on mobile only
        $classes[] = 'mobile-only';
        $inlineStyle = 'display: none;'; // Hide by default, shown on mobile
    } elseif ($desktopVisibility === false && $mobileVisibility === false) {
        // Hidden on all devices
        $inlineStyle = 'display: none !important;';
    }

    if ($darkMode) {
        $inlineStyle = 'display: none;'; // hides by default on light mode
    }

    // Join all classes into a single string
    $class = implode(' ', $classes);

    // Return the class and style as an associative array
    return [
        'class' => $class,
        'inlineStyle' => $inlineStyle
    ];
}

add_action('wp_ajax_generate_background_css_ajax', 'generate_background_css_ajax');
function generate_background_css_ajax()
{
    $backgroundSettings = $_POST['backgroundSettings'] ?? [];
    $prefix = $_POST['prefix'] ?? '';
    $css = generate_background_css($backgroundSettings, $prefix);
    echo json_encode($css);
    die();
}
function generate_background_css($backgroundSettings, $prefix = '', $forMso = false)
{
    $bg_type = $backgroundSettings[$prefix . 'background-type'] ?? 'none';
    $css = [];

    switch ($bg_type) {


        case 'custom':
            // No fallbacks, just send the custom CSS as-is
            $custom_css = $backgroundSettings[$prefix . 'custom-background-css'] ? trim($backgroundSettings[$prefix . 'custom-background-css']) : '';
            $css[] = $custom_css;
            break;
        case 'image':
            // Image properties
            $image_url = $backgroundSettings[$prefix . 'background-image-url'];
            $position = $backgroundSettings[$prefix . 'background-image-position'] != '' ? $backgroundSettings[$prefix . 'background-image-position'] : 'center';
            $size = $backgroundSettings[$prefix . 'background-image-size'] != '' ? $backgroundSettings[$prefix . 'background-image-size'] : 'cover';

            // Fallback color and additional properties
            $fallback_color = $backgroundSettings[$prefix . 'background-color'] ?? 'transparent';
            if ($fallback_color == 'rgba(0,0,0,0)') {
                $fallback_color = 'transparent';
            }

            // Only include fallback color for mso clients
            //if ($forMso) {
                $css[] = "background-color: $fallback_color;";
            //}
            if ($image_url) {
                $css[] = "background-image: url($image_url);";
                $css[] = "background-position: $position;";
                $css[] = "background-size: $size;";
            }



            // For outlook, exclude image position stuff since not necessary
            if (! $forMso) {
                // Background repeat
                $bgRepeatY = $backgroundSettings[$prefix . 'background-repeat-vertical'] ?? false;
                $bgRepeatX = $backgroundSettings[$prefix . 'background-repeat-horizontal'] ?? false;
                if ($bgRepeatY === true && $bgRepeatX === true) {
                    $css[] = "background-repeat: repeat;";
                } else if ($bgRepeatY === true) {
                    $css[] = "background-repeat: repeat-y;";
                } else if ($bgRepeatX === true) {
                    $css[] = "background-repeat: repeat-x;";
                } else {
                    $css[] = "background-repeat: no-repeat;";
                }
            }

            break;

        case 'solid':
            // Solid color background
            $color = $backgroundSettings[$prefix . 'background-color'] ?? 'transparent';
            if ($color == 'rgba(0,0,0,0)') {
                $color = 'transparent';
            }
            $css[] = "background-color: $color;";

            break;

        case 'none':
            // Transparent background
            $css[] = "background-color: transparent;";
            break;
    }

    // Check for forced background color
    $forceBackground = $backgroundSettings[$prefix . 'force-background'] ?? false;

    // If a background color is set and not transparent, force it using linear gradient
    if (
        $forceBackground == 'true'
        && $bg_type != 'none'
        && isset($backgroundSettings[$prefix . 'background-color'])
        && $backgroundSettings[$prefix . 'background-color'] != 'transparent'
        && ! $forMso
    ) {
        $css[] = "background-image: linear-gradient({$backgroundSettings[$prefix . 'background-color']}, {$backgroundSettings[$prefix . 'background-color']});";
    }



    return implode(" ", $css);
}