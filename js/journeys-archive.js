jQuery(document).ready(function($) {
	// Update journey count on page load
	function updateJourneyCount() {
		const journeyWrappers = $('.journey-wrapper').length;
		const countElement = $('#journey-count');
		if (countElement.length) {
			countElement.text(journeyWrappers);
		}
	}
	
	// Update count on page load
	updateJourneyCount();
	
	$('.sync-journeys, .sync-journey').on('click', function(e) {
		e.preventDefault();
		
		const $button = $(this);
		const originalText = $button.text();
		const journeyId = $button.data('journey-id');
		
		$button.prop('disabled', true).text('Syncing...');
		
		const data = {
			action: 'idemailwiz_ajax_sync',
			security: wizAjax.nonce,
			campaignIds: journeyId ? JSON.stringify([journeyId]) : JSON.stringify([])
		};
		
		$.post(wizAjax.ajaxurl, data)
			.done(function(response) {
				if (response.success) {
					$button.text('Synced!').removeClass('green').addClass('blue');
					setTimeout(() => {
						location.reload();
					}, 1000);
				} else {
					alert('Sync failed: ' + (response.data || 'Unknown error'));
					$button.prop('disabled', false).text(originalText);
				}
			})
			.fail(function(xhr, status, error) {
				alert('Sync failed: Network error');
				$button.prop('disabled', false).text(originalText);
			});
	});
	
	// Handle collapsible campaign tables
	$('.campaigns-details').on('toggle', function() {
		const $summary = $(this).find('.campaigns-summary .toggle-text');
		if (this.open) {
			$summary.text('Hide Individual Campaigns');
		} else {
			$summary.text('Show Individual Campaigns');
		}
	});
}); 