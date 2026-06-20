<?php
/*
 * Plugin Name: RNF Analytics
 * Description: Provides Google Analytics integration
 * Version: 0.1
 * Author: Taylor Smith
 */

/**
 * Implements admin_menu to add the options page to the Settings menu for admins.
 */
function rnf_analytics_add_admin_menu() {
  add_submenu_page('rnf-overrides', 'RNF Analytics Settings', 'Analytics', 'manage_options', 'rnf-analytics-settings', 'rnf_analytics_options_page');
}
add_action('admin_menu', 'rnf_analytics_add_admin_menu');

function rnf_analytics_settings_init() {
  register_setting('rnf_analytics_settings', 'rnf_ga');

  add_settings_section(
    'rnf_ga',
    'Google Analytics Settings',
    'rnf_ga_section_callback',
    'rnf_analytics_settings'
  );

  add_settings_field(
    'rnf_ga_prop_id',
    'Property/Tracking ID',
    'rnf_ga_prop_id_render',
    'rnf_analytics_settings',
    'rnf_ga'
  );

  add_settings_field(
    'rnf_ga_track_admins',
    'Track Admins?',
    'rnf_ga_track_admins_render',
    'rnf_analytics_settings',
    'rnf_ga'
  );
}
add_action('admin_init', 'rnf_analytics_settings_init');


function rnf_analytics_options_page() {
  ?>
  <form action='options.php' method='post'>

    <h1>RNF Analytics Configuration</h1>

    <?php
    settings_fields( 'rnf_analytics_settings' );
    do_settings_sections( 'rnf_analytics_settings' );
    submit_button();
    ?>

  </form>
  <?php
}


function rnf_ga_section_callback() {
}

function rnf_ga_prop_id_render() {
  $options = get_option( 'rnf_ga' );
  $value = isset($options['rnf_ga_prop_id']) ? esc_attr($options['rnf_ga_prop_id']) : '';
  echo "<input type='text' name='rnf_ga[rnf_ga_prop_id]' value='{$value}'>";
}

function rnf_ga_track_admins_render() {
  $options = get_option( 'rnf_ga' );

  // Note: This bit of logic means that the default would be for this checkbox to be unchecked (my preference)
  $value = isset($options['rnf_ga_track_admins']) ? esc_attr($options['rnf_ga_track_admins']) : null;
  $value = $value ? 'checked' : null;

  echo "<input type='checkbox' name='rnf_ga[rnf_ga_track_admins]' {$value}>";
  echo " <em>Applies to Contributor-level and higher users.</em>";
}

function rnf_analytics_ga_tracking_code_output() {
  $options = get_option('rnf_ga');
  $property = isset($options['rnf_ga_prop_id']) ? esc_attr($options['rnf_ga_prop_id']) : false;

  // So because this is a checkbox, either it will be saved "true" or it will not
  // be set in the option object.
  $track_admins = isset($options['rnf_ga_track_admins']) ? (bool) $options['rnf_ga_track_admins'] : null;

  $output = false;
  $is_admin = current_user_can('edit_posts');

  if (!$property) {
    // We have no property ID; bail.
    return;
  }

  if ($track_admins === TRUE) {
    // We know we are supposed to track admins. So logged in or not, output.
    $output = true;
  }

  if ($track_admins !== TRUE && !$is_admin) {
    // We aren't tracking admins (for reasons above, this could be NULL or FALSE)
    // and this user is not an admin.
    $output = true;
  }

  if (!$output) {
    // We're not going to output; bail.
    return;
  }

  ?>
    <!-- Cloudflare Web Analytics -->
      <script defer src='https://static.cloudflareinsights.com/beacon.min.js' data-cf-beacon='{"token": "8018535b62904b5093fe4b190b14d55f"}'></script>
    <!-- End Cloudflare Web Analytics -->
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $property ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?php echo $property ?>');
    </script>
  <?php

}
add_action('wp_footer', 'rnf_analytics_ga_tracking_code_output', 1000);
