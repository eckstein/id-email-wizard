<?php

function idwiz_get_spacer_chunk($chunk, $templateOptions, $chunkIndex = null, $isEditor = false)
{
	//print_r($chunk);

	$chunkSettings = $chunk['settings'];
	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "' data-chunk-type='" . $chunk["field_type"] . "'" : "";

	$chunkFields = $chunk['fields'];

	$spacerHeight = $chunkFields['spacer_height'] ?? '60px';

	$backgroundColorCss = generate_background_css($chunkSettings);

	$visibility = get_visibility_class_and_style($chunkSettings);
	if ($visibility['class'] != '') {
		$tableClassHtml = 'class="spacer ' . esc_attr($visibility['class']) . '"';
	} else {
		$tableClassHtml = 'class="spacer"';
	}

	$output = '';
	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--[if !mso]><!-->';
	}

	$output .= '
	<!--[if mso]>
	<table ' . $tableClassHtml . ' role="presentation" width="100%" aria-hidden="true" style="table-layout:fixed; ' . $visibility['inlineStyle'] . '">
	<tr>
	<td style="width:100%;text-align:center;mso-line-height-rule:exactly; ' . $backgroundColorCss . '" valign="middle">
	<![endif]-->
	<div class="chunk id-spacer ' . $chunkClasses . ' ' . $visibility['class'] . '" ' . $chunkDataAttr . ' style="' . $visibility['inlineStyle'] . '" aria-hidden="true">
		<div style="line-height:' . $spacerHeight . ';height:' . $spacerHeight . '; font-size: ' . $spacerHeight . '; ' . $backgroundColorCss . '"></div>
	</div>
	<!--[if mso]>
	</td>
	</tr>
	</table>
	<![endif]-->';

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--<![endif]-->';
	}
	return $output;
}


function idwiz_get_button_chunk($chunk, $templateOptions, $chunkIndex = null, $isEditor = false)
{
	$chunkSettings = $chunk['settings'];
	$chunkFields = $chunk['fields'];

	$chunkPadding = $chunkSettings['chunk_padding'] ?? '0px';
	$chunkPaddingCss = 'padding: ' . $chunkPadding;
	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "' data-chunk-type='" . $chunk["field_type"] . "'" : "";

	$ctaText = $chunkFields['button_text'] ?? 'Click here';
	$ctaUrl = $chunkFields['button_link'] ?? 'https://www.idtech.com';


	$backgroundColorCss = generate_background_css($chunkSettings);
	$msoBackgroundColorCss = generate_background_css($chunkSettings, '', true);

	$backgroundColor = $chunkSettings['background_color'] ?? 'transparent';

	$textAlign = $chunkFields['button_align'] ?? 'center';
	$btnBgColor = $chunkFields['button_fill_color'] ?? '#343434';
	$textColor = $chunkFields['button_text_color'] ?? '#fff';

	$btnBorderCss = '';
	$borderColor = $chunkFields['button_border_color'];
	$borderSize = $chunkFields['button_border_size'] ?? 0;
	if ($borderColor) {
		if ($borderSize > 0) {
			$btnBorderCss = 'border: ' . $borderSize . ' solid ' . $borderColor . ';';
		}
	}

	$borderRadius = $chunkFields['button_border_radius'] ?? "30px";
	$msoBorderPerc = 5;
	if (floatval($borderRadius) >= 20) {
		$msoBorderPerc = 50;
	}

	$buttonPadding = $chunkFields['button_padding'] ?? "12px 60px";

	// For MSO, base width of the button in pixels
	$baseWidth = 200;
	// Additional width per character over the threshold
	$additionalWidthPerChar = 10;
	// Character threshold after which the button width increases
	$charThreshold = 14;
	// Maximum width for the button
	$maxWidth = 400;

	// Calculate the width of the button based on the length of ctaText
	$ctaTextLength = strlen($ctaText);
	$additionalWidth = ($ctaTextLength > $charThreshold) ? ($ctaTextLength - $charThreshold) * $additionalWidthPerChar : 0;
	$buttonWidth = min($baseWidth + $additionalWidth, $maxWidth);

	$visibility = get_visibility_class_and_style($chunkSettings);
	if ($visibility['class'] != '') {
		$tableClassHtml = 'class="' . esc_attr($visibility['class']) . '"';
	} else {
		$tableClassHtml = '';
	}

	$output = '';
	$output = '<div class="chunk id-button ' . $chunkClasses . ' ' . $visibility['class'] . '" ' . $chunkDataAttr . ' style="width: 100%; border: 0; ' . $visibility['inlineStyle'] . ' ' . $backgroundColorCss . '">';

	// MSO version
	$output .= '<!--[if mso]>
	<table ' . $tableClassHtml . ' role="presentation" width="100%" style="table-layout:fixed; ' . $msoBackgroundColorCss . $visibility['inlineStyle'] . '">
	<tr><td style="width:100%;text-align:' . $textAlign . '; ' . esc_attr($msoBackgroundColorCss) . ' ' . $chunkPaddingCss . '" valign="middle">
	<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $ctaUrl . '" style="height:50px;v-text-anchor:middle;width:' . $buttonWidth . 'px;" arcsize="' . $msoBorderPerc . '%" strokecolor="' . $borderColor . '" strokeweight="2px" fillcolor="none">
	<w:anchorlock/>
	<center style="color:' . $btnBgColor . ';font-family:Poppins,Arial,sans-serif;font-size:' . ($chunk['fields']['button_font_size'] ?? '1.2em') . ';font-weight:bold;">' . $ctaText . '</center>
	</v:roundrect>
	</td></tr></table>
	<![endif]-->';

	// Non-MSO version
	$output .= '<!--[if !mso]><!-->';
	$output .= '
		<div style="' . $backgroundColorCss . ' ' . $chunkPaddingCss . '; border: 0!important; text-align: ' . $textAlign . '; font-family: Poppins, Arial, sans-serif; font-size: ' . ($templateOptions['template_styles']['template_font_size'] ?? '16px') . '">
			<a href="' . $ctaUrl . '" aria-label="' . $ctaText . '" class="id-button" style="font-size: ' . ($chunk['fields']['button_font_size'] ?? '1.2em') . '; line-height: 1.3; text-align: center; font-weight: bold;background-color: ' . $btnBgColor . '; ' . $btnBorderCss . ' text-decoration: none; padding:' . $buttonPadding . '; color: ' . $textColor . ' !important; border-radius: ' . $borderRadius . '; display: inline-block;">
				' . $ctaText . '
			</a>
		</div>
	';
	$output .= '<!--<![endif]-->';

	$output .= '</div>';

	return $output;
}




