(function ($) {
	"use strict";
	
	// Handle extra services setting
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
	
	// Fix for Select2 tooltip conflicts in admin settings
	$(document).ready(function() {
		// Initialize Select2 with proper configuration
		$('.mp_select2').select2({
			closeOnSelect: false,
			allowClear: true,
			width: '100%'
		});
		
		// Prevent tooltips on Select2 elements
		$(document).on('mouseenter', '.select2-container *, .select2-selection *, .select2-dropdown *', function(e) {
			e.stopPropagation();
			return false;
		});
		
		// Remove title attributes from Select2 elements
		$(document).on('select2:open', function() {
			$('.select2-container *').removeAttr('title');
		});
		
		// Handle Select2 choice removal
		$(document).on('click', '.select2-selection__choice__remove', function(e) {
			e.stopPropagation();
		});
	});
	//==== Live search icon======
	const searchInputIcon = document.getElementById('searchInputIcon');
	if (searchInputIcon) {
		searchInputIcon.addEventListener('input', function () {
			const filter = this.value.toLowerCase();
			const items = document.querySelectorAll('.popupTabItem .itemIconArea .iconItem');
			items.forEach(item => {
				const text = item.getAttribute('title')?.toLowerCase() || '';
				item.style.display = text.includes(filter) ? '' : 'none';
			});
		});
	}
	
    $(document).on('click', '.mptbm-sidebar-toggle', function() {
        $('.mptbm-sidebar-toggle i').toggleClass('mi-angle-right mi-angle-left');
        $('.tabLists.mptbm-sidebar').closest('.leftTabs').toggleClass('leftTabs-collapsed');
        $('.tabLists.mptbm-sidebar').toggleClass('mptbm-sidebar-collapsed');
    });



//NEW Feature
	const mptbm_areas = JSON.parse($("#mptbm_operation_zones").val());
	let selectedAreas = [];
	// Add Row
	$(document).on("click","#addPrice", function (e) {
		e.preventDefault();
		addRow();
	});
	function addRow() {
		let availableAreas = {};

		$.each(mptbm_areas, function (key, value) {
			if (!selectedAreas.includes(key)) {
				availableAreas[key] = value;
			}
		});
		if (Object.keys(availableAreas).length === 0) {
			alert("All areas already added");
			return;
		}

		let options = '<option value="">Select Area</option>';

		$.each(availableAreas, function (key, value) {
			options += `<option value="${key}">${value}</option>`;
		});

		let row = $(`
        <div class="row">
            <select class="areaSelect">${options}</select>
            <input type="number" class="fixed" placeholder="Fixed Price">
            <input type="number" class="km" placeholder="Per KM">
            <input type="number" class="hour" placeholder="Per Hour">
            <button class="remove">Remove</button>
        </div>
    `);

		$("#priceContainer").append(row);
	}
	// Select Area
	$(document).on("change", ".areaSelect", function () {
		let val = $(this).val();

		if (val && !selectedAreas.includes(val)) {
			selectedAreas.push(val);
		}
	});
	// Remove Row
	$(document).on("click", ".remove", function () {
		let row = $(this).closest(".row");
		let area = row.find(".areaSelect").val();

		if (area) {
			selectedAreas = selectedAreas.filter(a => a !== area);
		}

		row.remove();
	});
	// Save Data
	$(document).on("click", "#saveData", function (e) {
		e.preventDefault();

		let area_price_data = {};

		$("#priceContainer .row").each(function () {

			let area = $(this).find(".areaSelect").val();
			let fixed_price = $(this).find(".fixed").val();
			let km_price = $(this).find(".km").val();
			let hour_price = $(this).find(".hour").val();

			if (area) {
				area_price_data[area] = {
					fixed: fixed_price,
					per_km: km_price,
					per_hour: hour_price
				};
			}
		});

		let post_id = $('[name="mptbm_post_id"]').val();
		let target = $("#result"); // make sure this exists

		$.ajax({
			type: 'POST',
			url: mp_ajax_url,
			data: {
				action: "mptbm_operation_area_price_data_set",
				area_price_data: JSON.stringify(area_price_data),
				post_id: post_id,
				nonce: MPTBM_Ajax.nonce
			},
			beforeSend: function () {
				console.log("Sending...", area_price_data);
			},
			success: function (res) {
				console.log(res);
				target.html("Saved successfully");
			},
			error: function (err) {
				console.log("Error:", err);
			}
		});
	});

}(jQuery));