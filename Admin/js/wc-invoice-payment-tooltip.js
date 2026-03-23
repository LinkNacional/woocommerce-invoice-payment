/**
 * WC Invoice Payment Tooltip Functionality
 * Handles the subscription active badge tooltip interactions
 */

(function($) {
    'use strict';

    /**
     * Initialize tooltip functionality when DOM is ready
     */
    $(document).ready(function() {
        initSubscriptionTooltip();
    });

    /**
     * Initialize subscription badge tooltip
     */
    function initSubscriptionTooltip() {
        const badge = $('.wcip-subscription-badge');
        const tooltip = $('.wcip-subscription-tooltip');

        if (badge.length && tooltip.length) {
            // Mouse enter event
            badge.on('mouseenter', function() {
                showTooltip(tooltip);
            });

            // Mouse leave event
            badge.on('mouseleave', function() {
                hideTooltip(tooltip);
            });

            // Touch events for mobile devices
            badge.on('touchstart', function(e) {
                e.preventDefault();
                toggleTooltip(tooltip);
            });

            // Hide tooltip when clicking outside
            $(document).on('click touchstart', function(e) {
                if (!badge.is(e.target) && !tooltip.is(e.target) && 
                    badge.has(e.target).length === 0 && tooltip.has(e.target).length === 0) {
                    hideTooltip(tooltip);
                }
            });
        }
    }

    /**
     * Show tooltip with smooth animation
     * @param {jQuery} tooltip - The tooltip element
     */
    function showTooltip(tooltip) {
        tooltip.addClass('show');
    }

    /**
     * Hide tooltip with smooth animation
     * @param {jQuery} tooltip - The tooltip element
     */
    function hideTooltip(tooltip) {
        tooltip.removeClass('show');
    }

    /**
     * Toggle tooltip visibility (for touch devices)
     * @param {jQuery} tooltip - The tooltip element
     */
    function toggleTooltip(tooltip) {
        if (tooltip.hasClass('show')) {
            hideTooltip(tooltip);
        } else {
            showTooltip(tooltip);
        }
    }

    /**
     * Accessibility improvements
     */
    function addAccessibilityFeatures() {
        const badge = $('.wcip-subscription-badge');
        const tooltip = $('.wcip-subscription-tooltip');

        if (badge.length && tooltip.length) {
            // Add ARIA attributes for screen readers
            badge.attr({
                'aria-describedby': 'subscription-tooltip',
                'role': 'button',
                'tabindex': '0'
            });

            tooltip.attr({
                'id': 'subscription-tooltip',
                'role': 'tooltip',
                'aria-hidden': 'true'
            });

            // Keyboard support
            badge.on('keydown', function(e) {
                // Show/hide tooltip on Enter or Space key
                if (e.keyCode === 13 || e.keyCode === 32) {
                    e.preventDefault();
                    toggleTooltip(tooltip);
                }
                // Hide tooltip on Escape key
                if (e.keyCode === 27) {
                    hideTooltip(tooltip);
                }
            });

            // Update aria-hidden when showing/hiding tooltip
            badge.on('mouseenter', function() {
                tooltip.attr('aria-hidden', 'false');
            });

            badge.on('mouseleave', function() {
                tooltip.attr('aria-hidden', 'true');
            });
        }
    }

    // Initialize accessibility features
    $(document).ready(function() {
        addAccessibilityFeatures();
    });

})(jQuery);
