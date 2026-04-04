<?php
/**
 * Media upload tweaks.
 *
 * - Clears the auto-generated title attribute on new attachment uploads.
 *   WordPress derives the title from the filename by default; this is useless
 *   noise that shows as a tooltip on <img> elements and clutters the media library.
 *   Admins can still set a meaningful title manually after upload.
 *
 * @package Basecamp\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strip the auto-generated title from new attachment uploads.
 *
 * Hooked into wp_insert_attachment_data (fires before the DB write) so the
 * blank title is the initial value — no second write required.
 * Only runs on insert (no ID in $postarr), leaving manual edits untouched.
 *
 * @param array $data    Sanitised attachment data about to be written.
 * @param array $postarr Raw post array passed to wp_insert_post().
 * @return array
 */
add_filter( 'wp_insert_attachment_data', function ( array $data, array $postarr ): array {
	if ( empty( $postarr['ID'] ) ) {
		$data['post_title'] = '';
	}
	return $data;
}, 10, 2 );

// ---------------------------------------------------------------------------
// One-time bulk clear: wipe auto-generated titles from existing media library
// ---------------------------------------------------------------------------

/**
 * Handle the bulk-clear POST request.
 * Clears post_title on every attachment that still has a non-empty title.
 */
add_action( 'admin_post_basecamp_clear_media_titles', function (): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'basecamp' ) );
	}

	check_admin_referer( 'basecamp_clear_media_titles' );

	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$cleared = $wpdb->query(
		"UPDATE {$wpdb->posts} SET post_title = '' WHERE post_type = 'attachment' AND post_title != ''"
	);

	wp_safe_redirect( add_query_arg(
		[ 'basecamp_media_cleared' => (int) $cleared ],
		admin_url( 'upload.php' )
	) );
	exit;
} );

/**
 * Render the bulk-clear button + results notice on the Media Library screen.
 */
add_action( 'admin_notices', function (): void {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'upload' ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Results notice after the action ran.
	if ( isset( $_GET['basecamp_media_cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$count = (int) $_GET['basecamp_media_cleared']; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>'
			. sprintf(
				/* translators: %d: number of attachments updated */
				esc_html__( 'Done — %d media title(s) cleared.', 'basecamp' ),
				$count
			)
			. '</p></div>';
		return;
	}

	// Show the button only while there are attachments with non-empty titles.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$remaining = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title != ''"
	);

	if ( $remaining < 1 ) {
		return;
	}

	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'Media Library — legacy titles found', 'basecamp' ); ?></strong><br>
			<?php
			echo sprintf(
				/* translators: %d: number of attachments with titles */
				esc_html__( '%d attachment(s) still have auto-generated titles. Click below to clear them all at once.', 'basecamp' ),
				$remaining
			);
			?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:10px">
			<input type="hidden" name="action" value="basecamp_clear_media_titles">
			<?php wp_nonce_field( 'basecamp_clear_media_titles' ); ?>
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Clear all media titles', 'basecamp' ); ?>
			</button>
		</form>
	</div>
	<?php
} );
