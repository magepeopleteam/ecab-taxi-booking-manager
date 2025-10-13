var mapOptions;
var map;

var coordinates = [];
let new_coordinates = [];
let lastElemen;
var geoLocationOne;
var formattedAddress;
function InitMapOne(geoLocationOne) {
    var mapCanvas1 = document.getElementById('mptbm-map-canvas-one');
    if (mapCanvas1) {
        if(geoLocationOne===undefined){
            geoLocationOne = new google.maps.LatLng(23.8103, 90.4125);
        }
        
        mapOptions = {
            zoom: 10,
            center: geoLocationOne,
            mapTypeId: google.maps.MapTypeId.RoadMap
        };
        map = new google.maps.Map(mapCanvas1, mapOptions);

        var all_overlays = [];
        var selectedShape;
        var drawingManager = new google.maps.drawing.DrawingManager({
            drawingControlOptions: {
                position: google.maps.ControlPosition.TOP_CENTER,
                drawingModes: [
                    google.maps.drawing.OverlayType.POLYGON,
                ]
            },
            circleOptions: {
                fillColor: '#ffff00',
                fillOpacity: 0.2,
                strokeWeight: 3,
                clickable: false,
                editable: true,
                zIndex: 1
            },
            polygonOptions: {
                clickable: true,
                draggable: false,
                editable: true,
                fillColor: '#ADFF2F',
                fillOpacity: 0.5
            },
            rectangleOptions: {
                clickable: true,
                draggable: true,
                editable: true,
                fillColor: '#ffff00',
                fillOpacity: 0.5
            }
        });

        function clearSelection() {
            if (selectedShape) {
                selectedShape.setEditable(false);
                selectedShape = null;
            }
        }

        function stopDrawing() {
            drawingManager.setMap(null);
        }

        function setSelection(shape) {
            clearSelection();
            stopDrawing();
            selectedShape = shape;
            shape.setEditable(true);
        }

        function deleteSelectedShape() {
            if (selectedShape) {
                selectedShape.setMap(null);
                drawingManager.setMap(map);
                coordinates.splice(0, coordinates.length);
            }
        }

        function CenterControl(controlDiv, map) {
            var controlUI = document.createElement('div');
            controlUI.style.backgroundColor = '#fff';
            controlUI.style.border = '2px solid #fff';
            controlUI.style.borderRadius = '3px';
            controlUI.style.boxShadow = '0 2px 6px rgba(0,0,0,.3)';
            controlUI.style.cursor = 'pointer';
            controlUI.style.marginBottom = '22px';
            controlUI.style.textAlign = 'center';
            controlUI.title = 'Select to delete the shape';
            controlDiv.appendChild(controlUI);

            var controlText = document.createElement('div');
            controlText.style.color = 'rgb(25,25,25)';
            controlText.style.fontFamily = 'Roboto,Arial,sans-serif';
            controlText.style.fontSize = '16px';
            controlText.style.lineHeight = '38px';
            controlText.style.paddingLeft = '5px';
            controlText.style.paddingRight = '5px';
            controlText.innerHTML = 'Delete Selected Area';
            controlUI.appendChild(controlText);

            controlUI.addEventListener('click', function () {
                deleteSelectedShape();
            });
        }

        drawingManager.setMap(map);

        var getPolygonCoords = function (newShape) {
            coordinates.splice(0, coordinates.length);
            var len = newShape.getPath().getLength();
            for (var i = 0; i < len; i++) {
                coordinates.push(newShape.getPath().getAt(i).toUrlValue(6));
            }
            document.getElementById('mptbm-starting-location-one-hidden').value = formattedAddress;
            document.getElementById('mptbm-coordinates-one').value = coordinates;
        };

        google.maps.event.addListener(drawingManager, 'polygoncomplete', function (event) {
            event.getPath().getLength();
            google.maps.event.addListener(event, "dragend", getPolygonCoords(event));
            google.maps.event.addListener(event.getPath(), 'insert_at', function () {
                getPolygonCoords(event);
            });
            google.maps.event.addListener(event.getPath(), 'set_at', function () {
                getPolygonCoords(event);
            });
        });

        google.maps.event.addListener(drawingManager, 'overlaycomplete', function (event) {
            all_overlays.push(event);
            if (event.type !== google.maps.drawing.OverlayType.MARKER) {
                drawingManager.setDrawingMode(null);
                var newShape = event.overlay;
                newShape.type = event.type;
                google.maps.event.addListener(newShape, 'click', function () {
                    setSelection(newShape);
                });
                setSelection(newShape);
            }
        });

        var centerControlDiv = document.createElement('div');
        var centerControl = new CenterControl(centerControlDiv, map);
        centerControlDiv.index = 1;
        map.controls[google.maps.ControlPosition.BOTTOM_CENTER].push(centerControlDiv);
    }
}
function InitMapTwo(geoLocationOne) {
    var mapCanvas1 = document.getElementById('mptbm-map-canvas-two');
    if (mapCanvas1) {
        if(geoLocationOne===undefined){
            geoLocationOne = new google.maps.LatLng(23.8103, 90.4125);
        }
        
        mapOptions = {
            zoom: 10,
            center: geoLocationOne,
            mapTypeId: google.maps.MapTypeId.RoadMap
        };
        map = new google.maps.Map(mapCanvas1, mapOptions);

        var all_overlays = [];
        var selectedShape;
        var drawingManager = new google.maps.drawing.DrawingManager({
            drawingControlOptions: {
                position: google.maps.ControlPosition.TOP_CENTER,
                drawingModes: [
                    google.maps.drawing.OverlayType.POLYGON,
                ]
            },
            circleOptions: {
                fillColor: '#ffff00',
                fillOpacity: 0.2,
                strokeWeight: 3,
                clickable: false,
                editable: true,
                zIndex: 1
            },
            polygonOptions: {
                clickable: true,
                draggable: false,
                editable: true,
                fillColor: '#ADFF2F',
                fillOpacity: 0.5
            },
            rectangleOptions: {
                clickable: true,
                draggable: true,
                editable: true,
                fillColor: '#ffff00',
                fillOpacity: 0.5
            }
        });

        function clearSelection() {
            if (selectedShape) {
                selectedShape.setEditable(false);
                selectedShape = null;
            }
        }

        function stopDrawing() {
            drawingManager.setMap(null);
        }

        function setSelection(shape) {
            clearSelection();
            stopDrawing();
            selectedShape = shape;
            shape.setEditable(true);
        }

        function deleteSelectedShape() {
            if (selectedShape) {
                selectedShape.setMap(null);
                drawingManager.setMap(map);
                coordinates.splice(0, coordinates.length);
            }
        }

        function CenterControl(controlDiv, map) {
            var controlUI = document.createElement('div');
            controlUI.style.backgroundColor = '#fff';
            controlUI.style.border = '2px solid #fff';
            controlUI.style.borderRadius = '3px';
            controlUI.style.boxShadow = '0 2px 6px rgba(0,0,0,.3)';
            controlUI.style.cursor = 'pointer';
            controlUI.style.marginBottom = '22px';
            controlUI.style.textAlign = 'center';
            controlUI.title = 'Select to delete the shape';
            controlDiv.appendChild(controlUI);

            var controlText = document.createElement('div');
            controlText.style.color = 'rgb(25,25,25)';
            controlText.style.fontFamily = 'Roboto,Arial,sans-serif';
            controlText.style.fontSize = '16px';
            controlText.style.lineHeight = '38px';
            controlText.style.paddingLeft = '5px';
            controlText.style.paddingRight = '5px';
            controlText.innerHTML = 'Delete Selected Area';
            controlUI.appendChild(controlText);

            controlUI.addEventListener('click', function () {
                deleteSelectedShape();
            });
        }

        drawingManager.setMap(map);

        var getPolygonCoords = function (newShape) {
            coordinates.splice(0, coordinates.length);
            var len = newShape.getPath().getLength();
            for (var i = 0; i < len; i++) {
                coordinates.push(newShape.getPath().getAt(i).toUrlValue(6));
            }
            document.getElementById('mptbm-starting-location-two-hidden').value = formattedAddress;
            document.getElementById('mptbm-coordinates-two').value = coordinates;
        };

        google.maps.event.addListener(drawingManager, 'polygoncomplete', function (event) {
            event.getPath().getLength();
            google.maps.event.addListener(event, "dragend", getPolygonCoords(event));
            google.maps.event.addListener(event.getPath(), 'insert_at', function () {
                getPolygonCoords(event);
            });
            google.maps.event.addListener(event.getPath(), 'set_at', function () {
                getPolygonCoords(event);
            });
        });

        google.maps.event.addListener(drawingManager, 'overlaycomplete', function (event) {
            all_overlays.push(event);
            if (event.type !== google.maps.drawing.OverlayType.MARKER) {
                drawingManager.setDrawingMode(null);
                var newShape = event.overlay;
                newShape.type = event.type;
                google.maps.event.addListener(newShape, 'click', function () {
                    setSelection(newShape);
                });
                setSelection(newShape);
            }
        });

        var centerControlDiv = document.createElement('div');
        var centerControl = new CenterControl(centerControlDiv, map);
        centerControlDiv.index = 1;
        map.controls[google.maps.ControlPosition.BOTTOM_CENTER].push(centerControlDiv);
    }
}
function InitMapFixed(geoLocationOne) {
    var mapCanvas3 = document.getElementById('mptbm-map-canvas-three');
    if (mapCanvas3) {
        if(geoLocationOne===undefined){
            geoLocationOne = new google.maps.LatLng(23.8103, 90.4125);
        }
        
        mapOptions = {
            zoom: 10,
            center: geoLocationOne,
            mapTypeId: google.maps.MapTypeId.RoadMap
        };
        map = new google.maps.Map(mapCanvas3, mapOptions);

        var all_overlays = [];
        var selectedShape;
        var drawingManager = new google.maps.drawing.DrawingManager({
            drawingControlOptions: {
                position: google.maps.ControlPosition.TOP_CENTER,
                drawingModes: [
                    google.maps.drawing.OverlayType.POLYGON,
                ]
            },
            circleOptions: {
                fillColor: '#ffff00',
                fillOpacity: 0.2,
                strokeWeight: 3,
                clickable: false,
                editable: true,
                zIndex: 1
            },
            polygonOptions: {
                clickable: true,
                draggable: false,
                editable: true,
                fillColor: '#ADFF2F',
                fillOpacity: 0.5
            },
            rectangleOptions: {
                clickable: true,
                draggable: true,
                editable: true,
                fillColor: '#ffff00',
                fillOpacity: 0.5
            }
        });

        function clearSelection() {
            if (selectedShape) {
                selectedShape.setEditable(false);
                selectedShape = null;
            }
        }

        function stopDrawing() {
            drawingManager.setMap(null);
        }

        function setSelection(shape) {
            clearSelection();
            stopDrawing();
            selectedShape = shape;
            shape.setEditable(true);
        }

        function deleteSelectedShape() {
            if (selectedShape) {
                selectedShape.setMap(null);
                drawingManager.setMap(map);
                coordinates.splice(0, coordinates.length);
            }
        }

        function CenterControl(controlDiv, map) {
            var controlUI = document.createElement('div');
            controlUI.style.backgroundColor = '#fff';
            controlUI.style.border = '2px solid #fff';
            controlUI.style.borderRadius = '3px';
            controlUI.style.boxShadow = '0 2px 6px rgba(0,0,0,.3)';
            controlUI.style.cursor = 'pointer';
            controlUI.style.marginBottom = '22px';
            controlUI.style.textAlign = 'center';
            controlUI.title = 'Select to delete the shape';
            controlDiv.appendChild(controlUI);

            var controlText = document.createElement('div');
            controlText.style.color = 'rgb(25,25,25)';
            controlText.style.fontFamily = 'Roboto,Arial,sans-serif';
            controlText.style.fontSize = '16px';
            controlText.style.lineHeight = '38px';
            controlText.style.paddingLeft = '5px';
            controlText.style.paddingRight = '5px';
            controlText.innerHTML = 'Delete Selected Area';
            controlUI.appendChild(controlText);

            controlUI.addEventListener('click', function () {
                deleteSelectedShape();
            });
        }

        drawingManager.setMap(map);

        var getPolygonCoords = function (newShape) {
            coordinates.splice(0, coordinates.length);
            var len = newShape.getPath().getLength();
            for (var i = 0; i < len; i++) {
                coordinates.push(newShape.getPath().getAt(i).toUrlValue(6));
            }
            document.getElementById('mptbm-starting-location-three-hidden').value = formattedAddress;
            document.getElementById('mptbm-coordinates-three').value = coordinates;
        };

        google.maps.event.addListener(drawingManager, 'polygoncomplete', function (event) {
            event.getPath().getLength();
            google.maps.event.addListener(event, "dragend", getPolygonCoords(event));
            google.maps.event.addListener(event.getPath(), 'insert_at', function () {
                getPolygonCoords(event);
            });
            google.maps.event.addListener(event.getPath(), 'set_at', function () {
                getPolygonCoords(geoLocationOne);
            });
        });

        google.maps.event.addListener(drawingManager, 'overlaycomplete', function (event) {
            all_overlays.push(event);
            if (event.type !== google.maps.drawing.OverlayType.MARKER) {
                drawingManager.setDrawingMode(null);
                var newShape = event.overlay;
                newShape.type = event.type;
                google.maps.event.addListener(newShape, 'click', function () {
                    setSelection(newShape);
                });
                setSelection(newShape);
            }
        });

        var centerControlDiv = document.createElement('div');
        var centerControl = new CenterControl(centerControlDiv, map);
        centerControlDiv.index = 1;
        map.controls[google.maps.ControlPosition.BOTTOM_CENTER].push(centerControlDiv);
    }
}

