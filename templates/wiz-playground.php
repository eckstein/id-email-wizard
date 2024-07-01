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
			echo idwiz_display_hourly_metrics_table(10313062);

			$endpoints = idwiz_get_all_endpoints();
			foreach ($endpoints as $endpoint) :
			?>
				<div class="endpoint-item">
					<span class="endpoint-url"><?php echo esc_html('idemailwiz/v1/' . $endpoint); ?></span>
					<button class="remove-endpoint" data-endpoint="<?php echo esc_attr($endpoint); ?>">Remove Endpoint</button>
				</div>
			<?php
			endforeach;
			?>
			<button class="add-endpoint">Add Endpoint</button>
		</div>
	</div>
	</div>
</article>

<?php get_footer();
