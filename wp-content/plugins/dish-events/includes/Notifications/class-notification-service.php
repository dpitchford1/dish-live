<?php
/**
 * Notification dispatcher for dish-events.
 *
 * Listens on dish_booking_created and transition_post_status to send
 * the correct email for each booking lifecycle event.
 *
 * Dispatch points
 * ---------------
 *  dish_booking_created              → email_admin_new_booking   (studio copy, fires immediately)
 *  dish_booking: * → dish_completed  → email_booking_confirmation (customer)
 *  dish_booking: * → dish_cancelled  → email_booking_cancelled   (customer)
 *                                      email_admin_cancellation  (studio copy)
 *
 * Deferred dispatch points (not wired here)
 * -----------------------------------------
 *  email_booking_reminder    → cron job (Phase 13)
 *  email_payment_receipt     → PayPal confirm callback (Phase 10)
 *  email_waitlist_available  → WaitlistManager (Phase 9.6)
 *
 * @package Dish\Events\Notifications
 */

declare( strict_types=1 );

namespace Dish\Events\Notifications;

use Dish\Events\Admin\Settings;
use Dish\Events\Core\Loader;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\TicketTypeRepository;
use Dish\Events\Helpers\MoneyHelper;

/**
 * Class NotificationService
 */
final class NotificationService {

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	/**
	 * Register all notification hooks via the Loader.
	 *
	 * @param Loader $loader
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'dish_booking_created',    $this, 'on_booking_created' );
		$loader->add_action( 'transition_post_status',  $this, 'on_status_transition', 10, 3 );
	}

	// -------------------------------------------------------------------------
	// Hook callbacks
	// -------------------------------------------------------------------------

	/**
	 * Fires when a new booking record is created (status: dish_pending, pre-payment).
	 * Sends the studio notification immediately so they know a checkout started.
	 *
	 * @param int $booking_id
	 */
	public function on_booking_created( int $booking_id ): void {
		$this->dispatch( 'email_admin_new_booking', $booking_id );
	}

	/**
	 * Fires on every dish_booking post-status change.
	 * Routes to the correct email template(s) based on the new status.
	 *
	 * @param string   $new  New post status.
	 * @param string   $old  Previous post status.
	 * @param \WP_Post $post Post object.
	 */
	public function on_status_transition( string $new, string $old, \WP_Post $post ): void {
		if ( $post->post_type !== 'dish_booking' || $new === $old ) {
			return;
		}

		if ( $new === 'dish_completed' ) {
			// Customer confirmation — fires when admin manually marks completed,
			// or will fire via PayPal confirm callback in Phase 10.
			$this->dispatch( 'email_booking_confirmation', $post->ID );
		}

		if ( $new === 'dish_cancelled' ) {
			$this->dispatch( 'email_booking_cancelled', $post->ID );
			$this->dispatch( 'email_admin_cancellation', $post->ID );
		}
	}

	// -------------------------------------------------------------------------
	// Dispatch
	// -------------------------------------------------------------------------

