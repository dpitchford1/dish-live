<?php
/**
 * Template: FullCalendar calendar view.
 *
 * Rendered by Calendar::render() when [dish_classes view="calendar"] is used.
 *
 * Variables available:
 *   $formats  WP_Post[]  All published dish_format posts for the filter bar.
 *   $atts     array      Shortcode attributes (limit, format_id, view).
 *
 * Theme override: {theme}/dish-events/classes/calendar.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var \WP_Post[] $formats */
if ( ! isset( $formats ) || ! is_array( $formats ) ) {
	$formats = [];
}
?>
<div class="dish-calendar-wrap content-region">

	<?php if ( ! empty( $formats ) ) : ?>
		<nav class="dish-calendar-filters" aria-label="<?php esc_attr_e( 'Filter by format', 'dish-events' ); ?>">

			<button type="button" class="dish-calendar-filter is-active" data-format-id="0">
				<?php esc_html_e( 'All Formats', 'dish-events' ); ?>
			</button>

			<?php foreach ( $formats as $format ) :
				$color = (string) get_post_meta( $format->ID, 'dish_format_color', true ) ?: '#c0392b';
			?>
				<button type="button" class="dish-calendar-filter" data-format-id="<?php echo esc_attr( $format->ID ); ?>" style="--dish-filter-color: <?php echo esc_attr( $color ); ?>;"><?php echo esc_html( $format->post_title ); ?></button>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<section id="dish-calendar" class="dish-calendar"></section>

</div>