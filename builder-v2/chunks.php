<?php





function idwiz_get_spacer_chunk( $chunk, $templateOptions, $chunkIndex = null, $isEditor = false ) {
	//print_r($chunk);

	$chunkSettings = $chunk['settings'];
	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "'" : "";

	$chunkFields = $chunk['fields'];

	$spacerHeight = $chunkFields['spacer_height'] ?? '60px';

	$backgroundColorCss = generate_background_css( $chunkSettings );

	$visibility = get_visibility_class_and_style( $chunkSettings );
	if ( $visibility['class'] != '' ) {
		$tableClassHtml = 'class="spacer ' . esc_attr( $visibility['class'] ) . '"';
	} else {
		$tableClassHtml = 'class="spacer"';
	}

	ob_start();
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}

	?>



	<!--[if mso]>
	<table <?php echo $tableClassHtml; ?> role="presentation" width="100%" aria-hidden="true" style="table-layout:fixed; <?php echo $visibility['inlineStyle']; ?>">
	<tr>
	<td style="width:100%;text-align:center;mso-line-height-rule:exactly; <?php echo $backgroundColorCss; ?>" valign="middle">
	<![endif]-->
	<div class="chunk id-spacer <?php echo $chunkClasses; ?> <?php echo $visibility['class']; ?>" <?php echo $chunkDataAttr; ?>
		style="<?php echo $visibility['inlineStyle']; ?>" aria-hidden="true">
		<div
			style="line-height:<?php echo $spacerHeight; ?>;height:<?php echo $spacerHeight; ?>; font-size: <?php echo $spacerHeight; ?>; <?php echo $backgroundColorCss; ?>">
			&#8202;</div>
	</div>
	<!--[if mso]>
	</td>
	</tr>
	</table>
	<![endif]-->
	<?php
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--<![endif]-->';
	}
	return ob_get_clean();
}


function idwiz_get_button_chunk( $chunk, $templateOptions, $chunkIndex = null, $isEditor = false ) {
	$chunkSettings = $chunk['settings'];
	$chunkFields = $chunk['fields'];

	$chunkPadding = $chunkSettings['chunk_padding'] ?? '0px';
	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? 'data-chunk-index="' . $chunkIndex . '"' : '';

	$ctaText = $chunkFields['button_text'] ?? 'Click here';
	$ctaUrl = $chunkFields['button_link'] ?? 'https://www.idtech.com';


	$backgroundColorCss = generate_background_css( $chunkSettings );
	$msoBackgroundColorCss = generate_background_css( $chunkSettings, '', true );

	$backgroundColor = $chunkSettings['background_color'] ?? 'transparent';

	$textAlign = $chunkFields['button_align'] ?? 'center';
	$btnBgColor = $chunkFields['button_fill_color'] ?? '#343434';
	$textColor = $chunkFields['button_text_color'] ?? '#fff';

	$btnBorderCss = '';
	$borderColor = $chunkFields['button_border_color'];
	$borderSize = $chunkFields['button_border_size'] ?? 0;
	if ( $borderColor ) {
		if ( $borderSize > 0 ) {
			$btnBorderCss = 'border: ' . $borderSize . ' solid ' . $borderColor . ';';
		}
	}

	$borderRadius = $chunkFields['button_border_radius'] ?? "30px";
	$msoBorderPerc = 5;
	if ( floatval( $borderRadius ) >= 20 ) {
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
	$ctaTextLength = strlen( $ctaText );
	$additionalWidth = ( $ctaTextLength > $charThreshold ) ? ( $ctaTextLength - $charThreshold ) * $additionalWidthPerChar : 0;
	$buttonWidth = $baseWidth + $additionalWidth;

	$visibility = get_visibility_class_and_style( $chunkSettings );
	if ( $visibility['class'] != '' ) {
		$tableClassHtml = 'class="' . esc_attr( $visibility['class'] ) . '"';
	} else {
		$tableClassHtml = '';
	}

	//print_r( $chunkSettings );
	ob_start();

	echo '<!--[if mso]>';
	echo '<table ' . $tableClassHtml . ' role="presentation" width="100%" style="table-layout:fixed; ' . esc_attr( $msoBackgroundColorCss . $visibility['inlineStyle'] ) . '">';
	echo '<tr><td style="width:100%;text-align:center; ' . esc_attr( $msoBackgroundColorCss ) . '" valign="middle">';
	echo '<![endif]-->';



	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}



	?>

	<div class="chunk id-button <?php echo $chunkClasses; ?> <?php echo $visibility['class']; ?>" <?php echo $chunkDataAttr; ?>
		style="width: 100%; border: 0; border-spacing: 0; <?php echo $visibility['inlineStyle']; ?> <?php echo $backgroundColorCss; ?>">
		<table role="presentation" table-layout="fixed" style="width: 100%; <?php echo $backgroundColorCss; ?>">
			<tr>
				<td
					style="<?php echo $backgroundColorCss; ?> padding: <?php echo $chunkPadding; ?>; border: 0!important; text-align: <?php echo $textAlign; ?>; font-family: Poppins, Arial, sans-serif; font-size: <?php echo $templateOptions['template_styles']['template_font_size'] ?? '16px'; ?>">
					<!--[if mso]>
					<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo $ctaUrl; ?>" style="height: 50px; v-text-anchor: middle; width: <?php echo $buttonWidth; ?>px;" arcsize="<?php echo $msoBorderPerc; ?>%" strokecolor="<?php echo $vmlBorderColor; ?>" strokeweight="<?php echo $vmlBorderWeight; ?>px" fillcolor="<?php echo $btnBgColor; ?>">
						<w:anchorlock/>
						<center class="id-button" style="mso-style-textfill-type:gradient; mso-style-textfill-fill-gradientfill-stoplist:"0 \<?php echo $textColor; ?> 0 100000\,100000 \<?php echo $textColor; ?> 0 100000";color: <?php echo $vmlBorderColor; ?> !important; font-family: Poppins, Arial, sans-serif; font-size: 1.1em!important; line-height: 1.6em;font-weight: bold;mso-text-raise: 10pt;"><?php echo $ctaText; ?></center>
					</v:roundrect>
				<![endif]-->

					<!--[if !mso]> <!-->
					<a href="<?php echo $ctaUrl; ?>" aria-label="<?php echo $ctaText; ?>" class="id-button"
						style="font-size: <?php echo $chunk['fields']['button_font_size'] ?? '1.2em'; ?>; line-height: 1;text-align: center; font-weight: bold;background: <?php echo $btnBgColor; ?>; <?php echo $btnBorderCss; ?> text-decoration: none; padding:<?php echo $buttonPadding; ?>; color: <?php echo $textColor; ?> !important; border-radius: <?php echo $borderRadius; ?>; display: inline-block;">
						<?php echo $ctaText; ?>
					</a>
					<!-- <![endif]-->
				</td>
			</tr>
		</table>
	</div>
	<?php

	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--<![endif]-->';
	}

	echo '<!--[if mso]>';
	echo '</td></tr></table>';
	echo '<![endif]-->';


	return ob_get_clean();
}


