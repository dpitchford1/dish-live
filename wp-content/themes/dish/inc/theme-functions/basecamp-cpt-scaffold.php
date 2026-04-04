<?php

declare(strict_types=1);
/**
 * Custom Post Type Scaffold
 *
 * A ready-to-use, fully commented CPT registration template.
 * Duplicate this file (or copy the sections you need) to register
 * new post types and taxonomies for a project.
 *
 * HOW TO ACTIVATE
 * ---------------
 * 1. Copy this file or its contents into a new file, e.g.
 *    inc/theme-functions/basecamp-cpt-portfolio.php
 * 2. Rename every occurrence of:
 *      'basecamp_portfolio'  → your post type key  (max 20 chars, lowercase, underscores)
 *      'basecamp_portfolio_category' → your taxonomy key
 *      'Portfolio' / 'Portfolios' → your singular / plural labels
 * 3. Un-comment the require_once line in functions.php:
 *      // require_once get_template_directory() . '/inc/theme-functions/basecamp-cpt-scaffold.php';
 * 4. Flush rewrite rules: WP Admin → Settings → Permalinks → Save.
 *
 * This file is intentionally NOT loaded by default. Nothing below runs
 * unless you require_once it and call Basecamp_CPT_Scaffold::init().
 *
 * @package basecamp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// CPT Registration
// =============================================================================

class Basecamp_CPT_Scaffold {

	/**
	 * Register hooks.
	 * Call this from functions.php after requiring this file.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_post_type' ], 0 );
		add_action( 'init', [ __CLASS__, 'register_taxonomy' ],  0 );
	}

	/**
	 * Register the post type.
	 *
	 * Slug:        basecamp_portfolio
	 * Public URL:  /portfolio/%post_name%/
	 * Admin menu:  Portfolio
	 */
	public static function register_post_type() {

		$labels = [
			'name'                  => _x( 'Portfolios', 'post type general name', 'basecamp' ),
			'singular_name'         => _x( 'Portfolio',  'post type singular name', 'basecamp' ),
			'menu_name'             => _x( 'Portfolio',  'admin menu label', 'basecamp' ),
			'name_admin_bar'        => _x( 'Portfolio',  'add new on toolbar', 'basecamp' ),
			'add_new'               => __( 'Add New', 'basecamp' ),
			'add_new_item'          => __( 'Add New Portfolio Item', 'basecamp' ),
			'new_item'              => __( 'New Portfolio Item', 'basecamp' ),
			'edit_item'             => __( 'Edit Portfolio Item', 'basecamp' ),
			'view_item'             => __( 'View Portfolio Item', 'basecamp' ),
			'all_items'             => __( 'All Portfolio Items', 'basecamp' ),
			'search_items'          => __( 'Search Portfolio', 'basecamp' ),
			'parent_item_colon'     => __( 'Parent Item:', 'basecamp' ),
			'not_found'             => __( 'No portfolio items found.', 'basecamp' ),
			'not_found_in_trash'    => __( 'No portfolio items found in Trash.', 'basecamp' ),
			'featured_image'        => __( 'Featured Image', 'basecamp' ),
			'set_featured_image'    => __( 'Set featured image', 'basecamp' ),
			'remove_featured_image' => __( 'Remove featured image', 'basecamp' ),
			'use_featured_image'    => __( 'Use as featured image', 'basecamp' ),
			'archives'              => __( 'Portfolio Archives', 'basecamp' ),
			'insert_into_item'      => __( 'Insert into portfolio item', 'basecamp' ),
			'uploaded_to_this_item' => __( 'Uploaded to this portfolio item', 'basecamp' ),
			'items_list'            => __( 'Portfolio items list', 'basecamp' ),
			'items_list_navigation' => __( 'Portfolio items list navigation', 'basecamp' ),
			'filter_items_list'     => __( 'Filter portfolio items list', 'basecamp' ),
		];

		$args = apply_filters( 'basecamp_cpt_portfolio_args', [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,   // Enables block editor + REST API access
			'query_var'          => true,
			'rewrite'            => [ 'slug' => 'portfolio', 'with_front' => false ],
			'capability_type'    => 'post',
			'has_archive'        => true,   // Enables /portfolio/ archive page
			'hierarchical'       => false,  // Set true for page-like parent/child structure
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-portfolio',
			'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes' ],
		] );

		register_post_type( 'basecamp_portfolio', $args );
	}

	/**
	 * Register a hierarchical taxonomy (category-style) for the post type.
	 *
	 * Slug:        basecamp_portfolio_category
	 * Public URL:  /portfolio-category/%term_slug%/
	 */
	public static function register_taxonomy() {

		$labels = [
			'name'              => _x( 'Portfolio Categories', 'taxonomy general name', 'basecamp' ),
			'singular_name'     => _x( 'Portfolio Category',  'taxonomy singular name', 'basecamp' ),
			'search_items'      => __( 'Search Categories', 'basecamp' ),
			'all_items'         => __( 'All Categories', 'basecamp' ),
			'parent_item'       => __( 'Parent Category', 'basecamp' ),
			'parent_item_colon' => __( 'Parent Category:', 'basecamp' ),
			'edit_item'         => __( 'Edit Category', 'basecamp' ),
			'update_item'       => __( 'Update Category', 'basecamp' ),
			'add_new_item'      => __( 'Add New Category', 'basecamp' ),
			'new_item_name'     => __( 'New Category Name', 'basecamp' ),
			'menu_name'         => __( 'Categories', 'basecamp' ),
			'not_found'         => __( 'No categories found.', 'basecamp' ),
		];

		$args = apply_filters( 'basecamp_taxonomy_portfolio_category_args', [
			'hierarchical'      => true,    // Set false for tag-style (flat) taxonomy
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'portfolio-category', 'with_front' => false ],
		] );

		register_taxonomy( 'basecamp_portfolio_category', [ 'basecamp_portfolio' ], $args );
	}
}

