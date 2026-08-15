<?php
/**
 * Optional Meta Box adapter.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_Meta_Box_Adapter implements Atshift_Feed_Builder_Manual_Source_Adapter {
	public function get_id() {
		return 'metabox';
	}

	public function get_label() {
		return __( 'Meta Box field', 'atshift-feed-builder' );
	}

	public function is_available() {
		return function_exists( 'rwmb_get_value' );
	}

	public function get_fields() {
		return array();
	}

	public function get_manual_field_label() {
		return __( 'Meta Box field ID', 'atshift-feed-builder' );
	}

	public function get_manual_field_description() {
		return __( 'Enter the Meta Box field ID. Standard post storage is supported.', 'atshift-feed-builder' );
	}

	public function get_values( $post, $field_ids ) {
		if ( ! $this->is_available() || ! $post instanceof WP_Post ) {
			return array();
		}

		$output = array();
		ob_start();
		try {
			foreach ( $field_ids as $field_id ) {
				$key = sanitize_key( $field_id );
				if ( ! self::is_allowed_key( $key ) ) {
					continue;
				}
				$value = rwmb_get_value( $key, array(), $post->ID );
				if ( null !== $value && false !== $value && '' !== $value && array() !== $value ) {
					$output[ $key ] = $value;
				}
			}
		} catch ( Throwable $error ) {
			$output = array();
		}
		ob_end_clean();

		return $output;
	}

	public static function is_allowed_key( $key ) {
		return Atshift_Feed_Builder_Post_Meta_Adapter::is_allowed_key( $key );
	}
}
