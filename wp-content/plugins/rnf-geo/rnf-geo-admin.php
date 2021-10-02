<?php

function rnf_geo_admin_page() {
  $trips = rnf_geo_get_trips();
  $current = rnf_geo_current_trip();
  ?>
  <div class="wrap">
    <h1>Trips in Location Tracker <a id="rnf-cache-clear" class="page-title-action">Clear Trips Cache</a></h1>
    <?php /* @TODO: Let's do this the right way... */ ?>
    <table id="tqor-trips-list" class="wp-list-table widefat fixed striped posts">
      <thead>
        <tr>
          <th>Trip ID</th>
          <th>Machine Name</th>
          <th>Title</th>
          <th>Started</th>
          <th>Ended</th>
          <th>WordPress Category Assigned?</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($trips as $index => $trip): ?>
          <?php /* This is a terrible way to mark a row, do it better. */ ?>
          <tr <?php if (isset($current->id) && $trip->id == $current->id) { echo "style='font-weight: bold; background-color: #ccffcc;'"; } ?>>
            <td><?php print $trip->id; ?></td>
            <td><?php print $trip->slug; ?></td>
            <td><?php print $trip->label; ?></td>
            <td><?php print date('r', $trip->start); ?></td>
            <td><?php print date('r', $trip->end); ?></td>
            <td>
              <?php
                if ($trip->wp_category !== false) {
                  $url = get_term_link($trip->wp_category);
                  $title = $trip->wp_category->name;
                  print "<a href='{$url}'>{$title}</a>";
                } else {
                  print "<button data-trip-id='{$trip->id}' class='button-secondary'>Create?</button>";
                }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php /* @TODO: This definitely doesn't go here. */ ?>
  <script>
    jQuery(document).ready(function($) {
      $('#tqor-trips-list button').on('click', function(){
        var data = {
          'action': 'tqor_create_term',
          'trip_id': $(this).attr('data-trip-id')
        };
        jQuery.post(ajaxurl, data, function(response) {
          // @TODO: This could be something that isn't a page refresh...
          window.location.reload(true);
        });
      });
      $('#rnf-cache-clear').on('click', function(){
        var data = {
          'action': 'tqor_clear_trip_cache'
        };
        jQuery.post(ajaxurl, data, function(response) {
          // @TODO: This could be something that isn't a page refresh...
          window.location.reload(true);
        });
      });
    });
  </script>
	<?php
}