// =============================================================================
// Template hierarchy helpers
// =============================================================================

/*
 * WordPress will automatically look for these template files (in order):
 *
 *   Single post:
 *     single-basecamp_portfolio.php  → wp-content/themes/basecamp/
 *     single.php
 *     index.php
 *
 *   Archive:
 *     archive-basecamp_portfolio.php → wp-content/themes/basecamp/
 *     archive.php
 *     index.php
 *
 *   Taxonomy archive:
 *     taxonomy-basecamp_portfolio_category-{term-slug}.php
 *     taxonomy-basecamp_portfolio_category.php
 *     taxonomy.php
 *     archive.php
 *     index.php
 *
 * Create those files in the theme root when you need custom layouts.
 * Template parts go in template-parts/<area>/ and are called via get_template_part().
 */

// =============================================================================
// Query helpers
// =============================================================================

/*
 * Retrieve portfolio posts.
 *
 * Usage:
 *
 *   $posts = basecamp_get_portfolio_posts( [ 'posts_per_page' => 6 ] );
 *   foreach ( $posts as $post ) {
 *       setup_postdata( $post );
 *       // ... output ...
 *   }
 *   wp_reset_postdata();
 *
 * The $args array is merged over the defaults and passed through a filter
 * so individual templates or child themes can override the query.
 */
function basecamp_get_portfolio_posts( array $args = [] ): array {
	$defaults = [
		'post_type'      => 'basecamp_portfolio',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
		'no_found_rows'  => true, // Skip pagination SQL — faster when you don't need it
	];

	$query_args = apply_filters( 'basecamp_portfolio_query_args', array_merge( $defaults, $args ) );

	return get_posts( $query_args );
}

// =============================================================================
// Activation note
// =============================================================================
/*
 * To activate, add to functions.php (inside the Theme Functions section):
 *
 *   require_once get_template_directory() . '/inc/theme-functions/basecamp-cpt-scaffold.php';
 *   Basecamp_CPT_Scaffold::init();
 *
 * Then flush rewrite rules: WP Admin → Settings → Permalinks → Save Changes.
 */
