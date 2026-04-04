<?php
/**
 * Menu view renderer.
 *
 * Provides the [dish_upcoming_menus] shortcode handler.
 * Queries upcoming public class instances, resolves their template menu data,
 * and renders the list via templates/menus/upcoming.php.
 *
 * @package Dish\Events\Frontend
 */

declare( strict_types=1 );

namespace Dish\Events\Frontend;

use Dish\Events\Data\ClassRepository;

/**
 * Class MenuView
 */
final class MenuView {

	/**
	 * Shortcode: [dish_upcoming_menus limit="50"]
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_upcoming( $atts = [] ): string {
		$atts = shortcode_atts( [ 'limit' => 50 ], (array) $atts, 'dish_upcoming_menus' );

		// Query upcoming, public class instances ordered by start date ASC.
		$instances = get_posts( [
			'post_type'      => 'dish_class',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['limit'],
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'dish_start_datetime',
			'order'          => 'ASC',
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'     => 'dish_start_datetime',
					'value'   => time(),
					'compare' => '>=',
					'type'    => 'NUMERIC',
				],
				[
					'relation' => 'OR',
					[ 'key' => 'dish_is_private', 'compare' => 'NOT EXISTS' ],
					[ 'key' => 'dish_is_private', 'value' => '1', 'compare' => '!=' ],
				],
			],
		] );

		if ( empty( $instances ) ) {
			return '<p class="dish-no-results">' . esc_html__( 'No upcoming classes at this time. Check back soon!', 'dish-events' ) . '</p>';
		}

		// Build structured entries — all DB reads happen here, template stays clean.
		$entries = [];
		foreach ( $instances as $instance ) {
			$template_id = (int) get_post_meta( $instance->ID, 'dish_template_id', true );
			$template    = $template_id ? get_post( $template_id ) : null;
			if ( ! $template || 'publish' !== $template->post_status ) {
				continue;
			}

			$format_id = (int) get_post_meta( $template_id, 'dish_format_id', true );
			$format    = $format_id ? get_post( $format_id ) : null;

			// Chef names: guest chef name takes priority over linked dish_chef posts.
			$is_guest_chef   = (bool) get_post_meta( $template_id, 'dish_is_guest_chef', true );
			$guest_chef_name = $is_guest_chef
				? (string) get_post_meta( $template_id, 'dish_guest_chef_name', true )
				: '';

			$chefs = [];
			if ( $is_guest_chef && $guest_chef_name ) {
				// Guest chef — no profile page, name only.
				$chefs[] = [ 'name' => $guest_chef_name, 'url' => '' ];
			} else {
				foreach ( ClassRepository::get_chef_ids( $instance->ID ) as $cid ) {
					$chef_post = get_post( $cid );
					if ( $chef_post ) {
						$chefs[] = [ 'name' => $chef_post->post_title, 'url' => (string) get_permalink( $chef_post->ID ) ];
					}
				}
			}

			$entries[] = [
				'instance'        => $instance,
				'template'        => $template,
				'format'          => $format,
				'chefs'           => $chefs,
				'start'           => (int) get_post_meta( $instance->ID, 'dish_start_datetime', true ),
				'class_url'       => add_query_arg( 'class_id', $instance->ID, get_permalink( $template->ID ) ),
				'menu_items'      => (string) get_post_meta( $template_id, 'dish_menu_items',           true ),
				'dietary_flags'   => (array)  json_decode( get_post_meta( $template_id, 'dish_menu_dietary_flags', true ) ?: '[]', true ),
				'friendly_for'    => (array)  json_decode( get_post_meta( $template_id, 'dish_menu_friendly_for',  true ) ?: '[]', true ),
				'custom_dietary'  => (array)  json_decode( get_post_meta( $template_id, 'dish_menu_custom_dietary',  true ) ?: '[]', true ),
				'custom_friendly' => (array)  json_decode( get_post_meta( $template_id, 'dish_menu_custom_friendly', true ) ?: '[]', true ),
			];
		}

		if ( empty( $entries ) ) {
			return '<p class="dish-no-results">' . esc_html__( 'No upcoming classes at this time. Check back soon!', 'dish-events' ) . '</p>';
		}

		// Deduplicate by template — keep only the next upcoming instance per class.
		// Instances are already sorted ASC by start date so the first hit per
		// template_id is always the nearest future occurrence.
		$seen    = [];
		$entries = array_values( array_filter( $entries, function ( $e ) use ( &$seen ) {
			$tid = $e['template']->ID;
			if ( isset( $seen[ $tid ] ) ) {
				return false;
			}
			$seen[ $tid ] = true;
			return true;
		} ) );

		ob_start();
		include Frontend::locate( 'menus/upcoming.php' );
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode: [dish_menus limit="200" orderby="title"]
	 *
	 * Renders a catalogue of all published class-template menus.
	 * No booking links — suitable for use alongside a third-party booking system.
	 *
	 * If a future dish_class instance exists for a template its start timestamp
	 * is surfaced as read-only "Next class" metadata so visitors know the class
	 * is actively scheduled without needing a bookable link.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_all( $atts = [] ): string {
		$atts = shortcode_atts(
			[ 'limit' => 200, 'orderby' => 'title' ],
			(array) $atts,
			'dish_menus'
		);

		// All published templates, alphabetical by default.
		$templates = get_posts( [
			'post_type'              => 'dish_class_template',
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $atts['limit'],
			'orderby'                => sanitize_key( $atts['orderby'] ),
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		] );

		if ( empty( $templates ) ) {
			return '<p class="dish-no-results">' . esc_html__( 'No menus available at this time. Check back soon!', 'dish-events' ) . '</p>';
		}

		// One query to find the next upcoming instance per template.
		// Ordered ASC so the first hit per template_id is always the nearest
		// future occurrence — meta cache is primed for all instances at once.
		$next_dates = [];
		$upcoming   = get_posts( [
			'post_type'              => 'dish_class',
			'post_status'            => 'publish',
			'posts_per_page'         => 500,
			'orderby'                => 'meta_value_num',
			'meta_key'               => 'dish_start_datetime',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'     => 'dish_start_datetime',
					'value'   => time(),
					'compare' => '>=',
					'type'    => 'NUMERIC',
				],
			],
		] );

		foreach ( $upcoming as $inst ) {
			$tid   = (int) get_post_meta( $inst->ID, 'dish_template_id', true );
			$start = (int) get_post_meta( $inst->ID, 'dish_start_datetime', true );
			if ( $tid && $start && ! isset( $next_dates[ $tid ] ) ) {
				$next_dates[ $tid ] = $start;
			}
		}

		$entries = [];
		foreach ( $templates as $template ) {
			$format_id = (int) get_post_meta( $template->ID, 'dish_format_id', true );
			$format    = $format_id ? get_post( $format_id ) : null;

			$entries[] = [
			'template'        => $template,
			'format'          => $format,
			'template_url'    => (string) get_permalink( $template->ID ),
			'next_date'       => $next_dates[ $template->ID ] ?? null,
			'menu_items'      => (string) get_post_meta( $template->ID, 'dish_menu_items',           true ),
			'dietary_flags'   => (array)  json_decode( get_post_meta( $template->ID, 'dish_menu_dietary_flags', true ) ?: '[]', true ),
			'friendly_for'    => (array)  json_decode( get_post_meta( $template->ID, 'dish_menu_friendly_for',  true ) ?: '[]', true ),
			'custom_dietary'  => (array)  json_decode( get_post_meta( $template->ID, 'dish_menu_custom_dietary',  true ) ?: '[]', true ),
			'custom_friendly' => (array)  json_decode( get_post_meta( $template->ID, 'dish_menu_custom_friendly', true ) ?: '[]', true ),
			];
		}

		ob_start();
		include Frontend::locate( 'menus/all.php' );
		return (string) ob_get_clean();
	}
}
