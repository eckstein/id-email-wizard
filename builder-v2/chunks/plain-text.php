<?php
function idwiz_get_plain_text_chunk( $chunk ) {

	$chunkSettings = $chunk['chunk_settings'];

	$visibility = $chunkSettings['desktop_mobile_visibility'];
	$desktopVisibility = $visibility['desktop_visibility'] ?? 1;
	$mobileVisibility = $visibility['mobile_visibility'] ?? 1;

	$alignContent = $chunkSettings['align_content']['text_align'] ?? 'left';

	$textColor = $chunkSettings['color_settings']['text_color'] ?? '#000000';
	$bgColor = $chunkSettings['color_settings']['background_color'] ?? '#FFFFFF';

	$textContent = $chunk['plain_text_content'] ?? 'Your content goes here!';
	ob_start();
	?>
	<table role="presentation" style="width:100%;border:0;border-spacing:0;">
		<tr>
			<td
				style="padding:10px;text-align:<?php echo $alignContent; ?>; background-color:<?php echo $bgColor; ?>; color:<?php echo $textColor; ?>!important;">
				<?php echo wpautop( $textContent ); ?>
			</td>
		</tr>
	</table>

	<?php
	return ob_get_clean();
} ?>