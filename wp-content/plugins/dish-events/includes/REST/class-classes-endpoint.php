<?php
/**
 * REST endpoint: GET /wp-json/dish/v1/classes
 *
 * Returns an array of FullCalendar-compatible event objects for all published
 * dish_class instances within the requested date range.
 *
 * Query parameters:
 *   start       ISO 8601 date/datetime string. Inclusive lower bound on dish_start_datetime.
 *   end         ISO 8601 date/datetime string. Exclusive upper bound on dish_start_datetime.
 *   format_id   integer. When supplied, returns only instances belonging to this dish_format.
 *
 * Response shape per event:
 * {
 *   "id":              int,
 *   "title":           string,           // "Private Event" when dish_is_private
 *   "start":           string|null,      // ISO 8601 UTC  e.g. "2026-04-18T14:00:00Z"
 *   "end":             string|null,
 *   "url":             string|null,      // null for private events
 *   "backgroundColor": string,           // format colour hex
 *   "borderColor":     string,
 *   "extendedProps": {
 *     "is_private":      bool,
 *     "format":          { id, title, color } | null,
 *     "spots_remaining": int | null       // null when no capacity set
 *   }
 * }
 *
 * Caching: responses are cached in the WP object cache for 5 minutes per
 * unique start/end/format_id combination. The cache group is 'dish_events'.
 *
 * @package Dish\Events\REST
 */

declare( strict_types=1 );

namespace Dish\Events\REST;

use Dish\Events\Core\Loader;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Admin\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class ClassesEndpoint
 */
final class ClassesEndpoint {

