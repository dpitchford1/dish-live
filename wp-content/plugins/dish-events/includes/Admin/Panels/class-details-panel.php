<?php
/**
 * Details panel for the Class Settings meta box.
 *
 * Handles: class type (public/corporate), min/max attendees, admin notes.
 *
 * @package Dish\Events\Admin\Panels
 */

declare( strict_types=1 );

namespace Dish\Events\Admin\Panels;

/**
 * Class DetailsPanel
 */
final class DetailsPanel {

	/**
	 * Render the Details panel.
	 *
	 * @param array<string,mixed> $meta
	 */
	public function render( array $meta ): void {
		$class_type    = (string) ( $meta['dish_class_type'] ?? 'public' );
		$notes         = (string) ( $meta['dish_admin_notes'] ?? '' );

		// Check if the linked template is a guest chef class.
		$template_id   = (int) ( $meta['dish_template_id'] ?? 0 );
		$is_guest_chef = $template_id ? (bool) get_post_meta( $template_id, 'dish_is_guest_chef', true ) : false;
		$chef_label    = $is_guest_chef ? __( 'Guest Chef(s)', 'dish-events' ) : __( 'Chef(s)', 'dish-events' );

		$raw_ids   = $meta['dish_chef_ids'] ?? [];
		$saved_ids = is_array( $raw_ids )
			? array_map( 'intval', $raw_ids )
			: array_map( 'intval', (array) json_decode( (string) $raw_ids, true ) );

		$chefs = get_posts( [
			'post_type'      => 'dish_chef',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [
				'relation' => 'OR',
				[ 'key' => 'dish_is_team_member', 'compare' => 'NOT EXISTS' ],
				[ 'key' => 'dish_is_team_member', 'value' => '1', 'compare' => '!=' ],
			],
		] );
		?>
		<div class="dish-metabox__panel" data-panel="details" hidden>
			<table class="form-table dish-form-table">
				<tr>
					<th><?php echo esc_html( $chef_label ); ?></th>
					<td>
						<?php if ( empty( $chefs ) ) : ?>
							<p class="description"><?php esc_html_e( 'No chefs found. Add a chef first.', 'dish-events' ); ?></p>
						<?php else : ?>
							<ul class="dish-chef-list">
								<?php foreach ( $chefs as $chef ) : ?>
								<li>
									<label>
										<input type="checkbox" name="dish_chef_ids[]"
											value="<?php echo esc_attr( (string) $chef->ID ); ?>"
											<?php checked( in_array( $chef->ID, $saved_ids, true ) ); ?>>
										<?php echo esc_html( $chef->post_title ); ?>
									</label>
								</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="dish_class_type"><?php esc_html_e( 'Class type', 'dish-events' ); ?></label></th>
					<td>
						<select id="dish_class_type" name="dish_class_type">
							<option value="public"    <?php selected( $class_type, 'public' ); ?>><?php esc_html_e( 'Public', 'dish-events' ); ?></option>
							<option value="corporate" <?php selected( $class_type, 'corporate' ); ?>><?php esc_html_e( 'Corporate / Private', 'dish-events' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="dish_admin_notes"><?php esc_html_e( 'Admin notes', 'dish-events' ); ?></label></th>
					<td>
						<textarea id="dish_admin_notes" name="dish_admin_notes"
							rows="4" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Internal only — not shown to attendees.', 'dish-events' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Save Details panel fields.
	 *
	 * @param int $post_id
	 */
	public function save( int $post_id ): void {
		// Chef IDs.
		$chef_ids = [];
		if ( ! empty( $_POST['dish_chef_ids'] ) && is_array( $_POST['dish_chef_ids'] ) ) {
			$chef_ids = array_values( array_filter( array_map( 'absint', $_POST['dish_chef_ids'] ) ) );
		}
		update_post_meta( $post_id, 'dish_chef_ids', wp_json_encode( $chef_ids ) );

		$allowed_types = [ 'public', 'corporate' ];
		$class_type    = sanitize_key( wp_unslash( $_POST['dish_class_type'] ?? 'public' ) );

		update_post_meta(
			$post_id,
			'dish_class_type',
			in_array( $class_type, $allowed_types, true ) ? $class_type : 'public'
		);
		update_post_meta( $post_id, 'dish_admin_notes', sanitize_textarea_field( wp_unslash( $_POST['dish_admin_notes'] ?? '' ) ) );
	}
}