function idwiz_get_snippet_chunk( $chunk, $templateOptions, $chunkIndex = null, $isEditor = false ) {
	$chunkSettings = $chunk['settings'];
	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? 'data-chunk-index="' . $chunkIndex . '"' : '';

	$chunkFields = $chunk['fields'];

	$backgroundColorCss = generate_background_css( $chunkSettings );
	$msoBackgroundColorCss = generate_background_css( $chunkSettings );

	$fontCss = 'font-size: 1em; line-height: 1.5;';
	$brandedFont = $chunkSettings['branded_font'] ?? 0;
	if ( $brandedFont ) {
		$fontCss = "font-family: Poppins, Helvetica, Arial, sans-serif;";
	}

	$visibility = get_visibility_class_and_style( $chunkSettings );
	if ( $visibility['class'] != '' ) {
		$tableClassHtml = 'class="' . esc_attr( $visibility['class'] ) . '"';
	} else {
		$tableClassHtml = '';
	}

	$msoTableWrap = $chunkSettings['mso_table_wrap'] ?? false;

	// Retrieve the post object from the 'snippet' ACF field
	$snippetPostId = $chunkFields['select_snippet'];

	$shortcode = '[wiz_snippet id="' . $snippetPostId . '"]';
	$snippetContent = do_shortcode( $shortcode );

	ob_start();
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}

	if ( $msoTableWrap ) {
		echo '<!--[if mso]>';
		echo '<table ' . $tableClassHtml . ' role="presentation" width="100%" style="table-layout:fixed; ' . esc_attr( $fontCss . $msoBackgroundColorCss . $visibility['inlineStyle'] ) . '">';
		echo '<tr><td style="width:100%;text-align:center; ' . esc_attr( $msoBackgroundColorCss ) . '" valign="middle">';
		echo '<![endif]-->';
	}

	echo '<div class="chunk id-snippet ' . $chunkClasses . '" ' . $chunkDataAttr .' style="' . esc_attr( $fontCss . $backgroundColorCss . $visibility['inlineStyle'] ) . '">';
	echo $snippetContent;
	echo '</div>';

	if ( $msoTableWrap ) {
		echo '<!--[if mso]>';
		echo '</td></tr></table>';
		echo '<![endif]-->';
	}

	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--<![endif]-->';
	}

	return ob_get_clean();
}

function idwiz_get_raw_html_chunk( $chunk, $templateOptions, $chunkIndex = null, $isEditor = false ) {
	$templateStyles = $templateOptions['template_styles'] ?? [];

	$chunkFields = $chunk['fields'] ?? [];
	$chunkSettings = $chunk['settings'] ?? [];

	$chunkWrap = $chunkSettings['chunk_wrap'] ?? false;

	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? 'data-chunk-index="' . $chunkIndex . '"' : '';

	$visibility = get_visibility_class_and_style( $chunkSettings );
	if ( $visibility['class'] != '' ) {
		$tableClassHtml = 'class="' . esc_attr( $visibility['class'] ) . '"';
	} else {
		$tableClassHtml = '';
	}

	$backgroundColorCss = generate_background_css( $chunkSettings );
	$msoBackgroundColorCss = generate_background_css( $chunkSettings, '', true );

	$templateFontSize = $templateStyles['font-size']['template_font_size'] ?? '16px';

	$baseTextColor = $chunkSettings['text_base_color'] ?? '#000000';

	$gmailBlendDesktop = false;
	if ( isset( $chunkSettings['force_white_text_on_desktop'] ) ) {
		$gmailBlendDesktop = json_decode( $chunkSettings['force_white_text_on_desktop'] );
	}
	$gmailBlendMobile = false;
	if ( isset( $chunkSettings['force_white_text_on_desktop'] ) ) {
		$gmailBlendMobile = json_decode( $chunkSettings['force_white_text_on_mobile'] );
	}

	$gmailBlendDesktopClass = $gmailBlendDesktop ? 'desktop' : '';
	$gmailBlendMobileClass = $gmailBlendMobile ? 'mobile' : '';

	$chunkPadding = $chunkSettings['chunk_padding'] ?? '0px';

	$rawHtmlContent = $chunkFields['raw_html_content'] ?? '<p>Your code goes here!</p>';

	ob_start();

	if ( $chunkWrap ) {

		if ( $visibility['class'] == 'mobile-only' ) {
			echo '<!--[if !mso]><!-->';
		}


		?>
		<!--[if mso]>
	<table <?php echo $tableClassHtml; ?> role="presentation"
		style="width:100%;border:0;border-spacing:0; <?php echo $visibility['inlineStyle']; ?> <?php echo $msoBackgroundColorCss; ?>">
		<tr>
			
			<td class="id-raw-html" style="<?php echo $msoBackgroundColorCss; ?> padding: <?php echo $chunkPadding; ?>; color: <?php echo $baseTextColor; ?>; font-family: Poppins, Arial, sans-serif!important; font-size: <?php echo $templateFontSize; ?>;">
			<![endif]-->

		<div class="chunk id-raw-html <?php echo $chunkClasses; ?> <?php echo $visibility['class']; ?>" <?php echo $chunkDataAttr; ?>
			style="<?php echo $visibility['inlineStyle']; ?> <?php echo $backgroundColorCss; ?> color: <?php echo $baseTextColor; ?>; padding: <?php echo $chunkPadding; ?>; font-size: <?php echo $templateFontSize; ?>; border:1px solid transparent;">
			<?php
			if ( $gmailBlendDesktopClass || $gmailBlendMobileClass ) {
				echo '<div class="gmail-blend-screen ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
				echo '<div class="gmail-blend-difference ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
			}
	}
	echo $rawHtmlContent;
	if ( $chunkWrap ) {
		if ( $gmailBlendDesktopClass || $gmailBlendMobileClass ) {
			echo '</div>';
			echo '</div>';
		}
		?>
		</div>

		<!--[if mso]>
			</td>
			
		</tr>
	</table>
	<![endif]-->


		<?php


		if ( $visibility['class'] == 'mobile-only' ) {
			echo '<!--<![endif]-->';
		}
	}
	return ob_get_clean();
}

