<?php
/**
 * Public AJAX handlers.
 *
 * Handles all nopriv (guest-accessible) AJAX actions for the checkout flow:
 *
 *   dish_process_booking  — validate fields, call BookingManager::complete(),
 *                           return success + redirect URL.
 *   dish_release_hold     — explicit hold release when a user cancels checkout
 *                           or navigates away (fired via beacon / beforeunload).
 *
 * Both actions are available to logged-in users and guests alike so that the
 * guest-first checkout flow works without an account.
 *
 * Security: dish_process_booking is protected by a nonce ('dish_checkout').
 * dish_release_hold has no nonce because releasing a hold is benign and the
 * action only accepts a random UUID that the attacker would have to know.
 *
 * @package Dish\Events\Frontend
 */

declare( strict_types=1 );

namespace Dish\Events\Frontend;

use Dish\Events\Admin\Settings;
use Dish\Events\Booking\BookingManager;
use Dish\Events\Booking\CheckoutTimer;
use Dish\Events\Core\Loader;
use Dish\Events\Data\BookingRepository;

/**
 * Class PublicAjax
 */
final class PublicAjax {

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	/**
	 * Register AJAX action hooks via the Loader.
	 *
	 * @param Loader $loader
	 */
	public function register_hooks( Loader $loader ): void {
		foreach ( [ 'dish_process_booking', 'dish_release_hold', 'dish_update_qty' ] as $action ) {
			$method = str_replace( 'dish_', '', $action );
			$loader->add_action( 'wp_ajax_' . $action,        $this, $method );
			$loader->add_action( 'wp_ajax_nopriv_' . $action, $this, $method );
		}

		// Delete account is logged-in only — no nopriv variant.
		$loader->add_action( 'wp_ajax_dish_delete_account', $this, 'delete_account' );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * Process the checkout form submission and create a pending booking.
	 *
	 * Expected POST keys:
	 *   nonce          — wp_create_nonce('dish_checkout')
	 *   session_key    — active checkout UUID
	 *   ticket_type_id — int
	 *   customer_name  — string
	 *   customer_email — string
	 *   customer_phone — string (optional)
	 *   qty            — int (1–10)
	 *   attendees      — array[] of per-attendee field responses (optional)
	 *
	 * Returns JSON:
	 *   success: { booking_id: int, redirect_url: string }
	 *   error:   { message: string, field?: string }
	 */
	public function process_booking(): void {
		// ── Nonce ─────────────────────────────────────────────────────────────
		if ( ! check_ajax_referer( 'dish_checkout', 'nonce', false ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Security check failed. Please refresh and try again.', 'dish-events' ) ],
				403
			);
		}

		// ── Session ───────────────────────────────────────────────────────────
		$session_key = sanitize_text_field( wp_unslash( $_POST['session_key'] ?? '' ) );

		if ( '' === $session_key ) {
			wp_send_json_error(
				[ 'message' => __( 'Missing session. Please reload the page.', 'dish-events' ) ],
				400
			);
		}

		$timer = CheckoutTimer::get( $session_key );

		if ( ! $timer || CheckoutTimer::is_expired( $session_key ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Your reservation has expired. Please start over.', 'dish-events' ) ],
				400
			);
		}

		// ── Customer fields ───────────────────────────────────────────────────
		$name  = sanitize_text_field( wp_unslash( $_POST['customer_name']  ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['customer_email']       ?? '' ) );
		$phone = sanitize_text_field( wp_unslash( $_POST['customer_phone']  ?? '' ) );

		if ( '' === $name ) {
			wp_send_json_error(
				[ 'message' => __( 'Please enter your name.', 'dish-events' ), 'field' => 'customer_name' ],
				422
			);
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Please enter a valid email address.', 'dish-events' ), 'field' => 'customer_email' ],
				422
			);
		}

		// ── Terms acceptance ───────────────────────────────────────────────────
		$terms_required = (bool) Settings::get( 'terms_enabled', false );
		if ( $terms_required && empty( $_POST['terms_accepted'] ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Please accept the terms and conditions to continue.', 'dish-events' ), 'field' => 'terms_accepted' ],
				422
			);
		}

		// ── Ticket type and quantity ──────────────────────────────────────────
		$ticket_type_id = absint( $_POST['ticket_type_id'] ?? 0 );

		// ── Attendee data ─────────────────────────────────────────────────────
		$qty           = (int) $timer['qty'];
		$raw_attendees = isset( $_POST['attendees'] ) && is_array( $_POST['attendees'] )
			? $_POST['attendees']
			: [];

		$attendees = [];
		for ( $i = 0; $i < $qty; $i++ ) {
			$raw      = is_array( $raw_attendees[ $i ] ?? null ) ? $raw_attendees[ $i ] : [];
			$attendee = [];
			foreach ( $raw as $key => $val ) {
				$attendee[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( (string) $val ) );
			}
			$attendees[] = $attendee;
		}

		// ── Complete booking ──────────────────────────────────────────────────
		$result = BookingManager::complete(
			$session_key,
			[ 'name' => $name, 'email' => $email, 'phone' => $phone ],
			$attendees,
			$ticket_type_id
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[ 'message' => $result->get_error_message() ],
				422
			);
		}

