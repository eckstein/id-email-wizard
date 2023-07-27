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
			<a title="Save Template" class="button green" id="saveTemplate"><i class="fa-solid fa-floppy-disk"></i>&nbsp;&nbsp;Save Template</a>
			<a title="Get Template Code"  class="button" id="showFullCode" data-postid="<?php echo get_the_id(); ?>"><i class="fa-solid fa-code"></i>&nbsp;&nbsp;Get Code</a>
			<a title="Sync to Iterable"  class="button" id="sendToIterable" data-postid="<?php echo get_the_id(); ?>"><img style="width: 20px; height: 20px;" src="http://localhost/wp-content/uploads/2023/03/Iterable_square_logo-e1677898367554.png" /></a>
			<a title="Duplicate Template"  class="button duplicate-template" data-postid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-copy"></i></a>
			<a title="Delete Template"  class="button delete-template" data-postid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-trash"></i></a>
			<a title="Fill Merge Tags"  class="button fill-merge-tags" data-postid="<?php echo get_the_ID(); ?>">{ <em>unmerged</em> }</a>
			
			<div id="deviceSwitcher"><i title="Desktop Preview" class="fas fa-desktop active" id="showDesktop"></i><i title="Mobile Preview" class="fas fa-mobile-alt" id="showMobile"></i></div>
			
			</div>
		</div>
		<div id="templatePreview">
		<iframe id="previewFrame" src="<?php echo home_url('build-template/' . get_the_ID()); ?>"></iframe>
		</div>
		<div class="scrollSpace"></div>
	</div>
</div>
<div id="fullScreenCode">
<div class="fullScreenButtons"><button id="copyCode">Copy Code</button>&nbsp;&nbsp;<span class="copyConfirm">Copied!</span><button id="hideFullCode">X</button></div>
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
