<?php
/**
 * Template: dish_format single page.
 *
 * Displays the format's editorial content (title, featured image, body)
 * followed by a grid of all published class templates in this format.
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

	// Query all published templates assigned to this format.
	$templates = get_posts( [
		'post_type'      => 'dish_class_template',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => [
			[
				'key'   => 'dish_format_id',
				'value' => get_the_ID(),
				'type'  => 'NUMERIC',
			],
		],
	] );
	?>

	<main id="main-content" class="">
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

			<?php if ( ! empty( $templates ) ) : ?>
				<section class="dish-template-listing">
					<h2 class="dish-template-listing__heading">
						<?php esc_html_e( 'Available Classes', 'dish-events' ); ?>
					</h2>
					<div class="dish-template-grid">
						<?php foreach ( $templates as $template ) : ?>
							<?php include __DIR__ . '/../class-templates/card.php'; ?>
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
