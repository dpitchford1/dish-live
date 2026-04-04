<?php
/**
 * Money / currency helper.
 *
 * The plugin stores all monetary values as integer cents (e.g. $45.00 = 4500).
 * This helper converts between cents and display strings, always respecting
 * the currency symbol, decimal separator, and thousands separator stored in
 * Dish plugin settings (falling back to sensible CAD defaults).
 *
 * Usage:
 *   echo MoneyHelper::cents_to_display( 4500 );   // "$45.00"
 *   $cents = MoneyHelper::display_to_cents( '45.00' );  // 4500
 *
 * @package Dish\Events\Helpers
 */

declare( strict_types=1 );

namespace Dish\Events\Helpers;

use Dish\Events\Admin\Settings;

/**
 * Class MoneyHelper
 */
final class MoneyHelper {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Convert an integer cent value to a formatted currency string.
	 *
	 * @param int  $cents       Amount in cents (e.g. 4500).
	 * @param bool $symbol      Whether to prepend the currency symbol. Default true.
	 * @return string           e.g. "$45.00"
	 */
	public static function cents_to_display( int $cents, bool $symbol = true ): string {
		$dollars = $cents / 100;

		$formatted = number_format(
			$dollars,
			2,
			self::decimal_sep(),
			self::thousands_sep()
		);

		if ( $symbol ) {
			$formatted = self::currency_symbol() . $formatted;
		}

		return $formatted;
	}

	/**
	 * Parse a user-supplied price string into integer cents.
	 *
	 * Strips currency symbol, thousands separators, and whitespace before
	 * converting to cents. Accepts both "45.00" and "$45.00".
	 *
	 * @param string $display e.g. "$45.00" or "45.00" or "45"
	 * @return int            e.g. 4500. Returns 0 for unparseable input.
	 */
	public static function display_to_cents( string $display ): int {
		// Strip everything that isn't a digit, dot, or comma.
		$clean = preg_replace( '/[^\d.,]/', '', trim( $display ) );

		if ( null === $clean || '' === $clean ) {
			return 0;
		}

		$dec = self::decimal_sep();
		$tho = self::thousands_sep();

		// Remove thousands separators.
		if ( '' !== $tho ) {
			$clean = str_replace( $tho, '', $clean );
		}

		// Normalise decimal separator to dot.
		if ( ',' === $dec ) {
			$clean = str_replace( ',', '.', $clean );
		}

		return (int) round( (float) $clean * 100 );
	}

	/**
	 * Format a price that is already in dollars (float / string) as a display
	 * string.  Convenience wrapper around cents_to_display().
	 *
	 * @param float|string $price  Dollar amount, e.g. 45.0 or "45.00".
	 * @param bool         $symbol Whether to prepend the currency symbol.
	 * @return string
	 */
	public static function format_price( float|string $price, bool $symbol = true ): string {
		$cents = (int) round( (float) $price * 100 );
		return self::cents_to_display( $cents, $symbol );
	}

	// -------------------------------------------------------------------------
	// Internals
	// -------------------------------------------------------------------------

	/**
	 * Currency symbol from plugin settings. Default: "$".
	 *
	 * @return string
	 */
	private static function currency_symbol(): string {
		return (string) Settings::get( 'currency_symbol', '$' );
	}

	/**
	 * Decimal separator from plugin settings. Default: ".".
	 *
	 * @return string
	 */
	private static function decimal_sep(): string {
		return (string) Settings::get( 'decimal_separator', '.' );
	}

	/**
	 * Thousands separator from plugin settings. Default: ",".
	 *
	 * @return string
	 */
	private static function thousands_sep(): string {
		return (string) Settings::get( 'thousands_separator', ',' );
	}
}
