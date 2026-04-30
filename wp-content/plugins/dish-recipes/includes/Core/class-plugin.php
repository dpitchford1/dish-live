<?php
/**
 * Core plugin bootstrap.
 *
 * Instantiates the Loader, wires all module hooks, then fires
 * the dish_recipes_loaded action once everything is registered.
 *
 * @package Dish\Recipes\Core
 */

declare( strict_types=1 );

namespace Dish\Recipes\Core;

/**
 * Class Plugin
 *
 * Singleton — call Plugin::run() once on plugins_loaded.
 * Do not instantiate directly.
 */
final class Plugin {

	private Loader $loader;

	/**
	 * Private constructor — use Plugin::run().
	 */
	private function __construct() {
		$this->loader = new Loader();
		$this->wire_hooks();
	}

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	/**
	 * Entry point. Called once via add_action( 'plugins_loaded', ... ) in
	 * dish-recipes.php. Subsequent calls are no-ops.
	 */
	public static function run(): void {
		static $instance = null;

		if ( null !== $instance ) {
			return;
		}

		$instance = new self();
		$instance->loader->run();

		/**
		 * Fires after Dish Recipes has fully bootstrapped and all hooks are registered.
		 *
		 * @since 1.0.0
		 */
		do_action( 'dish_recipes_loaded' );
	}

	// -------------------------------------------------------------------------
	// Hook wiring
	// -------------------------------------------------------------------------

	/**
	 * Register all module hooks with the Loader.
	 */
	private function wire_hooks(): void {

		// Extend post-thumbnail support to dish_recipe CPT.
		$this->loader->add_action( 'after_setup_theme', $this, 'add_thumbnail_support', 11 );

		// Flush rewrite rules once after activation (when CPT + taxonomy are registered).
		$this->loader->add_action( 'admin_init', $this, 'maybe_flush_rewrite_rules' );

		// -------------------------------------------------------------------------
		// CPT + Taxonomy
		// -------------------------------------------------------------------------

		$recipe_post = new \Dish\Recipes\CPT\RecipePost();
		$this->loader->add_action( 'init', $recipe_post, 'register' );

		// -------------------------------------------------------------------------
		// Frontend template loader
		// -------------------------------------------------------------------------

		$template_loader = new \Dish\Recipes\Frontend\TemplateLoader();
		$this->loader->add_filter( 'template_include', $template_loader, 'load' );

		// -------------------------------------------------------------------------
		// Admin
		// -------------------------------------------------------------------------

		if ( is_admin() ) {
			$meta_box = new \Dish\Recipes\Admin\RecipeMetaBox();
			$meta_box->register_hooks( $this->loader );

			$columns = new \Dish\Recipes\Admin\RecipeColumns();
			$columns->register_hooks( $this->loader );

			$settings = new \Dish\Recipes\Admin\Settings();
			$settings->register_hooks( $this->loader );

			$category_meta = new \Dish\Recipes\Admin\CategoryMeta();
			$category_meta->register_hooks( $this->loader );
		}

		// -------------------------------------------------------------------------
		// SEO — Schema.org Recipe JSON-LD
		// -------------------------------------------------------------------------

		$schema = new \Dish\Recipes\SEO\RecipeSchema();
		$this->loader->add_action( 'wp_head', $schema, 'output' );

		// -------------------------------------------------------------------------
		// Frontend — Assets + Shortcodes
		// -------------------------------------------------------------------------

		$assets = new \Dish\Recipes\Frontend\Assets();
		$assets->register_hooks( $this->loader );

		$shortcodes = new \Dish\Recipes\Frontend\Shortcodes();
		$shortcodes->register_hooks( $this->loader );

		// -------------------------------------------------------------------------
		// Cross-plugin — Related recipes on dish_class_template single pages
		//
		// dish-events fires do_action( 'dish_after_class_template_content' ) in its
		// single template. We hook in here only if dish-events is active.
		// If dish-events is not active, nothing is hooked and nothing breaks.
		// -------------------------------------------------------------------------

		if ( function_exists( 'dish_events_loaded' ) || defined( 'DISH_EVENTS_VERSION' ) ) {
			$template_loader_ref = new \Dish\Recipes\Frontend\TemplateLoader();
			$this->loader->add_action(
				'dish_after_class_template_content',
				new \Dish\Recipes\Frontend\RelatedRecipes( $template_loader_ref ),
				'render'
			);
		}

		// -------------------------------------------------------------------------
		// Data layer — stateless static repositories, autoloaded on first use.
		//
		// Dish\Recipes\Data\RecipeRepository
		// -------------------------------------------------------------------------
	}

	// -------------------------------------------------------------------------
	// Theme supports
	// -------------------------------------------------------------------------

	/**
	 * Extend post-thumbnail support to dish_recipe.
	 *
	 * The Basecamp theme enables thumbnails only for 'post' and 'page'.
	 * Calling at priority 11 (after the theme's 10) appends our type safely.
	 */
	public function add_thumbnail_support(): void {
		add_theme_support( 'post-thumbnails', [ 'dish_recipe' ] );
	}

	// -------------------------------------------------------------------------
	// Rewrite flush
	// -------------------------------------------------------------------------

	/**
	 * Flush rewrite rules once after activation, when CPTs are already registered.
	 * The flag is set by Activator::activate() and cleared here after flushing.
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_option( 'dish_recipes_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'dish_recipes_flush_rewrite_rules' );
		}
	}
}
