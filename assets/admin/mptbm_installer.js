/**
 * MPTBM chunked installer.
 *
 * Walks a queue of plugins, each broken into small steps
 * (download -> extract -> activate). Every step is its own AJAX request so a
 * single PHP process never has to download, unzip AND activate at once — which
 * is what kills the install on low-memory / low-timeout hosts. One progress bar
 * tracks completion across all steps of all plugins.
 */
(function ($) {
	'use strict';

	var config = window.mptbm_installer || {};

	var $overlay, $popup, $btn, $progress, $fill, $status, $actions;
	var tasks = [];
	var totalTasks = 0;
	var doneTasks = 0;
	var working = false;

	// AJAX action name for each logical step.
	var ACTIONS = {
		download: 'mptbm_inst_download',
		extract:  'mptbm_inst_extract',
		activate: 'mptbm_inst_activate'
	};

	$(function () {
		$overlay  = $('#mptbm-inst-overlay');
		if (!$overlay.length) {
			return;
		}
		$popup    = $overlay.find('.mptbm-inst-popup');
		$btn      = $('#mptbm-inst-btn');
		$progress = $('#mptbm-inst-progress');
		$fill     = $('#mptbm-inst-progress-fill');
		$status   = $('#mptbm-inst-status-text');
		$actions  = $overlay.find('.mptbm-inst-actions');

		$btn.on('click', function (e) {
			e.preventDefault();
			if (working) {
				return;
			}
			start();
		});
	});

	function sprintf(tpl, value) {
		return (tpl || '').replace('%s', value);
	}

	// Flatten the plugin list into an ordered list of single-request steps.
	function buildTasks() {
		var list = [];
		var plugins = config.plugins || [];
		$.each(plugins, function (_, p) {
			if (p.active) {
				return; // Nothing to do — already installed and active.
			}
			if (!p.installed) {
				list.push({ slug: p.slug, name: p.name, step: 'download' });
				list.push({ slug: p.slug, name: p.name, step: 'extract' });
			}
			list.push({ slug: p.slug, name: p.name, step: 'activate' });
		});
		return list;
	}

	function start() {
		working = true;
		$btn.prop('disabled', true);
		$actions.slideUp(250);
		$progress.slideDown(300);

		tasks = buildTasks();
		totalTasks = tasks.length;
		doneTasks = 0;

		if (!totalTasks) {
			finish();
			return;
		}
		runTask(0);
	}

	function statusFor(task) {
		if (task.step === 'download') { return sprintf(config.i18n.downloading, task.name); }
		if (task.step === 'extract')  { return sprintf(config.i18n.extracting, task.name); }
		return sprintf(config.i18n.activating, task.name);
	}

	function setProgress(text) {
		var percent = totalTasks ? Math.round((doneTasks / totalTasks) * 100) : 0;
		// Keep the bar visibly moving while a request is in flight.
		$fill.css('width', Math.max(percent, 6) + '%');
		if (text) {
			$status.text(text).removeClass('mptbm-success mptbm-error');
		}
	}

	function runTask(index) {
		if (index >= tasks.length) {
			finish();
			return;
		}
		var task = tasks[index];
		setProgress(statusFor(task));

		$.ajax({
			url: config.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: ACTIONS[task.step],
				nonce:  config.nonce,
				slug:   task.slug
			}
		}).done(function (response) {
			if (response && response.success) {
				doneTasks++;
				setProgress();
				runTask(index + 1);
			} else {
				showError(response && response.data && response.data.message
					? response.data.message
					: config.i18n.error);
			}
		}).fail(function () {
			showError(config.i18n.error);
		});
	}

	function finish() {
		$fill.css('width', '100%');
		$popup.addClass('mptbm-state-success');
		$status.text(config.i18n.success).addClass('mptbm-success');

		$popup.find('.mptbm-inst-icon').html(
			'<svg width="40" height="40" viewBox="0 0 24 24" fill="none">' +
			'<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>' +
			'<path d="M8 12l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
			'</svg>'
		);
		$popup.find('.mptbm-inst-title').text(config.i18n.success);
		$popup.find('.mptbm-inst-desc').text(config.i18n.redirecting);

		setTimeout(function () {
			window.location.href = config.redirect_url;
		}, 1500);
	}

	function showError(message) {
		working = false;
		$popup.addClass('mptbm-state-error');
		$status.text(message).addClass('mptbm-error');
		$fill.css('width', '100%');

		$btn.prop('disabled', false);
		$actions.slideDown(250);

		setTimeout(function () {
			$popup.removeClass('mptbm-state-error');
			$progress.slideUp(250);
			$fill.css('width', '0%');
		}, 3500);
	}

})(jQuery);
