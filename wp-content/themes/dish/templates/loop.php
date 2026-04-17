<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

<article class="entry--content">
    <?php the_content(); ?>
</article>

<?php endwhile; endif; ?>