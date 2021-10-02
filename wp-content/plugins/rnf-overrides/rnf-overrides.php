<?php
/*
 * Plugin Name: RNF Overrides
 * Description: Limited-scope overrides to tweak defaults and other functionality
 * Version: 0.1
 * Author: Taylor Smith
 */

/**
 * Implements admin_menu to add the options page to the sidebar menu for admins.
 * See rnf-geo-settings.php for the output of this page.
 */
function rnf_overrides_add_admin_menu() {
  add_menu_page('RNF Options', 'RNF Options', 'edit_posts', 'rnf-overrides', 'rnf_overrides_admin_page', 'dashicons-location-alt', 78);
}
add_action('admin_menu', 'rnf_overrides_add_admin_menu', 5);
require_once "rnf-overrides-admin.php";

/**
 * Implements shortcode_atts_{$shortcode} filter to do an override on all
 * galleries to have links points to media files instead of attachment pages.
 * Legacy posts from Tumblr all link to attachment pages and that's hundreds of
 * images, and also this will just make it easier to ensure consistency on
 * hastily uploaded stuff from mobile.
 */
function rnf_gallery_overrides($out, $pairs, $atts, $shortcode) {
  $out['link'] = 'file';
  return $out;
};
add_filter('shortcode_atts_gallery', 'rnf_gallery_overrides', 10, 4);

/**
 * Implements pre_get_posts on category pages to flip the chrono order so
 * they can be read oldest-to-newest. Exclude tech-notes from that because
 * it's the only real blog category that newest-first is best.
 */
function rnf_trip_archives_in_chorno(&$query) {
  if ($query->is_category) {
    $term = get_queried_object();

    if ($term->slug != 'tech-notes') {
      $query->set( 'order', 'ASC' );
    }
  }
}
add_filter('pre_get_posts', 'rnf_trip_archives_in_chorno');

/**
 * Implements get_the_archive_title filter to override the title of archive
 * pages so that we can swap the "Category:" prefix with something more
 * specific.
 */
function rnf_overrides_archive_title_alter($title) {
  if ( is_category() ) {
    // @TODO: This is repeated from the function above; abstract/DRY?
    $term = get_queried_object();
    $trip_id = get_term_meta($term->term_id, 'rnf_geo_trip_id', true);

    if (is_numeric($trip_id) && (int) $trip_id > 0) {
      $title = single_cat_title( '', false );
    }
  }
  return $title;
}
add_filter('get_the_archive_title', 'rnf_overrides_archive_title_alter');

/**
 * Add embed handlers for a couple sites I link to a lot on here.
 * Informed by https://github.com/scottmac/opengraph/blob/master/OpenGraph.php
 * Disclaimer: This isn't the most awesome way to do this ever...
 */
