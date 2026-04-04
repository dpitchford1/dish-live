<?php
/**
 * Capacity manager.
 *
 * Tracks real-time spot availability for dish_class instances by combining
 * confirmed booking counts with short-lived checkout holds.
 *
 * Holds are stored as a single transient per class:
 *   dish_holds_{class_id}  →  array< session_key, array{qty:int, expires_at:int} >
 *
 * This keeps the pattern simple and readable while avoiding per-session DB
 * queries.  Expired holds are pruned lazily on every read/write.
 *
 * @package Dish\Events\Booking
 */

declare( strict_types=1 );

namespace Dish\Events\Booking;

use Dish\Events\Admin\Settings;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\ClassTemplateRepository;

/**
 * Class CapacityManager
 */
final class CapacityManager {

	/** Transient key prefix for per-class hold maps. */
	private const HOLDS_PREFIX = 'dish_holds_';

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Attempt to place a hold for $qty spots in $class_id under $session_key.
	 *
	 * Returns true on success, false when there are not enough spots.
	 * If $session_key already has a hold for this class it is replaced.
	 *
	 * @param int    $class_id    dish_class post ID.
	 * @param int    $qty         Number of spots to hold.
	 * @param string $session_key Unique checkout session identifier.
	 * @return bool
	 */
	public static function hold( int $class_id, int $qty, string $session_key ): bool {
		$holds = self::load_holds( $class_id );

		// Prune expired holds.
		$now = time();
		foreach ( $holds as $sk => $data ) {
			if ( (int) $data['expires_at'] < $now ) {
				unset( $holds[ $sk ] );
			}
		}

		// Calculate spots held by other sessions (not this one).
		$held_by_others = 0;
		foreach ( $holds as $sk => $data ) {
			if ( $sk !== $session_key ) {
				$held_by_others += (int) $data['qty'];
			}
		}

		$capacity = self::get_capacity( $class_id );
		// 0 capacity = unlimited — hold always succeeds.
		if ( $capacity > 0 ) {
			$booked    = ClassRepository::get_booked_count( $class_id );
			$available = $capacity - $booked - $held_by_others;

			if ( $available < $qty ) {
				return false;
			}
		}

		// Determine hold expiry from plugin settings.
		$minutes = max( 1, (int) Settings::get( 'checkout_timer_minutes', 10 ) );
		$expires  = $now + ( $minutes * 60 );

		$holds[ $session_key ] = [
			'qty'        => $qty,
			'expires_at' => $expires,
		];

		self::save_holds( $class_id, $holds );

		return true;
	}

	/**
	 * Release a hold for the given session and class.
	 *
	 * Safe to call even if no hold exists for the session.
	 *
	 * @param int    $class_id    dish_class post ID.
	 * @param string $session_key Checkout session identifier.
	 */
	public static function release( int $class_id, string $session_key ): void {
		if ( ! $session_key ) {
			return;
		}

		$holds = self::load_holds( $class_id );
		unset( $holds[ $session_key ] );
		self::save_holds( $class_id, $holds );
	}

	/**
	 * Return the number of available spots for a class (capacity − booked − held).
	 *
	 * Returns PHP_INT_MAX for classes with no capacity limit.
	 *
	 * @param int $class_id dish_class post ID.
	 * @return int
	 */
	public static function get_available( int $class_id ): int {
		$capacity = self::get_capacity( $class_id );

		if ( $capacity <= 0 ) {
			return PHP_INT_MAX;
		}

		$booked = ClassRepository::get_booked_count( $class_id );
		$held   = self::get_held( $class_id );

		return max( 0, $capacity - $booked - $held );
	}

	/**
	 * Return the total number of spots currently held (non-expired) for a class.
	 *
	 * @param int $class_id dish_class post ID.
	 * @return int
	 */
	public static function get_held( int $class_id ): int {
		$holds = self::load_holds( $class_id );
		$now   = time();
		$total = 0;

		foreach ( $holds as $data ) {
			if ( (int) $data['expires_at'] >= $now ) {
				$total += (int) $data['qty'];
			}
		}

		return $total;
	}

	/**
	 * Remove all expired holds for a class and persist the pruned map.
	 *
	 * Called by the cron cleanup job or lazily on every read/write.
	 *
	 * @param int $class_id dish_class post ID.
	 */
	public static function cleanup_for_class( int $class_id ): void {
		$holds   = self::load_holds( $class_id );
		$now     = time();
		$changed = false;

		foreach ( $holds as $sk => $data ) {
			if ( (int) $data['expires_at'] < $now ) {
				unset( $holds[ $sk ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			self::save_holds( $class_id, $holds );
		}
	}

	// -------------------------------------------------------------------------
	// Capacity lookup
	// -------------------------------------------------------------------------

	/**
	 * Resolve the capacity for a class instance from its template's ticket type.
	 *
	 * Returns 0 when the class has no template or no ticket type (= unlimited).
	 *
	 * @param int $class_id dish_class post ID.
	 * @return int
	 */
	private static function get_capacity( int $class_id ): int {
		$template_id = (int) get_post_meta( $class_id, 'dish_template_id', true );

		if ( ! $template_id ) {
			return 0;
		}

		$ticket_type = ClassTemplateRepository::get_ticket_type( $template_id );

		return $ticket_type ? (int) $ticket_type->capacity : 0;
	}

	// -------------------------------------------------------------------------
	// Persistence helpers
	// -------------------------------------------------------------------------

	/**
	 * Load the raw holds map for a class from its transient.
	 *
	 * @param int $class_id
	 * @return array<string, array{qty:int, expires_at:int}>
	 */
	private static function load_holds( int $class_id ): array {
		$raw = get_transient( self::HOLDS_PREFIX . $class_id );
		return is_array( $raw ) ? $raw : [];
	}

	/**
	 * Persist the holds map for a class.
	 *
	 * The transient TTL is set to 2 minutes after the latest hold expiry so
	 * data doesn't vanish while at least one active hold still exists.
	 *
	 * @param int   $class_id
	 * @param array $holds
	 */
	private static function save_holds( int $class_id, array $holds ): void {
		if ( empty( $holds ) ) {
			delete_transient( self::HOLDS_PREFIX . $class_id );
			return;
		}

		$max_expires = 0;
		foreach ( $holds as $data ) {
			if ( (int) $data['expires_at'] > $max_expires ) {
				$max_expires = (int) $data['expires_at'];
			}
		}

		$ttl = max( 60, $max_expires - time() + 120 );
		set_transient( self::HOLDS_PREFIX . $class_id, $holds, $ttl );
	}
}
