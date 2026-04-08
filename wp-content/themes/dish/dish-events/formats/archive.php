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

<main id="main-content" class="main--content fluid-content">
    <h1 class="dish-archive-title"><?php esc_html_e( 'Class Formats', 'dish-events' ); ?></h1>

	<header class="dish-archive-header dish-container">
		
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

		<div class="grid-general grid--3col">
			<?php foreach ( $formats as $format ) : ?>
				<?php include Frontend::locate( 'formats/card.php' ); ?>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

</main>

<?php get_footer(); ?>
