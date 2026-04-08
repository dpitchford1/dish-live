<?php
/**
 * Partial: chef card.
 *
 * Displays a single dish_chef as a card — photo, name, role, excerpt, and a
 * link to the full chef profile. Used in the [dish_chefs] archive grid.
 *
 * Variables in scope (injected by the archive loop):
 *   $chef  WP_Post  A dish_chef post.
 *
 * Theme override: {theme}/dish-events/chefs/card.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $chef ) || ! ( $chef instanceof WP_Post ) ) {
	return;
}

$card_url  = get_permalink( $chef->ID );
$role      = (string) get_post_meta( $chef->ID, 'dish_chef_role', true );
$has_thumb = has_post_thumbnail( $chef->ID );
?>
<article class="dish-card dish-chef-card" id="dish-chef-<?php echo esc_attr( $chef->ID ); ?>">

	<?php if ( $has_thumb ) : ?>
		<a href="<?php echo esc_url( $card_url ); ?>" class="dish-card__image-link dish-chef-card__photo-link" tabindex="-1" aria-hidden="true">
			<?php Basecamp_Frontend::picture( get_post_thumbnail_id( $chef->ID ), [
				'portrait_size'  => 'portait-m',
				'landscape_size' => 'basecamp-img-m',
				'img_class'      => 'dish-card__img',
				'loading'        => 'lazy',
			] ); ?>
		</a>
	<?php endif; ?>
	<div class="dish-card__body">

		<h3 class="dish-card__title"><a href="<?php echo esc_url( $card_url ); ?>"><?php echo esc_html( $chef->post_title ); ?></a></h3>

		<?php if ( $role ) : ?>
			<p class="dish-chef-card__role"><?php echo esc_html( $role ); ?></p>
		<?php endif; ?>

		<?php if ( $chef->post_excerpt ) : ?>
			<p class="dish-card__excerpt"><?php echo esc_html( $chef->post_excerpt ); ?></p>
		<?php endif; ?>

		<a href="<?php echo esc_url( $card_url ); ?>" class="dish-card__link button button--secondary"><?php esc_html_e( 'View Profile', 'dish-events' ); ?></a>

	</div>
</article>
