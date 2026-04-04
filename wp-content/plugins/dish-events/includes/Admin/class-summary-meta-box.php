<?php
/**
 * Summary sidebar meta box for the dish_class post type.
 *
 * Displays Total Bookings and Total Attendees at a glance.
 * Shown at the top of the sidebar on the class edit screen.
 *
 * Phase 4: uses WP_Query directly. Phase 6 will replace with BookingRepository.
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

/**
 * Class SummaryMetaBox
 */
final class SummaryMetaBox {

	/**
	 * Register the meta box.
	 * Hooked to 'add_meta_boxes'.
	 */
	public function register(): void {
		add_meta_box(
			'dish_class_summary',
			__( 'Summary', 'dish-events' ),
			[ $this, 'render' ],
			'dish_class',
			'side',
			'high'
		);
	}

	/**
	 * Render the summary meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		// New post — nothing to summarise yet.
		if ( ! $post->ID || $post->post_status === 'auto-draft' ) {
			?>
			<p class="description"><?php esc_html_e( 'Save the class to see booking totals.', 'dish-events' ); ?></p>
			<?php
			return;
		}

		// Phase 4: direct query. Phase 6 replaces with BookingRepository::get_for_class().
		$booking_ids = get_posts( [
			'post_type'      => 'dish_booking',
			'post_status'    => [ 'dish_pending', 'dish_completed' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ [
				'key'   => 'dish_class_id',
				'value' => $post->ID,
				'type'  => 'NUMERIC',
			] ],
		] );

		$total_bookings  = count( $booking_ids );
		$total_attendees = 0;
		foreach ( $booking_ids as $booking_id ) {
			$total_attendees += (int) get_post_meta( $booking_id, 'dish_ticket_qty', true );
		}
		?>
		<dl class="dish-summary-list">
			<div class="dish-summary-row">
				<dt><?php esc_html_e( 'Total Bookings', 'dish-events' ); ?></dt>
				<dd><?php echo esc_html( (string) $total_bookings ); ?></dd>
			</div>
			<div class="dish-summary-row">
				<dt><?php esc_html_e( 'Total Attendees', 'dish-events' ); ?></dt>
				<dd><?php echo esc_html( (string) $total_attendees ); ?></dd>
			</div>
		</dl>
		<?php
	}
}
