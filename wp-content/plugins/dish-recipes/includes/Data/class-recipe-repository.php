<?php
/**
 * Recipe data repository.
 *
 * Stateless static class — data retrieval only, no business logic.
 * All methods return WP_Post objects or arrays thereof.
 *
 * @package Dish\Recipes\Data
 */

declare( strict_types=1 );

namespace Dish\Recipes\Data;

/**
 * Class RecipeRepository
 */
final class RecipeRepository {

	// -------------------------------------------------------------------------
	// Single record
	// -------------------------------------------------------------------------

	/**
	 * Retrieve a single recipe post by ID.
	 *
	 * @param int $recipe_id Post ID of the dish_recipe.
	 * @return \WP_Post|null
	 */
	public static function get( int $recipe_id ): ?\WP_Post {
		$post = get_post( $recipe_id );

		if ( ! $post instanceof \WP_Post || 'dish_recipe' !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	// -------------------------------------------------------------------------
	// Collections
	// -------------------------------------------------------------------------

	/**
	 * Get all published recipes that reference a given class template ID.
	 *
	 * This is the single-source-of-truth lookup for the recipe ↔ class
	 * relationship. Relationships are stored on the recipe via
	 * dish_recipe_template_ids (JSON int array). dish-events stores nothing.
	 *
	 * @param int $template_id Post ID of the dish_class_template.
	 * @return \WP_Post[]
	 */
	public static function get_by_template_id( int $template_id ): array {
		$query = new \WP_Query( [
			'post_type'      => 'dish_recipe',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'key'     => 'dish_recipe_template_ids',
					'value'   => '"' . $template_id . '"',
					'compare' => 'LIKE', // IDs stored as JSON string array e.g. ["123","456"]
				],
			],
		] );

		return $query->posts;
	}

	/**
	 * Get published recipes filtered by dish_recipe_category term slug.
	 *
	 * @param string $category_slug Term slug.
	 * @param int    $limit         Max posts to return. -1 for all.
	 * @return \WP_Post[]
	 */
	public static function get_by_category( string $category_slug, int $limit = -1 ): array {
		$query = new \WP_Query( [
			'post_type'      => 'dish_recipe',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'tax_query'      => [
				[
					'taxonomy' => 'dish_recipe_category',
					'field'    => 'slug',
					'terms'    => $category_slug,
				],
			],
		] );

		return $query->posts;
	}

	/**
	 * Get the most recently published recipes.
	 *
	 * @param int $limit Max posts to return.
	 * @return \WP_Post[]
	 */
	public static function get_recent( int $limit = 6 ): array {
		$query = new \WP_Query( [
			'post_type'      => 'dish_recipe',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		] );

		return $query->posts;
	}

	/**
	 * Get recipes from the same category as a given recipe, excluding itself.
	 *
	 * Uses a deterministic offset derived from $recipe_id so the same recipe
	 * always surfaces the same suggestions — no rand() DB overhead, no cache
	 * busting on every page load.
	 *
	 * @param int $recipe_id  The current recipe post ID.
	 * @param int $limit      Number of recipes to return.
	 * @return \WP_Post[]
	 */
	public static function get_same_category( int $recipe_id, int $limit = 3 ): array {
		$terms = get_the_terms( $recipe_id, 'dish_recipe_category' );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return [];
		}

		$term_ids = wp_list_pluck( $terms, 'term_id' );

		$pool = ( new \WP_Query( [
			'post_type'      => 'dish_recipe',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'post__not_in'   => [ $recipe_id ],
			'tax_query'      => [
				[
					'taxonomy' => 'dish_recipe_category',
					'field'    => 'term_id',
					'terms'    => $term_ids,
					'operator' => 'IN',
				],
			],
		] ) )->posts;

		if ( empty( $pool ) ) {
			return [];
		}

		// Deterministic rotation: offset into the pool using recipe ID as seed.
		$count  = count( $pool );
		$offset = $recipe_id % $count;
		$pool   = array_merge( array_slice( $pool, $offset ), array_slice( $pool, 0, $offset ) );

