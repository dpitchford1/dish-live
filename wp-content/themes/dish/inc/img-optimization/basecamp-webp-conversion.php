<?php
/**
 * WebP image conversion functions
 *
 * Handles server-side conversion of images to WebP format using GD, Imagick, or cwebp.
 * Includes hooks for conversion on upload and batch processing.
 *
 * @package basecamp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if the server has the required tools for WebP conversion.
 *
 * @return bool Whether the server can convert to WebP.
 */
function basecamp_webp_conversion_supported() {
	// Check for GD with WebP support
	if ( function_exists( 'imagewebp' ) && function_exists( 'imagecreatefromjpeg' ) && function_exists( 'imagecreatefrompng' ) ) {
		return true;
	}
	// Check for ImageMagick with WebP support
	if ( class_exists( 'Imagick' ) ) {
		$formats = Imagick::queryFormats();
		if ( in_array( 'WEBP', $formats ) ) {
			return true;
		}
	}
	// Check for cwebp command-line tool
	$output = array();
	$return_val = -1;
	@exec( 'cwebp -version 2>&1', $output, $return_val );
	if ( $return_val === 0 && !empty( $output ) ) {
		return true;
	}
	return false;
}

/**
 * Get memory limit in bytes.
 *
 * @param string $memory_limit Memory limit string (e.g. '128M')
 * @return int Memory limit in bytes
 */
function basecamp_get_memory_limit_in_bytes($memory_limit = '') {
	if (empty($memory_limit)) {
		$memory_limit = ini_get('memory_limit');
	}
	// Handle unlimited memory
	if ($memory_limit === '-1') {
		return PHP_INT_MAX;
	}
	$memory_limit = trim($memory_limit);
	$last = strtolower($memory_limit[strlen($memory_limit) - 1]);
	$value = intval($memory_limit);
	switch ($last) {
		case 'g':
			$value *= 1024;
		case 'm':
			$value *= 1024;
		case 'k':
			$value *= 1024;
	}
	return $value;
}

/**
 * Convert an image to WebP format using GD.
 *
 * @param string $source_path Path to the source image.
 * @param string $destination_path Path for the WebP output.
 * @param int $quality Quality setting (0-100).
 * @return bool Whether the conversion was successful.
 */
function basecamp_convert_to_webp_gd($source_path, $destination_path, $quality = 80) {
	if (!function_exists('imagewebp') || !file_exists($source_path)) {
		return false;
	}
	$mime_info = wp_check_filetype($source_path);
	$mime_type = isset($mime_info['type']) ? $mime_info['type'] : '';
	$image = false;
	// Create image resource from source
	if ($mime_type === 'image/jpeg' || $mime_type === 'image/jpg') {
		$image = @imagecreatefromjpeg($source_path);
	} elseif ($mime_type === 'image/png') {
		$image = @imagecreatefrompng($source_path);
		// Handle PNG transparency
		if ($image) {
			imagepalettetotruecolor($image);
			imagealphablending($image, true);
			imagesavealpha($image, true);
		}
	}
	// Convert and save as WebP
	if (!$image) {
		return false;
	}
	$result = imagewebp($image, $destination_path, $quality);
	imagedestroy($image);
	// Remove WebP if it's larger than original
	if ($result && file_exists($destination_path)) {
		if (filesize($destination_path) > filesize($source_path)) {
			@unlink($destination_path);
			return false;
		}
		if (function_exists('gc_collect_cycles')) {
			gc_collect_cycles();
		}
		return true;
	}
	return false;
}

/**
 * Convert an image to WebP format using ImageMagick.
 *
 * @param string $source_path Path to the source image.
 * @param string $destination_path Path for the WebP output.
 * @param int $quality Quality setting (0-100).
 * @return bool Whether the conversion was successful.
 */
function basecamp_convert_to_webp_imagick($source_path, $destination_path, $quality = 80) {
	if (!class_exists('Imagick') || !file_exists($source_path)) {
		return false;
	}
	try {
		$image = new Imagick($source_path);
		$image->setImageFormat('WEBP');
		$image->setImageCompressionQuality($quality);
		$image->setOption('webp:lossless', 'false');
		$image->setOption('webp:method', '6');
		$image->setOption('webp:low-memory', 'true');
		// Add progressive option when supported
		if (method_exists($image, 'setInterlaceScheme')) {
			$image->setInterlaceScheme(Imagick::INTERLACE_PLANE);
		}
		$result = $image->writeImage($destination_path);
		$image->clear();
		$image->destroy();
		// Remove WebP if it's larger than original
		if ($result && file_exists($destination_path)) {
			if (filesize($destination_path) > filesize($source_path)) {
				@unlink($destination_path);
				return false;
			}
			if (function_exists('gc_collect_cycles')) {
				gc_collect_cycles();
			}
			return true;
		}
	} catch (Exception $e) {
		error_log('WebP conversion error with ImageMagick: ' . $e->getMessage());
	}
	return false;
}

