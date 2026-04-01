<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

<section class="">

    <div class="">
        <?php get_template_part( 'templates/header', 'title'); ?>
        <?php // Delete or comment out if you don't need this on your page or post. Edit in /templates/byline.php ?>
        <?php get_template_part( 'templates/byline'); ?>  
    </div> <?php // end article header ?>

    <article class="">
        <?php if ( has_post_format()) { 
            get_template_part( 'format', get_post_format() ); }
        ?>
        <?php the_content(); ?>
    </article> <?php // end article section ?>

    <div class="">
        <?php get_template_part( 'templates/category-tags'); ?>
    </div> <?php // end article footer ?>

</section> <?php // end article ?>

<?php endwhile; endif; ?>