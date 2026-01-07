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
}(jQuery));