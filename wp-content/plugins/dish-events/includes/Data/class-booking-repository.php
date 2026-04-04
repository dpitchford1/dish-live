<?php
/**
 * Booking repository.
 *
 * Reads and writes dish_booking posts and their meta. All write operations
 * (create, update_status, add_note) are the canonical path for mutating
 * booking state — callers should never write booking meta directly.
 *
 * Meta keys managed:
 *   dish_class_id           int    — dish_class post ID
 *   dish_customer_name      str
 *   dish_customer_email     str
 *   dish_customer_phone     str
 *   dish_customer_user_id   int    — WP user ID (0 = guest)
 *   dish_ticket_type_id     int    — dish_ticket_types.id
 *   dish_ticket_qty         int
 *   dish_ticket_total_cents int
 *   dish_transaction_id     str    — payment gateway reference
 *   dish_gateway            str    — 'paypal'|'manual'|…
 *   dish_attendees          json   — array of {name,email,…}
 *   dish_booking_notes      json   — array of {note,author,date}
 *
 * @package Dish\Events\Data
 */

declare( strict_types=1 );

namespace Dish\Events\Data;

use WP_Post;
use WP_Query;

/**
 * Class BookingRepository
 */
final class BookingRepository {

	// -------------------------------------------------------------------------
	// Single record
	// -------------------------------------------------------------------------

