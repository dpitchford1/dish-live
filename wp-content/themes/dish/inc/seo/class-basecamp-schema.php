<?php

declare(strict_types=1);
/**
 * Basecamp Schema — JSON-LD Structured Data
 *
 * Outputs JSON-LD structured data for Organization, Article, and
 * BreadcrumbList contexts. Defers to Yoast / Rank Math
 * if either plugin is active.
 *
 * @package basecamp
 */

namespace Basecamp\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {

	/**
	 * Register hooks.
	 */
	public static function init() {
		if ( class_exists( 'WPSEO_Frontend' ) || class_exists( 'RankMath' ) ) {
			return;
		}
		add_action( 'wp_head', [ __CLASS__, 'output' ], 6 );
	}

	/**
	 * Determine context and output all relevant JSON-LD blocks.
	 */
	public static function output() {
		$graphs = [];

		// Organization is always output — it anchors all other graph nodes.
		$graphs[] = self::organization();

		if ( is_singular( 'post' ) ) {
			$graphs[] = self::article();
			$graphs[] = self::breadcrumb();
		} elseif ( is_category() || is_tag() || is_tax() || is_post_type_archive() ) {
			$graphs[] = self::breadcrumb();
		}

		// Allow plugins / extensions to inject additional graph nodes.
		$graphs = apply_filters( 'basecamp_schema_graphs', $graphs );

		foreach ( array_filter( $graphs ) as $graph ) {
			echo '<script type="application/ld+json">'
				. wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
				. '</script>' . PHP_EOL;
		}
	}

	// -------------------------------------------------------------------------
	// Graph builders
	// -------------------------------------------------------------------------

	/**
	 * Organization schema — used as anchor node for all other graphs.
	 * Business details are filterable so they can be updated without
	 * touching this file.
	 *
	 * @return array
	 */
	protected static function organization() {
		$logo_url = '';
		$logo_id  = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_src = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $logo_src ) {
				$logo_url = $logo_src[0];
			}
		}

		$org = [
			'@context' => 'https://schema.org',
			'@type'    => 'LocalBusiness',
			'@id'      => home_url( '/#organization' ),
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
		];

		// Optional contact / social fields — configure via filters in functions.php or a child theme.
		$email   = apply_filters( 'basecamp_schema_email', '' );
		$phone   = apply_filters( 'basecamp_schema_telephone', '' );
		$hours   = apply_filters( 'basecamp_schema_hours', '' );
		$address = apply_filters( 'basecamp_schema_address', [] );
		$same_as = apply_filters( 'basecamp_schema_same_as', [] );

		if ( $email )   { $org['email']        = $email; }
		if ( $phone )   { $org['telephone']    = $phone; }
		if ( $hours )   { $org['openingHours'] = $hours; }
		if ( $address ) { $org['address']      = $address; }
		if ( $same_as ) { $org['sameAs']       = $same_as; }

		if ( $logo_url ) {
			$org['logo'] = [
				'@type' => 'ImageObject',
				'url'   => $logo_url,
			];
		}

		return apply_filters( 'basecamp_schema_organization', $org );
	}

	/**
	 * Article schema for news posts.
	 *
	 * @return array|null
	 */
	protected static function article() {
		global $post;
		if ( ! $post ) {
			return null;
		}

		$image_url   = '';
		$thumbnail   = get_post_thumbnail_id( $post->ID );
		if ( $thumbnail ) {
			$img = wp_get_attachment_image_src( $thumbnail, 'full' );
			if ( $img ) {
				$image_url = $img[0];
			}
		}

		$description = '';
		if ( has_excerpt( $post->ID ) ) {
			$description = strip_tags( get_the_excerpt( $post->ID ) );
		} else {
			$description = wp_trim_words(
				strip_tags( strip_shortcodes( $post->post_content ) ),
				30,
				'...'
			);
		}

		// Use metabox author when set (reprinted articles), fall back to WP post author
		$meta_author     = get_post_meta( $post->ID, '_basecamp_news_author', true );
		$meta_author_url = get_post_meta( $post->ID, '_basecamp_news_author_url', true );
		$author_name     = $meta_author ?: get_the_author_meta( 'display_name', $post->post_author );

		$author_node = [ '@type' => 'Person', 'name' => $author_name ];
		if ( $meta_author_url ) {
			$author_node['url'] = $meta_author_url;
		}

		// Use metabox publication as the publishing organisation when set
		$meta_publication = get_post_meta( $post->ID, '_basecamp_news_publication', true );

		$article = [
			'@context'      => 'https://schema.org',
			'@type'         => 'Article',
			'headline'      => get_the_title( $post ),
			'url'           => get_permalink( $post ),
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
			'author'        => $author_node,
			'publisher'     => [
				'@id' => home_url( '/#organization' ),
			],
		];

		if ( $meta_publication ) {
			$article['sourceOrganization'] = [
				'@type' => 'Organization',
				'name'  => $meta_publication,
			];

			$meta_source_url = get_post_meta( $post->ID, '_basecamp_news_source_url', true );
			if ( $meta_source_url ) {
				$article['sourceOrganization']['url'] = $meta_source_url;
			}
		}

		if ( $image_url ) {
			$article['image'] = $image_url;
		}

		if ( $description ) {
			$article['description'] = $description;
		}

		return apply_filters( 'basecamp_schema_article', $article, $post );
	}

	/**
	 * BreadcrumbList schema.
	 * Returns null when only the home node would be present (no value added).
	 *
	 * @return array|null
	 */
	protected static function breadcrumb() {
		$items    = [];
		$position = 1;

		$items[] = [
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => __( 'Home', 'basecamp' ),
			'item'     => home_url( '/' ),
		];

		if ( is_singular( 'post' ) ) {
			$categories = get_the_category();
			if ( $categories ) {
				$cat     = $categories[0];
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => $cat->name,
					'item'     => get_category_link( $cat->term_id ),
				];
			}
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => get_the_title(),
				'item'     => get_permalink(),
			];
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term    = get_queried_object();
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $term->name,
				'item'     => get_term_link( $term ),
			];
		} elseif ( is_post_type_archive() ) {
			$post_type_obj = get_post_type_object( get_query_var( 'post_type' ) );
			$archive_link  = get_post_type_archive_link( get_query_var( 'post_type' ) );
			if ( $post_type_obj && $archive_link ) {
				$items[] = [
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => $post_type_obj->labels->name,
					'item'     => $archive_link,
				];
			}
		}

		if ( count( $items ) <= 1 ) {
			return null;
		}

		return apply_filters( 'basecamp_schema_breadcrumb', [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		] );
	}

}

Schema::init();