/**
 * Convert an image to WebP format using cwebp command-line tool.
 *
 * @param string $source_path Path to the source image.
 * @param string $destination_path Path for the WebP output.
 * @param int $quality Quality setting (0-100).
 * @return bool Whether the conversion was successful.
 */
function basecamp_convert_to_webp_cwebp($source_path, $destination_path, $quality = 80) {
	if (!file_exists($source_path)) {
		return false;
	}
	$output = array();
	$return_val = -1;
	$command = "cwebp -q {$quality} " . escapeshellarg($source_path) . " -o " . escapeshellarg($destination_path) . " 2>&1";
	@exec($command, $output, $return_val);
	if ($return_val === 0 && file_exists($destination_path)) {
		if (filesize($destination_path) > filesize($source_path)) {
			@unlink($destination_path);
			return false;
		}
		return true;
	}
	return false;
}

/**
 * Resize an image if it's too large for memory constraints.
 *
 * @param string $source_path Path to the source image.
 * @param int $max_width Maximum width for the image.
 * @param int $max_height Maximum height for the image.
 * @return bool|string False if resize failed, or path to resized image.
 */
function basecamp_resize_image_for_conversion($source_path, $max_width = 2000, $max_height = 2000) {
	if (!function_exists('wp_get_image_editor') || !file_exists($source_path)) {
		return false;
	}
	$editor = wp_get_image_editor($source_path);
	if (is_wp_error($editor)) {
		return false;
	}
	$size = $editor->get_size();
	$width = $size['width'];
	$height = $size['height'];
	// Only resize if needed
	if ($width <= $max_width && $height <= $max_height) {
		return $source_path;
	}
	$pathinfo = pathinfo($source_path);
	$temp_path = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '-resized.' . $pathinfo['extension'];
	$editor->resize($max_width, $max_height, false);
	$result = $editor->save($temp_path);
	if (is_wp_error($result) || !file_exists($temp_path)) {
		return false;
	}
	return $temp_path;
}

/**
 * Convert an image to WebP format using available methods.
 *
 * @param string $source_path Path to the source image.
 * @param string $destination_path Path for the WebP output.
 * @param int $quality Quality setting (0-100).
 * @return bool Whether the conversion was successful.
 */
function basecamp_convert_to_webp($source_path, $destination_path, $quality = 80) {
	// Skip in admin post/page updates (except AJAX)
	if (is_admin() && !wp_doing_ajax()) {
		global $pagenow;
		if (in_array($pagenow, array('post.php', 'post-new.php', 'edit.php'))) {
			if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('editpost', 'edit', 'post', 'update'))) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log('[WEBP-CONVERSION] Skipped core WebP conversion during post update for: ' . basename($source_path));
				}
				return false;
			}
		}
	}
	// Skip if file doesn't exist or is SVG
	if (!file_exists($source_path)) {
		return false;
	}
	$mime_info = wp_check_filetype($source_path);
	$mime_type = isset($mime_info['type']) ? $mime_info['type'] : '';
	if (empty($mime_type) || $mime_type === 'image/svg+xml') {
		return false;
	}
	// Estimate memory required for image processing
	$image_info = @getimagesize($source_path);
	if ($image_info && isset($image_info[0]) && isset($image_info[1])) {
		$width = $image_info[0];
		$height = $image_info[1];
		$channels = isset($image_info['channels']) ? $image_info['channels'] : 4;
		$bits = isset($image_info['bits']) ? $image_info['bits'] : 8;
		$memory_needed = $width * $height * $channels * $bits / 8 * 3;
		$available_memory = basecamp_get_memory_limit_in_bytes() * 0.8;
		// Resize if needed
		if ($memory_needed > $available_memory) {
			$max_dimension = ceil(sqrt($available_memory / 3 * 8 / $channels / $bits));
			$max_width = $max_height = min(2000, $max_dimension);
			$resized_path = basecamp_resize_image_for_conversion($source_path, $max_width, $max_height);
			if ($resized_path && $resized_path !== $source_path) {
				$temp_source_path = $resized_path;
				$use_temp = true;
			} else {
				error_log(sprintf(
					'WebP conversion skipped for large image: %s (Estimated memory needed: %s MB, Available: %s MB)',
					basename($source_path),
					round($memory_needed / (1024 * 1024), 2),
					round($available_memory / (1024 * 1024), 2)
				));
				return false;
			}
		} else {
			$temp_source_path = $source_path;
			$use_temp = false;
		}
	} else {
		$temp_source_path = $source_path;
		$use_temp = false;
	}
	// Try each conversion method in order: GD, Imagick, cwebp
	$result = basecamp_convert_to_webp_gd($temp_source_path, $destination_path, $quality);
	if (!$result) {
		$result = basecamp_convert_to_webp_imagick($temp_source_path, $destination_path, $quality);
	}
	if (!$result) {
		$result = basecamp_convert_to_webp_cwebp($temp_source_path, $destination_path, $quality);
	}
	// Clean up temporary file if used
	if ($use_temp && file_exists($temp_source_path)) {
		@unlink($temp_source_path);
	}
	return $result;
}

