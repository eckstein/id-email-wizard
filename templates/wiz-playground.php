<?php
get_header();

global $wpdb;

// Set default values from GET parameters
$campaign_type = isset($_GET['campaign_type']) ? $_GET['campaign_type'] : 'Blast';
$selected_campaign_ids = isset($_GET['campaign_ids']) ? array_map('intval', $_GET['campaign_ids']) : [];
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$attribution_window = isset($_GET['attribution_window']) ? intval($_GET['attribution_window']) : 24;

// Enqueue Select2
wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));

?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="wizHeader">
		<div class="wizHeaderInnerWrap">
			<div class="wizHeader-left">
				<h1 class="wizEntry-title" itemprop="name">
					Campaign Attribution Playground
				</h1>
			</div>
		</div>
	</header>
	<div class="entry-content" itemprop="mainContentOfPage">
		<div class="attribution-interface">
			<form method="GET" id="attribution-form">
				<div class="form-section date-filters">
					<h3>Date Range</h3>
					<div class="date-inputs">
						<label>
							Start Date
							<input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" required>
						</label>
						<label>
							End Date
							<input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" required>
						</label>
					</div>
				</div>

				<div class="form-section">
					<h3>Attribution Settings</h3>
					<div class="attribution-settings">
						<label>
							Attribution Window (hours)
							<input type="number" name="attribution_window" value="<?php echo esc_attr($attribution_window); ?>" min="1" max="168" required>
						</label>
						<p class="description">Time window after send to attribute purchases (max 168 hours / 7 days)</p>
					</div>
				</div>

				<div class="form-section">
					<h3>Campaign Type</h3>
					<label>
						<input type="radio" name="campaign_type" value="Blast" 
							<?php echo $campaign_type === 'Blast' ? 'checked' : ''; ?> 
							onchange="this.form.submit()">
						Blast Campaigns
					</label>
					<label>
						<input type="radio" name="campaign_type" value="Triggered" 
							<?php echo $campaign_type === 'Triggered' ? 'checked' : ''; ?> 
							onchange="this.form.submit()">
						Triggered Campaigns
					</label>
				</div>

				<div class="form-section">
					<h3>Select Campaign(s)</h3>
					<select name="campaign_ids[]" multiple class="campaign-select" style="width: 100%;">
						<?php
						// Query args based on campaign type
						$args = ['type' => $campaign_type];
						
						// For blast campaigns, add date range
						if ($campaign_type === 'Blast') {
							$args['startAt_start'] = $start_date;
							$args['startAt_end'] = $end_date;
							$args['sortBy'] = 'startAt';
							$args['sort'] = 'DESC';
						}
						
						$campaigns = get_idwiz_campaigns($args);
						
						foreach ($campaigns as $campaign) {
							$display_date = '';
							if ($campaign_type === 'Blast' && isset($campaign['startAt'])) {
								$timestamp = is_numeric($campaign['startAt']) ? (int)($campaign['startAt'] / 1000) : 0;
								$display_date = $timestamp ? date('m/d/Y', $timestamp) . ' - ' : '';
							}
							
							$selected = in_array($campaign['id'], $selected_campaign_ids) ? 'selected' : '';
							echo '<option value="' . esc_attr($campaign['id']) . '" ' . $selected . '>' . 
								esc_html($display_date . $campaign['name']) . 
								'</option>';
						}
						?>
					</select>
				</div>
				
				<button type="submit" name="analyze" value="1" class="button button-primary">
					Analyze Attribution
				</button>
			</form>

			<?php
			if (isset($_GET['analyze']) && !empty($selected_campaign_ids)) {
				echo '<div class="attribution-results">';
				echo '<h3>Attribution Results</h3>';
				
				// Temporarily store current user's attribution settings
				$current_user_id = get_current_user_id();
				$original_att_mode = get_user_meta($current_user_id, 'purchase_attribution_mode', true);
				$original_att_length = get_user_meta($current_user_id, 'purchase_attribution_length', true);
				
				// Set attribution mode to campaign-id to get raw purchase data
				update_user_meta($current_user_id, 'purchase_attribution_mode', 'campaign-id');
				update_user_meta($current_user_id, 'purchase_attribution_length', 'allTime');
				
				// Get all purchases in the date range
				$purchase_args = [
					'startAt_start' => $start_date,
					'startAt_end' => $end_date,
					'include_null_campaigns' => true // Include purchases with no campaign ID
				];
				
				// Get all purchases in the date range
				$purchases = get_idwiz_purchases($purchase_args);
				
				// Restore user's attribution settings
				update_user_meta($current_user_id, 'purchase_attribution_mode', $original_att_mode);
				update_user_meta($current_user_id, 'purchase_attribution_length', $original_att_length);
				
				if (empty($purchases)) {
					echo '<p>No purchases found in the selected date range.</p>';
					echo '</div>';
					return;
				}
				
				$attributed_purchases = [];
				
				// Process each purchase
				foreach ($purchases as $purchase) {
					$attribution_info = [];
					$is_attributed = false;
					
					// If purchase has a campaign ID that doesn't match our selected campaigns, skip it
					if (isset($purchase['campaignId']) && !empty($purchase['campaignId']) && !in_array($purchase['campaignId'], $selected_campaign_ids)) {
						continue;
					}
					
					// Check UTM attribution
					if (isset($purchase['campaignId']) && in_array($purchase['campaignId'], $selected_campaign_ids)) {
						$attribution_info['utm_match'] = true;
						$is_attributed = true;
					}
					
					// Check time-based attribution only if no campaign ID or campaign ID matches
					$purchase_time = strtotime($purchase['purchaseDate']);
					
					foreach ($selected_campaign_ids as $campaign_id) {
						// Get campaign sends for this user
						if ($campaign_type === 'Triggered') {
							$sends = get_idemailwiz_triggered_data('idemailwiz_triggered_sends', [
								'campaignIds' => [$campaign_id],
								'userId' => $purchase['userId'],
								'startAt_start' => date('Y-m-d', $purchase_time - ($attribution_window * 3600)), // Custom window before purchase
								'startAt_end' => date('Y-m-d', $purchase_time) // Up to purchase time
							]);
						} else {
							$campaign = get_idwiz_campaign($campaign_id);
							if (!$campaign || !isset($campaign['startAt'])) continue;
							
							$campaign_time = (int)($campaign['startAt'] / 1000);
							$hours_difference = ($purchase_time - $campaign_time) / 3600;
							
							if ($hours_difference >= 0 && $hours_difference <= $attribution_window) {
								$sends = get_engagement_data_by_campaign_id($campaign_id, 'Blast', 'send');
								foreach ($sends as $send) {
									if ($send['userId'] === $purchase['userId']) {
										$attribution_info['time_window'] = number_format($hours_difference, 1);
										$is_attributed = true;
										break 2; // Break both loops
									}
								}
							}
						}
						
						if (!empty($sends)) {
							foreach ($sends as $send) {
								$send_time = (int)($send['startAt'] / 1000);
								$hours_difference = ($purchase_time - $send_time) / 3600;
								
								if ($hours_difference >= 0 && $hours_difference <= $attribution_window) {
									$attribution_info['time_window'] = number_format($hours_difference, 1);
									$is_attributed = true;
									break 2; // Break both loops
								}
							}
						}
					}
					
					if ($is_attributed) {
						$purchase['attribution_info'] = $attribution_info;
						$attributed_purchases[] = $purchase;
					}
				}
				
				// Display results
				if (!empty($attributed_purchases)) {
					echo '<table class="widefat">';
					echo '<thead><tr>
						<th>Purchase Date</th>
						<th>User ID</th>
						<th>Order ID</th>
						<th>Amount</th>
						<th>Campaign ID</th>
						<th>Attribution Type</th>
					</tr></thead>';
					echo '<tbody>';
					
					$total_revenue = 0;
					foreach ($attributed_purchases as $purchase) {
						$attribution_text = [];
						if (isset($purchase['attribution_info']['time_window'])) {
							$attribution_text[] = $purchase['attribution_info']['time_window'] . ' hours after send';
						}
						if (isset($purchase['attribution_info']['utm_match'])) {
							$attribution_text[] = 'Campaign ID Match';
						}
						
						$total_revenue += floatval($purchase['total']);
						
						echo '<tr>';
						echo '<td>' . esc_html($purchase['purchaseDate']) . '</td>';
						echo '<td>' . esc_html($purchase['userId']) . '</td>';
						echo '<td>' . esc_html($purchase['orderId']) . '</td>';
						echo '<td>$' . number_format($purchase['total'], 2) . '</td>';
						echo '<td>' . esc_html($purchase['campaignId']) . '</td>';
						echo '<td>' . implode('<br>', $attribution_text) . '</td>';
						echo '</tr>';
					}
					
					echo '</tbody>';
					echo '<tfoot><tr>
						<td colspan="3"><strong>Total Revenue:</strong></td>
						<td colspan="3"><strong>$' . number_format($total_revenue, 2) . '</strong></td>
					</tr></tfoot>';
					echo '</table>';
				} else {
					echo '<p>No attributed purchases found.</p>';
				}
				
				echo '</div>';
			}
			?>
		</div>
	</div>
