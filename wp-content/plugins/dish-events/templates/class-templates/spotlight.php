<?php
/**
 * Partial: Class in the Spotlight.
 *
 * A full-width two-column promotional component for a single highlighted class
 * template. One template at a time can carry the dish_is_spotlight flag.
 * Intended for use on the homepage and any other page via dish_the_spotlight_class().
 *
 * Variables in scope:
 *   $template  WP_Post  The dish_class_template post.
 *
 * Theme override: {theme}/dish-events/class-templates/spotlight.php
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
$ticket_type  = ClassTemplateRepository::get_ticket_type( $template->ID );
$price_label  = $ticket_type ? MoneyHelper::cents_to_display( (int) $ticket_type->price_cents ) : '';
$booking_type = (string) get_post_meta( $template->ID, 'dish_booking_type', true ) ?: 'online';
$is_enquiry   = ( $booking_type === 'enquiry' );

// Gallery IDs.
$gallery_ids = ClassTemplateRepository::get_gallery_ids( $template->ID );
$has_gallery = ! empty( $gallery_ids );

// Lazy-enqueue Swiper only when a gallery is present.
if ( $has_gallery ) {
	wp_enqueue_style( 'dish-swiper', site_url( '/assets/css/resources/swiper.min.css' ), [], '11.2.7' );
	wp_enqueue_script( 'dish-swiper', site_url( '/assets/js/resources/swiper.min.js' ), [], '11.2.7', true );
	wp_add_inline_script( 'dish-swiper', '(function () {
"use strict";
function initGallery() {
    var el = document.querySelector( ".dish-template-swiper" );
    if ( ! el ) { return; }
    new Swiper( el, {
        loop:        el.querySelectorAll( ".swiper-slide" ).length > 1,
        slidesPerView: 1,
        spaceBetween:  20,
        keyboard:     { enabled: true, onlyInViewport: true },
        pagination:  { el: el.querySelector( ".swiper-pagination" ), clickable: true },
        navigation:  { nextEl: el.querySelector( ".swiper-button-next" ), prevEl: el.querySelector( ".swiper-button-prev" ) },
    } );
}
if ( document.readyState === "loading" ) {
    document.addEventListener( "DOMContentLoaded", initGallery );
} else {
    initGallery();
}
}() );' );
}

// Next upcoming public instance.
$next_class = get_posts( [
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

$next_date = '';
if ( ! empty( $next_class ) ) {
	$next_start = (int) get_post_meta( $next_class[0], 'dish_start_datetime', true );
	$next_date  = $next_start ? DateHelper::format( $next_start, 'j M Y' ) : '';
}
?>
<section class="content-region spotlight-wrapper full--width">

	<h2 class="section-heading text--centered"><?php esc_html_e( 'Dish in the Spotlight', 'dish-events' ); ?></h2>

	<div class="grid-general grid--2col"> 

		<?php if ( $has_gallery ) : ?>
        <div class="swiper dish-template-swiper">
            <div class="swiper-wrapper">
            <?php foreach ( $gallery_ids as $gid ) :
                $alt = (string) get_post_meta( $gid, '_wp_attachment_image_alt', true );
            ?>
                <div class="swiper-slide">
                    <figure class="dish-template-swiper__item">
                        <?php echo wp_get_attachment_image( $gid, 'basecamp-img-m', false, [ 'alt' => $alt, 'loading' => 'lazy' ] ); ?>
                    </figure>
                </div>
            <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
            <button type="button" class="swiper-button-prev" aria-label="<?php esc_attr_e( 'Previous image', 'dish-events' ); ?>"></button>
            <button type="button" class="swiper-button-next" aria-label="<?php esc_attr_e( 'Next image', 'dish-events' ); ?>"></button>
        </div>
		<?php elseif ( has_post_thumbnail( $template->ID ) ) : ?>
		<div class="dish-spotlight__image">
			<a href="<?php echo esc_url( $card_url ); ?>" tabindex="-1" aria-hidden="true"><?php echo wp_get_attachment_image( get_post_thumbnail_id( $template->ID ), 'basecamp-img-m', false, [ 'loading' => 'lazy' ] ); ?></a>
		</div>
		<?php endif; ?>

		<div class="spotlight--content">
			<?php dish_the_format_pill( $format_post, $format_color ); ?>

			<h3 class="section-title"><a href="<?php echo esc_url( $card_url ); ?>"><?php echo esc_html( $template->post_title ); ?></a></h3>

			<?php if ( $template->post_content ) : ?>
				<div class="entry--content">
					<?php echo apply_filters( 'the_content', $template->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>

			<div class="spotlight-meta">
				<?php if ( $price_label && ! $is_enquiry ) : ?>
					<span class="spotlight-price"><?php echo esc_html( $price_label ); ?></span>
				<?php endif; ?>
				<?php if ( $next_date ) : ?>
					<p class="spotlight-next-date"><?php printf( esc_html__( 'Next session: %s', 'dish-events' ), esc_html( $next_date ) ); /* translators: %s: next session date */?></p>
				<?php endif; ?>
			</div>

			<div class="spotlight-actions">
				<a href="<?php echo esc_url( $card_url ); ?>" class="button button--primary"><?php esc_html_e( 'View Details', 'dish-events' ); ?></a>
				<?php if ( $is_enquiry ) : ?>
					<a href="<?php echo esc_url( dish_get_enquiry_url() ); ?>" class="button button--secondary"><?php esc_html_e( 'Enquire', 'dish-events' ); ?></a>
				<?php endif; ?>
			</div>

		</div><!-- .spotlight--content -->
	</div><!-- .grid-general -->
</section>