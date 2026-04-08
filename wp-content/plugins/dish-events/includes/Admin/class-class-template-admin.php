<?php
/**
 * Class Template meta box, list-table columns, and permalink handling.
 *
 * Responsible for:
 *  - The "Template Details" meta box on dish_class_template edit screens
 *  - Ticket type, gallery, social links, and theme override fields
 *  - Auto-deriving dish_format_id from the selected ticket type on save
 *  - List-table columns (Format, Ticket Type)
 *  - Custom rewrite rule for /classes/{format-slug}/{template-slug}/
 *  - post_type_link filter to build the nested URL
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Core\Loader;
use WP_Post;

/**
 * Class ClassTemplateAdmin
 */
final class ClassTemplateAdmin {

	/**
	 * Register all hooks via the Loader.
	 */
	public function register_hooks( Loader $loader ): void {

		// Meta box.
		$loader->add_action( 'add_meta_boxes',                   $this, 'register_meta_box' );
		$loader->add_action( 'save_post_dish_class_template',    $this, 'save', 10, 2 );
		$loader->add_action( 'admin_enqueue_scripts',            $this, 'enqueue_assets' );

		// List-table columns.
		$loader->add_filter( 'manage_dish_class_template_posts_columns',       $this, 'add_columns' );
		$loader->add_action( 'manage_dish_class_template_posts_custom_column', $this, 'render_column', 10, 2 );

		// Notice when redirected here from a new ticket type creation.
		$loader->add_action( 'admin_notices', $this, 'maybe_show_created_notice' );

		// Permalink hooks (register_rewrite_rule + filter_post_type_link) are wired
		// unconditionally in Plugin::wire_hooks() so they run on both frontend and
		// admin. Do not re-register them here.
	}

	// -------------------------------------------------------------------------
	// Notices
	// -------------------------------------------------------------------------

