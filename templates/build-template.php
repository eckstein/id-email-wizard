<?php 
//Current query is the custom endpoint we made
global $wp_query;
$template_id = intval($wp_query->query_vars['build-template']);

$chunks = get_field('field_63e3c7cfc01c6', $template_id);
$templateSettings = get_field('field_63e3d8d8cfd3a', $template_id);
$templateFonts = get_field('field_63e3d784ed5b5', $template_id);
$emailSettings = get_field('field_63e898c6dcd23', $template_id);
//print_r($templateSettings);
//print_r($templateFonts);
//print_r($emailSettings);

//Preview pane styles
?>
<style type="text/css">
.chunkWrap {
	border: 2px solid rgba(255,255,255,0);
	position:relative;
	cursor: pointer;
}
	.chunkWrap:hover {
		border: 2px solid #666;
	}
	.chunkWrap.active {
		border: 2px solid #000!important;
	}
.chunkWrap:hover .chunkOverlay {
	display: block;
}
.chunkOverlay {
	display: none;
	position: absolute;
	top: 5px;
	right: 5px;
}
	.chunkOverlay .chunk-label {
		font-size: 12px;
		padding: 5px;
		background-color: rgba(0,0,0,.9);
		color: #fff;
		display: inline-block;
		margin-right: 10px;
	}
.showChunkCode:hover {
	background-color: #222!important;
	color: #fff!important;
	border-color: #222!important;
}
</style>
<?php

//Start Template
//Default email header (<head>, open tags, styles, etc)
include(plugin_dir_path( __FILE__ ) . 'chunks/email-top.php');
include(plugin_dir_path( __FILE__ ) . 'chunks/css.php');
include(plugin_dir_path( __FILE__ ) . 'chunks/end-email-top.php');


//Standard email header snippet, if active
if ($templateSettings['id_tech_header'] == true) {
    //include(plugin_dir_path( __FILE__ ) . 'chunks/standard-email-header.php');
    include(plugin_dir_path( __FILE__ ) . 'chunks/preview-header.html');
}

//Start Chunk Content
$i=0;
foreach ($chunks as $chunk) {
    //print_r($chunk);
   $chunkFileName = str_replace('_', '-', $chunk['acf_fc_layout']);
   $file = plugin_dir_path( __FILE__ ) . 'chunks/' . $chunkFileName . '.php';
    if (file_exists($file)) {
        echo '<div class="chunkWrap" data-id="row-'.$i.'" data-chunk-layout="'.$chunk['acf_fc_layout'].'">';
        include($file);
        echo '<div class="chunkOverlay"><span class="chunk-label">Chunk Type: '.$chunk['acf_fc_layout'].'</span><button class="showChunkCode" data-id="row-'.$i.'">Get Code</button></div>';
        echo '</div>';
    }
    $i++;
}

//Email footer (close tags, disclaimer)
if ($templateSettings['id_tech_footer'] == true) {
    //Standard email footer snippet (social links, unsub, etc)
    //include(plugin_dir_path( __FILE__ ) . 'chunks/standard-email-footer.php');
    include(plugin_dir_path( __FILE__ ) . 'chunks/preview-footer.html');
}

//Fine print/disclaimer text
if (!empty($templateSettings['fine_print_disclaimer'])) {
    include(plugin_dir_path( __FILE__ ) . 'chunks/email-before-disclaimer.php');
    include(plugin_dir_path( __FILE__ ) . 'chunks/fine-print-disclaimer.php');
    include(plugin_dir_path( __FILE__ ) . 'chunks/email-after-disclaimer.php');
}


?>