<?php
/**
 * The base configuration for WordPress MODIFIED by tsmith to support Composer
 * only installation and update. This was pulled from WP 5.0's sample and edited
 * by specifying the Composer autoload (in case I add any libraries) and
 * supporting the altered directory structure ('wp' for core with an external
 * 'wp-content').
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

/* Additions for this particular project and its architecture */
require __DIR__ . '/wp-content/vendor/autoload.php';
  // In case I use Composer libraries
define( 'WP_CONTENT_DIR', dirname(__FILE__) . '/wp-content' );
  // To handle wp-content outside wp root
define( 'DISALLOW_FILE_MODS', true );
  // Disallow edits and updates on themes and plugins; it's all handled in code
  // so hide the editor in the admin to keep it out of the way.

if ($_SERVER['HTTP_HOST'] !== 'www.routenotfound.com') {
  // All non-prod instances
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', false);
  define('WP_DEBUG_DISPLAY', true);
} else {
  // Prod
  define('WP_DEBUG', false);
  define('FORCE_SSL_ADMIN', true);
}

define('DISABLE_WP_CRON', true);

define('RNF_VERSION', trim(exec('git describe --always')));

/** Changes location where Autoptimize stores optimized files */
define('AUTOPTIMIZE_CACHE_CHILD_DIR','/uploads/autoptimize/');

/* MySQL settings - Defaulting to the environment vars from rnf-deploy */
/** The name of the database for WordPress */
define('DB_NAME', $_ENV['MS_DB']);

/** MySQL database username */
define('DB_USER', $_ENV['MS_USER']);

/** MySQL database password */
define('DB_PASSWORD', $_ENV['MS_PASSWD']);

/** MySQL hostname */
define('DB_HOST', $_ENV['MS_HOST']);

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
require_once(__DIR__ . '/salt.php');


/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);
define( 'DISALLOW_FILE_MODS', true );
/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
