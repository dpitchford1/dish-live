<?php
/**
 * Partial: class template card.
 *
 * Used on dish_format single pages to display a grid of class templates.
 * Expects $template (WP_Post) to be in scope from the including loop.
 *
 * Theme override: {theme}/dish-events/class-templates/card.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $template ) || ! ( $template instanceof WP_Post ) ) {
	return;
}

use Dish\Events\Data\ClassTemplateRepository;
use Dish\Events\Helpers\DateHelper;
use Dish\Events\Helpers\MoneyHelper;

$card_url     = get_permalink( $template );
$format_id    = (int) get_post_meta( $template->ID, 'dish_format_id', true );
$format_post  = $format_id ? get_post( $format_id ) : null;
$format_color = $format_post ? ( (string) get_post_meta( $format_id, 'dish_format_color', true ) ?: '#c0392b' ) : '';
$format_url   = $format_post ? get_permalink( $format_post ) : '';
$ticket_type  = ClassTemplateRepository::get_ticket_type( $template->ID );
$price_label = $ticket_type ? MoneyHelper::cents_to_display( (int) $ticket_type->price_cents ) : '';

// Next upcoming public instance date.
$next_date = '';
$next_arr  = get_posts( [
	'post_type'      => 'dish_class',
	'post_status'    => 'publish',
	'posts_per_page' => 1,
	'orderby'        => 'meta_value_num',
	'meta_key'       => 'dish_start_datetime',
	'order'          => 'ASC',
	'fields'         => 'ids',
	'meta_query'     => [
		'relation' => 'AND',
		[
			'key'     => 'dish_template_id',
			'value'   => $template->ID,
			'compare' => '=',
			'type'    => 'NUMERIC',
		],
		[
			'key'     => 'dish_start_datetime',
			'value'   => time(),
			'compare' => '>=',
			'type'    => 'NUMERIC',
		],
		[
			'relation' => 'OR',
			[ 'key' => 'dish_is_private', 'compare' => 'NOT EXISTS' ],
			[ 'key' => 'dish_is_private', 'value'   => '1', 'compare' => '!=' ],
		],
	],
] );
if ( ! empty( $next_arr ) ) {
	$next_start = (int) get_post_meta( $next_arr[0], 'dish_start_datetime', true );
	$next_date  = $next_start ? DateHelper::format( $next_start, 'j M Y' ) : '';
}
?>
<article class="dish-card dish-template-card" id="template-<?php echo esc_attr( $template->ID ); ?>">

	<?php if ( has_post_thumbnail( $template->ID ) ) : ?>
		<a href="<?php echo esc_url( $card_url ); ?>" class="dish-card__image-link" tabindex="-1" aria-hidden="true">
			<?php echo get_the_post_thumbnail( $template->ID, 'medium' ); ?>
		</a>
	<?php endif; ?>

	<div class="dish-card__body">
		<?php if ( $format_post && $format_color ) : ?>
			<a href="<?php echo esc_url( $format_url ); ?>" class="dish-format-pill" style="--format-color:<?php echo esc_attr( $format_color ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Format: %s', 'dish-events' ), $format_post->post_title ) ); ?>">
				<?php echo esc_html( $format_post->post_title ); ?>
			</a>
		<?php endif; ?>

		<h3 class="dish-card__title">
			<a href="<?php echo esc_url( $card_url ); ?>">
				<?php echo esc_html( $template->post_title ); ?>
			</a>
		</h3>

		<?php if ( $template->post_excerpt ) : ?>
			<p class="dish-card__excerpt"><?php echo esc_html( $template->post_excerpt ); ?></p>
		<?php endif; ?>

		<div class="dish-card__meta">
			<?php if ( $price_label ) : ?>
				<span class="dish-card__price"><?php echo esc_html( $price_label ); ?></span>
			<?php endif; ?>
			<?php if ( $next_date ) : ?>
				<span class="dish-card__next-date">
					<?php
					/* translators: %s: next session date */
					printf( esc_html__( 'Next: %s', 'dish-events' ), esc_html( $next_date ) );
					?>
				</span>
			<?php endif; ?>
		</div>

		<a href="<?php echo esc_url( $card_url ); ?>" class="dish-card__link button">
			<?php esc_html_e( 'View Class', 'dish-events' ); ?>
		</a>
	</div>

</article>
