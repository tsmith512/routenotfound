<?php
/*
 * Plugin Name: RNF Trips, Maps, and Geography
 * Description: Provides taxonomy support, Location Tracker API integration, and Maps
 * Version: 0.1
 * Author: Taylor Smith
 */

require_once "rnf-geo-map-widget.php";
add_action( 'widgets_init', function() { register_widget( 'RNF_Geo_Map_Widget' ); } );

/**
 * Implements admin_menu to add the options page to the sidebar menu for admins.
 * See rnf-geo-settings.php for the output of this page.
 */
function rnf_geo_add_admin_menu() {
  add_submenu_page('rnf-overrides', 'Trip List', 'Trip List', 'manage_options', 'rnf-geo', 'rnf_geo_admin_page');
  add_submenu_page('rnf-overrides', 'Geo Settings', 'Geo Settings', 'manage_options', 'rnf-geo-settings', 'rnf_geo_options_page');
}
add_action('admin_menu', 'rnf_geo_add_admin_menu');
require_once "rnf-geo-admin.php";
require_once "rnf-geo-settings.php";

/**
 * Register but do not enqueue scripts and stylesheets for integrations and map
 * displays, including passing in the rnf_geo_settings data.
 */
function rnf_geo_register_assets() {
  wp_register_script('mapbox-core', content_url('/vendor/mapbox/mapbox-gl.js'), array(), null, true);
  wp_register_style('mapbox-style', content_url('/vendor/mapbox/mapbox-gl.css'), array(), null);

  wp_register_script('rnf-geo-js', plugin_dir_url( __FILE__ ) . 'js/rnf-geo.js', array('mapbox-core'), RNF_VERSION, true);
  wp_register_style('rnf-geo-style', plugin_dir_url( __FILE__ ) . 'css/rnf-geo-maps.css', array('mapbox-style'), RNF_VERSION);

  // Figure out where we are so we can tell the map where to start
  $object = get_queried_object();
  $current = rnf_geo_current_trip();
  $start = array();
  if ($object instanceof WP_Post) {
    // A single post was called, let's start the map on the post's location
    $start = array(
      'type' => 'post',
      'timestamp' => get_post_time('U', true),
    );

    // And only show the line for the trip the post is on
    $trip_term_id = wp_get_post_categories($object->ID, array('meta_key' => 'rnf_geo_trip_id'));
    if (!empty($trip_term_id) && $trip_term_id[0] > 0) {
      $trip_id = get_term_meta($trip_term_id[0], 'rnf_geo_trip_id', true);
      if (is_numeric($trip_id) && (int) $trip_id > 0) {
        $start['trip_id'] = $trip_id;
        $start['current'] = ($trip_id == $current->id);
      }
    }

  } elseif ($object instanceof WP_Term) {
    // It's a taxonomy term. Check to see if a trip id is associated.
    $trip_id = get_term_meta($object->term_id, 'rnf_geo_trip_id', true);

    if (is_numeric($trip_id) && (int) $trip_id > 0) {
      $start = array(
        'type' => 'trip',
        'trip_id' => $trip_id,
        'current' => (isset($current->id) && $trip_id == $current->id),
      );
    }
  } else if (!empty($current->wp_category)) {
    // There's no queried object, but we're currently traveling and the trip has
    // a corresponding category, so show that.
    $start = array(
      'type' => 'trip',
      'current' => true,
      'trip_id' => $current->id, // Note: this is the trip ID, not the term ID
    );

    // @TODO: This does mean that when the general blog is loaded during a trip,
    // the map will only show the route line for the current trip... Should it
    // show others in the past?

  } else {
    // There is no queried object. The main use-case for this is the default
    // blog view.
    $start = array(
      'type' => false,
    );
  }

  $options = get_option( 'rnf_geo_settings' );
  $tqor = array(
    'mapboxApi' => !empty($options['mapbox_api_token']) ? $options['mapbox_api_token'] : null,
    'mapboxStyle' => !empty($options['mapbox_style']) ? $options['mapbox_style'] : null,
    'locationApi' => !empty($options['location_tracker_endpoint']) ? $options['location_tracker_endpoint'] : null,
    'cache' => array(),
    'trips' => array(),
    'trips_with_content' => rnf_geo_get_trips_with_content(),
    'start' => $start
  );

  wp_localize_script('rnf-geo-js', 'tqor', $tqor);
}
add_action('wp_enqueue_scripts', 'rnf_geo_register_assets', 5);
add_action('admin_enqueue_scripts', 'rnf_geo_register_assets');

