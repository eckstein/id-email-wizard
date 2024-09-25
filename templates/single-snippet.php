<?php get_header(); ?>
<?php
// Retrieve the stored campaign IDs and send dates from post meta
$post_id = get_the_ID();

// Check if the startDate and endDate parameters are present in the $_GET array, if not, default
$startDate = $_GET['startDate'] ?? date( 'Y-m-01' );
$endDate = $_GET['endDate'] ?? date( 'Y-m-d' );

?>
<header class="wizHeader">
	<div class="wizHeaderInnerWrap">
		<div class="wizHeader-left">
			<h1 class="wizEntry-title single-wizcampaign-title" title="<?php echo get_the_title(); ?>" itemprop="name">
				<?php echo get_the_title(); ?>
			</h1>
			<div class="wizEntry-meta"><strong>Wiz Snippet</strong>
			</div>

		</div>
		<div class="wizHeader-right">
			<div class="wizHeader-actions">
				<button title="Save Snippet" class="wiz-button green" id="save-wizSnippet"
					data-post-id="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-floppy-disk"></i>&nbsp;&nbsp;Save
					Snippet</button>
                    <button title="New Snippet" class="new-snippet wiz-button green">
                        <i class="fa-solid fa-circle-plus"></i>&nbsp;&nbsp;New Snippet</button>
				<button title="Delete Snippet" class="delete-snippet wiz-button red"
					data-snippetid="<?php echo get_the_ID(); ?>"><i class="fa-solid fa-trash-can"></i></button>

			</div>
		</div>
	</div>
</header>

<article id="post-<?php the_ID(); ?>" data-journey="<?php echo get_the_ID(); ?>">

	<div class="entry-content" itemprop="mainContentOfPage">
		<div class="wizcampaign-sections-row" id="snippet-code-editors">

			<div class="wizcampaign-section inset" id="snippet-html-editor">
				<div class="wizcampaign-section-title-area">
					<h4>Edit HTML</h4>
					HTML will be inserted into template at desired position
				</div>
				<textarea id="wizSnippet-editor"
					class="wizCodeEditor html"><?php echo get_post_meta( get_the_ID(), 'snippet_content', true ); ?></textarea>

			</div>
			<div class="wizcampaign-section inset" id="snippet-css-editor">
				<div class="wizcampaign-section-title-area">
					<h4>Edit CSS</h4>
					CSS will be inserted into &lt;head&gt;
					of template
				</div>
				<textarea id="wizSnippet-css-editor"
					class="wizCodeEditor css"><?php echo get_post_meta( get_the_ID(), 'snippet_css', true ); ?></textarea>


			</div>
		</div>
	</div>
</article>

<?php get_footer(); ?>