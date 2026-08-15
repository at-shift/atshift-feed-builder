<?php
/**
 * Plugin runtime.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_Plugin {
	const POST_TYPE = 'atfb_feed';

	/** @var self|null */
	private static $instance;

	/** @var array<string,Atshift_Feed_Builder_Source_Adapter> */
	private $adapters = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$built_in_adapters = array(
			'postmeta' => new Atshift_Feed_Builder_Post_Meta_Adapter(),
			'pods'     => new Atshift_Feed_Builder_Pods_Adapter(),
			'acf'      => new Atshift_Feed_Builder_ACF_Adapter(),
			'metabox'  => new Atshift_Feed_Builder_Meta_Box_Adapter(),
			'carbon'   => new Atshift_Feed_Builder_Carbon_Fields_Adapter(),
			'fields'   => new Atshift_Feed_Builder_Fields_Adapter(),
			'upf'      => new Atshift_Feed_Builder_UPF_Adapter(),
		);

		/**
		 * Filters the data source adapters available to atshift Feed Builder.
		 *
		 * Adapter objects must implement Atshift_Feed_Builder_Source_Adapter.
		 * Manual-key adapters may also implement
		 * Atshift_Feed_Builder_Manual_Source_Adapter.
		 *
		 * @param array<string,Atshift_Feed_Builder_Source_Adapter> $built_in_adapters Source adapters.
		 */
		$registered_adapters = apply_filters( 'atshift_feed_builder_source_adapters', $built_in_adapters );
		$this->adapters      = $this->validate_adapters( $registered_adapters );

		$extension_ids = array_diff( array_keys( $this->adapters ), array_keys( $built_in_adapters ) );
		Atshift_Feed_Builder_Schema::set_extension_adapter_ids( $extension_ids );

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'after_setup_theme', array( $this, 'remove_default_feed_discovery_links' ), PHP_INT_MAX );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve_feed' ), 0 );
		add_action( 'save_post', array( $this, 'bump_cache_version' ) );
		add_action( 'deleted_post', array( $this, 'bump_cache_version' ) );
		add_action( 'added_post_meta', array( $this, 'bump_cache_version' ) );
		add_action( 'updated_post_meta', array( $this, 'bump_cache_version' ) );
		add_action( 'deleted_post_meta', array( $this, 'bump_cache_version' ) );
		add_action( 'set_object_terms', array( $this, 'bump_cache_version' ) );
		add_action( 'profile_update', array( $this, 'bump_cache_version' ) );
		add_action( 'added_user_meta', array( $this, 'bump_cache_version' ) );
		add_action( 'updated_user_meta', array( $this, 'bump_cache_version' ) );
		add_action( 'deleted_user_meta', array( $this, 'bump_cache_version' ) );
		add_action( 'updated_option', array( $this, 'maybe_bump_for_option' ), 10, 1 );

		if ( is_admin() ) {
			new Atshift_Feed_Builder_Admin( $this->adapters );
		}
	}

	/**
	 * Discard malformed adapter registrations before they reach the editor.
	 *
	 * @param mixed $adapters Filtered adapter collection.
	 * @return array<string,Atshift_Feed_Builder_Source_Adapter>
	 */
	private function validate_adapters( $adapters ) {
		$validated = array();

		foreach ( (array) $adapters as $adapter ) {
			if ( ! $adapter instanceof Atshift_Feed_Builder_Source_Adapter ) {
				continue;
			}

			try {
				$adapter_id = (string) $adapter->get_id();
			} catch ( Throwable $error ) {
				continue;
			}

			if ( '' === $adapter_id || sanitize_key( $adapter_id ) !== $adapter_id || in_array( $adapter_id, array( 'site', 'post', 'author', 'fixed', 'none', 'adapter' ), true ) ) {
				continue;
			}

			$validated[ $adapter_id ] = $adapter;
		}

		return $validated;
	}

	public function get_adapters() {
		return $this->adapters;
	}

	public function register_post_type() {
		$capabilities = array_fill_keys(
			array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ),
			'manage_atshift_feeds'
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'atshift Feed Builder', 'atshift-feed-builder' ),
					'singular_name' => __( 'Feed', 'atshift-feed-builder' ),
					'add_new_item'  => __( 'Add New Feed', 'atshift-feed-builder' ),
					'edit_item'     => __( 'Edit Feed', 'atshift-feed-builder' ),
					'new_item'      => __( 'New Feed', 'atshift-feed-builder' ),
					'all_items'     => __( 'Feeds', 'atshift-feed-builder' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-rss',
				'supports'     => array( 'title' ),
				'capabilities' => $capabilities,
				'map_meta_cap' => false,
			)
		);
	}

	public function add_rewrite_rules() {
		add_rewrite_rule( '^atshift-feed/([^/]+)/(rss|json)/?$', 'index.php?atfb_feed=$matches[1]&atfb_format=$matches[2]', 'top' );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'atfb_feed';
		$vars[] = 'atfb_format';
		return $vars;
	}

	public function remove_default_feed_discovery_links() {
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}

	public function serve_feed() {
		$slug = sanitize_title( (string) get_query_var( 'atfb_feed' ) );

		if ( '' === $slug ) {
			return;
		}

		$format = sanitize_key( (string) get_query_var( 'atfb_format' ) );
		if ( ! in_array( $format, array( 'rss', 'json' ), true ) ) {
			$this->send_not_found();
		}

		$feed = get_page_by_path( $slug, OBJECT, self::POST_TYPE );
		if ( ! $feed || 'publish' !== $feed->post_status ) {
			$this->send_not_found();
		}

		$settings = self::get_feed_settings( $feed->ID );
		if ( $format !== self::get_feed_format( $feed->ID ) ) {
			$this->send_not_found();
		}

		$version   = (int) get_option( 'atshift_feed_builder_cache_version', 1 );
		$cache_key = 'atfb_' . md5( $feed->ID . '|' . $format . '|' . $version );
		$response  = get_transient( $cache_key );

		if ( ! is_array( $response ) || empty( $response['body'] ) ) {
			$renderer = new Atshift_Feed_Builder_Renderer( $this->adapters );
			$response = $renderer->generate( $feed, $format );

			if ( is_wp_error( $response ) ) {
				status_header( 503 );
				header( 'Content-Type: text/plain; charset=UTF-8' );
				echo esc_html( $response->get_error_message() );
				exit;
			}

			set_transient( $cache_key, $response, $settings['cache_ttl'] );
		}

		$response['cache_ttl'] = $settings['cache_ttl'];
		$this->send_response( $response, $format );
	}

	private function send_response( $response, $format ) {
		$etag          = $response['etag'];
		$last_modified = (int) $response['last_modified'];
		$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
		$if_modified   = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? strtotime( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) ) : false;

		header( 'Content-Type: ' . ( 'json' === $format ? 'application/feed+json' : 'application/rss+xml' ) . '; charset=UTF-8' );
		header( 'ETag: ' . $etag );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
		header( 'Cache-Control: public, max-age=' . absint( $response['cache_ttl'] ?? 0 ) );
		header( 'X-Robots-Tag: noindex, follow', true );
		header( 'X-Content-Type-Options: nosniff', true );

		if ( $if_none_match === $etag || ( '' === $if_none_match && false !== $if_modified && $if_modified >= $last_modified ) ) {
			status_header( 304 );
			exit;
		}

		status_header( 200 );
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'get';
		if ( 'head' !== $request_method ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The complete body is escaped by the selected serializer.
			echo $response['body'];
		}
		exit;
	}

	private function send_not_found() {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		exit;
	}

	public function bump_cache_version() {
		update_option( 'atshift_feed_builder_cache_version', (int) get_option( 'atshift_feed_builder_cache_version', 1 ) + 1, false );
		update_option( 'atshift_feed_builder_last_change_gmt', gmdate( 'Y-m-d H:i:s' ), false );
	}

	public function maybe_bump_for_option( $option ) {
		if ( in_array( $option, array( 'atshift_upf_fields', 'atshift_upf_settings' ), true ) ) {
			$this->bump_cache_version();
		}
	}

	public static function get_feed_settings( $feed_id ) {
		$defaults = array(
				'post_types'     => array( 'post' ),
				'authors'        => array(),
				'taxonomy_terms' => array(),
				'meta_filters'   => array(),
				'item_limit'     => 20,
				'order_by'       => 'published',
				'cache_ttl'      => 900,
		);
		$settings = get_post_meta( $feed_id, '_atfb_settings', true );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
	}

	public static function get_feed_format( $feed_id ) {
		$format = sanitize_key( (string) get_post_meta( $feed_id, '_atfb_format', true ) );
		if ( in_array( $format, array( 'rss', 'json' ), true ) ) {
			return $format;
		}

		$legacy_settings = get_post_meta( $feed_id, '_atfb_settings', true );
		$legacy_formats  = is_array( $legacy_settings ) ? (array) ( $legacy_settings['formats'] ?? array() ) : array();
		return in_array( 'rss', $legacy_formats, true ) ? 'rss' : 'json';
	}

	public static function get_mappings( $feed_id, $format = '' ) {
		$format   = in_array( $format, array( 'rss', 'json' ), true ) ? $format : self::get_feed_format( $feed_id );
		$defaults = Atshift_Feed_Builder_Schema::get_default_mappings( $format );
		$saved    = get_post_meta( $feed_id, '_atfb_mappings', true );

		if ( ! is_array( $saved ) ) {
			return $defaults;
		}

		foreach ( $defaults as $key => $mapping ) {
			if ( isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ) {
				$defaults[ $key ]['source'] = sanitize_text_field( $saved[ $key ]['source'] ?? $mapping['source'] );
				$defaults[ $key ]['fixed']  = sanitize_textarea_field( $saved[ $key ]['fixed'] ?? '' );
				if ( isset( $mapping['fallback_source'] ) ) {
					$defaults[ $key ]['fallback_source'] = sanitize_text_field( $saved[ $key ]['fallback_source'] ?? $mapping['fallback_source'] );
					$defaults[ $key ]['fallback_fixed']  = sanitize_textarea_field( $saved[ $key ]['fallback_fixed'] ?? '' );
				}
			}
		}

		return $defaults;
	}

	public static function get_selected_fields( $feed_id ) {
		$fields = get_post_meta( $feed_id, '_atfb_selected_fields', true );
		return is_array( $fields ) ? array_values( array_unique( $fields ) ) : array();
	}

	public static function get_feed_url( $feed, $format ) {
		if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
			return add_query_arg(
				array(
					'atfb_feed'   => $feed->post_name,
					'atfb_format' => $format,
				),
				home_url( '/' )
			);
		}

		return home_url( user_trailingslashit( 'atshift-feed/' . $feed->post_name . '/' . $format ) );
	}

	public static function activate() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'manage_atshift_feeds' );
		}

		if ( ! get_option( 'atshift_feed_builder_site_uuid' ) ) {
			add_option( 'atshift_feed_builder_site_uuid', wp_generate_uuid4(), '', false );
		}

		add_option( 'atshift_feed_builder_cache_version', 1, '', false );
		add_option( 'atshift_feed_builder_last_change_gmt', gmdate( 'Y-m-d H:i:s' ), '', false );
		self::instance()->register_post_type();
		self::instance()->add_rewrite_rules();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
