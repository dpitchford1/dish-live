<?php
/**
 * Template: [dish_classes] archive — upcoming class instance grid.
 *
 * Variables available:
 *   $classes  WP_Post[]  Upcoming dish_class instances, ordered by start date.
 *
 * Theme override: {theme}/dish-events/classes/archive.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var \WP_Post[] $classes Injected by ClassView::render_archive(). */
if ( ! isset( $classes ) || ! is_array( $classes ) ) {
	$classes = [];
}

/**
 * Pre-loaded maps from ClassView::render_archive() to avoid N+1 queries.
 * @var array<int,int>         $booked_counts    class_id → booked count
 * @var array<int,object|null> $ticket_types_map template_id → ticket type row
 */
$booked_counts    = $booked_counts    ?? [];
$ticket_types_map = $ticket_types_map ?? [];
?>
<div class="dish-classes-archive">

	<?php if ( empty( $classes ) ) : ?>

		<p class="dish-no-results">
			<?php esc_html_e( 'No upcoming classes at the moment — check back soon!', 'dish-events' ); ?>
		</p>

	<?php else : ?>

		<div class="dish-card-grid">
			<?php foreach ( (array) $classes as $class ) : ?>
				<?php include \Dish\Events\Frontend\Frontend::locate( 'classes/card.php' ); ?>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

</div>