function idwiz_get_plain_text_chunk( $chunk, $templateOptions, $chunkIndex = null, $isEditor = false ) {
	$templateStyles = $templateOptions['template_styles'] ?? [];

	$chunkFields = $chunk['fields'] ?? [];
	$chunkSettings = $chunk['settings'] ?? [];

	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$visibility = get_visibility_class_and_style( $chunkSettings );
	if ( $visibility['class'] != '' ) {
		$tableClassHtml = 'class="' . esc_attr( $visibility['class'] ) . '"';
	} else {
		$tableClassHtml = '';
	}

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "'" : "";

	$backgroundColorCss = generate_background_css( $chunkSettings );
	$msoBackgroundColorCss = generate_background_css( $chunkSettings, '', true );

	$templateFontSize = $templateStyles['font-size']['template_font_size'] ?? '16px';

	$baseTextColor = $chunkSettings['text_base_color'] ?? '#000000';

	$gmailBlendDesktop = false;
	if ( isset( $chunkSettings['force_white_text_on_desktop'] ) ) {
		$gmailBlendDesktop = json_decode( $chunkSettings['force_white_text_on_desktop'] );
	}
	$gmailBlendMobile = false;
	if ( isset( $chunkSettings['force_white_text_on_desktop'] ) ) {
		$gmailBlendMobile = json_decode( $chunkSettings['force_white_text_on_mobile'] );
	}

	$gmailBlendDesktopClass = $gmailBlendDesktop ? 'desktop' : '';
	$gmailBlendMobileClass = $gmailBlendMobile ? 'mobile' : '';



	$chunkPadding = $chunkSettings['chunk_padding'] ?? '0px';

	$pPadding = $chunkSettings['p_padding'] ?? true;
	$pPaddingClass = $pPadding ? '' : 'noPpad';

	// Note that padding for <p> elements is only set in <style> blocks, so inboxes with no <style> support will not have padding applied in either case.

	$textContent = $chunkFields['plain_text_content'] ?? '<p>Your content goes here!</p>';

	$textContent = add_aria_label_to_links( $textContent );

	ob_start();
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}


	?>
	<!--[if mso]>
	<table <?php echo $tableClassHtml; ?> role="presentation" 
		style="width:100%;border:0;border-spacing:0; <?php echo $visibility['inlineStyle']; ?> <?php echo $msoBackgroundColorCss; ?>">
		<tr>
			
			<td class="id-plain-text" style="<?php echo $msoBackgroundColorCss; ?> padding: <?php echo $chunkPadding; ?>; color: <?php echo $baseTextColor; ?>; font-family: Poppins, Arial, sans-serif!important; font-size: <?php echo $templateFontSize; ?>;">
			<![endif]-->

	<div class="chunk id-plain-text <?php echo $chunkClasses; ?> <?php echo $pPaddingClass; ?> <?php echo $visibility['class']; ?>" <?php echo $chunkDataAttr; ?>
		style="<?php echo $visibility['inlineStyle']; ?> <?php echo $backgroundColorCss; ?> color: <?php echo $baseTextColor; ?>; padding: <?php echo $chunkPadding; ?>; font-size: <?php echo $templateFontSize; ?>; border:1px solid transparent;">
		<?php
		if ( $gmailBlendDesktopClass || $gmailBlendMobileClass ) {
			echo '<div class="gmail-blend-screen ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
			echo '<div class="gmail-blend-difference ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
		}
		echo wpautop( stripslashes( $textContent ) );
		if ( $gmailBlendDesktopClass || $gmailBlendMobileClass ) {
			echo '</div>';
			echo '</div>';
		}
		?>
	</div>

	<!--[if mso]>
			</td>
			
		</tr>
	</table>
	<![endif]-->


	<?php


	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--<![endif]-->';
	}
	return ob_get_clean();
}

