<?php
/*
 * Plugin Name: RNF Postie Extensions
 * Description: Provides overrides and filters for posts created by email.
 * Version: 0.1
 * Author: Taylor Smith
 */

/**
 * Implements admin_menu to add the options page to the sidebar menu for admins.
 * See rnf-postie-settings.php for the output of this page.
 */
function rnf_postie_add_admin_menu() {
  add_submenu_page('rnf-overrides', 'RNF Email and Postie Settings', 'Post by Email', 'manage_options', 'rnf-postie', 'rnf_postie_options_page');
}
add_action('admin_menu', 'rnf_postie_add_admin_menu');
require_once "rnf-postie-settings.php";


/**
 * Filter to whitelist authors by rewriting acceptable email addresses as my own
 * which Postie will then credit to me.
 */
function rnf_postie_inreach_author($email) {
  // So we can inspect the domain, refer to $domain[1]
  $domain = explode('@', $email);

  // Get the whitelisting options
  $options = get_option( 'rnf_postie_settings' );
  $accept_addresses = $options['emails'];
  $accept_domains = $options['domains'];

  // Test the email address and change it to mine if it's allowable.
  if (in_array($email, $accept_addresses) || in_array($domain[1], $accept_domains)) {
    // For a multi-author site, this should be a setting. For me, this is fine.
    $admin = get_userdata(1);
    return $admin->get('user_email');
  }
  return $email;
}
add_filter('postie_filter_email', 'rnf_postie_inreach_author');

/**
 * Clean up the Garmin inReach email by removing a bunch of stuff.
 */
function rnf_postie_inreach_content_clean($post, $headers) {
  // Only process emails via Garmin
  if ($headers["from"]["host"] == "garmin.com") {
    // Let's just take my name out of it, shall we?
    $post["post_title"] = "Update via inReach";

    // Processing message content line-at-a-time.
    $content = explode("  ", $post["post_content"]);
    foreach ($content as $index => &$line) {
      if ($index == 0) {
        // The first line is the message I wrote, pass it on.
        continue;
      }

      if (strpos($line, "View the location") !== false) {
        // This is a line with map and location, dump it
        $line = null;
      }

      if (strpos($line, "send a reply to") !== false) {
        // This is the line with the "view on map and reply" link, and while the
        // map is cool, I don't want to expose the link to reply.
        $line = null;
      }

      if (strpos($line, "sent this message from") !== false) {
        // inReach includes "Eric Bob sent this message from: Lat 30.274075 Lon -97.740579"
        // Swap that around to a Google Maps search and take my name out of it.
        if (preg_match_all('/(-?\d{1,3}\.\d+)/', $line, $coords)) {
          $link_text = "{$coords[0][0]}, {$coords[0][1]}";
          $google_maps_url = "https://www.google.com/maps/search/{$coords[0][0]},{$coords[0][1]}";
          $line = "<p><em>Sent from <a href=\"{$google_maps_url}\">{$link_text}</a>.</em></p>";
        }
      }

      if (strpos($line, "Do not reply directly") !== false || strpos($line, "sent to you using the inReach") !== false) {
        // Two disclaimer / product placement/ad lines.
        $line = null;
      }

      if (strpos($line, "<img src") !== false) {
        // Remove the tracking pixel
        $line = null;
      }

    }

    $post["post_content"] = implode("\n", $content);
  }
  return $post;
}
add_filter('postie_post_before', 'rnf_postie_inreach_content_clean', 10, 2);

/**
 * Look up if there's an active trip with a category when the email is parsed
 * and assign the new post to it. NOTE! Postie backdates posts to the email's
 * own sent date, and this filter evaluates based on the current server time
 * (because that's what rnf_geo_current_trip does). In my use case, these will
 * be the same because cron runs often.
 */
function rnf_postie_default_trip_category($category) {
  // Geo functions are provided by the rnf-geo plugin, check for it:
  if (!function_exists('rnf_geo_current_trip')) {
    return $category;
  }

  // Get the current trip
  $current = rnf_geo_current_trip();

  // If there is one _and_ it has an associated category, pass its ID
  if ($current && !empty($current->wp_category)) {
    return $current->wp_category->term_id;
  }

  return $category;
}
add_filter('postie_category_default', 'rnf_postie_default_trip_category');
