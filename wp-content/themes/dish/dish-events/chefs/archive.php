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
<div class="">

<?php if ( empty( $chefs ) ) : ?>

    <p class="">
        <?php esc_html_e( 'No chefs to display at the moment.', 'dish-events' ); ?>
    </p>

<?php else : ?>

    <div class="grid-general grid--4col">
        <?php foreach ( (array) $chefs as $chef ) : ?>
            <?php include \Dish\Events\Frontend\Frontend::locate( 'chefs/card.php' ); ?>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

</div>
