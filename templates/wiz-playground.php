<?php
get_header();

global $wpdb;

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="wizHeader">
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<h1 class="wizEntry-title" itemprop="name">
					Online Private Lessons Purchase Analysis
				</h1>
			</div>
		</div>
	</header>
	<div class="entry-content" itemprop="mainContentOfPage">
		<?php
		$query = "
			SELECT 
				p.purchaseDate,
				u.signupDate
			FROM {$wpdb->prefix}idemailwiz_purchases p
			JOIN {$wpdb->prefix}idemailwiz_users u 
				ON p.userId = u.userId
			WHERE p.shoppingCartItems_divisionName = 'Online Private Lessons'
			ORDER BY p.purchaseDate DESC
			LIMIT 10000
		";
		
		$results = $wpdb->get_results($query);
		?>
		
		<table class="idemailwiz_table display">
			<thead>
				<tr>
					<th>Purchase Date</th>
					<th>Signup Date</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($results as $row): ?>
					<tr>
						<td><?php echo date('Y-m-d', strtotime($row->purchaseDate)); ?></td>
						<td><?php echo date('Y-m-d', strtotime($row->signupDate)); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</article>

<?php get_footer();
