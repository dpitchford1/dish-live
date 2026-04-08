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

	// Partition into featured and standard templates.
	$featured_templates = array_values( array_filter( $templates, fn( $t ) => (bool) get_post_meta( $t->ID, 'dish_is_featured', true ) ) );
	$standard_templates = array_values( array_filter( $templates, fn( $t ) => ! (bool) get_post_meta( $t->ID, 'dish_is_featured', true ) ) );

	?>

	<main id="primary" class="site-main dish-format-page">
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="dish-format-hero">
					<?php the_post_thumbnail( 'large' ); ?>
				</div>
			<?php endif; ?>

			<header class="dish-format-header">
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

			<?php dish_the_upcoming_classes( [
				'template_ids'         => ! empty( $templates ) ? wp_list_pluck( $templates, 'ID' ) : [],
				'dedupe_by_template'   => true,
				/* translators: %s: format name e.g. "Hands On" */
				'heading'              => sprintf( __( 'Upcoming %s Classes', 'dish-events' ), $format_title ),
				'suppress_format_pill' => true,
			] ); ?>

			<?php if ( ! empty( $featured_templates ) ) : ?>
				<section class="dish-template-listing dish-template-listing--featured">
					<?php $suppress_format_pill = true; ?>
					<?php foreach ( $featured_templates as $template ) : ?>
						<?php include __DIR__ . '/../class-templates/card.php'; ?>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $standard_templates ) ) : ?>
				<section class="dish-template-listing dish-template-listing--standard">
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
						<?php $suppress_format_pill = true; ?>
						<?php foreach ( $standard_templates as $template ) : ?>
							<?php include __DIR__ . '/../class-templates/card.php'; ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php elseif ( empty( $featured_templates ) ) : ?>
				<p class="dish-no-classes">
					<?php esc_html_e( 'No classes currently available in this format.', 'dish-events' ); ?>
				</p>
			<?php endif; ?>

		</article>
	</main>

<?php endwhile; ?>

<?php get_footer(); ?>
