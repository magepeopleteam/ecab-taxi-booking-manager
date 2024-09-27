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
InitMapOne(geoLocationOne);
InitMapTwo(geoLocationOne);
InitMapFixed(geoLocationOne,formattedAddress);



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
        
        // Register event listener for input change
        $('#mptbm-starting-location-one').on('input', function () {
            var input = document.getElementById('mptbm-starting-location-one');
            var autocomplete = new google.maps.places.Autocomplete(input, { types: ['geocode'] });
            
            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();
                formattedAddress = place.formatted_address;
                if (place.geometry) {
                    var location = place.geometry.location;
                    InitMapOne(location,formattedAddress);
                }
            });
        });
        $('#mptbm-starting-location-two').on('input', function () {
            var input = document.getElementById('mptbm-starting-location-two');
            var autocomplete = new google.maps.places.Autocomplete(input, { types: ['geocode'] });
            
            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();
                formattedAddress = place.formatted_address;
                if (place.geometry) {
                    var location = place.geometry.location;
                    InitMapTwo(location,formattedAddress);
                }
            });
        });
        $('#mptbm-starting-location-three').on('input', function () {
            var input = document.getElementById('mptbm-starting-location-three');
            var autocomplete = new google.maps.places.Autocomplete(input, { types: ['geocode'] });
            
            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();
                formattedAddress = place.formatted_address;
                if (place.geometry) {
                    var location = place.geometry.location;
                    
                    InitMapFixed(location,formattedAddress);
                }
            });
        });
        
    });
    
})(jQuery);
