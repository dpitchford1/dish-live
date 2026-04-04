<?php
/**
 * Registers the dish_booking Custom Post Type and booking-specific post statuses.
 *
 * Bookings are admin-only records — no public URL, no "Add New" button.
 * They are created programmatically by BookingManager::create() in Phase 9.
 *
 * Post statuses registered here:
 *   - dish_pending   : checkout started, payment not yet confirmed
 *   - dish_completed : payment confirmed
 *   - dish_failed    : payment failed
 *   - dish_refunded  : manually marked refunded by admin
 *   - dish_cancelled : cancelled (by admin, or timer expiry, or future self-service)
 *
 * Note: dish_cancelled is also registered by ClassPost for use on dish_class.
 * WordPress handles duplicate register_post_status() calls gracefully.
 *
 * @package Dish\Events\CPT
 */

declare( strict_types=1 );

namespace Dish\Events\CPT;

/**
 * Class BookingPost
 */
final class BookingPost {

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
			'name'               => __( 'Bookings',                   'dish-events' ),
			'singular_name'      => __( 'Booking',                    'dish-events' ),
			'edit_item'          => __( 'View Booking',               'dish-events' ),
			'search_items'       => __( 'Search Bookings',            'dish-events' ),
			'not_found'          => __( 'No bookings found.',         'dish-events' ),
			'not_found_in_trash' => __( 'No bookings in Trash.',      'dish-events' ),
			'all_items'          => __( 'Bookings',                   'dish-events' ),
			'menu_name'          => __( 'Bookings',                   'dish-events' ),
			'name_admin_bar'     => __( 'Booking',                    'dish-events' ),
		];

		register_post_type( 'dish_booking', [
			'labels'              => $labels,
			'description'         => __( 'Class bookings and purchase records.', 'dish-events' ),
			'public'              => false,   // No frontend URL.
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=dish_class', // Under Dish Events menu.
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => [ 'title' ],
			'capability_type'     => 'post',
			// Prevent "Add New" button in admin — bookings are system-created only.
			'capabilities'        => [
				'create_posts' => 'do_not_allow',
			],
			'map_meta_cap'        => true,
			'query_var'           => false,
			'rewrite'             => false,
			'delete_with_user'    => false,
		] );
	}

	// -------------------------------------------------------------------------
	// Post Statuses
	// -------------------------------------------------------------------------

	private function register_post_statuses(): void {

		register_post_status( 'dish_pending', [
			'label'                     => _x( 'Pending', 'booking status', 'dish-events' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: count */
			'label_count'               => _n_noop(
				'Pending <span class="count">(%s)</span>',
				'Pending <span class="count">(%s)</span>',
				'dish-events'
			),
		] );

		register_post_status( 'dish_completed', [
			'label'                     => _x( 'Completed', 'booking status', 'dish-events' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: count */
			'label_count'               => _n_noop(
				'Completed <span class="count">(%s)</span>',
				'Completed <span class="count">(%s)</span>',
				'dish-events'
			),
		] );

		register_post_status( 'dish_failed', [
			'label'                     => _x( 'Failed', 'booking status', 'dish-events' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: count */
			'label_count'               => _n_noop(
				'Failed <span class="count">(%s)</span>',
				'Failed <span class="count">(%s)</span>',
				'dish-events'
			),
		] );

		register_post_status( 'dish_refunded', [
			'label'                     => _x( 'Refunded', 'booking status', 'dish-events' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: count */
			'label_count'               => _n_noop(
				'Refunded <span class="count">(%s)</span>',
				'Refunded <span class="count">(%s)</span>',
				'dish-events'
			),
		] );

		// dish_cancelled is also registered by ClassPost for use on dish_class.
		// WordPress silently ignores duplicate status registration — no conflict.
		register_post_status( 'dish_cancelled', [
			'label'                     => _x( 'Cancelled', 'booking status', 'dish-events' ),
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
