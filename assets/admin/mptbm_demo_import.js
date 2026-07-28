/**
 * MPTBM demo-data importer — bottom-right circular progress widget.
 *
 * On a fresh install with no transports yet, the widget auto-starts the demo
 * import and shows a modern radial progress ring. No blocking modal and no
 * confirmation step. Nonces/strings arrive via wp_localize_script as
 * `mptbm_demo_import`. Delegated handlers keep retry/close working regardless
 * of when the markup lands in the DOM.
 */
(function ($) {
	'use strict';

	var config   = window.mptbm_demo_import || {};
	var i18n     = config.i18n || {};
	var working  = false;
	var creep    = null;
	var progress = 0;

	function byId(id) {
		return document.getElementById(id);
	}

	// Circumference of the progress arc, derived from its actual radius so the
	// dash math stays correct even if the SVG geometry is tweaked later.
	function ringCircumference() {
		var arc = byId('mptbm-dw-arc');
		var r   = arc ? (parseFloat(arc.getAttribute('r')) || 34) : 34;
		return 2 * Math.PI * r;
	}

	function setProgress(p) {
		p = Math.max(0, Math.min(1, p));
		progress = p;
		var arc = byId('mptbm-dw-arc');
		var pct = byId('mptbm-dw-pct');
		var c   = ringCircumference();
		if (arc) { arc.style.strokeDashoffset = String(c * (1 - p)); }
		if (pct) { pct.textContent = Math.round(p * 100) + '%'; }
	}

	function setStatus(text) {
		var el = byId('mptbm-dw-status');
		if (el && text) { el.textContent = text; }
	}

	// Decelerating creep toward 90% while the request is in flight — the import
	// is a single server round-trip, so we simulate smooth forward motion and
	// snap to 100% only once it truly completes.
	function startCreep() {
		stopCreep();
		creep = window.setInterval(function () {
			if (progress < 0.9) {
				setProgress(progress + (0.9 - progress) * 0.06);
			}
			if (progress > 0.35 && progress < 0.75) {
				setStatus(i18n.importing);
			} else if (progress >= 0.75) {
				setStatus(i18n.finishing);
			}
		}, 200);
	}

	function stopCreep() {
		if (creep) { window.clearInterval(creep); creep = null; }
	}

	function showSuccess() {
		stopCreep();
		var widget = byId('mptbm-demo-widget');
		var title  = byId('mptbm-dw-title');
		if (widget) { widget.classList.remove('is-error'); widget.classList.add('is-success'); }
		setProgress(1);
		if (title) { title.textContent = i18n.success_title || 'All set!'; }
		setStatus(i18n.success);
		window.setTimeout(function () { window.location.reload(); }, 1400);
	}

	function showError() {
		working = false;
		stopCreep();
		var widget = byId('mptbm-demo-widget');
		var title  = byId('mptbm-dw-title');
		var retry  = byId('mptbm-dw-retry');
		var close  = byId('mptbm-dw-close');
		if (widget) { widget.classList.remove('is-success'); widget.classList.add('is-error'); }
		if (title) { title.textContent = i18n.error_title || 'Import failed'; }
		setStatus(i18n.error);
		if (retry) { retry.style.display = 'inline-flex'; }
		if (close) { close.style.display = 'flex'; }
	}

	function runImport() {
		if (working) { return; }
		working = true;

		var widget = byId('mptbm-demo-widget');
		var retry  = byId('mptbm-dw-retry');
		if (widget) { widget.classList.remove('is-error', 'is-success'); }
		if (retry) { retry.style.display = 'none'; }

		setProgress(0.08);
		setStatus(i18n.preparing);
		startCreep();

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
				showSuccess();
			} else {
				showError();
			}
		}).fail(function (xhr) {
			if (window.console && console.error) {
				console.error('[MPTBM] demo import failed', xhr && xhr.status, xhr && xhr.responseText);
			}
			showError();
		});
	}

	$(function () {
		var widget = byId('mptbm-demo-widget');
		if (!widget) { return; }

		// Prime the ring so the stroke animates up from empty rather than
		// flashing full on first paint.
		var arc = byId('mptbm-dw-arc');
		if (arc) {
			var c = ringCircumference();
			arc.style.strokeDasharray  = String(c);
			arc.style.strokeDashoffset = String(c);
		}

		// Let the slide-in read before the ring starts moving.
		window.setTimeout(runImport, 650);
	});

	$(document).on('click', '#mptbm-dw-retry', function (e) {
		e.preventDefault();
		runImport();
	});

	$(document).on('click', '#mptbm-dw-close', function (e) {
		e.preventDefault();
		var $w = $('#mptbm-demo-widget');
		$w.addClass('is-hiding');
		window.setTimeout(function () { $w.remove(); }, 300);
	});

})(jQuery);
