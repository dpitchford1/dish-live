<?php
/**
 * More Recipes partial — theme override.
 *
 * Same-category recipe suggestions shown on a single recipe page.
 *
 * @package Dish (theme override)
 *
 * @var \WP_Post[]                            $recipes
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
<section class="more-recipes">
	<h2 class="more-recipes__heading"><?php esc_html_e( 'More Recipes', 'dish-recipes' ); ?></h2>
	<div class="more-recipes__grid">
		<?php foreach ( $recipes as $recipe ) :
			$loader->load_template( 'card.php', [
				'recipe' => $recipe,
				'loader' => $loader,
			] );
		endforeach; ?>
	</div>
</section>
