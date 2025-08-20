jQuery(document).ready(function ($) {
    // Global campaign search functionality
    let searchTimeout;
    const searchInput = $('#global-search-input');
    const searchResults = $('#global-search-results');

    // Get current campaign type from URL
    function getCurrentCampaignType() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get("view") || "Blast";
    }

    // Debounced search function
    function performSearch(searchTerm) {
        if (searchTerm.length < 2) {
            searchResults.hide().empty();
            return;
        }

        // Show loading indicator
        searchResults.show().html('<div class="global-search-loading"><i class="fa-solid fa-spinner fa-spin"></i> Searching...</div>');

        $.ajax({
            url: idAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'idwiz_global_campaign_search',
                security: idAjax_data_tables.nonce,
                search_term: searchTerm,
                campaign_type: getCurrentCampaignType()
            },
            success: function(response) {
                if (response.success && response.data.campaigns) {
                    displaySearchResults(response.data.campaigns, searchTerm);
                } else {
                    searchResults.html('<div class="global-search-no-results">No campaigns found</div>');
                }
            },
            error: function() {
                searchResults.html('<div class="global-search-error">Search failed. Please try again.</div>');
            }
        });
    }

    // Display search results in dropdown
    function displaySearchResults(campaigns, searchTerm) {
        if (campaigns.length === 0) {
            searchResults.html('<div class="global-search-no-results">No campaigns found</div>');
            return;
        }

        let html = '<div class="global-search-results-list">';
        campaigns.forEach(function(campaign) {
            // Highlight search term in campaign name
            let highlightedName = highlightSearchTerm(campaign.campaign_name, searchTerm);
            
            html += `
                <div class="global-search-result-item" data-campaign-id="${campaign.campaign_id}">
                    <div class="search-result-date">${campaign.campaign_start_formatted}</div>
                    <a href="${campaign.campaign_url}" class="search-result-name">${highlightedName}</a>
                </div>
            `;
        });
        html += '</div>';
        
        if (campaigns.length === 50) {
            html += '<div class="global-search-more">Showing first 50 results. Refine your search for more specific results.</div>';
        }

        searchResults.html(html);
    }

    // Highlight search term in text
    function highlightSearchTerm(text, searchTerm) {
        if (!text || !searchTerm) return text;
        
        const regex = new RegExp('(' + escapeRegExp(searchTerm) + ')', 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    // Escape special regex characters
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // Event handlers
    searchInput.on('input', function() {
        const searchTerm = $(this).val().trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        if (searchTerm.length === 0) {
            searchResults.hide().empty();
            return;
        }

        // Debounce search for 300ms
        searchTimeout = setTimeout(function() {
            performSearch(searchTerm);
        }, 300);
    });

    // Handle focus and blur events
    searchInput.on('focus', function() {
        const searchTerm = $(this).val().trim();
        if (searchTerm.length >= 2 && searchResults.html()) {
            searchResults.show();
        }
    });

    // Hide results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#global-campaign-search').length) {
            searchResults.hide();
        }
    });

    // Handle keyboard navigation
    searchInput.on('keydown', function(e) {
        const resultItems = searchResults.find('.global-search-result-item');
        const currentActive = resultItems.filter('.active');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (currentActive.length === 0) {
                resultItems.first().addClass('active');
            } else {
                const next = currentActive.removeClass('active').next('.global-search-result-item');
                if (next.length) {
                    next.addClass('active');
                } else {
                    resultItems.first().addClass('active');
                }
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (currentActive.length === 0) {
                resultItems.last().addClass('active');
            } else {
                const prev = currentActive.removeClass('active').prev('.global-search-result-item');
                if (prev.length) {
                    prev.addClass('active');
                } else {
                    resultItems.last().addClass('active');
                }
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (currentActive.length) {
                const link = currentActive.find('.search-result-name');
                if (link.length) {
                    window.location.href = link.attr('href');
                }
            }
        } else if (e.key === 'Escape') {
            searchResults.hide();
            searchInput.blur();
        }
    });

    // Handle click on result items
    searchResults.on('click', '.global-search-result-item', function(e) {
        if (!$(e.target).is('a')) {
            const link = $(this).find('.search-result-name');
            if (link.length) {
                window.location.href = link.attr('href');
            }
        }
    });

    // Handle hover on result items
    searchResults.on('mouseenter', '.global-search-result-item', function() {
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
    });

    // Clear search when tab changes
    $(document).on('click', '.campaign-tab', function() {
        searchInput.val('');
        searchResults.hide().empty();
    });
});
