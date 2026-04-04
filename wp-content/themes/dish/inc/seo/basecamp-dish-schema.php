<?php

declare(strict_types=1);
/**
 * Dish Events — Schema.org JSON-LD extension for Basecamp\SEO\Schema.
 *
 * Hooks into the 'basecamp_schema_graphs' filter (added to Schema::output)
 * to inject structured-data graphs for Dish Events CPT contexts:
 *
 *   dish_class_template single
 *     → one schema:Event node per upcoming public instance, each with:
 *         - EventScheduled status
 *         - startDate / endDate
 *         - location (Place with address + geo)
 *         - organizer (@id reference to Organization)
 *         - performer (Person nodes for assigned chefs)
 *         - offers (Offer with price, currency, availability)
 *         - image (template featured image)
 *
 *   dish_chef single
 *     → schema:Person with name, job title, image, url, sameAs (social links)
 *
 *   dish_chef archive
 *     → schema:ItemList of Person references
 *
 * Venue data is read from dish_settings (populated in the Dish admin).
 * The Organization graph is handled by the core Schema class; this class
 * only appends extra graphs to avoid duplication.
 *
 * @package basecamp
 */

namespace Basecamp\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Data\ChefRepository;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\ClassTemplateRepository;
use Dish\Events\Helpers\MoneyHelper;

final class DishSchema {

	/**
	 * Register the graph filter.
	 */
	public static function init(): void {
		add_filter( 'basecamp_schema_graphs', [ __CLASS__, 'inject' ], 10, 1 );
	}

	/**
	 * Inject Dish Events graph nodes into the Schema output pipeline.
	 *
	 * @param  array[] $graphs  Existing graph arrays from Schema::output.
	 * @return array[]
	 */
	public static function inject( array $graphs ): array {
		if ( is_singular( 'dish_class_template' ) ) {
			foreach ( self::event_graphs( get_the_ID() ) as $graph ) {
				$graphs[] = $graph;
			}
		} elseif ( is_singular( 'dish_chef' ) ) {
			$graphs[] = self::person_graph( get_the_ID() );
		} elseif ( is_post_type_archive( 'dish_chef' ) ) {
			$list = self::chef_list_graph();
			if ( $list ) {
				$graphs[] = $list;
			}
		}

		return array_filter( $graphs );
	}

	// -------------------------------------------------------------------------
	// Event graphs — one per upcoming public class instance
	// -------------------------------------------------------------------------

