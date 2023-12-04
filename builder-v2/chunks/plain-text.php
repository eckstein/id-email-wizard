<?php
$chunkSettings = $chunk['chunk_settings'];
$textContent = $chunk['plain_text_content'] ?? 'Your content goes here!';

$contentAlign = $chunkSettings['align_content'] ?? 'center';
$textColor = $chunkSettings['text_color'] ?? '#000000';
$bgColor = $chunkSettings['background_color'] ?? '#FFFFFF';
?>
<table role="presentation" style="width:100%;border:0;border-spacing:0;">
  <tr>
    <td style="padding:10px;text-align:<?php echo $contentAlign; ?>;">
      <p style="margin:0;font-family:Poppins,sans-serif;font-size:18px;line-height:24px;"> <?php echo idwiz_pReplace(wpautop($textContent)); ?></p>
    </td>
  </tr>
</table>