var mapOptions;
var map;

var coordinates = [];
let new_coordinates = [];
let lastElemen;
var geoLocationOne;
var formattedAddress;
function notifyMPTBMOperationAreaField(inputId) {
    var input = document.getElementById(inputId);
    if (input) {
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
function InitMapOne(geoLocationOne, locationAddress) {
    var mapCanvas1 = document.getElementById('mptbm-map-canvas-one');
    if (mapCanvas1) {
        var slotFormattedAddress = locationAddress || (document.getElementById('mptbm-starting-location-one') ? document.getElementById('mptbm-starting-location-one').value : '');
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
                fillColor: '#635bff',
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
                document.getElementById('mptbm-coordinates-one').value = '';
                notifyMPTBMOperationAreaField('mptbm-coordinates-one');
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
            document.getElementById('mptbm-starting-location-one-hidden').value = slotFormattedAddress;
            document.getElementById('mptbm-coordinates-one').value = coordinates;
            notifyMPTBMOperationAreaField('mptbm-starting-location-one-hidden');
            notifyMPTBMOperationAreaField('mptbm-coordinates-one');
        };

        google.maps.event.addListener(drawingManager, 'polygoncomplete', function (event) {
            getPolygonCoords(event);
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
function InitMapTwo(geoLocationOne, locationAddress) {
    var mapCanvas1 = document.getElementById('mptbm-map-canvas-two');
    if (mapCanvas1) {
        var slotFormattedAddress = locationAddress || (document.getElementById('mptbm-starting-location-two') ? document.getElementById('mptbm-starting-location-two').value : '');
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
                fillColor: '#635bff',
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
                document.getElementById('mptbm-coordinates-two').value = '';
                notifyMPTBMOperationAreaField('mptbm-coordinates-two');
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
            document.getElementById('mptbm-starting-location-two-hidden').value = slotFormattedAddress;
            document.getElementById('mptbm-coordinates-two').value = coordinates;
            notifyMPTBMOperationAreaField('mptbm-starting-location-two-hidden');
            notifyMPTBMOperationAreaField('mptbm-coordinates-two');
        };

        google.maps.event.addListener(drawingManager, 'polygoncomplete', function (event) {
            getPolygonCoords(event);
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
function InitMapFixed(geoLocationOne, locationAddress) {
    var mapCanvas3 = document.getElementById('mptbm-map-canvas-three');
    if (mapCanvas3) {
        var slotFormattedAddress = locationAddress || (document.getElementById('mptbm-starting-location-three') ? document.getElementById('mptbm-starting-location-three').value : '');
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
                fillColor: '#635bff',
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
                document.getElementById('mptbm-coordinates-three').value = '';
                notifyMPTBMOperationAreaField('mptbm-coordinates-three');
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
            document.getElementById('mptbm-starting-location-three-hidden').value = slotFormattedAddress;
            document.getElementById('mptbm-coordinates-three').value = coordinates;
            notifyMPTBMOperationAreaField('mptbm-starting-location-three-hidden');
            notifyMPTBMOperationAreaField('mptbm-coordinates-three');
        };

        google.maps.event.addListener(drawingManager, 'polygoncomplete', function (event) {
            getPolygonCoords(event);
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
            fillColor: '#635bff',
            fillOpacity: 0.5
        }
    });

    google.maps.event.addListener(drawingManager, 'polygoncomplete', function(event) {
        getPolygonCoords(event);
        google.maps.event.addListener(event, "dragend", getPolygonCoords(event));
        google.maps.event.addListener(event.getPath(), 'insert_at', function() {
            getPolygonCoords(event);
        });
        google.maps.event.addListener(event.getPath(), 'set_at', function() {
            getPolygonCoords(event);
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
            notifyMPTBMOperationAreaField(mapAppendId);
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
        fillColor: "#635bff",
        fillOpacity: 0.5, // Adjust fill opacity
        editable: false // Make the polygon editable
    });

    // Set polygon on the map
    polygon.setMap(map);
    google.maps.event.addListener(polygon, 'click', function() {
        setSelection(polygon);
    });
    google.maps.event.addListener(polygon.getPath(), 'insert_at', function() {
        getPolygonCoords(polygon);
    });
    google.maps.event.addListener(polygon.getPath(), 'set_at', function() {
        getPolygonCoords(polygon);
    });

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
        if (mapAppendId) {
            document.getElementById(mapAppendId).value = '';
            notifyMPTBMOperationAreaField(mapAppendId);
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
                    var slot = inputId.split('-').pop();
                    var coordinatesInput = document.getElementById('mptbm-coordinates-' + slot);
                    var locationInput = document.getElementById('mptbm-starting-location-' + slot + '-hidden');
                    if (coordinatesInput) {
                        coordinatesInput.value = '';
                        notifyMPTBMOperationAreaField(coordinatesInput.id);
                    }
                    if (locationInput) {
                        locationInput.value = formattedAddress || input.value;
                        notifyMPTBMOperationAreaField(locationInput.id);
                    }
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

// Keep saved and newly-created modal maps in the same public slots. The
// Operation Area builder uses these references for its accessible Draw/Edit/
// Fit/Clear buttons, while the original Leaflet.draw toolbar remains intact.
function registerOSMOperationAreaMap(mapCanvasId, mapInstance, drawLayer, drawControl) {
    var slot = String(mapCanvasId || '').split('-').pop();
    var suffix = slot ? slot.charAt(0).toUpperCase() + slot.slice(1) : '';
    if (!suffix) return;
    window['osmMap' + suffix] = mapInstance;
    window['osmDrawLayer' + suffix] = drawLayer;
    window['osmDrawControl' + suffix] = drawControl;
    if (slot === 'one') {
        osmMapOne = mapInstance;
        osmDrawLayerOne = drawLayer;
        osmDrawControlOne = drawControl;
    } else if (slot === 'two') {
        osmMapTwo = mapInstance;
        osmDrawLayerTwo = drawLayer;
        osmDrawControlTwo = drawControl;
    } else if (slot === 'three') {
        osmMapFixed = mapInstance;
        osmDrawLayerFixed = drawLayer;
        osmDrawControlFixed = drawControl;
    }
    setupOSMFirstPointGuide(mapCanvasId, mapInstance);
}

// Leaflet.draw renders every vertex with the same small square. Highlight the
// first vertex during polygon creation so users always know where to click to
// close the boundary. This is presentation-only; Leaflet keeps handling the
// click and polygon completion exactly as before.
function setupOSMFirstPointGuide(mapCanvasId, mapInstance) {
    var mapCanvas = document.getElementById(mapCanvasId);
    if (!mapCanvas || !mapInstance || mapInstance._mptbmFirstPointGuide) return;

    var mapShell = mapCanvas.closest('.mptbm-operation-area-map-shell');
    var finishLabel = window.mptbmOperationAreas && window.mptbmOperationAreas.finishAtStart
        ? window.mptbmOperationAreas.finishAtStart
        : 'START — click to finish';

    function clearFirstPoint() {
        mapCanvas.querySelectorAll('.mptbm-first-draw-point').forEach(function(marker) {
            marker.classList.remove('mptbm-first-draw-point');
            marker.classList.remove('is-label-left');
            marker.removeAttribute('data-finish-label');
        });
        if (mapShell) mapShell.classList.remove('is-drawing-boundary');
    }

    function markFirstPoint(event) {
        window.setTimeout(function() {
            var markers = event && event.layers && typeof event.layers.getLayers === 'function'
                ? event.layers.getLayers()
                : [];
            var firstMarker = markers.length && markers[0]._icon
                ? markers[0]._icon
                : mapCanvas.querySelector('.leaflet-marker-pane .leaflet-editing-icon');

            mapCanvas.querySelectorAll('.mptbm-first-draw-point').forEach(function(marker) {
                if (marker !== firstMarker) marker.classList.remove('mptbm-first-draw-point');
            });
            if (firstMarker) {
                firstMarker.classList.add('mptbm-first-draw-point');
                firstMarker.setAttribute('data-finish-label', finishLabel);
                var markerRect = firstMarker.getBoundingClientRect();
                var canvasRect = mapCanvas.getBoundingClientRect();
                firstMarker.classList.toggle('is-label-left', markerRect.left > canvasRect.left + (canvasRect.width * 0.68));
            }
        }, 0);
    }

    mapInstance.on(L.Draw.Event.DRAWSTART, function() {
        clearFirstPoint();
        if (mapShell) mapShell.classList.add('is-drawing-boundary');
    });
    mapInstance.on(L.Draw.Event.DRAWVERTEX, markFirstPoint);
    mapInstance.on(L.Draw.Event.DRAWSTOP, clearFirstPoint);
    mapInstance.on(L.Draw.Event.CREATED, clearFirstPoint);
    mapInstance._mptbmFirstPointGuide = true;
}

// Let the guided Operation Area UI react immediately when Leaflet changes a
// hidden field. Native DOM assignments do not fire jQuery change handlers.
function notifyOSMOperationAreaField(inputId) {
    notifyMPTBMOperationAreaField(inputId);
}

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
                    color: '#635bff',
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
    registerOSMOperationAreaMap('mptbm-map-canvas-one', osmMapOne, osmDrawLayerOne, osmDrawControlOne);
    
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
        notifyOSMOperationAreaField('mptbm-coordinates-one');
    });
    
    // Setup autocomplete for location search
    setupOSMLocationSearch('mptbm-starting-location-one', osmMapOne, function(lat, lng, displayName) {
        osmMapOne.setView([lat, lng], 13);
        osmDrawLayerOne.clearLayers();
        document.getElementById('mptbm-starting-location-one-hidden').value = displayName;
        document.getElementById('mptbm-coordinates-one').value = '';
        notifyOSMOperationAreaField('mptbm-starting-location-one-hidden');
        notifyOSMOperationAreaField('mptbm-coordinates-one');
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
                    color: '#635bff',
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
    registerOSMOperationAreaMap('mptbm-map-canvas-two', osmMapTwo, osmDrawLayerTwo, osmDrawControlTwo);
    
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
        notifyOSMOperationAreaField('mptbm-coordinates-two');
    });
    
    setupOSMLocationSearch('mptbm-starting-location-two', osmMapTwo, function(lat, lng, displayName) {
        osmMapTwo.setView([lat, lng], 13);
        osmDrawLayerTwo.clearLayers();
        document.getElementById('mptbm-starting-location-two-hidden').value = displayName;
        document.getElementById('mptbm-coordinates-two').value = '';
        notifyOSMOperationAreaField('mptbm-starting-location-two-hidden');
        notifyOSMOperationAreaField('mptbm-coordinates-two');
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
                    color: '#635bff',
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
    registerOSMOperationAreaMap('mptbm-map-canvas-three', osmMapFixed, osmDrawLayerFixed, osmDrawControlFixed);
    
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
        notifyOSMOperationAreaField('mptbm-coordinates-three');
    });
    
    setupOSMLocationSearch('mptbm-starting-location-three', osmMapFixed, function(lat, lng, displayName) {
        osmMapFixed.setView([lat, lng], 13);
        osmDrawLayerFixed.clearLayers();
        document.getElementById('mptbm-starting-location-three-hidden').value = displayName;
        document.getElementById('mptbm-coordinates-three').value = '';
        notifyOSMOperationAreaField('mptbm-starting-location-three-hidden');
        notifyOSMOperationAreaField('mptbm-coordinates-three');
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
        notifyOSMOperationAreaField(inputId);
    }
}

// Load saved polygon onto map
function iniOSMSavedMap(coordinates, mapCanvasId, mapAppendId) {
    var mapCanvas = document.getElementById(mapCanvasId);
    if (!mapCanvas) return;

    var slot = String(mapCanvasId).split('-').pop();
    var suffix = slot ? slot.charAt(0).toUpperCase() + slot.slice(1) : '';
    var existingMap = suffix ? window['osmMap' + suffix] : null;

    if (existingMap) {
        try {
            existingMap.remove();
        } catch (e) {
            console.log('[OSM] Error removing saved map:', e);
        }
    }
    
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
                    color: '#635bff',
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
    registerOSMOperationAreaMap(mapCanvasId, savedMap, savedDrawLayer, savedDrawControl);
    if (slot === 'one' || slot === 'two' || slot === 'three') {
        setupOSMLocationSearch('mptbm-starting-location-' + slot, savedMap, function(lat, lng, displayName) {
            savedMap.setView([lat, lng], 13);
            savedDrawLayer.clearLayers();
            var hiddenLocation = document.getElementById('mptbm-starting-location-' + slot + '-hidden');
            if (hiddenLocation) {
                hiddenLocation.value = displayName;
                notifyOSMOperationAreaField(hiddenLocation.id);
            }
            if (mapAppendId) {
                document.getElementById(mapAppendId).value = '';
                notifyOSMOperationAreaField(mapAppendId);
            }
        });
    }
    
    // Convert coordinates array to LatLng array
    var latlngs = [];
    for (var i = 0; i < coordinates.length; i += 2) {
        latlngs.push([parseFloat(coordinates[i]), parseFloat(coordinates[i + 1])]);
    }
    
    // Draw the saved polygon
    var polygon = L.polygon(latlngs, {
        color: '#635bff',
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
            notifyOSMOperationAreaField(mapAppendId);
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

    if (input._mptbmOsmSearchState) {
        input._mptbmOsmSearchState.map = map;
        input._mptbmOsmSearchState.callback = callback;
        return;
    }
    
    var debounceTimer;
    var resultsContainer = document.createElement('div');
    resultsContainer.className = 'osm-location-autocomplete';
    resultsContainer.style.cssText = 'position: fixed; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 999999; display: none; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);';
    
    // Append to body to avoid parent overflow issues
    document.body.appendChild(resultsContainer);
    var searchState = {
        map: map,
        callback: callback,
        resultsContainer: resultsContainer
    };
    input._mptbmOsmSearchState = searchState;
    
    // Function to position the dropdown
    function positionDropdown() {
        var rect = input.getBoundingClientRect();
        var top = rect.bottom + 2;
        var left = rect.left;
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
            searchOSMLocation(query, resultsContainer, input, searchState.map, searchState.callback);
        }, 300);
    });
    
    // Reposition on scroll or resize
    document.addEventListener('scroll', function() {
        if (resultsContainer.style.display !== 'none') {
            positionDropdown();
        }
    }, true);
    
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
