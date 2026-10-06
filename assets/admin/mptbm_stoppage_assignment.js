/* global jQuery, mptbmStoppageAssignment */
(function ($) {
	'use strict';

	// Mirrors the Customer Add-ons master-toggle handler in mptbm_taxi_add_edit.js
	// (bound there to the #mptbm_taxi_ex_service_master_toggle *id*, so it never
	// fires for this checkbox) - the shared ON/OFF label text swap already works
	// for free via that file's class-based handler on .mptbm_taxi_ex_service_toggle_wrapper.
	$(document).on('change', '#mptbm_taxi_stoppage_master_toggle', function () {
		var $body = $('#mptbm_taxi_stoppage_body');
		if ($(this).is(':checked')) {
			$body.removeClass('mptbm_disabled').fadeIn();
		} else {
			$body.addClass('mptbm_disabled').fadeOut();
		}
	});

	$(function () {
		var cfg = window.mptbmStoppageAssignment || {};
		var $picker = $('[data-stoppage-picker]');
		if (!$picker.length) {
			return;
		}

		var $grid = $picker.find('[data-stoppage-picker-grid]');
		var $loadMore = $picker.find('[data-stoppage-load-more]');
		var $assignedField = $('[data-stoppage-assigned-field]');

		function currentAssigned() {
			var ids = [];
			$grid.find('.mptbm-stoppage-pick-card.is-assigned').each(function () {
				ids.push(parseInt($(this).data('id'), 10));
			});
			return ids;
		}

		function syncField() {
			$assignedField.val(JSON.stringify(currentAssigned()));
		}

		function loadPage() {
			var offset = parseInt($picker.data('offset'), 10) || 0;
			var assigned = JSON.parse($picker.attr('data-assigned') || '[]');

			$loadMore.prop('disabled', true).text(cfg.loadingLabel || 'Loading…');

			$.post(cfg.ajaxUrl, {
				action: cfg.action,
				nonce: cfg.nonce,
				offset: offset,
				assigned: JSON.stringify(assigned)
			}).done(function (resp) {
				if (resp && resp.success) {
					$grid.append(resp.data.html);
					$picker.data('offset', offset + parseInt($picker.data('per-page'), 10));
					$loadMore.toggle(!!resp.data.hasMore);
				} else {
					window.alert((resp && resp.data && resp.data.message) || cfg.loadError);
				}
			}).fail(function () {
				window.alert(cfg.loadError);
			}).always(function () {
				$loadMore.prop('disabled', false).text(cfg.loadMoreLabel || 'Load more');
			});
		}

		$(document).on('click', '[data-stoppage-pick-card]', function () {
			$(this).toggleClass('is-assigned');
			syncField();
		});

		// The card is a div with role="button" now (not a real <button>), so
		// Enter/Space activation has to be wired up by hand - a real button
		// gets that from the browser for free.
		$(document).on('keydown', '[data-stoppage-pick-card]', function (e) {
			if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
				e.preventDefault();
				$(this).toggleClass('is-assigned');
				syncField();
			}
		});

		$loadMore.on('click', function () {
			loadPage();
		});

		// The first page is already rendered server-side (see
		// MPTBM_Stoppage_Assignment::render()) - AJAX only fires from here on,
		// once "Load more" is clicked, starting at data-offset (= PER_PAGE).
	});
})(jQuery);
