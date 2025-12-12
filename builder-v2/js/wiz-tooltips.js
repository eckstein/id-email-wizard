/**
 * Wiz Tooltips
 * A lightweight tooltip system for the WYSIWYG builder
 */
(function($) {
    'use strict';

    const WizTooltips = {
        tooltipElement: null,
        activeTooltip: null,
        hideTimeout: null,

        init: function() {
            // Create the tooltip container element
            this.createTooltipElement();
            
            // Bind events
            this.bindEvents();
        },

        createTooltipElement: function() {
            // Create tooltip element if it doesn't exist
            if ($('#wiz-tooltip-container').length === 0) {
                $('body').append('<div id="wiz-tooltip-container" class="wiz-tooltip --bottom"><div class="wiz-tooltip-content"></div></div>');
            }
            this.tooltipElement = $('#wiz-tooltip-container');
        },

        bindEvents: function() {
            const self = this;

            // Show tooltip on hover/focus
            $(document).on('mouseenter focus', '.wiz-tooltip-trigger', function(e) {
                clearTimeout(self.hideTimeout);
                self.show($(this));
            });

            // Hide tooltip when leaving trigger or tooltip
            $(document).on('mouseleave blur', '.wiz-tooltip-trigger', function(e) {
                self.scheduleHide();
            });

            // Keep tooltip visible when hovering over it
            $(document).on('mouseenter', '#wiz-tooltip-container', function() {
                clearTimeout(self.hideTimeout);
            });

            $(document).on('mouseleave', '#wiz-tooltip-container', function() {
                self.scheduleHide();
            });

            // Hide on scroll or resize
            $(window).on('scroll resize', function() {
                self.hide();
            });

            // Hide on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.hide();
                }
            });
        },

        show: function($trigger) {
            const self = this;
            const tooltipText = $trigger.data('tooltip');
            const tooltipTitle = $trigger.data('tooltip-title') || '';
            
            if (!tooltipText) return;

            // Build content
            let content = '';
            if (tooltipTitle) {
                content += '<span class="wiz-tooltip-title">' + tooltipTitle + '</span>';
            }
            content += tooltipText;

            this.tooltipElement.find('.wiz-tooltip-content').html(content);
            
            // Position the tooltip
            this.position($trigger);
            
            // Show with animation
            this.tooltipElement.addClass('--visible');
            this.activeTooltip = $trigger;
        },

        position: function($trigger) {
            const triggerOffset = $trigger.offset();
            const triggerWidth = $trigger.outerWidth();
            const triggerHeight = $trigger.outerHeight();
            
            // Reset position classes
            this.tooltipElement.removeClass('--top --bottom --left --right');
            
            // Get tooltip dimensions (temporarily show to measure)
            this.tooltipElement.css({ visibility: 'hidden', display: 'block' });
            const tooltipWidth = this.tooltipElement.outerWidth();
            const tooltipHeight = this.tooltipElement.outerHeight();
            this.tooltipElement.css({ visibility: '', display: '' });

            const viewportWidth = $(window).width();
            const viewportHeight = $(window).height();
            const scrollTop = $(window).scrollTop();
            const scrollLeft = $(window).scrollLeft();

            // Calculate available space
            const spaceBelow = viewportHeight - (triggerOffset.top - scrollTop + triggerHeight);
            const spaceAbove = triggerOffset.top - scrollTop;
            const spaceRight = viewportWidth - (triggerOffset.left - scrollLeft + triggerWidth);
            const spaceLeft = triggerOffset.left - scrollLeft;

            let top, left;
            const gap = 8; // Gap between trigger and tooltip

            // Prefer bottom, then top, then right, then left
            if (spaceBelow >= tooltipHeight + gap || spaceBelow >= spaceAbove) {
                // Position below
                top = triggerOffset.top + triggerHeight + gap;
                left = triggerOffset.left + (triggerWidth / 2) - (tooltipWidth / 2);
                this.tooltipElement.addClass('--bottom');
            } else if (spaceAbove >= tooltipHeight + gap) {
                // Position above
                top = triggerOffset.top - tooltipHeight - gap;
                left = triggerOffset.left + (triggerWidth / 2) - (tooltipWidth / 2);
                this.tooltipElement.addClass('--top');
            } else if (spaceRight >= tooltipWidth + gap) {
                // Position right
                top = triggerOffset.top + (triggerHeight / 2) - (tooltipHeight / 2);
                left = triggerOffset.left + triggerWidth + gap;
                this.tooltipElement.addClass('--right');
            } else {
                // Position left
                top = triggerOffset.top + (triggerHeight / 2) - (tooltipHeight / 2);
                left = triggerOffset.left - tooltipWidth - gap;
                this.tooltipElement.addClass('--left');
            }

            // Keep tooltip within viewport horizontally
            if (left < scrollLeft + 10) {
                left = scrollLeft + 10;
            } else if (left + tooltipWidth > scrollLeft + viewportWidth - 10) {
                left = scrollLeft + viewportWidth - tooltipWidth - 10;
            }

            // Keep tooltip within viewport vertically
            if (top < scrollTop + 10) {
                top = scrollTop + 10;
            }

            this.tooltipElement.css({
                top: top + 'px',
                left: left + 'px'
            });
        },

        scheduleHide: function() {
            const self = this;
            this.hideTimeout = setTimeout(function() {
                self.hide();
            }, 150);
        },

        hide: function() {
            clearTimeout(this.hideTimeout);
            this.tooltipElement.removeClass('--visible');
            this.activeTooltip = null;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        WizTooltips.init();
    });

    // Expose globally if needed
    window.WizTooltips = WizTooltips;

})(jQuery);