// Map initialization is now handled in the PHP template
// based on whether coordinates are saved or not.
// Do not auto-initialize here to avoid conflicts.



function iniSavedtMap(coordinates,mapCanvasId,mapAppendId) {

    var all_overlays = [];
    var selectedShape;
    drawingManager = new google.maps.drawing.DrawingManager({
        drawingControlOptions: {
            position: google.maps.ControlPosition.TOP_CENTER,
            drawingModes: [
                google.maps.drawing.OverlayType.POLYGON,
            ]
        },
        polygonOptions: {
            clickable: true,
            draggable: false,
            editable: true,
            fillColor: '#ADFF2F', // Green fill color
            fillOpacity: 0.5
        }
    });

    google.maps.event.addListener(drawingManager, 'polygoncomplete', function(event) {
        event.getPath().getLength();
        google.maps.event.addListener(event, "dragend", getPolygonCoords(event));
        google.maps.event.addListener(event.getPath(), 'insert_at', function() {
            getPolygonCoords(event);
        });
        google.maps.event.addListener(event.getPath(), 'set_at', function() {
            getPolygonCoords(geoLocationOne);
        });

    });
    google.maps.event.addListener(drawingManager, 'overlaycomplete', function(event) {
        drawingManager.setOptions({
            drawingControl: false
        });
        all_overlays.push(event);
        if (event.type !== google.maps.drawing.OverlayType.MARKER) {
            drawingManager.setDrawingMode(null);
            var newShape = event.overlay;
            newShape.type = event.type;
            google.maps.event.addListener(newShape, 'click', function() {
                setSelection(newShape);
            });
            setSelection(newShape);
        }
    });

    function clearSelection() {
        if (selectedShape) {
            selectedShape.setEditable(false);
            selectedShape = null;
        }
    }

    function setSelection(shape) {
        clearSelection();
        stopDrawing();
        selectedShape = shape;
        shape.setEditable(true);
    }
    var getPolygonCoords = function(newShape) {
        coordinates.splice(0, coordinates.length);
        var len = newShape.getPath().getLength();
        for (var i = 0; i < len; i++) {
            coordinates.push(newShape.getPath().getAt(i).toUrlValue(6));
        }
        if(mapAppendId != null){
            document.getElementById(mapAppendId).value = coordinates;
        }
    };

    // Create map centered at the first coordinate
    var map = new google.maps.Map(document.getElementById(mapCanvasId), {
        center: {
            lat: parseFloat(coordinates[0]),
            lng: parseFloat(coordinates[1])
        },
        zoom: 10 // Set zoom level to 12
    });

    // Create an array to store LatLng objects
    var path = [];
    for (var i = 0; i < coordinates.length; i += 2) {
        var latLng = new google.maps.LatLng(parseFloat(coordinates[i]), parseFloat(coordinates[i + 1]));
        path.push(latLng);
    }

    // Construct the polygon
    var polygon = new google.maps.Polygon({
        paths: path,
        strokeColor: "#000000", // Change to black
        strokeOpacity: 0.8,
        strokeWeight: 4, // Increase the thickness
        fillColor: "#ADFF2F", // Make selected area green instead of red
        fillOpacity: 0.5, // Adjust fill opacity
        editable: false // Make the polygon editable
    });

    // Set polygon on the map
    polygon.setMap(map);

    // Function to calculate the center of the polygon
    function calculateCenter() {
        var bounds = new google.maps.LatLngBounds();
        path.forEach(function(latLng) {
            bounds.extend(latLng);
        });
        return bounds.getCenter();
    }

    // Center map on the calculated center of the polygon
    map.setCenter(calculateCenter());

    // Delete selected shape function
    function deleteSelectedShape() {

        if (selectedShape != undefined) {
            selectedShape.setMap(null);
            drawingManager.setMap(map);
            coordinates.splice(0, coordinates.length);
        }
        drawingManager.setOptions({
            drawingControl: true
        });
        if (polygon) {
            polygon.setMap(null);
            drawingManager.setMap(map);
            coordinates.splice(0, coordinates.length);
        }

    }
    function stopDrawing() {
        drawingManager.setMap(null);
    }
    // Add delete button control
    var deleteControlDiv = document.createElement('div');
    var deleteControl = new CenterControl(deleteControlDiv, map);

    if(mapAppendId != null){
        map.controls[google.maps.ControlPosition.BOTTOM_CENTER].push(deleteControlDiv);
    }

    function CenterControl(controlDiv, map) {
        // Create the button container
        var controlUI = document.createElement('div');
        controlUI.style.backgroundColor = '#fff';
        controlUI.style.border = '2px solid #fff';
        controlUI.style.borderRadius = '3px';
        controlUI.style.boxShadow = '0 2px 6px rgba(0,0,0,.3)';
        controlUI.style.cursor = 'pointer';
        controlUI.style.textAlign = 'center';
        controlUI.title = 'Select to delete the shape';
        controlDiv.appendChild(controlUI);

        // Create the text inside the button
        var controlText = document.createElement('div');
        controlText.style.color = 'rgb(25,25,25)';
        controlText.style.fontFamily = 'Roboto,Arial,sans-serif';
        controlText.style.fontSize = '16px';
        controlText.style.lineHeight = '38px';
        controlText.style.paddingLeft = '5px';
        controlText.style.paddingRight = '5px';
        controlText.innerHTML = 'Delete Selected Area';
        controlUI.appendChild(controlText);

        // Add click event listener to the button
        controlUI.addEventListener('click', function() {
            deleteSelectedShape();
        });

        // Add some margin
        controlDiv.style.marginBottom = '10px'; // Adjust margin as needed

        // Center the button
        controlDiv.style.padding = '5px';
        controlDiv.style.width = 'fit-content';
    }
}

