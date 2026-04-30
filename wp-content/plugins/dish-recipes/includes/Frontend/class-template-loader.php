<?php
/**
 * Template Loader.
 *
 * Resolves which template file to use for dish_recipe CPT requests.
 * Theme templates take precedence over plugin defaults.
 *
 * Theme override location: {theme}/dish-recipes/{template}.php
 * Plugin defaults:         {plugin}/templates/{template}.php
 *
 * @package Dish\Recipes\Frontend
 */

declare( strict_types=1 );

namespace Dish\Recipes\Frontend;

/**
 * Class TemplateLoader
 */
final class TemplateLoader {

	/**
	 * Filter template_include to serve plugin templates for dish_recipe requests.
	 * Hooked to 'template_include'.
	 *
	 * @param string $template Resolved template path from WordPress.
	 * @return string
	 */
	public function load( string $template ): string {
		if ( is_singular( 'dish_recipe' ) ) {
			return $this->locate( 'single.php' ) ?: $template;
		}

		if ( is_post_type_archive( 'dish_recipe' ) ) {
			return $this->locate( 'archive.php' ) ?: $template;
		}

		if ( is_tax( 'dish_recipe_category' ) ) {
			return $this->locate( 'archive.php' ) ?: $template;
		}

		return $template;
	}

	// -------------------------------------------------------------------------
	// Public locate — callable from templates for partials
	// -------------------------------------------------------------------------

	/**
	 * Locate a template file, preferring the theme override.
	 *
	 * Theme override path : {theme}/dish-recipes/{filename}
	 * Plugin default path : {plugin}/templates/{filename}
	 *
	 * @param string $filename Template filename (e.g. 'card.php').
	 * @return string Absolute path, or empty string if not found.
	 */
	public function locate( string $filename ): string {
		$theme_path  = get_stylesheet_directory() . '/dish-recipes/' . $filename;
		$plugin_path = DISH_RECIPES_PATH . 'templates/' . $filename;

		if ( file_exists( $theme_path ) ) {
			return $theme_path;
		}

		if ( file_exists( $plugin_path ) ) {
			return $plugin_path;
		}

		return '';
	}

	/**
	 * Load (include) a located template file.
	 * Passes $data variables into the template scope.
	 *
	 * @param string               $filename Template filename.
	 * @param array<string, mixed> $data     Variables to extract into template scope.
	 */
	public function load_template( string $filename, array $data = [] ): void {
		$path = $this->locate( $filename );

		if ( ! $path ) {
			return;
		}

		if ( ! empty( $data ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $data, EXTR_SKIP );
		}

		include $path;
	}
}
