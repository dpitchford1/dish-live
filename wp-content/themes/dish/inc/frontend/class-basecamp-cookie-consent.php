<?php

declare(strict_types=1);
/**
 * Cookie Consent
 *
 * GDPR & CCPA compliant cookie consent banner with Google Consent Mode v2.
 * Blocks analytics cookies until explicit consent is given.
 *
 * Features:
 *  - GA Consent Mode v2 defaults injected before gtag loads (wp_head priority 4)
 *  - Banner rendered in wp_footer, shown only when no stored preference exists
 *  - Consent stored in localStorage (client-side only, no server logging needed)
 *  - Admin settings page under Settings → Cookie Consent
 *  - [cookie_preferences] shortcode for a "Manage preferences" button
 *
 * @package basecamp
 */

namespace Basecamp\Frontend;

use Basecamp\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CookieConsent {

	const OPTION_KEY = 'basecamp_cookie_settings';

	/**
	 * Register all hooks.
	 * Bails early when the cookie compliance feature is disabled in Theme Settings.
	 */
	public static function init(): void {
		if ( ! Settings::get( 'cookie_compliance', '1' ) ) {
			return;
		}

		add_action( 'wp_head',             [ __CLASS__, 'output_consent_defaults' ], 4 );
		add_action( 'wp_enqueue_scripts',  [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_footer',           [ __CLASS__, 'render_banner' ], 100 );
		add_action( 'admin_menu',          [ __CLASS__, 'add_settings_page' ] );
		add_action( 'admin_init',          [ __CLASS__, 'register_settings' ] );
		add_shortcode( 'cookie_preferences', [ __CLASS__, 'preferences_shortcode' ] );
	}

	// =========================================================================
	// Settings helpers
	// =========================================================================

	/**
	 * Return merged settings with defaults.
	 *
	 * @return array<string, string>
	 */
	public static function get_settings(): array {
		$defaults = [
			'headline'     => __( 'We use cookies', 'basecamp' ),
			'body'         => __( 'We use analytics cookies to understand how visitors interact with our site. Your data is never sold. You can accept or decline non-essential cookies.', 'basecamp' ),
			'accept_text'  => __( 'Accept', 'basecamp' ),
			'decline_text' => __( 'Decline', 'basecamp' ),
			'policy_url'   => get_privacy_policy_url(),
			'position'     => 'bottom',
		];

		return wp_parse_args( get_option( self::OPTION_KEY, [] ), $defaults );
	}

	// =========================================================================
	// Frontend output
	// =========================================================================

	/**
	 * True when the visitor has already accepted or declined on a previous page load.
	 * Uses the browser cookie set by JS — readable server-side so we can skip
	 * rendering the banner HTML and enqueueing the consent script entirely.
	 */
	private static function has_stored_consent(): bool {
		$val = isset( $_COOKIE['basecamp_cookie_consent'] )
			? sanitize_key( $_COOKIE['basecamp_cookie_consent'] )
			: '';
		return in_array( $val, [ 'accepted', 'declined' ], true );
	}

	/**
	 * Inject Google Consent Mode v2 defaults before gtag loads (priority 4).
	 * gtag snippet is at priority 5, so this runs first.
	 * Pushes consent/default into dataLayer; GA processes it before any config call.
	 */
	public static function output_consent_defaults(): void {
		if ( ! Settings::get( 'ga_id' ) ) {
			return;
		}

		$stored   = isset( $_COOKIE['basecamp_cookie_consent'] )
			? sanitize_key( $_COOKIE['basecamp_cookie_consent'] )
			: '';
		$analytics = ( $stored === 'accepted' ) ? 'granted' : 'denied';
		?>
		<script>
		window.dataLayer = window.dataLayer || [];
		window.gtag = window.gtag || function () { window.dataLayer.push( arguments ); };
		gtag( 'consent', 'default', {
			analytics_storage : '<?php echo esc_js( $analytics ); ?>',
			ad_storage        : 'denied'<?php if ( $stored === '' ) : ?>,
			wait_for_update   : 500<?php endif; ?>
		} );
		</script>
		<?php
	}

	/**
	 * Enqueue banner CSS and JS.
	 */
	public static function enqueue_assets(): void {
		// Cookie already set — PHP handles GA consent state inline; no JS needed.
		if ( self::has_stored_consent() ) {
			return;
		}

		$ver = wp_get_theme()->get( 'Version' );

		// wp_enqueue_style(
		// 	'basecamp-cookie-consent',
		// 	esc_url( site_url( '/assets/css/build/cookie-consent.min.css' ) ),
		// 	[],
		// 	$ver
		// );

		wp_enqueue_script(
			'basecamp-cookie-consent',
			esc_url( site_url( '/assets/js/resources/cookie-consent.min.js' ) ),
			[],
			$ver,
			true
		);

		// Pass GA ID to JS so the consent update can include it.
		wp_localize_script( 'basecamp-cookie-consent', 'basecampCookieConfig', [
			'gaId' => (string) Settings::get( 'ga_id', '' ),
		] );
	}

	/**
	 * Render the consent banner in the footer (hidden by default via `hidden` attr).
	 * JS removes the attribute when no stored preference is found.
	 */
	public static function render_banner(): void {
		// Consent already stored — nothing to render.
		if ( self::has_stored_consent() ) {
			return;
		}

		$s              = self::get_settings();
		$position_class = $s['position'] === 'top' ? 'cookie-banner--top' : 'cookie-banner--bottom';

		$policy_link = '';
		if ( ! empty( $s['policy_url'] ) ) {
			$policy_link = sprintf(
				' <a href="%s" class="cookie-banner__link">%s</a>',
				esc_url( $s['policy_url'] ),
				esc_html__( 'Privacy Policy', 'basecamp' )
			);
		}
		?>
		<div
			id="basecamp-cookie-banner"
			class="cookie-banner <?php echo esc_attr( $position_class ); ?>"
			role="dialog"
			aria-label="<?php esc_attr_e( 'Cookie consent', 'basecamp' ); ?>"
			aria-live="polite"
			hidden
		>
			<div class="cookie-banner__inner">
				<div class="cookie-banner__content">
					<?php if ( ! empty( $s['headline'] ) ) : ?>
					<p class="cookie-banner__headline"><?php echo esc_html( $s['headline'] ); ?></p>
					<?php endif; ?>
					<p class="cookie-banner__body">
						<?php echo esc_html( $s['body'] ); ?>
						<?php echo $policy_link; // Already escaped above. ?>
					</p>
				</div>
				<div class="cookie-banner__actions">
					<button id="basecamp-cookie-accept" class="cookie-banner__btn cookie-banner__btn--accept" type="button">
						<?php echo esc_html( $s['accept_text'] ); ?>
					</button>
					<button id="basecamp-cookie-decline" class="cookie-banner__btn cookie-banner__btn--decline" type="button">
						<?php echo esc_html( $s['decline_text'] ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Shortcode: [cookie_preferences]
	 * Renders a "Manage cookie preferences" button that re-opens the banner.
	 * Add to the Privacy Policy page to give users a way to change their choice.
	 *
	 * @return string
	 */
	public static function preferences_shortcode(): string {
		// Clears both localStorage and the server-readable cookie, then reloads
		// so PHP serves the full banner HTML + JS on the next page load.
		// Intentionally inline — the consent JS may not be loaded on this page.
		$onclick = "localStorage.removeItem('basecamp_cookie_consent');"
			. "document.cookie='basecamp_cookie_consent=;path=/;max-age=0;SameSite=Lax';"
			. "location.reload();";

		return sprintf(
			'<button type="button" class="cookie-preferences-btn" onclick="%s">%s</button>',
			esc_attr( $onclick ),
			esc_html__( 'Manage cookie preferences', 'basecamp' )
		);
	}

	// =========================================================================
	// Admin settings page
	// =========================================================================

	/**
	 * Register the Settings submenu page.
	 */
	public static function add_settings_page(): void {
		add_options_page(
			__( 'Cookie Consent Settings', 'basecamp' ),
			__( 'Cookie Consent', 'basecamp' ),
			'manage_options',
			'basecamp-cookie-settings',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings, sections and fields.
	 */
	public static function register_settings(): void {
		register_setting( 'basecamp_cookie_group', self::OPTION_KEY, [
			'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
		] );

		add_settings_section(
			'basecamp_cookie_banner_section',
			__( 'Banner Content', 'basecamp' ),
			'__return_false',
			'basecamp-cookie-settings'
		);

		$fields = [
			'headline'     => __( 'Headline', 'basecamp' ),
			'body'         => __( 'Body Text', 'basecamp' ),
			'accept_text'  => __( 'Accept Button Text', 'basecamp' ),
			'decline_text' => __( 'Decline Button Text', 'basecamp' ),
			'policy_url'   => __( 'Privacy Policy URL', 'basecamp' ),
			'position'     => __( 'Banner Position', 'basecamp' ),
		];

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'basecamp_cookie_' . $key,
				$label,
				[ __CLASS__, 'render_field' ],
				'basecamp-cookie-settings',
				'basecamp_cookie_banner_section',
				[ 'key' => $key ]
			);
		}
	}

	/**
	 * Render an individual settings field.
	 *
	 * @param array{key: string} $args
	 */
	public static function render_field( array $args ): void {
		$settings = self::get_settings();
		$key      = $args['key'];
		$val      = $settings[ $key ] ?? '';
		$name     = self::OPTION_KEY . '[' . $key . ']';

		switch ( $key ) {
			case 'body':
				printf(
					'<textarea name="%s" rows="3" class="large-text">%s</textarea>',
					esc_attr( $name ),
					esc_textarea( $val )
				);
				break;

			case 'position':
				printf(
					'<select name="%s">
						<option value="bottom" %s>%s</option>
						<option value="top" %s>%s</option>
					</select>',
					esc_attr( $name ),
					selected( $val, 'bottom', false ),
					esc_html__( 'Bottom', 'basecamp' ),
					selected( $val, 'top', false ),
					esc_html__( 'Top', 'basecamp' )
				);
				break;

			default:
				printf(
					'<input type="text" name="%s" value="%s" class="regular-text">',
					esc_attr( $name ),
					esc_attr( $val )
				);
				break;
		}

		// Contextual hints.
		if ( $key === 'policy_url' ) {
			echo '<p class="description">' . esc_html__( 'Leave blank to use the WordPress Privacy Policy page URL.', 'basecamp' ) . '</p>';
		}
		if ( $key === 'accept_text' || $key === 'decline_text' ) {
			echo '<p class="description">' . esc_html__( 'Keep short — single word preferred.', 'basecamp' ) . '</p>';
		}
	}

	/**
	 * Sanitize settings on save.
	 *
	 * @param  mixed $input Raw POST data.
	 * @return array<string, string>
	 */
	public static function sanitize_settings( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$clean = [];

		foreach ( [ 'headline', 'accept_text', 'decline_text' ] as $field ) {
			$clean[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
		}

		$clean['body']       = isset( $input['body'] ) ? sanitize_textarea_field( $input['body'] ) : '';
		$clean['policy_url'] = isset( $input['policy_url'] ) ? esc_url_raw( $input['policy_url'] ) : '';
		$clean['position']   = ( isset( $input['position'] ) && $input['position'] === 'top' ) ? 'top' : 'bottom';

		return $clean;
	}

	/**
	 * Render the full settings page.
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cookie Consent Settings', 'basecamp' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Customize the GDPR/CCPA cookie consent banner. Analytics cookies are blocked until the visitor accepts. Consent is stored in the visitor\'s browser (localStorage).', 'basecamp' ); ?>
			</p>
			<hr>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'basecamp_cookie_group' );
				do_settings_sections( 'basecamp-cookie-settings' );
				submit_button();
				?>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Manage Preferences Button', 'basecamp' ); ?></h2>
			<p><?php esc_html_e( 'Add the shortcode below to your Privacy Policy page to allow visitors to update their cookie choice:', 'basecamp' ); ?></p>
			<code>[cookie_preferences]</code>
		</div>
		<?php
	}
}

CookieConsent::init();