(function ($) {

    $(document).ready(function () {
        
        // Initialize Google Places autocomplete instances only once
        function initializeAutocomplete(inputId, mapFunction) {
            var input = document.getElementById(inputId);
            if (!input || input.hasAttribute('data-autocomplete-initialized')) {
                return;
            }
            
            var autocomplete = new google.maps.places.Autocomplete(input, { types: ['geocode'] });
            
            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();
                formattedAddress = place.formatted_address;
                if (place.geometry) {
                    var location = place.geometry.location;
                    mapFunction(location, formattedAddress);
                }
            });
            
            // Mark as initialized to prevent duplicate initialization
            input.setAttribute('data-autocomplete-initialized', 'true');
        }
        
        // Initialize autocomplete for all three location inputs
        // Only initialize Google Maps autocomplete if Google Maps API is loaded
        // OpenStreetMap autocomplete is handled in the OSM functions
        if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
            initializeAutocomplete('mptbm-starting-location-one', InitMapOne);
            initializeAutocomplete('mptbm-starting-location-two', InitMapTwo);
            initializeAutocomplete('mptbm-starting-location-three', InitMapFixed);
        }
        
    });
    
})(jQuery);

// ==========================================
// OpenStreetMap Functions for Operation Areas
// ==========================================

// Global OSM variables
var osmMapOne, osmMapTwo, osmMapFixed;
var osmDrawLayerOne, osmDrawLayerTwo, osmDrawLayerFixed;
var osmDrawControlOne, osmDrawControlTwo, osmDrawControlFixed;

