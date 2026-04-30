<?php
/**
 * Google Reviews — server-side fetch and cache.
 *
 * Calls the Google Places API (Details) from PHP and stores the result in a
 * WP transient so zero Google scripts ever touch the browser.
 *
 * Usage:
 *   $reviews = GoogleReviews::get();   // returns array|null
 *   GoogleReviews::refresh();          // force-refresh the transient (also the cron callback)
 *
 * Settings keys (stored in `dish_settings`):
 *   google_reviews_api_key  — server-restricted Places API key
 *   google_reviews_place_id — the Google Place ID for the business
 *
 * @package Dish\Events\Core
 */

declare( strict_types=1 );

namespace Dish\Events\Core;

use Dish\Events\Admin\Settings;

/**
 * Class GoogleReviews
 */
final class GoogleReviews {

	/** Transient key used to cache review data. */
	const TRANSIENT = 'dish_google_reviews';

	/** How long to cache (seconds). 12 hours. */
	const TTL = 12 * HOUR_IN_SECONDS;

	/** WP Cron hook name. */
	const CRON_HOOK = 'dish_refresh_google_reviews';

	/** Google Places Details endpoint. */
	const API_URL = 'https://maps.googleapis.com/maps/api/place/details/json';

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Return cached reviews, fetching from Google if the transient is missing.
	 *
	 * Returns null when no API key / Place ID is configured, or when the
	 * API call fails — callers should handle null gracefully (render nothing).
	 *
	 * @return array<int, array{
	 *   author_name: string,
	 *   rating: int,
	 *   text: string,
	 *   time: int,
	 *   relative_time: string,
	 *   photo_url: string,
	 * }>|null
	 */
	public static function get(): ?array {
		if ( ! self::is_configured() ) {
			return null;
		}

		$cached = get_transient( self::TRANSIENT );

		if ( false !== $cached ) {
			return $cached ?: null;
		}

		return self::refresh();
	}

	/**
	 * Force-fetch from the Google Places API and update the transient.
	 * This is also the WP Cron callback.
	 *
	 * @return array<int, mixed>|null  Parsed reviews, or null on failure.
	 */
	public static function refresh(): ?array {
		if ( ! self::is_configured() ) {
			return null;
		}

		$api_key  = (string) Settings::get( 'google_reviews_api_key' );
		$place_id = (string) Settings::get( 'google_reviews_place_id' );

		$response = wp_remote_get(
			add_query_arg( [
				'place_id' => $place_id,
				'fields'   => 'reviews,rating,user_ratings_total',
				'key'      => $api_key,
				'language' => get_locale(),
			], self::API_URL ),
			[
				'timeout'    => 10,
				'user-agent' => 'Dish-Events/' . DISH_EVENTS_VERSION . '; ' . get_bloginfo( 'url' ),
			]
		);

		if ( is_wp_error( $response ) ) {
			// Store an empty array so we don't hammer the API on every page load
			// while the error persists — we'll retry on the next cron cycle.
			set_transient( self::TRANSIENT, [], self::TTL );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if (
			! is_array( $body ) ||
			( $body['status'] ?? '' ) !== 'OK' ||
			empty( $body['result']['reviews'] )
		) {
			set_transient( self::TRANSIENT, [], self::TTL );
			return null;
		}

		$reviews = self::parse( $body['result']['reviews'] );

		set_transient( self::TRANSIENT, $reviews, self::TTL );

		return $reviews;
	}

	/**
	 * Schedule the cron job on plugin activation / settings save.
	 * Safe to call repeatedly — no-ops if already scheduled.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'twicedaily', self::CRON_HOOK );
		}
	}

	/**
	 * Remove the cron job. Called on plugin deactivation.
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Bust the transient so the next call to get() triggers a live fetch.
	 * Useful when the Place ID or API key is updated in settings.
	 */
	public static function bust_cache(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Instance wrapper for bust_cache() — required so the Loader can register
	 * this as an object-method hook via add_action().
	 *
	 * @internal
	 */
	public function bust_cache_hook(): void {
		self::bust_cache();
	}

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Returns true only when both the API key and Place ID are non-empty.
	 */
	private static function is_configured(): bool {
		return
			! empty( Settings::get( 'google_reviews_api_key' ) ) &&
			! empty( Settings::get( 'google_reviews_place_id' ) );
	}

	/**
	 * Normalize raw Google review objects into our simpler schema.
	 *
	 * @param  array<int, mixed> $raw  Raw `reviews` array from Places API.
	 * @return array<int, array{
	 *   author_name: string,
	 *   rating: int,
	 *   text: string,
	 *   time: int,
	 *   relative_time: string,
	 *   photo_url: string,
	 * }>
	 */
	private static function parse( array $raw ): array {
		$reviews = [];

		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$reviews[] = [
				'author_name'   => sanitize_text_field( $item['author_name']            ?? '' ),
				'rating'        => (int) ( $item['rating']                              ?? 0 ),
				'text'          => sanitize_textarea_field( $item['text']               ?? '' ),
				'time'          => (int) ( $item['time']                                ?? 0 ),
				'relative_time' => sanitize_text_field( $item['relative_time_description'] ?? '' ),
				'photo_url'     => esc_url_raw( $item['profile_photo_url']              ?? '' ),
			];
		}

		// Sort: highest rating first, then most recent.
		usort( $reviews, static function ( array $a, array $b ): int {
			if ( $b['rating'] !== $a['rating'] ) {
				return $b['rating'] <=> $a['rating'];
			}
			return $b['time'] <=> $a['time'];
		} );

		return $reviews;
	}
}
