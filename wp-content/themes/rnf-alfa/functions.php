<?php
/**
 * Implements init to drop twentyseventeen's social icons SVGs include in the
 * footer and include my own with just what I'm using.
 */
function rnf_theme_swap_icons() {
  remove_action( 'wp_footer', 'twentyseventeen_include_svg_icons', 9999);
  add_action('wp_footer', 'rnf_include_svg_icons', 9999);
}
add_action('init', 'rnf_theme_swap_icons', 100);

function rnf_include_svg_icons() {
  $svg_icons = get_stylesheet_directory() . '/assets/images/svg-icons.svg';

  if ( file_exists($svg_icons) ) {
    require_once($svg_icons);
  }
}

/**
 * Implements wp_enqueue_scripts to register scripts/styles for my overrides.
 * Currently this is only CSS and JS for Colorbox, so we'll only actually
 * enqueue this stuff on post_gallery filter.
 */
function rnf_theme_register_scripts_and_styles() {
  // I've copied in twentyseventeen's original CSS and modified it.
  // Reregistering it by the same name makes sure that the parent theme's CSS
  // dependencies (mostly for gutenberg blocks) still render.
  wp_deregister_style('twentyseventeen-style');
  wp_register_style('twentyseventeen-style', get_stylesheet_uri(), array(), null);

  // Drop the Libre Franklin, I'm gonna use something else.
  wp_deregister_style('twentyseventeen-fonts');

  wp_register_style('rnf-header-images', get_stylesheet_directory_uri() . '/dist/css/header-images.css', array(), null);
  wp_enqueue_style('rnf-header-images');

  wp_register_style('rnf-hco-typefaces', '//cloud.typography.com/6795652/6519212/css/fonts.css', array(), null);
  wp_enqueue_style('rnf-hco-typefaces');

  // (Own) General site-wide stuff
  wp_register_script('rnf-alfa-js-main', get_stylesheet_directory_uri() . '/js/main.js', array(), null, true);
  wp_enqueue_script('rnf-alfa-js-main');
  wp_register_script('rnf-alfa-js-header-images', get_stylesheet_directory_uri() . '/dist/js/header-images.js', array(), null, true);
  wp_enqueue_script('rnf-alfa-js-header-images');

  // (Own) Media handlers
  wp_register_script('rnf-alfa-js-media', get_stylesheet_directory_uri() . '/js/media.js', array('fancybox-script', 'jquery'), null, true);

  // Was loading this conditionally on `post_gallery` filter, but I haven't
  // figured out how to attach it to Gutenberg blocks yet, and let's face it,
  // this is an entirely photo-driven site, this is actually needed on all
  // pages.
  wp_enqueue_style('fancybox-style');
  wp_enqueue_script('rnf-alfa-js-media');

  // And remove TwentySeventeen stuff we do not need
  wp_dequeue_style('twentyseventeen-ie8');
  wp_dequeue_style('classic-theme-styles');
  wp_dequeue_script('html5');
  wp_dequeue_script('twentyseventeen-global');
  wp_dequeue_script('jquery-scrollto');
  /* Remove twentyseventeen's preconnect for Google Fonts */
  remove_filter('wp_resource_hints', 'twentyseventeen_resource_hints', 10);
}
add_action( 'wp_enqueue_scripts', 'rnf_theme_register_scripts_and_styles', 20 );

/**
 * Implements style_loader_tag filter to rewrite CSS tags after they've been
 * assembled so we can use rel=preload to reduce render-blocking of external
 * CSS.
 */
function rnf_theme_css_preload($html, $handle, $href, $media) {
  // Hitting all the big CSS
  if (in_array($handle, array(
    'rnf-hco-typefaces',
    'rnf-header-images',
    'fancybox-style',
    'mapbox-style',
    'wp-block-library',
    'rnf-geo-style',
    'cloudflare-stream-block-style-css',
    'twentyseventeen-block-style-css',
    'vlp-public',
    ))) {

    // Set rel=preload
    $preload = str_replace('stylesheet', 'preload', $html);

    // And apply once it is loaded
    $resolve = str_replace('/>', "as=\"style\" onload=\"this.rel='stylesheet';this.onload=null;\" /><noscript>{$html}</noscript>", $preload);

    // Return the new tag
    return $resolve;
  }

  // Return anything else unmodified
  return $html;
}
add_filter('style_loader_tag', 'rnf_theme_css_preload', 900, 4);

/**
 * Implements wp_default_scripts to drop jQuery Migrate from pages viewed in
 * this theme. (Or should this go in rnf_overrides and limit to !is_admin?)
 */
function rnf_theme_drop_jqmigrate($scripts) {
  if (isset( $scripts->registered['jquery'] ) ) {
    $script = $scripts->registered['jquery'];

    if ( $script->deps ) { // Check whether the script has any dependencies
      $script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
    }
  }
}
add_action('wp_default_scripts', 'rnf_theme_drop_jqmigrate', 100);

/**
 * Implements wp_default_scripts to move jQuery core to the footer for pages
 * viewed in this theme.
 * See: https://wordpress.stackexchange.com/a/240612
 */
