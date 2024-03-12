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
	 
}(jQuery));