// The browser's back/forward cache (bfcache) can restore this page exactly
// as it was in memory -- DOM, JS variables, everything -- without re-running
// a request. That includes whatever search results/checkout markup were
// injected via AJAX, with the mptbm_add_to_cart_nonce that was valid *then*.
// Clicking Book Now on that restored page resubmits stale state (the earlier
// Book Now click already emptied/rebuilt the cart), which can fail with no
// visible error. event.persisted is true only when the page came from
// bfcache, not a normal load -- this file is enqueued on every frontend page
// (not just the booking one), so the reload is gated on the injected search-
// result/checkout markup actually being present, rather than firing site-wide
// on every back-navigation.
window.addEventListener('pageshow', function (event) {
    if (event.persisted && document.querySelector('.mptbm_map_search_result, .mptbm_order_summary')) {
        window.location.reload();
    }
});

let mptbm_map;
let mptbm_map_window;
var mptbm_start_marker = null;
var mptbm_end_marker = null;
var mptbm_extra_marker = null;

// Collects the multi-row "Add Extra Stop" values (get_details.php's
// .mptbm_extra_stops_wrapper) for the search-submit AJAX payloads below.
// The legacy single #mptbm_map_extra_stop_place input no longer exists in
// the markup (removed in favor of these rows), so this is the only working
// source of extra-stop data - without it, jQuery's parent.find('#mptbm_map_extra_stop_place')
// always resolves to an empty jQuery object and every request silently
// submitted zero stops regardless of what the customer added.
function mptbm_collect_extra_stop_places($scope) {
    var places = [];
    ($scope || jQuery(document)).find('.mptbm_extra_stop_place_input').each(function () {
        var val = jQuery(this).val();
        if (val) {
            places.push(val);
        }
    });
    return places;
}

function mptbm_collect_extra_stop_coordinates($scope) {
    var coords = [];
    ($scope || jQuery(document)).find('.mptbm_extra_stop_coords').each(function () {
        var val = jQuery(this).val();
        if (val) {
            coords.push(val);
        }
    });
    return coords;
}

// OpenStreetMap variables
var mptbm_osm_map = null;
var mptbm_osm_markers = [];
var mptbm_osm_route = null;
var mptbm_osm_start_marker = null;
var mptbm_osm_end_marker = null;
var mptbm_osm_extra_marker = null;

// Per-pageload cache of address-search results, keyed by lowercased query
// text -- re-typing something already searched (or the other field
// matching the same text) resolves instantly with no network round trip.
var mptbm_osm_search_cache = {};

// Base Price global variables
var mptbm_base_to_pickup_data = { distance: 0, duration: 0 };
var mptbm_dropoff_to_base_data = { distance: 0, duration: 0 };

function mptbm_calculate_base_distances(settings, pickup, dropoff, callback) {
    if (!settings || !settings.coords || (!pickup && !dropoff)) {
        if (callback) callback({ distance: 0, duration: 0 });
        return;
    }

    var mapType = document.getElementById('mptbm_map_type');
    var isOSM = mapType && mapType.value === 'openstreetmap';

    if (isOSM) {
        mptbm_calculate_base_distances_osm(settings, pickup, dropoff, callback);
    } else {
        mptbm_calculate_base_distances_google(settings, pickup, dropoff, callback);
    }
}

function mptbm_calculate_base_distances_google(settings, pickup, dropoff, callback) {
    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        if (callback) callback({ distance: 0, duration: 0 });
        return;
    }

    var service = new google.maps.DistanceMatrixService();
    var origins = [];
    var destinations = [];

    // Charge Pickup: Base -> Pickup
    if (settings.charge_pickup === 'yes' && pickup) {
        origins.push(settings.coords);
        destinations.push(pickup);
    }

    // Charge Dropoff: Dropoff -> Base
    if (settings.charge_dropoff === 'yes' && dropoff) {
        origins.push(dropoff);
        destinations.push(settings.coords);
    }

    if (origins.length === 0) {
        if (callback) callback({ distance: 0, duration: 0 });
        return;
    }

    service.getDistanceMatrix({
        origins: origins,
        destinations: destinations,
        travelMode: google.maps.TravelMode.DRIVING,
        unitSystem: google.maps.UnitSystem.METRIC,
    }, function (response, status) {
        var result = { distance: 0, duration: 0 };
        if (status === 'OK') {
            var idx = 0;
            // The results are in order of origins. Since each origin has one destination in our mapping:
            // If we have both pickup and dropoff, origins[0] is Base, destinations[0] is Pickup.
            // origins[1] is Dropoff, destinations[1] is Base.
            // Wait, getDistanceMatrix returns a matrix (origins x destinations).
            // This might result in 4 results if we pass [Base, Dropoff] and [Pickup, Base].
            // We only need (Base, Pickup) and (Dropoff, Base).

            // To keep it simple, let's just loop and pick the diagonal if we structured it right, 
            // but DistanceMatrix is more like a grid.

            // Better: use two separate requests or just parse the matrix correctly.
            // Response.rows[i].elements[j]

            if (settings.charge_pickup === 'yes' && pickup) {
                var element = response.rows[idx].elements[idx];
                if (element.status === 'OK') {
                    result.distance += element.distance.value;
                    result.duration += element.duration.value;
                }
                idx++;
            }
            if (settings.charge_dropoff === 'yes' && dropoff) {
                var element = response.rows[idx].elements[idx];
                if (element.status === 'OK') {
                    result.distance += element.distance.value;
                    result.duration += element.duration.value;
                }
            }
        }
        if (callback) callback(result);
    });
}

function mptbm_calculate_base_distances_osm(settings, pickup, dropoff, callback) {
    // For OSM, we need coordinates. If pickup/dropoff are names, we might need geocoding first.
    // However, the plugin seems to handle coordinate selection for OSM.

    var baseCoords = settings.coords.split(',');
    var baseLat = baseCoords[0].trim();
    var baseLng = baseCoords[1].trim();

    // We'll use the OSRM API directly or via a proxy if needed.
    // For now, let's assume we can use the same project OSRM as used in mptbm_calculate_osm_distance

    var totalDistance = 0;
    var totalDuration = 0;
    var pending = 0;

    function handleResult(data) {
        if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
            totalDistance += data.routes[0].distance;
            totalDuration += data.routes[0].duration;
        }
        pending--;
        if (pending === 0 && callback) {
            callback({ distance: totalDistance, duration: totalDuration });
        }
    }

    // Since OSRM handles point-to-point, we might need two calls if we don't want a single route.
    // Actually, we can't easily geocode names in JS here without an API.
    // Let's see if we have coordinates for pickup/dropoff.

    var startCoords = window.mptbm_fixed_zone_start_coords; // If set by mptbm_handle_osm_address_selection
    var endCoords = window.mptbm_fixed_zone_end_coords;

    if (settings.charge_pickup === 'yes' && startCoords) {
        pending++;
        var url = 'https://router.project-osrm.org/route/v1/driving/' + baseLng + ',' + baseLat + ';' + startCoords.longitude + ',' + startCoords.latitude + '?overview=false';
        fetch(url).then(r => r.json()).then(handleResult).catch(() => { pending--; if (pending === 0) callback({ distance: totalDistance, duration: totalDuration }); });
    }

    if (settings.charge_dropoff === 'yes' && endCoords) {
        pending++;
        var url = 'https://router.project-osrm.org/route/v1/driving/' + endCoords.longitude + ',' + endCoords.latitude + ';' + baseLng + ',' + baseLat + '?overview=false';
        fetch(url).then(r => r.json()).then(handleResult).catch(() => { pending--; if (pending === 0) callback({ distance: totalDistance, duration: totalDuration }); });
    }

    if (pending === 0 && callback) {
        callback({ distance: 0, duration: 0 });
    }
}

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

// Function to show location validation errors
function showLocationError(element, message) {
    // Remove any existing errors for this element
    var existingError = element.parentElement.querySelector('.mptbm-location-error');
    if (existingError) {
        existingError.remove();
    }

    // Add error class to input
    element.classList.add('mptbm-error-field');

    // Create error message element
    var errorDiv = document.createElement('div');
    errorDiv.className = 'mptbm-location-error';
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '8px';
    errorDiv.style.marginBottom = '10px';
    errorDiv.textContent = message;

    // Insert error message after the input
    element.parentElement.appendChild(errorDiv);
}

// Function to remove all location errors
function removeLocationErrors() {
    var errorFields = document.querySelectorAll('.mptbm-error-field');
    errorFields.forEach(function (field) {
        field.classList.remove('mptbm-error-field');
    });

    var errorMessages = document.querySelectorAll('.mptbm-location-error');
    errorMessages.forEach(function (error) {
        error.remove();
    });
}

function mptbm_resolve_redirect_url(response) {
    if (!response) {
        return '';
    }

    if (typeof response === 'object') {
        if (response.redirect_url) {
            return response.redirect_url;
        }

        if (response.data && response.data.redirect_url) {
            return response.data.redirect_url;
        }

        return '';
    }

    if (typeof response === 'string') {
        var cleaned = response.trim();

        if (!cleaned) {
            return '';
        }

        try {
            var parsed = JSON.parse(cleaned);

            if (typeof parsed === 'string') {
                cleaned = parsed;
            } else if (parsed && typeof parsed === 'object') {
                return parsed.redirect_url || (parsed.data && parsed.data.redirect_url) || '';
            }
        } catch (error) {
            // Keep the raw response when it is already a plain URL.
        }

        return cleaned
            .replace(/^"+|"+$/g, '')
            .replace(/\\\//g, '/');
    }

    return '';
}

// Add event listeners to clear errors when user starts typing
jQuery(document).ready(function ($) {
    // ---------------------------------------------------------------------
    // Refresh the booking nonce for cached pages.
    // Full-page caches serve logged-out visitors an HTML page whose embedded
    // WordPress nonce expires after ~24h, so the search AJAX then fails with a
    // 403/-1 ("works when logged in, fails when logged out"). admin-ajax.php is
    // never full-page cached, so we pull a live nonce here and use it for every
    // request. The user fills the pickup/dropoff/date form before searching, so
    // this round-trip has completed long before the first search fires.
    // ---------------------------------------------------------------------
    window.mptbmNonceReady = (function () {
        if (typeof mp_ajax_url === 'undefined' || typeof mptbm_ajax === 'undefined') {
            return $.Deferred().resolve().promise();
        }
        return $.ajax({
            type: 'POST',
            url: mp_ajax_url,
            data: { action: 'mptbm_refresh_search_nonce' },
            dataType: 'json'
        }).done(function (res) {
            if (res && res.success && res.data) {
                if (res.data.search_nonce) {
                    mptbm_ajax.search_nonce = res.data.search_nonce;
                }
                if (res.data.add_to_cart_nonce) {
                    mptbm_ajax.add_to_cart_nonce = res.data.add_to_cart_nonce;
                }
            }
        });
    })();

    // Clear errors on input for pickup location
    $(document).on('input change', '#mptbm_map_start_place, #mptbm_manual_start_place', function () {
        if (this.classList.contains('mptbm-error-field')) {
            this.classList.remove('mptbm-error-field');
            var errorMsg = this.parentElement.querySelector('.mptbm-location-error');
            if (errorMsg) {
                errorMsg.remove();
            }
        }
    });

    // Clear errors on input for dropoff location
    $(document).on('input change', '#mptbm_map_end_place, #mptbm_manual_end_place', function () {
        if (this.classList.contains('mptbm-error-field')) {
            this.classList.remove('mptbm-error-field');
            var errorMsg = this.parentElement.querySelector('.mptbm-location-error');
            if (errorMsg) {
                errorMsg.remove();
            }
        }
    });

    // Clear errors on input for extra stop location
    $(document).on('input change', '#mptbm_map_extra_stop_place', function () {
        if (this.classList.contains('mptbm-error-field')) {
            this.classList.remove('mptbm-error-field');
            var errorMsg = this.parentElement.querySelector('.mptbm-location-error');
            if (errorMsg) {
                errorMsg.remove();
            }
        }
    });
});

function mptbm_set_cookie_distance_duration(start_place, end_place) {


    // Check if OpenStreetMap is active
    var mapType = document.getElementById('mptbm_map_type');

    if (mapType && mapType.value === 'openstreetmap') {
        return false;
    }

    // Safari compatibility: provide default values
    start_place = start_place || "";
    end_place = end_place || "";

    // Check if map container exists before initializing
    var mapContainer = document.getElementById("mptbm_map_area");
    if (!mapContainer) {
        return false;
    }

    // Check if Google Maps API is loaded
    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        return false;
    }

    // Only create a new map if one doesn't exist
    if (!mptbm_map) {
        mptbm_map = new google.maps.Map(mapContainer, {
            mapTypeControl: false,
            center: mp_lat_lng,
            zoom: 15,
        });
    }

    // Check if we have enough locations to calculate a route
    // We need at least start_place and either end_place OR extra_stop
    var extra_stop = jQuery('#mptbm_map_extra_stop_place').val();

    // Multi-row "Add Extra Stop" inputs (get_details.php's
    // .mptbm_extra_stops_wrapper) - read fresh each call, in DOM order, so
    // added/edited/removed rows are always reflected without needing every
    // caller of this function to be updated. Google's Directions API
    // geocodes plain address text itself, so the input's value is enough -
    // no need to touch the paired coordinate field here.
    var multi_stop_addresses = [];
    jQuery('.mptbm_extra_stop_place_input').each(function () {
        var val = jQuery(this).val();
        if (val) {
            multi_stop_addresses.push(val);
        }
    });

    if (start_place && (end_place || extra_stop || multi_stop_addresses.length)) {
        var directionsService = new google.maps.DirectionsService();
        var directionsRenderer = new google.maps.DirectionsRenderer();
        directionsRenderer.setMap(mptbm_map);

        // If dropoff isn't set yet, fall back to the last known stop (a
        // multi-row stop if any exist, otherwise the legacy single extra
        // stop) as a temporary destination so a route still draws.
        var lastMultiStop = multi_stop_addresses.length ? multi_stop_addresses[multi_stop_addresses.length - 1] : '';
        var actualDestination = end_place || lastMultiStop || extra_stop;
        var useExtraAsWaypoint = (end_place || multi_stop_addresses.length) && extra_stop; // Only use legacy extra as waypoint if it's not the destination

        var waypoints = [];
        if (useExtraAsWaypoint) {
            waypoints.push({
                location: extra_stop,
                stopover: true
            });
        }
        multi_stop_addresses.forEach(function (address, index) {
            // The last multi-stop is being used as the temporary destination
            // above when dropoff isn't set yet - don't also list it as a waypoint.
            if (!end_place && index === multi_stop_addresses.length - 1) {
                return;
            }
            waypoints.push({
                location: address,
                stopover: true
            });
        });


        var request = {
            origin: start_place,
            destination: actualDestination,
            waypoints: waypoints,
            travelMode: google.maps.TravelMode.DRIVING,
            unitSystem: google.maps.UnitSystem.METRIC,
        };


        var now = new Date();
        var time = now.getTime();
        var expireTime = time + 3600 * 1000 * 12;
        now.setTime(expireTime);

        // Safari compatibility: use function instead of arrow function
        directionsService.route(request, function (result, status) {

            if (status === google.maps.DirectionsStatus.OK) {
                try {
                    // Sum all legs of the route (important when waypoints/extra stops are used)
                    var totalDistance = 0;
                    var totalDuration = 0;
                    var legs = result.routes[0].legs;


                    for (var i = 0; i < legs.length; i++) {
                        totalDistance += legs[i].distance.value;
                        totalDuration += legs[i].duration.value;
                    }


                    var distance = totalDistance;
                    var duration = totalDuration;

                    var kmOrMileElement = document.getElementById("mptbm_km_or_mile");
                    var kmOrMile = kmOrMileElement ? kmOrMileElement.value : 'km';
                    var distance_text;
                    var duration_text;

                    if (kmOrMile == 'mile') {
                        // Convert distance from meters to miles
                        var distanceInKilometers = distance / 1000;
                        var distanceInMiles = distanceInKilometers * 0.621371;
                        distance_text = distanceInMiles.toFixed(1) + ' miles';
                    } else {
                        // Convert distance from meters to kilometers
                        var distanceInKilometers = distance / 1000;
                        distance_text = distanceInKilometers.toFixed(1) + ' km';
                    }

                    // Format duration (convert seconds to hours and minutes)
                    var hours = Math.floor(duration / 3600);
                    var minutes = Math.round((duration % 3600) / 60);
                    if (hours > 0) {
                        // duration_text = hours + ' hour' + (hours > 1 ? 's' : '') + ' min';
                        duration_text = hours + ' hour' + (hours > 1 ? 's' : '') + ' ' + minutes + ' min';
                    } else {
                        duration_text = minutes + ' min';
                    }

                    // Safari compatibility: set cookies with proper encoding
                    var cookieOptions = "; expires=" + now.toUTCString() + "; path=/; SameSite=Lax";
                    document.cookie = "mptbm_distance=" + encodeURIComponent(distance) + cookieOptions;
                    document.cookie = "mptbm_distance_text=" + encodeURIComponent(distance_text) + cookieOptions;
                    document.cookie = "mptbm_duration=" + encodeURIComponent(duration) + cookieOptions;
                    document.cookie = "mptbm_duration_text=" + encodeURIComponent(duration_text) + cookieOptions;

                    // Fallback: Update hidden fields for AJAX transmission (when cookies are blocked)
                    var mapArea = jQuery('#mptbm_map_area').closest('.mptbm_transport_search_area');
                    if (mapArea.length > 0) {
                        if (mapArea.find('input[name="mptbm_hidden_distance"]').length === 0) {
                            mapArea.append('<input type="hidden" name="mptbm_hidden_distance" value="" />');
                        }
                        if (mapArea.find('input[name="mptbm_hidden_duration"]').length === 0) {
                            mapArea.append('<input type="hidden" name="mptbm_hidden_duration" value="" />');
                        }

                        // Also update our explicit hidden fields if they exist
                        var explicitDistance = document.getElementById('mptbm_calculated_distance');
                        if (explicitDistance) {
                            explicitDistance.value = distance;
                        }
                        var explicitDuration = document.getElementById('mptbm_calculated_duration');
                        if (explicitDuration) {
                            explicitDuration.value = duration;
                        }

                        // Add hidden inputs for text values as well (needed for cart display)
                        if (mapArea.find('input[name="mptbm_hidden_distance_text"]').length === 0) {
                            mapArea.append('<input type="hidden" name="mptbm_hidden_distance_text" value="" />');
                        }
                        if (mapArea.find('input[name="mptbm_hidden_duration_text"]').length === 0) {
                            mapArea.append('<input type="hidden" name="mptbm_hidden_duration_text" value="" />');
                        }

                        mapArea.find('input[name="mptbm_hidden_distance"]').val(distance);
                        mapArea.find('input[name="mptbm_hidden_duration"]').val(duration);
                        mapArea.find('input[name="mptbm_hidden_distance_text"]').val(distance_text);
                        mapArea.find('input[name="mptbm_hidden_duration_text"]').val(duration_text);
                    }

                    directionsRenderer.setDirections(result);

                    // Update UI elements
                    jQuery(".mptbm_total_distance").html(distance_text);
                    jQuery(".mptbm_total_time").html(duration_text);
                    jQuery(".mptbm_distance_time").slideDown("fast");
                    mptbm_update_fixed_hours_warning();


                } catch (error) {
                    // Use fallback for Safari
                    if (mptbm_is_safari()) {
                        mptbm_fallback_distance_calculation(start_place, end_place);
                    }
                }
            } else {

                // Use fallback for Safari when API fails
                if (mptbm_is_safari()) {
                    mptbm_fallback_distance_calculation(start_place, end_place);
                } else {
                    // Show user-friendly error message for other browsers
                    jQuery(".mptbm_total_distance").html("Error calculating distance");
                    jQuery(".mptbm_total_time").html("Error calculating time");
                    jQuery(".mptbm_distance_time").slideDown("fast");
                }
            }
        });
    } else if (start_place || end_place) {
        var place = start_place ? start_place : end_place;
        mptbm_map_window = new google.maps.InfoWindow();

        // Check if map container exists before initializing
        var mapContainer = document.getElementById("mptbm_map_area");
        if (!mapContainer) {
            console.warn("Map container #mptbm_map_area not found. Map initialization skipped.");
            return false;
        }

        // Only create a new map if one doesn't exist
        if (!mptbm_map) {
            mptbm_map = new google.maps.Map(mapContainer, {
                center: mp_lat_lng,
                zoom: 15,
            });
        }

        var request = {
            query: place,
            fields: ["name", "geometry"],
        };

        // Check if place is a coordinate string (Lat,Lng) to avoid INVALID_REQUEST from Places API
        // Dictionary-style check or Regex
        var coordPattern = /^-?\d+(\.\d+)?,\s*-?\d+(\.\d+)?$/;
        if (coordPattern.test(place)) {
            // It's a coordinate string. Manually create marker instead of calling Places API.
            var parts = place.split(',');
            var lat = parseFloat(parts[0]);
            var lng = parseFloat(parts[1]);

            if (!isNaN(lat) && !isNaN(lng)) {
                var location = new google.maps.LatLng(lat, lng);
                // Mock a place result object
                var mockPlace = {
                    geometry: { location: location },
                    name: place
                };
                mptbmCreateMarker(mockPlace);
                mptbm_map.setCenter(location);
                return true;
            }
        }

        var service = new google.maps.places.PlacesService(mptbm_map);
        // Safari compatibility: use function instead of arrow function
        service.findPlaceFromQuery(request, function (results, status) {

            if (status === google.maps.places.PlacesServiceStatus.OK && results) {
                for (var i = 0; i < results.length; i++) {
                    mptbmCreateMarker(results[i]);
                }
                mptbm_map.setCenter(results[0].geometry.location);
            } else {
                console.error("Places API error:", status);
            }
        });
    } else {
        var directionsRenderer = new google.maps.DirectionsRenderer();
        directionsRenderer.setMap(mptbm_map);
        //document.getElementById('mptbm_map_start_place').focus();
    }
    return true;
}
function mptbmCreateMarker(place) {
    if (!place.geometry || !place.geometry.location) return;

    // Clear existing markers before adding new ones
    if (mptbm_map && mptbm_map.markers) {
        mptbm_map.markers.forEach(function (marker) {
            marker.setMap(null);
        });
        mptbm_map.markers = [];
    } else if (mptbm_map) {
        mptbm_map.markers = [];
    }

    // Safari compatibility: use var instead of const
    var marker = new google.maps.Marker({
        map: mptbm_map,
        position: place.geometry.location,
    });

    // Store marker reference for future clearing
    if (mptbm_map) {
        mptbm_map.markers = mptbm_map.markers || [];
        mptbm_map.markers.push(marker);
    }

    // Safari compatibility: use function instead of arrow function
    google.maps.event.addListener(marker, "click", function () {
        if (mptbm_map_window) {
            mptbm_map_window.setContent(place.name || "");
            mptbm_map_window.open(mptbm_map);
        }
    });
}
// Every tab's template (Hourly, Distance, ...) renders its own
// id="mptbm_map_area" div. Once a second tab has been loaded via AJAX, the
// DOM holds more than one element sharing that id, so a bare
// getElementById/querySelector always resolves to whichever one appears
// first in the DOM -- typically the original (now hidden) tab, not the one
// actually on screen. Scope the lookup to the visible tab when tabs exist,
// and fall back to a bare lookup for the tabless [mptbm_booking] shortcode.
function mptbm_get_current_map_area() {
    var scoped = document.querySelector('.mptb-tab-content.current #mptbm_map_area');
    return scoped || document.getElementById('mptbm_map_area');
}

