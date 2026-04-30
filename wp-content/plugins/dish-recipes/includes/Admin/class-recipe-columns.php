<?php
/**
 * Recipe list table columns.
 *
 * Adds thumbnail, category, difficulty, and related class count columns
 * to the dish_recipe post list screen.
 *
 * @package Dish\Recipes\Admin
 */

declare( strict_types=1 );

namespace Dish\Recipes\Admin;

/**
 * Class RecipeColumns
 */
final class RecipeColumns {

	/**
	 * @param \Dish\Recipes\Core\Loader $loader
	 */
	public function register_hooks( \Dish\Recipes\Core\Loader $loader ): void {
		$loader->add_filter( 'manage_dish_recipe_posts_columns',       $this, 'add_columns' );
		$loader->add_action( 'manage_dish_recipe_posts_custom_column', $this, 'render_column', 10, 2 );
		$loader->add_filter( 'manage_edit-dish_recipe_sortable_columns', $this, 'sortable_columns' );
	}

	// -------------------------------------------------------------------------
	// Columns definition
	// -------------------------------------------------------------------------

	/**
	 * Define list table columns.
	 *
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function add_columns( array $columns ): array {
		$new = [];

		// Inject thumbnail after checkbox, before title.
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['dish_recipe_thumb'] = __( 'Photo', 'dish-recipes' );
			}
			$new[ $key ] = $label;
		}

		// Append custom columns.
		$new['dish_recipe_category']   = __( 'Category',    'dish-recipes' );
		$new['dish_recipe_difficulty'] = __( 'Difficulty',  'dish-recipes' );
		$new['dish_recipe_classes']    = __( 'Classes',     'dish-recipes' );

		// Remove default date column and re-add at end.
		unset( $new['date'] );
		$new['date'] = __( 'Date', 'dish-recipes' );

		return $new;
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Output the custom column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {

			case 'dish_recipe_thumb':
				echo get_the_post_thumbnail( $post_id, [ 60, 60 ] );
				break;

			case 'dish_recipe_category':
				$terms = get_the_terms( $post_id, 'dish_recipe_category' );
				if ( is_array( $terms ) && ! empty( $terms ) ) {
					$links = array_map( static function ( \WP_Term $term ): string {
						$url = add_query_arg( [
							'post_type'            => 'dish_recipe',
							'dish_recipe_category' => $term->slug,
						], admin_url( 'edit.php' ) );
						return '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
					}, $terms );
					echo implode( ', ', $links );
				} else {
					echo '—';
				}
				break;

			case 'dish_recipe_difficulty':
				$val = get_post_meta( $post_id, 'dish_recipe_difficulty', true );
				$map = [ 'easy' => 'Easy', 'medium' => 'Medium', 'advanced' => 'Advanced' ];
				echo esc_html( $map[ $val ] ?? '—' );
				break;

			case 'dish_recipe_classes':
				$raw = get_post_meta( $post_id, 'dish_recipe_template_ids', true );
				if ( $raw ) {
					$ids = json_decode( $raw, true );
					echo is_array( $ids ) ? count( $ids ) : '0';
				} else {
					echo '0';
				}
				break;
		}
	}

	// -------------------------------------------------------------------------
	// Sortable
	// -------------------------------------------------------------------------

	/**
	 * Register sortable columns.
	 *
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['dish_recipe_difficulty'] = 'dish_recipe_difficulty';
		return $columns;
	}
}
