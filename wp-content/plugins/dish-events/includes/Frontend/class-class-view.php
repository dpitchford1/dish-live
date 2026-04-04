<?php
/**
 * Class instance view renderer.
 *
 * Provides static shortcode handlers and output-buffered template loading
 * for dish_class archive and single views. All rendering is delegated to
 * PHP template files in templates/classes/, allowing theme overrides.
 *
 * @package Dish\Events\Frontend
 */

declare( strict_types=1 );

namespace Dish\Events\Frontend;

use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\ClassTemplateRepository;

/**
 * Class ClassView
 */
final class ClassView {

	// -------------------------------------------------------------------------
	// Shortcode handlers
	// -------------------------------------------------------------------------

	/**
	 * Shortcode: [dish_classes limit="12" format_id="" view="grid|calendar"]
	 *
	 * Renders an archive grid of upcoming class instances (default), or a
	 * FullCalendar calendar when view="calendar" is passed.
	 *
	 * @param  array<string,string>|string $atts  Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_archive( $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'limit'     => 12,
				'format_id' => '',
				'view'      => 'grid',
			],
			(array) $atts,
			'dish_classes'
		);

		// Delegate to FullCalendar view when requested.
		if ( 'calendar' === $atts['view'] ) {
			return Calendar::render( $atts );
		}

		$query_args = [
			'start_after' => time(),
			'limit'       => (int) $atts['limit'],
			'order'       => 'ASC',
		];

		if ( $atts['format_id'] !== '' ) {
			$format_id    = (int) $atts['format_id'];
			$template_ids = get_posts( [
				'post_type'      => 'dish_class_template',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => 'dish_format_id',
						'value' => $format_id,
						'type'  => 'NUMERIC',
					],
				],
			] );

			if ( empty( $template_ids ) ) {
				$classes = [];
			} else {
				$query_args['template_ids'] = $template_ids;
				$classes = ClassRepository::query( $query_args );
			}
		} else {
			$classes = ClassRepository::query( $query_args );
		}

		ob_start();

		// Batch-load all per-card data upfront so archive.php/card.php incur no
		// additional queries per card (resolves the N+1 identified in Phase 8).
		$class_ids    = array_map( static fn( \WP_Post $c ) => $c->ID, $classes );
		$template_ids = array_unique(
			array_filter(
				array_map( static fn( int $id ) => (int) get_post_meta( $id, 'dish_template_id', true ), $class_ids )
			)
		);
		$booked_counts    = ClassRepository::get_booked_counts_batch( $class_ids );
		$ticket_types_map = ClassTemplateRepository::get_ticket_types_batch( $template_ids );

		include Frontend::locate( 'classes/archive.php' );
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode: [dish_class id="N"]
	 *
	 * Renders a single class instance inline (useful for embedding on a page).
	 * Note: single-class permalinks are served via template_include in Frontend,
	 * not via this shortcode.
	 *
	 * @param  array<string,string>|string $atts  Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_single( $atts = [] ): string {
		$atts  = shortcode_atts( [ 'id' => 0 ], (array) $atts, 'dish_class' );
		$class = ClassRepository::get( (int) $atts['id'] );

		if ( ! $class ) {
			return '';
		}

		ob_start();
		include Frontend::locate( 'classes/single.php' );
		return (string) ob_get_clean();
	}
}
