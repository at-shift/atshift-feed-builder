<?php
/**
 * Optional Carbon Fields adapter.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_Carbon_Fields_Adapter implements Atshift_Feed_Builder_Manual_Source_Adapter {
	public function get_id() {
		return 'carbon';
	}

	public function get_label() {
		return __( 'Carbon Fields field', 'atshift-feed-builder' );
	}

	public function is_available() {
		return function_exists( 'carbon_get_post_meta' );
	}

	public function get_fields() {
		return array();
	}

	public function get_manual_field_label() {
		return __( 'Carbon Fields field name', 'atshift-feed-builder' );
	}

	public function get_manual_field_description() {
		return __( 'Enter the Carbon Fields post meta field name.', 'atshift-feed-builder' );
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
				$value = carbon_get_post_meta( $post->ID, $key );
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