/**
 * Go get the trips list from the location tracker, match 'em up with WP
 * categories if possible.
 */
function rnf_geo_get_trips($trip_id = null) {
  $trips = array();

  $transient = get_transient('rnf_geo_trips_cache');

  if (empty($transient)) {
    $options = get_option('rnf_geo_settings');
    $endpoint = $options['location_tracker_endpoint'] . '/trips';
    $result = wp_remote_get($endpoint);

    if (!is_array($result) || is_wp_error($result)) {
      return false;
    }

    if ($result['response']['code'] == 200) {
      $trips = json_decode($result['body']);
      set_transient( 'rnf_geo_trips_cache', $trips, DAY_IN_SECONDS );
    }
  } else {
    $trips = $transient;
  }

  // If we're only looking for data on a single Trip (an ID was provided),
  // then filter _now_ so we don't pound the DB looking for taxonomy terms for
  // trips we don't care about.
  if ($trip_id) {
    $trips = array_filter($trips, function($t) use ($trip_id) {
      // Typecast both because the ID we're testing for may have come in as a
      // string via AJAX, and for some stupid reason I'm returning the ID as a
      // string from the Location Tracker API as well. @TODO: Don't.
      return (int) $t->id === (int) $trip_id;
    });
  }

  foreach ($trips as &$trip) {
    $trip->wp_category = get_term_by('slug', $trip->slug, 'category');
  }

  return empty($trips) ? false : $trips;
}

/**
 * Return a list of trip IDs we have taxonomy terms for (i.e. not the list of
 * trips that exist on the remote service, but the ones we've written about).
 */
function rnf_geo_get_trips_with_content() {
  // The trip IDs are stored as a term-meta value for each category that is a
  // trip. There's not a Term Meta API way to aggregate "all values for this key
  // across all terms" without first fetching all terms, so just get 'em from
  // the DB:
  global $wpdb;
  $trip_ids = $wpdb->get_col("SELECT meta_value FROM {$wpdb->prefix}termmeta WHERE meta_key = 'rnf_geo_trip_id'");

  // WP meta values are all strings, but these should be integers
  array_walk($trip_ids, function(&$e) { $e = (int) $e; });

  return $trip_ids;
}

/**
 * Create a category for a given trip it. Should receive a trip object or trip it
 */
function rnf_geo_ajax_create_trip_category() {
  $trip_id = (int) $_POST['trip_id'];
  $trips = rnf_geo_get_trips($trip_id);

  if (empty($trips)) {
    // @TODO: ERROR
    return false;
  }

  $trip = reset($trips);
  $term = wp_insert_term($trip->label, 'category', array(
    'slug' => $trip->slug
  ));
  add_term_meta($term['term_id'], 'rnf_geo_trip_id', $trip->id, true);

  print json_encode($term);
  wp_die();
}
add_action( 'wp_ajax_tqor_create_term', 'rnf_geo_ajax_create_trip_category' );

/**
 * Dump the trip cache.
 */
function rnf_geo_ajax_clear_trip_cache() {
  delete_transient('rnf_geo_trips_cache');
}
add_action( 'wp_ajax_tqor_clear_trip_cache', 'rnf_geo_ajax_clear_trip_cache' );

/**
 * Display a Location Tracker Trip ID on the taxonomy term management page if
 * there is one.
 */
