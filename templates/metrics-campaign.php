<?php
/**
 * Template Name: Metrics Campaign Template
 */

get_header();

$campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;


$campaign = get_idwiz_campaign($campaign_id);
print_r($campaign);
?>

<div class="campaign-content">
  <!-- Display your content here -->
</div>

<?php get_footer(); ?>
