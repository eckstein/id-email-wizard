<?php
acf_form_head();
get_header(); 

$tempSettings = get_field('template_settings');
$templateStyles = get_field('template_styles');
$emailSettings = get_field('email_settings'); 
$dtSize = $templateStyles['desktop_font_size'] ?? '18px';
$dtHeight = $templateStyles['desktop_line_height'] ?? '26px';
$mobSize = $templateStyles['mobile_font_size'] ?? '16px';
$mobHeight = $templateStyles['mobile_line_height'] ?? '24px';	
$current_user = wp_get_current_user();
$itTemplateId = get_post_meta(get_the_ID(),'itTemplateId',true) ?? '';
?>

<div id="templateUI" class="two-col-wrap" data-iterableid="<?php echo $itTemplateId; ?>">
	<div class="left" id="builder">
	<div class="iDbreadcrumb">Located in: <?php echo display_template_folder_hierarchy(get_the_ID()); ?> | 
	<?php if ($itTemplateId){
		$lastIterableSync = get_post_meta(get_the_ID(), 'lastIterableSync', true) ?? '<em>an unknown date and time.</em>';
		echo 'Last synced to Iterable template <a target="_blank" href="https://app.iterable.com/templates/editor?templateId='.$itTemplateId.'">'.$itTemplateId.'</a> on '.$lastIterableSync;
		} else { 
			echo '<em>Not synced.</em>';
		} ?>
	</div>
	<div id="builder-chunks">
		<?php
		$acfForm = array(
			'id' => 'id-chunks-creator',
			'field_groups' => array(13),
			'post_title' => true,
			'updated_message' => false,
		);
		acf_form( $acfForm ); 
		?>
		<div class="builder-scrollspace"></div>
	</div>
	</div>
	
	<div class="right" id="preview" type="text/html">
		
		
		<div id="templateActions">
			
			<div class="innerWrap">
			<?php if (is_user_favorite(get_the_ID(), 'Template')) {
				  $fileStarClass = 'fa-solid';
			  } else {
				  $fileStarClass = 'fa-regular';
			  } 
			  ?>
			<i title="Add/Remove Favorite" class="addRemoveFavorite <?php echo  $fileStarClass; ?> fa-star" data-objecttype="Template"  data-objectid="<?php echo get_the_ID(); ?>"></i>
			<a title="Save Template" class="button green" id="saveTemplate"><i class="fa-solid fa-floppy-disk"></i>&nbsp;&nbsp;Save</a>
			<a title="Get Template Code"  class="button" id="showFullCode"><i class="fa-solid fa-code"></i>&nbsp;&nbsp;Get Code</a>
			<a title="Sync to Iterable"  class="button" id="sendToIterable" data-postid="<?php echo get_the_id(); ?>"><img src="http://localhost/wp-content/uploads/2023/03/Iterable_square_logo-e1677898367554.png" />&nbsp;&nbsp;Sync to Iterable</a>
			<a title="Duplicate Template"  class="button duplicate-template" data-postid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-copy"></i></a>
			<a title="Delete Template"  class="button delete-template" data-postid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-trash"></i></a>
			
			<div id="deviceSwitcher"><i title="Desktop Preview" class="fas fa-desktop active" id="showDesktop"></i><i title="Mobile Preview" class="fas fa-mobile-alt" id="showMobile"></i></div>
			
			</div>
		</div>
		<div id="templatePreview">
		<iframe id="previewFrame" src="<?php echo home_url('build-template/' . get_the_ID()); ?>"></iframe>
		</div>
		
	</div>
</div>

<?php
get_footer();
?>
