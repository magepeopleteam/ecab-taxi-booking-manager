/* global jQuery, mptbm_taxi_list_i18n */
(function ($) {
	'use strict';

	$(document).on('click', '[data-taxilist-view]', function () {
		var $btn = $(this);
		var view = $btn.data('taxilist-view');
		var $root = $btn.closest('.mptbm_taxilist');

		$root.attr('data-view', view);
		$root.find('[data-taxilist-view]').removeClass('is-active');
		$btn.addClass('is-active');

		try {
			localStorage.setItem('mptbm_taxilist_view', view);
		} catch (e) {
			// Private browsing / storage disabled - the toggle still works,
			// it just won't be remembered next visit.
		}
	});

	$(document).on('click', '[data-taxilist-load-more]', function () {
		var $btn = $(this);
		if ($btn.prop('disabled')) {
			return;
		}
		var $root = $btn.closest('.mptbm_taxilist');
		var $grid = $root.find('[data-taxilist-grid]');
		var offset = parseInt($root.attr('data-offset'), 10) || 0;
		var perPage = parseInt($root.attr('data-per-page'), 10) || 20;

		$btn.prop('disabled', true);
		$btn.find('[data-taxilist-spinner]').removeAttr('hidden');

		$.post(mptbm_taxi_list_i18n.ajax_url, {
			action: 'mptbm_taxi_list_load_more',
			nonce: mptbm_taxi_list_i18n.nonce,
			offset: offset,
			per_page: perPage
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				return;
			}
			$grid.append(response.data.html);

			var shown = parseInt(response.data.shown, 10) || 0;
			var total = parseInt(response.data.total, 10) || 0;
			$root.attr('data-offset', shown);

			if (shown >= total) {
				$root.find('[data-taxilist-footer]').remove();
			}
		}).always(function () {
			$btn.prop('disabled', false);
			$btn.find('[data-taxilist-spinner]').attr('hidden', true);
		});
	});

	// Restore the viewer's last-chosen view (grid/list), per browser - a
	// convenience only; without it the shortcode's own default still renders.
	$(function () {
		var saved;
		try {
			saved = localStorage.getItem('mptbm_taxilist_view');
		} catch (e) {
			saved = null;
		}
		if (saved !== 'grid' && saved !== 'list') {
			return;
		}
		$('.mptbm_taxilist').each(function () {
			var $root = $(this);
			$root.attr('data-view', saved);
			$root.find('[data-taxilist-view]').removeClass('is-active');
			$root.find('[data-taxilist-view="' + saved + '"]').addClass('is-active');
		});
	});
})(jQuery);