		$booking_id  = (int) $result;

		// ── Optional account creation ─────────────────────────────────────────
		// Respect the WP "Anyone can register" setting (Settings → General).
		if ( ! is_user_logged_in() && ! empty( $_POST['create_account'] ) && get_option( 'users_can_register' ) ) {
			$username = sanitize_user( wp_unslash( $_POST['account_username'] ?? '' ), true );
			$password = wp_unslash( $_POST['account_password'] ?? '' );

			if ( '' === $username ) {
				wp_send_json_error(
					[ 'message' => __( 'Please enter a username for your account.', 'dish-events' ), 'field' => 'account_username' ],
					422
				);
			}

			if ( username_exists( $username ) ) {
				wp_send_json_error(
					[ 'message' => __( 'That username is already taken. Please choose another.', 'dish-events' ), 'field' => 'account_username' ],
					422
				);
			}

			if ( strlen( $password ) < 8 ) {
				wp_send_json_error(
					[ 'message' => __( 'Your password must be at least 8 characters.', 'dish-events' ), 'field' => 'account_password' ],
					422
				);
			}

			$user_id = wp_create_user( $username, $password, $email );

			if ( ! is_wp_error( $user_id ) ) {
				// Store the customer name as the display name.
				wp_update_user( [ 'ID' => $user_id, 'display_name' => $name, 'first_name' => $name ] );

				BookingRepository::link_user( $booking_id, $user_id );

				// Log them in immediately.
				wp_set_auth_cookie( $user_id, false );
			}
			// Non-fatal: if account creation fails we still have a valid booking.
		}

		$booking_key = BookingRepository::ensure_booking_key( $booking_id );

		// Build confirmation URL.
		$details_pid = (int) Settings::get( 'booking_details_page', 0 );

		$redirect_url = $details_pid
			? add_query_arg(
				[ 'booking_id' => $booking_id, 'key' => $booking_key ],
				get_permalink( $details_pid )
			)
			: home_url( '/' );

