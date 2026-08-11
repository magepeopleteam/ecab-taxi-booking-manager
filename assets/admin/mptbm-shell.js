(function () {
	'use strict';

	// Top-of-page loading bar, shown on every plugin admin screen (not just
	// the edit screen) since every link here is a real full page load, not
	// AJAX navigation. Runs as its own IIFE at the very top of this file so
	// it applies before anything below it, and isn't affected by the
	// metabox-only relocation code further down.
	if (!document.body) {
		return;
	}
	var bar = document.createElement('div');
	bar.className = 'mptbm-shell-loadbar';
	document.body.appendChild(bar);

	window.requestAnimationFrame(function () {
		bar.classList.add('is-active');
		bar.style.width = '30%';
		setTimeout(function () {
			bar.classList.add('is-done');
		}, 250);
	});

	document.addEventListener('click', function (e) {
		var link = e.target.closest && e.target.closest('a[href]');
		if (!link || link.target === '_blank' || link.hasAttribute('download') ||
			e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
			return;
		}
		var href = link.getAttribute('href') || '';
		if (!href || href.charAt(0) === '#' || href.toLowerCase().indexOf('javascript:') === 0) {
			return;
		}
		try {
			if (new URL(link.href, window.location.href).origin !== window.location.origin) {
				return;
			}
		} catch (err) {
			return;
		}
		bar.classList.remove('is-done');
		bar.classList.add('is-active');
		bar.style.width = '90%';
	});
})();

