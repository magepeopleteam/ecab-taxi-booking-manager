let mptbm_map;
let mptbm_map_window;
function mptbm_set_cookie_distance_duration(start_place = '', end_place = '') {
	mptbm_map = new google.maps.Map(document.getElementById("mptbm_map_area"), {
			mapTypeControl: false,
			center: mp_lat_lng,
			zoom: 15,
		}
	);
	if (start_place && end_place) {
		let directionsService = new google.maps.DirectionsService();
		let directionsRenderer = new google.maps.DirectionsRenderer();
		directionsRenderer.setMap(mptbm_map);
		let request = {
			origin: start_place,
			destination: end_place,
			travelMode: google.maps.TravelMode.DRIVING,
			unitSystem: google.maps.UnitSystem.METRIC
		}
		let now = new Date();
		let time = now.getTime();
		let expireTime = time + 3600 * 1000 * 12;
		now.setTime(expireTime);
		directionsService.route(request, (result, status) => {
			if (status === google.maps.DirectionsStatus.OK) {
				let distance = result.routes[0].legs[0].distance.value;
				let distance_text = result.routes[0].legs[0].distance.text;
				let duration = result.routes[0].legs[0].duration.value;
				let duration_text = result.routes[0].legs[0].duration.text;
				// Build the set-cookie string:
				document.cookie = "mptbm_distance=" + distance + "; expires=" + now + "; path=/; ";
				document.cookie = "mptbm_distance_text=" + distance_text + "; expires=" + now + "; path=/; ";
				document.cookie = "mptbm_duration=" + duration + ";  expires=" + now + "; path=/; ";
				document.cookie = "mptbm_duration_text=" + duration_text + ";  expires=" + now + "; path=/; ";
				directionsRenderer.setDirections(result);
				//responseHandler(response.routes[0].legs[0].duration.value);
				jQuery('.mptbm_total_distance').html(distance_text);
				jQuery('.mptbm_total_time').html(duration_text);
				jQuery('.mptbm_distance_time').slideDown('fast');
			} else {
				directionsRenderer.setDirections({routes: []})
				//alert('location error');
			}
		});
	} else if (start_place || end_place) {
		let place = start_place ? start_place : end_place;
		mptbm_map_window = new google.maps.InfoWindow();
		map = new google.maps.Map(document.getElementById("mptbm_map_area"), {
			center: mp_lat_lng,
			zoom: 15,
		});
		const request = {
			query: place,
			fields: ["name", "geometry"],
		};
		service = new google.maps.places.PlacesService(map);
		service.findPlaceFromQuery(request, (results, status) => {
			if (status === google.maps.places.PlacesServiceStatus.OK && results) {
				for (let i = 0; i < results.length; i++) {
					mptbmCreateMarker(results[i]);
				}
				map.setCenter(results[0].geometry.location);
			}
		})
	} else {
		let directionsRenderer = new google.maps.DirectionsRenderer();
		directionsRenderer.setMap(mptbm_map);
		//document.getElementById('mptbm_map_start_place').focus();
	}
	return true;
}
function mptbmCreateMarker(place) {
	if (!place.geometry || !place.geometry.location) return;
	const marker = new google.maps.Marker({
		map,
		position: place.geometry.location,
	});
	google.maps.event.addListener(marker, "click", () => {
		mptbm_map_window.setContent(place.name || "");
		mptbm_map_window.open(map);
	});
}
(function ($) {
	"use strict";
	$(document).ready(function () {
		if ($('#mptbm_map_area').length > 0) {
			mptbm_set_cookie_distance_duration();
			if ($('#mptbm_map_start_place').length > 0 && $('#mptbm_map_end_place').length > 0) {
				let start_place = document.getElementById('mptbm_map_start_place');
				let end_place = document.getElementById('mptbm_map_end_place');
				let start_place_autoload = new google.maps.places.Autocomplete(start_place, mp_map_options);
				google.maps.event.addListener(start_place_autoload, 'place_changed', function () {
					mptbm_set_cookie_distance_duration(start_place.value, end_place.value);
				});
				let end_place_autoload = new google.maps.places.Autocomplete(end_place, mp_map_options);
				google.maps.event.addListener(end_place_autoload, 'place_changed', function () {
					mptbm_set_cookie_distance_duration(start_place.value, end_place.value);
				});
			}
		}
	});
	$(document).on("click", "#mptbm_get_vehicle", function () {
		let parent = $(this).closest('.mptbm_transport_search_area');
		let target = parent.find('.mptbm_map_search_result');
		let target_date = parent.find('#mptbm_map_start_date');
		let target_time = parent.find('#mptbm_map_start_time');
		let start_place;
		let end_place
		if (parent.find('#mptbm_map_start_place').length > 0 && parent.find('#mptbm_map_end_place').length > 0) {
			start_place = document.getElementById('mptbm_map_start_place');
			end_place = document.getElementById('mptbm_map_end_place');
		} else {
			start_place = document.getElementById('mptbm_manual_start_place');
			end_place = document.getElementById('mptbm_manual_end_place');
		}
		let start_date = target_date.val();
		let start_time = target_time.val();
		if (!start_date) {
			target_date.trigger('click');
		} else if (!start_time) {
			parent.find('#mptbm_map_start_time').closest('.mp_input_select').find('input.formControl').trigger('click');
		} else if (!start_place.value) {
			start_place.focus();
		} else if (!end_place.value) {
			end_place.focus();
		} else {
			dLoader(parent.find('.tabsContentNext'));
			mptbm_content_refresh(parent);
			mptbm_set_cookie_distance_duration(start_place.value, end_place.value);
			let price_based = parent.find('[name="mptbm_price_based"]').val();
			if (start_place.value && end_place.value && start_date && start_time) {
				$.ajax({
					type: 'POST',
					url: mp_ajax_url,
					data: {
						"action": "get_mptbm_map_search_result",
						"start_place": start_place.value,
						"end_place": end_place.value,
						"start_date": start_date,
						"start_time": start_time,
						"price_based": price_based,
					},
					beforeSend: function () {
						//dLoader(target);
					},
					success: function (data) {
						target.html(data).promise().done(function () {
							dLoaderRemove(parent.find('.tabsContentNext'));
							mp_sticky_management();
							parent.find('.nextTab_next').trigger('click');
						});
					},
					error: function (response) {
						console.log(response);
					}
				});
			}
		}
	});
	$(document).on("change", "#mptbm_manual_start_place", function () {
		let parent = $(this).closest('.mptbm_transport_search_area');
		mptbm_content_refresh(parent);
		let start_place = $(this).val();
		let target = parent.find('.mptbm_manual_end_place');
		if (start_place) {
			let end_place = '';
			let price_based = parent.find('[name="mptbm_price_based"]').val();
			if (price_based === 'manual') {
				let post_id = parent.find('[name="mptbm_post_id"]').val();
				$.ajax({
					type: 'POST',
					url: mp_ajax_url,
					data: {
						"action": "get_mptbm_end_place",
						"start_place": start_place,
						"price_based": price_based,
						"post_id": post_id,
					},
					beforeSend: function () {
						dLoader(target.closest('.mpForm'));
					},
					success: function (data) {
						target.html(data).promise().done(function () {
							dLoaderRemove(target.closest('.mpForm'));
						});
					},
					error: function (response) {
						console.log(response);
					}
				}).promise().done(function () {
					end_place = parent.find("#mptbm_manual_start_place").val();
					mptbm_set_cookie_distance_duration(start_place, end_place);
				});
			} else {
				end_place = $("#mptbm_manual_start_place").val();
				mptbm_set_cookie_distance_duration(start_place, end_place);
			}
		}
	});
	$(document).on("change", "#mptbm_manual_end_place", function () {
		let parent = $(this).closest('.mptbm_transport_search_area');
		mptbm_content_refresh(parent);
		let start_place = parent.find("#mptbm_manual_start_place").val();
		let end_place = $(this).val();
		if (end_place) {
			mptbm_set_cookie_distance_duration(start_place, end_place);
		}
	});
	$(document).on("change", "#mptbm_map_start_place,#mptbm_map_end_place", function () {
		let parent = $(this).closest('.mptbm_transport_search_area');
		mptbm_content_refresh(parent);
		let start_place = parent.find('#mptbm_map_start_place').val();
		let end_place = parent.find('#mptbm_map_end_place').val();
		if (start_place || end_place) {
			if (start_place) {
				mptbm_set_cookie_distance_duration(start_place);
				parent.find('#mptbm_map_end_place').focus();
			} else {
				mptbm_set_cookie_distance_duration(end_place);
				parent.find('#mptbm_map_start_place').focus();
			}
		} else {
			parent.find('#mptbm_map_start_place').focus();
		}
	});
	$(document).on("change", "#mptbm_map_start_date", function () {
		let parent = $(this).closest('.mptbm_transport_search_area');
		mptbm_content_refresh(parent);
		parent.find('#mptbm_map_start_time').closest('.mp_input_select').find('input.formControl').trigger('click');
	});
	$(document).on("change", "#mptbm_map_start_time", function () {
		let parent = $(this).closest('.mptbm_transport_search_area');
		mptbm_content_refresh(parent);
		parent.find('#mptbm_map_start_place').focus();
	});
}(jQuery));
function mptbm_content_refresh(parent) {
	parent.find('[name="mptbm_post_id"]').val('');
	parent.find('.mptbm_map_search_result').html('');
	parent.find('.mptbm_order_summary').html('');
	parent.find('.get_details_next_link').slideUp('fast');
}
//=======================//
function mptbm_price_calculation(parent) {
	let target_summary = parent.find('.mptbm_transport_summary');
	let total = 0;
	let post_id = parseInt(parent.find('[name="mptbm_post_id"]').val());
	if (post_id > 0) {
		total = total + parseFloat(parent.find('[name="mptbm_post_id"]').attr('data-price'));
		parent.find('.mptbm_extra_service_item').each(function () {
			let service_name = jQuery(this).find('[name="mptbm_extra_service[]"]').val();
			if (service_name) {
				let ex_target = jQuery(this).find('[name="mptbm_extra_service_qty[]');
				let ex_qty = parseInt(ex_target.val());
				let ex_price = ex_target.data('price');
				ex_price = ex_price && ex_price > 0 ? ex_price : 0;
				total = total + parseFloat(ex_price) * ex_qty;
			}
		});
	}
	target_summary.find('.mptbm_product_total_price').html(mp_price_format(total));
}
(function ($) {
	$(document).on('click', '.mptbm_transport_search_area .mptbm_transport_select', function () {
		let $this = $(this);
		let parent = $this.closest('.mptbm_transport_search_area');
		let target_summary = parent.find('.mptbm_transport_summary');
		let target_extra_service = parent.find('.mptbm_extra_service');
		let target_extra_service_summary = parent.find('.mptbm_extra_service_summary');
		target_summary.slideUp(350);
		target_extra_service.slideUp(350).html('');
		target_extra_service_summary.slideUp(350).html('');
		parent.find('[name="mptbm_post_id"]').val('');
		parent.find('.mptbm_order_summary').html('');
		if ($this.hasClass('active_select')) {
			$this.removeClass('active_select');
			mp_all_content_change($this);
		} else {
			parent.find('.mptbm_transport_select.active_select').each(function () {
				$(this).removeClass('active_select');
				mp_all_content_change($(this));
			}).promise().done(function () {
				let transport_name = $this.attr('data-transport-name');
				let transport_price = parseFloat($this.attr('data-transport-price'));
				let post_id = $this.attr('data-post-id');
				target_summary.find('.mptbm_product_name').html(transport_name);
				target_summary.find('.mptbm_product_price').html(mp_price_format(transport_price));
				$this.addClass('active_select');
				mp_all_content_change($this);
				parent.find('[name="mptbm_post_id"]').val(post_id).attr('data-price', transport_price).promise().done(function () {
					mptbm_price_calculation(parent);
				});
				$.ajax({
					type: 'POST',
					url: mp_ajax_url,
					data: {
						"action": "get_mptbm_extra_service",
						"post_id": post_id,
					},
					beforeSend: function () {
						dLoader(parent.find('.tabsContentNext'));
					},
					success: function (data) {
						target_extra_service.html(data);
					},
					error: function (response) {
						console.log(response);
					}
				}).promise().done(function () {
					$.ajax({
						type: 'POST',
						url: mp_ajax_url,
						data: {
							"action": "get_mptbm_extra_service_summary",
							"post_id": post_id,
						},
						success: function (data) {
							target_extra_service_summary.html(data).promise().done(function () {
								target_summary.slideDown(350);
								target_extra_service.slideDown(350);
								target_extra_service_summary.slideDown(350);
								pageScrollTo(target_extra_service);
								dLoaderRemove(parent.find('.tabsContentNext'));
							});
						},
						error: function (response) {
							console.log(response);
						}
					});
				});
			});
		}
	});
	$(document).on('click', '.mptbm_transport_search_area .mptbm_price_calculation', function () {
		mptbm_price_calculation($(this).closest('.mptbm_transport_search_area'));
	});
	//========Extra service==============//
	$(document).on('change', '.mptbm_transport_search_area [name="mptbm_extra_service_qty[]"]', function () {
		$(this).closest('.mptbm_extra_service_item').find('[name="mptbm_extra_service[]"]').trigger('change');
	});
	$(document).on('change', '.mptbm_transport_search_area [name="mptbm_extra_service[]"]', function () {
		let parent = $(this).closest('.mptbm_transport_search_area');
		let service_name = $(this).data('value');
		let service_value = $(this).val();
		if (service_value) {
			let qty = $(this).closest('.mptbm_extra_service_item').find('[name="mptbm_extra_service_qty[]"]').val();
			parent.find('[data-extra-service="' + service_name + '"]').slideDown(350).find('.ex_service_qty').html('x' + qty);
		} else {
			parent.find('[data-extra-service="' + service_name + '"]').slideUp(350);
		}
		mptbm_price_calculation(parent);
	});
	//===========================//
	$(document).on('click', '.mptbm_transport_search_area .mptbm_get_vehicle_prev', function () {
		let parent = $(this).closest('.mptbm_transport_search_area');
		parent.find('.get_details_next_link').slideDown('fast');
		parent.find('.nextTab_prev').trigger('click');
	});
	$(document).on('click', '.mptbm_transport_search_area .mptbm_summary_prev', function () {
		let parent = $(this).closest('.mptbm_transport_search_area');
		parent.find('.nextTab_prev').trigger('click');
	});
	//===========================//
	$(document).on("click", ".mptbm_book_now[type='button']", function () {
		let parent = $(this).closest('.mptbm_transport_search_area');
		let target_checkout = parent.find('.mptbm_order_summary');
		let start_place = parent.find('[name="mptbm_start_place"]').val();
		let end_place = parent.find('[name="mptbm_end_place"]').val();
		let post_id = parent.find('[name="mptbm_post_id"]').val();
		let date = parent.find('[name="mptbm_date"]').val();
		let link_id = $(this).attr('data-wc_link_id');
		if (start_place !== '' && end_place !== '' && link_id && post_id) {
			let extra_service_name = {};
			let extra_service_qty = {};
			let count = 0;
			parent.find('[name="mptbm_extra_service[]"]').each(function () {
				let ex_name = $(this).val();
				if (ex_name) {
					extra_service_name[count] = ex_name;
					let ex_qty = parseInt($(this).closest('.mptbm_extra_service_item').find('[name="mptbm_extra_service_qty[]"]').val());
					ex_qty = ex_qty > 0 ? ex_qty : 1;
					extra_service_qty[count] = ex_qty;
					count++;
				}
			});
			$.ajax({
				type: 'POST',
				url: mp_ajax_url,
				data: {
					"action": "mptbm_add_to_cart",
					//"product_id": post_id,
					"link_id": link_id,
					"mptbm_start_place": start_place,
					"mptbm_end_place": end_place,
					"mptbm_date": date,
					"mptbm_extra_service": extra_service_name,
					"mptbm_extra_service_qty": extra_service_qty,
				},
				beforeSend: function () {
					dLoader(parent.find('.tabsContentNext'));
				},
				success: function (data) {
					target_checkout.html(data);
					dLoaderRemove(parent.find('.tabsContentNext'));
					$(document.body).trigger('init_checkout');
					$('body #billing_country').select2({});
					$('body #billing_state').select2({});
					parent.find('.nextTab_next').trigger('click');
				},
				error: function (response) {
					console.log(response);
				}
			});
		}
	});
}(jQuery));
function gm_authFailure() { alert('Admin use Invalid Google Api Key . So, Google Map not working !'); }