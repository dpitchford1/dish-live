<?php

declare( strict_types=1 );
/**
 * Announcement Toast Bar
 *
 * Outputs a dismissable site-wide announcement bar.
 * Dismissed state is persisted in localStorage keyed to the content,
 * so changing the text or URL automatically re-shows for all visitors.
 *
 * Enabled/configured via Appearance → Theme Settings → Announcement Bar.
 *
 * Template tag (no namespace — global):
 *   the_toast()  — drop anywhere in a template.
 *
 * @package basecamp
 */

namespace Basecamp\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Toast {

	/**
	 * Output the toast bar if enabled and text is set.
	 * Exits silently when disabled or unconfigured — safe to call unconditionally.
	 */
	public static function render(): void {
		if ( \Basecamp_Settings::get( 'toast_enabled' ) !== '1' ) {
			return;
		}

		$text = trim( (string) \Basecamp_Settings::get( 'toast_text', '' ) );
		if ( empty( $text ) ) {
			return;
		}

		$url = trim( (string) \Basecamp_Settings::get( 'toast_url', '' ) );

		// Storage key is hashed from content so any edit auto-resets dismissal.
		$storage_key = 'basecamp_toast_' . md5( $text . $url );
		?>
		<div class="toast fluid-content" id="basecamp-toast" role="region" aria-label="<?php esc_attr_e( 'Announcement', 'basecamp' ); ?>" hidden>
			<div class="toast-content">
				<?php if ( $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" class="toast-message"><?php echo esc_html( $text ); ?></a>
				<?php else : ?>
					<p class="toast-message"><?php echo esc_html( $text ); ?></p>
				<?php endif; ?>
				<button type="button" class="toast-dismiss" aria-label="<?php esc_attr_e( 'Dismiss announcement', 'basecamp' ); ?>">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
		</div>
		<script>
		(function(){
			var key = <?php echo wp_json_encode( $storage_key ); ?>;
			var el  = document.getElementById( 'basecamp-toast' );
			if ( ! el ) { return; }
			if ( localStorage.getItem( key ) === '1' ) { return; }
			el.removeAttribute( 'hidden' );
			el.querySelector( '.toast-dismiss' ).addEventListener( 'click', function() {
				localStorage.setItem( key, '1' );
				el.setAttribute( 'hidden', '' );
			} );
		})();
		</script>
		<?php
	}
}
