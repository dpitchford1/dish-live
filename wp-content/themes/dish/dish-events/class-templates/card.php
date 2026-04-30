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

use Dish\Events\Admin\Settings;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\ClassTemplateRepository;
use Dish\Events\Helpers\DateHelper;
use Dish\Events\Helpers\MoneyHelper;

$card_url     = get_permalink( $template );
$is_featured  = (bool) get_post_meta( $template->ID, 'dish_is_featured', true );
$format_id    = (int) get_post_meta( $template->ID, 'dish_format_id', true );
$format_post  = $format_id ? get_post( $format_id ) : null;
$format_color = $format_post ? ( (string) get_post_meta( $format_id, 'dish_format_color', true ) ?: '#c0392b' ) : '';
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

// ── Availability & booking ────────────────────────────────────────────────────
$next_class_id = ! empty( $next_arr ) ? (int) $next_arr[0] : 0;
$capacity      = $ticket_type ? (int) $ticket_type->capacity : 0;
$booked        = $next_class_id ? ClassRepository::get_booked_count( $next_class_id ) : 0;
$remaining     = $capacity > 0 ? max( 0, $capacity - $booked ) : 0;
$booking_type  = (string) get_post_meta( $template->ID, 'dish_booking_type', true ) ?: 'online';
$is_enquiry    = ( 'enquiry' === $booking_type );
$booking_page  = (int) Settings::get( 'booking_page', 0 );
$book_url      = $next_class_id && $booking_page
	? add_query_arg( 'class_id', $next_class_id, get_permalink( $booking_page ) )
	: '';

// Spots label — only relevant when a next instance exists.
$spots_class = '';
$spots_label = '';
if ( $next_class_id && $capacity > 0 ) {
	if ( $remaining <= 0 ) {
		$spots_class = 'card-spots--soldout';
		$spots_label = __( 'Sold out', 'dish-events' );
	} elseif ( $remaining <= 3 ) {
		$spots_class = 'card-spots--low';
		/* translators: %d: number of spots left */
		$spots_label = sprintf( _n( '%d spot left', '%d spots left!', $remaining, 'dish-events' ), $remaining );
	} else {
		/* translators: %d: number of spots remaining */
		$spots_label = sprintf( _n( '%d spot available', '%d spots available', $remaining, 'dish-events' ), $remaining );
	}
}
?>
<article class="cards full--card <?php echo $is_featured ? ' card--featured' : ''; ?>" id="template-<?php echo esc_attr( $template->ID ); ?>">
	<?php if ( $is_featured ) : ?>
		<span class="card--featured-badge" aria-label="<?php esc_attr_e( 'Featured', 'dish-events' ); ?>"><?php esc_html_e( 'Featured', 'dish-events' ); ?></span>
	<?php endif; ?> 

	<?php if ( has_post_thumbnail( $template->ID ) ) : ?>
		<a href="<?php echo esc_url( $card_url ); ?>" class="card--img" tabindex="-1" aria-hidden="true">
			<?php echo wp_get_attachment_image( get_post_thumbnail_id( $template->ID ), 'basecamp-img-sm', false, [ 'class' => 'card__img', 'loading' => 'lazy' ] ); ?>
		</a>
	<?php endif; ?>

	<div class="card--body">
		<?php if ( empty( $suppress_format_pill ) ) : ?>
			<?php dish_the_format_pill( $format_post, $format_color ); ?>
		<?php endif; ?>

		<h3 class="card-title"><a href="<?php echo esc_url( $card_url ); ?>"><?php echo esc_html( $template->post_title ); ?></a></h3>

		<?php if ( $template->post_excerpt ) : ?>
			<p class="card--excerpt"><?php echo esc_html( $template->post_excerpt ); ?></p>
		<?php endif; ?>

		<div class="card--meta">
			<?php if ( $price_label && $next_class_id && $spots_class !== 'card-spots--soldout' && ! $is_enquiry ) : ?>
				<span class="card--price"><?php echo esc_html( $price_label ); ?></span>
			<?php endif; ?>

			<?php if ( $next_date ) : ?>
				<span class="card--date">
					<?php
					/* translators: %s: next session date */
					printf( esc_html__( 'Next: %s', 'dish-events' ), esc_html( $next_date ) );
					?>
				</span>
			<?php endif; ?>

			<?php if ( $spots_label && ! $is_enquiry ) : ?>
				<span class="card--spots <?php echo esc_attr( $spots_class ); ?>">
					<?php echo esc_html( $spots_label ); ?>
				</span>
			<?php endif; ?>

			<?php // waitlist button rendered below in the CTA block ?>
		</div>

		<?php if ( $is_enquiry ) : ?>
			<a href="<?php echo esc_url( dish_get_enquiry_url() ); ?>" class="button button--secondary">
				<?php esc_html_e( 'Enquire to Book', 'dish-events' ); ?>
			</a>
		<?php elseif ( $spots_class === 'card-spots--soldout' && ! $is_enquiry ) : ?>
			<?php
			$_waitlist_url = add_query_arg( [
				'class-name' => rawurlencode( $template->post_title ),
				'date-241'   => isset( $next_start ) && $next_start ? date( 'Y-m-d', $next_start ) : '',
			], dish_get_waitlist_url() );
			?>
			<a href="<?php echo esc_url( $_waitlist_url ); ?>" class="button button--secondary">
				<?php esc_html_e( 'Join the waiting list', 'dish-events' ); ?>
			</a>
		<?php elseif ( $next_class_id && $book_url ) : ?>
			<a href="<?php echo esc_url( $card_url ); ?>" class="button button--secondary"><?php esc_html_e( 'View Details', 'dish-events' ); ?></a>
			<a href="<?php echo esc_url( $book_url ); ?>" class="button button--primary"><?php esc_html_e( 'Book Now', 'dish-events' ); ?></a>
		<?php else : ?>
			<a href="<?php echo esc_url( $card_url ); ?>" class="button button--secondary"><?php esc_html_e( 'View Details', 'dish-events' ); ?></a>
		<?php endif; ?>
	</div>

</article>
