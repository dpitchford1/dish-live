<?php
/**
 * Reports repository.
 *
 * All aggregate queries used by the Reports admin page.
 * Direct $wpdb calls are used throughout because WP_Query is not efficient for
 * cross-row aggregations; every query is fully prepared and the phpcs overrides
 * are annotated inline.
 *
 * All money values are in integer cents.
 *
 * @package Dish\Events\Data
 */

declare( strict_types=1 );

namespace Dish\Events\Data;

/**
 * Class ReportsRepository
 */
final class ReportsRepository {

	// Active booking statuses considered real revenue/attendance.
	private const ACTIVE = [ 'dish_pending', 'dish_completed', 'dish_refunded' ];

	// -------------------------------------------------------------------------
	// Stat totals
	// -------------------------------------------------------------------------

	/**
	 * High-level summary stats for the Bookings tab header.
	 *
	 * @param string $date_from  Y-m-d, or '' for no lower bound.
	 * @param string $date_to    Y-m-d, or '' for no upper bound.
	 * @param string $status     Specific status slug, or '' for all active.
	 * @return array{
	 *   total_bookings: int,
	 *   total_revenue:  int,
	 *   avg_per_day:    int,
	 *   total_tickets:  int,
	 * }
	 */
	public static function get_summary(
		string $date_from = '',
		string $date_to   = '',
		string $status    = ''
	): array {
		global $wpdb;

		$where = self::build_date_status_where( $date_from, $date_to, $status );

		// $where is built from individual $wpdb->prepare() calls — no further prepare() needed.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT
			    COUNT( DISTINCT p.ID )                        AS total_bookings,
				    COALESCE( SUM( pm_total.meta_value + 0 ), 0 ) AS total_revenue,
				    COALESCE( SUM( pm_qty.meta_value   + 0 ), 0 ) AS total_tickets
				  FROM {$wpdb->posts} p
				  JOIN {$wpdb->postmeta} pm_total
				       ON pm_total.post_id = p.ID AND pm_total.meta_key = 'dish_ticket_total_cents'
				  JOIN {$wpdb->postmeta} pm_qty
				       ON pm_qty.post_id   = p.ID AND pm_qty.meta_key   = 'dish_ticket_qty'
				 WHERE p.post_type = 'dish_booking'
				   {$where}";
		$row = $wpdb->get_row( $sql, ARRAY_A );
		// phpcs:enable

		$total_bookings = (int) ( $row['total_bookings'] ?? 0 );
		$total_revenue  = (int) ( $row['total_revenue']  ?? 0 );
		$total_tickets  = (int) ( $row['total_tickets']  ?? 0 );

		// Average daily revenue over the date range (or all time if unbounded).
		$avg_per_day = 0;
		if ( $total_revenue > 0 ) {
			$days        = self::count_days( $date_from, $date_to );
			$avg_per_day = $days > 0 ? (int) round( $total_revenue / $days ) : $total_revenue;
		}

		return compact( 'total_bookings', 'total_revenue', 'avg_per_day', 'total_tickets' );
	}

	// -------------------------------------------------------------------------
	// Filterable bookings list
	// -------------------------------------------------------------------------

	/**
	 * Paginated, filterable list of bookings for the Bookings tab.
	 *
	 * Returns booking post objects with commonly used meta pre-fetched into a
	 * keyed array so the template avoids N+1 get_post_meta() calls.
	 *
	 * @param string $date_from
	 * @param string $date_to
	 * @param string $status        Specific status, or '' for all active.
	 * @param string $search        Customer name / e-mail fragment.
	 * @param int    $per_page
	 * @param int    $page          1-indexed.
	 * @return array{
	 *   rows:  array<int, array<string, string>>,
	 *   total: int,
	 * }
	 */
	public static function get_bookings_list(
		string $date_from = '',
		string $date_to   = '',
		string $status    = '',
		string $search    = '',
		int    $per_page  = 30,
		int    $page      = 1
	): array {
		global $wpdb;

		$where = self::build_date_status_where( $date_from, $date_to, $status );

		// Optional name / email search.
		$search_join  = '';
		$search_where = '';
		if ( '' !== $search ) {
			$like         = '%' . $wpdb->esc_like( $search ) . '%';
			$search_join  = "LEFT JOIN {$wpdb->postmeta} pm_sname  ON pm_sname.post_id  = p.ID AND pm_sname.meta_key  = 'dish_customer_name'
			                 LEFT JOIN {$wpdb->postmeta} pm_semail ON pm_semail.post_id = p.ID AND pm_semail.meta_key = 'dish_customer_email'";
			$search_where = $wpdb->prepare( 'AND ( pm_sname.meta_value LIKE %s OR pm_semail.meta_value LIKE %s )', $like, $like );
		}

		$offset = max( 0, ( $page - 1 ) * $per_page );

		// All clauses in $where and $search_where are pre-escaped via inner prepare() calls.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			"SELECT COUNT( DISTINCT p.ID )
			   FROM {$wpdb->posts} p
			   {$search_join}
			  WHERE p.post_type = 'dish_booking'
			    {$where}
			    {$search_where}"
		);

