<?php
/**
 * Booking manager.
 *
 * High-level orchestrator for the guest checkout flow.
 *
 *   1. initiate()  — validate availability, start timer, place hold.
 *   2. resume()    — re-attach to an existing live session from a cookie.
 *   3. complete()  — validate session, create dish_booking post, release hold.
 *   4. cancel()    — release hold and delete timer explicitly.
 *
 * All monetary values are in integer cents throughout.
 * Stub payment: bookings are created with status 'dish_pending'.
 * The dish_booking_created action fires after a successful create so that
 * email/notification modules (Phase 10+) can hook in without touching this class.
 *
 * @package Dish\Events\Booking
 */

declare( strict_types=1 );

namespace Dish\Events\Booking;

use Dish\Events\Data\BookingRepository;
use Dish\Events\Data\ClassTemplateRepository;
use Dish\Events\Data\TicketTypeRepository;
use WP_Error;

/**
 * Class BookingManager
 */
final class BookingManager {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Initiate a checkout session for a class instance.
	 *
	 * Validates that the class is published and has available spots, then
	 * starts a countdown timer and places a hold via CapacityManager.
	 *
	 * @param int $class_id dish_class post ID.
	 * @param int $qty      Number of tickets requested. Defaults to 1.
	 * @return array{
	 *   session_key:    string,
	 *   expires_at:     int,
	 *   class_id:       int,
	 *   qty:            int,
	 *   price_cents:    int,
	 *   total_cents:    int,
	 *   ticket_type_id: int,
	 * }|WP_Error
	 */
	public static function initiate( int $class_id, int $qty = 1 ): array|WP_Error {
		// Validate class.
		$class = get_post( $class_id );

		if ( ! $class || 'dish_class' !== $class->post_type || 'publish' !== $class->post_status ) {
			return new WP_Error(
				'invalid_class',
				__( 'This class is not available.', 'dish-events' )
			);
		}

		// Validate availability.
		$available = CapacityManager::get_available( $class_id );
		if ( $available < $qty ) {
			return new WP_Error(
				'no_spots',
				_n(
					'Only 1 spot is available for this class.',
					'Not enough spots are available for this class.',
					$available,
					'dish-events'
				)
			);
		}

		// Start the countdown timer first so we have the session key.
		$timer       = CheckoutTimer::start( $class_id, $qty );
		$session_key = $timer['session_key'];

		// Place hold — release timer and bail if it fails.
		$held = CapacityManager::hold( $class_id, $qty, $session_key );
		if ( ! $held ) {
			CheckoutTimer::cancel( $session_key );
			return new WP_Error(
				'hold_failed',
				__( 'Could not reserve your spot. Please try again.', 'dish-events' )
			);
		}

		return array_merge(
			$timer,
			self::ticket_pricing( $class_id, $qty )
		);
	}

	/**
	 * Resume an existing live checkout session from a stored session key.
	 *
	 * Returns null when the session does not exist, is expired, or belongs to a
	 * different class — the caller should then call initiate().
	 *
	 * @param string $session_key Cookie / POST value.
	 * @param int    $class_id    Expected class ID.
	 * @return array{
	 *   session_key:    string,
	 *   expires_at:     int,
	 *   class_id:       int,
	 *   qty:            int,
	 *   price_cents:    int,
	 *   total_cents:    int,
	 *   ticket_type_id: int,
	 * }|null
	 */
	public static function resume( string $session_key, int $class_id ): ?array {
		if ( '' === $session_key ) {
			return null;
		}

		$timer = CheckoutTimer::get( $session_key );

		if ( ! $timer ) {
			return null;
		}

		// Session expired — clean up and signal caller to start fresh.
		if ( CheckoutTimer::is_expired( $session_key ) ) {
			CheckoutTimer::cancel( $session_key );
			CapacityManager::release( $class_id, $session_key );
			return null;
		}

		// Session belongs to a different class.
		if ( (int) $timer['class_id'] !== $class_id ) {
			return null;
		}

		$qty = (int) $timer['qty'];

		return array_merge(
			[
				'session_key' => $session_key,
				'expires_at'  => (int) $timer['expires_at'],
				'class_id'    => $class_id,
				'qty'         => $qty,
			],
			self::ticket_pricing( $class_id, $qty )
		);
	}

