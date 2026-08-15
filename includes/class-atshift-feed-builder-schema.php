<?php
/**
 * Format-specific output schemas.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_Schema {
	/** @var array<string> */
	private static $extension_adapter_ids = array();

	/**
	 * Make registered extension adapters available to item mappings.
	 *
	 * @param array<string> $adapter_ids Adapter IDs.
	 */
	public static function set_extension_adapter_ids( $adapter_ids ) {
		self::$extension_adapter_ids = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $adapter_ids ) ) ) );
	}

	public static function get_formats() {
		return array(
			'rss'  => array(
				'label'       => __( 'RSS 2.0', 'atshift-feed-builder' ),
				'description' => __( 'For feed readers and broadly compatible content syndication.', 'atshift-feed-builder' ),
			),
			'json' => array(
				'label'       => __( 'JSON Feed 1.1', 'atshift-feed-builder' ),
				'description' => __( 'For applications, external services, and structured data consumers.', 'atshift-feed-builder' ),
			),
		);
	}

	public static function get_fields( $format ) {
		return 'json' === $format ? self::get_json_fields() : self::get_rss_fields();
	}

	public static function get_default_mappings( $format ) {
		$mappings = array();

		foreach ( self::get_fields( $format ) as $key => $field ) {
			if ( empty( $field['automatic'] ) ) {
				$mappings[ $key ] = array(
					'source' => $field['default'],
					'fixed'  => '',
				);
				if ( ! empty( $field['allow_fallback'] ) ) {
					$mappings[ $key ]['fallback_source'] = 'none';
					$mappings[ $key ]['fallback_fixed']  = '';
				}
			}
		}

		return $mappings;
	}

	private static function field( $scope, $path, $label, $description, $type, $default = 'none', $required = false, $sources = array(), $adapters = array(), $allow_fallback = false ) {
		if ( 'item' === $scope ) {
			$adapters = array_values( array_unique( array_merge( $adapters, array( 'postmeta', 'pods', 'acf', 'metabox', 'carbon' ), self::$extension_adapter_ids ) ) );
		}

		return array(
			'scope'       => $scope,
			'path'        => $path,
			'label'       => $label,
			'description' => $description,
			'type'        => $type,
			'default'     => $default,
			'required'    => $required,
			'automatic'   => false,
			'sources'     => $sources,
			'adapters'    => $adapters,
			'allow_fallback' => (bool) $allow_fallback,
		);
	}

	private static function automatic( $scope, $path, $label, $description ) {
		return array(
			'scope'       => $scope,
			'path'        => $path,
			'label'       => $label,
			'description' => $description,
			'type'        => 'automatic',
			'default'     => '',
			'required'    => true,
			'automatic'   => true,
		);
	}

	private static function get_rss_fields() {
		return array(
			'rss_version'      => self::automatic( 'feed', 'rss@version', __( 'RSS version', 'atshift-feed-builder' ), __( 'Always output as RSS 2.0.', 'atshift-feed-builder' ) ),
			'feed_title'       => self::field( 'feed', 'channel/title', __( 'Feed title', 'atshift-feed-builder' ), __( 'Name of this feed.', 'atshift-feed-builder' ), 'string', 'site:name', true, array( 'site:name' ) ),
			'feed_link'        => self::field( 'feed', 'channel/link', __( 'Publisher website', 'atshift-feed-builder' ), __( 'Website represented by the feed.', 'atshift-feed-builder' ), 'url', 'site:url', true, array( 'site:url' ) ),
			'feed_publisher'   => self::field( 'feed', 'channel/dc:publisher', __( 'Publisher / site operator', 'atshift-feed-builder' ), __( 'Person or organization responsible for publishing the feed.', 'atshift-feed-builder' ), 'string', 'site:name', false, array( 'site:name' ) ),
			'feed_description' => self::field( 'feed', 'channel/description', __( 'Feed description', 'atshift-feed-builder' ), __( 'Short description of the feed.', 'atshift-feed-builder' ), 'string', 'site:description', true, array( 'site:description' ) ),
			'feed_language'    => self::field( 'feed', 'channel/language', __( 'Language', 'atshift-feed-builder' ), __( 'Primary language of the feed.', 'atshift-feed-builder' ), 'string', 'site:language', false, array( 'site:language' ) ),
			'feed_copyright'   => self::field( 'feed', 'channel/copyright', __( 'Copyright', 'atshift-feed-builder' ), __( 'Optional rights statement.', 'atshift-feed-builder' ), 'string' ),
			'feed_last_build'  => self::automatic( 'feed', 'channel/lastBuildDate', __( 'Last updated', 'atshift-feed-builder' ), __( 'Calculated from feed and source updates.', 'atshift-feed-builder' ) ),
			'feed_self_link'   => self::automatic( 'feed', 'channel/atom:link', __( 'Canonical feed URL', 'atshift-feed-builder' ), __( 'Generated from the public feed URL.', 'atshift-feed-builder' ) ),
			'item_title'       => self::field( 'item', 'item/title', __( 'Post title', 'atshift-feed-builder' ), __( 'Title for each feed item.', 'atshift-feed-builder' ), 'string', 'post:title', true, array( 'post:title' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_link'        => self::field( 'item', 'item/link', __( 'Post URL', 'atshift-feed-builder' ), __( 'Public URL for each item.', 'atshift-feed-builder' ), 'url', 'post:url', true, array( 'post:url' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_guid'        => self::field( 'item', 'item/guid', __( 'Unique ID', 'atshift-feed-builder' ), __( 'Stable identifier used by feed readers.', 'atshift-feed-builder' ), 'string', 'post:stable_id', true, array( 'post:stable_id', 'post:url' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_description' => self::field( 'item', 'item/description', __( 'Summary', 'atshift-feed-builder' ), __( 'Short text shown in item lists.', 'atshift-feed-builder' ), 'string', 'post:excerpt', true, array( 'post:excerpt' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_content'     => self::field( 'item', 'item/content:encoded', __( 'Full content', 'atshift-feed-builder' ), __( 'HTML body of the item.', 'atshift-feed-builder' ), 'html', 'post:content_html', false, array( 'post:content_html' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_creator'     => self::field( 'item', 'item/dc:creator', __( 'Article author', 'atshift-feed-builder' ), __( 'Person credited with creating the item.', 'atshift-feed-builder' ), 'string', 'author:name', false, array( 'author:name' ), array( 'fields', 'upf', 'postmeta', 'pods' ) ),
			'item_reviewer'    => self::field( 'item', 'item/dc:contributor', __( 'Reviewer / supervisor', 'atshift-feed-builder' ), __( 'Person who reviewed or supervised the item.', 'atshift-feed-builder' ), 'string', 'none', false, array(), array( 'fields', 'upf', 'postmeta', 'pods' ) ),
			'item_source_name' => self::field( 'item', 'item/dc:source', __( 'Primary source name', 'atshift-feed-builder' ), __( 'Name of the original or related source.', 'atshift-feed-builder' ), 'string', 'none', false, array(), array( 'fields', 'postmeta', 'pods' ) ),
			'item_source_url'  => self::field( 'item', 'item/atom:link[@rel=related]@href', __( 'Primary source URL', 'atshift-feed-builder' ), __( 'Public URL of the original or related source.', 'atshift-feed-builder' ), 'url', 'none', false, array(), array( 'fields', 'postmeta', 'pods' ) ),
			'item_pub_date'    => self::field( 'item', 'item/pubDate', __( 'Published date', 'atshift-feed-builder' ), __( 'Publication date for the item.', 'atshift-feed-builder' ), 'datetime', 'post:published', false, array( 'post:published', 'post:modified' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_categories'  => self::field( 'item', 'item/category', __( 'Categories, tags, and terms', 'atshift-feed-builder' ), __( 'Names of public categories, tags, or custom taxonomy terms assigned to the item. Feed readers may use them for grouping or filtering.', 'atshift-feed-builder' ), 'list', 'post:terms', false, array( 'post:terms', 'post:categories', 'post:tags', 'post:custom_terms' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_image'       => self::field( 'item', 'item/media:content@url', __( 'Main image', 'atshift-feed-builder' ), __( 'Main item image published through Media RSS.', 'atshift-feed-builder' ), 'url', 'post:featured_image', false, array( 'post:featured_image' ), array( 'fields', 'postmeta', 'pods' ), true ),
		);
	}

	private static function get_json_fields() {
		return array(
			'json_version'        => self::automatic( 'feed', 'version', __( 'JSON Feed version', 'atshift-feed-builder' ), __( 'Always output as JSON Feed 1.1.', 'atshift-feed-builder' ) ),
			'feed_title'          => self::field( 'feed', 'title', __( 'Feed title', 'atshift-feed-builder' ), __( 'Name of this feed.', 'atshift-feed-builder' ), 'string', 'site:name', true, array( 'site:name' ) ),
			'feed_home_url'       => self::field( 'feed', 'home_page_url', __( 'Publisher website', 'atshift-feed-builder' ), __( 'Website represented by the feed.', 'atshift-feed-builder' ), 'url', 'site:url', false, array( 'site:url' ) ),
			'feed_url'            => self::automatic( 'feed', 'feed_url', __( 'Canonical feed URL', 'atshift-feed-builder' ), __( 'Generated from the public feed URL.', 'atshift-feed-builder' ) ),
			'feed_description'    => self::field( 'feed', 'description', __( 'Feed description', 'atshift-feed-builder' ), __( 'Short description of the feed.', 'atshift-feed-builder' ), 'string', 'site:description', false, array( 'site:description' ) ),
			'feed_language'       => self::field( 'feed', 'language', __( 'Language', 'atshift-feed-builder' ), __( 'Primary language of the feed.', 'atshift-feed-builder' ), 'string', 'site:language', false, array( 'site:language' ) ),
			'feed_author_name'    => self::field( 'feed', 'authors[].name', __( 'Publisher / site operator', 'atshift-feed-builder' ), __( 'Person or organization responsible for publishing the feed.', 'atshift-feed-builder' ), 'string', 'site:name', false, array( 'site:name' ) ),
			'feed_author_url'     => self::field( 'feed', 'authors[].url', __( 'Publisher URL', 'atshift-feed-builder' ), __( 'Public website or profile for the publisher.', 'atshift-feed-builder' ), 'url', 'site:url', false, array( 'site:url' ) ),
			'feed_author_avatar'  => self::field( 'feed', 'authors[].avatar', __( 'Publisher avatar', 'atshift-feed-builder' ), __( 'Square image representing the publisher.', 'atshift-feed-builder' ), 'url', 'site:icon', false, array( 'site:icon' ) ),
			'feed_icon'           => self::field( 'feed', 'icon', __( 'Feed icon', 'atshift-feed-builder' ), __( 'Square image representing the feed.', 'atshift-feed-builder' ), 'url', 'site:icon', false, array( 'site:icon' ) ),
			'items_container'     => self::automatic( 'item', 'items[]', __( 'Items', 'atshift-feed-builder' ), __( 'One item is generated for each matching post.', 'atshift-feed-builder' ) ),
			'item_id'             => self::field( 'item', 'items[].id', __( 'Unique ID', 'atshift-feed-builder' ), __( 'Stable identifier for the item.', 'atshift-feed-builder' ), 'string', 'post:stable_id', true, array( 'post:stable_id', 'post:url' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_url'            => self::field( 'item', 'items[].url', __( 'Post URL', 'atshift-feed-builder' ), __( 'Public URL for the item.', 'atshift-feed-builder' ), 'url', 'post:url', false, array( 'post:url' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_title'          => self::field( 'item', 'items[].title', __( 'Post title', 'atshift-feed-builder' ), __( 'Title for the item.', 'atshift-feed-builder' ), 'string', 'post:title', false, array( 'post:title' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_summary'        => self::field( 'item', 'items[].summary', __( 'Summary', 'atshift-feed-builder' ), __( 'Short plain-text summary.', 'atshift-feed-builder' ), 'string', 'post:excerpt', false, array( 'post:excerpt' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_content_html'   => self::field( 'item', 'items[].content_html', __( 'Content', 'atshift-feed-builder' ), __( 'HTML content for the item.', 'atshift-feed-builder' ), 'html', 'post:content_html', true, array( 'post:content_html' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_image'          => self::field( 'item', 'items[].image', __( 'Main image', 'atshift-feed-builder' ), __( 'Featured or mapped image URL.', 'atshift-feed-builder' ), 'url', 'post:featured_image', false, array( 'post:featured_image' ), array( 'fields', 'postmeta', 'pods' ), true ),
			'item_date_published' => self::field( 'item', 'items[].date_published', __( 'Published date', 'atshift-feed-builder' ), __( 'Publication date for the item.', 'atshift-feed-builder' ), 'datetime', 'post:published', false, array( 'post:published', 'post:modified' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_date_modified'  => self::field( 'item', 'items[].date_modified', __( 'Modified date', 'atshift-feed-builder' ), __( 'Most recent modification date.', 'atshift-feed-builder' ), 'datetime', 'post:modified', false, array( 'post:modified', 'post:published' ), array( 'fields', 'postmeta', 'pods' ) ),
			'item_author_name'    => self::field( 'item', 'items[].authors[].name', __( 'Article author', 'atshift-feed-builder' ), __( 'Person credited with creating the item.', 'atshift-feed-builder' ), 'string', 'author:name', false, array( 'author:name' ), array( 'fields', 'upf', 'postmeta', 'pods' ) ),
			'item_author_url'     => self::field( 'item', 'items[].authors[].url', __( 'Article author URL', 'atshift-feed-builder' ), __( 'Public profile or website for the article author.', 'atshift-feed-builder' ), 'url', 'author:url', false, array( 'author:url' ), array( 'fields', 'upf', 'postmeta', 'pods' ) ),
			'item_author_avatar'  => self::field( 'item', 'items[].authors[].avatar', __( 'Article author avatar', 'atshift-feed-builder' ), __( 'Image representing the article author.', 'atshift-feed-builder' ), 'url', 'author:avatar', false, array( 'author:avatar' ), array( 'fields', 'upf', 'postmeta', 'pods' ) ),
			'item_source_url'     => self::field( 'item', 'items[].external_url', __( 'Primary source URL', 'atshift-feed-builder' ), __( 'Public URL of the original or related source.', 'atshift-feed-builder' ), 'url', 'none', false, array(), array( 'fields', 'postmeta', 'pods' ) ),
			'item_source_name'    => self::field( 'item', 'items[]._atshift.source.name', __( 'Primary source name', 'atshift-feed-builder' ), __( 'Name of the original or related source.', 'atshift-feed-builder' ), 'string', 'none', false, array(), array( 'fields', 'postmeta', 'pods' ) ),
			'item_reviewer_name'  => self::field( 'item', 'items[]._atshift.reviewed_by.name', __( 'Reviewer / supervisor', 'atshift-feed-builder' ), __( 'Person who reviewed or supervised the item.', 'atshift-feed-builder' ), 'string', 'none', false, array(), array( 'fields', 'upf', 'postmeta', 'pods' ) ),
			'item_reviewer_url'   => self::field( 'item', 'items[]._atshift.reviewed_by.url', __( 'Reviewer / supervisor URL', 'atshift-feed-builder' ), __( 'Public profile or website for the reviewer.', 'atshift-feed-builder' ), 'url', 'none', false, array(), array( 'fields', 'upf', 'postmeta', 'pods' ) ),
			'item_tags'           => self::field( 'item', 'items[].tags[]', __( 'Categories, tags, and terms', 'atshift-feed-builder' ), __( 'Names of public categories, tags, or custom taxonomy terms assigned to the item. Feed readers may use them for grouping or filtering.', 'atshift-feed-builder' ), 'list', 'post:terms', false, array( 'post:terms', 'post:categories', 'post:tags', 'post:custom_terms' ), array( 'fields', 'postmeta', 'pods' ) ),
		);
	}
}