function idwiz_get_snippet_chunk($chunk, $templateOptions, $chunkIndex = null, $isEditor = false)
{
	$chunkSettings = $chunk['settings'];
	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "' data-chunk-type='" . $chunk["field_type"] . "'" : "";

	$chunkFields = $chunk['fields'];

	$backgroundColorCss = generate_background_css($chunkSettings);

	$visibility = get_visibility_class_and_style($chunkSettings);


	// Retrieve the post id
	$snippetPostId = $chunkFields['select_snippet'];

	$snippetContent = get_post_meta($snippetPostId, 'snippet_content', true);

	$output = '';
	$output .= '<div class="chunk id-snippet ' . $chunkClasses . '" ' . $chunkDataAttr . ' style="' . esc_attr($backgroundColorCss . $visibility['inlineStyle']) . '">';

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--[if !mso]><!-->';
	}

	$output .= $snippetContent;

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--<![endif]-->';
	}

	$output .= '</div>';

	return $output;
}

function idwiz_get_interactive_chunk($chunk, $templateOptions, $chunkIndex = null, $isEditor = false)
{
	$chunkSettings = $chunk['settings'];
	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "' data-chunk-type='" . $chunk["field_type"] . "'" : "";

	$chunkFields = $chunk['fields'];

	$backgroundColorCss = generate_background_css($chunkSettings);

	$visibility = get_visibility_class_and_style($chunkSettings);


	// Retrieve the post id
	$intPostId = $chunkFields['select_interactive'];

	$snippetContent = get_post_meta($intPostId, '_recommendation_engine_html', true);

	$output = '';
	$output .= '<div class="chunk id-interactive ' . $chunkClasses . '" ' . $chunkDataAttr . ' style="' . esc_attr($backgroundColorCss . $visibility['inlineStyle']) . '">';

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--[if !mso]><!-->';
	}

	$output .= $snippetContent;

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--<![endif]-->';
	}

	$output .= '</div>';

	return $output;
}

function idwiz_get_raw_html_chunk($chunk, $templateOptions, $chunkIndex = null, $isEditor = false)
{
	$templateStyles = $templateOptions['template_styles'] ?? [];

	$chunkFields = $chunk['fields'] ?? [];
	$chunkSettings = $chunk['settings'] ?? [];
	$chunkWrap = filter_var($chunkSettings['chunk_wrap'] ?? false, FILTER_VALIDATE_BOOLEAN);

	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "' data-chunk-type='" . $chunk["field_type"] . "'" : "";

	$visibility = get_visibility_class_and_style($chunkSettings);
	if ($visibility['class'] != '') {
		$tableClassHtml = 'class="' . esc_attr($visibility['class']) . '"';
	} else {
		$tableClassHtml = '';
	}

	$backgroundColorCss = generate_background_css($chunkSettings);
	$msoBackgroundColorCss = generate_background_css($chunkSettings, '', true);

	$templateFontSize = $templateStyles['font-size']['template_font_size'] ?? '16px';

	$gmailBlendDesktop = false;
	if (isset($chunkSettings['force_white_text_on_desktop'])) {
		$gmailBlendDesktop = json_decode($chunkSettings['force_white_text_on_desktop']);
	}
	$gmailBlendMobile = false;
	if (isset($chunkSettings['force_white_text_on_desktop'])) {
		$gmailBlendMobile = json_decode($chunkSettings['force_white_text_on_mobile']);
	}

	$gmailBlendDesktopClass = $gmailBlendDesktop ? 'desktop' : '';
	$gmailBlendMobileClass = $gmailBlendMobile ? 'mobile' : '';

	$chunkPadding = $chunkSettings['chunk_padding'] ?? '0px';

	$rawHtmlContent = $chunkFields['raw_html_content'] ?? '<p>Your code goes here!</p>';

	$output = '';


	$output .= $isEditor || $chunkWrap ? '<div class="chunk id-raw-html ' . $chunkClasses . ' ' . $visibility['class'] . '" ' . $chunkDataAttr . ' style="' . $visibility['inlineStyle'] . ' ' . $backgroundColorCss . ' padding: ' . $chunkPadding . '; font-size: ' . $templateFontSize . ';">' : '';
	if ($chunkWrap) {
		if ($visibility['class'] == 'mobile-only') {
			$output .= '<!--[if !mso]><!-->';
		}

		$output .= '
		<!--[if mso]>
		<table ' . $tableClassHtml . ' role="presentation"
			style="width:100%;border:0;border-spacing:0; ' . $visibility['inlineStyle'] . ' ' . $msoBackgroundColorCss . '">
			<tr>
				<td class="id-raw-html" style="' . $msoBackgroundColorCss . ' padding: ' . $chunkPadding . '; font-family: Poppins, Arial, sans-serif!important; font-size: ' . $templateFontSize . ';">
		<![endif]-->
		';

		if ($gmailBlendDesktopClass || $gmailBlendMobileClass) {
			$output .= '<div class="gmail-blend-screen ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
			$output .= '<div class="gmail-blend-difference ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
		}
	}

	$output .= $rawHtmlContent;

	if ($chunkWrap) {
		if ($gmailBlendDesktopClass || $gmailBlendMobileClass) {
			$output .= '</div>';
			$output .= '</div>';
		}

		$output .= '
		<!--[if mso]>
				</td>
			</tr>
		</table>
		<![endif]-->';

		if ($visibility['class'] == 'mobile-only') {
			$output .= '<!--<![endif]-->';
		}
	}
	$output .= $isEditor || $chunkWrap ? '</div>' : ''; // Close the main wrapper div if open

	return $output;
}

