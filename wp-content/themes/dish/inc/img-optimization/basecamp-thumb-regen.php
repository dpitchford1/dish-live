<?php

declare(strict_types=1);
/**
 * Thumbnail Regeneration — admin tool.
 *
 * Provides a Tools → Regenerate Thumbnails admin page that rebuilds
 * WP attachment metadata (all registered image sizes) one image at a time
 * via the same AJAX-driven batch pattern as the WebP conversion tool.
 *
 * Progress is stored in a single WP option — no extra DB tables.
 *
 * @package basecamp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Admin-init: handle form submissions before any output is sent.
// ---------------------------------------------------------------------------

/**
 * Handle POST/GET actions for the thumb regen tool at admin_init.
 */
function basecamp_thumb_regen_handle_actions(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Start regeneration (POST).
	if (
		isset( $_POST['basecamp_regen_action'] ) &&
		$_POST['basecamp_regen_action'] === 'start' &&
		isset( $_POST['basecamp_regen_nonce'] ) &&
		wp_verify_nonce( $_POST['basecamp_regen_nonce'], 'basecamp_thumb_regen' )
	) {
		basecamp_thumb_regen_reset();
		wp_redirect( add_query_arg( [ 'page' => 'basecamp-image-tools', 'tab' => 'regen' ], admin_url( 'tools.php' ) ) );
		exit;
	}

	// Reset (GET).
	if (
		isset( $_GET['regen_reset'] ) && $_GET['regen_reset'] === '1' &&
		isset( $_GET['nonce'] ) &&
		wp_verify_nonce( $_GET['nonce'], 'basecamp_regen_reset' )
	) {
		basecamp_thumb_regen_reset();
		wp_redirect( add_query_arg( [ 'page' => 'basecamp-image-tools', 'tab' => 'regen' ], admin_url( 'tools.php' ) ) );
		exit;
	}
}
add_action( 'admin_init', 'basecamp_thumb_regen_handle_actions' );

// ---------------------------------------------------------------------------
// Progress helpers — single WP option, no extra tables.
// ---------------------------------------------------------------------------

/**
 * Option key for progress storage.
 */
const BASECAMP_REGEN_OPTION = 'basecamp_thumb_regen_progress';

/**
 * Get current regeneration progress.
 *
 * @return array{running:bool,total:int,processed:int,failed:int,started:string,ids:int[]}
 */
function basecamp_thumb_regen_get_progress(): array {
	$defaults = [
		'running'   => false,
		'total'     => 0,
		'processed' => 0,
		'failed'    => 0,
		'started'   => '',
		'ids'       => [],
	];

	$saved = get_option( BASECAMP_REGEN_OPTION, [] );
	return array_merge( $defaults, is_array( $saved ) ? $saved : [] );
}

/**
 * Initialise a fresh regeneration run.
 * Queries all image attachment IDs and stores them in the option.
 */
function basecamp_thumb_regen_reset(): void {
	$ids = get_posts( [
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] );

	update_option( BASECAMP_REGEN_OPTION, [
		'running'   => false,
		'total'     => count( $ids ),
		'processed' => 0,
		'failed'    => 0,
		'started'   => current_time( 'mysql' ),
		'ids'       => array_values( array_map( 'intval', $ids ) ),
	] );
}

// ---------------------------------------------------------------------------
// AJAX handlers.
// ---------------------------------------------------------------------------

/**
 * AJAX: process the next single image.
 * Called repeatedly by JS until status === 'complete'.
 */
function basecamp_thumb_regen_process_single(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'Permission denied' ] );
	}

	if ( ! check_ajax_referer( 'basecamp_regen_ajax_nonce', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'Invalid security token' ] );
	}

	$progress = basecamp_thumb_regen_get_progress();

	// Nothing initialised yet — shouldn't happen but guard anyway.
	if ( empty( $progress['ids'] ) && $progress['processed'] === 0 ) {
		wp_send_json_success( [ 'status' => 'complete', 'progress' => $progress ] );
	}

	// Find the next unprocessed ID.
	$index = $progress['processed'] + $progress['failed'];

	if ( $index >= count( $progress['ids'] ) ) {
		$progress['running'] = false;
		update_option( BASECAMP_REGEN_OPTION, $progress );
		wp_send_json_success( [ 'status' => 'complete', 'progress' => $progress ] );
	}

	$attachment_id = (int) $progress['ids'][ $index ];
	$file          = get_attached_file( $attachment_id );
	$filename      = $file ? basename( $file ) : "ID #{$attachment_id}";

	$success = false;
	$message = '';

	if ( ! $file || ! file_exists( $file ) ) {
		$message = 'File not found: ' . $filename;
		$progress['failed']++;
	} else {
		// Ensure the image editor and metadata functions are available.
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );

		if ( is_wp_error( $metadata ) || empty( $metadata ) ) {
			$message = 'Metadata generation failed for: ' . $filename;
			$progress['failed']++;
		} else {
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$success = true;
			$message = 'Regenerated: ' . $filename;
			$progress['processed']++;
		}
	}

	$progress['running'] = true;
	update_option( BASECAMP_REGEN_OPTION, $progress );

	wp_send_json_success( [
		'status'   => 'processing',
		'success'  => $success,
		'message'  => $message,
		'filename' => $filename,
		'progress' => $progress,
	] );
}
add_action( 'wp_ajax_basecamp_regen_process_single', 'basecamp_thumb_regen_process_single' );

/**
 * AJAX: return current progress (used on page load to restore state).
 */
function basecamp_thumb_regen_get_progress_ajax(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied' );
	}

	if ( ! check_ajax_referer( 'basecamp_regen_ajax_nonce', 'nonce', false ) ) {
		wp_send_json_error( 'Invalid security token' );
	}

	wp_send_json_success( basecamp_thumb_regen_get_progress() );
}
add_action( 'wp_ajax_basecamp_regen_get_progress', 'basecamp_thumb_regen_get_progress_ajax' );

