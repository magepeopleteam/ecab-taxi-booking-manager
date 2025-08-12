let mptbm_map;
let mptbm_map_window;

// Function to clean up existing map instance
function mptbm_cleanup_map() {
    if (mptbm_map) {
        // Clear any existing map instances
        google.maps.event.clearInstanceListeners(mptbm_map);
        mptbm_map = null;
    }
    if (mptbm_map_window) {
        mptbm_map_window.close();
        mptbm_map_window = null;
    }
}
function mptbm_set_cookie_distance_duration(start_place = "", end_place = "") {
    // Check if map container exists before initializing
    const mapContainer = document.getElementById("mptbm_map_area");
    if (!mapContainer) {
        console.warn("Map container #mptbm_map_area not found. Map initialization skipped.");
        return false;
    }
    
    mptbm_map = new google.maps.Map(mapContainer, {
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
        
        // Check if map container exists before initializing
        const mapContainer = document.getElementById("mptbm_map_area");
        if (!mapContainer) {
            console.warn("Map container #mptbm_map_area not found. Map initialization skipped.");
            return false;
        }
        
        map = new google.maps.Map(mapContainer, {
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
    // Check if Google Maps API is loaded
    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        console.warn("Google Maps API not loaded. Skipping map initialization.");
        return false;
    }
    
    // Check if map container exists and is visible before initializing
    const mapContainer = document.getElementById("mptbm_map_area");
    if (!mapContainer) {
        console.warn("Map container #mptbm_map_area not found. Skipping map initialization.");
        return false;
    }
    
    // Check if the map container is visible (not hidden by CSS)
    const mapArea = document.querySelector('.mptbm_map_area');
    if (mapArea && mapArea.style.display === 'none') {
        console.warn("Map area is hidden. Skipping map initialization.");
        return false;
    }
    
    mptbm_set_cookie_distance_duration();

    // Initialize Google Places autocomplete for pickup location
    if (jQuery("#mptbm_map_start_place").length > 0) {
        let start_place = document.getElementById("mptbm_map_start_place");
        let start_place_autoload = new google.maps.places.Autocomplete(start_place);
        let mptbm_restrict_search_to_country = jQuery('[name="mptbm_restrict_search_country"]').val();
        let mptbm_country = jQuery('[name="mptbm_country"]').val();

        if (mptbm_restrict_search_to_country == 'yes') {
            start_place_autoload.setComponentRestrictions({
                country: [mptbm_country]
            });
        }

        google.maps.event.addListener(start_place_autoload, "place_changed", function () {
            let end_place = document.getElementById("mptbm_map_end_place");
            
            // Only sync dropoff with pickup if dropoff is hidden (hourly pricing with disabled dropoff)
            if (end_place && end_place.type === 'hidden') {
                console.log('Syncing dropoff with pickup (dropoff is hidden)');
                end_place.value = start_place.value;
            }
            
            mptbm_set_cookie_distance_duration(
                start_place.value,
                end_place ? end_place.value : start_place.value
            );
        });

        // Mark as initialized to prevent duplicate initialization
        start_place.setAttribute('data-autocomplete-initialized', 'true');
    }
    
    // Ensure Next button is properly positioned after map initialization
    setTimeout(function() {
        var nextButtonContainer = document.querySelector('.get_details_next_link');
        if (nextButtonContainer) {
            // Force a reflow to ensure proper positioning
            nextButtonContainer.style.display = 'none';
            nextButtonContainer.offsetHeight; // Force reflow
            nextButtonContainer.style.display = '';
            
            // Ensure it's positioned correctly relative to the map
            var mapArea = document.querySelector('.mptbm_map_area');
            if (mapArea && mapArea.style.display !== 'none') {
                nextButtonContainer.style.marginTop = '20px';
                nextButtonContainer.style.position = 'relative';
                nextButtonContainer.style.clear = 'both';
            }
        }
    }, 100);

    // Initialize Google Places autocomplete for dropoff location (only if it exists and is visible)
    if (jQuery("#mptbm_map_end_place").length > 0 && jQuery("#mptbm_map_end_place").is(":visible")) {
        let end_place = document.getElementById("mptbm_map_end_place");
        let end_place_autoload = new google.maps.places.Autocomplete(end_place);
        let mptbm_restrict_search_to_country = jQuery('[name="mptbm_restrict_search_country"]').val();
        let mptbm_country = jQuery('[name="mptbm_country"]').val();

        if (mptbm_restrict_search_to_country == 'yes') {
            end_place_autoload.setComponentRestrictions({
                country: [mptbm_country]
            });
        }

        google.maps.event.addListener(end_place_autoload, "place_changed", function () {
            let start_place = document.getElementById("mptbm_map_start_place");
            mptbm_set_cookie_distance_duration(
                start_place ? start_place.value : '',
                end_place ? end_place.value : ''
            );
        });
    }
}
(function ($) {
    "use strict";
    $(document).ready(function () {
        $(".mpStyle ul.mp_input_select_list").hide();

        // Function to initialize Google Places autocomplete (global scope)
        window.initializeGooglePlacesAutocomplete = function() {
            // Check if Google Maps API is loaded
            if (typeof google === 'undefined' || typeof google.maps === 'undefined' || typeof google.maps.places === 'undefined') {
                console.log('Google Maps API not loaded yet, retrying in 500ms...');
                setTimeout(function() {
                    initializeGooglePlacesAutocomplete();
                }, 500);
                return;
            }
            
                        var startPlaceInput = document.getElementById('mptbm_map_start_place');
            console.log('Pickup location input found:', startPlaceInput);
            if (startPlaceInput && !startPlaceInput.hasAttribute('data-autocomplete-initialized')) {
                    console.log('Initializing Google Places autocomplete for pickup location');
                    var startPlaceAutocomplete = new google.maps.places.Autocomplete(startPlaceInput);
                    var mptbmRestrictSearchToCountry = $('[name="mptbm_restrict_search_country"]').val();
                    var mptbmCountry = $('[name="mptbm_country"]').val();

                    if (mptbmRestrictSearchToCountry == 'yes') {
                        startPlaceAutocomplete.setComponentRestrictions({
                            country: [mptbmCountry]
                        });
                    }

                    google.maps.event.addListener(startPlaceAutocomplete, "place_changed", function () {
                        var endPlaceInput = document.getElementById('mptbm_map_end_place');
                        
                        // Only sync dropoff with pickup if dropoff is hidden (hourly pricing with disabled dropoff)
                        if (endPlaceInput && endPlaceInput.type === 'hidden') {
                            console.log('Syncing dropoff with pickup (dropoff is hidden)');
                            endPlaceInput.value = startPlaceInput.value;
                        }
                        
                        mptbm_set_cookie_distance_duration(
                            startPlaceInput.value,
                            endPlaceInput ? endPlaceInput.value : startPlaceInput.value
                        );
                    });
                    
                    // Mark as initialized to prevent duplicate initialization
                    startPlaceInput.setAttribute('data-autocomplete-initialized', 'true');
                }
        }

        // Initialize Google Places autocomplete on page load with a delay to ensure API is loaded
        console.log('Setting up Google Places autocomplete initialization...');
        setTimeout(function() {
            console.log('Attempting to initialize Google Places autocomplete...');
            initializeGooglePlacesAutocomplete();
        }, 500);
        
        // Handle Previous/Next button positioning after tab changes
        $(document).on('click', '.nextTab_prev, .nextTab_next', function() {
            setTimeout(function() {
                var nextButtonContainer = document.querySelector('.get_details_next_link');
                if (nextButtonContainer) {
                    // Force a reflow to ensure proper positioning
                    nextButtonContainer.style.display = 'none';
                    nextButtonContainer.offsetHeight; // Force reflow
                    nextButtonContainer.style.display = '';
                    
                    // Ensure it's positioned correctly relative to the map
                    var mapArea = document.querySelector('.mptbm_map_area');
                    if (mapArea && mapArea.style.display !== 'none') {
                        nextButtonContainer.style.marginTop = '20px';
                        nextButtonContainer.style.position = 'relative';
                        nextButtonContainer.style.clear = 'both';
                    }
                }
            }, 350); // Wait for slideDown animation to complete
        });

        // Function to validate and fix tab structure (silent version)
        function validateTabStructure() {
            // Check tab links
            $('.mptb-tabs li').each(function() {
                var tabId = $(this).attr('mptbm-data-tab');
                var isCurrent = $(this).hasClass('current');
                
                // Check if corresponding tab content exists
                var tabContent = $("#" + tabId);
                if (tabContent.length === 0) {
                    // Create missing tab content container
                    var tabContainerParent = $('.mptb-tab-container');
                    if (tabContainerParent.length > 0) {
                        var newTabContainer = $('<div id="' + tabId + '" class="mptb-tab-content"></div>');
                        tabContainerParent.append(newTabContainer);
                    }
                }
            });
            
            // Check tab content containers
            $('.mptb-tab-content').each(function() {
                var tabId = $(this).attr('id');
                var isCurrent = $(this).hasClass('current');
                var isVisible = $(this).is(':visible');
                
                // Ensure current tab is visible
                if (isCurrent && !isVisible) {
                    $(this).css('display', 'block');
                }
            });
        }

        // Function to ensure loading GIF element exists
        window.ensureLoadingGifExists = function() {
            var loadingGif = $('.mptbm-hide-gif');
            var tabContainer = $('.mptb-tab-container');
            
            if (loadingGif.length === 0 && tabContainer.length > 0) {
                console.log('Loading GIF element not found, creating it...');
                var loadingGifHtml = '<div class="mptbm-hide-gif mptbm-gif" style="display: none;"><img src="' + window.location.origin + '/taxi/wp-content/plugins/ecab-taxi-booking-manager/assets/images/loader.gif" class="mptb-tabs-loader" /></div>';
                tabContainer.append(loadingGifHtml);
                console.log('Loading GIF element created');
                return true;
            } else if (loadingGif.length === 0 && tabContainer.length === 0) {
                console.log('Tab container not found, will create loading GIF when needed');
                return false;
            }
            return true;
        };
        
        // Try to create loading GIF element immediately
        window.ensureLoadingGifExists();
        
        // Also try after a short delay to ensure DOM is fully ready
        setTimeout(function() {
            window.ensureLoadingGifExists();
            validateTabStructure();
        }, 100);

        // Only initialize map on page load if the first tab should have a map
        if ($("#mptbm_map_area").length > 0) {
            var hasTabs = $('.mptb-tabs').length > 0;
            if (hasTabs) {
                // Check if the current tab should have a map
                var currentTab = $('.mptb-tabs li.current').attr('mptbm-data-tab');
                var mapEnabled = $('.mptb-tabs li.current').attr('mptbm-data-map');
                
                // Don't initialize map for manual/flat-rate tab or if map is disabled
                if (currentTab !== 'flat-rate' && mapEnabled === 'yes') {
            mptbm_map_area_init();
                }
            } else {
                // No tabs (plain [mptbm_booking]) → initialize map if container is visible
                var mapAreaEl = document.querySelector('.mptbm_map_area');
                if (!mapAreaEl || mapAreaEl.style.display === 'none') {
                    // Skip if hidden by template conditions
                } else {
                    mptbm_map_area_init();
                }
            }
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
        let mptbm_original_price_base = parent.find('[name="mptbm_original_price_base"]').val();
        
        
        let mptbm_enable_view_search_result_page = parent
            .find('[name="mptbm_enable_view_search_result_page"]')
            .val();
        if (price_based === "manual") {
            start_place = document.getElementById("mptbm_manual_start_place");
            end_place = document.getElementById("mptbm_manual_end_place");
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
            // Fix for return_time conversion
            let selectedReturnTimeElement = parent.find("#mptbm_map_return_time").closest(".mp_input_select").find("li[data-value='" + return_time + "']");
            if (selectedReturnTimeElement.length) {
                return_time = selectedReturnTimeElement.attr('data-time');
                let [r_hours, r_minutes] = return_time.split('.');
                return_time = parseFloat(r_hours) + (parseFloat(r_minutes) / 60);
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
            start_place.focus();
        } else if (!end_place.value) {
            end_place.focus();
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
                                    mptbm_original_price_base: mptbm_original_price_base,
                                },
                                beforeSend: function () {
                                    //dLoader(target);
                                },
                                success: function (data) {
                                    // Check if the response is an error
                                    if (data.success === false) {
                                        alert(data.data.message || 'An error occurred. Please try again.');
                                        dLoaderRemove(parent.find(".tabsContentNext"));
                                        return;
                                    }
                                    
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
                                    mptbm_original_price_base: mptbm_original_price_base,
                                },
                                beforeSend: function () {
                                    dLoader(target);
                                },
                                success: function (data) {
                                    // Check if the response is an error
                                    if (data.success === false) {
                                        alert(data.data.message || 'An error occurred. Please try again.');
                                        dLoaderRemove(parent.find(".tabsContentNext"));
                                        return;
                                    }
                                    
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
                                mptbm_original_price_base: mptbm_original_price_base,
                            },
                            beforeSend: function () {
                                //dLoader(target);
                            },
                            success: function (data) {
                                // Check if the response is an error
                                if (data.success === false) {
                                    alert(data.data.message || 'An error occurred. Please try again.');
                                    dLoaderRemove(parent.find(".tabsContentNext"));
                                    return;
                                }
                                
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
                                mptbm_original_price_base: mptbm_original_price_base,
                            },
                            beforeSend: function () {
                                dLoader(target);
                            },
                            success: function (data) {
                                // Check if the response is an error
                                if (data.success === false) {
                                    alert(data.data.message || 'An error occurred. Please try again.');
                                    dLoaderRemove(parent.find(".tabsContentNext"));
                                    return;
                                }
                                
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
        let mptbm_buffer_end_minutes = parseInt($('[name="mptbm_buffer_end_minutes"]').val()) || 0;
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
            // For today's date, apply buffer time restrictions
            var currentTime = new Date();
            var currentHour = currentTime.getHours();
            var currentMinutes = currentTime.getMinutes();
            var currentTotalMinutes = (currentHour * 60) + currentMinutes;

            $('.start_time_list-no-dsiplay li').each(function () {
                const timeValue = parseFloat($(this).attr('data-value'));
                const timeInMinutes = Math.floor(timeValue) * 60 + ((timeValue % 1) * 100);
                
                // Only show times that are after the buffer period
                if (timeInMinutes > mptbm_buffer_end_minutes) {
                    $('#mptbm_map_start_time').siblings('.start_time_list').append($(this).clone());
                }
            });
        } else if (selectedDate == mptbm_first_calendar_date) {
            // For the first available date (which might be today or tomorrow depending on buffer)
            $('.start_time_list-no-dsiplay li').each(function () {
                const timeValue = parseFloat($(this).attr('data-value'));
                const timeInMinutes = Math.floor(timeValue) * 60 + ((timeValue % 1) * 100);
                
                // If this is tomorrow and buffer extends to tomorrow, apply buffer
                if (mptbm_buffer_end_minutes > 1440) {
                    const adjustedBufferMinutes = mptbm_buffer_end_minutes - 1440;
                    if (timeInMinutes > adjustedBufferMinutes) {
                        $('#mptbm_map_start_time').siblings('.start_time_list').append($(this).clone());
                    }
                } else {
                    // For other dates, show all times
                    $('#mptbm_map_start_time').siblings('.start_time_list').append($(this).clone());
                }
            });
        } else {
            // For future dates, show all available times
            $('.start_time_list-no-dsiplay li').each(function () {
                $('#mptbm_map_start_time').siblings('.start_time_list').append($(this).clone());
            });
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
                        // Remove any existing custom dropdown before AJAX call
                        $('.mptbm-custom-select-wrapper').remove();
                        dLoader(target.closest(".mptbm_search_area"));
                    },
                    success: function (data) {
                        console.log('AJAX response for end locations:', data);
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
                                
                                // Add a small delay to ensure the select is properly updated
                                setTimeout(function() {
                                    console.log('Select updated, options count:', target.find('option:not([disabled])').length);
                                }, 100);
                            });
                    },
                    error: function (response) {
                        console.log('AJAX error for end locations:', response);
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
                                if (!target_extra_service.find('[name="mptbm_extra_service[]"]').length) {
                                    parent.find('.mptbm_book_now[type="button"]').trigger('click');
                                } else {
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

        if (extraServicesAvailable) {
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
        let mptbm_original_price_base = parent.find('[name="mptbm_original_price_base"]').val();
        
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
                    mptbm_original_price_base: mptbm_original_price_base,
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
        } else if (count >= 2) {
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
            
            // Clean up existing map instance before switching tabs
            mptbm_cleanup_map();
            
            // Check if the target tab already has content
            var targetTabContainer = $("#" + tab_id);
            var hasExistingContent = targetTabContainer.length > 0 && targetTabContainer.html().trim() !== '';
            
            // Only show loading overlay if the tab doesn't have content or needs to be refreshed
            if (!hasExistingContent) {
                // Remove any existing loading overlay
                $('.mptbm-loading-overlay').remove();
                
                // Create a new loading overlay that will definitely be visible
                var loadingOverlay = $('<div class="mptbm-loading-overlay" style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; z-index: 9999 !important; padding: 30px !important; text-align: center !important; margin-top: 40px !important;"><img src="' + window.location.origin + '/taxi/wp-content/plugins/ecab-taxi-booking-manager/assets/images/loader.gif" style="width: 80px !important; height: auto !important;" /></div>');
                
                                // Append to body to ensure it's visible
                $('body').append(loadingOverlay);
            }
            
            // Mark the clicked tab as active
            $('.mptb-tabs li').removeClass('current');
            $(this).addClass('current');
            
            // Handle content loading based on whether tab already has content
            if (hasExistingContent) {
                // Tab already has content, just show it without AJAX call
                $('.mptb-tab-content').removeClass('current');
                targetTabContainer.addClass('current');
                
                // Force display block if CSS class doesn't work
                if (!targetTabContainer.is(':visible')) {
                    console.log('Tab not visible, forcing display block');
                    targetTabContainer.css('display', 'block');
                }
                
                console.log('Switched to existing tab content without AJAX');
                return; // Exit the click handler early
            }
            
            // Remove existing template before inserting the new one
            $('.mptb-tab-content').empty().removeClass('current');
            
            // Small delay to ensure loading GIF is rendered before AJAX starts (only when loading new content)
            setTimeout(function() {
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
                    // Check if the tab container exists before trying to insert loading message
                    var tabContainer = $("#" + tab_id);
                    if (tabContainer.length === 0) {
                        // Create the container if it doesn't exist
                        var tabContainerParent = $('.mptb-tab-container');
                        if (tabContainerParent.length > 0) {
                            var newTabContainer = $('<div id="' + tab_id + '" class="mptb-tab-content"></div>');
                            tabContainerParent.append(newTabContainer);
                            tabContainer = newTabContainer;
                        }
                    }
                    
                    if (tabContainer.length > 0) {
                        tabContainer.html('<div style="text-align: center; padding: 20px;"><p>Loading...</p><div style="margin-top: 10px;">Please wait while we load the booking form...</div></div>');
                    }
                },
                success: function (data) {
                    console.log('=== LOADING GIF DEBUG ===');
                    console.log('AJAX response received for tab:', tab_id);
                    console.log('Response data length:', data.length);
                    
                    // Check if the tab container exists
                    var tabContainer = $("#" + tab_id);
                    if (tabContainer.length === 0) {
                        console.log('Tab container not found, creating new one:', tab_id);
                        
                        // Try to create the tab container if it doesn't exist
                        var tabContainerParent = $('.mptb-tab-container');
                        if (tabContainerParent.length > 0) {
                            var newTabContainer = $('<div id="' + tab_id + '" class="mptb-tab-content"></div>');
                            tabContainerParent.append(newTabContainer);
                            tabContainer = newTabContainer;
                            console.log('Created new tab container:', tab_id);
                        } else {
                            console.error('Tab container parent not found');
                            return;
                        }
                    }
                    
                    // Insert the content into the correct tab container
                    tabContainer.html(data);
                    
                    // Ensure the tab content is visible using CSS classes
                    $('.mptb-tab-content').removeClass('current');
                    tabContainer.addClass('current');
                    
                    // Force display block if CSS class doesn't work
                    if (!tabContainer.is(':visible')) {
                        console.log('Tab not visible, forcing display block');
                        tabContainer.css('display', 'block');
                    }
                    
                    // Hide loading GIF after content is loaded with a minimum display time
                    console.log('Hiding loading GIF...');
                    
                    // Add a minimum display time of 1000ms to ensure the loading GIF is visible
                    // This gives more time for the user to see the loading state
                    setTimeout(function() {
                        // Remove the loading overlay
                        $('.mptbm-loading-overlay').remove();
                        console.log('Removed loading overlay');
                    }, 1000);
                    
                                         // Add a small delay to ensure DOM is fully updated before initializing map
                     setTimeout(function() {
                         // Only initialize map if the current tab should have a map
                         var currentTab = $('.mptb-tabs li.current').attr('mptbm-data-tab');
                         var mapEnabled = $('.mptb-tabs li.current').attr('mptbm-data-map');
                         
                         // Don't initialize map for manual/flat-rate tab or if map is disabled
                         if (currentTab !== 'flat-rate' && mapEnabled === 'yes') {
                    // **Reinitialize the map-related elements after template loads**
                    mptbm_map_area_init();
                         }
                         
                         // Always reinitialize Google Places autocomplete for pickup location
                         initializeGooglePlacesAutocomplete();
                     }, 100);
                },
                error: function (response) {
                    console.log('AJAX Error:', response);
                    // Hide loading GIF on error with minimum display time
                    setTimeout(function() {
                        // Remove the loading overlay
                        $('.mptbm-loading-overlay').remove();
                        console.log('Removed loading overlay on error');
                    }, 1000);
                    // Show error message
                    var tabContainer = $("#" + tab_id);
                    if (tabContainer.length > 0) {
                        tabContainer.html('<div style="text-align: center; padding: 20px; color: red;"><p>Error loading content. Please try again.</p></div>');
                    }
                },
            });
                }, 100); // Close the setTimeout for AJAX delay
        });
    });

    // Handle select dropdown search functionality
    $(document).on('click', '#mptbm_manual_start_place, #mptbm_manual_end_place', function(e) {
        // Prevent default select behavior
        e.preventDefault();
        e.stopPropagation();
        
        var $select = $(this);
        var selectId = $select.attr('id');
        
        console.log('Select clicked:', selectId);
        
        // Remove any existing custom search elements
        $('.mptbm-custom-select-wrapper').remove();
        
        // Check if select has options (dropoff might be empty initially)
        var $options = $select.find('option:not([disabled])');
        console.log('Available options for', selectId + ':', $options.length);
        
        if ($options.length <= 0) {
            console.log('No options available for:', selectId);
            return;
        }
        
        // Get select position and dimensions
        var selectOffset = $select.offset();
        var selectWidth = $select.outerWidth();
        var selectHeight = $select.outerHeight();
        
        // Keep the original select visible - don't hide it
        // $select.hide(); // REMOVED - keep select visible
        
        // Create custom select wrapper positioned below the select element
        var $customWrapper = $('<div class="mptbm-custom-select-wrapper" style="position: absolute !important; top: ' + (selectOffset.top + selectHeight + 2) + 'px !important; left: ' + selectOffset.left + 'px !important; width: ' + selectWidth + 'px !important; z-index: 9999 !important; background: white !important; border: 1px solid #ddd !important; border-radius: 4px !important; box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;"></div>');
        
        // Create search input
        var $searchInput = $('<input type="text" class="mptbm-custom-search-input" placeholder="Search locations..." style="width: 100% !important; padding: 8px !important; border: none !important; border-bottom: 1px solid #eee !important; border-radius: 4px 4px 0 0 !important; font-size: 14px !important; box-sizing: border-box !important; background: #F5F6F8 !important; color: #222222 !important; font-weight: 400 !important; outline: none !important;" />');
        
        // Create options container
        var $optionsContainer = $('<div class="mptbm-custom-options" style="max-height: 200px !important; overflow-y: auto !important; background: white !important;"></div>');
        
        // Get all options from original select (excluding disabled ones)
        var $originalOptions = $select.find('option:not([disabled])');
        var optionsHtml = '';
        
        console.log('Creating custom dropdown with', $originalOptions.length, 'options');
        
        $originalOptions.each(function() {
            var optionText = $(this).text();
            var optionValue = $(this).val();
            var isSelected = $(this).is(':selected');
            
            var selectedClass = isSelected ? 'mptbm-option-selected' : '';
            optionsHtml += '<div class="mptbm-custom-option ' + selectedClass + '" data-value="' + optionValue + '" style="padding: 8px !important; cursor: pointer !important; border-bottom: 1px solid #f5f5f5 !important; font-size: 14px !important; color: #222222 !important;">' + optionText + '</div>';
        });
        
        $optionsContainer.html(optionsHtml);
        
        // Assemble and append to body
        $customWrapper.append($searchInput).append($optionsContainer);
        $('body').append($customWrapper);
        
        // Ensure map elements are not affected by the dropdown
        $('.mptbm_map_area').css('z-index', '1');
        $('.mptbm_map_area #mptbm_map_area').css('z-index', '1');
        
        console.log('Custom select created');
        
        // Focus on search input
        $searchInput.focus();
        
        // Handle search input
        $searchInput.on('input', function() {
            var searchTerm = $(this).val().toLowerCase();
            var $options = $customWrapper.find('.mptbm-custom-option');
            
            console.log('Searching for:', searchTerm);
            
            $options.each(function() {
                var optionText = $(this).text().toLowerCase();
                if (optionText.includes(searchTerm) || searchTerm === '') {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
        
        // Handle option selection
        $customWrapper.on('click', '.mptbm-custom-option', function() {
            var selectedValue = $(this).data('value');
            var selectedText = $(this).text();
            
            // Update original select
            $select.val(selectedValue);
            $select.trigger('change');
            
            // Update search input with selected text
            $searchInput.val(selectedText);
            
            // Remove custom wrapper (select stays visible)
            $customWrapper.remove();
            
            // Restore map z-index
            $('.mptbm_map_area').css('z-index', '');
            $('.mptbm_map_area #mptbm_map_area').css('z-index', '');
            
            console.log('Option selected:', selectedValue);
        });
        
        // Handle select change event to clean up custom dropdown
        $select.one('change', function() {
            $customWrapper.remove();
            // Restore map z-index
            $('.mptbm_map_area').css('z-index', '');
            $('.mptbm_map_area #mptbm_map_area').css('z-index', '');
        });
        
        // Handle clicking outside to close
        $(document).one('click', function(e) {
            if (!$(e.target).closest('.mptbm-custom-select-wrapper, #' + selectId).length) {
                $customWrapper.remove();
                // Restore map z-index
                $('.mptbm_map_area').css('z-index', '');
                $('.mptbm_map_area #mptbm_map_area').css('z-index', '');
                console.log('Custom select closed - clicked outside');
            }
        });
        
        // Handle window resize to reposition dropdown
        $(window).one('resize scroll', function() {
            $customWrapper.remove();
            // Restore map z-index
            $('.mptbm_map_area').css('z-index', '');
            $('.mptbm_map_area #mptbm_map_area').css('z-index', '');
            console.log('Custom select closed - window resize/scroll');
        });
        
        // Handle escape key
        $searchInput.on('keydown', function(e) {
            if (e.key === 'Escape') {
                $customWrapper.remove();
                // Restore map z-index
                $('.mptbm_map_area').css('z-index', '');
                $('.mptbm_map_area #mptbm_map_area').css('z-index', '');
                console.log('Custom select closed - escape key');
            }
        });
    });
    
    // Prevent native dropdown behavior for manual select elements
    $(document).on('focus mousedown keydown', '#mptbm_manual_start_place, #mptbm_manual_end_place', function(e) {
        // Only prevent if it's not already handled by our custom dropdown
        if (!$(e.target).closest('.mptbm-custom-select-wrapper').length) {
            if (e.type === 'focus' || e.type === 'mousedown' || 
                (e.type === 'keydown' && (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown' || e.key === 'ArrowUp'))) {
                e.preventDefault();
                e.stopPropagation();
            }
        }
    });

    // Handle extra info toggle functionality
    $(document).on('click', '.mptbm-info-button', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Info button clicked!'); // Debug log
        
        var $button = $(this);
        var postId = $button.data('post-id');
        var $vehicleWrapper = $button.closest('.mptbm-vehicle-wrapper');
        var $content = $vehicleWrapper.find('.mptbm-extra-info-content[data-post-id="' + postId + '"]');
        var $icon = $button.find('i');
        
        console.log('Post ID:', postId); // Debug log
        console.log('Vehicle wrapper found:', $vehicleWrapper.length); // Debug log
        console.log('Content found:', $content.length); // Debug log
        
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
        } else {
            console.log('No content found for post ID:', postId); // Debug log
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

// Enable search in Drop-Off and Pickup Location dropdowns using selectWoo if available
jQuery(document).ajaxComplete(function() {
    if (typeof jQuery.fn.selectWoo !== 'undefined') {
        // Drop-Off Location
        jQuery('.mptbm_map_end_place').each(function() {
            if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                jQuery(this).selectWoo({
                    width: '100%',
                    placeholder: jQuery(this).find('option[selected]').text() || 'Select Destination Location',
                    allowClear: true
                });
            }
        });
        // Pickup Location (manual mode)
        jQuery('.mptbm_manual_start_place').each(function() {
            if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                jQuery(this).selectWoo({
                    width: '100%',
                    placeholder: jQuery(this).find('option[selected]').text() || 'Select Pick-Up Location',
                    allowClear: true
                });
            }
        });
    }
});


