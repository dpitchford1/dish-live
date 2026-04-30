<?php
/**
 * Plugin Activator.
 *
 * Runs on register_activation_hook(). Responsible for:
 *   - Setting flags consumed by Plugin (rewrite flush)
 *   - Enabling thumbnail support for dish_recipe
 *
 * IMPORTANT: flush_rewrite_rules() is NOT called here directly.
 * CPTs must be registered before the flush is meaningful, so we set the
 * dish_recipes_flush_rewrite_rules flag and let Plugin::maybe_flush_rewrite_rules()
 * handle it on the next admin_init.
 *
 * @package Dish\Recipes\Core
 */

declare( strict_types=1 );

namespace Dish\Recipes\Core;

/**
 * Class Activator
 */
final class Activator {

	/**
	 * Run all activation routines.
	 * Called via register_activation_hook() in dish-recipes.php.
	 */
	public static function activate(): void {
		// Signal that rewrite rules need flushing on next admin load
		// (once CPTs and taxonomies have been registered).
		update_option( 'dish_recipes_flush_rewrite_rules', '1' );
	}
}
