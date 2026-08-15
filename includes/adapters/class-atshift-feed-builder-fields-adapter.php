<?php
/**
 * atshift Fields / CFS-compatible adapter.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_Fields_Adapter implements Atshift_Feed_Builder_Source_Adapter {
	/** @var array<int,array<string,mixed>> */
	private $value_cache = array();

	/** @var array<string,array<string,mixed>>|null */
	private $fields_cache;

	public function get_id() {
		return 'fields';
	}

	public function get_label() {
		return __( 'atshift Fields', 'atshift-feed-builder' );
	}

	public function is_available() {
		return function_exists( 'CFS' ) && is_object( CFS() ) && is_callable( array( CFS(), 'find_fields' ) );
	}

	public function get_fields() {
		if ( ! $this->is_available() ) {
			return array();
		}

		if ( null !== $this->fields_cache ) {
			return $this->fields_cache;
		}

		$definitions    = array();
		$layout_types   = array( 'tab', 'group', 'accordion', 'conditional', 'external_metabox' );
		$native_types   = array( 'post_title', 'post_content', 'post_publish', 'post_categories', 'post_tags', 'featured_image' );
		$fields         = CFS()->find_fields();

		foreach ( (array) $fields as $field ) {
			$name = isset( $field['name'] ) ? sanitize_key( $field['name'] ) : '';
			$type = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';

			if ( '' === $name || isset( $definitions[ $name ] ) || in_array( $type, $layout_types, true ) || in_array( $type, $native_types, true ) || $this->is_hard_denied( $name ) ) {
				continue;
			}

			$definitions[ $name ] = array(
				'id'        => $name,
				'key'       => $name,
				'label'     => isset( $field['label'] ) && '' !== $field['label'] ? (string) $field['label'] : $name,
				'type'      => $this->map_type( $type ),
				'raw_type'  => $type,
				'group_id'  => isset( $field['group_id'] ) ? absint( $field['group_id'] ) : 0,
				'sensitive' => $this->looks_sensitive( $name, $field['label'] ?? '' ),
			);
		}

		$this->fields_cache = $definitions;
		return $this->fields_cache;
	}

	public function get_values( $post, $field_ids ) {
		if ( ! $this->is_available() || ! $post instanceof WP_Post ) {
			return array();
		}

		if ( ! isset( $this->value_cache[ $post->ID ] ) ) {
			$this->value_cache[ $post->ID ] = $this->read_values( $post->ID );
		}

		$definitions = $this->get_fields();
		$output      = array();

		foreach ( $field_ids as $field_id ) {
			$field_id = sanitize_key( $field_id );

			if ( ! isset( $definitions[ $field_id ] ) || ! array_key_exists( $field_id, $this->value_cache[ $post->ID ] ) ) {
				continue;
			}

			$value = Atshift_Feed_Builder_Normalizer::normalize(
				$this->value_cache[ $post->ID ][ $field_id ],
				$definitions[ $field_id ]['type']
			);

			if ( null !== $value && '' !== $value && array() !== $value ) {
				$output[ $definitions[ $field_id ]['key'] ] = $value;
			}
		}

		return $output;
	}

	/**
	 * Keep notices from legacy or malformed field values out of public feeds.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	private function read_values( $post_id ) {
		$values = array();
		ob_start();

		try {
			$values = (array) CFS()->get( false, $post_id, array( 'format' => 'api' ) );
		} catch ( Throwable $error ) {
			$values = array();
		}

		ob_end_clean();
		return $values;
	}

	private function map_type( $type ) {
		$map = array(
			'number'      => 'number',
			'true_false'  => 'boolean',
			'file'        => 'url',
			'url'         => 'url',
			'hyperlink'   => 'url',
			'wysiwyg'     => 'html',
			'embed_code'  => 'html',
			'loop'        => 'list',
			'checkbox'    => 'list',
			'gallery'     => 'list',
			'relationship'=> 'list',
			'user'        => 'list',
		);

		return $map[ $type ] ?? 'string';
	}

	private function looks_sensitive( $key, $label ) {
		return (bool) preg_match( '/(?:email|e-mail|phone|telephone|tel|address|postal|zip)/i', $key . ' ' . $label );
	}

	private function is_hard_denied( $key ) {
		return (bool) preg_match( '/(?:password|passwd|passkey|session|capabilit|user_level|token|secret|api[_-]?key)/i', $key )
			|| (bool) preg_match( '/(?:^|[_-])auth(?:entication|orization)?(?:[_-]|$)/i', $key );
	}
}
