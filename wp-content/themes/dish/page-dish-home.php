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
<?php if ( has_post_thumbnail() ) : ?>
<div class="hero has--feature-content">
    <?php Basecamp_Frontend::picture( get_post_thumbnail_id(), [
        'landscape_size' => 'basecamp-img-xl',
        'loading'        => 'eager',
        'fetchpriority'  => 'high',
        'img_class'      => 'hero-img size-basecamp-img-xl',
    ] ); ?>
    <div class="hero-feature--content">
        <h1 class="hero-title"><?php the_title(); ?></h1>
        <?php if ( has_excerpt() ) : ?>
            <p class="hero-excerpt"><?php the_excerpt(); ?></p>
        <?php endif; ?>
        <div class="hero-buttons">
            <a href="/classes/calendar/" class="button button--primary"><?php esc_html_e( 'View Calendar', 'dish-events' ); ?></a>
            <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_format' ) ); ?>" class="button button--primary"><?php esc_html_e( 'Class Formats', 'dish-events' ); ?></a>
        </div>
    </div>
</div>
<?php endif; ?>
<main id="main-content" class="main--content">
<?php // var_dump( wp_get_attachment_metadata( get_post_thumbnail_id( $promo_page->ID ) )['sizes'] ?? [] ); ?>
<?php /* ── 1. Hero ─────────────────────────────────────────────────────── */ ?>

<section class="content-region fluid-content">

    <!-- <h1 class="page--heading"><?php the_title(); ?></h1> -->

    <article class="entry--content text--centered"><?php the_content(); ?></article>

    <?php if ( has_excerpt() ) : ?>
        <p class="excerpt"><?php the_excerpt(); ?></p>
    <?php endif; ?>

</section>

<?php endif; ?><?php /* ── end while have_posts */ ?>

<section class="content-region spotlight-wrapper fluid-content">
    <div class="grid-general grid--3col">
        <div class="region">
            <h2>Cooking with Confidence</h2>
            <p>Our classes are designed for home cooks of all skill levels. Whether you're a beginner looking to learn the basics or an experienced cook aiming to refine your techniques, we have something for everyone.</p>
        </div>
        <div class="region">
            <h2>World-Class Chefs</h2>
            <p>Learn from the best in the industry. Our chefs bring a wealth of experience and a passion for teaching, ensuring that you receive top-notch instruction in every class.</p>
        </div>
        <div class="region">
            <h2>Community & Connection</h2>
            <p>Join a vibrant community of food enthusiasts. Share your culinary journey, exchange tips, and connect with fellow home cooks who share your passion for great food.</p>
        </div>
</section>

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
<section class="content-region fluid-content">
    <h2 class=""><?php esc_html_e( 'Class Formats', 'dish-events' ); ?></h2>
    <div class="grid-general grid--3col">
        <?php foreach ( $formats as $format ) : ?>
            <?php include Frontend::locate( 'formats/card.php' ); ?>
        <?php endforeach; ?>
    </div>
    <p class="region">
        <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_format' ) ); ?>" class="button"><?php esc_html_e( 'View All Formats', 'dish-events' ); ?></a>
    </p>

</section>
<?php endif; ?>

<?php
/* ── Promo Pages — Gift Cards + Our Store ───────────────────────────── */
// Slugs must match the page slugs set in WP Admin.
$promo_pages = array_filter( [
	get_page_by_path( 'gift-cards' ),
	get_page_by_path( 'our-store' ),
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
            <h2><a href="<?php echo esc_url( get_permalink( $promo_page->ID ) ); ?>"><?php echo esc_html( get_the_title( $promo_page->ID ) ); ?></a></h2>
            <?php $excerpt = get_the_excerpt( $promo_page ); ?>
            <?php if ( $excerpt ) : ?>
                <p><?php echo esc_html( $excerpt ); ?></p>
            <?php endif; ?>
            <a href="<?php echo esc_url( get_permalink( $promo_page->ID ) ); ?>" class="button"><?php esc_html_e( 'Read More', 'basecamp' ); ?></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php /* ── 3. Upcoming Classes ─────────────────────────────────────────── */ ?>
<?php dish_the_upcoming_classes( [
	'limit'         => 6,
	'section_class' => 'dish-home-upcoming fluid-content',
	'grid_class'    => 'grid-general grid--3col',
    'dedupe_by_template'   => true,
] ); ?>

<?php /* ── 3.5. Class in the Spotlight ────────────────────────────────── */ ?>
<?php dish_the_spotlight_class(); ?>

<?php /* ── 4. Meet the Chefs ────────────────────────────────────────────── */ ?>
<?php $chefs = ChefRepository::query( [ 'exclude_team' => true ] ); ?>
<?php if ( ! empty( $chefs ) ) : ?>
<section class="content-region fluid-content">

    <h2 class=""><?php esc_html_e( 'Meet the Chefs', 'dish-events' ); ?></h2>

    <div class="grid-general grid--4col">
        <?php foreach ( $chefs as $chef ) : ?>
            <?php include Frontend::locate( 'chefs/card.php' ); ?>
        <?php endforeach; ?>
    </div>

    <p class="region"><a href="<?php echo esc_url( get_post_type_archive_link( 'dish_chef' ) ); ?>" class="button"><?php esc_html_e( 'Meet The Whole Team', 'dish-events' ); ?></a></p>

</section>
<?php endif; ?>

</main>
<?php /* Main End */ ?>
<?php get_footer(); ?>
