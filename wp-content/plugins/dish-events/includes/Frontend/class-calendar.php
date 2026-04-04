<?php
/**
 * Calendar view — FullCalendar asset loader and renderer.
 *
 * Manages enqueuing FullCalendar and dish-calendar.js, passes the REST URL
 * and localised config to JS via wp_localize_script, and renders the calendar
 * template via output buffering.
 *
 * Assets are only enqueued when render() is called (i.e. when the shortcode
 * is actually used on the current page) — not globally.
 *
 * @package Dish\Events\Frontend
 */

declare( strict_types=1 );

namespace Dish\Events\Frontend;

use Dish\Events\Admin\Settings;

/**
 * Class Calendar
 */
final class Calendar {

	/** True once assets have been enqueued to prevent double-enqueue. */
	private static bool $enqueued = false;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Render the calendar shortcode HTML.
	 * Enqueues assets on first call.
	 *
	 * @param  array<string,string> $atts  Shortcode attributes from [dish_classes view="calendar"].
	 * @return string HTML output (FullCalendar mount point + filter bar).
	 */
	public static function render( array $atts ): string {
		self::enqueue();

		// Fetch formats for the filter bar (server-side).
		$formats = get_posts( [
			'post_type'      => 'dish_format',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		ob_start();
		include Frontend::locate( 'classes/calendar.php' );
		return (string) ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Asset registration
	// -------------------------------------------------------------------------

	/**
	 * Enqueue FullCalendar + dish-calendar.js and pass config to JS.
	 * Safe to call multiple times — idempotent.
	 */
	public static function enqueue(): void {
		if ( self::$enqueued ) {
			return;
		}

		self::$enqueued = true;

		// 1. FullCalendar v6 global bundle (includes dayGrid, timeGrid, list, interaction).
		wp_enqueue_script(
			'fullcalendar',
			DISH_EVENTS_URL . 'assets/vendor/fullcalendar/fullcalendar.min.js',
			[],
			'6.1.15',
			true
		);

		// 2. Plugin calendar init.
		wp_enqueue_script(
			'dish-calendar',
			DISH_EVENTS_URL . 'assets/js/dish-calendar.js',
			[ 'fullcalendar' ],
			DISH_EVENTS_VERSION,
			true
		);

		// 3. Pass config to JS.
		wp_localize_script( 'dish-calendar', 'dishCalendar', [
			'restUrl'        => rest_url( 'dish/v1/classes' ),
			'locale'         => str_replace( '_', '-', (string) get_locale() ),
			'currencySymbol' => Settings::get( 'currency_symbol', '$' ),
			'spotsThreshold' => (int) Settings::get( 'spots_left_threshold', 0 ),
			'i18n'           => [
				'allFormats'   => __( 'All Formats', 'dish-events' ),
				'noEvents'     => __( 'No classes this period.', 'dish-events' ),
				'privateEvent' => __( 'Private Event', 'dish-events' ),
				'close'        => __( 'Close', 'dish-events' ),
				'bookIt'       => __( 'Book It', 'dish-events' ),
				'viewClass'    => __( 'View class details', 'dish-events' ),
			],
		] );
	}
}
