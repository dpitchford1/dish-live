<?php
/**
 * Admin module hook registrar.
 *
 * Wires all admin-only modules to the Loader. Handles the post-activation
 * redirect to the Settings page.
 *
 * Only instantiated when is_admin() is true (enforced in Plugin::wire_hooks()).
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Core\Loader;

/**
 * Class Admin
 */
final class Admin {

	private Loader $loader;

	public function __construct( Loader $loader ) {
		$this->loader = $loader;
	}

	/**
	 * Register all admin hooks via the Loader.
	 * Called from Plugin::wire_hooks().
	 */
	public function register_hooks(): void {

		// Settings page.
		$settings = new Settings();
		$this->loader->add_action( 'admin_menu',  $settings, 'add_pages' );
		$this->loader->add_action( 'admin_init',  $settings, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $settings, 'enqueue_assets' );

		// Class list table columns, filters, and bulk actions.
		$columns = new ClassColumns();
		$columns->register_hooks( $this->loader );

		// Main tabbed meta box — primary edit interface for dish_class.
		$class_metabox = new ClassMetaBox();
		$this->loader->add_action( 'add_meta_boxes',       $class_metabox, 'register' );
		$this->loader->add_action( 'save_post_dish_class', $class_metabox, 'save', 10, 2 );

		// Summary sidebar meta box.
		$summary_mb = new SummaryMetaBox();
		$this->loader->add_action( 'add_meta_boxes', $summary_mb, 'register' );

		// Class Details meta box (What to Bring, Requirements, Included, Dietary).
		$details_mb = new ClassDetailsMetaBox();
		$this->loader->add_action( 'add_meta_boxes',      $details_mb, 'register' );
		$this->loader->add_action( 'save_post_dish_class', $details_mb, 'save', 10, 2 );

		// Ticketing admin — Ticket Types (registers the "Ticketing" parent menu item).
		$ticket_type_admin = new TicketTypeAdmin();
		$this->loader->add_action( 'admin_menu', $ticket_type_admin, 'add_pages' );
		$this->loader->add_action( 'admin_init', $ticket_type_admin, 'handle_request' );

		// Class Template meta box, columns, and permalink handling (Phase 2.5).
		$class_template_admin = new ClassTemplateAdmin();
		$class_template_admin->register_hooks( $this->loader );

		// dish_format CPT columns — adds Ticket Types count to the Formats list table.
		$format_columns = new FormatColumns();
		$format_columns->register_hooks( $this->loader );

		// dish_format colour picker meta box (Phase 8).
		$format_meta_box = new FormatMetaBox();
		$format_meta_box->register_hooks( $this->loader );

		// dish_chef CPT columns — thumbnail and role.
		$chef_columns = new ChefColumns();
		$chef_columns->register_hooks( $this->loader );

		// Chef meta box — role, social links (Instagram/LinkedIn/TikTok), gallery.
		$chef_metabox = new ChefMetaBox();
		$this->loader->add_action( 'add_meta_boxes',        $chef_metabox, 'register' );
		$this->loader->add_action( 'save_post_dish_chef',   $chef_metabox, 'save',   10, 2 );
		$this->loader->add_action( 'admin_enqueue_scripts', $chef_metabox, 'enqueue_assets' );

		// Menu meta box — menu items, dietary flags, friendly-for (on dish_class_template).
		$menu_metabox = new MenuMetaBox();
		$this->loader->add_action( 'add_meta_boxes',                    $menu_metabox, 'register' );
		$this->loader->add_action( 'save_post_dish_class_template',    $menu_metabox, 'save',   10, 2 );

		// Class Template grouped list page — replaces the flat CPT list in the nav.
		$template_list = new ClassTemplateListAdmin();
		$this->loader->add_action( 'admin_menu', $template_list, 'add_pages' );

		// Booking meta boxes — details, actions, notes.
		$booking_metabox = new BookingMetaBox();
		$this->loader->add_action( 'add_meta_boxes',           $booking_metabox, 'register' );
		$this->loader->add_action( 'save_post_dish_booking',   $booking_metabox, 'save',   10, 2 );

		// Booking list table columns and status filter.
		$booking_columns = new BookingColumns();
		$booking_columns->register_hooks( $this->loader );

		// Reports page — Bookings, Revenue, Attendees tabs + CSV export.
		$reports = new Reports();
		$reports->register_hooks( $this->loader );

		// Documentation viewer — Dish Events → Documentation.
		DishDocs::init();

		// Reorder the submenu at late priority, after all items are registered.
		$this->loader->add_action( 'admin_menu', $this, 'reorder_menu', 999 );

		// Admin assets (meta box JS + inline CSS).
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );

