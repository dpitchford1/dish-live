<?php
/**
 * Chef view renderer.
 *
 * Provides static shortcode handlers and output-buffered template loading
 * for dish_chef archive and single views. All rendering is delegated to
 * PHP template files in templates/chefs/, allowing theme overrides.
 *
 * @package Dish\Events\Frontend
 */

declare( strict_types=1 );

namespace Dish\Events\Frontend;

use Dish\Events\Data\ChefRepository;

/**
 * Class ChefView
 */
final class ChefView {

	// -------------------------------------------------------------------------
	// Shortcode handlers
	// -------------------------------------------------------------------------

	/**
	 * Shortcode: [dish_chefs limit="12"]
	 *
	 * Renders a card grid of published chefs.
	 *
	 * @param  array<string,string>|string $atts  Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_archive( $atts = [] ): string {
		$atts = shortcode_atts(
			[ 'limit' => 12 ],
			(array) $atts,
			'dish_chefs'
		);

		$chefs = ChefRepository::query(
			[
				'limit'        => (int) $atts['limit'],
				'orderby'      => 'title',
				'order'        => 'ASC',
				'exclude_team' => true,
			]
		);

		ob_start();
		include Frontend::locate( 'chefs/archive.php' );
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode: [dish_chef id="N"]
	 *
	 * Renders a single chef profile inline. Single chef permalinks are served
	 * via template_include in Frontend, not via this shortcode.
	 *
	 * @param  array<string,string>|string $atts  Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_single( $atts = [] ): string {
		$atts = shortcode_atts( [ 'id' => 0 ], (array) $atts, 'dish_chef' );
		$chef = ChefRepository::get( (int) $atts['id'] );

		if ( ! $chef ) {
			return '';
		}

		ob_start();
		include Frontend::locate( 'chefs/single.php' );
		return (string) ob_get_clean();
	}
}