// Initialize OSM Map One (Intercity - Location 1)
function InitOSMMapOne(geoLocation) {
    var mapCanvas1 = document.getElementById('mptbm-map-canvas-one');
    if (!mapCanvas1) return;
    
    // Check if already initialized and clean up
    if (osmMapOne) {
        try {
            osmMapOne.remove();
        } catch (e) {
            console.log('[OSM] Error removing map:', e);
        }
        osmMapOne = null;
    }
    
    // Clear Leaflet's internal reference on the container
    mapCanvas1._leaflet_id = null;
    mapCanvas1.innerHTML = '';
    
    // Default location: Dhaka
    var defaultLat = geoLocation ? geoLocation.lat : 23.8103;
    var defaultLng = geoLocation ? geoLocation.lng : 90.4125;
    
    // Initialize map
    osmMapOne = L.map('mptbm-map-canvas-one').setView([defaultLat, defaultLng], 10);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(osmMapOne);
    
    // Feature group to store drawn items
    osmDrawLayerOne = new L.FeatureGroup();
    osmMapOne.addLayer(osmDrawLayerOne);
    
    // Initialize draw control
    osmDrawControlOne = new L.Control.Draw({
        position: 'topright',
        draw: {
            polygon: {
                allowIntersection: false,
                drawError: {
                    color: '#e1e100',
                    message: '<strong>Error:</strong> Shape edges cannot cross!'
                },
                shapeOptions: {
                    color: '#ADFF2F',
                    fillOpacity: 0.5
                }
            },
            polyline: false,
            circle: false,
            rectangle: false,
            marker: false,
            circlemarker: false
        },
        edit: {
            featureGroup: osmDrawLayerOne,
            remove: true
        }
    });
    osmMapOne.addControl(osmDrawControlOne);
    
    // Handle polygon creation
    osmMapOne.on(L.Draw.Event.CREATED, function (e) {
        osmDrawLayerOne.clearLayers();
        var layer = e.layer;
        osmDrawLayerOne.addLayer(layer);
        saveOSMPolygonCoordinates(layer, 'mptbm-coordinates-one');
    });
    
    // Handle polygon edit
    osmMapOne.on(L.Draw.Event.EDITED, function (e) {
        var layers = e.layers;
        layers.eachLayer(function (layer) {
            saveOSMPolygonCoordinates(layer, 'mptbm-coordinates-one');
        });
    });
    
    // Handle polygon delete
    osmMapOne.on(L.Draw.Event.DELETED, function (e) {
        document.getElementById('mptbm-coordinates-one').value = '';
    });
    
    // Setup autocomplete for location search
    setupOSMLocationSearch('mptbm-starting-location-one', osmMapOne, function(lat, lng, displayName) {
        osmMapOne.setView([lat, lng], 13);
        document.getElementById('mptbm-starting-location-one-hidden').value = displayName;
    });
    
    // Force Leaflet to recalculate map size (fixes partial rendering)
    // Longer delay to ensure container is fully visible
    setTimeout(function() {
        osmMapOne.invalidateSize();
    }, 500);
}

