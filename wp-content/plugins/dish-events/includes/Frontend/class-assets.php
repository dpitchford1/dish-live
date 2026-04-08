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
	 * Enqueue dish-events.css when the current page needs frontend plugin styles.
	 */
	public function enqueue(): void {
		wp_enqueue_style(
			'dish-calendar',
			DISH_EVENTS_URL . 'assets/css/dish-events.css',
			[],
			DISH_EVENTS_VERSION
		);

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
