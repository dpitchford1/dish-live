<?php

declare(strict_types=1);
/**
 * Development helpers for Basecamp theme.
 *
 * @package basecamp
 */

namespace Basecamp\Development;

final class Development {

	public function __construct() {
		if ( $this->is_local() ) {
			require_once __DIR__ . '/template.php';
			add_action( 'wp_footer', [ $this, 'render_devpilot' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_dev_assets' ] );
		}
	}

	/**
	 * Check if environment is local.
	 */
	public function is_local() {
		return in_array( $_SERVER['REMOTE_ADDR'], [ '127.0.0.1', '::1' ] );
	}

	/**
	 * Enqueue DevPilot CSS/JS.
	 */
	public function enqueue_dev_assets() {
		$dev_uri = get_template_directory_uri() . '/inc/development';
		wp_enqueue_style( 'devpilot-drawer', $dev_uri . '/css/devpilot-drawer.min.css', [], null );
		wp_enqueue_style( 'html-outline', $dev_uri . '/css/html-outline.min.css', [], null );
		wp_enqueue_script( 'devpilot-working', $dev_uri . '/js/working.min.js', [], null, true );
		wp_enqueue_script( 'html-outline', $dev_uri . '/js/html-outline.min.js', [], null, true );
	}

	/**
	 * Render the DevPilot bar.
	 */
	public function render_devpilot() {
		include __DIR__ . '/development-pilot.php';
	}
}
