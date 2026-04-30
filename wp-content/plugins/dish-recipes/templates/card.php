<?php
/**
 * Recipe card partial.
 *
 * Used in:
 *   - archive.php grid
 *   - related-recipes.php on class template pages
 *   - [dish_recipe id=""] shortcode
 *
 * Theme override: {theme}/dish-recipes/card.php
 *
 * @package Dish\Recipes
 *
 * @var \WP_Post $recipe  The recipe post object.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $recipe ) || ! $recipe instanceof \WP_Post ) {
	return;
}

$difficulty       = get_post_meta( $recipe->ID, 'dish_recipe_difficulty', true );
$yield            = get_post_meta( $recipe->ID, 'dish_recipe_yield',      true );
$prep_time        = (int) get_post_meta( $recipe->ID, 'dish_recipe_prep_time', true );
$cook_time        = (int) get_post_meta( $recipe->ID, 'dish_recipe_cook_time', true );
$total_time       = (int) get_post_meta( $recipe->ID, 'dish_recipe_total_time', true );
$computed_total   = $total_time ?: ( $prep_time + $cook_time );
$difficulty_label = [ 'easy' => 'Easy', 'medium' => 'Medium', 'advanced' => 'Advanced' ][ $difficulty ] ?? '';

$categories = get_the_terms( $recipe->ID, 'dish_recipe_category' );
$cat_name   = ( is_array( $categories ) && ! empty( $categories ) ) ? $categories[0]->name : '';
?>

<article class="recipe-card">
	<a href="<?php echo esc_url( get_permalink( $recipe->ID ) ); ?>" class="recipe-card__link" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail( $recipe->ID ) ) : ?>
			<div class="recipe-card__picture">
				<?php echo get_the_post_thumbnail( $recipe->ID, 'medium', [ 'class' => 'recipe-card__img', 'alt' => esc_attr( $recipe->post_title ) ] ); ?>
			</div>
		<?php endif; ?>
	</a>

	<div class="recipe-card__body">
		<?php if ( $cat_name ) : ?>
			<span class="recipe-card__category"><?php echo esc_html( $cat_name ); ?></span>
		<?php endif; ?>

		<h3 class="recipe-card__title">
			<a href="<?php echo esc_url( get_permalink( $recipe->ID ) ); ?>">
				<?php echo esc_html( $recipe->post_title ); ?>
			</a>
		</h3>

		<?php if ( $recipe->post_excerpt ) : ?>
			<p class="recipe-card__excerpt"><?php echo esc_html( wp_trim_words( $recipe->post_excerpt, 20 ) ); ?></p>
		<?php endif; ?>

		<div class="recipe-card__meta">
			<?php if ( $computed_total ) : ?>
				<span class="recipe-card__time"><?php echo (int) $computed_total; ?> <?php esc_html_e( 'min', 'dish-recipes' ); ?></span>
			<?php endif; ?>
			<?php if ( $difficulty_label ) : ?>
				<span class="recipe-card__difficulty recipe-card__difficulty--<?php echo esc_attr( $difficulty ); ?>">
					<?php echo esc_html( $difficulty_label ); ?>
				</span>
			<?php endif; ?>
		</div>
	</div>
</article>
