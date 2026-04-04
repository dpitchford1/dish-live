<?php
/**
 * Plugin Name:       Dish Events
 * Plugin URI:        https://dishcookingstudio.com
 * Description:       Classes, chefs, and bookings for Dish Cooking Studio.
 * Version:           1.0.2
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Kaneism Design
 * Author URI:        https://kaneism.com
 * Text Domain:       dish-events
 * Domain Path:       /languages
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define( 'DISH_EVENTS_VERSION', '1.0.2' );
define( 'DISH_EVENTS_FILE',    __FILE__ );
define( 'DISH_EVENTS_PATH',    plugin_dir_path( __FILE__ ) );
define( 'DISH_EVENTS_URL',     plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// PSR-4 Autoloader  (no Composer dependency)
//
// Namespace root : Dish\Events\
// Maps to        : includes/{SubNamespace}/class-{kebab-name}.php
//
// Rules:
//   - CamelCase class name → kebab-case filename
//   - Classes ending in "Interface" use the interface- file prefix
//
// Examples:
//   Dish\Events\Core\Plugin           → includes/Core/class-plugin.php
//   Dish\Events\CPT\ClassPost         → includes/CPT/class-class-post.php
//   Dish\Events\Payments\GatewayInterface → includes/Payments/interface-gateway.php
// ---------------------------------------------------------------------------

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'Dish\\Events\\';

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

	$path = DISH_EVENTS_PATH . 'includes/'
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

register_activation_hook( __FILE__, [ 'Dish\\Events\\Core\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Dish\\Events\\Core\\Deactivator', 'deactivate' ] );

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

add_action( 'plugins_loaded', [ 'Dish\\Events\\Core\\Plugin', 'run' ] );
