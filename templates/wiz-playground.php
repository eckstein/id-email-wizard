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
// $allCourses = wizPulse_get_all_courses();
// foreach ( $allCourses as $course ) {
// 	if (!empty($course['locations'])) {
// 		if ($course['division']['name'] == 'iD Tech Camps') {
// 			echo $course['title'].'<br/>';
// 		}
// 	}
// }
//wizPulse_map_courses_to_database();
		?>
	</div>
	</div>
</article>

<?php get_footer();
