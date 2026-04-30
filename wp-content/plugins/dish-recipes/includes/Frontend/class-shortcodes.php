<?php
/**
 * Frontend shortcodes.
 *
 * [dish_recipes]         — Recipe archive grid with optional category filter.
 * [dish_recipe id="123"] — Embed a single recipe card inline.
 *
 * @package Dish\Recipes\Frontend
 */

declare( strict_types=1 );

namespace Dish\Recipes\Frontend;

use Dish\Recipes\Data\RecipeRepository;

/**
 * Class Shortcodes
 */
final class Shortcodes {

	private TemplateLoader $loader;

	public function __construct() {
		$this->loader = new TemplateLoader();
	}

	/**
	 * @param \Dish\Recipes\Core\Loader $loader
	 */
	public function register_hooks( \Dish\Recipes\Core\Loader $loader ): void {
		$loader->add_action( 'init', $this, 'register_shortcodes' );
	}

	/**
	 * Register the shortcodes with WordPress.
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'dish_recipes', [ $this, 'render_archive' ] );
		add_shortcode( 'dish_recipe',  [ $this, 'render_single'  ] );
	}

	// -------------------------------------------------------------------------
	// [dish_recipes]
	// -------------------------------------------------------------------------

	/**
	 * Render a recipe archive grid.
	 *
	 * Attributes:
	 *   category  (string) — dish_recipe_category slug to filter by.
	 *   limit     (int)    — Max recipes to show. Default 12.
	 *   columns   (int)    — Grid column count hint for CSS class. Default 3.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_archive( $atts ): string {
		$atts = shortcode_atts(
			[
				'category' => '',
				'limit'    => 12,
				'columns'  => 3,
			],
			$atts,
			'dish_recipes'
		);

		$limit    = max( 1, (int) $atts['limit'] );
		$columns  = max( 1, (int) $atts['columns'] );
		$category = sanitize_text_field( $atts['category'] );

		if ( $category ) {
			$recipes = RecipeRepository::get_by_category( $category, $limit );
		} else {
			$recipes = RecipeRepository::get_all( $limit );
		}

		ob_start();
		$this->loader->load_template( 'archive.php', [
			'recipes'  => $recipes,
			'columns'  => $columns,
			'category' => $category,
			'loader'   => $this->loader,
		] );
		return (string) ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [dish_recipe id="123"]
	// -------------------------------------------------------------------------

	/**
	 * Render a single recipe card inline.
	 *
	 * Attributes:
	 *   id (int) — Post ID of the dish_recipe.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_single( $atts ): string {
		$atts = shortcode_atts( [ 'id' => 0 ], $atts, 'dish_recipe' );

		$recipe_id = absint( $atts['id'] );

		if ( ! $recipe_id ) {
			return '';
		}

		$recipe = RecipeRepository::get( $recipe_id );

		if ( ! $recipe ) {
			return '';
		}

		ob_start();
		$this->loader->load_template( 'card.php', [
			'recipe' => $recipe,
			'loader' => $this->loader,
		] );
		return (string) ob_get_clean();
	}
}
