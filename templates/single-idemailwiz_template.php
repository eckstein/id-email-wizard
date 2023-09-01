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
<div id="templateUI" class="entry-content two-col-wrap" data-iterableid="<?php echo $itTemplateId; ?>" itemprop="mainContentOfPage">
	<div class="left" id="builder">
	<div class="iDbreadcrumb">Located in: <?php echo display_template_folder_hierarchy(get_the_ID()); ?> | 
	<?php if ($itTemplateId){
		$lastIterableSync = get_post_meta(get_the_ID(), 'lastIterableSync', true) ?? '<em>an unknown date and time.</em>';
		echo 'Last synced to Iterable template <a target="_blank" href="https://app.iterable.com/templates/editor?templateId='.$itTemplateId.'">'.$itTemplateId.'</a> on '.$lastIterableSync;
		} else { 
			echo '<em>Not synced.</em>';
		} ?>
	</div>
	<div id="single-template-title">
		<form id="template-title-form">
			<input type="text" name="templateTitle" id="idwiz_templateTitle" data-templateid="<?php echo get_the_ID(); ?>" value="<?php echo get_the_title(get_the_ID()); ?>"/>
		</form>
	</div>
	<div id="builder-chunks">
		<?php
		$acfForm = array(
			'id' => 'id-chunks-creator',
			'field_groups' => array(13),
			'updated_message' => false,
			'html_after_fields' => '<div class="scrollSpace"></div>'
		);
		acf_form( $acfForm ); 
		?>
		
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
			<a title="Save Template" class="wiz-button green" id="saveTemplate"><i class="fa-solid fa-floppy-disk"></i>&nbsp;&nbsp;Save</a>
			<a title="Get Template Code"  class="wiz-button" id="showFullCode" data-postid="<?php echo get_the_id(); ?>"><i class="fa-solid fa-code"></i>&nbsp;&nbsp;Code</a>
			<a title="Sync to Iterable"  class="wiz-button" id="sendToIterable" data-postid="<?php echo get_the_id(); ?>"><img style="width: 20px; height: 20px;" src="http://localhost/wp-content/uploads/2023/03/Iterable_square_logo-e1677898367554.png" />&nbsp;&nbsp Sync</a>
			<span class="templateActions-divider"></span>
			<a title="Duplicate Template"  class="wiz-button duplicate-template" data-postid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-copy"></i></a>
			<a title="Move Template"  class="wiz-button green moveTemplate" data-postid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-folder-tree"></i></i></a>
			<a title="Delete Template"  class="wiz-button delete-template" data-postid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-trash"></i></a>
			
			
			
			<div id="deviceSwitcher">
				<i title="Desktop Preview" class="fas fa-desktop active" id="showDesktop"></i>
				<i title="Mobile Preview" class="fas fa-mobile-alt" id="showMobile"></i>
				<div title="Toggle Separators"  class="toggle-separators active"><i class="fa-solid fa-xmarks-lines"></i></div>
				<div title="Fill Merge Tags"  class="fill-merge-tags" data-postid="<?php echo get_the_ID(); ?>">&nbsp;{<i class="fa-solid fa-power-off"></i>}&nbsp;</div>
			</div>
			
			</div>
		</div>
		<div id="templatePreview">
		<iframe id="previewFrame" src="<?php echo home_url('build-template/' . get_the_ID()); ?>"></iframe>
		</div>
		<div class="scrollSpace"></div>
	</div>
</div>
<div id="fullScreenCode">
	<div class="fullScreenButtons"><div class="wiz-button green" id="copyCode">Copy Code</button>&nbsp;&nbsp;<span class="copyConfirm">Copied!</span></div> <button class="wiz-button" id="hideFullCode">X</button></div>
	<div id="generatedHTML">
	<pre id="generatedCode" >
	<code class="language-html">
		Code here.
	</code>
	</pre>
	</div>
</div>
<?php
get_footer();
?>
