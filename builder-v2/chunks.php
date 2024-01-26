<?php
function get_visibility_class_and_style( $chunk ) {
	// Default visibility settings
	$defaultVisibility = [ 'desktop_visibility' => 1, 'mobile_visibility' => 1 ];

	// Extract visibility settings from the chunk
	$visibility = $chunk['chunk_settings']['visibility'] ?? $defaultVisibility;
	$desktopVisibility = $visibility['desktop_visibility'] ?? 1;
	$mobileVisibility = $visibility['mobile_visibility'] ?? 1;

	// Initialize class and inline style
	$classes = [];
	$inlineStyle = '';

	// Determine classes and inline style based on visibility
	if ( $desktopVisibility && !$mobileVisibility ) {
		// Visible on desktop only
		$classes[] = 'desktop-only';
	} elseif ( !$desktopVisibility && $mobileVisibility ) {
		// Visible on mobile only
		$classes[] = 'mobile-only';
		$inlineStyle = 'display: none;'; // Hide by default, shown on mobile
	} elseif ( !$desktopVisibility && !$mobileVisibility ) {
		// Hidden on all devices
		$inlineStyle = 'display: none !important;';
	}

	// Join all classes into a single string
	$class = implode(' ', $classes);

	// Return the class and style as an associative array
	return [ 
		'class' => $class,
		'inlineStyle' => $inlineStyle
	];
}




function idwiz_get_spacer_chunk( $chunk ) {
	$spacerHeight = $chunk['spacer_height'] ?? '20px';

	$chunkSettings = $chunk['chunk_settings'];

	$backgroundColorCss = generate_background_css( $chunkSettings['background_settings'] );

	$visibility = get_visibility_class_and_style( $chunk );

	ob_start();
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}

	?>
	<!--[if mso]>
	<table class="spacer <?php echo $visibility['class']; ?>" role="presentation" width="100%" aria-hidden="true" style="table-layout:fixed; <?php echo $backgroundColorCss; ?> <?php echo $visibility['inlineStyle']; ?>">
	<tr>
	<td style="width:100%;text-align:center; <?php echo $backgroundColorCss; ?>" valign="middle">
	<![endif]-->
	<div class="spacer <?php echo $visibility['class']; ?>" style="<?php echo $visibility['inlineStyle']; ?>"
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


function idwiz_get_button_chunk( $chunk ) {
	//print_r($chunk);
	$ctaText = $chunk['cta_text'] ?? 'Click here';
	$ctaUrl = $chunk['cta_url'] ?? 'https://www.idtech.com';

	$chunkSettings = $chunk['chunk_settings'];


	$backgroundColorCss = generate_background_css( $chunkSettings['background_settings'] );

	$backgroundColor = $chunkSettings['background_settings']['background_color'];

	$textAlign = $chunkSettings['align_button']['text_align'] ?? 'center';
	$btnBgColor = $chunkSettings['button_background_color'] ?? '#343434';
	$textColor = $chunkSettings['text_color'] ?? '#fff';

	$btnBorderCss = '';
	$borderColor = $chunkSettings['border_color'];
	$borderSize = $chunkSettings['border_size'] ?? 0;
	if ( $borderColor ) {
		if ( $borderSize > 0 ) {
			$btnBorderCss = 'border: ' . $borderSize . 'px solid ' . $borderColor . ';';
		}
	}

	$borderRadius = $chunkSettings['border_radius'] ?? 3;
	$msoBorderPerc = 5;
	if ( $borderRadius >= 20 ) {
		$msoBorderPerc = 50;
	}
	$horzPadding = $chunkSettings['horizontal_padding'] ?? 60;

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

	$visibility = get_visibility_class_and_style( $chunk );

	//print_r( $chunkSettings );
	ob_start();
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}

	?>

	<table class="<?php echo $visibility['class']; ?>" role="presentation" table-layout="fixed"
		style="width: 100%; border: 0; border-spacing: 0; <?php echo $visibility['inlineStyle']; ?> <?php echo $backgroundColorCss; ?>">
		<tr>
			<td
				style="<?php echo $backgroundColorCss; ?> text-align: <?php echo $textAlign; ?>; font-family: Poppins, Arial, sans-serif;">
				<!--[if mso]>
					<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo $ctaUrl; ?>" style="height: 50px; v-text-anchor: middle; width: <?php echo $buttonWidth; ?>px;" arcsize="<?php echo $msoBorderPerc; ?>%" strokecolor="<?php echo $vmlBorderColor; ?>" strokeweight="<?php echo $vmlBorderWeight; ?>px" fillcolor="<?php echo $btnBgColor; ?>">
						<w:anchorlock/>
						<center class="id-button" style="mso-style-textfill-type:gradient; mso-style-textfill-fill-gradientfill-stoplist:"0 \<?php echo $textColor; ?> 0 100000\,100000 \<?php echo $textColor; ?> 0 100000";color: <?php echo $vmlBorderColor; ?> !important; font-family: Poppins, Arial, sans-serif; font-size: 22px!important; line-height: 20px;font-weight: bold;mso-text-raise: 10pt;"><?php echo $ctaText; ?></center>
					</v:roundrect>
				<![endif]-->

				<!--[if !mso]> <!-->
				<a href="<?php echo $ctaUrl; ?>" aria-label="<?php echo $ctaText; ?>" class="id-button"
					style="font-size: 20px; line-height: 20px;text-align: center; background: <?php echo $btnBgColor; ?>; <?php echo $btnBorderCss; ?> text-decoration: none; padding-top: 12px; padding-bottom: 12px; padding-left:<?php echo $horzPadding; ?>px; padding-right: <?php echo $horzPadding; ?>px;color: <?php echo $textColor; ?> !important; border-radius: <?php echo $borderRadius; ?>px; display: inline-block; mso-padding-alt: 0; text-underline-color: #ffffff">
					<?php echo $ctaText; ?>
				</a>
				<!-- <![endif]-->
			</td>
		</tr>
	</table>

	<?php
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--<![endif]-->';
	}
	return ob_get_clean();
}


