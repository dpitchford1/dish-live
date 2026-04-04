<?php
/**
 * Settings panel for the Class Settings meta box.
 *
 * Handles: featured flag, external booking URL, show QR, template name.
 *
 * @package Dish\Events\Admin\Panels
 */

declare( strict_types=1 );

namespace Dish\Events\Admin\Panels;

/**
 * Class SettingsPanel
 */
final class SettingsPanel {

	/**
	 * Render the Settings panel.
	 *
	 * @param array<string,mixed> $meta
	 */
	public function render( array $meta ): void {
		$featured     = (bool) ( $meta['dish_is_featured'] ?? false );
		$template     = (string) ( $meta['dish_event_theme'] ?? '' );
		$is_private   = (bool) ( $meta['dish_is_private'] ?? false );
		?>
		<div class="dish-metabox__panel" data-panel="class-settings" hidden>
			<table class="form-table dish-form-table">
				<tr>
					<th><?php esc_html_e( 'Private event', 'dish-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="dish_is_private" value="1"
								<?php checked( $is_private ); ?>>
							<?php esc_html_e( 'Mark as private / corporate takeover', 'dish-events' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Disables public booking. Calendar label changes to "Private Event".', 'dish-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Featured', 'dish-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="dish_is_featured" value="1"
								<?php checked( $featured ); ?>>
							<?php esc_html_e( 'Mark as featured class', 'dish-events' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="dish_event_theme"><?php esc_html_e( 'Template override', 'dish-events' ); ?></label></th>
					<td>
						<input type="text" id="dish_event_theme" name="dish_event_theme"
							value="<?php echo esc_attr( $template ); ?>" class="regular-text">
						<p class="description">
							<?php esc_html_e( 'Leave blank for the default template.', 'dish-events' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Save Settings panel fields.
	 *
	 * @param int $post_id
	 */
	public function save( int $post_id ): void {
		update_post_meta( $post_id, 'dish_is_private',  ! empty( $_POST['dish_is_private'] ) );
		update_post_meta( $post_id, 'dish_is_featured', ! empty( $_POST['dish_is_featured'] ) );
		update_post_meta( $post_id, 'dish_event_theme', sanitize_text_field( wp_unslash( $_POST['dish_event_theme'] ?? '' ) ) );
	}
}
