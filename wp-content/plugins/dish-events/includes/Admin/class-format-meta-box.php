<?php
/**
 * Format meta box — settings sidebar.
 *
 * Adds a single "Format Settings" meta box to dish_format edit screens
 * containing: calendar colour picker, default capacity, and secondary image.
 *
 * Meta keys:
 *   dish_format_color             — 7-char hex string e.g. "#c0392b"
 *   dish_default_capacity         — integer
 *   dish_format_secondary_image   — attachment ID integer
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
		$loader->add_action( 'add_meta_boxes',        $this, 'add' );
		$loader->add_action( 'save_post_dish_format', $this, 'save', 10, 2 );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_media' );
	}

	// -------------------------------------------------------------------------
	// Enqueue
	// -------------------------------------------------------------------------

	/**
	 * Enqueue WP media uploader on the dish_format edit screen only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_media( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		if ( get_current_screen()?->post_type !== 'dish_format' ) {
			return;
		}
		wp_enqueue_media();
	}

	// -------------------------------------------------------------------------
	// Meta box registration
	// -------------------------------------------------------------------------

	/**
	 * Register a single Format Settings meta box on the dish_format edit screen.
	 */
	public function add(): void {
		add_meta_box(
'dish_format_settings',
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
	 * Output the full Format Settings meta box.
	 *
	 * Contains: colour picker + quick-pick swatches, default capacity,
	 * and secondary image picker.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'dish_format_settings_nonce', 'dish_format_settings_nonce' );

		$saved            = (string) get_post_meta( $post->ID, 'dish_format_color', true );
		$color            = preg_match( '/^#[0-9a-fA-F]{6}$/', $saved ) ? $saved : '#c0392b';
		$default_capacity = (int) get_post_meta( $post->ID, 'dish_default_capacity', true );
		$is_private       = (bool) get_post_meta( $post->ID, 'dish_format_is_private', true );
		$img_id           = (int) get_post_meta( $post->ID, 'dish_format_secondary_image', true );
		$img_src          = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';
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
			<label for="dish_default_capacity" style="display:block; font-weight:600; margin-bottom:4px;">
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
			<span style="color:#787c82; font-size:12px;"><?php esc_html_e( 'Pre-fills Capacity on new ticket types', 'dish-events' ); ?></span>
		</p>

		<p style="margin-top:12px;">
			<label style="display:flex; align-items:center; gap:6px; font-weight:600; cursor:pointer;">
				<input
					type="checkbox"
					id="dish_format_is_private"
					name="dish_format_is_private"
					value="1"
					<?php checked( $is_private ); ?>
				>
				<?php esc_html_e( 'Private format', 'dish-events' ); ?>
			</label>
			<span style="display:block; color:#787c82; font-size:12px; margin-top:3px;"><?php esc_html_e( 'Listed separately on the formats archive (e.g. Private Events).', 'dish-events' ); ?></span>
		</p>

		<hr style="margin:16px 0 12px; border:none; border-top:1px solid #dcdcde;">
		<p style="font-weight:600; margin:0 0 4px;"><?php esc_html_e( 'Secondary Image', 'dish-events' ); ?></p>
		<p style="color:#787c82; font-size:12px; margin:0 0 8px;"><?php esc_html_e( 'Shown beside content when no featured class is set.', 'dish-events' ); ?></p>

		<input type="hidden" id="dish_format_secondary_image" name="dish_format_secondary_image" value="<?php echo esc_attr( $img_id ?: '' ); ?>">

		<div style="margin-bottom:8px;">
			<img
				id="dish-secondary-image-preview"
				src="<?php echo $img_src ? esc_url( $img_src ) : ''; ?>"
				alt=""
				style="width:100%; height:auto; border-radius:3px;<?php echo $img_src ? '' : ' display:none;'; ?>"
			>
		</div>

		<div style="display:flex; gap:6px;">
			<button type="button" id="dish-secondary-image-select" class="button" style="flex:1;">
				<?php echo $img_id ? esc_html__( 'Change image', 'dish-events' ) : esc_html__( 'Select image', 'dish-events' ); ?>
			</button>
			<button type="button" id="dish-secondary-image-remove" class="button" aria-label="<?php esc_attr_e( 'Remove secondary image', 'dish-events' ); ?>"<?php echo $img_id ? '' : ' style="display:none;"'; ?>>&times;</button>
		</div>

		<script>
		( function () {
			var frame;
			var sel     = document.getElementById( 'dish-secondary-image-select' );
			var rem     = document.getElementById( 'dish-secondary-image-remove' );
			var preview = document.getElementById( 'dish-secondary-image-preview' );
			var input   = document.getElementById( 'dish_format_secondary_image' );

			sel.addEventListener( 'click', function () {
				if ( frame ) { frame.open(); return; }
				frame = wp.media( {
title    : '<?php echo esc_js( __( 'Select Secondary Image', 'dish-events' ) ); ?>',
button   : { text: '<?php echo esc_js( __( 'Use this image', 'dish-events' ) ); ?>' },
multiple : false,
library  : { type: 'image' },
} );
				frame.on( 'select', function () {
					var att          = frame.state().get( 'selection' ).first().toJSON();
					input.value      = att.id;
					preview.src      = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
					preview.style.display = 'block';
					sel.textContent  = '<?php echo esc_js( __( 'Change image', 'dish-events' ) ); ?>';
					rem.style.display = '';
				} );
				frame.open();
			} );

			rem.addEventListener( 'click', function () {
				input.value           = '';
				preview.src           = '';
				preview.style.display = 'none';
				sel.textContent       = '<?php echo esc_js( __( 'Select image', 'dish-events' ) ); ?>';
				rem.style.display     = 'none';
			} );
		} () );
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Persist all Format Settings fields on post save.
	 *
	 * @param int       $post_id Post ID.
	 * @param \WP_Post  $post    Post object.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if (
			! isset( $_POST['dish_format_settings_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['dish_format_settings_nonce'] ), 'dish_format_settings_nonce' )
		) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Colour.
		$raw   = isset( $_POST['dish_format_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['dish_format_color'] ) ) : '';
		$color = $raw ?: '';
		if ( $color ) {
			update_post_meta( $post_id, 'dish_format_color', $color );
		} else {
			delete_post_meta( $post_id, 'dish_format_color' );
		}

		// Default capacity.
		$cap = absint( $_POST['dish_default_capacity'] ?? 0 );
		if ( $cap > 0 ) {
			update_post_meta( $post_id, 'dish_default_capacity', $cap );
		} else {
			delete_post_meta( $post_id, 'dish_default_capacity' );
		}

		// Secondary image.
		$img_id = absint( $_POST['dish_format_secondary_image'] ?? 0 );
		if ( $img_id > 0 ) {
			update_post_meta( $post_id, 'dish_format_secondary_image', $img_id );
		} else {
			delete_post_meta( $post_id, 'dish_format_secondary_image' );
		}

		// Private flag.
		if ( ! empty( $_POST['dish_format_is_private'] ) ) {
			update_post_meta( $post_id, 'dish_format_is_private', 1 );
		} else {
			delete_post_meta( $post_id, 'dish_format_is_private' );
		}
	}
}