	/**
	 * Build, personalise, and send a single notification email.
	 *
	 * For admin-addressed emails (email_admin_*) the recipient is overridden
	 * to the admin-notify address from Settings; customer emails go to the
	 * booking's dish_customer_email meta value.
	 *
	 * @param string $key        Settings prefix, e.g. 'email_booking_confirmation'.
	 * @param int    $booking_id dish_booking post ID.
	 */
	public function dispatch( string $key, int $booking_id ): void {
		// Respect the per-email kill switch.
		if ( ! Settings::get( $key . '_enabled', true ) ) {
			return;
		}

		$tokens = $this->build_tokens( $booking_id );

		// Resolve recipient.
		$is_admin_email = str_starts_with( $key, 'email_admin_' );
		$to = $is_admin_email
			? (string) Settings::get( 'email_admin_to', get_option( 'admin_email' ) )
			: ( $tokens['{{customer_email}}'] ?? '' );

		if ( empty( trim( $to ) ) ) {
			return;
		}

		// Subject — use settings value (has defaults); fall through to blank.
		$subject_raw = (string) Settings::get( $key . '_subject', '' );

		// Body — use settings override when set, else fall back to built-in template.
		$body_raw = (string) Settings::get( $key . '_body', '' );
		if ( $body_raw === '' ) {
			$body_raw = EmailTemplate::get_default_body( $key );
		}

		// Replace tokens in subject + body.
		$subject = EmailTemplate::replace_tokens( $subject_raw, $tokens );
		$body    = EmailTemplate::replace_tokens( $body_raw, $tokens );

		// Wrap in HTML email layout.
		$html = EmailTemplate::wrap(
			$body,
			$tokens['{{studio_name}}'] ?? '',
			$tokens['{{studio_email}}'] ?? ''
		);

		// Build wp_mail headers.
		$from_name    = (string) Settings::get( 'email_from_name',    Settings::get( 'studio_name', get_bloginfo( 'name' ) ) );
		$from_address = (string) Settings::get( 'email_from_address', get_option( 'admin_email' ) );
		$cc_raw       = (string) Settings::get( $key . '_cc', '' );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_address ),
		];

		if ( $cc_raw !== '' ) {
			foreach ( array_map( 'trim', explode( ',', $cc_raw ) ) as $cc_addr ) {
				if ( $cc_addr !== '' ) {
					$headers[] = 'Cc: ' . $cc_addr;
				}
			}
		}

		$this->send( $to, $subject, $html, $headers );
	}

	// -------------------------------------------------------------------------
	// Token builder
	// -------------------------------------------------------------------------

	/**
	 * Build the full {{token}} → value map for a booking.
	 *
	 * @param  int $booking_id dish_booking post ID.
	 * @return array<string,string>
	 */
	public function build_tokens( int $booking_id ): array {
		$booking = get_post( $booking_id );
		if ( ! $booking instanceof \WP_Post ) {
			return [];
		}

		// --- Booking meta ---
		$class_id        = (int)    get_post_meta( $booking_id, 'dish_class_id',           true );
		$ticket_type_id  = (int)    get_post_meta( $booking_id, 'dish_ticket_type_id',      true );
		$qty             = (int)    get_post_meta( $booking_id, 'dish_ticket_qty',           true );
		$total_cents     = (int)    get_post_meta( $booking_id, 'dish_ticket_total_cents',   true );
		$customer_name   = (string) get_post_meta( $booking_id, 'dish_customer_name',        true );
		$customer_email  = (string) get_post_meta( $booking_id, 'dish_customer_email',       true );
		$customer_phone  = (string) get_post_meta( $booking_id, 'dish_customer_phone',       true );

		// --- Class data ---
		$class       = ClassRepository::get( $class_id );
		$class_title = $class ? $class->post_title : '';
		$class_date  = '';
		$class_time  = '';

		if ( $class ) {
			$start_epoch = (int) get_post_meta( $class->ID, 'dish_start_datetime', true );
			if ( $start_epoch > 0 ) {
				$tz         = new \DateTimeZone( wp_timezone_string() );
				$dt         = ( new \DateTimeImmutable( '@' . $start_epoch ) )->setTimezone( $tz );
				$class_date = $dt->format( 'l, F j, Y' );
				$class_time = $dt->format( 'g:i a' );
			}
		}

		// --- Ticket type ---
		$ticket_type      = TicketTypeRepository::get( $ticket_type_id );
		$ticket_type_name = $ticket_type ? $ticket_type->name : '';

		// --- Settings ---
		$location    = (string) Settings::get( 'venue_name',   '' );
		$studio_name  = (string) Settings::get( 'studio_name',  get_bloginfo( 'name' ) );
		$studio_email = (string) Settings::get( 'studio_email', get_option( 'admin_email' ) );
		$studio_phone = (string) Settings::get( 'studio_phone', '' );

		// --- Booking details URL ---
		$details_page_id = (int) Settings::get( 'booking_details_page', 0 );
		$details_url     = $details_page_id
			? (string) add_query_arg( 'booking_id', $booking_id, get_permalink( $details_page_id ) )
			: '';

		return [
			'{{booking_id}}'          => (string) $booking_id,
			'{{customer_name}}'       => $customer_name,
			'{{customer_email}}'      => $customer_email,
			'{{customer_phone}}'      => $customer_phone,
			'{{class_title}}'         => $class_title,
			'{{class_date}}'          => $class_date,
			'{{class_time}}'          => $class_time,
			'{{class_location}}'      => $location,
			'{{ticket_type}}'         => $ticket_type_name,
			'{{quantity}}'            => (string) $qty,
			'{{amount}}'              => MoneyHelper::cents_to_display( $total_cents ),
			'{{booking_details_url}}' => esc_url( $details_url ),
			'{{studio_name}}'         => $studio_name,
			'{{studio_email}}'        => $studio_email,
			'{{studio_phone}}'        => $studio_phone,
		];
	}

	// -------------------------------------------------------------------------
	// Send
	// -------------------------------------------------------------------------

	/**
	 * Send an email via wp_mail().
	 *
	 * The dish_notification_should_send filter lets tests and staging
	 * environments intercept or suppress delivery.
	 *
	 * @param string        $to
	 * @param string        $subject
	 * @param string        $body     HTML body (already wrapped).
	 * @param array<string> $headers
	 */
	private function send( string $to, string $subject, string $body, array $headers ): void {
		/**
		 * Filter: return false to prevent this notification from sending.
		 *
		 * @param bool   $should_send
		 * @param string $to
		 * @param string $subject
		 */
		if ( false === apply_filters( 'dish_notification_should_send', true, $to, $subject ) ) {
			return;
		}

		wp_mail( $to, $subject, $body, $headers );
	}
}
