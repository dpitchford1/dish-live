<?php
/**
 * Plugin Deactivator.
 *
 * Runs on register_deactivation_hook(). Responsible only for cleaning up
 * runtime state — transient flags.
 *
 * Does NOT delete any persistent data (options, post content, post meta).
 * Data removal happens in uninstall.php if ever written.
 *
 * @package Dish\Recipes\Core
 */

declare( strict_types=1 );

namespace Dish\Recipes\Core;

/**
 * Class Deactivator
 */
final class Deactivator {

	/**
	 * Run all deactivation routines.
	 * Called via register_deactivation_hook() in dish-recipes.php.
	 */
	public static function deactivate(): void {
		self::clear_flags();
	}

	// -------------------------------------------------------------------------
	// Flags
	// -------------------------------------------------------------------------

	/**
	 * Clear any pending activation flags so they don't fire unexpectedly
	 * if the plugin is reactivated later.
	 */
	private static function clear_flags(): void {
		delete_option( 'dish_recipes_flush_rewrite_rules' );
	}
}
