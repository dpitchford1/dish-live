<?php
/**
 * Tabbed meta box for the dish_class post type.
 *
 * Tabs
 * ----
 *  1. Date & Time  -- start/end datetime, recurrence
 *  2. Template     -- class template selection, booking-opens override
 *  3. Details      -- chefs, class type, admin notes
 *  4. Checkout     -- per-class checkout field overrides
 *  5. Settings     -- private flag, featured, QR, template override
 *
 * Each tab's render and save logic lives in its own Panel class under
 * includes/Admin/Panels/. This file is a thin orchestrator only.
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Admin\Panels\DatetimePanel;
use Dish\Events\Admin\Panels\TemplatePanel;
use Dish\Events\Admin\Panels\DetailsPanel;
use Dish\Events\Admin\Panels\CheckoutPanel;
use Dish\Events\Admin\Panels\SettingsPanel;

/**
 * Class ClassMetaBox
 */
final class ClassMetaBox {

	private const NONCE_ACTION    = 'dish_save_class_meta';
	private const NONCE_FIELD     = 'dish_class_meta_nonce';
	private const ERRORS_TRANSIENT = 'dish_class_errors_';

	private DatetimePanel $datetime;
	private TemplatePanel $template;
	private DetailsPanel  $details;
	private CheckoutPanel $checkout;
	private SettingsPanel $settings;

