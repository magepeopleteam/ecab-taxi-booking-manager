jQuery(document).ready(function($) {
    // Check if we're on the API settings page
    if (!$('#mptbm-api-keys-wrapper').length) {
        return;
    }
    
    console.log('MPTBM API Manager initialized');
    
    // Load API keys on page load
    loadApiKeys();
    
    // Show the key generation form with animation
    $('#mptbm-generate-key-btn').on('click', function(e) {
        e.preventDefault();
        $('#mptbm-key-form').slideDown(300);
        $('#mptbm-api-key-description').focus();
    });
    
    // Hide the key generation form
    $('#mptbm-cancel-api-key').on('click', function(e) {
        e.preventDefault();
        $('#mptbm-key-form').slideUp(200);
        $('#mptbm-api-key-description').val('');
    });
    
    // Handle Enter key in description field
    $('#mptbm-api-key-description').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#mptbm-generate-api-key').click();
        }
    });
    
    // Generate API key
    $('#mptbm-generate-api-key').on('click', function(e) {
        e.preventDefault();
        var description = $('#mptbm-api-key-description').val().trim();
        
        if (!description) {
            showNotice('error', 'Please enter a description for this API key');
            $('#mptbm-api-key-description').focus();
            return;
        }
        
        $(this).addClass('updating-message').prop('disabled', true);
        $('#mptbm-cancel-api-key').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mptbm_generate_api_key',
                nonce: mptbm_api_data.nonce,
                description: description
            },
            success: function(response) {
                if (response.success) {
                    $('#mptbm-api-keys-table').html(response.data.html);
                    $('#mptbm-key-form').slideUp(200);
                    $('#mptbm-api-key-description').val('');
                    showNotice('success', 'API key generated successfully');
                } else {
                    showNotice('error', response.data.message || 'Error generating API key');
                }
            },
            error: function(xhr, status, error) {
                showNotice('error', 'Server error: ' + error);
            },
            complete: function() {
                $('#mptbm-generate-api-key').removeClass('updating-message').prop('disabled', false);
                $('#mptbm-cancel-api-key').prop('disabled', false);
            }
        });
    });
    
    // Set up event delegation for dynamic elements
    $(document).on('click', '.mptbm-copy-key', function(e) {
        e.preventDefault();
        var key = $(this).data('key');
        copyToClipboard(key, this);
    });
    
    $(document).on('click', '.mptbm-delete-key', function(e) {
        e.preventDefault();
        var keyId = $(this).data('key-id');
        var description = $(this).closest('tr').find('td:first-child').text();
        
        if (confirm('Are you sure you want to delete the API key for "' + description + '"?\nThis action cannot be undone.')) {
            var $button = $(this);
            $button.addClass('updating-message').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mptbm_delete_api_key',
                    nonce: mptbm_api_data.nonce,
                    key_id: keyId
                },
                success: function(response) {
                    if (response.success) {
                        $('#mptbm-api-keys-table').html(response.data.html);
                        showNotice('success', 'API key deleted successfully');
                    } else {
                        showNotice('error', response.data.message || 'Error deleting API key');
                        $button.removeClass('updating-message').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'Server error: ' + error);
                    $button.removeClass('updating-message').prop('disabled', false);
                }
            });
        }
    });
    
    // Function to load API keys
    function loadApiKeys() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mptbm_get_api_keys',
                nonce: mptbm_api_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#mptbm-api-keys-table').html(response.data.html);
                } else {
                    console.error('Error loading API keys:', response.data?.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Server error loading API keys:', error);
            }
        });
    }
    
    // Modern copy to clipboard function
    function copyToClipboard(text, element) {
        // Use modern clipboard API if available
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                showCopiedFeedback(element);
            }).catch(function(err) {
                // Fallback for older browsers
                fallbackCopyToClipboard(text, element);
            });
        } else {
            // Fallback for older browsers or non-secure contexts
            fallbackCopyToClipboard(text, element);
        }
    }
    
    // Fallback copy method for older browsers
    function fallbackCopyToClipboard(text, element) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        
        // Make the textarea out of viewport
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            var successful = document.execCommand('copy');
            if (successful) {
                showCopiedFeedback(element);
            } else {
                console.error('Failed to copy text');
            }
        } catch (err) {
            console.error('Error copying text: ', err);
        }
        
        document.body.removeChild(textArea);
    }
    
    // Show feedback when text is copied
    function showCopiedFeedback(element) {
        var $element = $(element);
        
        // Add a class for the animation
        $element.addClass('mptbm-copy-success');
        
        // Store original title
        var originalTitle = $element.attr('title');
        $element.attr('title', 'Copied!');
        
        // Remove the class and restore title after animation completes
        setTimeout(function() {
            $element.removeClass('mptbm-copy-success');
            $element.attr('title', originalTitle);
        }, 1500);
    }
    
    // Show admin notice
    function showNotice(type, message) {
        // Remove any existing notices
        $('.mptbm-api-notice').remove();
        
        // Create notice element
        var $notice = $('<div class="notice is-dismissible mptbm-api-notice notice-' + type + '"><p>' + message + '</p></div>');
        
        // Add close button
        var $button = $('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
        $button.on('click', function() {
            $notice.fadeOut(300, function() { $(this).remove(); });
        });
        
        $notice.append($button);
        
        // Insert notice before the API keys wrapper
        $('#mptbm-api-keys-wrapper').before($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    }
}); 