// Initialize OSM Map Two (Intercity - Location 2)
function InitOSMMapTwo(geoLocation) {
    var mapCanvas2 = document.getElementById('mptbm-map-canvas-two');
    if (!mapCanvas2) return;
    
    // Check if already initialized and clean up
    if (osmMapTwo) {
        try {
            osmMapTwo.remove();
        } catch (e) {
            console.log('[OSM] Error removing map:', e);
        }
        osmMapTwo = null;
    }
    
    // Clear Leaflet's internal reference on the container
    mapCanvas2._leaflet_id = null;
    mapCanvas2.innerHTML = '';
    
    var defaultLat = geoLocation ? geoLocation.lat : 23.8103;
    var defaultLng = geoLocation ? geoLocation.lng : 90.4125;
    
    osmMapTwo = L.map('mptbm-map-canvas-two').setView([defaultLat, defaultLng], 10);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(osmMapTwo);
    
    osmDrawLayerTwo = new L.FeatureGroup();
    osmMapTwo.addLayer(osmDrawLayerTwo);
    
    osmDrawControlTwo = new L.Control.Draw({
        position: 'topright',
        draw: {
            polygon: {
                allowIntersection: false,
                drawError: {
                    color: '#e1e100',
                    message: '<strong>Error:</strong> Shape edges cannot cross!'
                },
                shapeOptions: {
                    color: '#ADFF2F',
                    fillOpacity: 0.5
                }
            },
            polyline: false,
            circle: false,
            rectangle: false,
            marker: false,
            circlemarker: false
        },
        edit: {
            featureGroup: osmDrawLayerTwo,
            remove: true
        }
    });
    osmMapTwo.addControl(osmDrawControlTwo);
    
    osmMapTwo.on(L.Draw.Event.CREATED, function (e) {
        osmDrawLayerTwo.clearLayers();
        var layer = e.layer;
        osmDrawLayerTwo.addLayer(layer);
        saveOSMPolygonCoordinates(layer, 'mptbm-coordinates-two');
    });
    
    osmMapTwo.on(L.Draw.Event.EDITED, function (e) {
        var layers = e.layers;
        layers.eachLayer(function (layer) {
            saveOSMPolygonCoordinates(layer, 'mptbm-coordinates-two');
        });
    });
    
    osmMapTwo.on(L.Draw.Event.DELETED, function (e) {
        document.getElementById('mptbm-coordinates-two').value = '';
    });
    
    setupOSMLocationSearch('mptbm-starting-location-two', osmMapTwo, function(lat, lng, displayName) {
        osmMapTwo.setView([lat, lng], 13);
        document.getElementById('mptbm-starting-location-two-hidden').value = displayName;
    });
    
    // Force Leaflet to recalculate map size (fixes partial rendering)
    // Longer delay to ensure container is fully visible
    setTimeout(function() {
        osmMapTwo.invalidateSize();
    }, 500);
}

