# atshift Feed Builder

atshift Feed Builder is a standalone WordPress plugin for publishing WordPress, atshift Fields, and atshift User Profile Fields data as RSS 2.0 or JSON Feed 1.1.

Each feed uses one fixed output standard. After choosing RSS 2.0 or JSON Feed 1.1, the editor displays that standard's structure and lets the publisher map each output field to a WordPress value, an atshift Fields value, a post-author UPF value, an explicitly allowed post_meta or Pods field name, fixed text, or no output where the standard permits it.

Public posts, pages, and public custom post types can be selected independently for each feed. Fields and UPF values replace explicit standard output fields; they are not appended automatically.

Feeds can be narrowed by author, public taxonomy terms, and up to five allow-listed post meta conditions. Different filter types and taxonomy groups are combined with AND; multiple selected terms within one taxonomy are matched with OR.

The editor can preview the first real item in the current feed order using the current unsaved settings. A reader-style view is shown first, with the matching XML or formatted JSON available in a secondary source-code tab for inspection, copying, and validation.

Publisher, article-author, primary-source, and review attribution can be mapped independently. RSS uses `dc:publisher`, `dc:creator`, `dc:contributor`, `dc:source`, and an Atom `rel="related"` link. JSON Feed uses its standard top-level `authors`, item `authors`, and `external_url` fields. Source names and reviewer roles that JSON Feed does not define are included only when explicitly mapped, under the valid JSON Feed extension object `_atshift`.

## Custom source adapters

Plugins can add value sources with the `atshift_feed_builder_source_adapters` filter. An adapter must implement `Atshift_Feed_Builder_Source_Adapter` and is automatically offered for compatible item fields.

```php
add_action(
	'plugins_loaded',
	function () {
		if ( ! interface_exists( 'Atshift_Feed_Builder_Source_Adapter' ) ) {
			return;
		}

		class Example_Feed_Source implements Atshift_Feed_Builder_Source_Adapter {
			public function get_id() {
				return 'example';
			}

			public function get_label() {
				return 'Example Plugin';
			}

			public function is_available() {
				return function_exists( 'example_get_value' );
			}

			public function get_fields() {
				return array(
					'subtitle' => array(
						'id'        => 'subtitle',
						'label'     => 'Subtitle',
						'type'      => 'string',
						'sensitive' => false,
					),
				);
			}

			public function get_values( $post, $field_ids ) {
				$values = array();
				foreach ( $field_ids as $field_id ) {
					$values[ $field_id ] = example_get_value( $field_id, $post->ID );
				}
				return $values;
			}
		}

		add_filter(
			'atshift_feed_builder_source_adapters',
			function ( $adapters ) {
				$adapters['example'] = new Example_Feed_Source();
				return $adapters;
			}
		);
	},
	5
);
```

Adapters that need a manually entered field name can implement `Atshift_Feed_Builder_Manual_Source_Adapter`. They must provide the input label and description and implement `is_allowed_key()` so protected or secret keys cannot be requested. Adapter IDs and field IDs must use lowercase WordPress-safe keys. Supported field types are `string`, `number`, `boolean`, `url`, `image`, `html`, and `list`.

atshift Feed Builder catches adapter exceptions and discards unexpected adapter output before rendering a public feed. Values are still normalized to the target RSS or JSON field type. Do not expose password, authentication, session, capability, token, secret, or private personal-information fields through an adapter.

## Initial endpoints

```text
/atshift-feed/{feed-slug}/rss/
/atshift-feed/{feed-slug}/json/
```

## Privacy model

- Fields and UPF values are never published automatically.
- Generic post_meta keys must be entered explicitly. Protected and security-related keys are blocked.
- When Pods is active, a field name can be entered explicitly and resolved through the Pods API for either meta or table storage.
- ACF / Secure Custom Fields, Meta Box, and Carbon Fields values can be loaded by entering their field name or ID when the respective plugin API is available.
- Main image mappings can define a second source used only when the primary value is empty.
- Publisher, article-author, primary-source, and reviewer values are independently allow-listed and omitted when empty.
- The JSON `_atshift` attribution extension is added only when a source name or reviewer is explicitly mapped.
- Third-party plugins can register allow-listed source adapters without adding executable code fields to the atshift Feed Builder editor.
- Authentication, session, capability, and password-related UPF fields are not selectable.
- Fields that may contain personal information are visibly marked in mapping selectors.
- Draft, private, scheduled, and password-protected source posts are excluded.
- WordPress core RSS and Atom discovery links are removed from the document head while atshift Feed Builder is active.