	/**
	 * Fetch a single dish_booking post by ID.
	 *
	 * @param int $post_id
	 * @return WP_Post|null
	 */
	public static function get( int $post_id ): ?WP_Post {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'dish_booking' !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	// -------------------------------------------------------------------------
	// Collections
	// -------------------------------------------------------------------------

	/**
	 * Return all bookings for a given class instance.
	 *
	 * @param int          $class_id dish_class post ID.
	 * @param string|array $status   Post status filter. Default: any active status.
	 * @return WP_Post[]
	 */
	public static function get_for_class( int $class_id, string|array $status = 'any' ): array {
		$q = new WP_Query( [
			'post_type'      => 'dish_booking',
			'post_status'    => $status,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'key'     => 'dish_class_id',
					'value'   => $class_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
		] );

		return $q->posts;
	}

	/**
	 * Return all bookings for a customer (by email address).
	 *
	 * @param string       $email  Customer email.
	 * @param string|array $status Post status filter. Default: any.
	 * @return WP_Post[]
	 */
	public static function get_for_customer( string $email, string|array $status = 'any', ?int $user_id = null ): array {
		// WP_Query's 'any' skips statuses with exclude_from_search => true, which
		// all our custom booking statuses use. Enumerate them explicitly instead.
		if ( 'any' === $status ) {
			$status = [ 'dish_pending', 'dish_completed', 'dish_failed', 'dish_refunded', 'dish_cancelled' ];
		}

		$base_args = [
			'post_type'      => 'dish_booking',
			'post_status'    => $status,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'fields'         => 'all',
		];

		// Primary lookup: by customer email.
		$by_email = ( new WP_Query( array_merge( $base_args, [
			'meta_query' => [
				[
					'key'     => 'dish_customer_email',
					'value'   => sanitize_email( $email ),
					'compare' => '=',
				],
			],
		] ) ) )->posts;

		// Secondary lookup: by linked WP user ID (populated after account creation).
		$by_user = [];
		if ( $user_id && $user_id > 0 ) {
			$by_user = ( new WP_Query( array_merge( $base_args, [
				'meta_query' => [
					[
						'key'     => 'dish_customer_user_id',
						'value'   => $user_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			] ) ) )->posts;
		}

		// Merge and deduplicate by post ID, preserving date-DESC order.
		$seen  = [];
		$posts = [];
		foreach ( array_merge( $by_email, $by_user ) as $post ) {
			if ( ! isset( $seen[ $post->ID ] ) ) {
				$seen[ $post->ID ] = true;
				$posts[]           = $post;
			}
		}

		return $posts;
	}

	// -------------------------------------------------------------------------
	// Write operations
	// -------------------------------------------------------------------------

	/**
	 * Create a new booking post and persist all meta in a single atomic-ish call.
	 *
	 * @param array{
	 *   class_id:           int,
	 *   customer_name:      string,
	 *   customer_email:     string,
	 *   customer_phone?:    string,
	 *   customer_user_id?:  int,
	 *   ticket_type_id:     int,
	 *   ticket_qty:         int,
	 *   total_cents:        int,
	 *   transaction_id?:    string,
	 *   gateway?:           string,
	 *   attendees?:         array,
	 *   status?:            string,
	 * } $data
	 * @return int|null  New post ID, or null on failure.
	 */
	public static function create( array $data ): ?int {
		$status    = $data['status'] ?? 'dish_pending';
		$cust_name = sanitize_text_field( $data['customer_name'] ?? '' );

		$post_id = wp_insert_post( [
			'post_type'   => 'dish_booking',
			'post_title'  => $cust_name ?: __( 'Booking', 'dish-events' ),
			'post_status' => $status,
			'post_author' => 0,
		], true );

		if ( is_wp_error( $post_id ) ) {
			return null;
		}

		$meta = [
			'dish_class_id'           => absint( $data['class_id']          ?? 0 ),
			'dish_customer_name'      => $cust_name,
			'dish_customer_email'     => sanitize_email( $data['customer_email']    ?? '' ),
			'dish_customer_phone'     => sanitize_text_field( $data['customer_phone']    ?? '' ),
			'dish_customer_user_id'   => absint( $data['customer_user_id']  ?? 0 ),
			'dish_ticket_type_id'     => absint( $data['ticket_type_id']    ?? 0 ),
			'dish_ticket_qty'         => absint( $data['ticket_qty']        ?? 1 ),
			'dish_ticket_total_cents' => absint( $data['total_cents']       ?? 0 ),
			'dish_transaction_id'     => sanitize_text_field( $data['transaction_id']    ?? '' ),
			'dish_gateway'            => sanitize_key( $data['gateway']             ?? '' ),
			'dish_attendees'          => wp_json_encode( $data['attendees'] ?? [] ),
			'dish_booking_notes'      => '[]',
		];

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * Transition a booking to a new post status.
	 *
	 * This is the canonical way to change booking state. Callers should not
	 * call wp_update_post() directly for status changes.
	 *
	 * @param int    $post_id    dish_booking post ID.
	 * @param string $new_status Valid dish_booking post status slug.
	 * @return bool  True on success.
	 */
	public static function update_status( int $post_id, string $new_status ): bool {
		$result = wp_update_post( [
			'ID'          => $post_id,
			'post_status' => $new_status,
		] );

		return ! is_wp_error( $result ) && $result > 0;
	}

	/**
	 * Append an admin note to a booking's note history.
	 *
	 * @param int    $post_id     dish_booking post ID.
	 * @param string $note        Plain-text note content.
	 * @param string $author      Display name of the note author.
	 * @return bool
	 */
	public static function add_note( int $post_id, string $note, string $author = '' ): bool {
		$raw   = get_post_meta( $post_id, 'dish_booking_notes', true ) ?: '[]';
		$notes = (array) json_decode( (string) $raw, true );

		if ( '' === $author ) {
			$user   = wp_get_current_user();
			$author = $user->exists() ? $user->display_name : __( 'System', 'dish-events' );
		}

		$notes[] = [
			'note'   => sanitize_textarea_field( $note ),
			'author' => sanitize_text_field( $author ),
			'date'   => wp_date( 'j M Y g:i a' ),
		];

		update_post_meta( $post_id, 'dish_booking_notes', wp_json_encode( $notes ) );

		return true;
	}

	// -------------------------------------------------------------------------
	// Export
	// -------------------------------------------------------------------------

	/**
	 * Return booking data for a class as a 2D array suitable for CSV export.
	 *
	 * Row 0 is the header row. Subsequent rows are individual bookings.
	 *
	 * @param int $class_id dish_class post ID.
	 * @return array<int, array<int, string>>
	 */
	public static function export_csv( int $class_id ): array {
		$bookings = self::get_for_class( $class_id );

		$rows = [
			[
				__( 'Booking ID',   'dish-events' ),
				__( 'Date',         'dish-events' ),
				__( 'Status',       'dish-events' ),
				__( 'Name',         'dish-events' ),
				__( 'Email',        'dish-events' ),
				__( 'Phone',        'dish-events' ),
				__( 'Tickets',      'dish-events' ),
				__( 'Total',        'dish-events' ),
				__( 'Transaction',  'dish-events' ),
				__( 'Gateway',      'dish-events' ),
			],
		];

		foreach ( $bookings as $booking ) {
			$rows[] = [
				(string) $booking->ID,
				get_the_date( 'Y-m-d H:i', $booking ),
				$booking->post_status,
				(string) get_post_meta( $booking->ID, 'dish_customer_name',      true ),
				(string) get_post_meta( $booking->ID, 'dish_customer_email',     true ),
				(string) get_post_meta( $booking->ID, 'dish_customer_phone',     true ),
				(string) get_post_meta( $booking->ID, 'dish_ticket_qty',         true ),
				(string) get_post_meta( $booking->ID, 'dish_ticket_total_cents', true ),
				(string) get_post_meta( $booking->ID, 'dish_transaction_id',     true ),
				(string) get_post_meta( $booking->ID, 'dish_gateway',            true ),
			];
		}

		return $rows;
	}

	// -------------------------------------------------------------------------
	// Customer helpers
	// -------------------------------------------------------------------------

	/**
	 * Total seats booked by a customer for a specific class instance.
	 *
	 * Looks up by email and, when provided, by linked WP user ID, then sums
	 * dish_ticket_qty across all non-cancelled/failed bookings.
	 *
	 * @param int      $class_id dish_class post ID.
	 * @param string   $email    Customer email.
	 * @param int|null $user_id  WP user ID (optional).
	 * @return int Total seats booked; 0 if none.
	 */
	public static function get_customer_seat_count( int $class_id, string $email, ?int $user_id = null ): int {
		global $wpdb;

		$active_statuses = [ 'dish_pending', 'dish_completed', 'dish_refunded' ];
		$status_in       = implode( ',', array_fill( 0, count( $active_statuses ), '%s' ) );

		$uid_join        = '';
		$customer_clause = $wpdb->prepare( 'pmcust.meta_value = %s', sanitize_email( $email ) );

		if ( $user_id && $user_id > 0 ) {
			$uid_join        = "LEFT JOIN {$wpdb->postmeta} pmuid ON pmuid.post_id = p.ID AND pmuid.meta_key = 'dish_customer_user_id'";
			$customer_clause = $wpdb->prepare(
				'( pmcust.meta_value = %s OR pmuid.meta_value = %d )',
				sanitize_email( $email ),
				$user_id
			);
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT COALESCE( SUM( qty.meta_value + 0 ), 0 )
			   FROM {$wpdb->posts} p
			   JOIN {$wpdb->postmeta} pmclass
			        ON pmclass.post_id = p.ID AND pmclass.meta_key = 'dish_class_id' AND pmclass.meta_value = %d
			   JOIN {$wpdb->postmeta} pmcust
			        ON pmcust.post_id = p.ID AND pmcust.meta_key = 'dish_customer_email'
			   JOIN {$wpdb->postmeta} qty
			        ON qty.post_id = p.ID AND qty.meta_key = 'dish_ticket_qty'
			   {$uid_join}
			  WHERE p.post_type   = 'dish_booking'
			    AND p.post_status IN ({$status_in})
			    AND {$customer_clause}",
			array_merge( [ $class_id ], $active_statuses )
		);

		return (int) $wpdb->get_var( $sql );
		// phpcs:enable
	}
}