/**
 * Convert existing images to WebP on media upload.
 *
 * @param array $metadata Attachment metadata.
 * @param int $attachment_id Attachment ID.
 * @return array Unmodified metadata.
 */
function basecamp_convert_uploaded_image( $metadata, $attachment_id ) {
	// Skip in admin post/page updates (except AJAX)
	if (is_admin() && !wp_doing_ajax()) {
		global $pagenow;
		if (in_array($pagenow, array('post.php', 'post-new.php', 'edit.php'))) {
			if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('editpost', 'edit', 'post', 'update'))) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log('[WEBP-CONVERSION] Skipped WebP conversion during post update');
				}
				return $metadata;
			}
		}
	}
	// Only run for image attachments
	if ( ! isset( $metadata['file'] ) || strpos( get_post_mime_type( $attachment_id ), 'image/' ) === false ) {
		return $metadata;
	}
	// Check server capability
	if ( ! basecamp_webp_conversion_supported() ) {
		return $metadata;
	}
	$upload_dir = wp_upload_dir();
	$base_file = trailingslashit( $upload_dir['basedir'] ) . $metadata['file'];
	$mime_type = get_post_mime_type( $attachment_id );
	// Skip WebP files and SVGs
	if ( $mime_type === 'image/webp' || $mime_type === 'image/svg+xml' ) {
		return $metadata;
	}
	// Convert the original image
	basecamp_convert_to_webp( $base_file, $base_file . '.webp' );
	// Convert all image sizes
	if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
		$base_dir = dirname( $base_file );
		foreach ( $metadata['sizes'] as $size => $size_info ) {
			$size_file = trailingslashit( $base_dir ) . $size_info['file'];
			basecamp_convert_to_webp( $size_file, $size_file . '.webp' );
		}
	}
	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'basecamp_convert_uploaded_image', 10, 2 );

/**
 * Handle Media Library Plus plugin uploads by hooking into its actions.
 *
 * @param array $file_info File info array.
 * @return array Unmodified file info.
 */
function basecamp_mlp_handle_upload( $file_info ) {
	// Skip in admin post/page updates (except AJAX)
	if (is_admin() && !wp_doing_ajax()) {
		global $pagenow;
		if (in_array($pagenow, array('post.php', 'post-new.php', 'edit.php'))) {
			if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('editpost', 'edit', 'post', 'update'))) {
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log('[WEBP-CONVERSION] Skipped MLP WebP conversion during post update');
				}
				return $file_info;
			}
		}
	}
	if ( empty( $file_info['path'] ) || ! file_exists( $file_info['path'] ) ) {
		return $file_info;
	}
	// Check server capability
	if ( ! basecamp_webp_conversion_supported() ) {
		return $file_info;
	}
	// Only convert image files
	$mime_info = wp_check_filetype( $file_info['path'] );
	$mime_type = isset($mime_info['type']) ? $mime_info['type'] : '';
	if ( strpos( $mime_type, 'image/' ) === false || $mime_type === 'image/webp' || $mime_type === 'image/svg+xml' ) {
		return $file_info;
	}
	// Convert the uploaded image
	basecamp_convert_to_webp( $file_info['path'], $file_info['path'] . '.webp' );
	return $file_info;
}
add_filter( 'wp_handle_upload', 'basecamp_mlp_handle_upload', 10, 1 );
add_filter( 'mla_handle_upload', 'basecamp_mlp_handle_upload', 10, 1 );

/**
 * Add admin page for bulk WebP conversion.
 */
function basecamp_webp_conversion_menu() {
	add_submenu_page(
		'tools.php',
		'WebP Conversion',
		'WebP Conversion',
		'manage_options',
		'basecamp-webp-conversion',
		'basecamp_webp_conversion_page'
	);
}
add_action( 'admin_menu', 'basecamp_webp_conversion_menu' );

/**
 * Admin page for bulk WebP conversion.
 */
