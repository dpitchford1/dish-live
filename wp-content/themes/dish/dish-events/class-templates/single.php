<?php
/**
 * Template: dish_class_template single page.
 *
 * Displays the canonical class offering page: hero image, price/duration meta,
 * body content, photo gallery, assigned chefs, class details (included, bring,
 * requirements, dietary), and the upcoming dated instance list.
 *
 * Theme override: {theme}/dish-events/class-templates/single.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Admin\Settings;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\ClassTemplateRepository;
use Dish\Events\Helpers\DateHelper;
use Dish\Events\Helpers\MoneyHelper;

get_header();

while ( have_posts() ) :
	the_post();

	$template_id = get_the_ID();
	$format_id    = (int) get_post_meta( $template_id, 'dish_format_id', true );
	$format       = $format_id ? get_post( $format_id ) : null;
	$format_color = $format ? ( (string) get_post_meta( $format_id, 'dish_format_color', true ) ?: '#c0392b' ) : '';

	// ── Specific instance requested (calendar / card click via ?class_id=N) ──
	$requested_class_id = absint( $_GET['class_id'] ?? 0 );
	$requested_class    = null;
	if ( $requested_class_id ) {
		$candidate = get_post( $requested_class_id );
		// Validate: must be a published dish_class belonging to this template.
		if (
			$candidate &&
			$candidate->post_type   === 'dish_class' &&
			$candidate->post_status === 'publish' &&
			(int) get_post_meta( $candidate->ID, 'dish_template_id', true ) === $template_id
		) {
			$requested_class = $candidate;
		}
	}

	// ── Ticket type ────────────────────────────────────────────────────────────────────
	$ticket_type = ClassTemplateRepository::get_ticket_type( $template_id );
	$price_cents = $ticket_type ? (int) $ticket_type->price_cents : 0;
	$sale_cents  = ( $ticket_type && $ticket_type->sale_price_cents ) ? (int) $ticket_type->sale_price_cents : 0;
	$capacity     = $ticket_type ? (int) $ticket_type->capacity : 0;
	$booking_type    = (string) get_post_meta( $template_id, 'dish_booking_type', true ) ?: 'online';
	$is_enquiry      = ( $booking_type === 'enquiry' );
	$is_guest_chef   = (bool)   get_post_meta( $template_id, 'dish_is_guest_chef',   true );
	$guest_chef_name = (string) get_post_meta( $template_id, 'dish_guest_chef_name', true );
	$guest_chef_role = (string) get_post_meta( $template_id, 'dish_guest_chef_role', true );

	// ── First upcoming public instance (source of duration + details data) ─────────
	$first_arr = get_posts( [
		'post_type'      => 'dish_class',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'meta_value_num',
		'meta_key'       => 'dish_start_datetime',
		'order'          => 'ASC',
		'meta_query'     => [
			'relation' => 'AND',
			[
				'key'     => 'dish_template_id',
				'value'   => $template_id,
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
	$first = ! empty( $first_arr ) ? $first_arr[0] : null;

	// ── Display class: specific if URL param provided, else first upcoming ──
	$display_class = $requested_class ?? $first;

	// ── Duration ──────────────────────────────────────────────────────────────────────────
	$duration_label = '';
	if ( $first ) {
		$inst_start = (int) get_post_meta( $first->ID, 'dish_start_datetime', true );
		$inst_end   = (int) get_post_meta( $first->ID, 'dish_end_datetime',   true );
		if ( $inst_start && $inst_end && $inst_end > $inst_start ) {
			$mins = (int) round( ( $inst_end - $inst_start ) / 60 );
			$h    = intdiv( $mins, 60 );
			$m    = $mins % 60;
			if ( $h && $m ) {
				$duration_label = sprintf( _n( '%d hr', '%d hrs', $h, 'dish-events' ), $h )
					. ' ' . sprintf( _n( '%d min', '%d mins', $m, 'dish-events' ), $m );
			} elseif ( $h ) {
				$duration_label = sprintf( _n( '%d hour', '%d hours', $h, 'dish-events' ), $h );
			} else {
				$duration_label = sprintf( _n( '%d min', '%d mins', $m, 'dish-events' ), $m );
			}
		}
	}

	// ── Gallery ───────────────────────────────────────────────────────────────────────────
	$gallery_ids = ClassTemplateRepository::get_gallery_ids( $template_id );

	// ── Chefs ─────────────────────────────────────────────────────────────────────────────
	$chefs = [];
	if ( $first ) {
		foreach ( ClassRepository::get_chef_ids( $first->ID ) as $cid ) {
			$chef = get_post( $cid );
			if ( $chef && 'dish_chef' === $chef->post_type && 'publish' === $chef->post_status ) {
				$chefs[] = $chef;
			}
		}
	}

	// ── Class details (pulled from first upcoming instance) ─────────────────────────
	$decode_checked = static function ( string $key ) use ( $first ): array {
		if ( ! $first ) {
			return [];
		}
		$raw     = get_post_meta( $first->ID, $key, true ) ?: '[]';
		$decoded = json_decode( $raw, true );
		return is_array( $decoded )
			? array_values( array_filter( $decoded, fn( $i ) => ! empty( $i['checked'] ) ) )
			: [];
	};

	$whats_included = $decode_checked( 'dish_whats_included' );
	$what_to_bring  = $decode_checked( 'dish_what_to_bring' );
	$requirements   = $decode_checked( 'dish_class_requirements' );
	$dietary_flags  = $decode_checked( 'dish_dietary_flags' );
	$attendee_note  = $first ? (string) get_post_meta( $first->ID, 'dish_attendee_note', true ) : '';
	?>

<?php /* ── Hero ─────────────────────────────────────────── */ ?>
<section class="global--hero">
    <div class="hero--wrapper">
    <?php if ( has_post_thumbnail() ) : ?>

        <?php Basecamp_Frontend::picture( get_post_thumbnail_id(), [
        'landscape_size' => 'basecamp-img-xl',
        'loading'        => 'eager',
        'fetchpriority'  => 'high',
        'img_class'      => 'hero--img size-basecamp-img-xl',
    ] ); ?>

    <?php endif; ?>
        <div class="hero--text-block">
            <div class="hero--content">
                <h1 class="hero--heading"><?php the_title(); ?></h1>
            </div>
        </div>
    </div>
