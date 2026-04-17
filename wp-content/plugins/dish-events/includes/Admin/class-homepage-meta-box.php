<?php
/**
 * Homepage Blocks meta box.
 *
 * Adds a "Homepage Blocks" meta box to pages using the Dish Home template.
 * Provides three content blocks, each with a title field and a mini rich-text
 * editor (wp_editor with teeny toolbar). Output is rendered in page-dish-home.php.
 *
 * Meta keys (per block, n = 1–3):
 *   dish_home_block_{n}_title  — plain text string
 *   dish_home_block_{n}_text   — filtered HTML from wp_editor
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Core\Loader;

/**
 * Class HomepageMetaBox
 */
final class HomepageMetaBox {

	/** Number of content blocks. */
	private const BLOCK_COUNT = 3;

	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'add_meta_boxes', $this, 'add' );
		$loader->add_action( 'save_post_page', $this, 'save', 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Register the meta box on page edit screens.
	 * Only shown when the Dish Home template is selected.
	 */
	public function add(): void {
		// Only show on the page that uses the Dish Home template.
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'page' ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : ( isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0 );
		if ( ! $post_id ) {
			return;
		}

		if ( get_page_template_slug( $post_id ) !== 'page-dish-home.php' ) {
			return;
		}

		add_meta_box(
			'dish_homepage_blocks',
			__( 'Homepage Blocks', 'dish-events' ),
			[ $this, 'render' ],
			'page',
			'normal',
			'default'
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render all three content blocks.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'dish_homepage_settings_nonce', 'dish_homepage_settings_nonce' );
		?>
		<p style="color:#787c82; font-size:12px; margin:0 0 12px;">
			<?php esc_html_e( 'Three spotlight blocks displayed on the homepage beneath the intro content.', 'dish-events' ); ?>
		</p>
		<?php for ( $i = 1; $i <= self::BLOCK_COUNT; $i++ ) :
			$title   = (string) get_post_meta( $post->ID, "dish_home_block_{$i}_title", true );
			$content = (string) get_post_meta( $post->ID, "dish_home_block_{$i}_text",  true );
			$editor_id = "dish_home_block_{$i}_text";
			?>
			<div style="border:1px solid #dcdcde; border-radius:3px; padding:12px 14px; margin-bottom:14px;">
				<p style="font-weight:600; margin:0 0 8px; font-size:13px;">
					<?php
					/* translators: %d: block number */
					printf( esc_html__( 'Block %d', 'dish-events' ), $i );
					?>
				</p>
				<p style="margin:0 0 6px;">
					<label for="dish_home_block_<?php echo $i; ?>_title" style="display:block; font-size:12px; color:#50575e; margin-bottom:3px;">
						<?php esc_html_e( 'Title', 'dish-events' ); ?>
					</label>
					<input
						type="text"
						id="dish_home_block_<?php echo $i; ?>_title"
						name="dish_home_block_<?php echo $i; ?>_title"
						value="<?php echo esc_attr( $title ); ?>"
						class="widefat"
						placeholder="<?php esc_attr_e( 'Block heading…', 'dish-events' ); ?>"
					>
				</p>
				<p style="margin:0 0 4px; font-size:12px; color:#50575e;">
					<?php esc_html_e( 'Text', 'dish-events' ); ?>
				</p>
				<?php
				wp_editor(
					$content,
					$editor_id,
					[
						'textarea_name' => $editor_id,
						'media_buttons' => false,
						'textarea_rows' => 5,
						'teeny'         => true,
						'quicktags'     => true,
					]
				);
				?>
			</div>
		<?php endfor; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Persist all block fields on page save.
	 *
	 * @param int       $post_id Post ID.
	 * @param \WP_Post  $post    Post object.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if (
			! isset( $_POST['dish_homepage_settings_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['dish_homepage_settings_nonce'] ), 'dish_homepage_settings_nonce' )
		) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		for ( $i = 1; $i <= self::BLOCK_COUNT; $i++ ) {
			// Title — plain text.
			$title_key = "dish_home_block_{$i}_title";
			$title     = sanitize_text_field( wp_unslash( $_POST[ $title_key ] ?? '' ) );
			if ( $title !== '' ) {
				update_post_meta( $post_id, $title_key, $title );
			} else {
				delete_post_meta( $post_id, $title_key );
			}

			// Text — allowed HTML (from wp_editor).
			$text_key = "dish_home_block_{$i}_text";
			$text     = wp_kses_post( wp_unslash( $_POST[ $text_key ] ?? '' ) );
			if ( $text !== '' ) {
				update_post_meta( $post_id, $text_key, $text );
			} else {
				delete_post_meta( $post_id, $text_key );
			}
		}
	}
}
