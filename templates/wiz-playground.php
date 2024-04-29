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
		<?php
		sync_single_campaign_data(5555153);
		//idemailwiz_process_job_from_sync_queue('2687542');
		//idwiz_export_and_store_jobs_to_sync_queue();
		//get_campaigns_to_sync();

		
		?>


	</div>
	</div>
</article>

<?php get_footer();