function rnf_theme_move_jq($scripts) {
  // This function would probably only execute on the customizer, but check
  // anyway, this would break admin.
  if (is_admin()) return;

  $scripts->add_data('jquery', 'group', 1);
  $scripts->add_data('jquery-core', 'group', 1);
  // StackOverflow answer also moved jquery-migrate, but I've dropped that.
}
add_action('wp_default_scripts', 'rnf_theme_move_jq');

/**
 * Implements wp_enqueue_scripts to register the scripts and styles for the
 * Colorbox lightbox library which will attach to all image galleries.
 *
 * @TODO: Can this be executed only when needed? Also replace with PhotoSwipe
 */
function rnf_theme_register_lightbox() {
  wp_register_style('fancybox-style', content_url('/vendor/fancyapps/fancybox/dist/jquery.fancybox.min.css'), array(), null);
  wp_register_script('fancybox-script', content_url('/vendor/fancyapps/fancybox/dist/jquery.fancybox.min.js'), array('jquery'), null, true);
}
add_action( 'wp_enqueue_scripts', 'rnf_theme_register_lightbox', 10 );

/**
 * Output a post date as a permalink. Overrides twentyseventeen's default to also
 * add a "show on map" link in the same place.
 */
function twentyseventeen_time_link() {
  $time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
  if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
    $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
  }

  // Put together the date permalink:
  $time_string = sprintf( $time_string,
    get_the_date( DATE_W3C ),
    get_the_date('d M Y'),
    get_the_modified_date( DATE_W3C ),
    get_the_modified_date('d M Y')
  );

  // Wrap the time string in a link, and preface it with 'Posted on'.
  $esc_path = esc_url( get_permalink() );
  $header = "// <a href='{$esc_path}' rel='bookmark'>{$time_string}</a>";

  // Get the author name; wrap it in a link.
  $byline = sprintf(
    __( 'by %s', 'twentyseventeen' ),
    '<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . get_the_author() . '</a></span>'
  );


  $header .= ' ' . $byline;


  // Now determine if we should show a link to the post on a map. That logic
  // is determined in rnf-geo.php and is: is post _about_ a trip _during_ a trip?
  $post = get_post();
  if (isset($post->rnf_geo_post_is_on_trip) && $post->rnf_geo_post_is_on_trip === TRUE) {
    // Wrap the time string in a link, and preface it with 'Posted on'.
    $timestamp = get_post_time('U', true);

    $map_link_text = (!empty($post->rnf_geo_city)) ? $post->rnf_geo_city : "Map";
    $map_icon = "<span class='rnf-map-jump-icon'>" . twentyseventeen_get_svg( array( 'icon' => 'thumb-tack' ) ) . "</span>";

    $header .= " / " . $map_icon .  "<a href='#' class='rnf-map-jump' data-timestamp='{$timestamp}'>{$map_link_text}</a>";
  }

  return $header;
}

/**
 * Replace twentyseventeen_posted_on() which is called on single post headers with
 * the above function to show an author name with the map link in indexes.
 */
function twentyseventeen_posted_on() {
  print twentyseventeen_time_link();
}

function rnf_theme_add_map_to_menu($items) {
  $link = '<li class="menu-item-map-toggle"><a href="#" class="menu-item-map-toggle-link">Show Map</a></li>';
  return $items . $link;
}
add_filter('wp_nav_menu_items', 'rnf_theme_add_map_to_menu', 100);

/**
 * Overrides twentyseventeen's default post edit link because it took a bunch of
 * CSS to make a space in front of it when it could just be a space.
 */
function twentyseventeen_edit_link($separator = TRUE) {
  edit_post_link(
    "Edit",
    ($separator ? ' / ' : ''),
    ''
  );
}

/**
 * Prints HTML with meta information for the categories, tags and comments.
 */
function twentyseventeen_entry_footer() {
  /* translators: Used between list items, there is a space after the comma. */
  $separate_meta = __( ', ', 'twentyseventeen' );

  // Get Categories for posts.
  $categories_list = get_the_category_list( $separate_meta );

  // Get Tags for posts.
  $tags_list = get_the_tag_list( '', $separate_meta );

  // We don't want to output .entry-footer if it will be empty, so make sure its not.
  if ( ( ( twentyseventeen_categorized_blog() && $categories_list ) || $tags_list ) || get_edit_post_link() ) {

    echo '<footer class="entry-footer">';

    if ( 'post' === get_post_type() ) {
      if ( ( $categories_list && twentyseventeen_categorized_blog() ) || $tags_list ) {
        echo '<span class="cat-tags-links">';

        // Make sure there's more than one category before displaying.
        if ( $categories_list && twentyseventeen_categorized_blog() ) {
          echo '<span class="cat-links"><span class="screen-reader-text">' . __( 'Categories', 'twentyseventeen' ) . '</span>' . $categories_list . '</span>';
        }

        if ( $tags_list && ! is_wp_error( $tags_list ) ) {
          echo '<span class="tags-links"><span class="screen-reader-text">' . __( 'Tags', 'twentyseventeen' ) . '</span>' . $tags_list . '</span>';
        }

        echo '</span>';
      }
    }

    twentyseventeen_edit_link(FALSE);

    echo '</footer> <!-- .entry-footer -->';
  }
}