(function ($) {
	'use strict';

	// Add/Edit Transportation screen only — DOM relocation, run SYNCHRONOUSLY
	// (not inside jQuery(document).ready()), so the featured-image/categories/
	// quick-tips sidebar below is already in place before the rest of the
	// page (including WordPress's own inline TinyMCE bootstrap) finishes
	// loading.

	// Force WordPress's single-column post-edit layout instead of the default
	// 2-column one, since the sidebar postboxes are being relocated elsewhere.
	$('#post-body.metabox-holder').removeClass('columns-2').addClass('columns-1');

	// The native Title field + content editor are NOT relocated here — the
	// "General Info" tab already has its own "Basic Information" card
	// (MPTBM_Rent_Custom_Editor::taxi_title_description_set(), rendered
	// server-side) covering the same Title/Description inputs. Relocating
	// #titlediv/#postdivrich here as well used to produce a second, duplicate
	// "Basic Information" card. #titlediv/#postdivrich stay in their default
	// DOM position and are hidden via CSS instead (see mptbm-shell.css).

	// Relabels #postimagediv .inside's contents — called once at page load
	// AND every time WP's media modal rewrites that same markup afterward
	// (see the MutationObserver below). Must therefore re-find every element
	// fresh each call rather than caching jQuery objects from an earlier run,
	// since WP replaces the actual DOM nodes each time, not just their text.
	function relabelFeaturedImageInside($featuredImageBox) {
		var $thumbLink = $featuredImageBox.find('#set-post-thumbnail');
		var $howto = $featuredImageBox.find('#set-post-thumbnail-desc');
		var $removeP = $featuredImageBox.find('#remove-post-thumbnail').text('Remove').closest('p');
		if ($thumbLink.find('img').length) {
			// A featured image is already set: turn the descriptive
			// "Click the image to edit or update" text into a "Change
			// image" link that proxy-clicks the same thickbox trigger.
			$howto.text('Change image').addClass('mptbm-change-image').on('click', function () {
				$thumbLink[0].click();
			});
			$howto.add($removeP).wrapAll('<div class="mptbm-featured-image-actions"></div>');
		} else {
			// No featured image set yet: style the native link as a
			// fixed-aspect dropzone instead of plain text.
			$thumbLink.addClass('mptbm-featured-image-placeholder').html(
				'<i class="fas fa-cloud-upload-alt"></i>' +
				'<strong>Click to upload or drag &amp; drop</strong>' +
				'<span>PNG, JPG or WebP (max. 5MB)</span>'
			);

			// WP core doesn't render the "Change image"/"Remove" row at all
			// when no thumbnail is set — synthesized here so the layout
			// matches the "has image" state.
			var $emptyActions = $(
				'<div class="mptbm-featured-image-actions">' +
					'<p class="hide-if-no-js howto mptbm-change-image" id="set-post-thumbnail-desc">Change image</p>' +
					'<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail" class="mptbm-inert" aria-disabled="true">Remove</a></p>' +
				'</div>'
			);
			$emptyActions.find('#set-post-thumbnail-desc').on('click', function () {
				$thumbLink[0].click();
			});
			$emptyActions.find('#remove-post-thumbnail').on('click', function (e) {
				e.preventDefault();
			});
			$thumbLink.after($emptyActions);
		}
	}

	// Move the native Featured Image box out of the side postbox column and
	// into a persistent sidebar next to the tab content, visible regardless
	// of which tab is active. Appended as a SIBLING of .tabsContent (inside
	// .mptbm-panel-row), not as a child of .tabsContent itself — the tab
	// switcher's slideUp()/slideDown() cross-fade briefly leaves the
	// outgoing AND incoming .tabsItem panels both non-"none" at the same
	// time; if the sidebar were a flex-item alongside those panels inside
	// .tabsContent, that momentary extra item would shove it sideways on
	// every tab switch. As a sibling one level up, it's unaffected by
	// whatever reflows happen inside the .tabsContent column.
	var $panelRow = $('#mptbm_rent_settings_panel .mptbm-panel-row');
	var $tabsContent = $('#mptbm_rent_settings_panel .tabsContent');
	var $featuredImageBox = $('#postimagediv');
	if ($panelRow.length && $tabsContent.length && $featuredImageBox.length) {
		var $sidebar = $('<div class="mptbm-tabs-sidebar"></div>');

		// Payment Method status card — lives in the "Transportation Details"
		// side metabox like Categories/Tags and Quick Tips below, but appended
		// FIRST so it sits at the very top of the sidebar, above the Featured
		// Image (see MPTBM_Payment_Settings::render_payment_sidebar_card()).
		var $paymentCard = $('.mptbm_payment_method_card');
		if ($paymentCard.length) {
			$sidebar.append($paymentCard);
		}

		$sidebar.append($featuredImageBox);
		$panelRow.append($sidebar);

		relabelFeaturedImageInside($featuredImageBox);

		// WP's featured-image picker (wp-includes/js/media-editor.js —
		// wp.media.featuredImage.set()/remove()) replaces #postimagediv
		// .inside's ENTIRE innerHTML via an AJAX call every time the user
		// sets or removes the image through the media modal, with fresh,
		// un-relabeled markup straight from the server — silently undoing
		// relabelFeaturedImageInside() above, which otherwise only ever
		// runs once at page load. Re-running it on every such change is
		// what keeps the dropzone/Change-image/Remove UI correct after the
		// user actually interacts with the picker, not just on first load.
		// Guarded with disconnect()/observe() around the call since
		// relabelFeaturedImageInside() itself edits this same subtree —
		// without that, every relabel would immediately re-trigger this
		// same observer.
		var $inside = $featuredImageBox.find('.inside');
		if ($inside.length && window.MutationObserver) {
			var insideObserver = new MutationObserver(function () {
				insideObserver.disconnect();
				relabelFeaturedImageInside($featuredImageBox);
				insideObserver.observe($inside[0], { childList: true });
			});
			insideObserver.observe($inside[0], { childList: true });
		}

		// Pricing Rules / Shortcode Usage Guide card — lives inside the
		// "Pricing" tab's markup (MPTBM_Rent_Custom_Editor::pricing_settings())
		// and documents the rules/shortcode for whichever pricing model is
		// currently selected there, so unlike the featured image/category/
		// quick-tips cards it should NOT be visible on every tab — only
		// while Pricing itself is active. Relocated here (directly after the
		// featured image, before Categories/Tags) purely for layout; visibility
		// is kept in sync with the active tab below since mp_script.js's tab
		// switcher only shows/hides its own .tabsContent children, and this
		// card no longer lives there.
		var $pricingRulesBox = $('.mptbm_pricing_rules_wrapper');
		if ($pricingRulesBox.length) {
			$pricingRulesBox.hide();
			$sidebar.append($pricingRulesBox);

			$(document).on('click', '#mptbm_rent_settings_panel .mpStyle [data-tabs-target]', function () {
				$pricingRulesBox.toggle($(this).data('tabs-target') === '#mptbm_settings_pricing');
			});
		}

		// Move the Categories/Tags manager (from the "Transportation
		// Details" side metabox) to sit directly under the featured image
		// in this same persistent sidebar — the Pro-upsell card in that
		// metabox stays where it is, only these two specific cards relocate.
		var $categoryTagBox = $('.mptbm_taxi_category_container');
		if ($categoryTagBox.length) {
			$sidebar.append($categoryTagBox);
		}

		// Quick Tips & Documentation card — moved to sit after the
		// Categories/Tags card in the same sidebar.
		var $quickTipsBox = $('.mptbm_quick_tips_card');
		if ($quickTipsBox.length) {
			$sidebar.append($quickTipsBox);
		}

		// Loading placeholder over the whole sidebar, matching the per-tab
		// skeletons mp_script.js adds to .tabsContent panels — same classes,
		// so that script's existing $(window).on('load') / fallback timeout
		// removes this one too without any extra wiring here.
		$sidebar.append(
			'<div class="mptbm-tab-skeleton" aria-hidden="true">' +
				'<div class="mptbm-tab-skeleton-bar h-lg"></div>' +
				'<div class="mptbm-tab-skeleton-bar h-row"></div>' +
				'<div class="mptbm-tab-skeleton-bar h-row w-80"></div>' +
				'<div class="mptbm-tab-skeleton-bar h-row w-60"></div>' +
			'</div>'
		);
	}

	// Floating Previous/Next bar (bottom of .mptbm-panel-row) — steps
	// through the same .tabLists items the tab switcher above already
	// drives, so clicking Next/Previous is equivalent to clicking the
	// corresponding tab directly. currentTabIndex is tracked in JS rather
	// than re-derived from the ".active" class on every click, since the
	// Prev/Next buttons' own handlers would otherwise race mp_script.js's
	// tab-click handler over which one sets/reads ".active" first.
	var $tabListItems = $('#mptbm_rent_settings_panel .tabLists [data-tabs-target]');
	var $navPrev = $('#mptbm-panel-row-prev');
	var $navNext = $('#mptbm-panel-row-next');
	var $navStep = $('#mptbm-panel-row-nav-step');
	if ($tabListItems.length && $navPrev.length && $navNext.length) {
		var currentTabIndex = -1;

		var updatePanelRowNavFor = function (tabEl) {
			var idx = $tabListItems.index(tabEl);
			if (idx < 0) {
				return;
			}
			currentTabIndex = idx;
			$navPrev.toggleClass('is-disabled', idx === 0);
			$navNext.toggleClass('is-disabled', idx === $tabListItems.length - 1);
			$navStep.text(
				$tabListItems.eq(idx).find('.menu-text').text() + ' (' + (idx + 1) + '/' + $tabListItems.length + ')'
			);
		};

		$(document).on('click', '#mptbm_rent_settings_panel .mpStyle [data-tabs-target]', function () {
			updatePanelRowNavFor(this);
		});

		$navPrev.on('click', function () {
			if (currentTabIndex > 0) {
				$tabListItems.eq(currentTabIndex - 1).trigger('click');
			}
		});
		$navNext.on('click', function () {
			if (currentTabIndex > -1 && currentTabIndex < $tabListItems.length - 1) {
				$tabListItems.eq(currentTabIndex + 1).trigger('click');
			}
		});
	}

	// Reveal the panel now that every relocation above is done — see
	// MPTBM_Admin_Shell::print_metabox_reveal_style() for why it starts hidden.
	document.getElementById('mptbm-metabox-reveal') && document.getElementById('mptbm-metabox-reveal').remove();
}(jQuery));

