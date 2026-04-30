<?php
/**
 * Class instance repository.
 *
 * Reads dish_class posts and their associated meta. All queries go through
 * WP_Query (for post data) or $wpdb->prepare() (for raw meta joins). No
 * business logic lives here — only data retrieval and transformation to
 * plain arrays / WP_Post objects.
 *
 * Meta keys read:
 *   dish_start_datetime   int   UTC epoch
 *   dish_end_datetime     int   UTC epoch
 *   dish_template_id      int   dish_class_template post ID
 *   dish_chef_ids         json  int[]
 *   dish_is_private       int   0|1
 *   dish_class_type       str   'public'|'private'|'corporate'
 *   dish_admin_notes      str
 *
 * @package Dish\Events\Data
 */

declare( strict_types=1 );

namespace Dish\Events\Data;

use WP_Post;
use WP_Query;

/**
 * Class ClassRepository
 */
final class ClassRepository {

	// -------------------------------------------------------------------------
	// Single record
	// -------------------------------------------------------------------------

	/**
	 * Fetch a single dish_class post by ID.
	 *
	 * @param int $post_id
	 * @return WP_Post|null  Null if not found or wrong post type.
	 */
	public static function get( int $post_id ): ?WP_Post {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'dish_class' !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	// -------------------------------------------------------------------------
	// Collections
	// -------------------------------------------------------------------------

	/**
	 * Flexible query returning an array of dish_class WP_Post objects.
	 *
	 * @param array{
	 *   status?:      string|string[],
	 *   template_id?: int,
	 *   start_after?: int,
	 *   start_before?: int,
	 *   is_private?:  bool,
	 *   orderby?:     string,
	 *   order?:       string,
	 *   limit?:       int,
	 *   offset?:      int,
	 * } $args
	 * @return WP_Post[]
	 */
	public static function query( array $args = [] ): array {
		$query_args = [
			'post_type'      => 'dish_class',
			'post_status'    => $args['status'] ?? 'publish',
			'posts_per_page' => $args['limit']  ?? -1,
			'offset'         => $args['offset'] ?? 0,
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'dish_start_datetime',
			'order'          => $args['order']  ?? 'ASC',
			'no_found_rows'  => true,
		];

		$meta_query = [];

		if ( isset( $args['template_id'] ) ) {
			$meta_query[] = [
				'key'     => 'dish_template_id',
				'value'   => $args['template_id'],
				'compare' => '=',
				'type'    => 'NUMERIC',
			];
		}

		if ( isset( $args['template_ids'] ) && is_array( $args['template_ids'] ) && ! empty( $args['template_ids'] ) ) {
			$meta_query[] = [
				'key'     => 'dish_template_id',
				'value'   => array_map( 'absint', $args['template_ids'] ),
				'compare' => 'IN',
				'type'    => 'NUMERIC',
			];
		}

		if ( isset( $args['start_after'] ) ) {
			$meta_query[] = [
				'key'     => 'dish_start_datetime',
				'value'   => $args['start_after'],
				'compare' => '>',
				'type'    => 'NUMERIC',
			];
		}

		if ( isset( $args['start_before'] ) ) {
			$meta_query[] = [
				'key'     => 'dish_start_datetime',
				'value'   => $args['start_before'],
				'compare' => '<',
				'type'    => 'NUMERIC',
			];
		}

		if ( isset( $args['is_private'] ) ) {
			if ( $args['is_private'] ) {
				// Explicitly private only.
				$meta_query[] = [
					'key'     => 'dish_is_private',
					'value'   => '1',
					'compare' => '=',
				];
			} else {
				// Exclude private: value is '0' OR the key is not set at all.
				$meta_query[] = [
					'relation' => 'OR',
					[
						'key'     => 'dish_is_private',
						'value'   => '1',
						'compare' => '!=',
					],
					[
						'key'     => 'dish_is_private',
						'compare' => 'NOT EXISTS',
					],
				];
			}
		}

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		$q = new WP_Query( $query_args );

		return $q->posts;
	}

	/**
	 * Return the next $limit upcoming class instances (start time > now).
	 *
	 * @param int $limit Maximum number of results. Default 10.
	 * @return WP_Post[]
	 */
	public static function get_upcoming( int $limit = 10 ): array {
		return self::query( [
			'start_after' => time(),
			'limit'       => $limit,
			'order'       => 'ASC',
		] );
	}

	/**
	 * Return the most recently created class instances, ordered by post date
	 * descending. Useful for selectors and reports dropdowns.
	 *
	 * @param int      $limit    Maximum number of results. Default 200.
	 * @param string[] $statuses Post statuses to include. Default publish + future.
	 * @return WP_Post[]
	 */
	public static function get_recent( int $limit = 200, array $statuses = [ 'publish', 'future' ] ): array {
		$posts = get_posts( [
			'post_type'      => 'dish_class',
			'post_status'    => $statuses,
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		return is_array( $posts ) ? $posts : [];
	}

	/**
	 * Return all instances belonging to a given template.
	 *
	 * @param int    $template_id  dish_class_template post ID.
	 * @param string $status       Post status. Default 'publish'.
	 * @return WP_Post[]
	 */
	public static function get_by_template( int $template_id, string $status = 'publish' ): array {
		return self::query( [
			'template_id' => $template_id,
			'status'      => $status,
		] );
	}

	// -------------------------------------------------------------------------
	// Meta helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the chef post IDs associated with a class instance.
	 *
	 * @param int $post_id dish_class post ID.
	 * @return int[]
	 */
	public static function get_chef_ids( int $post_id ): array {
		$raw = get_post_meta( $post_id, 'dish_chef_ids', true );

		if ( empty( $raw ) ) {
			return [];
		}

		$ids = json_decode( (string) $raw, true );

		return is_array( $ids ) ? array_values( array_map( 'absint', $ids ) ) : [];
	}

	/**
	 * Count confirmed bookings (non-cancelled, non-failed) for a class instance.
	 *
	 * Uses a direct $wpdb query against post_status + postmeta rather than
	 * a full WP_Query, keeping it lightweight for list-table column display.
	 *
	 * @param int $post_id dish_class post ID.
	 * @return int
	 */
	public static function get_booked_count( int $post_id ): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE( SUM( qty.meta_value + 0 ), 0 )
				   FROM {$wpdb->posts} p
				   JOIN {$wpdb->postmeta} pm
				        ON pm.post_id = p.ID
				        AND pm.meta_key = 'dish_class_id'
				        AND pm.meta_value = %d
				   JOIN {$wpdb->postmeta} qty
				        ON qty.post_id = p.ID
				        AND qty.meta_key = 'dish_ticket_qty'
				  WHERE p.post_type   = 'dish_booking'
				    AND p.post_status NOT IN ('dish_cancelled', 'dish_failed', 'trash')",
				$post_id
			)
		);

		return (int) $count;
	}

