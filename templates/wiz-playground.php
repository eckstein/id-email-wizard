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
			$fy24Campaigns = get_idwiz_campaigns(['startAt_start' => '2024-08-01', 'startAt_end' => '2024-08-30', 'type' => ['Blast']]);
			$hourlyMetrics = idwiz_get_hourly_metrics(array_column($fy24Campaigns, 'id'), ['opensByHour', 'clicksByHour'], 72);

			?>
			<div class="wizChartWrapper">
				<canvas id="opensByHourChart" class="engagementByHourChart" data-chartid="engagementByHour" data-campaignids='<?php echo json_encode(array_column($fy24Campaigns, 'id')); ?>' <?php //echo $byHourAttsString; 
																																																?>></canvas>
			</div>
			<div class="wizChartWrapper">
				<canvas id="clicksByHourChart" class="engagementByHourChart" data-chartid="engagementByHour" data-campaignids='<?php echo json_encode(array_column($fy24Campaigns, 'id')); ?>' <?php //echo $byHourAttsString; 
																																																?>></canvas>
			</div>
			<?php
			//print_r($hourlyMetrics);

			// $groupedMetrics = group_by_hour_metrics($hourlyMetrics, 10);


			// foreach ($groupedMetrics as $metricType => $hours) {
			// 	echo '<h4>' . $metricType . '</h4>';
			// 	echo '<table>';
			// 	echo '<tr><th>Hour</th><th>Count</th></tr>';
			// 	ksort($hours); // Sort hours in ascending order
			// 	foreach ($hours as $hour => $campaigns) {
			// 		
			?>
			<!-- <tr>
				 <td><?php //echo $hour; ?></td>
				 <td><?php //echo count($campaigns); ?></td>
				 </tr> -->
			<?php
			// 	}
			// 	echo '</table>';
			// }

			?>
			<!--<button class="add-endpoint">Add Endpoint</button>-->
		</div>
	</div>
	</div>
</article>

<?php get_footer();
