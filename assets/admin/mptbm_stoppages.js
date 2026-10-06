/* global jQuery, wp, tinymce, mptbmStoppages */
(function ($) {
	'use strict';

	$(function () {
		var cfg = window.mptbmStoppages || {};
		var $modal = $('[data-stoppage-modal]');
		var $form = $('[data-stoppage-form]');
		var $grid = $('[data-stoppages-grid]');
		var $empty = $('[data-stoppages-empty]');
		var $count = $('[data-stoppages-count]');
		var editorId = cfg.descriptionEditorId || 'mptbm_stoppage_description';
		var mediaFrame = null;
		var galleryFrame = null;
		var galleryItems = [];
		var mode = 'add';

		function setCount(n) {
			$count.text(n);
			$empty.toggleClass('is-hidden', n > 0);
		}

		// TinyMCE only exists once `wp_editor()`'s scripts have run - guard every
		// call, and skip straight to the plain textarea when the editor is in
		// "Text" mode (tinymce.get() still returns the instance then, just hidden).
		function setEditorContent(html) {
			var editor = window.tinymce && tinymce.get(editorId);
			if (editor && !editor.isHidden()) {
				editor.setContent(html || '');
			}
			$('#' + editorId).val(html || '');
		}

		function getEditorContent() {
			var editor = window.tinymce && tinymce.get(editorId);
			if (editor && !editor.isHidden()) {
				editor.save();
			}
			return $('#' + editorId).val();
		}

		function setCoverImage(url) {
			var $picker = $form.find('[data-stoppage-media-preview]');
			$picker.find('img').remove();
			if (url) {
				$picker.prepend($('<img>', { src: url, alt: '' }));
			}
			$picker.toggleClass('has-image', !!url);
			$form.find('[data-stoppage-media-pick-label]').text(url ? (cfg.changeImage || 'Change Image') : (cfg.chooseImage || 'Choose Image'));
		}

		function renderGallery() {
			var $galleryGrid = $form.find('[data-stoppage-gallery-grid]');
			$galleryGrid.empty();
			galleryItems.forEach(function (item) {
				var $thumb = $('<div>', { class: 'mptbm-stoppages-gallery-thumb', 'data-id': item.id });
				$thumb.append($('<img>', { src: item.url, alt: '' }));
				$thumb.append(
					$('<button>', { type: 'button', class: 'mptbm-stoppages-gallery-remove', 'aria-label': 'Remove image' })
						.attr('data-stoppage-gallery-remove', '')
						.append($('<i>', { class: 'fas fa-times', 'aria-hidden': 'true' }))
				);
				$galleryGrid.append($thumb);
			});
			$form.find('[data-stoppage-gallery-ids]').val(JSON.stringify(galleryItems.map(function (item) { return item.id; })));
		}

		function resetForm() {
			$form[0].reset();
			$form.find('[data-stoppage-post-id]').val('');
			$form.find('[data-stoppage-image-id]').val('');
			$form.find('[data-stoppage-message]').text('').removeClass('is-error is-success');
			setCoverImage('');
			setEditorContent('');
			galleryItems = [];
			renderGallery();
		}

		function openModal(titleText, submitLabel) {
			$modal.find('[data-stoppage-title]').text(titleText);
			$modal.find('[data-stoppage-submit-label]').text(submitLabel);
			$modal.attr('aria-hidden', 'false').addClass('is-open');
			$('body').addClass('mptbm-stoppages-modal-open');
		}

		function closeModal() {
			$modal.attr('aria-hidden', 'true').removeClass('is-open');
			$('body').removeClass('mptbm-stoppages-modal-open');
			resetForm();
		}

		$(document).on('click', '[data-stoppage-open]', function () {
			mode = 'add';
			resetForm();
			openModal(cfg.addTitle || 'Add Stoppage', cfg.addLabel || 'Add stoppage');
		});

		$(document).on('click', '[data-stoppage-close]', function (e) {
			if (e.target === this) {
				closeModal();
			}
		});

		$(document).on('click', '.mptbm-stoppages-modal-backdrop, .mptbm-stoppages-modal-close', function () {
			closeModal();
		});

		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $modal.hasClass('is-open')) {
				closeModal();
			}
		});

		$(document).on('click', '[data-stoppage-edit]', function () {
			var $card = $(this).closest('.mptbm-stoppages-card');
			mode = 'edit';
			resetForm();

			$form.find('[data-stoppage-post-id]').val($card.data('post-id'));
			$form.find('#mptbm-stoppage-name').val($card.data('title'));
			setEditorContent($card.data('description'));
			$form.find('#mptbm-stoppage-duration').val($card.data('duration'));
			$form.find('#mptbm-stoppage-price').val($card.data('price'));
			$form.find('#mptbm-stoppage-badge').val($card.data('badge') || '');

			var imageId = $card.data('image-id');
			var imageUrl = $card.data('image-url');
			if (imageId && imageUrl) {
				$form.find('[data-stoppage-image-id]').val(imageId);
				setCoverImage(imageUrl);
			}

			// jQuery auto-parses the JSON in data-gallery into a real array.
			var gallery = $card.data('gallery');
			galleryItems = Array.isArray(gallery) ? gallery : [];
			renderGallery();

			openModal(cfg.editTitle || 'Edit Stoppage', cfg.saveLabel || 'Save changes');
		});

		$(document).on('click', '[data-stoppage-delete]', function () {
			var $card = $(this).closest('.mptbm-stoppages-card');
			var postId = $card.data('post-id');
			if (!window.confirm(cfg.confirmDelete || 'Move this stoppage to Trash?')) {
				return;
			}
			$.post(cfg.ajaxUrl, {
				action: cfg.deleteAction,
				nonce: cfg.nonce,
				post_id: postId
			}).done(function (resp) {
				if (resp && resp.success) {
					$card.remove();
					setCount($grid.find('.mptbm-stoppages-card').length);
				} else {
					window.alert((resp && resp.data && resp.data.message) || cfg.genericError);
				}
			}).fail(function () {
				window.alert(cfg.genericError);
			});
		});

		$(document).on('click', '[data-stoppage-media-pick]', function (e) {
			e.preventDefault();
			if (mediaFrame) {
				mediaFrame.open();
				return;
			}
			mediaFrame = wp.media({
				title: cfg.mediaTitle || 'Select a stoppage image',
				button: { text: cfg.mediaButton || 'Use this image' },
				multiple: false,
				library: { type: 'image' }
			});
			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				$form.find('[data-stoppage-image-id]').val(attachment.id);
				setCoverImage(attachment.url);
			});
			mediaFrame.open();
		});

		$(document).on('click', '[data-stoppage-gallery-pick]', function (e) {
			e.preventDefault();
			if (galleryFrame) {
				galleryFrame.open();
				return;
			}
			galleryFrame = wp.media({
				title: cfg.galleryTitle || 'Select gallery images',
				button: { text: cfg.galleryButton || 'Add to gallery' },
				multiple: true,
				library: { type: 'image' }
			});
			galleryFrame.on('select', function () {
				galleryFrame.state().get('selection').each(function (attachment) {
					var data = attachment.toJSON();
					var alreadyAdded = galleryItems.some(function (item) {
						return item.id === data.id;
					});
					if (!alreadyAdded) {
						var thumbUrl = (data.sizes && data.sizes.thumbnail) ? data.sizes.thumbnail.url : data.url;
						galleryItems.push({ id: data.id, url: thumbUrl });
					}
				});
				renderGallery();
			});
			galleryFrame.open();
		});

		$(document).on('click', '[data-stoppage-gallery-remove]', function () {
			var id = $(this).closest('.mptbm-stoppages-gallery-thumb').data('id');
			galleryItems = galleryItems.filter(function (item) {
				return item.id !== id;
			});
			renderGallery();
		});

		$form.on('submit', function (e) {
			e.preventDefault();

			var name = $form.find('#mptbm-stoppage-name').val().trim();
			var $message = $form.find('[data-stoppage-message]');
			if (!name) {
				$message.text(cfg.requiredName || 'Enter a name for this stoppage.').addClass('is-error');
				return;
			}

			var isEdit = mode === 'edit';
			var payload = {
				action: isEdit ? cfg.updateAction : cfg.addAction,
				nonce: cfg.nonce,
				post_id: $form.find('[data-stoppage-post-id]').val(),
				title: name,
				description: getEditorContent(),
				duration: $form.find('#mptbm-stoppage-duration').val(),
				price: $form.find('#mptbm-stoppage-price').val(),
				badge: $form.find('#mptbm-stoppage-badge').val(),
				image_id: $form.find('[data-stoppage-image-id]').val(),
				gallery_ids: $form.find('[data-stoppage-gallery-ids]').val()
			};

			var $submit = $form.find('[data-stoppage-submit]');
			var originalLabel = $submit.find('[data-stoppage-submit-label]').text();
			$submit.prop('disabled', true).find('[data-stoppage-submit-label]').text(isEdit ? (cfg.savingLabel || 'Saving…') : (cfg.addingLabel || 'Adding…'));
			$message.text('').removeClass('is-error is-success');

			$.post(cfg.ajaxUrl, payload).done(function (resp) {
				if (resp && resp.success) {
					var $existing = $grid.find('.mptbm-stoppages-card[data-post-id="' + resp.data.postId + '"]');
					if ($existing.length) {
						$existing.replaceWith(resp.data.card);
					} else {
						$grid.prepend(resp.data.card);
					}
					setCount($grid.find('.mptbm-stoppages-card').length);
					closeModal();
				} else {
					$message.text((resp && resp.data && resp.data.message) || cfg.genericError).addClass('is-error');
				}
			}).fail(function () {
				$message.text(cfg.genericError).addClass('is-error');
			}).always(function () {
				$submit.prop('disabled', false).find('[data-stoppage-submit-label]').text(originalLabel);
			});
		});

		var $search = $('[data-stoppages-search]');
		var $searchEmpty = $('[data-stoppages-search-empty]');

		function applySearch() {
			var term = $.trim($search.val() || '').toLowerCase();
			var $cards = $grid.find('.mptbm-stoppages-card');
			var visibleCount = 0;

			$cards.each(function () {
				var $card = $(this);
				var matches = !term || (($card.data('title') || '').toString().toLowerCase().indexOf(term) !== -1);
				$card.toggle(matches);
				if (matches) {
					visibleCount++;
				}
			});

			// Only shown while a search term is active and it matched nothing -
			// the "no stoppages at all yet" empty state (data-stoppages-empty)
			// covers the zero-total case already.
			$searchEmpty.toggleClass('is-hidden', !(term && visibleCount === 0 && $cards.length > 0));
		}

		$search.on('input', applySearch);
	});
})(jQuery);
