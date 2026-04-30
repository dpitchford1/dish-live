<?php
/**
 * Frontend asset enqueuing.
 *
 * Enqueues dish-recipes.css on single recipe pages, the recipe archive,
 * and category term archives. Not loaded globally.
 *
 * @package Dish\Recipes\Frontend
 */

declare( strict_types=1 );

namespace Dish\Recipes\Frontend;

/**
 * Class Assets
 */
final class Assets {

	/**
	 * @param \Dish\Recipes\Core\Loader $loader
	 */
	public function register_hooks( \Dish\Recipes\Core\Loader $loader ): void {
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue' );
	}

	/**
	 * Enqueue frontend styles on recipe pages only.
	 */
	public function enqueue(): void {
		if (
			! is_singular( 'dish_recipe' ) &&
			! is_post_type_archive( 'dish_recipe' ) &&
			! is_tax( 'dish_recipe_category' )
		) {
			return;
		}

		wp_enqueue_style(
			'dish-recipes',
			DISH_RECIPES_URL . 'assets/css/dish-recipes.css',
			[],
			DISH_RECIPES_VERSION
		);
	}
}