		return array_slice( $pool, 0, $limit );
	}

	/**
	 * Get one recipe from each category the given recipe does NOT belong to.
	 *
	 * Intended for a "Complete the Menu" feature. Returns an associative array
	 * keyed by category term ID, each value a single WP_Post.
	 * Categories with no eligible recipes are omitted.
	 *
	 * Uses the same deterministic offset strategy as get_same_category().
	 *
	 * @param int $recipe_id  The current recipe post ID.
	 * @return array<int, array{ term: \WP_Term, recipe: \WP_Post }>
	 */
	public static function get_one_per_other_category( int $recipe_id ): array {
		$current_terms = get_the_terms( $recipe_id, 'dish_recipe_category' );
		$current_ids   = ( $current_terms && ! is_wp_error( $current_terms ) )
			? wp_list_pluck( $current_terms, 'term_id' )
			: [];

		$all_terms = get_terms( [
			'taxonomy'   => 'dish_recipe_category',
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
			return [];
		}

		$result = [];

		foreach ( $all_terms as $term ) {
			// Skip categories the current recipe already belongs to.
			if ( in_array( $term->term_id, $current_ids, true ) ) {
				continue;
			}

			$pool = ( new \WP_Query( [
				'post_type'      => 'dish_recipe',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'tax_query'      => [
					[
						'taxonomy' => 'dish_recipe_category',
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					],
				],
			] ) )->posts;

			if ( empty( $pool ) ) {
				continue;
			}

			// Deterministic pick: offset varies per recipe so different recipes
			// surface different suggestions without touching rand() or the DB twice.
			$offset = $recipe_id % count( $pool );

			$result[ $term->term_id ] = [
				'term'   => $term,
				'recipe' => $pool[ $offset ],
			];
		}

		return $result;
	}

	/**
	 * Get all published recipes, optionally limited.
	 *
	 * @param int $limit Max posts to return. -1 for all.
	 * @return \WP_Post[]
	 */
	public static function get_all( int $limit = -1 ): array {
		$query = new \WP_Query( [
			'post_type'      => 'dish_recipe',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		] );

		return $query->posts;
	}

	// -------------------------------------------------------------------------
	// Meta helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the ingredient sections for a recipe.
	 *
	 * @param int $recipe_id
	 * @return array<int, array{heading: string, items: array<int, array{qty: string, unit: string, item: string, note: string}>}>
	 */
	public static function get_ingredients( int $recipe_id ): array {
		$raw = get_post_meta( $recipe_id, 'dish_recipe_ingredients', true );

		if ( ! $raw ) {
			return [];
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Get the method sections for a recipe.
	 *
	 * @param int $recipe_id
	 * @return array<int, array{heading: string, steps: array<int, array{step: int, text: string}>}>
	 */
	public static function get_method( int $recipe_id ): array {
		$raw = get_post_meta( $recipe_id, 'dish_recipe_method', true );

		if ( ! $raw ) {
			return [];
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Get the related class template IDs for a recipe.
	 *
	 * @param int $recipe_id
	 * @return int[]
	 */
	public static function get_template_ids( int $recipe_id ): array {
		$raw = get_post_meta( $recipe_id, 'dish_recipe_template_ids', true );

		if ( ! $raw ) {
			return [];
		}

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			return [];
		}

		// Filter to only IDs that still resolve to a published class template.
		return array_values(
			array_filter(
				array_map( 'intval', $decoded ),
				static function ( int $id ): bool {
					$post = get_post( $id );
					return $post instanceof \WP_Post
						&& 'dish_class_template' === $post->post_type
						&& 'publish' === $post->post_status;
				}
			)
		);
	}

	/**
	 * Flatten all ingredient sections into a simple string array for schema output.
	 *
	 * Google's recipeIngredient spec does not support sections — all items are
	 * flattened to "qty unit item, note" strings regardless of section heading.
	 *
	 * @param int $recipe_id
	 * @return string[]
	 */
	public static function get_ingredients_flat( int $recipe_id ): array {
		$sections = self::get_ingredients( $recipe_id );
		$flat     = [];

		foreach ( $sections as $section ) {
			foreach ( $section['items'] ?? [] as $ing ) {
				$qty  = trim( $ing['qty']  ?? '' );
				$unit = trim( $ing['unit'] ?? '' );
				$item = trim( $ing['item'] ?? '' );
				$note = trim( $ing['note'] ?? '' );

				if ( '' === $item ) {
					continue;
				}

				$string = trim( "{$qty} {$unit} {$item}" );

				if ( '' !== $note ) {
					$string .= ", {$note}";
				}

				$flat[] = $string;
			}
		}

		return $flat;
	}
}
