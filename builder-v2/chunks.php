<?php





function idwiz_get_spacer_chunk( $chunk, $templateOptions ) {
	//print_r($chunk);

	$chunkSettings = $chunk['settings'];
	$chunkFields = $chunk['fields'];

	$spacerHeight = $chunkFields['spacer_height'] ?? '60px';

	$backgroundColorCss = generate_background_css( $chunkSettings );

	$visibility = get_visibility_class_and_style( $chunkSettings );

	ob_start();
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}

	?>
	<!--[if mso]>
	<table class="spacer <?php echo $visibility['class']; ?>" role="presentation" width="100%" aria-hidden="true" style="table-layout:fixed; <?php echo $visibility['inlineStyle']; ?>">
	<tr>
	<td style="width:100%;text-align:center; <?php echo $backgroundColorCss; ?>" valign="middle">
	<![endif]-->
	<div class="id-chunk id-spacer <?php echo $visibility['class']; ?>" style="<?php echo $visibility['inlineStyle']; ?>"
		aria-hidden="true">
		<div
			style="line-height:<?php echo $spacerHeight; ?>;height:<?php echo $spacerHeight; ?>;mso-line-height-rule:exactly;<?php echo $backgroundColorCss; ?>">
			&nbsp;</div>
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


function idwiz_get_button_chunk( $chunk, $templateOptions ) {
	$chunkSettings = $chunk['settings'];
	$chunkFields = $chunk['fields'];

	$chunkPadding = $chunkSettings['chunk_padding'] ?? '0px';

	$ctaText = $chunkFields['button_text'] ?? 'Click here';
	$ctaUrl = $chunkFields['button_link'] ?? 'https://www.idtech.com';


	$backgroundColorCss = generate_background_css( $chunkSettings );

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

	//print_r( $chunkSettings );
	ob_start();
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}

	?>

	<div class="id-chunk id-button <?php echo $visibility['class']; ?>"
		style="width: 100%; border: 0; border-spacing: 0; <?php echo $visibility['inlineStyle']; ?> <?php echo $backgroundColorCss; ?>">
		<table role="presentation" table-layout="fixed" style="width: 100%; <?php echo $backgroundColorCss; ?>">
			<tr>
				<td
					style="<?php echo $backgroundColorCss; ?> padding: <?php echo $chunkPadding; ?>; border: 0!important; text-align: <?php echo $textAlign; ?>; font-family: Poppins, Arial, sans-serif; font-size: <?php echo $templateOptions['templateStyles']['template_font_size'] ?? '16px'; ?>">
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
	return ob_get_clean();
}


function idwiz_get_snippet_chunk( $chunk, $templateOptions ) {
	$chunkSettings = $chunk['settings'];
	$chunkFields = $chunk['fields'];

	$backgroundColorCss = generate_background_css( $chunkSettings );

	$fontCss = 'font-size: 1em; line-height: 1.5;';
	$brandedFont = $chunkSettings['branded_font'] ?? 0;
	if ( $brandedFont ) {
		$fontCss = "font-family: Poppins, Helvetica, Arial, sans-serif;";
	}

	$visibility = get_visibility_class_and_style( $chunkSettings );
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
		echo '<table class="' . esc_attr( $visibility['class'] ) . '" role="presentation" width="100%" style="table-layout:fixed; ' . esc_attr( $fontCss . $backgroundColorCss . $visibility['inlineStyle'] ) . '">';
		echo '<tr><td style="width:100%;text-align:center; ' . esc_attr( $backgroundColorCss ) . '" valign="middle">';
		echo '<![endif]-->';
	}

	echo '<div class="id-chunk id-snippet" style="' . esc_attr( $fontCss . $backgroundColorCss . $visibility['inlineStyle'] ) . '">';
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



function idwiz_get_plain_text_chunk( $chunk, $templateOptions ) {
	$templateStyles = $templateOptions['templateStyles'] ?? [];

	$chunkFields = $chunk['fields'];
	$chunkSettings = $chunk['settings'];

	$visibility = get_visibility_class_and_style( $chunkSettings );

	$backgroundColorCss = generate_background_css( $chunkSettings );

	$chunkPadding = $chunkSettings['chunk_padding'] ?? '0px';

	$textContent = $chunkFields['plain_text_content'] ?? 'Your content goes here!';

	$textContent = add_aria_label_to_links( $textContent );


	ob_start();
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}


	?>
	<!--[if mso]>
	<table class="<?php echo $visibility['class']; ?>" role="presentation"
		style="width:100%;border:0;border-spacing:0; <?php echo $visibility['inlineStyle']; ?> <?php echo $backgroundColorCss; ?>">
		<tr>
			
			<td class="id-plain-text" style="<?php echo $backgroundColorCss; ?> padding: <?php echo $chunkPadding; ?>; font-family: Poppins, Arial, sans-serif!important; font-size: <?php echo $templateStyles['font-styles']['template_font_size'] ?? '16px'; ?>;">
			<![endif]-->

	<div class="id-chunk id-plain-text <?php echo $visibility['class']; ?>"
		style="<?php echo $visibility['inlineStyle']; ?>; <?php echo $backgroundColorCss; ?> padding: <?php echo $chunkPadding; ?>; font-size: <?php echo $templateStyles['font-styles']['template_font_size'] ?? '16px'; ?>; border-top:1px solid transparent;">
		<?php echo wpautop( stripslashes($textContent) ); ?>
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



