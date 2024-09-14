<?php

function idwiz_get_spacer_chunk($chunk, $templateOptions, $chunkIndex = null, $isEditor = false)
{
	//print_r($chunk);

	$chunkSettings = $chunk['settings'];
	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "'" : "";

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

	$chunkDataAttr = $isEditor ? 'data-chunk-index="' . $chunkIndex . '"' : '';

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

	// For MSO buttons, set the border to 2px solid the background color for dark mode on MSO
	$vmlBorderColor = $backgroundColor;
	$vmlBorderWeight = '2';

	// For MSO, base width of the button in pixels
	$baseWidth = 200;
	// Additional width per character over the threshold
	$additionalWidthPerChar = 10;
	// Character threshold after which the button width increases
	$charThreshold = 14;

	// Calculate the width of the button based on the length of ctaText
	$ctaTextLength = strlen($ctaText);
	$additionalWidth = ($ctaTextLength > $charThreshold) ? ($ctaTextLength - $charThreshold) * $additionalWidthPerChar : 0;
	$buttonWidth = $baseWidth + $additionalWidth;

	$visibility = get_visibility_class_and_style($chunkSettings);
	if ($visibility['class'] != '') {
		$tableClassHtml = 'class="' . esc_attr($visibility['class']) . '"';
	} else {
		$tableClassHtml = '';
	}

	//print_r( $chunkSettings );
	$output = '';
	$output = '<div class="chunk id-button ' . $chunkClasses . ' ' . $visibility['class'] . '" ' . $chunkDataAttr . ' style="width: 100%; border: 0; border-spacing: 0; ' . $visibility['inlineStyle'] . ' ' . $backgroundColorCss . '">';

	$output .= '<!--[if mso]>';
	$output .= '<table ' . $tableClassHtml . ' role="presentation" width="100%" style="table-layout:fixed; ' . esc_attr($msoBackgroundColorCss . $visibility['inlineStyle']) . '">';
	$output .= '<tr><td style="width:100%;text-align:center; ' . esc_attr($msoBackgroundColorCss) . ' ' . $chunkPaddingCss . '" valign="middle">';

	// Convert the button background color to RGBA format
	$btnBgColorRGBA = hex2rgba($btnBgColor, 1);

	$output .= '<br/>';
	$output .= '<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $ctaUrl . '" style="height: 50px; v-text-anchor: middle; width: ' . $buttonWidth . 'px;" arcsize="' . $msoBorderPerc . '%" strokecolor="' . $vmlBorderColor . '" strokeweight="' . $vmlBorderWeight . 'px" fillcolor="' . $btnBgColor . '">';
	$output .= '<w:anchorlock/>';
	$output .= '<center class="id-button" style="color: ' . $textColor . ' !important; font-family: Poppins, Arial, sans-serif; font-size: ' . ($chunk['fields']['button_font_size'] ?? '1.2em') . '!important; line-height: 1.6em;font-weight: bold;mso-text-raise: 10pt;">' . $ctaText . '</center>';
	$output .= '</v:roundrect>';
	$output .= '<br/>';
	$output .= '</td></tr></table>';
	$output .= '<![endif]-->';

	$output .= '<!--[if !mso]><!-->';
	$output .= '
	
		<div style="' . $backgroundColorCss . ' ' . $chunkPaddingCss . '; border: 0!important; text-align: ' . $textAlign . '; font-family: Poppins, Arial, sans-serif; font-size: ' . ($templateOptions['template_styles']['template_font_size'] ?? '16px') . '">
			<a href="' . $ctaUrl . '" aria-label="' . $ctaText . '" class="id-button" style="font-size: ' . ($chunk['fields']['button_font_size'] ?? '1.2em') . '; line-height: 1;text-align: center; font-weight: bold;background-color: ' . $btnBgColorRGBA . '; ' . $btnBorderCss . ' text-decoration: none; padding:' . $buttonPadding . '; color: ' . $textColor . ' !important; border-radius: ' . $borderRadius . '; display: inline-block;">
				' . $ctaText . '
			</a>
		</div>

	';
	$output .= '<!--<![endif]-->
	
	</div>';

	return $output;
}