	/**
	 * Count confirmed bookings for a set of class IDs in a single DB query.
	 *
	 * Used by archive views to avoid N+1 queries when rendering many cards.
	 *
	 * @param  int[] $class_ids
	 * @return array<int,int>  Maps class_id → booked count. All IDs initialised to 0.
	 */
	public static function get_booked_counts_batch( array $class_ids ): array {
		if ( empty( $class_ids ) ) {
			return [];
		}

		global $wpdb;

		$class_ids    = array_values( array_map( 'absint', $class_ids ) );
		$placeholders = implode( ',', array_fill( 0, count( $class_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS class_id, COALESCE( SUM( qty.meta_value + 0 ), 0 ) AS booked
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm
					 ON p.ID = pm.post_id AND pm.meta_key = 'dish_class_id'
				 INNER JOIN {$wpdb->postmeta} qty
					 ON p.ID = qty.post_id AND qty.meta_key = 'dish_ticket_qty'
				 WHERE p.post_type   = 'dish_booking'
				   AND p.post_status NOT IN ('dish_cancelled','dish_failed','trash')
				   AND CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)
				 GROUP BY pm.meta_value",
				...$class_ids
			)
		);

		$counts = array_fill_keys( $class_ids, 0 );

		foreach ( $rows as $row ) {
			$counts[ (int) $row->class_id ] = (int) $row->booked;
		}

		return $counts;
	}

	// -------------------------------------------------------------------------
	// Meta accessors
	// -------------------------------------------------------------------------

	/**
	 * Read a single meta value for a dish_class post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $default Returned when the meta key is absent or empty.
	 * @return mixed
	 */
	public static function get_meta( int $post_id, string $key, mixed $default = '' ): mixed {
		$value = get_post_meta( $post_id, $key, true );
		return ( $value !== '' && $value !== false ) ? $value : $default;
	}

	/**
	 * Write a single meta value for a dish_class post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value to store.
	 * @return bool
	 */
	public static function set_meta( int $post_id, string $key, mixed $value ): bool {
		return (bool) update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Read all meta for a dish_class post (used by ClassColumns::duplicate()).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, array<int, mixed>>
	 */
	public static function get_all_meta( int $post_id ): array {
		$meta = get_post_meta( $post_id );
		return is_array( $meta ) ? $meta : [];
	}
}