function idwiz_get_image_chunk( $chunk, $templateOptions ) {
	$chunkSettings = $chunk['settings'];

	$visibility = get_visibility_class_and_style( $chunkSettings );

	$chunkFields = $chunk['fields'];

	$variant = $chunkSettings['image_context'] ?? '';

	$templateWidth = $templateOptions['templateStyles']['body-and-background']['template_width'] ?? '648';

	$imageSrc = $chunkFields['image_url'] ?? '';
	$imageLink = $chunkFields['image_link'] ?? '';
	$imageAlt = $chunkFields['image_alt'] ?? '';

	// Default width for non-MSO clients
	$defaultWidth = '100%';

	// Specific widths for MSO clients based on variant
	$msoWidth = $templateWidth; // Default full width
	if ( $variant == 'two-col' ) {
		$msoWidth = $templateWidth > 0 ? round( $templateWidth / 2, 0 ) : $templateWidth;
	} elseif ( $variant == 'three-col' ) {
		$msoWidth = $templateWidth > 0 ? round( $templateWidth / 3, 0 ) : $templateWidth;
	}

	$backgroundColorCss = generate_background_css( $chunkSettings );



	ob_start();
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}

	// Check if alt text is empty and set aria-hidden accordingly
	$ariaHidden = empty( $imageAlt ) ? 'aria-hidden="true"' : '';
	$altAttribute = empty( $imageAlt ) ? '' : 'alt="' . $imageAlt . '"';

	// MSO conditional for Outlook
	echo '<!--[if mso]>';
	echo '<table role="presentation" style="width:100%;border:0;border-spacing:0;margin: 0;' . $visibility['inlineStyle'] . '"><tr><td style="font-size: 0; line-height: 0; ' . $backgroundColorCss . '">';
	if ( $imageLink ) {
		echo '<a href="' . $imageLink . '" ' . $ariaHidden . ' title="' . $imageAlt . '" style="display: block;margin: 0; padding: 0; line-height: 0;font-size:0;text-decoration:none;">';
	}
	echo '<img class="id-image ' . $visibility['class'] . '" src="' . $imageSrc . '" width="' . $msoWidth . '" ' . $altAttribute . ' style="width:' . $msoWidth . 'px; height:auto;' . $visibility['inlineStyle'] . '" />';
	if ( $imageLink ) {
		echo '</a>';
	}
	echo '</td></tr></table>';
	echo '<![endif]-->';

	// Non-MSO markup for other clients
	echo '<!--[if !mso]> <!-->';
	echo '<div class="id-chunk id-image ' . $visibility['class'] . '" style="' . $backgroundColorCss . ' ' . $visibility['inlineStyle'] . '">';
	if ( $imageLink ) {
		echo '<a href="' . $imageLink . '" ' . $ariaHidden . ' title="' . $imageAlt . '">';
	}
	echo '<img ' . $altAttribute . ' class="id-image ' . $visibility['class'] . '" src="' . $imageSrc . '" style="width:' . $defaultWidth . '; height:auto;' . $visibility['inlineStyle'] . '" />';
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
	$templateSettings = $templateOptions['templateSettings'];
	$templateStyles = $templateOptions['templateStyles'];
	$headerFooterSettings = $templateStyles['header-and-footer'];
	$headerLogo = $headerFooterSettings['template_header_logo'] ?? '';
	if ($headerLogo == 'manual') {
		$headerLogo = $headerFooterSettings['template_header_logo_manual'] ?? '';
	}
	ob_start();
	?>

	<table role="presentation" style="width:100%;border:0;border-spacing:0;table-layout:fixed;font-size: 0;">
		<tr>
			<td style="font-size: 0;line-height:0;margin:0;">
				<a href="https://www.idtech.com" style="margin:0; padding: 0;" aria-label="iD Tech Camps"
					title="iD Tech Camps">
				<img
					src="<?php echo $headerLogo; ?>"
					width="<?php echo $templateStyles['body-and-background']['template_width']; ?>" 
					alt=""
					style="width:<?php echo $templateStyles['body-and-background']['template_width']; ?>; max-width:100%;height:auto;display: block;" />
				</a>
			</td>
		</tr>
	</table>


	<?php
	return ob_get_clean();
}
function idwiz_get_standard_footer( $templateoptions, $showUnsub = true ) {
	$templateSettings = $templateoptions['templateSettings'] ?? [];
	$templateStyles = $templateoptions['templateStyles'] ?? [];
	$footerBackground = $templateStyles['header-and-footer']['template_footer_color'] != 'rgba(0, 0, 0, 0)' ? $templateStyles['header-and-footer']['template_footer_color'] : 'transparent';
	$footerTextColor = $templateStyles['header-and-footer']['template_footer_text_color'] ?? '#343434';
	$templateLinkColor = $templateStyles['link-styles']['template_link_style_color'] ?? '#000000';
	$footerLinkColor = $templateStyles['header-and-footer']['template_footer_link_color'] ?? $templateLinkColor ?? '#000000';

	ob_start();
	?>
	<!--[if mso]> 
	<table role="presentation" align="center" style="width:<?php echo $templateStyles['body-and-background']['template_width']; ?>;table-layout:fixed;font-family:Poppins, Arial, sans-serif;"> 
	<tr> 
	<td style="background-color: <?php echo $footerBackground; ?>; padding: 20px 0 10px 0; font-size: 12px;font-family:Poppins, Arial, sans-serif; text-align: center;"> 
	<![endif]-->
	<div
		style="background-color: <?php echo $footerBackground; ?>; max-width: <?php echo $templateStyles['body-and-background']['template_width']; ?>; padding: 20px 0 10px 0; font-size: 12px;font-family:Poppins, Arial, sans-serif; text-align: center;">

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
	$finePrintDisclaimer = $templateOptions['templateSettings']['message-settings']['fine_print_disclaimer'];
	$templateStyles = $templateOptions['templateStyles'];
	$footerBackground = $templateStyles['header-and-footer']['template_footer_color'] != 'rgba(0, 0, 0, 0)' ? $templateStyles['header-and-footer']['template_footer_color'] : 'transparent';
	$footerTextColor = $templateStyles['header-and-footer']['template_footer_text_color'] ?? '#343434';
	ob_start();
	?>
	<!--[if mso]> 
	<table role="presentation" align="center" style="width:<?php echo $templateStyles['body-and-background']['template_width']; ?>;table-layout:fixed;"> 
	<tr> 
	<td style="background-color: <?php echo $footerBackground; ?>; padding-bottom: 20px; font-size: 12px;">
	<![endif]-->
	<div
		style="background-color: <?php echo $footerBackground; ?>; width: 100%; max-width: <?php echo $templateStyles['body-and-background']['template_width']; ?>; padding-bottom: 20px; font-size: 12px;">
		<?php
		if ( $finePrintDisclaimer ) {
			echo '<center style="font-size:12px !important;color:' . $footerTextColor . ';line-height:16px;padding-left: 20px; padding-right: 20px;">' . $finePrintDisclaimer . '</center>';
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
	$visitedColor = $templateStyles['link-styles']['template_link_style_visited_color'] ?? $linkColor;

	$underlinkLinks = $templateStyles['link-styles']['template_link_style_underline'] ?? false;
	$boldLinks = $templateStyles['link-styles']['template_link_style_bold'] ?? false;
	$italicLinks = $templateStyles['link-styles']['template_link_style_italic'] ?? false;

	$linkStyles = '';
	if ( $linkColor ) {
		$linkStyles .= 'color: ' . $linkColor . ';';
	}
	if ( $underlinkLinks ) {
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
		<meta name="color-scheme" content="light dark"> <meta name="supported-color-schemes" content="light dark">

		<!--[if !mso]><!-->
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<!--<![endif]-->
		<title>
			<?php echo $templateSettings['subject_line'] ?? ''; ?>
		</title>


		<!-- The first style block will be removed by Yahoo! on android, so nothing here for that platform-->

		<!--dedicated block for gmail (for non-interactive stuff)-->
		<style type="text/css">
			u+#body a {
				color: inherit;
				text-decoration: none;
				font-size: inherit;
				font-family: inherit;
				font-weight: inherit;
				line-height: inherit;
			}
		</style>


		<!-- Global styles for all clients that can read them-->
		<style type="text/css">
			/*Fix rendering issues with <p> and other elements*/
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
				font-family: Poppins, Helvetica, Arial, sans-serif;
			}

			p {
				margin: 0;
				padding: 0 0 1em 0;
			}



			/*Prevent auto-blue links in Apple*/
			a[x-apple-data-detectors] {
				color: inherit !important;
				text-decoration: none !important;
				font-size: inherit !important;
				font-family: inherit !important;
				font-weight: inherit !important;
				line-height: inherit !important;
			}

			/*Prevent blue links in Samsung*/
			#MessageViewBody a {
				color: inherit !important;
				text-decoration: none !important;
				font-size: inherit !important;
				font-family: inherit !important;
				font-weight: inherit !important;
				line-height: inherit !important;
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

			a {

				<?php echo $linkStyles; ?>
			}

			a:visited {

				<?php echo $visitedColor ?>
			}

			a:hover {
				color:
					<?php echo $visitedColor; ?>
				;
			}

			ul>li {
				line-height: 1.5;
			}

			table,
			td {
				margin: 0;
				padding: 0;
				font-size: inherit;
				line-height: inherit;
				font-family: Poppins, Helvetica, Arial, sans-serif;
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
				.three-col .column {
					max-width: 100% !important;
					min-width: 100% !important;
					display: block !important;
				}

				.two-col .column {
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
	$templateWidth = $templateSettings['body-and-background']['template_width'] ?? 648;
	$bodyBackgroundCss = generate_background_css( $templateStyles['body-and-background']['body-background'], 'body_background_' );
	$pageBackgroundCss = generate_background_css( $templateStyles['body-and-background']['page-background'], 'page_background_' );
	?>

	<body class="body" id="body" style="margin: 0; padding: 0; word-spacing: normal;<?php echo $bodyBackgroundCss; ?>">
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
			<table role="presentation" style="width: 100%; border: 0; border-spacing: 0;margin: 0 auto;"
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

function generate_background_css( $backgroundSettings, $prefix = '' ) {
	$bg_type = $backgroundSettings[ $prefix . 'background-type' ] ?? 'none';
	$css = [];

	switch ( $bg_type ) {
		case 'gradient':
			$gradientStyles = json_decode( $backgroundSettings[ $prefix . 'gradient-styles' ], true );

			// Fallback color logic
			$fallback_color = $backgroundSettings[ $prefix . 'background-color' ] ?? 'transparent';
			$css[] = "background-color: $fallback_color;";

			// Use the gradient style directly if it's provided in the correct format
			if ( ! empty( $gradientStyles['style'] ) ) {
				$gradient_css = $gradientStyles['style'];
				$css[] = "background-image: $gradient_css;";
			}

			// Image fallback
			if ( ! empty( $backgroundSettings[ $prefix . 'background-image-url' ] ) ) {
				$image_url = $backgroundSettings[ $prefix . 'background-image-url' ];
				$position = $backgroundSettings[ $prefix . 'background-image-position' ] ?? 'center';
				$size = $backgroundSettings[ $prefix . 'background-image-size' ] ?? 'cover';

				$css[] = "background-image: url('$image_url'), $gradient_css;";
				$css[] = "background-position: $position;";
				$css[] = "background-size: $size;";
			}

			break;

		case 'image':
			// Image properties
			$image_url = $backgroundSettings[ $prefix . 'background-image-url' ];
			$position = $backgroundSettings[ $prefix . 'background-image-position' ] ?? 'center';
			$size = $backgroundSettings[ $prefix . 'background-image-size' ] ?? 'cover';

			// Fallback color and additional properties
			$fallback_color = $backgroundSettings['background-color'] ?? '#ffffff';

			$css[] = "background-color: $fallback_color;";
			if ( $image_url ) {
				$css[] = "background-image: url('$image_url');";
				$css[] = "background-position: $position;";
				$css[] = "background-size: $size;";
			}

			// Background repeat
			$bgRepeatY = $backgroundSettings[ $prefix . 'background-repeat-vertical' ] ?? false;
			$bgRepeatX = $backgroundSettings[ $prefix . 'background-repeat-horizontal' ] ?? false;
			if ( $bgRepeatY === true && $bgRepeatX === true ) {
				$css[] = "background-repeat: repeat;";
			} else if ( $bgRepeatY === true ) {
				$css[] = "background-repeat: repeat-y;";
			} else if ( $bgRepeatX === true ) {
				$css[] = "background-repeat: repeat-x;";
			} else {
				$css[] = "background-repeat: no-repeat;";
			}

			break;

		case 'solid':
			// Solid color background
			$color = $backgroundSettings[ $prefix . 'background-color' ] ?? '#ffffff';
			$css[] = "background-color: $color;";

			break;

		case 'none':
			// Transparent background
			$css[] = "background-color: transparent;";
			break;
	}

	// Check for forced background color
	$forceBackground = $backgroundSettings[ $prefix . 'force-background'] ?? false;

	// If a background color is set and not transparent, force it using linear gradient
	if ( $forceBackground == 'true'
		&& $bg_type != 'none'
		&& isset( $backgroundSettings[ $prefix . 'background-color' ] )
		&& $backgroundSettings[ $prefix . 'background-color' ] != 'transparent' ) {
		$css[] = "background-image: linear-gradient({$backgroundSettings[ $prefix . 'background-color' ]}, {$backgroundSettings[ $prefix . 'background-color' ]});";
	}



	return implode( " ", $css );
}





