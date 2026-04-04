<?php
/**
 * Template panel for the Class meta box.
 *
 * Handles: template dropdown (dish_class_template), read-only summary card,
 * booking opens datetime override.
 *
 * Saves:
 *  - dish_template_id     int  — dish_class_template post ID
 *  - dish_booking_opens   int  — UTC epoch; 0 = use ticket type rule
 *
 * @package Dish\Events\Admin\Panels
 */

declare( strict_types=1 );

namespace Dish\Events\Admin\Panels;

/**
 * Class TemplatePanel
 */
final class TemplatePanel {

	/**
	 * Render the Template panel.
	 *
	 * @param array<string,mixed> $meta
	 */
	public function render( array $meta ): void {
		$template_id   = (int) ( $meta['dish_template_id']   ?? 0 );
		$booking_opens = (int) ( $meta['dish_booking_opens'] ?? 0 );

		// All published class templates, ordered alphabetically.
		$templates = get_posts( [
			'post_type'      => 'dish_class_template',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		// Summary card data for the currently-selected template.
		$summary = $template_id ? $this->get_summary( $template_id ) : null;
		?>
		<div class="dish-metabox__panel" data-panel="template" hidden>
			<table class="form-table dish-form-table">

				<?php /* ---- Template dropdown ------------------------------------ */ ?>
				<tr>
					<th>
						<label for="dish_template_id">
							<?php esc_html_e( 'Class Template', 'dish-events' ); ?>
						</label>
					</th>
					<td>
						<?php if ( empty( $templates ) ) : ?>
							<p class="description">
								<?php esc_html_e( 'No published class templates found. Create one under Dish Events → Class Templates.', 'dish-events' ); ?>
							</p>
						<?php else : ?>
							<select name="dish_template_id" id="dish_template_id"
							        data-dish-template-select>
								<option value=""><?php esc_html_e( '— Select a Template —', 'dish-events' ); ?></option>
								<?php foreach ( $templates as $tmpl ) : ?>
									<option value="<?php echo esc_attr( (string) $tmpl->ID ); ?>"
									        data-summary-url="<?php echo esc_attr( get_edit_post_link( $tmpl->ID ) ); ?>"
									        <?php selected( $template_id, $tmpl->ID ); ?>>
										<?php echo esc_html( $tmpl->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</td>
				</tr>

				<?php /* ---- Summary card ----------------------------------------- */ ?>
				<?php if ( $summary ) : ?>
				<tr id="dish-template-summary-row">
					<th><?php esc_html_e( 'Summary', 'dish-events' ); ?></th>
					<td>
						<div class="dish-template-summary">
							<?php if ( $summary['format'] ) : ?>
								<span class="dish-summary-item">
									<strong><?php esc_html_e( 'Format:', 'dish-events' ); ?></strong>
									<?php echo esc_html( $summary['format'] ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $summary['ticket_type'] ) : ?>
								<span class="dish-summary-item">
									<strong><?php esc_html_e( 'Ticket Type:', 'dish-events' ); ?></strong>
									<?php echo esc_html( $summary['ticket_type'] ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $summary['price'] !== null ) : ?>
								<span class="dish-summary-item">
									<strong><?php esc_html_e( 'Price:', 'dish-events' ); ?></strong>
									<?php echo esc_html( $summary['price'] ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $summary['capacity'] !== null ) : ?>
								<span class="dish-summary-item">
									<strong><?php esc_html_e( 'Capacity:', 'dish-events' ); ?></strong>
									<?php echo esc_html( (string) $summary['capacity'] ); ?>
								</span>
							<?php endif; ?>
							<a href="<?php echo esc_url( get_edit_post_link( $template_id ) ); ?>"
							   class="dish-summary-edit" target="_blank">
								<?php esc_html_e( 'Edit template ↗', 'dish-events' ); ?>
							</a>
						</div>
					</td>
				</tr>
				<?php endif; ?>

				<?php /* ---- Booking opens override -------------------------------- */ ?>
				<tr>
					<th>
						<label for="dish_booking_opens">
							<?php esc_html_e( 'Booking Opens', 'dish-events' ); ?>
						</label>
					</th>
					<td>
						<input type="datetime-local" id="dish_booking_opens" name="dish_booking_opens"
					       value="<?php echo esc_attr( $this->epoch_to_local( $booking_opens ) ); ?>"
						       class="regular-text">
						<p class="description">
							<?php esc_html_e( 'Leave blank to use the ticket type\'s booking window rule.', 'dish-events' ); ?>
						</p>
					</td>
				</tr>

			</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Save Template panel fields.
	 *
	 * @param int $post_id
	 */
	public function save( int $post_id ): void {
		// Template ID.
		$template_id = absint( $_POST['dish_template_id'] ?? 0 );
		update_post_meta( $post_id, 'dish_template_id', $template_id );

		// Booking opens — convert local datetime-local string to UTC epoch.
		$raw_opens = sanitize_text_field( wp_unslash( $_POST['dish_booking_opens'] ?? '' ) );
		if ( $raw_opens ) {
			try {
				$tz    = new \DateTimeZone( wp_timezone_string() ?: 'UTC' );
				$dt    = new \DateTimeImmutable( $raw_opens, $tz );
				$epoch = $dt->getTimestamp();
			} catch ( \Exception $e ) {
				$epoch = 0;
			}
			update_post_meta( $post_id, 'dish_booking_opens', $epoch );
		} else {
			delete_post_meta( $post_id, 'dish_booking_opens' );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the summary card data for a given dish_class_template post.
	 *
	 * @param  int $template_id
	 * @return array{format:string|null, ticket_type:string|null, price:string|null, capacity:int|null}|null
	 */
	private function get_summary( int $template_id ): ?array {
		$post = get_post( $template_id );
		if ( ! $post || 'dish_class_template' !== $post->post_type ) {
			return null;
		}

		$format_id      = (int) get_post_meta( $template_id, 'dish_format_id',      true );
		$ticket_type_id = (int) get_post_meta( $template_id, 'dish_ticket_type_id', true );

		$format_name = null;
		if ( $format_id ) {
			$fmt         = get_post( $format_id );
			$format_name = $fmt ? $fmt->post_title : null;
		}

		$ticket_name = null;
		$price       = null;
		$capacity    = null;

		if ( $ticket_type_id ) {
			global $wpdb;
			$type = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT name, price_cents, capacity FROM {$wpdb->prefix}dish_ticket_types WHERE id = %d LIMIT 1",
					$ticket_type_id
				)
			);
			if ( $type ) {
				$ticket_name = $type->name;
				$capacity    = (int) $type->capacity;

				// Format price from cents.
				$settings = (array) get_option( 'dish_settings', [] );
				$symbol   = $settings['currency_symbol'] ?? '$';
				$price    = $symbol . number_format( (int) $type->price_cents / 100, 2 );
			}
		}

		return [
			'format'      => $format_name,
			'ticket_type' => $ticket_name,
			'price'       => $price,
			'capacity'    => $capacity,
		];
	}
}
