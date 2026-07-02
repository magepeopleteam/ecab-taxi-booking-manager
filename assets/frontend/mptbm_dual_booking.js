/*
 * Companion script for the [mptbm_dual_booking] shortcode.
 *
 * mptbm_registration.js listens for date/time changes using page-global
 * selectors (e.g. $('#mptbm_map_start_time'), $('.start_time_list-no-dsiplay li')).
 * That's fine when only one search form exists on the page, but this shortcode
 * puts two forms on the same page, and those global lookups always resolve to
 * the FIRST matching element regardless of which form the user is actually
 * using. This file detaches those specific handlers and re-registers scoped
 * equivalents (scoped to the surrounding .mptbm_transport_search_area) so each
 * form's date picker / time list behaves independently.
 *
 * Nothing in the original plugin files is modified; this only rewires jQuery
 * event bindings at runtime, and only for the handlers proven to need it.
 */
jQuery(function ($) {

	/*
	 * Both forms render the same plugin template, so every field shares the
	 * same id (mptbm_start_date, mptbm_map_start_time, mptbm_taxi_return, ...).
	 * A LOT of the plugin's own JS still looks things up with plain "#id"
	 * selectors, which always resolve to whichever matching element comes
	 * first in the DOM - so interacting with the second form could silently
	 * read/write the first form's fields.
	 *
	 * Instead of chasing every such lookup individually, keep only the form
	 * the visitor is currently using "live": as soon as they focus/click into
	 * a form, its ids are (re)restored and every other form's matching ids are
	 * removed (saved on a data attribute so they can be restored later). With
	 * only one element carrying each id at a time, any "#id" lookup anywhere
	 * - ours or the plugin's - resolves to the right form automatically.
	 */
	var MPTBM_ID_TOGGLE_EXCLUDE = ['mptbm_get_vehicle', 'mptbm_map_area'];
	var mptbmActiveForm = null;

	function mptbmIsManagedId(id) {
		return !!id && id.indexOf('mptbm_') === 0 && MPTBM_ID_TOGGLE_EXCLUDE.indexOf(id) === -1;
	}

	function mptbmActivateForm($form) {
		if (mptbmActiveForm && mptbmActiveForm.is($form)) {
			return;
		}

		// Disable managed ids on every other form on the page.
		$('.mptbm_transport_search_area').not($form).each(function () {
			$(this).find('[id]').each(function () {
				if (mptbmIsManagedId(this.id)) {
					this.setAttribute('data-mptbm-disabled-id', this.id);
					this.removeAttribute('id');
				}
			});
		});

		// Restore this form's own ids in case they were previously disabled.
		$form.find('[data-mptbm-disabled-id]').each(function () {
			this.id = this.getAttribute('data-mptbm-disabled-id');
			this.removeAttribute('data-mptbm-disabled-id');
		});

		mptbmActiveForm = $form;
	}

	$(document).on('mousedown focusin', '.mptbm_transport_search_area', function () {
		mptbmActivateForm($(this));
	});

	function currentDateStr() {
		var today = new Date();
		var day = String(today.getDate()).padStart(2, '0');
		var month = String(today.getMonth() + 1).padStart(2, '0');
		var year = today.getFullYear();
		return year + '-' + month + '-' + day;
	}

	// Remove the plugin's page-global handlers for these specific bindings.
	$(document).off('click', '.start_time_list li');
	$(document).off('click', '.return_time_list li');
	$(document).off('change', '#mptbm_map_start_date');
	$(document).off('change', '#mptbm_map_return_date');

	// Re-register them scoped to the form the user actually interacted with.
	$(document).on('click', '.start_time_list li', function () {
		var parent = $(this).closest('.mptbm_transport_search_area');
		var selectedValue = $(this).attr('data-value');
		parent.find('#mptbm_map_start_time').val(selectedValue).trigger('change');
	});

	$(document).on('click', '.return_time_list li', function () {
		var parent = $(this).closest('.mptbm_transport_search_area');
		var selectedValue = $(this).attr('data-value');
		parent.find('#mptbm_map_return_time').val(selectedValue).trigger('change');
	});

	$(document).on('change', '#mptbm_map_start_date', function () {
		var parent = $(this).closest('.mptbm_transport_search_area');

		parent.find('#mptbm_map_start_time').siblings('.start_time_list').empty();
		parent.find('.start_time_input, #mptbm_map_start_time').val('');

		var enableReturnDiffDate = parent.find('[name="mptbm_enable_return_in_different_date"]').val();
		var bufferEndMinutes = parseInt(parent.find('[name="mptbm_buffer_end_minutes"]').val()) || 0;
		var firstCalendarDate = parent.find('[name="mptbm_first_calendar_date"]').val();

		var selectedDate = parent.find('#mptbm_map_start_date').val();
		var formattedDate = $.datepicker.parseDate('yy-mm-dd', selectedDate);
		var today = currentDateStr();

		function appendAllowedTimes(isAllowed) {
			parent.find('.start_time_list-no-dsiplay li').each(function () {
				if (!isAllowed || isAllowed($(this))) {
					parent.find('#mptbm_map_start_time').siblings('.start_time_list').append($(this).clone());
				}
			});
		}

		if (selectedDate == today) {
			appendAllowedTimes(function ($li) {
				var timeValue = parseFloat($li.attr('data-value'));
				var timeInMinutes = Math.floor(timeValue) * 60 + ((timeValue % 1) * 100);
				return timeInMinutes > bufferEndMinutes;
			});
		} else if (selectedDate == firstCalendarDate) {
			appendAllowedTimes(function ($li) {
				var timeValue = parseFloat($li.attr('data-value'));
				var timeInMinutes = Math.floor(timeValue) * 60 + ((timeValue % 1) * 100);
				if (bufferEndMinutes > 1440) {
					return timeInMinutes > (bufferEndMinutes - 1440);
				} else if (bufferEndMinutes < 1440 && bufferEndMinutes > 0) {
					return timeInMinutes >= bufferEndMinutes;
				}
				return true;
			});
		} else {
			appendAllowedTimes(null);
		}

		if (enableReturnDiffDate == 'yes') {
			var returnDate = parent.find('#mptbm_return_date');
			if (returnDate.length && returnDate.data('datepicker')) {
				returnDate.datepicker('option', 'minDate', formattedDate);
			}
		}

		if (typeof mptbm_content_refresh === 'function') {
			mptbm_content_refresh(parent);
		}
		parent.find('#mptbm_map_start_time').closest('.mp_input_select').find('input.formControl').trigger('click');
	});

	$(document).on('change', '#mptbm_map_return_date', function () {
		var parent = $(this).closest('.mptbm_transport_search_area');
		var enableReturnDiffDate = parent.find('[name="mptbm_enable_return_in_different_date"]').val();

		if (enableReturnDiffDate == 'yes') {
			var selectedTime = parseFloat(parent.find('#mptbm_map_start_time').val());
			var selectedDate = parent.find('#mptbm_map_start_date').val();
			var dateValue = parent.find('#mptbm_map_return_date').val();

			parent.find('#mptbm_map_return_time').siblings('.mp_input_select_list').empty();
			parent.find('.mptbm_map_return_time_input').val('');

			if (selectedDate == dateValue) {
				parent.find('.mp_input_select_list li').each(function () {
					var timeValue = parseFloat($(this).attr('data-value'));
					if (timeValue > selectedTime) {
						parent.find('#mptbm_map_return_time').siblings('.mp_input_select_list').append($(this).clone());
					}
				});
			} else {
				parent.find('.return_time_list-no-dsiplay li').each(function () {
					parent.find('#mptbm_map_return_time').siblings('.mp_input_select_list').append($(this).clone());
				});
			}
		}

		if (typeof mptbm_content_refresh === 'function') {
			mptbm_content_refresh(parent);
		}
		parent.find('#mptbm_map_return_time').closest('.mp_input_select').find('input.formControl').trigger('click');
	});

	/*
	 * mptbm_registration.js implements a custom searchable dropdown for
	 * #mptbm_manual_start_place / #mptbm_manual_end_place (the flat-rate
	 * pickup/dropoff selects) instead of the native <select> list. When it
	 * builds that dropdown it appends it with the unscoped selector
	 * $('.mptbm_transport_search_area') - with only one such container on the
	 * page that's harmless, but with two forms it matches both, so jQuery
	 * clones the dropdown into the second container and only one of the two
	 * copies keeps its click handlers. Depending on DOM order, that can make
	 * the dropdown you actually opened not respond to option clicks.
	 *
	 * This replaces that one handler with an identical copy, scoped to the
	 * clicked select's own .mptbm_transport_search_area, so exactly one
	 * dropdown - the working one - is created per click.
	 */
	$(document).off('click', '#mptbm_manual_start_place, #mptbm_manual_end_place');

	$(document).on('click', '#mptbm_manual_start_place, #mptbm_manual_end_place', function (e) {
		var $select = $(this);
		var selectId = $select.attr('id');
		var $scope = $select.closest('.mptbm_transport_search_area');

		$scope.find('.mptbm-custom-select-wrapper').remove();

		var $options = $select.find('option:not([disabled])');
		if ($options.length <= 0) {
			return;
		}

		var $customWrapper = $('<div class="mptbm-custom-select-wrapper" style="position: fixed !important; z-index: 9999 !important; background: white !important; border: 1px solid #ddd !important; border-radius: 4px !important; box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;"></div>');
		var $searchInput = $('<input type="text" class="mptbm-custom-search-input" placeholder="Search locations..." style="width: 100% !important; padding: 8px !important; border: none !important; border-bottom: 1px solid #eee !important; border-radius: 4px 4px 0 0 !important; font-size: 14px !important; box-sizing: border-box !important; background: #F5F6F8 !important; color: #222222 !important; font-weight: 400 !important; outline: none !important;" />');
		var $optionsContainer = $('<div class="mptbm-custom-options" style="max-height: 200px !important; overflow-y: auto !important; background: white !important;"></div>');

		function updateDropdownPosition() {
			var currentOffset = $select.offset();
			var currentWidth = $select.outerWidth();
			var currentHeight = $select.outerHeight();

			var scrollTop = $(window).scrollTop();
			var scrollLeft = $(window).scrollLeft();

			var top = currentOffset.top - scrollTop + currentHeight + 2;
			var left = currentOffset.left - scrollLeft;
			var width = currentWidth;

			var windowHeight = $(window).height();
			var windowWidth = $(window).width();

			var availableHeight = windowHeight - top - 20;
			var maxHeight = Math.min(250, Math.max(100, availableHeight));

			$optionsContainer.css('max-height', maxHeight + 'px');

			if (left + width > windowWidth - 10) {
				left = windowWidth - width - 10;
			}
			if (left < 10) left = 10;

			$customWrapper.css({
				top: top + 'px',
				left: left + 'px',
				width: width + 'px'
			});
		}

		var $originalOptions = $select.find('option:not([disabled])');
		var optionsHtml = '';

		$originalOptions.each(function () {
			var optionText = $(this).text();
			var optionValue = $(this).val();
			var isSelected = $(this).is(':selected');

			var selectedClass = isSelected ? 'mptbm-option-selected' : '';
			optionsHtml += '<div class="mptbm-custom-option ' + selectedClass + '" data-value="' + optionValue + '" style="padding: 8px !important; cursor: pointer !important; border-bottom: 1px solid #f5f5f5 !important; font-size: 14px !important; color: #222222 !important;">' + optionText + '</div>';
		});

		$optionsContainer.html(optionsHtml);

		$customWrapper.append($searchInput).append($optionsContainer);
		$scope.append($customWrapper);

		updateDropdownPosition();
		$(window).on('scroll resize', updateDropdownPosition);

		$scope.find('.mptbm_map_area').css('z-index', '1');
		$scope.find('.mptbm_map_area #mptbm_map_area').css('z-index', '1');

		$searchInput.focus();

		$searchInput.on('input', function () {
			var searchTerm = $(this).val().toLowerCase();
			var $opts = $customWrapper.find('.mptbm-custom-option');

			$opts.each(function () {
				var optionText = $(this).text().toLowerCase();
				if (optionText.includes(searchTerm) || searchTerm === '') {
					$(this).show();
				} else {
					$(this).hide();
				}
			});
		});

		$customWrapper.on('click', '.mptbm-custom-option', function () {
			var selectedValue = $(this).data('value');
			var selectedText = $(this).text();

			$select.val(selectedValue);
			$select.trigger('change');

			$searchInput.val(selectedText);
			$customWrapper.remove();

			$scope.find('.mptbm_map_area').css('z-index', '');
			$scope.find('.mptbm_map_area #mptbm_map_area').css('z-index', '');
		});

		$select.one('change', function () {
			$customWrapper.remove();
			$scope.find('.mptbm_map_area').css('z-index', '');
			$scope.find('.mptbm_map_area #mptbm_map_area').css('z-index', '');
		});

		$(document).one('click', function (e) {
			if (!$(e.target).closest('.mptbm-custom-select-wrapper, #' + selectId).length) {
				$customWrapper.remove();
				$scope.find('.mptbm_map_area').css('z-index', '');
				$scope.find('.mptbm_map_area #mptbm_map_area').css('z-index', '');
			}
		});

		var positionUpdateTimeout;
		var positionUpdateHandler = function () {
			clearTimeout(positionUpdateTimeout);
			positionUpdateTimeout = setTimeout(function () {
				updateDropdownPosition();
			}, 16);
		};

		$(window).on('resize.mptbm-dropdown', positionUpdateHandler);

		var originalRemove = $customWrapper.remove;
		$customWrapper.remove = function () {
			clearTimeout(positionUpdateTimeout);
			$(window).off('resize.mptbm-dropdown');
			$(window).off('scroll resize', updateDropdownPosition);
			return originalRemove.call(this);
		};

		$searchInput.on('keydown', function (e2) {
			if (e2.key === 'Escape') {
				$customWrapper.remove();
				$scope.find('.mptbm_map_area').css('z-index', '');
				$scope.find('.mptbm_map_area #mptbm_map_area').css('z-index', '');
			}
		});
	});
});
