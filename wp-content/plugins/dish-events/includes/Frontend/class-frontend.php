<?php
/**
 * Frontend template loader and theme-override resolver.
 *
 * Hooks into template_include to serve plugin templates for all dish CPT
 * single pages. The static locate() method is shared by view classes and
 * template partials throughout the plugin.
 *
 * Override resolution order for any relative path:
 *   1. {child-theme}/dish-events/{relative}
 *   2. {parent-theme}/dish-events/{relative}   (only when child ≠ parent)
 *   3. Plugin fallback: {plugin}/templates/{relative}
 *
 * @package Dish\Events\Frontend
 */

declare( strict_types=1 );

namespace Dish\Events\Frontend;

use Dish\Events\Admin\Settings;
use Dish\Events\Core\Loader;

/**
 * Class Frontend
 */
final class Frontend {

	/**
	 * Register all frontend hooks via the Loader.
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_filter( 'template_include', $this, 'template_include' );
	}

	// -------------------------------------------------------------------------
	// Template routing
	// -------------------------------------------------------------------------

	/**
	 * Swap in a plugin template for dish CPT single pages.
	 *
	 * @param string $template  Template file chosen by WordPress.
	 * @return string
	 */
	public function template_include( string $template ): string {
		// ── Booking page routing ─────────────────────────────────────────────────
		$booking_page = (int) Settings::get( 'booking_page', 0 );
		$details_page = (int) Settings::get( 'booking_details_page', 0 );

		if ( $booking_page && is_page( $booking_page ) && isset( $_GET['class_id'] ) ) {
			$resolved = self::locate( 'booking/checkout.php' );
			return file_exists( $resolved ) ? $resolved : $template;
		}

		if ( $details_page && is_page( $details_page ) && isset( $_GET['booking_id'] ) ) {
			$resolved = self::locate( 'booking/confirmation.php' );
			return file_exists( $resolved ) ? $resolved : $template;
		}

		// ── Archive routing ─────────────────────────────────────────────────────
		if ( is_post_type_archive( 'dish_chef' ) ) {
			$resolved = self::locate( 'chefs/archive-page.php' );
			return file_exists( $resolved ) ? $resolved : $template;
		}

		if ( is_post_type_archive( 'dish_format' ) ) {
			$resolved = self::locate( 'formats/archive.php' );
			return file_exists( $resolved ) ? $resolved : $template;
		}

		// ── Single routing ──────────────────────────────────────────────────────

		// dish_class_template: private formats use a dedicated template.
		if ( is_singular( 'dish_class_template' ) ) {
			$fmt_id   = (int) get_post_meta( get_the_ID(), 'dish_format_id', true );
			$private  = $fmt_id && get_post_meta( $fmt_id, 'dish_format_is_private', true );
			$relative = $private ? 'class-templates/single-private.php' : 'class-templates/single.php';
			$resolved = self::locate( $relative );
			return file_exists( $resolved ) ? $resolved : $template;
		}

		$map = [
			'dish_format' => 'formats/single.php',
			'dish_class'  => 'classes/single.php',
			'dish_chef'   => 'chefs/single.php',
		];

		foreach ( $map as $post_type => $relative ) {
			if ( is_singular( $post_type ) ) {
				$resolved = self::locate( $relative );
				return file_exists( $resolved ) ? $resolved : $template;
			}
		}

		return $template;
	}

	// -------------------------------------------------------------------------
	// Template resolver (shared by view classes and template partials)
	// -------------------------------------------------------------------------

	/**
	 * Locate the correct template file for a given relative path, checking
	 * theme directories before falling back to the plugin's templates/ folder.
	 *
	 * Returns the plugin fallback path even when the file does not yet exist,
	 * so callers can `include` it and get a natural PHP error on missing files
	 * during development.
	 *
	 * @param  string $relative  Path within templates/, e.g. 'classes/card.php'.
	 * @return string            Absolute path to the resolved template file.
	 */
	public static function locate( string $relative ): string {
		// 1. Child-theme override.
		$child = get_stylesheet_directory() . '/dish-events/' . $relative;
		if ( file_exists( $child ) ) {
			return $child;
		}

		// 2. Parent-theme override (only when a separate child theme is active).
		if ( get_stylesheet_directory() !== get_template_directory() ) {
			$parent = get_template_directory() . '/dish-events/' . $relative;
			if ( file_exists( $parent ) ) {
				return $parent;
			}
		}

		// 3. Plugin fallback.
		return DISH_EVENTS_PATH . 'templates/' . $relative;
	}
}
