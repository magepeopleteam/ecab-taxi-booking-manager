(function ($) {
    'use strict';

    $(function () {
        var $page = $('.mptbm-modern-global-settings');

        if (!$page.length) {
            return;
        }

        // Fade the "Settings saved" banner out on its own after a few
        // seconds, and drop settings-updated from the URL so a page refresh
        // doesn't bring it back.
        var $savedBanner = $('.mptbm-settings-saved-banner');
        if ($savedBanner.length) {
            setTimeout(function () {
                $savedBanner.fadeOut(400, function () {
                    $(this).remove();
                });
            }, 4000);

            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.delete('settings-updated');
                window.history.replaceState({}, '', url.toString());
            }
        }

        var $currentSection = $('.mptbm-global-settings-current-section');
        var $changeStatus = $('.mptbm-global-settings-change-status');

        function activeTabItem() {
            var $active = $page.find('.tabsContent > .tabsItem.active');
            return $active.length ? $active.first() : $page.find('.tabsContent > .tabsItem').first();
        }

        function activeNavItem() {
            var $active = $page.find('.tabLists [data-tabs-target].active');
            return $active.length ? $active.first() : $page.find('.tabLists [data-tabs-target]').first();
        }

        function updateSectionLabel() {
            var label = $.trim(activeNavItem().text());
            $currentSection.text(label);
        }

        function updateFieldCount(count) {
            if (!count && count !== 0) {
                $currentSection.removeAttr('data-count');
                return;
            }

            $currentSection.attr('data-count', String(count));
        }

        $page.find('.tabsContent > .tabsItem form').each(function () {
            var $form = $(this);
            var $heading = $form.children('h2').first();

            if ($heading.length) {
                $heading.wrap('<div class="mptbm-global-settings-section-header"></div>');
                $heading.after($('<p>').text(mptbmGlobalSettings.sectionDescription));
            }

            $form.children('.justifyBetween._mT').prepend(
                $('<span class="mptbm-settings-save-hint">').text(mptbmGlobalSettings.saveHint)
            );
        });

        // Map API Settings carries one field set per map provider. A field from the
        // wrong set is not merely clutter - a Google key does nothing in OpenStreetMap
        // mode, and nothing here applies with the map switched off - so a filled-in but
        // inert field reads as configured when it isn't. Each row is therefore shown
        // only for the modes it actually applies to, driven off "Pricing system based
        // on map" (display_map).
        var $displayMap = $page.find('select[name="mptbm_map_api_settings[display_map]"]');

        function settingRow(name) {
            return $page.find('[name="mptbm_map_api_settings[' + name + ']"]').closest('tr');
        }

        // field name -> the display_map values it belongs to.
        var MAP_MODE_FIELDS = {
            // Google's own credentials, plus the browser-vs-server fare choice that only
            // exists because Google refuses referrer-restricted keys server-side.
            gmap_api_key: ['enable'],
            gmap_server_api_key: ['enable'],
            fare_distance_source: ['enable'],
            // Routing applies to both live map providers: it measures the distance the
            // fare is built from, whichever map is drawn on screen.
            fallback_routing_provider: ['enable', 'openstreetmap'],
            tomtom_api_key: ['enable', 'openstreetmap'],
            use_shortest_route: ['enable', 'openstreetmap'],
            // Map presentation and geocoding defaults - pointless with no map at all.
            show_map_on_search_result: ['enable', 'openstreetmap'],
            mp_latitude: ['enable', 'openstreetmap'],
            mp_longitude: ['enable', 'openstreetmap'],
            mp_country: ['enable', 'openstreetmap'],
            mp_country_restriction: ['enable', 'openstreetmap']
        };

        var $routingProvider = $page.find('select[name="mptbm_map_api_settings[fallback_routing_provider]"]');

        function applyMapModeVisibility() {
            if (!$displayMap.length) {
                return;
            }
            var mode = $displayMap.val();
            Object.keys(MAP_MODE_FIELDS).forEach(function (name) {
                settingRow(name).toggle(MAP_MODE_FIELDS[name].indexOf(mode) !== -1);
            });
            // The TomTom key is only worth asking for once TomTom is the chosen routing
            // service. Nested inside the mode check above, so picking TomTom can never
            // surface the key in a mode where routing itself is hidden.
            if ($routingProvider.length && MAP_MODE_FIELDS.tomtom_api_key.indexOf(mode) !== -1) {
                settingRow('tomtom_api_key').toggle($routingProvider.val() === 'tomtom');
            }

            // The routing field means something different in each mode - the measurement
            // itself under OpenStreetMap, only a backup under Google - so its label and
            // description each ship both variants and we reveal the matching one. Without
            // this it reads as a second, competing distance setting next to the Google key.
            var $routingRow = settingRow('fallback_routing_provider');
            $routingRow.find('[data-routing-label], [data-routing-desc]').hide();
            $routingRow.find('[data-routing-label="' + mode + '"], [data-routing-desc="' + mode + '"]').show();

            updateFieldCount(activeTabItem().find('table.form-table > tbody > tr:visible').length);
        }

        applyMapModeVisibility();
        $page.on('change', 'select[name="mptbm_map_api_settings[display_map]"]', applyMapModeVisibility);
        $page.on('change', 'select[name="mptbm_map_api_settings[fallback_routing_provider]"]', applyMapModeVisibility);

        var $shortestRouteRow = settingRow('use_shortest_route');

        // The field's description has a "No" and a "Yes" variant baked into the markup
        // (see the 'desc' sprintf in Admin/MPTBM_Settings_Global.php) - show only the
        // one matching whatever's currently selected, so it always describes what
        // picking that option actually does instead of listing both permanently.
        var $shortestRouteSelect = $page.find('select[name="mptbm_map_api_settings[use_shortest_route]"]');

        function toggleShortestRouteDesc() {
            if (!$shortestRouteSelect.length) {
                return;
            }
            $shortestRouteRow.find('[data-shortest-route-desc]').hide();
            $shortestRouteRow.find('[data-shortest-route-desc="' + $shortestRouteSelect.val() + '"]').show();
        }

        toggleShortestRouteDesc();
        $page.on('change', 'select[name="mptbm_map_api_settings[use_shortest_route]"]', toggleShortestRouteDesc);

        updateSectionLabel();
        updateFieldCount(activeTabItem().find('table.form-table > tbody > tr:visible').length);

        $page.on('click', '.tabLists [data-tabs-target]', function () {
            window.setTimeout(function () {
                updateSectionLabel();
                updateFieldCount(activeTabItem().find('table.form-table > tbody > tr:visible').length);
            }, 380);
        });

        $page.on('change input', '.tabsContent form :input', function () {
            if ($(this).is('[type="submit"], [type="hidden"]')) {
                return;
            }

            $changeStatus
                .addClass('is-visible')
                .empty()
                .append('<i class="fas fa-circle" aria-hidden="true"></i>')
                .append(document.createTextNode(mptbmGlobalSettings.unsaved));
        });

        $page.on('submit', '.tabsContent form', function () {
            var $button = $(this).find('input[type="submit"]');
            $button.val(mptbmGlobalSettings.saving).prop('disabled', true);
            $changeStatus.removeClass('is-visible').empty();
        });

        // Badge text for rows flagged with 'class' => 'mptbm-setting-highlight'.
        // Written as a data attribute for the CSS ::after to pick up, rather than
        // hardcoded in the stylesheet, so the word stays translatable.
        $page.find('tr.mptbm-setting-highlight').attr('data-mptbm-badge', mptbmGlobalSettings.highlightBadge);
    });
})(jQuery);
