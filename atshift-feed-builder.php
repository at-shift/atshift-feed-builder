<?php
/**
 * Plugin Name: atshift Feed Builder
 * Plugin URI: https://upf.at-shift.net/feed-builder/
 * Description: Build safe RSS and JSON feeds from WordPress posts, custom fields, and author profile data.
 * Version: 0.3.3
 * Author: @shift
 * Author URI: https://cfs.at-shift.net/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: atshift-feed-builder
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ATSHIFT_FEED_BUILDER_VERSION', '0.3.3' );
define( 'ATSHIFT_FEED_BUILDER_FILE', __FILE__ );
define( 'ATSHIFT_FEED_BUILDER_DIR', plugin_dir_path( __FILE__ ) );
define( 'ATSHIFT_FEED_BUILDER_URL', plugin_dir_url( __FILE__ ) );

require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/interface-atshift-feed-builder-source-adapter.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/class-atshift-feed-builder-normalizer.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/class-atshift-feed-builder-schema.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/adapters/class-atshift-feed-builder-post-meta-adapter.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/adapters/class-atshift-feed-builder-pods-adapter.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/adapters/class-atshift-feed-builder-acf-adapter.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/adapters/class-atshift-feed-builder-meta-box-adapter.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/adapters/class-atshift-feed-builder-carbon-fields-adapter.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/adapters/class-atshift-feed-builder-fields-adapter.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/adapters/class-atshift-feed-builder-upf-adapter.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/class-atshift-feed-builder-renderer.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/class-atshift-feed-builder-admin.php';
require_once ATSHIFT_FEED_BUILDER_DIR . 'includes/class-atshift-feed-builder-plugin.php';

register_activation_hook( __FILE__, array( 'Atshift_Feed_Builder_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Atshift_Feed_Builder_Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Atshift_Feed_Builder_Plugin::instance();
	},
	20
);