function idwiz_get_html_chunk( $chunk ) {
	$chunkSettings = $chunk['chunk_settings'];

	$backgroundColorCss = generate_background_css( $chunkSettings['background_settings'] );

	$fontCss = 'font-size: 18px;';
	$brandedFont = $chunkSettings['branded_font'] ?? 0;
	if ( $brandedFont ) {
		$fontCss = "font-family: Poppins, Helvetica, Arial, sans-serif;";
	}

	$visibility = get_visibility_class_and_style( $chunk );
	$msoTableWrap = $chunkSettings['mso_table_wrap'] ?? 0;

	// Retrieve the post object from the 'snippet' ACF field
	$snippetPostId = $chunk['snippet'];

	$shortcode = '[wiz_snippet id="' . $snippetPostId . '"]';
	$snippetContent = do_shortcode( $shortcode );

	ob_start();
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}

	if ( $msoTableWrap ) {
		echo '<!--[if mso]>';
		echo '<table class="id-html ' . esc_attr( $visibility['class'] ) . '" role="presentation" width="100%" style="table-layout:fixed; ' . esc_attr( $fontCss . $backgroundColorCss . $visibility['inlineStyle'] ) . '">';
		echo '<tr><td style="width:100%;text-align:center; ' . esc_attr( $backgroundColorCss ) . '" valign="middle">';
		echo '<![endif]-->';
	}

	echo '<div class="id-html" style="' . esc_attr( $fontCss . $backgroundColorCss . $visibility['inlineStyle'] ) . '">';
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


