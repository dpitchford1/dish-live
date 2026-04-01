<?php
/**
 * Partial: upcoming class instances for a template.
 *
 * Included from class-templates/single.php. Reads get_the_ID() to find the
 * parent dish_class_template post, then queries future dish_class instances
 * and renders them using the shared classes/card.php partial — same layout
 * and CSS as the format page's "Available Classes" grid.
 *
 * Theme override: {theme}/dish-events/class-templates/upcoming.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Frontend\Frontend;

$template_id = get_the_ID();

$upcoming = get_posts( [
	'post_type'      => 'dish_class',
	'post_status'    => 'publish',
	'posts_per_page' => 10,
	'meta_key'       => 'dish_start_datetime',
	'orderby'        => 'meta_value_num',
	'order'          => 'ASC',
	'meta_query'     => [
		'relation' => 'AND',
		[
			'key'   => 'dish_template_id',
			'value' => $template_id,
			'type'  => 'NUMERIC',
		],
		[
			'key'     => 'dish_start_datetime',
			'value'   => time(),
			'compare' => '>=',
			'type'    => 'NUMERIC',
		],
	],
] );

if ( empty( $upcoming ) ) {
	return;
}
?>
<section class="dish-template-listing">
	<h2 class="dish-template-listing__heading"><?php esc_html_e( 'Upcoming Classes', 'dish-events' ); ?></h2>
	<div class="dish-template-grid">
		<?php foreach ( $upcoming as $class ) :
			include Frontend::locate( 'classes/card.php' );
		endforeach; ?>
	</div>
</section>