</section>
<?php /* ── Breadcrumb ─────────────────────────────────────────── */ ?>
<div class="fluid-content breadcrumb"><?php dish_the_breadcrumb(); ?></div>

<div class="content--region has--aside fluid-content">
<?php /* ── Main Content ─────────────────────────────────────────── */ ?>
<main id="main-content" class="main--content">
    
    
    <header class="dish-template-headers dish-containers">
    <?php if ( has_excerpt() ) : ?>
        <p class="dish-template-excerpt"><?php the_excerpt(); ?></p>
    <?php endif; ?>
    </header>

    <section class="class-content">

<?php /* ── Description ─────────────────────────────────────────── */ ?>
<?php if ( get_the_content() ) : ?>
    <article class="dish-template-contents dish-containers dish-contents">
        <?php the_content(); ?>

<?php /* ── Gallery ─────────────────────────────────────────── */ ?>
    <?php if ( ! empty( $gallery_ids ) ) :
        // Lazy-enqueue Swiper — only fires when the gallery actually renders.
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
    spaceBetween:  0,
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
    ?>
    <div class="dish-template-gallery dish-container" aria-label="<?php esc_attr_e( 'Class gallery', 'dish-events' ); ?>">
        <div class="swiper dish-template-swiper">
            <div class="swiper-wrapper">
                <?php foreach ( $gallery_ids as $gid ) :
                    $alt    = (string) get_post_meta( $gid, '_wp_attachment_image_alt', true );
                    $img_html = wp_get_attachment_image( $gid, 'basecamp-img-sm', false, [ 'alt' => $alt, 'loading' => 'lazy' ] );
                    if ( ! $img_html ) {
                        $img_html = wp_get_attachment_image( $gid, 'full', false, [ 'alt' => $alt, 'loading' => 'lazy' ] );
                    }
                    if ( ! $img_html ) continue;
            ?>
                <div class="swiper-slide">
                    <figure class="dish-template-swiper__item">
                        <?php echo $img_html; // Already escaped by wp_get_attachment_image. ?>
                    </figure>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
            <button type="button" class="swiper-button-prev" aria-label="<?php esc_attr_e( 'Previous image', 'dish-events' ); ?>"></button>
            <button type="button" class="swiper-button-next" aria-label="<?php esc_attr_e( 'Next image', 'dish-events' ); ?>"></button>
        </div>
    </div>
