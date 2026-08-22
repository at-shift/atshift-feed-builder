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
	const REWRITE_VERSION = '2';

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
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve_feed' ), 0 );
		add_action( 'wp_head', array( $this, 'print_custom_feed_discovery_links' ), 4 );
		add_filter( 'feed_links_show_posts_feed', array( $this, 'show_posts_feed_link' ) );
		add_filter( 'feed_links_extra_show_post_type_archive_feed', array( $this, 'show_post_type_archive_feed_link' ) );
		add_filter( 'feed_links_extra_show_category_feed', array( $this, 'show_category_feed_link' ) );
		add_filter( 'feed_links_extra_show_tag_feed', array( $this, 'show_tag_feed_link' ) );
		add_filter( 'feed_links_extra_show_tax_feed', array( $this, 'show_taxonomy_feed_link' ) );
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
		add_action( 'atshift_cfs_values_updated', array( $this, 'bump_cache_version' ) );
		add_action( 'updated_option', array( $this, 'maybe_bump_for_option' ), 10, 1 );
		add_filter( 'plugin_action_links_' . plugin_basename( ATSHIFT_FEED_BUILDER_FILE ), array( $this, 'filter_plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'filter_plugin_row_meta' ), 10, 4 );

		if ( is_admin() ) {
			new Atshift_Feed_Builder_Admin( $this->adapters );
		}
	}

	/**
	 * Add the settings shortcut before the standard deactivate link.
	 *
	 * @param array<string, string> $links Existing plugin action links.
	 * @return array<string, string>
	 */
	public function filter_plugin_action_links( $links ) {
		$actions = array(
			'settings' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'edit.php?post_type=' . self::POST_TYPE ) ),
				esc_html__( 'Settings', 'atshift-feed-builder' )
			),
		);

		return array_merge( $actions, $links );
	}

	/**
	 * Build the plugin metadata row in the shared atshift order.
	 *
	 * @param array<int, string>   $links       Existing plugin metadata links.
	 * @param string               $plugin_file Plugin basename.
	 * @param array<string, mixed> $plugin_data Parsed plugin headers.
	 * @param string               $status      Plugin status.
	 * @return array<int, string>
	 */
	public function filter_plugin_row_meta( $links, $plugin_file, $plugin_data, $status ) {
		$original_links = $links;
		unset( $status );

		if ( plugin_basename( ATSHIFT_FEED_BUILDER_FILE ) !== $plugin_file ) {
			return $original_links;
		}

		$details_url = 0 === strpos( determine_locale(), 'ja' )
			? 'https://upf.at-shift.net/feed-builder/'
			: 'https://upf.at-shift.net/en/feed-builder/';
		$author_url = 0 === strpos( determine_locale(), 'ja' )
			? 'https://cfs.at-shift.net/'
			: 'https://cfs.at-shift.net/en/';

		return array(
			sprintf(
				/* translators: %s: Plugin version. */
				esc_html__( 'Version %s', 'atshift-feed-builder' ),
				esc_html( isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : ATSHIFT_FEED_BUILDER_VERSION )
			),
			sprintf(
				/* translators: %s: Plugin author. */
				__( 'By %s', 'atshift-feed-builder' ),
				'<a href="' . esc_url( $author_url ) . '" target="_blank" rel="noopener noreferrer">@shift</a>'
			),
			sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $details_url ),
				esc_html__( 'View details', 'atshift-feed-builder' )
			),
		);
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
		add_rewrite_rule( '^feeds/([^/]+)/(rss|json)/?$', 'index.php?atfb_feed=$matches[1]&atfb_format=$matches[2]', 'top' );
		add_rewrite_rule( '^atshift-feed/([^/]+)/(rss|json)/?$', 'index.php?atfb_feed=$matches[1]&atfb_format=$matches[2]&atfb_legacy=1', 'top' );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'atfb_feed';
		$vars[] = 'atfb_format';
		$vars[] = 'atfb_legacy';
		return $vars;
	}

	public function maybe_flush_rewrite_rules() {
		if ( self::REWRITE_VERSION === (string) get_option( 'atshift_feed_builder_rewrite_version', '' ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'atshift_feed_builder_rewrite_version', self::REWRITE_VERSION, false );
	}

	public function serve_feed() {
		$slug = sanitize_title( (string) get_query_var( 'atfb_feed' ) );

		if ( '' !== $slug ) {
			$this->serve_custom_feed( $slug, (bool) get_query_var( 'atfb_legacy' ) );
		}

		if ( ! is_feed() || is_comment_feed() ) {
			return;
		}

		$target = $this->get_current_standard_target();
		if ( '' === $target ) {
			return;
		}

		$feed = self::get_standard_feed( $target );
		if ( ! $feed ) {
			return;
		}

		$mode = self::get_publication_mode( $feed->ID );
		if ( 'disabled' === $mode ) {
			$this->send_not_found();
		}
		if ( 'standard' !== $mode ) {
			return;
		}

		$request_format = sanitize_key( (string) get_query_var( 'feed' ) );
		if ( ! in_array( $request_format, array( '', 'feed', 'rss2' ), true ) ) {
			return;
		}

		$context = array();
		if ( 0 === strpos( $target, 'taxonomy:' ) ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$context['taxonomy'] = $term->taxonomy;
				$context['term_id']  = $term->term_id;
				$context['feed_url'] = get_term_feed_link( $term->term_id, $term->taxonomy, 'rss2' );
			}
		}

		$this->serve_configured_feed( $feed, 'rss', $context );
	}

	private function serve_custom_feed( $slug, $legacy = false ) {
		$format = sanitize_key( (string) get_query_var( 'atfb_format' ) );
		if ( ! in_array( $format, array( 'rss', 'json' ), true ) ) {
			$this->send_not_found();
		}

		$feed = get_page_by_path( $slug, OBJECT, self::POST_TYPE );
		if ( ! $feed || 'publish' !== $feed->post_status ) {
			$this->send_not_found();
		}

		if ( $format !== self::get_feed_format( $feed->ID ) ) {
			$this->send_not_found();
		}
		if ( 'custom' !== self::get_publication_mode( $feed->ID ) ) {
			$this->send_not_found();
		}
		if ( $legacy ) {
			wp_safe_redirect( self::get_feed_url( $feed, $format ), 301, 'atshift Feed Builder' );
			exit;
		}

		$this->serve_configured_feed( $feed, $format );
	}

	private function serve_configured_feed( $feed, $format, $context = array() ) {
		$settings  = self::get_feed_settings( $feed->ID );
		$version   = (int) get_option( 'atshift_feed_builder_cache_version', 1 );
		$cache_key = 'atfb_' . md5( $feed->ID . '|' . $format . '|' . wp_json_encode( $context ) . '|' . $version );
		$response  = get_transient( $cache_key );

		if ( ! is_array( $response ) || empty( $response['body'] ) ) {
			$renderer = new Atshift_Feed_Builder_Renderer( $this->adapters );
			$response = $renderer->generate( $feed, $format, null, null, false, $context );

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

	public function print_custom_feed_discovery_links() {
		$feeds = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Discovery is an explicit per-feed setting and the query only scans the private feed configuration post type.
				'meta_query'     => array(
					array(
						'key'   => '_atfb_discovery',
						'value' => '1',
					),
				),
			)
		);

		foreach ( $feeds as $feed ) {
			if ( 'custom' !== self::get_publication_mode( $feed->ID ) ) {
				continue;
			}
			$format = self::get_feed_format( $feed->ID );
			$type   = 'json' === $format ? 'application/feed+json' : 'application/rss+xml';
			printf(
				'<link rel="alternate" type="%1$s" title="%2$s" href="%3$s" />' . "\n",
				esc_attr( $type ),
				esc_attr( get_the_title( $feed ) ),
				esc_url( self::get_feed_url( $feed, $format ) )
			);
		}
	}

	public function show_posts_feed_link( $show ) {
		return $this->is_standard_target_disabled( 'posts' ) ? false : $show;
	}

	public function show_post_type_archive_feed_link( $show ) {
		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
		return $this->is_standard_target_disabled( 'post_type:' . sanitize_key( (string) $post_type ) ) ? false : $show;
	}

	public function show_category_feed_link( $show ) {
		return $this->is_standard_target_disabled( 'taxonomy:category' ) ? false : $show;
	}

	public function show_tag_feed_link( $show ) {
		return $this->is_standard_target_disabled( 'taxonomy:post_tag' ) ? false : $show;
	}

	public function show_taxonomy_feed_link( $show ) {
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return $show;
		}

		return $this->is_standard_target_disabled( 'taxonomy:' . $term->taxonomy ) ? false : $show;
	}

	private function is_standard_target_disabled( $target ) {
		$feed = self::get_standard_feed( $target );
		return $feed && 'disabled' === self::get_publication_mode( $feed->ID );
	}

	private function get_current_standard_target() {
		if ( is_category() ) {
			return 'taxonomy:category';
		}
		if ( is_tag() ) {
			return 'taxonomy:post_tag';
		}
		if ( is_tax() ) {
			$term = get_queried_object();
			return $term instanceof WP_Term ? 'taxonomy:' . $term->taxonomy : '';
		}
		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			return '' !== (string) $post_type ? 'post_type:' . sanitize_key( (string) $post_type ) : '';
		}

		return 'posts';
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

	public static function get_publication_mode( $feed_id ) {
		$mode = sanitize_key( (string) get_post_meta( $feed_id, '_atfb_publication_mode', true ) );
		if ( 'rss' === self::get_feed_format( $feed_id ) && in_array( $mode, array( 'standard', 'disabled' ), true ) ) {
			return $mode;
		}

		return 'custom';
	}

	public static function get_standard_target( $feed_id ) {
		$target  = sanitize_text_field( (string) get_post_meta( $feed_id, '_atfb_standard_target', true ) );
		$targets = self::get_standard_targets();
		return isset( $targets[ $target ] ) ? $target : 'posts';
	}

	public static function get_standard_targets() {
		$targets = array(
			'posts' => array(
				'label'      => __( 'Main posts feed', 'atshift-feed-builder' ),
				'post_types' => array( 'post' ),
				'kind'       => 'posts',
			),
		);

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( $post_type->_builtin || empty( $post_type->has_archive ) || 'attachment' === $post_type->name ) {
				continue;
			}
			$targets[ 'post_type:' . $post_type->name ] = array(
				'label'      => sprintf(
					/* translators: %s: Post type label. */
					__( '%s archive feed', 'atshift-feed-builder' ),
					$post_type->labels->name
				),
				'post_types' => array( $post_type->name ),
				'kind'       => 'post_type',
			);
		}

		$public_types     = array_keys( $post_types );
		$public_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $public_taxonomies as $taxonomy ) {
			$object_types = array_values( array_intersect( (array) $taxonomy->object_type, $public_types ) );
			if ( empty( $object_types ) ) {
				continue;
			}
			$targets[ 'taxonomy:' . $taxonomy->name ] = array(
				'label'      => sprintf(
					/* translators: %s: Taxonomy label. */
					__( '%s feeds', 'atshift-feed-builder' ),
					$taxonomy->labels->name
				),
				'post_types' => $object_types,
				'kind'       => 'taxonomy',
			);
		}

		return $targets;
	}

	public static function get_standard_feed( $target ) {
		$target = sanitize_text_field( $target );
		if ( ! isset( self::get_standard_targets()[ $target ] ) ) {
			return null;
		}

		$feeds = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Standard destination and publication mode are the indexed configuration lookup for this private post type.
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_atfb_standard_target',
						'value' => $target,
					),
					array(
						'key'     => '_atfb_publication_mode',
						'value'   => array( 'standard', 'disabled' ),
						'compare' => 'IN',
					),
				),
			)
		);

		return empty( $feeds ) ? null : $feeds[0];
	}

	public static function get_standard_target_url( $target, $term = null ) {
		if ( 'posts' === $target ) {
			return get_feed_link( 'rss2' );
		}
		if ( 0 === strpos( $target, 'post_type:' ) ) {
			return get_post_type_archive_feed_link( substr( $target, strlen( 'post_type:' ) ), 'rss2' );
		}
		if ( 0 === strpos( $target, 'taxonomy:' ) && $term instanceof WP_Term ) {
			return get_term_feed_link( $term->term_id, $term->taxonomy, 'rss2' );
		}

		return '';
	}

	public static function get_standard_target_post_types( $target ) {
		$targets = self::get_standard_targets();
		return isset( $targets[ $target ] ) ? $targets[ $target ]['post_types'] : array( 'post' );
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
		if ( 'rss' === $format && 'standard' === self::get_publication_mode( $feed->ID ) ) {
			$standard_url = self::get_standard_target_url( self::get_standard_target( $feed->ID ) );
			if ( '' !== $standard_url ) {
				return $standard_url;
			}
		}

		if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
			return add_query_arg(
				array(
					'atfb_feed'   => $feed->post_name,
					'atfb_format' => $format,
				),
				home_url( '/' )
			);
		}

		return home_url( user_trailingslashit( 'feeds/' . $feed->post_name . '/' . $format ) );
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
		update_option( 'atshift_feed_builder_rewrite_version', self::REWRITE_VERSION, false );
		self::instance()->register_post_type();
		self::instance()->add_rewrite_rules();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