function hex2rgba($color, $opacity = 1)
{
	$color = ltrim($color, '#');
	$r = hexdec(substr($color, 0, 2));
	$g = hexdec(substr($color, 2, 2));
	$b = hexdec(substr($color, 4, 2));
	return "rgba($r, $g, $b, $opacity)";
}


function idwiz_get_snippet_chunk($chunk, $templateOptions, $chunkIndex = null, $isEditor = false)
{
	$chunkSettings = $chunk['settings'];
	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? 'data-chunk-index="' . $chunkIndex . '"' : '';

	$chunkFields = $chunk['fields'];

	$backgroundColorCss = generate_background_css($chunkSettings);
	$msoBackgroundColorCss = generate_background_css($chunkSettings);

	$fontCss = 'font-size: 1em; line-height: 1.5;';
	$brandedFont = $chunkSettings['branded_font'] ?? 0;
	if ($brandedFont) {
		$fontCss = "font-family: Poppins, Helvetica, Arial, sans-serif;";
	}

	$visibility = get_visibility_class_and_style($chunkSettings);
	if ($visibility['class'] != '') {
		$tableClassHtml = 'class="' . esc_attr($visibility['class']) . '"';
	} else {
		$tableClassHtml = '';
	}

	$msoTableWrap = $chunkSettings['mso_table_wrap'] ?? false;

	// Retrieve the post id
	$snippetPostId = $chunkFields['select_snippet'];

	// $shortcode = '[wiz_snippet id="' . $snippetPostId . '"]';
	// $snippetContent = do_shortcode($shortcode);

	$snippetContent = get_post_meta($snippetPostId, 'snippet_content', true);

	$output = '';
	$output .= '<div class="chunk id-snippet ' . $chunkClasses . '" ' . $chunkDataAttr . ' style="' . esc_attr($fontCss . $backgroundColorCss . $visibility['inlineStyle']) . '">';

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--[if !mso]><!-->';
	}

	if ($msoTableWrap) {
		$output .= '<!--[if mso]>';
		$output .= '<table ' . $tableClassHtml . ' role="presentation" width="100%" style="table-layout:fixed; ' . esc_attr($fontCss . $msoBackgroundColorCss . $visibility['inlineStyle']) . '">';
		$output .= '<tr><td style="display: block;width:100%;text-align:center; ' . esc_attr($msoBackgroundColorCss) . '" valign="middle">';
		$output .= '<![endif]-->';
	}

	$output .= $snippetContent;

	if ($msoTableWrap) {
		$output .= '<!--[if mso]>';
		$output .= '</td></tr></table>';
		$output .= '<![endif]-->';
	}

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

	$chunkDataAttr = $isEditor ? 'data-chunk-index="' . $chunkIndex . '"' : '';

	$visibility = get_visibility_class_and_style($chunkSettings);
	if ($visibility['class'] != '') {
		$tableClassHtml = 'class="' . esc_attr($visibility['class']) . '"';
	} else {
		$tableClassHtml = '';
	}

	$backgroundColorCss = generate_background_css($chunkSettings);
	$msoBackgroundColorCss = generate_background_css($chunkSettings, '', true);

	$templateFontSize = $templateStyles['font-size']['template_font_size'] ?? '16px';

	$baseTextColor = $chunkSettings['text_base_color'] ?? '#000000';

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

	
		$output .= '<div class="chunk id-raw-html ' . $chunkClasses . ' ' . $visibility['class'] . '" ' . $chunkDataAttr . ' style="' . $visibility['inlineStyle'] . ' ' . $backgroundColorCss . ' color: ' . $baseTextColor . '; padding: ' . $chunkPadding . '; font-size: ' . $templateFontSize . ';">';
	if ($chunkWrap) {
		if ($visibility['class'] == 'mobile-only') {
			$output .= '<!--[if !mso]><!-->';
		}

		$output .= '
		<!--[if mso]>
		<table ' . $tableClassHtml . ' role="presentation"
			style="width:100%;border:0;border-spacing:0; ' . $visibility['inlineStyle'] . ' ' . $msoBackgroundColorCss . '">
			<tr>
				<td class="id-raw-html" style="' . $msoBackgroundColorCss . ' padding: ' . $chunkPadding . '; color: ' . $baseTextColor . '; font-family: Poppins, Arial, sans-serif!important; font-size: ' . $templateFontSize . ';">
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
	$output .= '</div>'; // Close the main wrapper div

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

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "'" : "";

	$backgroundColorCss = generate_background_css($chunkSettings);
	$msoBackgroundColorCss = generate_background_css($chunkSettings, '', true);

	$templateFontSize = $templateStyles['font-size']['template_font_size'] ?? '16px';

	$baseTextColor = $chunkSettings['text_base_color'] ?? '#000000';

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

	$pPadding = $chunkSettings['p_padding'] ?? true;
	$pPaddingClass = $pPadding ? '' : 'noPpad';

	// Note that padding for <p> elements is only set in <style> blocks, so inboxes with no <style> support will not have padding applied in either case.

	$textContent = $chunkFields['plain_text_content'] ?? '<p>Your content goes here!</p>';

	$textContent = add_aria_label_to_links($textContent);

	$output = '';
	$output .= '<div class="chunk id-plain-text ' . $chunkClasses . ' ' . $pPaddingClass . ' ' . $visibility['class'] . '" ' . $chunkDataAttr . ' style="' . $visibility['inlineStyle'] . ' ' . $backgroundColorCss . ' color: ' . $baseTextColor . '; padding: ' . $chunkPadding . '; font-size: ' . $templateFontSize . '; border:1px solid transparent;">';

	if ($visibility['class'] == 'mobile-only') {
		$output .= '<!--[if !mso]><!-->';
	}

	$output .= '<!--[if mso]>
	<table ' . $tableClassHtml . ' role="presentation" 
		style="width:100%;border:0;border-spacing:0; ' . $visibility['inlineStyle'] . ' ' . $msoBackgroundColorCss . '">
		<tr>
			
			<td class="id-plain-text" style="' . $msoBackgroundColorCss . ' padding: ' . $chunkPadding . '; color: ' . $baseTextColor . '; font-family: Poppins, Arial, sans-serif!important; font-size: ' . $templateFontSize . ';">
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



	$output .= '<!--[if mso]>
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

function add_aria_label_to_links($html)
{
	return preg_replace_callback(
		'/<a\s+(.*?)>(.*?)<\/a>/is',
		function ($matches) {
			$attributes = $matches[1];
			$content = $matches[2];

			// Extract link text or use alt attribute if content is empty
			if (trim(strip_tags($content)) === '') {
				// If content is empty, try to get alt attribute
				if (preg_match('/\balt=["\'](.+?)["\']/i', $attributes, $alt_match)) {
					$linkText = $alt_match[1];
				} else {
					$linkText = '';
				}
			} else {
				$linkText = strip_tags($content);
			}

			$linkText = preg_replace('/\s+/', ' ', $linkText); // Replace multiple whitespaces with a single space
			$linkText = trim($linkText); // Trim whitespace from the beginning and end

			// Check if aria-label or title already exists
			$hasAriaLabel = preg_match('/\baria-label=["\'].*?["\']/i', $attributes);
			$hasTitle = preg_match('/\btitle=["\'].*?["\']/i', $attributes);

			// Add aria-label and title if they don't exist and linkText is not empty
			if (!empty($linkText)) {
				if (!$hasAriaLabel) {
					$attributes .= ' aria-label="' . esc_attr($linkText) . '"';
				}
				if (!$hasTitle) {
					$attributes .= ' title="' . esc_attr($linkText) . '"';
				}
			}

			// Open all links in new tab
			if (!preg_match('/\btarget=["\'].*?["\']/i', $attributes)) {
				$attributes .= ' target="_blank"';
			}
			if (!preg_match('/\brel=["\'].*?["\']/i', $attributes)) {
				$attributes .= ' rel="noopener noreferrer"';
			}

			// Create the new <a> tag
			return '<a ' . $attributes . '>' . $content . '</a>';
		},
		$html
	);
}



function idwiz_get_image_chunk($chunk, $templateOptions, $chunkIndex = null, $isEditor = false)
{
	$chunkSettings = $chunk['settings'];

	$chunkSettings = $chunk['settings'];

	$visibility = get_visibility_class_and_style($chunkSettings);
	if ($visibility['class'] != '') {
		$tableClassHtml = 'class="' . esc_attr($visibility['class']) . '"';
	} else {
		$tableClassHtml = '';
	}

	$chunkFields = $chunk['fields'];

	$chunkSettings = $chunk['settings'];

	$chunkPadding = $chunk['settings']['chunk_padding'] ?? '';

	$chunkPaddingCss = $chunkPadding ? 'padding:' . $chunkPadding . ';' : '';
	$msoPaddingToMargin = $chunkPadding ? 'margin:' . $chunkPadding . ';' : 'margin: 0;';

	$image_context = $chunkSettings['image_context'] ?? '';
	$chunkClasses = 'id-image '.$chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "'" : "";

	$templateWidth = $templateOptions['template_styles']['body-and-background']['template_width'] ?? '648';

	$imageSrc = $chunkFields['image_url'] ?? '';
	$cachedImageSrc = get_wizbuilder_image_src($chunkFields['image_url'] ?? '', $isEditor);

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
	$imageAspectRatioResult = get_image_aspect_ratio($imageSrc);// we pass the remote URL here, not the cached data
	$status = $imageAspectRatioResult['status'];
	$imageAspectRatio = $imageAspectRatioResult['data'];

	//error_log(print_r($imageAspectRatioResult, true));

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

	$output .= '<img class="id-image ' . $visibility['class'] . '" src="' . $imageSrc . '" width="' . $msoWidth . '" height="' . $msoHeight . '" ' . $altAttribute . ' style="' . $pointerEventsCss . 'width:100%; max-width:' . $msoWidth . 'px; height:'. $msoHeight.'; max-height:' . $msoHeight . 'px;' . $visibility['inlineStyle'] . ';" />';
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
	$output .= '<img ' . $altAttribute . ' class="id-image ' . $visibility['class'] . '" target="_blank" rel="noopener noreferrer" src="' . $cachedImageSrc . '" style="' . $pointerEventsCss . 'width:100%; height:auto;' . $visibility['inlineStyle'] . '" />';
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



function idwiz_get_standard_header($templateOptions)
{
	$templateStyles = $templateOptions['template_styles'];
	$headerFooterSettings = $templateStyles['header-and-footer'];
	$headerLogo = $headerFooterSettings['template_header_logo'] ?? '';
	if ($headerLogo == 'manual') {
		$headerLogo = $headerFooterSettings['template_header_logo_manual'] ?? '';
	}
	$headerPadding = $headerFooterSettings['header_padding'] ?? '0 0 20px 0';
	$output = '';
	$output .= '<table role="presentation" style="width:100%;border:0;border-spacing:0;table-layout:fixed;font-size: 0;">';
	$output .= '<tr>';
	$output .= '<td style="font-size: 0;line-height:0;margin:0;padding:' . $headerPadding . ';">';
	$output .= '<a href="https://www.idtech.com" style="margin:0; padding: 0;" aria-label="iD Tech Camps" title="iD Tech Camps">';
	$output .= '<img src="' . $headerLogo . '" width="' . $templateStyles['body-and-background']['template_width'] . '" alt="" style="width:' . $templateStyles['body-and-background']['template_width'] . '; max-width:100%;height:auto;display: block;" />';
	$output .= '</a>';
	$output .= '</td>';
	$output .= '</tr>';
	$output .= '</table>';

	return $output;
}
function idwiz_get_standard_footer($templateStyles)
{

	$headerAndFooter = $templateStyles['header-and-footer'];
	$bodyAndBackground = $templateStyles['body-and-background'];
	$linkStyles = $templateStyles['link-styles'];

	$showUnsub = json_decode($headerAndFooter['show_unsub'] ?? 'true');
	$footerBackground = $headerAndFooter['footer-background'] ?? [];
	$footerBackgroundCss = generate_background_css($footerBackground);
	$footerTextColor = $headerAndFooter['template_footer_text_color'] ?? '#343434';
	$templateWidth = $bodyAndBackground['template_width'];
	$templateLinkColor = $linkStyles['template_link_style_color'] ?? '#1b75d0';
	$footerLinkColor = $headerAndFooter['template_footer_link_color'] ?? $templateLinkColor ?? '#343434';

	$gmailBlendDesktop = json_decode($headerAndFooter['footer_force_white_text_on_desktop'] ?? 'false');
	$gmailBlendMobile = json_decode($headerAndFooter['footer_force_white_text_on_mobile'] ?? 'false');
	$gmailBlendDesktopClass = $gmailBlendDesktop ? 'desktop' : '';
	$gmailBlendMobileClass = $gmailBlendMobile ? 'mobile' : '';

	$output = '';
	$output .= '<div class="chunk id-footer" style="' . $footerBackgroundCss . ' max-width: ' . $templateWidth . '; padding: 20px 0 10px 0; font-size: 12px; font-family: Poppins, Arial, sans-serif; text-align: center;">';

	$output .= '<!--[if mso]>
	<table role="presentation" align="center" style="width:' . $templateWidth . ';table-layout:fixed;font-family:Poppins, Arial, sans-serif;">
	<tr>
	<td style="' . $footerBackgroundCss . ' padding: 20px 0 10px 0; font-size: 12px;font-family:Poppins, Arial, sans-serif; text-align: center;">
	<![endif]-->';

	// Social media icons
	$output .= idwiz_get_social_media_icons();

	if ($gmailBlendDesktop || $gmailBlendMobile) {
		$output .= '<div class="gmail-blend-screen ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
		$output .= '<div class="gmail-blend-difference ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
	}

	// Contact information
	$output .= idwiz_get_contact_info($footerTextColor);

	// Unsubscribe links
	if ($showUnsub) {
		$output .= idwiz_get_unsubscribe_links($footerLinkColor);
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
	$footerTextColor = $headerAndFooter['template_footer_text_color'] ?? '#ffffff';
	$templateWidth = $bodyAndBackground['template_width'];

	$gmailBlendDesktop = json_decode($headerAndFooter['footer_force_white_text_on_desktop'] ?? 'false');
	$gmailBlendMobile = json_decode($headerAndFooter['footer_force_white_text_on_mobile'] ?? 'false');
	$gmailBlendDesktopClass = $gmailBlendDesktop ? 'desktop' : '';
	$gmailBlendMobileClass = $gmailBlendMobile ? 'mobile' : '';

	$output = '';
	$output .= '<div class="chunk id-fine-print" style="' . $footerBackgroundCss . ' width: 100%; max-width: ' . $templateWidth . '; padding-bottom: 20px; font-size: 12px;">';

	$output .= '<!--[if mso]>
	<table role="presentation" align="center" style="width:' . $templateWidth . ';table-layout:fixed;">
	<tr>
	<td style="' . $footerBackgroundCss . ' padding-bottom: 20px; font-size: 12px; text-align: center;">
	<![endif]-->';

	if ($finePrintDisclaimer) {
		if ($gmailBlendDesktop || $gmailBlendMobile) {
			$output .= '<div class="gmail-blend-screen ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
			$output .= '<div class="gmail-blend-difference ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
		}
		$output .= '<center style="font-size:12px !important;color:' . $footerTextColor . ';line-height:16px;padding-left: 20px; padding-right: 20px;">' . $finePrintDisclaimer . '</center>';
		if ($gmailBlendDesktop || $gmailBlendMobile) {
			$output .= '</div>';
			$output .= '</div>';
		}
	}

	$output .= '<!--[if mso]>
	</td>
	</tr>
	</table>
	<![endif]-->';

	$output .= '</div>';

	return $output;
}

// Helper functions

function idwiz_get_social_media_icons()
{
	$icons = [
		['url' => 'https://www.facebook.com/computercamps', 'title' => 'iD Tech on Facebook', 'src' => 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/e1322b55-a0f4-4246-b530-3a0790a4c361.png'],
		['url' => 'https://twitter.com/idtechcamps', 'title' => 'iD Tech on Twitter', 'src' => 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/94d22bf5-cc89-43f6-a4d8-8567c4e81d9d.png'],
		['url' => 'https://www.instagram.com/idtech/', 'title' => 'iD Tech on Instagram', 'src' => 'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/6b969394-7c4c-45c1-9079-7e98dddcbbb2.png']
	];

	$output = '';
	foreach ($icons as $icon) {
		$output .= '<span style="margin:0;font-family:Poppins,sans-serif;font-size:12px;line-height:16px;">';
		$output .= '<a href="' . $icon['url'] . '" title="' . $icon['title'] . '" aria-label="' . $icon['title'] . '">';
		$output .= '<img width="35" alt="' . $icon['title'] . '" style="width: 35px;height: auto;" src="' . $icon['src'] . '" />';
		$output .= '</a></span> ';
	}
	$output .= '<br />';
	return $output;
}

function idwiz_get_contact_info($footerTextColor)
{
	return '<p style="color:' . $footerTextColor . ';margin:0;padding: 1em 0;font-family:Poppins,sans-serif;font-size:12px;line-height:16px;">
		<strong>Contact Us:</strong><br />
		1-888-709-8324<br />
		+1-408-871-3700 (international)<br /><br />

		<strong>Address:</strong> P.O. Box 111720, Campbell, CA 95011<br /><br />

		Copyright © {{now format=\'yyyy\'}} All rights reserved.
	</p>';
}

function idwiz_get_unsubscribe_links($footerLinkColor)
{
	$output = '{{#if userId}}';
	$output .= '<a href="{{hostedUnsubscribeUrl}}" aria-label="Manage Subscription Preferences" title="Manage Subscription Preferences" style="color: ' . $footerLinkColor . ';">Manage preferences</a><br />';
	$output .= '{{/if}}';
	$output .= '<a href="{{unsubscribeMessageTypeUrl}}" aria-label="Unsubscribe from emails like this" title="Manage Subscription Preferences" style="color: ' . $footerLinkColor . ';">Unsubscribe from emails like this</a><br />';
	$output .= '<a href="{{unsubscribeUrl}}" aria-label="Unsubscribe from all marketing emails" title="Manage Subscription Preferences" style="color: ' . $footerLinkColor . ';">Unsubscribe from all marketing emails</a><br /><br />';
	return $output;
}

function idwiz_get_email_top($templateSettings, $templateStyles, $rows)
{

	ob_start();
?>
	<!DOCTYPE html>
	<html lang="en" xmlns="https://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office" title="iD Tech Camps">

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
		$darkModeSupport = json_decode($templateStyles['custom-styles']['dark-mode-support'], true) ?? false;
		if ($darkModeSupport === true) { ?>
			<meta name="color-scheme" content="light dark">
			<meta name="supported-color-schemes" content="light dark">
		<?php } ?>



		<title>
			<?php
			$subjectLine = $templateSettings['subject_line'] ?? '';
			// re-convert back to non-html-entities
			echo html_entity_decode($subjectLine);
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
		echo get_snippet_css($rows);
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

	?>


<?php
	return ob_get_clean();
}


function idwiz_get_email_body_top($templateStyles)
{
	$templateWidth = $templateStyles['body-and-background']['template_width'];
	$templateWidth = (int) $templateWidth > 0 ? (int) $templateWidth : 648;
	$bodyBackgroundCss = generate_background_css($templateStyles['body-and-background']['body-background'], 'body_background_');
	$pageBackgroundCss = generate_background_css($templateStyles['body-and-background']['page-background'], 'page_background_');

	$previewTextHack = get_preview_text_hack();

	$return = '<body class="body" id="body" style="margin: 0; padding: 0; word-spacing: normal;">
	<div style="display: none; max-height: 0px; overflow: hidden;">
	' . $previewTextHack . '
	</div>
	<div role="article" aria-roledescription="email" lang="en" style="-webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; word-spacing:normal;text-align: center;">
	<table role="presentation" style="width: 100%; border: 0; border-spacing: 0;margin: 0 auto; ' . $bodyBackgroundCss . '" class="email-wrapper">
	<tr>
	<td align="center">
	<!--[if mso]> 
	<table role="presentation" align="center" style="width:' . $templateWidth . 'px; border: 0; border-spacing: 0;margin: 0 auto;"> 
	<tr> 
	<td style="padding:20px 0; ' . $pageBackgroundCss . '"> 
	<![endif]-->
	<div class="outer" style="width: 100%; max-width: ' . $templateWidth . 'px; margin: 20px auto; ' . $pageBackgroundCss . '">';

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
						<![endif]-->
					</td>
				</tr>
			</table>
		</div>
	</body>';
	return $emailBottom;
}

function idwiz_get_email_bottom()
{
	return '</html>';
}
