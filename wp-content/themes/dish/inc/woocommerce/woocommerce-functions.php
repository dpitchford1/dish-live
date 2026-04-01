<?php
/**
 * WooCommerce integration for Basecamp theme.
 *
 * This file is intentionally not loaded by default. Enable it by uncommenting
 * the require_once line in functions.php once WooCommerce is installed:
 *
 *   require_once __DIR__ . '/inc/woocommerce/woocommerce-functions.php';
 *
 * Everything in this file is guarded by a WooCommerce activation check, so it
 * is safe to load unconditionally — it simply no-ops if the plugin is absent.
 *
 * @package basecamp
 */

namespace Basecamp\Ecommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce theme integration.
 */
class WooCommerceIntegration {

	/**
	 * Register hooks. Bails silently if WooCommerce is not active.
	 */
	public static function init(): void {
		if ( ! self::is_active() ) {
			return;
		}
		add_action( 'after_setup_theme', [ __CLASS__, 'setup' ] );
		add_action( 'init',              [ __CLASS__, 'setup_layout' ] );
	}

	// =============================================================================
	// Helpers
	// =============================================================================

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	// =============================================================================
	// Theme support
	// =============================================================================

	/**
	 * Declare WooCommerce theme support.
	 *
	 * Called on after_setup_theme so it runs before WooCommerce's own init hooks.
	 * Adjust the product_gallery keys to match your template's lightbox / zoom setup.
	 */
	public static function setup(): void {
		add_theme_support(
			'woocommerce',
			apply_filters(
				'basecamp_woocommerce_args',
				[
					'thumbnail_image_width' => 600,
					'single_image_width'    => 800,
					'product_grid'          => [
						'default_rows'    => 3,
						'min_rows'        => 1,
						'default_columns' => 3,
						'min_columns'     => 1,
						'max_columns'     => 6,
					],
				]
			)
		);

		// Product gallery features — comment out any you don't need.
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}

	// =============================================================================
	// Layout tweaks
	// =============================================================================

	/**
	 * Remove the default WooCommerce sidebar.
	 *
	 * Basecamp removes all sidebars globally. This hook ensures WooCommerce's own
	 * sidebar action is also removed so no empty wrapper markup is output.
	 */
	public static function setup_layout(): void {
		remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
	}

	// =============================================================================
	// Add further WooCommerce hooks below.
	// Common extension points:
	//
	//   - woocommerce_before_main_content  / woocommerce_after_main_content
	//   - woocommerce_shop_loop_item_title
	//   - woocommerce_single_product_summary
	//   - woocommerce_checkout_fields       (filter)
	//
	// Remove default WooCommerce styles:
	//   add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
	// =============================================================================
}

WooCommerceIntegration::init();
