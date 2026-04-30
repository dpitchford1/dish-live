<?php
/**
 * Complete the Menu partial — one recipe from each other category.
 *
 * Theme override: {theme}/dish-recipes/complete-menu.php
 *
 * @package Dish\Recipes
 *
 * @var array<int, array{ term: \WP_Term, recipe: \WP_Post }> $menu_items
 * @var \Dish\Recipes\Frontend\TemplateLoader                 $loader
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $menu_items ) ) {
	return;
}
?>
<section class="complete-menu">
	<h2 class="complete-menu__heading"><?php esc_html_e( 'Complete the Menu', 'dish-recipes' ); ?></h2>
	<div class="complete-menu__grid">
		<?php foreach ( $menu_items as $item ) : ?>
			<div class="complete-menu__category">
				<h3 class="complete-menu__category-name"><?php echo esc_html( $item['term']->name ); ?></h3>
				<?php $loader->load_template( 'card.php', [
					'recipe' => $item['recipe'],
					'loader' => $loader,
				] ); ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
