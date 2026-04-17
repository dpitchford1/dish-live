<?php
/**
 * Partial: dish_format card.
 *
 * Used in both the format archive and the [dish_formats] shortcode.
 * Expects $format (WP_Post) to be in scope from the including loop.
 *
 * Theme override: {theme}/dish-events/formats/card.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $format ) || ! ( $format instanceof WP_Post ) ) {
	return;
}

$card_url    = get_permalink( $format );
$card_title  = get_the_title( $format );
$card_thumb  = has_post_thumbnail( $format->ID );
$card_color  = (string) get_post_meta( $format->ID, 'dish_format_color', true ) ?: '#c0392b';
?>
<article class="cards full--card format--card" id="format-<?php echo esc_attr( $format->ID ); ?>" style="--format-color:<?php echo esc_attr( $card_color ); ?>">

	<?php if ( $card_thumb ) : ?>
		<a href="<?php echo esc_url( $card_url ); ?>" class="card--img" tabindex="-1" aria-hidden="true"><?php echo wp_get_attachment_image( get_post_thumbnail_id( $format->ID ), 'basecamp-img-s', false, [ 'class' => 'card__img', 'loading' => 'lazy' ] ); ?></a>
	<?php endif; ?>

	<div class="card--body">
		<h3 class="card-title"><a href="<?php echo esc_url( $card_url ); ?>"><?php echo esc_html( $card_title ); ?></a></h3>

		<?php if ( $format->post_excerpt ) : ?>
			<p class="card--excerpt"><?php echo esc_html( $format->post_excerpt ); ?></p>
		<?php endif; ?>

		<a href="<?php echo esc_url( $card_url ); ?>" class="button button--secondary"><?php printf( esc_html__( 'More about %s', 'dish-events' ), esc_html( $card_title ) ); ?></a>
	</div>

</article>
