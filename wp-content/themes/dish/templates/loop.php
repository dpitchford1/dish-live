<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

<section class="">

    <?php get_template_part( 'templates/header', 'title'); ?>

    <?php // Delete or comment out if you don't need this on your page or post. Edit in /templates/byline.php ?>
    <?php // get_template_part( 'templates/byline'); ?>

    <article class="entry--content">
        <?php the_content(); ?>
    </article>

</section> <?php // end article ?>

<?php endwhile; endif; ?>