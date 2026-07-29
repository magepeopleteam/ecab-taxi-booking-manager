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
	// (not inside jQuery(document).ready()). WordPress's own TinyMCE bootstrap
	// for the content editor is an inline <script> near the end of the page
	// that calls tinymce.init() immediately if the document is already
	// 'interactive' — it does not wait for DOMContentLoaded. Since this file is
	// enqueued as a plain <script src>, it runs in document order, before that
	// inline bootstrap. Running the relocation here, synchronously, ensures
	// #postdivrich is already in its final position before TinyMCE ever
	// touches the textarea — moving it afterwards would force the browser to
	// reload the iframe as a side effect, silently wiping the editor.

	// Force WordPress's single-column post-edit layout instead of the default
	// 2-column one, since the sidebar postboxes are being relocated elsewhere.
	$('#post-body.metabox-holder').removeClass('columns-2').addClass('columns-1');

	// Move the native Title field + content editor into the "General Info"
	// tab of the Information Settings metabox, wrapped in a "Basic
	// Information" card. Only the DOM position changes — name="post_title"/
	// name="content" stay inside the same <form id="post">, so saving is
	// unaffected.
	var $generalInfoTab = $('[data-tabs="#mptbm_general_info"]');
	var $titlediv = $('#titlediv');
	if ($generalInfoTab.length && $titlediv.length) {
		var $basicInfoCard = $(
			'<div class="mptbm_rent_editor_wrapper mptbm-basic-info-card">' +
				'<div class="mptbm_rent_editor_header"><h2 class="mptbm_rent_editor_title"><i class="fas fa-file-lines"></i> Basic Information</h2></div>' +
				'<div class="mptbm_rent_editor_body mptbm-basic-info-body"></div>' +
			'</div>'
		);
		var $titleLabel = $('<label class="mptbm_rent_label mptbm-content-editor-label" for="title">Rent Title <span class="mptbm_rent_required">*</span></label>');
		var $descriptionLabel = $('<label class="mptbm_rent_label mptbm-content-editor-label">Description</label>');
		$titlediv.find('#title').prop('required', true);
		$basicInfoCard.find('.mptbm-basic-info-body').append($titleLabel, $titlediv, $descriptionLabel, $('#postdivrich'));
		$generalInfoTab.prepend($basicInfoCard);
	}

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
	}

	// Reveal the panel now that every relocation above is done — see
	// MPTBM_Admin_Shell::print_metabox_reveal_style() for why it starts hidden.
	document.getElementById('mptbm-metabox-reveal') && document.getElementById('mptbm-metabox-reveal').remove();
}(jQuery));

jQuery(function ($) {
	'use strict';

	// Topbar: Update/Preview proxy-click the real native controls (WP's
	// #publish submit input and #post-preview link) rather than moving them —
	// #publish lives inside <form id="post">, and the topbar is injected via
	// in_admin_header, which runs before that form even opens in the DOM.
	var $topbarUpdate = $('#mptbm-edit-topbar-update');
	var $realPublish = $('#publish');
	if ($topbarUpdate.length && $realPublish.length) {
		$topbarUpdate.text($realPublish.val());
		$topbarUpdate.on('click', function (e) {
			e.preventDefault();
			$realPublish[0].click();
		});
	}

	// Split-button "Save Draft" — available both before and after first
	// publish, so an already-published vehicle can be taken back to Draft too.
	var $splitPublish = $('#mptbm-edit-topbar-publish');
	var $publishToggle = $('#mptbm-edit-topbar-publish-toggle');
	if ($splitPublish.length) {
		$('#mptbm-edit-topbar-save-draft').on('click', function (e) {
			e.preventDefault();
			var $realSaveDraft = $('#save-post');
			if ($realSaveDraft.length) {
				// Post is already unpublished — WP's own "Save Draft" control
				// already does the right thing.
				$realSaveDraft[0].click();
			} else {
				// Post is currently published — WP has no native "Save
				// Draft" control in this state. Set the real status <select>
				// (hidden in #submitdiv but still functional) to "draft",
				// then submit via the hidden "press Enter to save" button
				// (id="save", always present). NOT via #publish:
				// _wp_translate_postdata() forces post_status back to
				// "publish" whenever $_POST['publish'] is set.
				$('#post_status').val('draft');
				$('#hidden_post_status').val('draft');
				$('#save')[0].click();
			}
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
	if ($topbarPreview.length && $realPreview.length) {
		$topbarPreview.on('click', function (e) {
			e.preventDefault();
			$realPreview[0].click();
		});
	} else if ($topbarPreview.length) {
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
