<?php
/**
 * Registers the dish_chef Custom Post Type.
 *
 * Chefs are the instructors who lead classes. This CPT provides public
 * single-profile pages and is managed entirely in the admin by the studio owner.
 *
 * Appears in the admin menu under Dish Events (dish_class parent menu) as "Chefs".
 *
 * @package Dish\Events\CPT
 */

declare( strict_types=1 );

namespace Dish\Events\CPT;

/**
 * Class ChefPost
 */
final class ChefPost {

	/**
	 * Register the CPT.
	 * Hooked to 'init'.
	 */
	public function register(): void {
		$this->register_post_type();
	}

	// -------------------------------------------------------------------------
	// Post Type
	// -------------------------------------------------------------------------

	private function register_post_type(): void {
		$settings = (array) get_option( 'dish_settings', [] );
		$slug     = sanitize_title( $settings['chef_slug'] ?? 'chef' );

		$labels = [
			'name'                  => __( 'Chefs',                     'dish-events' ),
			'singular_name'         => __( 'Chef',                      'dish-events' ),
			'add_new'               => __( 'Add New',                   'dish-events' ),
			'add_new_item'          => __( 'Add New Chef',              'dish-events' ),
			'edit_item'             => __( 'Edit Chef',                 'dish-events' ),
			'new_item'              => __( 'New Chef',                  'dish-events' ),
			'view_item'             => __( 'View Chef',                 'dish-events' ),
			'view_items'            => __( 'View Chefs',                'dish-events' ),
			'search_items'          => __( 'Search Chefs',              'dish-events' ),
			'not_found'             => __( 'No chefs found.',           'dish-events' ),
			'not_found_in_trash'    => __( 'No chefs found in Trash.',  'dish-events' ),
			'all_items'             => __( 'Chefs',                     'dish-events' ),
			'archives'              => __( 'Chef Profiles',             'dish-events' ),
			'attributes'            => __( 'Chef Attributes',           'dish-events' ),
			'insert_into_item'      => __( 'Insert into chef profile',  'dish-events' ),
			'uploaded_to_this_item' => __( 'Uploaded to this chef',     'dish-events' ),
			'menu_name'             => __( 'Chefs',                     'dish-events' ),
			'name_admin_bar'        => __( 'Chef',                      'dish-events' ),
		];

		register_post_type( 'dish_chef', [
			'labels'             => $labels,
			'description'        => __( 'Chef and instructor profiles.', 'dish-events' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=dish_class', // Under Dish Events menu.
			'show_in_nav_menus'  => true,
			'show_in_rest'       => false,
			'has_archive'        => true,               // /chef/ archive; also available as [dish_chefs] shortcode.
			'hierarchical'       => false,
			'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ],
			'rewrite'            => [ 'slug' => $slug, 'with_front' => false ],
			'capability_type'    => 'post',
			'query_var'          => true,
			'delete_with_user'   => false,
		] );
	}
}
