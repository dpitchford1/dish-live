<?php
/**
 * Page conditional helpers.
 *
 * Utility functions for use in sidebar.php and template conditionals.
 *
 * @package Basecamp\Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check whether the current page is a specific page OR any descendant of it.
 *
 * Works at any depth — children, grandchildren, etc.
 *
 * Usage:
 *   if ( basecamp_is_page_or_child_of( 'about-dish' ) ) { ... }
 *   if ( basecamp_is_page_or_child_of( [ 'about-dish', 'our-story' ] ) ) { ... }
 *
 * @param string|int|array $slugs_or_ids  Page slug(s), ID(s), or title(s) — same as is_page().
 * @return bool
 */
function basecamp_is_page_or_child_of( $slugs_or_ids ): bool {
	if ( ! is_page() ) {
		return false;
	}

	// Exact match first — is_page() handles slug, ID, or title.
	if ( is_page( $slugs_or_ids ) ) {
		return true;
	}

	// Walk up the ancestor chain and check each one.
	$ancestors = get_post_ancestors( get_the_ID() );
	foreach ( $ancestors as $ancestor_id ) {
		if ( is_page( $slugs_or_ids ) ) {
			// Already checked above — use slug/ID comparison directly.
			break;
		}
		$ancestor_slug  = get_post_field( 'post_name', $ancestor_id );
		$ancestor_title = get_post_field( 'post_title', $ancestor_id );

		foreach ( (array) $slugs_or_ids as $needle ) {
			if (
				(string) $needle === (string) $ancestor_id ||
				$needle === $ancestor_slug ||
				$needle === $ancestor_title
			) {
				return true;
			}
		}
	}

	return false;
}