		$limit_int  = (int) $per_page;
		$offset_int = (int) $offset;
		$raw = $wpdb->get_results(
			"SELECT
			      p.ID,
			      p.post_status,
			      p.post_date,
			      pm_name.meta_value   AS customer_name,
			      pm_email.meta_value  AS customer_email,
			      pm_phone.meta_value  AS customer_phone,
			      pm_class.meta_value  AS class_id,
			      pm_qty.meta_value    AS ticket_qty,
			      pm_total.meta_value  AS total_cents,
			      pm_tid.meta_value    AS transaction_id,
			      pm_gw.meta_value     AS gateway
			   FROM {$wpdb->posts} p
			   LEFT JOIN {$wpdb->postmeta} pm_name  ON pm_name.post_id  = p.ID AND pm_name.meta_key  = 'dish_customer_name'
			   LEFT JOIN {$wpdb->postmeta} pm_email ON pm_email.post_id = p.ID AND pm_email.meta_key = 'dish_customer_email'
			   LEFT JOIN {$wpdb->postmeta} pm_phone ON pm_phone.post_id = p.ID AND pm_phone.meta_key = 'dish_customer_phone'
			   LEFT JOIN {$wpdb->postmeta} pm_class ON pm_class.post_id = p.ID AND pm_class.meta_key = 'dish_class_id'
			   LEFT JOIN {$wpdb->postmeta} pm_qty   ON pm_qty.post_id   = p.ID AND pm_qty.meta_key   = 'dish_ticket_qty'
			   LEFT JOIN {$wpdb->postmeta} pm_total ON pm_total.post_id = p.ID AND pm_total.meta_key = 'dish_ticket_total_cents'
			   LEFT JOIN {$wpdb->postmeta} pm_tid   ON pm_tid.post_id   = p.ID AND pm_tid.meta_key   = 'dish_transaction_id'
			   LEFT JOIN {$wpdb->postmeta} pm_gw    ON pm_gw.post_id    = p.ID AND pm_gw.meta_key    = 'dish_gateway'
			   {$search_join}
			  WHERE p.post_type = 'dish_booking'
			    {$where}
			    {$search_where}
			  ORDER BY p.post_date DESC
			  LIMIT {$limit_int} OFFSET {$offset_int}",
			ARRAY_A
		);
		// phpcs:enable

