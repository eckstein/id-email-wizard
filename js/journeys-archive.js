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
	
	// AJAX search functionality
	let searchTimeout;
	const $searchInput = $('#journey-search');
	const $journeysContainer = $('#journeys-container');
	
	function performSearch() {
		const searchTerm = $searchInput.val().trim();
		const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'running';
		
		// Show loading state
		$journeysContainer.html('<div class="wizcampaign-section inset"><div class="rollup_summary_loader"><i class="fa-solid fa-spinner fa-spin"></i>&nbsp;&nbsp;Searching journeys...</div></div>');
		
		// Build URL with current filter and search term
		const url = new URL(window.location.href);
		if (searchTerm) {
			url.searchParams.set('search', searchTerm);
		} else {
			url.searchParams.delete('search');
		}
		url.searchParams.set('filter', currentFilter);
		
		// Update browser history without page reload
		history.pushState(null, '', url.toString());
		
		// Make AJAX request to get filtered content
		$.ajax({
			url: idAjax.ajaxurl,
			type: 'POST',
			data: {
				action: 'filter_journeys',
				security: idAjax.wizAjaxNonce,
				filter: currentFilter,
				search: searchTerm
			},
			success: function(response) {
				if (response.success) {
					$journeysContainer.html(response.data.html);
					updateJourneyCount();
				} else {
					$journeysContainer.html('<div class="wizcampaign-section inset"><p>Error loading journeys: ' + (response.data || 'Unknown error') + '</p></div>');
				}
			},
			error: function() {
				$journeysContainer.html('<div class="wizcampaign-section inset"><p>Error loading journeys. Please try again.</p></div>');
			}
		});
	}
	
	// Debounced search input
	$searchInput.on('input', function() {
		clearTimeout(searchTimeout);
		searchTimeout = setTimeout(performSearch, 300); // 300ms delay
	});
	
	// Handle filter tab clicks
	$('.filter-tab').on('click', function(e) {
		e.preventDefault();
		
		const $tab = $(this);
		// Extract filter value from href - more reliable approach
		const href = $tab.attr('href');
		const filterMatch = href.match(/[?&]filter=([^&]*)/);
		const filterValue = filterMatch ? filterMatch[1] : 'running';
		
		// Update active tab
		$('.filter-tab').removeClass('active');
		$tab.addClass('active');
		
		// Show loading state
		$journeysContainer.html('<div class="wizcampaign-section inset"><div class="rollup_summary_loader"><i class="fa-solid fa-spinner fa-spin"></i>&nbsp;&nbsp;Loading journeys...</div></div>');
		
		// Update URL
		const url = new URL(window.location.href);
		url.searchParams.set('filter', filterValue);
		history.pushState(null, '', url.toString());
		
		// Perform AJAX request with current search term and new filter
		const searchTerm = $searchInput.val().trim();
		
		$.ajax({
			url: idAjax.ajaxurl,
			type: 'POST',
			data: {
				action: 'filter_journeys',
				security: idAjax.wizAjaxNonce,
				filter: filterValue,
				search: searchTerm
			},
			success: function(response) {
				if (response.success) {
					$journeysContainer.html(response.data.html);
					updateJourneyCount();
				} else {
					$journeysContainer.html('<div class="wizcampaign-section inset"><p>Error loading journeys: ' + (response.data || 'Unknown error') + '</p></div>');
				}
			},
			error: function() {
				$journeysContainer.html('<div class="wizcampaign-section inset"><p>Error loading journeys. Please try again.</p></div>');
			}
		});
	});
	
	// Handle sync buttons
	$('.sync-journeys, .sync-journey').on('click', function(e) {
		e.preventDefault();
		
		const $button = $(this);
		const originalText = $button.text();
		const journeyId = $button.data('journey-id');
		
		$button.prop('disabled', true).text('Syncing...');
		
		const data = {
			action: 'idemailwiz_ajax_sync',
			security: idAjax.wizAjaxNonce,
			campaignIds: journeyId ? JSON.stringify([journeyId]) : JSON.stringify([])
		};
		
		$.post(idAjax.ajaxurl, data)
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
	
	// Handle collapsible campaign tables (event delegation for dynamically loaded content)
	$(document).on('toggle', '.campaigns-details', function() {
		const $summary = $(this).find('.campaigns-summary .toggle-text');
		if (this.open) {
			$summary.text('Hide Individual Campaigns');
		} else {
			$summary.text('Show Individual Campaigns');
		}
	});
	
	// Handle browser back/forward navigation
	window.addEventListener('popstate', function(event) {
		location.reload();
	});
	
	// Clear search functionality
	$(document).on('click', '.search-icon', function() {
		if ($searchInput.val()) {
			$searchInput.val('').trigger('input');
		}
	});
	
	// Enter key search
	$searchInput.on('keypress', function(e) {
		if (e.which === 13) { // Enter key
			clearTimeout(searchTimeout);
			performSearch();
		}
	});
}); 