function basecamp_webp_conversion_page() {
	// Check server capability
	$conversion_supported = basecamp_webp_conversion_supported();
	
	// Get conversion progress from the database
	$progress = basecamp_webp_get_conversion_progress();
	$is_converting = $progress['converting'];
	$total_images = $progress['total_images'];
	$processed_images = $progress['processed_images'];
	$quality = isset($progress['quality']) ? $progress['quality'] : 80;
	
	// Initialize or update total count if needed
	if (isset($_POST['basecamp_webp_action']) && $_POST['basecamp_webp_action'] === 'start' && 
		isset($_POST['basecamp_webp_nonce']) && wp_verify_nonce($_POST['basecamp_webp_nonce'], 'basecamp_webp_conversion')) {
		
		// Get quality setting
		$quality = isset($_POST['basecamp_webp_quality']) ? intval($_POST['basecamp_webp_quality']) : 80;
		
		// Reset any existing conversion and start a new one
		basecamp_webp_reset_conversion();
		$batch_id = basecamp_webp_get_current_batch($quality);
		
		// Get updated progress
		$progress = basecamp_webp_get_conversion_progress();
		
		// Redirect to refresh the page
		wp_redirect(add_query_arg('page', 'basecamp-webp-conversion', admin_url('tools.php')));
		exit;
	}
	
	// Reset conversion
	if (isset($_GET['reset']) && $_GET['reset'] == '1' && 
		isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'basecamp_reset_conversion')) {
		
		// Reset conversion progress using the new database function
		basecamp_webp_reset_conversion();
		
		// Redirect back to the page
		wp_redirect(add_query_arg('page', 'basecamp-webp-conversion', admin_url('tools.php')));
		exit;
	}
	
	?>
	<div class="wrap webp-conversion-wrap">
		<h1>WebP Image Conversion</h1>
		
		<?php if ( ! $conversion_supported ) : ?>
		<div class="notice notice-error">
			<p>Your server does not have the required tools to convert images to WebP format. Please enable one of the following:</p>
			<ul>
				<li>PHP GD with WebP support</li>
				<li>PHP ImageMagick with WebP support</li>
				<li>cwebp command-line tool</li>
			</ul>
		</div>
		<?php else : ?>
		
		<div class="notice notice-info webp-notice">
			<p>This tool will convert all your JPEG and PNG images in the Media Library to WebP format. The original images will be preserved, and WebP versions will be created alongside them.</p>
			<p>Click the "Start Processing" button to begin converting images one by one automatically. You can pause the process at any time and resume later.</p>
		</div>
		
		<div class="conversion-progress">
			<h2>Conversion Progress</h2>
			
			<?php if ($total_images > 0): ?>
			<div class="progress-bar">
				<div class="progress" style="width: <?php echo esc_attr( ($processed_images / $total_images) * 100 ); ?>%;"></div>
			</div>
			
			<p class="progress-text">
				<strong>Progress:</strong> <?php echo esc_html( $processed_images ); ?> of <?php echo esc_html( $total_images ); ?> images processed
				(<?php echo esc_html( round( ($processed_images / $total_images) * 100, 1 ) ); ?>%)
			</p>
			
			<?php if ($processed_images > 0 && isset($progress['space_saved'])): ?>
			<div class="stats-section">
				<h3>Conversion Statistics</h3>
				
				<div class="stats-grid">
					<div class="stat-card">
						<h4>Total Images</h4>
						<div class="stat-value"><?php echo esc_html($total_images); ?></div>
						<p class="stat-description">Total images in the media library</p>
					</div>
					
					<div class="stat-card">
						<h4>Processed</h4>
						<div class="stat-value"><?php echo esc_html($processed_images); ?></div>
						<p class="stat-description">Images processed so far</p>
					</div>
					
					<?php if (isset($progress['successful']) && isset($progress['failed'])): ?>
					<div class="stat-card">
						<h4>Success Rate</h4>
						<div class="stat-value"><?php 
							$success_rate = $processed_images > 0 ? round(($progress['successful'] / $processed_images) * 100, 1) : 0;
							echo esc_html($success_rate) . '%'; 
						?></div>
						<p class="stat-description"><?php echo esc_html($progress['successful']); ?> succeeded, <?php echo esc_html($progress['failed']); ?> failed</p>
					</div>
					<?php endif; ?>
					
					<div class="stat-card">
						<h4>Space Saved</h4>
						<div class="stat-value"><?php 
							echo esc_html(size_format($progress['space_saved'], 2)); 
						?></div>
						<p class="stat-description"><?php echo esc_html($progress['space_saved_percentage']); ?>% reduction in file size</p>
					</div>
					
					<?php if (isset($progress['start_time'])): ?>
					<div class="stat-card">
						<h4>Process Started</h4>
						<div class="stat-value"><?php 
							$start_time = strtotime($progress['start_time']);
							echo esc_html(human_time_diff($start_time, current_time('timestamp'))); 
						?> ago</div>
						<p class="stat-description"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $start_time)); ?></p>
					</div>
					<?php endif; ?>
					
					<?php if (isset($progress['quality'])): ?>
					<div class="stat-card">
						<h4>Quality Setting</h4>
						<div class="stat-value"><?php echo esc_html($progress['quality']); ?></div>
						<p class="stat-description">WebP compression quality (0-100)</p>
					</div>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
			
			<div id="current-processing" class="processing-status" style="display: none;">
				<p><strong>Currently processing:</strong> <span id="current-image-name">...</span></p>
			</div>
			
			<div id="processing-log" class="processing-log">
				<ul id="log-entries">
					<!-- Log entries will be added here by JavaScript -->
				</ul>
			</div>
			
			<div class="controls">
				<button id="start-processing" class="button button-primary">Start Processing</button>
				<button id="pause-processing" class="button" style="display: none;">Pause</button>
				<button id="resume-processing" class="button" style="display: none;">Resume</button>
			</div>
			
			<div class="reset-conversion">
				<a href="<?php echo esc_url(add_query_arg(array(
					'page' => 'basecamp-webp-conversion',
					'reset' => 1,
					'nonce' => wp_create_nonce('basecamp_reset_conversion')
				), admin_url('tools.php'))); ?>" class="button" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to reset the conversion progress?', 'basecamp')); ?>');">Reset Conversion</a>
				<p class="description">This will reset the conversion progress, allowing you to start from the beginning.</p>
			</div>
			<?php else: ?>
			<div class="start-conversion">
				<form method="post" action="" id="webp-conversion-form">
					<?php wp_nonce_field( 'basecamp_webp_conversion', 'basecamp_webp_nonce' ); ?>
					<input type="hidden" name="basecamp_webp_action" value="start">
					
					<table class="form-table">
						<tr>
							<th scope="row"><label for="basecamp_webp_quality">WebP Quality</label></th>
							<td>
								<input type="number" name="basecamp_webp_quality" id="basecamp_webp_quality" min="0" max="100" value="80" class="small-text">
								<p class="description">Quality setting (0-100). Higher values result in better image quality but larger file sizes. 80 is recommended for a good balance.</p>
							</td>
						</tr>
					</table>
					
					<p class="submit">
						<input type="submit" name="basecamp_webp_convert" id="basecamp_webp_convert" class="button button-primary" value="Start WebP Conversion">
					</p>
				</form>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Enqueue scripts for the WebP conversion admin page.
 */
