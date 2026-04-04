<?php

declare(strict_types=1);
/**
 * Theme Settings
 *
 * Central admin settings page for the Basecamp theme.
 * Stored as a single serialised option array; read anywhere with Basecamp_Settings::get().
 *
 * To override the GA4 ID at the server level without touching the database, define the
 * constant in wp-config.php — it takes precedence over the saved value:
 *
 *   define( 'BASECAMP_GA_MEASUREMENT_ID', 'G-XXXXXXXXXX' );
 *
 * Menu location: Appearance → Theme Settings
 *
 * @package basecamp
 */

namespace Basecamp\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	const OPTION_KEY = 'basecamp_theme_settings';

	/**
	 * Default values applied when the option has never been saved.
	 * Feature flags default to enabled so existing installs are unaffected on upgrade.
	 *
	 * @return array<string,string>
	 */
	private static function defaults(): array {
		return [
			'ga_id'             => '',
			'cookie_compliance' => '1',
			'gsc_verification'  => '',
			'schema_output'     => '1',
			'webp_optimization' => '1',
		];
	}

	/**
	 * Register hooks.
	 * Safe to call unconditionally — admin-only hooks never fire on the frontend.
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_settings_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'wp_head',    [ __CLASS__, 'output_gsc_tag' ], 1 );
	}

	// =========================================================================
	// Settings reader
	// =========================================================================

	/**
	 * Retrieve a single setting value.
	 *
	 * Merges saved values over defaults on first call and caches the result for
	 * the rest of the request. For `ga_id`, a defined BASECAMP_GA_MEASUREMENT_ID
	 * constant in wp-config.php takes precedence over the database value.
	 *
	 * @param  string $key     Option key.
	 * @param  mixed  $default Fallback when the key is absent from saved + defaults.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		static $settings = null;

		if ( null === $settings ) {
			$settings = wp_parse_args(
				get_option( self::OPTION_KEY, [] ),
				self::defaults()
			);
		}

		// wp-config.php constant overrides the DB value for the GA ID.
		if ( 'ga_id' === $key
			&& defined( 'BASECAMP_GA_MEASUREMENT_ID' )
			&& BASECAMP_GA_MEASUREMENT_ID
		) {
			return BASECAMP_GA_MEASUREMENT_ID;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	// =========================================================================
	// Frontend output
	// =========================================================================

	/**
	 * Inject the Google Search Console verification meta tag in <head> at priority 1.
	 * Only outputs when a verification code has been saved.
	 */
	public static function output_gsc_tag(): void {
		$code = self::get( 'gsc_verification' );
		if ( empty( $code ) ) {
			return;
		}
		printf(
			'<meta name="google-site-verification" content="%s">' . "\n",
			esc_attr( $code )
		);
	}

	// =========================================================================
	// Admin settings page
	// =========================================================================

	/**
	 * Register the Theme Settings page under Appearance.
	 */
	public static function add_settings_page(): void {
		add_theme_page(
			__( 'Theme Settings', 'basecamp' ),
			__( 'Theme Settings', 'basecamp' ),
			'manage_options',
			'basecamp-theme-settings',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	/**
	 * Register the single option, its sections, and all fields.
	 */
	public static function register_settings(): void {
		register_setting( 'basecamp_theme_group', self::OPTION_KEY, [
			'sanitize_callback' => [ __CLASS__, 'sanitize' ],
		] );

		// --- Analytics -------------------------------------------------------
		add_settings_section(
			'basecamp_settings_analytics',
			__( 'Analytics', 'basecamp' ),
			'__return_false',
			'basecamp-theme-settings'
		);

		add_settings_field(
			'basecamp_ga_id',
			__( 'GA4 Measurement ID', 'basecamp' ),
			[ __CLASS__, 'render_field' ],
			'basecamp-theme-settings',
			'basecamp_settings_analytics',
			[ 'key' => 'ga_id' ]
		);

		// --- Privacy & Compliance --------------------------------------------
		add_settings_section(
			'basecamp_settings_compliance',
			__( 'Privacy &amp; Compliance', 'basecamp' ),
			'__return_false',
			'basecamp-theme-settings'
		);

		add_settings_field(
			'basecamp_cookie_compliance',
			__( 'Cookie Consent Banner', 'basecamp' ),
			[ __CLASS__, 'render_field' ],
			'basecamp-theme-settings',
			'basecamp_settings_compliance',
			[ 'key' => 'cookie_compliance' ]
		);

		// --- Features --------------------------------------------------------
		add_settings_section(
			'basecamp_settings_features',
			__( 'Features', 'basecamp' ),
			'__return_false',
			'basecamp-theme-settings'
		);

		add_settings_field(
			'basecamp_schema_output',
			__( 'Structured Data', 'basecamp' ),
			[ __CLASS__, 'render_field' ],
			'basecamp-theme-settings',
			'basecamp_settings_features',
			[ 'key' => 'schema_output' ]
		);

		add_settings_field(
			'basecamp_webp_optimization',
			__( 'WebP Image Optimisation', 'basecamp' ),
			[ __CLASS__, 'render_field' ],
			'basecamp-theme-settings',
			'basecamp_settings_features',
			[ 'key' => 'webp_optimization' ]
		);

		// --- Verification ----------------------------------------------------
		add_settings_section(
			'basecamp_settings_verification',
			__( 'Verification', 'basecamp' ),
			'__return_false',
			'basecamp-theme-settings'
		);

		add_settings_field(
			'basecamp_gsc_verification',
			__( 'Google Search Console', 'basecamp' ),
			[ __CLASS__, 'render_field' ],
			'basecamp-theme-settings',
			'basecamp_settings_verification',
			[ 'key' => 'gsc_verification' ]
		);
	}

	/**
	 * Render a field. Checkboxes for feature flags; text inputs for everything else.
	 *
	 * @param array{key: string} $args
	 */
	public static function render_field( array $args ): void {
		$key  = $args['key'];
		$val  = self::get( $key );
		$name = self::OPTION_KEY . '[' . $key . ']';

		switch ( $key ) {
			case 'cookie_compliance':
			case 'schema_output':
			case 'webp_optimization':
				printf(
					'<label><input type="checkbox" name="%s" value="1" %s> %s</label>',
					esc_attr( $name ),
					checked( $val, '1', false ),
					esc_html( self::field_label( $key ) )
				);
				break;

			default:
				printf(
					'<input type="text" name="%s" value="%s" class="regular-text">',
					esc_attr( $name ),
					esc_attr( (string) $val )
				);

				$hint = self::field_hint( $key );
				if ( $hint ) {
					echo '<p class="description">' . esc_html( $hint ) . '</p>';
				}
				break;
		}

		// Warn when the GA ID is locked by a wp-config.php constant.
		if ( 'ga_id' === $key
			&& defined( 'BASECAMP_GA_MEASUREMENT_ID' )
			&& BASECAMP_GA_MEASUREMENT_ID
		) {
			echo '<p class="description" style="color:#d63638;">&#9888; '
				. esc_html__( 'Value overridden by BASECAMP_GA_MEASUREMENT_ID constant in wp-config.php. The field above has no effect.', 'basecamp' )
				. '</p>';
		}
	}

	/**
	 * Inline label text for checkbox fields.
	 */
	private static function field_label( string $key ): string {
		$labels = [
			'cookie_compliance' => __( 'Show the GDPR/CCPA cookie consent banner and load GA Consent Mode v2. Disabling removes the banner and all consent-mode scripts.', 'basecamp' ),
			'schema_output'     => __( 'Output Schema.org JSON-LD structured data in the page head.', 'basecamp' ),
			'webp_optimization' => __( 'Automatically substitute .webp variants for uploaded images when available.', 'basecamp' ),
		];
		return $labels[ $key ] ?? '';
	}

	/**
	 * Hint text rendered beneath text input fields.
	 */
	private static function field_hint( string $key ): string {
		$hints = [
			'ga_id'            => __( 'Enter your GA4 Measurement ID (e.g. G-XXXXXXXXXX). Leave empty to disable analytics entirely. Can be overridden via BASECAMP_GA_MEASUREMENT_ID in wp-config.php.', 'basecamp' ),
			'gsc_verification' => __( 'Paste only the code value from the <meta name="google-site-verification" content="…"> tag, not the full HTML tag.', 'basecamp' ),
		];
		return $hints[ $key ] ?? '';
	}

	/**
	 * Sanitize all settings on save.
	 *
	 * @param  mixed $input Raw POST data.
	 * @return array<string,string>
	 */
	public static function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			return self::defaults();
		}

		return [
			'ga_id'             => sanitize_text_field( $input['ga_id'] ?? '' ),
			'cookie_compliance' => ! empty( $input['cookie_compliance'] ) ? '1' : '0',
			'gsc_verification'  => sanitize_text_field( $input['gsc_verification'] ?? '' ),
			'schema_output'     => ! empty( $input['schema_output'] ) ? '1' : '0',
			'webp_optimization' => ! empty( $input['webp_optimization'] ) ? '1' : '0',
		];
	}

	/**
	 * Render the settings page.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Theme Settings', 'basecamp' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Core configuration for the Basecamp theme. Controls analytics, compliance, and feature flags. See the developer docs for wp-config.php override options.', 'basecamp' ); ?>
			</p>
			<hr>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'basecamp_theme_group' );
				do_settings_sections( 'basecamp-theme-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
