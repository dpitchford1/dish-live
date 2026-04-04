<?php
/**
 * Class template repository.
 *
 * Reads dish_class_template posts and their associated meta + $wpdb rows.
 * No business logic — only data retrieval.
 *
 * Meta keys read:
 *   dish_ticket_type_id   int   FK → dish_ticket_types.id
 *   dish_format_id        int   FK → dish_format post ID
 *   dish_gallery_ids      json  int[]  attachment IDs
 *   dish_event_theme      str   template slug override
 *
 * @package Dish\Events\Data
 */

declare( strict_types=1 );

namespace Dish\Events\Data;

use WP_Post;
use WP_Query;

/**
 * Class ClassTemplateRepository
 */
final class ClassTemplateRepository {

	// -------------------------------------------------------------------------
	// Single record
	// -------------------------------------------------------------------------

	/**
	 * Fetch a single dish_class_template post by ID.
	 *
	 * @param int $post_id
	 * @return WP_Post|null
	 */
	public static function get( int $post_id ): ?WP_Post {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'dish_class_template' !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	// -------------------------------------------------------------------------
	// Collections
	// -------------------------------------------------------------------------

	/**
	 * Return all published class templates.
	 *
	 * @return WP_Post[]
	 */
	public static function get_active(): array {
		$q = new WP_Query( [
			'post_type'      => 'dish_class_template',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		] );

		return $q->posts;
	}

	/**
	 * Return published templates assigned to a specific dish_format post.
	 *
	 * @param int $format_id dish_format post ID.
	 * @return WP_Post[]
	 */
	public static function get_by_format( int $format_id ): array {
		$q = new WP_Query( [
			'post_type'      => 'dish_class_template',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'key'     => 'dish_format_id',
					'value'   => $format_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
		] );

		return $q->posts;
	}

	/**
	 * Return the next $limit upcoming class instances for a given template.
	 *
	 * Delegates to ClassRepository to avoid duplicating meta-query logic.
	 *
	 * @param int $template_id dish_class_template post ID.
	 * @param int $limit       Maximum number of results. Default 5.
	 * @return WP_Post[]
	 */
	public static function get_upcoming_instances( int $template_id, int $limit = 5 ): array {
		return ClassRepository::query( [
			'template_id' => $template_id,
			'start_after' => time(),
			'limit'       => $limit,
			'order'       => 'ASC',
		] );
	}

	// -------------------------------------------------------------------------
	// Meta helpers
	// -------------------------------------------------------------------------

	/**
	 * Fetch the ticket type row (from dish_ticket_types table) for a template.
	 *
	 * @param int $template_id dish_class_template post ID.
	 * @return object|null  stdClass row or null if not found / not set.
	 */
	public static function get_ticket_type( int $template_id ): ?object {
		$ticket_type_id = (int) get_post_meta( $template_id, 'dish_ticket_type_id', true );

		if ( $ticket_type_id <= 0 ) {
			return null;
		}

		return TicketTypeRepository::get( $ticket_type_id );
	}

	/**
	 * Return the gallery attachment IDs for a template.
	 *
	 * @param int $template_id dish_class_template post ID.
	 * @return int[]
	 */
	public static function get_gallery_ids( int $template_id ): array {
		$raw = get_post_meta( $template_id, 'dish_gallery_ids', true );

		if ( empty( $raw ) ) {
			return [];
		}

		$ids = json_decode( (string) $raw, true );

		return is_array( $ids ) ? array_values( array_map( 'absint', $ids ) ) : [];
	}

	/**
	 * Fetch ticket type rows for a set of template IDs in a single DB query.
	 *
	 * Used by archive views to avoid N+1 queries when rendering many cards.
	 *
	 * @param  int[] $template_ids  dish_class_template post IDs.
	 * @return array<int,object|null>  Maps template_id → stdClass ticket type row (or null).
	 */
	public static function get_ticket_types_batch( array $template_ids ): array {
		if ( empty( $template_ids ) ) {
			return [];
		}

		$template_ids = array_values( array_map( 'absint', $template_ids ) );

		// Resolve template_id → ticket_type_id using WP postmeta (cached per request).
		$ticket_type_ids = [];
		$map             = []; // ticket_type_id → template_id

		foreach ( $template_ids as $tid ) {
			$ttid = (int) get_post_meta( $tid, 'dish_ticket_type_id', true );
			if ( $ttid > 0 ) {
				$ticket_type_ids[] = $ttid;
				$map[ $ttid ]      = $tid;
			}
		}

		// Initialise all template IDs to null.
		$result = array_fill_keys( $template_ids, null );

		if ( empty( $ticket_type_ids ) ) {
			return $result;
		}

		global $wpdb;

		$ticket_type_ids = array_values( array_unique( $ticket_type_ids ) );
		$placeholders    = implode( ',', array_fill( 0, count( $ticket_type_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}dish_ticket_types WHERE id IN ($placeholders)",
				...$ticket_type_ids
			)
		);

		foreach ( $rows as $row ) {
			$tid = $map[ (int) $row->id ] ?? null;
			if ( $tid !== null ) {
				$result[ $tid ] = $row;
			}
		}

		return $result;
	}
}
