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

        updateSectionLabel();
        updateFieldCount(activeTabItem().find('table.form-table > tbody > tr').length);

        $page.on('click', '.tabLists [data-tabs-target]', function () {
            window.setTimeout(function () {
                updateSectionLabel();
                updateFieldCount(activeTabItem().find('table.form-table > tbody > tr').length);
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
