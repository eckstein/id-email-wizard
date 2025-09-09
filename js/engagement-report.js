/**
 * Engagement Report JavaScript
 */

jQuery(document).ready(function($) {
    // Handle module control toggle with more specific targeting
    $(document).on('click', '.engagement-module-actions .toggle-module-controls', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        var moduleId = $(this).data('module');
        var $controls = $('#' + moduleId + '-module .engagement-module-controls');
        var $button = $(this);
        
        if ($controls.is(':visible')) {
            // Hide controls
            $controls.slideUp(300, function() {
                $button.text('Show Controls');
            });
        } else {
            // Show controls
            $controls.slideDown(300, function() {
                $button.text('Hide Controls');
            });
        }
        
        return false;
    });
    
    // Handle individual module updates
    $(document).on('click', '.update-module-btn', function() {
        var moduleId = $(this).data('module');
        updateEngagementModule(moduleId);
    });
    
    function updateEngagementModule(moduleId) {
        var $module = $('#' + moduleId + '-module');
        var $canvas = $module.find('canvas');
        
        // Add visual feedback
        $module.addClass('updating');
        
        // Get current values
        var minFilter = parseFloat($module.find('.module-filter-min').val()) || 0;
        var maxFilter = parseFloat($module.find('.module-filter-max').val()) || 100;
        var minScale = parseFloat($module.find('.module-scale-min').val()) || 0;
        var maxScale = parseFloat($module.find('.module-scale-max').val()) || 100;
        
        // Validate input ranges
        if (minFilter >= maxFilter) {
            alert('Minimum filter value must be less than maximum filter value');
            $module.removeClass('updating');
            return;
        }
        
        if (minScale >= maxScale) {
            alert('Minimum scale value must be less than maximum scale value');
            $module.removeClass('updating');
            return;
        }
        
        // Update canvas data attributes
        $canvas.attr('data-minfilter', minFilter);
        $canvas.attr('data-maxfilter', maxFilter);
        $canvas.attr('data-minscale', minScale);
        $canvas.attr('data-maxscale', maxScale);
        $canvas.attr('data-minmetric', minFilter);
        $canvas.attr('data-maxmetric', maxFilter);
        
        // Destroy existing chart and recreate
        if ($canvas[0].chartInstance) {
            $canvas[0].chartInstance.destroy();
        }
        
        // Recreate chart with delay for visual feedback
        setTimeout(function() {
            idwiz_fill_chart_canvas($canvas[0]);
            
            // Remove updating state after a brief delay
            setTimeout(function() {
                $module.removeClass('updating');
            }, 500);
        }, 100);
        
        // Update URL parameters for persistence
        var url = new URL(window.location);
        url.searchParams.set(moduleId + '_minFilter', minFilter);
        url.searchParams.set(moduleId + '_maxFilter', maxFilter);
        url.searchParams.set(moduleId + '_minScale', minScale);
        url.searchParams.set(moduleId + '_maxScale', maxScale);
        window.history.pushState({}, '', url);
    }
});
