<?php get_header(); ?>

<main id="main-content" class="main--content fluid">

    <section class="error-404 not-found">

        <?php get_template_part( 'templates/header', 'title'); ?>

        <article id="post-not-found" class="">

            <div class="404-txt">

                <h3><?php _e( 'I\'m sorry Dave, I\'m afraid I can\'t do that.', 'basecamp' ); ?></h3>
                <p>We couldn't find what you are looking for, please try searching.</p>

            </div>

        </article>

    </section>

</main>

<?php get_footer(); ?>