	/**
	 * Show a one-time notice when the admin arrives here via the auto-create
	 * redirect from the ticket type save handler.
	 */
	public function maybe_show_created_notice(): void {
		global $post;

		if ( empty( $_GET['dish_from_ticket_type'] ) ) {
			return;
		}

		if ( ! $post || 'dish_class_template' !== $post->post_type ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html__( 'Class Template created. Fill in the remaining details below and publish when ready.', 'dish-events' )
			. '</p></div>';
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue the WP Media Library on dish_class_template edit screens.
	 */
	public function enqueue_assets( string $hook ): void {
		global $post;

		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		if ( ! $post || 'dish_class_template' !== $post->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'dish-chef-admin',
			DISH_EVENTS_URL . 'assets/js/dish-chef-admin.js',
			[],
			DISH_EVENTS_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Meta Box
	// -------------------------------------------------------------------------

	/**
	 * Register the "Template Details" meta box on dish_class_template.
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'dish_template_details',
			__( 'Template Details', 'dish-events' ),
			[ $this, 'render_meta_box' ],
			'dish_class_template',
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box HTML.
	 */
	public function render_meta_box( WP_Post $post ): void {
		$ticket_type_id = (int) get_post_meta( $post->ID, 'dish_ticket_type_id', true );
		$format_id      = (int) get_post_meta( $post->ID, 'dish_format_id',      true );
		$raw_gallery    = get_post_meta( $post->ID, 'dish_gallery_ids',   true ) ?: '[]';
		$gallery_ids    = (array) json_decode( $raw_gallery, true );
		$event_theme    = (string) get_post_meta( $post->ID, 'dish_event_theme', true );

		$booking_type      = (string) get_post_meta( $post->ID, 'dish_booking_type', true ) ?: 'online';
		$is_featured       = (bool)   get_post_meta( $post->ID, 'dish_is_featured',      true );
		$is_spotlight      = (bool)   get_post_meta( $post->ID, 'dish_is_spotlight',     true );
		$is_guest_chef     = (bool)   get_post_meta( $post->ID, 'dish_is_guest_chef',    true );
		$guest_chef_name   = (string) get_post_meta( $post->ID, 'dish_guest_chef_name',  true );
		$guest_chef_role   = (string) get_post_meta( $post->ID, 'dish_guest_chef_role',  true );
		$enquiry_format_id = (int) get_post_meta( $post->ID, 'dish_format_id', true );

		// Formats for the enquiry-mode selector.
		$all_formats = get_posts( [
			'post_type'   => 'dish_format',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		] );

		// Active ticket types grouped by format.
		// No user-supplied values in this query, but $wpdb->prepare() is used
		// consistently throughout the plugin as a defensive coding pattern.
		global $wpdb;
		$types = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT tt.id, tt.name, tt.format_id,
				        COALESCE(p.post_title, '—') AS format_name
				 FROM   {$wpdb->prefix}dish_ticket_types tt
				 LEFT JOIN {$wpdb->prefix}posts p ON p.ID = tt.format_id AND p.post_status = %s
				 WHERE  tt.is_active = 1
				 ORDER  BY format_name ASC, tt.name ASC",
				'publish'
			)
		) ?: [];

		wp_nonce_field( 'dish_template_save_' . $post->ID, 'dish_template_nonce' );
		?>
		<div class="dish-meta-box dish-template-meta-box">

			<?php /* ---- Booking Type ----------------------------------------------- */ ?>
			<div class="dish-field">
				<label for="dish_booking_type"><?php esc_html_e( 'Booking Type', 'dish-events' ); ?></label>
				<select name="dish_booking_type" id="dish_booking_type">
					<option value="online"  <?php selected( $booking_type, 'online' );  ?>><?php esc_html_e( 'Online — standard checkout', 'dish-events' ); ?></option>
					<option value="enquiry" <?php selected( $booking_type, 'enquiry' ); ?>><?php esc_html_e( 'By Request — contact to book', 'dish-events' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( '"By Request" hides the price and replaces the booking button with an enquiry link. No ticket type required.', 'dish-events' ); ?></p>
			</div>

			<?php /* ---- Guest Chef flag -------------------------------------------- */ ?>
			<div class="dish-field">
				<label>
					<input type="checkbox" name="dish_is_guest_chef" id="dish_is_guest_chef" value="1" <?php checked( $is_guest_chef ); ?>>
					<?php esc_html_e( 'Guest Chef class', 'dish-events' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Changes the "Your Chef" label and shows the name below on the frontend.', 'dish-events' ); ?></p>
				<div id="dish-guest-chef-fields" style="margin-top:8px;padding:10px 12px;background:#f6f7f7;border-left:3px solid #c3c4c7;<?php echo $is_guest_chef ? '' : 'display:none;'; ?>">
					<p style="margin:0 0 6px">
						<label for="dish_guest_chef_name" style="display:block;font-weight:600;margin-bottom:2px"><?php esc_html_e( 'Name', 'dish-events' ); ?></label>
						<input type="text" id="dish_guest_chef_name" name="dish_guest_chef_name"
						       value="<?php echo esc_attr( $guest_chef_name ); ?>"
						       class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Marco Canora', 'dish-events' ); ?>">
					</p>
					<p style="margin:0">
						<label for="dish_guest_chef_role" style="display:block;font-weight:600;margin-bottom:2px"><?php esc_html_e( 'Title / Role', 'dish-events' ); ?></label>
						<input type="text" id="dish_guest_chef_role" name="dish_guest_chef_role"
						       value="<?php echo esc_attr( $guest_chef_role ); ?>"
						       class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Head Butcher, Sanagan\'s Meat Locker', 'dish-events' ); ?>">
					</p>
				</div>
				<script>
				(function () {
					var cb = document.getElementById( 'dish_is_guest_chef' );
					var fields = document.getElementById( 'dish-guest-chef-fields' );
					if ( cb && fields ) {
						cb.addEventListener( 'change', function () {
							fields.style.display = cb.checked ? '' : 'none';
						} );
					}
				})();
				</script>
			</div>

			<?php /* ---- Ticket Type (always; for enquiry, used for capacity only) --- */ ?>
			<div class="dish-field" id="dish-ticket-type-field">
				<label for="dish_ticket_type_id">
					<?php esc_html_e( 'Ticket Type', 'dish-events' ); ?>
					<span class="required" aria-hidden="true">*</span>
				</label>
				<select name="dish_ticket_type_id" id="dish_ticket_type_id">
					<option value=""><?php esc_html_e( '— Select a Ticket Type —', 'dish-events' ); ?></option>
					<?php
					$current_group = null;
					foreach ( $types as $type ) {
						if ( $type->format_name !== $current_group ) {
							if ( null !== $current_group ) {
								echo '</optgroup>';
							}
							$current_group = $type->format_name;
							echo '<optgroup label="' . esc_attr( $type->format_name ) . '">';
						}
						printf(
							'<option value="%d"%s>%s</option>',
							(int) $type->id,
							selected( $ticket_type_id, (int) $type->id, false ),
							esc_html( $type->name )
						);
					}
					if ( null !== $current_group ) {
						echo '</optgroup>';
					}
					?>
				</select>
				<?php if ( $format_id ) :
					$fmt = get_post( $format_id ); ?>
				<p class="dish-field-note">
					<?php
					printf(
						/* translators: %s: format name */
						esc_html__( 'Format: %s', 'dish-events' ),
						$fmt ? esc_html( $fmt->post_title ) : esc_html__( '(unknown)', 'dish-events' )
					);
					?>
				</p>
				<?php endif; ?>
			</div>
			<?php /* ---- Format (by-request only) ------------------------------------ */ ?>
			<div class="dish-field" id="dish-enquiry-format-field"<?php echo $booking_type !== 'enquiry' ? ' style="display:none"' : ''; ?>>
				<label for="dish_enquiry_format_id"><?php esc_html_e( 'Format', 'dish-events' ); ?> <span class="required" aria-hidden="true">*</span></label>
				<select name="dish_enquiry_format_id" id="dish_enquiry_format_id">
					<option value=""><?php esc_html_e( '— Select a Format —', 'dish-events' ); ?></option>
					<?php foreach ( $all_formats as $afmt ) : ?>
						<option value="<?php echo absint( $afmt->ID ); ?>" <?php selected( $enquiry_format_id, (int) $afmt->ID ); ?>>
							<?php echo esc_html( $afmt->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Determines the URL and breadcrumb trail for this class.', 'dish-events' ); ?></p>
			</div>

			<script>
			(function () {
				const bt = document.getElementById( 'dish_booking_type' );
				if ( ! bt ) return;
				function toggleFields() {
					const isEnquiry = bt.value === 'enquiry';
					document.getElementById( 'dish-enquiry-format-field' ).style.display = isEnquiry ? '' : 'none';
				}
				bt.addEventListener( 'change', toggleFields );
			})();
			</script>
			<?php /* ---- Gallery ---------------------------------------------------- */ ?>
			<div class="dish-field">
				<label><?php esc_html_e( 'Gallery Images', 'dish-events' ); ?></label>
				<input type="hidden" name="dish_gallery_ids" id="dish_gallery_ids"
				       value="<?php echo esc_attr( wp_json_encode( $gallery_ids ) ); ?>">
				<div id="dish-gallery-preview" class="dish-gallery-preview">
					<?php foreach ( $gallery_ids as $aid ) : ?>
						<?php echo wp_get_attachment_image( (int) $aid, [ 80, 80 ] ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button dish-gallery-add" id="dish-gallery-add"
				        data-input="dish_gallery_ids"
				        data-preview="dish-gallery-preview"
				        data-clear="dish-gallery-clear"
				        data-frame-title="<?php echo esc_attr( __( 'Select Gallery Images', 'dish-events' ) ); ?>"
				        data-frame-button="<?php echo esc_attr( __( 'Add to Gallery', 'dish-events' ) ); ?>">
					<?php esc_html_e( 'Add Images', 'dish-events' ); ?>
				</button>
				<button type="button" class="button" id="dish-gallery-clear"
				        <?php echo empty( $gallery_ids ) ? 'style="display:none"' : ''; ?>>
					<?php esc_html_e( 'Clear Gallery', 'dish-events' ); ?>
				</button>
			</div>

			<?php /* ---- Frontend Template ------------------------------------------ */ ?>
			<div class="dish-field">
				<label for="dish_event_theme"><?php esc_html_e( 'Frontend Template', 'dish-events' ); ?></label>
				<input type="text" name="dish_event_theme" id="dish_event_theme"
				       value="<?php echo esc_attr( $event_theme ); ?>"
				       placeholder="<?php esc_attr_e( 'default', 'dish-events' ); ?>">
				<p class="description">
					<?php esc_html_e( 'Optional template slug override. Leave blank to use the default template.', 'dish-events' ); ?>
				</p>
			</div>

			<?php /* ---- Featured flag ---------------------------------------------- */ ?>
			<div class="dish-field">
				<label>
					<input type="checkbox" name="dish_is_featured" value="1" <?php checked( $is_featured ); ?>>
					<?php esc_html_e( 'Mark as featured class', 'dish-events' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Featured classes are pulled out of the standard grid and displayed prominently on the format page.', 'dish-events' ); ?></p>
			</div>

			<?php /* ---- Spotlight flag -------------------------------------------- */ ?>
			<div class="dish-field">
				<label>
					<input type="checkbox" name="dish_is_spotlight" value="1" <?php checked( $is_spotlight ); ?>>
					<?php esc_html_e( 'Class in the Spotlight', 'dish-events' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Promotes this template as the “Class in the Spotlight” component. Only one template can hold this flag — saving will automatically remove it from any other template.', 'dish-events' ); ?></p>
			</div>

		</div>

		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Save meta box data.
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save( int $post_id, WP_Post $post ): void {
		// Skip autosaves and revisions.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Verify nonce.
		$nonce = sanitize_text_field( wp_unslash( $_POST['dish_template_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'dish_template_save_' . $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Booking type.
		$booking_type = sanitize_key( wp_unslash( $_POST['dish_booking_type'] ?? 'online' ) );
		$booking_type = in_array( $booking_type, [ 'online', 'enquiry' ], true ) ? $booking_type : 'online';
		update_post_meta( $post_id, 'dish_booking_type', $booking_type );

		// Guest Chef flag.
		update_post_meta( $post_id, 'dish_is_guest_chef', ! empty( $_POST['dish_is_guest_chef'] ) ? 1 : 0 );
		update_post_meta( $post_id, 'dish_guest_chef_name', sanitize_text_field( wp_unslash( $_POST['dish_guest_chef_name'] ?? '' ) ) );
		update_post_meta( $post_id, 'dish_guest_chef_role', sanitize_text_field( wp_unslash( $_POST['dish_guest_chef_role'] ?? '' ) ) );

		// Featured flag.
		update_post_meta( $post_id, 'dish_is_featured', ! empty( $_POST['dish_is_featured'] ) ? 1 : 0 );

		// Spotlight — only one template can hold this flag at a time.
		$is_spotlight = ! empty( $_POST['dish_is_spotlight'] );
		if ( $is_spotlight ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'dish_is_spotlight' AND meta_value = '1' AND post_id != %d",
					$post_id
				)
			);
		}
		update_post_meta( $post_id, 'dish_is_spotlight', $is_spotlight ? 1 : 0 );

		if ( $booking_type === 'enquiry' ) {
			// Ticket type is optional for enquiry; used only to surface capacity on the frontend.
			$ticket_type_id = absint( $_POST['dish_ticket_type_id'] ?? 0 );
			update_post_meta( $post_id, 'dish_ticket_type_id', $ticket_type_id );
			$enquiry_format_id = absint( $_POST['dish_enquiry_format_id'] ?? 0 );
			if ( $enquiry_format_id ) {
				update_post_meta( $post_id, 'dish_format_id', $enquiry_format_id );
			} else {
				delete_post_meta( $post_id, 'dish_format_id' );
			}
		} else {
			// Ticket Type (online).
			$ticket_type_id = absint( $_POST['dish_ticket_type_id'] ?? 0 );
			update_post_meta( $post_id, 'dish_ticket_type_id', $ticket_type_id );

			// Auto-derive Format ID from the selected ticket type.
			if ( $ticket_type_id > 0 ) {
				global $wpdb;
				$format_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT format_id FROM {$wpdb->prefix}dish_ticket_types WHERE id = %d LIMIT 1",
						$ticket_type_id
					)
				);
				update_post_meta( $post_id, 'dish_format_id', $format_id );
			} else {
				delete_post_meta( $post_id, 'dish_format_id' );
			}
		}

		// Gallery IDs — JSON array of attachment IDs.
		$raw_gallery = sanitize_text_field( wp_unslash( $_POST['dish_gallery_ids'] ?? '' ) );
		$gallery_ids = json_decode( $raw_gallery, true );
		if ( ! is_array( $gallery_ids ) ) {
			$gallery_ids = [];
		}
		$gallery_ids = array_values( array_map( 'absint', $gallery_ids ) );
		update_post_meta( $post_id, 'dish_gallery_ids', wp_json_encode( $gallery_ids ) );

		// Frontend template slug.
		$theme = sanitize_key( wp_unslash( $_POST['dish_event_theme'] ?? '' ) );
		if ( $theme ) {
			update_post_meta( $post_id, 'dish_event_theme', $theme );
		} else {
			delete_post_meta( $post_id, 'dish_event_theme' );
		}
	}

	// -------------------------------------------------------------------------
	// List-Table Columns
	// -------------------------------------------------------------------------

	/**
	 * Inject Format and Ticket Type columns after Title.
	 *
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function add_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['dish_format']      = __( 'Format',      'dish-events' );
				$new['dish_ticket_type'] = __( 'Ticket Type', 'dish-events' );
			}
		}
		return $new;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column
	 * @param int    $post_id
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'dish_format':
				$format_id = (int) get_post_meta( $post_id, 'dish_format_id', true );
				if ( $format_id ) {
					$fmt = get_post( $format_id );
					echo $fmt ? esc_html( $fmt->post_title ) : '—';
				} else {
					echo '—';
				}
				break;

			case 'dish_ticket_type':
				$tid = (int) get_post_meta( $post_id, 'dish_ticket_type_id', true );
				if ( $tid ) {
					global $wpdb;
					$name = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT name FROM {$wpdb->prefix}dish_ticket_types WHERE id = %d LIMIT 1",
							$tid
						)
					);
					echo $name ? esc_html( $name ) : '—';
				} else {
					echo '—';
				}
				break;
		}
	}

	// -------------------------------------------------------------------------
	// Permalink Handling
	// -------------------------------------------------------------------------

	/**
	 * Register a custom rewrite rule for /classes/{format-slug}/{template-slug}/.
	 *
	 * Runs on init at priority 20 — after CPTs register at priority 10 — so
	 * the more-specific 3-segment rule is added to extra_rules_top before the
	 * 2-segment dish_format rule is compiled.
	 */
	public function register_rewrite_rule(): void {
		$settings = (array) get_option( 'dish_settings', [] );
		$base     = sanitize_title( $settings['class_format_slug'] ?? 'classes' );

		// matches /classes/{format-slug}/{template-slug}/
		add_rewrite_rule(
			$base . '/([^/]+)/([^/]+)/?$',
			'index.php?dish_class_template=$matches[2]',
			'top'
		);
	}

	/**
	 * Build the nested permalink for dish_class_template posts.
	 *
	 * Returns /classes/{format-slug}/{template-slug}/ when the post has a
	 * resolved dish_format_id. Falls back to the original URL otherwise so
	 * drafts and templates without a format still have a usable link.
	 *
	 * @param string  $url
	 * @param WP_Post $post
	 */
	public function filter_post_type_link( string $url, WP_Post $post ): string {
		if ( 'dish_class_template' !== $post->post_type ) {
			return $url;
		}

		$format_id = (int) get_post_meta( $post->ID, 'dish_format_id', true );
		if ( ! $format_id ) {
			return $url;
		}

		$format = get_post( $format_id );
		if ( ! $format || 'publish' !== $format->post_status ) {
			return $url;
		}

		$settings = (array) get_option( 'dish_settings', [] );
		$base     = sanitize_title( $settings['class_format_slug'] ?? 'classes' );

		return home_url( trailingslashit( $base . '/' . $format->post_name . '/' . $post->post_name ) );
	}
}
