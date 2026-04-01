<?php
/**
 * Conditional Google Analytics loader.
 *
 * The GA4 Measurement ID is configured via Appearance → Theme Settings.
 * To lock the ID at the server level, define the constant in wp-config.php — it
 * takes precedence over the database value:
 *
 *   define( 'BASECAMP_GA_MEASUREMENT_ID', 'G-XXXXXXXXXX' );
 *
 * Environment detection uses the BASECAMP_ENV server variable (set in wp-config.php):
 *   BASECAMP_ENV=local / staging  → script loads but no config hit is sent
 *   BASECAMP_ENV=production       → full tracking
 *   (not set)                     → treated as production
 *
 * @package basecamp
 */

namespace Basecamp\ThemeFunctions;

use Basecamp\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages conditional Google Analytics loading.
 */
class Analytics {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'output_snippet' ], 5 );
	}

	/**
	 * Returns true when the current environment should send GA hits.
	 * Reads BASECAMP_ENV from the server environment (set in wp-config.php).
	 */
	public static function is_prod_host(): bool {
		$env = getenv( 'BASECAMP_ENV' );
		if ( false !== $env && '' !== $env ) {
			return 'production' === $env;
		}
		// No BASECAMP_ENV set — treat as production so tracking fires on live sites.
		return true;
	}

	/**
	 * Output the GA4 snippet.
	 * Loads on all environments; only sends config hits on production.
	 * Bails silently if no GA ID has been configured.
	 */
	public static function output_snippet(): void {
		$id = (string) Settings::get( 'ga_id' );
		if ( '' === $id ) {
			return;
		}

		$is_prod = self::is_prod_host();

		if ( $is_prod || apply_filters( 'basecamp_ga_hints_on_nonprod', false ) ) {
			?>
			<link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
			<link rel="dns-prefetch" href="//www.googletagmanager.com">
			<link rel="preconnect" href="https://www.google-analytics.com" crossorigin>
			<link rel="dns-prefetch" href="//www.google-analytics.com">
			<?php
			if ( apply_filters( 'basecamp_ga_preload_enabled', false ) && $is_prod ) : ?>
				<link rel="preload" as="script" href="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $id ); ?>">
			<?php endif;
		}
		?>
		<!-- Google Analytics -->
		<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $id ); ?>"></script>
		<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		<?php if ( $is_prod ) : ?>
		gtag('config', '<?php echo esc_js( $id ); ?>', { transport_type: 'beacon' });
		<?php else : ?>
		console.info('[basecamp][GA] Non-production: no hits sent. Host:', location.host);
		<?php endif; ?>
		</script>
		<?php
	}
}

Analytics::init();