function rnf_overrides_oembed_handler($matches, $attr, $url, $rawattr) {
  // We'll store these fully rendered in the transient cache:
  $cache_name = "rnf_og_embed_" . md5($url);

  if (strpos($url, 'kinja') !== false) {
    $render = array();
    $render[] = "<div class='rnf-card'>";
    $render[] = "<p class='rnf-card-text'>";
    $render[] = "<span class='rnf-card-link'>{$url}</span>";
    $render[] = "<em>Kinja has deleted its OppositeLock and Overland communities.</em>";
    $render[] = "<span class='rnf-card-citation'>";
    $render[] = "Content Unavailable";
    $render[] = "</span>";
    $render[] = "</p>";
    $render[] = "</div>";
    $output = implode(' ', $render);
    return apply_filters('embed_rnf_failed', $output, $matches, $attr, $url, $rawattr);
  }


  // Attempt to fetch the transient cache version and use it if we get one:
  if (false !== ($value = get_transient($cache_name)) ) {
    // We got a response from the cache and it is current:
    return apply_filters('embed_rnf', $value, $matches, $attr, $url, $rawattr);
  }

  // We didn't, continue on:
  try {
    $response = wp_remote_get($url, array());

    $old_libxml_error = libxml_use_internal_errors(true);

    if ($response['response']['code'] !== 200) {
      throw new Exception('Content unavailable');
    }

    $doc = new DOMDocument();
    $doc->loadHTML($response['body']);

    libxml_use_internal_errors($old_libxml_error);
  }
  catch (WP_Error | Throwable | Exception $e) {
    // Handle general failure of "can't get and parse the content of that page"
    // by returning the URL as a link. We'll add an optional filter in case I
    // want to dress that up later.
    $render = array();

    $render[] = "<div class='rnf-card'>";

    $render[] = "<p class='rnf-card-text'>";
    $render[] = "<a href='{$url}' class='rnf-card-link'>{$url}</a>";

    $render[] = "<span class='rnf-card-citation'>";
    $render[] = "Could not Embed";
    $render[] = "</span>";
    $render[] = "</p>";

    $render[] = "</div>";

    $output = implode(' ', $render);


    // In case this was a resolvable condition (timeout, remote content being
    // edited), cache this failure to keep the site moving in the short-term,
    // but only for an hour so it can hopefully resolve itself.
    set_transient($cache_name, $output, HOUR_IN_SECONDS);
    return apply_filters('embed_rnf_failed', $output, $matches, $attr, $url, $rawattr);
  }

  $title_tags = $doc->getElementsByTagName('title');
  $meta_tags = $doc->getElementsByTagName('meta');

  $title = trim($title_tags->item(0)->nodeValue);
  $meta = array();

  foreach($meta_tags as $tag) {
    if ($tag->hasAttribute('property') && strpos($tag->getAttribute('property'), 'og:') === 0) {
      $key = strtr(substr($tag->getAttribute('property'), 3), '-', '_');
      $meta[$key] = $tag->getAttribute('content');
    }

    // For pages which use "value" isntead of "content" for the og data (which is wrong, but in the wild)
    if ($tag->hasAttribute('value') && $tag->hasAttribute('property') && strpos($tag->getAttribute('property'), 'og:') === 0) {
      $key = strtr(substr($tag->getAttribute('property'), 3), '-', '_');
      $meta[$key] = $tag->getAttribute('value');
    }

    // Handle the author, if set
    if ($tag->hasAttribute('name') && $tag->getAttribute('name') == 'author') {
      $meta['author'] = $tag->getAttribute('content');
    }
  }

  $data = array(
    'src'    => isset($meta['image:secure_url']) ? $meta['image:secure_url'] :
                  (isset($meta['image'])           ? $meta['image']            : false),
    'height' => isset($meta['image:height'])     ? $meta['image:height']     : false,
    'width'  => isset($meta['image:width'])      ? $meta['image:width']      : false,
    'title'  => isset($meta['title'])            ? $meta['title']            : $title,
    'site'   => isset($meta['site_name'])        ? $meta['site_name']        : false,
    'author' => isset($meta['author'])           ? $meta['author']           : false,
  );

  $render = array();

  $render[] = "<div class='rnf-card'>";

  if (!empty($data['src'])) {
    $render[] = "<a href='{$url}'>";
    $render[] = "<img src='{$data['src']}'";
    if (!empty($data['height'] && !empty($data['width']))) {
      $render[] = "height='{$data['height']}' width ='{$data['width']}'";
    }
    $render[] = "/ >";
    $render[] = "</a>";
  }

  $render[] = "<p class='rnf-card-text'>";
  if (!empty($data['title'])) {
    $render[] = "<a href='{$url}' class='rnf-card-link'>{$data['title']}</a>";
  }

  if (!empty($data['author'] || !empty($data['site']))) {
    $render[] = "<span class='rnf-card-citation'>";
    if ($data['author']) $render[] = "{$data['author']}";
    if ($data['author'] && $data['site']) $render[] = "|";
    if ($data['site']) $render[] = "{$data['site']}";
    $render[] = "</span>";
  }
  $render[] = "</p>";

  $render[] = "</div>";

  $output = implode(' ', $render);

  // Save for use later:
  set_transient($cache_name, $output, WEEK_IN_SECONDS);

  return apply_filters('embed_rnf', $output, $matches, $attr, $url, $rawattr);
}
wp_embed_register_handler('alltrails', '#https?://www.alltrails.com.+#', 'rnf_overrides_oembed_handler', 5);
wp_embed_register_handler('oppo', '#https?://oppositelock.kinja.com.+#', 'rnf_overrides_oembed_handler', 5);
wp_embed_register_handler('oande', '#https?://overland.kinja.com.+#', 'rnf_overrides_oembed_handler', 5);
wp_embed_register_handler('tsc', '#https?://(www.)?tsmithcreative.com.+#', 'rnf_overrides_oembed_handler', 5);

/**
 * Add a marker to the admin bar with an environment label.
 * Inspired by https://wordpress.org/plugins/show-environment-in-admin-bar
 */
function rnf_overrides_admin_bar_env_note($admin_menu_bar) {
  $environment = FALSE;
  $class = "rnf-env-";

  if ($_SERVER['HTTP_HOST'] == "www.routenotfound.com" || $_SERVER['HTTP_HOST'] == "routenotfound.com") {
    $environment = "Production";
    $class .= "prod";
  } else if ($_SERVER['HTTP_HOST'] == "staging.routenotfound.com") {
    $environment = "Staging";
    $class .= "staging";
  } else {
    // @TODO: This is a bold assumption to make an an unqualified else{} statement...
    $environment = "Dev";
    $class .= "dev";
  }

  if ($environment) {
    $admin_menu_bar->add_node(array(
      'id' => 'rnf-env-marker',
      'title' => $environment,
      'meta'   => array( 'class' => "rnf-env-marker-link {$class}" ),
    ));
  }
}
add_action('admin_bar_menu', 'rnf_overrides_admin_bar_env_note', 1);

function rnf_overrides_admin_bar_styles() {
  wp_register_style('rnf-env-marker-style', plugin_dir_url( __FILE__ ) . 'css/rnf-env-marker.css', RNF_VERSION, false);
  if (is_user_logged_in()) wp_enqueue_style('rnf-env-marker-style');
}
add_action('admin_enqueue_scripts', 'rnf_overrides_admin_bar_styles');
add_action('wp_enqueue_scripts', 'rnf_overrides_admin_bar_styles');

/**
 * Filter the authentication cookie expriation time to extend login
 * validity for a year.
 */
function rnf_overrides_login_session_duration($expire) {
  return YEAR_IN_SECONDS;
}
add_filter('auth_cookie_expiration', 'rnf_overrides_login_session_duration');

// Include external files
include_once "rnf-overrides-drop-wpemoji.php";
