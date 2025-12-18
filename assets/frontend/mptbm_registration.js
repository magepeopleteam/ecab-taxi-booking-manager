let mptbm_map;
let mptbm_map_window;

// OpenStreetMap variables
var mptbm_osm_map = null;
var mptbm_osm_markers = [];
var mptbm_osm_route = null;
var mptbm_osm_start_marker = null;
var mptbm_osm_end_marker = null;

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
    errorDiv.style.marginTop = '5px';
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

// Add event listeners to clear errors when user starts typing
jQuery(document).ready(function ($) {
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

    if (start_place && end_place) {
        var directionsService = new google.maps.DirectionsService();
        var directionsRenderer = new google.maps.DirectionsRenderer();
        directionsRenderer.setMap(mptbm_map);

        var request = {
            origin: start_place,
            destination: end_place,
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
                    var distance = result.routes[0].legs[0].distance.value;
                    var kmOrMileElement = document.getElementById("mptbm_km_or_mile");
                    var kmOrMile = kmOrMileElement ? kmOrMileElement.value : 'km';
                    var distance_text = result.routes[0].legs[0].distance.text;
                    var duration = result.routes[0].legs[0].duration.value;
                    var duration_text = result.routes[0].legs[0].duration.text;

                    if (kmOrMile == 'mile') {
                        // Convert distance from kilometers to miles
                        var distanceInKilometers = distance / 1000;
                        var distanceInMiles = distanceInKilometers * 0.621371;
                        distance_text = distanceInMiles.toFixed(1) + ' miles';
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
function mptbm_map_area_init() {

    // Check if map container exists and is visible before initializing
    var mapContainer = document.getElementById("mptbm_map_area");
    if (!mapContainer) {
        console.warn("[Map Init] Map container #mptbm_map_area not found. Skipping map initialization.");
        return false;
    }

    // Check if the map container is visible (not hidden by CSS)
    var mapArea = document.querySelector('.mptbm_map_area');
    if (mapArea && mapArea.style.display === 'none') {
        console.warn("[Map Init] Map area is hidden. Skipping map initialization.");
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

function mptbm_init_osm_map() {

    if (typeof L === 'undefined') {
        return false;
    }

    // Check if map container exists
    var mapContainer = document.getElementById("mptbm_map_area");
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
        } catch (e) {
            console.log("[OSM] Error removing map:", e);
        }
    }


    // Get default coordinates from PHP or use fallback
    var defaultLat = (typeof mptbm_default_lat !== 'undefined') ? mptbm_default_lat : 40.7128;
    var defaultLng = (typeof mptbm_default_lng !== 'undefined') ? mptbm_default_lng : -74.0060;

    // Initialize OpenStreetMap with configured coordinates
    mptbm_osm_map = L.map('mptbm_map_area').setView([defaultLat, defaultLng], 10);

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
}

function mptbm_setup_osm_autocomplete(input, type) {
    // Check if autocomplete is already initialized on this input
    if (input.hasAttribute('data-osm-autocomplete-initialized')) {
        return;
    }


    var debounceTimer;
    var currentSearchQuery = '';
    var resultsContainer = document.createElement('div');
    resultsContainer.className = 'mptbm-osm-autocomplete';
    resultsContainer.setAttribute('data-autocomplete-type', type);
    resultsContainer.style.cssText = 'position: fixed; font-size:16px; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 99999 !important; display: none; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);';

    // Append to body to avoid parent overflow issues
    document.body.appendChild(resultsContainer);

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

    input.addEventListener('input', function (e) {
        clearTimeout(debounceTimer);
        var query = e.target.value.trim();

        if (query.length < 3) {
            resultsContainer.style.display = 'none';
            currentSearchQuery = '';
            return;
        }

        // Store the current query
        currentSearchQuery = query;

        debounceTimer = setTimeout(function () {
            positionDropdown();
            mptbm_search_osm_address(query, resultsContainer, input, type, currentSearchQuery);
        }, 300);
    });

    // Reposition on scroll or resize
    window.addEventListener('scroll', positionDropdown);
    window.addEventListener('resize', positionDropdown);

    // Hide results when clicking outside
    document.addEventListener('click', function (e) {
        if (e.target !== input && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
}

function mptbm_search_osm_address(query, container, input, type, expectedQuery) {
    container.innerHTML = '<div style="padding: 10px; text-align: center; color: #666;">Searching...</div>';
    container.style.display = 'block';

    // Use WordPress AJAX proxy
    var ajaxUrl = mptbm_ajax.ajax_url + '?action=mptbm_osm_search&nonce=' + mptbm_ajax.osm_nonce + '&q=' + encodeURIComponent(query);

    fetch(ajaxUrl)
        .then(response => {
            return response.json();
        })
        .then(response => {
            // Check if this response is still relevant (user hasn't typed more)
            var currentValue = input.value.trim();
            if (expectedQuery && currentValue !== expectedQuery) {
                return;
            }

            container.innerHTML = '';

            if (!response.success) {
                container.innerHTML = '<div style="padding: 10px; color: #f00;">Error: ' + response.data + '</div>';
                container.style.display = 'block';
                return;
            }

            if (!response.data || response.data.length === 0) {
                container.innerHTML = '<div style="padding: 10px; color: #666;">No results found</div>';
                container.style.display = 'block';
                return;
            }

            var data = response.data;

            data.forEach(function (result) {
                var item = document.createElement('div');
                item.style.cssText = 'padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; background-color: white;';
                item.textContent = result.display_name;

                item.addEventListener('click', function () {
                    input.value = result.display_name;
                    container.style.display = 'none';
                    mptbm_handle_osm_address_selection(result, type);
                });

                item.addEventListener('mouseenter', function () {
                    this.style.backgroundColor = '#f5f5f5';
                });

                item.addEventListener('mouseleave', function () {
                    this.style.backgroundColor = 'white';
                });

                container.appendChild(item);
            });

            // Ensure container is visible and positioned
            container.style.display = 'block';
        })
        .catch(error => {
            console.error('[OSM Search] Fetch error:', error);
            container.innerHTML = '<div style="padding: 10px; color: #f00;">Search failed. Please try again.</div>';
            container.style.display = 'block';
        });
}

function mptbm_handle_osm_address_selection(address, type) {
    var lat = parseFloat(address.lat);
    var lng = parseFloat(address.lon);

    // Remove existing marker for this type
    if (type === 'start' && mptbm_osm_start_marker) {
        mptbm_osm_map.removeLayer(mptbm_osm_start_marker);
    } else if (type === 'end' && mptbm_osm_end_marker) {
        mptbm_osm_map.removeLayer(mptbm_osm_end_marker);
    }

    // Create new marker
    var marker = L.marker([lat, lng]).addTo(mptbm_osm_map);
    marker.bindPopup(address.display_name);

    if (type === 'start') {
        mptbm_osm_start_marker = marker;
    } else if (type === 'end') {
        mptbm_osm_end_marker = marker;
    }

    // Calculate distance if both markers exist
    if (mptbm_osm_start_marker && mptbm_osm_end_marker) {
        mptbm_calculate_osm_distance();
    }

    // Fit map to show all markers
    var group = new L.featureGroup([mptbm_osm_start_marker, mptbm_osm_end_marker].filter(Boolean));
    if (group.getLayers().length > 0) {
        mptbm_osm_map.fitBounds(group.getBounds().pad(0.1));
    }
}

function mptbm_calculate_osm_distance() {
    if (!mptbm_osm_start_marker || !mptbm_osm_end_marker) return;

    var startLatLng = mptbm_osm_start_marker.getLatLng();
    var endLatLng = mptbm_osm_end_marker.getLatLng();


    // Get route from OSRM (Open Source Routing Machine)
    var osrmUrl = 'https://router.project-osrm.org/route/v1/driving/' +
        startLatLng.lng + ',' + startLatLng.lat + ';' +
        endLatLng.lng + ',' + endLatLng.lat +
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

            // Only sync dropoff with pickup if dropoff is hidden (hourly pricing with disabled dropoff)
            if (end_place && end_place.type === 'hidden') {
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

                    // Only sync dropoff with pickup if dropoff is hidden (hourly pricing with disabled dropoff)
                    if (endPlaceInput && endPlaceInput.type === 'hidden') {
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

            // Initialize Google Places autocomplete for dropoff location as well (independent of map visibility)
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
                    mptbm_set_cookie_distance_duration(
                        startInput ? startInput.value : '',
                        endPlaceInput ? endPlaceInput.value : ''
                    );
                });

                // Mark as initialized to prevent duplicate initialization
                endPlaceInput.setAttribute('data-autocomplete-initialized', 'true');
            }
        }

        // Initialize Google Places autocomplete on page load with a delay to ensure API is loaded
        setTimeout(function () {
            var mapType = document.getElementById('mptbm_map_type');

            if (mapType && mapType.value === 'openstreetmap') {
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
        } else if (!start_place.value || (start_place.tagName === 'SELECT' && start_place.options[start_place.selectedIndex].disabled)) {
            start_place.focus();
            // Show error message
            let startMsg = price_based === 'manual' ? 'Please select a pickup location' : 'Please enter a pickup location';
            showLocationError(start_place, startMsg);
        } else if (!end_place.value || (end_place.tagName === 'SELECT' && end_place.options[end_place.selectedIndex].disabled)) {
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

            dLoader(parent.find(".tabsContentNext"));
            mptbm_content_refresh(parent);
            if (price_based !== "manual") {
                mptbm_set_cookie_distance_duration(start_place.value, end_place.value);
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
            if (price_based !== 'manual') {

                $.when(
                    getCoordinatesAsync(start_place.value),
                    getCoordinatesAsync(end_place.value)
                ).done(function (startCoordinates, endCoordinates) {
                    // Validate that geocoding was successful
                    if (!startCoordinates || startCoordinates === null) {
                        dLoaderRemove(parent.find(".tabsContentNext"));
                        showLocationError(start_place, 'Invalid pickup location. Please select a valid address.');
                        start_place.focus();
                        return;
                    }

                    if (!endCoordinates || endCoordinates === null) {
                        dLoaderRemove(parent.find(".tabsContentNext"));
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
                                    mptbm_max_bag: parent.find('#mptbm_max_bag').val(),
                                    mptbm_original_price_base: mptbm_original_price_base,
                                    mptbm_distance: parent.find('input[name="mptbm_hidden_distance"]').val(),
                                    mptbm_duration: parent.find('input[name="mptbm_hidden_duration"]').val(),
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
                                    mptbm_max_bag: parent.find('#mptbm_max_bag').val(),
                                    mptbm_original_price_base: mptbm_original_price_base,
                                    mptbm_distance: parent.find('input[name="mptbm_hidden_distance"]').val(),
                                    mptbm_duration: parent.find('input[name="mptbm_hidden_duration"]').val(),
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
                                mptbm_max_bag: parent.find('#mptbm_max_bag').val(),
                                mptbm_original_price_base: mptbm_original_price_base,
                                mptbm_distance: parent.find('input[name="mptbm_hidden_distance"]').val(),
                                mptbm_duration: parent.find('input[name="mptbm_hidden_duration"]').val(),
                                mptbm_distance_text: parent.find('input[name="mptbm_hidden_distance_text"]').val(),
                                mptbm_duration_text: parent.find('input[name="mptbm_hidden_duration_text"]').val(),
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
                                mptbm_max_bag: parent.find('#mptbm_max_bag').val(),
                                mptbm_original_price_base: mptbm_original_price_base,
                                mptbm_distance: parent.find('input[name="mptbm_hidden_distance"]').val(),
                                mptbm_duration: parent.find('input[name="mptbm_hidden_duration"]').val(),
                                mptbm_distance_text: parent.find('input[name="mptbm_hidden_distance_text"]').val(),
                                mptbm_duration_text: parent.find('input[name="mptbm_hidden_duration_text"]').val(),
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
jQuery(document).ready(function ($) {

    // Test if info buttons exist
    setTimeout(function () {
        var infoButtons = $('.mptbm-info-button');
        if (infoButtons.length > 0) {
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
                $('.mptbm_booking_item').removeClass('selected');
                $this.closest('.mptbm_booking_item').addClass('selected');


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

            // DEBUG: Log data being sent to add to cart
            console.log("MPTBM DEBUG: Add to Cart Data", {
                start: start_place,
                end: end_place,
                distance: parent.find('input[name="mptbm_hidden_distance"]').val(),
                distance_text: parent.find('input[name="mptbm_hidden_distance_text"]').val(),
                post_id: post_id
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
                    mptbm_max_bag: parent.find('#mptbm_max_bag').val(),
                    mptbm_original_price_base: mptbm_original_price_base,
                    mptbm_distance: parent.find('input[name="mptbm_hidden_distance"]').val(),
                    mptbm_duration: parent.find('input[name="mptbm_hidden_duration"]').val(),
                    mptbm_distance_text: parent.find('input[name="mptbm_hidden_distance_text"]').val(),
                    mptbm_duration_text: parent.find('input[name="mptbm_hidden_duration_text"]').val(),
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

                // Create a new loading overlay with CSS spinner animation
                var loadingOverlay = $('<div class="mptbm-loading-overlay" style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; z-index: 9999 !important; padding: 30px !important; text-align: center !important;"><div class="mptbm-spinner"></div></div>');

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
                    targetTabContainer.css('display', 'block');
                }

                return; // Exit the click handler early
            }

            // Remove existing template before inserting the new one
            $('.mptb-tab-content').empty().removeClass('current');

            // Small delay to ensure loading GIF is rendered before AJAX starts (only when loading new content)
            setTimeout(function () {
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


                        // Check if the tab container exists
                        var tabContainer = $("#" + tab_id);
                        if (tabContainer.length === 0) {

                            // Try to create the tab container if it doesn't exist
                            var tabContainerParent = $('.mptb-tab-container');
                            if (tabContainerParent.length > 0) {
                                var newTabContainer = $('<div id="' + tab_id + '" class="mptb-tab-content"></div>');
                                tabContainerParent.append(newTabContainer);
                                tabContainer = newTabContainer;
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
                            tabContainer.css('display', 'block');
                        }

                        // Hide loading GIF after content is loaded with a minimum display time

                        // Add a minimum display time of 1000ms to ensure the loading GIF is visible
                        // This gives more time for the user to see the loading state
                        setTimeout(function () {
                            // Remove the loading overlay
                            $('.mptbm-loading-overlay').remove();
                        }, 1000);

                        // Add a small delay to ensure DOM is fully updated before initializing map
                        setTimeout(function () {
                            // Only initialize map if the current tab should have a map
                            var currentTab = $('.mptb-tabs li.current').attr('mptbm-data-tab');
                            var mapEnabled = $('.mptb-tabs li.current').attr('mptbm-data-map');

                            // Don't initialize map for manual/flat-rate tab or if map is disabled
                            if (currentTab !== 'flat-rate' && mapEnabled === 'yes') {
                                // **Reinitialize the map-related elements after template loads**
                                mptbm_map_area_init();
                            }

                            // Reinitialize autocomplete based on map type
                            var mapType = document.getElementById('mptbm_map_type');

                            if (mapType && mapType.value === 'openstreetmap') {
                            } else {
                                initializeGooglePlacesAutocomplete();
                            }
                        }, 100);
                    },
                    error: function (response) {
                        // Hide loading GIF on error with minimum display time
                        setTimeout(function () {
                            // Remove the loading overlay
                            $('.mptbm-loading-overlay').remove();
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
        var $customWrapper = $('<div class="mptbm-custom-select-wrapper" style="position: fixed !important; z-index: 9999 !important; background: white !important; border: 1px solid #ddd !important; border-radius: 4px !important; box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;"></div>');

        // Create search input
        var $searchInput = $('<input type="text" class="mptbm-custom-search-input" placeholder="Search locations..." style="width: 100% !important; padding: 8px !important; border: none !important; border-bottom: 1px solid #eee !important; border-radius: 4px 4px 0 0 !important; font-size: 14px !important; box-sizing: border-box !important; background: #F5F6F8 !important; color: #222222 !important; font-weight: 400 !important; outline: none !important;" />');

        // Create options container
        var $optionsContainer = $('<div class="mptbm-custom-options" style="max-height: 200px !important; overflow-y: auto !important; background: white !important;"></div>');

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
            optionsHtml += '<div class="mptbm-custom-option ' + selectedClass + '" data-value="' + optionValue + '" style="padding: 8px !important; cursor: pointer !important; border-bottom: 1px solid #f5f5f5 !important; font-size: 14px !important; color: #222222 !important;">' + optionText + '</div>';
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

    // Handle extra info toggle functionality
    $(document).on('click', '.mptbm-info-button', function (e) {
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
            $content.slideToggle(300, function () {
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
    $(document).on('click', function (e) {
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