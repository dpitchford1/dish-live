<?php get_header(); ?>
<?php /* ── Hero ─────────────────────────────────────────── */ ?>
<?php if ( has_post_thumbnail() ) : ?>
<div class="hero has--feature-content">
    <?php Basecamp_Frontend::picture( get_post_thumbnail_id(), [
        'landscape_size' => 'basecamp-img-xl',
        'loading'        => 'eager',
        'fetchpriority'  => 'high',
    ] ); ?>
</div>
<?php endif; ?>
<main id="main-content" class="main--content fluid">

    <?php // Edit the loop in /templates/archive-loop. Or roll your own. ?>
    <?php get_template_part( 'templates/archive', 'loop'); ?>

</main>

<?php // get_sidebar(); ?>

<?php get_footer(); ?>
