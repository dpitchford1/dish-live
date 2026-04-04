<?php
/**
 * Chef repository.
 *
 * Reads dish_chef posts and their meta. No business logic.
 *
 * Meta keys read:
 *   dish_chef_role        str
 *   dish_chef_website     str
 *   dish_chef_instagram   str
 *   dish_chef_linkedin    str
 *   dish_chef_tiktok      str
 *   dish_chef_gallery_ids json  int[]
 *
 * @package Dish\Events\Data
 */

declare( strict_types=1 );

namespace Dish\Events\Data;

use WP_Post;
use WP_Query;

/**
 * Class ChefRepository
 */
final class ChefRepository {

	// -------------------------------------------------------------------------
	// Single record
	// -------------------------------------------------------------------------

	/**
	 * Fetch a single dish_chef post by ID.
	 *
	 * @param int $post_id
	 * @return WP_Post|null
	 */
	public static function get( int $post_id ): ?WP_Post {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'dish_chef' !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	// -------------------------------------------------------------------------
	// Collections
	// -------------------------------------------------------------------------

	/**
	 * Flexible query returning an array of dish_chef WP_Post objects.
	 *
	 * @param array{
	 *   status?:  string|string[],
	 *   orderby?: string,
	 *   order?:   string,
	 *   limit?:   int,
	 * } $args
	 * @return WP_Post[]
	 */
	public static function query( array $args = [] ): array {
		$query_args = [
			'post_type'      => 'dish_chef',
			'post_status'    => $args['status']  ?? 'publish',
			'posts_per_page' => $args['limit']   ?? -1,
			'orderby'        => $args['orderby'] ?? 'title',
			'order'          => $args['order']   ?? 'ASC',
			'no_found_rows'  => true,
		];

		if ( ! empty( $args['exclude_team'] ) ) {
			// Chefs only — exclude anyone flagged as a team member.
			$query_args['meta_query'] = [
				'relation' => 'OR',
				[ 'key' => 'dish_is_team_member', 'compare' => 'NOT EXISTS' ],
				[ 'key' => 'dish_is_team_member', 'value' => '1', 'compare' => '!=' ],
			];
		} elseif ( ! empty( $args['team_only'] ) ) {
			// Team section — only flagged members.
			$query_args['meta_query'] = [
				[ 'key' => 'dish_is_team_member', 'value' => '1' ],
			];
		}

		$q = new WP_Query( $query_args );

		return $q->posts;
	}

	/**
	 * Return chefs associated with a class instance.
	 *
	 * Fetches IDs via ClassRepository then bulk-loads the posts.
	 *
	 * @param int $class_post_id dish_class post ID.
	 * @return WP_Post[]
	 */
	public static function get_for_class( int $class_post_id ): array {
		$ids = ClassRepository::get_chef_ids( $class_post_id );

		if ( empty( $ids ) ) {
			return [];
		}

		$q = new WP_Query( [
			'post_type'      => 'dish_chef',
			'post_status'    => 'publish',
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		] );

		return $q->posts;
	}

	// -------------------------------------------------------------------------
	// Meta
	// -------------------------------------------------------------------------

	/**
	 * Read a single meta value for a chef post.
	 *
	 * @param int    $post_id Chef post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $default Value when meta is absent or empty string.
	 * @return mixed
	 */
	public static function get_meta( int $post_id, string $key, mixed $default = '' ): mixed {
		$value = get_post_meta( $post_id, $key, true );
		return ( '' === $value || false === $value ) ? $default : $value;
	}
}
