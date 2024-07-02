<?php
get_header();

global $wpdb;

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="wizHeader">
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<h1 class="wizEntry-title" itemprop="name">
					Playground
				</h1>
			</div>
			<div class="wizHeader-right">
				<div class="wizHeader-actions">

				</div>
			</div>
		</div>
	</header>
	<div class="entry-content" itemprop="mainContentOfPage">
		<div class="data-feed-builder">
			<?php
			echo idwiz_display_hourly_metrics_table(9682120);
			?>
			<button class="add-endpoint">Add Endpoint</button>
		</div>
	</div>
	</div>
</article>

<?php get_footer();
