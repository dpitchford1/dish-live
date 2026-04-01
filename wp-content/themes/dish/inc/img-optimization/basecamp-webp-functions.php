<?php
/**
 * WebP image support and frontend output functions
 *
 * Handles detection of browser WebP support, replacement of image URLs with WebP versions,
 * and filters for image output (src, srcset, content, thumbnails, etc.).
 *
 * @package basecamp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if the browser supports WebP images.
 *
 * Uses the HTTP_ACCEPT header and user agent sniffing for fallback.
 *
 * @return bool Whether the browser supports WebP.
 */
function basecamp_webp_is_supported() {
	// Check for Accept header (most reliable)
	if ( isset( $_SERVER['HTTP_ACCEPT'] ) && strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false ) {
		return true;
	}

	// Fallback: Check user agent for known WebP compatible browsers
	if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$user_agent = $_SERVER['HTTP_USER_AGENT'];

		// Chrome 9+, Opera 12+, Firefox 65+, Edge 18+, Safari 14+
		if (
			( strpos( $user_agent, 'Chrome/' ) !== false && preg_match( '/Chrome\/([0-9]+)/', $user_agent, $matches ) && (int) $matches[1] >= 9 ) ||
			( strpos( $user_agent, 'Opera/' ) !== false ) ||
			( strpos( $user_agent, 'Firefox/' ) !== false && preg_match( '/Firefox\/([0-9]+)/', $user_agent, $matches ) && (int) $matches[1] >= 65 ) ||
			( strpos( $user_agent, 'Edge/' ) !== false && preg_match( '/Edge\/([0-9]+)/', $user_agent, $matches ) && (int) $matches[1] >= 18 ) ||
			( strpos( $user_agent, 'Safari/' ) !== false && preg_match( '/Version\/([0-9]+)/', $user_agent, $matches ) && (int) $matches[1] >= 14 )
		) {
			return true;
		}
	}

	return false;
}

/**
 * Check if a WebP version of an image exists.
 *
 * @param string $image_url URL of the original image.
 * @return bool|string WebP image URL if exists, false otherwise.
 */
function basecamp_get_webp_image( $image_url ) {
	// Skip if URL is empty or already a WebP image
	if ( empty( $image_url ) || strpos( $image_url, '.webp' ) !== false ) {
		return false;
	}

	// Skip SVG images
	if ( strpos( $image_url, '.svg' ) !== false ) {
		return false;
	}

	// Convert URL to file path
	$upload_dir = wp_upload_dir();
	$file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $image_url );

	// Check if original file exists
	if ( ! file_exists( $file_path ) ) {
		return false;
	}

	// Get file extension
	$path_parts = pathinfo($file_path);

	// Check for replacement format (image.webp)
	$webp_path = str_replace('.' . $path_parts['extension'], '.webp', $file_path);

	// If not found, check for appended format (image.jpg.webp)
	if (!file_exists($webp_path)) {
		$webp_path = $file_path . '.webp';
	}

	// If either WebP version exists, return the corresponding URL
	if (file_exists($webp_path)) {
		if (strpos($webp_path, $file_path . '.webp') !== false) {
			// Appended format
			$webp_url = $image_url . '.webp';
		} else {
			// Replacement format
			$webp_url = str_replace('.' . $path_parts['extension'], '.webp', $image_url);
		}
		return $webp_url;
	}

	return false;
}

/**
 * Replace image URLs with WebP versions if available and supported.
 *
 * @param string $image_url Original image URL.
 * @return string Modified image URL.
 */
function basecamp_replace_with_webp( $image_url ) {
	// Only replace if browser supports WebP
	if ( ! basecamp_webp_is_supported() ) {
		return untrailingslashit($image_url);
	}

	$webp_url = basecamp_get_webp_image( $image_url );

	if ( $webp_url ) {
		return untrailingslashit($webp_url);
	}

	return untrailingslashit($image_url);
}

/**
 * Filter image source to use WebP when available.
 *
 * @param array|false $image Image data.
 * @param int $attachment_id Attachment ID.
 * @param string|array $size Registered image size or dimensions.
 * @return array|false Modified image data.
 */
function basecamp_filter_get_image_src( $image, $attachment_id, $size ) {
	// Skip in admin (except AJAX)
	if (is_admin() && !wp_doing_ajax()) {
		return $image;
	}

	if ( ! $image ) {
		return $image;
	}

	$image[0] = basecamp_replace_with_webp( $image[0] );
	$image[0] = untrailingslashit($image[0]);

	return $image;
}
add_filter( 'wp_get_attachment_image_src', 'basecamp_filter_get_image_src', 10, 3 );

/**
 * Filter post thumbnail image source to use WebP.
 *
 * @param string $html Image HTML.
 * @param int $post_id Post ID.
 * @param int $post_thumbnail_id Thumbnail ID.
 * @param string|array $size Registered image size or dimensions.
 * @param string $attr Query string of attributes.
 * @return string Modified image HTML.
 */
