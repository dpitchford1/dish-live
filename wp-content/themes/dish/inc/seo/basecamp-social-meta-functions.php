<?php

declare(strict_types=1);
/**
 * Basecamp Social Meta Class
 *
 * Handles Open Graph, Twitter Card, and other social media metadata.
 *
 * @package basecamp
 */

namespace Basecamp\SEO;

final class SocialMeta {

	/**
	 * Register hooks for social meta functionality.
	 */
	public static function init() {
		// Avoid duplicate meta if major SEO plugins are active
		if (class_exists('WPSEO_Frontend') || class_exists('RankMath')) {
			return;
		}
		add_action('wp_head', [__CLASS__, 'add_social_meta'], 2);
		add_action('wp_head', [__CLASS__, 'add_social_image_dimensions'], 3);
		self::maybe_remove_old_social_meta();
	}

	/**
	 * Remove the old Open Graph function from MU plugins if it exists.
	 */
	protected static function maybe_remove_old_social_meta() {
		if (function_exists('basecamp_add_opengraph')) {
			remove_action('wp_head', 'basecamp_add_opengraph');
		}
		global $wp_filter;
		if (isset($wp_filter['wp_head']) && is_object($wp_filter['wp_head'])) {
			$og_functions = ['add_opengraph', 'og_tags', 'open_graph_meta', 'add_open_graph'];
			foreach ($og_functions as $function) {
				if (has_filter('wp_head', $function)) {
					remove_filter('wp_head', $function);
				}
			}
		}
	}

