<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

<section class="">

    <div class="">
        <?php get_template_part( 'templates/header', 'title'); ?>
        <?php get_template_part( 'templates/byline'); ?>
    </div>

    <article class="">               
        <?php the_content(); ?>
    </article>
    
    <div class="">
        <?php get_template_part( 'templates/category-tags'); ?>
    </div>

</section>

<?php endwhile; endif; ?>

<?php get_template_part( 'templates/post-navigation'); ?>