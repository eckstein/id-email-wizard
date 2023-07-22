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
		echo '<strong>Last synced to Iterable template <a target="_blank" href="https://app.iterable.com/templates/editor?templateId='.$itTemplateId.'">'.$itTemplateId.'</a></strong>';
		} else { 
			echo '<em>Not synced.</em>';
		} ?>
	</div>
		<?php
		$acfForm = array(
			'id' => 'id-chunks-creator',
			'field_groups' => array(13),
			'post_title' => true,
			'updated_message' => false,
		);
		acf_form( $acfForm ); 
		?>
		<div id="stopScroll"></div>
	</div>
	
	<div class="right" id="preview" type="text/html">
		
		
		<div id="templateActions" class="pre-sticky">
			
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
		<?php if (isset($tempSettings['id_tech_header']) && $tempSettings['id_tech_header'] == true) { ?>
		<div class="iDheader">
		<img src="https://d15k2d11r6t6rl.cloudfront.net/public/users/Integrators/669d5713-9b6a-46bb-bd7e-c542cff6dd6a/d290cbad793f433198aa08e5b69a0a3d/editor_images/id-grey-header-white-bg_1.jpg" style="max-width: 100%;"/>
		</div>
		<?php } ?>
		
		
		<style>
		<?php echo file_get_contents(get_stylesheet_directory_uri().'/styles/ieFixStyle.css'); ?>
		</style>
		<div id="inline-styles">
		<?php //echo file_get_contents(get_stylesheet_directory_uri().'/styles/inlineStyles-mobile.css'); ?>
		<link rel="stylesheet" href="<?php echo plugins_url('/styles/inlineStyles.css', IDEMAILWIZ_ROOT); ?>" type="text/css" />
		<?php //echo file_get_contents(get_stylesheet_directory_uri().'/styles/inlineStyles.css'); ?>
		</div>
		<style type="text/css">
		 /*Desktop Only Style*/
		@media screen and (min-width: 661px) {
			.responsive-text,.responsive-text p {
				font-size: <?php echo $dtSize; ?>!important;
				line-height: <?php echo $dtHeight; ?>!important;
			}
		}
		/*Mobile Only Style*/
		@media screen and (max-width: 660px) {
			.responsive-text,.responsive-text p {
				font-size: <?php echo $mobSize; ?>!important;
				line-height: <?php echo $mobHeight; ?>!important;
				padding-left: 10px;
				padding-right: 10px;
			}
			.center-on-mobile {
				text-align: center!important;
			}
		}
		</style>
		<?php 
		if( have_rows('add_chunk') ):
			$chunks = '';
			$chunkArray = array();
			$i = 0;
			while ( have_rows('add_chunk') ) : the_row();
				//Generate the chunks with output buffering
				$chunkRow = get_row();
				$chunk = iD_generate_chunk($chunkRow);
				if ($emailSettings['external_utms']) {
					$UTMs = $emailSettings['external_utm_string'];
					//If we're adding custom UTMs, do that here
					$chunk = preg_replace_callback('/href="([^"]+)"/', function($match) use ($UTMs) {
						return 'href="' . $match[1] . $UTMs . '"';
					}, $chunk);
				}
				echo '<div class="chunkWrap" data-id="row-'.$i.'" data-chunk-layout="'.$chunkRow['acf_fc_layout'].'">';
				
				echo $chunk;
				echo '<div class="chunkOverlay"><span class="chunk-label">Chunk Type: '.$chunkRow['acf_fc_layout'].'</span><button class="showChunkCode" data-id="row-'.$i.'">Get Code</button>';
				echo '<div style="display: none;" class="hiddenCodeChunk"><pre><code>'.htmlspecialchars(str_replace(array('&#8220;', '&#8221;', '&#8216;'),array('"','"','\''), html_entity_decode($chunk))).'</code></pre></div>';
				echo '</div>';
				echo '</div>';
				
				//Put chunks into array
				$chunkArray['row-'.$i] = htmlspecialchars(str_replace(array('&#8220;', '&#8221;', '&#8216;'),array('"','"','\''), html_entity_decode($chunk)));
				
				//put all the HTML into a variable to display it down in the HTML panel
				$chunks .= $chunk;				
				$i++;
			// End loop.
			endwhile;
		// No value.
		else :
			$chunks = '';
		endif;
		?>
		
		<?php if (isset($tempSettings['id_tech_footer']) && $tempSettings['id_tech_footer'] == true) { ?>
		<div class="iDfooter">
		<?php echo file_get_contents(plugins_url('templates/chunks/preview-footer.html', IDEMAILWIZ_ROOT));?>
		
		</div>
		<?php } ?>
		<?php if (isset($tempSettings['fine_print_disclaimer']) && $tempSettings['fine_print_disclaimer'] == true) { ?>
		<div class="finePrint">
		<?php echo $tempSettings['fine_print_disclaimer']; ?>
		</div>
		<?php } ?>
		</div>
	</div>
</div>
<div id="fullScreenCode">
<div class="fullScreenButtons"><button id="copyCode">Copy Code</button>&nbsp;&nbsp;<span class="copyConfirm">Copied!</span><button id="hideFullCode">X</button></div>
<div id="generatedHTML">


	<pre id="generatedCode" >
	<code>
	
	<?php
	
	$desktopCss =
'.responsive-text {
	font-size: '.$dtSize.';
	line-height: '.$dtHeight.';
	}';
	$mobileCss =
'.responsive-text {
	font-size: '.$mobSize.';
	line-height: '.$mobHeight.';
	padding-left: 10px;
	padding-right: 10px;
	}';

	ob_start();
	include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/chunks/email-top.php';
	$emailTop = ob_get_clean();
	
	$emailTop = str_replace("/*DesktopResponsiveTextHere*/", $desktopCss, $emailTop);
	$emailTop = str_replace("/*MobileResponsiveTextHere*/", $mobileCss, $emailTop);
		echo htmlspecialchars($emailTop);
		if ($tempSettings['id_tech_header'] == true) {
			ob_start();
			include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/chunks/standard-email-header.php';
			$standardHeader = ob_get_clean();
			echo htmlspecialchars($standardHeader).'&#10;';
		}
		
		//Echo the HTML for all the in-between chunks
		//$decodedChunks = html_entity_decode($chunks);
		$decodedChunks = str_replace('&#8220;', '"', $chunks);
		$decodedChunks = str_replace('&#8221;', '"', $decodedChunks);
		$decodedChunks = str_replace('&#8216;', '\'', $decodedChunks);
		
	
		
		echo htmlspecialchars(html_entity_decode($decodedChunks)).'&#10;';
		
		if ($tempSettings['id_tech_footer'] == true) {
			ob_start();
			include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/chunks/standard-email-footer.php';
			$standardFooter = ob_get_clean();
			echo htmlspecialchars($standardFooter).'&#10;';
		}
		if ($tempSettings['fine_print_disclaimer'] != "") {
			ob_start();
			include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/chunks/email-before-disclaimer.php';
			include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/chunks/fine-print-disclaimer.php';
			include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/chunks/email-after-disclaimer.php';
			$beforeDisclaimer = ob_get_clean();
			echo htmlspecialchars($beforeDisclaimer).'&#10;';	
		}
			ob_start();
			include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/chunks/email-closing-tags.php';
			$closingTags = ob_get_clean();
			echo htmlspecialchars($closingTags);
?>
</code>
</pre>
</div>
</div>





<?php
get_footer();
?>
