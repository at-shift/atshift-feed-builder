=== atshift Feed Builder ===
Contributors: atshift
Tags: rss, json feed, custom fields, structured data, ai
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build safe RSS 2.0 and JSON Feed 1.1 feeds from WordPress, atshift Fields, and atshift User Profile Fields data.

== Description ==

atshift Feed Builder creates purpose-specific public feeds from structured information stored in WordPress.

The first release includes:

* Multiple feed configurations.
* Separate creation flows for replacing a WordPress standard RSS feed or adding a custom RSS 2.0 or JSON Feed 1.1 feed.
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
* Existing WordPress feed URLs remain unchanged when their RSS output is replaced.
* Per-feed controls for replacing or disabling standard feeds and advertising custom feeds in the document head.

atshift Fields and atshift User Profile Fields are optional integrations. atshift Feed Builder remains independently usable for standard WordPress post data.

== Links ==

* Official website: [upf.at-shift.net/feed-builder](https://upf.at-shift.net/feed-builder/)

== Privacy ==

atshift Feed Builder does not send site data to the plugin author or any external service. It does not include analytics, telemetry, advertising, or remote executable code.

Feeds created with this plugin are public URLs. Only explicitly mapped values are output, and protected authentication, session, token, and capability keys are blocked. Site administrators are responsible for reviewing mappings before publication, especially fields that may contain personal information.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from the Plugins screen.
3. Open atshift Feed Builder > Add New Feed in the WordPress administration menu.
4. Choose whether to replace a WordPress standard RSS feed or add a custom RSS 2.0 or JSON Feed 1.1 feed.
5. Select the posts, pages, or public custom post types to include.
6. Map the output fields, then save the feed.
7. Review the first-item preview and the XML or JSON source before sharing the feed URL.

atshift Fields and atshift User Profile Fields are optional. When they are active, their field definitions and saved values become available as mapping sources.

== Screenshots ==

1. Choose whether to replace a WordPress standard RSS feed or create a custom RSS 2.0 or JSON Feed 1.1 feed.
2. Select WordPress values, atshift Fields, supported custom-field integrations, fixed values, or no output for each feed field.
3. Configure the source, delivery settings, and RSS 2.0 output mappings in one feed editor.
4. Configure JSON Feed 1.1 metadata and item mappings with format-specific field names.

== Related Projects ==

* [atshift User Profile Fields](https://wordpress.org/plugins/atshift-user-profile-fields/) - create and manage structured custom fields for WordPress users.
* [at-shift Fields](https://wordpress.org/plugins/atshift-fields-maintenance-for-custom-field-suite/) - arrange custom fields for posts and public custom post types with a field-building interface.
* [atshift Freeform Login](https://wordpress.org/plugins/atshift-freeform-login/) - add passkey login, customize the WordPress login screen, and place login controls with shortcodes.

== Changelog ==

= 0.3.3 =

* Changed custom feed URLs to `/feeds/{feed-slug}/{format}/` so public URLs no longer include a plugin-name prefix.
* Added permanent redirects from legacy `/atshift-feed/` URLs to preserve existing subscriptions and integrations.

= 0.3.2 =

* Fixed translation domains for the Plugins screen links.

= 0.3.1 =

* Added settings and standardized metadata links to the Plugins screen.

= 0.3.0 =

* Initial stable release of the RSS 2.0 and JSON Feed 1.1 builder.
* Added WordPress standard feed replacement for posts, public custom post type archives, and public taxonomy term feeds while preserving their URLs.
* Added explicit controls for custom feed discovery and disabling selected standard feeds.
* Added allow-listed mappings for WordPress, atshift Fields, UPF, post meta, and supported custom field plugins.

= 0.1.0 =

* Initial development release.