</article>

<style>
.attribution-interface {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.form-section {
    margin-bottom: 20px;
}

.date-filters {
    margin-bottom: 30px;
}

.date-inputs {
    display: flex;
    gap: 20px;
}

.date-inputs label {
    flex: 1;
}

.date-inputs input[type="date"],
.attribution-settings input[type="number"] {
    width: 100%;
    padding: 8px;
    margin-top: 5px;
}

.attribution-settings {
    max-width: 300px;
}

.description {
    font-size: 0.9em;
    color: #666;
    margin-top: 5px;
}

.campaign-select {
    width: 100%;
}

.attribution-results {
    margin-top: 30px;
}

.campaign-attribution {
    margin-bottom: 30px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow-x: auto;
}

.widefat {
    font-size: 14px;
    border-collapse: collapse;
    width: 100%;
    margin-top: 20px;
}

.widefat th {
    background-color: #f0f0f0;
    position: sticky;
    top: 0;
    z-index: 1;
}

.widefat td, 
.widefat th {
    padding: 8px;
    border: 1px solid #ddd;
    text-align: left;
}

.widefat tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.form-section label {
    display: block;
    margin: 10px 0;
}

/* Select2 customization */
.select2-container--default .select2-selection--multiple {
    border-color: #ddd;
}

.select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: #007cba;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.campaign-select').select2({
        placeholder: 'Search and select campaigns...',
        allowClear: true,
        width: '100%'
    });
});
</script>

<?php get_footer(); ?>