		wp_send_json_success( [
			'booking_id'   => $booking_id,
			'redirect_url' => esc_url_raw( $redirect_url ),
		] );
	}

	/**
	 * Release a checkout hold without creating a booking.
	 *
	 * Called via navigator.sendBeacon on page unload so that held spots are
	 * freed immediately rather than waiting for the timer to expire naturally.
	 *
	 * Expected POST keys:
	 *   session_key  — UUID of the session to cancel.
	 */
	public function release_hold(): void {
		$session_key = sanitize_text_field( wp_unslash( $_POST['session_key'] ?? '' ) );

		if ( '' !== $session_key ) {
			BookingManager::cancel( $session_key );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: swap the active hold for a new one with a different quantity.
	 *
	 * Cancels the caller's existing checkout session and calls
	 * BookingManager::initiate() with the requested qty. The JS timer is reset
	 * client-side using the returned expires_at.
	 *
	 * Expected POST keys:
	 *   nonce        — wp_create_nonce('dish_checkout')
	 *   session_key  — current active checkout UUID
	 *   class_id     — int
	 *   qty          — int (1–8)
	 *
	 * Returns JSON:
	 *   success: { session_key, expires_at, price_cents, total_cents, ticket_type_id }
	 *   error:   { message: string }
	 */
	public function update_qty(): void {
		if ( ! check_ajax_referer( 'dish_checkout', 'nonce', false ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Security check failed. Please refresh and try again.', 'dish-events' ) ],
				403
			);
		}

		$session_key = sanitize_text_field( wp_unslash( $_POST['session_key'] ?? '' ) );
		$class_id    = absint( $_POST['class_id'] ?? 0 );
		$new_qty     = max( 1, min( 8, absint( $_POST['qty'] ?? 1 ) ) );

		if ( '' === $session_key || ! $class_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'dish-events' ) ], 400 );
		}

		// Release the existing hold before starting a new one.
		BookingManager::cancel( $session_key );

		$result = BookingManager::initiate( $class_id, $new_qty );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		// Refresh the browser cookie so it carries the new session key.
		setcookie(
			'dish_checkout_session',
			$result['session_key'],
			[
				'expires'  => $result['expires_at'] + 600,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);

		wp_send_json_success( [
			'session_key'    => $result['session_key'],
			'expires_at'     => $result['expires_at'],
			'price_cents'    => $result['price_cents'],
			'total_cents'    => $result['total_cents'],
			'ticket_type_id' => $result['ticket_type_id'],
		] );
	}

	/**
	 * Delete the current user's account.
	 *
	 * Anonymises all booking records linked to the account (by user ID or
	 * email), then deletes the WordPress user. The booking email is intentionally
	 * preserved on each booking post as a payment-reconciliation reference;
	 * all other PII (name, phone, user link) is cleared.
	 *
	 * Expected POST keys:
	 *   nonce          — wp_create_nonce('dish_delete_account')
	 *   confirm_email  — must match the current user's registered email
	 */
	public function delete_account(): void {
		if ( ! check_ajax_referer( 'dish_delete_account', 'nonce', false ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Security check failed. Please refresh and try again.', 'dish-events' ) ],
				403
			);
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in to delete your account.', 'dish-events' ) ], 401 );
		}

		$user          = wp_get_current_user();
		$confirm_email = sanitize_email( wp_unslash( $_POST['confirm_email'] ?? '' ) );

		if ( '' === $confirm_email || ! hash_equals( $user->user_email, $confirm_email ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Email address does not match. Please try again.', 'dish-events' ), 'field' => 'confirm_email' ],
				422
			);
		}

		// Don't allow admins or editors to self-delete here — that would be
		// catastrophic and should go through the WP admin panel.
		if ( user_can( $user->ID, 'edit_posts' ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Administrator and editor accounts cannot be deleted from the front end.', 'dish-events' ) ],
				403
			);
		}

		// ── Anonymise all bookings linked to this user ──────────────────────────
		BookingRepository::anonymise_for_user( $user->ID, $user->user_email );

		// ── Delete the WordPress user ────────────────────────────────────────
		// wp_delete_user() lives in the admin includes.
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$deleted = wp_delete_user( $user->ID );

		if ( ! $deleted ) {
			wp_send_json_error(
				[ 'message' => __( 'Something went wrong. Please contact us to delete your account.', 'dish-events' ) ],
				500
			);
		}

		// Clear cookies and session.
		wp_logout();

		wp_send_json_success( [
			'redirect_url' => esc_url_raw( home_url( '/' ) ),
		] );
	}

	// -------------------------------------------------------------------------
	// Booking key helpers (delegates to BookingRepository — canonical location)
	// -------------------------------------------------------------------------

	/**
	 * Return (and lazily create) the URL-safe verification key for a booking.
	 *
	 * Delegates to BookingRepository::ensure_booking_key(). Kept here for
	 * template backward-compatibility.
	 *
	 * @param int $booking_id dish_booking post ID.
	 * @return string 12-character hex key.
	 */
	public static function ensure_booking_key( int $booking_id ): string {
		return BookingRepository::ensure_booking_key( $booking_id );
	}

	/**
	 * Verify a booking key matches the one stored for the given booking.
	 *
	 * Delegates to BookingRepository::verify_booking_key(). Kept here for
	 * template backward-compatibility.
	 *
	 * @param int    $booking_id
	 * @param string $key
	 * @return bool
	 */
	public static function verify_booking_key( int $booking_id, string $key ): bool {
		return BookingRepository::verify_booking_key( $booking_id, $key );
	}
}
