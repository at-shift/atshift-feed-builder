<?php
/**
 * Feed configuration screens.
 *
 * @package AtshiftFeedBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atshift_Feed_Builder_Admin {
	/** @var array<string,Atshift_Feed_Builder_Source_Adapter> */
	private $adapters;

	/** @var bool */
	private $saving = false;

	public function __construct( $adapters ) {
		$this->adapters = $adapters;

		add_action( 'add_meta_boxes_' . Atshift_Feed_Builder_Plugin::POST_TYPE, array( $this, 'add_meta_boxes' ), 10, 1 );
		add_action( 'save_post_' . Atshift_Feed_Builder_Plugin::POST_TYPE, array( $this, 'save_feed' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_new_feed_page' ) );
		add_action( 'admin_menu', array( $this, 'replace_default_add_new_menu' ), 99 );
		add_action( 'admin_post_atfb_create_feed', array( $this, 'create_feed' ) );
		add_action( 'wp_ajax_atfb_preview_feed', array( $this, 'preview_feed' ) );
		add_action( 'load-post-new.php', array( $this, 'redirect_default_add_new' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'manage_' . Atshift_Feed_Builder_Plugin::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Atshift_Feed_Builder_Plugin::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
	}

	public function add_new_feed_page() {
		add_submenu_page(
			'edit.php?post_type=' . Atshift_Feed_Builder_Plugin::POST_TYPE,
			__( 'Add New Feed', 'atshift-feed-builder' ),
			__( 'Add New Feed', 'atshift-feed-builder' ),
			'manage_atshift_feeds',
			'atfb-new-feed',
			array( $this, 'render_new_feed_page' )
		);
	}

	public function replace_default_add_new_menu() {
		remove_submenu_page(
			'edit.php?post_type=' . Atshift_Feed_Builder_Plugin::POST_TYPE,
			'post-new.php?post_type=' . Atshift_Feed_Builder_Plugin::POST_TYPE
		);
	}

	public function redirect_default_add_new() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only route selection; no state is changed.
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
		if ( Atshift_Feed_Builder_Plugin::POST_TYPE !== $post_type ) {
			return;
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Atshift_Feed_Builder_Plugin::POST_TYPE . '&page=atfb-new-feed' ) );
		exit;
	}

	public function render_new_feed_page() {
		$formats          = Atshift_Feed_Builder_Schema::get_formats();
		$standard_targets = Atshift_Feed_Builder_Plugin::get_standard_targets();
		?>
		<div class="wrap atfb-new-feed">
			<h1><?php esc_html_e( 'Choose how to publish the feed', 'atshift-feed-builder' ); ?></h1>
			<p class="atfb-lead"><?php esc_html_e( 'Keep an existing WordPress feed URL and replace its output, or add a separate feed for a new purpose.', 'atshift-feed-builder' ); ?></p>
			<section class="atfb-create-section">
				<h2><?php esc_html_e( 'Replace a WordPress standard feed', 'atshift-feed-builder' ); ?></h2>
				<p><?php esc_html_e( 'The existing RSS URL stays the same. WordPress continues to advertise that URL in the page head, while atshift Feed Builder controls its content.', 'atshift-feed-builder' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="atfb-standard-create">
					<input type="hidden" name="action" value="atfb_create_feed">
					<input type="hidden" name="atfb_format" value="rss">
					<input type="hidden" name="atfb_publication_mode" value="standard">
					<?php wp_nonce_field( 'atfb_create_feed', 'atfb_create_nonce' ); ?>
					<label for="atfb-new-standard-target"><?php esc_html_e( 'Standard feed to replace', 'atshift-feed-builder' ); ?></label>
					<select id="atfb-new-standard-target" name="atfb_standard_target">
						<?php foreach ( $standard_targets as $target => $details ) : ?>
							<option value="<?php echo esc_attr( $target ); ?>"><?php echo esc_html( $details['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Create standard RSS feed', 'atshift-feed-builder' ); ?></button>
				</form>
			</section>

			<section class="atfb-create-section">
				<h2><?php esc_html_e( 'Add a custom feed', 'atshift-feed-builder' ); ?></h2>
				<p><?php esc_html_e( 'Create a separate URL with its own slug for a specific service, audience, or use case.', 'atshift-feed-builder' ); ?></p>
			<div class="atfb-format-grid">
				<?php foreach ( $formats as $format => $details ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="atfb-format-card">
						<input type="hidden" name="action" value="atfb_create_feed">
						<input type="hidden" name="atfb_format" value="<?php echo esc_attr( $format ); ?>">
						<input type="hidden" name="atfb_publication_mode" value="custom">
						<?php wp_nonce_field( 'atfb_create_feed', 'atfb_create_nonce' ); ?>
						<span class="atfb-format-mark" aria-hidden="true"><?php echo 'rss' === $format ? 'XML' : '{}'; ?></span>
						<h2><?php echo esc_html( $details['label'] ); ?></h2>
						<p><?php echo esc_html( $details['description'] ); ?></p>
						<button type="submit" class="button button-primary button-large">
							<?php
							printf(
								/* translators: %s: Feed format name. */
								esc_html__( 'Create %s feed', 'atshift-feed-builder' ),
								esc_html( $details['label'] )
							);
							?>
						</button>
					</form>
				<?php endforeach; ?>
			</div>
			</section>
		</div>
		<?php
	}

	public function create_feed() {
		if ( ! current_user_can( 'manage_atshift_feeds' ) ) {
			wp_die( esc_html__( 'You are not allowed to create feeds.', 'atshift-feed-builder' ) );
		}

		check_admin_referer( 'atfb_create_feed', 'atfb_create_nonce' );
		$format           = isset( $_POST['atfb_format'] ) ? sanitize_key( wp_unslash( $_POST['atfb_format'] ) ) : '';
		$publication_mode = isset( $_POST['atfb_publication_mode'] ) ? sanitize_key( wp_unslash( $_POST['atfb_publication_mode'] ) ) : 'custom';
		$standard_target  = isset( $_POST['atfb_standard_target'] ) ? sanitize_text_field( wp_unslash( $_POST['atfb_standard_target'] ) ) : 'posts';
		$formats          = Atshift_Feed_Builder_Schema::get_formats();
		$targets          = Atshift_Feed_Builder_Plugin::get_standard_targets();

		if ( ! isset( $formats[ $format ] ) ) {
			wp_die( esc_html__( 'Invalid feed format.', 'atshift-feed-builder' ) );
		}
		if ( 'standard' === $publication_mode && ( 'rss' !== $format || ! isset( $targets[ $standard_target ] ) ) ) {
			wp_die( esc_html__( 'Invalid standard feed destination.', 'atshift-feed-builder' ) );
		}
		$publication_mode = 'standard' === $publication_mode ? 'standard' : 'custom';
		$title            = 'standard' === $publication_mode
			? $targets[ $standard_target ]['label']
			: sprintf(
				/* translators: %s: Feed format name. */
				__( 'New %s feed', 'atshift-feed-builder' ),
				$formats[ $format ]['label']
			);

		$post_id = wp_insert_post(
			array(
				'post_type'   => Atshift_Feed_Builder_Plugin::POST_TYPE,
				'post_status' => 'auto-draft',
				'post_title'  => $title,
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_die( esc_html__( 'The feed could not be created.', 'atshift-feed-builder' ) );
		}

		update_post_meta( $post_id, '_atfb_format', $format );
		update_post_meta( $post_id, '_atfb_publication_mode', $publication_mode );
		update_post_meta( $post_id, '_atfb_discovery', '0' );
		if ( 'standard' === $publication_mode ) {
			update_post_meta( $post_id, '_atfb_standard_target', $standard_target );
			update_post_meta(
				$post_id,
				'_atfb_settings',
				array( 'post_types' => Atshift_Feed_Builder_Plugin::get_standard_target_post_types( $standard_target ) )
			);
		}
		update_post_meta( $post_id, '_atfb_mappings', Atshift_Feed_Builder_Schema::get_default_mappings( $format ) );
		wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
		exit;
	}

	public function add_meta_boxes( $post ) {
		$format  = Atshift_Feed_Builder_Plugin::get_feed_format( $post->ID );
		$formats = Atshift_Feed_Builder_Schema::get_formats();

		add_meta_box( 'atfb-settings', __( 'Feed source', 'atshift-feed-builder' ), array( $this, 'render_settings' ), Atshift_Feed_Builder_Plugin::POST_TYPE, 'normal', 'high' );
		add_meta_box(
			'atfb-mappings',
			sprintf(
				/* translators: %s: Feed format name. */
				__( '%s output mapping', 'atshift-feed-builder' ),
				$formats[ $format ]['label']
			),
			array( $this, 'render_mappings' ),
			Atshift_Feed_Builder_Plugin::POST_TYPE,
			'normal',
			'default'
		);
		add_meta_box( 'atfb-urls', __( 'Feed URL', 'atshift-feed-builder' ), array( $this, 'render_urls' ), Atshift_Feed_Builder_Plugin::POST_TYPE, 'side', 'default' );
		add_meta_box( 'atfb-preview', __( 'Preview', 'atshift-feed-builder' ), array( $this, 'render_preview' ), Atshift_Feed_Builder_Plugin::POST_TYPE, 'side', 'default' );
	}

	private function render_help_tooltip( $text ) {
		if ( '' === $text ) {
			return;
		}

		$tooltip_id = wp_unique_id( 'atfb-help-' );
		?>
		<span class="atfb-help-tooltip">
			<button type="button" class="atfb-help-button" aria-label="<?php esc_attr_e( 'Help', 'atshift-feed-builder' ); ?>" aria-describedby="<?php echo esc_attr( $tooltip_id ); ?>" aria-expanded="false">?</button>
			<span class="atfb-help-content" id="<?php echo esc_attr( $tooltip_id ); ?>" role="tooltip"><?php echo esc_html( $text ); ?></span>
		</span>
		<?php
	}

	private function get_mapping_help( $key ) {
		$help = array(
			'feed_last_build'     => __( 'The most recent update time in the feed, calculated automatically. Services use it to detect changes.', 'atshift-feed-builder' ),
			'feed_self_link'      => __( 'The permanent public URL of this feed. It is generated automatically and tells services which URL is authoritative.', 'atshift-feed-builder' ),
			'feed_url'            => __( 'The permanent public URL of this feed. It is generated automatically and tells services which URL is authoritative.', 'atshift-feed-builder' ),
			'feed_publisher'      => __( 'The person or organization responsible for operating and publishing the feed, not necessarily the article author.', 'atshift-feed-builder' ),
			'feed_author_name'    => __( 'The person or organization responsible for operating and publishing the feed, not necessarily the article author.', 'atshift-feed-builder' ),
			'items_container'     => __( 'A feed item is one published post included in the feed. This container is created automatically.', 'atshift-feed-builder' ),
			'item_guid'           => __( 'Feed readers use this value to recognize the same post after an update and avoid duplicates. It should not change after publication.', 'atshift-feed-builder' ),
			'item_id'             => __( 'Feed readers use this value to recognize the same post after an update and avoid duplicates. It should not change after publication.', 'atshift-feed-builder' ),
			'item_link'           => __( 'The public page readers open for this post. Custom post types also use their normal permalink.', 'atshift-feed-builder' ),
			'item_url'            => __( 'The public page readers open for this post. Custom post types also use their normal permalink.', 'atshift-feed-builder' ),
			'item_creator'        => __( 'The person credited with writing or creating the post.', 'atshift-feed-builder' ),
			'item_author_name'    => __( 'The person credited with writing or creating the post.', 'atshift-feed-builder' ),
			'item_reviewer'       => __( 'An optional person who checked or supervised the content. Leave it out when there is no reviewer.', 'atshift-feed-builder' ),
			'item_reviewer_name'  => __( 'An optional person who checked or supervised the content. Leave it out when there is no reviewer.', 'atshift-feed-builder' ),
			'item_source_name'    => __( 'The original or related source behind the post. Use this when the WordPress post summarizes information published elsewhere.', 'atshift-feed-builder' ),
			'item_source_url'     => __( 'The original or related source behind the post. Use this when the WordPress post summarizes information published elsewhere.', 'atshift-feed-builder' ),
			'item_categories'     => __( 'Classification labels attached to the post. They help readers and services group or filter items.', 'atshift-feed-builder' ),
			'item_tags'           => __( 'Classification labels attached to the post. They help readers and services group or filter items.', 'atshift-feed-builder' ),
			'item_image'          => __( 'The representative image for the item. A fallback can be used when the post has no featured image.', 'atshift-feed-builder' ),
		);

		return $help[ $key ] ?? '';
	}

	public function render_settings( $post ) {
		$settings         = Atshift_Feed_Builder_Plugin::get_feed_settings( $post->ID );
		$format           = Atshift_Feed_Builder_Plugin::get_feed_format( $post->ID );
		$formats          = Atshift_Feed_Builder_Schema::get_formats();
		$publication_mode = Atshift_Feed_Builder_Plugin::get_publication_mode( $post->ID );
		$standard_target  = Atshift_Feed_Builder_Plugin::get_standard_target( $post->ID );
		$standard_targets = Atshift_Feed_Builder_Plugin::get_standard_targets();
		$discovery        = '1' === (string) get_post_meta( $post->ID, '_atfb_discovery', true );
		$post_types       = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );
		$core_post_types   = array();
		$custom_post_types = array();
		foreach ( $post_types as $post_type ) {
			if ( $post_type->_builtin ) {
				$core_post_types[] = $post_type;
			} else {
				$custom_post_types[] = $post_type;
			}
		}
		$authors             = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );
		$filter_taxonomies   = array();
		$public_type_names   = array_keys( $post_types );
		$public_taxonomies   = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $public_taxonomies as $taxonomy ) {
			if ( empty( array_intersect( (array) $taxonomy->object_type, $public_type_names ) ) ) {
				continue;
			}
			$terms = get_terms( array( 'taxonomy' => $taxonomy->name, 'hide_empty' => false ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$filter_taxonomies[ $taxonomy->name ] = array( 'object' => $taxonomy, 'terms' => $terms );
			}
		}
		$meta_filters = ! empty( $settings['meta_filters'] ) ? $settings['meta_filters'] : array( array( 'key' => '', 'compare' => '=', 'value' => '' ) );
		$meta_operators = array(
			'='          => __( 'Equals', 'atshift-feed-builder' ),
			'!='         => __( 'Does not equal', 'atshift-feed-builder' ),
			'LIKE'       => __( 'Contains', 'atshift-feed-builder' ),
			'EXISTS'     => __( 'Exists', 'atshift-feed-builder' ),
			'NOT EXISTS' => __( 'Does not exist', 'atshift-feed-builder' ),
		);
		$has_active_filters = ! empty( $settings['authors'] ) || ! empty( $settings['taxonomy_terms'] ) || ! empty( $settings['meta_filters'] );
		wp_nonce_field( 'atfb_save_feed', 'atfb_nonce' );
		?>
		<div class="atfb-format-summary">
			<span><?php esc_html_e( 'Output format', 'atshift-feed-builder' ); ?></span>
			<strong><?php echo esc_html( $formats[ $format ]['label'] ); ?></strong>
			<small><?php esc_html_e( 'The format is fixed for this feed.', 'atshift-feed-builder' ); ?></small>
		</div>
		<div class="atfb-form-grid atfb-publication-editor" data-publication-mode="<?php echo esc_attr( $publication_mode ); ?>">
			<section class="atfb-publication-settings atfb-field-wide">
				<div class="atfb-field">
					<span class="atfb-field-label"><?php esc_html_e( 'Publication method', 'atshift-feed-builder' ); ?></span>
					<?php if ( 'rss' === $format ) : ?>
						<div class="atfb-publication-options">
							<label><input type="radio" name="atfb_publication_mode" value="standard" <?php checked( $publication_mode, 'standard' ); ?>><span><strong><?php esc_html_e( 'Replace a standard feed', 'atshift-feed-builder' ); ?></strong><small><?php esc_html_e( 'Keep the WordPress URL and replace its RSS output.', 'atshift-feed-builder' ); ?></small></span></label>
							<label><input type="radio" name="atfb_publication_mode" value="custom" <?php checked( $publication_mode, 'custom' ); ?>><span><strong><?php esc_html_e( 'Custom feed', 'atshift-feed-builder' ); ?></strong><small><?php esc_html_e( 'Publish at a separate URL with a custom slug.', 'atshift-feed-builder' ); ?></small></span></label>
							<label><input type="radio" name="atfb_publication_mode" value="disabled" <?php checked( $publication_mode, 'disabled' ); ?>><span><strong><?php esc_html_e( 'Disable a standard feed', 'atshift-feed-builder' ); ?></strong><small><?php esc_html_e( 'Remove its discovery link and return not found at that RSS URL.', 'atshift-feed-builder' ); ?></small></span></label>
						</div>
					<?php else : ?>
						<input type="hidden" name="atfb_publication_mode" value="custom">
						<p class="atfb-publication-note"><?php esc_html_e( 'JSON Feed uses a custom URL because WordPress does not provide a standard JSON Feed endpoint.', 'atshift-feed-builder' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="atfb-publication-panel" data-publication-panel="standard">
					<div class="atfb-field">
						<label for="atfb-standard-target"><?php esc_html_e( 'Target WordPress standard feed', 'atshift-feed-builder' ); ?></label>
						<select id="atfb-standard-target" name="atfb_standard_target">
							<?php foreach ( $standard_targets as $target => $details ) : ?>
								<option value="<?php echo esc_attr( $target ); ?>" data-post-types="<?php echo esc_attr( implode( ',', $details['post_types'] ) ); ?>" <?php selected( $standard_target, $target ); ?>><?php echo esc_html( $details['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Choose the standard WordPress feed affected by this setting. Category, tag, and taxonomy targets use each term\'s existing feed URL.', 'atshift-feed-builder' ); ?></p>
					</div>
				</div>

				<div class="atfb-publication-panel" data-publication-panel="custom">
					<div class="atfb-field">
						<label for="atfb-slug"><span class="atfb-label-with-help"><?php esc_html_e( 'Feed slug', 'atshift-feed-builder' ); ?><?php $this->render_help_tooltip( __( 'A short identifier used in the public feed URL. Use lowercase letters, numbers, and hyphens.', 'atshift-feed-builder' ) ); ?></span></label>
						<input type="text" class="regular-text" id="atfb-slug" name="atfb_slug" value="<?php echo esc_attr( $post->post_name ); ?>" placeholder="news">
						<p class="description"><?php esc_html_e( 'Changing this value changes the public feed URL.', 'atshift-feed-builder' ); ?></p>
					</div>
					<label class="atfb-discovery-option"><input type="checkbox" name="atfb_discovery" value="1" <?php checked( $discovery ); ?>><span><strong><?php esc_html_e( 'Advertise this feed in the page head', 'atshift-feed-builder' ); ?></strong><small><?php esc_html_e( 'Adds an alternate feed link so browsers and services can discover this custom feed.', 'atshift-feed-builder' ); ?></small></span></label>
				</div>
			</section>

			<fieldset class="atfb-field atfb-field-wide atfb-custom-content">
				<legend><span class="atfb-label-with-help"><?php esc_html_e( 'Content types', 'atshift-feed-builder' ); ?><?php $this->render_help_tooltip( __( 'Choose which kinds of WordPress content become feed items. Selecting a custom post type includes its published entries.', 'atshift-feed-builder' ) ); ?></span></legend>
				<div class="atfb-post-type-groups">
					<?php
					$groups = array(
						array( 'label' => __( 'WordPress content', 'atshift-feed-builder' ), 'types' => $core_post_types, 'empty' => '' ),
						array( 'label' => __( 'Custom post types', 'atshift-feed-builder' ), 'types' => $custom_post_types, 'empty' => __( 'No public custom post types are currently registered.', 'atshift-feed-builder' ) ),
					);
					foreach ( $groups as $group ) :
						$group_types = $group['types'];
						?>
						<div class="atfb-post-type-group">
							<strong><?php echo esc_html( $group['label'] ); ?></strong>
							<?php if ( empty( $group_types ) ) : ?>
								<?php if ( '' !== $group['empty'] ) : ?><p class="atfb-post-type-empty"><?php echo esc_html( $group['empty'] ); ?></p><?php endif; ?>
							<?php else : ?>
								<div class="atfb-post-type-list">
									<?php foreach ( $group_types as $post_type ) : ?>
										<label>
											<input type="checkbox" name="atfb_settings[post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $settings['post_types'], true ) ); ?>>
											<span><?php echo esc_html( $post_type->labels->name ); ?></span>
											<code><?php echo esc_html( $post_type->name ); ?></code>
										</label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				</fieldset>

				<details class="atfb-filter-section atfb-field-wide atfb-output-settings"<?php if ( $has_active_filters ) : ?> open<?php endif; ?>>
					<summary class="atfb-filter-summary">
						<span class="atfb-label-with-help"><?php esc_html_e( 'Feed content filters', 'atshift-feed-builder' ); ?><?php $this->render_help_tooltip( __( 'These settings limit which published posts are included in the feed. They do not change the values output for each item.', 'atshift-feed-builder' ) ); ?></span>
						<span class="atfb-filter-summary-meta">
							<?php if ( $has_active_filters ) : ?>
								<span class="atfb-filter-active"><?php esc_html_e( 'Active', 'atshift-feed-builder' ); ?></span>
							<?php endif; ?>
							<span class="dashicons dashicons-arrow-down-alt2 atfb-filter-chevron" aria-hidden="true"></span>
						</span>
					</summary>
					<div class="atfb-filter-body">
						<p class="atfb-filter-intro"><?php esc_html_e( 'Choose which posts are included by author, taxonomy term, or custom field value. Different filter groups must all match; within each taxonomy, any selected term can match.', 'atshift-feed-builder' ); ?></p>
						<div class="atfb-filter-grid">
							<fieldset class="atfb-filter-group">
							<legend><span class="atfb-label-with-help"><?php esc_html_e( 'Authors', 'atshift-feed-builder' ); ?><?php $this->render_help_tooltip( __( 'Include posts written by the selected authors.', 'atshift-feed-builder' ) ); ?></span></legend>
							<div class="atfb-filter-options">
								<?php foreach ( $authors as $author ) : ?>
									<label>
										<input type="checkbox" name="atfb_settings[authors][]" value="<?php echo absint( $author->ID ); ?>" <?php checked( in_array( (int) $author->ID, array_map( 'intval', (array) $settings['authors'] ), true ) ); ?>>
										<span><?php echo esc_html( $author->display_name ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
							</fieldset>

							<fieldset class="atfb-filter-group">
							<legend><span class="atfb-label-with-help"><?php esc_html_e( 'Taxonomy terms', 'atshift-feed-builder' ); ?><?php $this->render_help_tooltip( __( 'Include posts assigned to the selected categories, tags, or custom taxonomy terms.', 'atshift-feed-builder' ) ); ?></span></legend>
							<?php if ( empty( $filter_taxonomies ) ) : ?>
								<p class="atfb-filter-empty"><?php esc_html_e( 'No public taxonomy terms are currently registered.', 'atshift-feed-builder' ); ?></p>
							<?php else : ?>
								<div class="atfb-taxonomy-filters">
									<?php foreach ( $filter_taxonomies as $taxonomy_name => $taxonomy_data ) : ?>
										<div class="atfb-taxonomy-filter">
											<strong><?php echo esc_html( $taxonomy_data['object']->labels->singular_name ); ?></strong>
											<div class="atfb-filter-options">
												<?php foreach ( $taxonomy_data['terms'] as $term ) : ?>
													<label>
														<input type="checkbox" name="atfb_settings[taxonomy_terms][<?php echo esc_attr( $taxonomy_name ); ?>][]" value="<?php echo absint( $term->term_id ); ?>" <?php checked( in_array( (int) $term->term_id, array_map( 'intval', (array) ( $settings['taxonomy_terms'][ $taxonomy_name ] ?? array() ) ), true ) ); ?>>
														<span><?php echo esc_html( $term->name ); ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							</fieldset>

							<fieldset class="atfb-filter-group atfb-filter-group-wide">
							<legend><span class="atfb-label-with-help"><?php esc_html_e( 'Custom field conditions', 'atshift-feed-builder' ); ?><?php $this->render_help_tooltip( __( 'Include posts by matching a public custom field key and value.', 'atshift-feed-builder' ) ); ?></span></legend>
							<div class="atfb-meta-filters">
								<?php foreach ( $meta_filters as $index => $filter ) : ?>
									<div class="atfb-meta-filter-row">
										<input type="text" name="atfb_settings[meta_filters][<?php echo absint( $index ); ?>][key]" value="<?php echo esc_attr( $filter['key'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Custom field key', 'atshift-feed-builder' ); ?>" autocomplete="off" spellcheck="false">
										<select name="atfb_settings[meta_filters][<?php echo absint( $index ); ?>][compare]" aria-label="<?php esc_attr_e( 'Comparison', 'atshift-feed-builder' ); ?>">
											<?php foreach ( $meta_operators as $operator => $operator_label ) : ?>
												<option value="<?php echo esc_attr( $operator ); ?>" <?php selected( $filter['compare'] ?? '=', $operator ); ?>><?php echo esc_html( $operator_label ); ?></option>
											<?php endforeach; ?>
										</select>
										<input type="text" class="atfb-meta-filter-value" name="atfb_settings[meta_filters][<?php echo absint( $index ); ?>][value]" value="<?php echo esc_attr( $filter['value'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Value', 'atshift-feed-builder' ); ?>">
										<button type="button" class="atfb-icon-button atfb-remove-meta-filter" title="<?php esc_attr_e( 'Remove condition', 'atshift-feed-builder' ); ?>">
											<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
											<span class="screen-reader-text"><?php esc_html_e( 'Remove condition', 'atshift-feed-builder' ); ?></span>
										</button>
									</div>
								<?php endforeach; ?>
							</div>
							<button type="button" class="button atfb-add-meta-filter">
								<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
								<?php esc_html_e( 'Add condition', 'atshift-feed-builder' ); ?>
							</button>
							<template class="atfb-meta-filter-template">
								<div class="atfb-meta-filter-row">
									<input type="text" name="atfb_settings[meta_filters][__INDEX__][key]" placeholder="<?php esc_attr_e( 'Custom field key', 'atshift-feed-builder' ); ?>" autocomplete="off" spellcheck="false">
									<select name="atfb_settings[meta_filters][__INDEX__][compare]" aria-label="<?php esc_attr_e( 'Comparison', 'atshift-feed-builder' ); ?>">
										<?php foreach ( $meta_operators as $operator => $operator_label ) : ?>
											<option value="<?php echo esc_attr( $operator ); ?>"><?php echo esc_html( $operator_label ); ?></option>
										<?php endforeach; ?>
									</select>
									<input type="text" class="atfb-meta-filter-value" name="atfb_settings[meta_filters][__INDEX__][value]" placeholder="<?php esc_attr_e( 'Value', 'atshift-feed-builder' ); ?>">
									<button type="button" class="atfb-icon-button atfb-remove-meta-filter" title="<?php esc_attr_e( 'Remove condition', 'atshift-feed-builder' ); ?>">
										<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
										<span class="screen-reader-text"><?php esc_html_e( 'Remove condition', 'atshift-feed-builder' ); ?></span>
									</button>
								</div>
							</template>
							</fieldset>
						</div>
					</div>
				</details>

			<div class="atfb-basic-settings atfb-field-wide atfb-output-settings">
				<div class="atfb-field">
					<label for="atfb-limit"><span class="atfb-label-with-help"><?php esc_html_e( 'Number of items', 'atshift-feed-builder' ); ?><?php $this->render_help_tooltip( __( 'The maximum number of posts included each time the feed is generated.', 'atshift-feed-builder' ) ); ?></span></label>
					<input type="number" id="atfb-limit" name="atfb_settings[item_limit]" value="<?php echo absint( $settings['item_limit'] ); ?>" min="1" max="100">
				</div>

				<div class="atfb-field">
					<label for="atfb-order"><span class="atfb-label-with-help"><?php esc_html_e( 'Order items by', 'atshift-feed-builder' ); ?><?php $this->render_help_tooltip( __( 'Choose whether newly published posts or recently updated posts appear first.', 'atshift-feed-builder' ) ); ?></span></label>
					<select id="atfb-order" name="atfb_settings[order_by]">
						<option value="published" <?php selected( $settings['order_by'], 'published' ); ?>><?php esc_html_e( 'Published date', 'atshift-feed-builder' ); ?></option>
						<option value="modified" <?php selected( $settings['order_by'], 'modified' ); ?>><?php esc_html_e( 'Modified date', 'atshift-feed-builder' ); ?></option>
					</select>
				</div>

				<div class="atfb-field">
					<label for="atfb-cache-ttl"><span class="atfb-label-with-help"><?php esc_html_e( 'Cache lifetime (seconds)', 'atshift-feed-builder' ); ?><?php $this->render_help_tooltip( __( 'How long generated output is reused before checking WordPress for changes. For example, 900 seconds is 15 minutes.', 'atshift-feed-builder' ) ); ?></span></label>
					<input type="number" id="atfb-cache-ttl" name="atfb_settings[cache_ttl]" value="<?php echo absint( $settings['cache_ttl'] ); ?>" min="60" max="86400">
				</div>
			</div>
		</div>
		<?php
	}

	public function render_mappings( $post ) {
		$format   = Atshift_Feed_Builder_Plugin::get_feed_format( $post->ID );
		$fields   = Atshift_Feed_Builder_Schema::get_fields( $format );
		$mappings = Atshift_Feed_Builder_Plugin::get_mappings( $post->ID, $format );
		?>
		<p class="atfb-mapping-intro"><?php esc_html_e( 'Choose a value source for each output field. Field-specific choices appear only when they are needed.', 'atshift-feed-builder' ); ?></p>
		<?php foreach ( array( 'feed' => __( 'Feed information', 'atshift-feed-builder' ), 'item' => __( 'Each item', 'atshift-feed-builder' ) ) as $scope => $heading ) : ?>
			<section class="atfb-mapping-section">
				<h3><?php echo esc_html( $heading ); ?></h3>
				<div class="atfb-mapping-table">
					<?php foreach ( $fields as $key => $field ) : ?>
						<?php if ( $scope !== $field['scope'] ) : continue; endif; ?>
						<div class="atfb-mapping-row<?php echo ! empty( $field['automatic'] ) ? ' is-automatic' : ''; ?>">
							<div class="atfb-output-field">
								<div class="atfb-output-field-heading">
									<strong><?php echo esc_html( $field['label'] ); ?></strong>
									<?php $this->render_help_tooltip( $this->get_mapping_help( $key ) ); ?>
									<?php if ( ! empty( $field['required'] ) ) : ?><span class="atfb-required"><?php esc_html_e( 'Required', 'atshift-feed-builder' ); ?></span><?php endif; ?>
								</div>
								<code><?php echo esc_html( $field['path'] ); ?></code>
								<small><?php echo esc_html( $field['description'] ); ?></small>
							</div>
							<div class="atfb-source-control">
								<?php if ( ! empty( $field['automatic'] ) ) : ?>
									<span class="atfb-auto-source"><?php esc_html_e( 'Generated automatically', 'atshift-feed-builder' ); ?></span>
								<?php else : ?>
									<div class="atfb-source-picker">
										<?php $this->render_source_select( $key, $field, $mappings[ $key ] ); ?>
										<input type="text" class="atfb-fixed-value" name="atfb_mappings[<?php echo esc_attr( $key ); ?>][fixed]" value="<?php echo esc_attr( $mappings[ $key ]['fixed'] ); ?>" placeholder="<?php esc_attr_e( 'Fixed value', 'atshift-feed-builder' ); ?>">
									</div>
									<?php if ( ! empty( $field['allow_fallback'] ) ) : ?>
										<div class="atfb-fallback-control">
											<strong><?php esc_html_e( 'If the primary value is empty', 'atshift-feed-builder' ); ?></strong>
											<div class="atfb-source-picker">
												<?php $this->render_source_select( $key, $field, $mappings[ $key ], 'fallback_source' ); ?>
												<input type="text" class="atfb-fixed-value" name="atfb_mappings[<?php echo esc_attr( $key ); ?>][fallback_fixed]" value="<?php echo esc_attr( $mappings[ $key ]['fallback_fixed'] ); ?>" placeholder="<?php esc_attr_e( 'Fallback URL', 'atshift-feed-builder' ); ?>">
											</div>
										</div>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
		<?php
	}

	private function render_source_select( $key, $field, $mapping, $mapping_name = 'source' ) {
		$selected         = $mapping[ $mapping_name ];
		$selected_kind    = $this->get_source_kind( $selected );
		$standard_options = $this->get_standard_source_options( $field );
		$adapter_options  = array();
		$manual_options   = array();

		foreach ( $this->adapters as $adapter ) {
			if ( ! in_array( $adapter->get_id(), $field['adapters'], true ) || ! $this->is_adapter_available( $adapter ) ) {
				continue;
			}
			if ( $this->is_manual_adapter( $adapter->get_id() ) ) {
				$manual_options[ $adapter->get_id() ] = $this->get_adapter_label( $adapter );
				continue;
			}

			$options = $this->get_adapter_field_options( $adapter, $field );
			if ( ! empty( $options ) ) {
				$adapter_options[ $adapter->get_id() ] = $options;
			}
		}

		$kind_is_available = isset( $standard_options[ $selected_kind ] )
			|| 'fixed' === $selected_kind
			|| ( 'none' === $selected_kind && empty( $field['required'] ) )
			|| ( 0 === strpos( $selected_kind, 'adapter:' ) && isset( $adapter_options[ substr( $selected_kind, 8 ) ] ) )
			|| isset( $manual_options[ $selected_kind ] );
		?>
		<input type="hidden" class="atfb-source-value" name="atfb_mappings[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $mapping_name ); ?>]" value="<?php echo esc_attr( $selected ); ?>">
		<select class="atfb-source-select" aria-label="<?php esc_attr_e( 'Value source', 'atshift-feed-builder' ); ?>">
			<optgroup label="<?php esc_attr_e( 'WordPress values', 'atshift-feed-builder' ); ?>">
				<?php foreach ( $standard_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $selected_kind ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</optgroup>
			<?php if ( ! empty( $adapter_options ) || ! empty( $manual_options ) ) : ?>
				<optgroup label="<?php esc_attr_e( 'Custom values', 'atshift-feed-builder' ); ?>">
					<?php foreach ( $adapter_options as $adapter_id => $options ) : ?>
						<option value="adapter:<?php echo esc_attr( $adapter_id ); ?>" <?php selected( 'adapter:' . $adapter_id, $selected_kind ); ?>><?php echo esc_html( $this->get_adapter_label( $this->adapters[ $adapter_id ] ) ); ?></option>
					<?php endforeach; ?>
					<?php foreach ( $manual_options as $adapter_id => $adapter_label ) : ?>
						<option value="<?php echo esc_attr( $adapter_id ); ?>" <?php selected( $adapter_id, $selected_kind ); ?>><?php echo esc_html( $adapter_label ); ?></option>
					<?php endforeach; ?>
				</optgroup>
			<?php endif; ?>
			<optgroup label="<?php esc_attr_e( 'Other', 'atshift-feed-builder' ); ?>">
				<option value="fixed" <?php selected( 'fixed', $selected_kind ); ?>><?php esc_html_e( 'Fixed value', 'atshift-feed-builder' ); ?></option>
				<?php if ( empty( $field['required'] ) ) : ?>
					<option value="none" <?php selected( 'none', $selected_kind ); ?>><?php esc_html_e( 'Do not output', 'atshift-feed-builder' ); ?></option>
				<?php endif; ?>
				<?php if ( ! $kind_is_available ) : ?>
					<option value="<?php echo esc_attr( $selected_kind ); ?>" selected><?php esc_html_e( 'Unavailable source (selection preserved)', 'atshift-feed-builder' ); ?></option>
				<?php endif; ?>
			</optgroup>
		</select>

		<?php foreach ( $adapter_options as $adapter_id => $options ) : ?>
			<div class="atfb-source-detail" data-source-kind="adapter:<?php echo esc_attr( $adapter_id ); ?>" hidden>
				<label><?php echo esc_html( $this->get_adapter_label( $this->adapters[ $adapter_id ] ) ); ?></label>
				<select class="atfb-adapter-field">
					<?php foreach ( $options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $selected ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
					<?php if ( 'adapter:' . $adapter_id === $selected_kind && ! isset( $options[ $selected ] ) ) : ?>
						<option value="<?php echo esc_attr( $selected ); ?>" selected><?php esc_html_e( 'Unavailable field (selection preserved)', 'atshift-feed-builder' ); ?></option>
					<?php endif; ?>
				</select>
			</div>
		<?php endforeach; ?>

		<?php foreach ( $manual_options as $adapter_id => $adapter_label ) : ?>
			<?php $manual_key = $adapter_id === $selected_kind ? substr( $selected, strlen( $adapter_id ) + 1 ) : ''; ?>
			<div class="atfb-source-detail" data-source-kind="<?php echo esc_attr( $adapter_id ); ?>" hidden>
				<label><?php echo esc_html( $this->get_manual_field_label( $adapter_id ) ); ?></label>
				<input type="text" class="atfb-manual-key" data-source-prefix="<?php echo esc_attr( $adapter_id ); ?>" value="<?php echo esc_attr( $manual_key ); ?>" autocomplete="off" spellcheck="false" placeholder="example_field">
				<small>
					<?php
					echo esc_html( $this->get_manual_field_description( $adapter_id ) );
					?>
				</small>
			</div>
		<?php endforeach; ?>
		<?php
	}

	private function get_standard_source_options( $field ) {
		$labels = array(
			'site:name'          => __( 'Site name', 'atshift-feed-builder' ),
			'site:description'   => __( 'Site tagline', 'atshift-feed-builder' ),
			'site:url'           => __( 'Site URL', 'atshift-feed-builder' ),
			'site:language'      => __( 'Site language', 'atshift-feed-builder' ),
			'site:icon'          => __( 'Site icon URL', 'atshift-feed-builder' ),
			'post:title'         => __( 'Post title', 'atshift-feed-builder' ),
			'post:url'           => __( 'Post permalink', 'atshift-feed-builder' ),
			'post:stable_id'     => __( 'Stable post ID', 'atshift-feed-builder' ),
			'post:excerpt'       => __( 'Post excerpt (generated from content if empty)', 'atshift-feed-builder' ),
			'post:content_html'  => __( 'Post content (HTML)', 'atshift-feed-builder' ),
			'post:published'     => __( 'Published date', 'atshift-feed-builder' ),
			'post:modified'      => __( 'Modified date', 'atshift-feed-builder' ),
			'post:featured_image'=> __( 'Featured image URL', 'atshift-feed-builder' ),
			'post:terms'         => __( 'All public taxonomy terms', 'atshift-feed-builder' ),
			'post:categories'    => __( 'Categories only', 'atshift-feed-builder' ),
			'post:tags'          => __( 'Tags only', 'atshift-feed-builder' ),
			'post:custom_terms'  => __( 'Public custom taxonomy terms only', 'atshift-feed-builder' ),
			'author:name'        => __( 'Display name', 'atshift-feed-builder' ),
			'author:url'         => __( 'Author archive URL', 'atshift-feed-builder' ),
			'author:avatar'      => __( 'Avatar URL', 'atshift-feed-builder' ),
		);
		$options = array();

		foreach ( $field['sources'] as $source ) {
			if ( isset( $labels[ $source ] ) ) {
				$options[ $source ] = $labels[ $source ];
			}
		}

		return $options;
	}

	private function get_adapter_field_options( $adapter, $field ) {
		$options = array();
		try {
			$definitions = (array) $adapter->get_fields();
		} catch ( Throwable $error ) {
			$definitions = array();
		}

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) || empty( $definition['id'] ) || empty( $definition['label'] ) || empty( $definition['type'] ) || ! is_scalar( $definition['label'] ) || ! is_scalar( $definition['type'] ) ) {
				continue;
			}

			$field_id = sanitize_key( $definition['id'] );
			if ( '' === $field_id || $field_id !== (string) $definition['id'] ) {
				continue;
			}

			if ( ! $this->is_compatible_adapter_type( $definition['type'], $field['type'] ) ) {
				continue;
			}

			$label = $definition['label'];
			if ( ! empty( $definition['sensitive'] ) ) {
				$label .= ' - ' . __( 'may contain personal information', 'atshift-feed-builder' );
			}
			$options[ $adapter->get_id() . ':' . $field_id ] = $label;
		}

		return $options;
	}

	private function get_source_kind( $source ) {
		$parts = explode( ':', $source, 2 );
		if ( 2 === count( $parts ) && isset( $this->adapters[ $parts[0] ] ) ) {
			return $this->is_manual_adapter( $parts[0] ) ? $parts[0] : 'adapter:' . $parts[0];
		}

		return $source;
	}

	private function is_manual_adapter( $adapter_id ) {
		return isset( $this->adapters[ $adapter_id ] ) && $this->adapters[ $adapter_id ] instanceof Atshift_Feed_Builder_Manual_Source_Adapter;
	}

	private function get_manual_field_label( $adapter_id ) {
		if ( $this->is_manual_adapter( $adapter_id ) ) {
			try {
				$label = (string) $this->adapters[ $adapter_id ]->get_manual_field_label();
				return '' !== $label ? $label : __( 'Field name', 'atshift-feed-builder' );
			} catch ( Throwable $error ) {
				return __( 'Field name', 'atshift-feed-builder' );
			}
		}

		return __( 'Field name', 'atshift-feed-builder' );
	}

	private function get_manual_field_description( $adapter_id ) {
		if ( $this->is_manual_adapter( $adapter_id ) ) {
			try {
				$description = (string) $this->adapters[ $adapter_id ]->get_manual_field_description();
				return '' !== $description ? $description : __( 'Enter the field name used by the plugin.', 'atshift-feed-builder' );
			} catch ( Throwable $error ) {
				return __( 'Enter the field name used by the plugin.', 'atshift-feed-builder' );
			}
		}

		return __( 'Enter the field name used by the plugin.', 'atshift-feed-builder' );
	}

	private function get_adapter_label( $adapter ) {
		try {
			$label = (string) $adapter->get_label();
			return '' !== $label ? $label : $adapter->get_id();
		} catch ( Throwable $error ) {
			return $adapter->get_id();
		}
	}

	private function is_adapter_available( $adapter ) {
		try {
			return (bool) $adapter->is_available();
		} catch ( Throwable $error ) {
			return false;
		}
	}

	private function is_compatible_adapter_type( $source_type, $target_type ) {
		$compatible = array(
			'string'   => array( 'string', 'number', 'boolean', 'url' ),
			'url'      => array( 'string', 'url', 'image' ),
			'html'     => array( 'string', 'html' ),
			'datetime' => array( 'string', 'number' ),
			'list'     => array( 'string', 'list' ),
		);

		return isset( $compatible[ $target_type ] ) && in_array( $source_type, $compatible[ $target_type ], true );
	}

	public function render_urls( $post ) {
		if ( 'publish' !== $post->post_status || '' === $post->post_name ) {
			echo '<p>' . esc_html__( 'Publish this feed to create its public URL.', 'atshift-feed-builder' ) . '</p>';
			return;
		}

		$format = Atshift_Feed_Builder_Plugin::get_feed_format( $post->ID );
		$mode   = Atshift_Feed_Builder_Plugin::get_publication_mode( $post->ID );
		if ( 'disabled' === $mode ) {
			echo '<p><strong>' . esc_html__( 'Standard feed disabled', 'atshift-feed-builder' ) . '</strong><br>' . esc_html__( 'The selected WordPress feed URL returns not found and is not advertised in the page head.', 'atshift-feed-builder' ) . '</p>';
			return;
		}
		if ( 'standard' === $mode && 0 === strpos( Atshift_Feed_Builder_Plugin::get_standard_target( $post->ID ), 'taxonomy:' ) ) {
			echo '<p><strong>' . esc_html__( 'Existing term feed URLs', 'atshift-feed-builder' ) . '</strong><br>' . esc_html__( 'Each category, tag, or taxonomy term keeps its own WordPress feed URL.', 'atshift-feed-builder' ) . '</p>';
			return;
		}

		$url = Atshift_Feed_Builder_Plugin::get_feed_url( $post, $format );
		printf( '<p><strong>%1$s</strong><br><a href="%2$s" target="_blank" rel="noopener">%3$s</a></p>', esc_html( strtoupper( $format ) ), esc_url( $url ), esc_html( $url ) );
	}

	public function render_preview( $post ) {
		$format  = Atshift_Feed_Builder_Plugin::get_feed_format( $post->ID );
		$formats = Atshift_Feed_Builder_Schema::get_formats();
		?>
		<div class="atfb-preview-box">
			<p><?php esc_html_e( 'Check the current settings without saving them.', 'atshift-feed-builder' ); ?></p>
			<button type="button" class="button button-secondary atfb-preview-open" data-post-id="<?php echo absint( $post->ID ); ?>">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
				<?php esc_html_e( 'Preview feed', 'atshift-feed-builder' ); ?>
			</button>
			<small><?php esc_html_e( 'The preview uses the first item in the current feed order.', 'atshift-feed-builder' ); ?></small>
		</div>
		<dialog class="atfb-preview-dialog" aria-labelledby="atfb-preview-title-<?php echo absint( $post->ID ); ?>">
			<div class="atfb-preview-header">
				<div>
					<h2 id="atfb-preview-title-<?php echo absint( $post->ID ); ?>"><?php esc_html_e( 'Feed preview', 'atshift-feed-builder' ); ?></h2>
					<span class="atfb-preview-format"><?php echo esc_html( $formats[ $format ]['label'] ); ?></span>
				</div>
				<button type="button" class="atfb-icon-button atfb-preview-close" title="<?php esc_attr_e( 'Close preview', 'atshift-feed-builder' ); ?>">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Close preview', 'atshift-feed-builder' ); ?></span>
				</button>
			</div>
			<div class="atfb-preview-status" role="status" aria-live="polite"></div>
			<div class="atfb-preview-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Preview view', 'atshift-feed-builder' ); ?>">
				<button type="button" id="atfb-reader-tab-<?php echo absint( $post->ID ); ?>" class="atfb-preview-tab is-active" role="tab" aria-selected="true" aria-controls="atfb-reader-panel-<?php echo absint( $post->ID ); ?>" data-preview-panel="reader">
					<?php esc_html_e( 'Reader preview', 'atshift-feed-builder' ); ?>
				</button>
				<button type="button" id="atfb-source-tab-<?php echo absint( $post->ID ); ?>" class="atfb-preview-tab" role="tab" aria-selected="false" aria-controls="atfb-source-panel-<?php echo absint( $post->ID ); ?>" data-preview-panel="source" tabindex="-1">
					<?php esc_html_e( 'Source code', 'atshift-feed-builder' ); ?>
				</button>
			</div>
			<div id="atfb-reader-panel-<?php echo absint( $post->ID ); ?>" class="atfb-preview-panel" role="tabpanel" aria-labelledby="atfb-reader-tab-<?php echo absint( $post->ID ); ?>" data-preview-panel="reader">
				<div class="atfb-reader-preview"></div>
			</div>
			<div id="atfb-source-panel-<?php echo absint( $post->ID ); ?>" class="atfb-preview-panel" role="tabpanel" aria-labelledby="atfb-source-tab-<?php echo absint( $post->ID ); ?>" data-preview-panel="source" hidden>
				<pre class="atfb-preview-output" tabindex="0"><code></code></pre>
			</div>
			<div class="atfb-preview-actions" hidden>
				<button type="button" class="button atfb-preview-copy" disabled>
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<?php esc_html_e( 'Copy output', 'atshift-feed-builder' ); ?>
				</button>
			</div>
		</dialog>
		<?php
	}

	public function preview_feed() {
		check_ajax_referer( 'atfb_preview_feed', 'nonce' );

		if ( ! current_user_can( 'manage_atshift_feeds' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to preview feeds.', 'atshift-feed-builder' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$feed    = get_post( $post_id );
		if ( ! $feed || Atshift_Feed_Builder_Plugin::POST_TYPE !== $feed->post_type ) {
			wp_send_json_error( array( 'message' => __( 'The feed configuration could not be found.', 'atshift-feed-builder' ) ), 404 );
		}

		$format = Atshift_Feed_Builder_Plugin::get_feed_format( $post_id );
		$mode   = isset( $_POST['atfb_publication_mode'] ) ? sanitize_key( wp_unslash( $_POST['atfb_publication_mode'] ) ) : 'custom';
		$target = isset( $_POST['atfb_standard_target'] ) ? sanitize_text_field( wp_unslash( $_POST['atfb_standard_target'] ) ) : 'posts';
		$mode   = 'rss' === $format && in_array( $mode, array( 'standard', 'disabled' ), true ) ? $mode : 'custom';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each setting is allow-listed and sanitized by sanitize_settings().
		$raw_settings = isset( $_POST['atfb_settings'] ) && is_array( $_POST['atfb_settings'] ) ? wp_unslash( $_POST['atfb_settings'] ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each mapping is allow-listed and sanitized by sanitize_mappings().
		$raw_mappings = isset( $_POST['atfb_mappings'] ) && is_array( $_POST['atfb_mappings'] ) ? wp_unslash( $_POST['atfb_mappings'] ) : array();
		$settings     = $this->sanitize_settings(
			$raw_settings,
			'custom' === $mode ? null : Atshift_Feed_Builder_Plugin::get_standard_target_post_types( $target )
		);
		$mappings     = $this->sanitize_mappings( $format, $raw_mappings, Atshift_Feed_Builder_Plugin::get_mappings( $post_id, $format ) );
		$settings['item_limit'] = 1;

		$preview_feed = clone $feed;
		$slug         = isset( $_POST['atfb_slug'] ) ? sanitize_title( wp_unslash( $_POST['atfb_slug'] ) ) : '';
		$preview_feed->post_name = '' !== $slug ? $slug : ( '' !== $feed->post_name ? $feed->post_name : 'feed-' . $post_id );
		$context = array();
		if ( 'standard' === $mode ) {
			$standard_url = Atshift_Feed_Builder_Plugin::get_standard_target_url( $target );
			if ( '' !== $standard_url ) {
				$context['feed_url'] = $standard_url;
			}
		}

		$renderer = new Atshift_Feed_Builder_Renderer( $this->adapters );
		$response = $renderer->generate( $preview_feed, $format, $settings, $mappings, true, $context );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ), 422 );
		}

		wp_send_json_success(
			array(
				'body'       => $response['body'],
				'format'     => strtoupper( $format ),
				'item_count' => $response['item_count'],
				'preview'    => $response['preview'],
			)
		);
	}

	public function save_feed( $post_id, $post ) {
		if ( $this->saving || ! isset( $_POST['atfb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['atfb_nonce'] ) ), 'atfb_save_feed' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'manage_atshift_feeds' ) ) {
			return;
		}

		$format = Atshift_Feed_Builder_Plugin::get_feed_format( $post_id );
		$mode   = isset( $_POST['atfb_publication_mode'] ) ? sanitize_key( wp_unslash( $_POST['atfb_publication_mode'] ) ) : 'custom';
		$target = isset( $_POST['atfb_standard_target'] ) ? sanitize_text_field( wp_unslash( $_POST['atfb_standard_target'] ) ) : 'posts';
		$mode   = 'rss' === $format && in_array( $mode, array( 'standard', 'disabled' ), true ) ? $mode : 'custom';
		$targets = Atshift_Feed_Builder_Plugin::get_standard_targets();
		if ( ! isset( $targets[ $target ] ) ) {
			$target = 'posts';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are allow-listed and sanitized by sanitize_settings().
		$raw = isset( $_POST['atfb_settings'] ) && is_array( $_POST['atfb_settings'] ) ? wp_unslash( $_POST['atfb_settings'] ) : array();
		$settings = $this->sanitize_settings(
			$raw,
			'custom' === $mode ? null : Atshift_Feed_Builder_Plugin::get_standard_target_post_types( $target )
		);

		update_post_meta( $post_id, '_atfb_settings', $settings );
		update_post_meta( $post_id, '_atfb_publication_mode', $mode );
		update_post_meta( $post_id, '_atfb_discovery', 'custom' === $mode && ! empty( $_POST['atfb_discovery'] ) ? '1' : '0' );
		if ( 'custom' !== $mode ) {
			update_post_meta( $post_id, '_atfb_standard_target', $target );
			if ( 'publish' === $post->post_status ) {
				$this->release_standard_target( $post_id, $target );
			}
		}
		$this->save_mappings( $post_id );

		$slug = sanitize_title( wp_unslash( $_POST['atfb_slug'] ?? '' ) );
		if ( '' === $slug ) {
			$slug = sanitize_title( $post->post_title );
		}
		if ( '' === $slug ) {
			$slug = 'feed-' . $post_id;
		}

		$slug = wp_unique_post_slug( $slug, $post_id, $post->post_status, Atshift_Feed_Builder_Plugin::POST_TYPE, 0 );
		if ( $slug !== $post->post_name ) {
			$this->saving = true;
			wp_update_post( array( 'ID' => $post_id, 'post_name' => $slug ) );
				$this->saving = false;
			}
		}

	private function sanitize_settings( $raw, $post_types_override = null ) {
		$public_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $public_types['attachment'] );
		$requested_types = is_array( $post_types_override ) ? $post_types_override : (array) ( $raw['post_types'] ?? array() );
		$post_types   = array_values( array_intersect( array_map( 'sanitize_key', $requested_types ), $public_types ) );
		$post_types   = empty( $post_types ) ? array( 'post' ) : $post_types;

		$requested_authors = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $raw['authors'] ?? array() ) ) ) ) );
		$authors           = empty( $requested_authors ) ? array() : array_map(
			'intval',
			get_users( array( 'include' => $requested_authors, 'fields' => 'ID' ) )
		);

		$taxonomy_terms     = array();
		$object_taxonomies  = get_object_taxonomies( $post_types, 'names' );
		$public_taxonomies  = get_taxonomies( array( 'public' => true ), 'names' );
		$allowed_taxonomies = array_intersect( $object_taxonomies, $public_taxonomies );
		foreach ( (array) ( $raw['taxonomy_terms'] ?? array() ) as $taxonomy => $term_ids ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
				continue;
			}
			$valid_terms = array();
			foreach ( array_unique( array_map( 'absint', (array) $term_ids ) ) as $term_id ) {
				if ( $term_id && term_exists( $term_id, $taxonomy ) ) {
					$valid_terms[] = $term_id;
				}
			}
			if ( ! empty( $valid_terms ) ) {
				$taxonomy_terms[ $taxonomy ] = $valid_terms;
			}
		}

		$meta_filters     = array();
		$allowed_compares = array( '=', '!=', 'LIKE', 'EXISTS', 'NOT EXISTS' );
		foreach ( array_slice( (array) ( $raw['meta_filters'] ?? array() ), 0, 5 ) as $filter ) {
			if ( ! is_array( $filter ) ) {
				continue;
			}
			$key     = sanitize_key( $filter['key'] ?? '' );
			$compare = strtoupper( sanitize_text_field( $filter['compare'] ?? '=' ) );
			if ( ! Atshift_Feed_Builder_Post_Meta_Adapter::is_allowed_key( $key ) || ! in_array( $compare, $allowed_compares, true ) ) {
				continue;
			}
			$meta_filters[] = array(
				'key'     => $key,
				'compare' => $compare,
				'value'   => in_array( $compare, array( 'EXISTS', 'NOT EXISTS' ), true ) ? '' : sanitize_text_field( $filter['value'] ?? '' ),
			);
		}

		return array(
			'post_types'     => $post_types,
			'authors'        => $authors,
			'taxonomy_terms' => $taxonomy_terms,
			'meta_filters'   => $meta_filters,
			'item_limit'     => max( 1, min( 100, absint( $raw['item_limit'] ?? 20 ) ) ),
			'order_by'       => 'modified' === ( $raw['order_by'] ?? '' ) ? 'modified' : 'published',
			'cache_ttl'      => max( 60, min( DAY_IN_SECONDS, absint( $raw['cache_ttl'] ?? 900 ) ) ),
		);
	}

	private function release_standard_target( $post_id, $target ) {
		$duplicates = get_posts(
			array(
				'post_type'      => Atshift_Feed_Builder_Plugin::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'post__not_in'   => array( $post_id ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_atfb_standard_target',
						'value' => $target,
					),
				),
			)
		);

		foreach ( $duplicates as $duplicate_id ) {
			$duplicate_mode = Atshift_Feed_Builder_Plugin::get_publication_mode( $duplicate_id );
			if ( in_array( $duplicate_mode, array( 'standard', 'disabled' ), true ) ) {
				update_post_meta( $duplicate_id, '_atfb_publication_mode', 'custom' );
				update_post_meta( $duplicate_id, '_atfb_discovery', '0' );
			}
		}
	}

	private function save_mappings( $post_id ) {
		$format   = Atshift_Feed_Builder_Plugin::get_feed_format( $post_id );
		$current  = Atshift_Feed_Builder_Plugin::get_mappings( $post_id, $format );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The caller verifies the feed nonce; each mapping is allow-listed and sanitized below.
		$raw = isset( $_POST['atfb_mappings'] ) && is_array( $_POST['atfb_mappings'] ) ? wp_unslash( $_POST['atfb_mappings'] ) : array();
		update_post_meta( $post_id, '_atfb_mappings', $this->sanitize_mappings( $format, $raw, $current ) );
	}

	private function sanitize_mappings( $format, $raw, $current ) {
		$schema   = Atshift_Feed_Builder_Schema::get_fields( $format );
		$defaults = Atshift_Feed_Builder_Schema::get_default_mappings( $format );
		$mappings = array();
		foreach ( $defaults as $key => $default ) {
			$field  = $schema[ $key ];
			$source = sanitize_text_field( $raw[ $key ]['source'] ?? $default['source'] );
			$fixed  = sanitize_textarea_field( $raw[ $key ]['fixed'] ?? '' );

			if ( ! $this->is_allowed_source( $source, $field, $current[ $key ]['source'] ?? '' ) ) {
				$source = $default['source'];
			}
			if ( ! empty( $field['required'] ) && 'none' === $source ) {
				$source = $default['source'];
			}

			$mappings[ $key ] = array( 'source' => $source, 'fixed' => $fixed );

			if ( ! empty( $field['allow_fallback'] ) ) {
				$fallback_source = sanitize_text_field( $raw[ $key ]['fallback_source'] ?? $default['fallback_source'] );
				$fallback_fixed  = sanitize_textarea_field( $raw[ $key ]['fallback_fixed'] ?? '' );
				if ( ! $this->is_allowed_source( $fallback_source, $field, $current[ $key ]['fallback_source'] ?? '' ) ) {
					$fallback_source = $default['fallback_source'];
				}
				$mappings[ $key ]['fallback_source'] = $fallback_source;
				$mappings[ $key ]['fallback_fixed']  = $fallback_fixed;
			}
		}

		return $mappings;
	}

	private function is_allowed_source( $source, $field, $current_source ) {
		if ( isset( $this->get_standard_source_options( $field )[ $source ] ) || in_array( $source, array( 'fixed', 'none' ), true ) ) {
			return true;
		}
		if ( $source === $current_source && (bool) preg_match( '/^[a-z0-9_-]+:[a-z0-9_-]+$/', $source ) ) {
			return true;
		}

		$parts = explode( ':', $source, 2 );
		if ( 2 !== count( $parts ) || ! in_array( $parts[0], $field['adapters'], true ) || ! isset( $this->adapters[ $parts[0] ] ) ) {
			return false;
		}

		if ( $this->is_manual_adapter( $parts[0] ) ) {
			if ( ! $this->is_adapter_available( $this->adapters[ $parts[0] ] ) ) {
				return false;
			}
			try {
				return (bool) $this->adapters[ $parts[0] ]->is_allowed_key( $parts[1] );
			} catch ( Throwable $error ) {
				return false;
			}
		}

		if ( isset( $this->get_adapter_field_options( $this->adapters[ $parts[0] ], $field )[ $source ] ) ) {
			return true;
		}

		return false;
	}

	public function enqueue_assets() {
		$screen = get_current_screen();
		if ( ! $screen || ( Atshift_Feed_Builder_Plugin::POST_TYPE !== $screen->post_type && false === strpos( $screen->id, 'atfb-new-feed' ) ) ) {
			return;
		}

		wp_enqueue_style( 'atshift-feed-builder-admin', ATSHIFT_FEED_BUILDER_URL . 'assets/admin.css', array(), ATSHIFT_FEED_BUILDER_VERSION );
		wp_enqueue_script( 'atshift-feed-builder-admin', ATSHIFT_FEED_BUILDER_URL . 'assets/admin.js', array(), ATSHIFT_FEED_BUILDER_VERSION, true );
		wp_localize_script(
			'atshift-feed-builder-admin',
				'atfbPreviewData',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'atfb_preview_feed' ),
					'loading'    => __( 'Generating preview...', 'atshift-feed-builder' ),
					'error'      => __( 'The preview could not be generated.', 'atshift-feed-builder' ),
					/* translators: %s: Feed format. */
					'status'     => __( '%s preview, first item', 'atshift-feed-builder' ),
					'copied'     => __( 'Output copied.', 'atshift-feed-builder' ),
					'copyFailed' => __( 'The output could not be copied.', 'atshift-feed-builder' ),
					/* translators: %s: Publisher name. */
					'publishedBy'=> __( 'Published by %s', 'atshift-feed-builder' ),
					/* translators: %s: Reviewer name. */
					'reviewedBy' => __( 'Reviewed by %s', 'atshift-feed-builder' ),
					/* translators: %s: Source name. */
					'source'     => __( 'Source: %s', 'atshift-feed-builder' ),
				)
			);
	}

	public function columns( $columns ) {
		$columns['atfb_format']      = __( 'Format', 'atshift-feed-builder' );
		$columns['atfb_publication'] = __( 'Publication', 'atshift-feed-builder' );
		$columns['atfb_url']         = __( 'Feed URL', 'atshift-feed-builder' );
		return $columns;
	}

	public function column_content( $column, $post_id ) {
		$post   = get_post( $post_id );
		$format = Atshift_Feed_Builder_Plugin::get_feed_format( $post_id );

		if ( 'atfb_format' === $column ) {
			$formats = Atshift_Feed_Builder_Schema::get_formats();
			echo esc_html( $formats[ $format ]['label'] );
		}
		if ( 'atfb_publication' === $column ) {
			$mode = Atshift_Feed_Builder_Plugin::get_publication_mode( $post_id );
			$labels = array(
				'custom'   => __( 'Custom feed', 'atshift-feed-builder' ),
				'standard' => __( 'Standard feed replacement', 'atshift-feed-builder' ),
				'disabled' => __( 'Standard feed disabled', 'atshift-feed-builder' ),
			);
			echo esc_html( $labels[ $mode ] );
		}
		if ( 'atfb_url' === $column && $post && 'publish' === $post->post_status ) {
			$mode = Atshift_Feed_Builder_Plugin::get_publication_mode( $post_id );
			if ( 'disabled' === $mode ) {
				esc_html_e( 'Disabled', 'atshift-feed-builder' );
			} elseif ( 'standard' === $mode && 0 === strpos( Atshift_Feed_Builder_Plugin::get_standard_target( $post_id ), 'taxonomy:' ) ) {
				esc_html_e( 'Dynamic term URLs', 'atshift-feed-builder' );
			} else {
				$url = Atshift_Feed_Builder_Plugin::get_feed_url( $post, $format );
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( strtoupper( $format ) ) . '</a>';
			}
		}
	}

	public function title_placeholder( $placeholder, $post ) {
		if ( $post && Atshift_Feed_Builder_Plugin::POST_TYPE === $post->post_type ) {
			return __( 'Internal feed name', 'atshift-feed-builder' );
		}

		return $placeholder;
	}
}