	/** Default format colour when none is set. */
	private const DEFAULT_COLOR = '#c0392b';

	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'rest_api_init', $this, 'register_routes' );
	}

	// -------------------------------------------------------------------------
	// Route registration
	// -------------------------------------------------------------------------

	/**
	 * Register the REST route under dish/v1.
	 */
	public function register_routes(): void {
		register_rest_route(
			'dish/v1',
			'/classes',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'start'     => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'end'       => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'format_id' => [
						'type'    => 'integer',
						'default' => 0,
					],
				],
			]
		);

		register_rest_route(
			'dish/v1',
			'/ping',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'ping' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	// -------------------------------------------------------------------------
	// Endpoint handlers
	// -------------------------------------------------------------------------

	/**
	 * Health-check endpoint. Returns {"status":"ok"}.
	 *
	 * GET /wp-json/dish/v1/ping
	 *
	 * @return WP_REST_Response
	 */
	public function ping(): WP_REST_Response {
		return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
	}

	/**
	 * Return FullCalendar event objects for the requested date range.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$start_str = (string) $request->get_param( 'start' );
		$end_str   = (string) $request->get_param( 'end' );
		$format_id = (int) $request->get_param( 'format_id' );

		// ── Cache ─────────────────────────────────────────────────────────────
		$cache_key = 'dish_classes_feed_' . md5( $start_str . '_' . $end_str . '_' . $format_id );
		$cached    = wp_cache_get( $cache_key, 'dish_events' );

		if ( false !== $cached ) {
			return new WP_REST_Response( $cached, 200 );
		}

		// ── Build meta_query ──────────────────────────────────────────────────
		$meta_query = [
			'relation' => 'AND',
			[
				'key'     => 'dish_start_datetime',
				'compare' => 'EXISTS',
			],
		];

		if ( $start_str !== '' ) {
			$start_ts = strtotime( $start_str );
			if ( $start_ts ) {
				$meta_query[] = [
					'key'     => 'dish_start_datetime',
					'value'   => $start_ts,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				];
			}
		}

		if ( $end_str !== '' ) {
			$end_ts = strtotime( $end_str );
			if ( $end_ts ) {
				$meta_query[] = [
					'key'     => 'dish_start_datetime',
					'value'   => $end_ts,
					'compare' => '<',
					'type'    => 'NUMERIC',
				];
			}
		}

		// ── Format filter — resolve template IDs for this format ──────────────
		if ( $format_id > 0 ) {
			$template_ids = get_posts( [
				'post_type'      => 'dish_class_template',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => 'dish_format_id',
						'value' => $format_id,
						'type'  => 'NUMERIC',
					],
				],
			] );

			if ( empty( $template_ids ) ) {
				wp_cache_set( $cache_key, [], 'dish_events', 5 * MINUTE_IN_SECONDS );
				return new WP_REST_Response( [], 200 );
			}

			$meta_query[] = [
				'key'     => 'dish_template_id',
				'value'   => $template_ids,
				'compare' => 'IN',
				'type'    => 'NUMERIC',
			];
		}

		// ── Fetch instances ───────────────────────────────────────────────────
		$posts = get_posts( [
			'post_type'      => 'dish_class',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'meta_key'       => 'dish_start_datetime',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => $meta_query,
		] );

		if ( empty( $posts ) ) {
			wp_cache_set( $cache_key, [], 'dish_events', 5 * MINUTE_IN_SECONDS );
			return new WP_REST_Response( [], 200 );
		}

		// ── Batch-load related data ───────────────────────────────────────────

		// 1. Template IDs from each class instance.
		$tpl_ids = array_values( array_unique( array_filter( array_map(
			static fn( $p ) => (int) get_post_meta( $p->ID, 'dish_template_id', true ),
			$posts
		) ) ) );

		// 2. Load template posts + their format/ticket meta.
		$templates = []; // [ template_id => [ 'post', 'format_id', 'ticket_id' ] ]
		if ( ! empty( $tpl_ids ) ) {
			$tpl_posts = get_posts( [
				'post_type'      => 'dish_class_template',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'post__in'       => $tpl_ids,
			] );
			foreach ( $tpl_posts as $tp ) {
				$templates[ $tp->ID ] = [
					'post'         => $tp,
					'format_id'    => (int) get_post_meta( $tp->ID, 'dish_format_id',      true ),
					'ticket_id'    => (int) get_post_meta( $tp->ID, 'dish_ticket_type_id', true ),
					'booking_type' => (string) get_post_meta( $tp->ID, 'dish_booking_type', true ) ?: 'online',
				];
			}
		}

		// 3. Load format colours and private flag.
		$fmt_ids = array_values( array_unique( array_filter( array_column( $templates, 'format_id' ) ) ) );
		$formats = []; // [ format_id => [ 'id', 'title', 'color', 'is_private' ] ]
		foreach ( $fmt_ids as $fid ) {
			$fp = get_post( $fid );
			$formats[ $fid ] = [
				'id'         => $fid,
				'title'      => $fp ? $fp->post_title : '',
				'color'      => (string) get_post_meta( $fid, 'dish_format_color', true ) ?: self::DEFAULT_COLOR,
				'is_private' => (bool) get_post_meta( $fid, 'dish_format_is_private', true ),
			];
		}

		// 4. Load ticket capacities in a single DB query.
		$tkt_ids = array_values( array_unique( array_filter( array_column( $templates, 'ticket_id' ) ) ) );
		$tickets = []; // [ ticket_id => stdClass ]
		if ( ! empty( $tkt_ids ) ) {
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $tkt_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT id, capacity, price_cents FROM {$wpdb->prefix}dish_ticket_types WHERE id IN ($placeholders)", ...$tkt_ids )
			);
			foreach ( $rows as $row ) {
				$tickets[ (int) $row->id ] = $row;
			}
		}

		// 5. Batch-load booked counts — one query for all class IDs.
		$class_ids     = array_map( static fn( $p ) => $p->ID, $posts );
		$booked_counts = ClassRepository::get_booked_counts_batch( $class_ids );

		// 6. Booking page base URL (used for direct-to-checkout links).
		$booking_page_id  = (int) Settings::get( 'booking_page', 0 );
		$booking_page_url = $booking_page_id ? (string) get_permalink( $booking_page_id ) : '';

			// 7. Enquiry page URL (used for by-request classes).
			$enquiry_page_id  = (int) Settings::get( 'enquiry_page', 0 );
			$enquiry_page_url = $enquiry_page_id
				? (string) get_permalink( $enquiry_page_id )
				: 'mailto:' . Settings::get( 'studio_email', (string) get_bloginfo( 'admin_email' ) );

		$site_tz = wp_timezone();

		foreach ( $posts as $post ) {
			$start_ts   = (int) get_post_meta( $post->ID, 'dish_start_datetime', true );
			$end_ts     = (int) get_post_meta( $post->ID, 'dish_end_datetime',   true );

			$tpl_id  = (int) get_post_meta( $post->ID, 'dish_template_id', true );
			$tpl     = $templates[ $tpl_id ] ?? null;
			$fmt_id  = $tpl ? $tpl['format_id'] : 0;
			$format  = $formats[ $fmt_id ] ?? null;
			$tkt_id  = $tpl ? $tpl['ticket_id'] : 0;
			$ticket  = $tickets[ $tkt_id ] ?? null;

			$is_private = (bool) get_post_meta( $post->ID, 'dish_is_private', true )
						|| get_post_meta( $post->ID, 'dish_class_type', true ) === 'private'
						|| ( $format && ! empty( $format['is_private'] ) );

			$title = $tpl && $tpl['post'] ? $tpl['post']->post_title : $post->post_title;

			$capacity  = $ticket ? (int) $ticket->capacity : 0;
			$booked    = $booked_counts[ $post->ID ] ?? 0;
			$remaining = $capacity > 0 ? max( 0, $capacity - $booked ) : null;

			$color = $format ? $format['color'] : self::DEFAULT_COLOR;

			// Output timestamps with site timezone offset so FullCalendar
			// displays the correct local time regardless of browser timezone.
			$fmt_ts = static function ( int $ts ) use ( $site_tz ): ?string {
				if ( $ts <= 0 ) { return null; }
				return ( new \DateTimeImmutable( '@' . $ts ) )
					->setTimezone( $site_tz )
					->format( 'c' );
			};

			// Use the template's public permalink + class_id so the template page
			// can surface specific date/spots and a direct Book Now button.
			$tpl_post  = $tpl['post'] ?? null;
			$event_url = ( ! $is_private && $tpl_post )
				? add_query_arg( 'class_id', $post->ID, get_permalink( $tpl_post->ID ) )
				: '';

			$events[] = [
				'id'              => $post->ID,
				'title'           => $title,
				'start'           => $fmt_ts( $start_ts ),
				'end'             => $fmt_ts( $end_ts ),
				'backgroundColor' => $color,
				'borderColor'     => $color,
				'extendedProps'   => [
					'is_private'      => $is_private,				'is_past'         => $start_ts > 0 && $start_ts < time(),					'format'          => $format,
					'spots_remaining' => $remaining,
					'price_cents'     => $ticket ? (int) $ticket->price_cents : 0,
					'booking_url'     => ( ! $is_private && $booking_page_url && ( $tpl['booking_type'] ?? 'online' ) !== 'enquiry' )
						? add_query_arg( 'class_id', $post->ID, $booking_page_url )
						: '',
					'booking_type'    => $tpl['booking_type'] ?? 'online',
					'enquiry_url'     => $enquiry_page_url,
					'thumbnail_url'   => $tpl_post ? ( get_the_post_thumbnail_url( $tpl_post->ID, 'medium' ) ?: '' ) : '',
					'detail_url'      => $event_url,
				],
			];
		}

		wp_cache_set( $cache_key, $events, 'dish_events', 5 * MINUTE_IN_SECONDS );

		return new WP_REST_Response( $events, 200 );
	}

}
