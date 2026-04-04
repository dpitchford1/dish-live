<?php
/**
 * Template: dish_class single page.
 *
 * Displays a dated class instance — hero image, meta (date, price, spots),
 * chef(s), content (instance override or template content), and a booking CTA.
 * The booking section is a stub; Phase 9 will replace it with the full checkout.
 *
 * Also used by [dish_class id="N"] shortcode via ClassView::render_single().
 *
 * Theme override: {theme}/dish-events/classes/single.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Data\BookingRepository;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\ClassTemplateRepository;
use Dish\Events\Data\ChefRepository;
use Dish\Events\Helpers\DateHelper;
use Dish\Events\Helpers\MoneyHelper;

get_header();

while ( have_posts() ) :
	the_post();

	$class_id    = get_the_ID();
	$template_id = (int) get_post_meta( $class_id, 'dish_template_id', true );
	$template    = $template_id ? get_post( $template_id ) : null;

	// Dates.
	$start = (int) get_post_meta( $class_id, 'dish_start_datetime', true );
	$end   = (int) get_post_meta( $class_id, 'dish_end_datetime', true );

	// Content: use instance override if set, otherwise fall back to template body.
	$content_override = get_post_meta( $class_id, 'dish_content_override', true );
	$display_content  = $content_override
		? apply_filters( 'the_content', wp_unslash( $content_override ) )
		: ( $template ? apply_filters( 'the_content', $template->post_content ) : '' );

	// Chefs.
	$chef_ids      = ClassRepository::get_chef_ids( $class_id );
	$chefs         = $chef_ids ? ChefRepository::query( [ 'post__in' => $chef_ids, 'orderby' => 'post__in' ] ) : [];
	$is_guest_chef = $template_id ? (bool) get_post_meta( $template_id, 'dish_is_guest_chef', true ) : false;

	// Price & spots.
	$ticket_type = $template_id ? ClassTemplateRepository::get_ticket_type( $template_id ) : null;
	$price_label = $ticket_type ? MoneyHelper::cents_to_display( (int) $ticket_type->price_cents ) : '';
	$capacity    = $ticket_type ? (int) $ticket_type->capacity : 0;
	$booked      = ClassRepository::get_booked_count( $class_id );
	$remaining   = $capacity > 0 ? max( 0, $capacity - $booked ) : 0;
	$is_sold_out = $capacity > 0 && $remaining <= 0;
	$is_private  = (bool) get_post_meta( $class_id, 'dish_is_private', true );

	// Seats already booked by the current logged-in user for this instance.
	$my_seats = 0;
	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		$my_seats     = BookingRepository::get_customer_seat_count(
			$class_id,
			$current_user->user_email,
			(int) $current_user->ID
		);
	}

	// Format / breadcrumb.
	$format_id    = $template_id ? (int) get_post_meta( $template_id, 'dish_format_id', true ) : 0;
	$format_post  = $format_id   ? get_post( $format_id ) : null;
	$format_color = $format_post ? ( (string) get_post_meta( $format_id, 'dish_format_color', true ) ?: '#c0392b' ) : '';
	?>

<main id="main-content" class="main--content">
    <article id="post-<?php the_ID(); ?>">

        <?php
        // Hero image: prefer class thumbnail, fall back to template.
        $thumb_id = get_post_thumbnail_id( $class_id ) ?: ( $template ? get_post_thumbnail_id( $template->ID ) : 0 );
        if ( $thumb_id ) :
        ?>
            <div class="dish-class-hero">
                <?php Basecamp_Frontend::picture( (int) $thumb_id, [
                    'landscape_size' => 'basecamp-img-xl',
                    'img_class'      => 'dish-hero__img',
                    'loading'        => 'eager',
                    'fetchpriority'  => 'high',
                ] ); ?>
            </div>
        <?php endif; ?>

        <header class="dish-class-header dish-container">

			<?php dish_the_breadcrumb(); ?>

            <h1 class="dish-class-title">
                <?php echo esc_html( $template ? $template->post_title : get_the_title() ); ?>
            </h1>

            <?php // Meta row: date, duration, price, spots. ?>
            <div class="dish-class-meta">

                <?php if ( $start ) : ?>
                    <div class="dish-class-meta__item">
                        <span class="dish-class-meta__label"><?php esc_html_e( 'Date & Time', 'dish-events' ); ?></span>
                        <time class="dish-class-meta__value" datetime="<?php echo esc_attr( DateHelper::format( $start, 'c' ) ); ?>">
                            <?php echo esc_html( DateHelper::to_display( $start ) ); ?>
                        </time>
                    </div>
                <?php endif; ?>

                <?php if ( $end && $end > $start ) : ?>
                    <div class="dish-class-meta__item">
                        <span class="dish-class-meta__label"><?php esc_html_e( 'Ends', 'dish-events' ); ?></span>
                        <time class="dish-class-meta__value" datetime="<?php echo esc_attr( DateHelper::format( $end, 'c' ) ); ?>">
                            <?php echo esc_html( DateHelper::to_display( $end ) ); ?>
                        </time>
                    </div>
                <?php endif; ?>

                <?php if ( $price_label ) : ?>
                    <div class="dish-class-meta__item">
                        <span class="dish-class-meta__label"><?php esc_html_e( 'Price', 'dish-events' ); ?></span>
                        <span class="dish-class-meta__value dish-class-meta__price">
                            <?php echo esc_html( $price_label ); ?><?php esc_html_e( ' per person', 'dish-events' ); ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ( $capacity > 0 ) : ?>
                    <div class="dish-class-meta__item">
                        <span class="dish-class-meta__label"><?php esc_html_e( 'Availability', 'dish-events' ); ?></span>
                        <?php if ( $is_sold_out ) : ?>
                            <span class="dish-class-meta__value dish-class-meta__spots--sold-out">
                                <?php esc_html_e( 'Sold out', 'dish-events' ); ?>
                            </span>
                        <?php else : ?>
                            <span class="dish-class-meta__value">
                                <?php
                                /* translators: %d: spots remaining */
                                printf( esc_html( _n( '%d spot remaining', '%d spots remaining', $remaining, 'dish-events' ) ), (int) $remaining );
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div><!-- .dish-class-meta -->

            <?php if ( ! empty( $chefs ) ) : ?>
                <div class="dish-class-chefs">
                    <span class="dish-class-chefs__label">
                        <?php echo $is_guest_chef ? esc_html__( 'Guest Chef', 'dish-events' ) : esc_html__( 'With', 'dish-events' ); ?>
                    </span>
                    <ul class="dish-class-chefs__list">
                        <?php foreach ( $chefs as $chef ) : ?>
                            <li>
                                <a href="<?php echo esc_url( get_permalink( $chef->ID ) ); ?>">
                                    <?php echo esc_html( $chef->post_title ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

        </header>

        <?php if ( $display_content ) : ?>
            <div class="dish-class-content dish-content dish-container">
                <?php echo wp_kses_post( $display_content ); ?>
            </div>
        <?php endif; ?>

        <?php /* Menu — items + dietary flags from the linked class template. */ ?>
        <?php
            $_m_menu_items    = $template_id ? (string) get_post_meta( $template_id, 'dish_menu_items',           true ) : '';
            $_m_menu_dietary  = $template_id ? (array)  json_decode( get_post_meta( $template_id, 'dish_menu_dietary_flags', true ) ?: '[]', true ) : [];
            $_m_menu_friendly = $template_id ? (array)  json_decode( get_post_meta( $template_id, 'dish_menu_friendly_for',  true ) ?: '[]', true ) : [];
        ?>
        <?php if ( $_m_menu_items || $_m_menu_dietary ) : ?>
            <section class="dish-class-menu dish-container" aria-label="<?php esc_attr_e( 'Class menu', 'dish-events' ); ?>">
                <h2 class="dish-class-menu__heading"><?php esc_html_e( 'The Menu', 'dish-events' ); ?></h2>

                <?php if ( $_m_menu_items ) :
                    $_m_items = array_filter( array_map( 'trim', explode( "\n", $_m_menu_items ) ) );
                ?>
                    <ul class="dish-menu-list">
                        <?php foreach ( $_m_items as $_m_item ) : ?>
                            <li class="dish-menu-list__item"><?php echo esc_html( $_m_item ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ( $_m_menu_dietary || $_m_menu_friendly ) :
                    $_m_flag_labels     = \Dish\Events\Admin\MenuMetaBox::DIETARY_FLAGS;
                    $_m_friendly_labels = \Dish\Events\Admin\MenuMetaBox::FRIENDLY_FOR;
                ?>
                    <div class="dish-menu-dietary">
                        <?php if ( $_m_menu_dietary ) :
                            $_m_flag_display = array_map(
                                fn( $k ) => $_m_flag_labels[ $k ] ?? ucfirst( str_replace( '_', ' ', $k ) ),
                                $_m_menu_dietary
                            );
                        ?>
                            <p class="dish-menu-dietary__flags">
                                <strong><?php esc_html_e( 'Dietary Flags:', 'dish-events' ); ?></strong>
                                <?php echo esc_html( implode( ', ', $_m_flag_display ) ); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ( $_m_menu_friendly ) :
                            $_m_friendly_display = array_map(
                                fn( $k ) => $_m_friendly_labels[ $k ] ?? ucfirst( str_replace( '_', ' ', $k ) ),
                                $_m_menu_friendly
                            );
                        ?>
                            <p class="dish-menu-dietary__friendly">
                                <?php echo esc_html( implode( '/', $_m_friendly_display ) . ' ' . __( 'Friendly', 'dish-events' ) ); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ( $_m_menu_dietary ) : ?>
                            <p class="dish-menu-dietary__disclaimer">
                                <?php esc_html_e( 'Please contact us if any of the above dietary flags apply to you to ensure we can accommodate your dietary requirements.', 'dish-events' ); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </section>
        <?php endif; ?>

        <?php if ( $my_seats > 0 ) : ?>
            <div class="dish-class-my-booking dish-container">
                <p class="dish-class-my-booking__notice">
                    <?php
                    printf(
                        esc_html(
                            /* translators: %d: number of seats booked by this user */
                            _n( "You've already booked %d seat for this class.", "You've already booked %d seats for this class.", $my_seats, 'dish-events' )
                        ),
                        (int) $my_seats
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <?php // Booking CTA ?>
        <div class="dish-class-booking dish-container">
            <?php if ( $is_private ) : ?>
                <p class="dish-booking-private">
                    <?php esc_html_e( 'Private event', 'dish-events' ); ?>
                </p>
            <?php elseif ( $is_sold_out ) : ?>
                <p class="dish-booking-sold-out">
                    <?php esc_html_e( 'This class is sold out.', 'dish-events' ); ?>
                </p>
                <p class="dish-waitlist-hint">
                    <?php esc_html_e( 'Waitlist coming soon — check back or contact us to be notified.', 'dish-events' ); ?>
                </p>
            <?php else : ?>
                <p class="dish-booking-cta-notice">
                    <?php esc_html_e( 'Online booking coming soon — please contact us to reserve your spot.', 'dish-events' ); ?>
                </p>
            <?php endif; ?>
        </div>

    </article>
</main>

<?php endwhile; ?>

<?php get_footer(); ?>
