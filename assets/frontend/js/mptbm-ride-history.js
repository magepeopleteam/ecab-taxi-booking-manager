/**
 * Ride History JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Handle rebook button click
        $('.mptbm-rebook-button').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const orderId = $button.data('order-id');
            const itemId = $button.data('item-id');
            
            // Prevent multiple clicks
            if ($button.hasClass('loading')) {
                return;
            }
            
            // Add loading state
            $button.addClass('loading');
            $button.text(mptbm_ride_history.loading_text);
            
            // Send AJAX request
            $.ajax({
                url: mptbm_ride_history.ajax_url,
                type: 'POST',
                data: {
                    action: 'mptbm_rebook_ride',
                    order_id: orderId,
                    item_id: itemId,
                    nonce: mptbm_ride_history.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        $button.text(mptbm_ride_history.success_text);
                        
                        // Redirect to cart
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 1000);
                    } else {
                        // Show error message
                        $button.removeClass('loading');
                        $button.text(mptbm_ride_history.error_text);
                        
                        // Reset button after delay
                        setTimeout(function() {
                            $button.text(mptbm_ride_history.rebook_text);
                        }, 2000);
                        
                        console.error('Error:', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message
                    $button.removeClass('loading');
                    $button.text(mptbm_ride_history.error_text);
                    
                    // Reset button after delay
                    setTimeout(function() {
                        $button.text(mptbm_ride_history.rebook_text);
                    }, 2000);
                    
                    console.error('AJAX Error:', error);
                }
            });
        });
    });
    
})(jQuery);