<?php

declare(strict_types=1);
/**
 * RemoveBloat — frontend cleanup for Basecamp theme.
 *
 * Removes WP default cruft: emoji scripts, feed links, REST/oEmbed head tags,
 * jQuery Migrate, heartbeat, useless stylesheets, and pingback URL.
 *
 * @package basecamp
 */

namespace Basecamp\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoveBloat {

	/**
	 * Register all cleanup hooks.
	 */
	public static function init(): void {
		add_action( 'init',                   [ __CLASS__, 'disable_emojis' ] );
		add_action( 'stop_heartbeat',         [ __CLASS__, 'stop_heartbeat' ] );
		add_action( 'wp_enqueue_scripts',     [ __CLASS__, 'remove_useless_styles' ], 20 );
		add_action( 'wp_default_scripts',     [ __CLASS__, 'remove_jquery_migrate' ] );

		add_filter( 'tiny_mce_plugins',       [ __CLASS__, 'disable_emojis_tinymce' ] );
		add_filter( 'wp_resource_hints',      [ __CLASS__, 'disable_emojis_dns_prefetch' ], 10, 2 );
		add_filter( 'feed_links_show_comments_feed', '__return_false' );
		add_filter( 'the_generator',          '__return_empty_string' );
		add_filter( 'xmlrpc_enabled',         '__return_false' );
		add_filter( 'bloginfo_url',           [ __CLASS__, 'remove_pingback_url' ], 11, 2 );
		add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );

		remove_action( 'wp_head', 'feed_links',                  2 );
		remove_action( 'wp_head', 'feed_links_extra',            3 );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'rest_output_link_wp_head',    10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_filter( 'oembed_dataparse',    'wp_filter_oembed_result',  10 );
		remove_filter( 'the_content',         'convert_smilies' );
		remove_filter( 'the_excerpt',         'wpautop' );
		remove_action( 'set_comment_cookies', 'wp_set_comment_cookies' );
		remove_action( 'wp_enqueue_scripts',  'wp_enqueue_global_styles' );
		remove_action( 'wp_body_open',        'wp_global_styles_render_svg_filters' );

        add_filter( 'should_load_separate_core_block_assets', '__return_false' );
	}

	/**
	 * Disable the Heartbeat API script.
	 */
	public static function stop_heartbeat(): void {
		wp_deregister_script( 'heartbeat' );
	}

	/**
	 * Remove all emoji-related scripts, styles, and DNS prefetch hints.
	 */
	public static function disable_emojis(): void {
		remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles',     'print_emoji_styles' );
		remove_action( 'admin_print_styles',  'print_emoji_styles' );
		remove_filter( 'the_content_feed',    'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss',    'wp_staticize_emoji' );
		remove_filter( 'wp_mail',             'wp_staticize_emoji_for_email' );
	}

	/**
	 * Remove the TinyMCE emoji plugin.
	 *
	 * @param  array $plugins Registered TinyMCE plugins.
	 * @return array
	 */
	public static function disable_emojis_tinymce( array $plugins ): array {
		return array_diff( $plugins, [ 'wpemoji' ] );
	}

	/**
	 * Remove the emoji CDN from DNS prefetch hints.
	 *
	 * @param  array  $urls          Resource hint URLs.
	 * @param  string $relation_type Hint type (dns-prefetch, preconnect, etc.).
	 * @return array
	 */
	public static function disable_emojis_dns_prefetch( array $urls, string $relation_type ): array {
		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );
			$urls = array_diff( $urls, [ $emoji_svg_url ] );
		}
		return $urls;
	}

	/**
	 * Dequeue unwanted core stylesheet handles.
	 */
	public static function remove_useless_styles(): void {
		//wp_dequeue_style( 'classic-theme-styles' );
		//wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'wp-block-library' );

        wp_dequeue_style( 'classic-theme-styles' );
        wp_deregister_style( 'global-styles' );
        wp_dequeue_style( 'global-styles' );

        wp_deregister_style( 'global-styles-inline-css' );
        wp_dequeue_style( 'global-styles-inline-css' );

        // wp_deregister_style( 'dish-events' );
        // wp_dequeue_style( 'dish-events' );

        // wp_deregister_style( 'dish-calendar' );
        // wp_dequeue_style( 'dish-calendar' );
	}

	/**
	 * Remove jQuery Migrate from the jquery bundle on the frontend.
	 *
	 * @param \WP_Scripts $scripts The WP_Scripts instance.
	 */
	public static function remove_jquery_migrate( \WP_Scripts $scripts ): void {
		if ( ! is_admin() && ! empty( $scripts->registered['jquery'] ) ) {
			$scripts->registered['jquery']->deps = array_diff(
				$scripts->registered['jquery']->deps,
				[ 'jquery-migrate' ]
			);
		}
	}

	/**
	 * Remove the pingback URL from bloginfo output.
	 *
	 * @param  string $output   Original value.
	 * @param  string $property Requested property name.
	 * @return string|null
	 */
	public static function remove_pingback_url( string $output, string $property ): ?string {
		return ( 'pingback_url' === $property ) ? null : $output;
	}
}

RemoveBloat::init();
