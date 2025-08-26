let mptbm_map;
let mptbm_map_window;
function mptbm_set_cookie_distance_duration(start_place = "", end_place = "") {
    mptbm_map = new google.maps.Map(document.getElementById("mptbm_map_area"), {
        mapTypeControl: false,
        center: mp_lat_lng,
        zoom: 15,
    });
    if (start_place && end_place) {
        let directionsService = new google.maps.DirectionsService();
        let directionsRenderer = new google.maps.DirectionsRenderer();
        directionsRenderer.setMap(mptbm_map);
        let request = {
            origin: start_place,
            destination: end_place,
            travelMode: google.maps.TravelMode.DRIVING,
            unitSystem: google.maps.UnitSystem.METRIC,
        };
        let now = new Date();
        let time = now.getTime();
        let expireTime = time + 3600 * 1000 * 12;
        now.setTime(expireTime);
        directionsService.route(request, (result, status) => {
            if (status === google.maps.DirectionsStatus.OK) {
                let distance = result.routes[0].legs[0].distance.value;
                let kmOrMile = document.getElementById("mptbm_km_or_mile").value;
                let distance_text = result.routes[0].legs[0].distance.text;
                let duration = result.routes[0].legs[0].duration.value;
                var duration_text = result.routes[0].legs[0].duration.text;
                if (kmOrMile == 'mile') {
                    // Convert distance from kilometers to miles
                    var distanceInKilometers = distance / 1000;
                    var distanceInMiles = distanceInKilometers * 0.621371;
                    distance_text = distanceInMiles.toFixed(1) + ' miles'; // Format to 2 decimal places
                }
                // Build the set-cookie string:
                document.cookie =
                    "mptbm_distance=" + distance + "; expires=" + now + "; path=/; ";
                document.cookie =
                    "mptbm_distance_text=" +
                    distance_text +
                    "; expires=" +
                    now +
                    "; path=/; ";
                document.cookie =
                    "mptbm_duration=" + duration + ";  expires=" + now + "; path=/; ";
                document.cookie =
                    "mptbm_duration_text=" +
                    duration_text +
                    ";  expires=" +
                    now +
                    "; path=/; ";
                directionsRenderer.setDirections(result);
                jQuery(".mptbm_total_distance").html(distance_text);
                jQuery(".mptbm_total_time").html(duration_text);
                jQuery(".mptbm_distance_time").slideDown("fast");
            } else {
                //directionsRenderer.setDirections({routes: []})
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
        });
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
function mptbm_map_area_init() {
    mptbm_set_cookie_distance_duration();

    if (
        jQuery("#mptbm_map_start_place").length > 0 &&
        jQuery("#mptbm_map_end_place").length > 0
    ) {
        let start_place = document.getElementById("mptbm_map_start_place");
        let end_place = document.getElementById("mptbm_map_end_place");

        let start_place_autoload = new google.maps.places.Autocomplete(start_place);
        let mptbm_restrict_search_to_country = jQuery('[name="mptbm_restrict_search_country"]').val();
        let mptbm_country = jQuery('[name="mptbm_country"]').val();

        if (mptbm_restrict_search_to_country == 'yes') {
            start_place_autoload.setComponentRestrictions({
                country: [mptbm_country]
            });
        }

        google.maps.event.addListener(start_place_autoload, "place_changed", function () {
            mptbm_set_cookie_distance_duration(
                start_place.value,
                end_place.value
            );
        });

        let end_place_autoload = new google.maps.places.Autocomplete(end_place);
        if (mptbm_restrict_search_to_country == 'yes') {
            end_place_autoload.setComponentRestrictions({
                country: [mptbm_country]
            });
        }

        google.maps.event.addListener(end_place_autoload, "place_changed", function () {
            mptbm_set_cookie_distance_duration(
                start_place.value,
                end_place.value
            );
        });
    }
}
(function ($) {
    "use strict";
    $(document).ready(function () {
        $(".mpStyle ul.mp_input_select_list").hide();

        if ($("#mptbm_map_area").length > 0) {
            mptbm_map_area_init();
        }
    });
    $(document).on("click", "#mptbm_get_vehicle", function () {
        let parent = $(this).closest(".mptbm_transport_search_area");
        let mptbm_enable_return_in_different_date = parent
            .find('[name="mptbm_enable_return_in_different_date"]')
            .val();

        let target = parent.find(".tabsContentNext");
        let target_date = parent.find("#mptbm_map_start_date");
        let return_target_date = parent.find("#mptbm_map_return_date");
        let target_time = parent.find("#mptbm_map_start_time");
        let return_target_time = parent.find("#mptbm_map_return_time");
        let start_place;
        let end_place;
        let price_based = parent.find('[name="mptbm_price_based"]').val();
        let two_way = parent.find('[name="mptbm_taxi_return"]').val();
        let waiting_time = parent.find('[name="mptbm_waiting_time"]').val();
        let fixed_time = parent.find('[name="mptbm_fixed_hours"]').val();
        
        let mptbm_enable_view_search_result_page = parent
            .find('[name="mptbm_enable_view_search_result_page"]')
            .val();
        if (price_based === "manual") {
            // Get Select2 values properly for manual mode
            let start_place_select = parent.find("#mptbm_manual_start_place");
            let end_place_select = parent.find("#mptbm_manual_end_place");
            
            // Check if Select2 is applied and get the value accordingly
            if (start_place_select.hasClass('select2-hidden-accessible')) {
                // Try different methods to get Select2/SelectWoo value
                let start_val = null;
                
                // Try selectWoo first
                if (typeof start_place_select.selectWoo === 'function') {
                    start_val = start_place_select.selectWoo('val');
                }
                // Fallback to select2
                if (!start_val && typeof start_place_select.select2 === 'function') {
                    start_val = start_place_select.select2('val');
                }
                // Fallback to regular val()
                if (!start_val) {
                    start_val = start_place_select.val();
                }
                // Final fallback to selected option
                if (!start_val) {
                    start_val = start_place_select.find('option:selected').val();
                }
                
                start_place = { value: start_val };
            } else {
                start_place = { value: start_place_select.val() };
            }
            
            if (end_place_select.hasClass('select2-hidden-accessible')) {
                // Try different methods to get Select2/SelectWoo value
                let end_val = null;
                
                // Try selectWoo first
                if (typeof end_place_select.selectWoo === 'function') {
                    end_val = end_place_select.selectWoo('val');
                }
                // Fallback to select2
                if (!end_val && typeof end_place_select.select2 === 'function') {
                    end_val = end_place_select.select2('val');
                }
                // Fallback to regular val()
                if (!end_val) {
                    end_val = end_place_select.val();
                }
                // Final fallback to selected option
                if (!end_val) {
                    end_val = end_place_select.find('option:selected').val();
                }
                
                end_place = { value: end_val };
            } else {
                end_place = { value: end_place_select.val() };
            }
        } else {
            start_place = document.getElementById("mptbm_map_start_place");
            end_place = document.getElementById("mptbm_map_end_place");
        }
        let start_date = target_date.val();
        let return_date;
        let return_time;

        if (mptbm_enable_return_in_different_date == 'yes' && two_way != 1 && price_based != 'fixed_hourly') {
            return_date = return_target_date.val();
            return_time = return_target_time.val();
            
            // Get the actual time from the data-time attribute (consistent with start_time)
            let selectedReturnTimeElement = parent.find("#mptbm_map_return_time").closest(".mp_input_select").find("li[data-value='" + return_time + "']");
            if (selectedReturnTimeElement.length) {
                return_time = selectedReturnTimeElement.attr('data-time');
            }
            
        } else {
            return_date = start_date;
            return_time = 'Not applicable';
        }
        let start_time = target_time.val();
        // Get the actual time from the data-time attribute
        let selectedTimeElement = parent.find("#mptbm_map_start_time").closest(".mp_input_select").find("li[data-value='" + start_time + "']");
        if (selectedTimeElement.length) {
            start_time = selectedTimeElement.attr('data-time');
            
        }
        
        if (!start_date) {
            target_date.trigger("click");
        } else if (start_time === undefined || start_time === null || start_time === '') {
            parent
                .find("#mptbm_map_start_time")
                .closest(".mp_input_select")
                .find("input.formControl")
                .trigger("click");
        } else if (!return_date) {
            if (mptbm_enable_return_in_different_date == 'yes' && two_way != 1) {
                return_target_date.trigger("click");
            }
        } else if (return_time === undefined || return_time === null || return_time === '') {
            if (mptbm_enable_return_in_different_date == 'yes' && two_way != 1) {
                parent
                    .find("#mptbm_map_return_time")
                    .closest(".mp_input_select")
                    .find("input.formControl")
                    .trigger("click");
            }
        } else if (!start_place.value) {
            if (price_based === "manual") {
                parent.find("#mptbm_manual_start_place").focus();
            } else {
            start_place.focus();
            }
        } else if (!end_place.value) {
            if (price_based === "manual") {
                parent.find("#mptbm_manual_end_place").focus();
            } else {
            end_place.focus();
            }
        } else {
            dLoader(parent.find(".tabsContentNext"));
            mptbm_content_refresh(parent);
            if (price_based !== "manual") {
                mptbm_set_cookie_distance_duration(start_place.value, end_place.value);
            }
            //let price_based = parent.find('[name="mptbm_price_based"]').val();
            function getGeometryLocation(address, callback) {
                var geocoder = new google.maps.Geocoder();
                var coordinatesOfPlace = {};
                geocoder.geocode({ address: address }, function (results, status) {
                    if (status === "OK") {
                        var latitude = results[0].geometry.location.lat();
                        var longitude = results[0].geometry.location.lng();
                        coordinatesOfPlace["latitude"] = latitude;
                        coordinatesOfPlace["longitude"] = longitude;
                        // Call the callback function with the coordinates
                        callback(coordinatesOfPlace);
                    } else {
                        console.error(
                            "Geocode was not successful for the following reason: " + status
                        );
                        // Call the callback function with null to indicate failure
                        callback(null);
                    }
                });
            }
            // Define a function to get the coordinates asynchronously and return a Deferred object
            
            function getCoordinatesAsync(address) {
                var deferred = $.Deferred();
                getGeometryLocation(address, function (coordinates) {
                    deferred.resolve(coordinates);
                });
                return deferred.promise();
            }
            if (price_based !== 'manual') {

                $.when(
                    getCoordinatesAsync(start_place.value),
                    getCoordinatesAsync(end_place.value)
                ).done(function (startCoordinates, endCoordinates) {
                    if (start_place.value && end_place.value && start_date && 
                        (start_time !== undefined && start_time !== null && start_time !== '') && 
                        return_date && 
                        (return_time !== undefined && return_time !== null && return_time !== '')) {
                        let actionValue;
                        if (!mptbm_enable_view_search_result_page) {
                            actionValue = "get_mptbm_map_search_result";
                            $.ajax({
                                type: "POST",
                                url: mp_ajax_url,
                                data: {
                                    action: actionValue,
                                    start_place: start_place.value,
                                    start_place_coordinates: startCoordinates,
                                    end_place_coordinates: endCoordinates,
                                    end_place: end_place.value,
                                    start_date: start_date,
                                    start_time: start_time,
                                    price_based: price_based,
                                    two_way: two_way,
                                    waiting_time: waiting_time,
                                    fixed_time: fixed_time,
                                    return_date: return_date,
                                    return_time: return_time,
                                    mptbm_passengers: parent.find('#mptbm_passengers').val(),
                                    mptbm_max_passenger: parent.find('#mptbm_max_passenger').val(),
                                    mptbm_max_bag: parent.find('#mptbm_max_bag').val(),
                                },
                                beforeSend: function () {
                                    //dLoader(target);
                                },
                                success: function (data) {
                                    target
                                        .append(data)
                                        .promise()
                                        .done(function () {
                                            dLoaderRemove(parent.find(".tabsContentNext"));
                                            parent.find(".nextTab_next").trigger("click");
                                            // iOS DOM reflow workaround
                                            if (mptbm_is_ios()) {
                                                target[0].style.display = 'none';
                                                void target[0].offsetHeight;
                                                target[0].style.display = '';
                                            }
                                        });
                                },
                                error: function (response) {
                                    console.log(response);
                                },
                            });
                        } else {
                            actionValue = "get_mptbm_map_search_result_redirect";
                            $.ajax({
                                type: "POST",
                                url: mp_ajax_url,
                                data: {
                                    action: actionValue,
                                    start_place: start_place.value,
                                    start_place_coordinates: startCoordinates,
                                    end_place_coordinates: endCoordinates,
                                    end_place: end_place.value,
                                    start_date: start_date,
                                    start_time: start_time,
                                    price_based: price_based,
                                    two_way: two_way,
                                    waiting_time: waiting_time,
                                    fixed_time: fixed_time,
                                    return_date: return_date,
                                    return_time: return_time,
                                    mptbm_enable_view_search_result_page: mptbm_enable_view_search_result_page,
                                    mptbm_passengers: parent.find('#mptbm_passengers').val(),
                                    mptbm_max_passenger: parent.find('#mptbm_max_passenger').val(),
                                    mptbm_max_bag: parent.find('#mptbm_max_bag').val(),
                                },
                                beforeSend: function () {
                                    dLoader(target);
                                },
                                success: function (data) {
                                    var cleanedURL = data.replace(/"/g, ""); // Remove all double quotes from the string
                                    window.location.href = cleanedURL; // Redirect to the URL received from the server
                                },
                                error: function (response) {
                                    console.log(response);
                                },
                            });
                        }
                    }
                });
            } else {

                if (start_place.value && end_place.value && start_date && 
                    (start_time !== undefined && start_time !== null && start_time !== '') && 
                    return_date && 
                    (return_time !== undefined && return_time !== null && return_time !== '')) {

                    let actionValue;
                    if (!mptbm_enable_view_search_result_page) {
                        actionValue = "get_mptbm_map_search_result";
                        $.ajax({
                            type: "POST",
                            url: mp_ajax_url,
                            data: {
                                action: actionValue,
                                start_place: start_place.value,
                                end_place: end_place.value,
                                start_date: start_date,
                                start_time: start_time,
                                price_based: price_based,
                                two_way: two_way,
                                waiting_time: waiting_time,
                                fixed_time: fixed_time,
                                return_date: return_date,
                                return_time: return_time,
                                mptbm_passengers: parent.find('#mptbm_passengers').val(),
                                mptbm_max_passenger: parent.find('#mptbm_max_passenger').val(),
                                mptbm_max_bag: parent.find('#mptbm_max_bag').val(),
                            },
                            beforeSend: function () {
                                //dLoader(target);
                            },
                            success: function (data) {
                                target
                                    .append(data)
                                    .promise()
                                    .done(function () {
                                        dLoaderRemove(parent.find(".tabsContentNext"));
                                        parent.find(".nextTab_next").trigger("click");
                                        // iOS DOM reflow workaround
                                        if (mptbm_is_ios()) {
                                            target[0].style.display = 'none';
                                            void target[0].offsetHeight;
                                            target[0].style.display = '';
                                        }
                                    });
                            },
                            error: function (response) {
                                console.log(response);
                            },
                        });
                    } else {
                        actionValue = "get_mptbm_map_search_result_redirect";
                        $.ajax({
                            type: "POST",
                            url: mp_ajax_url,
                            data: {
                                action: actionValue,
                                start_place: start_place.value,
                                end_place: end_place.value,
                                start_date: start_date,
                                start_time: start_time,
                                price_based: price_based,
                                two_way: two_way,
                                waiting_time: waiting_time,
                                fixed_time: fixed_time,
                                return_date: return_date,
                                return_time: return_time,
                                mptbm_enable_view_search_result_page: mptbm_enable_view_search_result_page,
                                mptbm_passengers: parent.find('#mptbm_passengers').val(),
                                mptbm_max_passenger: parent.find('#mptbm_max_passenger').val(),
                                mptbm_max_bag: parent.find('#mptbm_max_bag').val(),
                            },
                            beforeSend: function () {
                                dLoader(target);
                            },
                            success: function (data) {
                                var cleanedURL = data.replace(/"/g, ""); // Remove all double quotes from the string
                                window.location.href = cleanedURL; // Redirect to the URL received from the server
                            },
                            error: function (response) {
                                console.log(response);
                            },
                        });
                    }
                }
            }
        }
    });
    $(document).on("change", "#mptbm_map_start_date", function () {
        // Clear the time slots list

        $('#mptbm_map_start_time').siblings('.start_time_list').empty();
        $('.start_time_input,#mptbm_map_start_time').val('');
        let mptbm_enable_return_in_different_date = $('[name="mptbm_enable_return_in_different_date"]').val();
        let mptbm_buffer_end_minutes = $('[name="mptbm_buffer_end_minutes"]').val();
        let mptbm_first_calendar_date = $('[name="mptbm_first_calendar_date"]').val();

        var selectedDate = $('#mptbm_map_start_date').val();
        var formattedDate = $.datepicker.parseDate('yy-mm-dd', selectedDate);

        // Get today's date in YYYY-MM-DD format
        var today = new Date();
        var day = String(today.getDate()).padStart(2, '0');
        var month = String(today.getMonth() + 1).padStart(2, '0');
        var year = today.getFullYear();
        var currentDate = year + '-' + month + '-' + day;

        if (selectedDate == currentDate) {
            var currentTime = new Date();
            var currentHour = currentTime.getHours();
            var currentMinutes = currentTime.getMinutes();

            // Format minutes to always have two digits (e.g., 5 -> 05)
            var formattedMinutes = String(currentMinutes).padStart(2, '0');

            // Combine hours and formatted minutes
            var currentTimeFormatted = currentHour + '.' + formattedMinutes;
            $('.start_time_list-no-dsiplay li').each(function () {
                const timeValue = parseFloat($(this).attr('data-value'));
                if (timeValue > parseFloat(currentTimeFormatted) && timeValue >= mptbm_buffer_end_minutes / 60) {
                    $('#mptbm_map_start_time').siblings('.start_time_list').append($(this).clone());
                }
            });
        } else {
            if (selectedDate == mptbm_first_calendar_date) {
                console.log(mptbm_first_calendar_date);
                $('.start_time_list-no-dsiplay li').each(function () {
                    const timeValue = parseFloat($(this).attr('data-value'));
                    if (timeValue >= mptbm_buffer_end_minutes / 60) {
                        $('#mptbm_map_start_time').siblings('.start_time_list').append($(this).clone());
                    }
                });
            } else {
                $('.start_time_list-no-dsiplay li').each(function () {
                    $('#mptbm_map_start_time').siblings('.start_time_list').append($(this).clone());
                });
            }


        }

        // Update the return date picker if needed
        if (mptbm_enable_return_in_different_date == 'yes') {
            $('#mptbm_return_date').datepicker('option', 'minDate', formattedDate);
        }

        let parent = $(this).closest(".mptbm_transport_search_area");
        mptbm_content_refresh(parent);
        parent
            .find("#mptbm_map_start_time")
            .closest(".mp_input_select")
            .find("input.formControl")
            .trigger("click");
    });


    $(document).on("change", "#mptbm_map_return_date", function () {
        let mptbm_enable_return_in_different_date = $('[name="mptbm_enable_return_in_different_date"]').val();

        if (mptbm_enable_return_in_different_date == 'yes') {
            var selectedTime = parseFloat($('#mptbm_map_start_time').val());
            var selectedDate = $('#mptbm_map_start_date').val();
            var dateValue = $('#mptbm_map_return_date').val();

            // Check if the return date is the same as the pickup date
            if (selectedDate == dateValue) {
                $('#return_time_list').show();
                // Clear existing options
                $('#mptbm_map_return_time').siblings('.mp_input_select_list').empty();
                $('.mptbm_map_return_time_input').val('');
                // If return date is the same as the pickup date, show only times after pickup time
                $('.mp_input_select_list li').each(function () {
                    var timeValue = parseFloat($(this).attr('data-value'));
                    if (timeValue > selectedTime) {
                        $('#mptbm_map_return_time').siblings('.mp_input_select_list').append($(this).clone());
                    }
                });
            } else {
                // Clear existing options
                $('#mptbm_map_return_time').siblings('.mp_input_select_list').empty();
                $('.mptbm_map_return_time_input').val('');
                $('.return_time_list-no-dsiplay li').each(function () {
                    var timeValue = parseFloat($(this).attr('data-value'));
                    $('#mptbm_map_return_time').siblings('.mp_input_select_list').append($(this).clone());
                });
            }
        }

        // Trigger refresh and display logic
        let parent = $(this).closest(".mptbm_transport_search_area");
        mptbm_content_refresh(parent);
        parent.find("#mptbm_map_return_time").closest(".mp_input_select").find("input.formControl").trigger("click");
    });


    $(document).on("click", ".start_time_list li", function () {
        let selectedValue = $(this).attr('data-value');
        $('#mptbm_map_start_time').val(selectedValue).trigger('change');
    });
    $(document).on("click", ".return_time_list li", function () {
        let selectedValue = $(this).attr('data-value');
        $('#mptbm_map_return_time').val(selectedValue).trigger('change');
    });
    $(document).on("change", "#mptbm_map_start_time", function () {
        let parent = $(this).closest(".mptbm_transport_search_area");
        mptbm_content_refresh(parent);
        parent.find("#mptbm_map_start_place").focus();
    });
    $(document).on("change", "#mptbm_manual_start_place", function () {
        let parent = $(this).closest(".mptbm_transport_search_area");
        mptbm_content_refresh(parent);
        let start_place = $(this).val();
        let target = parent.find(".mptbm_manual_end_place");
        if (start_place) {
            let end_place = "";
            let price_based = parent.find('[name="mptbm_price_based"]').val();
            if (price_based === "manual") {
                let post_id = parent.find('[name="mptbm_post_id"]').val();
                $.ajax({
                    type: "POST",
                    url: mp_ajax_url,
                    data: {
                        action: "get_mptbm_end_place",
                        start_place: start_place,
                        price_based: price_based,
                        post_id: post_id,
                    },
                    beforeSend: function () {
                        dLoader(target.closest(".mptbm_search_area"));
                    },
                    success: function (data) {
                        target
                            .html(data)
                            .promise()
                            .done(function () {
                                dLoaderRemove(target.closest(".mptbm_search_area"));
                                // iOS DOM reflow workaround
                                if (mptbm_is_ios()) {
                                    target[0].style.display = 'none';
                                    void target[0].offsetHeight;
                                    target[0].style.display = '';
                                }
                            });
                    },
                    error: function (response) {
                        console.log(response);
                    },
                });
            }
        }
    });
    $(document).on("change", "#mptbm_manual_end_place", function () {
        let parent = $(this).closest(".mptbm_transport_search_area");
        mptbm_content_refresh(parent);
    });
    $(document).on("change", "#mptbm_map_start_place,#mptbm_map_end_place", function () {
        let parent = $(this).closest(".mptbm_transport_search_area");
        mptbm_content_refresh(parent);
        let start_place = parent.find("#mptbm_map_start_place").val();
        let end_place = parent.find("#mptbm_map_end_place").val();
        if (start_place || end_place) {
            if (start_place) {
                mptbm_set_cookie_distance_duration(start_place);
                parent.find("#mptbm_map_end_place").focus();
            } else {
                mptbm_set_cookie_distance_duration(end_place);
                parent.find("#mptbm_map_start_place").focus();
            }
        } else {
            parent.find("#mptbm_map_start_place").focus();
        }
    }
    );
    $(document).on("change", ".mptbm_transport_search_area [name='mptbm_taxi_return']", function () {
        let parent = $(this).closest(".mptbm_transport_search_area");
        mptbm_content_refresh(parent);
    }
    );
    $(document).on(
        "change",
        ".mptbm_transport_search_area [name='mptbm_waiting_time']",
        function () {
            let parent = $(this).closest(".mptbm_transport_search_area");
            mptbm_content_refresh(parent);
        }
    );
})(jQuery);

// Add this test to verify jQuery and event handlers are working
jQuery(document).ready(function($) {
    console.log('MPTBM Registration JS loaded - jQuery working');
    
    // Test if info buttons exist
    setTimeout(function() {
        var infoButtons = $('.mptbm-info-button');
        console.log('Info buttons found:', infoButtons.length);
        if (infoButtons.length > 0) {
            console.log('Info buttons are present in DOM');
        }
    }, 1000);
});

function mptbm_content_refresh(parent) {
    parent.find('[name="mptbm_post_id"]').val("");
    parent.find(".mptbm_map_search_result").remove();
    parent.find(".mptbm_order_summary").remove();
    parent.find(".get_details_next_link").slideUp("fast");
}
//=======================//
function mptbm_price_calculation(parent) {
    let target_summary = parent.find(".mptbm_transport_summary");
    let total = 0;
    let post_id = parseInt(parent.find('[name="mptbm_post_id"]').val());
    if (post_id > 0) {
        total =
            total +
            parseFloat(parent.find('[name="mptbm_post_id"]').attr("data-price"));
        parent.find(".mptbm_extra_service_item").each(function () {
            let service_name = jQuery(this)
                .find('[name="mptbm_extra_service[]"]')
                .val();
            if (service_name) {
                let ex_target = jQuery(this).find('[name="mptbm_extra_service_qty[]');
                let ex_qty = parseInt(ex_target.val());
                let ex_price = ex_target.data("price");
                ex_price = ex_price && ex_price > 0 ? ex_price : 0;
                total = total + parseFloat(ex_price) * ex_qty;
            }
        });
    }
    var el = target_summary.find(".mptbm_product_total_price");
    el.html(mp_price_format(total));
    // iOS DOM reflow workaround
    if (mptbm_is_ios()) {
        el.hide().show(0);
    }
}
(function ($) {
    
    $(document).on('click', '.mp_quantity_minus, .mp_quantity_plus', function () {
        var postId = $(this).data('post-id');
        var $input = $(`.mp_quantity_input[data-post-id="${postId}"]`);
        var currentVal = parseInt($input.val());
        var maxVal = parseInt($input.attr('max'));
        var minVal = parseInt($input.attr('min'));
    
        if ($(this).hasClass('mp_quantity_minus')) {
            if (currentVal > minVal) {
                $input.val(currentVal - 1);
            }
        } else {
            if (currentVal < maxVal) {
                $input.val(currentVal + 1);
            }
        }
    
        var updatedVal = parseInt($input.val());
        var $parent = $(this).closest('.mptbm_booking_item');
        var $searchArea = $parent.closest('.mptbm_transport_search_area');
        var transportPrice = parseFloat($(`.mptbm_transport_select[data-post-id="${postId}"]`).attr('data-transport-price'));
        var $summary = $searchArea.find('.mptbm_transport_summary');
    
        // Check if there's a custom message
        let customMessage = $parent.find('.mptbm-custom-price-message').html();
        if (customMessage) {
            // If there's a custom message, show it with quantity
            $summary.find('.mptbm_product_price').html(
                'x' + updatedVal + ' <span style="color:#000;">|&nbsp;&nbsp;</span>' + customMessage
            );
        } else {
            // If no custom message, show price as before
            $summary.find('.mptbm_product_price').html(
                'x' + updatedVal + ' <span style="color:#000;">|&nbsp;&nbsp;</span>' + mp_price_format(transportPrice * updatedVal)
            );
        }
    
        // 🧠 Update the data-price dynamically if needed
        $searchArea.find('[name="mptbm_post_id"]').attr('data-price', transportPrice * updatedVal);
    
        // ✅ Now update the total
        mptbm_price_calculation($searchArea);
    });
    $(document).on('click', '.mptbm_transport_search_area .mptbm_transport_select', function () {
        let $this = $(this);
        let postId = $this.data('post-id');
        let parent = $this.closest('.mptbm_transport_search_area');
    
        // Keeping all original variables
        let target_summary = parent.find('.mptbm_transport_summary');
        let target_extra_service = parent.find('.mptbm_extra_service');
        let target_extra_service_summary = parent.find('.mptbm_extra_service_summary');
        let all_quantity_selectors = parent.find('.mptbm_quantity_selector');
        let target_quantity_selector = parent.find('.mptbm_quantity_selector_' + postId);
    
        // Toggle logic for quantity selector
        if (target_quantity_selector.length && target_quantity_selector.hasClass('mptbm_booking_item_hidden')) {
            // Hide all first, then show selected one
            all_quantity_selectors.addClass('mptbm_booking_item_hidden');
            target_quantity_selector.removeClass('mptbm_booking_item_hidden');
        } else {
            // If already visible or doesn't exist, hide all
            all_quantity_selectors.addClass('mptbm_booking_item_hidden');
        }

        target_summary.slideDown(400);
        target_extra_service.slideDown(400).html('');
        target_extra_service_summary.slideDown(400).html('');
        parent.find('[name="mptbm_post_id"]').val('');
        parent.find('.mptbm_checkout_area').html('');
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
                let quantityInput = parent.find(`.mp_quantity_input[data-post-id="${post_id}"]`);
                let quantityVal = quantityInput.length ? parseInt(quantityInput.val()) || 1 : 1;

                // Check if there's a custom message
                let customMessage = $this.closest('.mptbm_booking_item').find('.mptbm-custom-price-message').html();
                if (customMessage) {
                    // If there's a custom message, show it with quantity
                    target_summary.find('.mptbm_product_price').html(
                        'x' + quantityVal + ' <span style="color:#000;">|&nbsp;&nbsp;</span> ' + customMessage
                    );
                } else {
                    // If no custom message, show price as before
                    target_summary.find('.mptbm_product_price').html(
                        'x' + quantityVal + ' <span style="color:#000;">|&nbsp;&nbsp;</span> ' + mp_price_format(transport_price * quantityVal)
                    );
                }

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
                        checkAndToggleBookNowButton(parent);
                        // iOS DOM reflow workaround
                        if (mptbm_is_ios()) {
                            target_extra_service[0].style.display = 'none';
                            void target_extra_service[0].offsetHeight;
                            target_extra_service[0].style.display = '';
                        }
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
                                // Check if there are extra services before scrolling
                                if (target_extra_service.find('[name="mptbm_extra_service[]"]').length > 0) {
                                    target_summary.slideDown(400);
                                    target_extra_service.slideDown(400);
                                    target_extra_service_summary.slideDown(400);
                                    pageScrollTo(target_extra_service);
                                }
                                dLoaderRemove(parent.find('.tabsContentNext'));
                                // Check if vehicle has seat plan enabled
                                let hasSeatPlan = parent.find('.mptbm_transport_select[data-post-id="' + post_id + '"]').hasClass('mptbm-has-seat-plan');
                                
                                if (!target_extra_service.find('[name="mptbm_extra_service[]"]').length && !hasSeatPlan) {
                                    // Only auto-trigger checkout if no extra services AND no seat plan
                                    parent.find('.mptbm_book_now[type="button"]').trigger('click');
                                } else {
                                    // Show Book Now button if there are extra services OR seat plan
                                    checkAndToggleBookNowButton(parent);
                                }
                                // iOS DOM reflow workaround
                                if (mptbm_is_ios()) {
                                    target_extra_service_summary[0].style.display = 'none';
                                    void target_extra_service_summary[0].offsetHeight;
                                    target_extra_service_summary[0].style.display = '';
                                }
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
        let parent = $(this).closest('.mptbm_transport_search_area');
        checkAndToggleBookNowButton(parent);
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

        checkAndToggleBookNowButton(parent);
    });

    function checkAndToggleBookNowButton(parent) {
        // Check if there are any extra services present
        let extraServicesAvailable = parent.find('[name="mptbm_extra_service[]"]').length > 0;
        
        // Check if the selected vehicle has seat plan enabled
        let post_id = parent.find('[name="mptbm_post_id"]').val();
        let hasSeatPlan = false;
        if (post_id) {
            hasSeatPlan = parent.find('.mptbm_transport_select[data-post-id="' + post_id + '"]').hasClass('mptbm-has-seat-plan');
        }

        // Show Book Now button if there are extra services OR seat plan
        if (extraServicesAvailable || hasSeatPlan) {
            parent.find('.mptbm_book_now[type="button"]').show();
        } else {
            parent.find('.mptbm_book_now[type="button"]').hide();
        }
    }



    //===========================//
    $(document).on('click', '.mptbm_transport_search_area .mptbm_get_vehicle_prev', function () {
        var mptbmTemplateExists = $(".mptbm-show-search-result").length;
        if (mptbmTemplateExists) {
            // Function to retrieve cookie value by name
            function getCookie(name) {
                // Split the cookies by semicolon
                var cookies = document.cookie.split(";");
                // Loop through each cookie to find the one with the specified name
                for (var i = 0; i < cookies.length; i++) {
                    var cookie = cookies[i].trim();
                    // Check if the cookie starts with the specified name
                    if (cookie.startsWith(name + "=")) {
                        // Return the value of the cookie
                        return cookie.substring(name.length + 1);
                    }
                }
                // Return null if the cookie is not found
                return null;
            }
            // Usage example:
            var httpReferrerValue = getCookie("httpReferrer");
            // Function to delete a cookie by setting its expiry date to a past time
            function deleteCookie(name) {
                document.cookie =
                    name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            }
            deleteCookie("httpReferrer");
            window.location.href = httpReferrerValue;
        } else {
            let parent = $(this).closest(".mptbm_transport_search_area");
            parent.find(".get_details_next_link").slideDown("fast");
            parent.find(".nextTab_prev").trigger("click");
        }
    });
    $(document).on('click', '.mptbm_transport_search_area .mptbm_summary_prev', function () {
        let mptbmTemplateExists = $(".mptbm-show-search-result").length;
        if (mptbmTemplateExists) {
            $(".mptbm_order_summary").css("display", "none");
            $(".mptbm_map_search_result").css("display", "block").hide().slideDown("slow");
            $(".step-place-order").removeClass("active");
        } else {
            let parent = $(this).closest(".mptbm_transport_search_area");
            parent.find(".nextTab_prev").trigger("click");
        }
    });
    //===========================//
    $(document).on("click", ".mptbm_book_now[type='button']", function () {
        let parent = $(this).closest('.mptbm_transport_search_area');
        let target_checkout = parent.find('.mptbm_checkout_area');
        let start_place = parent.find('[name="mptbm_start_place"]').val();
        let end_place = parent.find('[name="mptbm_end_place"]').val();
        let mptbm_waiting_time = parent.find('[name="mptbm_waiting_time"]').val();
        let mptbm_taxi_return = parent.find('[name="mptbm_taxi_return"]').val();
        let return_target_date = parent.find("#mptbm_map_return_date").val();
        let return_target_time = parent.find("#mptbm_map_return_time").val();
        let mptbm_fixed_hours = parent.find('[name="mptbm_fixed_hours"]').val();
        let post_id = parent.find('[name="mptbm_post_id"]').val();
        let date = parent.find('[name="mptbm_date"]').val();
        let link_id = $(this).attr('data-wc_link_id');
        let quantity = parseInt(parent.find(`.mp_quantity_input[data-post-id="${post_id}"]`).val()) || 1;
       
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
            // Collect seat plan data if available
            let seatData = {};
            let seatDisplayData = {};
            
            // Look for seat number inputs for this vehicle
            let seatNumbersInput = parent.find(`.mptbm-selected-seat-numbers[data-post-id="${post_id}"]`);
            let seatDisplayInput = parent.find(`input[name="vehicle_seat_display_names[${post_id}]"]`);
            
            if (seatNumbersInput.length > 0) {
                seatData[post_id] = seatNumbersInput.val();
    
            }
            
            if (seatDisplayInput.length > 0) {
                seatDisplayData[post_id] = seatDisplayInput.val();

            }
            
            
            
            $.ajax({
                type: 'POST',
                url: mp_ajax_url,
                data: {
                    action: "mptbm_add_to_cart",
                    //"product_id": post_id,
                    transport_quantity: quantity,
                    link_id: link_id,
                    mptbm_start_place: start_place,
                    mptbm_end_place: end_place,
                    mptbm_waiting_time: mptbm_waiting_time,
                    mptbm_taxi_return: mptbm_taxi_return,
                    mptbm_fixed_hours: mptbm_fixed_hours,
                    mptbm_date: date,
                    mptbm_return_date: return_target_date,
                    mptbm_return_time: return_target_time,
                    mptbm_extra_service: extra_service_name,
                    mptbm_extra_service_qty: extra_service_qty,
                    mptbm_passengers: parent.find('#mptbm_passengers').val(),
                    mptbm_max_passenger: parent.find('#mptbm_max_passenger').val(),
                    mptbm_max_bag: parent.find('#mptbm_max_bag').val(),
                    vehicle_seat_numbers: seatData,
                    vehicle_seat_display_names: seatDisplayData,
                },
                beforeSend: function () {
                    dLoader(parent.find('.tabsContentNext'));
                },
                success: function (data) {
                    if ($('<div />', { html: data }).find("div").length > 0) {
                        var mptbmTemplateExists = $(".mptbm-show-search-result").length;
                        if (mptbmTemplateExists) {
                            $(".mptbm_map_search_result").css("display", "none");
                            $(".mptbm_order_summary").css("display", "block");
                            $(".step-place-order").addClass('active');
                        }
                        target_checkout.html(data).promise().done(function () {
                            target_checkout.find('.woocommerce-billing-fields .required').each(function () {
                                $(this).closest('p').find('.input-text , select, textarea ').attr('required', 'required');
                            });
                            $(document.body).trigger('init_checkout');
                            if ($('body select#billing_country').length > 0) {
                                $('body select#billing_country').select2({});
                            }
                            if ($('body select#billing_state').length > 0) {
                                $('body select#billing_state').select2({});
                            }
                            dLoaderRemove(parent.find('.tabsContentNext'));
                            parent.find('.nextTab_next').trigger('click');
                            // iOS DOM reflow workaround
                            if (mptbm_is_ios()) {
                                target_checkout[0].style.display = 'none';
                                void target_checkout[0].offsetHeight;
                                target_checkout[0].style.display = '';
                            }
                        });
                    } else {
                        window.location.href = data;
                    }
                },
                error: function (response) {
                    console.log(response);
                }
            });
        }
    });



    $(document).ready(function () {
        let $tabs = $('.tab-link');
        let count = $tabs.length;

        // Reset previous border-radius styles
        $tabs.css({
            'border-radius': '', // Clears any previously applied styles
        });

        if (count === 1) {
            // If only one element, apply radius to all sides
            $tabs.eq(0).css('border-radius', 'var(--dbrl)');
        } else if (count === 2) {
            // If two elements, apply left radius to first and right radius to second
            $tabs.eq(0).css({
                'border-top-left-radius': 'var(--dbrl)',
                'border-bottom-left-radius': 'var(--dbrl)'
            });
            $tabs.eq(1).css({
                'border-top-right-radius': 'var(--dbrl)',
                'border-bottom-right-radius': 'var(--dbrl)'
            });
        } else if (count >= 3) {
            // If three or more, apply left radius to first and right radius to third
            $tabs.eq(0).css({
                'border-top-left-radius': 'var(--dbrl)',
                'border-bottom-left-radius': 'var(--dbrl)'
            });
            $tabs.last().css({
                'border-top-right-radius': 'var(--dbrl)',
                'border-bottom-right-radius': 'var(--dbrl)'
            });
        }
        $('.mptb-tabs li').click(function () {
            var tab_id = $(this).attr('mptbm-data-tab');
            var form_style = $(this).attr('mptbm-data-form-style');
            var map = $(this).attr('mptbm-data-map');
            
            // Remove existing template before inserting the new one
            $('.mptb-tab-content').empty().removeClass('current');
            $('.mptbm-hide-gif').css('display', 'block');
            // Mark the clicked tab as active
            $('.mptb-tabs li').removeClass('current');
            $(this).addClass('current');
            
            // AJAX call to load the template
            $.ajax({
                type: "POST",
                url: mp_ajax_url, // WordPress AJAX URL
                data: {
                    action: "load_get_details_page",
                    tab_id: tab_id,
                    form_style: form_style,
                    map: map
                },
                beforeSend: function () {
                    $("#" + tab_id).html('<p>Loading...</p>'); // Show loading message
                },
                success: function (data) {
                    $("#" + tab_id).html(data).addClass('current'); // Load the template
                    $('.mptbm-hide-gif').css('display', 'none');
                    // **Reinitialize the map-related elements after template loads**
                    mptbm_map_area_init();
                },
                error: function (response) {
                    console.log(response);
                },
            });
        });
    });

    // Handle extra info toggle functionality
    $(document).on('click', '.mptbm-info-button', function(e) {
        e.preventDefault();
        e.stopPropagation();
        

        
        var $button = $(this);
        var postId = $button.data('post-id');
        var $vehicleWrapper = $button.closest('.mptbm-vehicle-wrapper');
        var $content = $vehicleWrapper.find('.mptbm-extra-info-content[data-post-id="' + postId + '"]');
        var $icon = $button.find('i');
        

        
        // Close other open info panels
        $('.mptbm-extra-info-content').not($content).slideUp(200);
        $('.mptbm-info-button').not($button).css('background', 'var(--color_theme)').find('i').removeClass('fa-times').addClass('fa-info');
        
        if ($content.length > 0) {
            $content.slideToggle(300, function() {
                if ($content.is(':visible')) {
                    $button.css('background', '#dc3545'); // Red when open
                    $icon.removeClass('fa-info').addClass('fa-times');
                } else {
                    $button.css('background', 'var(--color_theme)');
                    $icon.removeClass('fa-times').addClass('fa-info');
                }
            });
        }
    });
    
    // Close info panels when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.mptbm-button-container, .mptbm-extra-info-content').length) {
            $('.mptbm-extra-info-content').slideUp(200);
            $('.mptbm-info-button').css('background', 'var(--color_theme)').find('i').removeClass('fa-times').addClass('fa-info');
        }
    });

}(jQuery));

function gm_authFailure() {
    var warning = jQuery('.mptbm-map-warning').html();
    jQuery('#mptbm_map_area').html('<div class="mptbm-map-warning"><h6>' + warning + '</h6></div>');
}
// Utility: Detect iOS
function mptbm_is_ios() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
}

