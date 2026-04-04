<?php

declare(strict_types=1);
/**
 * Frontend functionality for Basecamp theme.
 *
 * @package basecamp
 */

namespace Basecamp\Frontend;

final class Frontend {

	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'start_output_buffer' ] );

		// Frontend hooks and filters
		add_filter('nav_menu_link_attributes', [ __CLASS__, 'menu_selected_class' ], 99, 4);
		add_filter('wp_resource_hints', '__return_empty_array', 99);
		add_filter('wp_img_tag_add_auto_sizes', '__return_false');
		//add_filter('wp_speculation_rules_configuration', '__return_null');
		add_filter('is_active_sidebar', [ __CLASS__, 'remove_sidebar' ], 10, 2);

		// Social icons in menu (Basecamp SVG system)
			if ( class_exists( SVGIcons::class ) ) {
			add_filter( 'walker_nav_menu_start_el', [ __CLASS__, 'basecamp_nav_menu_social_icons' ], 10, 4 );
		}
	}

	/**
	 * Start output buffering to clean up self-closing tags in HTML output.
	 */
	public function start_output_buffer() {
		ob_start( [ $this, 'remove_trailing_slash_on_html_tags' ] );
	}

	/**
	 * Remove trailing slashes and spaces from self-closing HTML tags (e.g., <br />, <img />).
	 *
	 * @param string $content HTML content.
	 * @return string
	 */
	public function remove_trailing_slash_on_html_tags( $content ) {
		return preg_replace( '/<(img|br|hr|input|meta|link)([^>]*)\s+\/>/', '<$1$2>', $content );
	}

	/**
	 * Add schema.org markup to the html tag.
	 */
    // usage: Basecamp_Frontend::html_schema(); within the php tag
	public static function html_schema() {
		$schema = 'http://schema.org/';
		if ( is_single() ) {
			$type = "Article";
		} elseif ( is_home() || is_archive() || is_category() ) {
			$type = "WebPage";
		} elseif ( is_front_page() ) {
			$type = "WebPage";
		} else {
			$type = 'WebPage';
		}
		echo 'itemscope="itemscope" itemtype="' . $schema . $type . '"';
	}

	/**
	 * Gets a nicely formatted string for the published date.
	 */
    // usage: <span class="posted-on">' . Basecamp_Frontend::template_time_link() . '</span>
	public static function template_time_link() {
		$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
		$time_string = sprintf(
			$time_string,
			get_the_date( DATE_W3C ),
			get_the_date(),
			get_the_modified_date( DATE_W3C ),
			get_the_modified_date()
		);
		return sprintf(
			/* translators: %s: post date */
			__( '<span class="hide-text">Posted on</span> %s', 'basecamp' ),
			'<a href="' . esc_url( get_permalink() ) . '" rel="bookmark">' . $time_string . '</a>'
		);
	}

	/**
	 * Display post thumbnail.
	 *
	 * @param string $size The post thumbnail size.
	 */
	public static function post_thumbnail( $size = 'full' ) {
		if ( has_post_thumbnail() ) {
			the_post_thumbnail( $size );
		}
	}

	/**
	 * Output critical CSS inline in the head.
	 * @param string $css_file_path Absolute path to the critical CSS file.
	 * @param string $transient_key Unique key for the transient.
	 */
	public static function output_critical_css( $css_file_path, $transient_key = 'basecamp_critical_css' ) {
		$css = get_transient( $transient_key );
		$file_mtime = file_exists( $css_file_path ) ? filemtime( $css_file_path ) : 0;

		if ( false === $css || get_transient( $transient_key . '_mtime' ) !== $file_mtime ) {
			$css = file_exists( $css_file_path ) ? file_get_contents( $css_file_path ) : '';
			$css = preg_replace( '/\s+/', ' ', $css ); // Simple minification
			set_transient( $transient_key, $css, DAY_IN_SECONDS );
			set_transient( $transient_key . '_mtime', $file_mtime, DAY_IN_SECONDS );
		}

		if ( $css ) {
			echo '<style id="critical-css">' . $css . '</style>';
		}
	}

	/**
	 * Add 'menu--selected' class to anchor tags for active menu items.
	 */
	public static function menu_selected_class($atts, $item, $args, $depth) {
		$item_classes = is_array($item->classes) ? $item->classes : array();
		$active_classes = array(
			'current-menu-item',
			'current-menu-ancestor',
			'current-menu-parent',
			'current_page_item',
			'current_page_parent',
			'current_page_ancestor'
		);
		$is_active = false;
		foreach ($active_classes as $class) {
			if (in_array($class, $item_classes)) {
				$is_active = true;
				break;
			}
		}
		if (!isset($atts['class'])) {
			$atts['class'] = '';
		}
		if ($is_active) {
			$atts['class'] .= ' menu--selected';
			$atts['class'] = trim($atts['class']);
		} else {
			$atts['class'] = trim($atts['class']);
		}
		return $atts;
	}

	/**
	 * Remove the sidebar from the main query.
	 */
	public static function remove_sidebar() {
		return false;
	}

	/**
	 * Displays SVG icons in social links menu (for Basecamp).
	 */
	public static function basecamp_nav_menu_social_icons( $item_output, $item, $depth, $args ) {
		if ( 'social' === $args->theme_location ) {
				$svg = SVGIcons::get_social_link_svg( $item->url, 24 );
			if ( empty( $svg ) && function_exists( 'basecamp_get_theme_svg' ) ) {
				$svg = \basecamp_get_theme_svg( 'link' );
			}
			$item_output = str_replace( $args->link_after, '</span>' . $svg, $item_output );
		}
		return $item_output;
	}

	/**
	 * Numeric Page Navigation.
	 *
	 * @param WP_Query|null $query Optionally pass a custom query object.
	 * @param array $args Optional. Additional paginate_links args.
	 */
	public static function page_navi( $query = null, $args = [] ) {
	$query = $query instanceof \WP_Query ? $query : $GLOBALS['wp_query'];
		$total_pages = isset( $query->max_num_pages ) ? (int) $query->max_num_pages : 1;
		if ( $total_pages <= 1 ) {
			return;
		}

		$bignum = 999999999;
		$defaults = [
			'base'      => str_replace( $bignum, '%#%', esc_url( get_pagenum_link( $bignum ) ) ),
			'format'    => '',
			'current'   => max( 1, get_query_var( 'paged' ) ),
			'total'     => $total_pages,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'type'      => 'list',
			'end_size'  => 3,
			'mid_size'  => 3,
			'add_args'  => false,
		];
		$paginate_args = wp_parse_args( $args, $defaults );

		$links = paginate_links( $paginate_args );
		if ( $links ) {
			echo '<nav class="paged--pagination" aria-label="' . esc_attr__( 'Pagination', 'basecamp' ) . '">';
			echo wp_kses_post( $links );
			echo '</nav>';
		}
	}

	/**
	 * Display related posts based on shared tags.
	 */
	public static function related_posts( $args = [] ) {
		global $post;
		if ( ! $post ) {
			return;
		}

		$default_args = [
			'posts_per_page' => apply_filters( 'basecamp_related_posts_count', 5 ),
			'post__not_in'   => [ $post->ID ],
			'tag__in'        => [],
			'fields'         => 'ids',
			'ignore_sticky_posts' => true,
			'no_found_rows'  => true,
		];

		$tags = wp_get_post_tags( $post->ID, [ 'fields' => 'ids' ] );
		if ( empty( $tags ) ) {
			echo '<ul id="related--posts"><li class="not--related">' . esc_html__( 'No Related Posts Yet!', 'basecamp' ) . '</li></ul>';
			return;
		}

		$query_args = wp_parse_args( $args, array_merge( $default_args, [ 'tag__in' => $tags ] ) );

		$related_query = new \WP_Query( $query_args );

		echo '<ul id="related--posts">';
		if ( $related_query->have_posts() ) {
			foreach ( $related_query->posts as $related_post_id ) {
				$title = get_the_title( $related_post_id );
				$permalink = get_permalink( $related_post_id );
				echo '<li class="is--related"><a class="related" href="' . esc_url( $permalink ) . '" title="' . esc_attr( $title ) . '">' . esc_html( $title ) . '</a></li>';
			}
		} else {
			echo '<li class="not--related">' . esc_html__( 'No Related Posts Yet!', 'basecamp' ) . '</li>';
		}
		echo '</ul>';

		wp_reset_postdata();
	}

	/**
	 * Output a <picture> element that serves a portrait crop on mobile and
	 * a full responsive landscape <img> on wider viewports.
	 *
	 * The landscape fallback is generated via wp_get_attachment_image(), so the
	 * browser receives the full WP-generated srcset along with automatic WebP
	 * substitution, width/height attributes, and lazy-loading — no extra work needed.
	 *
	 * Usage:
	 *   Basecamp_Frontend::picture( get_post_thumbnail_id(), [
	 *       'class'         => 'hero__picture',
	 *       'img_class'     => 'hero__img',
	 *       'loading'       => 'eager',
	 *       'fetchpriority' => 'high',
	 *   ] );
	 *
	 * Skip the portrait <source> entirely (landscape-only content):
	 *   Basecamp_Frontend::picture( $id, [ 'portrait_size' => false ] );
	 *
	 * @param int   $attachment_id Attachment/image ID.
	 * @param array $args {
	 *   Optional overrides.
	 *   @type string|false $portrait_size   Size handle for the portrait <source> URL, or false to skip. Default 'portait-m'.
	 *   @type string       $portrait_break  max-width at which portrait is served. Default '600px'.
	 *   @type string       $landscape_size  Size handle passed to wp_get_attachment_image(). Default 'basecamp-img-xl'.
	 *   @type string|null  $alt             Alt text. Null (default) uses the attachment's own alt field.
	 *   @type string       $class           Class on the <picture> element. Default ''.
	 *   @type string       $img_class       Class on the <img> inside the picture. Default ''.
	 *   @type string       $loading         loading attribute: 'lazy' or 'eager'. Default 'lazy'.
	 *   @type string|false $fetchpriority   fetchpriority attribute: 'high', 'low', or false to omit. Default false.
	 * }
	 * Width/height and decoding="async" are always output — no args needed.
	 */
	public static function picture( int $attachment_id, array $args = [] ): void {
		$defaults = [
			'portrait_size'  => 'portait-m',
			'portrait_break' => '600px',
			'landscape_size' => 'basecamp-img-xl',
			'alt'            => null,
			'class'          => '',
			'img_class'      => '',
			'loading'        => 'lazy',
			'fetchpriority'  => false,
		];

		$args = wp_parse_args( $args, $defaults );

		if ( ! $attachment_id ) {
			return;
		}

		// Build attrs array for wp_get_attachment_image() — it handles srcset,
		// WebP substitution, width/height, and all standard img attributes.
		$img_attrs = [
			'loading'  => $args['loading'],
			'decoding' => 'async',
		];
		if ( $args['img_class'] ) {
			$img_attrs['class'] = $args['img_class'];
		}
		if ( $args['fetchpriority'] ) {
			$img_attrs['fetchpriority'] = $args['fetchpriority'];
		}
		// Only override alt if caller passed a value; null defers to attachment meta.
		if ( null !== $args['alt'] ) {
			$img_attrs['alt'] = $args['alt'];
		}

		$landscape_img = wp_get_attachment_image( $attachment_id, $args['landscape_size'], false, $img_attrs );

		// Portrait <source> uses a single URL (one mobile-optimised crop, no srcset).
		$portrait_url = '';
		if ( $args['portrait_size'] ) {
			$portrait_src = wp_get_attachment_image_src( $attachment_id, $args['portrait_size'] );
			$portrait_url = $portrait_src ? esc_url( $portrait_src[0] ) : '';
		}

		if ( ! $landscape_img && ! $portrait_url ) {
			return;
		}

		$picture_class = $args['class'] ? ' class="' . esc_attr( $args['class'] ) . '"' : '';

		echo '<picture' . $picture_class . '>';

		// Portrait <source> — single URL, browser switches at the breakpoint.
		if ( $portrait_url ) {
			echo '<source'
				. ' srcset="' . $portrait_url . '"'
				. ' media="(max-width: ' . esc_attr( $args['portrait_break'] ) . ')"'
				. '>';
		}

		// Landscape <img> — wp_get_attachment_image() provides full srcset,
		// WebP, width/height, and all loading attributes automatically.
		echo $landscape_img;

		echo '</picture>';
	}

	/**
	 * Build a srcset string from an array of registered image size handles.
	 * URLs are passed through wp_get_attachment_image_src(), so the WebP
	 * filter applies automatically — no manual substitution needed.
	 *
	 * Note: picture() no longer calls this — wp_get_attachment_image() generates
	 * the landscape srcset automatically. Keep for custom markup that needs a
	 * hand-built srcset string.
	 *
	 * @param int      $attachment_id Attachment ID.
	 * @param string[] $sizes         Array of registered size handles.
	 * @return string Comma-separated srcset value, or empty string.
	 */
	protected static function build_srcset( int $attachment_id, array $sizes ): string {
		$parts = [];
		foreach ( $sizes as $size ) {
			$src = wp_get_attachment_image_src( $attachment_id, $size );
			if ( $src && ! empty( $src[0] ) && ! empty( $src[1] ) ) {
				$parts[] = esc_url( $src[0] ) . ' ' . (int) $src[1] . 'w';
			}
		}
		return implode( ', ', $parts );
	}
}