		return [
			'rows'  => is_array( $raw ) ? $raw : [],
			'total' => $total,
		];
	}

	// -------------------------------------------------------------------------
	// Revenue breakdown (Payments tab)
	// -------------------------------------------------------------------------

	/**
	 * Revenue grouped by class template, for the Revenue tab.
	 *
	 * Returns rows sorted by total revenue descending.
	 *
	 * @param string $date_from
	 * @param string $date_to
	 * @return array<int, array{class_id:string, class_title:string, bookings:string, tickets:string, revenue:string}>
	 */
	public static function get_revenue_by_class(
		string $date_from = '',
		string $date_to   = ''
	): array {
		global $wpdb;

		$where = self::build_date_status_where( $date_from, $date_to );

		// $where is built from individual $wpdb->prepare() calls — no further prepare() needed.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT
			      pm_class.meta_value                          AS class_id,
			      COUNT( DISTINCT p.ID )                       AS bookings,
			      COALESCE( SUM( pm_qty.meta_value   + 0 ), 0) AS tickets,
			      COALESCE( SUM( pm_total.meta_value + 0 ), 0) AS revenue
			   FROM {$wpdb->posts} p
			   JOIN {$wpdb->postmeta} pm_class ON pm_class.post_id = p.ID AND pm_class.meta_key = 'dish_class_id'
			   JOIN {$wpdb->postmeta} pm_total ON pm_total.post_id = p.ID AND pm_total.meta_key = 'dish_ticket_total_cents'
			   JOIN {$wpdb->postmeta} pm_qty   ON pm_qty.post_id   = p.ID AND pm_qty.meta_key   = 'dish_ticket_qty'
			  WHERE p.post_type = 'dish_booking'
			    {$where}
			  GROUP BY pm_class.meta_value
			  ORDER BY revenue DESC
			  LIMIT 100",
			ARRAY_A
		);
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return [];
		}

		// Resolve class titles (template title preferred).
		foreach ( $rows as &$row ) {
			$class_id    = (int) $row['class_id'];
			$template_id = $class_id ? (int) get_post_meta( $class_id, 'dish_template_id', true ) : 0;
			$title       = $template_id ? get_the_title( $template_id ) : ( $class_id ? get_the_title( $class_id ) : '' );
			$row['class_title'] = $title ?: sprintf( __( 'Class #%d', 'dish-events' ), $class_id );
		}
		unset( $row );

		return $rows;
	}

	// -------------------------------------------------------------------------
	// Attendees (Attendees tab)
	// -------------------------------------------------------------------------

	/**
	 * All bookings for a specific class instance, with attendee detail.
	 *
	 * @param int $class_id dish_class post ID.
	 * @return array<int, array<string, string>>
	 */
	public static function get_attendees_for_class( int $class_id ): array {
		global $wpdb;

		$statuses    = self::ACTIVE;
		$status_in   = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				      p.ID,
				      p.post_status,
				      p.post_date,
				      pm_name.meta_value   AS customer_name,
				      pm_email.meta_value  AS customer_email,
				      pm_phone.meta_value  AS customer_phone,
				      pm_qty.meta_value    AS ticket_qty,
				      pm_total.meta_value  AS total_cents,
				      pm_att.meta_value    AS attendees_json
				   FROM {$wpdb->posts} p
				   JOIN {$wpdb->postmeta} pm_class ON pm_class.post_id = p.ID AND pm_class.meta_key = 'dish_class_id' AND pm_class.meta_value = %d
				   LEFT JOIN {$wpdb->postmeta} pm_name  ON pm_name.post_id  = p.ID AND pm_name.meta_key  = 'dish_customer_name'
				   LEFT JOIN {$wpdb->postmeta} pm_email ON pm_email.post_id = p.ID AND pm_email.meta_key = 'dish_customer_email'
				   LEFT JOIN {$wpdb->postmeta} pm_phone ON pm_phone.post_id = p.ID AND pm_phone.meta_key = 'dish_customer_phone'
				   LEFT JOIN {$wpdb->postmeta} pm_qty   ON pm_qty.post_id   = p.ID AND pm_qty.meta_key   = 'dish_ticket_qty'
				   LEFT JOIN {$wpdb->postmeta} pm_total ON pm_total.post_id = p.ID AND pm_total.meta_key = 'dish_ticket_total_cents'
				   LEFT JOIN {$wpdb->postmeta} pm_att   ON pm_att.post_id   = p.ID AND pm_att.meta_key   = 'dish_attendees'
				  WHERE p.post_type   = 'dish_booking'
				    AND p.post_status IN ({$status_in})
				  ORDER BY p.post_date ASC",
				array_merge( [ $class_id ], $statuses )
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : [];
	}

	// -------------------------------------------------------------------------
	// CSV export
	// -------------------------------------------------------------------------

	/**
	 * Build a 2-D array suitable for fputcsv export — all bookings (global).
	 *
	 * Row 0 is the header row.
	 *
	 * @param string $date_from
	 * @param string $date_to
	 * @param string $status
	 * @param string $search
	 * @return array<int, array<int, string>>
	 */
	public static function export_bookings_csv(
		string $date_from = '',
		string $date_to   = '',
		string $status    = '',
		string $search    = ''
	): array {
		// Re-use the list query but fetch everything (no pagination).
		$result = self::get_bookings_list( $date_from, $date_to, $status, $search, 99999, 1 );
		$rows   = $result['rows'];

		$out = [ [
			__( 'Booking ID',   'dish-events' ),
			__( 'Date',         'dish-events' ),
			__( 'Status',       'dish-events' ),
			__( 'Class',        'dish-events' ),
			__( 'Name',         'dish-events' ),
			__( 'Email',        'dish-events' ),
			__( 'Phone',        'dish-events' ),
			__( 'Tickets',      'dish-events' ),
			__( 'Total ($)',     'dish-events' ),
			__( 'Transaction',  'dish-events' ),
			__( 'Gateway',      'dish-events' ),
		] ];

		foreach ( $rows as $row ) {
			$class_id    = (int) $row['class_id'];
			$template_id = $class_id ? (int) get_post_meta( $class_id, 'dish_template_id', true ) : 0;
			$class_title = $template_id ? get_the_title( $template_id ) : ( $class_id ? get_the_title( $class_id ) : '' );

			$out[] = [
				(string) $row['ID'],
				(string) $row['post_date'],
				(string) $row['post_status'],
				$class_title,
				(string) $row['customer_name'],
				(string) $row['customer_email'],
				(string) $row['customer_phone'],
				(string) $row['ticket_qty'],
				number_format( (int) $row['total_cents'] / 100, 2 ),
				(string) $row['transaction_id'],
				(string) $row['gateway'],
			];
		}

		return $out;
	}

	/**
	 * Build a 2-D CSV array for all attendees of a specific class.
	 *
	 * Row 0 is the header. Each attendee within a multi-ticket booking gets its
	 * own row; the primary customer is row 1 for each booking.
	 *
	 * @param int $class_id
	 * @return array<int, array<int, string>>
	 */
	public static function export_attendees_csv( int $class_id ): array {
		$bookings = self::get_attendees_for_class( $class_id );

		$out = [ [
			__( 'Booking ID',    'dish-events' ),
			__( 'Attendee #',    'dish-events' ),
			__( 'Name',          'dish-events' ),
			__( 'Email',         'dish-events' ),
			__( 'Phone',         'dish-events' ),
			__( 'Status',        'dish-events' ),
			__( 'Booked On',     'dish-events' ),
		] ];

		foreach ( $bookings as $bk ) {
			$attendees = json_decode( (string) ( $bk['attendees_json'] ?? '[]' ), true );

			// Row 1: primary customer (seat #1).
			$out[] = [
				(string) $bk['ID'],
				'1',
				(string) $bk['customer_name'],
				(string) $bk['customer_email'],
				(string) $bk['customer_phone'],
				(string) $bk['post_status'],
				(string) $bk['post_date'],
			];

			// Subsequent rows: additional attendees from the attendees JSON.
			if ( is_array( $attendees ) ) {
				foreach ( $attendees as $i => $att ) {
					// Skip index 0 — that represents the primary customer already added.
					if ( 0 === $i ) continue;
					$out[] = [
						(string) $bk['ID'],
						(string) ( $i + 1 ),
						(string) ( $att['name']  ?? '' ),
						(string) ( $att['email'] ?? '' ),
						'',
						(string) $bk['post_status'],
						(string) $bk['post_date'],
					];
				}
			}
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a WHERE fragment for date-range and status filtering.
	 *
	 * Each clause is individually prepared via $wpdb->prepare() so the returned
	 * string is safe to interpolate directly into a query string.
	 *
	 * @param string $date_from  Y-m-d or ''.
	 * @param string $date_to    Y-m-d or ''.
	 * @param string $status     Specific status slug or '' for all active.
	 * @return string  Pre-escaped WHERE clauses (no leading WHERE keyword).
	 */
	private static function build_date_status_where(
		string $date_from,
		string $date_to,
		string $status = ''
	): string {
		global $wpdb;

		$clauses = [];

		// Status filter.
		if ( '' !== $status && str_starts_with( $status, 'dish_' ) ) {
			$clauses[] = $wpdb->prepare( 'AND p.post_status = %s', $status );
		} else {
			$statuses  = self::ACTIVE;
			$in        = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
			$clauses[] = $wpdb->prepare( "AND p.post_status IN ({$in})", ...$statuses ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Date bounds — post_date is stored in site-local time.
		if ( '' !== $date_from ) {
			$clauses[] = $wpdb->prepare( 'AND DATE( p.post_date ) >= %s', $date_from );
		}
		if ( '' !== $date_to ) {
			$clauses[] = $wpdb->prepare( 'AND DATE( p.post_date ) <= %s', $date_to );
		}

		return implode( ' ', $clauses );
	}

	/**
	 * Number of calendar days covered by the given date range.
	 * Falls back to "days since first booking" when bounds are empty.
	 *
	 * @param string $date_from
	 * @param string $date_to
	 * @return int  At least 1 to avoid division-by-zero.
	 */
	private static function count_days( string $date_from, string $date_to ): int {
		$tz = new \DateTimeZone( wp_timezone_string() );

		if ( '' !== $date_from && '' !== $date_to ) {
			$from = new \DateTimeImmutable( $date_from, $tz );
			$to   = new \DateTimeImmutable( $date_to,   $tz );
			return max( 1, (int) $from->diff( $to )->days + 1 );
		}

		// Unbounded — use days since the earliest booking post.
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$oldest = $wpdb->get_var(
			"SELECT MIN( post_date ) FROM {$wpdb->posts} WHERE post_type = 'dish_booking'"
		);
		// phpcs:enable

		if ( ! $oldest ) {
			return 1;
		}

		$from = new \DateTimeImmutable( $oldest, $tz );
		$to   = new \DateTimeImmutable( 'now',   $tz );

		return max( 1, (int) $from->diff( $to )->days + 1 );
	}
}
