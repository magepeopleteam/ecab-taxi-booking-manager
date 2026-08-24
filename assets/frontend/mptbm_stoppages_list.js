jQuery(function ($) {
	'use strict';

	$(document).on('click', '.mptbm-stops-view-btn', function () {
		var $btn = $(this);
		var $wrap = $btn.closest('.mptbm-stops');
		var view = $btn.data('view-btn');

		$wrap.attr('data-view', view);
		$wrap.find('.mptbm-stops-view-btn').removeClass('is-active').attr('aria-pressed', 'false');
		$btn.addClass('is-active').attr('aria-pressed', 'true');
	});

	var $lightbox = $('.mptbm-stop-lightbox');
	var lightboxSrcs = [];
	var lightboxIndex = 0;

	function openLightbox(srcs, index) {
		lightboxSrcs = srcs;
		lightboxIndex = index;
		$lightbox.find('[data-lightbox-image]').attr('src', lightboxSrcs[lightboxIndex]);
		$lightbox.addClass('is-open').removeAttr('hidden');
		$('body').addClass('mptbm-stop-lightbox-locked');
	}

	function closeLightbox() {
		$lightbox.removeClass('is-open').attr('hidden', 'hidden');
		$('body').removeClass('mptbm-stop-lightbox-locked');
	}

	function stepLightbox(delta) {
		if (!lightboxSrcs.length) {
			return;
		}
		lightboxIndex = (lightboxIndex + delta + lightboxSrcs.length) % lightboxSrcs.length;
		$lightbox.find('[data-lightbox-image]').attr('src', lightboxSrcs[lightboxIndex]);
	}

	$(document).on('click', '[data-lightbox-src]', function () {
		var $gallery = $(this).closest('[data-lightbox-gallery]');
		var $items = $gallery.find('[data-lightbox-src]');
		var srcs = $items.map(function () { return $(this).data('lightbox-src'); }).get();

		openLightbox(srcs, $items.index(this));
	});

	$(document).on('click', '[data-lightbox-close]', closeLightbox);
	$(document).on('click', '[data-lightbox-prev]', function () { stepLightbox(-1); });
	$(document).on('click', '[data-lightbox-next]', function () { stepLightbox(1); });

	$(document).on('click', '[data-lightbox]', function (e) {
		if (e.target === this) {
			closeLightbox();
		}
	});

	$(document).on('keydown', function (e) {
		if (!$lightbox.hasClass('is-open')) {
			return;
		}
		if (e.key === 'Escape') { closeLightbox(); }
		if (e.key === 'ArrowLeft') { stepLightbox(-1); }
		if (e.key === 'ArrowRight') { stepLightbox(1); }
	});

	$(document).on('click', '[data-load-more]', function () {
		if (typeof mptbmStoppagesList === 'undefined') {
			return;
		}

		var $btn = $(this);
		var $wrap = $btn.closest('.mptbm-stops');
		var $grid = $wrap.find('[data-stops-grid]');
		var loaded = parseInt($wrap.attr('data-loaded'), 10) || 0;
		var perPage = parseInt($wrap.attr('data-per-page'), 10) || 8;

		$btn.prop('disabled', true).addClass('is-loading');

		$.post(mptbmStoppagesList.ajaxUrl, {
			action: mptbmStoppagesList.action,
			nonce: $btn.data('nonce'),
			offset: loaded,
			per_page: perPage
		}).done(function (response) {
			if (response && response.success && response.data) {
				$grid.append(response.data.html);
				$wrap.attr('data-loaded', response.data.loaded);
				$wrap.attr('data-total', response.data.total);

				if (!response.data.has_more) {
					$btn.closest('.mptbm-stops-load-more-wrap').remove();
				}
			}
		}).always(function () {
			$btn.prop('disabled', false).removeClass('is-loading');
		});
	});
});
