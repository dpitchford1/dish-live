<?php
/**
 * Checkout field repository.
 *
 * All reads and writes against the `{prefix}dish_checkout_fields` custom DB table.
 * Uses $wpdb->prepare() exclusively — no WP_Query, no raw interpolation.
 *
 * Table columns:
 *   id, field_type, label, options, is_required, apply_per_attendee,
 *   is_active, created_at, updated_at
 *
 * @package Dish\Events\Data
 */

declare( strict_types=1 );

namespace Dish\Events\Data;

/**
 * Class CheckoutFieldRepository
 */
final class CheckoutFieldRepository {

	// -------------------------------------------------------------------------
	// Collections
	// -------------------------------------------------------------------------

	/**
	 * Return all active checkout fields, ordered by id (insertion order).
	 *
	 * @return object[]  Array of stdClass rows.
	 */
	public static function get_active(): array {
		global $wpdb;

		return (array) $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}dish_checkout_fields
			  WHERE is_active = 1
			  ORDER BY id ASC"
		);
	}

	/**
	 * Return a single checkout field row by ID.
	 *
	 * @param int $id dish_checkout_fields.id
	 * @return object|null
	 */
	public static function get( int $id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dish_checkout_fields WHERE id = %d LIMIT 1",
				$id
			)
		);

		return $row ?: null;
	}

	// -------------------------------------------------------------------------
	// Write operations
	// -------------------------------------------------------------------------

	/**
	 * Insert or update a checkout field row.
	 *
	 * Pass `id` in $data to update; omit it to insert.
	 *
	 * @param array{
	 *   id?:                int,
	 *   field_type:         string,
	 *   label:              string,
	 *   options?:           string,
	 *   is_required?:       bool,
	 *   apply_per_attendee?: bool,
	 *   is_active?:         bool,
	 * } $data
	 * @return int|null  Row ID on success, null on failure.
	 */
	public static function save( array $data ): ?int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$row = [
			'field_type'        => sanitize_key( $data['field_type']           ?? 'text' ),
			'label'             => sanitize_text_field( $data['label']                    ?? '' ),
			'options'           => isset( $data['options'] ) ? sanitize_textarea_field( $data['options'] ) : null,
			'is_required'       => (int) ( $data['is_required']        ?? false ),
			'apply_per_attendee' => (int) ( $data['apply_per_attendee'] ?? false ),
			'is_active'         => (int) ( $data['is_active']           ?? true ),
			'updated_at'        => $now,
		];

		$formats = [ '%s', '%s', '%s', '%d', '%d', '%d', '%s' ];

		if ( ! empty( $data['id'] ) ) {
			$result = $wpdb->update(
				$wpdb->prefix . 'dish_checkout_fields',
				$row,
				[ 'id' => absint( $data['id'] ) ],
				$formats,
				[ '%d' ]
			);

			return ( false !== $result ) ? absint( $data['id'] ) : null;
		}

		$row['created_at'] = $now;
		$formats[]         = '%s';

		$result = $wpdb->insert( $wpdb->prefix . 'dish_checkout_fields', $row, $formats );

		return ( false !== $result ) ? (int) $wpdb->insert_id : null;
	}

	/**
	 * Soft-delete a checkout field by setting is_active = 0.
	 *
	 * @param int $id dish_checkout_fields.id
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'dish_checkout_fields',
			[ 'is_active' => 0, 'updated_at' => current_time( 'mysql', true ) ],
			[ 'id'        => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}
}
