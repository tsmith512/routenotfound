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

    $current = rnf_geo_current_trip();
    ?>
      <div class="rnf-geo-map-widget">
        <div id="map"></div>
        <?php if (!empty($current->wp_category)): ?>
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
