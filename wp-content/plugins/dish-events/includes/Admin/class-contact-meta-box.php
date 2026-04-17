<?php
/**
 * Contact Form meta box.
 *
 * Adds a "Contact Form" meta box to pages using the Contact Template.
 * Stores a single CF7 shortcode string per page so the template can render
 * the correct form without hardcoded IDs or URL checks.
 *
 * Meta key:
 *   dish_cf7_shortcode — raw CF7 shortcode string e.g. [contact-form-7 id="abc" title="..."]
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Core\Loader;

/**
 * Class ContactMetaBox
 */
final class ContactMetaBox {

	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'add_meta_boxes', $this, 'add' );
		$loader->add_action( 'save_post_page', $this, 'save', 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Register the meta box — only on pages using the Contact Template.
	 */
	public function add(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'page' ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : ( isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0 );
		if ( ! $post_id ) {
			return;
		}

		if ( get_page_template_slug( $post_id ) !== 'page-contact.php' ) {
			return;
		}

		add_meta_box(
			'dish_contact_form',
			__( 'Contact Form', 'dish-events' ),
			[ $this, 'render' ],
			'page',
			'normal',
			'high'
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render the Contact Form meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'dish_contact_form_nonce', 'dish_contact_form_nonce' );

		$shortcode = (string) get_post_meta( $post->ID, 'dish_cf7_shortcode', true );
		?>
		<p style="color:#787c82; font-size:12px; margin:0 0 10px;">
			<?php esc_html_e( 'Paste the Contact Form 7 shortcode for this page. Leave blank to show no form.', 'dish-events' ); ?>
		</p>
		<input
			type="text"
			id="dish_cf7_shortcode"
			name="dish_cf7_shortcode"
			value="<?php echo esc_attr( $shortcode ); ?>"
			class="widefat"
			placeholder='[contact-form-7 id="abc123" title="General Inquiry"]'
		>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Persist the CF7 shortcode field on page save.
	 *
	 * @param int       $post_id Post ID.
	 * @param \WP_Post  $post    Post object.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if (
			! isset( $_POST['dish_contact_form_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['dish_contact_form_nonce'] ), 'dish_contact_form_nonce' )
		) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$shortcode = sanitize_text_field( wp_unslash( $_POST['dish_cf7_shortcode'] ?? '' ) );

		if ( $shortcode !== '' ) {
			update_post_meta( $post_id, 'dish_cf7_shortcode', $shortcode );
		} else {
			delete_post_meta( $post_id, 'dish_cf7_shortcode' );
		}
	}
}