function basecamp_filter_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
	// Skip in admin (except AJAX)
	if (is_admin() && !wp_doing_ajax()) {
		return $html;
	}

	// Exclude if needed
	if(basecamp_should_exclude_webp()) {
		return $html;
	}

	if (!basecamp_webp_is_supported() || empty($html)) {
		return $html;
	}

	// Replace src attribute with WebP if available
	$html = preg_replace_callback(
		'/src="([^"]+\.(jpg|jpeg|png))"/i',
		function($matches) {
			$webp_url = basecamp_get_webp_image($matches[1]);
			if ($webp_url) {
				return 'src="' . esc_url($webp_url) . '"';
			}
			return $matches[0];
		},
		$html
	);

	return $html;
}
add_filter( 'post_thumbnail_html', 'basecamp_filter_post_thumbnail_html', 10, 5 );

/**
 * Should WebP conversion be excluded in the current context?
 *
 * @return bool Whether to exclude WebP conversion.
 */
function basecamp_should_exclude_webp() {
	// Exclude in admin post save/update
	if (is_admin() && !wp_doing_ajax()) {
		global $pagenow;
		if (in_array($pagenow, array('post.php', 'post-new.php'))) {
			return true;
		}
	}

	// Exclude if URL param disables WebP
	if (isset($_GET['disable_webp']) || isset($_GET['test_native_srcset'])) {
		return true;
	}

	// Exclude if post has gallery shortcode
	global $post;
	if ($post && has_shortcode($post->post_content, 'gallery')) {
		return true;
	}

	return false;
}

/**
 * Check if a specific element should have WebP excluded based on classes.
 *
 * @param array $attr Image attributes including class.
 * @return bool Whether to exclude WebP for this element.
 */
