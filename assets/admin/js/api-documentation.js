jQuery(document).ready(function($) {
    
    // Load existing API keys on page load
    loadApiKeys();
    
    // Handle API key generation form
    $('#generate-api-key-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const originalText = $submitBtn.text();
        
        // Validate form inputs
        const name = $('#api-key-name').val().trim();
        if (!name) {
            alert('Please enter a name for the API key');
            return;
        }
        
        if (name.length > 200) {
            alert('API key name is too long (maximum 200 characters)');
            return;
        }
        
        // Check for potentially harmful characters
        if (/<script|javascript:|data:|vbscript:|onload|onerror/i.test(name)) {
            alert('Invalid characters in API key name');
            return;
        }
        
        // Get form data
        const formData = {
            action: 'mptbm_generate_api_key',
            nonce: mptbm_api.nonce,
            name: name,
            permissions: []
        };
        
        // Get selected permissions
        $form.find('input[name="permissions[]"]').each(function() {
            if ($(this).is(':checked')) {
                formData.permissions.push($(this).val());
            }
        });
        
        // Show loading state
        $submitBtn.text('Generating...').prop('disabled', true);
        
        // Send AJAX request
        $.post(mptbm_api.ajax_url, formData, function(response) {
            if (response.success) {
                alert('API key generated successfully!');
                $form[0].reset();
                $form.find('input[name="permissions[]"]').prop('checked', true);
                loadApiKeys(); // Refresh the list
            } else {
                alert('Error: ' + response.data);
            }
        }).fail(function() {
            alert('Network error occurred. Please try again.');
        }).always(function() {
            $submitBtn.text(originalText).prop('disabled', false);
        });
    });
    
    // Handle API key revocation
    $(document).on('click', '.revoke-key', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to revoke this API key?')) {
            return;
        }
        
        const $btn = $(this);
        const apiKey = $btn.data('api-key');
        const originalText = $btn.text();
        
        // Validate API key format
        if (!apiKey || !/^etbm_[a-zA-Z0-9]{32}$/.test(apiKey)) {
            alert('Invalid API key format');
            return;
        }
        
        $btn.text('Revoking...').prop('disabled', true);
        
        $.post(mptbm_api.ajax_url, {
            action: 'mptbm_revoke_api_key',
            nonce: mptbm_api.nonce,
            api_key: apiKey
        }, function(response) {
            if (response.success) {
                alert('API key revoked successfully!');
                loadApiKeys(); // Refresh the list
            } else {
                alert('Error: ' + response.data);
            }
        }).fail(function() {
            alert('Network error occurred. Please try again.');
        }).always(function() {
            $btn.text(originalText).prop('disabled', false);
        });
    });
    
    function loadApiKeys() {
        const $container = $('#api-keys-container');
        
        $container.html('<div>Loading API keys...</div>');
        
        $.post(mptbm_api.ajax_url, {
            action: 'mptbm_get_api_keys',
            nonce: mptbm_api.nonce
        }, function(response) {
            if (response.success) {
                displayApiKeys(response.data);
            } else {
                $container.html('<div>Error loading API keys: ' + response.data + '</div>');
            }
        }).fail(function() {
            $container.html('<div>Network error occurred while loading API keys.</div>');
        });
    }
    
    function displayApiKeys(keys) {
        const $container = $('#api-keys-container');
        
        if (keys.length === 0) {
            $container.html('<div>No API keys found. Generate one above.</div>');
            return;
        }
        
        let html = '';
        
        keys.forEach(function(key) {
            const permissions = JSON.parse(key.permissions || '[]');
            const statusClass = key.status === 'active' ? 'active' : 'revoked';
            const createdDate = new Date(key.created_at).toLocaleString();
            const lastUsed = key.last_used ? new Date(key.last_used).toLocaleString() : 'Never';
            
            html += `
                <div class="api-key-item">
                    <div class="api-key-header">
                        <span class="api-key-name">${escapeHtml(key.name)}</span>
                        <span class="api-key-status ${escapeHtml(statusClass)}">${escapeHtml(key.status)}</span>
                    </div>
                    <div class="api-key-details">
                        <div><strong>API Key:</strong> <code>${escapeHtml(key.api_key)}</code></div>
                        <div><strong>Permissions:</strong> ${permissions.map(p => escapeHtml(p)).join(', ')}</div>
                        <div><strong>Created:</strong> ${escapeHtml(createdDate)}</div>
                        <div><strong>Last Used:</strong> ${escapeHtml(lastUsed)}</div>
                    </div>
                    ${key.status === 'active' ? `
                        <div class="api-key-actions">
                            <button class="revoke-key" data-api-key="${escapeHtml(key.api_key)}">Revoke</button>
                        </div>
                    ` : ''}
                </div>
            `;
        });
        
        $container.html(html);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});


