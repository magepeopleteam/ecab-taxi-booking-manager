(function ($) {
    'use strict';

    $(function () {
        var $page = $('.mptbm-modern-global-settings');

        if (!$page.length) {
            return;
        }

        var $search = $('#mptbm-global-settings-search');
        var $clear = $('.mptbm-global-settings-search-clear');
        var $currentSection = $('.mptbm-global-settings-current-section');
        var $changeStatus = $('.mptbm-global-settings-change-status');
        var $emptyState = $('.mptbm-global-settings-empty');

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

        function filterActiveSection() {
            var query = $.trim($search.val()).toLowerCase();
            var $tab = activeTabItem();
            var $rows = $tab.find('table.form-table > tbody > tr');
            var visibleCount = 0;

            $rows.each(function () {
                var $row = $(this);
                var matches = !query || $row.text().toLowerCase().indexOf(query) !== -1;
                $row.toggleClass('mptbm-setting-filtered-out', !matches);

                if (matches) {
                    visibleCount += 1;
                }
            });

            $clear.toggleClass('is-visible', query.length > 0);
            $emptyState.toggleClass('is-visible', query.length > 0 && visibleCount === 0);
            $page.toggleClass('has-empty-search', query.length > 0 && visibleCount === 0);
            updateFieldCount(visibleCount);
        }

        function resetSearch() {
            $search.val('');
            $clear.removeClass('is-visible');
            $page.find('.mptbm-setting-filtered-out').removeClass('mptbm-setting-filtered-out');
            $emptyState.removeClass('is-visible');
            $page.removeClass('has-empty-search');
            updateFieldCount(activeTabItem().find('table.form-table > tbody > tr').length);
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

        updateSectionLabel();
        updateFieldCount(activeTabItem().find('table.form-table > tbody > tr').length);

        $search.on('input', filterActiveSection);

        $clear.on('click', function () {
            resetSearch();
            $search.trigger('focus');
        });

        $page.on('click', '.tabLists [data-tabs-target]', function () {
            window.setTimeout(function () {
                resetSearch();
                updateSectionLabel();
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