function rnf_geo_category_add_id_display($term) {
  $trip_id = get_term_meta( $term->term_id, 'rnf_geo_trip_id', true );

  if ($trip_id) {
    print "Location Tracker Trip ID: $trip_id";
  } else {
    print "<em>Term not associated to a trip. Create from RNF Geo page directly.</em>";
  }
}
add_action('category_edit_form_fields', 'rnf_geo_category_add_id_display');

/**
 * Check to see if a post was actually published during the trip it is about.
 * We will only show a "map" link on posts that are actually visible on the map.
 */
function rnf_geo_is_post_during_trip(&$post) {
  // Assume a post isn't written during the trip it is about as a baseline.
  $post->rnf_geo_post_is_on_trip = false;

  // @TODO: This is repeated from rnf_geo_register_assets, need to abstract it
  $trip_term_id = wp_get_post_categories($post->ID, array('meta_key' => 'rnf_geo_trip_id'));

  if (!empty($trip_term_id) && $trip_term_id[0] > 0) {
    // We got a WP category ID, look up its associated trip:
    $trip_id = get_term_meta($trip_term_id[0], 'rnf_geo_trip_id', true);

    if (is_numeric($trip_id) && (int) $trip_id > 0) {
      // We have a trip ID to match up with.

      // Get the post's unix timestamp
      $timestamp = get_post_time('U', true);

      // Get the timestamps of the beginning and end of the trip
      $trip_details = rnf_geo_get_trips($trip_id);
      $trip_details = reset($trip_details);

      // So is this post actually dated _during_ the trip it is about?
      $post->rnf_geo_post_is_on_trip = ($trip_details->start <= $timestamp && $timestamp <= $trip_details->end);

      if ($post->rnf_geo_post_is_on_trip) {
        rnf_geo_attach_city($post);
      }
    } else {
      // There was a category attached to this post with an rnf_geo_trip_id
      // value, but we didn't get a value... that's really weird.
    }
  } else {
    // This isn't even about a trip.
    // @TODO: So clearly, no link should show, but does that logic belong here?
  }
}
add_action('the_post', 'rnf_geo_is_post_during_trip');

/**
 * Given a post that is on a trip, query the API to get what city it was
 * written in and add that to the post object.
 */
function rnf_geo_attach_city(&$post) {
  // Store these by timestamp so that they can be reused across
  // posts at the same time (rare...) and also are automatically
  // invalidated if a post's date changes.
  $timestamp = get_post_time('U', true);
  $transient = get_transient('rnf_geo_city_for_' . $timestamp);

  if (empty($transient)) {
    $options = get_option('rnf_geo_settings');
    $endpoint = $options['location_tracker_endpoint'] . "/waypoint/{$timestamp}";
    $result = wp_remote_get($endpoint);

    if (!is_array($result) || is_wp_error($result)) {
      return;
    }

    if ($result['response']['code'] == 200) {
      $location = json_decode($result['body']);

      // Location stamps are 30 minutes apart. If the response we get was within an hour
      // of the post type, we can assume that the location service has recent data. If
      // the difference is more than 2 hours, the location tracker may be behind. Don't
      // save for very long so we can refresh later.
      $ttl = (abs($timestamp - $location->timestamp) < 2 * HOUR_IN_SECONDS) ? YEAR_IN_SECONDS : HOUR_IN_SECONDS;
      set_transient('rnf_geo_city_for_' . $timestamp, $location, $ttl);
    }
  } else {
    $location = $transient;
  }

  $post->rnf_geo_city = $location->label ?: false;
}

/**
 * Determine which, if any, trip we may currently be on. Will return false if
 * current time is not in a trip range, otherwise will return current trip
 * object and, if created, the corresponding post category object.
 */
function rnf_geo_current_trip() {
  $trips =  rnf_geo_get_trips();

  $current_trip = false;
  foreach ($trips as $trip) {
    if ($trip->start < time() && time() < $trip->end) {
      // @TODO: Though there's no business logic case for two trips to overlap,
      // this would only return the lowest index trip in the case that multiple
      // are active...
      $current_trip = $trip;
      break;
    }
  }
  return $current_trip;
}
