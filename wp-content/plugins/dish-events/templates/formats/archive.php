<?php
/**
 * Template: dish_format archive — Class Formats listing.
 *
 * Served when WordPress routes to the dish_format post type archive
 * (e.g. /classes/). Renders a grid of all published formats.
 *
 * Theme override: {theme}/dish-events/formats/archive.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Frontend\Frontend;

get_header();

$formats = get_posts( [
	'post_type'      => 'dish_format',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
] );
?>

<main id="primary" class="site-main dish-formats-page">

	<header class="dish-archive-header dish-container">
		<h1 class="dish-archive-title">
			<?php esc_html_e( 'Class Formats', 'dish-events' ); ?>
		</h1>
		<?php if ( get_the_archive_description() ) : ?>
			<div class="dish-archive-description">
				<?php the_archive_description(); ?>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( empty( $formats ) ) : ?>

		<div class="dish-container">
			<p class="dish-no-results">
				<?php esc_html_e( 'No formats to display at the moment. Check back soon!', 'dish-events' ); ?>
			</p>
		</div>

	<?php else : ?>

		<div class="dish-card-grid dish-format-grid dish-container">
			<?php foreach ( $formats as $format ) : ?>
				<?php include Frontend::locate( 'formats/card.php' ); ?>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

</main>

<?php get_footer(); ?>