	/**
	 * Complete a booking: validate session, create the booking post, and
	 * release the hold.
	 *
	 * Payment is stubbed for Phase 9 — bookings are saved as 'dish_pending'.
	 *
	 * @param string $session_key    Active checkout session UUID.
	 * @param array{
	 *   name:   string,
	 *   email:  string,
	 *   phone?: string,
	 * }            $customer       Customer contact details.
	 * @param array  $attendees     Per-ticket attendee data (may be empty).
	 * @param int    $ticket_type_id dish_ticket_types.id
	 * @return int|WP_Error  New booking post ID, or error.
	 */
	public static function complete(
		string $session_key,
		array  $customer,
		array  $attendees,
		int    $ticket_type_id
	): int|WP_Error {
		$timer = CheckoutTimer::get( $session_key );

		if ( ! $timer || CheckoutTimer::is_expired( $session_key ) ) {
			return new WP_Error(
				'session_expired',
				__( 'Your reservation has expired. Please start again.', 'dish-events' )
			);
		}

		$class_id = (int) $timer['class_id'];
		$qty      = (int) $timer['qty'];

		// Calculate total from live ticket-type price, including any configured fees.
		$ticket_type = TicketTypeRepository::get( $ticket_type_id );
		$price_cents = $ticket_type ? (int) $ticket_type->price_cents : 0;
		[ 'fees_cents' => $fees_cents ] = self::calculate_fees( $ticket_type, $qty );
		$total_cents = $price_cents * $qty + $fees_cents;

		// Create the booking post (status: pending — payment stub).
		$booking_id = BookingRepository::create( [
			'class_id'         => $class_id,
			'customer_name'    => $customer['name']  ?? '',
			'customer_email'   => $customer['email'] ?? '',
			'customer_phone'   => $customer['phone'] ?? '',
			'customer_user_id' => get_current_user_id(),
			'ticket_type_id'   => $ticket_type_id,
			'ticket_qty'       => $qty,
			'total_cents'      => $total_cents,
			'transaction_id'   => '',
			'gateway'          => 'manual',
			'attendees'        => $attendees,
			'status'           => 'dish_pending',
		] );

		if ( ! $booking_id ) {
			return new WP_Error(
				'booking_failed',
				__( 'Could not save your booking. Please try again.', 'dish-events' )
			);
		}

		// Release hold and cancel timer.
		CapacityManager::release( $class_id, $session_key );
		CheckoutTimer::cancel( $session_key );

		/**
		 * Fires after a new booking has been created successfully.
		 *
		 * @param int $booking_id  dish_booking post ID.
		 * @param int $class_id    dish_class post ID.
		 * @param int $qty         Number of tickets booked.
		 * @param int $total_cents Total charged in integer cents.
		 * @since 1.0.2
		 */
		do_action( 'dish_booking_created', $booking_id, $class_id, $qty, $total_cents );

		return $booking_id;
	}

	/**
	 * Cancel an active checkout session — release hold and delete timer.
	 *
	 * Safe to call even when the session is already expired or gone.
	 *
	 * @param string $session_key
	 */
	public static function cancel( string $session_key ): void {
		$timer = CheckoutTimer::get( $session_key );

		if ( $timer ) {
			CapacityManager::release( (int) $timer['class_id'], $session_key );
		}

		CheckoutTimer::cancel( $session_key );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Resolve ticket type and pricing for a class/qty combination.
	 *
	 * @param int $class_id
	 * @param int $qty
	 * @return array{price_cents:int, total_cents:int, ticket_type_id:int}
	 */
	private static function ticket_pricing( int $class_id, int $qty ): array {
		$template_id    = (int) get_post_meta( $class_id, 'dish_template_id', true );
		$ticket_type    = $template_id ? ClassTemplateRepository::get_ticket_type( $template_id ) : null;
		$price_cents    = $ticket_type ? (int) $ticket_type->price_cents : 0;
		$ticket_type_id = $ticket_type ? (int) $ticket_type->id : 0;

		[ 'fee_lines' => $fee_lines, 'fees_cents' => $fees_cents ] = self::calculate_fees( $ticket_type, $qty );

		return [
			'price_cents'    => $price_cents,
			'total_cents'    => $price_cents * $qty + $fees_cents,
			'ticket_type_id' => $ticket_type_id,
			'fee_lines'      => $fee_lines,
		];
	}

	/**
	 * Decode and sum per-ticket and per-booking fees for a given quantity.
	 *
	 * @param object|null $ticket_type  stdClass row from dish_ticket_types, or null.
	 * @param int         $qty
	 * @return array{fee_lines:list<array{label:string,amount_cents:int,per_ticket:bool}>, fees_cents:int}
	 */
	private static function calculate_fees( ?object $ticket_type, int $qty ): array {
		if ( ! $ticket_type ) {
			return [ 'fee_lines' => [], 'fees_cents' => 0 ];
		}

		$fee_lines   = [];
		$fees_cents  = 0;

		$per_ticket  = json_decode( $ticket_type->per_ticket_fees  ?? 'null', true ) ?? [];
		$per_booking = json_decode( $ticket_type->per_booking_fees ?? 'null', true ) ?? [];

		foreach ( $per_ticket as $fee ) {
			$amount = (int) ( $fee['amount_cents'] ?? 0 );
			if ( $amount > 0 ) {
				$fee_lines[]  = [
					'label'        => (string) ( $fee['label'] ?? '' ),
					'amount_cents' => $amount,
					'per_ticket'   => true,
				];
				$fees_cents += $amount * $qty;
			}
		}

		foreach ( $per_booking as $fee ) {
			$amount = (int) ( $fee['amount_cents'] ?? 0 );
			if ( $amount > 0 ) {
				$fee_lines[]  = [
					'label'        => (string) ( $fee['label'] ?? '' ),
					'amount_cents' => $amount,
					'per_ticket'   => false,
				];
				$fees_cents += $amount;
			}
		}

		return [ 'fee_lines' => $fee_lines, 'fees_cents' => $fees_cents ];
	}
}
