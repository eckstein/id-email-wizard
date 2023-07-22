<?php
if (function_exists(get_sub_field)) {
$customHTMLcontent = get_sub_field('raw_html');
} else {
    $customHTMLcontent = '';
}?>

<!-- Custom HTML -->
<?php echo $customHTMLcontent; ?>
<!-- /Custom HTML -->
