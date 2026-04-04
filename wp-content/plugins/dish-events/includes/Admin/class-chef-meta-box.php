<?php
/**
 * Chef profile meta box.
 *
 * Handles: role, website, social links (Instagram, LinkedIn, TikTok), gallery (JSON attachment IDs).
 * The title and featured image are handled natively by WP (supports: title, thumbnail).
 * The bio lives in the standard WP editor (supports: editor).
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use WP_Post;

/**
 * Class ChefMetaBox
 */
final class ChefMetaBox {

	/**
	 * Register the meta box on dish_chef edit screens.
	 * Hooked to 'add_meta_boxes'.
	 */
	public function register(): void {
		add_meta_box(
			'dish_chef_details',
			__( 'Chef Details', 'dish-events' ),
			[ $this, 'render' ],
			'dish_chef',
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue WP media library on dish_chef edit screens.
	 * Hooked to 'admin_enqueue_scripts'.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'dish_chef' ) {
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
	// Render
	// -------------------------------------------------------------------------

	public function render( WP_Post $post ): void {
		$is_team_member = (bool) get_post_meta( $post->ID, 'dish_is_team_member', true );
		$role      = (string) get_post_meta( $post->ID, 'dish_chef_role',      true );
		$website   = (string) get_post_meta( $post->ID, 'dish_chef_website',   true );
		$instagram = (string) get_post_meta( $post->ID, 'dish_chef_instagram', true );
		$linkedin  = (string) get_post_meta( $post->ID, 'dish_chef_linkedin',  true );
		$tiktok    = (string) get_post_meta( $post->ID, 'dish_chef_tiktok',    true );
		$raw       = get_post_meta( $post->ID, 'dish_chef_gallery_ids',  true ) ?: '[]';
		$gallery   = (array) json_decode( $raw, true );

		wp_nonce_field( 'dish_chef_save_' . $post->ID, 'dish_chef_nonce' );
		?>
		<div class="dish-meta-box dish-chef-meta-box">
			<table class="form-table dish-form-table">

				<tr>
					<th><label for="dish_chef_role"><?php esc_html_e( 'Role', 'dish-events' ); ?></label></th>
					<td>
						<input type="text" id="dish_chef_role" name="dish_chef_role"
						       value="<?php echo esc_attr( $role ); ?>" class="regular-text"
						       placeholder="<?php esc_attr_e( 'e.g. Head Chef, Sous Chef, Prep', 'dish-events' ); ?>">
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Type', 'dish-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="dish_is_team_member" value="1" <?php checked( $is_team_member ); ?>>
							<?php esc_html_e( 'Team member (not a teaching chef)', 'dish-events' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Check for kitchen staff, managers, and support team. They appear in a separate &ldquo;The Team&rdquo; section on the archive page and are excluded from class assignments.', 'dish-events' ); ?></p>
					</td>
				</tr>

				<tr>
					<th><label for="dish_chef_website"><?php esc_html_e( 'Website', 'dish-events' ); ?></label></th>
					<td>
						<input type="url" id="dish_chef_website" name="dish_chef_website"
						       value="<?php echo esc_attr( $website ); ?>" class="regular-text"
						       placeholder="https://...">
					</td>
				</tr>

				<tr>
					<th><label for="dish_chef_instagram"><?php esc_html_e( 'Instagram', 'dish-events' ); ?></label></th>
					<td>
						<input type="url" id="dish_chef_instagram" name="dish_chef_instagram"
						       value="<?php echo esc_attr( $instagram ); ?>" class="regular-text"
						       placeholder="https://instagram.com/...">
					</td>
				</tr>

				<tr>
					<th><label for="dish_chef_linkedin"><?php esc_html_e( 'LinkedIn', 'dish-events' ); ?></label></th>
					<td>
						<input type="url" id="dish_chef_linkedin" name="dish_chef_linkedin"
						       value="<?php echo esc_attr( $linkedin ); ?>" class="regular-text"
						       placeholder="https://linkedin.com/in/...">
					</td>
				</tr>

				<tr>
					<th><label for="dish_chef_tiktok"><?php esc_html_e( 'TikTok', 'dish-events' ); ?></label></th>
					<td>
						<input type="url" id="dish_chef_tiktok" name="dish_chef_tiktok"
						       value="<?php echo esc_attr( $tiktok ); ?>" class="regular-text"
						       placeholder="https://tiktok.com/@...">
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Gallery', 'dish-events' ); ?></th>
					<td>
						<input type="hidden" name="dish_chef_gallery_ids" id="dish_chef_gallery_ids"
						       value="<?php echo esc_attr( wp_json_encode( $gallery ) ); ?>">
						<div id="dish-chef-gallery-preview" class="dish-gallery-preview">
							<?php foreach ( $gallery as $aid ) :
								echo wp_get_attachment_image( (int) $aid, [ 80, 80 ] );
							endforeach; ?>
						</div>
					<button type="button" class="button" id="dish-chef-gallery-add"
					        data-frame-title="<?php echo esc_attr( __( 'Select Gallery Images', 'dish-events' ) ); ?>"
					        data-frame-button="<?php echo esc_attr( __( 'Add to Gallery', 'dish-events' ) ); ?>">
							<?php esc_html_e( 'Add Images', 'dish-events' ); ?>
						</button>
						<button type="button" class="button" id="dish-chef-gallery-clear"
						        <?php echo empty( $gallery ) ? 'style="display:none"' : ''; ?>>
							<?php esc_html_e( 'Clear Gallery', 'dish-events' ); ?>
						</button>
					</td>
				</tr>

			</table>
		</div>

		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	public function save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['dish_chef_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'dish_chef_save_' . $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, 'dish_is_team_member', ! empty( $_POST['dish_is_team_member'] ) ? 1 : 0 );
		update_post_meta( $post_id, 'dish_chef_role',     sanitize_text_field( wp_unslash( $_POST['dish_chef_role']     ?? '' ) ) );
		update_post_meta( $post_id, 'dish_chef_website',   esc_url_raw( wp_unslash( $_POST['dish_chef_website']   ?? '' ) ) );
		update_post_meta( $post_id, 'dish_chef_instagram', esc_url_raw( wp_unslash( $_POST['dish_chef_instagram'] ?? '' ) ) );
		update_post_meta( $post_id, 'dish_chef_linkedin',  esc_url_raw( wp_unslash( $_POST['dish_chef_linkedin']  ?? '' ) ) );
		update_post_meta( $post_id, 'dish_chef_tiktok',    esc_url_raw( wp_unslash( $_POST['dish_chef_tiktok']    ?? '' ) ) );

		$raw_gallery = sanitize_text_field( wp_unslash( $_POST['dish_chef_gallery_ids'] ?? '' ) );
		$gallery     = json_decode( $raw_gallery, true );
		$gallery     = is_array( $gallery ) ? array_values( array_map( 'absint', $gallery ) ) : [];
		update_post_meta( $post_id, 'dish_chef_gallery_ids', wp_json_encode( $gallery ) );
	}
}