// Initialize OSM Map Fixed (Single Operation Area)
function InitOSMMapFixed(geoLocation, formattedAddress) {
    var mapCanvas3 = document.getElementById('mptbm-map-canvas-three');
    if (!mapCanvas3) return;
    
    // Check if already initialized and clean up
    if (osmMapFixed) {
        try {
            osmMapFixed.remove();
        } catch (e) {
            console.log('[OSM] Error removing map:', e);
        }
        osmMapFixed = null;
    }
    
    // Clear Leaflet's internal reference on the container
    mapCanvas3._leaflet_id = null;
    mapCanvas3.innerHTML = '';
    
    var defaultLat = geoLocation ? geoLocation.lat : 23.8103;
    var defaultLng = geoLocation ? geoLocation.lng : 90.4125;
    
    osmMapFixed = L.map('mptbm-map-canvas-three').setView([defaultLat, defaultLng], 10);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(osmMapFixed);
    
    osmDrawLayerFixed = new L.FeatureGroup();
    osmMapFixed.addLayer(osmDrawLayerFixed);
    
    osmDrawControlFixed = new L.Control.Draw({
        position: 'topright',
        draw: {
            polygon: {
                allowIntersection: false,
                drawError: {
                    color: '#e1e100',
                    message: '<strong>Error:</strong> Shape edges cannot cross!'
                },
                shapeOptions: {
                    color: '#ADFF2F',
                    fillOpacity: 0.5
                }
            },
            polyline: false,
            circle: false,
            rectangle: false,
            marker: false,
            circlemarker: false
        },
        edit: {
            featureGroup: osmDrawLayerFixed,
            remove: true
        }
    });
    osmMapFixed.addControl(osmDrawControlFixed);
    
    osmMapFixed.on(L.Draw.Event.CREATED, function (e) {
        osmDrawLayerFixed.clearLayers();
        var layer = e.layer;
        osmDrawLayerFixed.addLayer(layer);
        saveOSMPolygonCoordinates(layer, 'mptbm-coordinates-three');
    });
    
    osmMapFixed.on(L.Draw.Event.EDITED, function (e) {
        var layers = e.layers;
        layers.eachLayer(function (layer) {
            saveOSMPolygonCoordinates(layer, 'mptbm-coordinates-three');
        });
    });
    
    osmMapFixed.on(L.Draw.Event.DELETED, function (e) {
        document.getElementById('mptbm-coordinates-three').value = '';
    });
    
    setupOSMLocationSearch('mptbm-starting-location-three', osmMapFixed, function(lat, lng, displayName) {
        osmMapFixed.setView([lat, lng], 13);
        document.getElementById('mptbm-starting-location-three-hidden').value = displayName;
    });
    
    // Force Leaflet to recalculate map size (fixes partial rendering)
    // Longer delay to ensure container is fully visible
    setTimeout(function() {
        osmMapFixed.invalidateSize();
    }, 500);
}

