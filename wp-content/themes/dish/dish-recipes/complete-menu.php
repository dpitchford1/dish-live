<?php
/**
 * Complete the Menu partial — theme override.
 *
 * One recipe pulled from each category the current recipe doesn't belong to.
 *
 * @package Dish (theme override)
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
	<h2 class="section-heading"><?php esc_html_e( 'Complete the Menu', 'dish-recipes' ); ?></h2>
	<div class="grid-general grid--4col tight--grid">
		<?php foreach ( $menu_items as $item ) : ?>
			<div class="complete-menu__category">
				<!-- <h3 class="complete-menu__category-name"><?php echo esc_html( $item['term']->name ); ?></h3> -->
				<?php $loader->load_template( 'card-simple.php', [
					'recipe' => $item['recipe'],
					'loader' => $loader,
				] ); ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
