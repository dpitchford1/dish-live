<?php get_header(); ?>

<main id="main-content" class="main--content fluid">

    <?php // Edit the loop in /templates/archive-loop. Or roll your own. ?>
    <?php get_template_part( 'templates/archive', 'loop'); ?>

</main>

<?php // get_sidebar(); ?>

<?php get_footer(); ?>