function add_aria_label_to_links( $html ) {
	return preg_replace_callback(
		'/<a\s+(.*?)>(.*?)<\/a>/is',
		function ($matches) {
			// $matches[1] contains all existing attributes, $matches[2] is the link text
			$linkText = strip_tags( $matches[2] ); // Remove any HTML tags from the link text
			$linkText = preg_replace( '/\s+/', ' ', $linkText ); // Replace multiple whitespaces with a single space
			$linkText = trim( $linkText ); // Trim whitespace from the beginning and end
	
			// Check if aria-label or title already exists
			$hasAriaLabel = preg_match( '/\baria-label=["\'].*?["\']/i', $matches[1] );
			$hasTitle = preg_match( '/\btitle=["\'].*?["\']/i', $matches[1] );

			// Add aria-label and title if they don't exist
			$newAttributes = $matches[1];
			if ( ! $hasAriaLabel ) {
				$newAttributes .= ' aria-label="' . $linkText . '"';
			}
			if ( ! $hasTitle ) {
				$newAttributes .= ' title="' . $linkText . '"';
			}

			// Create the new <a> tag
			return '<a ' . $newAttributes . '>' . $matches[2] . '</a>';
		},
		$html
	);
}



function idwiz_get_image_chunk( $chunk, $templateOptions, $chunkIndex = null, $isEditor = false ) {
	$chunkSettings = $chunk['settings'];

	$chunkSettings = $chunk['settings'];

	$visibility = get_visibility_class_and_style( $chunkSettings );
	if ( $visibility['class'] != '' ) {
		$tableClassHtml = 'class="' . esc_attr( $visibility['class'] ) . '"';
	} else {
		$tableClassHtml = '';
	}

	$chunkFields = $chunk['fields'];

	$chunkSettings = $chunk['settings'];

	$chunkPadding = $chunk['settings']['chunk_padding'] ?? '';

	$chunkPaddingCss = $chunkPadding ? 'padding:' . $chunkPadding . ';' : '';
	$msoPaddingToMargin = $chunkPadding ? 'margin:' . $chunkPadding . ';' : 'margin: 0;';

	$image_context = $chunkSettings['image_context'] ?? '';
	$chunkClasses = $chunkSettings['chunk_classes'] ?? '';

	$chunkDataAttr = $isEditor ? "data-chunk-index='" . $chunkIndex . "'" : "";

	$templateWidth = $templateOptions['template_styles']['body-and-background']['template_width'] ?? '648';

	$imageSrc = $chunkFields['image_url'] ?? '';
	$imageLink = $chunkFields['image_link'] ?? '';
	$imageAlt = $chunkFields['image_alt'] ?? '';

	// Specific widths for MSO clients based on image_context
	$msoWidth = $templateWidth; // Default full width

	if ( $image_context == 'two-col' ) {
		$msoWidth = $templateWidth > 0 ? round( $templateWidth / 2, 0 ) : $templateWidth;
	} elseif ( $image_context == 'three-col' ) {
		$msoWidth = $templateWidth > 0 ? round( $templateWidth / 3, 0 ) : $templateWidth;
	} elseif ( $image_context == 'sidebar-main' ) {
		$msoWidth = $templateWidth > 0 ? round( $templateWidth * 0.6667, 0 ) : $templateWidth;
	} elseif ( $image_context == 'sidebar-side' ) {
		$msoWidth = $templateWidth > 0 ? round( $templateWidth * 0.3333, 0 ) : $templateWidth;
	}

	$backgroundColorCss = generate_background_css( $chunkSettings );
	$msoBackgroundColorCss = generate_background_css( $chunkSettings, '', true );



	ob_start();
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}

	// Check if alt text is empty and set aria-hidden accordingly
	$ariaHidden = empty( $imageAlt ) ? 'aria-hidden="true"' : '';
	$altAttribute = empty( $imageAlt ) ? '' : 'alt="' . $imageAlt . '"';

	// MSO conditional for Outlook
	echo '<!--[if mso]>';
	echo '<table ' . $tableClassHtml . ' role="presentation" style="width:100%;border:0;border-spacing:0;margin: 0;' . $visibility['inlineStyle'] . '">
		<tr>
		<td style="font-size: 0; line-height: 0; ' . $msoBackgroundColorCss . '">';
	if ( $imageLink ) {
		echo '<a href="' . $imageLink . '" ' . $ariaHidden . ' class="id-image-link" title="' . $imageAlt . '" style="' . $msoPaddingToMargin . 'display: block;padding: 0; line-height: 0;font-size:0;text-decoration:none;">';
	}

	// If no link, add pointer-events: none to prevent click interaction
	$pointerEventsCss = ! $imageLink ? 'pointer-events: none;' : '';

	echo '<img class="id-image ' . $visibility['class'] . '" src="' . $imageSrc . '" width="' . $msoWidth . '" ' . $altAttribute . ' style="' . $pointerEventsCss . 'width:100%; max-width:' . $msoWidth . 'px; height:auto;' . $visibility['inlineStyle'] . '" />';
	if ( $imageLink ) {
		echo '</a>';
	}
	echo '</td></tr></table>';
	echo '<![endif]-->';

	// Non-MSO markup for other clients
	echo '<!--[if !mso]> <!-->';
	echo '<div class="chunk id-image ' . $chunkClasses . ' ' . $visibility['class'] . '" ' . $chunkDataAttr . ' style="' . $backgroundColorCss . ' ' . $visibility['inlineStyle'] . $chunkPaddingCss . '">';
	if ( $imageLink ) {
		echo '<a href="' . $imageLink . '" ' . $ariaHidden . ' class="id-image-link" title="' . $imageAlt . '">';
	}
	echo '<img ' . $altAttribute . ' class=" ' . $visibility['class'] . '" src="' . $imageSrc . '" style="' . $pointerEventsCss . 'width:100%; height:auto;' . $visibility['inlineStyle'] . '" />';
	if ( $imageLink ) {
		echo '</a>';
	}
	echo '</div>';
	echo '<!-- <![endif]-->';

	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--<![endif]-->';
	}
	return ob_get_clean();
}



