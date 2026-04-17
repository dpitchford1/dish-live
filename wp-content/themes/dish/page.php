<?php get_header(); ?>

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
                <p class="hero--excerpt"><?php // the_excerpt(); ?></p>
            <?php endif; ?>
            </div>
            <div class="hero-buttons">
                <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_format' ) ); ?>" class="button button--primary"><?php esc_html_e( 'Class Formats', 'dish-events' ); ?></a>
                <a href="/classes/class-menus/" class="button button--primary"><?php esc_html_e( 'Class Menus', 'dish-events' ); ?></a>
            </div>
        </div>
    </div>
</section>

<main id="main-content" class="main--content">

    <section class="content-region fluid-content">

        <article class="entry--content"><?php the_content(); ?></article>

        <?php if ( has_excerpt() ) : ?>
            <p class="excerpt"><?php the_excerpt(); ?></p>
        <?php endif; ?>

        <?php // echo do_shortcode( '[dish_classes view="calendar"]' ); ?>

    </section> 

<?php endif; ?><?php /* ── end while have_posts */ ?>

</main>
<?php get_footer(); ?>
