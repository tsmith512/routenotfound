<?php
/**
 * Create RNF_Geo_Map_Widget widget which will be a container for a
 * Mapbox map with annotations built in Mapbox and/or Location data from the
 * Location Tracker API.
 */
class RNF_Geo_Map_Widget extends WP_Widget {
  /**
   * Constructor with admin info
   */
  public function __construct() {
    parent::__construct(
      'rnf_geo_map_widget',
      'RNF Map',
      array(
        'description' => 'A Mapbox Map with TL/Location Tracker Data'
      )
    );
  }

  /**
   * Display widget frontend. @TODO: Placeholder.
   *
   * @see WP_Widget::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function widget($args, $instance) {
    wp_enqueue_style('mapbox-style');
    wp_enqueue_script('mapbox-core');
    wp_enqueue_style('rnf-geo-style');
    wp_enqueue_script('rnf-geo-js');

    $object = get_queried_object();
    $current = rnf_geo_current_trip();

    // This widget includes some PHP and JS stuff to show info about the current
    // trip, if we're on one. There's some logic to this. Default to no.
    $show_current_trip_info = false;

    // Are we on a trip?
    if (!empty($current->wp_category)) {

      // Is the current page request for a trip/category archive?
      if ($object instanceof WP_Term) {

        // Yes, so we should only show the box if it is the archive for the current trip.
        if (!empty($object->term_id) && $object->term_id == $current->wp_category->term_id) {
          $show_current_trip_info = true;
        }
      }

      // Is the current page request for a post?
      elseif ($object instanceof WP_Post) {
        // Yes. This one's harder... get category IDs for the post:
        $categories = array_map(function ($term) { return $term->term_id; }, get_the_category($object->ID));

        // And if one of them is the current trip, show the box:
        if (in_array($current->wp_category->term_id, $categories)) {
          $show_current_trip_info = true;
        }
      }

      // No, we don't know what was queried (most likely this is the home page /
      // blog view). But we're on a trip, so show the thing.
      else {
        $show_current_trip_info = true;
      }
    }
    ?>
      <div class="rnf-geo-map-widget">
        <div id="map"></div>
        <div id="mapclose">
          <span>Close</span>
        </div>
        <?php if ($show_current_trip_info): ?>
          <div class="trip-info">
            <span class="rnf-geo-widget-icon rnf-geo-widget-icon-marker">Current Location:</span>
            <em><span id="rnf-location"></span> &bull; <span id="rnf-timestamp"></span></em>
            <hr />
            <span class="rnf-geo-widget-icon rnf-geo-widget-icon-trip">Trip:</span>
            <a href="<?php echo get_term_link($current->wp_category); ?>"><?php echo $current->wp_category->name; ?></a>
          </div>
        <?php endif; ?>
      </div>

    <?php
  }
}
