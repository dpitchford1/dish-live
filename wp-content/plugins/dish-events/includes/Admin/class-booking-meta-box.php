<?php
/**
 * Booking record meta boxes.
 *
 * Three meta boxes are registered on dish_booking edit screens:
 *
 *  1. "Booking Details"  — read-only sections: General, Tickets, Attendees,
 *                          Checkout Fields, Transaction Log.
 *  2. "Booking Actions"  — status-change buttons (Complete / Cancel / Refund).
 *  3. "Internal Notes"   — textarea to log an admin note; all notes are stored
 *                          as a JSON array in the `dish_booking_notes` meta key.
 *
 * Bookings are created programmatically by BookingManager (Phase 9) and are
 * never created via the WP Add New screen. This class only deals with reading
 * and displaying that stored data, plus the lightweight notes-save path.
 *
 * Meta key convention (all written by BookingManager in Phase 9):
 *   dish_class_id           int     — linked dish_class post ID
 *   dish_customer_name      string  — full customer name
 *   dish_customer_email     string  — customer e-mail address
 *   dish_customer_phone     string  — customer phone number
 *   dish_customer_user_id   int     — WP user ID, 0 if guest checkout
 *   dish_ticket_type_id     int     — dish_format (ticket type) post ID
 *   dish_ticket_qty         int     — number of tickets purchased
 *   dish_ticket_total_cents int     — total charged in cents
 *   dish_attendees          json    — array of {name,email,…} objects
 *   dish_checkout_fields    json    — array of {label,value} pairs
 *   dish_transaction_id     string  — payment-gateway transaction reference
 *   dish_gateway            string  — gateway slug e.g. "stripe"
 *   dish_booking_notes      json    — array of {note,author,date} admin notes
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use WP_Post;
use Dish\Events\Helpers\MoneyHelper;

/**
 * Class BookingMetaBox
 */
final class BookingMetaBox {

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Register all three meta boxes on the dish_booking edit screen.
	 * Hooked to 'add_meta_boxes'.
	 */
	public function register(): void {
		add_meta_box(
			'dish_booking_details',
			__( 'Booking Details', 'dish-events' ),
			[ $this, 'render_details' ],
			'dish_booking',
			'normal',
			'high'
		);

		add_meta_box(
			'dish_booking_actions',
			__( 'Booking Actions', 'dish-events' ),
			[ $this, 'render_actions' ],
			'dish_booking',
			'side',
			'high'
		);

		add_meta_box(
			'dish_booking_notes',
			__( 'Internal Notes', 'dish-events' ),
			[ $this, 'render_notes' ],
			'dish_booking',
			'normal',
			'default'
		);
	}

	// -------------------------------------------------------------------------
	// Render: Booking Details
	// -------------------------------------------------------------------------

