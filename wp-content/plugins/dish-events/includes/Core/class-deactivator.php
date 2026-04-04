<?php
/**
 * Plugin Deactivator.
 *
 * Runs on register_deactivation_hook(). Responsible only for cleaning up
 * runtime state — scheduled events and transient flags.
 *
 * Does NOT delete any persistent data (tables, options, post content).
 * Data removal happens in uninstall.php.
 *
 * @package Dish\Events\Core
 */

declare( strict_types=1 );

namespace Dish\Events\Core;

/**
 * Class Deactivator
 */
final class Deactivator {

	/**
	 * Run all deactivation routines.
	 * Called via register_deactivation_hook() in dish-events.php.
	 */
	public static function deactivate(): void {
		self::unschedule_cron();
		self::clear_flags();
	}

	// -------------------------------------------------------------------------
	// Cron
	// -------------------------------------------------------------------------

	/**
	 * Unschedule all plugin cron events.
	 */
	private static function unschedule_cron(): void {
		$jobs = [
			'dish_cleanup_expired_bookings',
		];

		foreach ( $jobs as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Flags
	// -------------------------------------------------------------------------

	/**
	 * Clear transient activation flags so they don't linger across
	 * deactivation/reactivation cycles.
	 */
	private static function clear_flags(): void {
		delete_option( 'dish_flush_rewrite_rules' );
		delete_option( 'dish_activation_redirect' );
	}
}