// Save polygon coordinates to hidden input
function saveOSMPolygonCoordinates(layer, inputId) {
    var coordinates = [];
    var latlngs = layer.getLatLngs()[0]; // Get first ring of polygon
    
    latlngs.forEach(function(latlng) {
        coordinates.push(latlng.lat.toFixed(6));
        coordinates.push(latlng.lng.toFixed(6));
    });
    
    var inputElement = document.getElementById(inputId);
    if (inputElement) {
        inputElement.value = coordinates.join(',');
    }
}

// Load saved polygon onto map
function iniOSMSavedMap(coordinates, mapCanvasId, mapAppendId) {
    var mapCanvas = document.getElementById(mapCanvasId);
    if (!mapCanvas) return;
    
    // Clear any existing Leaflet instance
    if (mapCanvas._leaflet_id) {
        mapCanvas._leaflet_id = null;
        mapCanvas.innerHTML = '';
    }
    
    // Initialize map
    var savedMap = L.map(mapCanvasId).setView([parseFloat(coordinates[0]), parseFloat(coordinates[1])], 10);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(savedMap);
    
    // Feature group for drawn items
    var savedDrawLayer = new L.FeatureGroup();
    savedMap.addLayer(savedDrawLayer);
    
    // Draw control
    var savedDrawControl = new L.Control.Draw({
        position: 'topright',
        draw: {
            polygon: {
                allowIntersection: false,
                shapeOptions: {
                    color: '#ADFF2F',
                    fillOpacity: 0.5
                }
            },
            polyline: false,
            circle: false,
            rectangle: false,
            marker: false,
            circlemarker: false
        },
        edit: {
            featureGroup: savedDrawLayer,
            remove: true
        }
    });
    savedMap.addControl(savedDrawControl);
    
    // Convert coordinates array to LatLng array
    var latlngs = [];
    for (var i = 0; i < coordinates.length; i += 2) {
        latlngs.push([parseFloat(coordinates[i]), parseFloat(coordinates[i + 1])]);
    }
    
    // Draw the saved polygon
    var polygon = L.polygon(latlngs, {
        color: '#ADFF2F',
        fillOpacity: 0.5
    }).addTo(savedDrawLayer);
    
    // Fit map to polygon bounds
    savedMap.fitBounds(polygon.getBounds());
    
    // Force map to recalculate size after container is visible
    setTimeout(function() {
        savedMap.invalidateSize();
        savedMap.fitBounds(polygon.getBounds());
    }, 300);
    
    // Handle edits
    savedMap.on(L.Draw.Event.EDITED, function (e) {
        var layers = e.layers;
        layers.eachLayer(function (layer) {
            if (mapAppendId) {
                saveOSMPolygonCoordinates(layer, mapAppendId);
            }
        });
    });
    
    savedMap.on(L.Draw.Event.DELETED, function (e) {
        if (mapAppendId) {
            document.getElementById(mapAppendId).value = '';
        }
    });
    
    savedMap.on(L.Draw.Event.CREATED, function (e) {
        savedDrawLayer.clearLayers();
        var layer = e.layer;
        savedDrawLayer.addLayer(layer);
        if (mapAppendId) {
            saveOSMPolygonCoordinates(layer, mapAppendId);
        }
    });
}