	public function render_details( WP_Post $post ): void {
		$class_id    = (int)    get_post_meta( $post->ID, 'dish_class_id',           true );
		$cust_name   = (string) get_post_meta( $post->ID, 'dish_customer_name',      true );
		$cust_email  = (string) get_post_meta( $post->ID, 'dish_customer_email',     true );
		$cust_phone  = (string) get_post_meta( $post->ID, 'dish_customer_phone',     true );
		$user_id     = (int)    get_post_meta( $post->ID, 'dish_customer_user_id',   true );
		$ticket_tid  = (int)    get_post_meta( $post->ID, 'dish_ticket_type_id',     true );
		$ticket_qty  = (int)    get_post_meta( $post->ID, 'dish_ticket_qty',         true );
		$total_cents = (int)    get_post_meta( $post->ID, 'dish_ticket_total_cents', true );
		$txn_id      = (string) get_post_meta( $post->ID, 'dish_transaction_id',     true );
		$gateway     = (string) get_post_meta( $post->ID, 'dish_gateway',            true );

		$raw_attendees = get_post_meta( $post->ID, 'dish_attendees',      true ) ?: '[]';
		$attendees     = (array) json_decode( (string) $raw_attendees, true );

		$raw_checkout = get_post_meta( $post->ID, 'dish_checkout_fields', true ) ?: '[]';
		$checkout     = (array) json_decode( (string) $raw_checkout, true );

		// Format date.
		$tz           = new \DateTimeZone( wp_timezone_string() );
		$booked_dt    = ( new \DateTimeImmutable( $post->post_date ) )->setTimezone( $tz );
		$booked_label = $booked_dt->format( 'D, M j Y, g:i a' );

		// Linked class title.
		$class_title  = $class_id ? get_the_title( $class_id ) : '—';
		$class_link   = $class_id ? get_edit_post_link( $class_id ) : '';

		// Ticket type name — dish_ticket_types are stored in a custom DB table, not as posts.
		$ticket_row   = $ticket_tid ? \Dish\Events\Data\TicketTypeRepository::get( $ticket_tid ) : null;
		$ticket_name  = $ticket_row ? $ticket_row->name : '—';

		// Customer name/email.
		$cust_display = $cust_name ?: '—';
		if ( $user_id ) {
			$user_obj = get_userdata( $user_id );
			if ( $user_obj ) {
				$cust_display .= ' (<a href="' . esc_url( get_edit_user_link( $user_id ) ) . '">'
					. esc_html( $user_obj->user_login ) . '</a>)';
			}
		}

		// Total formatted.
		$total_fmt = $total_cents ? MoneyHelper::cents_to_display( $total_cents ) : '—';
		?>
		<div class="dish-booking-details">

			<!-- Section: General -->
			<div class="dish-booking-section">
				<h3 class="dish-booking-section__title"><?php esc_html_e( 'General', 'dish-events' ); ?></h3>
				<table class="dish-detail-table">
					<tr>
						<th><?php esc_html_e( 'Booking ID', 'dish-events' ); ?></th>
						<td><?php echo esc_html( (string) $post->ID ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Class', 'dish-events' ); ?></th>
						<td>
							<?php if ( $class_link ) : ?>
								<a href="<?php echo esc_url( $class_link ); ?>"><?php echo esc_html( $class_title ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $class_title ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Customer', 'dish-events' ); ?></th>
						<td>
							<?php
							// $cust_display contains HTML (linked user login), safe to output.
							echo wp_kses(
								$cust_display,
								[ 'a' => [ 'href' => [] ] ]
							);
							?>
						</td>
					</tr>
					<?php if ( $cust_email ) : ?>
					<tr>
						<th><?php esc_html_e( 'Email', 'dish-events' ); ?></th>
						<td><a href="mailto:<?php echo esc_attr( $cust_email ); ?>"><?php echo esc_html( $cust_email ); ?></a></td>
					</tr>
					<?php endif; ?>
					<?php if ( $cust_phone ) : ?>
					<tr>
						<th><?php esc_html_e( 'Phone', 'dish-events' ); ?></th>
						<td><a href="tel:<?php echo esc_attr( $cust_phone ); ?>"><?php echo esc_html( $cust_phone ); ?></a></td>
					</tr>
					<?php endif; ?>
					<tr>
						<th><?php esc_html_e( 'Status', 'dish-events' ); ?></th>
						<td><?php echo esc_html( $this->status_label( $post->post_status ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Date Booked', 'dish-events' ); ?></th>
						<td><?php echo esc_html( $booked_label ); ?></td>
					</tr>
				</table>
			</div>

			<!-- Section: Tickets -->
			<div class="dish-booking-section">
				<h3 class="dish-booking-section__title"><?php esc_html_e( 'Ticket', 'dish-events' ); ?></h3>
				<table class="dish-detail-table">
					<tr>
						<th><?php esc_html_e( 'Ticket Type', 'dish-events' ); ?></th>
						<td><?php echo esc_html( $ticket_name ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Quantity', 'dish-events' ); ?></th>
						<td><?php echo esc_html( (string) ( $ticket_qty ?: '—' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Total Charged', 'dish-events' ); ?></th>
						<td><strong><?php echo esc_html( $total_fmt ); ?></strong></td>
					</tr>
				</table>
			</div>

			<!-- Section: Attendees -->
			<?php if ( ! empty( $attendees ) ) : ?>
			<div class="dish-booking-section">
				<h3 class="dish-booking-section__title">
					<?php
					printf(
						/* translators: %d: attendee count */
						esc_html( _n( 'Attendee (%d)', 'Attendees (%d)', count( $attendees ), 'dish-events' ) ),
						count( $attendees )
					);
					?>
				</h3>
				<table class="dish-detail-table">
					<thead>
						<tr>
							<th>#</th>
							<th><?php esc_html_e( 'Name', 'dish-events' ); ?></th>
							<th><?php esc_html_e( 'Email', 'dish-events' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $attendees as $i => $a ) :
						$a = is_array( $a ) ? $a : [];
						?>
						<tr>
							<td><?php echo esc_html( (string) ( $i + 1 ) ); ?></td>
							<td><?php echo esc_html( (string) ( $a['name'] ?? '—' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $a['email'] ?? '—' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<!-- Section: Checkout Fields -->
			<?php if ( ! empty( $checkout ) ) : ?>
			<div class="dish-booking-section">
				<h3 class="dish-booking-section__title"><?php esc_html_e( 'Checkout Fields', 'dish-events' ); ?></h3>
				<table class="dish-detail-table">
					<?php foreach ( $checkout as $field ) :
						$field = is_array( $field ) ? $field : [];
						?>
						<tr>
							<th><?php echo esc_html( (string) ( $field['label'] ?? '' ) ); ?></th>
							<td><?php echo esc_html( (string) ( $field['value'] ?? '—' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
			<?php endif; ?>

			<!-- Section: Transaction Log -->
			<?php if ( $txn_id || $gateway ) : ?>
			<div class="dish-booking-section">
				<h3 class="dish-booking-section__title"><?php esc_html_e( 'Transaction Log', 'dish-events' ); ?></h3>
				<table class="dish-detail-table">
					<?php if ( $gateway ) : ?>
					<tr>
						<th><?php esc_html_e( 'Gateway', 'dish-events' ); ?></th>
						<td><?php echo esc_html( ucfirst( $gateway ) ); ?></td>
					</tr>
					<?php endif; ?>
					<?php if ( $txn_id ) : ?>
					<tr>
						<th><?php esc_html_e( 'Transaction ID', 'dish-events' ); ?></th>
						<td><code><?php echo esc_html( $txn_id ); ?></code></td>
					</tr>
					<?php endif; ?>
				</table>
			</div>
			<?php endif; ?>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Render: Booking Actions
	// -------------------------------------------------------------------------

	public function render_actions( WP_Post $post ): void {
		$current = $post->post_status;
		$transitions = $this->allowed_transitions( $current );
		wp_nonce_field( 'dish_booking_action_' . $post->ID, 'dish_booking_action_nonce' );
		?>
		<div class="dish-booking-actions">
			<p class="dish-action-label">
				<strong><?php esc_html_e( 'Current Status:', 'dish-events' ); ?></strong><br>
				<span class="dish-status-badge dish-status-<?php echo esc_attr( $current ); ?>">
					<?php echo esc_html( $this->status_label( $current ) ); ?>
				</span>
			</p>

			<?php if ( ! empty( $transitions ) ) : ?>
				<p class="dish-action-label"><?php esc_html_e( 'Change status to:', 'dish-events' ); ?></p>
				<?php foreach ( $transitions as $new_status => $btn_label ) : ?>

					<button type="submit"
					        name="dish_booking_new_status"
					        value="<?php echo esc_attr( $new_status ); ?>"
					class="button button-secondary dish-action-btn dish-action-btn--<?php echo esc_attr( str_replace( 'dish_', '', $new_status ) ); ?>">
						<?php echo esc_html( $btn_label ); ?>
					</button>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="dish-empty-state"><?php esc_html_e( 'No status transitions available.', 'dish-events' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Render: Internal Notes
	// -------------------------------------------------------------------------

	public function render_notes( WP_Post $post ): void {
		$raw   = get_post_meta( $post->ID, 'dish_booking_notes', true ) ?: '[]';
		$notes = (array) json_decode( (string) $raw, true );
		wp_nonce_field( 'dish_booking_note_' . $post->ID, 'dish_booking_note_nonce' );
		?>
		<div class="dish-booking-notes">

			<?php if ( ! empty( $notes ) ) : ?>
			<ul class="dish-notes-list">
				<?php foreach ( array_reverse( $notes ) as $n ) :
					$n = is_array( $n ) ? $n : [];
					?>
				<li class="dish-note-item">
					<div class="dish-note-meta">
						<strong><?php echo esc_html( (string) ( $n['author'] ?? '—' ) ); ?></strong>
						<span class="dish-note-date"><?php echo esc_html( (string) ( $n['date'] ?? '' ) ); ?></span>
					</div>
					<div class="dish-note-body"><?php echo esc_html( (string) ( $n['note'] ?? '' ) ); ?></div>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php else : ?>
				<p class="dish-empty-state"><?php esc_html_e( 'No notes yet.', 'dish-events' ); ?></p>
			<?php endif; ?>

			<hr>
			<label for="dish_booking_new_note" class="dish-add-note-label">
				<?php esc_html_e( 'Add Note', 'dish-events' ); ?>
			</label>
			<textarea id="dish_booking_new_note" name="dish_booking_new_note"
			          rows="4"></textarea>
			<p class="dish-note-submit">
				<button type="submit" name="dish_booking_save_note" value="1"
				        class="button button-primary">
					<?php esc_html_e( 'Save Note', 'dish-events' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save: status action + note
	// -------------------------------------------------------------------------

	/**
	 * Handle status transitions and note saves.
	 * Hooked to 'save_post_dish_booking'.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// --- Note save ---
		$note_nonce = sanitize_text_field( wp_unslash( $_POST['dish_booking_note_nonce'] ?? '' ) );
		if ( wp_verify_nonce( $note_nonce, 'dish_booking_note_' . $post_id ) ) {
			$new_note_text = sanitize_textarea_field( wp_unslash( $_POST['dish_booking_new_note'] ?? '' ) );
			if ( $new_note_text !== '' && ! empty( $_POST['dish_booking_save_note'] ) ) {
				$raw   = get_post_meta( $post_id, 'dish_booking_notes', true ) ?: '[]';
				$notes = (array) json_decode( (string) $raw, true );
				$tz    = new \DateTimeZone( wp_timezone_string() );
				$notes[] = [
					'note'   => $new_note_text,
					'author' => wp_get_current_user()->display_name ?: 'Admin',
					'date'   => ( new \DateTimeImmutable( 'now', $tz ) )->format( 'M j, Y g:i a' ),
				];
				update_post_meta( $post_id, 'dish_booking_notes', wp_json_encode( $notes ) );
			}
		}

		// --- Status transition ---
		$action_nonce = sanitize_text_field( wp_unslash( $_POST['dish_booking_action_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $action_nonce, 'dish_booking_action_' . $post_id ) ) {
			return;
		}
		$new_status = sanitize_key( wp_unslash( $_POST['dish_booking_new_status'] ?? '' ) );
		if ( $new_status === '' ) {
			return;
		}

		$allowed = array_keys( $this->allowed_transitions( $post->post_status ) );
		if ( ! in_array( $new_status, $allowed, true ) ) {
			return;
		}

		// Unhook to avoid recursion, update status, re-hook.
		remove_action( 'save_post_dish_booking', [ $this, 'save' ], 10 );
		wp_update_post( [ 'ID' => $post_id, 'post_status' => $new_status ] );
		add_action( 'save_post_dish_booking', [ $this, 'save' ], 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Human-readable label for a booking post status.
	 *
	 * @param  string $status  Post status slug.
	 * @return string          Translated label.
	 */
	private function status_label( string $status ): string {
		$map = [
			'dish_pending'   => __( 'Pending',   'dish-events' ),
			'dish_completed' => __( 'Completed', 'dish-events' ),
			'dish_failed'    => __( 'Failed',    'dish-events' ),
			'dish_refunded'  => __( 'Refunded',  'dish-events' ),
			'dish_cancelled' => __( 'Cancelled', 'dish-events' ),
		];
		return $map[ $status ] ?? ucfirst( str_replace( [ 'dish_', '_' ], [ '', ' ' ], $status ) );
	}

	/**
	 * Return the allowed next statuses and their button labels for a given
	 * current status.
	 *
	 * @param  string                $current  Current post status slug.
	 * @return array<string,string>            next_status => button label
	 */
	private function allowed_transitions( string $current ): array {
		$all = [
			'dish_pending' => [
				'dish_completed' => __( '✓ Mark Completed',   'dish-events' ),
				'dish_cancelled' => __( '✕ Cancel Booking',   'dish-events' ),
			],
			'dish_failed' => [
				'dish_completed' => __( '✓ Mark Completed',   'dish-events' ),
				'dish_pending'   => __( '↺ Reset to Pending', 'dish-events' ),
				'dish_cancelled' => __( '✕ Cancel Booking',   'dish-events' ),
			],
			'dish_completed' => [
				'dish_pending'   => __( '↺ Reset to Pending', 'dish-events' ),
				'dish_refunded'  => __( '↩ Mark Refunded',   'dish-events' ),
				'dish_cancelled' => __( '✕ Cancel Booking',  'dish-events' ),
			],
			'dish_cancelled' => [
				'dish_pending'   => __( '↺ Reset to Pending', 'dish-events' ),
			],
			'dish_refunded' => [
				'dish_pending'   => __( '↺ Reset to Pending', 'dish-events' ),
			],
			// Recovery transitions for posts that ended up on a native WP status.
			'publish' => [
				'dish_pending'   => __( '↺ Set to Pending',   'dish-events' ),
				'dish_completed' => __( '✓ Mark Completed',   'dish-events' ),
				'dish_cancelled' => __( '✕ Cancel Booking',   'dish-events' ),
			],
			'pending' => [
				'dish_pending'   => __( '↺ Set to Pending',   'dish-events' ),
				'dish_completed' => __( '✓ Mark Completed',   'dish-events' ),
				'dish_cancelled' => __( '✕ Cancel Booking',   'dish-events' ),
			],
		];
		return $all[ $current ] ?? [];
	}
}
