/* global jQuery, mptbm_price_calculation */
(function ($) {
	'use strict';

	function searchArea($el) {
		return $el.closest('.mptbm_transport_search_area');
	}

	function refreshTriggerCount($panel) {
		var n = $panel.find('.mptbm_stoppage_item').filter(function () {
			return $(this).find('[name="mptbm_stoppage_id[]"]').val() !== '';
		}).length;
		var $count = $panel.find('[data-stoppage-count]');
		if (n > 0) {
			$count.text(n).show();
		} else {
			$count.text('').hide();
		}
	}

	function setSelected($item, selected) {
		var $input = $item.find('[name="mptbm_stoppage_id[]"]');
		var $btn = $item.find('[data-stoppage-toggle]');
		var $icon = $item.find('[data-stoppage-icon]');

		$item.toggleClass('is-selected', selected);
		$input.val(selected ? $input.data('id') : '');
		$icon.toggleClass('fa-plus', !selected).toggleClass('fa-check', selected);
		$btn.attr('title', selected ? mptbm_stoppages_i18n.remove : mptbm_stoppages_i18n.add);
	}

	function toggleItem($item) {
		var isSelected = $item.hasClass('is-selected');
		setSelected($item, !isSelected);

		var $panel = $item.closest('.mptbm_stoppage_panel');
		refreshTriggerCount($panel);

		var $parent = searchArea($panel);
		if ($parent.length && typeof mptbm_price_calculation === 'function') {
			mptbm_price_calculation($parent);
		}
	}

	$(document).on('click', '[data-stoppage-trigger]', function () {
		$(this).closest('.mptbm_stoppage_panel').find('[data-stoppage-popup]').attr('aria-hidden', 'false').addClass('is-open');
		$('body').addClass('mptbm-stoppage-popup-open');
	});

	$(document).on('click', '[data-stoppage-close]', function () {
		$(this).closest('.mptbm_stoppage_popup').attr('aria-hidden', 'true').removeClass('is-open');
		$('body').removeClass('mptbm-stoppage-popup-open');
	});

	// Detail popup has its own close trigger (backdrop + X button) - separate
	// from the main popup's, so it needs its own handler.
	$(document).on('click', '[data-stoppage-detail-close]', function () {
		$(this).closest('.mptbm_stoppage_detail_popup').attr('aria-hidden', 'true').removeClass('is-open');
	});

	$(document).on('click', '[data-stoppage-toggle]', function (e) {
		e.preventDefault();
		toggleItem($(this).closest('.mptbm_stoppage_item'));
	});

	// Image/name area opens the shared detail view instead of toggling directly.
	$(document).on('click', '[data-stoppage-details-trigger]', function () {
		var $item = $(this).closest('.mptbm_stoppage_item');
		var $panel = $item.closest('.mptbm_stoppage_panel');
		var $detail = $panel.find('[data-stoppage-detail-popup]');

		var image = $item.data('image');
		$detail.find('[data-stoppage-detail-media]').css({
			'background-image': image ? 'url(' + image + ')' : 'none',
			'background-size': 'cover',
			'background-position': 'center center',
			'background-repeat': 'no-repeat'
		});
		$detail.find('[data-stoppage-detail-name]').text($item.data('name'));

		var badge = $item.data('badge');
		var $badge = $detail.find('[data-stoppage-detail-badge]');
		if (badge && mptbm_stoppages_i18n.badges && mptbm_stoppages_i18n.badges[badge]) {
			$badge.removeAttr('hidden').attr('class', 'mptbm_stoppage_badge is-' + badge);
			$badge.find('[data-stoppage-detail-badge-text]').text(mptbm_stoppages_i18n.badges[badge]);
		} else {
			$badge.attr('hidden', true);
		}

		var duration = $item.data('duration');
		if (duration) {
			$detail.find('[data-stoppage-detail-duration]').text(duration);
			$detail.find('[data-stoppage-detail-duration-pill]').removeAttr('hidden');
		} else {
			$detail.find('[data-stoppage-detail-duration-pill]').attr('hidden', true);
		}

		// mp_price_format() returns an HTML string (currency symbol as an
		// entity/markup, e.g. "250.00&#2547;") - .html() renders it, .text()
		// would print the raw entity code as literal text.
		var price = parseFloat($item.data('price')) || 0;
		$detail.find('[data-stoppage-detail-price]').html(price > 0 ? mp_price_format(price) : mptbm_stoppages_i18n.free);

		// Description can now contain rich HTML (admin uses wp_editor, sanitized
		// server-side with wp_kses_post) - render it, don't print it as text.
		$detail.find('[data-stoppage-detail-desc]').html($item.data('description') || '');

		var $addBtn = $detail.find('[data-stoppage-detail-add]').data('target-id', $item.data('id'));
		setDetailAddButton($addBtn, $item.hasClass('is-selected'));

		$detail.attr('aria-hidden', 'false').addClass('is-open');
	});

	function setDetailAddButton($btn, isSelected) {
		$btn.find('[data-stoppage-detail-add-text]').text(isSelected ? mptbm_stoppages_i18n.remove : mptbm_stoppages_i18n.add);
		$btn.find('[data-stoppage-detail-add-icon]').toggleClass('fa-plus', !isSelected).toggleClass('fa-check', isSelected);
		$btn.toggleClass('is-selected', isSelected);
	}

	$(document).on('click', '[data-stoppage-detail-add]', function () {
		var $detail = $(this).closest('[data-stoppage-detail-popup]');
		var targetId = $(this).data('target-id');
		var $item = $detail.closest('.mptbm_stoppage_panel').find('.mptbm_stoppage_item').filter(function () {
			return String($(this).data('id')) === String(targetId);
		});
		if (!$item.length) {
			return;
		}
		toggleItem($item);
		setDetailAddButton($(this), $item.hasClass('is-selected'));
	});

	$(document).on('keydown', function (e) {
		if (e.key !== 'Escape') {
			return;
		}
		$('.mptbm_stoppage_detail_popup.is-open, .mptbm_stoppage_popup.is-open').each(function () {
			$(this).attr('aria-hidden', 'true').removeClass('is-open');
		});
	});
})(jQuery);
