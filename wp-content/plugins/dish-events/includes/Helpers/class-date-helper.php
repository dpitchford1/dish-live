<?php
/**
 * Date / timezone helper.
 *
 * All timestamps stored in the database are Unix epoch (UTC integers).
 * This helper converts between epoch, site-local datetime strings, and
 * display strings — always respecting the WordPress "Timezone" setting.
 *
 * Usage:
 *   $epoch   = DateHelper::from_input( '2026-06-15T14:00' );   // local → epoch
 *   $display = DateHelper::to_display( $epoch );               // epoch → "15 Jun 2026 2:00 pm"
 *   $iso     = DateHelper::format( $epoch, 'Y-m-d H:i' );      // epoch → arbitrary format
 *
 * @package Dish\Events\Helpers
 */

declare( strict_types=1 );

namespace Dish\Events\Helpers;

/**
 * Class DateHelper
 */
final class DateHelper {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Convert a Unix epoch (UTC) to a human-readable display string in the
	 * site's configured timezone.
	 *
	 * Format: "15 Jun 2026 2:00 pm"  (respects WP date/time format settings)
	 *
	 * @param int $epoch Unix timestamp (UTC).
	 * @return string  Empty string if $epoch is 0.
	 */
	public static function to_display( int $epoch ): string {
		if ( 0 === $epoch ) {
			return '';
		}

		return wp_date(
			get_option( 'date_format', 'j M Y' ) . ' ' . get_option( 'time_format', 'g:i a' ),
			$epoch
		);
	}

	/**
	 * Format an epoch using an arbitrary PHP date format string, in the site
	 * timezone.
	 *
	 * @param int    $epoch  Unix timestamp (UTC).
	 * @param string $format PHP date() format string.
	 * @return string
	 */
	public static function format( int $epoch, string $format ): string {
		if ( 0 === $epoch ) {
			return '';
		}

		return wp_date( $format, $epoch );
	}

	/**
	 * Convert a `datetime-local` input value ("Y-m-d\TH:i", no timezone) to a
	 * UTC Unix epoch.
	 *
	 * The input is treated as site-local time (matching what the `<input
	 * type="datetime-local">` widget displays).
	 *
	 * @param string $local_str e.g. "2026-06-15T14:00" or "2026-06-15 14:00:00"
	 * @return int UTC epoch, or 0 on parse failure.
	 */
	public static function from_input( string $local_str ): int {
		$local_str = trim( $local_str );

		if ( '' === $local_str ) {
			return 0;
		}

		$tz = wp_timezone();

		try {
			$dt = new \DateTimeImmutable( $local_str, $tz );
		} catch ( \Exception $e ) {
			return 0;
		}

		return $dt->getTimestamp();
	}

	/**
	 * Convert a UTC epoch back to a site-local "Y-m-d\TH:i" string suitable
	 * for populating `<input type="datetime-local">`.
	 *
	 * @param int $epoch UTC Unix timestamp.
	 * @return string e.g. "2026-06-15T14:00"  Empty string if epoch is 0.
	 */
	public static function to_input( int $epoch ): string {
		if ( 0 === $epoch ) {
			return '';
		}

		return wp_date( 'Y-m-d\TH:i', $epoch );
	}

	/**
	 * Return the current UTC epoch.
	 *
	 * Thin wrapper around time() — gives tests an obvious seam to mock.
	 *
	 * @return int
	 */
	public static function now(): int {
		return time();
	}

	/**
	 * Return true when the given epoch is in the past.
	 *
	 * An epoch of 0 (unset) is not considered past.
	 *
	 * @param int $epoch UTC Unix timestamp.
	 * @return bool
	 */
	public static function is_past( int $epoch ): bool {
		return $epoch > 0 && $epoch < time();
	}
}