function mptbm_get_current_map_wrap() {
    var scoped = document.querySelector('.mptb-tab-content.current .mptbm_map_area');
    return scoped || document.querySelector('.mptbm_map_area');
}

function mptbm_map_area_init() {

    // Check if map container exists and is visible before initializing
    var mapContainer = mptbm_get_current_map_area();
    if (!mapContainer) {
        console.warn("[Map Init] Map container #mptbm_map_area not found. Skipping map initialization.");
        return false;
    }

    // Check if the map container is visible (not hidden by CSS)
    var mapArea = mptbm_get_current_map_wrap();
    if (mapArea && mapArea.style.display === 'none') {
        // If map is hidden, we still want to initialize address search for OSM
        var mapType = document.getElementById('mptbm_map_type');
        if (mapType && mapType.value === 'openstreetmap') {
            mptbm_init_osm_address_search();
        }
        console.warn("[Map Init] Map area is hidden. Map rendering skipped, but address search may continue.");
        return false;
    }

    // Check map type setting
    var mapType = document.getElementById('mptbm_map_type');

    if (!mapType) {
        mapType = { value: 'enable' };
    }


    // Initialize based on map type
    if (mapType.value === 'openstreetmap') {
        return mptbm_init_osm_map();
    } else if (mapType.value === 'enable') {
        return mptbm_init_google_map();
    } else {
        return false;
    }
}

// Safety net called right before the first marker is placed: if the map
// somehow isn't up yet (e.g. its container was hidden when
// mptbm_map_area_init() ran), build it lazily now instead of silently
// dropping the marker. A no-op once the map already exists.
function mptbm_ensure_osm_map_ready() {
    if (mptbm_osm_map) {
        return true;
    }
    var mapContainer = mptbm_get_current_map_area();
    if (!mapContainer) {
        return false;
    }
    mapContainer.innerHTML = '';
    return mptbm_init_osm_map();
}

function mptbm_init_osm_map() {

    if (typeof L === 'undefined') {
        return false;
    }

    // Check if map container exists
    var mapContainer = mptbm_get_current_map_area();
    if (!mapContainer) {
        return false;
    }

    // Clean up existing map instance if it exists
    if (mptbm_osm_map) {
        try {
            mptbm_osm_map.remove();
            mptbm_osm_map = null;
            mptbm_osm_markers = [];
            mptbm_osm_route = null;
            mptbm_osm_start_marker = null;
            mptbm_osm_end_marker = null;
            mptbm_osm_extra_marker = null;
        } catch (e) {
            console.log("[OSM] Error removing map:", e);
        }
    }


    // Get default coordinates from PHP or use fallback
    var defaultLat = (typeof mptbm_default_lat !== 'undefined') ? mptbm_default_lat : 40.7128;
    var defaultLng = (typeof mptbm_default_lng !== 'undefined') ? mptbm_default_lng : -74.0060;

    // Initialize OpenStreetMap with configured coordinates. Passed as the
    // resolved element (not the 'mptbm_map_area' id string) since that id is
    // duplicated across tabs -- Leaflet would otherwise resolve it via its
    // own getElementById and land on the wrong tab's div.
    mptbm_osm_map = L.map(mapContainer).setView([defaultLat, defaultLng], 10);

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(mptbm_osm_map);

    // Initialize address search functionality
    mptbm_init_osm_address_search();

    return true;
}

function mptbm_init_osm_address_search() {

    // Clean up any existing autocomplete containers
    var existingContainers = document.querySelectorAll('.mptbm-osm-autocomplete');
    existingContainers.forEach(function (container) {
        container.remove();
    });

    var startInput = document.getElementById('mptbm_map_start_place');
    var endInput = document.getElementById('mptbm_map_end_place');


    if (startInput) {
        mptbm_setup_osm_autocomplete(startInput, 'start');
    }
    if (endInput) {
        mptbm_setup_osm_autocomplete(endInput, 'end');
    }
    var extraInput = document.getElementById('mptbm_map_extra_stop_place');
    if (extraInput) {
        mptbm_setup_osm_autocomplete(extraInput, 'extra');
    }
}

function mptbm_cleanup_osm_autocomplete(input) {
    if (!input || !input._mptbmOsmAutocomplete) {
        return;
    }

    var state = input._mptbmOsmAutocomplete;

    if (state.debounceTimer) {
        clearTimeout(state.debounceTimer);
    }

    if (state.abortController) {
        state.abortController.abort();
    }

    if (state.handlers) {
        input.removeEventListener('input', state.handlers.input);
        window.removeEventListener('scroll', state.handlers.positionDropdown);
        window.removeEventListener('resize', state.handlers.positionDropdown);
        document.removeEventListener('click', state.handlers.documentClick);
    }

    if (state.container && state.container.parentNode) {
        state.container.parentNode.removeChild(state.container);
    }

    delete input._mptbmOsmAutocomplete;
    input.removeAttribute('data-osm-autocomplete-initialized');
}

function mptbm_setup_osm_autocomplete(input, type) {
    mptbm_cleanup_osm_autocomplete(input);

    var autocompleteState = {
        abortController: null,
        container: null,
        currentSearchQuery: '',
        debounceTimer: null,
        handlers: null
    };
    var resultsContainer = document.createElement('div');
    resultsContainer.className = 'mptbm-osm-autocomplete';
    resultsContainer.setAttribute('data-autocomplete-type', type);
    resultsContainer.style.cssText = 'position: fixed; box-sizing: border-box; font-size:14px; background: #fff; border: 1px solid #e7eaf0; border-radius: 14px; max-height: 240px; overflow-y: auto; z-index: 99999 !important; display: none; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12), 0 2px 8px rgba(15, 23, 42, 0.06); padding: 6px;';

    // Append to body to avoid parent overflow issues
    document.body.appendChild(resultsContainer);
    autocompleteState.container = resultsContainer;
    input._mptbmOsmAutocomplete = autocompleteState;

    // Mark input as initialized
    input.setAttribute('data-osm-autocomplete-initialized', 'true');

    // Function to position the dropdown
    function positionDropdown() {
        var rect = input.getBoundingClientRect();
        // For fixed positioning, don't add scroll offset - use viewport coordinates directly
        var top = rect.bottom + 2;
        var left = rect.left;
        var width = rect.width;

        resultsContainer.style.top = top + 'px';
        resultsContainer.style.left = left + 'px';
        resultsContainer.style.width = width + 'px';

    }

    function handleInput(e) {
        clearTimeout(autocompleteState.debounceTimer);
        var query = e.target.value.trim();

        if (query.length < 3) {
            if (autocompleteState.abortController) {
                autocompleteState.abortController.abort();
                autocompleteState.abortController = null;
            }
            resultsContainer.style.display = 'none';
            autocompleteState.currentSearchQuery = '';
            return;
        }

        // Store the current query
        autocompleteState.currentSearchQuery = query;

        autocompleteState.debounceTimer = setTimeout(function () {
            positionDropdown();
            mptbm_search_osm_address(query, resultsContainer, input, type, autocompleteState.currentSearchQuery, autocompleteState);
        }, 300);
    }

    input.addEventListener('input', handleInput);

    // Reposition on scroll or resize
    window.addEventListener('scroll', positionDropdown);
    window.addEventListener('resize', positionDropdown);

    // Hide results when clicking outside
    function handleDocumentClick(e) {
        if (e.target !== input && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    }

    document.addEventListener('click', handleDocumentClick);

    autocompleteState.handlers = {
        input: handleInput,
        positionDropdown: positionDropdown,
        documentClick: handleDocumentClick
    };
}

// Bangladesh-specific fallback matching, ported from the old server-side
// proxy (MPTBM_Dependencies::osm_search_proxy) -- Photon's country field is
// sometimes empty for BD results, so city/state name also counts as a match.
var MPTBM_BD_CITIES = ['DHAKA', 'CHITTAGONG', 'SYLHET', 'RAJSHAHI', 'KHULNA', 'BARISAL', 'RANGPUR', 'COMILLA', 'NARAYANGANJ', 'GAZIPUR'];
var MPTBM_BD_STATES = ['DHAKA', 'CHITTAGONG', 'SYLHET', 'RAJSHAHI', 'KHULNA', 'BARISAL', 'RANGPUR', 'DIVISION'];

// Same GeoJSON -> {display_name, lat, lon} shape and country filtering the
// PHP proxy used to do server-side, ported 1:1 so behavior is unchanged now
// that the call happens directly from the browser.
function mptbm_transform_photon_results(geojson, restrictToCountry, countryCode) {
    var results = [];
    if (!geojson || !Array.isArray(geojson.features)) {
        return results;
    }

    geojson.features.forEach(function (feature) {
        var props = feature.properties || {};
        var coords = (feature.geometry && feature.geometry.coordinates) || [];

        if (restrictToCountry && countryCode) {
            var featureCountry = (props.countrycode || '').toUpperCase();
            var featureCountryName = (props.country || '').toUpperCase();
            var featureState = (props.state || '').toUpperCase();
            var featureCity = (props.city || '').toUpperCase();
            var matches = false;

            if (featureCountry && featureCountry === countryCode) {
                matches = true;
            }

            if (!matches && countryCode === 'BD') {
                if (featureCountryName === 'BANGLADESH' || featureCountryName === 'BD') {
                    matches = true;
                }
                if (!matches && MPTBM_BD_CITIES.indexOf(featureCity) !== -1) {
                    matches = true;
                } else if (!matches && MPTBM_BD_STATES.indexOf(featureState) !== -1) {
                    matches = true;
                }
            }

            // No country info at all on this result -- allow it through
            // rather than silently dropping it.
            if (!featureCountry && !featureCountryName && !featureCity && !featureState) {
                matches = true;
            }

            if (!matches) {
                return;
            }
        }

        // Place name + city is enough to identify a pickup/drop-off for a
        // service operating in one city/country - state and country were
        // repeated on every single result (e.g. "..., Dhaka Division,
        // Bangladesh") and made addresses needlessly long throughout the
        // booking flow (summary, admin bookings list, order emails, ...).
        // Only fall back to the broader state/country if neither a specific
        // name nor a city came back at all, so a legitimately resolvable
        // rural result still shows something instead of "Unknown Location".
        var nameParts = [props.name, props.city].filter(Boolean);
        if (!nameParts.length) {
            nameParts = [props.state, props.country].filter(Boolean);
        }

        results.push({
            display_name: nameParts.length ? nameParts.join(', ') : 'Unknown Location',
            lat: coords.length > 1 ? coords[1] : 0,
            lon: coords.length > 0 ? coords[0] : 0
        });
    });

    return results;
}

function mptbm_render_osm_search_results(results, container, input, type) {
    container.innerHTML = '';

    if (!results || results.length === 0) {
        container.innerHTML = '<div style="padding: 9px 12px; box-sizing: border-box; display: flex; align-items: center; justify-content: center; text-align: center; color: #94a3b8; font-size: 13px; font-weight: 600;">No results found</div>';
        container.style.display = 'block';
        return;
    }

    results.forEach(function (result) {
        var item = document.createElement('div');
        item.style.cssText = 'display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; margin: 2px 0; cursor: pointer; border-radius: 10px; color: #0f172a; font-size: 13.5px; font-weight: 500; line-height: 1.4; transition: background-color .15s ease;';

        var icon = document.createElement('i');
        icon.className = 'fas fa-map-marker-alt';
        icon.style.cssText = 'flex: 0 0 auto; width: 14px; margin-top: 2px; color: #94a3b8; font-size: 13px;';

        var text = document.createElement('span');
        text.textContent = result.display_name;
        text.style.cssText = 'flex: 1 1 auto; min-width: 0;';

        item.appendChild(icon);
        item.appendChild(text);

        item.addEventListener('click', function () {
            input.value = result.display_name;
            container.style.display = 'none';
            mptbm_handle_osm_address_selection(result, type);
        });

        item.addEventListener('mouseenter', function () {
            this.style.backgroundColor = '#f1f4f9';
        });

        item.addEventListener('mouseleave', function () {
            this.style.backgroundColor = 'transparent';
        });

        container.appendChild(item);
    });

    container.style.display = 'block';
}

// Calls Photon directly from the browser instead of proxying through
// admin-ajax.php. The proxy added a full WordPress bootstrap plus an
// outbound request from the server itself on every keystroke search --
// on hosts where the server's own outbound connection is slow (the same
// class of issue as a cURL timeout to any other external API), that hop
// alone could make autocomplete take several seconds per query. Calling
// Photon straight from the visitor's browser removes that hop entirely;
// Photon's public API already supports CORS for exactly this use.
function mptbm_search_osm_address(query, container, input, type, expectedQuery, autocompleteState, retryCount) {
    retryCount = retryCount || 0;
    var cacheKey = query.toLowerCase();
    var cached = mptbm_osm_search_cache[cacheKey];
    if (cached) {
        mptbm_render_osm_search_results(cached, container, input, type);
        return;
    }

    container.innerHTML = '<div style="padding: 9px 12px; box-sizing: border-box; display: flex; align-items: center; justify-content: center; text-align: center; color: #94a3b8; font-size: 13px; font-weight: 600;">Searching&hellip;</div>';
    container.style.display = 'block';

    var restrictEl = document.getElementById('mptbm_restrict_search_country');
    var countryEl = document.getElementById('mptbm_country');
    var restrictToCountry = !!restrictEl && restrictEl.value === 'yes';
    var countryCode = (countryEl && countryEl.value ? countryEl.value : '').toUpperCase();

    var abortController = null;
    var timeoutId = null;
    if (autocompleteState && typeof AbortController !== 'undefined') {
        if (autocompleteState.abortController) {
            autocompleteState.abortController.abort();
        }

        abortController = new AbortController();
        autocompleteState.abortController = abortController;
        // Photon is a shared public instance -- bound the wait instead of
        // leaving "Searching..." on screen indefinitely if it stalls.
        timeoutId = setTimeout(function () {
            abortController.abort();
        }, 8000);
    }

    var url = 'https://photon.komoot.io/api/?q=' + encodeURIComponent(query) + '&limit=5&lang=en';

    fetch(url, {
        signal: abortController ? abortController.signal : undefined
    })
        .then(function (response) {
            return response.json();
        })
        .then(function (geojson) {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
            if (autocompleteState && autocompleteState.abortController === abortController) {
                autocompleteState.abortController = null;
            }

            // Check if this response is still relevant (user hasn't typed more)
            var currentValue = input.value.trim();
            if (expectedQuery && currentValue !== expectedQuery) {
                return;
            }

            var results = mptbm_transform_photon_results(geojson, restrictToCountry, countryCode);
            mptbm_osm_search_cache[cacheKey] = results;
            mptbm_render_osm_search_results(results, container, input, type);
        })
        .catch(function (error) {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
            if (autocompleteState && autocompleteState.abortController === abortController) {
                autocompleteState.abortController = null;
            }

            if (error && error.name === 'AbortError') {
                return;
            }

            // fetch() collapses every network-layer failure (DNS hiccup, a
            // VPN/Wi-Fi adapter flapping mid-request -- ERR_NETWORK_CHANGED,
            // a dropped connection, ...) into this same generic "Failed to
            // fetch" TypeError with no way to tell them apart. Most of those
            // are transient and gone a moment later, so retry once
            // automatically before bothering the visitor with an error --
            // only if they haven't since typed something else.
            console.error('[OSM Search] Fetch error:', error);
            if (retryCount < 1 && input.value.trim() === expectedQuery) {
                setTimeout(function () {
                    if (input.value.trim() === expectedQuery) {
                        mptbm_search_osm_address(query, container, input, type, expectedQuery, autocompleteState, retryCount + 1);
                    }
                }, 700);
                return;
            }

            container.innerHTML = '<div style="padding: 9px 12px; box-sizing: border-box; display: flex; align-items: center; justify-content: center; text-align: center; color: #dc2626; font-size: 13px; font-weight: 600;">Search failed. Please try again.</div>';
            container.style.display = 'block';
        });
}

function mptbm_handle_osm_address_selection(address, type) {
    var lat = parseFloat(address.lat);
    var lng = parseFloat(address.lon);
    var price_based = jQuery('[name="mptbm_price_based"]').val();

    // First address picked: swap the idle Google preview for the real map.
    mptbm_ensure_osm_map_ready();

    // Remove existing marker for this type
    if (type === 'start' && mptbm_osm_start_marker) {
        mptbm_osm_map.removeLayer(mptbm_osm_start_marker);
    } else if (type === 'end' && mptbm_osm_end_marker) {
        mptbm_osm_map.removeLayer(mptbm_osm_end_marker);
    } else if (type === 'extra' && mptbm_osm_extra_marker) {
        mptbm_osm_map.removeLayer(mptbm_osm_extra_marker);
    }

    // Create new marker if map exists
    if (mptbm_osm_map) {
        var marker = L.marker([lat, lng]).addTo(mptbm_osm_map);
        marker.bindPopup(address.display_name);

        if (type === 'start') {
            mptbm_osm_start_marker = marker;
            window.mptbm_fixed_zone_start_coords = { latitude: lat, longitude: lng };
            // Remembers exactly which address text these coordinates belong
            // to, so the Search button can reuse them instead of re-geocoding
            // the same text again -- see getCachedOrFreshCoordinates().
            window.mptbm_osm_start_coords_address = address.display_name;
        } else if (type === 'end') {
            mptbm_osm_end_marker = marker;
            window.mptbm_fixed_zone_end_coords = { latitude: lat, longitude: lng };
            window.mptbm_osm_end_coords_address = address.display_name;
        } else if (type === 'extra') {
            mptbm_osm_extra_marker = marker;
        }

        // Calculate distance if we have start marker and either end marker OR extra marker
        if (mptbm_osm_start_marker && (mptbm_osm_end_marker || mptbm_osm_extra_marker)) {
            mptbm_calculate_osm_distance();
        }

        // Fit map to show all markers
        var markersToFit = [mptbm_osm_start_marker, mptbm_osm_end_marker];
        if (mptbm_osm_extra_marker) markersToFit.push(mptbm_osm_extra_marker);

        var group = new L.featureGroup(markersToFit.filter(Boolean));
        if (group.getLayers().length > 0) {
            mptbm_osm_map.fitBounds(group.getBounds().pad(0.1));
        }
    }
}

// Warn the user when the selected "Select Hours" rental duration is shorter
// than the estimated drive time for the chosen pickup/drop-off route.
function mptbm_update_fixed_hours_warning() {
    var priceBasedEl = document.querySelector('[name="mptbm_price_based"]');
    if (!priceBasedEl || priceBasedEl.value !== 'fixed_hourly') return;

    var warningEl = document.getElementById('mptbm_fixed_hours_warning');
    var hoursEl = document.getElementById('mptbm_fixed_hours');
    var durationEl = document.getElementById('mptbm_calculated_duration');
    if (!warningEl || !hoursEl || !durationEl) return;

    var durationSeconds = parseFloat(durationEl.value) || 0;
    var selectedHours = parseInt(hoursEl.value, 10) || 0;

    if (durationSeconds <= 0) {
        warningEl.style.display = 'none';
        return;
    }

    var requiredHours = Math.max(1, Math.ceil(durationSeconds / 3600));

    if (requiredHours > selectedHours) {
        var totalMinutes = Math.round(durationSeconds / 60);
        var h = Math.floor(totalMinutes / 60);
        var m = totalMinutes % 60;
        var tripTimeText = h > 0 ? (h + 'h' + (m > 0 ? ' ' + m + 'm' : '')) : m + 'm';
        var hourWord = requiredHours === 1 ? 'hour' : 'hours';

        var textEl = warningEl.querySelector('span');
        if (textEl) {
            textEl.textContent = 'Estimated trip time is ~' + tripTimeText + '. Select at least ' + requiredHours + ' ' + hourWord + ' to cover this trip.';
        }
        warningEl.style.display = 'flex';
    } else {
        warningEl.style.display = 'none';
    }
}

jQuery(document).on('mp_change change', '#mptbm_fixed_hours', function () {
    mptbm_update_fixed_hours_warning();
});

// Custom-styled dropdown proxy for Transfer Type / Extra Waiting Hours.
// A real (visually hidden) <select> stays in the DOM so existing behaviour
// (the Return-date/time collapse toggle, price refresh listeners) keeps
// working untouched -- clicking a list item just updates that select and
// fires a native "change" event on it.
(function ($) {
    "use strict";

    $(document).on('click', '.mptbm_select_proxy input.formControl', function (e) {
        e.stopPropagation();
        var $list = $(this).closest('.mptbm_select_proxy').find('.mp_input_select_list');
        var isOpen = $list.is(':visible');
        $('.mptbm_select_proxy .mp_input_select_list').not($list).slideUp(200);
        $list[isOpen ? 'slideUp' : 'slideDown'](200);
    });

    $(document).on('click', '.mptbm_select_proxy .mp_input_select_list li', function (e) {
        e.preventDefault();
        var $li = $(this);
        var $wrapper = $li.closest('.mptbm_select_proxy');

        $wrapper.find('input.formControl').val($li.text());
        $wrapper.find('select.mptbm_proxy_native_select').val($li.data('value')).trigger('change');
        $wrapper.find('.mp_input_select_list').slideUp(200);
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.mptbm_select_proxy').length) {
            $('.mptbm_select_proxy .mp_input_select_list').slideUp(200);
        }
    });
})(jQuery);

