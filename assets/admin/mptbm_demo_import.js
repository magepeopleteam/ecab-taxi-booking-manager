/**
 * MPTBM demo-data importer popup.
 *
 * Uses delegated event binding (on `document`) so the handlers work no matter
 * when the popup markup appears or whether the page re-renders its DOM, and is
 * loaded as an enqueued script with a jQuery dependency. Nonces/strings come in
 * via wp_localize_script as `mptbm_demo_import`.
 */
(function ($) {
	'use strict';

	var config  = window.mptbm_demo_import || {};
	var working = false;

	// Helps confirm in the console that this file actually loaded/ran.
	if (window.console && console.log) {
		console.log('[MPTBM] demo-import script loaded');
	}

	function showError($overlay, message) {
		var $popup    = $overlay.find('.mptbm-inst-popup');
		var $progress = $('#mptbm-demo-progress');
		var $fill     = $('#mptbm-demo-progress-fill');
		var $status   = $('#mptbm-demo-status-text');
		var $actions  = $overlay.find('.mptbm-inst-actions');

		working = false;
		$popup.addClass('mptbm-state-error');
		$status.text(message).addClass('mptbm-error');
		$fill.css('width', '100%');
		$('#mptbm-demo-import-btn, #mptbm-demo-dismiss-btn').prop('disabled', false);
		$actions.slideDown(250);
		setTimeout(function () {
			$popup.removeClass('mptbm-state-error');
			$progress.slideUp(250);
			$fill.css('width', '0%');
		}, 3500);
	}

	// Open from anywhere via a trigger element.
	$(document).on('click', '#mptbm-trigger-demo-import', function (e) {
		e.preventDefault();
		$('#mptbm-demo-overlay').css('display', 'flex');
	});

	// Import.
	$(document).on('click', '#mptbm-demo-import-btn', function (e) {
		e.preventDefault();
		if (working) { return; }
		working = true;

		var $overlay  = $('#mptbm-demo-overlay');
		var $popup    = $overlay.find('.mptbm-inst-popup');
		var $progress = $('#mptbm-demo-progress');
		var $fill     = $('#mptbm-demo-progress-fill');
		var $status   = $('#mptbm-demo-status-text');
		var $actions  = $overlay.find('.mptbm-inst-actions');

		$('#mptbm-demo-import-btn, #mptbm-demo-dismiss-btn').prop('disabled', true);
		$actions.slideUp(250);
		$progress.slideDown(300);
		$fill.css('width', '55%');
		$status.text((config.i18n && config.i18n.importing) || 'Importing...').removeClass('mptbm-success mptbm-error');

		$.ajax({
			url: (config.ajax_url || window.ajaxurl),
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'mptbm_import_dummy_data',
				nonce:  config.import_nonce
			}
		}).done(function (response) {
			if (response && response.success) {
				$fill.css('width', '100%');
				$popup.addClass('mptbm-state-success');
				$status.text((config.i18n && config.i18n.success) || 'Done!').addClass('mptbm-success');
				$popup.find('.mptbm-inst-icon').html('<svg width="40" height="40" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M8 12l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>');
				$popup.find('.mptbm-inst-title').text((config.i18n && config.i18n.success_title) || 'All set!');
				setTimeout(function () { window.location.reload(); }, 1500);
			} else {
				showError($overlay, (response && response.data && response.data.message) ? response.data.message : ((config.i18n && config.i18n.error) || 'Import failed.'));
			}
		}).fail(function (xhr) {
			if (window.console && console.error) {
				console.error('[MPTBM] demo import AJAX failed', xhr && xhr.status, xhr && xhr.responseText);
			}
			showError($overlay, (config.i18n && config.i18n.error) || 'Import failed.');
		});
	});

	// Dismiss.
	$(document).on('click', '#mptbm-demo-dismiss-btn', function (e) {
		e.preventDefault();
		if (working) { return; }
		working = true;
		var $overlay = $('#mptbm-demo-overlay');
		$overlay.css('opacity', '0.5');
		$.ajax({
			url: (config.ajax_url || window.ajaxurl),
			type: 'POST',
			data: {
				action: 'mptbm_dismiss_dummy_import',
				nonce:  config.dismiss_nonce
			}
		}).always(function () {
			$overlay.fadeOut(300, function () { $(this).remove(); });
		});
	});

})(jQuery);
