<?php
/**
 * Plugin Name:       Dish Recipes
 * Plugin URI:        https://dishcookingstudio.com
 * Description:       Structured recipe content with Schema.org output for Dish Cooking Studio.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Kaneism Design
 * Author URI:        https://kaneism.com
 * Text Domain:       dish-recipes
 * Domain Path:       /languages
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define( 'DISH_RECIPES_VERSION', '1.0.0' );
define( 'DISH_RECIPES_FILE',    __FILE__ );
define( 'DISH_RECIPES_PATH',    plugin_dir_path( __FILE__ ) );
define( 'DISH_RECIPES_URL',     plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// PSR-4 Autoloader  (no Composer dependency)
//
// Namespace root : Dish\Recipes\
// Maps to        : includes/{SubNamespace}/class-{kebab-name}.php
//
// Rules:
//   - CamelCase class name → kebab-case filename
//   - Classes ending in "Interface" use the interface- file prefix
//
// Examples:
//   Dish\Recipes\Core\Plugin           → includes/Core/class-plugin.php
//   Dish\Recipes\CPT\RecipePost        → includes/CPT/class-recipe-post.php
//   Dish\Recipes\Data\RecipeRepository → includes/Data/class-recipe-repository.php
// ---------------------------------------------------------------------------

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'Dish\\Recipes\\';

	if ( ! str_starts_with( $class, $prefix ) ) {
		return;
	}

	$relative   = substr( $class, strlen( $prefix ) );
	$parts      = explode( '\\', $relative );
	$class_name = array_pop( $parts );

	// Convert CamelCase → kebab-case.
	$kebab = strtolower( (string) preg_replace( '/(?<!^)[A-Z]/', '-$0', $class_name ) );

	// Determine file prefix: interface- or class-.
	if ( str_ends_with( $kebab, '-interface' ) ) {
		$filename = 'interface-' . substr( $kebab, 0, -strlen( '-interface' ) ) . '.php';
	} else {
		$filename = 'class-' . $kebab . '.php';
	}

	$path = DISH_RECIPES_PATH . 'includes/'
		. ( $parts ? implode( '/', $parts ) . '/' : '' )
		. $filename;

	if ( file_exists( $path ) ) {
		require_once $path;
	}
} );

// ---------------------------------------------------------------------------
// Activation / Deactivation hooks
// (must be registered before plugins_loaded fires)
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, [ 'Dish\\Recipes\\Core\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Dish\\Recipes\\Core\\Deactivator', 'deactivate' ] );

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

add_action( 'plugins_loaded', [ 'Dish\\Recipes\\Core\\Plugin', 'run' ] );

// ---------------------------------------------------------------------------
// Template helper functions
// ---------------------------------------------------------------------------

/**
 * Return the attachment ID of the recipes archive featured image.
 * Returns 0 if none has been set.
 *
 * @return int
 */
function dish_recipes_get_archive_image_id(): int {
	return (int) \Dish\Recipes\Admin\Settings::get( 'archive_image_id', 0 );
}

/**
 * Return the correct hero image ID for the current recipe archive/category page.
 *
 * On a category term page, returns the category image if one is set,
 * otherwise falls back to the global archive image.
 * On the main archive, returns the global archive image.
 * Returns 0 if nothing is set.
 *
 * @return int
 */
function dish_recipes_get_hero_image_id(): int {
	if ( is_tax( 'dish_recipe_category' ) ) {
		$term     = get_queried_object();
		$image_id = $term instanceof \WP_Term
			? \Dish\Recipes\Admin\CategoryMeta::get_image_id( $term )
			: 0;
		if ( $image_id ) {
			return $image_id;
		}
	}
	return dish_recipes_get_archive_image_id();
}

/**
 * Return the correct hero image ID for a single recipe page.
 *
 * Priority:
 *   1. Recipe featured image (post thumbnail)
 *   2. Category image for the recipe's primary category
 *   3. Global archive image
 * Returns 0 if nothing is set.
 *
 * @param  int $recipe_id
 * @return int
 */
function dish_recipes_get_single_hero_image_id( int $recipe_id ): int {
	// 1. Recipe featured image
	$thumb_id = (int) get_post_thumbnail_id( $recipe_id );
	if ( $thumb_id ) {
		return $thumb_id;
	}

	// 2. Primary category image
	$terms = get_the_terms( $recipe_id, 'dish_recipe_category' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		$cat_image_id = \Dish\Recipes\Admin\CategoryMeta::get_image_id( $terms[0] );
		if ( $cat_image_id ) {
			return $cat_image_id;
		}
	}

	// 3. Global archive image
	return dish_recipes_get_archive_image_id();
}

/**
 * Render same-category recipe suggestions on a single recipe page.
 *
 * Pulls up to $limit recipes from the same category (excluding the current
 * recipe) using a deterministic offset — no rand(), cache-safe.
 *
 * @param int $recipe_id  Current recipe post ID.
 * @param int $limit      Number of recipes to show. Default 3.
 */
function dish_recipes_more_recipes( int $recipe_id, int $limit = 3 ): void {
	$recipes = \Dish\Recipes\Data\RecipeRepository::get_same_category( $recipe_id, $limit );

	if ( empty( $recipes ) ) {
		return;
	}

	$loader = new \Dish\Recipes\Frontend\TemplateLoader();
	$loader->load_template( 'more-recipes.php', [
		'recipes' => $recipes,
		'loader'  => $loader,
	] );
}

/**
 * Render the "Complete the Menu" block on a single recipe page.
 *
 * Picks one recipe from each category the current recipe does NOT belong to,
 * using a deterministic offset — no rand(), cache-safe.
 *
 * @param int $recipe_id  Current recipe post ID.
 */
function dish_recipes_complete_menu( int $recipe_id ): void {
	$menu_items = \Dish\Recipes\Data\RecipeRepository::get_one_per_other_category( $recipe_id );

	if ( empty( $menu_items ) ) {
		return;
	}

	$loader = new \Dish\Recipes\Frontend\TemplateLoader();
	$loader->load_template( 'complete-menu.php', [
		'menu_items' => $menu_items,
		'loader'     => $loader,
	] );
}
