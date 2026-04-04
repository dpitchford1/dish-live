<?php

declare(strict_types=1);
/**
 * Dish Events — hierarchical title extension for Basecamp\SEO\TitleManager.
 *
 * Produces breadcrumb-style browser/SEO titles for all dish CPT contexts:
 *
 *   dish_class_template:  German Beer Hall › Hands On › Classes › {Site}
 *   dish_format:          Hands On › Classes › {Site}
 *   dish_chef (single):   Catharina Mostazo › Chef › {Site}
 *   dish_chef (archive):  Meet the Team › Chefs › {Site}
 *   dish_class:           {template title} › {format title} › Classes › {Site}
 *
 * Registered in TitleManager::$extensions (basecamp-title-functions.php) and
 * also applied to og:title / twitter:title via the 'basecamp_og_title' filter
 * added to SocialMeta::add_social_meta() in basecamp-social-meta-functions.php.
 *
 * @package basecamp
 */

namespace Basecamp\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TitleDishEvents {

	/** Breadcrumb separator — standard typographic right-angle quotation mark. */
	const SEP = ' › ';

	/**
	 * Return a formatted hierarchical title for dish CPT contexts.
	 * Returns null for all other contexts so the next extension can run.
	 *
	 * @param  string|null $title  Title passed in from TitleManager (may be empty).
	 * @return string|null
	 */
	public static function maybe_title( $title ) {
		$site = get_bloginfo( 'name' );

		// ── dish_class_template single ────────────────────────────────────────
		if ( is_singular( 'dish_class_template' ) ) {
			$template_title = get_the_title();
			$format_id      = (int) \Dish\Events\Data\ClassTemplateRepository::get_meta( get_the_ID(), 'dish_format_id', 0 );
			$format_title   = $format_id ? get_the_title( $format_id ) : '';

			return implode(
				self::SEP,
				array_filter( [ $template_title, $format_title, __( 'Classes', 'dish-events' ), $site ] )
			);
		}

		// ── dish_format single ────────────────────────────────────────────────
		if ( is_singular( 'dish_format' ) ) {
			return implode(
				self::SEP,
				array_filter( [ get_the_title(), __( 'Classes', 'dish-events' ), $site ] )
			);
		}

		// ── dish_chef archive ─────────────────────────────────────────────────
		if ( is_post_type_archive( 'dish_chef' ) ) {
			return implode(
				self::SEP,
				[ __( 'Meet the Team', 'dish-events' ), __( 'Chefs', 'dish-events' ), $site ]
			);
		}

		// ── dish_chef single ──────────────────────────────────────────────────
		if ( is_singular( 'dish_chef' ) ) {
			return implode(
				self::SEP,
				array_filter( [ get_the_title(), __( 'Chef', 'dish-events' ), $site ] )
			);
		}

		// ── dish_class single (non-public; handles edge cases) ────────────────
		if ( is_singular( 'dish_class' ) ) {
			$template_id    = (int) get_post_meta( get_the_ID(), 'dish_template_id', true );
			$template_title = $template_id ? get_the_title( $template_id ) : get_the_title();
			$format_id      = $template_id ? (int) get_post_meta( $template_id, 'dish_format_id', true ) : 0;
			$format_title   = $format_id ? get_the_title( $format_id ) : '';

			return implode(
				self::SEP,
				array_filter( [ $template_title, $format_title, __( 'Classes', 'dish-events' ), $site ] )
			);
		}

		return null;
	}

	/**
	 * Filter callback for 'basecamp_og_title'.
	 *
	 * Replaces the default og:title / twitter:title with the same hierarchical
	 * string so social sharing cards match the browser tab title.
	 *
	 * @param  string $title  Title built by SocialMeta (may already include site name).
	 * @return string
	 */
	public static function filter_og_title( string $title ): string {
		$result = static::maybe_title( $title );
		return $result ?? $title;
	}
}

// Wire the OG title filter.
add_filter( 'basecamp_og_title', [ 'Basecamp\\SEO\\TitleDishEvents', 'filter_og_title' ] );