function idwiz_get_icon_list_chunk($chunk, $templateOptions, $chunkIndex = null, $isEditor = false)
{
	$chunkFields = $chunk['fields'] ?? [];
	$chunkSettings = $chunk['settings'] ?? [];

	$visibility = get_visibility_class_and_style($chunkSettings);
	$darkModeVisibility = get_visibility_class_and_style($chunkSettings, true);
	$classAttr = $visibility['class'] ? 'class="' . esc_attr($visibility['class']) . '"' : '';

	$darkModeSupport = $templateOptions['template_styles']['custom-styles']['dark-mode-support'] === true ? true : false;

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "' data-chunk-type='" . $chunk["field_type"] . "'" : "";

	$backgroundColorCss = generate_background_css($chunkSettings);
	$msoBackgroundColorCss = generate_background_css($chunkSettings, '', true);

	$chunkPadding = $chunkSettings['chunk_padding'] ?? '0px';
	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';
	$chunkPaddingCss = $chunkPadding ? 'padding:' . $chunkPadding . ';' : '';
	$msoPaddingToMargin = $chunkPadding ? 'margin:' . $chunkPadding . ';' : 'margin: 0;';

	$pPadding = $chunkSettings['p_padding'] ?? false;
	$pPaddingClass = $pPadding ? '' : 'noPpad';

	$listWidth = $chunkSettings['list_width'] ?? '600px';
	$iconWidth = $chunkSettings['icon_width'] ?? '80px';
	$contentPadding = $chunkSettings['content_padding'] ?? '0';
	$contentPaddingCss = $contentPadding ? 'padding:' . $contentPadding . ';' : '';

	$imageSrc = $chunkFields['image_url'] ?? '';
	$darkModeImageSrc = $chunkFields['dark_mode_image_url'] ?? '';

	// Determine the aspect ratio of the image
	$imageAspectRatioResult = get_image_aspect_ratio($imageSrc); // we pass the remote URL here, not the cached data
	$status = $imageAspectRatioResult['status'];
	$imageAspectRatio = $imageAspectRatioResult['data'];

	if ($status == 'error') {
		$imageAspectRatio = 1;
	}

	// Calculate the height based on the aspect ratio and msoWidth
	// Remove "px" from iconWidth
	$iconWidthPx = str_replace('px', '', $iconWidth);
	$msoHeight = round($iconWidthPx / $imageAspectRatio, 2);

	$imageLink = $chunkFields['image_link'] ?? '';
	$imageAlt = $chunkFields['image_alt'] ?? '';

	$lightModeImgClass = ($darkModeSupport && $darkModeImageSrc) ? 'light-image' : '';

	$textContent = $chunkFields['plain_text_content'] ?? '<p>Your content goes here!</p>';
	$textContent = add_aria_label_to_links($textContent);

	$templateFontSize = $templateOptions['template_styles']['font-size']['template_font_size'] ?? '16px';

	$output = '';
	$output .= '<div class="chunk id-icon-list ' . $chunkClasses . ' ' . $visibility['class'] . ' ' . $pPaddingClass . '" ' . $chunkDataAttr . ' style="' . $backgroundColorCss . ' ' . $visibility['inlineStyle'] . $chunkPaddingCss . '">';

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--[if !mso]><!-->';
	}

	$output .= '<!--[if mso]>
	<table ' . $classAttr . ' role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" align="center" style="width:100%; max-width: ' . $listWidth . '; ' . $msoBackgroundColorCss . ' ' . $msoPaddingToMargin . '">
	<tr>
	<td width="' . $iconWidth . '" valign="top" align="right" style="width: ' . $iconWidth . '; max-width: ' . $iconWidth . ';">
	<![endif]-->
	<div class="icon-bullet-wrapper" style="max-width: ' . $listWidth . '; width: 100%; margin: 0 auto; font-family: Arial, sans-serif; font-size: ' . $templateFontSize . '; line-height: 1.5;">
	  <div class="icon-bullet-image-wrap" style="width: ' . $iconWidth . '; float: left;">
		<a href="' . $imageLink . '" style="display: block; text-align: right;">
		<img class="icon-bullet-image '. $lightModeImgClass.'" src="' . $imageSrc . '" style="display:block; max-width: 100%; width: ' . $iconWidth . '; height: auto;" height=' . $msoHeight . ' width="' . $iconWidthPx . '" alt="' . $imageAlt . '">';
	if ($darkModeSupport && $darkModeImageSrc) {
		$output .= '<!--[if !mso]><!-->';
		$output .= '<img ' . $imageAlt . ' class="id-image dark-image ' . $visibility['class'] . '" target="_blank" rel="noopener noreferrer" src="' . $darkModeImageSrc . '" style="' . $darkModeVisibility['inlineStyle'] . '" width= "' . $iconWidthPx . '" height="' . $msoHeight . '"  />';
		$output .= '<!--<![endif]-->';
	}
	$output .= 	'</a>
	  </div>
	<!--[if mso]></td><td valign="top" style="' . $contentPaddingCss . '"><![endif]-->
	  <div class="icon-bullet-content-wrap" style="margin-left: ' . (intval($iconWidth) + 10) . 'px; ' . $contentPaddingCss . '">
		' . wpautop(stripslashes($textContent)) . '
	  </div>
	</div>
	<div style="clear: both; width: 100%;"></div>
	<!--[if mso]>
	</td>
	</tr>
	</table>
	<![endif]-->';

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--<![endif]-->';
	}
	$output .= '</div>';

	return $output;
}