// Setup location search with autocomplete
function setupOSMLocationSearch(inputId, map, callback) {
    var input = document.getElementById(inputId);
    if (!input) return;
    
    var debounceTimer;
    var resultsContainer = document.createElement('div');
    resultsContainer.className = 'osm-location-autocomplete';
    resultsContainer.style.cssText = 'position: absolute; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 99999; display: none; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);';
    
    // Append to body to avoid parent overflow issues
    document.body.appendChild(resultsContainer);
    
    // Function to position the dropdown
    function positionDropdown() {
        var rect = input.getBoundingClientRect();
        var top = rect.bottom + window.scrollY + 2;
        var left = rect.left + window.scrollX;
        var width = rect.width;
        
        resultsContainer.style.top = top + 'px';
        resultsContainer.style.left = left + 'px';
        resultsContainer.style.width = width + 'px';
    }
    
    input.addEventListener('input', function(e) {
        clearTimeout(debounceTimer);
        var query = e.target.value.trim();
        
        if (query.length < 3) {
            resultsContainer.style.display = 'none';
            return;
        }
        
        debounceTimer = setTimeout(function() {
            positionDropdown(); // Position before showing
            searchOSMLocation(query, resultsContainer, input, map, callback);
        }, 300);
    });
    
    // Reposition on scroll or resize
    window.addEventListener('scroll', function() {
        if (resultsContainer.style.display !== 'none') {
            positionDropdown();
        }
    });
    
    window.addEventListener('resize', function() {
        if (resultsContainer.style.display !== 'none') {
            positionDropdown();
        }
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== input && !resultsContainer.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
}

// Search location using Photon API
function searchOSMLocation(query, container, input, map, callback) {
    var url = 'https://photon.komoot.io/api/?q=' + encodeURIComponent(query) + '&limit=5';
    
    container.innerHTML = '<div style="padding: 2px; text-align: center; color: #666;">Searching...</div>';
    container.style.display = 'block';
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            container.innerHTML = '';
            
            if (!data.features || data.features.length === 0) {
                container.innerHTML = '<div style="padding: 2px; color: #666;">No results found</div>';
                return;
            }
            
            data.features.forEach(function(feature) {
                var properties = feature.properties;
                var coordinates = feature.geometry.coordinates;
                
                var name_parts = [];
                if (properties.name) name_parts.push(properties.name);
                if (properties.city) name_parts.push(properties.city);
                if (properties.state) name_parts.push(properties.state);
                if (properties.country) name_parts.push(properties.country);
                
                var displayName = name_parts.join(', ');
                
                var item = document.createElement('div');
                item.style.cssText = 'padding: 10px; cursor: pointer; border-bottom: 1px solid #eee;';
                item.textContent = displayName;
                
                item.addEventListener('click', function() {
                    input.value = displayName;
                    container.style.display = 'none';
                    
                    var lat = coordinates[1];
                    var lng = coordinates[0];
                    
                    if (callback) {
                        callback(lat, lng, displayName);
                    }
                });
                
                item.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f5f5f5';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'white';
                });
                
                container.appendChild(item);
            });
        })
        .catch(error => {
            console.error('Search error:', error);
            container.innerHTML = '<div style="padding: 10px; color: #f00;">Search failed</div>';
        });
}
