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
			} else {
				directionsRenderer.setDirections({routes: []})
				alert('location error');
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
					createMarker(results[i]);
				}
				map.setCenter(results[0].geometry.location);
			}
		})
	} else {
		let directionsRenderer = new google.maps.DirectionsRenderer();
		directionsRenderer.setMap(mptbm_map);
		//document.getElementById('mptbm_map_start_place').focus();
	}
}

function createMarker(place) {
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
	$(document).on("click", "#mptbm-get-vehicle", function () {
		let start_date = $('#mptbm_map_start_date').val();
		let start_time = $('#mptbm_map_start_time').val();
		let start_place;
		let end_place
		if ($('#mptbm_map_start_place').length > 0 && $('#mptbm_map_end_place').length > 0) {
			start_place = document.getElementById('mptbm_map_start_place');
			end_place = document.getElementById('mptbm_map_end_place');
		}else{
			start_place = document.getElementById('mptbm_manual_start_place');
			end_place = document.getElementById('mptbm_manual_end_place');
		}

		if (!start_date) {
			$('#mptbm_map_start_date').trigger('click');
		} else if (!start_time) {
			$('#mptbm_map_start_time').trigger('click');
		} else if (!start_place.value) {
			start_place.focus();
		} else if (!end_place.value) {
			end_place.focus();
		} else {
			let price_based = $('[name="mptbm_price_based"]').val();
			let post_id = $('[name="mptbm_filter_post_id"]').val();
			mptbm_set_cookie_distance_duration(start_place.value, end_place.value);
			let target = $('.mptbm_map_search_result');
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
						"post_id": post_id
					},
					beforeSend: function () {
						dLoader(target);
					},
					success: function (data) {
						target.html(data).promise().done(function () {
							loadBgImage();
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
		let start_place = $(this).val();
		let target = $('.mptbm_manual_end_place');
		if (start_place) {
			let end_place='';
			let price_based = $('[name="mptbm_price_based"]').val();
			if(price_based==='manual') {
				let post_id = $('[name="mptbm_filter_post_id"]').val();
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
					{
						end_place = $("#mptbm_manual_start_place").val();
						mptbm_set_cookie_distance_duration(start_place,end_place);
					}
				});
			}else{
				end_place = $("#mptbm_manual_start_place").val();
				mptbm_set_cookie_distance_duration(start_place,end_place);
			}
		}
	});
	$(document).on("change", "#mptbm_manual_end_place", function () {
		let start_place = $("#mptbm_manual_start_place").val();
		let end_place = $(this).val();
		if (end_place) {
			mptbm_set_cookie_distance_duration(start_place, end_place);
		}
	});
	//=======================//
}(jQuery));
//==========Date picker=================//
(function ($) {
	$(document).ready(function () {
		$(".mpStyle .date_type").datepicker({
			dateFormat: mp_date_format,
			autoSize: true,
			minDate: 0,
			onSelect: function (dateString, data) {
				let date = data.selectedYear + '-' + (parseInt(data.selectedMonth) + 1) + '-' + data.selectedDay;
				$(this).closest('label').find('[type="hidden"]').val(date);
			}
		});
	});
}(jQuery));