jQuery(function ($) {
	'use strict';

	// Topbar: save the complete native form through WordPress's normal post
	// pipeline, but return JSON so the custom editor never has to reload.
	var $topbarUpdate = $('#mptbm-edit-topbar-update');
	var $realPublish = $('#publish');
	var $postForm = $('form#post');
	var $publishToggle = $('#mptbm-edit-topbar-publish-toggle');
	var saveRequest = null;
	var saveButtonTimer = null;
	var savedFormState = '';

	function syncEditors() {
		if (window.tinymce) {
			window.tinymce.triggerSave();
		}
	}

	function getComparableFormState() {
		syncEditors();
		return $.param($postForm.serializeArray().filter(function (field) {
			return field.name !== 'action' &&
				field.name !== '_wp_http_referer' &&
				field.name !== 'active_post_lock' &&
				field.name.indexOf('nonce') === -1;
		}));
	}

	function markEditorsClean() {
		if (!window.tinymce) {
			return;
		}
		var editors = window.tinymce.editors ||
			(window.tinymce.EditorManager && window.tinymce.EditorManager.editors) || [];
		$.each(editors, function (index, editor) {
			if (editor && typeof editor.setDirty === 'function') {
				editor.setDirty(false);
			}
		});
	}

	function showSaveNotice(message, type, savedAt) {
		var $notice = $('#mptbm-ajax-save-notice');
		if (!$notice.length) {
			$notice = $('<div id="mptbm-ajax-save-notice" class="mptbm-ajax-save-notice" role="status" aria-live="polite"></div>').appendTo('body');
		}
		$notice.removeClass('is-success is-error').addClass(type === 'error' ? 'is-error' : 'is-success');
		$notice.empty().append($('<i>', { 'class': type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle' }));
		$notice.append($('<span>').text(message));
		if (savedAt) {
			$notice.append($('<small>').text(savedAt));
		}
		$notice.addClass('is-visible');
		clearTimeout($notice.data('hide-timer'));
		$notice.data('hide-timer', setTimeout(function () {
			$notice.removeClass('is-visible');
		}, type === 'error' ? 6000 : 3000));
	}

	function finishSaveButton(label) {
		$topbarUpdate.prop('disabled', false).removeClass('is-saving').text(label || 'Update');
		$publishToggle.prop('disabled', false);
	}

	function ajaxSaveTransport(desiredStatus) {
		if (!$postForm.length || saveRequest) {
			return;
		}
		if ($postForm[0].checkValidity && !$postForm[0].checkValidity()) {
			$postForm[0].reportValidity();
			return;
		}

		syncEditors();
		var formData = new FormData($postForm[0]);
		formData.set('action', 'mptbm_ajax_save_transport');
		formData.set('nonce', mptbmShell.nonce);
		formData.set('desired_status', desiredStatus);

		if (window.wp && wp.autosave && wp.autosave.server) {
			wp.autosave.server.suspend();
		}
		clearTimeout(saveButtonTimer);
		$topbarUpdate.prop('disabled', true).addClass('is-saving').text(mptbmShell.saving || 'Saving…');
		$publishToggle.prop('disabled', true);

		saveRequest = $.ajax({
			url: mptbmShell.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			dataType: 'json'
		}).done(function (response) {
			if (!response || !response.success) {
				var failedMessage = response && response.data && response.data.message ? response.data.message : mptbmShell.saveError;
				showSaveNotice(failedMessage, 'error');
				finishSaveButton($realPublish.val() || 'Update');
				return;
			}

			var data = response.data;
			// Schedule recovery before touching optional editor/UI APIs. Even if
			// another plugin breaks a success-side enhancement, the save button
			// must never remain disabled or display a permanent loading state.
			saveButtonTimer = setTimeout(function () {
				finishSaveButton(data.buttonLabel);
			}, 900);
			$('#post_status, #hidden_post_status, #original_post_status').val(data.status);
			$('#auto_draft').val('');
			$('#_wpnonce').val(data.postNonce);
			$('#mptbm-edit-topbar-title').text(data.title || $('#title').val());
			$('#mptbm-edit-topbar-status')
				.removeClass('is-publish is-draft is-pending is-private')
				.addClass('is-' + data.status)
				.text(data.statusLabel);
			$realPublish.val(data.buttonLabel);
			$topbarUpdate.removeClass('is-saving').text(mptbmShell.saved || 'Saved');
			$('#mptbm-edit-topbar-preview').attr('data-preview-url', data.previewUrl).show();

			if (data.editUrl && window.history && window.history.replaceState) {
				window.history.replaceState({}, document.title, data.editUrl);
				document.body.classList.remove('post-new-php');
				document.body.classList.add('post-php');
			}

			markEditorsClean();
			savedFormState = getComparableFormState();
			showSaveNotice(data.message, 'success', data.savedAt);
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
				? xhr.responseJSON.data.message
				: (mptbmShell.saveError || 'The transportation could not be saved. Please try again.');
			showSaveNotice(message, 'error');
			finishSaveButton($realPublish.val() || 'Update');
		}).always(function () {
			saveRequest = null;
			if (window.wp && wp.autosave && wp.autosave.server) {
				wp.autosave.server.resume();
			}
		});
	}

	if ($topbarUpdate.length && $realPublish.length && $postForm.length) {
		$topbarUpdate.text($realPublish.val());
		savedFormState = getComparableFormState();
		$topbarUpdate.on('click', function (e) {
			e.preventDefault();
			var currentStatus = $('#post_status').val();
			var desiredStatus = currentStatus === 'publish' || currentStatus === 'private' ? currentStatus : 'publish';
			ajaxSaveTransport(desiredStatus);
		});

		// Replace WordPress's page-load-only warning with one whose baseline is
		// refreshed after each successful AJAX save.
		$(window).off('beforeunload.edit-post').on('beforeunload.mptbm-edit-post', function (event) {
			if (getComparableFormState() !== savedFormState) {
				event.preventDefault();
				return mptbmShell.unsavedWarning || 'You have unsaved transportation changes.';
			}
		});

		$(document).on('keydown.mptbm-ajax-save', function (e) {
			if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 's') {
				e.preventDefault();
				$topbarUpdate.trigger('click');
			}
		});
	}

	// Split-button "Save Draft" — available both before and after first
	// publish, so an already-published vehicle can be taken back to Draft too.
	var $splitPublish = $('#mptbm-edit-topbar-publish');
	if ($splitPublish.length) {
		$('#mptbm-edit-topbar-save-draft').on('click', function (e) {
			e.preventDefault();
			$splitPublish.removeClass('is-open');
			$publishToggle.attr('aria-expanded', 'false');
			ajaxSaveTransport('draft');
		});

		$publishToggle.on('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var isOpen = $splitPublish.toggleClass('is-open').hasClass('is-open');
			$publishToggle.attr('aria-expanded', isOpen ? 'true' : 'false');
		});

		$(document).on('click', function (e) {
			if ($splitPublish.hasClass('is-open') && !$splitPublish.is(e.target) && $splitPublish.has(e.target).length === 0) {
				$splitPublish.removeClass('is-open');
				$publishToggle.attr('aria-expanded', 'false');
			}
		});

		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' && $splitPublish.hasClass('is-open')) {
				$splitPublish.removeClass('is-open');
				$publishToggle.attr('aria-expanded', 'false');
			}
		});
	}

	var $topbarPreview = $('#mptbm-edit-topbar-preview');
	var $realPreview = $('#post-preview');
	if ($topbarPreview.length) {
		$topbarPreview.on('click', function (e) {
			e.preventDefault();
			var previewUrl = $(this).attr('data-preview-url');
			if (previewUrl) {
				window.open(previewUrl, 'wp-preview');
			} else if ($realPreview.length) {
				$realPreview[0].click();
			}
		});
	}
	if ($topbarPreview.length && !$realPreview.length) {
		// No preview link exists (e.g. new unsaved auto-draft) — hide rather
		// than show a dead button.
		$topbarPreview.hide();
	}

	// Live-sync the topbar title as the user types.
	var $topbarTitle = $('#mptbm-edit-topbar-title');
	var $realTitleField = $('#title');
	if ($topbarTitle.length && $realTitleField.length) {
		$realTitleField.on('input', function () {
			$topbarTitle.text($(this).val() || '');
		});
	}

	// Sidebar items with a sub-menu ("Transportation") toggle that sub-menu
	// open/closed instead of navigating away — only reachable while that
	// item is already active (has-children is only added then), so this
	// never blocks navigation TO the page from elsewhere. Delegated from
	// document so it covers both the flex shell and the edit screen's fixed
	// sidebar, which renders the same markup outside any .mptbm-shell wrapper.
	$(document).on('click', '.mptbm-shell-menu > li.has-children > a', function (e) {
		e.preventDefault();
		$(this).closest('li').toggleClass('mptbm-shell-submenu-collapsed');
	});

	// Mobile sidebar drawer toggle — delegated on document since both the
	// flex shell (dashboard pages) and the fixed overlay (edit screen) can
	// render this trigger, and the edit screen's sidebar/topbar sit outside
	// any .mptbm-shell wrapper.
	$(document).on('click', '.mptbm-shell-mobile-trigger', function (e) {
		e.preventDefault();
		$('body').toggleClass('mptbm-mobile-menu-open');
		$('.mptbm-shell').toggleClass('mobile-menu-open');
	});

	// Deep-link support: the sidebar's Transportation sub-menu (Published,
	// Draft) links to the Transportation Lists page with a
	// "?mptbm_filter=<value>" query arg — otherwise every one of those links
	// always opened the page on its default "All" view. Activates the
	// matching client-side filter pill the list page already has. Deferred
	// to window 'load' (not run inline here) so it fires after
	// mptbm_transportation_lists.js has bound its own click handler to
	// .mptbm-filter-pill, regardless of which script's ready-handler the
	// browser happens to run first.
	$(window).on('load', function () {
		var params = new URLSearchParams(window.location.search);
		var requestedFilter = params.get('mptbm_filter');
		if (!requestedFilter) {
			return;
		}
		var $pill = $('.mptbm-filter-pill[data-status="' + requestedFilter + '"]');
		if ($pill.length) {
			$pill.trigger('click');
		}
	});

	var $shell = $('.mptbm-shell');
	if (!$shell.length) {
		return;
	}

	// Sidebar collapse/expand, persisted per-user (flex shell only — the
	// edit screen's fixed sidebar has no fold trigger).
	$shell.on('click', '.mptbm-shell-fold-trigger', function (e) {
		e.preventDefault();
		var isCompact = $shell.hasClass('side-menu-compact');
		var nextStyle = isCompact ? 'full' : 'compact';

		$shell.toggleClass('side-menu-compact', !isCompact);

		if (typeof mptbmShell === 'undefined') {
			return;
		}

		$.post(mptbmShell.ajaxUrl, {
			action: 'mptbm_set_menu_layout_style',
			nonce: mptbmShell.nonce,
			style: nextStyle
		});
	});
});