	public function __construct() {
		$this->datetime = new DatetimePanel();
		$this->template = new TemplatePanel();
		$this->details  = new DetailsPanel();
		$this->checkout = new CheckoutPanel();
		$this->settings = new SettingsPanel();
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Register the single tabbed meta box.
	 * Hooked to 'add_meta_boxes'.
	 */
	public function register(): void {
		add_meta_box(
			'dish_class_settings',
			__( 'Class Settings', 'dish-events' ),
			[ $this, 'render' ],
			'dish_class',
			'normal',
			'high'
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render the tabbed meta box shell and delegate to each panel.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$meta = $this->get_meta( $post->ID );
		$tabs = [
			'datetime'       => __( 'Date & Time', 'dish-events' ),
			'template'       => __( 'Template',    'dish-events' ),
			'details'        => __( 'Details',     'dish-events' ),
			'checkout'       => __( 'Checkout',    'dish-events' ),
			'class-settings' => __( 'Settings',    'dish-events' ),
		];
		?>
		<div class="dish-metabox" id="dish-class-metabox">

			<nav class="dish-metabox__nav" role="tablist">
				<?php foreach ( $tabs as $slug => $label ) : ?>
				<button
					type="button"
					role="tab"
					class="dish-metabox__tab<?php echo $slug === 'datetime' ? ' is-active' : ''; ?>"
					data-tab="<?php echo esc_attr( $slug ); ?>"
					aria-selected="<?php echo $slug === 'datetime' ? 'true' : 'false'; ?>"
				><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</nav>

			<div class="dish-metabox__panels">
				<?php $this->datetime->render( $meta ); ?>
				<?php $this->template->render( $meta ); ?>
				<?php $this->details->render( $meta ); ?>
				<?php $this->checkout->render( $meta ); ?>
				<?php $this->settings->render( $meta ); ?>
			</div>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Validate nonce + capability, then delegate save to each panel.
	 * Hooked to 'save_post_dish_class'.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		// Bail during the shutdown phase — RecurrenceManager calls wp_insert_post()
		// programmatically; $_POST still holds the parent form data so the nonce
		// would pass, causing every child's dates to be overwritten with the
		// parent's start datetime.
		if ( doing_action( 'shutdown' ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if (
			! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ),
				self::NONCE_ACTION
			)
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Validate required fields and surface errors via admin_notices.
		$errors = self::validate_post_data();
		if ( ! empty( $errors ) ) {
			set_transient( self::ERRORS_TRANSIENT . $post_id, $errors, 60 );
		}

		$this->datetime->save( $post_id );
		$this->template->save( $post_id );
		$this->details->save( $post_id );
		$this->checkout->save( $post_id );
		$this->settings->save( $post_id );

		// If the "apply to series" checkbox was ticked, propagate to all children.
		if ( $this->datetime->should_apply_to_series() ) {
			add_action( 'shutdown', function () use ( $post_id ): void {
				( new \Dish\Events\Recurrence\RecurrenceManager() )->update_series( $post_id );
			} );
		}
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Validate required class fields from $_POST.
	 *
	 * @return string[] Error messages; empty array if all valid.
	 */
	private static function validate_post_data(): array {
		$errors    = [];
		$start_raw = sanitize_text_field( wp_unslash( $_POST['dish_start_datetime'] ?? '' ) );
		$end_raw   = sanitize_text_field( wp_unslash( $_POST['dish_end_datetime']   ?? '' ) );
		$template  = absint( $_POST['dish_template_id'] ?? 0 );

		if ( empty( $start_raw ) ) {
			$errors[] = __( 'Start date & time is required (Date & Time tab).', 'dish-events' );
		}

		if ( empty( $end_raw ) ) {
			$errors[] = __( 'End date & time is required (Date & Time tab).', 'dish-events' );
		}

		if ( ! empty( $start_raw ) && ! empty( $end_raw ) ) {
			try {
				$tz    = new \DateTimeZone( wp_timezone_string() ?: 'UTC' );
				$start = new \DateTimeImmutable( $start_raw, $tz );
				$end   = new \DateTimeImmutable( $end_raw,   $tz );
				if ( $end <= $start ) {
					$errors[] = __( 'End date & time must be after the start date & time (Date & Time tab).', 'dish-events' );
				}
			} catch ( \Exception $e ) {
				$errors[] = __( 'One or more dates could not be parsed — please check the format (Date & Time tab).', 'dish-events' );
			}
		}

		if ( $template === 0 ) {
			$errors[] = __( 'A Class Template must be selected (Template tab).', 'dish-events' );
		}

		return $errors;
	}

	/**
	 * Force the post back to draft status when required fields are missing.
	 * Hooked to 'wp_insert_post_data' — fires before the DB write.
	 *
	 * @param array<string,mixed> $data    Sanitised post data.
	 * @param array<string,mixed> $postarr Raw $_POST data.
	 * @return array<string,mixed>
	 */
	public function maybe_force_draft( array $data, array $postarr ): array {
		if ( ( $data['post_type'] ?? '' ) !== 'dish_class' ) {
			return $data;
		}

		if (
			! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ),
				self::NONCE_ACTION
			)
		) {
			return $data;
		}

		if ( ! empty( self::validate_post_data() ) ) {
			$data['post_status'] = 'draft';
		}

		return $data;
	}

	/**
	 * Display admin notices for class validation errors.
	 * Reads the transient set during save() and deletes it after display.
	 * Hooked to 'admin_notices'.
	 */
	public function show_admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'dish_class' ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $post_id ) {
			return;
		}

		$key    = self::ERRORS_TRANSIENT . $post_id;
		$errors = get_transient( $key );
		if ( empty( $errors ) || ! is_array( $errors ) ) {
			return;
		}

		delete_transient( $key );

		echo '<div class="notice notice-error">';
		echo '<p><strong>' . esc_html__( 'This class could not be published — please correct the following:', 'dish-events' ) . '</strong></p>';
		echo '<ul style="list-style:disc;margin-left:2em">';
		foreach ( $errors as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Load all single post meta for a class into a flat associative array.
	 * JSON fields (dish_recurrence, dish_chef_ids) are decoded to arrays.
	 *
	 * @param  int $post_id
	 * @return array<string,mixed>
	 */
	private function get_meta( int $post_id ): array {
		$raw = get_post_meta( $post_id );
		$out = [ '_post_id' => $post_id ];

		foreach ( $raw as $key => $values ) {
			$out[ $key ] = $values[0] ?? null;
		}

		foreach ( [ 'dish_recurrence', 'dish_chef_ids' ] as $json_key ) {
			if ( isset( $out[ $json_key ] ) && is_string( $out[ $json_key ] ) ) {
				$decoded          = json_decode( $out[ $json_key ], true );
				$out[ $json_key ] = is_array( $decoded ) ? $decoded : null;
			}
		}

		return $out;
	}
}
