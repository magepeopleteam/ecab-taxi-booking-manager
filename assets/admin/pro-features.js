(function () {
	'use strict';

	function openModal(id) {
		var modal = document.getElementById('mptbm-pro-modal-' + id);
		if (!modal) {
			return;
		}
		modal.hidden = false;
		modal.classList.add('is-open');
		document.body.classList.add('mptbm-pro-modal-locked');
	}

	function closeModal(modal) {
		modal.classList.remove('is-open');
		modal.hidden = true;
		document.body.classList.remove('mptbm-pro-modal-locked');
	}

	function closest(el, selector) {
		if (el && el.closest) {
			return el.closest(selector);
		}
		return null;
	}

	document.addEventListener('click', function (e) {
		var opener = closest(e.target, '[data-open-modal]');
		if (opener) {
			openModal(opener.getAttribute('data-open-modal'));
			return;
		}

		var closer = closest(e.target, '[data-modal-close]');
		if (closer) {
			var modal = closest(closer, '.mptbm-pro-modal');
			if (modal) {
				closeModal(modal);
			}
			return;
		}

		var codeEl = closest(e.target, '.mptbm-pro-modal-code');
		if (codeEl && window.getSelection && document.createRange) {
			var range = document.createRange();
			range.selectNodeContents(codeEl);
			var selection = window.getSelection();
			selection.removeAllRanges();
			selection.addRange(range);
		}
	});

	document.addEventListener('keydown', function (e) {
		if ((e.key === 'Enter' || e.key === ' ') && document.activeElement) {
			var opener = closest(document.activeElement, '[data-open-modal]');
			if (opener) {
				e.preventDefault();
				openModal(opener.getAttribute('data-open-modal'));
			}
		}

		if (e.key === 'Escape') {
			var openModals = document.querySelectorAll('.mptbm-pro-modal.is-open');
			openModals.forEach(function (modal) {
				closeModal(modal);
			});
		}
	});
})();