function idwiz_get_plain_text_chunk($chunk, $templateOptions, $chunkIndex = null, $isEditor = false)
{
	$templateStyles = $templateOptions['template_styles'] ?? [];

	$chunkFields = $chunk['fields'] ?? [];
	$chunkSettings = $chunk['settings'] ?? [];

	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$visibility = get_visibility_class_and_style($chunkSettings);
	if ($visibility['class'] != '') {
		$tableClassHtml = 'class="' . esc_attr($visibility['class']) . '"';
	} else {
		$tableClassHtml = '';
	}

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "' data-chunk-type='" . $chunk["field_type"] . "'" : "";

	$backgroundColorCss = generate_background_css($chunkSettings);
	$msoBackgroundColorCss = generate_background_css($chunkSettings, '', true);

	$templateFontSize = $templateStyles['font-size']['template_font_size'] ?? '16px';

	$gmailBlendDesktop = false;
	if (isset($chunkSettings['force_white_text_on_desktop'])) {
		$gmailBlendDesktop = json_decode($chunkSettings['force_white_text_on_desktop']);
	}
	$gmailBlendMobile = false;
	if (isset($chunkSettings['force_white_text_on_desktop'])) {
		$gmailBlendMobile = json_decode($chunkSettings['force_white_text_on_mobile']);
	}

	$gmailBlendDesktopClass = $gmailBlendDesktop ? 'desktop' : '';
	$gmailBlendMobileClass = $gmailBlendMobile ? 'mobile' : '';



	$chunkPadding = $chunkSettings['chunk_padding'] ?? '0px';

	$pPadding = $chunkSettings['p_padding'] ?? false;
	$pPaddingClass = $pPadding ? '' : 'noPpad';

	// Note that padding for <p> elements is only set in <style> blocks, so inboxes with no <style> support will not have padding applied in either case.

	$textContent = $chunkFields['plain_text_content'] ?? '<p>Your content goes here!</p>';

	$textContent = add_aria_label_to_links($textContent);

	$output = '';
	$output .= '<div class="chunk id-plain-text ' . $chunkClasses . ' ' . $pPaddingClass . ' ' . $visibility['class'] . '" ' . $chunkDataAttr . ' style="' . $visibility['inlineStyle'] . ' ' . $backgroundColorCss . ' padding: ' . $chunkPadding . '; font-size: ' . $templateFontSize . ';">';

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--[if !mso]><!-->';
	}

	$output .= '<!--[if mso]><table ' . $tableClassHtml . ' role="presentation" style="width:100%;border:0;border-spacing:0; ' . $visibility['inlineStyle'] . ' ' . $msoBackgroundColorCss . '">
		<tr>
			<td class="id-plain-text" style="' . $msoBackgroundColorCss . ' padding: ' . $chunkPadding . '; font-family: Poppins, Arial, sans-serif!important; font-size: ' . $templateFontSize . ';">
			<![endif]-->';



	if ($gmailBlendDesktopClass || $gmailBlendMobileClass) {
		$output .= '<div class="gmail-blend-screen ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
		$output .= '<div class="gmail-blend-difference ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
	}
	$output .= wpautop(stripslashes($textContent));
	if ($gmailBlendDesktopClass || $gmailBlendMobileClass) {
		$output .= '</div>';
		$output .= '</div>';
	}



	$output .= '<!--[if mso]></td></tr></table><![endif]-->';

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--<![endif]-->';
	}

	$output .= '</div>';

	return $output;
}



