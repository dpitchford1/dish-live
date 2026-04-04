<?php
/**
 * Shared helpers for ClassMetaBox panels.
 *
 * Used as a trait so every panel gets identical datetime and price
 * conversion utilities without inheritance.
 *
 * @package Dish\Events\Admin\Panels
 */

declare( strict_types=1 );

namespace Dish\Events\Admin\Panels;

/**
 * Trait MetaBoxHelpers
 */
trait MetaBoxHelpers {

	/**
	 * Convert a UTC epoch to a datetime-local string in site timezone.
	 *
	 * @param  int $epoch UTC Unix timestamp; 0 = empty.
	 * @return string 'Y-m-d\TH:i' or '' when epoch is 0.
	 */
	protected function epoch_to_local( int $epoch ): string {
		if ( $epoch <= 0 ) {
			return '';
		}
		$tz = new \DateTimeZone( wp_timezone_string() );
		$dt = new \DateTimeImmutable( '@' . $epoch );
		return $dt->setTimezone( $tz )->format( 'Y-m-d\TH:i' );
	}

	/**
	 * Convert a datetime-local string (site timezone) to a UTC epoch.
	 *
	 * @param  string $local 'Y-m-d\TH:i' or ''. Empty → 0.
	 * @return int UTC Unix timestamp, or 0 on failure.
	 */
	protected function local_to_epoch( string $local ): int {
		if ( $local === '' ) {
			return 0;
		}
		try {
			$tz = new \DateTimeZone( wp_timezone_string() );
			$dt = new \DateTimeImmutable( $local, $tz );
			return (int) $dt->format( 'U' );
		} catch ( \Throwable ) {
			return 0;
		}
	}

	/**
	 * Convert a display price string ('45.00') to integer cents (4500).
	 *
	 * @param  string $display Dollar amount string.
	 * @return int Cents.
	 */
	protected function display_to_cents( string $display ): int {
		$display = trim( $display );
		if ( $display === '' || $display === '0' || $display === '0.00' ) {
			return 0;
		}
		return (int) round( (float) $display * 100 );
	}

	/**
	 * Label for the recurrence interval unit.
	 *
	 * @param  string $type 'daily'|'weekly'|'monthly'|'none'
	 * @return string
	 */
	protected function interval_unit_label( string $type ): string {
		return match ( $type ) {
			'daily'   => __( 'day(s)', 'dish-events' ),
			'weekly'  => __( 'week(s)', 'dish-events' ),
			'monthly' => __( 'month(s)', 'dish-events' ),
			default   => '',
		};
	}
}
