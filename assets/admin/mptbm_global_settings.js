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

        // "Use Shortest Distance Route" only makes sense with Google Maps active -
        // OSRM's public routing endpoint rarely returns real alternatives to choose a
        // shortest one from (see MPTBM_Function::get_server_distance()), so the field
        // would just be confusing/inert there. Hide its row unless "Pricing system
        // based on map" (display_map) is set to Google map.
        var $displayMap = $page.find('select[name="mptbm_map_api_settings[display_map]"]');
        var $shortestRouteRow = $page.find('select[name="mptbm_map_api_settings[use_shortest_route]"]').closest('tr');

        function toggleShortestRouteField() {
            if (!$displayMap.length || !$shortestRouteRow.length) {
                return;
            }
            $shortestRouteRow.toggle($displayMap.val() === 'enable');
            updateFieldCount(activeTabItem().find('table.form-table > tbody > tr:visible').length);
        }

        toggleShortestRouteField();
        $page.on('change', 'select[name="mptbm_map_api_settings[display_map]"]', toggleShortestRouteField);

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
    });
})(jQuery);