function basecamp_webp_admin_scripts( $hook ) {
	if ( 'tools_page_basecamp-webp-conversion' !== $hook ) {
		return;
	}
	
	// Enqueue CSS
	wp_enqueue_style( 'basecamp-webp-admin', get_template_directory_uri() . '/inc/img-optimization/assets/css/webp-admin.min.css', array(), '1.0' );
	
	// Enqueue JavaScript
	wp_enqueue_script( 'basecamp-webp-admin', get_template_directory_uri() . '/inc/img-optimization/assets/js/webp-admin.min.js', array( 'jquery' ), '1.0', true );
	
	wp_localize_script( 'basecamp-webp-admin', 'basecampWebp', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'basecamp_webp_ajax_nonce' ),
		'strings' => array(
			'processing' => __( 'Processing images...', 'basecamp' ),
			'complete' => __( 'Conversion complete!', 'basecamp' ),
			'paused' => __( 'Conversion paused.', 'basecamp' ),
			'error' => __( 'Error occurred during conversion.', 'basecamp' ),
			'resume' => __( 'Resume conversion?', 'basecamp' ),
			'confirmReset' => __( 'Are you sure you want to reset the conversion progress?', 'basecamp' )
		)
	) );
}
add_action( 'admin_enqueue_scripts', 'basecamp_webp_admin_scripts' );

/**
 * Add AJAX endpoint for getting conversion status.
 */
function basecamp_webp_get_status() {
	// Check for an admin user
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Permission denied' );
	}
	
	// Verify nonce
	if ( ! check_ajax_referer( 'basecamp_webp_ajax_nonce', 'nonce', false ) ) {
		wp_send_json_error( 'Invalid security token' );
	}
	
	// Get conversion status
	$conversion_status = get_option( 'basecamp_webp_conversion_status', array() );
	
	wp_send_json_success( $conversion_status );
}
add_action( 'wp_ajax_basecamp_webp_get_status', 'basecamp_webp_get_status' );

/**
 * Process a single image via AJAX.
 */