function idwiz_get_image_chunk($chunk, $templateOptions, $chunkIndex = null, $isEditor = false)
{
	$chunkSettings = $chunk['settings'];

	$chunkSettings = $chunk['settings'];

	$darkModeSupport = $templateOptions['template_styles']['custom-styles']['dark-mode-support'] === true ? true : false;

	$visibility = get_visibility_class_and_style($chunkSettings);
	
	if ($visibility['class'] != '') {
		$tableClassHtml = 'class="' . esc_attr($visibility['class']) . '"';
	} else {
		$tableClassHtml = '';
	}

	$darkModeVisibility = get_visibility_class_and_style($chunkSettings, true);

	$chunkFields = $chunk['fields'];

	$chunkSettings = $chunk['settings'];

	$chunkPadding = $chunk['settings']['chunk_padding'] ?? '';

	$chunkPaddingCss = $chunkPadding ? 'padding:' . $chunkPadding . ';' : '';
	$msoPaddingToMargin = $chunkPadding ? 'margin:' . $chunkPadding . ';' : 'margin: 0;';

	$image_context = $chunkSettings['image_context'] ?? '';
	$chunkClasses = 'id-image ' . $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "' data-chunk-type='" . $chunk["field_type"] . "'" : "";

	$templateWidth = $templateOptions['template_styles']['body-and-background']['template_width'] ?? '648';

	$imageSrc = $chunkFields['image_url'] ?? '';

	$darkModeImageSrc = $chunkFields['dark_mode_image_url'] ?? '';

	$imageLink = $chunkFields['image_link'] ?? '';
	$imageAlt = $chunkFields['image_alt'] ?? '';

	// Specific widths for MSO clients based on image_context
	$msoWidth = $templateWidth; // Default full width

	if ($image_context == 'two-col') {
		$msoWidth = $templateWidth > 0 ? round($templateWidth / 2, 0) : $templateWidth;
	} elseif ($image_context == 'three-col') {
		$msoWidth = $templateWidth > 0 ? round($templateWidth / 3, 0) : $templateWidth;
	} elseif ($image_context == 'sidebar-main') {
		$msoWidth = $templateWidth > 0 ? round($templateWidth * 0.6667, 0) : $templateWidth;
	} elseif ($image_context == 'sidebar-side') {
		$msoWidth = $templateWidth > 0 ? round($templateWidth * 0.3333, 0) : $templateWidth;
	}

	// Determine the aspect ratio of the image
	$imageAspectRatioResult = get_image_aspect_ratio($imageSrc); // we pass the remote URL here, not the cached data
	$status = $imageAspectRatioResult['status'];
	$imageAspectRatio = $imageAspectRatioResult['data'];

	$imageRatioSuccess = ($status === 'success');
	if (!$imageRatioSuccess && $isEditor) {
		$chunkClasses .= ' image-ratio-error';
	}

	// Calculate the height based on the aspect ratio and msoWidth
	$msoHeight = round($msoWidth / $imageAspectRatio, 2);

	$backgroundColorCss = generate_background_css($chunkSettings);
	$msoBackgroundColorCss = generate_background_css($chunkSettings, '', true);

	$output = '';
	$output .= '<div class="chunk ' . $chunkClasses . ' ' . $visibility['class'] . '" ' . $chunkDataAttr . ' style="' . $backgroundColorCss . ' ' . $visibility['inlineStyle'] . $chunkPaddingCss . '">';
	// if ($isEditor) {
	// 	$output .= '<div class="chunk-image-info">';
	// 	$output .= 'Aspect Ratio: ' . $imageAspectRatio . ' | Width: ' . $msoWidth . ' | Height: ' . $msoHeight;
	// 	$output .= '</div>';
	// }
	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--[if !mso]><!-->';
	}

	// Check if alt text is empty and set aria-hidden accordingly
	$ariaHidden = empty($imageAlt) ? 'aria-hidden="true"' : '';
	$altAttribute = empty($imageAlt) ? '' : 'alt="' . $imageAlt . '"';

	// MSO conditional for Outlook
	$output .= '<!--[if mso]>';
	$output .= '<table ' . $tableClassHtml . ' role="presentation" style="width:100%;border:0;border-spacing:0;margin: 0;' . $visibility['inlineStyle'] . '">
			<tr>
			<td style="text-align: center; font-size: 0; line-height: 0; ' . $msoBackgroundColorCss . ' ' . $msoPaddingToMargin . '" align="center">';
	if ($imageLink) {
		$output .= '<a href="' . $imageLink . '" ' . $ariaHidden . ' class="id-image-link" title="' . $imageAlt . '" style="display: block;padding: 0; line-height: 0;font-size:0;text-decoration:none;">';
	}

	// If no link, add pointer-events: none to prevent click interaction
	$pointerEventsCss = !$imageLink ? 'pointer-events: none;' : '';

	$output .= '<img class="id-image ' . $visibility['class'] . '" src="' . $imageSrc . '" width="' . $msoWidth . '" height="' . $msoHeight . '" ' . $altAttribute . ' style="' . $pointerEventsCss . 'width:100%; max-width:' . $msoWidth . 'px; height:' . $msoHeight . '; max-height:' . $msoHeight . 'px;' . $visibility['inlineStyle'] . ';" />';
	if ($imageLink) {
		$output .= '</a>';
	}
	$output .= '</td></tr></table>';
	$output .= '<![endif]-->';

	// Non-MSO markup for other clients
	$output .= '<!--[if !mso]> <!-->';

	if ($imageLink) {
		$output .= '<a href="' . $imageLink . '" ' . $ariaHidden . ' class="id-image-link" title="' . $imageAlt . '">';
	}

	$lightModeImgClass = ($darkModeSupport && $darkModeImageSrc) ? 'light-image' : '';
	$output .= '<img ' . $altAttribute . ' class="id-image '.$lightModeImgClass.' ' . $visibility['class'] . '" target="_blank" rel="noopener noreferrer" src="' . $imageSrc . '" style="display:block;' . $pointerEventsCss . 'width:100%; height:auto;' . $visibility['inlineStyle'] . '" />';
	
	if ($darkModeSupport && $darkModeImageSrc) {
		$output .= '<!--[if !mso]><!-->';
		$output .= '<img ' . $altAttribute . ' class="id-image dark-image ' . $visibility['class'] . '" target="_blank" rel="noopener noreferrer" src="' . $darkModeImageSrc . '" style="' . $pointerEventsCss . 'width:100%; height:auto;' . $darkModeVisibility['inlineStyle'] . '" />';
		$output .= '<!--<![endif]-->';
	}
	if ($imageLink) {
		$output .= '</a>';
	}

	$output .= '<!-- <![endif]-->';

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--<![endif]-->';
	}
	$output .= '</div>';
	return $output;
}



