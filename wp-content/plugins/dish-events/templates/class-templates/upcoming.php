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

dish_the_upcoming_classes( [
	'template_id'          => get_the_ID(),
	'limit'                => 10,
	'suppress_format_pill' => true,
] );