// ---------------------------------------------------------------------------
// Admin page.
// ---------------------------------------------------------------------------

/**
 * Register menu item under Tools.
 */
// Menu registration removed — this page is now rendered as the 'regen' tab
// on the consolidated Image Tools page (Tools → Image Tools).

/**
 * Render the admin page.
 */
function basecamp_thumb_regen_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}

	$progress      = basecamp_thumb_regen_get_progress();
	$total         = $progress['total'];
	$processed     = $progress['processed'];
	$failed        = $progress['failed'];
	$has_progress  = $total > 0;

	?>
	<div class="wrap webp-conversion-wrap">
		<h1>Regenerate Thumbnails</h1>

		<div class="notice notice-info webp-notice">
			<p>This tool regenerates all registered image sizes for every image in the Media Library, updating the attachment metadata in the database. Use it whenever you add or change image size registrations in the theme.</p>
			<p>Images are processed one at a time. You can pause and resume at any point.</p>
		</div>

		<div class="conversion-progress">
			<h2>Progress</h2>

			<?php if ( $has_progress ) :
				$done_pct     = ( ( $processed + $failed ) / $total ) * 100;
				$done_pct_str = number_format( $done_pct, 1 );
			?>
			<div class="progress-bar">
				<div class="progress" id="regen-progress-bar" style="width: <?php echo esc_attr( $done_pct_str ); ?>%;"></div>
			</div>

			<p class="progress-text" id="regen-progress-text">
				<strong>Progress:</strong>
				<?php echo esc_html( (string) ( $processed + $failed ) ); ?> of <?php echo esc_html( (string) $total ); ?> images processed
				(<?php echo esc_html( $done_pct_str ); ?>%)
			</p>

			<div class="stats-section">
				<div class="stats-grid">
					<div class="stat-card">
						<h4>Total Images</h4>
						<div class="stat-value"><?php echo esc_html( (string) $total ); ?></div>
						<p class="stat-description">Images in the Media Library</p>
					</div>
					<div class="stat-card">
						<h4>Regenerated</h4>
						<div class="stat-value"><?php echo esc_html( (string) $processed ); ?></div>
						<p class="stat-description">Successfully regenerated</p>
					</div>
					<div class="stat-card">
						<h4>Failed / Skipped</h4>
						<div class="stat-value"><?php echo esc_html( (string) $failed ); ?></div>
						<p class="stat-description">File not found or error</p>
					</div>
					<?php if ( $progress['started'] ) : ?>
					<div class="stat-card">
						<h4>Started</h4>
						<div class="stat-value"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $progress['started'] ) ) ); ?></div>
						<p class="stat-description">&nbsp;</p>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<div id="regen-current-processing" class="processing-status" style="display:none;">
				<p><strong>Currently processing:</strong> <span id="regen-current-image-name">...</span></p>
			</div>

			<div id="regen-processing-log" class="processing-log">
				<ul id="regen-log-entries"></ul>
			</div>

			<div class="controls">
				<button id="regen-start" class="button button-primary">Start Regenerating</button>
				<button id="regen-pause" class="button" style="display:none;">Pause</button>
				<button id="regen-resume" class="button" style="display:none;">Resume</button>
			</div>

			<div class="reset-conversion">
				<a href="<?php echo esc_url( add_query_arg( [
					'page'        => 'basecamp-image-tools',
					'tab'         => 'regen',
					'regen_reset' => '1',
					'nonce'       => wp_create_nonce( 'basecamp_regen_reset' ),
				], admin_url( 'tools.php' ) ) ); ?>"
				   class="button"
				   onclick="return confirm('Reset regeneration progress and re-scan the media library?');">
					Reset
				</a>
				<p class="description">Re-scans the media library and resets all counts, allowing you to run a fresh regeneration.</p>
			</div>

			<?php else : ?>

			<div class="start-conversion">
				<form method="post" action="" id="regen-start-form">
					<?php wp_nonce_field( 'basecamp_thumb_regen', 'basecamp_regen_nonce' ); ?>
					<input type="hidden" name="basecamp_regen_action" value="start">
					<p>No scan has been run yet. Click below to scan the media library and begin.</p>
					<p class="submit">
						<input type="submit" class="button button-primary" value="Scan &amp; Start Regenerating"
						       onclick="return confirm('Scan the media library and start regenerating thumbnails?');">
					</p>
				</form>
			</div>

			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Enqueue scripts for the thumbnail regen admin page.
 */
function basecamp_thumb_regen_admin_scripts( string $hook ): void {
	if ( 'tools_page_basecamp-image-tools' !== $hook ) {
		return;
	}

	wp_enqueue_script(
		'basecamp-thumb-regen-admin',
		get_template_directory_uri() . '/inc/img-optimization/assets/js/thumb-regen-admin.min.js',
		[ 'jquery' ],
		'1.0',
		true
	);

	wp_localize_script( 'basecamp-thumb-regen-admin', 'basecampRegen', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'basecamp_regen_ajax_nonce' ),
		'strings' => [
			'processing' => __( 'Regenerating thumbnails...', 'basecamp' ),
			'complete'   => __( 'Regeneration complete!', 'basecamp' ),
			'paused'     => __( 'Regeneration paused.', 'basecamp' ),
			'error'      => __( 'An error occurred.', 'basecamp' ),
			'resume'     => __( 'Resume regeneration?', 'basecamp' ),
		],
	] );
}
add_action( 'admin_enqueue_scripts', 'basecamp_thumb_regen_admin_scripts' );
