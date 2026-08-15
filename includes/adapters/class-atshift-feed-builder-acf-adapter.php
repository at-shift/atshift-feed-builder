<?php
/**
 * Optional Advanced Custom Fields / Secure Custom Fields adapter.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_ACF_Adapter implements Atshift_Feed_Builder_Manual_Source_Adapter {
	public function get_id() {
		return 'acf';
	}

	public function get_label() {
		return __( 'ACF / Secure Custom Fields field', 'atshift-feed-builder' );
	}

	public function is_available() {
		return function_exists( 'get_field' );
	}

	public function get_fields() {
		return array();
	}

	public function get_manual_field_label() {
		return __( 'ACF / SCF field name', 'atshift-feed-builder' );
	}

	public function get_manual_field_description() {
		return __( 'Enter the ACF or Secure Custom Fields field name or field key.', 'atshift-feed-builder' );
	}

	public function get_values( $post, $field_ids ) {
		return $this->read_fields( $post, $field_ids );
	}

	private function read_fields( $post, $field_ids ) {
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
				$value = get_field( $key, $post->ID, true );
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
