<?php
/**
 * Data source adapter contract.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Atshift_Feed_Builder_Source_Adapter {
	/**
	 * Return the stable adapter identifier.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Return the human-readable adapter label.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Check whether the source plugin is available.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Return selectable field definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields();

	/**
	 * Return selected values for a post.
	 *
	 * @param WP_Post       $post      Source post.
	 * @param array<string> $field_ids Adapter-local field IDs.
	 * @return array<string,mixed>
	 */
	public function get_values( $post, $field_ids );
}

/**
 * Contract for adapters whose field key is entered manually.
 */
interface Atshift_Feed_Builder_Manual_Source_Adapter extends Atshift_Feed_Builder_Source_Adapter {
	/**
	 * Return the label shown above the field-key input.
	 *
	 * @return string
	 */
	public function get_manual_field_label();

	/**
	 * Return the help text shown below the field-key input.
	 *
	 * @return string
	 */
	public function get_manual_field_description();

	/**
	 * Check whether a manually entered field key is safe to use.
	 *
	 * @param string $key Field key.
	 * @return bool
	 */
	public static function is_allowed_key( $key );
}
