<?php
function idwiz_get_button_chunk( $chunk ) {
	$ctaText = $chunk['cta_text'] ?? 'Click here';
	$ctaUrl = $chunk['cta_url'] ?? 'https://www.idtech.com';

	$chunkSettings = $chunk['chunk_settings'];
	$visibility = $chunkSettings['visibility'];
	$desktopVisibility = $visibility['desktop_visibility'] ?? 1;
	$mobileVisibility = $visibility['mobile_visibility'] ?? 1;

	$chunkBgColor = $chunkSettings['chunk_background_color'] ?? '#fff';
	$textAlign = $chunkSettings['align_button']['text_align'] ?? 'center';
	$btnBgColor = $chunkSettings['button_background_color'] ?? '#343434';
	$textColor = $chunkSettings['text_color'] ?? '#fff';
	$borderColor = $chunkSettings['border_color'] ?? '#343434';
	$borderSize = $chunkSettings['border_size'] ?? 0;
	$borderRadius = $chunkSettings['border_radius'] ?? '3px';
	$horzPadding = $chunkSettings['horizontal_padding'] ?? '20px';

	//print_r( $chunkSettings );
    ob_start();
	?>

	<!--[if mso]>
  <table role="presentation" width="100%">
  <tr>
  <td style="width:100px;padding:10px;text-align:center;" valign="middle">
  <![endif]-->
	<div class="button" style="background-color: <?php echo $chunkBgColor; ?>">
		<!--[if mso]>
	<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo $ctaUrl; ?>" style="height:40px;v-text-anchor:middle;width:200px;" arcsize="50%" strokecolor="#343434" fillcolor="#343434">
	<w:anchorlock/>
	<center style="color:#ffffff;font-family:sans-serif;font-size:18px;font-weight:bold;">
		<?php echo $ctaText; ?>
	</center>
	</v:roundrect>
	<![endif]-->
		<!--[if !mso]>-->
		<p style="margin:0;font-family:Poppins,sans-serif; text-align: <?php echo $textAlign; ?>">
			<a href="<?php echo $ctaUrl; ?>"
				style="background: <?php echo $btnBgColor; ?>; border-style: solid; border-width: <?php echo $borderSize; ?>; border-color: <?php echo $borderColor; ?>; text-decoration: none; padding: 15px <?php echo $horzPadding; ?>; color: <?php echo $textColor; ?>; border-radius: <?php echo $borderRadius; ?>; display:inline-block; mso-padding-alt:0;">
				<span style="font-weight:bold; font-size: 18px; line-height: 18px;">
					<?php echo $ctaText; ?>
				</span>
			</a>
		</p>
		<!--<![endif]>-->
	</div>


	<!--[if mso]>
  </td>
  </tr>
  </table>
  <![endif]-->

	<?php return ob_get_clean();
}
