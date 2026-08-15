<?php
/**
 * Values shared by all output formats.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_Normalizer {
	const MAX_DEPTH = 6;
	const MAX_ITEMS = 200;

	/**
	 * Normalize a value while preserving useful scalar and collection types.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  Declared field type.
	 * @param int    $depth Current recursion depth.
	 * @return mixed
	 */
	public static function normalize( $value, $type = 'string', $depth = 0 ) {
		if ( $depth >= self::MAX_DEPTH ) {
			return null;
		}

		if ( is_wp_error( $value ) || is_resource( $value ) ) {
			return null;
		}

		if ( null === $value ) {
			return null;
		}

		if ( is_object( $value ) ) {
			if ( $value instanceof JsonSerializable ) {
				$value = $value->jsonSerialize();
			} else {
				$value = get_object_vars( $value );
			}
		}

		if ( is_array( $value ) ) {
			$normalized = array();
			$count      = 0;

			foreach ( $value as $key => $item ) {
				if ( $count >= self::MAX_ITEMS ) {
					break;
				}

				$normalized_key                = is_int( $key ) ? $key : sanitize_key( (string) $key );
				$normalized[ $normalized_key ] = self::normalize( $item, 'auto', $depth + 1 );
				++$count;
			}

			return $normalized;
		}

		switch ( $type ) {
			case 'boolean':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN );

			case 'integer':
				return (int) $value;

			case 'number':
				return is_numeric( $value ) ? (float) $value : null;

			case 'url':
			case 'image':
				$url = esc_url_raw( (string) $value );
				return '' === $url ? null : $url;

			case 'html':
				return wp_kses_post( (string) $value );

			case 'auto':
				if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
					return $value;
				}
				break;
		}

		return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : null;
	}
}
