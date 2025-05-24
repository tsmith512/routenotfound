<?php

function rnf_geo_admin_page() {
  $trips = rnf_geo_get_trips() ?: [];
  $current = rnf_geo_current_trip();
  $total_posts = 0;
  $total_words = 0;
  ?>
  <div class="wrap">
    <h1>Trips in Location Tracker <a id="rnf-cache-clear" class="page-title-action">Clear Trips Cache</a></h1>
    <?php /* @TODO: Let's do this the right way... */ ?>
    <table id="rnf-trips-list" class="wp-list-table widefat fixed striped posts">
      <thead>
        <tr>
          <th>Trip ID</th>
          <th>Machine Name</th>
          <th>Title</th>
          <th>Started</th>
          <th>Ended</th>
          <th>WordPress Category Assigned?</th>
          <th>Metrics</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!count($trips)): ?>
          <tr>
            <td colspan="6"><strong>Error:</strong> Empty trip list. Check for backend connection errors or refresh cache?</td>
          </tr>
        <?php endif; ?>
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
            <td>
              <?php
                if ($trip->wp_category !== false) {
                  $stats = rnf_geo_get_trip_content_metrics($trip->slug);
                  print "Posts: " . $stats['posts'] . "<br /> Words: " . $stats['words'];
                  $total_posts += $stats['posts'];
                  $total_words += $stats['words'];
                }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <thead>
        <tr>
          <th>Trip ID</th>
          <th>Machine Name</th>
          <th>Title</th>
          <th>Started</th>
          <th>Ended</th>
          <th>Category</th>
          <th>
            <strong>Total Metrics</strong>
            <br />Total posts: <?php print $total_posts; ?>
            <br />Total words: <?php print $total_words; ?>
          </th>
        </tr>
      </thead>

    </table>
  </div>

  <?php /* @TODO: This definitely doesn't go here. */ ?>
  <script>
    jQuery(document).ready(function($) {
      $('#rnf-trips-list button').on('click', function(){
        var data = {
          'action': 'rnf_create_term',
          'trip_id': $(this).attr('data-trip-id')
        };
        jQuery.post(ajaxurl, data, function(response) {
          // @TODO: This could be something that isn't a page refresh...
          window.location.reload(true);
        });
      });
      $('#rnf-cache-clear').on('click', function(){
        var data = {
          'action': 'rnf_clear_trip_cache'
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
