<?php

declare(strict_types=1);
/**
 * Admin and sanitization helpers for Basecamp theme.
 *
 * @package basecamp
 */

namespace Basecamp\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Miscellaneous admin helpers — sanitizers, timeouts, MIME-type allowlisting.
 */
final class AdminHelpers {

	/**
	 * Register all hooks managed by this class.
	 */
	public static function init(): void {
		add_action( 'admin_init', [ __CLASS__, 'increase_admin_timeout' ], 5 );
		add_filter( 'upload_mimes', [ __CLASS__, 'add_mime_types' ] );
	}

	/**
	 * Sanitizes a select / radio Customizer control value against its choices.
	 *
	 * @param mixed                 $input   Raw input value.
	 * @param \WP_Customize_Setting $setting Customizer setting object.
	 * @return mixed Sanitized value or the control default.
	 */
	public static function sanitize_choices( $input, $setting ) {
		$input   = sanitize_key( $input );
		$choices = $setting->manager->get_control( $setting->id )->choices;
		return array_key_exists( $input, $choices ) ? $input : $setting->default;
	}

	/**
	 * Sanitizes a checkbox Customizer control value.
	 *
	 * @param mixed $checked Raw input.
	 * @return bool
	 */
	public static function sanitize_checkbox( $checked ): bool {
		return isset( $checked ) && true === $checked;
	}

	/**
	 * Raises the PHP execution time limit on post-editing screens.
	 */
	public static function increase_admin_timeout(): void {
		if ( ! is_admin() ) {
			return;
		}
		global $pagenow;
		if ( in_array( $pagenow, [ 'post.php', 'post-new.php', 'edit.php' ], true ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Allow SVG uploads.
	 *
	 * @param array $mime_types Existing allowed MIME types.
	 * @return array
	 */
	public static function add_mime_types( array $mime_types ): array {
		$mime_types['svg'] = 'image/svg+xml';
		return $mime_types;
	}
}

AdminHelpers::init();