function idwiz_get_standard_header( $templateOptions ) {
	$templateStyles = $templateOptions['template_styles'];
	$headerFooterSettings = $templateStyles['header-and-footer'];
	$headerLogo = $headerFooterSettings['template_header_logo'] ?? '';
	if ( $headerLogo == 'manual' ) {
		$headerLogo = $headerFooterSettings['template_header_logo_manual'] ?? '';
	}
	$headerPadding = $headerFooterSettings['header_padding'] ?? '0 0 20px 0';
	ob_start();
	?>

	<table role="presentation" style="width:100%;border:0;border-spacing:0;table-layout:fixed;font-size: 0;">
		<tr>
			<td style="font-size: 0;line-height:0;margin:0;padding:<?php echo $headerPadding; ?>;">
				<a href="https://www.idtech.com" style="margin:0; padding: 0;" aria-label="iD Tech Camps"
					title="iD Tech Camps">
					<img src="<?php echo $headerLogo; ?>"
						width="<?php echo $templateStyles['body-and-background']['template_width']; ?>" alt=""
						style="width:<?php echo $templateStyles['body-and-background']['template_width']; ?>; max-width:100%;height:auto;display: block;" />
				</a>
			</td>
		</tr>
	</table>


	<?php
	return ob_get_clean();
}
function idwiz_get_standard_footer( $templateoptions, $showUnsub = true ) {
	$templateSettings = $templateoptions['message_settings'] ?? [];
	$templateStyles = $templateoptions['template_styles'] ?? [];
	//$footerBackground = $templateStyles['header-and-footer']['template_footer_color'] ?? false;
	$footerBackground = $templateStyles['header-and-footer']['footer-background'] ?? [];
	$footerBackgroundCss = generate_background_css( $footerBackground );
	$footerTextColor = $templateStyles['header-and-footer']['template_footer_text_color'] ?? '#343434';

	$gmailBlendDesktop = false;
	if ( isset( $templateStyles['header-and-footer']['footer_force_white_text_on_desktop'] ) ) {
		$gmailBlendDesktop = json_decode( $templateStyles['header-and-footer']['footer_force_white_text_on_desktop'] );
	}
	$gmailBlendMobile = false;
	if ( isset( $templateStyles['header-and-footer']['footer_force_white_text_on_mobile'] ) ) {
		$gmailBlendMobile = json_decode( $templateStyles['header-and-footer']['footer_force_white_text_on_mobile'] );
	}

	$gmailBlendDesktopClass = $gmailBlendDesktop ? 'desktop' : '';
	$gmailBlendMobileClass = $gmailBlendMobile ? 'mobile' : '';

	$templateLinkColor = $templateStyles['link-styles']['template_link_style_color'] ?? '#1b75d0';
	$footerLinkColor = $templateStyles['header-and-footer']['template_footer_link_color'] ?? $templateLinkColor ?? '#343434';

	ob_start();
	?>
	<!--[if mso]> 
	<table role="presentation" align="center" style="width:<?php echo $templateStyles['body-and-background']['template_width']; ?>;table-layout:fixed;font-family:Poppins, Arial, sans-serif;"> 
	<tr> 
	<td style="<?php echo $footerBackgroundCss; ?>; padding: 20px 0 10px 0; font-size: 12px;font-family:Poppins, Arial, sans-serif; text-align: center;"> 
	<![endif]-->
	<div
		style="<?php echo $footerBackgroundCss; ?>; max-width: <?php echo $templateStyles['body-and-background']['template_width']; ?>; padding: 20px 0 10px 0; font-size: 12px;font-family:Poppins, Arial, sans-serif; text-align: center;">

		<span style="margin:0;font-family:Poppins,sans-serif;font-size:12px;line-height:16px;">
			<a href="https://www.facebook.com/computercamps" title="iD Tech on Facebook"
				aria-label="iD Tech on Facebook"><img width="35" alt="iD Tech on Facebook" style="width: 35px;height: auto;"
					src="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/e1322b55-a0f4-4246-b530-3a0790a4c361.png" /></a></span>
		&nbsp;
		<span style="margin:0;font-family:Poppins,sans-serif;font-size:12px;line-height:16px;">
			<a href="https://twitter.com/idtechcamps" title="iD Tech on Twitter" aria-label="iD Tech on Twitter"><img
					width="35" alt="iD Tech on Twitter" style="width: 35px;height: auto;"
					src="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/94d22bf5-cc89-43f6-a4d8-8567c4e81d9d.png" /></a></span>
		&nbsp;
		<span style="margin:0;font-family:Poppins,sans-serif;font-size:12px;line-height:16px;">
			<a href="https://www.instagram.com/idtech/" title="iD Tech on Instagram" aria-label="iD Tech on Instagram"><img
					width="35" alt="iD Tech on Instagram" style="width: 35px;height: auto;"
					src="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/6b969394-7c4c-45c1-9079-7e98dddcbbb2.png" /></a></span>
		<br />
		<?php
		if ( $gmailBlendDesktopClass || $gmailBlendMobileClass ) {
			echo '<div class="gmail-blend-screen ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
			echo '<div class="gmail-blend-difference ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
		}
		?>
		<p
			style="color:<?php echo $footerTextColor; ?>;margin:0;padding: 1em 0;font-family:Poppins,sans-serif;font-size:12px;line-height:16px;">
			<strong>Contact Us:</strong><br />
			1-888-709-8324<br />
			+1-408-871-3700 (international)<br /><br />

			<strong>Address:</strong> P.O. Box 111720, Campbell, CA 95011<br /><br />

			Copyright © {{now format='yyyy'}} All rights reserved.
		</p>

		<?php if ( $showUnsub ) { ?>
			<?php echo '{{#if userId}}'; ?>
			<a href="{{hostedUnsubscribeUrl}}" aria-label="Manage Subscription Preferences"
				title="Manage Subscription Preferences" style="color: <?php echo $footerLinkColor; ?>;">Manage
				preferences</a>
			<br />
			<?php echo '{{/if}}'; ?>

			<a href="{{unsubscribeMessageTypeUrl}}" aria-label="Unsubscribe from emails like this"
				title="Manage Subscription Preferences" style="color: <?php echo $footerLinkColor; ?>;">Unsubscribe from emails
				like this</a>
			<br />
			<a href="{{unsubscribeUrl}}" aria-label="Unsubscribe from all marketing emails"
				title="Manage Subscription Preferences" style="color: <?php echo $footerLinkColor; ?>;">Unsubscribe from all
				marketing emails</a>
			<br /><br />
		<?php } ?>
		<?php
		if ( $gmailBlendDesktopClass || $gmailBlendMobileClass ) {
			echo '</div>';
			echo '</div>';
		}
		?>
	</div>
	<!--[if mso]> 
	</td>
	</tr>
	</table>
	<![endif]-->
	<?php
	return ob_get_clean();
}

