<?php
/**
 * Theme integration — Basecamp schema filters.
 *
 * Pipes venue data from the dish_settings option into the Basecamp theme's
 * basecamp_schema_* filters, populating the Organisation/LocalBusiness node
 * with studio contact details, address, opening hours, and social links.
 *
 * Also boots the DishSchema extension (Events, Person, ItemList graphs)
 * once the theme's SEO class is confirmed to exist.
 *
 * Kept in the plugin because all data originates from plugin-owned options
 * (dish_settings) and the integration point is plugin-specific. The theme
 * itself has no knowledge of dish_settings.
 *
 * @package Dish\Events\Core
 */

declare( strict_types=1 );

namespace Dish\Events\Core;

final class ThemeIntegration {

	/**
	 * Register the init hook that wires all schema filters.
	 * Called once from Plugin::wire_hooks().
	 */
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_schema_filters' ] );
	}

	/**
	 * Read dish_settings and register basecamp_schema_* filters.
	 * Fires on 'init' so the option is reliably available.
	 */
	public static function register_schema_filters(): void {
		$s = (array) get_option( 'dish_settings', [] );

		// Populate the Organisation node with venue contact data.
		if ( ! empty( $s['studio_phone'] ) ) {
			add_filter( 'basecamp_schema_telephone', fn() => $s['studio_phone'] );
		}
		if ( ! empty( $s['studio_email'] ) ) {
			add_filter( 'basecamp_schema_email', fn() => $s['studio_email'] );
		}

		// Opening hours for LocalBusiness schema (one entry per line in textarea).
		$hours = trim( $s['venue_hours'] ?? '' );
		if ( $hours ) {
			$hours_array = array_values( array_filter( array_map( 'trim', explode( "\n", $hours ) ) ) );
			add_filter( 'basecamp_schema_hours', fn() => count( $hours_array ) === 1 ? $hours_array[0] : $hours_array );
		}

		// PostalAddress for the Organisation node.
		$street = trim( $s['venue_address']     ?? '' );
		$city   = trim( $s['venue_city']        ?? '' );
		$region = trim( $s['venue_province']    ?? '' );
		$postal = trim( $s['venue_postal_code'] ?? '' );
		if ( $street || $city ) {
			add_filter( 'basecamp_schema_address', fn() => array_filter( [
				'@type'           => 'PostalAddress',
				'streetAddress'   => $street,
				'addressLocality' => $city,
				'addressRegion'   => $region,
				'postalCode'      => $postal,
				'addressCountry'  => 'CA',
			] ) );
		}

		// sameAs social links.
		$same_as = array_values( array_filter( [
			$s['studio_website']   ?? '',
			$s['studio_instagram'] ?? '',
			$s['studio_facebook']  ?? '',
		] ) );
		if ( ! empty( $same_as ) ) {
			add_filter( 'basecamp_schema_same_as', fn() => $same_as );
		}

		// Boot the Dish Events schema extension (Events, Person, ItemList graphs).
		if ( class_exists( 'Basecamp\\SEO\\DishSchema' ) ) {
			\Basecamp\SEO\DishSchema::init();
		}
	}
}
