<?php
/**
 * Format meta box — colour picker.
 *
 * Adds a small "Calendar Colour" meta box to dish_format edit screens so
 * each format can have a distinct colour on the FullCalendar frontend.
 *
 * Meta key: dish_format_color  (7-char hex string, e.g. "#c0392b")
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Core\Loader;

/**
 * Class FormatMetaBox
 */
final class FormatMetaBox {

	/** Default palette offered as swatches below the colour picker. */
	private const PALETTE = [
		'#c0392b', '#e67e22', '#f1c40f',
		'#27ae60', '#2980b9', '#8e44ad',
		'#1abc9c', '#d35400', '#2c3e50',
	];

	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'add_meta_boxes', $this, 'add' );
		$loader->add_action( 'save_post_dish_format', $this, 'save', 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Meta box registration
	// -------------------------------------------------------------------------

	/**
	 * Register the meta box on the dish_format edit screen.
	 */
	public function add(): void {
		add_meta_box(
			'dish_format_color',
			__( 'Format Settings', 'dish-events' ),
			[ $this, 'render' ],
			'dish_format',
			'side',
			'default'
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Output the colour picker inside the meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'dish_format_color_nonce', 'dish_format_color_nonce' );

		$saved = (string) get_post_meta( $post->ID, 'dish_format_color', true );
		$color = preg_match( '/^#[0-9a-fA-F]{6}$/', $saved ) ? $saved : '#c0392b';
		$default_capacity = (int) get_post_meta( $post->ID, 'dish_default_capacity', true );
		?>
		<p>
			<label for="dish_format_color" class="screen-reader-text">
				<?php esc_html_e( 'Calendar colour', 'dish-events' ); ?>
			</label>
			<input
				type="color"
				id="dish_format_color"
				name="dish_format_color"
				value="<?php echo esc_attr( $color ); ?>"
				style="width:100%; height:40px; padding:2px; border:1px solid #c3c4c7; border-radius:3px; cursor:pointer;"
			>
		</p>
		<p style="margin-top:8px;">
			<strong style="font-size:11px; text-transform:uppercase; color:#50575e;">
				<?php esc_html_e( 'Quick pick', 'dish-events' ); ?>
			</strong>
		</p>
		<div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:4px;">
			<?php foreach ( self::PALETTE as $swatch ) : ?>
				<button
					type="button"
					data-color="<?php echo esc_attr( $swatch ); ?>"
					style="width:24px; height:24px; background:<?php echo esc_attr( $swatch ); ?>; border:2px solid transparent; border-radius:3px; cursor:pointer; padding:0;"
					title="<?php echo esc_attr( $swatch ); ?>"
					onclick="document.getElementById('dish_format_color').value='<?php echo esc_js( $swatch ); ?>'"
				></button>
			<?php endforeach; ?>
		</div>
		<p style="margin-top:16px;">
			<label for="dish_default_capacity" style="display:block;font-weight:600;margin-bottom:4px;">
				<?php esc_html_e( 'Default capacity', 'dish-events' ); ?>
			</label>
			<input
				type="number"
				id="dish_default_capacity"
				name="dish_default_capacity"
				value="<?php echo $default_capacity > 0 ? absint( $default_capacity ) : ''; ?>"
				min="1"
				class="small-text"
				placeholder="—"
			>
			<span style="color:#787c82;font-size:12px;"><?php esc_html_e( 'Pre-fills Capacity on new ticket types', 'dish-events' ); ?></span>
		</p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Persist the colour on post save.
	 *
	 * @param int       $post_id Post ID.
	 * @param \WP_Post  $post    Post object.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		// Nonce check.
		if (
			! isset( $_POST['dish_format_color_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['dish_format_color_nonce'] ), 'dish_format_color_nonce' )
		) {
			return;
		}

		// Autosave / revision guard.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw   = isset( $_POST['dish_format_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['dish_format_color'] ) ) : '';
		$color = $raw ?: '';

		if ( $color ) {
			update_post_meta( $post_id, 'dish_format_color', $color );
		} else {
			delete_post_meta( $post_id, 'dish_format_color' );
		}

		$cap = absint( $_POST['dish_default_capacity'] ?? 0 );
		if ( $cap > 0 ) {
			update_post_meta( $post_id, 'dish_default_capacity', $cap );
		} else {
			delete_post_meta( $post_id, 'dish_default_capacity' );
		}
	}
}
