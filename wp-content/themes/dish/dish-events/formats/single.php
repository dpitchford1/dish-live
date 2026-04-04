<?php
/**
 * Template: dish_format single page.
 *
 * Displays the format's editorial content (title, featured image, body)
 * followed by:
 *   1. Upcoming scheduled classes for this format (when any exist)
 *   2. All published class templates in this format — always present
 *
 * Theme override: {theme}/dish-events/formats/single.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Data\ClassRepository;

get_header();

while ( have_posts() ) :
	the_post();

	$format_id    = get_the_ID();
	$format_title = get_the_title();

	// All published templates for this format.
	$templates = get_posts( [
		'post_type'      => 'dish_class_template',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => [ [
			'key'   => 'dish_format_id',
			'value' => $format_id,
			'type'  => 'NUMERIC',
		] ],
	] );

	// Upcoming scheduled class instances for this format's templates.
	$upcoming_classes = [];
	if ( ! empty( $templates ) ) {
		$template_ids = wp_list_pluck( $templates, 'ID' );
		$upcoming_classes = ClassRepository::query( [
			'template_ids' => $template_ids,
			'start_after'  => time(),
			'is_private'   => false,
			'limit'        => -1,
			'order'        => 'ASC',
		] );
	}
	?>

<main id="main-content" class="main--content">
    <article id="post-<?php the_ID(); ?>">

        <?php if ( has_post_thumbnail() ) : ?>
            <div class="dish-format-hero">
                <?php Basecamp_Frontend::picture( get_post_thumbnail_id(), [
                    'landscape_size' => 'basecamp-img-xl',
                    'loading'        => 'eager',
                    'fetchpriority'  => 'high',
                ] ); ?>
            </div>
        <?php endif; ?>

        <header class="dish-format-header">

		<?php dish_the_breadcrumb(); ?>

        <h1 class="dish-format-title"><?php the_title(); ?></h1>

        <?php if ( has_excerpt() ) : ?>
                <p class="dish-format-excerpt"><?php the_excerpt(); ?></p>
            <?php endif; ?>
        </header>

        <?php if ( get_the_content() ) : ?>
            <div class="dish-format-content dish-content">
                <?php the_content(); ?>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $upcoming_classes ) ) : ?>
            <section class="dish-upcoming-listing">
                <h2 class="dish-upcoming-listing__heading">
                    <?php
                    echo esc_html( sprintf(
                        /* translators: %s: format name e.g. "Hands On" */
                        __( 'Upcoming %s Classes', 'dish-events' ),
                        $format_title
                    ) );
                    ?>
                </h2>
                <div class="dish-class-grid">
                    <?php foreach ( $upcoming_classes as $class ) : ?>
                        <?php include locate_template( 'dish-events/classes/card.php' ); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ( ! empty( $templates ) ) : ?>
            <section class="dish-template-listing">
                <h2 class="dish-template-listing__heading">
                    <?php
                    echo esc_html( sprintf(
                        /* translators: %s: format name e.g. "Hands On" */
                        __( 'Our %s Class Types', 'dish-events' ),
                        $format_title
                    ) );
                    ?>
                </h2>
                <div class="dish-template-grid">
                    <?php foreach ( $templates as $template ) : ?>
                        <?php include locate_template( 'dish-events/class-templates/card.php' ); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else : ?>
            <p class="dish-no-classes">
                <?php esc_html_e( 'No classes currently available in this format.', 'dish-events' ); ?>
            </p>
        <?php endif; ?>

    </article>
</main>

<?php endwhile; ?>

<?php get_footer(); ?>
