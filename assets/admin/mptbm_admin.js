(function ($) {
	"use strict";
	$(document).on('change', '.mptbm_extra_services_setting [name="mptbm_extra_services_id"]', function () {
		let ex_id = $(this).val();
		let parent = $(this).closest('.mptbm_extra_services_setting');
		let target = parent.find('.mptbm_extra_service_area');
		let post_id = $('[name="mptbm_post_id"]').val();
		if (ex_id && post_id) {
			$.ajax({
				type: 'POST', url: mp_ajax_url, data: {
					"action": "get_mptbm_ex_service", "ex_id": ex_id, "post_id": post_id
				}, beforeSend: function () {
					dLoader(target);
				}, success: function (data) {
					target.html(data);
				}
			});
		} else {
			target.html('');
		}
	});

	// Seat layout preview functionality
	function generateSeatLayout() {
		var rows = parseInt($('#mptbm_seat_rows').val()) || 2;
		var columns = parseInt($('#mptbm_seat_columns').val()) || 2;
		var previewContainer = $('#seat-layout-preview');
		
		if (rows < 1 || columns < 1) {
			previewContainer.html('<p class="seat-layout-error">Please enter valid rows and columns (minimum 1 each)</p>');
			return;
		}
		
		var html = '<div class="seat-layout-container">';
		var seatNumber = 1;
		
		for (var row = 1; row <= rows; row++) {
			html += '<div class="seat-row" data-row="' + row + '">';
			html += '<span class="row-label">Row ' + row + '</span>';
			for (var col = 1; col <= columns; col++) {
				html += '<div class="seat" data-seat="' + seatNumber + '" title="Seat ' + seatNumber + '">';
				html += '<span class="seat-number">' + seatNumber + '</span>';
				html += '</div>';
				seatNumber++;
			}
			html += '</div>';
		}
		
		html += '</div>';
		html += '<div class="seat-layout-info">';
		html += '<p><strong>Total Seats:</strong> ' + (rows * columns) + '</p>';
		html += '<p><em>Seat numbers are assigned from left to right, top to bottom</em></p>';
		html += '</div>';
		
		previewContainer.html(html);
	}

	// Handle mutual exclusivity between inventory and seat plan
	function toggleMutualExclusivity() {
		var inventoryEnabled = $('#mptbm_enable_inventory').is(':checked');
		var seatPlanEnabled = $('#mptbm_enable_seat_plan').is(':checked');
		var $inventoryContainer = $('.mptbm_inventory_switch_container');
		var $seatPlanContainer = $('.mptbm_seat_plan_switch_container');
		var $inventorySection = $('[data-collapse="#mptbm_enable_inventory"]');
		var $seatPlanSection = $('[data-collapse="#mptbm_enable_seat_plan"]');
		
		if (inventoryEnabled) {
			// Disable seat plan when inventory is enabled
			$seatPlanContainer.addClass('disabled');
			$inventoryContainer.removeClass('disabled');
			if (seatPlanEnabled) {
				$('#mptbm_enable_seat_plan').prop('checked', false).trigger('change');
				$seatPlanSection.removeClass('mActive');
			}
		} else if (seatPlanEnabled) {
			// Disable inventory when seat plan is enabled
			$inventoryContainer.addClass('disabled');
			$seatPlanContainer.removeClass('disabled');
		} else {
			// Both are disabled, enable both options
			$inventoryContainer.removeClass('disabled');
			$seatPlanContainer.removeClass('disabled');
		}
	}

	$(document).ready(function() {
		// Generate initial layout if seat layout preview container exists
		if ($('#seat-layout-preview').length) {
			generateSeatLayout();
		}
		
		// Update layout when rows or columns change
		$(document).on('input change', '#mptbm_seat_rows, #mptbm_seat_columns', function() {
			generateSeatLayout();
		});

		// Handle mutual exclusivity between inventory and seat plan
		if ($('#mptbm_enable_inventory').length || $('#mptbm_enable_seat_plan').length) {
			// Initial check
			toggleMutualExclusivity();
			
			// Listen for inventory changes
			$(document).on('change', '#mptbm_enable_inventory', function() {
				toggleMutualExclusivity();
			});
			
			// Listen for seat plan changes
			$(document).on('change', '#mptbm_enable_seat_plan', function() {
				toggleMutualExclusivity();
			});
			
			// Prevent clicks on disabled switches
			$(document).on('click', '.mptbm_inventory_switch_container.disabled .switch_button, .mptbm_seat_plan_switch_container.disabled .switch_button', function(e) {
				e.preventDefault();
				e.stopPropagation();
				return false;
			});
		}
	});
	 
}(jQuery));