<?php
/**
 * Registers the dish_format custom post type.
 *
 * dish_format is the "class category" — e.g. "Hands On", "Demonstration".
 * Each format gets a full WP page at /{class_format_slug}/{format-slug}/
 * (e.g. /classes/hands-on/) with title, block editor content, excerpt,
 * featured image, and full SEO support from the Basecamp theme.
 *
 * URL hierarchy (complete picture):
 *   /classes/hands-on/                       ← dish_format single post
 *   /classes/hands-on/german-beer-garden/    ← dish_class_template (Phase 2.5)
 *
 * The nested template URL is wired in Phase 2.5 via a custom rewrite rule
 * and a post_type_link filter on dish_class_template.
 *
 * @package Dish\Events\CPT
 */

declare( strict_types=1 );

namespace Dish\Events\CPT;

/**
 * Class FormatPost
 */
final class FormatPost {

	/**
	 * Register the dish_format CPT.
	 * Hooked to 'init'.
	 */
	public function register(): void {
		$settings = (array) get_option( 'dish_settings', [] );
		$raw_slug = $settings['class_format_slug'] ?? 'classes/formats';
		$slug     = implode( '/', array_map( 'sanitize_title', explode( '/', $raw_slug ) ) );

		$labels = [
			'name'                  => __( 'Formats',                       'dish-events' ),
			'singular_name'         => __( 'Format',                        'dish-events' ),
			'add_new'               => __( 'Add New',                       'dish-events' ),
			'add_new_item'          => __( 'Add New Format',                'dish-events' ),
			'edit_item'             => __( 'Edit Format',                   'dish-events' ),
			'new_item'              => __( 'New Format',                    'dish-events' ),
			'view_item'             => __( 'View Format',                   'dish-events' ),
			'search_items'          => __( 'Search Formats',                'dish-events' ),
			'not_found'             => __( 'No formats found.',             'dish-events' ),
			'not_found_in_trash'    => __( 'No formats found in trash.',    'dish-events' ),
			'all_items'             => __( 'Formats',                       'dish-events' ),
			'archives'              => __( 'Format Archives',               'dish-events' ),
			'menu_name'             => __( 'Formats',                       'dish-events' ),
			'name_admin_bar'        => __( 'Format',                        'dish-events' ),
		];

		register_post_type( 'dish_format', [
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=dish_class',  // Under Dish Events menu.
			'show_in_nav_menus'   => true,
			'show_in_rest'        => false,
			'rewrite'             => [ 'slug' => $slug, 'with_front' => false ],
			'query_var'           => true,
			'hierarchical'        => false,
			'capability_type'     => 'post',
			'menu_icon'           => 'dashicons-category',
			'supports'            => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ],
			'has_archive'         => $slug,  // /classes/ → dish_format archive.
		] );
	}
}
