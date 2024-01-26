<?php
function idwiz_get_image_chunk( $chunk ) {
	$imageSrc = $chunk['image_src'] ?? '';
	$imageLink = $chunk['image_link'] ?? '';

	ob_start();
	?>
	<table role="presentation" style="width:100%; max-width: 780px; border:0;border-spacing:0;">
		<tr>
			<td style="font-family:Poppins,sans-serif;font-size:24px;line-height:28px; padding: 0!important;">
				<a href="<?php echo $imageLink; ?>"><img src="<?php echo $imageSrc; ?>" width="780px" alt=""
						style="width:100%;height:auto;" /></a>
			</td>
		</tr>
	</table>
	<?php
	return ob_get_clean();
} ?>