jQuery(document).ready(function($) {
    // Initialize all endpoint sections as collapsed
    $('.endpoint-section .endpoint-details').hide();
    
    // Handle click on endpoint section headers
    $('.endpoint-section h3').on('click', function() {
        var $section = $(this).closest('.endpoint-section');
        var $details = $section.find('.endpoint-details');
        
        // Toggle the active class
        $section.toggleClass('active');
        
        // Slide toggle the content
        $details.slideToggle(300);
    });

    // Show the first endpoint section by default
    $('.endpoint-section:first').addClass('active').find('.endpoint-details').show();

    // Add copy buttons to all code blocks
    $('code, pre').each(function() {
        var $code = $(this);
        var $copyButton = $('<button/>')
            .addClass('copy-button')
            .text('Copy')
            .css({
                position: 'absolute',
                right: '10px',
                top: '10px',
                padding: '4px 8px',
                background: '#f0f0f1',
                border: '1px solid #c3c4c7',
                borderRadius: '3px',
                cursor: 'pointer',
                fontSize: '12px'
            });

        // Add relative positioning to the code block's parent
        $code.parent().css('position', 'relative');
        
        // Add the copy button
        $code.parent().append($copyButton);

        // Handle copy button click
        $copyButton.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Get the text to copy
            var textToCopy = $code.text();

            // Create a temporary textarea element
            var $temp = $('<textarea>')
                .val(textToCopy)
                .appendTo('body')
                .select();

            try {
                // Copy the text
                document.execCommand('copy');
                
                // Update button text temporarily
                var $button = $(this);
                $button.text('Copied!');
                $button.css('background', '#00a32a').css('color', 'white');
                
                // Reset button text after 2 seconds
                setTimeout(function() {
                    $button.text('Copy');
                    $button.css('background', '#f0f0f1').css('color', 'initial');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy text:', err);
            }

            // Remove the temporary textarea
            $temp.remove();
        });
    });

    // Add HTTP method badges
    $('.endpoint-details').each(function() {
        var $endpoint = $(this);
        var endpointText = $endpoint.find('p:contains("Endpoint:")').text();
        
        // Extract HTTP method from the endpoint text
        var method = endpointText.match(/(GET|POST|PUT|DELETE)/);
        
        if (method) {
            var methodClass = method[1].toLowerCase();
            var $badge = $('<span/>')
                .addClass('http-method ' + methodClass)
                .text(method[1]);
            
            $endpoint.find('p:contains("Endpoint:")').prepend($badge);
        }
    });

    // Add status code badges
    $('.example-response').each(function() {
        var $response = $(this);
        var responseText = $response.text();
        
        // Look for common status codes
        var statusCodes = responseText.match(/(200|201|400|401|404)/);
        
        if (statusCodes) {
            var $badge = $('<span/>')
                .addClass('status-code status-' + statusCodes[1])
                .text(statusCodes[1]);
            
            $response.find('h4').append($badge);
        }
    });

    // Add required field indicators
    $('.parameters tbody tr').each(function() {
        var $row = $(this);
        var isRequired = $row.find('td:eq(2)').text().toLowerCase() === 'yes';
        
        if (isRequired) {
            $row.find('td:first').append(
                $('<span/>')
                    .addClass('required')
                    .text(' *')
                    .attr('title', 'Required field')
            );
        }
    });

    // Add smooth scroll to anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this.hash);
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 50
            }, 500);
        }
    });

    // Add search functionality
    var $searchInput = $('<input/>')
        .attr({
            type: 'text',
            placeholder: 'Search endpoints...',
            id: 'endpoint-search'
        })
        .css({
            width: '100%',
            padding: '8px',
            marginBottom: '20px',
            border: '1px solid #ddd',
            borderRadius: '4px'
        });

    $('.mptbm-api-endpoints').prepend($searchInput);

    $('#endpoint-search').on('input', function() {
        var searchText = $(this).val().toLowerCase();
        
        $('.endpoint-section').each(function() {
            var $section = $(this);
            var sectionText = $section.text().toLowerCase();
            
            if (sectionText.includes(searchText)) {
                $section.show();
            } else {
                $section.hide();
            }
        });
    });
});