function basecamp_webp_process_single() {
	// Check for an admin user
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array(
			'message' => 'Permission denied'
		) );
	}
	
	// Verify nonce
	if ( ! check_ajax_referer( 'basecamp_webp_ajax_nonce', 'nonce', false ) ) {
		wp_send_json_error( array(
			'message' => 'Invalid security token'
		) );
	}
	
	// Get the quality setting from the request or use the default
	$quality = isset( $_POST['quality'] ) ? intval( $_POST['quality'] ) : 80;
	
	// Get or create a batch
	$batch_id = basecamp_webp_get_current_batch($quality);
	
	// Get next image to process
	$image = basecamp_webp_get_next_image();
	
	// If no images left, we're done
	if (!$image) {
		// For backward compatibility, also update the old status
		update_option('basecamp_webp_conversion_status', array(
			'converting' => false,
			'total_images' => 0,
			'processed_images' => 0,
			'current_offset' => 0,
			'quality' => $quality,
		));
		
		wp_send_json_success(array(
			'status' => 'complete',
			'message' => 'All images have been processed',
			'progress' => basecamp_webp_get_conversion_progress()
		));
		return;
	}
	
	// Get file information
	$attachment_id = $image['attachment_id'];
	$file = $image['file_path'];
	$record_id = $image['id'];
	
	// Get metadata
	$metadata = wp_get_attachment_metadata($attachment_id);
	$filename = basename($file);
	$result = array(
		'id' => $attachment_id,
		'filename' => $filename,
		'success' => true,
		'message' => '',
		'details' => array(),
	);
	
	// For conversion time measurement
	$start_time = microtime(true);
	
	// Check if file exists
	if (!$file || !file_exists($file)) {
		$result['success'] = false;
		$result['message'] = 'File not found: ' . $filename;
		
		// Update record
		basecamp_webp_update_conversion_record($record_id, false, array(
			'error_message' => 'File not found: ' . $filename
		));
		
		// Return response
		wp_send_json_success(array(
			'status' => 'processing',
			'image' => $result,
			'progress' => basecamp_webp_get_conversion_progress()
		));
		return;
	}
	
	// Set timeout for larger files
	$max_exec_time = ini_get('max_execution_time');
	if ($max_exec_time < 60 && $max_exec_time != 0) {
		@set_time_limit(60); // Try to set to 60 seconds
	}
	
	// Try to convert the original image
	$webp_path = $file . '.webp';
	$original_result = basecamp_convert_to_webp($file, $webp_path, $quality);
	
	// Calculate conversion time
	$conversion_time = microtime(true) - $start_time;
	
	// Handle result
	if (!$original_result) {
		$error_info = error_get_last();
		$error_message = isset($error_info['message']) ? $error_info['message'] : 'Unknown error';
		
		$result['message'] = 'Failed to convert original image';
		if ($error_message !== 'Unknown error') {
			$result['message'] .= ': ' . $error_message;
		}
		
		$result['success'] = false;
		$result['details']['file_size'] = @filesize($file);
		$result['details']['dimensions'] = @getimagesize($file);
		
		// Update record
		basecamp_webp_update_conversion_record($record_id, false, array(
			'error_message' => $result['message'],
			'conversion_time' => $conversion_time
		));
	} else {
		$webp_size = @filesize($webp_path);
		$original_size = @filesize($file);
		
		$result['details']['original'] = array(
			'size_before' => $original_size,
			'size_after' => $webp_size,
			'reduction' => $original_size ? round((1 - ($webp_size / $original_size)) * 100, 1) . '%' : 'unknown'
		);
		
		// Update record
		basecamp_webp_update_conversion_record($record_id, true, array(
			'webp_size' => $webp_size,
			'conversion_time' => $conversion_time
		));
	}
	
	// Convert all image sizes
	$size_results = array();
	$size_success_count = 0;
	$size_total_count = 0;
	
	if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
		$base_dir = dirname($file);
		
		foreach ($metadata['sizes'] as $size => $size_info) {
			$size_file = trailingslashit($base_dir) . $size_info['file'];
			$size_total_count++;
			
			if (file_exists($size_file)) {
				$size_result = basecamp_convert_to_webp($size_file, $size_file . '.webp', $quality);
				if ($size_result) {
					$size_success_count++;
					$size_results[$size] = array(
						'success' => true,
						'size_before' => @filesize($size_file),
						'size_after' => @filesize($size_file . '.webp'),
					);
				} else {
					$size_results[$size] = array(
						'success' => false,
						'error' => 'Conversion failed',
					);
				}
			} else {
				$size_results[$size] = array(
					'success' => false,
					'error' => 'Size file not found',
				);
			}
		}
	}
	
	$result['size_results'] = $size_results;
	$result['details']['sizes_converted'] = $size_success_count . ' of ' . $size_total_count;
	
	// Force garbage collection after processing
	if (function_exists('gc_collect_cycles')) {
		gc_collect_cycles();
	}
	
	// Get updated progress
	$progress = basecamp_webp_get_conversion_progress();
	
	// For backward compatibility, also update the old status
	update_option('basecamp_webp_conversion_status', array(
		'converting' => true,
		'total_images' => $progress['total_images'],
		'processed_images' => $progress['processed_images'],
		'current_offset' => $progress['current_offset'],
		'quality' => $quality,
		'last_processed' => time(),
	));
	
	// Return the result
	wp_send_json_success(array(
		'status' => 'processing',
		'image' => $result,
		'progress' => $progress
	));
}
add_action( 'wp_ajax_basecamp_webp_process_single', 'basecamp_webp_process_single' );

/**
 * Create or upgrade the WebP conversion database table.
 */
function basecamp_webp_create_db_table() {
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'basecamp_webp_conversions';
	$summary_table = $wpdb->prefix . 'basecamp_webp_stats';
	$charset_collate = $wpdb->get_charset_collate();
	
	// We need to include the upgrade functionality
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	
	// Check if table exists before attempting to create it
	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL,
			file_path varchar(255) NOT NULL,
			file_size bigint(20) unsigned DEFAULT 0,
			webp_size bigint(20) unsigned DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			conversion_time float DEFAULT 0,
			attempts smallint(5) unsigned DEFAULT 0,
			last_attempt datetime DEFAULT NULL,
			error_message text,
			date_added datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			date_converted datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY attachment_id (attachment_id),
			KEY status (status)
		) $charset_collate;";
		
		dbDelta($sql);
	}
	
	// Create conversions summary table if it doesn't exist
	if ($wpdb->get_var("SHOW TABLES LIKE '$summary_table'") !== $summary_table) {
		$sql = "CREATE TABLE $summary_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			batch_id varchar(32) NOT NULL,
			start_time datetime NOT NULL,
			end_time datetime DEFAULT NULL,
			total_images int unsigned DEFAULT 0,
			processed_images int unsigned DEFAULT 0,
			successful_conversions int unsigned DEFAULT 0,
			failed_conversions int unsigned DEFAULT 0,
			total_original_size bigint(20) unsigned DEFAULT 0,
			total_webp_size bigint(20) unsigned DEFAULT 0,
			quality tinyint unsigned DEFAULT 80,
			status varchar(20) NOT NULL DEFAULT 'running',
			PRIMARY KEY  (id),
			KEY batch_id (batch_id),
			KEY status (status)
		) $charset_collate;";
		
		dbDelta($sql);
	}
}

