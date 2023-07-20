<?php
//Chunk Settings
$chunkSettings = get_sub_field('chunk_settings');
	$bgColor = $chunkSettings['background_color'] ?? '#FFFFFF';
$mobileVis = get_sub_field('mobile_visibility');
	$hideMobile = '';
	if ($mobileVis == false) {
		$hideMobile = 'hide-mobile';
	}
$desktopVis = get_sub_field('desktop_visibility');
	$hideDesktop = '';
	if ($desktopVis == false) {
		$hideDesktop = 'hide-desktop';
	}

$leftContent = get_sub_field('left_content') ? get_sub_field('left_content') : array('content_type'=>'image','left_image'=>array('left_image_link'=>'https://www.idtech.com','left_image_url'=>'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/square-image.jpg','left_image_alt'=>''));
$middleContent = get_sub_field('middle_content') ? get_sub_field('middle_content') : array('content_type'=>'text','middle_text'=>array('align'=>'center','text_content'=>'Your content here!','text_color'=>'#000000'));
$rightContent = get_sub_field('right_content') ? get_sub_field('right_content') : array('content_type'=>'image','right_image'=>array('right_image_link'=>'https://www.idtech.com','right_image_url'=>'https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/square-image.jpg','right_image_alt'=>''));
?>

<!-- 3x Columns -->
<table border="0" width="100%" align="center" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;" class="<?php echo $hideMobile . ' ' . $hideDesktop; ?>">
  <tr>
    <td align="center" valign="top">
      <table border="0" width="100%" align="center" cellpadding="0" cellspacing="0" class="row" style=
        "width:100%;max-width:100%;">
        <tr>
          <td class="body-bg-color" align="center" valign="top" bgcolor="#F4F4F4">
            <table border="0" width="800" align="center" cellpadding="0" cellspacing="0" class="row" style=
              "width:800px;max-width:800px;">
              <tr>
                <td class="bg-color" align="center" valign="top" bgcolor="<?php echo $bgColor; ?>">
                  <table width="100%" border="0" cellpadding="0" cellspacing="0" align="center" style=
                    "width:100%;max-width:100%;">
                    <tr>
<?php
for ($x = 1; $x <= 3; $x++) {
if ($x == 1) {
$colContent = $leftContent;
$colKey = 'left';
} elseif ($x == 2) {
$colContent = $middleContent;
$colKey = 'middle';
} elseif ($x == 3) {
$colContent = $rightContent;
$colKey = 'right';
}
echo '
                      <!-- Column '.$x.' Start -->';
if ($colContent['content_type'] == 'image') {
?>

                      <td align="center" valign="top" class="img-responsive">
                        <a href="<?php echo $colContent[$colKey.'_image'][$colKey.'_image_link'] ?>"><img src="<?php echo $colContent[$colKey.'_image'][$colKey.'_image_url'] ?>"
                          style="display:block;max-width:calc(800px/3);border:0px;"
                          width="266" border="0" alt="<?php echo $colContent[$colKey.'_image'][$colKey.'_image_alt'] ?>" /></a>
                      </td>
<?php } elseif ($colContent['content_type'] == 'text') { ?>

                      <td class="text responsive-text <?php echo $colContent[$colKey.'_text']['align'] ?>-text" valign="middle" align="<?php echo $colContent[$colKey.'_text']['align'] ?>" style="font-family:Poppins, sans-serif;color:<?php echo $colContent[$colKey.'_text']['text_color'] ?>!important;text-decoration:none; width:266px;padding: 0 15px;">
<?php echo'                      '.$colContent[$colKey.'_text']['text_content'] ?>

                      </td>
<?php } echo
'                      <!-- / End Column '.$x.' -->

';
                        }
                        ?>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<!-- /3x Columns -->