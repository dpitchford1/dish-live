<?php
/**
 * Template Name: Dish Home
 *
 * Homepage template — pulls live Dish Events data into distinct sections:
 *   1. Hero     — page title + excerpt from the WP page itself
 *   2. Formats  — all published class formats (card grid)
 *   3. Upcoming — next 6 upcoming public dish_class instances
 *   4. Chefs    — all published chefs (card grid)
 *
 * Assign this template to whatever page is set as the static front page.
 *
 * @package basecamp
 */

use Dish\Events\Data\ChefRepository;
use Dish\Events\Frontend\Frontend;
use Dish\Events\Helpers\DateHelper;
use Dish\Events\Helpers\MoneyHelper;
use Dish\Events\Data\ClassTemplateRepository;

get_header();
?>
<main id="main-content" class="main--content">
<?php /* ── 1. Hero ─────────────────────────────────────────────────────── */ ?>
<?php if ( have_posts() ) : the_post(); ?>
<section class="dish-home-hero fluid">

    <h2 class="dish-home-hero__title"><?php the_title(); ?></h2>

    <div><?php the_content(); ?></div>

    <?php if ( has_excerpt() ) : ?>
        <p class="dish-home-hero__lead"><?php the_excerpt(); ?></p>
    <?php endif; ?>

</section>
<?php endif; ?>

<?php /* ── 2. Class Formats ────────────────────────────────────────────── */ ?>
<?php
$formats = get_posts( [
	'post_type'      => 'dish_format',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
] );
?>
<?php if ( ! empty( $formats ) ) : ?>
<section class="dish-home-section dish-home-formats fluid">

    <h2 class="dish-home-section__heading"><?php esc_html_e( 'Browse by Format', 'dish-events' ); ?></h2>
    <div class="grid-general grid--3col">
        <?php foreach ( $formats as $format ) : ?>
            <?php include Frontend::locate( 'formats/card.php' ); ?>
        <?php endforeach; ?>
    </div>
    <p class="dish-home-section__more">
        <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_format' ) ); ?>" class="button">
            <?php esc_html_e( 'View All Formats', 'dish-events' ); ?>
        </a>
    </p>

</section>
<?php endif; ?>

<?php /* ── 3. Upcoming Classes ─────────────────────────────────────────── */ ?>
<?php
$upcoming = get_posts( [
	'post_type'      => 'dish_class',
	'post_status'    => 'publish',
	'posts_per_page' => 6,
	'orderby'        => 'meta_value_num',
	'meta_key'       => 'dish_start_datetime',
	'order'          => 'ASC',
	'meta_query'     => [
		'relation' => 'AND',
		[
			'key'     => 'dish_start_datetime',
			'value'   => time(),
			'compare' => '>=',
			'type'    => 'NUMERIC',
		],
		[
			'relation' => 'OR',
			[ 'key' => 'dish_is_private', 'compare' => 'NOT EXISTS' ],
			[ 'key' => 'dish_is_private', 'value' => '1', 'compare' => '!=' ],
		],
	],
] );
?>
<?php if ( ! empty( $upcoming ) ) : ?>
<section class="dish-home-section dish-home-upcoming fluid">

    <h2 class="dish-home-section__heading"><?php esc_html_e( 'Upcoming Classes', 'dish-events' ); ?></h2>
    <div class="grid-general grid--3col">
        <?php foreach ( $upcoming as $class ) : ?>
            <?php
            $template_id  = (int) get_post_meta( $class->ID, 'dish_template_id', true );
            $start        = (int) get_post_meta( $class->ID, 'dish_start_datetime', true );
            $ticket_type  = $template_id ? ClassTemplateRepository::get_ticket_type( $template_id ) : null;
            $price_label  = $ticket_type ? MoneyHelper::cents_to_display( (int) $ticket_type->price_cents ) : '';
            $card_url     = $template_id ? get_permalink( $template_id ) . '?class_id=' . $class->ID : '#';
            $date_str     = $start ? DateHelper::format( $start, 'j M Y · g:i a' ) : '';
            $thumb        = $template_id && has_post_thumbnail( $template_id )
                ? get_the_post_thumbnail( $template_id, 'medium' )
                : '';
            ?>
            <article class="dish-card dish-class-card" id="class-<?php echo esc_attr( $class->ID ); ?>">
                <?php if ( $thumb ) : ?>
                    <a href="<?php echo esc_url( $card_url ); ?>" class="dish-card__image-link" tabindex="-1" aria-hidden="true">
                        <?php echo $thumb; ?>
                    </a>
                <?php endif; ?>
                <div class="dish-card__body">
                    <h3 class="dish-card__title">
                        <a href="<?php echo esc_url( $card_url ); ?>">
                            <?php echo esc_html( get_the_title( $template_id ?: $class->ID ) ); ?>
                        </a>
                    </h3>
                    <?php if ( $date_str ) : ?>
                        <p class="dish-card__date"><?php echo esc_html( $date_str ); ?></p>
                    <?php endif; ?>
                    <?php if ( $price_label ) : ?>
                        <p class="dish-card__price"><?php echo esc_html( $price_label ); ?></p>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( $card_url ); ?>" class="dish-card__link button">
                        <?php esc_html_e( 'Book Now', 'dish-events' ); ?>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

</section>
<?php endif; ?>

<?php /* ── 4. Meet the Chefs ────────────────────────────────────────────── */ ?>
<?php $chefs = ChefRepository::query( [ 'exclude_team' => true ] ); ?>
<?php if ( ! empty( $chefs ) ) : ?>
<section class="dish-home-section dish-home-chefs fluid">

    <h2 class="dish-home-section__heading"><?php esc_html_e( 'Meet the Chefs', 'dish-events' ); ?></h2>

    <div class="grid-general grid--4col">
        <?php foreach ( $chefs as $chef ) : ?>
            <?php include Frontend::locate( 'chefs/card.php' ); ?>
        <?php endforeach; ?>
    </div>

    <p class="dish-home-section__more">
        <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_chef' ) ); ?>" class="button">
            <?php esc_html_e( 'Meet All Chefs', 'dish-events' ); ?>
        </a>
    </p>

</section>
<?php endif; ?>

</main>
<?php /* Main End */ ?>
<?php get_footer(); ?>
