<?php
/**
 * Optional Pods field adapter.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_Pods_Adapter implements Atshift_Feed_Builder_Manual_Source_Adapter {
	public function get_id() {
		return 'pods';
	}

	public function get_label() {
		return __( 'Pods field', 'atshift-feed-builder' );
	}

	public function is_available() {
		return function_exists( 'pods' );
	}

	/**
	 * Field names are entered explicitly instead of being enumerated.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields() {
		return array();
	}

	public function get_manual_field_label() {
		return __( 'Pods field name', 'atshift-feed-builder' );
	}

	public function get_manual_field_description() {
		return __( 'Enter the Pods field name. The current post type and post ID are detected automatically.', 'atshift-feed-builder' );
	}

	public function get_values( $post, $field_ids ) {
		if ( ! $this->is_available() || ! $post instanceof WP_Post ) {
			return array();
		}

		$output = array();
		ob_start();

		try {
			$pod = pods( $post->post_type, $post->ID );
			if ( ! is_object( $pod ) || ! is_callable( array( $pod, 'field' ) ) ) {
				ob_end_clean();
				return $output;
			}

			foreach ( $field_ids as $field_id ) {
				$key = sanitize_key( $field_id );
				if ( ! self::is_allowed_key( $key ) ) {
					continue;
				}

				$value = $pod->field( $key );
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