		// Activation redirect — fires once after plugin is activated.
		$this->loader->add_action( 'admin_init', $this, 'maybe_redirect_on_activation' );
	}

	// -------------------------------------------------------------------------
	// Menu order
	// -------------------------------------------------------------------------

	/**
	 * Reorder the Dish Events submenu to match the desired UX sequence.
	 *
	 * Hooked to admin_menu at priority 999 so all CPT and custom submenu
	 * items are already registered before we sort them.
	 */
	public function reorder_menu(): void {
		global $submenu;

		$parent = 'edit.php?post_type=dish_class';

		if ( empty( $submenu[ $parent ] ) ) {
			return;
		}

		$desired_order = [
			'edit.php?post_type=dish_class',           // All Classes
			'post-new.php?post_type=dish_class',       // Add New
			'edit.php?post_type=dish_format',          // Formats
			ClassTemplateListAdmin::PAGE_SLUG,         // Class Templates (grouped)
			'dish-ticket-types',                       // Ticketing
			'edit.php?post_type=dish_chef',            // Chefs
			'edit.php?post_type=dish_booking',         // Bookings
			'dish-events-reports',                     // Reporting
			'dish-events-docs',                        // Documentation
			'dish-events-settings',                    // Settings
		];

		// Index existing items by slug (index 2).
		$indexed = [];
		foreach ( $submenu[ $parent ] as $item ) {
			$indexed[ $item[2] ] = $item;
		}

		$sorted = [];
		foreach ( $desired_order as $slug ) {
			if ( isset( $indexed[ $slug ] ) ) {
				$sorted[] = $indexed[ $slug ];
				unset( $indexed[ $slug ] );
			}
		}

		// Append anything not in the list (future-proofing).
		foreach ( $indexed as $item ) {
			$sorted[] = $item;
		}

		$submenu[ $parent ] = $sorted;
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue admin JS and inline CSS on dish_class edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen ) return;
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php', 'edit.php' ], true ) ) return;

		$post_types = [ 'dish_class', 'dish_chef', 'dish_booking', 'dish_class_template' ];
		if ( ! in_array( $screen->post_type, $post_types, true ) ) return;

		// dish_class-only scripts.
		if ( $screen->post_type === 'dish_class' ) {

			wp_enqueue_script(
				'dish-admin',
				DISH_EVENTS_URL . 'assets/js/dish-admin.js',
				[],
				DISH_EVENTS_VERSION,
				true
			);
		}

		// Stylesheet — shared across all three dish post types.
		wp_enqueue_style(
			'dish-admin',
			DISH_EVENTS_URL . 'assets/css/dish-admin.css',
			[],
			DISH_EVENTS_VERSION
		);
	}

	// -------------------------------------------------------------------------
	// Activation redirect
	// -------------------------------------------------------------------------

	/**
	 * Redirect to the Settings page once, immediately after activation.
	 * The flag is set by Activator::activate() and cleared here.
	 */
	public function maybe_redirect_on_activation(): void {
		if ( ! get_option( 'dish_activation_redirect' ) ) {
			return;
		}

		delete_option( 'dish_activation_redirect' );

		// Don't redirect during bulk plugin activation.
		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=dish_class&page=dish-events-settings' ) );
		exit;
	}
}
