<?php
/**
 * Partial: class instance card.
 *
 * Displays a single upcoming dish_class as a card — date, title, price, and
 * remaining spots. Used in the [dish_classes] archive grid.
 *
 * Variables in scope (injected by the archive loop):
 *   $class  WP_Post  A dish_class post.
 *
 * Theme override: {theme}/dish-events/classes/card.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $class ) || ! ( $class instanceof WP_Post ) ) {
	return;
}

use Dish\Events\Admin\Settings;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\ClassTemplateRepository;
use Dish\Events\Helpers\DateHelper;
use Dish\Events\Helpers\MoneyHelper;

// ── Meta ──────────────────────────────────────────────────────────────────────
$template_id   = (int) get_post_meta( $class->ID, 'dish_template_id', true );
$template_post = $template_id ? get_post( $template_id ) : null;
$start         = (int) get_post_meta( $class->ID, 'dish_start_datetime', true );

// Card title/image → template page with ?class_id=N (shows class detail + instance panel).
// "Book Now" button → checkout page with ?class_id=N.
// Both fall back gracefully when the respective destination isn't configured.
$booking_page = (int) Settings::get( 'booking_page', 0 );
$book_url     = $booking_page
	? add_query_arg( 'class_id', $class->ID, get_permalink( $booking_page ) )
	: '';
$card_url     = $template_post
	? add_query_arg( 'class_id', $class->ID, get_permalink( $template_post->ID ) )
	: $book_url;

// ── Price & availability ──────────────────────────────────────────────────────
// Use pre-loaded maps when available (archive batch load), otherwise fall back
// to individual queries (e.g. when card.php is included standalone).
/** @var array<int,int>         $booked_counts    Injected by archive.php */
/** @var array<int,object|null> $ticket_types_map Injected by archive.php */
$ticket_type = isset( $ticket_types_map ) && $template_id
	? ( $ticket_types_map[ $template_id ] ?? null )
	: ( $template_id ? ClassTemplateRepository::get_ticket_type( $template_id ) : null );
$price_label = $ticket_type ? MoneyHelper::cents_to_display( (int) $ticket_type->price_cents ) : '';
$capacity    = $ticket_type ? (int) $ticket_type->capacity : 0;
$booked      = isset( $booked_counts )
	? ( $booked_counts[ $class->ID ] ?? 0 )
	: ClassRepository::get_booked_count( $class->ID );
$remaining   = $capacity > 0 ? max( 0, $capacity - $booked ) : 0;

// ── Display helpers ───────────────────────────────────────────────────────────
$title         = $template_post ? $template_post->post_title : get_the_title( $class->ID );
$date_label    = $start ? DateHelper::to_display( $start ) : '';
$thumb_id      = get_post_thumbnail_id( $class->ID ) ?: ( $template_post ? get_post_thumbnail_id( $template_post->ID ) : 0 );
$thumb_html = $thumb_id
	? wp_get_attachment_image( (int) $thumb_id, 'basecamp-img-s', false, [ 'class' => 'card__img', 'loading' => 'lazy' ] )
	: '';

$is_private   = (bool) get_post_meta( $class->ID, 'dish_is_private', true );
$booking_type = $template_post ? ( (string) get_post_meta( $template_post->ID, 'dish_booking_type', true ) ?: 'online' ) : 'online';
$is_enquiry   = ( $booking_type === 'enquiry' );

// Format pill.
$format_id    = $template_id ? (int) get_post_meta( $template_id, 'dish_format_id', true ) : 0;
$format_post  = $format_id ? get_post( $format_id ) : null;
$format_color = $format_post ? ( (string) get_post_meta( $format_id, 'dish_format_color', true ) ?: '#c0392b' ) : '';

// Spots label.
$spots_class = '';
$spots_label = '';
if ( $is_private ) {
	$spots_class = 'card-spots--private';
	$spots_label = __( 'Booked', 'dish-events' );
} elseif ( $capacity > 0 ) {
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
<article class="cards full--card" id="dish-class-<?php echo esc_attr( $class->ID ); ?>">
	<?php if ( $thumb_html ) : ?>
		<a href="<?php echo esc_url( $card_url ); ?>" class="card--img" tabindex="-1" aria-hidden="true"><?php echo $thumb_html; // Already escaped by wp_get_attachment_image. ?></a>
	<?php endif; ?>

	<div class="card--body">
		<?php if ( empty( $suppress_format_pill ) ) : ?>
			<?php dish_the_format_pill( $format_post, $format_color ); ?>
		<?php endif; ?>
		<?php if ( $date_label ) : ?>
			<time class="card--date" datetime="<?php echo esc_attr( $start ? DateHelper::format( $start, 'c' ) : '' ); ?>"><?php echo esc_html( $date_label ); ?></time>
		<?php endif; ?>

		<h3 class="card-title"><a href="<?php echo esc_url( $card_url ); ?>"><?php echo esc_html( $title ); ?></a></h3>

		<div class="card--meta">
			<?php if ( $price_label && $spots_class !== 'card-spots--soldout' && ! $is_enquiry ) : ?>
				<span class="card--price"><?php echo esc_html( $price_label ); ?> - </span>
			<?php endif; ?>

			<?php if ( $spots_label && ! $is_enquiry ) : ?>
				<span class="card--spots <?php echo esc_attr( $spots_class ); ?>">
					<?php echo esc_html( $spots_label ); ?>
				</span>
			<?php endif; ?>

			<?php if ( $spots_class === 'card-spots--soldout' && ! $is_enquiry ) : ?>
				<span class="dish-waitlist-hint"><?php esc_html_e( 'Waitlist coming soon', 'dish-events' ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( $is_private ) : ?>
			<span class="button button--disabled" aria-disabled="true">
				<?php esc_html_e( 'Private event', 'dish-events' ); ?>
			</span>
		<?php elseif ( $is_enquiry ) : ?>
			<a href="<?php echo esc_url( dish_get_enquiry_url() ); ?>" class="button button--secondary">
				<?php esc_html_e( 'Enquire to Book', 'dish-events' ); ?>
			</a>
		<?php elseif ( $spots_class !== 'card-spots--soldout' && $book_url ) : ?>
			<a href="<?php echo esc_url( $book_url ); ?>" class="button button--primary">
				<?php esc_html_e( 'Book Now', 'dish-events' ); ?>
			</a>
		<?php endif; ?>

	</div>

</article>