function idwiz_get_two_column_chunk( $chunk, $forPreview = false ) {
	ob_start();
	//print_r( $chunk );
	// Check if both columns exist
	if ( ! isset( $chunk['column_1_content']['add_chunk'] ) || ! isset( $chunk['column_2_content']['add_chunk'] ) ) {
		ob_end_clean();
		return "Error: Both columns must be set.";
	}

	$chunkSettings = $chunk['chunk_settings'];
	$chunkVertAlign = $chunkSettings['vertical_align']['vertical_align'] ?? 'top';
	//print_r( $chunkVertAlign );
	$chunkBackgroundColorCss = generate_background_css( $chunkSettings['background_settings'] );

	$magicWrapCss = '';
	$magicWrapAttr = '';
	$magicWrap = $chunkSettings['magic_wrap'] ?? 0;
	if ( $magicWrap ) {
		$magicWrapCss = 'direction: rtl;';
		$magicWrapAttr = 'dir="rtl"';
	}

	$visibility = get_visibility_class_and_style( $chunk );

	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}

	?>
	<div class="two-col <?php echo $visibility['class']; ?>" <?php echo $magicWrapAttr; ?>
		style="<?php echo $magicWrapCss; ?> font-size: 0;text-align: center; <?php echo $chunkBackgroundColorCss; ?> <?php echo $visibility['inlineStyle']; ?>">
		<!--[if mso]> 
	<table role="presentation" style="white-space:nowrap;width: 100%; border: 0; border-spacing: 0; border-collapse: collapse; <?php echo $chunkBackgroundColorCss; ?> <?php echo $visibility['inlineStyle']; ?>">
	<tr> 
	<td style="width:50%;" valign="<?php echo $chunkVertAlign; ?>" dir="ltr">
	<![endif]-->

		<div class="column" dir="ltr"
			style="width: 100%; font-size: 1em; max-width: 400px; display: inline-block; direction:ltr; vertical-align: <?php echo $chunkVertAlign; ?>;">

			<?php
			if ( ! empty( $chunk['column_1_content']['add_chunk'] ) ) {
				foreach ( $chunk['column_1_content']['add_chunk'] as $rowId => $rowContent ) {
					echo idwiz_get_chunk_template( $rowContent, '2-col', 'column_1_content', $rowId, $forPreview );
					?>
					<?php
				}
			}
			?>

		</div>
		<!--[if mso]></td><td style="width:50%;  direction:ltr;" valign="<?php echo $chunkVertAlign; ?>" dir="ltr"><![endif]-->
		<div class="column" dir="ltr"
			style="width: 100%; font-size: 1em; direction:ltr; max-width: 400px; display: inline-block;  vertical-align: <?php echo $chunkVertAlign; ?>;">

			<?php
			if ( ! empty( $chunk['column_2_content']['add_chunk'] ) ) {
				foreach ( $chunk['column_2_content']['add_chunk'] as $rowId => $rowContent ) {
					echo idwiz_get_chunk_template( $rowContent, '2-col', 'column_2_content', $rowId, $forPreview ); ?>
					<?php
				}
			}
			?>


		</div>
		<!--[if mso]> 
	</td> 
	</tr> 
	</table> 
	<![endif]-->
	</div>
	<?php
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--<![endif]-->';
	}
	return ob_get_clean();
}



function idwiz_get_three_column_chunk( $chunk, $forPreview = false ) {
	//print_r( $chunk );


	$chunkSettings = $chunk['chunk_settings'];

	$chunkVertAlign = $chunkSettings['vertical_align']['vertical_align'] ?? 'top';

	$chunkBackgroundColorCss = generate_background_css( $chunkSettings['background_settings'] );

	$visibility = get_visibility_class_and_style( $chunk );

	ob_start();
	// Checking if all three columns exist
	if ( ! isset( $chunk['column_1_content']['add_chunk'] ) || ! isset( $chunk['column_2_content']['add_chunk'] ) || ! isset( $chunk['column_3_content']['add_chunk'] ) ) {
		ob_end_clean();
		return "Error: All three columns must be set.";
	}

	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--[if !mso]><!-->';
	}

	?>
	<div class="three-col <?php echo $visibility['class']; ?>?>"
		style="text-align: center; font-size: 0;<?php echo $chunkBackgroundColorCss; ?> <?php echo $visibility['inlineStyle']; ?>">
		<!--[if mso]> 
	<table role="presentation" width="100%" style="white-space:nowrap;text-align:center; <?php echo $chunkBackgroundColorCss; ?> <?php echo $visibility['inlineStyle']; ?>"> 
	<tr> 
	<td style="width:266px;" valign="<?php echo $chunkVertAlign ?? 'top'; ?>"> 
	<![endif]-->

		<div class="column"
			style="width: 100%; font-size: 1em; max-width: 266px; display: inline-block; vertical-align: <?php echo $chunkVertAlign ?? 'top'; ?>">
			<?php
			foreach ( $chunk['column_1_content']['add_chunk'] as $rowId => $rowContent ) {
				echo idwiz_get_chunk_template( $rowContent, '3-col', 'column_1_content', $rowId, $forPreview ); ?>
				<?php
			}
			?>
		</div>
		<!--[if mso]> 
	</td> 
	<td style="width:266px;" valign="<?php echo $chunkVertAlign ?? 'top'; ?>"> 
	<![endif]-->
		<div class="column"
			style="width: 100%; font-size: 1em; max-width: 266px; display: inline-block;  vertical-align: <?php echo $chunkVertAlign ?? 'top'; ?>">
			<?php
			foreach ( $chunk['column_2_content']['add_chunk'] as $rowId => $rowContent ) {
				echo idwiz_get_chunk_template( $rowContent, '3-col', 'column_2_content', $rowId, $forPreview ); ?>
				<?php
			}
			?>
		</div>
		<!--[if mso]> 
	</td> 
	<td style="width:266px;" valign="<?php echo $chunkVertAlign ?? 'top'; ?>"> 
	<![endif]-->
		<div class="column"
			style="width: 100%; font-size: 1em; max-width: 266px; display: inline-block;  vertical-align: <?php echo $chunkVertAlign ?? 'top'; ?>">
			<?php
			foreach ( $chunk['column_3_content']['add_chunk'] as $rowId => $rowContent ) {
				echo idwiz_get_chunk_template( $rowContent, '3-col', 'column_3_content', $rowId, $forPreview ); ?>
				<?php
			}
			?>
		</div>
		<!--[if mso]> 
	</td> 
	</tr> 
	</table> 
	<![endif]-->
	</div>
	<?php
	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--<![endif]-->';
	}
	return ob_get_clean();
}


