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

get_header();
?>
<?php if ( have_posts() ) : the_post(); ?>

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
        <div class="hero--text-block animate-slide-down">
            <div class="hero--content">
                <h1 class="hero--heading"><?php the_title(); ?></h1>
            <?php if ( has_excerpt() ) : ?>
                <p class="hero-excerpt"><?php // the_excerpt(); ?></p>
            <?php endif; ?>
            </div>
            <div class="hero-buttons">
                <a href="/classes/calendar/" class="button button--primary"><?php esc_html_e( 'View Calendar', 'dish-events' ); ?></a>
                <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_format' ) ); ?>" class="button button--primary"><?php esc_html_e( 'Class Formats', 'dish-events' ); ?></a>
            </div>
        </div>
    </div>
</section>

<main id="main-content" class="main--content">
    <?php /* ── Homepage Blurb */ ?>
    <section class="content-region fluid-content">
        <article class="entry--content text--centered entry--blurb"><?php the_content(); ?></article>
        <?php if ( has_excerpt() ) : ?>
            <p class="excerpt"><?php the_excerpt(); ?></p>
        <?php endif; ?>
    </section>
<?php endif; ?><?php /* ── end while have_posts */ ?>

<?php
$_home_id = get_queried_object_id();
$_blocks  = [];
for ( $i = 1; $i <= 3; $i++ ) {
	$_blocks[] = [
		'title' => (string) get_post_meta( $_home_id, "dish_home_block_{$i}_title", true ),
		'text'  => (string) get_post_meta( $_home_id, "dish_home_block_{$i}_text",  true ),
	];
}
$_has_blocks = (bool) array_filter( $_blocks, fn( $b ) => $b['title'] !== '' || $b['text'] !== '' );
?>
<?php if ( $_has_blocks ) : ?>
<section class="content-region spotlight-wrapper fluid-content">
    <div class="grid-general grid--3col">
        <?php foreach ( $_blocks as $_block ) : ?>
            <?php if ( $_block['title'] !== '' || $_block['text'] !== '' ) : ?>
            <div class="region">
                <?php if ( $_block['title'] !== '' ) : ?>
                    <h3 class="section-title"><?php echo esc_html( $_block['title'] ); ?></h3>
                <?php endif; ?>
                <?php if ( $_block['text'] !== '' ) : ?>
                    <div class="region__text"><?php echo wp_kses_post( $_block['text'] ); ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php /* ── 3. Upcoming Classes ─────────────────────────────────────────── */ ?>
<?php dish_the_upcoming_classes( [
	'limit'         => 4,
	'section_class' => 'dish-home-upcoming fluid-content',
	'grid_class'    => 'grid-general grid--4col',
    'dedupe_by_template'   => true,
] ); ?>

<?php
/* ── Promo Pages — Gift Cards + Our Store ───────────────────────────── */
// Slugs must match the page slugs set in WP Admin.
$promo_pages = array_filter( [
	get_page_by_path( 'gift-cards' ),
	get_page_by_path( 'prepared-foods' ),
] );
?>
<?php if ( ! empty( $promo_pages ) ) : ?>
<section class="content-region spotlight-wrapper fluid-content">
    <div class="grid-general grid--2col">
        <?php foreach ( $promo_pages as $promo_page ) : ?>
        <div class="region mini-card">
            <?php if ( has_post_thumbnail( $promo_page->ID ) ) : ?>
                <?php echo wp_get_attachment_image( get_post_thumbnail_id( $promo_page->ID ), 'basecamp-img-sq-sm', false, [ 'loading' => 'lazy', 'class' => 'mini-card--image' ] ); ?> 
            <?php endif; ?>
            <div class="mini-card--content">
            <h2 class="section-title"><a href="<?php echo esc_url( get_permalink( $promo_page->ID ) ); ?>"><?php echo esc_html( get_the_title( $promo_page->ID ) ); ?></a></h2>
            <?php $excerpt = get_the_excerpt( $promo_page ); ?>
            <?php if ( $excerpt ) : ?>
                <p><?php echo esc_html( $excerpt ); ?></p>
            <?php endif; ?>
            <a href="<?php echo esc_url( get_permalink( $promo_page->ID ) ); ?>" class=""><?php esc_html_e( 'More about', 'basecamp' ); ?> <?php echo esc_html( get_the_title( $promo_page->ID ) ); ?></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php /* ── 2. Class Formats ────────────────────────────────────────────── */ ?>
<?php
$formats = get_posts( [
	'post_type'      => 'dish_format',
	'post_status'    => 'publish',
	'posts_per_page' => 3,
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
	'meta_query'     => [
		'relation' => 'OR',
		[
			'key'     => 'dish_format_is_private',
			'compare' => 'NOT EXISTS',
		],
		[
			'key'     => 'dish_format_is_private',
			'value'   => '1',
			'compare' => '!=',
		],
	],
] );
?>
<?php if ( ! empty( $formats ) ) : ?>
<section class="content-region fluid-content">
    <h2 class="section-heading"><?php esc_html_e( 'Class Formats', 'dish-events' ); ?></h2>
    <div class="grid-general grid--3col">
        <?php foreach ( $formats as $format ) : ?>
            <?php include Frontend::locate( 'formats/card.php' ); ?>
        <?php endforeach; ?>
    </div>
    <p class="region">
        <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_format' ) ); ?>" class="button button--primary"><?php esc_html_e( 'View All Formats', 'dish-events' ); ?></a>
    </p>
</section>
<?php endif; ?>

<?php /* ── 3.5. Class in the Spotlight ────────────────────────────────── */ ?>
<?php dish_the_spotlight_class(); ?>

<?php /* ── 4. Meet the Chefs ────────────────────────────────────────────── */ ?>
<?php $chefs = ChefRepository::query( [ 'exclude_team' => true ] ); ?>
<?php if ( ! empty( $chefs ) ) : ?>
<section class="content-region fluid-content">

    <h2 class="section-heading"><?php esc_html_e( 'Meet the Chefs', 'dish-events' ); ?></h2>

    <div class="grid-general grid--4col">
        <?php foreach ( $chefs as $chef ) : ?>
            <?php include Frontend::locate( 'chefs/card.php' ); ?>
        <?php endforeach; ?>
    </div>

    <p class="region"><a href="<?php echo esc_url( get_post_type_archive_link( 'dish_chef' ) ); ?>" class="button"><?php esc_html_e( 'Meet The Whole Team', 'dish-events' ); ?></a></p>

</section>
<?php endif; ?>

<section class="content-region fluid-content">
    <h2 class="section-heading"><?php esc_html_e( 'Corporate & Private Event Types', 'dish-events' ); ?></h2>
    <?php echo do_shortcode( '[dish_class_types private_only="1" limit="8" columns="4"]' ); ?>
</section>

</main>
<?php /* Main End */ ?>
<?php get_footer(); ?>
