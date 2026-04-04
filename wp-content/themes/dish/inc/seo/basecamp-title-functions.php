<?php

declare(strict_types=1);
/**
 * Basecamp Title System
 *
 * Extensible page title manager. TitleCore handles all standard
 * WordPress contexts. Additional extensions can be registered in
 * TitleManager::$extensions to handle custom post types, taxonomies,
 * or plugin-specific contexts (e.g. WooCommerce).
 *
 * To add an extension:
 *  1. Define a class with a static maybe_title( $title ) method.
 *  2. Return a formatted title string if the current request is your concern.
 *  3. Return null to pass control to the next extension.
 *  4. Add the FQCN to TitleManager::$extensions.
 *
 * Extensions are evaluated in array order. Basecamp_Title_Core always runs last
 * as the catch-all fallback.
 *
 * @package basecamp
 */

namespace Basecamp\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// Core — catch-all fallback. Always runs last.
// =============================================================================

final class TitleCore {
	public static function maybe_title( $title ) {
		$site_name = get_bloginfo( 'name' );
		if ( empty( $title ) && is_singular() ) {
			$title = get_the_title();
		}
		if ( empty( $title ) ) {
			return $site_name;
		}
		if ( strpos( $title, $site_name ) === false ) {
			return "$title - $site_name";
		}
		return $title;
	}
}

// =============================================================================
// Extension examples — copy, adapt, and register in $extensions below.
// =============================================================================

/*
// Example: Custom post type extension.
// Handles a 'portfolio' CPT, its taxonomy, and its archive.
class Basecamp_Title_Portfolio {
	public static function maybe_title( $title ) {
		if ( is_singular( 'portfolio' ) || is_tax( 'portfolio_category' ) || is_post_type_archive( 'portfolio' ) ) {
			$site_name = get_bloginfo( 'name' );
			if ( is_singular( 'portfolio' ) ) {
				return get_the_title() . ' - Portfolio - ' . $site_name;
			} elseif ( is_tax( 'portfolio_category' ) ) {
				$term = get_queried_object();
				return $term->name . ' - Portfolio - ' . $site_name;
			} elseif ( is_post_type_archive( 'portfolio' ) ) {
				return 'Portfolio - ' . $site_name;
			}
		}
		return null;
	}
}
*/

/*
// Example: WooCommerce extension. Uncomment when WooCommerce is active.
class Basecamp_Title_Woo {
	public static function maybe_title( $title ) {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return null;
		}
		if ( is_woocommerce() || is_shop() || is_product_category() || is_product_tag() ) {
			$site_name = get_bloginfo( 'name' );
			if ( is_product() ) {
				return get_the_title() . ' - Shop - ' . $site_name;
			} elseif ( is_product_category() || is_product_tag() ) {
				$term = get_queried_object();
				return $term->name . ' - Shop - ' . $site_name;
			} elseif ( is_shop() ) {
				return 'Shop - ' . $site_name;
			}
		}
		return null;
	}
}
*/

// =============================================================================
// Manager — registers filters and runs extensions in order.
// =============================================================================

final class TitleManager {

	/**
	 * Registered extension class names.
	 * Add custom extension class names here to activate them.
	 *
	 * @var string[]
	 */
	protected static $extensions = [
                'Basecamp\\SEO\\TitleDishEvents', // Hierarchical titles for dish CPTs.
		// 'Basecamp\\SEO\\TitleWoo',
	];

	public static function init() {
		add_filter( 'pre_get_document_title', [ __CLASS__, 'filter_title' ], 1 );
		add_filter( 'wp_title',               [ __CLASS__, 'filter_wp_title' ], 1, 2 );
	}

	public static function filter_title( $title ) {
		foreach ( self::$extensions as $ext ) {
			if ( class_exists( $ext ) && is_callable( [ $ext, 'maybe_title' ] ) ) {
				$result = $ext::maybe_title( $title );
				if ( null !== $result ) {
					return $result;
				}
			}
		}
		return TitleCore::maybe_title( $title );
	}

	public static function filter_wp_title( $title, $sep ) {
		return self::filter_title( $title );
	}
}

TitleManager::init();