function idwiz_get_plain_text_chunk( $chunk ) {
	//print_r($chunk);
	$chunkSettings = $chunk['chunk_settings'];

	$alignContent = $chunkSettings['align_content']['text_align'] ?? 'left';

	$chunkPadding = $chunkSettings['padding']['chunk_padding'] ?? '10px';

	$visibility = get_visibility_class_and_style( $chunk );

	$textContent = $chunk['plain_text_content'] ?? 'Your content goes here!';


	$backgroundColorCss = generate_background_css( $chunkSettings['background_settings'] );


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
			
			<td class="id-plain-text" style="<?php echo $backgroundColorCss; ?> font-family: Poppins, Arial, sans-serif!important; padding:<?php echo $chunkPadding; ?>;text-align:<?php echo $alignContent; ?>;">
			<![endif]-->
	<div class="id-plain-text wrapper" style="<?php echo $backgroundColorCss; ?>  padding:<?php echo $chunkPadding; ?>;">
		<div class="id-plain-text"
			style="background:transparent;margin: 0 auto; max-width: 600px;font-size: 18px;font-family: Poppins, Arial, sans-serif!important; text-align:<?php echo $alignContent; ?>;">
			<?php echo wpautop( $textContent ); ?>
		</div>
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



function idwiz_get_image_chunk( $chunk, $variant = false ) {
	$imageSrc = $chunk['image_src'] ?? '';
	$imageLink = $chunk['image_link'] ?? '';
	$imageAlt = $chunk['image_alt'] ?? '';

	$chunkSettings = $chunk['chunk_settings'];

	// Default width for non-MSO clients
	$defaultWidth = '100%';

	// Specific widths for MSO clients based on variant
	$msoWidth = '800'; // Default full width
	if ( $variant == '2-col' ) {
		$msoWidth = '400'; // Half width for 2 columns
	} elseif ( $variant == '3-col' ) {
		$msoWidth = '267'; // Third width for 3 columns
	}

	$backgroundColorCss = generate_background_css( $chunkSettings['background_settings'] );

	$visibility = get_visibility_class_and_style( $chunk );
	
	

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
	echo '<a href="' . $imageLink . '" ' . $ariaHidden . ' title="' . $imageAlt . '">';
	echo '<img class="id-image ' . $visibility['class'] . '" src="' . $imageSrc . '" width="' . $msoWidth . '" ' . $altAttribute . ' style="display: block; width:' . $msoWidth . 'px; height:auto;' . $visibility['inlineStyle'] . '" />';
	echo '</a>';
	echo '</td></tr></table>';
	echo '<![endif]-->';

	// Non-MSO markup for other clients
	echo '<!--[if !mso]> <!-->';
	echo '<div class="id-image '.$visibility['class'].'" style="' . $backgroundColorCss . ' '.$visibility['inlineStyle'].'">';
	echo '<a href="' . $imageLink . '" ' . $ariaHidden . ' title="' . $imageAlt . '">';
	echo '<img ' . $altAttribute . ' class="id-image ' . $visibility['class'] . '" src="' . $imageSrc . '" style="display: block; width:' . $defaultWidth . '; height:auto;' . $visibility['inlineStyle'] . '" />';
	echo '</a>';
	echo '</div>';
	echo '<!-- <![endif]-->';

	if ( $visibility['class'] == 'mobile-only' ) {
		echo '<!--<![endif]-->';
	}
	return ob_get_clean();
}



function idwiz_get_standard_header() {
	ob_start();
	?>

	<table role="presentation" style="width:100%;border:0;border-spacing:0;table-layout:fixed;font-size:0;">
		<tr>
			<td style="font-size: 0;line-height:0;margin:0;">
				<a href="https://www.idtech.com" style="margin:0; padding: 0;" aria-label="iD Tech Camps"
					title="iD Tech Camps"><img
						src="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/id-grey-header-white-bg_1.jpg"
						width="800" alt=""
						style="min-width: 100%; width:800px; max-width:100%;height:auto;display: block; margin: 0!important;" /></a>
			</td>
		</tr>
	</table>


	<?php
	return ob_get_clean();
}
function idwiz_get_standard_footer( $showUnsub = true ) {
	ob_start();
	?>
	<!--[if mso]> 
	<table role="presentation" align="center" style="width:800px;table-layout:fixed;font-family:Poppins, Arial, sans-serif;"> 
	<tr> 
	<td style="background-color: #f4f4f4; padding: 20px 0 10px 0; font-size: 12px;font-family:Poppins, Arial, sans-serif; text-align: center;"> 
	<![endif]-->
	<div
		style="background-color: #f4f4f4; max-width: 800px; padding: 20px 0 10px 0; font-size: 12px;font-family:Poppins, Arial, sans-serif; text-align: center;">

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
		<br /><br />
		<strong>Contact Us:</strong><br />
		1-888-709-8324<br />
		+1-408-871-3700 (international)<br /><br />

		<strong>Address:</strong> P.O. Box 111720, Campbell, CA 95011<br /><br />

		Copyright © {{now format='yyyy'}} All rights reserved.
		<br /><br />
		<?php if ( $showUnsub ) { ?>

			<a href="{{hostedUnsubscribeUrl}}" aria-label="Manage Subscription Preferences"
				title="Manage Subscription Preferences" style="color: #343434;">Manage
				preferences</a>
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

function idwiz_get_fine_print_disclaimer( $finePrintDisclaimer ) {
	ob_start();
	?>
	<!--[if mso]> 
	<table role="presentation" align="center" style="width:800px;table-layout:fixed;"> 
	<tr> 
	<td style="background-color: #f4f4f4; padding-bottom: 20px; font-size: 12px;">
	<![endif]-->
	<div style="background-color: #f4f4f4; width: 100%; max-width: 800px; padding-bottom: 20px; font-size: 12px;">
		<?php
		if ( $finePrintDisclaimer ) {
			echo '<center style="font-size:12px !important;color:#444444;line-height:16px;">' . $finePrintDisclaimer . '</center>';
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

function idwiz_get_email_top( $chunks, $templateSettings, $templateStyles, $emailSettings ) {
	$bodyBackgroundSettings = $templateStyles['body_background'];
	//print_r($chunks);
	$bodyBackgroundCss = generate_background_css( $bodyBackgroundSettings );

	$linkColor = $templateStyles['link_color'] ?? '#1e73be';
	$visitedColor = $templateStyles['visited_color'] ?? '#0066bf';
	$underlineLinks = $templateStyles['underline_links'] ?? true;
	if ( $underlineLinks ) {
		$linkStyle = 'text-decoration: underline;';
	} else {
		$linkStyle = 'text-decoration: none;';
	}

	$dtFontSize = $templateStyles['desktop_font_size'] ?? '1em';
	$dtLineHeight = $templateStyles['desktop_line_height'] ?? '1.5em';
	$mobFontSize = $templateStyles['mobile_font_size'] ?? '1.1em';
	$mobLineHeight = $templateStyles['mobile_line_height'] ?? '1.6em';

	ob_start();
	?>
	<!DOCTYPE html>
	<html lang="en" xmlns="https://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office"
		title="iD Tech Camps">

	<head><!-- Yahoo App Android will strip this --></head>

	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width,initial-scale=1" />
		<meta name="x-apple-disable-message-reformatting" />
		
		<!--[if !mso]><!-->
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<!--<![endif]-->
		<title>
			<?php echo $emailSettings['subject_line']; ?>
		</title>

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

			/*Fix rendering issues with <p> elements*/
			.ExternalClass,
			.ExternalClass p,
			.ExternalClass span,
			.ExternalClass font,
			.ExternalClass td,
			.ExternalClass div {
				line-height: 100%;
			}

			p {
				margin: 0;
				padding: 0;
				margin: 1em 0;
				font-size:
					<?php echo $dtFontSize; ?>
					!important;
				line-height:
					<?php echo $dtLineHeight; ?>
					!important;
			}

			@media screen and (max-width: 460px) {
				p {
					font-size:
						<?php echo $mobFontSize; ?>
						!important;
					line-height:
						<?php echo $mobLineHeight; ?>
						!important;
				}
			}

			h1,
			h2,
			h3,
			h4,
			h5,
			h6 {
				line-height: 1.3;
				font-weight: bold;
			}

			h1 {
				margin: .67em 0;
				font-size: 2em;
				!important;
			}

			h2 {
				margin: .83em 0;
				font-size: 1.5em;
				!important;
			}

			h3 {
				margin: 1em 0;
				font-size: 1.17em;
				!important;
			}

			h4 {
				margin: 1.33em 0;
				font-size: 1em;
				!important;
			}

			h5 {
				margin: 1.67em 0;
				font-size: .83em;
				!important;
			}

			h6 {
				margin: 1.33em 0;
				font-size: .67em;
				!important;
			}

			a {
				color:
					<?php echo $linkColor; ?>
				;
				<?php echo $linkStyle; ?>
			}

			a:hover {
				color:
					<?php echo $visitedColor; ?>
				;
			}

			ul>li {
				line-height: 1.5;
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
				}

				.two-col .column {
					max-width: 50% !important;
					min-width: 50% !important;
					display: inline-block;
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

			@media screen and (max-width: 600px) {
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


		<?php if ( $templateSettings['include_interactive_css'] == 1 ) { ?>
			<!-- For  Gmail, we dedicate a style block to hide interactive elements in case it removes <style> blocks it doesn't like-->
			<style type="text/css">
				u+.bodyClass .interactiveClick {
					display: none !important
				}

				u+.bodyClass .interactiveReveal {
					display: block;
				}
			</style>

			<!-- For accessibility, we hide the input elements without the use of display: none to enable keyboard navigation-->
			<style type="text/css">
				.checkbox,
				.radio {
					Display: inline-block !important;
					Opacity: 0;
					Width: 0;
					Height: 0;
					Margin: 0 0 0 -9999px;
					Float: left;
					Position: absolute;
					-webkit-appearance: none;
				}

				input:focus~.interactiveClick,
				input:focus~.interactiveReveal {
					outline: highlight auto 2px;
					outline: -webkit-focus-ring-color: auto 5px;
				}
			</style>
		<?php } ?>
		<style type="text/css">
			<?php
			// Echo out any additional CSS block from snippets added to this template
			echo get_snippet_css( $chunks );
			?>
		</style>




	</head>

	<title>
		<?php echo $templateSettings['subject_link'] ?? ''; ?>
	</title>

	<body class="body" id="body"
		style="margin: 0; padding: 0; word-spacing: normal; line-height: 1.5;<?php echo $bodyBackgroundCss; ?>;">
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
			style="-webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; word-spacing:normal;">
			<table role="presentation" style="width: 100%; border: 0; border-spacing: 0">
				<tr>
					<td align="center">
						<!--[if mso]> 
												<table role="presentation" align="center" style="width:800px;"> 
												<tr> 
												<td style="padding:20px 0;"> 
												<![endif]-->
						<div class="outer" style="width: 96%; max-width: 800px; margin: 20px auto">
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

function generate_background_css( $backgroundSettings ) {
	$bg_type = $backgroundSettings['background_type'];
	$css = [];

	switch ( $bg_type ) {
		case 'gradient':
			$gradient_type = $backgroundSettings['gradient_type'];

			// Constructing the gradient colors string with positions
			$start_color = $backgroundSettings['start_color']['gradient_start'];
			$start_color_position = $backgroundSettings['start_color']['position'] . '%';
			$middle_color = $backgroundSettings['middle_color']['gradient_middle'];
			$middle_color_position = $backgroundSettings['middle_color']['position'] . '%';
			$end_color = $backgroundSettings['end_color']['gradient_end'];
			$end_color_position = $backgroundSettings['end_color']['position'] . '%';

			$gradient_colors = $start_color . ' ' . $start_color_position;
			if ( ! empty( $middle_color ) ) {
				$gradient_colors .= ', ' . $middle_color . ' ' . $middle_color_position;
			}
			$gradient_colors .= ', ' . $end_color . ' ' . $end_color_position;

			// Determine gradient direction or shape and complete gradient definition
			if ( $gradient_type === 'linear' ) {
				$angle = ! empty( $backgroundSettings['gradient_angle'] ) ? $backgroundSettings['gradient_angle'] . 'deg' : null;
				$direction_map = [ 'to-bottom' => 'to bottom', 'to-right' => 'to right', 'to-top-right' => 'to top right', 'to-bottom-right' => 'to bottom right' ];
				$direction = $angle ? $angle : ( isset( $direction_map[ $backgroundSettings['gradient_direction'] ] ) ? $direction_map[ $backgroundSettings['gradient_direction'] ] : 'to top' );
				$gradient_css = "linear-gradient($direction, $gradient_colors)";
			} else { // radial gradient
				$gradient_css = "radial-gradient(circle, $gradient_colors)";
			}

			// Fallback color logic
			$fallback_color = $backgroundSettings['background_color'] === 'transparent' ? ( $start_color ?: '#fff' ) : $backgroundSettings['background_color'];

			// Fallback gradient image and additional properties
			$fallback_image = $backgroundSettings['gradient_image_fallback'];
			$bg_size = $backgroundSettings['background_size'] ? $backgroundSettings['background_size'] : 'auto';
			$bg_repeat = $backgroundSettings['repeat_background'] ? 'repeat' : 'no-repeat';
			$limit_repeat = $backgroundSettings['limit_repeat'] ?? false;
			$bg_repeat = $limit_repeat ? $limit_repeat : $bg_repeat;

			// Add multiple background properties
			$css[] = "background-color: $fallback_color;";
			if ( $fallback_image ) {
				$css[] = "background: url('$fallback_image') right center / $bg_size $bg_repeat;";
			}
			$css[] = "background: $gradient_css;"; // This remains as is

			break;

		case 'image':
			// Image properties
			$image_url = $backgroundSettings['background_image'];
			$position = $backgroundSettings['background_image_position'];
			$bg_size = $backgroundSettings['background_size'] ? $backgroundSettings['background_size'] : 'auto';
			$bg_repeat = $backgroundSettings['repeat_background'] ? 'repeat' : 'no-repeat';
			$limit_repeat = $backgroundSettings['limit_repeat'] ?? false;
			$bg_repeat = $limit_repeat ? $limit_repeat : $bg_repeat;

			// Fallback color and additional properties
			$fallback_color = $backgroundSettings['background_color'] ?: '#fff';


			// Add multiple background attributes
			$css[] = "background-color: $fallback_color;";

			$css[] = "background: url('$image_url') $position / $bg_size $bg_repeat;";

			break;


		case 'color':
			// Solid color background
			$css[] = "background-color: " . $backgroundSettings['background_color'] . "; background-image: linear-gradient(" . $backgroundSettings['background_color'] . "," . $backgroundSettings['background_color'] . ");";
			break;

		case 'transparent':
			// Transparent background
			$css[] = "background: transparent;";
			break;
	}

	return implode( " ", $css );
}




