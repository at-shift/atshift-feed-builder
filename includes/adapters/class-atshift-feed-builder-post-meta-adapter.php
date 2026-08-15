<?php
/**
 * Generic WordPress post meta adapter.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_Post_Meta_Adapter implements Atshift_Feed_Builder_Manual_Source_Adapter {
	public function get_id() {
		return 'postmeta';
	}

	public function get_label() {
		return __( 'WordPress custom field (post_meta)', 'atshift-feed-builder' );
	}

	public function is_available() {
		return true;
	}

	/**
	 * Meta keys are entered explicitly instead of being enumerated.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields() {
		return array();
	}

	public function get_manual_field_label() {
		return __( 'Custom field key', 'atshift-feed-builder' );
	}

	public function get_manual_field_description() {
		return __( 'Enter a public post_meta key. This covers Custom Field Template and other plugins that use standard post meta.', 'atshift-feed-builder' );
	}

	public function get_values( $post, $field_ids ) {
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$output = array();
		foreach ( $field_ids as $field_id ) {
			$key = sanitize_key( $field_id );
			if ( ! self::is_allowed_key( $key ) ) {
				continue;
			}

			$values = get_post_meta( $post->ID, $key, false );
			$value  = 1 === count( $values ) ? reset( $values ) : $values;
			if ( null !== $value && '' !== $value && array() !== $value ) {
				$output[ $key ] = $value;
			}
		}

		return $output;
	}

	public static function is_allowed_key( $key ) {
		$key = (string) $key;
		if ( '' === $key || '_' === $key[0] || sanitize_key( $key ) !== $key ) {
			return false;
		}

		return ! preg_match( '/(?:password|passwd|passkey|session|capabilit|user_level|token|secret|api[_-]?key)/i', $key )
			&& ! preg_match( '/(?:^|[_-])auth(?:entication|orization)?(?:[_-]|$)/i', $key );
	}
}
