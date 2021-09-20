(function(){
  'use strict';

  L.mapbox.accessToken = tqor.mapboxApi;
  window.map = L.mapbox.map('map').fitBounds([[50.79204, -97.20703],[15.02968, -125.77148]], {animate: true, padding: [10, 10]});
  L.mapbox.styleLayer(tqor.mapboxStyle).addTo(map);

  // @TODO: NOTE! THIS CALLBACK IS EXECUTED FOR EACH LOAD at the moment. It's
  // original usecase was just to check dates anyway, but we need a way to call
  // loadAllTrips's callback _after_ all of forEach(laodTrip()) is done.
  var loadAllTrips = function(tripsToLoad, callback) {
    tripsToLoad = (Array.isArray(tqor.trips_with_content) && tripsToLoad.length) ? tripsToLoad : false;
    callback = callback || false;

    // If we don't have a list of trips to load provided by WordPress, request them all.
    if (!tripsToLoad) {
      // Request from the location server API all of the Trips in the database.
      var xhr = new XMLHttpRequest();
      xhr.open('GET', tqor.locationApi + '/trips');
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onload = function () {
        if (xhr.status === 200) {
          var response = JSON.parse(xhr.responseText);
          // For every trip identified in the database, fetch its information and
          // add the LineString to the map.
          response.forEach(function (trip) {
            loadTrip(trip.id, callback);
          });
        }
      };
      xhr.send();
    } else {
      tripsToLoad.forEach(function (trip) {
        loadTrip(trip, callback);
      });
    }
  }

  var loadTrip = function(trip_id, callback) {
    callback = callback || false;

    var tripXhr = new XMLHttpRequest();
    tripXhr.open('GET', tqor.locationApi + '/trip/' + trip_id);
    tripXhr.setRequestHeader('Content-Type', 'application/json');
    tripXhr.onload = function () {
      if (tripXhr.status === 200) {
        var tripResponse = JSON.parse(tripXhr.responseText);
        window.tqor.trips[trip_id] = tripResponse;
        if (tripResponse.hasOwnProperty('line') && tripResponse.line.coordinates) {
          window.tqor.trips[trip_id].line = L.mapbox.featureLayer(tripResponse.line).addTo(map);

          if (callback) {
            callback(tripResponse);
          }
        }
      }
    };
    tripXhr.send();
  }

  var mapToTimestamp = function(timestamp) {
    if (!window.tqor.cache.hasOwnProperty(timestamp)) {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', tqor.locationApi + '/waypoint/' + timestamp);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onload = function () {
        if (xhr.status === 200) {
          var response = JSON.parse(xhr.responseText);
          if (response.hasOwnProperty('lat')) {
            map.setView([response.lat, response.lon], 8);
            window.tqor.cache[timestamp] = [response.lat, response.lon];
          }
        }
      };
      xhr.send();
    }
    else {
      map.setView(window.tqor.cache[timestamp], 8);
    }
    map.invalidateSize();
  }

  var addMarkerToTimestamp = function(timestamp) {
    if (!window.tqor.cache.hasOwnProperty(timestamp)) {
      var xhr = new XMLHttpRequest();
      xhr.open('GET', tqor.locationApi + '/waypoint/' + timestamp);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onload = function () {
        if (xhr.status === 200) {
          var response = JSON.parse(xhr.responseText);
          if (response.hasOwnProperty('lat')) {
            window.tqor.cache[timestamp] = [response.lat, response.lon];
            // Drop a marker on the map:
            var markerGeoJSON = [
              {
                type: "Feature",
                geometry: {
                  type: "Point",
                  coordinates: [response.lon, response.lat]
                },
                properties: {
                  "marker-color": "#FF6633",
                  "marker-size": "small",
                  "marker-symbol": "post"
                }
              }
            ];
            if (window.tqor.hasOwnProperty('postMarker')) {
              window.tqor.postMarker.removeFrom(map);
            }
            window.tqor.postMarker = L.mapbox.featureLayer().setGeoJSON(markerGeoJSON).addTo(map);
          }
        }
      };
      xhr.send();
    }
    else {
      // Drop a marker on the map:
      var markerGeoJSON = [
        {
          type: "Feature",
          geometry: {
            type: "Point",
            coordinates: window.tqor.cache[timestamp]
          },
          properties: {
            "marker-color": "#FF6633",
            "marker-size": "small",
            "marker-symbol": "post"
          }
        }
      ];
      if (window.tqor.hasOwnProperty('postMarker')) {
        window.tqor.postMarker.removeFrom(map);
      }
      window.tqor.postMarker = L.mapbox.featureLayer().setGeoJSON(markerGeoJSON).addTo(map);
    }
  }

  var rnfCustomMapControl = L.Control.extend({
    options: {
      position: 'topright',
    },
    onAdd: function (map) {
      var container = L.DomUtil.create('div', 'leaflet-bar rnfmap-custom-controls');
      L.DomEvent.disableClickPropagation(container);

      var closeMapLink = L.DomUtil.create('a', 'icon-close rnfmap-close', container);
      closeMapLink.href = '#';
      closeMapLink.text = 'Close';
      L.DomEvent.addListener(closeMapLink, 'click', this._closeMapClick, this);

      return container;
    },

    _closeMapClick: function (e) {
      L.DomEvent.stop(e);
      map._container.parentElement.classList.toggle('visible');
    }
  });
  map.addControl(new rnfCustomMapControl());

  var mapJumpLinks = document.querySelectorAll('article a.tqor-map-jump');
  mapJumpLinks.forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      var timestamp = el.getAttribute('data-timestamp');
      mapToTimestamp(timestamp);
      addMarkerToTimestamp(timestamp);

      // And add the visible class to the container so it opens on mobile.
      map._container.parentElement.classList.toggle('visible');
      map.invalidateSize();
    });
  });

  // Decide what to show on the map given what kind of page we're on
  // @TODO: A trip category page is going to need the trip data from above,
  // may need to wrap this in a promise.
  var tripsToLoad = false;

  if (tqor.hasOwnProperty('trips_with_content') && tqor.trips_with_content.length) {
    tripsToLoad = tqor.trips_with_content;
  }

  if (tqor.hasOwnProperty('start')) {
    switch (window.tqor.start.type) {
      case 'trip':
        loadTrip(window.tqor.start.trip_id, function(trip) {
          if (trip.hasOwnProperty('boundaries')) {
            var bounds = trip.boundaries.match(/-?\d+\.\d+/g);
            var boxes = [[bounds[1], bounds[0]], [bounds[3], bounds[2]]];
            window.map.fitBounds(boxes, {animate: true, padding: [10, 10]});
          }
        });
        break;
      case 'post':
        if (window.tqor.start.hasOwnProperty('trip_id')) {
          loadTrip(window.tqor.start.trip_id, function(trip) {
            // If this trip has a line, zoom the map in
            if (trip.hasOwnProperty('boundaries')) {
              var bounds = trip.boundaries.match(/-?\d+\.\d+/g);
              var boxes = [[bounds[1], bounds[0]], [bounds[3], bounds[2]]];
              window.map.fitBounds(boxes, {animate: true, padding: [10, 10]});
            }

            // If the post was written during this trip, add a marker
            if (trip.start <= window.tqor.start.timestamp && window.tqor.start.timestamp <= trip.end) {
              addMarkerToTimestamp(window.tqor.start.timestamp);
            }
          });
        } else {
          loadAllTrips(tripsToLoad);
        }
        break;
      default:
        var currentTimestamp = Date.now() / 1000;
        loadAllTrips(tripsToLoad, function(tripResponse){
          if (tripResponse.start <= currentTimestamp && currentTimestamp <= tripResponse.end) {
            // This trip is happening now, zoom to it.
            if (tripResponse.hasOwnProperty('boundaries')) {
              window.map.fitBounds(tripResponse.boundaries, {animate: true, padding: [10, 10]});
            }
          }
        });
        // @TODO: Where should we center the map in this case?
    }
  }

  // @TODO: There's probably a better way to handle this logic, but WordPress
  // will only output this information panel if we're on a trip that has an
  // associated category. So this check is asking "are we on a trip with content?"
  if (document.querySelectorAll('.rnf-geo-map-widget .trip-info').length) {
    var currentLocation = new XMLHttpRequest();
    currentLocation.open('GET', tqor.locationApi + '/waypoint');
    currentLocation.setRequestHeader('Content-Type', 'application/json');
    currentLocation.onload = function () {
      if (currentLocation.status === 200) {
        var response = JSON.parse(currentLocation.responseText);
        if (response.hasOwnProperty('time')) {
          // Set the current city in the widget:
          document.getElementById('rnf-location').innerText = response.label;

          // Set the current time in the widget:
          var now = Math.floor(new Date().getTime() / 1000);
          var then = response.timestamp;
          var diff = (now - then) / 60 / 60;
          var output = (diff < 1) ? "less than an hour ago" : (Math.floor(diff) + " hours ago")
          document.getElementById('rnf-timestamp').innerText = output;
          window.tqor.currentLocation = response;
        }

        // Drop a marker on the map:
        var markerGeoJSON = [
          {
            type: "Feature",
            geometry: {
              type: "Point",
              coordinates: [response.lon, response.lat]
            },
            properties: {
              "marker-color": "#FF6633",
              "marker-size": "small",
              "marker-symbol": "car"
            }
          }
        ];

        window.tqor.currentLocation.markerLayer = L.mapbox.featureLayer().setGeoJSON(markerGeoJSON).addTo(map);
      }
    };
    currentLocation.send();
  }


})();
