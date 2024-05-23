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
		if (isset($_GET['sync-sends-by-week'])) {
			$year = $_GET['weekYear'] ?? date('Y');
			$week = $_GET['week'] ?? date('W');

			// Check if the year has a 53rd week
			$lastWeekOfYear = date('W', strtotime("$year-12-31"));
			if ($week == 53 && $lastWeekOfYear != 53) {
				echo "The year $year does not have a 53rd week.";
			} else {
				idemailwiz_sync_sends_by_week($year, $week);
				echo "Synced year $year, week $week";
			}
		}
		$yearsOptions = ['2024', '2023', '2022', '2021'];
		// determined selected based on $_GET
		$selected = '';

		?>
		<form name="sync-sends-by-week" method="GET" action="<?php echo esc_url(get_permalink()); ?>">
			<select name="weekYear">
				<?php
				foreach ($yearsOptions as $yearOption) {
					$selected = ($yearOption == $year) ? 'selected' : '';
					echo "<option value='$yearOption' $selected>$yearOption</option>";
				}
				?>
			</select>
			<input type="number" name="week" value="<?php echo $week ?? '1'; ?>" max="53" min="1" />
			<input type="hidden" name="sync-sends-by-week" value="1" />
			<input type="submit" value="Sync Weekly Sends" />
		</form>
	</div>
	</div>
</article>

<?php get_footer();
