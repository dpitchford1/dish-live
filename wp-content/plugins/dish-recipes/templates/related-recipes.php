<?php
/**
 * Related recipes block — rendered on dish_class_template single pages.
 *
 * Loaded by RelatedRecipes::render() via TemplateLoader.
 * Theme override: {theme}/dish-recipes/related-recipes.php
 *
 * @package Dish\Recipes
 *
 * @var \WP_Post[]                           $recipes
 * @var \Dish\Recipes\Frontend\TemplateLoader $loader
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $recipes ) ) {
	return;
}
?>

<section class="related-recipes">
	<h2 class="related-recipes__title"><?php esc_html_e( 'Recipes From This Class', 'dish-recipes' ); ?></h2>

	<div class="related-recipes__grid">
		<?php foreach ( $recipes as $recipe ) :
			$loader->load_template( 'card.php', [
				'recipe' => $recipe,
				'loader' => $loader,
			] );
		endforeach; ?>
	</div>
</section>