/**
 * Register activation hook to create database tables.
 */
function basecamp_webp_activation() {
	basecamp_webp_create_db_table();
}
register_activation_hook(__FILE__, 'basecamp_webp_activation');

/**
 * Initialize WebP conversion database if needed.
 */
function basecamp_webp_init() {
	// Check if both database tables exist and create them if needed
	global $wpdb;
	$table_name = $wpdb->prefix . 'basecamp_webp_conversions';
	$summary_table = $wpdb->prefix . 'basecamp_webp_stats';
	
	$tables_needed = false;
	
	// Check if either table needs to be created
	if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
		$tables_needed = true;
	}
	
	if ($wpdb->get_var("SHOW TABLES LIKE '$summary_table'") !== $summary_table) {
		$tables_needed = true;
	}
	
	// Only call the create function if tables are actually needed
	if ($tables_needed) {
		basecamp_webp_create_db_table();
	}
}
add_action('init', 'basecamp_webp_init');

/**
 * Get or create current WebP conversion batch.
 * 
 * @param int $quality Quality setting.
 * @return string Batch ID.
 */
function basecamp_webp_get_current_batch($quality = 80) {
	global $wpdb;
	$summary_table = $wpdb->prefix . 'basecamp_webp_stats';
	
	// Look for a running batch
	$batch = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $summary_table WHERE status = %s ORDER BY id DESC LIMIT 1",
			'running'
		)
	);
	
	if ($batch) {
		return $batch->batch_id;
	}
	
	// Create a new batch
	$batch_id = md5(uniqid('webp', true));
	
	// Get total images to convert
	$count_args = array(
		'post_type'      => 'attachment',
		'post_mime_type' => array('image/jpeg', 'image/jpg', 'image/png'),
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);
	$attachments = get_posts($count_args);
	$total_images = count($attachments);
	
	// Insert new batch record
	$wpdb->insert(
		$summary_table,
		array(
			'batch_id' => $batch_id,
			'start_time' => current_time('mysql'),
			'total_images' => $total_images,
			'quality' => $quality,
			'status' => 'running'
		)
	);
	
	// Initialize conversion records for all images
	if ($total_images > 0) {
		$conversion_table = $wpdb->prefix . 'basecamp_webp_conversions';
		$values = array();
		
		foreach ($attachments as $attachment_id) {
			$file_path = get_attached_file($attachment_id);
			if ($file_path && file_exists($file_path)) {
				$file_size = filesize($file_path);
				
				// Check if already in database
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM $conversion_table WHERE attachment_id = %d AND file_path = %s",
						$attachment_id,
						$file_path
					)
				);
				
				if (!$exists) {
					$values[] = $wpdb->prepare(
						"(%d, %s, %d, %s, %s, %s)",
						$attachment_id,
						$file_path,
						$file_size,
						'pending',
						0,
						current_time('mysql')
					);
					
					// Insert in batches of 50 to avoid huge queries
					if (count($values) >= 50) {
						$query = "INSERT INTO $conversion_table (attachment_id, file_path, file_size, status, attempts, date_added) VALUES ";
						$query .= implode(', ', $values);
						$wpdb->query($query);
						$values = array();
					}
				}
			}
		}
		
		// Insert any remaining records
		if (!empty($values)) {
			$query = "INSERT INTO $conversion_table (attachment_id, file_path, file_size, status, attempts, date_added) VALUES ";
			$query .= implode(', ', $values);
			$wpdb->query($query);
		}
	}
	
	return $batch_id;
}

/**
 * Get next image to convert from database.
 * 
 * @return array|false Image data or false if no images to convert.
 */
function basecamp_webp_get_next_image() {
	global $wpdb;
	$conversion_table = $wpdb->prefix . 'basecamp_webp_conversions';
	
	// Get next pending image
	$image = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $conversion_table 
			WHERE status = %s OR (status = %s AND attempts < 3)
			ORDER BY attempts ASC, id ASC LIMIT 1",
			'pending',
			'error'
		),
		ARRAY_A
	);
	
	if (!$image) {
		return false;
	}
	
	// Update attempt count and timestamp
	$wpdb->update(
		$conversion_table,
		array(
			'attempts' => $image['attempts'] + 1,
			'last_attempt' => current_time('mysql'),
			'status' => 'processing'
		),
		array('id' => $image['id'])
	);
	
	return $image;
}

