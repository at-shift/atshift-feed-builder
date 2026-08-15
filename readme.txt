=== atshift Feed Builder ===
Contributors: atshift
Tags: rss, json feed, custom fields, structured data, ai
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build safe RSS 2.0 and JSON Feed 1.1 feeds from WordPress, atshift Fields, and atshift User Profile Fields data.

== Description ==

atshift Feed Builder creates purpose-specific public feeds from structured information stored in WordPress.

The first release includes:

* Multiple feed configurations.
* A format-first creation flow for RSS 2.0 or JSON Feed 1.1.
* Public post type selection.
* Author, public taxonomy term, and allow-listed custom field filters.
* Format-specific mapping of standard output fields.
* WordPress, fixed text, atshift Fields, post-author UPF, explicitly selected post_meta, and optional Pods API value sources.
* Optional field-name integrations for ACF / Secure Custom Fields, Meta Box, and Carbon Fields.
* A public source-adapter API for additional plugin integrations.
* Independent publisher, article-author, primary-source, and reviewer mappings.
* Main image fallback mapping when the primary image source is empty.
* A reader-first preview of the first real item in the current feed order, with matching XML or JSON source in a secondary tab.
* Personal-information warnings and hard exclusion of authentication and permission data.
* Stable item IDs, ETag, Last-Modified, and response caching.
* Removal of WordPress core RSS and Atom discovery links from the document head.

atshift Fields and atshift User Profile Fields are optional integrations. atshift Feed Builder remains independently usable for standard WordPress post data.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress Plugins screen.
3. Open atshift Feed Builder and choose RSS 2.0 or JSON Feed 1.1.
4. Select posts, pages, or public custom post types.
5. Map each output field to a WordPress, atshift Fields, UPF, post_meta, Pods, or fixed value.

== Changelog ==

= 0.1.0 =

* Initial development release.