function idwiz_get_fine_print_disclaimer( $templateOptions ) {
	//print_r($templateOptions);
	$finePrintDisclaimer = $templateOptions['message_settings']['fine_print_disclaimer'];
	$templateStyles = $templateOptions['template_styles'];
	//$footerBackground = $templateStyles['header-and-footer']['template_footer_color'] != 'rgba(0, 0, 0, 0)' ? $templateStyles['header-and-footer']['template_footer_color'] : 'transparent';
	$footerBackground = $templateStyles['header-and-footer']['footer-background'] ?? [];
	$footerBackgroundCss = generate_background_css( $footerBackground );
	$footerTextColor = $templateStyles['header-and-footer']['template_footer_text_color'] ?? '#ffffff';
	$gmailBlendDesktop = false;
	if ( isset( $templateStyles['header-and-footer']['footer_force_white_text_on_desktop'] ) ) {
		$gmailBlendDesktop = json_decode( $templateStyles['header-and-footer']['footer_force_white_text_on_desktop'] );
	}
	$gmailBlendMobile = false;
	if ( isset( $templateStyles['header-and-footer']['footer_force_white_text_on_mobile'] ) ) {
		$gmailBlendMobile = json_decode( $templateStyles['header-and-footer']['footer_force_white_text_on_mobile'] );
	}

	$gmailBlendDesktopClass = $gmailBlendDesktop ? 'desktop' : '';
	$gmailBlendMobileClass = $gmailBlendMobile ? 'mobile' : '';
	ob_start();
	?>
	<!--[if mso]> 
	<table role="presentation" align="center" style="width:<?php echo $templateStyles['body-and-background']['template_width']; ?>;table-layout:fixed;"> 
	<tr> 
	<td style="<?php echo $footerBackgroundCss; ?> padding-bottom: 20px; font-size: 12px;">
	<![endif]-->
	<div
		style="<?php echo $footerBackgroundCss; ?> width: 100%; max-width: <?php echo $templateStyles['body-and-background']['template_width']; ?>; padding-bottom: 20px; font-size: 12px;">
		<?php
		if ( $finePrintDisclaimer ) {
			if ( $gmailBlendDesktopClass || $gmailBlendMobileClass ) {
				echo '<div class="gmail-blend-screen ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
				echo '<div class="gmail-blend-difference ' . $gmailBlendDesktopClass . ' ' . $gmailBlendMobileClass . '">';
			}
			echo '<center style="font-size:12px !important;color:' . $footerTextColor . ';line-height:16px;padding-left: 20px; padding-right: 20px;">' . $finePrintDisclaimer . '</center>';
			if ( $gmailBlendDesktopClass || $gmailBlendMobileClass ) {
				echo '</div>';
				echo '</div>';
			}
		}
		?>
	</div>
	<!--[if mso]> 
	</td>
	</tr>
	</table>
	<![endif]-->
	<?php
	return ob_get_clean();
}

