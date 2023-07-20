<?php 
$customHTMLcontent = get_sub_field('raw_html');
$mobileVis = $chunkSettings['mobile_visibility'] ?? true;
$hideMobile = '';
if ($mobileVis == false) {
$hideMobile = 'hide-mobile';
}?>

<!-- Custom HTML -->
<?php echo $customHTMLcontent; ?>
<!-- /Custom HTML -->
