<?php
/*
 Template Name: Classes Template
 
 * This is a Classes page with a sidebar. 
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
        </div>
    </div>
</section>
<div class="fluid-content has--aside">
    <main id="main-content" class="main--content inner--content">

        <?php // Edit the loop in /templates/loop. Or roll your own. ?>
        <?php // get_template_part( 'templates/loop'); ?>
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>

        <article class="entry--contents">
            <?php the_content(); ?>
        </article>

        <?php endwhile; endif; ?>

    </main>
    <?php get_sidebar(); ?>
    </div>
<?php get_footer(); ?>
