<?php
/**
 * Basecamp Theme — Bootstrap
 *
 * Loads all theme modules in dependency order:
 *   Core → Settings → Frontend → Admin → SEO → Theme Functions → WebP → REST → Cron → Dev → WooCommerce
 *
 * To disable a module, comment out its require_once line.
 *
 * @package basecamp
 */

// ---------------------------------------------------------------------------
// Theme version + global object
// ---------------------------------------------------------------------------

$theme            = wp_get_theme( 'basecamp' );
$basecamp_version = $theme['Version'];

$basecamp = (object) array(
	'version' => $basecamp_version,
	'main'    => require_once __DIR__ . '/inc/class-basecamp.php',
);

// ---------------------------------------------------------------------------
// Settings (must load before Frontend and Theme Functions — provides Basecamp_Settings::get())
// ---------------------------------------------------------------------------

require_once __DIR__ . '/inc/admin/class-basecamp-settings.php';
// Back-compat alias — allows ::init() and ::get() calls below to work without use statements.
class_alias( 'Basecamp\Admin\Settings', 'Basecamp_Settings' );
Basecamp_Settings::init();

// ---------------------------------------------------------------------------
// Frontend
// ---------------------------------------------------------------------------

require_once __DIR__ . '/inc/frontend/class-basecamp-svg-icons.php';
require_once __DIR__ . '/inc/frontend/class-basecamp-frontend.php';
// Back-compat alias — header.php and template parts call Basecamp_Frontend:: directly.
class_alias( 'Basecamp\Frontend\Frontend', 'Basecamp_Frontend' );
require_once __DIR__ . '/inc/frontend/remove-bloat.php';
require_once __DIR__ . '/inc/frontend/class-basecamp-cookie-consent.php';
require_once __DIR__ . '/inc/frontend/class-basecamp-toast.php';

/**
 * Template tag — output the announcement toast bar.
 * Exits silently when disabled or unconfigured.
 * Drop <?php the_toast(); ?> anywhere in a template.
 */
function the_toast(): void {
	\Basecamp\Frontend\Toast::render();
}

require_once __DIR__ . '/inc/frontend/basecamp-page-helpers.php';
require_once __DIR__ . '/inc/frontend/basecamp-subnav.php';
//require_once __DIR__ . '/inc/frontend/class-basecamp-video-carousel-metabox.php';
$basecamp_frontend = new Basecamp_Frontend();

// ---------------------------------------------------------------------------
// Admin
// ---------------------------------------------------------------------------

if ( is_admin() ) {
	require_once __DIR__ . '/inc/admin/class-basecamp-admin.php';
	require_once __DIR__ . '/inc/admin/basecamp-admin-helpers.php';
	require_once __DIR__ . '/inc/admin/class-basecamp-docs.php';
	require_once __DIR__ . '/inc/admin/basecamp-media.php';
}

// Replace the WP logo on the login screen with the Dish logo.
// login_enqueue_scripts fires outside is_admin(), so this must live outside that gate.
add_action( 'login_enqueue_scripts', function () {
	?>
	<style>
		#login h1 a, .login h1 a {
			background-image: url(<?php echo esc_url( home_url( '/assets/img/logos/screenshot.png' ) ); ?>);
			height: 150px;
			width: 300px;
			background-size: 300px auto;
			background-repeat: no-repeat;
		}
	</style>
	<?php
} );
// Note: login_enqueue_scripts fires outside is_admin(), so this must live outside that gate.
add_action( 'login_enqueue_scripts', function () {
	?>
	<style>
		#login h1 a, .login h1 a {
			background-image: url(<?php echo esc_url( home_url( '/assets/img/logos/screenshot.png' ) ); ?>);
			height: 150px;
			width: 300px;
			background-size: 300px auto;
			background-repeat: no-repeat;
		}
	</style>
	<?php
} );

// ---------------------------------------------------------------------------
// SEO
// ---------------------------------------------------------------------------

require_once __DIR__ . '/inc/seo/class-basecamp-seo.php';

// ---------------------------------------------------------------------------
// Theme Functions
// ---------------------------------------------------------------------------

// require_once __DIR__ . '/inc/theme-functions/basecamp-meta-link-list.php';

/**
 * Global template tag for the link list — delegates to MetaLinkList::get().
 * Declared here (no namespace) so it resolves as a true global function.
 *
 * @param int|null $post_id
 * @return array
 */
function basecamp_get_link_list( ?int $post_id = null ): array {
	return \Basecamp\ThemeFunctions\MetaLinkList::get( $post_id );
}
require_once __DIR__ . '/inc/theme-functions/basecamp-analytics.php';

// CPT scaffold — copy/rename for each project CPT, then uncomment and call ::init()
// require_once __DIR__ . '/inc/theme-functions/basecamp-cpt-scaffold.php';
// Basecamp_CPT_Scaffold::init();

// ---------------------------------------------------------------------------
// Image Optimization (WebP)
// ---------------------------------------------------------------------------

if ( Basecamp_Settings::get( 'webp_optimization', '1' ) ) {
	require_once __DIR__ . '/inc/img-optimization/basecamp-webp-functions.php';
	require_once __DIR__ . '/inc/img-optimization/basecamp-webp-conversion.php';
	require_once __DIR__ . '/inc/img-optimization/webp-test-admin.php';
}

require_once __DIR__ . '/inc/img-optimization/basecamp-thumb-regen.php';

// ---------------------------------------------------------------------------
// REST API
// ---------------------------------------------------------------------------

require_once __DIR__ . '/inc/rest/basecamp-rest-endpoints.php';

// ---------------------------------------------------------------------------
// Scheduled Events (Cron)
// ---------------------------------------------------------------------------

require_once __DIR__ . '/inc/core/basecamp-scheduled-events.php';

// ---------------------------------------------------------------------------
// Development Tools (local only)
// ---------------------------------------------------------------------------

if ( in_array( $_SERVER['REMOTE_ADDR'], [ '127.0.0.1', '::1' ] ) ) {
	require_once __DIR__ . '/inc/development/class-basecamp-development.php';
	$basecamp_development = new \Basecamp\Development\Development();
}

// ---------------------------------------------------------------------------
// Ecommerce — WooCommerce (toggle: uncomment to activate)
// Requires WooCommerce plugin. The file handles activation checks internally.
// ---------------------------------------------------------------------------

// require_once __DIR__ . '/inc/woocommerce/woocommerce-functions.php';