function mptbm_calculate_osm_distance() {
    // Multi-row "Add Extra Stop" coordinates (get_details.php's
    // .mptbm_extra_stops_wrapper), read fresh in DOM order so added/edited/
    // removed rows are always reflected. OSRM needs actual coordinates
    // (unlike Google's Directions API, which can geocode address text
    // itself), which is exactly what the paired .mptbm_extra_stop_coords
    // hidden field stores ("lat,lng") once a suggestion is picked.
    var multiStopCoords = [];
    jQuery('.mptbm_extra_stop_coords').each(function () {
        var val = jQuery(this).val();
        if (!val) return;
        var parts = val.split(',');
        if (parts.length === 2) {
            var lat = parseFloat(parts[0]);
            var lng = parseFloat(parts[1]);
            if (!isNaN(lat) && !isNaN(lng)) {
                multiStopCoords.push({ lat: lat, lng: lng });
            }
        }
    });

    // We need at least start marker and either end marker, extra marker, or a multi-stop
    if (!mptbm_osm_start_marker || (!mptbm_osm_end_marker && !mptbm_osm_extra_marker && !multiStopCoords.length)) return;

    var startLatLng = mptbm_osm_start_marker.getLatLng();

    // Use end marker if available; otherwise fall back to the last known
    // stop (a multi-row stop if any exist, else the legacy single extra
    // marker) as a temporary destination so a route still draws.
    var endLatLng;
    if (mptbm_osm_end_marker) {
        endLatLng = mptbm_osm_end_marker.getLatLng();
    } else if (multiStopCoords.length) {
        var lastStop = multiStopCoords.pop();
        endLatLng = L.latLng(lastStop.lat, lastStop.lng);
    } else {
        endLatLng = mptbm_osm_extra_marker.getLatLng();
    }

    // Determine if we should use the legacy single extra marker as a
    // waypoint (only if it isn't itself being used as the destination above)
    var useExtraAsWaypoint = (mptbm_osm_end_marker || multiStopCoords.length) && mptbm_osm_extra_marker;

    var urlCoords = startLatLng.lng + ',' + startLatLng.lat;

    if (useExtraAsWaypoint) {
        var extraLatLng = mptbm_osm_extra_marker.getLatLng();
        urlCoords += ';' + extraLatLng.lng + ',' + extraLatLng.lat;
    }

    multiStopCoords.forEach(function (coord) {
        urlCoords += ';' + coord.lng + ',' + coord.lat;
    });

    urlCoords += ';' + endLatLng.lng + ',' + endLatLng.lat;

    // Get route from OSRM (Open Source Routing Machine)
    var osrmUrl = 'https://router.project-osrm.org/route/v1/driving/' +
        urlCoords +
        '?overview=full&geometries=geojson';

    fetch(osrmUrl)
        .then(response => response.json())
        .then(data => {

            if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                var route = data.routes[0];
                var distanceInMeters = route.distance; // Distance in meters
                var durationInSeconds = route.duration; // Duration in seconds
                var distance = distanceInMeters / 1000; // Convert meters to km
                var duration = durationInSeconds / 3600; // Convert seconds to hours


                // Prepare cookie data
                var kmOrMile = document.getElementById('mptbm_km_or_mile').value;
                var distance_text, display_distance;

                if (kmOrMile === 'mile') {
                    // Convert to miles
                    var distanceInMiles = distance * 0.621371;
                    distance_text = distanceInMiles.toFixed(1) + ' miles';
                    display_distance = ' ' + distanceInMiles.toFixed(1) + ' MILE';
                } else {
                    distance_text = distance.toFixed(1) + ' km';
                    display_distance = ' ' + distance.toFixed(1) + ' KM';
                }

                // Format duration text
                var hours = Math.floor(duration);
                var minutes = Math.round((duration - hours) * 60);
                var duration_text;
                if (hours > 0) {
                    duration_text = hours + ' Hour ' + minutes + ' Min';
                } else {
                    duration_text = minutes + ' Min';
                }

                // Set cookies for price calculation (same format as Google Maps)
                var now = new Date();
                now.setTime(now.getTime() + (24 * 60 * 60 * 1000)); // 24 hours
                var cookieOptions = "; expires=" + now.toUTCString() + "; path=/; SameSite=Lax";

                document.cookie = "mptbm_distance=" + encodeURIComponent(distanceInMeters) + cookieOptions;
                document.cookie = "mptbm_distance_text=" + encodeURIComponent(distance_text) + cookieOptions;
                document.cookie = "mptbm_duration=" + encodeURIComponent(durationInSeconds) + cookieOptions;
                document.cookie = "mptbm_duration_text=" + encodeURIComponent(duration_text) + cookieOptions;

                // Update explicit hidden fields
                var explicitDistance = document.getElementById('mptbm_calculated_distance');
                if (explicitDistance) {
                    explicitDistance.value = distanceInMeters;
                }
                var explicitDuration = document.getElementById('mptbm_calculated_duration');
                if (explicitDuration) {
                    explicitDuration.value = durationInSeconds;
                }


                // Update distance display
                var distanceElement = document.querySelector('.mptbm_total_distance');
                if (distanceElement) {
                    distanceElement.textContent = display_distance;
                }

                // Update time display
                var timeElement = document.querySelector('.mptbm_total_time');
                if (timeElement) {
                    timeElement.textContent = duration_text;
                }

                // Show distance/time section
                jQuery(".mptbm_distance_time").slideDown("fast");
                mptbm_update_fixed_hours_warning();

                // Draw route on map
                if (mptbm_osm_route) {
                    mptbm_osm_map.removeLayer(mptbm_osm_route);
                }

                // Convert GeoJSON coordinates to Leaflet format [lat, lng]
                var coordinates = route.geometry.coordinates.map(function (coord) {
                    return [coord[1], coord[0]]; // GeoJSON uses [lng, lat], Leaflet uses [lat, lng]
                });

                mptbm_osm_route = L.polyline(coordinates, {
                    color: '#ff4757',
                    weight: 4,
                    opacity: 0.8
                }).addTo(mptbm_osm_map);

                // Fit map to show the entire route
                mptbm_osm_map.fitBounds(mptbm_osm_route.getBounds().pad(0.1));

            } else {
                console.error('[OSM Route] No route found');
                // Fallback to straight line
                drawStraightLine(startLatLng, endLatLng);
            }
        })
        .catch(error => {
            console.error('[OSM Route] Error fetching route:', error);
            // Fallback to straight line
            drawStraightLine(startLatLng, endLatLng);
        });

    // Fallback function to draw straight line
    function drawStraightLine(start, end) {
        var distance = mptbm_osm_map.distance(start, end) / 1000;

        var distanceElement = document.querySelector('.mptbm_total_distance');
        if (distanceElement) {
            var kmOrMile = document.getElementById('mptbm_km_or_mile').value;
            if (kmOrMile === 'mile') {
                distance = distance * 0.621371;
                distanceElement.textContent = ' ' + distance.toFixed(1) + ' MILE';
            } else {
                distanceElement.textContent = ' ' + distance.toFixed(1) + ' KM';
            }
        }

        if (mptbm_osm_route) {
            mptbm_osm_map.removeLayer(mptbm_osm_route);
        }

        mptbm_osm_route = L.polyline([start, end], {
            color: '#ff4757',
            weight: 4,
            opacity: 0.8,
            dashArray: '10, 10' // Dashed to show it's straight line
        }).addTo(mptbm_osm_map);
    }
}

function mptbm_calculate_google_route_from_markers() {
    if (!mptbm_start_marker || !mptbm_end_marker || !mptbm_map) return;

    var directionsService = new google.maps.DirectionsService();
    var directionsRenderer = new google.maps.DirectionsRenderer();
    directionsRenderer.setMap(mptbm_map);

    var request = {
        origin: mptbm_start_marker.getPosition(),
        destination: mptbm_end_marker.getPosition(),
        travelMode: google.maps.TravelMode.DRIVING,
        unitSystem: google.maps.UnitSystem.METRIC,
    };

    directionsService.route(request, function (result, status) {
        if (status === google.maps.DirectionsStatus.OK) {
            try {
                var distance = result.routes[0].legs[0].distance.value;
                var kmOrMileElement = document.getElementById("mptbm_km_or_mile");
                var kmOrMile = kmOrMileElement ? kmOrMileElement.value : 'km';
                var distance_text = result.routes[0].legs[0].distance.text;
                var duration = result.routes[0].legs[0].duration.value;
                var duration_text = result.routes[0].legs[0].duration.text;

                if (kmOrMile == 'mile') {
                    var distanceInKilometers = distance / 1000;
                    var distanceInMiles = distanceInKilometers * 0.621371;
                    distance_text = distanceInMiles.toFixed(1) + ' miles';
                }

                var now = new Date();
                var time = now.getTime();
                var expireTime = time + 3600 * 1000 * 12;
                now.setTime(expireTime);
                var cookieOptions = "; expires=" + now.toUTCString() + "; path=/; SameSite=Lax";
                document.cookie = "mptbm_distance=" + encodeURIComponent(distance) + cookieOptions;
                document.cookie = "mptbm_distance_text=" + encodeURIComponent(distance_text) + cookieOptions;
                document.cookie = "mptbm_duration=" + encodeURIComponent(duration) + cookieOptions;
                document.cookie = "mptbm_duration_text=" + encodeURIComponent(duration_text) + cookieOptions;

                var mapArea = jQuery('#mptbm_map_area').closest('.mptbm_transport_search_area');
                if (mapArea.length > 0) {
                    if (mapArea.find('input[name="mptbm_hidden_distance"]').length === 0) {
                        mapArea.append('<input type="hidden" name="mptbm_hidden_distance" value="" />');
                    }
                    if (mapArea.find('input[name="mptbm_hidden_duration"]').length === 0) {
                        mapArea.append('<input type="hidden" name="mptbm_hidden_duration" value="" />');
                    }

                    var explicitDistance = document.getElementById('mptbm_calculated_distance');
                    if (explicitDistance) {
                        explicitDistance.value = distance;
                    }
                    var explicitDuration = document.getElementById('mptbm_calculated_duration');
                    if (explicitDuration) {
                        explicitDuration.value = duration;
                    }

                    if (mapArea.find('input[name="mptbm_hidden_distance_text"]').length === 0) {
                        mapArea.append('<input type="hidden" name="mptbm_hidden_distance_text" value="" />');
                    }
                    if (mapArea.find('input[name="mptbm_hidden_duration_text"]').length === 0) {
                        mapArea.append('<input type="hidden" name="mptbm_hidden_duration_text" value="" />');
                    }

                    mapArea.find('input[name="mptbm_hidden_distance"]').val(distance);
                    mapArea.find('input[name="mptbm_hidden_duration"]').val(duration);
                    mapArea.find('input[name="mptbm_hidden_distance_text"]').val(distance_text);
                    mapArea.find('input[name="mptbm_hidden_duration_text"]').val(duration_text);
                }

                directionsRenderer.setDirections(result);

                jQuery(".mptbm_total_distance").html(distance_text);
                jQuery(".mptbm_total_time").html(duration_text);
                jQuery(".mptbm_distance_time").slideDown("fast");
                mptbm_update_fixed_hours_warning();

                // Fit map to show the entire route
                var bounds = new google.maps.LatLngBounds();
                result.routes[0].legs.forEach(function (leg) {
                    bounds.extend(leg.start_location);
                    bounds.extend(leg.end_location);
                });
                mptbm_map.fitBounds(bounds);
            } catch (error) {
                console.error('[Google Maps Route] Error:', error);
            }
        } else {
            console.error('[Google Maps Route] Status:', status);
        }
    });
}

