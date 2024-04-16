<?php
get_header();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="wizHeader">
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<h1 class="wizEntry-title" itemprop="name">Subject Line Builder</h1>
			</div>
			<div class="wizHeader-right">
				<!-- Additional header actions if needed -->
			</div>
		</div>
	</header>

	<div class="entry-content" itemprop="mainContentOfPage">
		<div class="wizcampaign-sections-row">
			<!-- Sync Form -->
			<div class="wizcampaign-section inset">
				<h2>Subject Line Builder</h2>
				<div id="subjectLineBuilder">
					<div id="subjectLinePanel" class="wizcampaign-section shadow">
						<input type="text" id="subjectLine" placeholder="Enter a subject line" />
					</div>
					<div id="previewTextPanel" class="wizcampaign-section shadow">
						<input type="text" id="previewText" placeholder="Enter a preview text" />
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php get_footer(); ?>