function idwiz_get_email_top( $templateSettings, $templateStyles, $rows ) {

	//error_log(print_r($templateSettings, true));
	//error_log(print_r($templateStyles, true));
	//error_log($bodyBackgroundCss);

	//Font Styles
	$templateFontSize = $templateStyles['font-styles']['template_font_size'] ?? '16px';
	$templateLineHeight = $templateStyles['font-styles']['template_line_height'] ?? '1.5';

	//Link Styles
	$linkColor = $templateStyles['link-styles']['template_link_style_color'] ?? '#1e73be';
	$linkHoverColor = $templateStyles['link-styles']['template_link_style_hover_color'] ?? $linkColor;

	$linkStyles = $templateStyles['link-styles'];
	$underlineLinks = json_decode( $linkStyles['underline'] ) ?? false;
	$boldLinks = json_decode( $linkStyles['bold'] ) ?? false;
	$italicLinks = json_decode( $linkStyles['italic'] ) ?? false;


	$linkStyles = '';
	if ( $linkColor ) {
		$linkStyles .= 'color: ' . $linkColor . ';';
	}
	if ( $underlineLinks ) {
		$linkStyles .= 'text-decoration: underline;';
	} else {
		$linkStyles .= 'text-decoration: none;';
	}
	if ( $boldLinks ) {
		$linkStyles .= 'font-weight: bold;';
	}
	if ( $italicLinks ) {
		$linkStyles .= 'font-style: italic;';
	}

	ob_start();
	?>
	<!DOCTYPE html>
	<html lang="en" xmlns="https://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office"
		title="iD Tech Camps">

	<head><!-- Yahoo App Android will strip this --></head>

	<head>
		<meta charset="utf-8" />
		<meta http-equiv="Content-Type" content="text/html charset=UTF-8" />
		<meta name="viewport" content="width=device-width,initial-scale=1" />
		<meta name="x-apple-disable-message-reformatting" />
		<?php
		$darkModeSupport = json_decode( $templateStyles['custom-styles']['dark-mode-support'], true ) ?? false;
		if ( $darkModeSupport === true ) { ?>
			<meta name="color-scheme" content="light dark">
			<meta name="supported-color-schemes" content="light dark">
		<?php } ?>
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<title>
			<?php echo $templateSettings['subject_line'] ?? ''; ?>
		</title>


		<!-- The first style block will be removed by Yahoo! on android, so nothing here for that platform-->

		<!--dedicated block for gmail-->
		<style type="text/css">
			u+#body a {
				color: <?php echo $linkColor; ?>;
				<?php if ( $underlineLinks ) { ?>
					text-decoration: underline;
				<?php } else { ?>
					text-decoration: none;
				<?php } ?>
				font-size: inherit;
				font-family: inherit;
				font-weight: inherit;
				line-height: inherit;
			}
		</style>

		<style type="text/css">
			@media only screen and (min-width: 768px) {
				u+.body .gmail-blend-screen.desktop {
					background: #000;
					mix-blend-mode: screen;
				}

				u+.body .gmail-blend-difference.desktop {
					background-color: #000;
					mix-blend-mode: difference;
				}
			}

			@media only screen and (max-width: 767px) {
				u+.body .gmail-blend-screen.mobile {
					background: #000;
					mix-blend-mode: screen;
				}

				u+.body .gmail-blend-difference.mobile {
					background-color: #000;
					mix-blend-mode: difference;
				}
			}
		</style>



		<style type="text/css">
			/*Prevent auto-blue links in Apple*/
			a[x-apple-data-detectors] {
				color: <?php echo $linkColor; ?> !important;
				<?php if ( $underlineLinks ) { ?>
					text-decoration: underline !important;
				<?php } else { ?>
					text-decoration: none !important;
				<?php } ?>
				font-size: inherit !important;
				font-family: inherit !important;
				font-weight: inherit !important;
				line-height: inherit !important;
			}

			/*Prevent blue links in Samsung*/
			#MessageViewBody a {
				color: <?php echo $linkColor; ?> !important;
				<?php if ( $underlineLinks ) { ?>
					text-decoration: underline !important;
				<?php } else { ?>
					text-decoration: none !important;
				<?php } ?>
				font-size: inherit !important;
				font-family: inherit !important;
				font-weight: inherit !important;
				line-height: inherit !important;
			}
		</style>


		<!-- Global styles for all clients that can read them-->
		<style type="text/css">
			html * {
				font-family: 'Poppins', Helvetica, Arial, sans-serif;
			}

			#outlook a {
				padding: 0;
				color: <?php echo $linkColor; ?>;
			}

			.ReadMsgBody {
				width: 100%;
			}

			.ExternalClass {
				width: 100%;
			}

			.ExternalClass,
			.ExternalClass p,
			.ExternalClass span,
			.ExternalClass font,
			.ExternalClass td,
			.ExternalClass div {
				line-height: 100%;
			}

			body {
				font-size:
					<?php echo $templateFontSize; ?>
				;
				line-height:
					<?php echo $templateLineHeight; ?>
				;
				font-family: 'Poppins', Helvetica, Arial, sans-serif;
				height: 100% !important;
				margin: 0 !important;
				padding: 0 !important;
				width: 100% !important;
			}

			body,
			table,
			td,
			a {
				-webkit-text-size-adjust: 100%;
				-ms-text-size-adjust: 100%;
				color: <?php echo $linkColor; ?>;
			}

			table,
			td {
				mso-table-lspace: 0pt;
				mso-table-rspace: 0pt;
			}

			img {
				-ms-interpolation-mode: bicubic;
				border: 0;
				height: auto;
				line-height: 100%;
				outline: none;
				text-decoration: none;
			}

			p {
				margin: 0;
				padding: 0 0 1em 0;
				color: inherit;
			}

			.noPpad p {
				padding: 0 !important;
			}


			/* Desktop Headers */
			h1 {
				margin: 0 !important;
				padding: 0 0 .67em 0;
				font-size: 2em;
				!important;
			}

			h2 {
				margin: 0 !important;
				padding: 0 0 .83em 0;
				font-size: 1.5em;
				!important;
			}

			h3 {
				margin: 0 !important;
				padding: 0 0 1em 0;
				font-size: 1.17em;
				!important;
			}

			h4 {
				margin: 0 !important;
				padding: 0 0 1.33em 0;
				font-size: 1em;
				!important;
			}

			h5 {
				margin: 0 !important;
				padding: 0 0 1.67em 0;
				font-size: .83em;
				!important;
			}

			h6 {
				margin: 0 !important;
				padding: 0 0 1.33em 0;
				font-size: .67em;
				!important;
			}

			.noPad h1, .noPad h2, .noPad h3, .noPad, h4, .noPad h5, .noPad h6 {
				padding: 0 !important;
			}

			a,
			a:visited {
				<?php echo $linkStyles; ?>
			}


			a:hover {
				color:
					<?php echo $linkHoverColor; ?>
				;
			}

			a.id-button {
				text-decoration: none !important;
				font-style: normal !important;
				font-weight: bold;
				color: inherit;
			}

			a.id-image-link {
				color: transparent;
				text-decoration: none !important;
				line-height: 0;
				font-size: 0;
			}

			ul {
				margin-top: 0;
				margin-block-start: 0;
			}

			ul>li {
				line-height: 1;
				padding-bottom: .7em;
			}

			table,
			td {
				margin: 0;
				padding: 0;
				font-size: inherit;
				line-height: inherit;
				font-family: 'Poppins', Helvetica, Arial, sans-serif;
			}

			@media screen and (max-width: 460px) {

				/* Mobile Headers */
				h1 {
					margin: 0 0 .83em 0;
					font-size: 1.5em;
					!important;
				}

				h2 {
					margin: 0 0 .9em 0;
					font-size: 1.3em;
					!important;
				}

				h3 {
					margin: 0 0 1em 0;
					font-size: 1.17em;
					!important;
				}

				h4 {
					margin: 0 0 1.33em 0;
					font-size: 1em;
					!important;
				}

				h5 {
					margin: 0 0 1.67em 0;
					font-size: .83em;
					!important;
				}

				h6 {
					margin: 0 0 1.33em 0;
					font-size: .67em;
					!important;
				}
			}
		</style>

		<!-- MSO only styles-->
		<!--[if mso]>
			<style type="text/css">
				table {
					border-collapse: collapse;
					border: 0;
					border-spacing: 0;
					margin: 0;
					mso-table-lspace: 0pt !important;
					mso-table-rspace: 0pt !important;
				}
				div,
				td {
					padding: 0;
				}
				div,p {
					margin: 0 !important;
				}
				
				.desktop-only {
					display: block; /* Show by default */
				}
				table.desktop-only {
					display: table;
				}

				.mobile-only {
					display: none!important; /* Hide by default */
				}

				.column {
					width: 100%!important;
					max-width: 100%!important;
					min-width: 100%!important;
				}
			</style>
			<noscript>
				<xml>
					<o:OfficeDocumentSettings>
						<o:PixelsPerInch>96</o:PixelsPerInch>
					</o:OfficeDocumentSettings>
				</xml>
			</noscript>
		<![endif]-->

		<!-- If this is a non-MSO client, we include styles for dynamic mobile/desktop visibility -->
		<!--[if !mso]><!-->

		<style type="text/css">
			.desktop-only {
				display: block;
				/* Show by default */
			}

			table.desktop-only {
				display: table;
			}

			.mobile-only {
				display: none;
				/* Hide by default */
			}

			@media screen and (min-width: 601px) {
				.three-col .column {
					max-width: 33.333% !important;
					min-width: 33.333% !important;
					display: inline-block;
					text-align: left;
				}

				.two-col .column {
					max-width: 50% !important;
					min-width: 50% !important;
					display: inline-block;
					text-align: left;
				}

				.sidebar-left:not([dir="rtl"]) .column:nth-child(1),
				.sidebar-right[dir="rtl"] .column:nth-child(1),
				.sidebar-right:not([dir="rtl"]) .column:nth-child(2),
				.sidebar-left[dir="rtl"] .column:nth-child(2) {
					max-width: 33.333% !important;
					min-width: 33.333% !important;
					display: inline-block;
					text-align: left;
				}

				.sidebar-left:not([dir="rtl"]) .column:nth-child(2),
				.sidebar-right[dir="rtl"] .column:nth-child(2),
				.sidebar-right:not([dir="rtl"]) .column:nth-child(1),
				.sidebar-left[dir="rtl"] .column:nth-child(1) {
					max-width: 66.667% !important;
					min-width: 66.667% !important;
					display: inline-block;
					text-align: left;
				}

				.desktop-only {
					display: block !important;
					/* Show on desktop */
				}

				table.desktop-only {
					display: table !important;
				}

				.mobile-only {
					display: none !important;
					/* Hide on desktop */
				}
			}

			@media screen and (max-width: 648px) {

				.three-col .column,
				.two-col .column,
				.sidebar-left .column,
				.sidebar-right .column {
					max-width: 100% !important;
					min-width: 100% !important;
					display: block !important;
				}

				.mobile-only {
					display: block !important;
					/* Show on mobile */
				}

				table.mobile-only {
					display: table !important;
				}

				.desktop-only {
					display: none !important;
					/* Hide on mobile */
				}
			}
		</style>
		<!--<![endif]-->


		<?php if ( isset( $templateStyles['custom-styles']['additional_template_css'] ) ) {
			echo $templateStyles['custom-styles']['additional_template_css'];
		} ?>

		<?php
		// Echo out any additional CSS block from snippets added to this template
		echo get_snippet_css( $rows );
		?>




	</head>

	<title>
		<?php echo $templateSettings['subject_line'] ?? ''; ?>
	</title>

	<?php
	$templateWidth = $templateStyles['body-and-background']['template_width'];
	$templateWidth = (int) $templateWidth > 0 ? (int) $templateWidth : 648;
	$bodyBackgroundCss = generate_background_css( $templateStyles['body-and-background']['body-background'], 'body_background_' );
	$pageBackgroundCss = generate_background_css( $templateStyles['body-and-background']['page-background'], 'page_background_' );
	?>

	<body class="body" id="body" style="margin: 0; padding: 0; word-spacing: normal;">
		<div style="display: none">
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847; &#8199;&#847;
			&shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy;
			&shy; &shy; &shy; &shy; &shy; &shy; &shy;
			&shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy;
			&shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy;
			&shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy;
			&shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy;
			&shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy;
			&shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy;
			&shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &shy; &nbsp;
		</div>
		<div role="article" aria-roledescription="email" lang="en"
			style="-webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; word-spacing:normal;text-align: center;">
			<table role="presentation"
				style="width: 100%; border: 0; border-spacing: 0;margin: 0 auto; <?php echo $bodyBackgroundCss; ?>"
				class="email-wrapper">
				<tr>
					<td align="center">
						<!--[if mso]> 
							<table role="presentation" align="center" style="width:<?php echo $templateWidth ?>px;"> 
							<tr> 
							<td style="padding:20px 0; <?php echo $pageBackgroundCss; ?>"> 
							<![endif]-->
						<div class="outer"
							style="width: 100%; max-width: <?php echo $templateWidth ?>px; margin: 20px auto; <?php echo $pageBackgroundCss; ?>">
							<?php
							return ob_get_clean();
}


function idwiz_get_email_bottom() {
	ob_start();
	?>
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
	</body>

	</html>
	<?php
	return ob_get_clean();
}