<?php endif; ?>

<?php /* ── Menu ─────────────────────────────────────────── */ ?>
        <?php dish_the_menu( $template_id ); ?>
    </article>
<?php endif; ?>
    </section>


<?php /* ── Details panels ─────────────────────────────────────────── */ ?>
<?php if ( $whats_included || $what_to_bring || $requirements || $dietary_flags ) : ?>

    <section class="grid-general grid--4col tight--grid">

        <?php if ( ! empty( $whats_included ) ) : ?>
            <div class="dish-details-panel">
                <h4 class="dish-details-panel__title"><?php esc_html_e( "What's Included", 'dish-events' ); ?></h4>
                <ul class="dish-details-panel__list">
                    <?php foreach ( $whats_included as $item ) : ?>
                        <li><?php echo esc_html( $item['label'] ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $what_to_bring ) ) : ?>
            <div class="dish-details-panel">
                <h4 class="dish-details-panel__title"><?php esc_html_e( 'What to Bring', 'dish-events' ); ?></h4>
                <ul class="dish-details-panel__list">
                    <?php foreach ( $what_to_bring as $item ) : ?>
                        <li><?php echo esc_html( $item['label'] ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $requirements ) ) : ?>
            <div class="dish-details-panel">
                <h4 class="dish-details-panel__title"><?php esc_html_e( 'Class Requirements', 'dish-events' ); ?></h4>
                <ul class="dish-details-panel__list">
                    <?php foreach ( $requirements as $item ) : ?>
                        <li><?php echo esc_html( $item['label'] ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $dietary_flags ) ) : ?>
            <div class="dish-details-panel">
                <h4 class="dish-details-panel__title"><?php esc_html_e( 'Dietary Information', 'dish-events' ); ?></h4>
                <ul class="dish-details-panel__list">
                    <?php foreach ( $dietary_flags as $item ) : ?>
                        <li><?php echo esc_html( $item['label'] ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    </section><!-- .dish-details-grid -->

<?php endif; ?>
    
</main>

    <aside class="aside class--aside">
        <h3 class="card-title"><?php esc_html_e( 'Class Details', 'dish-events' ); ?></h3>
        
        <?php /* ── Class Meta ─────────────────────────────────────────── */ ?>
    <?php if ( ! $display_class && ( ( ! $is_enquiry && $price_cents ) || $capacity ) ) : ?>
        <ul class="dish-template-meta" aria-label="<?php esc_attr_e( 'Class overview', 'dish-events' ); ?>">

        <?php if ( $format ) : ?>
            <li class="dish-template-meta__item">
                <span class="dish-template-meta__label"><?php esc_html_e( 'Format', 'dish-events' ); ?></span>
                <span class="dish-template-format" style="color: <?php echo esc_attr( $format_color ); ?>;"><?php echo esc_html( $format->post_title ); ?></span>
            </li>
        <?php endif; ?>

        <?php if ( $price_cents && ! $is_enquiry ) : ?>
            <li class="dish-template-meta__item">
                <span class="dish-template-meta__label"><?php esc_html_e( 'From', 'dish-events' ); ?></span>
                <?php if ( $sale_cents && $sale_cents < $price_cents ) : ?>
                    <span class="dish-template-meta__value">
                        <del><?php echo esc_html( MoneyHelper::cents_to_display( $price_cents ) ); ?></del>
                        <?php echo esc_html( MoneyHelper::cents_to_display( $sale_cents ) ); ?>
                    </span>
                <?php else : ?>
                    <span class="dish-template-meta__value"><?php echo esc_html( MoneyHelper::cents_to_display( $price_cents ) ); ?></span>
                <?php endif; ?>
            </li>
        <?php endif; ?>

        <?php if ( $duration_label ) : ?>
            <li class="dish-template-meta__item">
                <span class="dish-template-meta__label"><?php esc_html_e( 'Duration', 'dish-events' ); ?></span>
                <span class="dish-template-meta__value"><?php echo esc_html( $duration_label ); ?></span>
            </li>
        <?php endif; ?>

        <?php if ( $capacity ) : ?>
            <li class="dish-template-meta__item">
                <span class="dish-template-meta__label"><?php esc_html_e( 'Capacity', 'dish-events' ); ?></span>
                <span class="dish-template-meta__value"><?php echo esc_html( sprintf( _n( '%d person', '%d people', $capacity, 'dish-events' ), $capacity ) ); ?></span>
            </li>
        <?php endif; ?>

        </ul>
        <p><?php esc_html_e( 'No classes are currently scheduled. Please check back later for upcoming dates.', 'dish-events' ); ?></p>
    <?php endif; ?>

    <?php
    // ── Instance booking panel ─────────────────────────────────────────────
    // Shows the first upcoming class by default; overridden by ?class_id=N.
    if ( $display_class ) :
        $_dish_settings  = (array) get_option( 'dish_settings', [] );
        $_booking_page   = (int) ( $_dish_settings['booking_page'] ?? 0 );
        $_book_url       = $_booking_page
            ? add_query_arg( 'class_id', $display_class->ID, get_permalink( $_booking_page ) )
            : '';

        $_inst_start  = (int) get_post_meta( $display_class->ID, 'dish_start_datetime', true );
        $_inst_end    = (int) get_post_meta( $display_class->ID, 'dish_end_datetime',   true );
        $_is_private  = (bool) get_post_meta( $display_class->ID, 'dish_is_private', true );
        $_booked      = ClassRepository::get_booked_count( $display_class->ID );
        $_remaining   = $capacity > 0 ? max( 0, $capacity - $_booked ) : null;

        $_date_label  = $_inst_start ? DateHelper::to_display( $_inst_start ) : '';
        $_time_label  = '';
        if ( $_inst_start ) {
            $_time_label = DateHelper::format( $_inst_start, get_option( 'time_format' ) );
            if ( $_inst_end && $_inst_end > $_inst_start ) {
                $_time_label .= ' – ' . DateHelper::format( $_inst_end, get_option( 'time_format' ) );
            }
        }
    ?>
    <div class="dish-instance-panel dish-containers">
        <h4 class="dish-instance-panel__title"><?php esc_html_e( 'Next Class', 'dish-events' ); ?></h4>
        <div class="dish-instance-panel__inner">
            <div class="dish-instance-panel__meta">
                <?php if ( $_date_label ) : ?>
                    <span class="dish-instance-panel__item">
                        <span class="dish-instance-panel__icon" aria-hidden="true">📅</span>
                        <?php echo esc_html( $_date_label ); ?>
                    </span>
                <?php endif; ?>
                <?php if ( $_time_label ) : ?>
                    <span class="dish-instance-panel__item">
                        <span class="dish-instance-panel__icon" aria-hidden="true">🕐</span>
                        <?php echo esc_html( $_time_label ); ?>
                    </span>
                <?php endif; ?>
                <?php if ( $_remaining !== null ) : ?>
                    <span class="dish-instance-panel__item <?php echo $_remaining <= 0 ? 'dish-instance-panel__item--sold-out' : ( $_remaining <= 3 ? 'dish-instance-panel__item--low' : '' ); ?>">
                        <span class="dish-instance-panel__icon" aria-hidden="true">🎟</span>
                        <?php
                        if ( $_remaining <= 0 ) {
                            esc_html_e( 'Sold out', 'dish-events' );
                        } elseif ( $_remaining <= 3 ) {
                            echo esc_html( sprintf( _n( '%d spot left', '%d spots left', $_remaining, 'dish-events' ), $_remaining ) );
                        } else {
                            echo esc_html( sprintf( _n( '%d spot available', '%d spots available', $_remaining, 'dish-events' ), $_remaining ) );
                        }
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php if ( ! $_is_private && ( $_remaining === null || $_remaining > 0 ) ) : ?>
            <?php if ( $is_enquiry ) : ?>
                <a href="<?php echo esc_url( dish_get_enquiry_url() ); ?>" class="dish-instance-panel__cta button button--secondary">
                    <?php esc_html_e( 'Enquire to Book', 'dish-events' ); ?>
                </a>
            <?php elseif ( $_book_url ) : ?>
                <a href="<?php echo esc_url( $_book_url ); ?>" class="dish-instance-panel__cta button button--primary">
                    <?php esc_html_e( 'Book This Class', 'dish-events' ); ?>
                </a>
            <?php endif; ?>
        <?php elseif ( $_remaining !== null && $_remaining <= 0 ) : ?>
            <span class="dish-instance-panel__cta button button--disabled" aria-disabled="true">
                <?php esc_html_e( 'Sold Out', 'dish-events' ); ?>
            </span>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php /* ── Chefs ─────────────────────────────────────────── */ ?>
    <?php if ( $is_guest_chef && $guest_chef_name ) : ?>
    <div class="dish-template-chefs dish-container">
        <h4 class="dish-template-chefs__heading"><?php esc_html_e( 'The Guest Chef', 'dish-events' ); ?></h4>
        <div class="dish-chef-mini-list">
            <div class="dish-chef-mini">
                <div class="dish-chef-mini__info">
                    <strong class="dish-chef-mini__name"><?php echo esc_html( $guest_chef_name ); ?></strong>
                    <?php if ( $guest_chef_role ) : ?>
                        <span class="dish-chef-mini__role"><?php echo esc_html( $guest_chef_role ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ( ! $is_guest_chef && ! empty( $chefs ) ) : ?>
    <div class="dish-template-chefss dish-containers">
        <h4 class="dish-template-chefs__heading">
            <?php echo esc_html( _n( 'Class Chef', 'Class Chefs', count( $chefs ), 'dish-events' ) ); ?>
        </h4>
        <div class="dish-chef-mini-list">
            <?php foreach ( $chefs as $chef ) :
                $chef_role = (string) get_post_meta( $chef->ID, 'dish_chef_role', true );
            ?>
                <a href="<?php echo esc_url( get_permalink( $chef->ID ) ); ?>" class="dish-chef-mini">
                    <?php if ( has_post_thumbnail( $chef->ID ) ) : ?>
                    <?php echo wp_get_attachment_image( get_post_thumbnail_id( $chef->ID ), 'thumbnail', false, [ 'class' => 'dish-chef-mini__photo', 'loading' => 'lazy' ] ); ?>
                    <?php endif; ?>
                    <div class="dish-chef-mini__info">
                        <h5><?php echo esc_html( $chef->post_title ); ?></h5>
                        <?php if ( $chef_role ) : ?>
                            <span class="dish-chef-mini__role"><?php echo esc_html( $chef_role ); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ( $attendee_note ) : ?>
        <div class="dish-attendee-note dish-containers">
            <p>Note from the chef: <?php echo wp_kses_post( nl2br( $attendee_note ) ); ?></p>
        </div>
    <?php endif; ?>
            
    </aside>
</div>

<?php /* ── Upcoming Classes ─────────────────────────────────────────── */ ?>
<?php include __DIR__ . '/upcoming.php'; ?>

<?php endwhile; ?>

<?php get_footer(); ?>
