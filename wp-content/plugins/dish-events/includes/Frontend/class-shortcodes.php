<?php
/**
 * Shortcode registrar.
 *
 * Registers all [dish_*] shortcodes on the init hook. Rendering is delegated
 * to dedicated view classes (ClassView, ChefView) or handled inline for the
 * simpler account templates.
 *
 * Shortcodes registered:
 *   [dish_classes  limit="12" format_id=""]   → Class instance archive grid
 *   [dish_chefs    limit="12"]                → Chef card grid
 *   [dish_login]                              → WordPress login form
 *   [dish_register]                           → WordPress registration form
 *   [dish_profile]                            → Logged-in user booking history
 *
 * @package Dish\Events\Frontend
 */

declare( strict_types=1 );

namespace Dish\Events\Frontend;

use Dish\Events\Core\Loader;
use Dish\Events\Frontend\FormatView;

/**
 * Class Shortcodes
 */
final class Shortcodes {

	/**
	 * Register hooks via the Loader.
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'init', $this, 'register' );
	}

	// -------------------------------------------------------------------------
	// Shortcode registration
	// -------------------------------------------------------------------------

	/**
	 * Register all [dish_*] shortcodes.
	 */
	public function register(): void {
		add_shortcode( 'dish_classes',         [ ClassView::class,  'render_archive'    ] );
		add_shortcode( 'dish_chefs',           [ ChefView::class,   'render_archive'    ] );
		add_shortcode( 'dish_formats',         [ FormatView::class, 'render_archive'    ] );
		add_shortcode( 'dish_class_types',     [ FormatView::class, 'render_class_types'] );
		add_shortcode( 'dish_upcoming_menus',  [ MenuView::class,   'render_upcoming'   ] );
		add_shortcode( 'dish_menus',           [ MenuView::class,   'render_all'        ] );
		add_shortcode( 'dish_login',    [ $this, 'render_login'    ] );
		add_shortcode( 'dish_register', [ $this, 'render_register' ] );
		add_shortcode( 'dish_profile',  [ $this, 'render_profile'  ] );
		add_shortcode( 'dish_reviews',  [ $this, 'render_reviews'  ] );
	}

	// -------------------------------------------------------------------------
	// Account shortcode renderers
	// -------------------------------------------------------------------------

	/**
	 * [dish_login] — renders a login form, or a "you are already logged in"
	 * notice when the visitor is authenticated.
	 *
	 * @param array<string,string> $atts  Unused; reserved for future redirect_to etc.
	 * @return string HTML output.
	 */
	public function render_login( array $atts = [] ): string {
		ob_start();
		include Frontend::locate( 'account/login.php' );
		return (string) ob_get_clean();
	}

	/**
	 * [dish_register] — renders a registration form, or a redirect notice
	 * if registration is disabled or the user is already logged in.
	 *
	 * @param array<string,string> $atts  Unused; reserved for future use.
	 * @return string HTML output.
	 */
	public function render_register( array $atts = [] ): string {
		ob_start();
		include Frontend::locate( 'account/register.php' );
		return (string) ob_get_clean();
	}

	/**
	 * [dish_profile] — renders the logged-in user's booking history.
	 * Redirects (or shows a notice) when the visitor is not authenticated.
	 *
	 * @param array<string,string> $atts  Unused; reserved for future use.
	 * @return string HTML output.
	 */
	/**
	 * [dish_reviews] — renders Google reviews from the server-side transient cache.
	 * Outputs nothing when no API credentials are configured or when the cache
	 * is empty.
	 *
	 * @param array<string,string> $atts  Unused; reserved for future use.
	 * @return string HTML output.
	 */
	public function render_reviews( array $atts = [] ): string {
		$reviews = \Dish\Events\Core\GoogleReviews::get();

		if ( empty( $reviews ) ) {
			return '';
		}

		ob_start();
		include Frontend::locate( 'reviews.php' );
		return (string) ob_get_clean();
	}
}
