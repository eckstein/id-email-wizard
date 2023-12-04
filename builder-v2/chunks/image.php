<?php
$imageSrc = $chunk['image_src'] ?? '';
$imageLink = $chunk['image_link'] ?? '';
?>
<table role="presentation" style="width:100%;border:0;border-spacing:0;">
    <tr>
        <td
            style="font-family:Poppins,sans-serif;font-size:24px;line-height:28px; padding: 0!important;">
            <img src="<?php echo $imageSrc; ?>" width="800" alt="" style="width:100%;height:auto;" />
        </td>
    </tr>
</table>
