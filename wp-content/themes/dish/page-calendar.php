<?php
/*
 Template Name: Calendar Template
 
 * This is a Calendar page - full width. 
 * 
 * 
 * For more info: http://codex.wordpress.org/Page_Templates
 * 
 * Visual interactive WordPress template hierarchy: https://wphierarchy.com
*/
?>
<?php get_header(); ?>

<?php if ( have_posts() ) : the_post(); ?>

<?php /* ── Hero ─────────────────────────────────────────── */ ?>
<section class="global--hero">
    <?php if ( has_post_thumbnail() ) : ?>

        <?php Basecamp_Frontend::picture( get_post_thumbnail_id(), [
        'landscape_size' => 'basecamp-img-xl',
        'loading'        => 'eager',
        'fetchpriority'  => 'high',
        'img_class'      => 'hero--img size-basecamp-img-xl',
    ] ); ?>
    <?php endif; ?>
    <div class="hero--wrapper">
        <div class="hero--text-block">
            <div class="hero--cta">
            <div class="hero--content">
                <h1 class="hero--heading"><?php the_title(); ?></h1>
            </div> 
            <div class="hero-buttons">
                <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_format' ) ); ?>" class="button button--primary"><?php esc_html_e( 'Class Formats', 'dish-events' ); ?></a>
                <a href="/classes/class-menus/" class="button button--primary"><?php esc_html_e( 'Class Menus', 'dish-events' ); ?></a>
            </div>
            </div>
        </div>
    </div>
</section>

<main id="main-content" class="main--content inner--content">

    <section class="content-region fluid-content">
        <article class="entry--content entry--blurb text--centered"><?php the_content(); ?></article>
        <?php echo do_shortcode( '[dish_classes view="calendar"]' ); ?>

        <p class="text--centered"><strong><a href="/about-dish/cancellation-policy/">Please review our cancellation policy for classes</a>.</strong></p>
    </section> 
<?php endif; ?><?php /* ── end while have_posts */ ?>
</main>
<?php get_footer(); ?>
