<?php
/**
 * Feed model builder and serializers.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_Renderer {
	/** @var array<string,Atshift_Feed_Builder_Source_Adapter> */
	private $adapters;

	public function __construct( $adapters ) {
		$this->adapters = $adapters;
	}

	/**
	 * Generate a complete feed response.
	 *
	 * @param WP_Post    $feed              Feed configuration post.
	 * @param string     $format            rss or json.
	 * @param array|null $settings_override Optional unsaved settings for previews.
	 * @param array|null $mappings_override Optional unsaved mappings for previews.
	 * @param bool       $include_preview   Include normalized reader-preview data.
	 * @param array      $context           Optional standard-feed request context.
	 * @return array<string,mixed>|WP_Error
	 */
	public function generate( $feed, $format, $settings_override = null, $mappings_override = null, $include_preview = false, $context = array() ) {
		$format   = 'json' === $format ? 'json' : 'rss';
		$settings = is_array( $settings_override ) ? $settings_override : Atshift_Feed_Builder_Plugin::get_feed_settings( $feed->ID );
		$schema   = Atshift_Feed_Builder_Schema::get_fields( $format );
		$mappings = is_array( $mappings_override ) ? $mappings_override : Atshift_Feed_Builder_Plugin::get_mappings( $feed->ID, $format );
		$query_args = array(
			'post_type'           => $settings['post_types'],
			'post_status'         => 'publish',
			'posts_per_page'      => $settings['item_limit'],
			'orderby'             => 'modified' === $settings['order_by'] ? 'modified' : 'date',
			'order'               => 'DESC',
			'has_password'        => false,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( ! empty( $settings['authors'] ) ) {
			$query_args['author__in'] = array_map( 'absint', $settings['authors'] );
		}

		$tax_query = array( 'relation' => 'AND' );
		if ( ! empty( $context['taxonomy'] ) && ! empty( $context['term_id'] ) && taxonomy_exists( $context['taxonomy'] ) ) {
			$tax_query[] = array(
				'taxonomy' => sanitize_key( $context['taxonomy'] ),
				'field'    => 'term_id',
				'terms'    => array( absint( $context['term_id'] ) ),
				'operator' => 'IN',
			);
		}
		foreach ( (array) ( $settings['taxonomy_terms'] ?? array() ) as $taxonomy => $term_ids ) {
			if ( taxonomy_exists( $taxonomy ) && ! empty( $term_ids ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array_map( 'absint', (array) $term_ids ),
					'operator' => 'IN',
				);
			}
		}
		if ( 1 < count( $tax_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Taxonomy filtering is an explicit feed-builder feature selected by an administrator.
			$query_args['tax_query'] = $tax_query;
		}

		$meta_query = array( 'relation' => 'AND' );
		foreach ( (array) ( $settings['meta_filters'] ?? array() ) as $filter ) {
			if ( empty( $filter['key'] ) || empty( $filter['compare'] ) ) {
				continue;
			}
			$clause = array(
				'key'     => $filter['key'],
				'compare' => $filter['compare'],
			);
			if ( ! in_array( $filter['compare'], array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
				$clause['value'] = $filter['value'] ?? '';
			}
			$meta_query[] = $clause;
		}
		if ( 1 < count( $meta_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Meta filtering is an explicit, bounded feed-builder feature selected by an administrator.
			$query_args['meta_query'] = $meta_query;
		}

		$query = new WP_Query( $query_args );

		$items         = array();
		$last_modified = strtotime( $feed->post_modified_gmt . ' GMT' );

		foreach ( $query->posts as $post ) {
			$items[]       = $this->resolve_scope( 'item', $schema, $mappings, $post );
			$post_modified = strtotime( $post->post_modified_gmt . ' GMT' );
			$last_modified = max( $last_modified, $post_modified );
		}

		$global_change = strtotime( (string) get_option( 'atshift_feed_builder_last_change_gmt', '' ) . ' GMT' );
		$last_modified = max( $last_modified, $global_change, time() - 1 );
		$model         = array(
			'feed'          => $this->resolve_scope( 'feed', $schema, $mappings, null ),
			'feed_url'      => ! empty( $context['feed_url'] ) ? esc_url_raw( html_entity_decode( (string) $context['feed_url'], ENT_QUOTES, 'UTF-8' ) ) : Atshift_Feed_Builder_Plugin::get_feed_url( $feed, $format ),
			'last_modified' => $last_modified,
			'items'         => $items,
		);

		$body = 'rss' === $format ? $this->render_rss( $model ) : $this->render_json( $model );

		if ( false === $body || '' === $body ) {
			return new WP_Error( 'atfb_generation_failed', __( 'The feed could not be encoded.', 'atshift-feed-builder' ) );
		}

		$response = array(
			'body'          => $body,
			'etag'          => '"' . md5( $body ) . '"',
			'last_modified' => $last_modified,
			'item_count'    => count( $items ),
		);
		if ( $include_preview ) {
			$response['preview'] = $this->build_reader_preview( $model, $format );
		}

		return $response;
	}

	private function build_reader_preview( $model, $format ) {
		$is_json = 'json' === $format;
		$feed    = $model['feed'];
		$preview = array(
			'title'       => $feed['feed_title'],
			'description' => $feed['feed_description'] ?? '',
			'home_url'    => $is_json ? ( $feed['feed_home_url'] ?? '' ) : ( $feed['feed_link'] ?? '' ),
			'publisher'   => $is_json ? ( $feed['feed_author_name'] ?? '' ) : ( $feed['feed_publisher'] ?? '' ),
			'items'       => array(),
		);

		foreach ( $model['items'] as $item ) {
			$preview['items'][] = array(
				'title'   => $item['item_title'] ?? '',
				'url'     => $is_json ? ( $item['item_url'] ?? '' ) : ( $item['item_link'] ?? '' ),
				'summary' => $is_json ? ( $item['item_summary'] ?? '' ) : ( $item['item_description'] ?? '' ),
				'author'  => $is_json ? ( $item['item_author_name'] ?? '' ) : ( $item['item_creator'] ?? '' ),
				'reviewer'=> $is_json ? ( $item['item_reviewer_name'] ?? '' ) : ( $item['item_reviewer'] ?? '' ),
				'source_name' => $item['item_source_name'] ?? '',
				'source_url'  => $item['item_source_url'] ?? '',
				'date'    => $is_json ? ( $item['item_date_published'] ?? '' ) : ( $item['item_pub_date'] ?? '' ),
				'image'   => $item['item_image'] ?? '',
			);
		}

		return $preview;
	}

	private function resolve_scope( $scope, $schema, $mappings, $post ) {
		$values = array();

		foreach ( $schema as $key => $field ) {
			if ( $scope !== $field['scope'] || ! empty( $field['automatic'] ) ) {
				continue;
			}

			$mapping = $mappings[ $key ];
			$value   = $this->resolve_source( $mapping['source'], $mapping['fixed'], $post );
			$value   = $this->coerce_value( $value, $field['type'] );
			if ( $this->is_empty( $value ) && ! empty( $field['allow_fallback'] ) && 'none' !== $mapping['fallback_source'] ) {
				$value = $this->resolve_source( $mapping['fallback_source'], $mapping['fallback_fixed'], $post );
				$value = $this->coerce_value( $value, $field['type'] );
			}

			if ( $this->is_empty( $value ) && ! empty( $field['required'] ) ) {
				$value = $this->coerce_value( $this->resolve_source( $field['default'], '', $post ), $field['type'] );
			}

			$values[ $key ] = $value;
		}

		return $values;
	}

	private function resolve_source( $source, $fixed, $post ) {
		if ( 'none' === $source ) {
			return null;
		}
		if ( 'fixed' === $source ) {
			return $fixed;
		}

		switch ( $source ) {
			case 'site:name':
				return get_bloginfo( 'name' );
			case 'site:description':
				return get_bloginfo( 'description' );
			case 'site:url':
				return home_url( '/' );
			case 'site:language':
				return str_replace( '_', '-', get_locale() );
			case 'site:icon':
				return get_site_icon_url( 512 );
		}

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$author = get_userdata( $post->post_author );
		switch ( $source ) {
			case 'post:title':
				return get_the_title( $post );
			case 'post:url':
				return get_permalink( $post );
			case 'post:stable_id':
				return sprintf( 'urn:atshift-feed:%s:post:%d', (string) get_option( 'atshift_feed_builder_site_uuid', '' ), $post->ID );
			case 'post:excerpt':
				return has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 55 );
			case 'post:content_html':
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Apply the standard WordPress content rendering pipeline.
				return apply_filters( 'the_content', $post->post_content );
			case 'post:published':
				return get_post_time( 'c', true, $post );
			case 'post:modified':
				return get_post_modified_time( 'c', true, $post );
			case 'post:featured_image':
				return get_the_post_thumbnail_url( $post, 'full' );
			case 'post:terms':
				return $this->get_item_terms( $post );
			case 'post:categories':
				return $this->get_item_terms( $post, 'categories' );
			case 'post:tags':
				return $this->get_item_terms( $post, 'tags' );
			case 'post:custom_terms':
				return $this->get_item_terms( $post, 'custom' );
			case 'author:name':
				return $author ? $author->display_name : '';
			case 'author:url':
				return $author ? get_author_posts_url( $author->ID ) : '';
			case 'author:avatar':
				return $author ? get_avatar_url( $author->ID ) : '';
		}

		$parts = explode( ':', $source, 2 );
		if ( 2 !== count( $parts ) || ! isset( $this->adapters[ $parts[0] ] ) ) {
			return null;
		}

		$values = array();
		ob_start();
		try {
			$values = (array) $this->adapters[ $parts[0] ]->get_values( $post, array( $parts[1] ) );
		} catch ( Throwable $error ) {
			$values = array();
		}
		ob_end_clean();
		if ( array_key_exists( $parts[1], $values ) ) {
			return $values[ $parts[1] ];
		}

		return empty( $values ) ? null : reset( $values );
	}

	private function coerce_value( $value, $type ) {
		if ( null === $value || '' === $value || array() === $value ) {
			return null;
		}

		switch ( $type ) {
			case 'url':
				return $this->extract_url( $value );

			case 'html':
				return wp_kses_post( $this->stringify( $value ) );

			case 'datetime':
				$timestamp = is_numeric( $value ) ? (int) $value : strtotime( $this->stringify( $value ) );
				return false === $timestamp ? null : gmdate( 'c', $timestamp );

			case 'list':
				$values = is_array( $value ) ? $value : array( $value );
				$output = array();
				array_walk_recursive(
					$values,
					static function ( $item ) use ( &$output ) {
						if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
							$output[] = sanitize_text_field( (string) $item );
						}
					}
				);
				return array_values( array_unique( $output ) );
		}

		return sanitize_textarea_field( $this->stringify( $value ) );
	}

	private function stringify( $value ) {
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		if ( is_array( $value ) ) {
			$scalars = array();
			array_walk_recursive(
				$value,
				static function ( $item ) use ( &$scalars ) {
					if ( is_scalar( $item ) ) {
						$scalars[] = (string) $item;
					}
				}
			);
			return implode( ', ', $scalars );
		}

		return '';
	}

	private function extract_url( $value ) {
		if ( is_numeric( $value ) ) {
			$url = wp_get_attachment_url( (int) $value );
			return $url ? esc_url_raw( $url ) : null;
		}

		if ( is_array( $value ) ) {
			foreach ( array( 'url', 'src', 'full' ) as $key ) {
				if ( ! empty( $value[ $key ] ) ) {
					return $this->extract_url( $value[ $key ] );
				}
			}
			foreach ( $value as $item ) {
				$url = $this->extract_url( $item );
				if ( $url ) {
					return $url;
				}
			}
			return null;
		}

		$url = esc_url_raw( (string) $value );
		return '' === $url ? null : $url;
	}

	private function get_item_terms( $post, $scope = 'all' ) {
		$terms      = array();
		$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( empty( $taxonomy->public ) ) {
				continue;
			}
			if ( 'categories' === $scope && 'category' !== $taxonomy->name ) {
				continue;
			}
			if ( 'tags' === $scope && 'post_tag' !== $taxonomy->name ) {
				continue;
			}
			if ( 'custom' === $scope && in_array( $taxonomy->name, array( 'category', 'post_tag' ), true ) ) {
				continue;
			}

			$names = wp_get_post_terms( $post->ID, $taxonomy->name, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $names ) ) {
				$terms = array_merge( $terms, $names );
			}
		}

		return array_values( array_unique( array_map( 'sanitize_text_field', $terms ) ) );
	}

	private function is_empty( $value ) {
		return null === $value || '' === $value || array() === $value;
	}

	private function render_json( $model ) {
		$feed = array(
			'version'  => 'https://jsonfeed.org/version/1.1',
			'title'    => $model['feed']['feed_title'],
			'feed_url' => $model['feed_url'],
			'items'    => array(),
		);

		$this->add_json_value( $feed, 'home_page_url', $model['feed']['feed_home_url'] );
		$this->add_json_value( $feed, 'description', $model['feed']['feed_description'] );
		$this->add_json_value( $feed, 'language', $model['feed']['feed_language'] );
		$this->add_json_value( $feed, 'icon', $model['feed']['feed_icon'] );
		$publisher = array();
		$this->add_json_value( $publisher, 'name', $model['feed']['feed_author_name'] );
		$this->add_json_value( $publisher, 'url', $model['feed']['feed_author_url'] );
		$this->add_json_value( $publisher, 'avatar', $model['feed']['feed_author_avatar'] );
		if ( ! empty( $publisher ) ) {
			$feed['authors'] = array( $publisher );
		}

		foreach ( $model['items'] as $values ) {
			$item = array(
				'id'           => $values['item_id'],
				'content_html' => $values['item_content_html'],
			);
			$this->add_json_value( $item, 'url', $values['item_url'] );
			$this->add_json_value( $item, 'external_url', $values['item_source_url'] );
			$this->add_json_value( $item, 'title', $values['item_title'] );
			$this->add_json_value( $item, 'summary', $values['item_summary'] );
			$this->add_json_value( $item, 'image', $values['item_image'] );
			$this->add_json_value( $item, 'date_published', $values['item_date_published'] );
			$this->add_json_value( $item, 'date_modified', $values['item_date_modified'] );
			$this->add_json_value( $item, 'tags', $values['item_tags'] );

			$author = array();
			$this->add_json_value( $author, 'name', $values['item_author_name'] );
			$this->add_json_value( $author, 'url', $values['item_author_url'] );
			$this->add_json_value( $author, 'avatar', $values['item_author_avatar'] );
			if ( ! empty( $author ) ) {
				$item['authors'] = array( $author );
			}

			$source = array();
			$this->add_json_value( $source, 'name', $values['item_source_name'] );
			$this->add_json_value( $source, 'url', $values['item_source_url'] );
			$reviewer = array();
			$this->add_json_value( $reviewer, 'name', $values['item_reviewer_name'] );
			$this->add_json_value( $reviewer, 'url', $values['item_reviewer_url'] );
			$extension = array();
			if ( ! empty( $source ) ) {
				$extension['source'] = $source;
			}
			if ( ! empty( $reviewer ) ) {
				$extension['reviewed_by'] = $reviewer;
			}
			if ( ! empty( $extension ) ) {
				$item['_atshift'] = $extension;
			}

			$feed['items'][] = $item;
		}

		return wp_json_encode( $feed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	}

	private function add_json_value( &$target, $key, $value ) {
		if ( ! $this->is_empty( $value ) ) {
			$target[ $key ] = $value;
		}
	}

	private function render_rss( $model ) {
		$feed    = $model['feed'];
		$output  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$output .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:media="http://search.yahoo.com/mrss/">' . "\n";
		$output .= "<channel>\n";
		$output .= '<title>' . $this->xml( $feed['feed_title'] ) . "</title>\n";
		$output .= '<link>' . $this->xml( $feed['feed_link'] ) . "</link>\n";
		$output .= '<description>' . $this->xml( $feed['feed_description'] ) . "</description>\n";
		if ( ! $this->is_empty( $feed['feed_language'] ) ) {
			$output .= '<language>' . $this->xml( $feed['feed_language'] ) . "</language>\n";
		}
		if ( ! $this->is_empty( $feed['feed_copyright'] ) ) {
			$output .= '<copyright>' . $this->xml( $feed['feed_copyright'] ) . "</copyright>\n";
		}
		$output .= '<lastBuildDate>' . gmdate( DATE_RSS, $model['last_modified'] ) . "</lastBuildDate>\n";
		$output .= '<generator>atshift Feed Builder ' . esc_html( ATSHIFT_FEED_BUILDER_VERSION ) . "</generator>\n";
		$output .= '<atom:link href="' . $this->xml_attr( $model['feed_url'] ) . '" rel="self" type="application/rss+xml" />' . "\n";
		if ( ! $this->is_empty( $feed['feed_publisher'] ) ) {
			$output .= '<dc:publisher>' . $this->xml( $feed['feed_publisher'] ) . "</dc:publisher>\n";
		}

		foreach ( $model['items'] as $item ) {
			$output .= "<item>\n";
			$output .= '<title>' . $this->xml( $item['item_title'] ) . "</title>\n";
			$output .= '<link>' . $this->xml( $item['item_link'] ) . "</link>\n";
			$output .= '<guid isPermaLink="false">' . $this->xml( $item['item_guid'] ) . "</guid>\n";
			if ( ! $this->is_empty( $item['item_pub_date'] ) ) {
				$output .= '<pubDate>' . gmdate( DATE_RSS, strtotime( $item['item_pub_date'] ) ) . "</pubDate>\n";
			}
			$output .= '<description><![CDATA[' . $this->cdata( esc_html( $item['item_description'] ) ) . "]]></description>\n";
			foreach ( (array) $item['item_categories'] as $category ) {
				$output .= '<category>' . $this->xml( $category ) . "</category>\n";
			}
			if ( ! $this->is_empty( $item['item_content'] ) ) {
				$output .= '<content:encoded><![CDATA[' . $this->cdata( $item['item_content'] ) . "]]></content:encoded>\n";
			}
			if ( ! $this->is_empty( $item['item_creator'] ) ) {
				$output .= '<dc:creator>' . $this->xml( $item['item_creator'] ) . "</dc:creator>\n";
			}
			if ( ! $this->is_empty( $item['item_reviewer'] ) ) {
				$output .= '<dc:contributor>' . $this->xml( $item['item_reviewer'] ) . "</dc:contributor>\n";
			}
			if ( ! $this->is_empty( $item['item_source_name'] ) ) {
				$output .= '<dc:source>' . $this->xml( $item['item_source_name'] ) . "</dc:source>\n";
			}
			if ( ! $this->is_empty( $item['item_source_url'] ) ) {
				$output .= '<atom:link rel="related" href="' . $this->xml_attr( $item['item_source_url'] ) . '"';
				if ( ! $this->is_empty( $item['item_source_name'] ) ) {
					$output .= ' title="' . $this->xml_attr( $item['item_source_name'] ) . '"';
				}
				$output .= ' />' . "\n";
			}
			if ( ! $this->is_empty( $item['item_image'] ) ) {
				$image_url = $this->xml_attr( $item['item_image'] );
				$output   .= '<media:content url="' . $image_url . '" medium="image" />' . "\n";
				$output   .= '<media:thumbnail url="' . $image_url . '" />' . "\n";
			}
			$output .= "</item>\n";
		}

		$output .= "</channel>\n</rss>\n";
		return $output;
	}

	private function xml( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
	}

	private function xml_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	private function cdata( $value ) {
		return str_replace( ']]>', ']]]]><![CDATA[>', (string) $value );
	}
}
