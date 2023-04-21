(function(){
  'use strict';

  let autoScrollMapPoint = true;

  /**
   * Initialize the Mapbox GL JS library and map container
   */
  mapboxgl.accessToken = rnf.mapboxApi;
  const map = new mapboxgl.Map({
    container: 'map',
    style: rnf.mapboxStyle,
    center: [-109.77, 42.99],
    zoom: 2,
  });

  map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-left');

  /**
   * When the Map object is initialized, populate it based on current page
   */
  map.on('load', () => {
    const allTrips =
      (window.rnf?.trips_with_content?.length) ? rnf.trips_with_content : [];

    // Did WordPress rnf-geo tell us what to load? (If the plugin is enabled,
    // this will always exist, though it might be empty.)
    if (window.rnf?.start) {
      // Single post or a trip: get the line.
      if (['post', 'trip'].indexOf(window.rnf.start.type) > -1 && window.rnf.start.hasOwnProperty('trip_id')) {
        loadTrip(window.rnf.start.trip_id, (trip) => {
          const bounds = trip.boundaries.match(/-?\d+\.\d+/g);
          var boxes = [[bounds[0], bounds[1]], [bounds[2], bounds[3]]];
          map.fitBounds(boxes, {animate: true, padding: 10});
        });
      } else {
        // It's not a post or category associated to a trip, load everything
        // I've written about.
        loadAllTrips(allTrips);
        window.addEventListener('scroll', setupMapAutoPanToPost);
      }

      // Single post: add a marker.
      if (window.rnf.start.type === 'post' && window.rnf.start.hasOwnProperty('trip_id')) {
        addMarkerForTimestamp(window.rnf.start.timestamp);
      }

      // Archive view: add the scroll handler
      if (window.rnf.start.type === 'trip') {
        window.addEventListener('scroll', setupMapAutoPanToPost);
      }

      // There's an active trip and we're looking at it or a general index
      if (window.rnf.start.current === true) {
        setupCurrentLocation();
      }
    } else {
      // @TODO: The only way to get here is if rnf-geo didn't execute its init
      // hook, meaning it's disabled or errored. Should we bail?
      console.log('general case');
      loadAllTrips(allTrips);
    }

    setupMapJumpLinks();
    setupMapCloseButton();
    setupMapOpenLink();
  });

  /**
   * Given an array of trip IDs, load and display GeoJSON lines for each.
   */
  const loadAllTrips = (tripsToLoad, callback) => {
    callback = callback || false;

    tripsToLoad.forEach(tripId => {
      loadTrip(tripId, callback);
    });
  };

  /**
   * Given a trip ID, load and display its GeoJSON line.
   */
  const loadTrip = (tripId, callback) => {
    callback = callback || false;

    fetch(`${rnf.locationApi}/trip/${tripId}`)
      .then(res => {
        if (res.ok) {
          return res.json();
        } else {
          throw new Error(JSON.stringify(res));
        }
      })
      .then(tripData => {
        // @TODO: This may not be needed anymore.
        window.rnf.trips[tripId] = tripData;

        // If the trip has a started line, add it to the map.
        if (tripData.line?.coordinates?.length) {
          map.addSource(`trip-${tripId}-source`, {
            type: 'geojson',
            data: tripData.line,
          });

          map.addLayer({
            id: `trip-${tripId}-layer`,
            type: 'line',
            source: `trip-${tripId}-source`,
            paint: {
              'line-color': '#FF3300',
              'line-width': 2,
            },
          });
        }

        // Fire a callback if necessary: used to reset map bounds after a line
        // is loaded. @TODO: could async/promise that...
        if (callback) {
          callback(tripData);
        }
      })
      .catch(error => { console.log(error) });
  }

  /**
   * Given a unix timestamp, get the closest coordinates from the API.
   */
  const getGeoForTimestamp = async (timestamp) => {
    let waypoint = [];

    if (!window.rnf.cache.hasOwnProperty(timestamp)) {
      waypoint = await fetch(`${rnf.locationApi}/waypoint/${timestamp}`)
        .then(res => {
          if (res.ok) {
            return res.json();
          } else {
            throw new Error(JSON.stringify(res));
          }
        })
        .then(payload => {
          waypoint = [payload.lon, payload.lat];
          window.rnf.cache[timestamp] = waypoint;
          return waypoint;
        })
        .catch(error => {
          console.log(error);
          return false;
        });
    } else {
      waypoint = window.rnf.cache[timestamp];
    }

    return waypoint;
  }

  /**
   * Given a unix timestamp, put a marker on the map for where we were.
   */
  const addMarkerForTimestamp = async (timestamp) => {
    const waypoint = await getGeoForTimestamp(timestamp);

    if (window.rnf.rnfPostMarker) {
      window.rnf.rnfPostMarker.remove();
      delete window.rnf.rnfPostMarker;
    }

    // Currently only showing one of these at a time, but it'd be cool to do
    // something more interactive.
    window.rnf.rnfPostMarker = new mapboxgl.Marker({ color: '#FF3300' })
      .setLngLat(waypoint)
      .addTo(map);
  }

  /**
   * Given a unix timestamp, move and zoom the map to where we were.
   */
  const moveMapToTimestamp = async (timestamp) => {
    const waypoint = await getGeoForTimestamp(timestamp);
    map.easeTo({
      center: waypoint,
      zoom: 7,
    });
  }

  /**
   * Snag each of the "location links" in article headers. When clicked, add a
   * marker and move the map. Reveal the map if currently hidden on mobile.
   */
  const setupMapJumpLinks = () => {
    document.querySelectorAll('article a.rnf-map-jump').forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();

        // If user clicks a map link, stop doing it automatically.
        autoScrollMapPoint = false;

        const timestamp = link.getAttribute('data-timestamp');
        addMarkerForTimestamp(timestamp);
        moveMapToTimestamp(timestamp);

        map.getContainer().parentElement.classList.add('visible');
        map.resize();
      });
    });
  };

  const setupMapOpenLink = () => {
    document.querySelectorAll('.map-toggle').forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        map.getContainer().parentElement.classList.add('visible');
        map.resize();
      });
    });
  }

  /**
   * Set up a scroll tracker on article containers to check the map as we go.
   */
  const setupMapAutoPanToPost = () => {
    document.querySelectorAll('main > article.post').forEach((postEl) => {
      if (isPostInScrollView(postEl) && !postEl.classList.contains('rnf-in-view')) {
        postEl.classList.add('rnf-in-view');

        const mapLink = postEl.querySelector('.rnf-map-jump');

        if (mapLink && autoScrollMapPoint) {
          addMarkerForTimestamp(mapLink.getAttribute('data-timestamp'));
        }
      }

      if (postEl.classList.contains('rnf-in-view') && !isPostInScrollView(postEl)) {
        postEl.classList.remove('rnf-in-view');
      }
    });
  }

  const isPostInScrollView = (el) => {
    const rectangle = el.getBoundingClientRect();
    // window.innerHeight;
    // window.scrollY;

    //      Are we past the start?             and  not yet past the end?
    return (rectangle.top < window.innerHeight) && (rectangle.bottom > 0)
  }

  /**
   * Wire up my hacky little "close map" button.
   */
  const setupMapCloseButton = () => {
    document.getElementById('mapclose').addEventListener('click', (e) => {
      e.preventDefault();
      map.getContainer().parentElement.classList.remove('visible');
    });
  };

  /**
   * We have information about a current trip, so there's a little info box in
   * the map display.
   */
  const setupCurrentLocation = () => {
    fetch(`${rnf.locationApi}/waypoint`)
      .then(res => {
        if (res.ok) {
          return res.json();
        } else {
          throw new Error(JSON.stringify(res));
        }
      })
      .then(payload => {
        if (payload.hasOwnProperty('timestamp')) {
          // Where we at?
          document.getElementById('rnf-location').innerText = payload.label;

          // How long ago?
          const now = Math.floor(new Date().getTime() / 1000);
          const diff = (now - payload.timestamp) / 60 / 60;
          const output = (diff < 1) ? "less than an hour ago" : (Math.floor(diff) + " hours ago")
          document.getElementById('rnf-timestamp').innerText = output;

          // Save current location
          window.rnf.currentLocation = payload;

          window.rnf.rnfCurrentMarker = new mapboxgl.Marker({ color: '#0066FF' })
            .setLngLat([payload.lon, payload.lat])
            .addTo(map);
        }
      })
      .catch(error => {
        console.log(error);
        return false;
      });
  }
})();
