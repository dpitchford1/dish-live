<?php get_header(); ?>

<main id="main-content" class="main--content fluid">

    <?php // Edit the loop in /templates/index-loop. Or roll your own. ?>
    <?php get_template_part( 'templates/index','loop'); ?>

</main>

<?php // get_sidebar(); ?>

<?php get_footer(); ?>
