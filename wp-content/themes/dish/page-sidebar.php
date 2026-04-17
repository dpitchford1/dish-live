<?php
/*
 Template Name: Sidebar Page
 
 * This is a default page with a sidebar. 
 * 
 * 
 * For more info: http://codex.wordpress.org/Page_Templates
 * 
 * Visual interactive WordPress template hierarchy: https://wphierarchy.com
*/
?>

<?php get_header(); ?>

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
                <!-- <p class="hero--excerpt"><?php // the_excerpt(); ?></p> -->
            <?php endif; ?>
            </div>
            <!-- <div class="hero-buttons">
                <a href="/classes/calendar/" class="button button--primary"><?php esc_html_e( 'View Calendar', 'dish-events' ); ?></a>
                <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_format' ) ); ?>" class="button button--primary"><?php esc_html_e( 'Class Formats', 'dish-events' ); ?></a>
            </div> -->
        </div>
    </div>
</section>

<?php /* ── Hero ─────────────────────────────────────────── */ ?>
<?php if ( has_post_thumbnail() ) : ?>
<!-- <div class="hero has--feature-content">
    <?php Basecamp_Frontend::picture( get_post_thumbnail_id(), [
        'landscape_size' => 'basecamp-img-xl',
        'loading'        => 'eager',
        'fetchpriority'  => 'high',
        'img_class'      => 'hero-img size-basecamp-img-xl',
    ] ); ?>
    <div class="hero-feature--content text--centered">
        <h1 class="hero-title"><?php the_title(); ?></h1>
        <?php if ( has_excerpt() ) : ?>
            <p class="hero-excerpt"><?php // the_excerpt(); ?></p>
        <?php endif; ?>
    </div>
</div> -->
<?php endif; ?>

<?php the_subnav(); ?>
<div class="fluid-content has--aside">

<main id="main-content" class="main--content inner--content">

    <?php // Edit the loop in /templates/loop. Or roll your own. ?>
    <?php get_template_part( 'templates/loop'); ?>

</main>

<?php get_sidebar(); ?>

</div>
<?php get_footer(); ?>