// Enable search in Drop-Off and Pickup Location dropdowns using selectWoo or select2 if available
jQuery(document).ajaxComplete(function() {
    // Check for selectWoo first (WooCommerce's version of Select2)
    if (typeof jQuery.fn.selectWoo !== 'undefined') {
        // Drop-Off Location - only target select elements, not summary display elements
        jQuery('select.mptbm_map_end_place').each(function() {
            if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                jQuery(this).selectWoo({
                    width: '100%',
                    placeholder: jQuery(this).find('option[selected]').text() || 'Select Destination Location',
                    allowClear: true
                });
            }
        });
        // Pickup Location (manual mode) - only target select elements, not summary display elements
        jQuery('select.mptbm_manual_start_place').each(function() {
            if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                jQuery(this).selectWoo({
                    width: '100%',
                    placeholder: jQuery(this).find('option[selected]').text() || 'Select Pick-Up Location',
                    allowClear: true
                });
            }
        });
    }
    // Fallback to select2 if selectWoo is not available
    else if (typeof jQuery.fn.select2 !== 'undefined') {
        // Drop-Off Location - only target select elements, not summary display elements
        jQuery('select.mptbm_map_end_place').each(function() {
            if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                jQuery(this).select2({
                    width: '100%',
                    placeholder: jQuery(this).find('option[selected]').text() || 'Select Destination Location',
                    allowClear: true
                });
            }
        });
        // Pickup Location (manual mode) - only target select elements, not summary display elements
        jQuery('select.mptbm_manual_start_place').each(function() {
            if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                jQuery(this).select2({
                    width: '100%',
                    placeholder: jQuery(this).find('option[selected]').text() || 'Select Pick-Up Location',
                    allowClear: true
                });
            }
        });
    }
});


