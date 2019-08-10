<?php
/*
 * Plugin Name: RNF Postie Extensions
 * Description: Provides overrides and filters for posts created by email.
 * Version: 0.1
 * Author: Taylor Smith
 */

function rnf_postie_inreach_author($email) {
  // @TODO: The site won't post from just anybody. Rather than allow for
  // anonymous posting, I'm going to "whitelist" inReach by changing the inReach
  // email address to my own. (Also to-do: do this by WP lookup, not by putting
  // my email address in the codebase...). Docs indicate the $email parameter is
  // single email address.
  return $email;
}
add_filter('postie_filter_email', 'rnf_postie_inreach_author');

function rnf_postie_inreach_content_clean($post, $headers) {
  // @TODO: The inReach email may include personally identifying info or the
  // ability to open 2-way messages. Check if the post is an inReach post
  // something in ($headers) and clean anything from $post['post_content'] as
  // necessary. See https://developer.wordpress.org/reference/functions/wp_insert_post/
  return $post;
}
add_filter('postie_post_before', 'rnf_postie_inreach_content_clean', 10, 2);

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
