<?php
/**
 * Template: [dish_chefs] archive — chef card grid.
 *
 * Variables available:
 *   $chefs  WP_Post[]  Published dish_chef posts, ordered by title.
 *
 * Theme override: {theme}/dish-events/chefs/archive.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var \WP_Post[] $chefs Injected by ChefView::render_archive(). */
if ( ! isset( $chefs ) || ! is_array( $chefs ) ) {
	$chefs = [];
}
?>
<div class="dish-archive">

	<?php if ( empty( $chefs ) ) : ?>

		<p class="dish-no-results">
			<?php esc_html_e( 'No chefs to display at the moment.', 'dish-events' ); ?>
		</p>

	<?php else : ?>

		<div class="dish-card-grid dish-chef-grid">
			<?php foreach ( (array) $chefs as $chef ) : ?>
				<?php include \Dish\Events\Frontend\Frontend::locate( 'chefs/card.php' ); ?>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

</div>