function basecamp_should_exclude_element_webp($attr) {
	// Global exclusion
	if (basecamp_should_exclude_webp()) {
		return true;
	}

	// Exclude for specific classes
	if (isset($attr['class'])) {
		$excluded_classes = array('native-img', 'no-webp');
		$classes = explode(' ', $attr['class']);

		foreach ($excluded_classes as $excluded_class) {
			if (in_array($excluded_class, $classes)) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Filter image attributes to use WebP.
 *
 * @param array $attr Attributes for the image markup.
 * @param WP_Post $attachment Image attachment post.
 * @param string|array $size Requested size.
 * @return array Modified attributes.
 */
function basecamp_filter_wp_get_attachment_image_attributes( $attr, $attachment, $size ) {
	// Skip in admin (except AJAX)
	if (is_admin() && !wp_doing_ajax()) {
		return $attr;
	}

	// Exclude if needed
	if (basecamp_should_exclude_element_webp($attr)) {
		return $attr;
	}

	// Skip if browser doesn't support WebP
	if (!basecamp_webp_is_supported()) {
		return $attr;
	}

	// Replace src with WebP version if available
	if (isset($attr['src'])) {
		$webp_src = basecamp_get_webp_image($attr['src']);
		if ($webp_src) {
			$attr['src'] = untrailingslashit($webp_src);

			// Also handle srcset for consistency
			if (isset($attr['srcset'])) {
				$srcset_urls = explode(',', $attr['srcset']);
				$new_srcset = array();

				foreach ($srcset_urls as $srcset_url) {
					// Each srcset entry has format "url size"
					if (preg_match('/(.+?)(\s+.+)/', $srcset_url, $matches)) {
						$url = trim($matches[1]);
						$size_info = $matches[2];

						// Convert URL to WebP
						$webp_url = basecamp_get_webp_image($url);
						if ($webp_url) {
							$new_srcset[] = untrailingslashit($webp_url) . $size_info;
						} else {
							$new_srcset[] = untrailingslashit($url) . $size_info;
						}
					} else {
						$new_srcset[] = untrailingslashit($srcset_url);
					}
				}

				$attr['srcset'] = implode(', ', $new_srcset);
			}
		} else {
			$attr['src'] = untrailingslashit($attr['src']);
		}
	} else if (isset($attr['srcset'])) {
		// If no src but srcset exists, process srcset
		$srcset_urls = explode(',', $attr['srcset']);
		$new_srcset = array();
		$any_webp_found = false;

		foreach ($srcset_urls as $srcset_url) {
			if (preg_match('/(.+?)(\s+.+)/', $srcset_url, $matches)) {
				$url = trim($matches[1]);
				$size_info = $matches[2];

				$webp_url = basecamp_get_webp_image($url);
				if ($webp_url) {
					$new_srcset[] = untrailingslashit($webp_url) . $size_info;
					$any_webp_found = true;
				} else {
					$new_srcset[] = untrailingslashit($url) . $size_info;
				}
			} else {
				$new_srcset[] = untrailingslashit($srcset_url);
			}
		}

		if ($any_webp_found) {
			$attr['srcset'] = implode(', ', $new_srcset);
		}
	}

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'basecamp_filter_wp_get_attachment_image_attributes', 10, 3 );

/**
 * Check WebP conversion for all image URLs in an HTML string.
 *
 * @param string $html HTML with image tags.
 * @return string Modified HTML with WebP images.
 */
function basecamp_check_webp_conversion($html) {
	// Skip in admin (except AJAX)
	if (is_admin() && !wp_doing_ajax()) {
		return $html;
	}

	// Skip if browser doesn't support WebP
	if (!basecamp_webp_is_supported()) {
		return $html;
	}

	// Process src and srcset attributes for img tags
	$html = preg_replace_callback(
		'/(src|srcset)=["\'](.*?)["\']/i',
		function($matches) {
			$attr = $matches[1]; // src or srcset
			$value = $matches[2]; // URL or srcset string

			if ($attr === 'src') {
				// Single URL for src
				$webp_url = basecamp_get_webp_image($value);
				if ($webp_url) {
					return 'src="' . esc_url(untrailingslashit($webp_url)) . '"';
				}
				return 'src="' . esc_url(untrailingslashit($value)) . '"';
			} else if ($attr === 'srcset') {
				// Multiple URLs for srcset
				$srcset_parts = explode(',', $value);
				$new_srcset_parts = array();

				foreach ($srcset_parts as $part) {
					if (preg_match('/(.+?)(\s+.+)/', trim($part), $url_matches)) {
						$url = $url_matches[1];
						$descriptor = $url_matches[2];

						$webp_url = basecamp_get_webp_image($url);
						if ($webp_url) {
							$new_srcset_parts[] = untrailingslashit($webp_url) . $descriptor;
						} else {
							$new_srcset_parts[] = untrailingslashit($url) . $descriptor;
						}
					} else {
						$new_srcset_parts[] = untrailingslashit(trim($part));
					}
				}

				return 'srcset="' . implode(', ', $new_srcset_parts) . '"';
			}

			return $matches[0];
		},
		$html
	);

	return $html;
}
add_filter('wp_get_attachment_image', 'basecamp_check_webp_conversion', 999);

/**
 * Filter content images to use WebP.
 *
 * @param string $content Post content.
 * @return string Modified post content.
 */
function basecamp_filter_content_images( $content ) {
	// Skip in admin (except AJAX)
	if (is_admin() && !wp_doing_ajax()) {
		return $content;
	}

	// Exclude if needed
	if (basecamp_should_exclude_webp()) {
		return $content;
	}

	if (!basecamp_webp_is_supported() || empty($content)) {
		return $content;
	}

	// Replace image URLs in src attribute
	$content = preg_replace_callback(
		'/<img[^>]+src="([^"]+\.(jpg|jpeg|png))"[^>]*>/i',
		function($matches) {
			$img_tag = $matches[0];
			$src = $matches[1];

			$webp_url = basecamp_get_webp_image($src);
			if ($webp_url) {
				$img_tag = str_replace('src="' . $src . '"', 'src="' . untrailingslashit($webp_url) . '"', $img_tag);
			} else {
				$img_tag = str_replace('src="' . $src . '"', 'src="' . untrailingslashit($src) . '"', $img_tag);
			}

			return $img_tag;
		},
		$content
	);

	return $content;
}
add_filter( 'the_content', 'basecamp_filter_content_images', 10 );

// Apply the WebP check to images in content (for wp-image-... classes)
add_filter('the_content', function($content) {
	// Skip in admin (except AJAX)
	if (is_admin() && !wp_doing_ajax()) {
		return $content;
	}

	return preg_replace_callback(
		'/<img[^>]+class=["\'][^"\']*wp-image-[^"\']*["\'][^>]*>/i',
		function($matches) {
			return basecamp_check_webp_conversion($matches[0]);
		},
		$content
	);
}, 999);

/**
 * Debug function to log WebP conversion activity.
 * Only active when WP_DEBUG is true.
 *
 * @param string $message The message to log.
 * @param mixed $data Additional data to log.
 */
function basecamp_webp_debug_log($message, $data = null) {
	if (defined('WP_DEBUG') && WP_DEBUG) {
		$context = is_admin() ? 'ADMIN' : 'FRONTEND';
		global $pagenow;
		$page = isset($pagenow) ? $pagenow : 'unknown';
		$log_message = sprintf('[WEBP-%s/%s] %s', $context, $page, $message);
		if ($data !== null) {
			$log_message .= ' - Data: ' . (is_array($data) || is_object($data) ? json_encode($data) : $data);
		}
		error_log($log_message);
	}
}

// Uncomment this to debug WebP conversion activity
// add_action('init', function() {
//     basecamp_webp_debug_log('WebP module initialized');
// });