function mptbm_init_google_map() {

    // Check if Google Maps API is loaded
    if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        console.warn("[Google Map] Google Maps API not loaded. Skipping map initialization.");
        return false;
    }


    mptbm_set_cookie_distance_duration();

    // Initialize Google Places autocomplete for pickup location
    if (jQuery("#mptbm_map_start_place").length > 0) {
        var start_place = document.getElementById("mptbm_map_start_place");
        var start_place_autoload = new google.maps.places.Autocomplete(start_place);
        var mptbm_restrict_search_to_country = jQuery('[name="mptbm_restrict_search_country"]').val();
        var mptbm_country = jQuery('[name="mptbm_country"]').val();

        if (mptbm_restrict_search_to_country == 'yes') {
            start_place_autoload.setComponentRestrictions({
                country: [mptbm_country]
            });
        }

        google.maps.event.addListener(start_place_autoload, "place_changed", function () {
            var end_place = document.getElementById("mptbm_map_end_place");
            var price_based = jQuery('[name="mptbm_price_based"]').val();

            // Disable auto-syncing dropoff with pickup unless dropoff is hidden
            if (end_place && end_place.type === 'hidden') {
                end_place.value = start_place.value;
            }

            // For fixed_zone_dropoff, set start marker when pickup is searched
            if (price_based === 'fixed_zone_dropoff' && start_place_autoload.getPlace()) {
                var place = start_place_autoload.getPlace();
                if (place.geometry && place.geometry.location) {
                    if (typeof mptbm_start_marker !== 'undefined' && mptbm_start_marker) {
                        mptbm_start_marker.setMap(null);
                    }
                    mptbm_start_marker = new google.maps.Marker({
                        position: place.geometry.location,
                        map: mptbm_map,
                        title: place.name || place.formatted_address
                    });
                    mptbm_map.setCenter(place.geometry.location);
                    mptbm_map.setZoom(14);

                    // Calculate route if end marker exists
                    if (typeof mptbm_end_marker !== 'undefined' && mptbm_end_marker) {
                        mptbm_calculate_google_route_from_markers();
                    }
                }
            }

            // Focus on Extra Stop if it exists, otherwise Dropoff
            var extra_stop = document.getElementById("mptbm_map_extra_stop_place");
            if (extra_stop && extra_stop.offsetParent !== null) { // Check if visible
                extra_stop.focus();
            } else if (end_place && end_place.offsetParent !== null) {
                end_place.focus();
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
    setTimeout(function () {
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
        var end_place = document.getElementById("mptbm_map_end_place");
        var end_place_autoload = new google.maps.places.Autocomplete(end_place);
        var mptbm_restrict_search_to_country = jQuery('[name="mptbm_restrict_search_country"]').val();
        var mptbm_country = jQuery('[name="mptbm_country"]').val();

        if (mptbm_restrict_search_to_country == 'yes') {
            end_place_autoload.setComponentRestrictions({
                country: [mptbm_country]
            });
        }

        google.maps.event.addListener(end_place_autoload, "place_changed", function () {
            var start_place = document.getElementById("mptbm_map_start_place");
            var price_based = jQuery('[name="mptbm_price_based"]').val();

            // For fixed_zone, set end marker when dropoff is searched
            if (price_based === 'fixed_zone' && end_place_autoload.getPlace()) {
                var place = end_place_autoload.getPlace();
                if (place.geometry && place.geometry.location) {
                    if (typeof mptbm_end_marker !== 'undefined' && mptbm_end_marker) {
                        mptbm_end_marker.setMap(null);
                    }
                    mptbm_end_marker = new google.maps.Marker({
                        position: place.geometry.location,
                        map: mptbm_map,
                        title: place.name || place.formatted_address
                    });
                    mptbm_map.setCenter(place.geometry.location);
                    mptbm_map.setZoom(14);

                    // Calculate route if start marker exists
                    if (typeof mptbm_start_marker !== 'undefined' && mptbm_start_marker) {
                        mptbm_calculate_google_route_from_markers();
                    }
                }
            }

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
        window.initializeGooglePlacesAutocomplete = function (retryCount = 0) {
            // Check if OpenStreetMap is being used instead
            var mapType = document.getElementById('mptbm_map_type');


            if (mapType && mapType.value === 'openstreetmap') {
                return;
            }


            // Maximum retry attempts to prevent infinite loops
            const MAX_RETRIES = 10;
            const INITIAL_DELAY = 100; // Start with 100ms instead of 500ms

            // Check if Google Maps API is loaded
            if (typeof google === 'undefined' || typeof google.maps === 'undefined' || typeof google.maps.places === 'undefined') {
                if (retryCount >= MAX_RETRIES) {
                    console.warn('Google Maps API failed to load after', MAX_RETRIES, 'attempts. Please check your API key and connection.');
                    return;
                }

                // Exponential backoff: 100ms, 200ms, 400ms, 800ms, etc.
                const delay = INITIAL_DELAY * Math.pow(2, retryCount);

                setTimeout(function () {
                    initializeGooglePlacesAutocomplete(retryCount + 1);
                }, delay);
                return;
            }

            var startPlaceInput = document.getElementById('mptbm_map_start_place');
            if (startPlaceInput && !startPlaceInput.hasAttribute('data-autocomplete-initialized')) {
                var startPlaceAutocomplete = new google.maps.places.Autocomplete(startPlaceInput);
                var mptbm_restrict_search_to_country = $('[name="mptbm_restrict_search_country"]').val();
                var mptbm_country = $('[name="mptbm_country"]').val();

                if (mptbm_restrict_search_to_country == 'yes') {
                    startPlaceAutocomplete.setComponentRestrictions({
                        country: [mptbm_country]
                    });
                }

                google.maps.event.addListener(startPlaceAutocomplete, "place_changed", function () {
                    var endPlaceInput = document.getElementById('mptbm_map_end_place');
                    var price_based = $('[name="mptbm_price_based"]').val();
                    var end_val = endPlaceInput ? endPlaceInput.value : (startPlaceInput ? startPlaceInput.value : '');

                    if (price_based === 'fixed_zone_dropoff' && window.mptbm_fixed_zone_end_coords) {
                        end_val = window.mptbm_fixed_zone_end_coords.latitude + "," + window.mptbm_fixed_zone_end_coords.longitude;
                    }

                    // Only sync dropoff with pickup if dropoff is hidden (hourly pricing with disabled dropoff)
                    if (endPlaceInput && endPlaceInput.type === 'hidden') {
                        endPlaceInput.value = startPlaceInput.value;
                        end_val = startPlaceInput.value;
                    }



                    mptbm_set_cookie_distance_duration(
                        startPlaceInput.value,
                        end_val
                    );
                });

                // Mark as initialized to prevent duplicate initialization
                startPlaceInput.setAttribute('data-autocomplete-initialized', 'true');
            }

            // Initialize Google Places autocomplete for dropoff location (only if it exists and is visible)
            var endPlaceInput = document.getElementById('mptbm_map_end_place');




            if (endPlaceInput && !endPlaceInput.hasAttribute('data-autocomplete-initialized') && endPlaceInput.type !== 'hidden') {
                var endPlaceAutocomplete = new google.maps.places.Autocomplete(endPlaceInput);
                var mptbm_restrict_search_to_country = $('[name="mptbm_restrict_search_country"]').val();
                var mptbm_country = $('[name="mptbm_country"]').val();

                if (mptbm_restrict_search_to_country == 'yes') {
                    endPlaceAutocomplete.setComponentRestrictions({
                        country: [mptbm_country]
                    });
                }

                google.maps.event.addListener(endPlaceAutocomplete, 'place_changed', function () {
                    var startInput = document.getElementById('mptbm_map_start_place');
                    var price_based = $('[name="mptbm_price_based"]').val();
                    var start_val = startInput ? startInput.value : '';

                    if (price_based === 'fixed_zone' && window.mptbm_fixed_zone_start_coords) {
                        start_val = window.mptbm_fixed_zone_start_coords.latitude + "," + window.mptbm_fixed_zone_start_coords.longitude;
                    }



                    mptbm_set_cookie_distance_duration(
                        start_val,
                        endPlaceInput ? endPlaceInput.value : ''
                    );
                });

                endPlaceInput.setAttribute('data-autocomplete-initialized', 'true');
            }

            // Initialize Google Places autocomplete for EXTRA STOP location
            var extraStopInput = document.getElementById('mptbm_map_extra_stop_place');

            if (extraStopInput && !extraStopInput.hasAttribute('data-autocomplete-initialized')) {
                var extraStopAutocomplete = new google.maps.places.Autocomplete(extraStopInput);
                var mptbm_restrict_search_to_country = $('[name="mptbm_restrict_search_country"]').val();
                var mptbm_country = $('[name="mptbm_country"]').val();

                if (mptbm_restrict_search_to_country == 'yes') {
                    extraStopAutocomplete.setComponentRestrictions({
                        country: [mptbm_country]
                    });
                }

                google.maps.event.addListener(extraStopAutocomplete, 'place_changed', function () {
                    var place = extraStopAutocomplete.getPlace();

                    // Create marker for extra stop
                    if (place.geometry && place.geometry.location) {
                        // Remove existing extra marker if present
                        if (typeof mptbm_extra_marker !== 'undefined' && mptbm_extra_marker) {
                            mptbm_extra_marker.setMap(null);
                        }

                        // Create new marker for extra stop
                        mptbm_extra_marker = new google.maps.Marker({
                            position: place.geometry.location,
                            map: mptbm_map,
                            title: place.name || place.formatted_address,
                            label: {
                                text: 'E',
                                color: 'white',
                                fontWeight: 'bold'
                            }
                        });

                        mptbm_map.setCenter(place.geometry.location);
                        mptbm_map.setZoom(14);
                    }

                    // Update the map route when extra stop changes
                    var startInput = document.getElementById('mptbm_map_start_place');
                    var endInput = document.getElementById('mptbm_map_end_place');

                    mptbm_set_cookie_distance_duration(
                        startInput ? startInput.value : '',
                        endInput ? endInput.value : ''
                    );
                });

                extraStopInput.setAttribute('data-autocomplete-initialized', 'true');
            }
        };

        // Maximum retry attempts to prevent infinite loops

        // Initialize Google Places autocomplete on page load with a delay to ensure API is loaded
        setTimeout(function () {
            var mapType = document.getElementById('mptbm_map_type');

            if (mapType && mapType.value === 'openstreetmap') {
                mptbm_init_osm_address_search();
            } else {
                initializeGooglePlacesAutocomplete();
            }
        }, 100); // Reduced from 500ms to 100ms for faster initialization

        // Handle Previous/Next button positioning after tab changes
        $(document).on('click', '.nextTab_prev, .nextTab_next', function () {
            setTimeout(function () {
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
            $('.mptb-tabs li').each(function () {
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
            $('.mptb-tab-content').each(function () {
                var tabId = $(this).attr('id');
                var isCurrent = $(this).hasClass('current');
                var isVisible = $(this).is(':visible');

                // Ensure current tab is visible
                if (isCurrent && !isVisible) {
                    $(this).css('display', 'block');
                }
            });
        }

        // Function to ensure loading spinner element exists
        window.ensureLoadingGifExists = function () {
            var loadingGif = $('.mptbm-hide-gif');
            var tabContainer = $('.mptb-tab-container');

            if (loadingGif.length === 0 && tabContainer.length > 0) {
                var loadingSpinnerHtml = '<div class="mptbm-hide-gif mptbm-gif" style="display: none;"><div class="mptbm-spinner"></div></div>';
                tabContainer.append(loadingSpinnerHtml);
                return true;
            } else if (loadingGif.length === 0 && tabContainer.length === 0) {
                return false;
            }
            return true;
        };

        // Try to create loading GIF element immediately
        window.ensureLoadingGifExists();

        // Also try after a short delay to ensure DOM is fully ready
        setTimeout(function () {
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

        let target = parent.find(".mptbm_inline_search_results");
        let target_date = parent.find("#mptbm_map_start_date");
        let return_target_date = parent.find("#mptbm_map_return_date");
        let target_time = parent.find("#mptbm_map_start_time");
        let return_target_time = parent.find("#mptbm_map_return_time");
        let start_place;
        let end_place;
        let price_based = parent.find('[name="mptbm_price_based"]').val();
        let two_way_field = parent.find('[name="mptbm_taxi_return"]');
        let two_way = two_way_field.length ? two_way_field.val() : '1';
        let waiting_time = parent.find('[name="mptbm_waiting_time"]').val();
        let fixed_time = parent.find('[name="mptbm_fixed_hours"]').val();
        let mptbm_original_price_base = parent.find('[name="mptbm_original_price_base"]').val();


        let mptbm_enable_view_search_result_page = parent
            .find('[name="mptbm_enable_view_search_result_page"]')
            .val();
        if (price_based === "manual") {
            start_place = document.getElementById("mptbm_manual_start_place");
            end_place = document.getElementById("mptbm_manual_end_place");
        } else if (price_based === "fixed_zone") {
            start_place = document.getElementById("mptbm_manual_start_place");
            end_place = document.getElementById("mptbm_map_end_place");
        } else if (price_based === "fixed_zone_dropoff") {
            start_place = document.getElementById("mptbm_map_start_place");
            end_place = document.getElementById("mptbm_manual_end_place");
        } else {
            start_place = document.getElementById("mptbm_map_start_place");
            end_place = document.getElementById("mptbm_map_end_place");
        }
        let start_date = target_date.val();
        let return_date;
        let return_time;
        let has_return_fields = return_target_date.length > 0 && return_target_time.length > 0;

        if (mptbm_enable_return_in_different_date == 'yes' && two_way != 1 && price_based != 'fixed_hourly' && has_return_fields) {
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



        // Helper function to safely get value from input or select
        function getElementValue(element) {
            if (!element) return '';
            if (element.tagName === 'SELECT') {
                return element.value || '';
            }
            return element.value || '';
        }

        let start_place_value = getElementValue(start_place);
        let end_place_value = getElementValue(end_place);

        if (!start_date) {
            target_date.trigger("click");
        } else if (start_time === undefined || start_time === null || start_time === '') {
            parent
                .find("#mptbm_map_start_time")
                .closest(".mp_input_select")
                .find("input.formControl")
                .trigger("click");
        } else if (!return_date) {
            if (mptbm_enable_return_in_different_date == 'yes' && two_way != 1 && has_return_fields) {
                return_target_date.trigger("click");
            }
        } else if (return_time === undefined || return_time === null || return_time === '') {
            if (mptbm_enable_return_in_different_date == 'yes' && two_way != 1 && has_return_fields) {
                parent
                    .find("#mptbm_map_return_time")
                    .closest(".mp_input_select")
                    .find("input.formControl")
                    .trigger("click");
            }
        } else if (!start_place_value || (start_place && start_place.tagName === 'SELECT' && start_place.options[start_place.selectedIndex] && start_place.options[start_place.selectedIndex].disabled)) {
            if (start_place) start_place.focus();
            // Show error message
            let startMsg = price_based === 'manual' || price_based === 'fixed_zone' ? 'Please select a pickup location' : 'Please enter a pickup location';
            if (start_place) showLocationError(start_place, startMsg);
        } else if (!end_place_value || (end_place && end_place.tagName === 'SELECT' && end_place.options[end_place.selectedIndex] && end_place.options[end_place.selectedIndex].disabled)) {
            // Check if dropoff is required (not hidden for hourly)
            let hideDropoff = parent.find('[name="mptbm_original_price_base"]').val() === 'fixed_hourly' &&
                document.getElementById('mptbm_map_end_place').type === 'hidden';
            if (!hideDropoff) {
                end_place.focus();
                // Show error message
                let endMsg = price_based === 'manual' ? 'Please select a dropoff location' : 'Please enter a dropoff location';
                showLocationError(end_place, endMsg);
            }
        } else {
            // Remove any existing error messages
            removeLocationErrors();

            mptbm_search_loading(parent, true);
            mptbm_content_refresh(parent);
            if (price_based !== "manual") {
                let calc_start = start_place_value;
                let calc_end = end_place_value;

                if (price_based === 'fixed_zone' && window.mptbm_fixed_zone_start_coords) {
                    calc_start = window.mptbm_fixed_zone_start_coords.latitude + "," + window.mptbm_fixed_zone_start_coords.longitude;
                } else if (price_based === 'fixed_zone_dropoff' && window.mptbm_fixed_zone_end_coords) {
                    calc_end = window.mptbm_fixed_zone_end_coords.latitude + "," + window.mptbm_fixed_zone_end_coords.longitude;
                }

                mptbm_set_cookie_distance_duration(calc_start, calc_end);
            }
            //let price_based = parent.find('[name="mptbm_price_based"]').val();
            function getGeometryLocation(address, callback) {
                // Check if using OpenStreetMap
                var mapType = document.getElementById('mptbm_map_type');
                if (mapType && mapType.value === 'openstreetmap') {
                    // Use OpenStreetMap geocoding via our proxy
                    var ajaxUrl = mptbm_ajax.ajax_url + '?action=mptbm_osm_search&nonce=' + mptbm_ajax.osm_nonce + '&q=' + encodeURIComponent(address);

                    fetch(ajaxUrl)
                        .then(response => response.json())
                        .then(response => {
                            if (response.success && response.data && response.data.length > 0) {
                                var result = response.data[0];
                                var coordinatesOfPlace = {
                                    "latitude": parseFloat(result.lat),
                                    "longitude": parseFloat(result.lon)
                                };
                                callback(coordinatesOfPlace);
                            } else {
                                console.error("OSM geocoding failed for:", address);
                                callback(null);
                            }
                        })
                        .catch(error => {
                            console.error("Error in OSM geocoding:", error);
                            callback(null);
                        });
                } else {
                    // Use Google Maps geocoding
                    var geocoder = new google.maps.Geocoder();
                    var coordinatesOfPlace = {};

                    geocoder.geocode({ address: address }, function (results, status) {

                        if (status === "OK") {
                            try {
                                var latitude = results[0].geometry.location.lat();
                                var longitude = results[0].geometry.location.lng();
                                coordinatesOfPlace["latitude"] = latitude;
                                coordinatesOfPlace["longitude"] = longitude;
                                // Call the callback function with the coordinates
                                callback(coordinatesOfPlace);
                            } catch (error) {
                                console.error("Error processing geocoding results:", error);
                                callback(null);
                            }
                        } else {
                            console.error(
                                "Geocode was not successful for the following reason: " + status
                            );
                            // Call the callback function with null to indicate failure
                            callback(null);
                        }
                    });
                }
            }
            // Define a function to get the coordinates asynchronously and return a Deferred object

            function getCoordinatesAsync(address) {
                var deferred = $.Deferred();
                getGeometryLocation(address, function (coordinates) {
                    deferred.resolve(coordinates);
                });
                return deferred.promise();
            }

            // In OSM mode, picking a suggestion from the pickup/drop-off
            // autocomplete already geocoded this exact text once
            // (mptbm_handle_osm_address_selection stores the result +
            // the matched text). Re-geocoding the same address again here
            // was pure waste -- an extra round trip through the slower
            // server-side lookup, on every single Search click, for
            // something already known. Reuse it when the field's current
            // value still matches exactly what was picked; otherwise the
            // visitor edited the text since then, so fall back to a fresh
            // lookup rather than trust stale coordinates.
            function getCachedOrFreshCoordinates(address, cachedCoords, cachedAddress) {
                if (cachedCoords && cachedAddress === address) {
                    return $.Deferred().resolve(cachedCoords).promise();
                }
                return getCoordinatesAsync(address);
            }
            if (price_based !== 'manual') {

                // For fixed_zone, pickup is from dropdown (term_XX), so we use pre-stored coords
                // Only geocode the end_place (dropoff search input)
                // For fixed_zone_dropoff, pickup is from map search, dropoff is from dropdown
                if (price_based === 'fixed_zone' || price_based === 'fixed_zone_dropoff') {
                    let searchInput, dropdownCoords, startCoordinates, endCoordinates;

                    if (price_based === 'fixed_zone') {
                        // fixed_zone: pickup = dropdown, dropoff = map search
                        searchInput = end_place;
                        dropdownCoords = window.mptbm_fixed_zone_start_coords || null;

                        let searchInputValue = getElementValue(searchInput);
                        getCachedOrFreshCoordinates(searchInputValue, window.mptbm_fixed_zone_end_coords, window.mptbm_osm_end_coords_address).done(function (searchCoordinates) {
                            if (!searchCoordinates || searchCoordinates === null) {
                                mptbm_search_loading(parent, false);
                                showLocationError(end_place, 'Invalid dropoff location. Please select a valid address.');
                                end_place.focus();
                                return;
                            }

                            startCoordinates = dropdownCoords;
                            endCoordinates = searchCoordinates;

                            submitFixedZoneSearch();
                        });
                    } else {
                        // fixed_zone_dropoff: pickup = map search, dropoff = dropdown
                        searchInput = start_place;
                        dropdownCoords = window.mptbm_fixed_zone_end_coords || null;

                        if (!dropdownCoords) {
                            mptbm_search_loading(parent, false);
                            showLocationError(end_place, 'Please select a dropoff location from the dropdown.');
                            parent.find("#mptbm_manual_end_place").focus();
                            return;
                        }

                        let searchInputValue = getElementValue(searchInput);
                        getCachedOrFreshCoordinates(searchInputValue, window.mptbm_fixed_zone_start_coords, window.mptbm_osm_start_coords_address).done(function (searchCoordinates) {
                            if (!searchCoordinates || searchCoordinates === null) {
                                mptbm_search_loading(parent, false);
                                showLocationError(start_place, 'Invalid pickup location. Please select a valid address.');
                                start_place.focus();
                                return;
                            }

                            startCoordinates = searchCoordinates;
                            endCoordinates = dropdownCoords;

                            submitFixedZoneSearch();
                        });
                    }

                    function submitFixedZoneSearch() {
                        let start_val = getElementValue(start_place);
                        let end_val = getElementValue(end_place);
                        if (start_val && end_val && start_date &&
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
                                        nonce: mptbm_ajax.search_nonce,
                                        start_place: start_val,
                                        start_place_coordinates: JSON.stringify(startCoordinates),
                                        end_place_coordinates: JSON.stringify(endCoordinates),
                                        end_place: end_val,
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
                                        mptbm_max_hand_luggage: parent.find('#mptbm_max_hand_luggage').val(),
                                        mptbm_extra_stop_place: mptbm_collect_extra_stop_places(parent),
                                        mptbm_extra_stop_place_coordinates: mptbm_collect_extra_stop_coordinates(parent),
                                        mptbm_original_price_base: mptbm_original_price_base,
                                        mptbm_distance: parent.find('#mptbm_calculated_distance').val() || parent.find('input[name="mptbm_hidden_distance"]').val(),
                                        mptbm_duration: parent.find('#mptbm_calculated_duration').val() || parent.find('input[name="mptbm_hidden_duration"]').val(),
                                    },
                                    success: function (data) {
                                        if (data.success === false) {
                                            alert(data.data.message || 'An error occurred. Please try again.');
                                            mptbm_search_loading(parent, false);
                                            return;
                                        }
                                        target.append(data).promise().done(function () {
                                            mptbm_search_loading(parent, false);
                                            mptbm_reveal_inline_results(target);
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
                                        nonce: mptbm_ajax.search_nonce,
                                        start_place: start_val,
                                        start_place_coordinates: JSON.stringify(startCoordinates),
                                        end_place_coordinates: JSON.stringify(endCoordinates),
                                        end_place: end_val,
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
                                        mptbm_max_hand_luggage: parent.find('#mptbm_max_hand_luggage').val(),
                                        mptbm_extra_stop_place: mptbm_collect_extra_stop_places(parent),
                                        mptbm_extra_stop_place_coordinates: mptbm_collect_extra_stop_coordinates(parent),
                                        mptbm_original_price_base: mptbm_original_price_base,
                                        mptbm_distance: parent.find('#mptbm_calculated_distance').val() || parent.find('input[name="mptbm_hidden_distance"]').val(),
                                        mptbm_duration: parent.find('#mptbm_calculated_duration').val() || parent.find('input[name="mptbm_hidden_duration"]').val(),
                                    },
                                    success: function (data) {
                                        if (data.success === false) {
                                            alert(data.data.message || 'An error occurred. Please try again.');
                                            mptbm_search_loading(parent, false);
                                            return;
                                        }

                                        var redirectUrl = mptbm_resolve_redirect_url(data);
                                        if (!redirectUrl) {
                                            mptbm_search_loading(parent, false);
                                            alert('Unable to open the search results page. Please try again.');
                                            return;
                                        }

                                        window.location.href = redirectUrl;
                                    },
                                    error: function (response) {
                                        console.log(response);
                                    },
                                });
                            }
                        }
                    }

                    return; // Exit early for fixed_zone
                }

                $.when(
                    getCachedOrFreshCoordinates(start_place.value, window.mptbm_fixed_zone_start_coords, window.mptbm_osm_start_coords_address),
                    getCachedOrFreshCoordinates(end_place.value, window.mptbm_fixed_zone_end_coords, window.mptbm_osm_end_coords_address)
                ).done(function (startCoordinates, endCoordinates) {
                    // Validate that geocoding was successful
                    if (!startCoordinates || startCoordinates === null) {
                        mptbm_search_loading(parent, false);
                        showLocationError(start_place, 'Invalid pickup location. Please select a valid address.');
                        start_place.focus();
                        return;
                    }

                    if (!endCoordinates || endCoordinates === null) {
                        mptbm_search_loading(parent, false);
                        showLocationError(end_place, 'Invalid dropoff location. Please select a valid address.');
                        end_place.focus();
                        return;
                    }

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
                                    nonce: mptbm_ajax.search_nonce,
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
                                    mptbm_max_hand_luggage: parent.find('#mptbm_max_hand_luggage').val(),
                                    mptbm_extra_stop_place: mptbm_collect_extra_stop_places(parent),
                                    mptbm_extra_stop_place_coordinates: mptbm_collect_extra_stop_coordinates(parent),
                                    mptbm_original_price_base: mptbm_original_price_base,
                                    mptbm_distance: parent.find('#mptbm_calculated_distance').val() || parent.find('input[name="mptbm_hidden_distance"]').val(),
                                    mptbm_duration: parent.find('#mptbm_calculated_duration').val() || parent.find('input[name="mptbm_hidden_duration"]').val(),
                                },
                                beforeSend: function () {
                                    //mptbm_search_loading(parent, true);
                                },
                                success: function (data) {
                                    // Check if the response is an error
                                    if (data.success === false) {
                                        alert(data.data.message || 'An error occurred. Please try again.');
                                        mptbm_search_loading(parent, false);
                                        return;
                                    }

                                    target
                                        .append(data)
                                        .promise()
                                        .done(function () {
                                            mptbm_search_loading(parent, false);
                                            mptbm_reveal_inline_results(target);
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
                                    nonce: mptbm_ajax.search_nonce,
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
                                    mptbm_max_hand_luggage: parent.find('#mptbm_max_hand_luggage').val(),
                                    mptbm_extra_stop_place: mptbm_collect_extra_stop_places(parent),
                                    mptbm_extra_stop_place_coordinates: mptbm_collect_extra_stop_coordinates(parent),
                                    mptbm_original_price_base: mptbm_original_price_base,
                                    mptbm_distance: parent.find('#mptbm_calculated_distance').val() || parent.find('input[name="mptbm_hidden_distance"]').val(),
                                    mptbm_duration: parent.find('#mptbm_calculated_duration').val() || parent.find('input[name="mptbm_hidden_duration"]').val(),
                                },
                                beforeSend: function () {
                                    mptbm_search_loading(parent, true);
                                },
                                success: function (data) {
                                    // Check if the response is an error
                                    if (data.success === false) {
                                        alert(data.data.message || 'An error occurred. Please try again.');
                                        mptbm_search_loading(parent, false);
                                        return;
                                    }

                                    var redirectUrl = mptbm_resolve_redirect_url(data);
                                    if (!redirectUrl) {
                                        mptbm_search_loading(parent, false);
                                        alert('Unable to open the search results page. Please try again.');
                                        return;
                                    }

                                    window.location.href = redirectUrl;
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
                                nonce: mptbm_ajax.search_nonce,
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
                                mptbm_max_hand_luggage: parent.find('#mptbm_max_hand_luggage').val(),
                                mptbm_extra_stop_place: mptbm_collect_extra_stop_places(parent),
                                mptbm_extra_stop_place_coordinates: mptbm_collect_extra_stop_coordinates(parent),
                                mptbm_original_price_base: mptbm_original_price_base,
                                mptbm_distance: parent.find('#mptbm_calculated_distance').val() || parent.find('input[name="mptbm_hidden_distance"]').val(),
                                mptbm_duration: parent.find('#mptbm_calculated_duration').val() || parent.find('input[name="mptbm_hidden_duration"]').val(),
                                mptbm_distance_text: parent.find('input[name="mptbm_hidden_distance_text"]').val(),
                                mptbm_duration_text: parent.find('input[name="mptbm_hidden_duration_text"]').val(),
                            },
                            beforeSend: function () {
                                //mptbm_search_loading(parent, true);
                            },
                            success: function (data) {
                                // Check if the response is an error
                                if (data.success === false) {
                                    alert(data.data.message || 'An error occurred. Please try again.');
                                    mptbm_search_loading(parent, false);
                                    return;
                                }

                                target
                                    .append(data)
                                    .promise()
                                    .done(function () {
                                        mptbm_search_loading(parent, false);
                                        mptbm_reveal_inline_results(target);
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
                                nonce: mptbm_ajax.search_nonce,
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
                                mptbm_max_hand_luggage: parent.find('#mptbm_max_hand_luggage').val(),
                                mptbm_extra_stop_place: mptbm_collect_extra_stop_places(parent),
                                mptbm_extra_stop_place_coordinates: mptbm_collect_extra_stop_coordinates(parent),
                                mptbm_original_price_base: mptbm_original_price_base,
                                mptbm_distance: parent.find('#mptbm_calculated_distance').val() || parent.find('input[name="mptbm_hidden_distance"]').val(),
                                mptbm_duration: parent.find('#mptbm_calculated_duration').val() || parent.find('input[name="mptbm_hidden_duration"]').val(),
                                mptbm_distance_text: parent.find('input[name="mptbm_hidden_distance_text"]').val(),
                                mptbm_duration_text: parent.find('input[name="mptbm_hidden_duration_text"]').val(),
                            },
                            beforeSend: function () {
                                mptbm_search_loading(parent, true);
                            },
                            success: function (data) {
                                // Check if the response is an error
                                if (data.success === false) {
                                    alert(data.data.message || 'An error occurred. Please try again.');
                                    mptbm_search_loading(parent, false);
                                    return;
                                }

                                var redirectUrl = mptbm_resolve_redirect_url(data);
                                if (!redirectUrl) {
                                    mptbm_search_loading(parent, false);
                                    alert('Unable to open the search results page. Please try again.');
                                    return;
                                }

                                window.location.href = redirectUrl;
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
    $(document).on("change", "#mptbm_map_start_date", function (e, meta) {
        // Clear the time slots list
        $('#mptbm_map_start_time').siblings('.start_time_list').empty();
        $('.start_time_input,#mptbm_map_start_time').val('');
        let mptbm_enable_return_in_different_date = $('[name="mptbm_enable_return_in_different_date"]').val();
        let mptbm_buffer_end_minutes = parseInt($('[name="mptbm_buffer_end_minutes"]').val()) || 0;
        let mptbm_first_calendar_date = $('[name="mptbm_first_calendar_date"]').val();

        var selectedDate = $('#mptbm_map_start_date').val();
        var formattedDate = flatpickr.parseDate(selectedDate, 'Y-m-d');

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
                        $('#mptbm_map_start_time')
                            .siblings('.start_time_list')
                            .append($(this).clone());
                    }

                } else if (mptbm_buffer_end_minutes < 1440 && mptbm_buffer_end_minutes > 0) {
                    // ✅ If buffer does not extend to tomorrow, show time after buffer end time
                    if (timeInMinutes >= mptbm_buffer_end_minutes) {
                        $('#mptbm_map_start_time')
                            .siblings('.start_time_list')
                            .append($(this).clone());
                    }

                } else {
                    // For other dates or no buffer, show all times
                    $('#mptbm_map_start_time')
                        .siblings('.start_time_list')
                        .append($(this).clone());
                }
            });
        }
        else {
            // For future dates, show all available times
            $('.start_time_list-no-dsiplay li').each(function () {
                $('#mptbm_map_start_time').siblings('.start_time_list').append($(this).clone());
            });
        }

        // Update the return date picker if needed
        if (mptbm_enable_return_in_different_date == 'yes') {
            var fpReturn = $('#mptbm_return_date')[0];
            if (fpReturn && fpReturn._flatpickr) {
                fpReturn._flatpickr.set('minDate', formattedDate);
            }
        }

        let parent = $(this).closest(".mptbm_transport_search_area");
        mptbm_content_refresh(parent);
        // Auto-opening the time dropdown is a "guide the user to the next
        // field" convenience for when they've just picked a date themselves -
        // this same "change" event also fires once on every tab load/init
        // (flatpickr's onReady syncing its defaultDate into this hidden field,
        // see MP_Global_Function::date_picker_js) with meta.initial set, which
        // should rebuild the time list above but not pop its dropdown open.
        if (!(meta && meta.initial)) {
            parent
                .find("#mptbm_map_start_time")
                .closest(".mp_input_select")
                .find("input.formControl")
                .trigger("click");
        }
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
        let price_based = parent.find('[name="mptbm_price_based"]').val();

        // For fixed_zone (pickup), place marker on map using geo coordinates
        if (price_based === "fixed_zone" && start_place) {
            let selectedOption = $(this).find('option:selected');
            let geoCoords = selectedOption.data('geo');
            let locationLabel = selectedOption.data('label') || selectedOption.text();



            if (geoCoords) {
                let coords = geoCoords.split(',');
                let lat = parseFloat(coords[0]);
                let lng = parseFloat(coords[1]);

                if (!isNaN(lat) && !isNaN(lng)) {
                    // Check if using OpenStreetMap or Google Maps
                    var mapType = document.getElementById('mptbm_map_type');

                    if (mapType && mapType.value === 'openstreetmap') {
                        // OpenStreetMap (Leaflet)
                        if (typeof mptbm_osm_map !== 'undefined' && mptbm_osm_map) {
                            // Clear existing markers
                            if (typeof mptbm_osm_start_marker !== 'undefined' && mptbm_osm_start_marker) {
                                mptbm_osm_map.removeLayer(mptbm_osm_start_marker);
                            }
                            // Add new marker
                            mptbm_osm_start_marker = L.marker([lat, lng]).addTo(mptbm_osm_map);
                            mptbm_osm_map.setView([lat, lng], 14);

                            // Store coordinates for later use
                            window.mptbm_fixed_zone_start_coords = { latitude: lat, longitude: lng };


                            // Calculate route if end marker exists
                            if (typeof mptbm_osm_end_marker !== 'undefined' && mptbm_osm_end_marker) {
                                mptbm_calculate_osm_distance();
                            }
                        } else {

                        }
                    } else if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                        // Google Maps
                        if (typeof mptbm_map !== 'undefined' && mptbm_map) {
                            var latLng = new google.maps.LatLng(lat, lng);

                            // Clear existing start marker
                            if (typeof mptbm_start_marker !== 'undefined' && mptbm_start_marker) {
                                mptbm_start_marker.setMap(null);
                            }

                            // Add new marker
                            mptbm_start_marker = new google.maps.Marker({
                                position: latLng,
                                map: mptbm_map,
                                title: locationLabel
                            });

                            mptbm_map.setCenter(latLng);
                            mptbm_map.setZoom(14);

                            // Store coordinates for later use
                            window.mptbm_fixed_zone_start_coords = { latitude: lat, longitude: lng };


                            // Update distance cookie for Google Maps routing
                            var latLngStr = lat + "," + lng;
                            var endPlace = document.getElementById('mptbm_map_end_place');
                            mptbm_set_cookie_distance_duration(latLngStr, endPlace ? endPlace.value : '');

                            // Calculate route if end marker exists
                            if (typeof mptbm_end_marker !== 'undefined' && mptbm_end_marker) {
                                mptbm_calculate_google_route_from_markers();
                            }
                        }
                    }
                }
            }


        } else {
            // Reset start coordinates if no place selected
            window.mptbm_fixed_zone_start_coords = null;
        }

        if (start_place) {
            let end_place = "";
            if (price_based === "manual") {
                let post_id = parent.find('[name="mptbm_post_id"]').val();
                $.ajax({
                    type: "POST",
                    url: mp_ajax_url,
                    data: {
                        action: "get_mptbm_end_place",
                        nonce: mptbm_ajax.search_nonce,
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
                                setTimeout(function () {
                                    //console.log('Select updated, options count:', target.find('option:not([disabled])').length);
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
        let end_place = $(this).val();
        let price_based = parent.find('[name="mptbm_price_based"]').val();

        // For fixed_zone_dropoff, place marker on map using geo coordinates
        if (price_based === "fixed_zone_dropoff" && end_place) {
            let selectedOption = $(this).find('option:selected');
            let geoCoords = selectedOption.data('geo');
            let locationLabel = selectedOption.data('label') || selectedOption.text();



            if (geoCoords) {
                let coords = geoCoords.split(',');
                let lat = parseFloat(coords[0]);
                let lng = parseFloat(coords[1]);

                if (!isNaN(lat) && !isNaN(lng)) {
                    var mapType = document.getElementById('mptbm_map_type');

                    if (mapType && mapType.value === 'openstreetmap') {
                        if (typeof mptbm_osm_map !== 'undefined' && mptbm_osm_map) {
                            if (typeof mptbm_osm_end_marker !== 'undefined' && mptbm_osm_end_marker) {
                                mptbm_osm_map.removeLayer(mptbm_osm_end_marker);
                            }
                            mptbm_osm_end_marker = L.marker([lat, lng]).addTo(mptbm_osm_map);
                            mptbm_osm_map.setView([lat, lng], 14);
                            window.mptbm_fixed_zone_end_coords = { latitude: lat, longitude: lng };

                            // Calculate route if start marker exists
                            if (typeof mptbm_osm_start_marker !== 'undefined' && mptbm_osm_start_marker) {
                                mptbm_calculate_osm_distance();
                            }
                        }
                    } else if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                        if (typeof mptbm_map !== 'undefined' && mptbm_map) {
                            var latLng = new google.maps.LatLng(lat, lng);
                            if (typeof mptbm_end_marker !== 'undefined' && mptbm_end_marker) {
                                mptbm_end_marker.setMap(null);
                            }
                            mptbm_end_marker = new google.maps.Marker({
                                position: latLng,
                                map: mptbm_map,
                                title: locationLabel
                            });
                            mptbm_map.setCenter(latLng);
                            mptbm_map.setZoom(14);
                            window.mptbm_fixed_zone_end_coords = { latitude: lat, longitude: lng };


                            // Update distance cookie for Google Maps
                            var latLngStr = lat + "," + lng;
                            var startPlace = document.getElementById('mptbm_map_start_place');
                            mptbm_set_cookie_distance_duration(startPlace ? startPlace.value : '', latLngStr);

                            // Calculate route if start marker exists
                            if (typeof mptbm_start_marker !== 'undefined' && mptbm_start_marker) {
                                mptbm_calculate_google_route_from_markers();
                            }
                        }
                    }
                }
            }
        } else {
            // Reset end coordinates if no place selected
            window.mptbm_fixed_zone_end_coords = null;
        }
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

function mptbm_content_refresh(parent) {
    parent.find('[name="mptbm_post_id"]').val("");
    parent.find(".mptbm_map_search_result").remove();
    parent.find(".mptbm_order_summary").remove();
}
// Route planning + map now stay visible while results load inline below the
// map (instead of switching to a separate step/tab under a full dark
// overlay) - show a lightweight in-button spinner instead of blurring the
// whole panel.
// The AJAX response's outer wrapper carries data-tabs-next="#mptbm_search_result",
// which the shared tab-pane CSS (.tabsContentNext [data-tabs-next]) hides by
// default unless it has .active - normally added by switching to that tab.
// We're no longer switching tabs (results render inline below the map
// instead), so mark it active and drop the attribute directly.
// The OSM map keeps whatever zoom/center it had before its container was
// resized - Leaflet has no way to know the container changed size on its
// own, so a collapse/expand leaves it showing a cropped fragment of the
// route rather than re-fitting to it. invalidateSize() tells Leaflet to
// re-measure its container, then re-fitting to the route's own bounds (or
// the marker group, before a route line exists) zooms so the whole trip is
// visible at whatever height the map currently has.
function mptbm_refit_osm_map() {
    if (typeof mptbm_osm_map === 'undefined' || !mptbm_osm_map) {
        return;
    }
    setTimeout(function () {
        mptbm_osm_map.invalidateSize();
        if (typeof mptbm_osm_route !== 'undefined' && mptbm_osm_route) {
            mptbm_osm_map.fitBounds(mptbm_osm_route.getBounds().pad(0.1));
            return;
        }
        var markers = [
            typeof mptbm_osm_start_marker !== 'undefined' ? mptbm_osm_start_marker : null,
            typeof mptbm_osm_end_marker !== 'undefined' ? mptbm_osm_end_marker : null,
            typeof mptbm_osm_extra_marker !== 'undefined' ? mptbm_osm_extra_marker : null,
        ].filter(Boolean);
        if (markers.length > 0) {
            var group = new L.featureGroup(markers);
            mptbm_osm_map.fitBounds(group.getBounds().pad(0.1));
        }
    }, 320);
}
// Sets the "1 Enter Ride Details / 2 Choose a vehicle / 3 Place Order" step
// indicator (.tabListsNext, top of the whole booking flow) to reflect
// targetSelector as the current step - mirrors what mp_script.js's
// active_next_tab() does to these same classes/icons/text when its
// nextTab_next/nextTab_prev buttons are clicked, but written as an explicit,
// idempotent set instead of reusing that function directly: that one also
// slides .tabsContentNext panels open/closed, adjusts page scroll, and (via
// mp_all_content_change) unconditionally *toggles* each step's checkmark/
// class/text - fine for a single click, but calling it every time results
// load (including repeat searches) would flip the checkmark back and forth
// instead of staying put once a step is done.
function mptbm_set_step_active(parent, targetSelector) {
    var $stepList = parent.find('.tabListsNext').first();
    if (!$stepList.length) {
        return;
    }
    var $steps = $stepList.children('[data-tabs-target-next]');
    var targetIndex = $steps.filter('[data-tabs-target-next="' + targetSelector + '"]').index() + 1;
    if (targetIndex < 1) {
        return;
    }
    $steps.each(function (i) {
        var stepNum = i + 1;
        var $step = jQuery(this);
        $step.toggleClass('active', stepNum <= targetIndex);
        // Steps strictly before the current one are "done" - swap their
        // number for a checkmark (data-open-icon/-text vs data-close-icon/
        // -text, same attributes mp_script.js's content_*_change() read).
        var isDone = stepNum < targetIndex;
        var addClass = $step.data('add-class');
        if (addClass) {
            var $classTarget = $step.find('[data-class]').length ? $step.find('[data-class]') : $step;
            $classTarget.toggleClass(addClass, isDone);
        }
        var openIcon = $step.data('open-icon');
        var closeIcon = $step.data('close-icon');
        if (openIcon || closeIcon) {
            var $iconTarget = $step.find('[data-icon]');
            if (isDone) {
                $iconTarget.removeClass(openIcon).addClass(closeIcon);
            } else {
                $iconTarget.removeClass(closeIcon).addClass(openIcon);
            }
        }
        var openText = $step.data('open-text') != null ? $step.data('open-text').toString() : '';
        var closeText = $step.data('close-text') != null ? $step.data('close-text').toString() : '';
        if (openText || closeText) {
            $step.find('[data-text]').html(isDone ? closeText : openText);
        }
    });
}
function mptbm_reveal_inline_results(target) {
    target.find('[data-tabs-next]').addClass('active').removeAttr('data-tabs-next');
    target.addClass('mptbm_inline_results_active');
    // Results live inside .mptbm_map_area (a sibling of the collapsible map
    // body), taking over that same column. For "manual" pricing the whole
    // column starts display:none (no map at all) - force it visible now
    // that it has results to show. Then auto-collapse just the map body
    // (still toggleable back open) since results are the priority now.
    var $mapArea = target.closest('.mptbm_map_area');
    // Flat-rate/"manual" pricing has no real map at all - no geocoding, just
    // named location dropdowns. .mptbm_inline_search_results still lives
    // nested inside .mptbm_map_area though (see below), so that column still
    // needs to be revealed to show these results - just without any of the
    // map-specific chrome/collapse/refit logic, which would otherwise force
    // open a blank gray box sized for a map that was never there.
    //
    // Can't tell "no map" apart from "map not initialized yet" just by
    // checking mptbm_osm_map's truthiness - on the tabs page it's one shared
    // global var, so a map already initialized for a different tab (e.g. the
    // default "Hourly" tab on page load) stays truthy even after switching to
    // Flat Rate. Read this specific tab/form's own price_based value instead
    // - the same field the PHP template itself keys the map's display on.
    var $searchAreaRoot = $mapArea.closest('.mptbm_transport_search_area');
    var priceBased = $searchAreaRoot.find('input[name="mptbm_price_based"]').val();
    // Deliberately NOT reading data-map here - that's the shortcode/block's
    // own "map" option and only governs the pre-search form state. Once
    // results are revealed, visibility is instead driven by the separate
    // global admin switch (Map API Settings > Show Map on Search Result
    // Page), rendered into data-show-map-result in get_details.php. Without
    // this check, that setting was being silently ignored here and the map
    // always forced back to visible once results were shown.
    var showMapResult = ($mapArea.attr('data-show-map-result') || 'yes').toLowerCase();
    var hasMap = priceBased !== 'manual' && showMapResult !== 'no';
    // Results now show inline on step 1's own panel instead of switching to a
    // separate step-2 panel, but the step indicator above it should still
    // read as "Choose a vehicle" being current now that there's something to
    // choose from.
    mptbm_set_step_active($searchAreaRoot, '#mptbm_search_result');
    if ($mapArea.length) {
        if (hasMap) {
            // mptbm_map_collapsed toggles on/off as the user opens/closes the
            // map peek - mptbm_results_shown never comes back off, marking
            // "results are now sharing this column with the map" for CSS
            // that needs to tell that apart from the pre-search state (see
            // the .fullHeight height override below).
            $mapArea.css('display', 'flex').addClass('mptbm_map_collapsed mptbm_results_shown');
            var $toggle = $mapArea.find('.mptbm_map_collapse_toggle');
            $toggle.attr('aria-expanded', 'false');
            $toggle.find('[data-label]').text($toggle.data('expand-text'));
            mptbm_refit_osm_map();
        } else {
            $mapArea.css('display', 'flex').addClass('mptbm_map_area_no_map');
        }
        // Fold Total Distance/Total Time into the trip-summary card's own
        // meta row too, alongside Duration/Pickup Date/Pickup Time - reading
        // the current text rather than moving the live .mptbm_total_distance/
        // .mptbm_total_time nodes themselves, since those keep getting
        // updated in place as the user adjusts locations for their *next*
        // search and need to still exist for that. Hiding (not removing)
        // the original bar keeps that live-update wiring intact underneath.
        // There can be several .mptbm_summary_top_row elements at once - one
        // per hidden .leftSidebar (summary.php renders one per pricing tab)
        // plus the one actually inside the visible .mptbm_results_toolbar -
        // .first() alone would silently grab a hidden one, so scope to the
        // toolbar's own row specifically.
        var $summaryRow = target.find('.mptbm_results_toolbar .mptbm_summary_top_row').first();
        if ($summaryRow.length) {
            // Transfer Type and Extra Waiting Hours are their own custom
            // dropdown widgets (a readonly text input showing the chosen
            // option's label, proxying a real <select>) living in Route
            // Planning itself (a sibling of .mptbm_map_area, both under the
            // same .mptbm_transport_search_area root) - only rendered at all
            // when their respective settings are enabled, so each is read
            // only if actually present.
            var $transferType = $searchAreaRoot.find('[data-proxy-for="mptbm_taxi_return"]');
            var $waitingHours = $searchAreaRoot.find('[data-proxy-for="mptbm_waiting_time"]');
            if ($transferType.length) {
                var transferLabel = $transferType.find('span').first().text().trim();
                var transferValue = $transferType.find('input.formControl').first().val();
                if (transferValue) {
                    $summaryRow.append(
                        jQuery('<div class="mptbm_summary_top_col mptbm_summary_col_transfer_type"></div>')
                            .append(jQuery('<h6/>').text(transferLabel))
                            .append(jQuery('<p class="_textLight_1"/>').text(transferValue))
                    );
                }
            }
            if ($waitingHours.length) {
                var waitingLabel = $waitingHours.find('span').first().text().trim();
                var waitingValue = $waitingHours.find('input.formControl').first().val();
                if (waitingValue) {
                    $summaryRow.append(
                        jQuery('<div class="mptbm_summary_top_col mptbm_summary_col_waiting_hours"></div>')
                            .append(jQuery('<h6/>').text(waitingLabel))
                            .append(jQuery('<p class="_textLight_1"/>').text(waitingValue))
                    );
                }
            }
        }
        // Flat-rate/"manual" pricing never computes a real distance/time (no
        // geocoding at all) - the bar always just shows its placeholder
        // "0 KM"/"0 Hour" text, so skip merging it in for that mode instead
        // of surfacing those meaningless zero values in the summary.
        var $distanceTime = hasMap ? $mapArea.find('.mptbm_distance_time') : jQuery();
        if ($summaryRow.length && $distanceTime.length) {
            var $distanceVal = $distanceTime.find('.mptbm_total_distance').first();
            var $timeVal = $distanceTime.find('.mptbm_total_time').first();
            var distanceText = $distanceVal.text().trim();
            var timeText = $timeVal.text().trim();
            // The original bar's labels are written in ALL CAPS in the source
            // string itself (translated, so not just a CSS transform) - title-
            // casing here (rather than a CSS transform, which can't lowercase
            // characters that are already uppercase) matches the normal-case
            // style of Duration/Pickup Date/Pickup Time next to them.
            function mptbm_title_case(str) {
                return str.toLowerCase().replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            }
            var distanceLabel = mptbm_title_case($distanceVal.closest('.fdColumn').find('h6').first().text().trim());
            var timeLabel = mptbm_title_case($timeVal.closest('.fdColumn').find('h6').first().text().trim());
            if (distanceText) {
                $summaryRow.append(
                    jQuery('<div class="mptbm_summary_top_col mptbm_summary_col_distance"></div>')
                        .append(jQuery('<h6/>').text(distanceLabel))
                        .append(jQuery('<p class="_textLight_1"/>').text(distanceText))
                );
            }
            if (timeText) {
                $summaryRow.append(
                    jQuery('<div class="mptbm_summary_top_col mptbm_summary_col_time_total"></div>')
                        .append(jQuery('<h6/>').text(timeLabel))
                        .append(jQuery('<p class="_textLight_1"/>').text(timeText))
                );
            }
            $distanceTime.addClass('mptbm_distance_time_merged');
        }
    }
    // mp_script.js's lazy-load only scans [data-bg-image] on initial page
    // load/scroll (loadBgImage(), guarded so it only ever runs once) - vehicle
    // photos appended here afterwards never get picked up otherwise, showing
    // as the empty gray placeholder. loadBgImage() itself has no such guard,
    // so calling it directly force-scans the newly appended cards' images.
    if (typeof loadBgImage === 'function') {
        loadBgImage();
    }
}
function mptbm_search_loading(parent, isLoading) {
    var btn = parent.find('#mptbm_get_vehicle');
    var icon = btn.find('.fa-search-location, .fa-spinner').first();
    if (isLoading) {
        btn.prop('disabled', true).addClass('mptbm-searching');
        icon.removeClass('fa-search-location').addClass('fa-spinner fa-spin');
    } else {
        btn.prop('disabled', false).removeClass('mptbm-searching');
        icon.removeClass('fa-spinner fa-spin').addClass('fa-search-location');
    }
}
//=======================//
function mptbm_price_calculation(parent) {
    let target_summary = parent.find(".mptbm_transport_summary");
    let total = 0;
    let post_id = parseInt(parent.find('[name="mptbm_post_id"]').val());
    if (post_id > 0) {
        let quantityInput = parent.find(`.mp_quantity_input[data-post-id="${post_id}"]`);
        let quantityVal = quantityInput.length ? parseInt(quantityInput.val()) || 1 : 1;

        // Use the unit price of transport
        let unit_transport_price = parseFloat(parent.find('[name="mptbm_post_id"]').data("unit-transport-price") || parent.find('[name="mptbm_post_id"]').attr("data-price") / quantityVal || 0);
        let base_transport_price = unit_transport_price * quantityVal;

        let unit_base_price_extra = parseFloat(parent.find('[name="mptbm_post_id"]').attr("data-unit-base-price") || 0);
        let tax_multiplier_val = parseFloat(parent.find('[name="mptbm_post_id"]').attr("data-tax-multiplier") || 1);


        let base_price_extra = unit_base_price_extra * quantityVal * tax_multiplier_val;

        total = total + base_transport_price + base_price_extra;


        parent.find(".mptbm_extra_service_item").each(function () {
            let service_name = jQuery(this)
                .find('[name="mptbm_extra_service[]"]')
                .val();
            if (service_name) {
                let ex_target = jQuery(this).find('[name="mptbm_extra_service_qty[]"]'); // Added missing ] also
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

/**
 * Calculates distance from Base Location to Pickup and Dropoff to Base Location
 */
function mptbm_calculate_base_distances(settings, pickup, dropoff, callback) {
    if (!settings || !settings.coords || !pickup || !dropoff) {
        callback({ distance: 0, duration: 0 });
        return;
    }

    // Check if we should use OSM (if Google is not defined or explicitly using OSM)
    // We check for mptbm_osm_map to see if OSM is the active map provider
    if (typeof google === 'undefined' || typeof mptbm_osm_map !== 'undefined') {
        mptbm_calculate_base_distances_osm(settings, pickup, dropoff, callback);
        return;
    }

    let base_coords = settings.coords;
    let total_distance = 0;
    let total_duration = 0;
    let pending_calls = 0;

    let check_complete = function () {
        if (pending_calls === 0) {
            callback({ distance: total_distance, duration: total_duration });
        }
    };

    let calculate = function (origin, destination) {
        pending_calls++;
        let service = new google.maps.DistanceMatrixService();
        service.getDistanceMatrix({
            origins: [origin],
            destinations: [destination],
            travelMode: 'DRIVING',
        }, function (response, status) {
            if (status === 'OK' && response.rows[0].elements[0].status === 'OK') {
                total_distance += response.rows[0].elements[0].distance.value;
                total_duration += response.rows[0].elements[0].duration.value;
            }
            pending_calls--;
            check_complete();
        });
    };

    if (settings.charge_pickup === 'yes') {
        calculate(base_coords, pickup);
    }
    if (settings.charge_dropoff === 'yes') {
        calculate(dropoff, base_coords);
    }

    if (pending_calls === 0) {
        callback({ distance: 0, duration: 0 });
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

        let target_summary = parent.find('.mptbm_transport_summary');
        let target_extra_service = parent.find('.mptbm_extra_service');
        let target_extra_service_summary = parent.find('.mptbm_extra_service_summary');
        let all_quantity_selectors = parent.find('.mptbm_quantity_selector');
        let target_quantity_selector = parent.find('.mptbm_quantity_selector_' + postId);

        if (target_quantity_selector.length && target_quantity_selector.hasClass('mptbm_booking_item_hidden')) {
            all_quantity_selectors.addClass('mptbm_booking_item_hidden');
            target_quantity_selector.removeClass('mptbm_booking_item_hidden');
        } else {
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
            target_summary.slideUp(400);
            // No vehicle selected anymore -- step 3 is no longer a preview.
            // Matched by data-tabs-target-next rather than .step-place-order:
            // that class only exists in transport_result.php's copy of this
            // stepper, not the one registration_layout.php renders.
            parent.find('[data-tabs-target-next="#mptbm_order_summary"]').removeClass('active');
        } else {
            parent.find('.mptbm_transport_select.active_select').each(function () {
                $(this).removeClass('active_select');
                mp_all_content_change($(this));
            }).promise().done(function () {
                let transport_name = $this.attr('data-transport-name');
                let transport_price = parseFloat($this.attr('data-transport-price'));
                let post_id = $this.attr('data-post-id');

                let quantityInput = parent.find(`.mp_quantity_input[data-post-id="${post_id}"]`);
                let quantityVal = quantityInput.length ? parseInt(quantityInput.val()) || 1 : 1;

                target_summary.find('.mptbm_product_name').html(transport_name);

                let customMessage = $this.closest('.mptbm_booking_item').find('.mptbm-custom-price-message').html();
                if (customMessage) {
                    target_summary.find('.mptbm_product_price').html(
                        'x' + quantityVal + ' <span style="color:#000;">|&nbsp;&nbsp;</span> ' + customMessage
                    );
                } else {
                    target_summary.find('.mptbm_product_price').html(
                        'x' + quantityVal + ' <span style="color:#000;">|&nbsp;&nbsp;</span> ' + mp_price_format(transport_price * quantityVal)
                    );
                }

                $this.addClass('active_select');
                $('.mptbm_booking_item').removeClass('selected');
                $this.closest('.mptbm_booking_item').addClass('selected');

                // Preview step 3 ("Place Order") as active as soon as a vehicle is
                // picked, ahead of the checkout content actually loading.
                parent.find('[data-tabs-target-next="#mptbm_order_summary"]').addClass('active');

                mp_all_content_change($this);

                parent.find('[name="mptbm_post_id"]').val(post_id);
                parent.find('[name="mptbm_post_id"]').attr('data-price', transport_price * quantityVal);
                parent.find('[name="mptbm_post_id"]').attr('data-unit-transport-price', transport_price);
                parent.find('[name="mptbm_post_id"]').attr('data-base-price-calculated', 0);
                parent.find('[name="mptbm_post_id"]').attr('data-unit-base-price', 0);

                // --- BASE PRICE CALCULATION ---
                // FIX: Use the server-calculated base price directly to avoid discrepancies (1.30 difference)
                // The server has already calculated this using high-precision coordinates and settings.
                // We should trust it instead of re-calculating on the client side which might use slightly different logic/API.

                let calcBasePrice = function (callback) {
                    let server_base_price = parseFloat($this.attr('data-unit-base-price') || 0);
                    callback(server_base_price);
                };

                calcBasePrice(function (base_p) {
                    parent.find('[name="mptbm_post_id"]').attr('data-base-price-calculated', base_p * quantityVal);
                    parent.find('[name="mptbm_post_id"]').attr('data-unit-base-price', base_p);
                    let total_b = base_p * quantityVal;

                    // Update the new inline base price detail in summary.php
                    let detail_container = parent.find('.mptbm_base_price_detail');
                    if (base_p > 0) {
                        let b_html = '<div class="_textTheme" style="font-size: 13px; margin-top: 5px; padding-left: 25px;">' +
                            'Base Price: ' + mp_price_format(total_b) + '</div>';
                        detail_container.html(b_html).show();
                    } else {
                        detail_container.html('').hide();
                    }

                    mptbm_price_calculation(parent);
                });

                // Fetch extra services + their summary in parallel instead of
                // one after another - each request only needs post_id and
                // renders its own independent template server-side (see
                // get_mptbm_extra_service/_summary in MPTBM_Transport_Search.php),
                // so chaining them was just doubling the wait for no reason.
                dLoader(parent.find('.tabsContentNext'));
                var extraServiceReq = $.ajax({
                    type: 'POST',
                    url: mp_ajax_url,
                    data: { "action": "get_mptbm_extra_service", "nonce": mptbm_ajax.search_nonce, "post_id": post_id }
                });
                var extraServiceSummaryReq = $.ajax({
                    type: 'POST',
                    url: mp_ajax_url,
                    data: { "action": "get_mptbm_extra_service_summary", "nonce": mptbm_ajax.search_nonce, "post_id": post_id }
                });
                $.when(extraServiceReq, extraServiceSummaryReq).done(function (serviceResp, summaryResp) {
                    target_extra_service.html(serviceResp[0]);
                    // The footer copy of .mptbm_transport_summary just arrived with
                    // this markup - CSS hides every .mptbm_transport_summary by
                    // default (shown only via the sidebar's own slideDown, which
                    // already ran before this element existed), so reveal this one
                    // explicitly rather than waiting on the next price recalculation.
                    target_extra_service.find('.mptbm_transport_summary').show();
                    // Fill in its price/total immediately - otherwise it stays blank
                    // until the customer's first extra-service click, since the vehicle
                    // price line and base price calculation both already ran before
                    // this copy existed. Copy the sidebar's already-computed price line
                    // rather than re-deriving it (quantity/tax/custom-message logic
                    // lives in the click handler above, not worth duplicating here).
                    target_extra_service.find('.mptbm_product_price').html(
                        target_summary.find('.mptbm_product_price').first().html()
                    );
                    mptbm_price_calculation(parent);
                    checkAndToggleBookNowButton(parent);
                    if (mptbm_is_ios()) {
                        target_extra_service[0].style.display = 'none';
                        void target_extra_service[0].offsetHeight;
                        target_extra_service[0].style.display = '';
                    }

                    // Re-query instead of using target_extra_service_summary as
                    // captured before this vehicle's markup existed: that snapshot
                    // only ever matched the sidebar copy, so the footer copy
                    // (revealed just above) would never receive the itemized
                    // breakdown otherwise.
                    let all_extra_service_summary = parent.find('.mptbm_extra_service_summary');
                    all_extra_service_summary.html(summaryResp[0]).promise().done(function () {
                        if (target_extra_service.find('[name="mptbm_extra_service[]"]').length > 0) {
                            target_summary.slideDown(400);
                            target_extra_service.slideDown(400);
                            all_extra_service_summary.slideDown(400);
                            pageScrollTo(target_extra_service);
                        }
                        dLoaderRemove(parent.find('.tabsContentNext'));
                        if (!target_extra_service.find('[name="mptbm_extra_service[]"]').length) {
                            parent.find('.mptbm_book_now[type="button"]').trigger('click');
                        } else {
                            checkAndToggleBookNowButton(parent);
                        }
                        if (mptbm_is_ios()) {
                            all_extra_service_summary.hide().show(0);
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
    $(document).on('click', '.mptbm_map_collapse_toggle', function () {
        var $btn = $(this);
        var $mapArea = $btn.closest('.mptbm_map_area');
        var collapsed = $mapArea.toggleClass('mptbm_map_collapsed').hasClass('mptbm_map_collapsed');
        $btn.attr('aria-expanded', collapsed ? 'false' : 'true');
        $btn.find('[data-label]').text(collapsed ? $btn.data('expand-text') : $btn.data('collapse-text'));
        mptbm_refit_osm_map();
    });

    //===========================//
    $(document).on('click', '.mptbm_inline_results_reset', function () {
        window.location.reload();
    });

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
        if ($(this).is(':disabled')) {
            // Booking is unavailable (no WooCommerce and no Pro plugin active);
            // the disabled state can still be reached via the auto-click that
            // fires when a vehicle has no extra services, so bail out here too.
            return;
        }
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
        let mptbm_threshold_base_price = parent.find('[name="mptbm_post_id"]').attr('data-base-price-calculated') || 0;
        // Generated with the vehicle-result response, so it remains fresh even when a
        // guest received the outer booking page from a full-page cache.
        let add_to_cart_nonce = parent.find('[name="mptbm_add_to_cart_nonce"]').val() || '';

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

            // Get coordinates for fixed_zone/fixed_zone_dropoff pricing
            let start_place_coordinates = null;
            let end_place_coordinates = null;

            // Try to get from hidden inputs first
            let start_coords_input = parent.find('input[name="mptbm_start_place_coordinates"]');
            let end_coords_input = parent.find('input[name="mptbm_end_place_coordinates"]');

            if (start_coords_input.length && start_coords_input.val()) {
                try {
                    start_place_coordinates = JSON.parse(start_coords_input.val());
                } catch (e) {
                    start_place_coordinates = start_coords_input.val();
                }
            } else if (typeof window.mptbm_fixed_zone_start_coords !== 'undefined' && window.mptbm_fixed_zone_start_coords) {
                start_place_coordinates = window.mptbm_fixed_zone_start_coords;
            } else if (mptbm_start_marker) {
                let pos = mptbm_start_marker.getPosition();
                if (pos) {
                    start_place_coordinates = { latitude: pos.lat(), longitude: pos.lng() };
                }
            }

            if (end_coords_input.length && end_coords_input.val()) {
                try {
                    end_place_coordinates = JSON.parse(end_coords_input.val());
                } catch (e) {
                    end_place_coordinates = end_coords_input.val();
                }
            } else if (typeof window.mptbm_fixed_zone_end_coords !== 'undefined' && window.mptbm_fixed_zone_end_coords) {
                end_place_coordinates = window.mptbm_fixed_zone_end_coords;
            } else if (mptbm_end_marker) {
                let pos = mptbm_end_marker.getPosition();
                if (pos) {
                    end_place_coordinates = { latitude: pos.lat(), longitude: pos.lng() };
                }
            }

            $.ajax({
                type: 'POST',
                url: mp_ajax_url,
                data: {
                    action: "mptbm_add_to_cart",
                    mptbm_add_to_cart_nonce: add_to_cart_nonce,
                    // Kept for one rolling-deployment cycle so an older PHP handler can
                    // still validate clients whose assets update before the backend.
                    nonce: mptbm_ajax.search_nonce,
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
                    mptbm_max_hand_luggage: parent.find('#mptbm_max_hand_luggage').val(),
                    mptbm_extra_stop_place: parent.find('input[name="mptbm_extra_stop_place"]').val(),
                    mptbm_original_price_base: mptbm_original_price_base,
                    mptbm_distance: parent.find('input[name="mptbm_hidden_distance"]').val(),
                    mptbm_duration: parent.find('input[name="mptbm_hidden_duration"]').val(),
                    mptbm_duration_text: parent.find('input[name="mptbm_hidden_duration_text"]').val(),
                    start_place_coordinates: start_place_coordinates ? JSON.stringify(start_place_coordinates) : '',
                    end_place_coordinates: end_place_coordinates ? JSON.stringify(end_place_coordinates) : '',
                    mptbm_threshold_base_price: mptbm_threshold_base_price,
                    // Standalone (no-WooCommerce) Pro custom booking flow fields. Ignored
                    // by the WooCommerce add-to-cart handler when WooCommerce is active.
                    mptbm_payment_method: parent.find('[name="mptbm_payment_method"]:checked').val() || '',
                    mptbm_billing_name: parent.find('[name="mptbm_billing_name"]').val() || '',
                    mptbm_billing_email: parent.find('[name="mptbm_billing_email"]').val() || '',
                    mptbm_billing_phone: parent.find('[name="mptbm_billing_phone"]').val() || ''
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
                    } else if (data && /^https?:\/\//i.test(data.trim())) {
                        window.location.href = data;
                    } else {
                        // Empty/invalid response (e.g. both WooCommerce and a
                        // custom payment method were enabled and the request
                        // fell through without a usable result) - clear the
                        // loader instead of leaving the button stuck.
                        dLoaderRemove(parent.find('.tabsContentNext'));
                        console.log('mptbm_add_to_cart: unexpected response', data);
                    }
                },
                error: function (response) {
                    dLoaderRemove(parent.find('.tabsContentNext'));
                    console.log(response);
                }
            });
        } else {
            // Missing required data - bail out loud instead of leaving the button
            // looking clicked with no visible feedback and nothing in the console.
            console.warn('mptbm_book_now: missing required booking data, not submitting', {
                start_place: start_place, end_place: end_place, link_id: link_id, post_id: post_id
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
            /*$tabs.eq(0).css({
                'border-top-left-radius': 'var(--dbrl)',
                'border-bottom-left-radius': 'var(--dbrl)'
            });
            $tabs.last().css({
                'border-top-right-radius': 'var(--dbrl)',
                'border-bottom-right-radius': 'var(--dbrl)'
            });*/
        }
        $('.mptb-tabs li').click(function () {
            var tab_id = $(this).attr('mptbm-data-tab');
            var form_style = $(this).attr('mptbm-data-form-style');
            var map = $(this).attr('mptbm-data-map');
            var $tabContentWrap = $(this).closest('.mptb-tab-container').find('.mptb-tabs-content-wrap');

            // Ignore re-clicks on the active tab or while a switch is already loading
            if ($(this).hasClass('current') || $tabContentWrap.hasClass('mptbm-tab-loading')) {
                return;
            }

            // Clean up existing map instance before switching tabs
            mptbm_cleanup_map();

            // Freeze height so the wrap does not collapse while content stays
            // visible (faded) under the spinner. After load, settleHeight()
            // eases to the new content size, then clears the lock (height: auto).
            var lockedHeight = $tabContentWrap.outerHeight() || 0;
            if (lockedHeight > 0) {
                $tabContentWrap.css('min-height', lockedHeight + 'px');
            }

            function settleHeight() {
                if (!$tabContentWrap.length) {
                    return;
                }
                var $currentTab = $tabContentWrap.find('.mptb-tab-content.current');
                var contentHeight = $currentTab.length ? $currentTab.outerHeight(true) : 0;
                if (contentHeight > 0) {
                    $tabContentWrap.css('min-height', contentHeight + 'px');
                }
                setTimeout(function () {
                    $tabContentWrap.css('min-height', '');
                }, 260);
            }

            function clearTabLoader() {
                $tabContentWrap.removeClass('mptbm-tab-loading');
                $tabContentWrap.find('.mptbm-tab-loader, .mptbm-loading-overlay').remove();
            }

            // Keep the active tab content on screen (opacity via CSS) and show
            // a spinner with no overlay background while the next tab loads.
            clearTabLoader();
            $tabContentWrap.addClass('mptbm-tab-loading');
            $tabContentWrap.append('<div class="mptbm-tab-loader"><div class="mptbm-spinner"></div></div>');

            // Mark the clicked tab pill as active
            $('.mptb-tabs li').removeClass('current');
            $(this).addClass('current');

            $.ajax({
                type: "POST",
                url: mp_ajax_url,
                data: {
                    action: "load_get_details_page",
                    nonce: mptbm_ajax.search_nonce,
                    tab_id: tab_id,
                    form_style: form_style,
                    map: map
                },
                success: function (data) {
                    var tabContainer = $("#" + tab_id);
                    if (tabContainer.length === 0) {
                        var tabContainerParent = $tabContentWrap.length ? $tabContentWrap : $('.mptb-tab-container');
                        if (tabContainerParent.length > 0) {
                            var newTabContainer = $('<div id="' + tab_id + '" class="mptb-tab-content"></div>');
                            tabContainerParent.append(newTabContainer);
                            tabContainer = newTabContainer;
                        } else {
                            console.error('Tab container parent not found');
                            clearTabLoader();
                            return;
                        }
                    }

                    // Empty other tabs only now — keeps previous content visible
                    // under the faded loader until replacement is ready, and
                    // avoids duplicate field IDs once the new markup is inserted.
                    $('.mptb-tab-content').not(tabContainer).empty();
                    tabContainer.html(data);

                    $('.mptb-tab-content').removeClass('current');
                    tabContainer.addClass('current');

                    if (!tabContainer.is(':visible')) {
                        tabContainer.css('display', 'block');
                    }

                    clearTabLoader();
                    settleHeight();

                    setTimeout(function () {
                        var currentTab = $('.mptb-tabs li.current').attr('mptbm-data-tab');
                        var mapEnabled = $('.mptb-tabs li.current').attr('mptbm-data-map');

                        if (currentTab !== 'flat-rate' && mapEnabled === 'yes') {
                            mptbm_map_area_init();
                        }

                        var mapType = document.getElementById('mptbm_map_type');
                        if (!(mapType && mapType.value === 'openstreetmap')) {
                            initializeGooglePlacesAutocomplete();
                        }

                        settleHeight();
                    }, 100);
                },
                error: function () {
                    clearTabLoader();
                    var tabContainer = $("#" + tab_id);
                    if (tabContainer.length > 0) {
                        $('.mptb-tab-content').not(tabContainer).empty();
                        tabContainer.html('<div style="text-align: center; padding: 20px; color: red;"><p>Error loading content. Please try again.</p></div>');
                        $('.mptb-tab-content').removeClass('current');
                        tabContainer.addClass('current');
                    }
                    settleHeight();
                },
            });
        });
    });

    // Handle select dropdown search functionality
    $(document).on('click', '#mptbm_manual_start_place, #mptbm_manual_end_place', function (e) {


        var $select = $(this);
        var selectId = $select.attr('id');


        // Remove any existing custom search elements
        $('.mptbm-custom-select-wrapper').remove();

        // Check if select has options (dropoff might be empty initially)
        var $options = $select.find('option:not([disabled])');

        if ($options.length <= 0) {
            return;
        }

        // Get select position and dimensions
        var selectOffset = $select.offset();
        var selectWidth = $select.outerWidth();
        var selectHeight = $select.outerHeight();

        // Keep the original select visible - don't hide it
        // $select.hide(); // REMOVED - keep select visible

        // Create custom select wrapper with dynamic positioning
        // (cosmetic styling lives in mptbm_registration.css; only positioning is set inline)
        var $customWrapper = $('<div class="mptbm-custom-select-wrapper"></div>');

        // Create search input
        var $searchInput = $('<input type="text" class="mptbm-custom-search-input" placeholder="Search locations..." />');

        // Create options container
        var $optionsContainer = $('<div class="mptbm-custom-options"></div>');

        // Function to update dropdown position
        function updateDropdownPosition() {
            var currentOffset = $select.offset();
            var currentWidth = $select.outerWidth();
            var currentHeight = $select.outerHeight();

            var scrollTop = $(window).scrollTop();
            var scrollLeft = $(window).scrollLeft();

            // Always position below
            var top = currentOffset.top - scrollTop + currentHeight + 2;
            var left = currentOffset.left - scrollLeft;
            var width = currentWidth;

            var windowHeight = $(window).height();
            var windowWidth = $(window).width();

            // Calculate available space and adjust dropdown height accordingly
            var availableHeight = windowHeight - top - 20; // 20px padding from bottom
            var maxHeight = Math.min(250, Math.max(100, availableHeight)); // Min 100px, max 250px

            $optionsContainer.css('max-height', maxHeight + 'px');

            // Prevent offscreen left/right
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

        // Get all options from original select (excluding disabled ones)
        var $originalOptions = $select.find('option:not([disabled])');
        var optionsHtml = '';


        $originalOptions.each(function () {
            var optionText = $(this).text();
            var optionValue = $(this).val();
            var isSelected = $(this).is(':selected');

            var selectedClass = isSelected ? 'mptbm-option-selected' : '';
            optionsHtml += '<div class="mptbm-custom-option ' + selectedClass + '" data-value="' + optionValue + '">' + optionText + '</div>';
        });

        $optionsContainer.html(optionsHtml);

        // Assemble and append to mptbm_transport_search_area
        $customWrapper.append($searchInput).append($optionsContainer);
        $('.mptbm_transport_search_area').append($customWrapper);

        // Initial and responsive position
        updateDropdownPosition();
        $(window).on('scroll resize', updateDropdownPosition);

        // Ensure map elements are not affected by the dropdown
        $('.mptbm_map_area').css('z-index', '1');
        $('.mptbm_map_area #mptbm_map_area').css('z-index', '1');

        // Focus on search input
        $searchInput.focus();

        // Handle search input
        $searchInput.on('input', function () {
            var searchTerm = $(this).val().toLowerCase();
            var $options = $customWrapper.find('.mptbm-custom-option');


            $options.each(function () {
                var optionText = $(this).text().toLowerCase();
                if (optionText.includes(searchTerm) || searchTerm === '') {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Handle option selection
        $customWrapper.on('click', '.mptbm-custom-option', function () {
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

        });

        // Handle select change event to clean up custom dropdown
        $select.one('change', function () {
            $customWrapper.remove();
            // Restore map z-index
            $('.mptbm_map_area').css('z-index', '');
            $('.mptbm_map_area #mptbm_map_area').css('z-index', '');
        });

        // Handle clicking outside to close
        $(document).one('click', function (e) {
            if (!$(e.target).closest('.mptbm-custom-select-wrapper, #' + selectId).length) {
                $customWrapper.remove();
                // Restore map z-index
                $('.mptbm_map_area').css('z-index', '');
                $('.mptbm_map_area #mptbm_map_area').css('z-index', '');
            }
        });

        // Handle window resize to update dropdown position with debouncing
        var positionUpdateTimeout;
        var positionUpdateHandler = function () {
            clearTimeout(positionUpdateTimeout);
            positionUpdateTimeout = setTimeout(function () {
                updateDropdownPosition();
            }, 16); // ~60fps throttling
        };

        $(window).on('resize.mptbm-dropdown', positionUpdateHandler);

        // Clean up event listeners when dropdown is removed
        var originalRemove = $customWrapper.remove;
        $customWrapper.remove = function () {
            clearTimeout(positionUpdateTimeout);
            $(window).off('resize.mptbm-dropdown');
            $(window).off('scroll resize', updateDropdownPosition);
            return originalRemove.call(this);
        };

        // Handle escape key
        $searchInput.on('keydown', function (e) {
            if (e.key === 'Escape') {
                $customWrapper.remove();
                // Restore map z-index
                $('.mptbm_map_area').css('z-index', '');
                $('.mptbm_map_area #mptbm_map_area').css('z-index', '');
            }
        });
    });

    // Prevent native dropdown behavior for manual select elements
    $(document).on('focus mousedown keydown', '#mptbm_manual_start_place, #mptbm_manual_end_place', function (e) {
        // Only prevent if it's not already handled by our custom dropdown
        if (!$(e.target).closest('.mptbm-custom-select-wrapper').length) {
            if (e.type === 'focus' || e.type === 'mousedown' ||
                (e.type === 'keydown' && (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown' || e.key === 'ArrowUp'))) {
                e.preventDefault();
                e.stopPropagation();
            }
        }
    });

    // "View Details" toggle - expands the real specs/reviews panel rendered
    // server-side in vehicle_item.php (only present when there is genuinely
    // more content than the visible feature chips).
    $(document).on('click', '.mptbm_view_details_toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $button = $(this);
        var postId = $button.data('post-id');
        var $wrapper = $button.closest('.mptbm-vehicle-wrapper');
        var $panel = $wrapper.find('.mptbm_vehicle_details_panel[data-post-id="' + postId + '"]');
        if (!$panel.length) {
            return;
        }

        $panel.slideToggle(250, function () {
            var isOpen = $panel.is(':visible');
            $button.attr('aria-expanded', isOpen ? 'true' : 'false');
            $button.toggleClass('is-open', isOpen);
            $button.find('[data-label]').text(isOpen ? $button.data('hide-text') : $button.data('view-text'));
        });
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

// Utility: Detect Safari
function mptbm_is_safari() {
    return /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
}

// Fallback distance calculation for Safari when Google Maps API fails
function mptbm_fallback_distance_calculation(start_place, end_place) {

    // Simple fallback: show placeholder values
    var fallback_distance = "Calculating...";
    var fallback_duration = "Calculating...";

    // Update UI with fallback values
    jQuery(".mptbm_total_distance").html(fallback_distance);
    jQuery(".mptbm_total_time").html(fallback_duration);
    jQuery(".mptbm_distance_time").slideDown("fast");

    // Set cookies with fallback values
    var now = new Date();
    var time = now.getTime();
    var expireTime = time + 3600 * 1000 * 12;
    now.setTime(expireTime);

    var cookieOptions = "; expires=" + now.toUTCString() + "; path=/; SameSite=Lax";
    document.cookie = "mptbm_distance=" + encodeURIComponent("0") + cookieOptions;
    document.cookie = "mptbm_distance_text=" + encodeURIComponent(fallback_distance) + cookieOptions;
    document.cookie = "mptbm_duration=" + encodeURIComponent("0") + cookieOptions;
    document.cookie = "mptbm_duration_text=" + encodeURIComponent(fallback_duration) + cookieOptions;

    // Try to use server-side calculation as backup
    if (typeof mp_ajax_url !== 'undefined') {
        console.log("MPTBM JS: Calling server-side fallback");
        jQuery.ajax({
            type: "POST",
            url: mp_ajax_url,
            data: {
                action: "mptbm_calculate_distance_fallback",
                start_place: start_place,
                end_place: end_place
            },
            success: function (response) {
                console.log("MPTBM JS: Server fallback response", response);
                if (response.success && response.data) {
                    var distElem = jQuery(".mptbm_total_distance");
                    var timeElem = jQuery(".mptbm_total_time");
                    console.log("MPTBM JS: Updating UI. Dist Elem length:", distElem.length, "Time Elem length:", timeElem.length);

                    distElem.html(response.data.distance_text);
                    timeElem.html(response.data.duration_text);

                    // Update hidden inputs for AJAX fallback
                    var mapArea = jQuery('#mptbm_map_area').closest('.mptbm_transport_search_area');
                    if (mapArea.length > 0) {
                        mapArea.find('input[name="mptbm_hidden_distance"]').val(response.data.distance);
                        mapArea.find('input[name="mptbm_hidden_duration"]').val(response.data.duration);
                        mapArea.find('input[name="mptbm_hidden_distance_text"]').val(response.data.distance_text);
                        mapArea.find('input[name="mptbm_hidden_duration_text"]').val(response.data.duration_text);
                    }

                    // Update cookies with server response
                    document.cookie = "mptbm_distance=" + encodeURIComponent(response.data.distance) + cookieOptions;
                    document.cookie = "mptbm_distance_text=" + encodeURIComponent(response.data.distance_text) + cookieOptions;
                    document.cookie = "mptbm_duration=" + encodeURIComponent(response.data.duration) + cookieOptions;
                    document.cookie = "mptbm_duration_text=" + encodeURIComponent(response.data.duration_text) + cookieOptions;
                }
            },
            error: function () {
                console.log("Server-side distance calculation also failed");
            }
        });
    }
}

// "Best Price" badge on the search-results vehicle cards: computed from the
// actual prices rendered for the current search (not a fabricated claim).
// The vehicle list loads in over AJAX after a search, and .mainSection
// itself doesn't exist until then, so this watches the whole document for
// it to appear/change rather than trying to bind to a specific element.
(function ($) {
    "use strict";

    function highlightBestPrice() {
        $('.mainSection').each(function () {
            var mainSection = $(this);
            var cheapestBtn = null;
            var cheapestPrice = Infinity;

            mainSection.find('.mptbm_transport_select[data-transport-price]').each(function () {
                var price = parseFloat($(this).attr('data-transport-price'));
                var item = $(this).closest('.mptbm_booking_item');
                if (!price || price <= 0 || item.hasClass('mptbm_booking_item_hidden')) {
                    return;
                }
                if (price < cheapestPrice) {
                    cheapestPrice = price;
                    cheapestBtn = $(this);
                }
            });

            mainSection.find('.mptbm_best_price_badge').remove();

            if (cheapestBtn) {
                var image = cheapestBtn.closest('.mptbm_booking_item').find('.mptbm_vehicle_image').first();
                image.append('<span class="mptbm_best_price_badge">Best Price</span>');
            }
        });
    }

    // Results toolbar: live count of vehicles actually visible after geo-fence /
    // availability filtering (real data — same "hidden" class the rest of the
    // page already relies on), not the raw server-side query count.
    function updateResultsCount() {
        $('.mainSection').each(function () {
            var mainSection = $(this);
            var visible = mainSection.find('.mptbm_booking_item').not('.mptbm_booking_item_hidden').length;
            mainSection.find('.mptbm_results_count_number').text(visible);
        });
    }

    var debounceTimer = null;
    function scheduleHighlight() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            highlightBestPrice();
            updateResultsCount();
        }, 150);
    }

    // Sort vehicle cards by their real rendered price. "Recommended" restores
    // the original server-rendered order (first time we see a given results
    // list, its current DOM order is cached as that baseline).
    function sortVehicles(mainSection, mode) {
        var area = mainSection.find('.mp_sticky_depend_area').first();
        var wrappers = area.children('.mptbm-vehicle-wrapper').toArray();
        if (!wrappers.length) {
            return;
        }
        if (!area.data('mptbmOriginalOrder')) {
            area.data('mptbmOriginalOrder', wrappers.slice());
        }

        var ordered;
        if (mode === 'price_low' || mode === 'price_high') {
            ordered = wrappers.slice().sort(function (a, b) {
                var priceA = parseFloat($(a).find('[data-transport-price]').first().attr('data-transport-price')) || 0;
                var priceB = parseFloat($(b).find('[data-transport-price]').first().attr('data-transport-price')) || 0;
                return mode === 'price_high' ? priceB - priceA : priceA - priceB;
            });
        } else if (mode === 'rating') {
            ordered = wrappers.slice().sort(function (a, b) {
                var ratingA = parseFloat($(a).find('[data-transport-rating]').first().attr('data-transport-rating')) || 0;
                var ratingB = parseFloat($(b).find('[data-transport-rating]').first().attr('data-transport-rating')) || 0;
                return ratingB - ratingA;
            });
        } else {
            ordered = area.data('mptbmOriginalOrder');
        }

        var anchor = area.find('.geo-fence-no-transport').first();
        ordered.forEach(function (el) {
            if (anchor.length) {
                $(el).insertBefore(anchor);
            } else {
                area.append(el);
            }
        });
    }

    $(document).ready(function () {
        highlightBestPrice();
        updateResultsCount();
        if (typeof MutationObserver !== 'undefined') {
            new MutationObserver(scheduleHighlight).observe(document.body, { childList: true, subtree: true });
        }
    });

    $(document).on('change', '.mptbm_sort_select', function () {
        var mainSection = $(this).closest('.mainSection');
        sortVehicles(mainSection, $(this).val());
    });

    // Grid/List view toggle - purely a layout reflow of the same real cards,
    // no data changes.
    $(document).on('click', '.mptbm_results_view_toggle button', function () {
        var $btn = $(this);
        if ($btn.hasClass('is-active')) {
            return;
        }
        var view = $btn.data('view');
        var area = $btn.closest('.mainSection').find('.mp_sticky_depend_area').first();
        $btn.siblings().removeClass('is-active');
        $btn.addClass('is-active');
        area.toggleClass('mptbm_view_grid', view === 'grid');
        area.toggleClass('mptbm_view_list', view === 'list');
    });
})(jQuery);

// Multi-row "Add Extra Stop" (.mptbm_extra_stops_wrapper in get_details.php).
// This markup had no JS behind it at all - clicking "Add Extra Stop" did
// nothing. Kept self-contained (own OSM search, not the shared
// mptbm_setup_osm_autocomplete/mptbm_handle_osm_address_selection pair) since
// those are hard-wired to the single start/end/legacy-extra marker slots and
// reworking them for an arbitrary number of rows risked regressing the
// pickup/dropoff autocomplete that already works.
(function ($) {
    "use strict";

    // Both mptbm_set_cookie_distance_duration (Google) and
    // mptbm_calculate_osm_distance (OSM) now read every current
    // .mptbm_extra_stop_place_input / .mptbm_extra_stop_coords fresh from the
    // DOM on each call - this just needs to trigger the right one whenever a
    // stop is picked or removed, same as the existing start/end change handlers do.
    function mptbmExtraStopTriggerRecalculation() {
        var mapTypeEl = document.getElementById('mptbm_map_type');
        if (mapTypeEl && mapTypeEl.value === 'openstreetmap') {
            if (typeof mptbm_calculate_osm_distance === 'function') {
                mptbm_calculate_osm_distance();
            }
            return;
        }
        var startInput = document.getElementById('mptbm_map_start_place') || document.getElementById('mptbm_manual_start_place');
        var endInput = document.getElementById('mptbm_map_end_place') || document.getElementById('mptbm_manual_end_place');
        if (typeof mptbm_set_cookie_distance_duration === 'function') {
            mptbm_set_cookie_distance_duration(startInput ? startInput.value : '', endInput ? endInput.value : '');
        }
    }

    function mptbmExtraStopMaxRows($wrapper) {
        var max = parseInt($wrapper.attr('data-max-stops'), 10);
        return max > 0 ? max : 3;
    }

    function mptbmExtraStopToggleAddLink($wrapper) {
        var max = mptbmExtraStopMaxRows($wrapper);
        var count = $wrapper.find('.mptbm_extra_stops_list').children('.mptbm_extra_stop_row').length;
        $wrapper.find('.mptbm_add_extra_stop_row').toggle(count < max);
    }

    function mptbmExtraStopSearch(query, container, input, coordsInput) {
        container.innerHTML = '<div style="padding: 9px 12px; color:#94a3b8; font-size:13px;">Searching&hellip;</div>';
        container.style.display = 'block';

        var body = new URLSearchParams();
        body.append('action', 'mptbm_osm_search');
        body.append('nonce', mptbm_ajax.osm_nonce);
        body.append('q', query);

        fetch(mptbm_ajax.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body,
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (response) {
                // A slower-to-resolve older search (e.g. from a stop row the
                // customer has since edited or moved on from) must not pop
                // this dropdown back open with stale results out from under
                // whatever they're doing now - bail if the field no longer
                // holds the text this response was for.
                if (input.value.trim() !== query) {
                    return;
                }

                container.innerHTML = '';

                if (!response.success || !response.data || !response.data.length) {
                    container.innerHTML = '<div style="padding: 9px 12px; color:#94a3b8; font-size:13px;">No results found</div>';
                    container.style.display = 'block';
                    return;
                }

                response.data.forEach(function (result) {
                    var item = document.createElement('div');
                    item.style.cssText = 'display:flex; align-items:flex-start; gap:10px; padding:10px 12px; margin:2px 0; cursor:pointer; border-radius:10px; color:#0f172a; font-size:13.5px; font-weight:500; line-height:1.4;';

                    var icon = document.createElement('i');
                    icon.className = 'fas fa-map-marker-alt';
                    icon.style.cssText = 'flex:0 0 auto; width:14px; margin-top:2px; color:#94a3b8; font-size:13px;';

                    var text = document.createElement('span');
                    text.textContent = result.display_name;
                    text.style.cssText = 'flex:1 1 auto; min-width:0;';

                    item.appendChild(icon);
                    item.appendChild(text);

                    item.addEventListener('click', function () {
                        input.value = result.display_name;
                        // Matches the "lat,lng" string format
                        // get_server_distance_with_stops() parses from
                        // mptbm_extra_stop_place_coordinates[].
                        coordsInput.value = parseFloat(result.lat) + ',' + parseFloat(result.lon);
                        container.style.display = 'none';

                        // Drop/replace a pin for this specific row so the map
                        // shows the stop, not just the bent route line.
                        if (typeof mptbm_osm_map !== 'undefined' && mptbm_osm_map && typeof L !== 'undefined') {
                            if (input._mptbmStopMarker) {
                                mptbm_osm_map.removeLayer(input._mptbmStopMarker);
                            }
                            input._mptbmStopMarker = L.marker([parseFloat(result.lat), parseFloat(result.lon)], {
                                title: result.display_name
                            }).addTo(mptbm_osm_map).bindPopup(result.display_name);
                        }

                        mptbmExtraStopTriggerRecalculation();
                    });
                    item.addEventListener('mouseenter', function () { this.style.backgroundColor = '#f1f4f9'; });
                    item.addEventListener('mouseleave', function () { this.style.backgroundColor = ''; });

                    container.appendChild(item);
                });
            })
            .catch(function () {
                if (input.value.trim() !== query) {
                    return;
                }
                container.innerHTML = '<div style="padding: 9px 12px; color:#dc2626; font-size:13px;">Search failed</div>';
            });
    }

    function mptbmExtraStopSetupAutocomplete($row) {
        var input = $row.find('.mptbm_extra_stop_place_input')[0];
        var coordsInput = $row.find('.mptbm_extra_stop_coords')[0];
        if (!input || !coordsInput) {
            return;
        }

        var mapTypeEl = document.getElementById('mptbm_map_type');
        var isOSM = !mapTypeEl || mapTypeEl.value === 'openstreetmap';

        if (!isOSM) {
            if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
                return;
            }
            var autocomplete = new google.maps.places.Autocomplete(input);
            var restrict = $('[name="mptbm_restrict_search_country"]').val();
            var country = $('[name="mptbm_country"]').val();
            if (restrict === 'yes' && country) {
                autocomplete.setComponentRestrictions({ country: [country] });
            }
            google.maps.event.addListener(autocomplete, 'place_changed', function () {
                var place = autocomplete.getPlace();
                if (place && place.geometry && place.geometry.location) {
                    coordsInput.value = place.geometry.location.lat() + ',' + place.geometry.location.lng();
                } else {
                    coordsInput.value = '';
                }
                mptbmExtraStopTriggerRecalculation();
            });
            return;
        }

        var resultsContainer = document.createElement('div');
        resultsContainer.className = 'mptbm-osm-autocomplete';
        resultsContainer.style.cssText = 'position: fixed; box-sizing: border-box; font-size:14px; background: #fff; border: 1px solid #e7eaf0; border-radius: 14px; max-height: 240px; overflow-y: auto; z-index: 99999 !important; display: none; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12), 0 2px 8px rgba(15, 23, 42, 0.06); padding: 6px;';
        document.body.appendChild(resultsContainer);
        // Appended to body (so it can float over everything), not to the row
        // itself - stash a reference so removing the row can clean this up
        // too, instead of leaving an orphaned dropdown behind forever.
        input._mptbmStopResultsContainer = resultsContainer;

        function positionDropdown() {
            var rect = input.getBoundingClientRect();
            resultsContainer.style.top = (rect.bottom + 2) + 'px';
            resultsContainer.style.left = rect.left + 'px';
            resultsContainer.style.width = rect.width + 'px';
        }

        var debounceTimer = null;
        input.addEventListener('input', function (e) {
            clearTimeout(debounceTimer);
            var query = e.target.value.trim();
            coordsInput.value = '';
            if (query.length < 3) {
                resultsContainer.style.display = 'none';
                return;
            }
            debounceTimer = setTimeout(function () {
                positionDropdown();
                mptbmExtraStopSearch(query, resultsContainer, input, coordsInput);
            }, 300);
        });

        window.addEventListener('scroll', positionDropdown);
        window.addEventListener('resize', positionDropdown);
        document.addEventListener('click', function (e) {
            if (e.target !== input && !resultsContainer.contains(e.target)) {
                resultsContainer.style.display = 'none';
            }
        });
    }

    $(document).on('click', '.mptbm_add_extra_stop_row', function () {
        var $wrapper = $(this).closest('.mptbm_extra_stops_wrapper');
        var $list = $wrapper.find('.mptbm_extra_stops_list');

        if ($list.children('.mptbm_extra_stop_row').length >= mptbmExtraStopMaxRows($wrapper)) {
            return;
        }

        var template = $wrapper.find('#mptbm_extra_stop_row_template')[0];
        if (!template || !template.content) {
            return;
        }

        $list.append(template.content.cloneNode(true));
        mptbmExtraStopSetupAutocomplete($list.children('.mptbm_extra_stop_row').last());
        mptbmExtraStopToggleAddLink($wrapper);
    });

    $(document).on('click', '.mptbm_remove_extra_stop_row', function () {
        var $wrapper = $(this).closest('.mptbm_extra_stops_wrapper');
        var $row = $(this).closest('.mptbm_extra_stop_row');
        var input = $row.find('.mptbm_extra_stop_place_input')[0];
        var hadCoords = !!$row.find('.mptbm_extra_stop_coords').val();
        if (input && input._mptbmStopMarker && typeof mptbm_osm_map !== 'undefined' && mptbm_osm_map) {
            mptbm_osm_map.removeLayer(input._mptbmStopMarker);
        }
        if (input && input._mptbmStopResultsContainer && input._mptbmStopResultsContainer.parentNode) {
            input._mptbmStopResultsContainer.parentNode.removeChild(input._mptbmStopResultsContainer);
        }
        $row.remove();
        mptbmExtraStopToggleAddLink($wrapper);
        if (hadCoords) {
            mptbmExtraStopTriggerRecalculation();
        }
    });
})(jQuery);
