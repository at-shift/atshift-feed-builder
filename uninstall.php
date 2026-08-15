<?php
/**
 * Uninstall intentionally preserves feed configurations.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Feed definitions are retained to avoid accidental loss of publishing policy.
