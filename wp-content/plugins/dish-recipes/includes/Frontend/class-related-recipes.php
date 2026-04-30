<?php
/**
 * Related Recipes block for dish_class_template single pages.
 *
 * Hooks into do_action( 'dish_after_class_template_content' ) which is
 * fired by the dish-events single class template template.
 *
 * Only wired up by Plugin::wire_hooks() when DISH_EVENTS_VERSION is defined.
 * If dish-events is inactive, this class is never instantiated.
 *
 * @package Dish\Recipes\Frontend
 */

declare( strict_types=1 );

namespace Dish\Recipes\Frontend;

use Dish\Recipes\Data\RecipeRepository;

/**
 * Class RelatedRecipes
 */
final class RelatedRecipes {

	private TemplateLoader $loader;

	/**
	 * @param TemplateLoader $loader
	 */
	public function __construct( TemplateLoader $loader ) {
		$this->loader = $loader;
	}

	/**
	 * Render the related recipes block.
	 * Hooked to dish_after_class_template_content.
	 */
	public function render(): void {
		$template_id = get_the_ID();

		if ( ! $template_id ) {
			return;
		}

		$recipes = RecipeRepository::get_by_template_id( $template_id );

		if ( empty( $recipes ) ) {
			return;
		}

		$this->loader->load_template( 'related-recipes.php', [
			'recipes' => $recipes,
			'loader'  => $this->loader,
		] );
	}
}