/**
 * Update image conversion record with result.
 * 
 * @param int $record_id Record ID.
 * @param bool $success Whether conversion was successful.
 * @param array $data Additional data to save.
 */
function basecamp_webp_update_conversion_record($record_id, $success, $data = array()) {
	global $wpdb;
	$conversion_table = $wpdb->prefix . 'basecamp_webp_conversions';
	
	$update_data = array(
		'status' => $success ? 'completed' : 'error',
	);
	
	if ($success) {
		$update_data['date_converted'] = current_time('mysql');
	}
	
	if (isset($data['webp_size'])) {
		$update_data['webp_size'] = $data['webp_size'];
	}
	
	if (isset($data['conversion_time'])) {
		$update_data['conversion_time'] = $data['conversion_time'];
	}
	
	if (isset($data['error_message']) && !$success) {
		$update_data['error_message'] = $data['error_message'];
	}
	
	$wpdb->update(
		$conversion_table,
		$update_data,
		array('id' => $record_id)
	);
	
	// Update batch statistics
	basecamp_webp_update_batch_stats();
}

/**
 * Update batch statistics.
 */
function basecamp_webp_update_batch_stats() {
	global $wpdb;
	$conversion_table = $wpdb->prefix . 'basecamp_webp_conversions';
	$summary_table = $wpdb->prefix . 'basecamp_webp_stats';
	
	// Get the current running batch
	$batch = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $summary_table WHERE status = %s ORDER BY id DESC LIMIT 1",
			'running'
		)
	);
	
	if (!$batch) {
		return;
	}
	
	// Get current stats
	$stats = $wpdb->get_row(
		"SELECT 
			COUNT(*) AS total_processed,
			SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS successful,
			SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) AS failed,
			SUM(file_size) AS total_original,
			SUM(webp_size) AS total_webp
		FROM $conversion_table
		WHERE (status = 'completed' OR status = 'error')"
	);
	
	// Update batch record
	$wpdb->update(
		$summary_table,
		array(
			'processed_images' => (int)$stats->total_processed,
			'successful_conversions' => (int)$stats->successful,
			'failed_conversions' => (int)$stats->failed,
			'total_original_size' => (int)$stats->total_original,
			'total_webp_size' => (int)$stats->total_webp,
		),
		array('id' => $batch->id)
	);
	
	// Check if batch is complete
	if ((int)$stats->total_processed >= $batch->total_images) {
		$wpdb->update(
			$summary_table,
			array(
				'status' => 'completed',
				'end_time' => current_time('mysql')
			),
			array('id' => $batch->id)
		);
	}
}

/**
 * Get current conversion progress.
 * 
 * @return array Progress data.
 */
function basecamp_webp_get_conversion_progress() {
	global $wpdb;
	$summary_table = $wpdb->prefix . 'basecamp_webp_stats';
	
	// Get the current or most recent batch
	$batch = $wpdb->get_row(
		"SELECT * FROM $summary_table ORDER BY id DESC LIMIT 1"
	);
	
	if (!$batch) {
		return array(
			'converting' => false,
			'total_images' => 0,
			'processed_images' => 0,
			'current_offset' => 0,
			'quality' => 80,
		);
	}
	
	// Calculate statistics
	$space_saved = $batch->total_original_size - $batch->total_webp_size;
	$space_saved_percentage = $batch->total_original_size > 0 ? 
		round(($space_saved / $batch->total_original_size) * 100, 1) : 0;
	
	return array(
		'converting' => $batch->status === 'running',
		'total_images' => $batch->total_images,
		'processed_images' => $batch->processed_images,
		'current_offset' => $batch->processed_images,
		'successful' => $batch->successful_conversions,
		'failed' => $batch->failed_conversions,
		'quality' => $batch->quality,
		'batch_id' => $batch->batch_id,
		'start_time' => $batch->start_time,
		'end_time' => $batch->end_time,
		'status' => $batch->status,
		'total_original_size' => $batch->total_original_size,
		'total_webp_size' => $batch->total_webp_size,
		'space_saved' => $space_saved,
		'space_saved_percentage' => $space_saved_percentage,
	);
}

/**
 * Reset WebP conversion progress.
 */
function basecamp_webp_reset_conversion() {
	global $wpdb;
	$summary_table = $wpdb->prefix . 'basecamp_webp_stats';
	$conversion_table = $wpdb->prefix . 'basecamp_webp_conversions';
	
	// Mark any running batches as cancelled
	$wpdb->update(
		$summary_table,
		array(
			'status' => 'cancelled',
			'end_time' => current_time('mysql')
		),
		array('status' => 'running')
	);
	
	// Reset all conversion records to pending
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE $conversion_table SET status = %s, attempts = 0, error_message = NULL WHERE status != %s",
			'pending',
			'completed'
		)
	);
	
	// Also reset the old style conversion status for compatibility
	update_option('basecamp_webp_conversion_status', array());
}