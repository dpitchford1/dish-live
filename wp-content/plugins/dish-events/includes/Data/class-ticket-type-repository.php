<?php
/**
 * Ticket type repository.
 *
 * All reads and writes against the `{prefix}dish_ticket_types` custom DB table.
 * Uses $wpdb->prepare() exclusively — no WP_Query, no raw interpolation.
 *
 * Table columns:
 *   id, format_id, name, description, price_cents, sale_price_cents,
 *   capacity, show_remaining, min_per_booking, per_ticket_fees,
 *   per_booking_fees, booking_starts, show_booking_dates, is_active,
 *   created_at, updated_at
 *
 * @package Dish\Events\Data
 */

declare( strict_types=1 );

namespace Dish\Events\Data;

/**
 * Class TicketTypeRepository
 */
final class TicketTypeRepository {

	// -------------------------------------------------------------------------
	// Single record
	// -------------------------------------------------------------------------

	/**
	 * Fetch a single ticket type row by its primary key.
	 *
	 * @param int $id dish_ticket_types.id
	 * @return object|null  stdClass row or null if not found.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dish_ticket_types WHERE id = %d LIMIT 1",
				$id
			)
		);

		return $row ?: null;
	}

	// -------------------------------------------------------------------------
	// Collections
	// -------------------------------------------------------------------------

	/**
	 * Return all active ticket types, ordered by format then name.
	 *
	 * @return object[]  Array of stdClass rows.
	 */
	public static function get_active(): array {
		global $wpdb;

		return (array) $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}dish_ticket_types
			  WHERE is_active = 1
			  ORDER BY format_id ASC, name ASC"
		);
	}

	/**
	 * Return active ticket types for a specific format.
	 *
	 * @param int $format_id dish_format post ID.
	 * @return object[]
	 */
	public static function get_by_format( int $format_id ): array {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dish_ticket_types
				  WHERE format_id = %d AND is_active = 1
				  ORDER BY name ASC",
				$format_id
			)
		);
	}

	// -------------------------------------------------------------------------
	// Write operations
	// -------------------------------------------------------------------------

	/**
	 * Insert or update a ticket type row.
	 *
	 * Pass `id` in $data to update; omit it to insert.
	 *
	 * @param array{
	 *   id?:                 int,
	 *   format_id:           int,
	 *   name:                string,
	 *   description?:        string,
	 *   price_cents:         int,
	 *   sale_price_cents?:   int|null,
	 *   capacity?:           int|null,
	 *   show_remaining?:     bool,
	 *   min_per_booking?:    int,
	 *   per_ticket_fees?:    string,
	 *   per_booking_fees?:   string,
	 *   booking_starts?:     string,
	 *   show_booking_dates?: bool,
	 *   is_active?:          bool,
	 * } $data
	 * @return int|null  Row ID on success, null on failure.
	 */
	public static function save( array $data ): ?int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$row = [
			'format_id'          => absint( $data['format_id']       ?? 0 ),
			'name'               => sanitize_text_field( $data['name']             ?? '' ),
			'description'        => sanitize_textarea_field( $data['description']       ?? '' ),
			'price_cents'        => absint( $data['price_cents']      ?? 0 ),
			'sale_price_cents'   => isset( $data['sale_price_cents'] ) ? absint( $data['sale_price_cents'] ) : null,
			'capacity'           => isset( $data['capacity'] )         ? absint( $data['capacity'] )          : null,
			'show_remaining'     => (int) ( $data['show_remaining']   ?? false ),
			'min_per_booking'    => max( 1, absint( $data['min_per_booking'] ?? 1 ) ),
			'per_ticket_fees'    => $data['per_ticket_fees']   ?? null,
			'per_booking_fees'   => $data['per_booking_fees']  ?? null,
			'booking_starts'     => $data['booking_starts']    ?? null,
			'show_booking_dates' => (int) ( $data['show_booking_dates'] ?? false ),
			'is_active'          => (int) ( $data['is_active'] ?? true ),
			'updated_at'         => $now,
		];

		$formats = [ '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s' ];

		if ( ! empty( $data['id'] ) ) {
			// Update.
			$result = $wpdb->update(
				$wpdb->prefix . 'dish_ticket_types',
				$row,
				[ 'id' => absint( $data['id'] ) ],
				$formats,
				[ '%d' ]
			);

			return ( false !== $result ) ? absint( $data['id'] ) : null;
		}

		// Insert.
		$row['created_at'] = $now;
		$formats[]         = '%s';

		$result = $wpdb->insert( $wpdb->prefix . 'dish_ticket_types', $row, $formats );

		return ( false !== $result ) ? (int) $wpdb->insert_id : null;
	}

	/**
	 * Soft-delete a ticket type by setting is_active = 0.
	 *
	 * Hard deletes are avoided because ticket types may be referenced by
	 * existing bookings.
	 *
	 * @param int $id dish_ticket_types.id
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'dish_ticket_types',
			[ 'is_active' => 0, 'updated_at' => current_time( 'mysql', true ) ],
			[ 'id'        => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}
}
