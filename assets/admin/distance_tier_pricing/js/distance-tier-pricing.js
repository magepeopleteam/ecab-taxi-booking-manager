/**
 * Frontend Distance Tier Pricing JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize distance tier pricing functionality
    initDistanceTierPricing();

    function initDistanceTierPricing() {
        // Update distance tier badges when distance changes
        updateDistanceTierBadges();

    // Listen for distance changes from multiple sources
    $(document).on('change input', 'input[name="distance"], input[name="mptbm_distance"], input[name*="distance"]', function() {
        setTimeout(updateDistanceTierBadges, 300);
    });

    // Also listen for cookie changes or AJAX updates
    $(document).on('mptbm_distance_updated', function() {
        setTimeout(updateDistanceTierBadges, 300);
    });

        // Listen for form submissions
        $(document).on('submit', 'form', function() {
            setTimeout(updateDistanceTierBadges, 1000);
        });
    }

    function updateDistanceTierBadges() {
        $('.mptbm_booking_item').each(function() {
            var $item = $(this);
            var postId = $item.data('post-id') || $item.find('[data-post-id]').first().data('post-id');

            if (!postId) {
                // Try to extract post ID from class name
                var classMatch = $item.attr('class').match(/mptbm_booking_item_(\d+)/);
                if (classMatch) {
                    postId = classMatch[1];
                }
            }

            if (postId) {
                // Get current distance from multiple sources
                var distance = $('input[name="distance"]').val() ||
                              $('input[name="mptbm_distance"]').val() ||
                              getCookie('mptbm_distance');

                // Also check hidden inputs or data attributes
                if (!distance) {
                    distance = $item.data('distance') || $item.find('input[type="hidden"][name*="distance"]').val();
                }

                if (distance && parseFloat(distance) > 0) {
                    // Make AJAX call to check distance tier
                    $.ajax({
                        url: mptbm_ajax.ajax_url || '/wp-admin/admin-ajax.php',
                        type: 'POST',
                        data: {
                            action: 'check_distance_tier_pricing',
                            post_id: postId,
                            distance: parseFloat(distance),
                            nonce: mptbm_ajax.nonce || ''
                        },
                        success: function(response) {
                            if (response.success && response.data.tier_info) {
                                updateTierBadgeDisplay($item, response.data.tier_info);
                            } else {
                                updateTierBadgeDisplay($item, null);
                            }
                        },
                        error: function() {
                            // Fallback: remove badge
                            updateTierBadgeDisplay($item, null);
                        }
                    });
                } else {
                    updateTierBadgeDisplay($item, null);
                }
            }
        });
    }

    function updateTierBadgeDisplay($item, tierInfo) {
        var $badgeContainer = $item.find('.mptbm-tier-pricing-savings-ticket-container');

        // Remove existing tier badge
        $badgeContainer.find('.mptbm-distance-tier-badge').remove();

        if (tierInfo) {
            var badgeHtml = '<div class="mptbm-distance-tier-badge" title="Distance: ' +
                          tierInfo.distance_km.toFixed(1) + 'km - ' +
                          tierInfo.tier_name + '">' +
                          '<span class="badge-icon">📏</span>' +
                          '<span class="badge-text">' + tierInfo.tier_name + '</span>' +
                          '</div>';

            $badgeContainer.prepend(badgeHtml);
        }
    }

    // Helper function to get cookie value
    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length == 2) return parts.pop().split(";").shift();
    }

    // Initial badge update
    setTimeout(updateDistanceTierBadges, 1000);
});