function idwiz_get_standard_header($templateOptions, $isEditor = false)
{
	$templateStyles = $templateOptions['template_styles'];
	$headerFooterSettings = $templateStyles['header-and-footer'];
	$templateWidth = $templateStyles['body-and-background']['template_width'];
	$showIdHeader = filter_var($headerFooterSettings['show_id_header'] ?? false, FILTER_VALIDATE_BOOLEAN);
	if (!$showIdHeader) {
		return '';
	}
	$headerLogoUrl = $headerFooterSettings['template_header_logo'] ?? '';

	$useDarkMode = $headerFooterSettings['show_dark_mode_header'] ?? false;
	$darkModeLogoUrl = $headerFooterSettings['template_header_logo_dark_mode'] ?? '';

	// Determine the aspect ratio of the image
	$imageAspectRatioResult = get_image_aspect_ratio($headerLogoUrl); // we pass the remote URL here, not the cached data
	$status = $imageAspectRatioResult['status'];
	$imageAspectRatio = $imageAspectRatioResult['data'];

	$chunkClasses = '';
	$imageRatioSuccess = ($status === 'success');
	if (!$imageRatioSuccess && $isEditor) {
		$chunkClasses = ' image-ratio-error';
	}

	// Calculate the height based on the aspect ratio and msoWidth
	$msoHeight = round($templateWidth / $imageAspectRatio, 0);

	$headerPadding = $headerFooterSettings['header_padding'] ?? '0 0 20px 0';
	$output = '<div class="chunk id-header ' . $chunkClasses . '" role="header" aria-label="iD Tech Camps" style="padding:' . $headerPadding . ';">';
	$output .= '<!--[if mso]>';
	$output .= '<table role="presentation" style="width:100%;border:0;border-spacing:0;table-layout:fixed;font-size: 0;" class="id-header">';
	$output .= '<tr>';
	$output .= '<td style="font-size: 0;line-height:0;margin:0;padding:' . $headerPadding . ';">';
	$output .= '<![endif]-->';
	$output .= '<a href="https://www.idtech.com" style="margin:0; padding: 0;" aria-label="iD Tech Camps" title="iD Tech Camps">';
	//Only give the light image a class if there's a dark image to go with it
	$lightModeImgClass = $useDarkMode ? 'light-image' : '';
	$output .= '<img class="'.$lightModeImgClass.'" src="' . $headerLogoUrl . '" width="' . $templateWidth . '" height="' . $msoHeight .'" alt="" style="max-width:100%; display:block;" />';
	if ($useDarkMode) {
		$output .= '<!--[if !mso]><!-->';
		$output .= '<img class="dark-image" src="' . $darkModeLogoUrl . '" width="' . $templateWidth . '" height="' . $msoHeight . '"alt="" style="max-width:100%; display:none;" />';
		$output .= '<!--<![endif]-->';
	}
	$output .= '</a>';
	$output .= '<!--[if mso]>';
	$output .= '</td>';
	$output .= '</tr>';
	$output .= '</table>';
	$output .= '<![endif]-->';
	$output .= '</div>';

	return $output;
}
function idwiz_get_standard_footer($templateStyles, $isEditor = false)
{
	$headerAndFooter = $templateStyles['header-and-footer'];
	$showIdFooter = filter_var($headerAndFooter['show_id_footer'] ?? false, FILTER_VALIDATE_BOOLEAN);
	if (!$showIdFooter) {
		return '';
	}

	$bodyAndBackground = $templateStyles['body-and-background'];

	$showUnsub = json_decode($headerAndFooter['show_unsub'] ?? 'true');
	$footerBackground = $headerAndFooter['footer-background'] ?? [];
	$footerBackgroundCss = generate_background_css($footerBackground);
	$templateWidth = $bodyAndBackground['template_width'];

	$gmailBlendDesktop = json_decode($headerAndFooter['footer_force_white_text_on_desktop'] ?? 'false');
	$gmailBlendMobile = json_decode($headerAndFooter['footer_force_white_text_on_mobile'] ?? 'false');
	$gmailBlendDesktopClass = $gmailBlendDesktop ? 'desktop' : '';
	$gmailBlendMobileClass = $gmailBlendMobile ? 'mobile' : '';

	$output = '';
	$output .= '<div class="chunk id-footer" role="footer" aria-label="iD Tech Camps" style="' . $footerBackgroundCss . ' max-width: ' . $templateWidth . '; padding: 20px 0 10px 0; font-size: 12px; font-family: Poppins, Arial, sans-serif; text-align: center;">';

	$output .= '<!--[if mso]>
	<table role="presentation" align="center" style="width:' . $templateWidth . ';table-layout:fixed;font-family:Poppins, Arial, sans-serif;">
	<tr>
	<td style="' . $footerBackgroundCss . ' padding: 20px 0 10px 0; font-size: 12px;font-family:Poppins, Arial, sans-serif; text-align: center;">
	<![endif]-->';

	// Social media icons
	$output .= idwiz_get_social_media_icons($templateStyles, $isEditor);

	if ($gmailBlendDesktop || $gmailBlendMobile) {
		$output .= '<div class="gmail-blend-screen ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
		$output .= '<div class="gmail-blend-difference ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
	}

	// Contact information
	$output .= idwiz_get_contact_info();

	// Unsubscribe links
	if ($showUnsub) {
		$output .= idwiz_get_unsubscribe_links();
	}

	if ($gmailBlendDesktop || $gmailBlendMobile) {
		$output .= '</div>';
		$output .= '</div>';
	}

	$output .= '<!--[if mso]>
	</td>
	</tr>
	</table>
	<![endif]-->';

	$output .= '</div>';

	return $output;
}

function idwiz_get_fine_print_disclaimer($templateOptions)
{
	$templateStyles = $templateOptions['template_styles'];
	$headerAndFooter = $templateStyles['header-and-footer'];
	$bodyAndBackground = $templateStyles['body-and-background'];

	$finePrintDisclaimer = $templateOptions['message_settings']['fine_print_disclaimer'];
	$footerBackground = $headerAndFooter['footer-background'] ?? [];
	$footerBackgroundCss = generate_background_css($footerBackground);
	$templateWidth = $bodyAndBackground['template_width'];

	$gmailBlendDesktop = json_decode($headerAndFooter['footer_force_white_text_on_desktop'] ?? 'false');
	$gmailBlendMobile = json_decode($headerAndFooter['footer_force_white_text_on_mobile'] ?? 'false');
	$gmailBlendDesktopClass = $gmailBlendDesktop ? 'desktop' : '';
	$gmailBlendMobileClass = $gmailBlendMobile ? 'mobile' : '';

	$output = '';
	if ($finePrintDisclaimer) {
		$output .= '<div class="chunk id-fine-print" style="' . $footerBackgroundCss . ' width: 100%; max-width: ' . $templateWidth . '; padding-bottom: 20px; font-size: 12px;">';

		$output .= '<!--[if mso]>
	<table role="presentation" align="center" style="width:' . $templateWidth . ';table-layout:fixed;">
	<tr>
	<td style="' . $footerBackgroundCss . ' padding-bottom: 20px; font-size: 12px; text-align: center;">
	<![endif]-->';


		if ($gmailBlendDesktop || $gmailBlendMobile) {
			$output .= '<div class="gmail-blend-screen ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
			$output .= '<div class="gmail-blend-difference ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
		}
		$output .= '<center style="font-size:12px !important;line-height:16px;padding-left: 20px; padding-right: 20px;">' . $finePrintDisclaimer . '</center>';
		if ($gmailBlendDesktop || $gmailBlendMobile) {
			$output .= '</div>';
			$output .= '</div>';
		}


		$output .= '<!--[if mso]>
	</td>
	</tr>
	</table>
	<![endif]-->';

		$output .= '</div>';
	}

	return $output;
}