	/**
	 * Add social media metadata tags to the site head.
	 */
	public static function add_social_meta() {
		$title = '';
		$url = '';
		$image = '';
		$type = 'website';
		$site_name = get_bloginfo('name');
		$default_image = get_theme_mod('basecamp_default_share_image', get_template_directory_uri() . '/assets/img/logos/login_logo.png');
		$url = esc_url((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");

		if (is_singular()) {
			global $post;
			$title = get_the_title();
			$url = get_permalink();
			$type = 'article';
			if (has_post_thumbnail($post->ID)) {
				$thumbnail_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
				if ($thumbnail_src) {
					$image = esc_url($thumbnail_src[0]);
				}
			}
			if (function_exists('is_product') && is_product()) {
				$type = 'product';
			}
		} elseif (is_home() || is_front_page()) {
			$title = get_bloginfo('name');
			if (get_bloginfo('description')) {
				$title .= ' | ' . get_bloginfo('description');
			}
			$url = home_url('/');
			$image = get_theme_mod('basecamp_home_share_image', $default_image);
		} elseif (is_tax() || is_category() || is_tag()) {
			$term = get_queried_object();
			if ($term) {
				$title = $term->name;
				if (is_category()) {
					$title .= ' | Category';
				} elseif (is_tag()) {
					$title .= ' | Tag';
				}
				$title .= ' | ' . get_bloginfo('name');
				if (function_exists('get_term_meta')) {
					$term_image_id = get_term_meta($term->term_id, 'thumbnail_id', true);
					if ($term_image_id) {
						$term_image = wp_get_attachment_image_src($term_image_id, 'full');
						if ($term_image) {
							$image = esc_url($term_image[0]);
						}
					}
				}
			}
		} elseif (function_exists('is_shop') && is_shop()) {
			$shop_page_id = wc_get_page_id('shop');
			if ($shop_page_id > 0) {
				$title = get_the_title($shop_page_id);
				$url = get_permalink($shop_page_id);
				if (has_post_thumbnail($shop_page_id)) {
					$thumbnail_src = wp_get_attachment_image_src(get_post_thumbnail_id($shop_page_id), 'full');
					if ($thumbnail_src) {
						$image = esc_url($thumbnail_src[0]);
					}
				}
			} else {
				$title = 'Shop - ' . get_bloginfo('name');
			}
		} elseif (is_post_type_archive()) {
			$post_type = get_query_var('post_type');
			$post_type_obj = get_post_type_object($post_type);
			if ($post_type_obj) {
				$title = isset($post_type_obj->labels->name) ? $post_type_obj->labels->name : $post_type;
				$title .= ' - ' . get_bloginfo('name');
			}
		} elseif (is_author()) {
			$author = get_queried_object();
			if ($author) {
				$title = isset($author->display_name) ? 'Posts by ' . $author->display_name : 'Author Archive';
				$title .= ' - ' . get_bloginfo('name');
				$image = get_avatar_url($author->ID, ['size' => 512]);
			}
		} elseif (is_search()) {
			$title = 'Search Results for "' . get_search_query() . '"';
			$title .= ' - ' . get_bloginfo('name');
		} elseif (is_404()) {
			$title = 'Page Not Found - ' . get_bloginfo('name');
		}

		if (empty($title)) {
			$title = get_bloginfo('name');
		}
		if (empty($url)) {
			$url = home_url('/');
		}
		if (empty($image)) {
			$image = $default_image;
		}
		if (strpos($title, $site_name) === false) {
			$title .= ' - ' . $site_name;
		}

		// Allow extensions to override the final OG title (e.g. breadcrumb-style CPT titles).
		$title = apply_filters( 'basecamp_og_title', $title );

		echo '<meta property="og:title" content="' . esc_attr($title) . '">' . PHP_EOL;
		echo '<meta property="og:url" content="' . esc_url($url) . '">' . PHP_EOL;
		echo '<meta property="og:type" content="' . esc_attr($type) . '">' . PHP_EOL;
		echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . PHP_EOL;
		if (!empty($image)) {
			echo '<meta property="og:image" content="' . esc_url($image) . '">' . PHP_EOL;

			// Output og:image:width and og:image:height if local image
			$site_url = site_url();
			if (strpos($image, $site_url) === 0) {
				$local_path = str_replace($site_url, ABSPATH, $image);
				if (file_exists($local_path) && is_readable($local_path)) {
					$dimensions = getimagesize($local_path);
					if ($dimensions) {
						$width = $dimensions[0];
						$height = $dimensions[1];
						echo '<meta property="og:image:width" content="' . $width . '">' . PHP_EOL;
						echo '<meta property="og:image:height" content="' . $height . '">' . PHP_EOL;
					}
				}
			}
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . PHP_EOL;
		echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . PHP_EOL;
		echo '<meta name="twitter:url" content="' . esc_url($url) . '">' . PHP_EOL;
		if (!empty($image)) {
			echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . PHP_EOL;
		}
		$twitter_site = get_theme_mod('basecamp_twitter_site', '');
		if (!empty($twitter_site)) {
			echo '<meta name="twitter:site" content="@' . esc_attr(str_replace('@', '', $twitter_site)) . '">' . PHP_EOL;
		}
		if (is_singular() && function_exists('get_the_author_meta')) {
			$twitter_creator = get_the_author_meta('twitter', get_post_field('post_author', get_the_ID()));
			if (!empty($twitter_creator)) {
				echo '<meta name="twitter:creator" content="@' . esc_attr(str_replace('@', '', $twitter_creator)) . '">' . PHP_EOL;
			}
		}
	}

	/**
	 * Add width and height attributes to Open Graph images.
	 * (No longer needed, kept for backward compatibility)
	 */
	public static function add_social_image_dimensions() {
		// No output buffering or manipulation here.
	}

	/**
	 * Get social media metadata for a specific post/page.
	 */
	public static function get_social_meta($post_id = null) {
		$meta = [
			'title' => '',
			'url' => '',
			'image' => '',
			'type' => 'website',
			'description' => '',
			'site_name' => get_bloginfo('name')
		];
		$post = get_post($post_id);
		if ($post) {
			$meta['title'] = get_the_title($post);
			$meta['url'] = get_permalink($post);
			$meta['type'] = 'article';
			if (function_exists('is_product') && $post->post_type === 'product') {
				$meta['type'] = 'product';
			}
			if (has_excerpt($post->ID)) {
				$meta['description'] = strip_tags(get_the_excerpt($post->ID));
			} else {
				$excerpt = strip_tags($post->post_content);
				$excerpt = strip_shortcodes($excerpt);
				$meta['description'] = wp_trim_words($excerpt, 30, '...');
			}
			if (has_post_thumbnail($post->ID)) {
				$thumbnail_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
				if ($thumbnail_src) {
					$meta['image'] = $thumbnail_src[0];
				}
			} else {
				$meta['image'] = get_theme_mod('basecamp_default_share_image', get_template_directory_uri() . '/assets/img/logos/login_logo.png');
			}
		}
		return $meta;
	}
}

// Register hooks on init.
add_action('init', [ SocialMeta::class, 'init' ]);