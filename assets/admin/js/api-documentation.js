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

        $container.html('<div class="mptbm-apidocs-keys-loading">Loading API keys...</div>');

        $.post(mptbm_api.ajax_url, {
            action: 'mptbm_get_api_keys',
            nonce: mptbm_api.nonce
        }, function(response) {
            if (response.success) {
                displayApiKeys(response.data);
            } else {
                $container.html('<div class="mptbm-apidocs-keys-loading">Error loading API keys: ' + response.data + '</div>');
            }
        }).fail(function() {
            $container.html('<div class="mptbm-apidocs-keys-loading">Network error occurred while loading API keys.</div>');
        });
    }

    function displayApiKeys(keys) {
        const $container = $('#api-keys-container');

        if (keys.length === 0) {
            $container.html('<div class="mptbm-apidocs-keys-loading">No API keys found. Generate one above.</div>');
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
    'use strict';

    // ---- Copy to clipboard (base URL chip + per-endpoint code blocks) ----
    function copyText(text, $button) {
        function onCopied() {
            $button.addClass('is-copied');
            const $icon = $button.find('i');
            $icon.removeClass('fa-copy').addClass('fa-check');
            window.setTimeout(function () {
                $button.removeClass('is-copied');
                $icon.removeClass('fa-check').addClass('fa-copy');
            }, 1600);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(onCopied).catch(function () {
                fallbackCopy(text, onCopied);
            });
        } else {
            fallbackCopy(text, onCopied);
        }
    }

    function fallbackCopy(text, onCopied) {
        const $temp = $('<textarea readonly></textarea>').val(text).css({ position: 'fixed', top: '-9999px' }).appendTo('body');
        $temp[0].select();
        try {
            document.execCommand('copy');
            onCopied();
        } catch (err) {
            // Nothing we can do without clipboard access; leave the button as-is.
        }
        $temp.remove();
    }

    $(document).on('click', '.mptbm-apidocs-copy', function (e) {
        e.preventDefault();
        const targetId = $(this).data('copy-target');
        const text = targetId ? $('#' + targetId).text().trim() : '';
        if (text) {
            copyText(text, $(this));
        }
    });

    $(document).on('click', '.mptbm-apidocs-copy-code', function (e) {
        e.preventDefault();
        const $button = $(this);
        const text = $button.siblings('pre').text();
        if (text) {
            copyText(text, $button);
        }
    });

    // ---- Search / filter endpoint cards ----
    $('#mptbm-apidocs-search-input').on('input', function () {
        const query = $.trim($(this).val()).toLowerCase();

        $('[data-apidocs-endpoints]').each(function () {
            const $group = $(this);
            let visibleCount = 0;

            $group.find('[data-apidocs-endpoint]').each(function () {
                const $card = $(this);
                const matches = query === '' || $card.text().toLowerCase().indexOf(query) !== -1;
                $card.toggleClass('is-hidden', !matches);
                if (matches) {
                    visibleCount++;
                }
            });

            $group.closest('.mptbm-apidocs-section').toggle(visibleCount > 0);
        });
    });

    // ---- Sidebar nav: smooth scroll + active-section highlighting ----
    const $navLinks = $('.mptbm-apidocs-nav-link');

    $navLinks.on('click', function (e) {
        const target = document.getElementById($(this).attr('href').slice(1));
        if (target) {
            e.preventDefault();
            $('html, body').animate({ scrollTop: $(target).offset().top - 32 }, 400);
        }
    });

    if ('IntersectionObserver' in window) {
        const sections = document.querySelectorAll('.mptbm-apidocs-section[id]');
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    $navLinks.removeClass('is-active');
                    $navLinks.filter('[href="#' + entry.target.id + '"]').addClass('is-active');
                }
            });
        }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });

        sections.forEach(function (section) {
            observer.observe(section);
        });
    }
});