// Helper functions

function idwiz_get_social_media_icons($templateStyles, $isEditor = false)
{
	// Get header and footer styles
	$headerFooterStyles = $templateStyles['header-and-footer'] ?? [];
	
	// Build icons array from template settings
	$icons = [];
	for ($i = 1; $i <= 5; $i++) {
		$iconImage = $headerFooterStyles["social_icon_{$i}_image"] ?? '';
		$iconLink = $headerFooterStyles["social_icon_{$i}_link"] ?? '';
		
		// Set default values for all 5 icons if empty (backward compatibility)
		if ($i === 1 && empty($iconImage)) {
			$iconImage = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/iD%20Tech_Social%20Icon%20Updates__fb.png';
			$iconLink = 'https://www.facebook.com/computercamps';
		} elseif ($i === 2 && empty($iconImage)) {
			$iconImage = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/iD%20Tech_Social%20Icon%20Updates__tt.png';
			$iconLink = 'https://www.tiktok.com/@idtechcamps';
		} elseif ($i === 3 && empty($iconImage)) {
			$iconImage = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/iD%20Tech_Social%20Icon%20Updates__ig.png';
			$iconLink = 'https://www.instagram.com/idtech/';
		} elseif ($i === 4 && empty($iconImage)) {
			$iconImage = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/iD%20Tech_Social%20Icon%20Updates__in.png';
			$iconLink = 'https://www.linkedin.com/company/id-tech-camps';
		} elseif ($i === 5 && empty($iconImage)) {
			$iconImage = 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/iD%20Tech_Social%20Icon%20Updates__yt.png';
			$iconLink = 'https://www.youtube.com/@idtechcamps';
		}
		
		// Only add icon if both image and link are provided
		if (!empty($iconImage) && !empty($iconLink)) {
			$icons[] = [
				'url' => $iconLink,
				'src' => $iconImage,
				'title' => 'Social Media Link ' . $i
			];
		}
	}

	$output = '';
	foreach ($icons as $icon) {
		$output .= '<span style="margin:0;font-family:Poppins,sans-serif;font-size:12px;line-height:16px;">';
		$output .= '<a href="' . $icon['url'] . '" title="' . $icon['title'] . '" aria-label="' . $icon['title'] . '">';
		$output .= '<img width="35" height="27" alt="' . $icon['title'] . '" style="width: 35px; height: auto;" src="' . $icon['src'] . '" />';
		$output .= '</a></span> ';
	}
	if (!empty($icons)) {
		$output .= '<br />';
	}
	return $output;
}

