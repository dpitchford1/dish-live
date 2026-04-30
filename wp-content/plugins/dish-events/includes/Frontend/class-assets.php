<?php
/**
 * Frontend asset enqueuer.
 *
 * Enqueues dish-events.css conditionally — only on pages that need it:
 *   – Any dish CPT single page (dish_class, dish_chef, dish_class_template, dish_format)
 *   – Any page / post whose content contains at least one [dish_*] shortcode
 *
 * This keeps the stylesheet off unrelated pages entirely.
 *
 * @package Dish\Events\Frontend
 */

declare( strict_types=1 );

namespace Dish\Events\Frontend;

use Dish\Events\Admin\Settings;
use Dish\Events\Core\Loader;

/**
 * Class Assets
 */
final class Assets {

	/**
	 * Register hooks via the Loader.
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue' );
	}

	// -------------------------------------------------------------------------
	// Enqueue
	// -------------------------------------------------------------------------

	/**
	 * Enqueue plugin stylesheets on pages that need frontend plugin styles.
	 *
	 * dish-calendar.css is registered first (no deps) because it carries the
	 * :root design tokens. dish-events.css declares it as a dependency so the
	 * tokens are always resolved before component styles are parsed.
	 */
	public function enqueue(): void {
		wp_enqueue_style(
			'dish-calendar',
			DISH_EVENTS_URL . 'assets/css/dish-calendar.min.css',
			[],
			DISH_EVENTS_VERSION
		);

		wp_enqueue_style(
			'dish-events',
			DISH_EVENTS_URL . 'assets/css/dish-events.min.css',
			[ 'dish-calendar' ],
			DISH_EVENTS_VERSION
		);

		// Calendar JS — only on the classes/calendar page.
		$classes_page = (int) Settings::get( 'classes_page', 0 );

		if ( $classes_page && is_page( $classes_page ) ) {
			wp_enqueue_script(
				'fullcalendar',
				DISH_EVENTS_URL . 'assets/vendor/fullcalendar/fullcalendar.min.js',
				[],
				'6.1.15',
				true
			);

			wp_enqueue_script(
				'dish-calendar',
				DISH_EVENTS_URL . 'assets/js/dish-calendar.js',
				[ 'fullcalendar' ],
				DISH_EVENTS_VERSION,
				true
			);

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

		// Booking JS — only on the checkout page.
		$booking_page = (int) Settings::get( 'booking_page', 0 );

		if ( $booking_page && is_page( $booking_page ) ) {
			wp_enqueue_script(
				'dish-booking',
				DISH_EVENTS_URL . 'assets/js/dish-booking.js',
				[],
				DISH_EVENTS_VERSION,
				true
			);
		}
	}

}