	/**
	 * Build an array of schema:Event nodes for a class template.
	 *
	 * @param  int $template_id  dish_class_template post ID.
	 * @return array[]
	 */
	private static function event_graphs( int $template_id ): array {
		// Fetch up to 6 upcoming public instances — enough to populate Google's
		// Events carousel without bloating the <head> on large recurring series.
		$instances = ClassTemplateRepository::get_upcoming_instances( $template_id, 6 );
		if ( empty( $instances ) ) {
			return [];
		}

		$settings     = (array) get_option( 'dish_settings', [] );
		$venue_place  = self::venue_place( $settings );
		$ticket_type  = ClassTemplateRepository::get_ticket_type( $template_id );
		$template_url = get_permalink( $template_id );
		$description  = get_the_excerpt( $template_id ) ?: '';
		$image_url    = self::post_image_url( $template_id );
		$format_id    = (int) ClassTemplateRepository::get_meta( $template_id, 'dish_format_id', 0 );
		$format_name  = $format_id ? get_the_title( $format_id ) : '';

		$graphs = [];

		foreach ( $instances as $instance ) {
			// Skip private instances.
			if ( (string) ClassRepository::get_meta( $instance->ID, 'dish_is_private', '' ) === '1' ) {
				continue;
			}

			$start_ts = (int) ClassRepository::get_meta( $instance->ID, 'dish_start_datetime', 0 );
			$end_ts   = (int) ClassRepository::get_meta( $instance->ID, 'dish_end_datetime',   0 );

			if ( ! $start_ts ) {
				continue;
			}

			$event = [
				'@context'    => 'https://schema.org',
				'@type'       => 'Event',
				'@id'         => $template_url . '#event-' . $instance->ID,
				'name'        => get_the_title( $template_id ),
				'eventStatus' => 'https://schema.org/EventScheduled',
				'startDate'   => gmdate( 'c', $start_ts ),
				'url'         => $template_url,
				'organizer'   => [ '@id' => home_url( '/#organization' ) ],
			];

			if ( $end_ts ) {
				$event['endDate'] = gmdate( 'c', $end_ts );
			}

			if ( $description ) {
				$event['description'] = $description;
			}

			if ( $image_url ) {
				$event['image'] = $image_url;
			}

			if ( $format_name ) {
				$event['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
				$event['keywords']            = $format_name;
			}

			if ( $venue_place ) {
				$event['location'] = $venue_place;
			}

			// Offer.
			if ( $ticket_type ) {
				$capacity = (int) $ticket_type->capacity;
				$booked   = ClassRepository::get_booked_count( $instance->ID );
				$avail    = ( $capacity > 0 && ( $capacity - $booked ) <= 0 )
					? 'https://schema.org/SoldOut'
					: 'https://schema.org/InStock';

				$event['offers'] = [
					'@type'         => 'Offer',
					'price'         => number_format( (int) $ticket_type->price_cents / 100, 2, '.', '' ),
					'priceCurrency' => strtoupper( $settings['currency'] ?? 'CAD' ),
					'availability'  => $avail,
					'url'           => $template_url,
					'validFrom'     => gmdate( 'c', strtotime( get_the_date( 'c', $template_id ) ) ),
				];
			}

			// Performers — assigned chefs.
			$chef_ids = ClassRepository::get_chef_ids( $instance->ID );
			if ( ! empty( $chef_ids ) ) {
				$performers = [];
				foreach ( $chef_ids as $chef_id ) {
					$chef = get_post( $chef_id );
					if ( $chef && 'dish_chef' === $chef->post_type && 'publish' === $chef->post_status ) {
						$performers[] = [
							'@type' => 'Person',
							'name'  => $chef->post_title,
							'url'   => get_permalink( $chef_id ),
						];
					}
				}
				if ( ! empty( $performers ) ) {
					$event['performer'] = count( $performers ) === 1 ? $performers[0] : $performers;
				}
			}

			$graphs[] = apply_filters( 'dish_schema_event', $event, $instance, $template_id );
		}

		return $graphs;
	}

	// -------------------------------------------------------------------------
	// Person graph — chef single
	// -------------------------------------------------------------------------

	/**
	 * Build a schema:Person node for a chef.
	 *
	 * @param  int $chef_id  dish_chef post ID.
	 * @return array
	 */
	private static function person_graph( int $chef_id ): array {
		$role      = (string) ChefRepository::get_meta( $chef_id, 'dish_chef_role' );
		$instagram = (string) ChefRepository::get_meta( $chef_id, 'dish_chef_instagram' );
		$linkedin  = (string) ChefRepository::get_meta( $chef_id, 'dish_chef_linkedin' );
		$tiktok    = (string) ChefRepository::get_meta( $chef_id, 'dish_chef_tiktok' );
		$website   = (string) ChefRepository::get_meta( $chef_id, 'dish_chef_website' );

		$person = [
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'@id'      => get_permalink( $chef_id ) . '#person',
			'name'     => get_the_title( $chef_id ),
			'url'      => get_permalink( $chef_id ),
			'worksFor' => [ '@id' => home_url( '/#organization' ) ],
		];

		if ( $role ) {
			$person['jobTitle'] = $role;
		}

		$image_url = self::post_image_url( $chef_id );
		if ( $image_url ) {
			$person['image'] = $image_url;
		}

		$same_as = array_values( array_filter( [ $website, $instagram, $linkedin, $tiktok ] ) );
		if ( ! empty( $same_as ) ) {
			$person['sameAs'] = $same_as;
		}

		return apply_filters( 'dish_schema_person', $person, $chef_id );
	}

	// -------------------------------------------------------------------------
	// ItemList graph — chef archive
	// -------------------------------------------------------------------------

	/**
	 * Build a schema:ItemList of chefs for the archive page.
	 *
	 * @return array|null
	 */
	private static function chef_list_graph(): ?array {
		$chefs = get_posts( [
			'post_type'      => 'dish_chef',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		] );

		if ( empty( $chefs ) ) {
			return null;
		}

		$items = [];
		$pos   = 1;
		foreach ( $chefs as $chef_id ) {
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $pos++,
				'item'     => [
					'@type' => 'Person',
					'@id'   => get_permalink( $chef_id ) . '#person',
					'name'  => get_the_title( $chef_id ),
					'url'   => get_permalink( $chef_id ),
				],
			];
		}

		return [
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => __( 'Our Chefs', 'dish-events' ),
			'itemListElement' => $items,
		];
	}

	// -------------------------------------------------------------------------
	// Shared helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a schema:Place node from dish_settings venue data.
	 *
	 * @param  array $settings  Dish settings option array.
	 * @return array|null
	 */
	private static function venue_place( array $settings ): ?array {
		$name    = trim( $settings['venue_name']    ?? '' );
		$street  = trim( $settings['venue_address'] ?? '' );
		$city    = trim( $settings['venue_city']    ?? '' );
		$region  = trim( $settings['venue_province'] ?? '' );
		$postal  = trim( $settings['venue_postal_code'] ?? '' );
		$lat     = trim( $settings['venue_lat'] ?? '' );
		$lng     = trim( $settings['venue_lng'] ?? '' );
		$map_url = trim( $settings['venue_google_maps_url'] ?? '' );

		if ( ! $name && ! $street ) {
			return null;
		}

		$place = [
			'@type' => 'Place',
			'name'  => $name ?: get_bloginfo( 'name' ),
		];

		if ( $street || $city ) {
			$place['address'] = array_filter( [
				'@type'           => 'PostalAddress',
				'streetAddress'   => $street,
				'addressLocality' => $city,
				'addressRegion'   => $region,
				'postalCode'      => $postal,
				'addressCountry'  => 'CA',
			] );
		}

		if ( $lat && $lng ) {
			$place['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			];
		}

		if ( $map_url ) {
			$place['hasMap'] = $map_url;
		}

		return $place;
	}

	/**
	 * Return the full-size featured image URL for a post, or empty string.
	 *
	 * @param  int $post_id
	 * @return string
	 */
	private static function post_image_url( int $post_id ): string {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumb_id ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $thumb_id, 'full' );
		return $src ? (string) $src[0] : '';
	}
}
