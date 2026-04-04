<?php

declare(strict_types=1);
/**
 * Basecamp Meta Description Class
 *
 * Handles meta descriptions for SEO.
 *
 * @package basecamp
 */

namespace Basecamp\SEO;

final class MetaDescription {

	/**
	 * Register hooks for meta description functionality.
	 */
	public static function init() {
		// Avoid duplicate meta if major SEO plugins are active
		if (class_exists('WPSEO_Frontend') || class_exists('RankMath')) {
			return;
		}
		add_action('wp_head', [__CLASS__, 'add_meta_descriptions'], 1);
	}

	/**
	 * Add meta description and social meta tags to the header.
	 */
	public static function add_meta_descriptions() {
		try {
			$description = get_bloginfo('description');

			if (is_singular()) {
				global $post;
				if (has_excerpt($post->ID)) {
					$description = strip_tags(get_the_excerpt());
				} else {
					$excerpt = strip_tags($post->post_content);
					$excerpt = strip_shortcodes($excerpt);
					$excerpt = wp_trim_words($excerpt, 30, '...');
					if (!empty($excerpt)) {
						$description = $excerpt;
					}
				}
			} elseif (is_category() || is_tag() || is_tax()) {
				$term = get_queried_object();
				if (!empty($term->description)) {
					$description = strip_tags($term->description);
				} else {
					$description = sprintf(__('Browse all %s content', 'basecamp'), single_term_title('', false));
				}
			} elseif (is_post_type_archive()) {
				$post_type = get_query_var('post_type');
				if (is_array($post_type)) {
					$post_type = reset($post_type);
				}
				$post_type_obj = get_post_type_object($post_type);
				if ($post_type_obj) {
					$description = $post_type_obj->description;
				}
			} elseif (is_author()) {
				$author = get_queried_object();
				if ($author) {
					$description = sprintf(__('Posts by %s', 'basecamp'), $author->display_name);
				}
			} elseif (is_search()) {
				$description = sprintf(__('Search results for "%s"', 'basecamp'), get_search_query());
			} elseif (is_archive()) {
				if (is_date()) {
					if (is_day()) {
						$description = sprintf(__('Archive for %s', 'basecamp'), get_the_date());
					} elseif (is_month()) {
						$description = sprintf(__('Archive for %s', 'basecamp'), get_the_date('F Y'));
					} elseif (is_year()) {
						$description = sprintf(__('Archive for %s', 'basecamp'), get_the_date('Y'));
					}
				}
			}

			$description = wp_trim_words($description, 30, '...');

			echo '<meta name="description" content="' . esc_attr($description) . '">' . PHP_EOL;
			echo '<meta property="og:description" content="' . esc_attr($description) . '">' . PHP_EOL;
			echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . PHP_EOL;
		} catch ( \Exception $e ) {
			$fallback = esc_attr( get_bloginfo( 'description' ) );
			echo '<meta name="description" content="' . $fallback . '">' . PHP_EOL;
			echo '<meta property="og:description" content="' . $fallback . '">' . PHP_EOL;
			echo '<meta name="twitter:description" content="' . $fallback . '">' . PHP_EOL;
		}
	}

	/**
	 * Get meta description for a specific post/page/term.
	 *
	 * @param int|\WP_Post|\WP_Term|null $object Post, term or ID to get description for
	 * @param int $word_count Maximum number of words (default 30)
	 * @return string The meta description
	 */
	public static function get_meta_description( $object = null, int $word_count = 30 ): string {
		$description = get_bloginfo( 'description' );

		if ( $object instanceof \WP_Post || ( is_numeric( $object ) && get_post( $object ) ) ) {
			$post = $object instanceof \WP_Post ? $object : get_post( $object );
			if ( has_excerpt( $post->ID ) ) {
				$description = strip_tags( get_the_excerpt( $post->ID ) );
			} else {
				$excerpt = strip_tags( $post->post_content );
				$excerpt = strip_shortcodes( $excerpt );
				$excerpt = wp_trim_words( $excerpt, $word_count, '...' );
				if ( ! empty( $excerpt ) ) {
					$description = $excerpt;
				}
			}
		} elseif ( $object instanceof \WP_Term ) {
			if ( ! empty( $object->description ) ) {
				$description = strip_tags( $object->description );
			} else {
				$description = sprintf( __( 'Browse all %s content', 'basecamp' ), $object->name );
			}
		}

		return wp_trim_words($description, $word_count, '...');
	}
}

// Register hooks on init.
add_action('init', [ MetaDescription::class, 'init' ]);