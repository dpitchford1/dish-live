<?php
/**
 * Read-only data access for dish_format posts.
 *
 * @package Dish\Events\Data
 */

declare( strict_types=1 );

namespace Dish\Events\Data;

/**
 * Class FormatRepository
 *
 * Stateless read-only repository for dish_format posts.
 * All business logic lives in callers; this class only retrieves data.
 */
final class FormatRepository {

	/**
	 * Return a single meta value for a dish_format post.
	 *
	 * Returns $default when the stored value is an empty string or boolean false
	 * (i.e. meta key does not exist).
	 *
	 * @param int    $post_id dish_format post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $default Fallback value when meta is empty.
	 * @return mixed
	 */
	public static function get_meta( int $post_id, string $key, mixed $default = '' ): mixed {
		$value = get_post_meta( $post_id, $key, true );
		return ( '' !== $value && false !== $value ) ? $value : $default;
	}

	/**
	 * Return all published dish_format posts ordered by menu_order then title.
	 *
	 * @return \WP_Post[]
	 */
	public static function get_all_published(): array {
		$posts = get_posts( [
			'post_type'      => 'dish_format',
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
		] );

		return is_array( $posts ) ? $posts : [];
	}
}
