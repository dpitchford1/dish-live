<?php
/**
 * Registers the dish_class Custom Post Type and its post statuses.
 *
 * dish_class is a non-public dated instance. It has no frontend URL — all
 * public-facing content lives on dish_class_template. Instances are created
 * manually or via RecurrenceManager and are referenced by bookings.
 *
 * Post statuses registered here are specific to class instances:
 *   - dish_expired   : past the end date, no longer bookable
 *   - dish_cancelled : manually cancelled by admin
 *
 * Notes on capability_type:
 *   'post' is used intentionally — this is a single-admin install (owner only).
 *   Using custom capability types would require additional role grants with no
 *   practical benefit. Upgrade to custom caps if multi-user roles are introduced.
 *
 * @package Dish\Events\CPT
 */

declare( strict_types=1 );

namespace Dish\Events\CPT;

/**
 * Class ClassPost
 */
final class ClassPost {

	/**
	 * Register the CPT and post statuses.
	 * Hooked to 'init'.
	 */
	public function register(): void {
		$this->register_post_type();
		$this->register_post_statuses();
	}

	// -------------------------------------------------------------------------
	// Post Type
	// -------------------------------------------------------------------------

	private function register_post_type(): void {
		$labels = [
			'name'                  => __( 'Classes',                    'dish-events' ),
			'singular_name'         => __( 'Class',                      'dish-events' ),
			'add_new'               => __( 'Add New',                    'dish-events' ),
			'add_new_item'          => __( 'Add New Class',              'dish-events' ),
			'edit_item'             => __( 'Edit Class',                 'dish-events' ),
			'new_item'              => __( 'New Class',                  'dish-events' ),
			'search_items'          => __( 'Search Classes',             'dish-events' ),
			'not_found'             => __( 'No classes found.',          'dish-events' ),
			'not_found_in_trash'    => __( 'No classes found in Trash.', 'dish-events' ),
			'all_items'             => __( 'All Classes',                'dish-events' ),
			'attributes'            => __( 'Class Attributes',           'dish-events' ),
			'insert_into_item'      => __( 'Insert into class',          'dish-events' ),
			'uploaded_to_this_item' => __( 'Uploaded to this class',     'dish-events' ),
			'menu_name'             => __( 'Dish Events',                'dish-events' ),
			'name_admin_bar'        => __( 'Class',                      'dish-events' ),
		];

		register_post_type( 'dish_class', [
			'labels'             => $labels,
			'description'        => __( 'Dated class instances. Non-public — no frontend URL. Bookings reference these records.', 'dish-events' ),
			'public'             => false,  // Instances have no frontend URL.
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,   // Top-level menu item; other CPTs anchor under it.
			'show_in_nav_menus'  => false,
			'show_in_rest'       => false,  // No block editor.
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => [ 'title', 'editor', 'revisions' ],
			'rewrite'            => false,
			'capability_type'    => 'post',
			'menu_icon'          => 'dashicons-calendar-alt',
			'menu_position'      => 30,
			'query_var'          => false,
			'delete_with_user'   => false,
		] );
	}

	// -------------------------------------------------------------------------
	// Post Statuses
	// -------------------------------------------------------------------------

	private function register_post_statuses(): void {

		register_post_status( 'dish_expired', [
			'label'                     => _x( 'Expired', 'post status', 'dish-events' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: count */
			'label_count'               => _n_noop(
				'Expired <span class="count">(%s)</span>',
				'Expired <span class="count">(%s)</span>',
				'dish-events'
			),
		] );

		register_post_status( 'dish_cancelled', [
			'label'                     => _x( 'Cancelled', 'post status', 'dish-events' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: count */
			'label_count'               => _n_noop(
				'Cancelled <span class="count">(%s)</span>',
				'Cancelled <span class="count">(%s)</span>',
				'dish-events'
			),
		] );
	}
}
