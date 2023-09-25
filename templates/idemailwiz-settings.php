<?php
acf_form_head();
get_header();
?>

<?php
// Display site settings from wp-admin
$settings = get_option('idemailwiz_settings');
print_r($settings);
$siteOptions = array ('post_id'=>'options','field_groups'=>array('group_650291cf9488a'), 'updated_message'=>'Site options updated');
acf_form($siteOptions);
?>
<?php
get_footer();
?>