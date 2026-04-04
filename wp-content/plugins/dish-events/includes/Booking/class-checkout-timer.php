<?php
/**
 * Checkout timer.
 *
 * Each guest checkout session is assigned a UUID token on initiation.
 * The timer data is stored as a WordPress transient keyed by that token:
 *
 *   dish_timer_{session_key}  →  array{
 *     class_id:   int,
 *     qty:        int,
 *     expires_at: int,   ← Unix timestamp
 *   }
 *
 * The transient TTL is set to 5 minutes beyond the logical expiry so that
 * a race condition between "timer expired" and "cron cleanup" doesn't
 * inadvertently discard data that is still readable.
 *
 * cleanup_expired() is wired to the dish_cleanup_expired_bookings cron action
 * (every 15 minutes).  Because WordPress auto-expires transients, this method
 * is intentionally lightweight — it just fires a hook so that any future
 * database-backed session store can plug in here without changing the caller.
 *
 * @package Dish\Events\Booking
 */

declare( strict_types=1 );

namespace Dish\Events\Booking;

use Dish\Events\Admin\Settings;

/**
 * Class CheckoutTimer
 */
final class CheckoutTimer {

	/** Transient key prefix for individual checkout sessions. */
	private const TIMER_PREFIX = 'dish_timer_';

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Start a new checkout timer session.
	 *
	 * Generates a unique token, persists the session data, and returns
	 * the full session array for use by the caller.
	 *
	 * @param int $class_id dish_class post ID.
	 * @param int $qty      Number of tickets being held.
	 * @return array{session_key:string, class_id:int, qty:int, expires_at:int}
	 */
	public static function start( int $class_id, int $qty ): array {
		$minutes = max( 1, (int) Settings::get( 'checkout_timer_minutes', 10 ) );
		$expires  = time() + ( $minutes * 60 );

		// wp_generate_uuid4() produces a cryptographically-random UUID.
		$session_key = wp_generate_uuid4();

		$data = [
			'class_id'   => $class_id,
			'qty'        => $qty,
			'expires_at' => $expires,
		];

		// Store with 5-minute buffer so reads after logical expiry still work.
		set_transient( self::TIMER_PREFIX . $session_key, $data, $minutes * 60 + 300 );

		return array_merge( [ 'session_key' => $session_key ], $data );
	}

	/**
	 * Retrieve timer data for a given session key.
	 *
	 * Returns null when the transient has been deleted or never existed.
	 *
	 * @param string $session_key UUID token.
	 * @return array{class_id:int, qty:int, expires_at:int}|null
	 */
	public static function get( string $session_key ): ?array {
		if ( '' === $session_key ) {
			return null;
		}

		$data = get_transient( self::TIMER_PREFIX . $session_key );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Check whether a checkout session has passed its logical expiry.
	 *
	 * Returns true if the session does not exist or its expires_at is in the past.
	 *
	 * @param string $session_key
	 * @return bool
	 */
	public static function is_expired( string $session_key ): bool {
		$data = self::get( $session_key );

		if ( ! $data ) {
			return true;
		}

		return time() >= (int) $data['expires_at'];
	}

	/**
	 * Return the number of seconds remaining in a checkout session.
	 *
	 * Returns 0 if the session does not exist or has expired.
	 *
	 * @param string $session_key
	 * @return int
	 */
	public static function get_remaining( string $session_key ): int {
		$data = self::get( $session_key );

		if ( ! $data ) {
			return 0;
		}

		return max( 0, (int) $data['expires_at'] - time() );
	}

	/**
	 * Permanently delete a checkout session timer.
	 *
	 * Safe to call even when no session exists.
	 *
	 * @param string $session_key
	 */
	public static function cancel( string $session_key ): void {
		if ( '' !== $session_key ) {
			delete_transient( self::TIMER_PREFIX . $session_key );
		}
	}

	/**
	 * Cron handler: clean up expired booking sessions.
	 *
	 * WordPress auto-expires transients, so this primarily fires the
	 * dish_checkout_timer_cleanup action for any future storage back-ends
	 * (e.g., a proper sessions table) to hook into.
	 *
	 * Wired in class-plugin.php to the dish_cleanup_expired_bookings cron event.
	 */
	public static function cleanup_expired(): void {
		/**
		 * Fires when the periodic checkout-timer cleanup cron runs.
		 *
		 * Listeners can use this to sweep up orphaned holds in custom storage.
		 *
		 * @since 1.0.2
		 */
		do_action( 'dish_checkout_timer_cleanup' );
	}
}