function idwiz_get_contact_info()
{
	return '<p style="margin:0;padding: 1em 0;font-family:Poppins,sans-serif;font-size:12px;line-height:16px;">
		<strong>Contact Us:</strong><br />
		1-888-709-8324<br />
		+1-408-871-3700 (international)<br /><br />

		<strong>Address:</strong> P.O. Box 111720, Campbell, CA 95011<br /><br />

		Copyright © {{now format=\'yyyy\'}} All rights reserved.
	</p>';
}

function idwiz_get_unsubscribe_links()
{
	$output = '{{#if userId}}';
	$output .= '<a class="footer-link" href="{{hostedUnsubscribeUrl}}" aria-label="Manage Subscription Preferences" title="Manage Subscription Preferences">Manage preferences</a><br />';
	$output .= '{{/if}}';
	$output .= '<a class="footer-link" href="{{unsubscribeMessageTypeUrl}}" aria-label="Unsubscribe from emails like this" title="Manage Subscription Preferences">Unsubscribe from emails like this</a><br />';
	$output .= '<a class="footer-link" href="{{unsubscribeUrl}}" aria-label="Unsubscribe from all marketing emails" title="Manage Subscription Preferences">Unsubscribe from all marketing emails</a><br /><br />';
	return $output;
}

function idwiz_get_email_head($templateSettings, $templateStyles, $rows)
{

	ob_start();
?>

	<head><!-- Yahoo App Android will strip this --></head>

	<head>
		<base target="_blank">
		<meta charset="utf-8" />
		<meta http-equiv="Content-Type" content="text/html charset=UTF-8" />
		<meta name="viewport" content="width=device-width,initial-scale=1" />
		<meta name="x-apple-disable-message-reformatting" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />

		<?php
		$gmailDesc = $templateSettings['gmail-annotations']['gmail_description'] ?? '';
		$gmailPromoCode = $templateSettings['gmail-annotations']['gmail_promo_code'] ?? '';
		$gmailStartDate = $templateSettings['gmail-annotations']['gmail_start_date'] ?? '';
		$gmailEndDate = $templateSettings['gmail-annotations']['gmail_end_date'] ?? '';
		$gmailImageUrl = $templateSettings['gmail-annotations']['gmail_image_url'] ?? '';
		$gmailImageLink = $templateSettings['gmail-annotations']['gmail_image_link'] ?? '';

		// Check if any of the DiscountOffer fields are present
		$hasDiscountOffer = !empty($gmailDesc) || !empty($gmailPromoCode) || !empty($gmailStartDate) || !empty($gmailEndDate);

		// Generate headline based on available information
		$gmailImageHeadline = '';
		if (!empty($gmailPromoCode) && !empty($gmailDesc)) {
			$gmailImageHeadline = "Use code $gmailPromoCode - $gmailDesc";
		} elseif (!empty($gmailPromoCode)) {
			$gmailImageHeadline = "Use code $gmailPromoCode";
		} elseif (!empty($gmailDesc)) {
			$gmailImageHeadline = $gmailDesc;
		}

		// Render DiscountOffer section if any relevant data is present
		if ($hasDiscountOffer): ?>
			<div itemscope itemtype="http://schema.org/DiscountOffer">
				<?php if (!empty($gmailDesc)): ?>
					<meta itemprop="description" content="<?php echo htmlspecialchars($gmailDesc); ?>" />
				<?php endif; ?>
				<?php if (!empty($gmailPromoCode)): ?>
					<meta itemprop="discountCode" content="<?php echo htmlspecialchars($gmailPromoCode); ?>" />
				<?php endif; ?>
				<?php if (!empty($gmailStartDate)): ?>
					<meta itemprop="availabilityStarts" content="<?php echo htmlspecialchars($gmailStartDate); ?>" />
				<?php endif; ?>
				<?php if (!empty($gmailEndDate)): ?>
					<meta itemprop="availabilityEnds" content="<?php echo htmlspecialchars($gmailEndDate); ?>" />
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php
		// Render PromotionCard section if image URL and link are present
		if (!empty($gmailImageUrl) && !empty($gmailImageLink)): ?>
			<div itemscope itemtype="http://schema.org/PromotionCard">
				<meta itemprop="image" content="<?php echo htmlspecialchars($gmailImageUrl); ?>" />
				<meta itemprop="url" content="<?php echo htmlspecialchars($gmailImageLink); ?>" />
				<?php if (!empty($gmailImageHeadline)): ?>
					<meta itemprop="headline" content="<?php echo htmlspecialchars($gmailImageHeadline); ?>" />
				<?php endif; ?>
			</div>
		<?php endif; ?>


		<?php
		$darkModeSupport = $templateStyles['custom-styles']['dark-mode-support'] === true ? true : false;
		if ($darkModeSupport) { ?>
			<meta name="color-scheme" content="light dark">
			<meta name="supported-color-schemes" content="light dark">
		<?php } ?>



		<title>
			<?php
			$subjectLine = $templateSettings['subject_line'] ?? '';

			// Remove anything that's not plain text and re-convert back to non-html-entities
			$subjectLine = strip_tags($subjectLine);
			$subjectLine = html_entity_decode($subjectLine, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			echo $subjectLine;
			?>
		</title>

		<?php
		// Main CSS
		require_once(plugin_dir_path(__FILE__) . 'wiztemplate-css.php'); ?>

		<?php
		// Additional custom CSS
		if (isset($templateStyles['custom-styles']['additional_template_css'])) {
			echo $templateStyles['custom-styles']['additional_template_css'];
		} ?>
		<?php
		// Additional CSS block from snippets
		echo get_chunk_css_for_head($rows);
		?>

		<!--[if mso]>
		<noscript>
			<xml>
				<o:OfficeDocumentSettings>
					<o:PixelsPerInch>96</o:PixelsPerInch>
				</o:OfficeDocumentSettings>
			</xml>
		</noscript>
		<![endif]-->
	</head>
<?php
	return ob_get_clean();
}


function idwiz_get_email_body_top($templateSettings, $templateStyles)
{
	$templateWidth = $templateStyles['body-and-background']['template_width'];
	$templateWidth = (int) $templateWidth > 0 ? (int) $templateWidth : 648;
	$bodyBackgroundCss = generate_background_css($templateStyles['body-and-background']['body-background'], 'body_background_');
	$pageBackgroundCss = generate_background_css($templateStyles['body-and-background']['page-background'], 'page_background_');

	$previewTextHack = get_preview_text_hack();

	$subjectLine = $templateSettings['subject_line'] ?? '';

	// Remove anything that's not plain text and re-convert back to non-html-entities
	$subjectLine = strip_tags($subjectLine);
	$subjectLine = html_entity_decode($subjectLine, ENT_QUOTES | ENT_HTML5, 'UTF-8');

	$return = '<body class="body" id="body" style="margin: 0; padding: 0; word-spacing: normal; ' . $bodyBackgroundCss . '">';
	// This div serves to replace the <html> or <body> tags for accessability and bg color in inboxes that remove one or both
	$return .= '<div lang="en" dir="ltr" style="font-size: medium; font-size:max(16px, 1rem); ' . $bodyBackgroundCss . '">'; 
	$return .= '<div style="display: none; max-height: 0px; overflow: hidden;">
	' . $previewTextHack . '
	</div>
	<div role="article" aria-label="iD Tech Camps" aria-roledescription="email" class="email-wrapper" style="-webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; word-spacing:normal;text-align: center;">
		
		<!--[if mso]> 
		<table role="presentation" style="width: 100%; border: 0; border-spacing: 0;margin: 0 auto; ' . $bodyBackgroundCss . '" class="email-wrapper">
			<tr>
			<td align="center">
				<table role="presentation" align="center" style="width:' . $templateWidth . 'px; border: 0; border-spacing: 0;margin: 0 auto;"> 
					<tr> 
					<td style="padding:20px 0; ' . $pageBackgroundCss . '"> 
					<![endif]-->
						<div class="outer" style="width: 100%; max-width: ' . $templateWidth . 'px; margin: 0 auto; ' . $pageBackgroundCss .'">';
						
						

	return $return;
}


function idwiz_get_email_body_bottom()
{
	$emailBottom = '
						</div>
					<!--[if mso]> 
					</td> 
					</tr> 
				</table> 
			</td>
			</tr>
		</table>
		<![endif]-->
	</div>
</div>
</body>';
	return $emailBottom;
}

function idwiz_get_email_bottom()
{
	return '</html>';
}
