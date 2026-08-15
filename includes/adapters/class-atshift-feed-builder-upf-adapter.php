<?php
/**
 * atshift User Profile Fields adapter.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_UPF_Adapter implements Atshift_Feed_Builder_Source_Adapter {
	/** @var array<string,array<string,mixed>>|null */
	private $fields_cache;

	public function get_id() {
		return 'upf';
	}

	public function get_label() {
		return __( 'atshift User Profile Fields (post author)', 'atshift-feed-builder' );
	}

	public function is_available() {
		return class_exists( 'Atshift_UPF_Plugin' ) && is_callable( array( 'Atshift_UPF_Plugin', 'get_fields' ) );
	}

	public function get_fields() {
		if ( ! $this->is_available() ) {
			return array();
		}

		if ( null !== $this->fields_cache ) {
			return $this->fields_cache;
		}

		$definitions = array();
		$excluded     = array(
			'group',
			'box',
			'conditional',
			'accordion',
			'passkeys',
			'core_username',
			'core_email',
			'core_password',
			'core_sessions',
			'core_application_passwords',
			'core_role',
			'core_notification',
			'core_submit_button',
			'core_visual_editor',
			'core_syntax_highlighting',
			'core_keyboard_shortcuts',
			'core_toolbar',
			'core_admin_color',
		);

		foreach ( (array) Atshift_UPF_Plugin::get_fields() as $field ) {
			$key  = isset( $field['key'] ) ? sanitize_key( $field['key'] ) : '';
			$type = isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text';

			if ( '' === $key || in_array( $type, $excluded, true ) || $this->is_hard_denied( $key ) ) {
				continue;
			}

			$definitions[ $key ] = array(
				'id'        => $key,
				'key'       => $key,
				'label'     => isset( $field['label'] ) && '' !== $field['label'] ? (string) $field['label'] : $key,
				'type'      => $this->map_type( $type ),
				'raw_type'  => $type,
				'sensitive' => $this->looks_sensitive( $key, $field['label'] ?? '', $type ),
			);
		}

		$this->fields_cache = $definitions;
		return $this->fields_cache;
	}

	public function get_values( $post, $field_ids ) {
		if ( ! $this->is_available() || ! $post instanceof WP_Post || ! $post->post_author ) {
			return array();
		}

		$user        = get_userdata( $post->post_author );
		$definitions = $this->get_fields();
		$output      = array();

		if ( ! $user ) {
			return $output;
		}

		foreach ( $field_ids as $field_id ) {
			$field_id = sanitize_key( $field_id );

			if ( ! isset( $definitions[ $field_id ] ) ) {
				continue;
			}

			$definition = $definitions[ $field_id ];
			$value      = $this->get_user_value( $user, $field_id, $definition['raw_type'] );
			$value      = Atshift_Feed_Builder_Normalizer::normalize( $value, $definition['type'] );

			if ( null !== $value && '' !== $value && array() !== $value ) {
				$output[ $definition['key'] ] = $value;
			}
		}

		return $output;
	}

	private function get_user_value( $user, $key, $type ) {
		$meta_map = array(
			'core_first_name'   => 'first_name',
			'core_last_name'    => 'last_name',
			'core_nickname'     => 'nickname',
			'core_language'     => 'locale',
			'core_bio'          => 'description',
		);

		if ( isset( $meta_map[ $type ] ) ) {
			return get_user_meta( $user->ID, $meta_map[ $type ], true );
		}

		if ( 'core_display_name' === $type ) {
			return $user->display_name;
		}

		if ( 'core_website' === $type ) {
			return $user->user_url;
		}

		if ( 'core_profile_picture' === $type ) {
			return get_avatar_url( $user->ID );
		}

		if ( function_exists( 'atshift_upf_get_user_field' ) ) {
			return atshift_upf_get_user_field( $key, $user->ID );
		}

		return get_user_meta( $user->ID, '_atshift_upf_' . $key, true );
	}

	private function map_type( $type ) {
		$map = array(
			'number'               => 'number',
			'checkbox'             => 'boolean',
			'url'                  => 'url',
			'image'                => 'image',
			'core_website'         => 'url',
			'core_profile_picture' => 'image',
		);

		return $map[ $type ] ?? 'string';
	}

	private function is_hard_denied( $key ) {
		return (bool) preg_match( '/(?:password|passwd|passkey|session|capabilit|user_level|token|secret|api[_-]?key)/i', $key )
			|| (bool) preg_match( '/(?:^|[_-])auth(?:entication|orization)?(?:[_-]|$)/i', $key );
	}

	private function looks_sensitive( $key, $label, $type ) {
		return in_array( $type, array( 'email', 'phone', 'additional_name', 'core_first_name', 'core_last_name' ), true )
			|| (bool) preg_match( '/(?:email|e-mail|phone|telephone|tel|address|postal|zip)/i', $key . ' ' . $label );
	}
}
